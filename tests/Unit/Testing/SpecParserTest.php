<?php

use mindtwo\TwoTility\Testing\Api\Parsers\YamlFakeApiParser;

test('parses yaml spec file correctly', function () {
    $parser = new YamlFakeApiParser;
    $parser->parse(__DIR__.'/../../../stubs/api_spec.yaml');

    $paths = $parser->getPaths();

    expect($paths)->toHaveCount(2)
        ->and($paths['/users'])->toHaveKeys(['faker', 'create', 'show'])
        ->and($paths['/users']['list'])->toHaveKeys(['authRequired', 'method', 'responses'])
        ->and($paths['/users']['list']['method'])->toBe('GET')
        ->and($paths['/users']['list']['authRequired'])->toBeTrue()
        ->and($paths['/users']['show'])->toHaveKeys(['authRequired', 'method', 'responses'])
        ->and($paths['/products'])->toHaveKeys(['faker', 'list', 'create'])
        ->and($paths['/products']['list'])->toHaveKeys(['authRequired', 'method', 'responses'])
        ->and($paths['/products']['list']['method'])->toBe('GET')
        ->and($paths['/products']['list']['authRequired'])->toBeFalse();
});

test('parses json spec file correctly', function () {
    $parser = new YamlFakeApiParser;
    $parser->parse(__DIR__.'/../../../stubs/api_spec.json');

    $paths = $parser->getPaths();

    expect($paths)->toHaveCount(2)
        ->and($paths['/users'])->toHaveKeys(['faker', 'list', 'create'])
        ->and($paths['/users']['list'])->toHaveKeys(['authRequired', 'method', 'responses'])
        ->and($paths['/users']['list']['method'])->toBe('GET')
        ->and($paths['/users']['list']['authRequired'])->toBeTrue();

    expect($paths['/products'])->toHaveKeys(['faker', 'list', 'create'])
        ->and($paths['/products']['list'])->toHaveKeys(['authRequired', 'method', 'responses'])
        ->and($paths['/products']['list']['method'])->toBe('GET')
        ->and($paths['/products']['list']['authRequired'])->toBeFalse();
});
