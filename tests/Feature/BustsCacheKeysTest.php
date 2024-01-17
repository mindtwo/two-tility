<?php

use Illuminate\Support\Facades\Cache;

it('busts cache key on model events', function () {
    $model = new \mindtwo\TwoTility\Tests\Mock\BustingModel();
    $model->save();

    Cache::put('test-key-1', 'foo');
    expect(Cache::has('test-key-1'))->toBeTrue();

    $model->update(['foo' => 'bar']);
    expect(Cache::has('test-key-1'))->toBeFalse();
});

it('busts cache keys on model events', function () {
    $model = new \mindtwo\TwoTility\Tests\Mock\BustingModel();
    $model->save();

    $model->cacheKeysToBust = [
        'updated' => [
            'test-key-1',
            'test-key-2',
        ],
    ];

    Cache::put('test-key-1', 'foo');
    Cache::put('test-key-2', 'foo');
    expect(Cache::has('test-key-1'))->toBeTrue()
        ->and(Cache::has('test-key-2'))->toBeTrue();

    $model->update(['foo' => 'bar']);
    expect(Cache::has('test-key-1'))->toBeFalse()
        ->and(Cache::has('test-key-2'))->toBeFalse();
});

it('busts cache keys on model events with closure', function () {
    $model = new \mindtwo\TwoTility\Tests\Mock\BustingModel();
    $model->save();

    $model->cacheKeysToBust = [
        'updated' => [
            'test-key-1',
            'test-key-2',
            function () {
                return 'test-key-3';
            },
        ],
    ];

    $fn = (function () {
        return 'test-key-3';
    });

    // dd($fn instanceof \Closure && is_string($value = $fn()));

    Cache::put('test-key-1', 'foo');
    Cache::put('test-key-2', 'foo');
    Cache::put('test-key-3', 'foo');
    expect(Cache::has('test-key-1'))->toBeTrue()
        ->and(Cache::has('test-key-2'))->toBeTrue()
        ->and(Cache::has('test-key-3'))->toBeTrue();

    $model->update(['foo' => 'bar']);
    expect(Cache::has('test-key-3'))->toBeFalse();

    // expect(Cache::has('test-key-1'))->toBeFalse()
        // ->and(Cache::has('test-key-2'))->toBeFalse()
        // ->and(Cache::has('test-key-3'))->toBeFalse();
});

it('busts cache keys on model event with cache_key helper', function () {
    $model = new \mindtwo\TwoTility\Tests\Mock\BustingModel();
    $model->save();

    $model->cacheKeysToBust = [
        'updated' => [
            'test-key-1',
            'test-key-2',
            cache_key('test-key-3'),
        ],
    ];

    Cache::put('test-key-1', 'foo');
    Cache::put('test-key-2', 'foo');
    Cache::put(cache_key('test-key-3')->__toString(), 'foo');
    expect(Cache::has('test-key-1'))->toBeTrue()
        ->and(Cache::has('test-key-2'))->toBeTrue()
        ->and(Cache::has(cache_key('test-key-3')->__toString()))->toBeTrue();

    $model->update(['foo' => 'bar']);
    expect(Cache::has('test-key-1'))->toBeFalse()
        ->and(Cache::has('test-key-2'))->toBeFalse()
        ->and(Cache::has(cache_key('test-key-3')->__toString()))->toBeFalse();
});
