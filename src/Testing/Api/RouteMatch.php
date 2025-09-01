<?php

namespace mindtwo\TwoTility\Testing\Api;

/**
 * Represents a successful route match with extracted parameters and operation details.
 */
class RouteMatch
{
    /**
     * @param  RouteOperation  $operation  The matched operation type.
     * @param  string  $path  The full matched path.
     * @param  string  $method  The HTTP method used for the request.
     * @param  string  $collectionName  The name of the collection being accessed.
     * @param  array<string, string>  $parameters  Extracted parameters from the path.
     * @param  string|null  $basePath  Optional base path for the API.
     */
    public function __construct(
        public readonly RouteOperation $operation,
        public readonly string $path,
        public readonly string $method,
        public readonly string $collectionName,
        public readonly array $parameters = [],
        public readonly ?string $basePath = null,
    ) {}

    /**
     * Get the matched operation type.
     */
    public function operation(): string
    {
        return $this->operation->operation();
    }

    /**
     * Get the HTTP method.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Get the full matched path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Get all extracted parameters.
     *
     * @return array<string, string>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get a specific parameter value.
     */
    public function parameter(string $name, ?string $default = null): ?string
    {
        return $this->parameters[$name] ?? $default;
    }

    /**
     * Check if a parameter exists.
     */
    public function has(string $name): bool
    {
        return isset($this->parameters[$name]);
    }

    /**
     * Get the resource ID (alias for 'id' parameter or 'resource' parameter for nested resources).
     */
    public function getResourceId(): ?string
    {
        return $this->parameter('resource') ?? $this->parameter('id');
    }

    /**
     * Get the collection name from the path.
     */
    public function collection(): string
    {
        return $this->collectionName;
    }

    /**
     * Get the underlying RouteOperation.
     */
    public function routeOperation(): RouteOperation
    {
        return $this->operation;
    }

    /**
     * Get the handler method name based on the operation and path.
     *
     * This generates a method name like `handleGetUsers` for GET requests to `/users`.
     */
    public function handlerMethod(): string
    {
        $operation = ucfirst($this->operation());
        $path = pascalCasePath($this->routeOperation()->pattern());

        return "handle{$path}{$operation}";
    }
}
