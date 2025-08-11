<?php

use mindtwo\TwoTility\Testing\Api\Parsers\YamlFakeApiParser;

test('parses yaml spec file correctly', function () {
    $parser = new YamlFakeApiParser;
    $parser->parse(__DIR__.'/../../../stubs/api_spec.yaml');

    $paths = $parser->getPaths();

    // With the updated collections format, we expect 7 paths including static routes with hyphens
    expect($paths)->toHaveCount(7)
        ->and($paths)->toHaveKey('/v1/users')
        ->and($paths)->toHaveKey('/v1/users/orders')  // Static route
        ->and($paths)->toHaveKey('/v1/users/active-carts')  // Static route with hyphens
        ->and($paths)->toHaveKey('/v1/users/{id}')    // Dynamic route
        ->and($paths)->toHaveKey('/v1/users/{id}/profile')  // Mixed route
        ->and($paths)->toHaveKey('/v1/users/{id}/orders')   // Mixed route
        ->and($paths)->toHaveKey('/v1/users/{id}/shopping-history');  // Mixed route with hyphens

    // Test base collection operations
    expect($paths['/v1/users'])->toHaveKeys(['list', 'create'])
        ->and($paths['/v1/users']['list'])->toHaveKeys(['authRequired', 'method', 'path'])
        ->and($paths['/v1/users']['list']['method'])->toBe('GET')
        ->and($paths['/v1/users']['list']['authRequired'])->toBeTrue();

    // Test static route operations
    expect($paths['/v1/users/orders'])->toHaveKeys(['list', 'create'])
        ->and($paths['/v1/users/orders']['list']['method'])->toBe('GET')
        ->and($paths['/v1/users/orders']['list']['authRequired'])->toBeTrue();

    // Test static route with hyphens
    expect($paths['/v1/users/active-carts'])->toHaveKeys(['list', 'create'])
        ->and($paths['/v1/users/active-carts']['list']['method'])->toBe('GET')
        ->and($paths['/v1/users/active-carts']['list']['authRequired'])->toBeTrue();

    // Test dynamic route operations
    expect($paths['/v1/users/{id}'])->toHaveKeys(['show', 'update', 'delete'])
        ->and($paths['/v1/users/{id}']['show']['method'])->toBe('GET')
        ->and($paths['/v1/users/{id}']['show']['authRequired'])->toBeTrue();

    // Test mixed route operations
    expect($paths['/v1/users/{id}/profile'])->toHaveKeys(['show', 'update'])
        ->and($paths['/v1/users/{id}/profile']['show']['method'])->toBe('GET')
        ->and($paths['/v1/users/{id}/profile']['show']['authRequired'])->toBeTrue()
        ->and($paths['/v1/users/{id}/profile']['show']['responses'])->toHaveKey('200');

    // Test another mixed route with faker definitions
    expect($paths['/v1/users/{id}/orders'])->toHaveKeys(['list', 'create'])
        ->and($paths['/v1/users/{id}/orders']['list']['method'])->toBe('GET')
        ->and($paths['/v1/users/{id}/orders']['create']['method'])->toBe('POST');

    // Test mixed route with hyphens
    expect($paths['/v1/users/{id}/shopping-history'])->toHaveKeys(['list'])
        ->and($paths['/v1/users/{id}/shopping-history']['list']['method'])->toBe('GET')
        ->and($paths['/v1/users/{id}/shopping-history']['list']['authRequired'])->toBeTrue();
});

test('parses json spec file correctly', function () {
    $parser = new YamlFakeApiParser;
    $parser->parse(__DIR__.'/../../../stubs/api_spec.json');

    $paths = $parser->getPaths();

    // The JSON file uses the old "paths" format, which the parser no longer supports
    // Since we've moved to collections-based format, this should return empty
    expect($paths)->toBeArray()
        ->and($paths)->toBeEmpty();
})->skip('JSON format uses old structure, collections format is now required');
