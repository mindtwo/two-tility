<?php

use Illuminate\Support\Facades\Http;
use mindtwo\TwoTility\Testing\Api\Parsers\YamlFakeApiParser;
use mindtwo\TwoTility\Testing\ApiFake;

test('scoped data isolation by user ID header', function () {
    $file = __DIR__.'/../../../stubs/api_spec.yaml';

    $parser = new YamlFakeApiParser;
    $parser->parse($file);

    $api = new ApiFake('https://api.fake.test');

    $api->bootFromParser($parser);
    $api->fake();

    $res1 = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->post('https://api.fake.test/users', ['name' => 'Alice']);

    expect($res1->status())->toBe(201)
        ->and($res1->json())->toHaveKey('id')
        ->and($res1->json()['name'])->toBe('Alice');

    $id = $res1->json()['id'];

    // Get the user by ID to ensure it exists
    $res1 = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get("https://api.fake.test/users/{$id}");

    expect($res1->status())->toBe(200)
        ->and($res1->json())->toHaveKey('id')
        ->and($res1->json()['id'])->toEqual($id)
        ->and($res1->json()['name'])->toBe('Alice');

    $res2 = Http::withHeaders(['X-User-ID' => 'user-2'])
        ->post('https://api.fake.test/users', ['name' => 'Bob']);

    expect($res2->status())->toBe(201)
        ->and($res2->json())->toHaveKey('id')
        ->and($res2->json()['id'])->not->toEqual($res1->json()['id'])
        ->and($res2->json()['name'])->toBe('Bob');

    $res1 = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get('https://api.fake.test/users');

    $res2 = Http::withHeaders(['X-User-ID' => 'user-2'])
        ->get('https://api.fake.test/users');

    expect($res1->json())
        ->toHaveCount(1)
        ->and($res1->json()[0]['name'])
        ->toBe('Alice');

    expect($res2->json())
        ->toHaveCount(1)
        ->and($res2->json()[0]['name'])
        ->toBe('Bob');
});
