<?php

namespace mindtwo\TwoTility\Testing\Api;

class RouteResolver
{
    /** @var RouteOperation[] */
    protected array $operations = [];

    /**
     * Register a custom operation matcher.
     *
     * @param  string  $operation  The operation name (e.g., 'create', 'update').
     * @param  string  $pattern  The pattern to match the operation.
     * @param  string  $method  The HTTP method for this operation (e.g., 'POST', 'GET').
     * @param  string|null  $basePath  The base path for this operation (e.g., '/v1/users').
     */
    public function registerOperationMatcher(string $operation, string $pattern, string $method, ?string $basePath = null): void
    {
        if (! in_array($operation, ['list', 'show', 'create', 'update', 'delete'])) {
            throw new \InvalidArgumentException("Invalid operation: {$operation}");
        }

        // Convert {paramName} placeholders to capturing groups
        $regex = preg_replace_callback('/\{(\w+)\}/', function ($matches) {
            $paramName = $matches[1];

            // Use appropriate regex based on parameter name
            return match ($paramName) {
                'id' => '([0-9a-f\-]{36})', // UUID format
                'resource', 'operation' => '([a-zA-Z0-9_-]+)', // Alphanumeric with underscores and hyphens
                default => '([^/]+)', // Generic parameter - anything except forward slash
            };
        }, $pattern);

        $this->operations[] = new RouteOperation(
            operation: $operation,
            pattern: $pattern,
            regex: "#^{$regex}$#",
            method: strtoupper($method),
            basePath: $basePath ?? $this->extractBasePathFromPattern($pattern)
        );
    }

    /**
     * Register a nested resource operation for patterns like /collection/{resource}/operation.
     *
     * Example usage:
     * $resolver->registerNestedResourceOperation('users', 'profile', 'GET', 'show');
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
    public function resolveOperationFromPath(string $path, string $method): ?string
    {
        $match = $this->matchRoute($path, $method);

        return $match?->getOperation();
    }

    /**
     * Match a route and return a RouteMatch object with all details.
     *
     * @param  string  $path  The path of the request.
     * @param  string  $method  The HTTP method of the request (e.g., 'GET', 'POST').
     * @return ?RouteMatch The matched route or null if no match is found.
     */
    public function matchRoute(string $path, string $method): ?RouteMatch
    {
        foreach ($this->operations as $operation) {
            if ($operation->matches($path, $method)) {
                $parameters = $operation->extractParameters($path);

                return new RouteMatch(
                    operation: $operation,
                    path: $path,
                    method: strtoupper($method),
                    parameters: $parameters,
                    basePath: $operation->getBasePath()
                );
            }
        }

        return null;
    }

    /**
     * Extract the resource ID from a nested resource path (backward compatibility).
     *
     * @param  string  $path  The path to extract the resource ID from.
     * @return ?string The resource ID or null if not found.
     */
    public function extractResourceId(string $path): ?string
    {
        $parts = explode('/', trim($path, '/'));

        // For patterns like /collection/resource-id/operation
        if (count($parts) >= 3) {
            return $parts[1];
        }

        return null;
    }

    /**
     * Extract the operation name from a nested resource path (backward compatibility).
     *
     * @param  string  $path  The path to extract the operation from.
     * @return ?string The operation name or null if not found.
     */
    public function extractOperationName(string $path): ?string
    {
        $parts = explode('/', trim($path, '/'));

        // For patterns like /collection/resource-id/operation
        if (count($parts) >= 3) {
            return $parts[2];
        }

        return null;
    }

    /**
     * Extract the collection name from a path (backward compatibility).
     *
     * @param  string  $path  The path to extract the collection from.
     * @return ?string The collection name or null if not found.
     */
    public function extractCollectionName(string $path): ?string
    {
        $parts = explode('/', trim($path, '/'));

        if (count($parts) >= 1) {
            return $parts[0];
        }

        return null;
    }

    /**
     * Get all registered operations.
     *
     * @return RouteOperation[]
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Get all registered operation matchers (backward compatibility).
     *
     * @return array<string, array{operation: string, regex: string, method: string, pattern: string}>
     */
    public function getOperationMatchers(): array
    {
        return array_map(fn (RouteOperation $op) => $op->toArray(), $this->operations);
    }

    /**
     * Clear all registered operation matchers.
     */
    public function clearOperationMatchers(): void
    {
        $this->operations = [];
    }

    /**
     * Extract the base path from a route pattern.
     * For patterns like "/v1/users/{id}", returns "/v1/users"
     * For patterns like "/users", returns "/users"
     */
    protected function extractBasePathFromPattern(string $pattern): string
    {
        // Remove parameters like {id}, {resource}, etc.
        $basePath = preg_replace('/\/\{[^}]+\}.*$/', '', $pattern);
        
        // If no parameters were found, return the entire pattern
        if ($basePath === $pattern) {
            return $pattern;
        }
        
        return $basePath;
    }
}
