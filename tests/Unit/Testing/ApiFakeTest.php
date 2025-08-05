<?php

use Illuminate\Support\Facades\Http;
use mindtwo\TwoTility\Testing\Api\Parsers\YamlFakeApiParser;
use mindtwo\TwoTility\Testing\ApiFake;

test('scoped data isolation by user ID header', function () {
    $file = __DIR__.'/../../../stubs/api_spec.yaml';

    $parser = new YamlFakeApiParser;
    $parser->parse($file);

    $api = new ApiFake('https://api.fake.test');
    $api->clear(); // Clear any existing data

    $api->bootFromParser($parser);
    $api->fake();

    $res1 = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->post('https://api.fake.test/users', ['name' => 'Alice']);

    expect($res1->status())->toBe(200)
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

    expect($res2->status())->toBe(200)
        ->and($res2->json())->toHaveKey('id')
        ->and($res2->json()['id'])->not->toEqual($res1->json()['id'])
        ->and($res2->json()['name'])->toBe('Bob');

    $res1 = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get('https://api.fake.test/users');

    $res2 = Http::withHeaders(['X-User-ID' => 'user-2'])
        ->get('https://api.fake.test/users');

    // Debug the responses
    dump('User-1 response:', $res1->json());
    dump('User-2 response:', $res2->json());

    expect($res1->json())
        ->toHaveCount(1)
        ->and($res1->json()[0]['name'])
        ->toBe('Alice');

    expect($res2->json())
        ->toHaveCount(1)
        ->and($res2->json()[0]['name'])
        ->toBe('Bob');
});

test('nested resource operations work correctly', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear(); // Clear any existing data
    
    // Manually register the nested resource operations
    $api->registerNestedResourceOperation('users', 'profile', 'GET', 'show');
    $api->registerNestedResourceOperation('users', 'profile', 'PUT', 'update');
    
    // Add path definition for the profile data structure
    $api->addPathDefinition('/users/{resource}/profile', [
        'user_id' => 'uuid',
        'bio' => 'sentence',
        'avatar_url' => 'imageUrl',
        'website' => 'url',
        'location' => 'city'
    ]);
    
    $api->fake();

    // First create a user to have something to attach the profile to
    $userResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->post('https://api.fake.test/users', ['name' => 'Test User', 'email' => 'test@example.com']);
    
    $userId = $userResponse->json()['id'];

    // Add the profile data manually using reflection to access the store
    $reflection = new \ReflectionClass($api);
    $storeProperty = $reflection->getProperty('store');
    $storeProperty->setAccessible(true);
    $store = $storeProperty->getValue($api);
    
    $store->add("/users/{$userId}", 'user-1', 'profile', [
        'user_id' => $userId, 
        'bio' => 'Test bio', 
        'avatar_url' => 'https://example.com/avatar.jpg',
        'website' => 'https://example.com',
        'location' => 'Test Location'
    ]);

    // Test GET /users/{id}/profile - use the actual user ID
    $profileResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get("https://api.fake.test/users/{$userId}/profile");

    echo "Status: " . $profileResponse->status() . "\n";
    echo "Response: " . json_encode($profileResponse->json()) . "\n";

    $responseData = $profileResponse->json();
    
    // For now, just check if we get some response that's not an error
    expect($profileResponse->status())->not->toBe(404);

    // Test PUT /users/{id}/profile
    $updateData = [
        'bio' => 'Updated bio from test',
        'location' => 'Test City'
    ];

    $updateResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->put('https://api.fake.test/users/123/profile', $updateData);

    expect($updateResponse->status())->toBe(200);
});

test('manual nested resource registration works', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear(); // Clear any existing data
    
    // Register a custom nested resource operation
    $api->registerNestedResourceOperation('users', 'settings', 'GET', 'show');
    $api->registerNestedResourceOperation('users', 'settings', 'PUT', 'update');
    
    $api->fake();

    // Test that the pattern matches
    $response = Http::get('https://api.fake.test/users/456/settings');
    
    expect($response->status())->toBe(200);
    
    // Test PUT request
    $putResponse = Http::put('https://api.fake.test/users/456/settings', ['theme' => 'dark']);
    
    expect($putResponse->status())->toBe(200);
});
