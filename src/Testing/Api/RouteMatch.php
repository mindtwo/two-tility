<?php

namespace mindtwo\TwoTility\Testing\Api;

/**
 * Represents a successful route match with extracted parameters and operation details.
 */
class RouteMatch
{
    public function __construct(
        public readonly RouteOperation $operation,
        public readonly string $path,
        public readonly string $method,
        public readonly array $parameters = [],
        public readonly ?string $basePath = null
    ) {}

    /**
     * Get the matched operation type.
     */
    public function getOperation(): string
    {
        return $this->operation->getOperation();
    }

    /**
     * Get the HTTP method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the full matched path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get all extracted parameters.
     *
     * @return array<string, string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get a specific parameter value.
     */
    public function getParameter(string $name, ?string $default = null): ?string
    {
        return $this->parameters[$name] ?? $default;
    }

    /**
     * Check if a parameter exists.
     */
    public function hasParameter(string $name): bool
    {
        return isset($this->parameters[$name]);
    }

    /**
     * Get the resource ID (alias for 'id' parameter or 'resource' parameter for nested resources).
     */
    public function getResourceId(): ?string
    {
        return $this->getParameter('resource') ?? $this->getParameter('id');
    }

    /**
     * Get the collection name from the path.
     */
    public function getCollectionName(): ?string
    {
        $parts = explode('/', trim($this->path, '/'));
        
        if (count($parts) >= 1) {
            return $parts[0];
        }
        
        return null;
    }

    /**
     * Get the operation name from nested resource paths.
     */
    public function getOperationName(): ?string
    {
        $parts = explode('/', trim($this->path, '/'));
        
        // For patterns like /collection/resource-id/operation
        if (count($parts) >= 3) {
            return $parts[2];
        }
        
        return null;
    }

    /**
     * Check if this is a nested resource operation.
     */
    public function isNestedResource(): bool
    {
        return count(explode('/', trim($this->path, '/'))) >= 3;
    }

    /**
     * Get the collection path for store operations.
     * For nested routes like /users/{id}/profile, returns /users
     * For simple routes like /users/{id}, returns /users
     * If basePath is provided (e.g. /v1/users), returns the full base path
     */
    public function getCollectionPath(): string
    {
        // If we have basePath from the API spec, use it
        if ($this->basePath !== null) {
            return $this->basePath;
        }
        
        // Fallback to extracting from path (legacy behavior)
        $parts = explode('/', trim($this->path, '/'));
        
        if (count($parts) >= 1) {
            return '/' . $parts[0];
        }
        
        return $this->path;
    }

    /**
     * Get the underlying RouteOperation.
     */
    public function getRouteOperation(): RouteOperation
    {
        return $this->operation;
    }
}