<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use mindtwo\TwoTility\Tests\Mock\Cache\CachedAttributesDataCache;

beforeEach(function () {
    Cache::flush();
});

it('can access cached attributes', function () {
    allowAccess();

    $model = new \mindtwo\TwoTility\Tests\Mock\CachedAttributesModel;
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
    allowAccess();

    $model = new \mindtwo\TwoTility\Tests\Mock\CachedAttributesModel;

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

it('can`t be accessed if authorize returns false', function () {
    allowAccess(false);

    expect(Gate::allows('access-cached-attributes'))->toBeFalse();

    $model = new \mindtwo\TwoTility\Tests\Mock\CachedAttributesModel;

    $model->save();

    $cacheKey = cache_key('data_cache', [
        'class' => class_basename(CachedAttributesDataCache::class),
    ])->toString();

    // check that cache key does not exist
    expect(Cache::has($cacheKey))->toBeFalse();

    expect(fn () => $model->foo)->toThrow(function (\Exception $exception) {
        return $exception->getMessage() === 'Unauthorized to access data cache.';
    });

    expect(Cache::has($cacheKey))->toBeFalse();
});

function allowAccess($allow = true)
{
    Gate::define('access-cached-attributes', function (?User $user) use ($allow) {
        return $allow;
    });
}
