<?php

namespace mindtwo\TwoTility\Testing;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use mindtwo\TwoTility\Helper\Hookable;
use mindtwo\TwoTility\Testing\Api\ApiResponse;
use mindtwo\TwoTility\Testing\Api\DefinitionFaker;
use mindtwo\TwoTility\Testing\Api\RouteMatch;
use mindtwo\TwoTility\Testing\Api\RouteResolver;
use mindtwo\TwoTility\Testing\Api\Stores\FakeArrayStore;
use mindtwo\TwoTility\Testing\Contracts\SpecParserInterface;

class ApiFake
{
    /** @use Hookable<'init'|'create'|'creating'|'created'|'showing'|'updating'|'updated'|'deleting'|'deleted'> */
    use Hookable;

    protected FakeArrayStore $store;

    protected RouteResolver $routeResolver;

    /** @var array<string, array<string, bool>> */
    protected array $authRequired = [];

    /**
     * The path definitions for the fake API.
     *
     * This is used to store the definitions for each path, which can be used to generate
     * items based on the provided definitions.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $collectionFakerDefinitions = [];

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
     * Temporary response overrides with call counts.
     *
     * Structure: [path][method] = ['response' => mixed, 'count' => int, 'used' => int]
     *
     * @var array<string, array<string, array{response: mixed, count: int, used: int}>>
     */
    protected array $temporaryResponses = [];

    /**
     * Create a new instance of the ApiFake class.
     *
     * @param  string  $baseUrl  The base URL for the fake API.
     */
    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->store = app(FakeArrayStore::class);
        $this->routeResolver = new RouteResolver;
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

                // Check for temporary response overrides first (before route matching)
                $temporaryResponse = $this->checkTemporaryResponse($path, $method);
                if ($temporaryResponse !== null) {
                    $this->lastResponse = $temporaryResponse;

                    return $this->lastResponse;
                }

                // Get the route match to pass to handlers
                $match = $this->routeResolver->matchRoute($path, $method);

                // If no operation is matched, return a 404 response
                if (! $match) {
                    return Http::response(['error' => 'No route matched'], 404);
                }

                // Check if the user is authorized for this operation
                if (! $this->isAuthorized($path, $method, $request)) {
                    return Http::response(['error' => 'Unauthorized'], 401);
                }

                // Match the method and handle the request accordingly
                $this->lastResponse = $this->serveOperation($path, $method, $match, $request);

