<?php

namespace mindtwo\TwoTility\Testing;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use mindtwo\TwoTility\Helper\Hookable;
use mindtwo\TwoTility\Testing\Api\DefinitionFaker;
use mindtwo\TwoTility\Testing\Api\Stores\FakeArrayStore;
use mindtwo\TwoTility\Testing\Contracts\SpecParserInterface;

class ApiFake
{
    /** @use Hookable<'init'|'create'|'creating'|'created'|'showing'|'updating'|'updated'|'deleting'|'deleted'> */
    use Hookable;

    protected FakeArrayStore $store;

    /** @var array<string, array<string, bool>> */
    protected array $authRequired = [];

    /**
     * The route patterns for dynamic path matching.
     *
     * This is used to match incoming requests to the defined API paths.
     * The keys are regex patterns, and the values are the original paths.
     *
     * @var array<string, string>
     */
    protected array $routePatterns = []; // [regex => originalPath]

    /** @var array<string, array{operation: string, regex: string, method: string}> */
    protected array $operationMatchers = [];

    /**
     * The path definitions for the fake API.
     *
     * This is used to store the definitions for each path, which can be used to generate
     * items based on the provided definitions.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $pathFakerDefinitions = [];

    /**
     * The last response returned by the fake API.
     */
    protected mixed $lastResponse;

    /**
     * The base URL for the API.
     */
    protected string $baseUrl;

    /**
     * The authentication resolver for the fake API.
     *
     * This is used to determine if a request is authorized based on the provided headers.
     * It can be customized to use different headers or logic for authentication.
     */
    protected ?\Closure $authResolver = null;

    /**
     * The scope resolver for the fake API.
     *
     * This is used to determine the scope key based on the request headers.
     * It can be customized to use different headers or logic for scoping.
     */
    protected ?\Closure $scopeResolver = null;

    /**
     * The response formatter for the fake API.
     *
     * This can be used to format the responses returned by the fake API.
     * It is set to null by default, meaning no custom formatting is applied.
     *
     * @var null|callable
     */
    protected $responseFormatter = null;

