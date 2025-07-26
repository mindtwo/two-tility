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

        $this->parsedPaths = $data['paths'] ?? [];

        foreach ($this->parsedPaths as $path => $entry) {
            if (! empty($entry['faker'])) {
                $this->fakerDefinitions[$path] = $entry['faker'];
            }

            // Initialize supported methods and auth requirements for the path
            foreach (['list', 'show', 'create', 'update', 'delete'] as $op) {
                if (! isset($entry[$op])) {
                    continue;
                }

                // Check if we have a param option for the path
                $param = $entry[$op]['param'] ?? null;

                if ($param) {
                    // If a param is defined, we need to adjust the path to include it
                    $path = "{$path}/{{$param}}";

                    // Replace the current path
                }

                $method = strtoupper($entry[$op]['method']);
                $this->supportedMethods[$path][] = $method;
                $this->authRequirements[$path][$method] = $entry[$op]['authRequired'] ?? false;
            }
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
