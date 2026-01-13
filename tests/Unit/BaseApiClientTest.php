<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use mindtwo\TwoTility\Tests\Mock\TestApiClient;

beforeEach(function () {
    config([
        'test-api.base_url' => 'https://api.example.test/v1',
        'test-api.timeout' => 30,
        'test-api.connectTimeout' => 10,
        'test-api.retries' => 3,
        'test-api.log_error' => true,
        'test-api.log_level' => 'error',
    ]);

    $this->client = new TestApiClient;
});

it('generates correct base url with version', function () {
    expect($this->client->baseUrl())->toBe('https://api.example.test/v1');
});

it('generates correct base url without trailing slash', function () {
    config(['test-api.base_url' => 'https://api.example.test/v1']);
    expect($this->client->baseUrl())->toBe('https://api.example.test/v1');
});

it('makes GET request successfully', function () {
    Http::fake([
        'api.example.test/v1/data/123' => Http::response(['id' => 123, 'name' => 'Test'], 200),
    ]);

    $response = $this->client->getData('123');

    expect($response->successful())->toBeTrue()
        ->and($response->json())->toBe(['id' => 123, 'name' => 'Test']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.test/v1/data/123'
            && $request->method() === 'GET';
    });
});

it('makes GET request with query parameters', function () {
    Http::fake([
        'api.example.test/v1/data*' => Http::response(['results' => []], 200),
    ]);

    $response = $this->client->searchData(['q' => 'test', 'limit' => 10]);

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'q=test')
            && str_contains($request->url(), 'limit=10');
    });
});

it('makes POST request successfully', function () {
    Http::fake([
        'api.example.test/v1/data' => Http::response(['id' => 456, 'name' => 'Created'], 201),
    ]);

    $response = $this->client->createData(['name' => 'New Item']);

    expect($response->successful())->toBeTrue()
        ->and($response->json())->toBe(['id' => 456, 'name' => 'Created']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.test/v1/data'
            && $request->method() === 'POST'
            && $request->data() === ['name' => 'New Item'];
    });
});

it('makes PUT request successfully', function () {
    Http::fake([
        'api.example.test/v1/data/123' => Http::response(['id' => 123, 'name' => 'Updated'], 200),
    ]);

    $response = $this->client->updateData('123', ['name' => 'Updated']);

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.test/v1/data/123'
            && $request->method() === 'PUT';
    });
});

it('makes PATCH request successfully', function () {
    Http::fake([
        'api.example.test/v1/data/123' => Http::response(['id' => 123], 200),
    ]);

    $response = $this->client->patchData('123', ['status' => 'active']);

    expect($response->successful())->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.test/v1/data/123'
            && $request->method() === 'PATCH';
    });
});

it('makes DELETE request successfully', function () {
    Http::fake([
        'api.example.test/v1/data/123' => Http::response(null, 204),
    ]);

    $response = $this->client->deleteData('123');

    expect($response->status())->toBe(204);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.test/v1/data/123'
            && $request->method() === 'DELETE';
    });
});

it('logs error and throws exception on request failure', function () {
    Log::spy();

    Http::fake([
        'api.example.test/v1/data/123' => Http::response(null, 404),
    ]);

    try {
        $this->client->getData('123');
    } catch (\Illuminate\Http\Client\RequestException $e) {
        // Expected exception
        expect($e)->toBeInstanceOf(Throwable::class);
    }

    Log::shouldHaveReceived('log')
        ->once()
        ->with('error', \Mockery::on(function ($message) {
            return str_contains($message, '[test-api]')
                && str_contains($message, 'An error occurred while requesting external data');
        }), \Mockery::on(function ($context) {
            return $context['method'] === 'GET'
                && str_contains($context['url'], '/data/123');
        }));
});

it('does not log error when log_error config is false', function () {
    config(['test-api.log_error' => false]);
    Log::spy();

    Http::fake(function () {
        throw new \Illuminate\Http\Client\RequestException(
            new \Illuminate\Http\Client\Response(
                new \GuzzleHttp\Psr7\Response(404, [], json_encode(['error' => 'Not found']))
            )
        );
    });

    try {
        $this->client->getData('123');
    } catch (Throwable $e) {
        // Expected exception
    }

    Log::shouldNotHaveReceived('log');
});

it('returns api name', function () {
    expect($this->client->apiName())->toBe('test-api');
});
