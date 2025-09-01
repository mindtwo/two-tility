<?php

namespace mindtwo\TwoTility\Testing\Api;

/**
 * Represents a route operation with its matching criteria and metadata.
 */
class RouteOperation
{
    /**
     * @param  string  $operation  The operation type (e.g., 'list', 'show', 'create', 'update', 'delete').
     * @param  string  $pattern  The pattern to match the operation (e.g., '/users/{id}').
     * @param  string  $regex  The compiled regex pattern for matching.
     * @param  string  $method  The HTTP method for this operation (e.g., 'POST', 'GET').
     * @param  string  $collectionName  The collection name this operation belongs to.
     * @param  array<string, string>  $parameters  Optional parameters extracted from the path.
     * @param  string|null  $basePath  Optional base path for the API.
     */
    public function __construct(
        public readonly string $operation,
        public readonly string $pattern,
        public readonly string $regex,
        public readonly string $method,
        public readonly string $collectionName,
        public readonly array $parameters = [],
        public readonly ?string $basePath = null
    ) {}

    /**
     * Check if this operation matches the given path and method.
     */
    public function matches(string $path, string $method): bool
    {
        return preg_match($this->regex, $path) && $this->method === strtoupper($method);
    }

    /**
     * Extract parameters from the given path using the operation's regex pattern.
     *
     * @return array<string, string>
     */
    public function extractParameters(string $path): array
    {
        $parameters = [];

        if (preg_match($this->regex, $path, $matches)) {
            // Extract named parameters from the pattern
            if (preg_match_all('/\{(\w+)\}/', $this->pattern, $paramMatches)) {
                foreach ($paramMatches[1] as $index => $paramName) {
                    // Match index + 1 because $matches[0] contains the full match
                    if (isset($matches[$index + 1])) {
                        $parameters[$paramName] = $matches[$index + 1];
                    }
                }
            }
        }

        return $parameters;
    }

    /**
     * Get the operation type (list, show, create, update, delete).
     */
    public function operation(): string
    {
        return $this->operation;
    }

    /**
     * Get the HTTP method for this operation.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Get the original pattern used to create this operation.
     */
    public function pattern(): string
    {
        return $this->pattern;
    }

    /**
     * Get the compiled regex pattern.
     */
    public function regex(): string
    {
        return $this->regex;
    }

    /**
     * Get the base path for this operation.
     */
    public function basePath(): ?string
    {
        return $this->basePath;
    }

    public function collection(): string
    {
        return $this->collectionName;
    }

    /**
     * Convert to array format (for backward compatibility).
     *
     * @return array{operation: string, regex: string, method: string, pattern: string}
     */
    public function toArray(): array
    {
        return [
            'operation' => $this->operation,
            'regex' => $this->regex,
            'method' => $this->method,
            'pattern' => $this->pattern,
        ];
    }
}
