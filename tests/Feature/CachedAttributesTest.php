<?php

use Illuminate\Support\Facades\Cache;
use mindtwo\TwoTility\Tests\Mock\Cache\CachedAttributesDataCache;

beforeEach(function () {
    Cache::flush();
});

it('can access cached attributes', function () {
    $model = new \mindtwo\TwoTility\Tests\Mock\CachedAttributesModel();
    $model->save();

    $cacheKey = cache_key('data_cache', [
        'class' => class_basename(CachedAttributesDataCache::class),
    ])->toString();

    // check that cache key does not exist
    expect(Cache::has($cacheKey))->toBeFalse();

    expect($model->foo)->toBe('bar');
    expect($model->baz)->toBe('qux');

    expect(Cache::has($cacheKey))->toBeTrue();
});

it('can`t access cached attributes if empty and load on access not allowed', function () {
    $model = new \mindtwo\TwoTility\Tests\Mock\CachedAttributesModel();

    $model->loadOnAccess = false;
    $model->allowEmpty = false;

    $model->save();

    $cacheKey = cache_key('data_cache', [
        'class' => class_basename(CachedAttributesDataCache::class),
    ])->toString();

    // check that cache key does not exist
    expect(Cache::has($cacheKey))->toBeFalse();

    expect($model->foo)->toBe(null);
    expect($model->baz)->toBe(null);

    expect(Cache::has($cacheKey))->toBeFalse();
});
