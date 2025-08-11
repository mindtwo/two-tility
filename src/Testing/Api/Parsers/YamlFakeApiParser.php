<?php

namespace mindtwo\TwoTility\Testing\Api\Parsers;

use mindtwo\TwoTility\Testing\Contracts\SpecParserInterface;
use Symfony\Component\Yaml\Yaml;

class YamlFakeApiParser implements SpecParserInterface
{
    /**
     * @var array<string, array{
     *     faker?: array<string, mixed>,
     *     list?: array{method: string, authRequired: bool, responses: array<string, mixed>},
     *     show?: array{method: string, authRequired: bool, responses: array<string, mixed>},
     *     create?: array{method: string, authRequired: bool, responses: array<string, mixed>},
     *     update?: array{method: string, authRequired: bool, responses: array<string, mixed>},
     *     delete?: array{method: string, authRequired: bool, responses: array<string, mixed>}
     * }>
     */
    protected array $parsedPaths = [];

    /**
     * @var array<string, array<string, mixed>> Path => faker definition
     */
    protected array $fakerDefinitions = [];

    /**
     * @var array<string, array<string>> Path => supported HTTP methods
     */
    protected array $supportedMethods = [];

    /**
     * @var array<string, array<string, bool>> Path => method => authRequired
     */
    protected array $authRequirements = [];

    /**
     * Parses a structured YAML API spec into accessible path/method metadata.
     *
     * @param  string  $file  Absolute path to a YAML spec file
     */
    public function parse(string $file): void
    {
        $data = Yaml::parseFile($file);

        $collections = $data['collections'] ?? [];

        foreach ($collections as $collectionName => $collection) {
            $basePath = $collection['basePath'] ?? "/{$collectionName}";

            // Store faker definitions for the base collection
            if (! empty($collection['faker'])) {
                $this->fakerDefinitions[$collectionName] = $collection['faker'];
            }

            // Process base operations (list, create, etc.)
            if (! empty($collection['operations'])) {
                $this->processOperations($basePath, $collection['operations'], $basePath, $collectionName, $basePath);
            }

            // Process nested routes
            if (! empty($collection['routes'])) {
                foreach ($collection['routes'] as $routePath => $route) {
                    // Change the base path if specified
                    $routeBasePath = $route['basePath'] ?? null;
                    $fullPath = $routeBasePath ? "{$routeBasePath}{$routePath}" : "{$basePath}{$routePath}";

                    // Store faker definitions for the route if provided
                    if (! empty($route['faker'])) {
                        $this->fakerDefinitions[$fullPath] = $route['faker'];
                    }

                    // Process operations for this route
                    if (! empty($route['operations'])) {
                        $this->processOperations($fullPath, $route['operations'], $fullPath, $collectionName, $routeBasePath ?? $basePath);
                    }
                }
            }
        }
    }

    /**
     * Process operations for a given path.
     *
     * @param  string  $path  The path for the operations
     * @param  array<string, mixed>  $operations  The operations array
     * @param  string  $originalPath  The original path for storing in parsedPaths
     * @param  string  $collection  The collection name for this path
     * @param  string|null  $basePath  The base path from the collection definition
     */
    protected function processOperations(string $path, array $operations, string $originalPath, string $collection, ?string $basePath = null): void
    {
        foreach ($operations as $operation => $config) {
            if (! in_array($operation, ['list', 'show', 'create', 'update', 'delete'])) {
                continue;
            }

            $method = strtoupper($config['method']);
            $authRequired = $config['authRequired'] ?? false;

            // Store in parsedPaths structure
            if (! isset($this->parsedPaths[$originalPath])) {
                $this->parsedPaths[$originalPath] = [];
            }

            $this->parsedPaths[$originalPath][$operation] = [
                'collection' => $collection,
                'method' => $method,
                'authRequired' => $authRequired,
                'path' => $path,
                'basePath' => $basePath,
                'responses' => $config['responses'] ?? [],
            ];

            // Track supported methods
            $this->supportedMethods[$path][] = $method;

            // Track auth requirements
            $this->authRequirements[$path][$method] = $authRequired;
        }
    }

    /**
     * Returns the full parsed path structure from the API spec.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPaths(): array
    {
        return $this->parsedPaths;
    }

    /**
     * Returns faker definitions grouped by path.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getFakerDefinitions(): array
    {
        return $this->fakerDefinitions;
    }

    /**
     * Returns a list of supported HTTP methods for each path.
     *
     * @return array<string, array<string>>
     */
    public function getSupportedMethods(): array
    {
        return $this->supportedMethods;
    }

    /**
     * Returns auth requirements per path/method.
     *
     * @return array<string, array<string, bool>>
     */
    public function getAuthRequirements(): array
    {
        return $this->authRequirements;
    }
}