                return $this->lastResponse;
            }]);

        $this->runHooks('init', $this);
    }

    /**
     * Serve the operation based on the path, method, and request.
     */
    protected function serveOperation(string $path, string $method, RouteMatch $match, Request $request): PromiseInterface
    {
        // For nested operations like /collection/{resource}/operation, try custom handler first
        $operationName = $match->operation();

        // Check if a custom handler method exists for the matched operation
        if (method_exists($this, $match->handlerMethod())) {
            // Call the custom handler method
            $handlerMethodName = $match->handlerMethod();
            $result = $this->{$handlerMethodName}($request, $match, $path, $method);

            return $this->processHandlerResult($result, $path, $method, $operationName);
        }

        // If no custom handler, use the default handlers based on operation type
        $result = match ($operationName) {
            'list' => $this->handleList($match, $request),
            'show' => $this->handleShow($match, $request),
            'create' => $this->handleCreate($match, $request),
            'update' => $this->handleUpdate($match, $request),
            'delete' => $this->handleDelete($match, $request),
            default => ApiResponse::withStatus(['error' => 'Unsupported method'], 405),
        };

        return $this->processHandlerResult($result, $path, $method, $operationName);
    }

    /**
     * Process the result from a handler and return a PromiseInterface.
     */
    protected function processHandlerResult(mixed $result, string $path, string $method, string $operation): PromiseInterface
    {
        // Handle ApiResponse objects
        if ($result instanceof ApiResponse) {
            $formatted = $this->formatResponse($result->getData(), $path, $method, $operation);

            return Http::response($formatted, $result->getStatus(), $result->getHeaders());
        }

        // Handle PromiseInterface objects (already formatted responses)
        if ($result instanceof PromiseInterface) {
            return $result;
        }

        // Handle legacy mixed responses (backward compatibility)
        $formatted = $this->formatResponse($result, $path, $method, $operation);

        // Try to infer status code from response data
        $statusCode = $this->inferStatusCode($result, $operation);

        return Http::response($formatted, $statusCode);
    }

    /**
     * Infer the appropriate status code from response data for backward compatibility.
     */
    protected function inferStatusCode(mixed $result, string $operation): int
    {
        // If result is null (common for delete operations), return 204 No Content
        if ($result === null) {
            return 204;
        }

        // If result is an array and contains error information, return appropriate error status
        if (is_array($result)) {
            if (isset($result['error'])) {
                return match ($result['error']) {
                    'Not found' => 404,
                    'Unauthorized' => 401,
                    'Forbidden' => 403,
                    'Bad request' => 400,
                    'Unprocessable entity' => 422,
                    default => 400, // Default to bad request for unknown errors
                };
            }
        }

        // For create operations, return 201 Created
        if ($operation === 'create') {
            return 201;
        }

        // Default to 200 OK for successful operations
        return 200;
    }

    /**
     * Handle POST requests to create a new item in the store.
     *
     * @param  RouteMatch  $routeMatch  The route match containing path, method, and parameters.
     * @param  Request  $request  The request object containing the data to create.
     */
    protected function handleCreate(RouteMatch $routeMatch, Request $request): mixed
    {
        $id = $this->generateId($routeMatch->path(), $request);
        $scope = $this->resolveScopeKey($request);

        // If the scope is a string, convert it to a closure that returns the scope
        $item = array_merge($request->data(), ['id' => $id]);

        // Run hooks for creating the item
        $this->runHooks('creating', $item, $routeMatch->path(), $request);

        $this->store->add($routeMatch->collection(), $scope, $id, $item);

        // Run hooks for created item
        $this->runHooks('created', $item, $routeMatch->path(), $request);

        return $item;
    }

    /**
     * Handle GET requests to retrieve a collection from the store.
     *
     * @param  RouteMatch  $routeMatch  The route match containing path, method, and parameters.
     * @param  Request  $request  The request object.
     * @return mixed The response data.
     */
    protected function handleList(RouteMatch $routeMatch, Request $request): mixed
    {
        // Resolve the scope key based on the request
        $scope = $this->resolveScopeKey($request);

        // If there are no items in the collection for the scope, return an empty array
        if (! $this->store->has($routeMatch->collection(), $scope)) {
            return [];
        }

        // Get the list of items in the collection
        $list = array_values($this->store->get($routeMatch->collection(), $scope));

        return $list;
    }

    /**
     * Handle GET requests to retrieve an item from the store.
     *
     * @param  RouteMatch  $routeMatch  The route match containing path, method, and parameters.
     * @param  Request  $request  The request object.
     * @return mixed The response data.
     */
    protected function handleShow(RouteMatch $routeMatch, Request $request): mixed
    {
        $id = $routeMatch->getResourceId() ?? basename($routeMatch->path());
        $collection = $routeMatch->collection();

        // Resolve the scope key based on the request
        $scope = $this->resolveScopeKey($request);

        if (! $this->store->has($collection, $scope, $id)) {
            return ['error' => 'Not found'];
        }
        // Get the item from the store
        $item = $this->store->get($collection, $scope, $id);

        $this->runHooks('showing', $item, $routeMatch->path(), $request);

        return $item;
    }

    /**
     * Handle PUT/PATCH requests to update an item in the store.
     *
     * @param  RouteMatch  $routeMatch  The route match containing path, method, and parameters.
     * @param  Request  $request  The request object containing the data to update.
     */
    protected function handleUpdate(RouteMatch $routeMatch, Request $request): mixed
    {
        $id = $routeMatch->getResourceId() ?? basename($routeMatch->path());
        $collection = $routeMatch->collection();

        // Resolve the scope key based on the request
        $scope = $this->resolveScopeKey($request);

        if ($this->store->has($collection, $scope, $id)) {
            // after
            $item = $this->store->get($collection, $scope, $id);

            // Run hooks for updating the item
            $this->runHooks('updating', $item, $routeMatch->path(), $request);

            // Update the item with the new data
            $item = array_merge($item, $request->data());
            $this->store->put($collection, $scope, $id, $item);

            // Run hooks for updated item
            $this->runHooks('updated', $item, $routeMatch->path(), $request);

            return $item;
        } else {
            return ['error' => 'Not found'];
        }
    }

    /**
     * Handle DELETE requests to remove an item from the store.
     *
     * @param  RouteMatch  $routeMatch  The route match containing path, method, and parameters.
     * @param  Request  $request  The request object.
     */
    protected function handleDelete(RouteMatch $routeMatch, Request $request): mixed
    {
        $id = $routeMatch->getResourceId() ?? basename($routeMatch->path());
        $collection = $routeMatch->collection();
        $scope = $this->resolveScopeKey($request);

        if ($this->store->has($collection, $scope, $id)) {
            // Run hooks for deleting the item
            $this->runHooks('deleting', $id, $collection, $request);

            $this->store->remove($collection, $scope, $id);

            // Run hooks for deleted item
            $this->runHooks('deleted', $id, $collection, $request);

            return true;
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

        $definition = $definition ?? $this->collectionFakerDefinitions[$path] ?? [];
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
        $this->collectionFakerDefinitions[$path] = $definition;
    }

    /**
     * Get the route resolver instance.
     */
    public function getRouteResolver(): RouteResolver
    {
        return $this->routeResolver;
    }

    /**
     * Match a route and return detailed information about the match.
     *
     * @param  string  $path  The path to match
     * @param  string  $method  The HTTP method
     */
    public function matchRoute(string $path, string $method): ?\mindtwo\TwoTility\Testing\Api\RouteMatch
    {
        return $this->routeResolver->matchRoute($path, $method);
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
     * @param  array<string, array{faker?: array<string, mixed>,list?: array{method: string, collection: string, authRequired: bool, responses: array<string, mixed>},show?: array{method: string, collection: string, path?: string, authRequired: bool, responses: array<string, mixed>},create?: array{method: string, collection: string, authRequired: bool, responses: array<string, mixed>},update?: array{method: string, collection: string, authRequired: bool, responses: array<string, mixed>}, delete?: array{method: string, collection: string, authRequired: bool, responses: array<string, mixed>}}>  $parsedPaths
     * @param  array<string, array<string, mixed>>  $fakerDefinitions
     * @param  array<string, array<string, bool>>  $authRequirements
     */
    public function bootFromParsed(
        array $parsedPaths = [],
        array $fakerDefinitions = [],
        array $authRequirements = [],
    ): self {
        // Initialize the store with parsed paths
        $this->collectionFakerDefinitions = $fakerDefinitions;
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
                $collectionName = $operation['collection'];

                // Register the operation matcher for this path
                $this->routeResolver->registerOperationMatcher(
                    $op,
                    $operationPath,
                    $method,
                    $collectionName,
                    $operation['basePath'] ?? null
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

    /**
     * Add a temporary response override that will be returned for the specified path and method.
     * The response will be returned for the specified number of calls, then removed.
     *
     * @param  string  $path  The path to override (e.g., '/users', '/users/123').
     * @param  string  $method  The HTTP method to override (e.g., 'GET', 'POST').
     * @param  mixed  $response  The response data to return.
     * @param  int  $count  The number of times to return this response (default: 1).
     * @param  int  $statusCode  The HTTP status code to return (default: 200).
     * @param  array<string, string>  $headers  Additional headers to include.
     */
    public function addTemporaryResponse(string $path, string $method, mixed $response, int $count = 1, int $statusCode = 200, array $headers = []): self
    {
        $normalizedPath = rtrim($path, '/') ?: '/';
        $normalizedMethod = strtoupper($method);

        $this->temporaryResponses[$normalizedPath][$normalizedMethod] = [
            'response' => $response,
            'count' => $count,
            'used' => 0,
            'statusCode' => $statusCode,
            'headers' => $headers,
        ];

        return $this;
    }

    /**
     * Check if there's a temporary response override for the given path and method.
     * If found and not exhausted, increments usage count and returns the response.
     * If exhausted, removes the override and returns null.
     *
     * @param  string  $path  The request path.
     * @param  string  $method  The HTTP method.
     * @return PromiseInterface|null The temporary response or null if none available.
     */
    protected function checkTemporaryResponse(string $path, string $method): ?PromiseInterface
    {
        $normalizedPath = rtrim($path, '/') ?: '/';
        $normalizedMethod = strtoupper($method);

        // Check for exact path match first
        if (isset($this->temporaryResponses[$normalizedPath][$normalizedMethod])) {
            $override = &$this->temporaryResponses[$normalizedPath][$normalizedMethod];

            if ($override['used'] < $override['count']) {
                $override['used']++;

                // Remove if exhausted
                if ($override['used'] >= $override['count']) {
                    unset($this->temporaryResponses[$normalizedPath][$normalizedMethod]);
                    if (empty($this->temporaryResponses[$normalizedPath])) {
                        unset($this->temporaryResponses[$normalizedPath]);
                    }
                }

                return Http::response($override['response'], $override['statusCode'], $override['headers']);
            }
        }

        // Check for wildcard patterns (e.g., /users/* matches /users/123)
        foreach ($this->temporaryResponses as $overridePath => $methods) {
            if (str_ends_with($overridePath, '/*')) {
                $basePath = rtrim($overridePath, '/*');
                if (str_starts_with($normalizedPath, $basePath)) {
                    if (isset($methods[$normalizedMethod])) {
                        $override = &$this->temporaryResponses[$overridePath][$normalizedMethod];

                        if ($override['used'] < $override['count']) {
                            $override['used']++;

                            // Remove if exhausted
                            if ($override['used'] >= $override['count']) {
                                unset($this->temporaryResponses[$overridePath][$normalizedMethod]);
                                if (empty($this->temporaryResponses[$overridePath])) {
                                    unset($this->temporaryResponses[$overridePath]);
                                }
                            }

                            return Http::response($override['response'], $override['statusCode'], $override['headers']);
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Clear all temporary response overrides.
     */
    public function clearTemporaryResponses(): self
    {
        $this->temporaryResponses = [];

        return $this;
    }

    /**
     * Get the current temporary response overrides.
     *
     * @return array<string, array<string, array{response: mixed, count: int, used: int, statusCode: int, headers: array<string, string>}>>
     */
    public function getTemporaryResponses(): array
    {
        return $this->temporaryResponses;
    }

    /**
     * Get the store instance used by the API fake.
     */
    public function store(): FakeArrayStore
    {
        return $this->store;
    }
}
