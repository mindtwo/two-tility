<?php

namespace mindtwo\TwoTility\Testing\Api;

/**
 * Represents a route operation with its matching criteria and metadata.
 */
class RouteOperation
{
    public function __construct(
        public readonly string $operation,
        public readonly string $pattern,
        public readonly string $regex,
        public readonly string $method,
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
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * Get the HTTP method for this operation.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the original pattern used to create this operation.
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Get the compiled regex pattern.
     */
    public function getRegex(): string
    {
        return $this->regex;
    }

    /**
     * Get the base path for this operation.
     */
    public function getBasePath(): ?string
    {
        return $this->basePath;
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