<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use mindtwo\TwoTility\Tests\Mock\TestApiClient;
use mindtwo\TwoTility\Tests\Mock\TestCachedApiService;

beforeEach(function () {
    config([
        'test-api.base_url' => 'https://api.example.test/v1',
        'test-api.timeout' => 30,
        'test-api.log_error' => false,
    ]);

    $this->cache = Cache::store('array');
    $this->service = new TestCachedApiService($this->cache);
});

it('resolves the underlying client', function () {
    expect($this->service->client())->toBeInstanceOf(TestApiClient::class);
});

it('proxies getData method to underlying client', function () {
    Http::fake([
        'api.example.test/v1/data/123' => Http::response(['id' => 123, 'name' => 'Test Data'], 200),
    ]);

    $response = $this->service->getData('123');

    expect($response->successful())->toBeTrue()
        ->and($response->json())->toBe(['id' => 123, 'name' => 'Test Data']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.example.test/v1/data/123'
            && $request->method() === 'GET';
    });
});

it('throws BadMethodCallException for non-existent methods', function () {
    expect(fn () => $this->service->nonExistentMethod())
        ->toThrow(BadMethodCallException::class);
});

it('calls client method only once per instance', function () {
    $client1 = $this->service->client();
    $client2 = $this->service->client();

    expect($client1)->toBe($client2);
});
