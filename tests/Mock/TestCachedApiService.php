<?php

namespace mindtwo\TwoTility\Tests\Mock;

use mindtwo\TwoTility\Http\CachedApiService;

/**
 * @extends CachedApiService<TestApiClient>
 */
class TestCachedApiService extends CachedApiService
{
    protected function getClientClass(): string
    {
        return TestApiClient::class;
    }
}
