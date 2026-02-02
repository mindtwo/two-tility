<?php

namespace mindtwo\TwoTility\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class BaseApiClient
{
    abstract public function apiName(): string;

    /**
     * Get the config key for client configuration.
     */
    abstract protected function configBaseKey(): string;

    /**
     * Get the base url for request.
     */
    public function baseUrl(): string
    {
        $baseUrl = rtrim($this->config('baseUrl'), '/');

        return $baseUrl;
    }

    /**
     * Create a new HTTP client instance.
     *
     * @return PendingRequest<false>
     */
    public function client(): PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl())
            ->withMiddleware($this->requestLogMiddleware())
            ->throw();

        // Return configured client
        return $this->configure($client);
    }

    /**
     * Get headers for request.
     */
    protected function headers(): array
    {
        return $this->config('headers', []);
    }

    /**
     * Configure the client.
     */
    protected function configure(PendingRequest $client): PendingRequest
    {
        // Before configuration
        $this->beforeConfigure($client);

        // Set request header
        $client->withHeaders(
            $this->headers()
        );

        // Set the timeout and connect timeout if they are set
        if ($timeout = $this->config('timeout')) {
            $client->timeout($timeout);
        }
        if ($connectTimeout = $this->config('connectTimeout')) {
            $client->connectTimeout($connectTimeout);
        }

        // Set the retries if they are set
        if ($retries = $this->config('retries')) {
            $retryDelay = $this->config('retryDelay') ?? fn ($attempt) => $attempt * 300;

            $client->retry(
                $retries,
                $retryDelay,
                function ($response, $exception) {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if ($response instanceof Response) {
                        return $response->status() === 429;
                    }

                    return false;
                }
            );
        }

        // Post config
        $this->afterConfigure($client);

        return $client;
    }

    /**
     * Hook before client configuration.
     */
    protected function beforeConfigure(PendingRequest $client): void
    {
        // ...
    }

    /**
     * Hook called after configuration.
     */
    protected function afterConfigure(PendingRequest $client): void
    {
        // ...
    }

    /**
     * Get value for a part from config.
     */
    protected function config(string $key, mixed $default = null): mixed
    {
        $baseKey = $this->configBaseKey();

        return config("$baseKey.$key", $default);
    }

    /**
     * Check whether config key is set.
     */
    protected function hasConfigKey(string $key): bool
    {
        return ! empty($this->config($key));
    }

    /**
     * Create a log entry for a request.
     */
    protected function logRequest(RequestInterface $request, ResponseInterface $response): void
    {
        // Check if error logging is active in api config
        $message = sprintf(
            '[%s] An error occurred while requesting external data. (Code: %s, Message: %s)',
            $this->apiName(),
            $response->getStatusCode(),
            (string) $response->getBody(),
        );

        // Only use logLevel for error logging
        $logLevel = $response->getStatusCode() >= 400 ? $this->logLevel() : 'debug';

        Log::log($logLevel, $message, [
            'version' => $this->version ?? null,
            'baseUrl' => $this->baseUrl ?? null,
            'method' => $request->getMethod(),
            'url' => (string) $request->getUri(),
            'body' => (string) $request->getBody(),
        ]);
    }

    /**
     * Get the log level for error logging.
     */
    private function logLevel(): string
    {
        return $this->config('logLevel', 'error');
    }

    /**
     * Create requestLogMiddleware
     */
    private function requestLogMiddleware(): callable
    {
        return function (callable $handler): callable {
            return function (RequestInterface $request, array $options = []) use ($handler) {
                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($request): ResponseInterface {

                        if ($this->config('debug', false) || $response->getStatusCode() >= 400) {
                            // If debug enabled log request
                            $this->logRequest($request, $response);
                        }

                        return $response;
                    },
                );
            };
        };
    }
}
