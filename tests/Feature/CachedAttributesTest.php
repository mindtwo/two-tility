<?php

use Illuminate\Support\Facades\Cache;
use mindtwo\TwoTility\Tests\Mock\CachedAttributesModel;

beforeEach(function () {
    Cache::flush();
});

it('can access cached attributes', function () {
    $model = CachedAttributesModel::create([
        'name' => 'test model',
    ]);

    $cacheKey = $model->cachedAttributeKey();
    Cache::store()->set($cacheKey, [
        'foo' => 'bar',
        'baz' => 'qux',
    ]);

    expect($model->foo)->toBe('bar');
    expect($model->baz)->toBe('qux');

    expect($model->name)->toBe('test model');

    expect(Cache::has($cacheKey))->toBeTrue();
});

it('can`t access cached attributes if nothing in cache', function () {
    $model = CachedAttributesModel::create([
        'name' => 'test model',
    ]);

    $cacheKey = $model->cachedAttributeKey();

    // check that cache key does not exist
    expect(Cache::has($cacheKey))->toBeFalse();

    expect($model->foo)->toBe(null);
    expect($model->baz)->toBe(null);

    expect($model->name)->toBe('test model');

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('can access recursive relationship without triggering unnecessary cache loads', function () {
    // Create parent model
    $parent = CachedAttributesModel::create([
        'name' => 'parent model',
    ]);

    // Create child model with parent relationship
    $child = CachedAttributesModel::create([
        'name' => 'child model',
        'parent_id' => $parent->id,
    ]);

    // Set up cache for parent only
    $parentCacheKey = $parent->cachedAttributeKey();
    Cache::store()->set($parentCacheKey, [
        'foo' => 'parent value',
    ]);

    // Refresh child from database to simulate fresh load
    $child = CachedAttributesModel::find($child->id);

    // Access regular attribute - should not load cache yet
    expect($child->name)->toBe('child model');

    // Access relationship - should not trigger cache load for child
    expect($child->parent)->not->toBeNull();
    expect($child->parent->name)->toBe('parent model');

    // Access parent's cached attribute
    expect($child->parent->foo)->toBe('parent value');

    // Access child's cached attribute - now it should load (and be empty)
    expect($child->foo)->toBe(null);
});

it('lazy loads cached attributes only when accessing cached properties', function () {
    // Create multiple models
    $parent = CachedAttributesModel::create(['name' => 'parent']);
    $child1 = CachedAttributesModel::create(['name' => 'child1', 'parent_id' => $parent->id]);
    $child2 = CachedAttributesModel::create(['name' => 'child2', 'parent_id' => $parent->id]);

    // Set up cache for all models
    Cache::store()->set($parent->cachedAttributeKey(), ['foo' => 'value1']);
    Cache::store()->set($child1->cachedAttributeKey(), ['foo' => 'value2']);
    Cache::store()->set($child2->cachedAttributeKey(), ['foo' => 'value3']);

    // Query all children
    $children = CachedAttributesModel::where('parent_id', $parent->id)->get();

    // Access regular attributes - no cache loads should happen
    expect($children)->toHaveCount(2);
    expect($children->first()->name)->toBe('child1');

    // Access parent relationship - no cache loads should happen yet
    expect($children->first()->parent->name)->toBe('parent');

    // Now access a cached attribute on first child - should load cache for that one only
    expect($children->first()->foo)->toBe('value2');

    // Access cached attribute on parent - should load parent's cache
    expect($children->first()->parent->foo)->toBe('value1');

    // Second child's cache shouldn't be loaded until we access its cached attribute
    expect($children->last()->foo)->toBe('value3');
});
