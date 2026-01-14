<?php

namespace mindtwo\TwoTility\Http;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Response;

/**
 * @template TClient of BaseApiClient
 */
abstract class CachedApiService
{
    /**
     * @var TClient
     */
    private BaseApiClient $client;

    /**
     * Create a new cached API service instance.
     */
    public function __construct(
        protected CacheRepository $cache,
    ) {
        $this->client = resolve($this->getClientClass());
    }

    /**
     * Get the API client class name.
     *
     * @return class-string<TClient>
     */
    abstract protected function getClientClass(): string;

    /**
     * Proxy method calls to the underlying API client.
     *
     * @return Response
     */
    public function __call(string $method, array $arguments): mixed
    {
        // Check if the method exists on the client
        if (! method_exists($this->client, $method)) {
            throw new \BadMethodCallException(
                sprintf('Method %s does not exist on %s', $method, get_class($this->client))
            );
        }

        // Proxy the call to the underlying client
        return $this->client->$method(...$arguments);
    }

    /**
     * Get the underlying API client.
     *
     * @return TClient
     */
    public function client(): BaseApiClient
    {
        return $this->client;
    }
}
