<?php

namespace mindtwo\TwoTility\Testing;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use mindtwo\TwoTility\Testing\Api\DefinitionFaker;
use mindtwo\TwoTility\Testing\Contracts\SpecParserInterface;

class ApiFake
{
    /**
     * The memory store for the fake API.
     * [path][scope][id => data]
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected array $store = [];

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

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

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

                // TODO: serveOperation to direct to the correct handler
                // TODO: allow overrides for the handlers

                // return $this->serveOperation($path, $method, $operation, $request);
                // Match the method and handle the request accordingly
                match ($method) {
                    'POST' => $this->handleCreate($path, $request),
                    'GET' => $this->handleRead($path, $request),
                    'PUT', 'PATCH' => $this->handleUpdate($path, $request),
                    'DELETE' => $this->handleDelete($path, $request),
                    default => $this->lastResponse = Http::response(['error' => 'Unsupported method'], 405),
                };

                return $this->lastResponse;
            }]);
    }

    /**
     * Handle POST requests to create a new item in the store.
     *
     * @param  string  $path  The path of the request, which includes the collection and item ID.
     * @param  Request  $request  The request object containing the data to create.
     */
    protected function handleCreate(string $path, Request $request): void
    {
        $id = $this->generateId($path, $request);
        $scope = $this->resolveScopeKey($request);

        $item = array_merge($request->data(), ['id' => $id]);

        $this->store[$path][$scope][$id] = $item;
        $this->lastResponse = Http::response($item, 201);
    }

    /**
     * Handle GET requests to retrieve an item or collection from the store.
     *
     * @param  string  $path  The path of the request, which includes the collection and item ID.
     */
    protected function handleRead(string $path, Request $request): void
    {
        $id = basename($path);
        $collectionPath = dirname($path);

        // Resolve the scope key based on the request
        $scope = $this->resolveScopeKey($request);

        if (isset($this->store[$collectionPath][$scope][$id])) {
            $entry = $this->store[$collectionPath][$scope][$id];

            $this->lastResponse = Http::response($entry);
        } elseif (isset($this->store[$path][$scope])) {
            $list = $this->store[$path][$scope];

            $this->lastResponse = Http::response(array_values($list));
        } else {
            $this->lastResponse = Http::response(['error' => 'Not found'], 404);
        }
    }

    /**
     * Handle PUT/PATCH requests to update an item in the store.
     *
     * @param  string  $path  The path of the request, which includes the collection and item ID.
     * @param  Request  $request  The request object containing the data to update.
     */
    protected function handleUpdate(string $path, Request $request): void
    {
        $id = basename($path);
        $collectionPath = dirname($path);

        // Resolve the scope key based on the request
        $scope = $this->resolveScopeKey($request);

        if (isset($this->store[$collectionPath][$scope][$id])) {
            $this->store[$collectionPath][$scope][$id] = array_merge(
                $this->store[$collectionPath][$scope][$id],
                $request->data()
            );

            $this->lastResponse = Http::response($this->store[$collectionPath][$id]);
        } else {
            $this->lastResponse = Http::response(['error' => 'Not found'], 404);
        }
    }

    /**
     * Handle DELETE requests to remove an item from the store.
     *
     * @param  string  $path  The path of the request, which includes the collection and item ID.
     */
    protected function handleDelete(string $path, Request $request): void
    {
        $key = md5($path); // fallback when there's no request body

        $scope = $this->resolveScopeKey($request);

        if (isset($this->store[$path][$scope][$key])) {
            unset($this->store[$path][$scope][$key]);
            $this->lastResponse = Http::response(null, 204);
        } else {
            $this->lastResponse = Http::response(['error' => 'Not found'], 404);
        }
    }

    /**
     * Reset the store to an empty state.
     */
    public function reset(): void
    {
        $this->store = [];
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
     * @param  string  $scope  The scope under which the items are created (e.g., 'anonymous', 'user-1').
     * @return array<string, mixed> The created item with an ID.
     */
    public function addItem(string $path, ?array $definition = null, array $overrides = [], string $scope = 'anonymous'): array
    {
        $faker = new DefinitionFaker;

        $definition = $definition ?? $this->pathFakerDefinitions[$path] ?? [];
        if (empty($definition)) {
            throw new \InvalidArgumentException("No definition found for path: {$path}");
        }

        $item = array_merge($faker->make($definition), $overrides);

        $id = $item['id'] ?? $this->generateId($path, null);
        $item['id'] = $id;

        $this->store[$path][$scope][$id] = $item;

        return $item;
    }

    /**
     * Add multiple items to the store based on a definition and optional overrides.
     *
     * @param  string  $path  The path of the request, which includes the collection.
     * @param  int  $count  The number of items to add.
     * @param  ?array<string, mixed>  $definition  The definition for the item to be created.
     * @param  array<string, mixed>  $overrides  Optional overrides for each item.
     * @param  string  $scope  The scope under which the items are created (e.g., 'anonymous', 'user-1').
     * @return array<array<string, mixed>> An array of created items.
     */
    public function addItems(string $path, int $count = 1, ?array $definition = null, array $overrides = [], string $scope = 'anonymous'): array
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
            '#\{id\}#',
            '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}',
            $key
        );

        $this->operationMatchers[] = [
            'operation' => $operation,
            'regex' => "#^{$regex}$#",
            'method' => strtoupper($method),
        ];
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
    public function setAuthResolver(\Closure $callback): void
    {
        $this->authResolver = $callback;
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
    public function setScopeResolver(\Closure $callback): void
    {
        $this->scopeResolver = $callback;
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
