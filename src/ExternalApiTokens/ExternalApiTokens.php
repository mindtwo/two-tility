<?php

namespace mindtwo\TwoTility\ExternalApiTokens;

use RuntimeException;
use mindtwo\TwoTility\ExternalApiTokens\Contracts\ExternalApiTokenRepository;

class ExternalApiTokens
{
    /**
     * Get ExternalApiTokenRepository by name.
     *
     * @param string|null $apiName
     * @return ExternalApiTokenRepository
     */
    public function repository(?string $apiName = null): ExternalApiTokenRepository
    {
        // Determine the API name to use
        $apiName = $apiName ?? $this->getDefaultConfig('api');

        throw_if(! $apiName, new RuntimeException('No API name provided and no default API configured'));

        // Get API-specific config
        $apiConfig = config("external-api.apis.{$apiName}", []);

        // Merge with defaults
        $repositoryAlias = $apiConfig['repository'] ?? $this->getDefaultConfig('repository', 'eloquent');
        $keyMapping = $apiConfig['key_mapping'] ?? [];

        // Resolve repository class from alias
        $repositoryClass = $this->resolveAlias($repositoryAlias);

        // Instantiate repository
        return new $repositoryClass($apiName, $keyMapping);
    }

    /**
     * Resolve repository class from alias.
     *
     * @param string $alias
     * @return string
     */
    protected function resolveAlias(string $alias): string
    {
        if (class_exists($alias)) {
            return $alias;
        }

        $repositoryClass = config("external-api.alias.{$alias}");

        throw_if(! $repositoryClass, new RuntimeException("Repository alias '{$alias}' not found in config"));
        throw_if(! class_exists($repositoryClass), new RuntimeException("Repository class '{$repositoryClass}' does not exist"));

        return $repositoryClass;
    }

    /**
     * Get default config value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getDefaultConfig(string $key, mixed $default = null): mixed
    {
        return config("external-api.defaults.{$key}", $default);
    }

}