    /**
     * Create a new instance of the ApiFake class.
     *
     * @param  string  $baseUrl  The base URL for the fake API.
     */
    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->store = app(FakeArrayStore::class);
    }

    /**
     * Initialize the fake API with parsed paths, faker definitions, and auth requirements.
     */
    public function fake(): void
    {
        Http::fake([
            "{$this->baseUrl}/*" => function (Request $request) {
                $path = parse_url($request->url(), PHP_URL_PATH);
                $method = strtoupper($request->method());

                $operation = $this->resolveOperationFromPath($path, $method);

                // If no operation is matched, return a 404 response
                if (! $operation) {
                    return Http::response(['error' => 'No route matched'], 404);
                }

                // Check if the user is authorized for this operation
                if (! $this->isAuthorized($path, $method, $request)) {
                    return Http::response(['error' => 'Unauthorized'], 401);
                }

                // Match the method and handle the request accordingly
                $this->lastResponse = $this->serveOperation($path, $method, $operation, $request);

                return $this->lastResponse;
            }]);

        $this->runHooks('init', $this);
    }

    /**
     * Serve the operation based on the path, method, and request.
     */
    protected function serveOperation(string $path, string $method, string $operation, Request $request): PromiseInterface
    {
        // For nested operations like /collection/{resource}/operation, try custom handler first
        $operationName = $this->extractOperationName($path);
        $collectionName = $this->extractCollectionName($path);
        
        if ($operationName && $collectionName) {
            // Try method like getCollectionResourceOperation
            $nestedHandlerMethod = strtolower($method) . ucfirst($collectionName) . 'Resource' . ucfirst($operationName);
            
            if (method_exists($this, $nestedHandlerMethod)) {
                return $this->{$nestedHandlerMethod}($path, $request, $method);
            }
            
            // Try method like handleCustomOperation
            $customHandlerMethod = 'handle' . ucfirst($operationName);
            
            if (method_exists($this, $customHandlerMethod)) {
                return $this->{$customHandlerMethod}($path, $request, $method);
            }
        }

        // Get method handler name for standard operations
        $handlerMethod = strtolower($method).pascalCasePath($path).ucfirst($operation);

        if (method_exists($this, $handlerMethod)) {
            return $this->{$handlerMethod}($path, $request, $method);
        }

        $result = match ($operation) {
            'list' => $this->handleList($path, $request, $method),
            'show' => $this->handleShow($path, $request, $method),
            'create' => $this->handleCreate($path, $request, $method),
            'update' => $this->handleUpdate($path, $request, $method),
            'delete' => $this->handleDelete($path, $request, $method),
            default => Http::response(['error' => 'Unsupported method'], 405),
        };

        $formatted = $this->formatResponse($result, $path, $method, $operation);

        return Http::response($formatted, 200);

    }

    /**
     * Handle POST requests to create a new item in the store.
     *
     * @param  string  $path  The path of the request, which includes the collection and item ID.
     * @param  Request  $request  The request object containing the data to create.
     * @param  string  $method  The HTTP method of the request (e.g., 'POST').
     */
    protected function handleCreate(string $path, Request $request, string $method): mixed
    {
        $id = $this->generateId($path, $request);
        $scope = $this->resolveScopeKey($request);

        // If the scope is a string, convert it to a closure that returns the scope
        $item = array_merge($request->data(), ['id' => $id]);

        // Run hooks for creating the item
        $this->runHooks('creating', $item, $path, $request);

        $this->store->add($path, $scope, $id, $item);

        // Run hooks for created item
        $this->runHooks('created', $item, $path, $request);

        return $item;
    }

    /**
     * Handle GET requests to retrieve a collection from the store.
     *
     * @param  string  $path  The path of the request, which includes the collection and item ID.
     * @param  Request  $request  The request object.
     * @param  string  $method  The HTTP method of the request (e.g, 'GET').
     * @return mixed The response data.
     */
    protected function handleList(string $path, Request $request, string $method): mixed
    {
        // Resolve the scope key based on the request
        $scope = $this->resolveScopeKey($request);

        if (! $this->store->has($path, $scope)) {
            return [];
        }

        // Get the list of items in the collection
        $list = array_values($this->store->get($path, $scope));

        return $list;
    }

    /**
     * Handle GET requests to retrieve an item from the store.
     *
     * @param  string  $path  The path of the request, which includes the collection and item ID.
     * @param  Request  $request  The request object.
     * @param  string  $method  The HTTP method of the request (e.g, 'GET').
     * @return mixed The response data.
     */
    protected function handleShow(string $path, Request $request, string $method): mixed
    {
        $id = basename($path);
        $collectionPath = dirname($path);

        // Resolve the scope key based on the request
        $scope = $this->resolveScopeKey($request);

        if (! $this->store->has($collectionPath, $scope, $id)) {
            return ['error' => 'Not found'];
        }
        // Get the item from the store
        $item = $this->store->get($collectionPath, $scope, $id);

        $this->runHooks('showing', $item, $path, $request);

        return $item;
    }

    /**
     * Handle PUT/PATCH requests to update an item in the store.
     *
     * @param  string  $path  The path of the request, which includes the collection and item ID.
     * @param  Request  $request  The request object containing the data to update.
     * @param  string  $method  The HTTP method of the request (e.g., 'PUT', 'PATCH').
     */
    protected function handleUpdate(string $path, Request $request, string $method): mixed
    {
        $id = basename($path);
        $collectionPath = dirname($path);

        // Resolve the scope key based on the request
        $scope = $this->resolveScopeKey($request);

        if ($this->store->has($collectionPath, $scope, $id)) {
            // after
            $item = $this->store->get($collectionPath, $scope, $id);

            // Run hooks for updating the item
            $this->runHooks('updating', $item, $path, $request);

            // Update the item with the new data
            $item = array_merge($item, $request->data());
            $this->store->put($collectionPath, $scope, $id, $item);

            // Run hooks for updated item
            $this->runHooks('updated', $item, $path, $request);

            return $item;
        } else {
            return ['error' => 'Not found'];
        }
    }

    /**
     * Handle DELETE requests to remove an item from the store.
     *
     * @param  string  $path  The path of the request, which includes the collection and item ID.
     * @param  Request  $request  The request object.
     * @param  string  $method  The HTTP method of the request (e.g. 'DELETE').
     */
    protected function handleDelete(string $path, Request $request, string $method): mixed
    {
        $id = basename($path);
        $collectionPath = dirname($path);
        $scope = $this->resolveScopeKey($request);

        if ($this->store->has($collectionPath, $scope, $id)) {
            // Run hooks for deleting the item
            $this->runHooks('deleting', $id, $collectionPath, $request);

            $this->store->remove($collectionPath, $scope, $id);

            // Run hooks for deleted item
            $this->runHooks('deleted', $id, $collectionPath, $request);

            return null;
        } else {
            return ['error' => 'Not found'];
        }
    }

    /**
     * Reset the store to an empty state.
     */
    public function clear(): void
    {
        $this->store->clear();
    }

    /**
     * Generate a unique ID for an item based on the path.
     */
    protected function generateId(string $path, ?Request $request): string
    {
        return Str::uuid()->toString();
    }

    /**
     * Add a new item to the store based on a definition and optional overrides.
     *
     * @param  string  $path  The path of the request, which includes the collection.
     * @param  ?array<string, mixed>  $definition  The definition for the item to be created.
     * @param  array<string, mixed>  $overrides  Optional overrides for the item.
     * @param  string|callable  $scope  The scope under which the items are created (e.g., 'anonymous', 'user-1').
     * @return array<string, mixed> The created item with an ID.
     */
    public function addItem(string $path, ?array $definition = null, array $overrides = [], string|callable $scope = 'anonymous'): array
    {
        $faker = new DefinitionFaker;

        $definition = $definition ?? $this->pathFakerDefinitions[$path] ?? [];
        if (empty($definition)) {
            throw new \InvalidArgumentException("No definition found for path: {$path}");
        }

        $item = array_merge($faker->make($definition), $overrides);

        $id = $item['id'] ?? $this->generateId($path, null);
        $item['id'] = $id;

        // If the scope is a string, convert it to a closure that returns the scope
        if (is_string($scope)) {
            $scope = function () use ($scope) {
                return $scope;
            };
        }
        $scope = call_user_func($scope);

        // Add the item to the store
        $this->store->add($path, $scope, $id, $item);

        return $item;
    }

    /**
     * Add multiple items to the store based on a definition and optional overrides.
     *
     * @param  string  $path  The path of the request, which includes the collection.
     * @param  int  $count  The number of items to add.
     * @param  ?array<string, mixed>  $definition  The definition for the item to be created.
     * @param  array<string, mixed>  $overrides  Optional overrides for each item.
     * @param  string|callable  $scope  The scope under which the items are created (e.g., 'anonymous', 'user-1').
     * @return array<array<string, mixed>> An array of created items.
     */
    public function addItems(string $path, int $count = 1, ?array $definition = null, array $overrides = [], string|callable $scope = 'anonymous'): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $this->addItem($path, $definition, $overrides, $scope);
        }

        return $items;
    }

    /**
     * Add a path definition to the fake API.
     *
     * This allows you to define how items should be generated for a specific path.
     *
     * @param  string  $path  The path for which the definition applies.
     * @param  array<string, mixed>  $definition  The definition for the items at this path.
     */
    public function addPathDefinition(string $path, array $definition): void
    {
        $this->pathFakerDefinitions[$path] = $definition;
    }

    /**
     * Register a custom operation matcher for the fake API.
     *
     * This allows you to define custom regex patterns for matching operations.
     *
     * @param  string  $operation  The operation name (e.g., 'create', 'update').
     * @param  string  $key  The regex pattern to match the operation.
     * @param  string  $method  The HTTP method for this operation (e.g., 'POST', 'GET').
     */
    public function registerOperationMatcher(string $operation, string $key, string $method): void
    {
        if (! in_array($operation, ['list', 'show', 'create', 'update', 'delete'])) {
            throw new \InvalidArgumentException("Invalid operation: {$operation}");
        }

        $regex = preg_replace(
            ['#\{id\}#', '#\{resource\}#', '#\{operation\}#'],
            ['[0-9a-f\-]{36}', '[a-zA-Z0-9_-]+', '[a-zA-Z0-9_-]+'],
            $key
        );

        $this->operationMatchers[] = [
            'operation' => $operation,
            'regex' => "#^{$regex}$#",
            'method' => strtoupper($method),
            'pattern' => $key,
        ];
    }

    /**
     * Register a nested resource operation for patterns like /collection/{resource}/operation.
     *
     * Example usage:
     * $apiFake->registerNestedResourceOperation('users', 'profile', 'GET', 'show');
     * This will handle GET /users/{userId}/profile
     *
     * @param  string  $collection  The base collection name
     * @param  string  $operation  The operation name (custom operation name)
     * @param  string  $method  The HTTP method for this operation
     * @param  string  $crudOperation  The CRUD operation to map to ('list', 'show', 'create', 'update', 'delete')
     */
    public function registerNestedResourceOperation(string $collection, string $operation, string $method, string $crudOperation = 'list'): void
    {
        $pattern = "/{$collection}/{resource}/{$operation}";
        $this->registerOperationMatcher($crudOperation, $pattern, $method);
    }

    /**
     * Resolve the operation from the path and method.
     *
     * This method checks the registered operation matchers to find a matching operation
     * for the given path and method.
     *
     * @param  string  $path  The path of the request.
     * @param  string  $method  The HTTP method of the request (e.g., 'GET', 'POST').
     * @return ?string The matched operation name or null if no match is found.
     */
    protected function resolveOperationFromPath(string $path, string $method): ?string
    {
        foreach ($this->operationMatchers as $match) {
            if (preg_match($match['regex'], $path) && $match['method'] === strtoupper($method)) {
                return $match['operation'];
            }
        }

        return null;
    }

    /**
     * Extract the resource ID from a nested resource path.
     *
     * @param  string  $path  The path to extract the resource ID from.
     * @return ?string The resource ID or null if not found.
     */
    protected function extractResourceId(string $path): ?string
    {
        $parts = explode('/', trim($path, '/'));
        
        // For patterns like /collection/resource-id/operation
        if (count($parts) >= 3) {
            return $parts[1];
        }
        
        return null;
    }

    /**
     * Extract the operation name from a nested resource path.
     *
     * @param  string  $path  The path to extract the operation from.
     * @return ?string The operation name or null if not found.
     */
    protected function extractOperationName(string $path): ?string
    {
        $parts = explode('/', trim($path, '/'));
        
        // For patterns like /collection/resource-id/operation
        if (count($parts) >= 3) {
            return $parts[2];
        }
        
        return null;
    }

    /**
     * Extract the collection name from a path.
     *
     * @param  string  $path  The path to extract the collection from.
     * @return ?string The collection name or null if not found.
     */
    protected function extractCollectionName(string $path): ?string
    {
        $parts = explode('/', trim($path, '/'));
        
        if (count($parts) >= 1) {
            return $parts[0];
        }
        
        return null;
    }

    /**
     * Format the response using a custom formatter.
     *
     * This allows you to define how the response should be formatted before returning it.
     *
     * @param  callable  $formatter  The callback function that formats the response.
     */
    public function formatResponseUsing(callable $formatter): self
    {
        $this->responseFormatter = $formatter;

        return $this;
    }

    /**
     * Format the response based on the provided formatter or return the raw response.
     */
    protected function formatResponse(mixed $response, string $path, string $method, string $operation = ''): mixed
    {
        if ($this->responseFormatter) {
            return call_user_func($this->responseFormatter, $response ?? [], $path, $method, $operation);
        }

        return $response;
    }

    /**
     * Register an authentication requirement for a specific path and method.
     *
     * @param  string  $path  The path for which the authentication requirement applies.
     * @param  string  $method  The HTTP method (e.g., 'GET', 'POST').
     * @param  bool  $required  Whether authentication is required for this path and method.
     */
    public function registerAuthRequirement(string $path, string $method, bool $required = true): void
    {
        $this->authRequired[$path][strtoupper($method)] = $required;
    }

    /**
     * Check if a request is authorized for a specific path and method.
     *
     * @param  string  $path  The path of the request.
     * @param  string  $method  The HTTP method of the request (e.g., 'GET', 'POST').
     * @param  ?Request  $request  The request object, if available.
     * @return bool True if the request is authorized, false otherwise.
     */
    public function isAuthorized(string $path, string $method, ?Request $request = null): bool
    {
        if ($this->authResolver) {
            return call_user_func($this->authResolver, $path, $method, $request);
        }

        $required = $this->authRequired[$path][strtoupper($method)] ?? false;

        return ! $required || ($request && $request->hasHeader('X-User-ID'));
    }

    /**
     * Set a custom authentication resolver for the fake API.
     *
     * This allows you to define custom logic for determining if a request is authorized.
     *
     * @param  \Closure  $callback  The callback function that takes the path, method, and request.
     */
    public function setAuthResolver(\Closure $callback): self
    {
        $this->authResolver = $callback;

        return $this;
    }

    /**
     * Resolve the scope key for the request.
     *
     * This method determines the scope key based on the request headers.
     * It prefers the 'X-User-ID' header or falls back to the 'Authorization' token hash.
     *
     * @param  Request  $request  The request object.
     * @return string The resolved scope key.
     */
    protected function resolveScopeKey(Request $request): string
    {
        if ($this->scopeResolver) {
            return call_user_func($this->scopeResolver, $request);
        }

        // Default scope resolver logic
        $header = $request->header('X-User-ID');
        if ($header) {
            return $header[0];
        }

        return 'anonymous';
    }

    /**
     * Set a custom scope resolver for the fake API.
     *
     * This allows you to define custom logic for determining the scope key based on request headers.
     *
     * @param  \Closure  $callback  The callback function that takes the request and returns a scope key.
     */
    public function setScopeResolver(\Closure $callback): self
    {
        $this->scopeResolver = $callback;

        return $this;
    }

    /**
     * @param  array<string, array{faker?: array<string, mixed>,list?: array{method: string, authRequired: bool, responses: array<string, mixed>},show?: array{method: string, path?: string, authRequired: bool, responses: array<string, mixed>},create?: array{method: string, authRequired: bool, responses: array<string, mixed>},update?: array{method: string, authRequired: bool, responses: array<string, mixed>}, delete?: array{method: string, authRequired: bool, responses: array<string, mixed>}}>  $parsedPaths
     * @param  array<string, array<string, mixed>>  $fakerDefinitions
     * @param  array<string, array<string, bool>>  $authRequirements
     */
    public function bootFromParsed(
        array $parsedPaths = [],
        array $fakerDefinitions = [],
        array $authRequirements = [],
    ): self {
        // Initialize the store with parsed paths
        $this->pathFakerDefinitions = $fakerDefinitions;
        $this->authRequired = $authRequirements;

        // Load fake responses from path data
        foreach ($parsedPaths as $path => $entry) {
            foreach (['list', 'show', 'create', 'update', 'delete'] as $op) {
                if (! isset($entry[$op])) {
                    continue;
                }

                $operation = $entry[$op];

                // Get method
                $method = strtoupper($operation['method']);
                $operationPath = $operation['path'] ?? $path;
                // Register the operation matcher for this path
                $this->registerOperationMatcher(
                    $op,
                    $operationPath,
                    $method
                );

                $this->registerAuthRequirement(
                    $path,
                    $method,
                    $authRequirements[$path][$method] ?? false
                );
            }
        }

        return $this;
    }

    /**
     * Boot the API fake from a YAML parser.
     *
     * This method initializes the API fake with paths, faker definitions, and auth requirements
     * parsed from a YAML file.
     *
     * @param  SpecParserInterface  $parser  The parser instance containing the parsed API spec.
     */
    public function bootFromParser(SpecParserInterface $parser): self
    {
        return $this->bootFromParsed(
            $parser->getPaths(),
            $parser->getFakerDefinitions(),
            $parser->getAuthRequirements(),
        );
    }
}
