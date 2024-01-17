<?php

it('generates a cache key', function () {
    $key = cache_key('foo');

    expect($key)->toBeStringable();

    // toString generates the key which is a md5 hash of the given string.
    expect($key->__toString())->toEqual(md5('foo'));
});

it('generates does not generate a hash if condition does not apply', function () {
    $key1 = cache_key('foo')->debugIf(1 === 1);
    $key2 = cache_key('foo')->debug();

    expect($key1)->toEqual($key2);

    $key3 = cache_key('foo')->debugIf(1 === 2);
    expect($key3)->not->toEqual($key2);

    expect($key3->__toString())->not->toEqual($key2->__toString())
        ->and($key1->__toString())->toEqual($key2->__toString());
});

it('generates a cache key with added param', function () {
    $key = cache_key('foo')->addParam('bar');

    expect($key)->toBeStringable();

    // toString generates the key which is a md5 hash of the given string.
    expect($key->__toString())->toEqual(md5('foo:bar'));
});

it('adds a param from options array', function () {
    $key = cache_key('foo', ['baz']);

    expect($key)->toBeStringable();

    // toString generates the key which is a md5 hash of the given string.
    expect($key->__toString())->toEqual(md5('foo:baz'));

    // it only adds the value
    $key = cache_key('foo', ['baz' => 'bar']);
    expect($key->__toString())->toEqual(md5('foo:bar'))
        ->and($key->__toString())->not->toEqual(md5('foo:baz'));
});

it('adds a param from request header if value is "header"', function () {
    reqHeader('baz', 'bazVal');

    $key = cache_key('foo', ['baz' => 'header']);

    expect($key)->toBeStringable();

    // toString generates the key which is a md5 hash of the given string.
    expect($key->__toString())->toEqual(md5('foo:'.request()->header('baz')))
        ->and($key->__toString())->toEqual(md5('foo:bazVal'));
});
