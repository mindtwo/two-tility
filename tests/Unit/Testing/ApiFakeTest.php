<?php

use GuzzleHttp\Promise\PromiseInterface;
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
        ->post('https://api.fake.test/v1/users', ['name' => 'Alice']);

    expect($res1->status())->toBe(201)
        ->and($res1->json())->toHaveKey('id')
        ->and($res1->json()['name'])->toBe('Alice');

    $id = $res1->json()['id'];

    // Get the user by ID to ensure it exists
    $res1 = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get("https://api.fake.test/v1/users/{$id}");

    expect($res1->status())->toBe(200)
        ->and($res1->json())->toHaveKey('id')
        ->and($res1->json()['id'])->toEqual($id)
        ->and($res1->json()['name'])->toBe('Alice');

    $res2 = Http::withHeaders(['X-User-ID' => 'user-2'])
        ->post('https://api.fake.test/v1/users', ['name' => 'Bob']);

    expect($res2->status())->toBe(201)
        ->and($res2->json())->toHaveKey('id')
        ->and($res2->json()['id'])->not->toEqual($res1->json()['id'])
        ->and($res2->json()['name'])->toBe('Bob');

    $res1 = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get('https://api.fake.test/v1/users');

    $res2 = Http::withHeaders(['X-User-ID' => 'user-2'])
        ->get('https://api.fake.test/v1/users');

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
    $file = __DIR__.'/../../../stubs/api_spec.yaml';

    $parser = new YamlFakeApiParser;
    $parser->parse($file);

    // Debug: check what paths are being parsed
    $paths = $parser->getPaths();
    expect($paths)->toHaveKey('/v1/users/{id}/profile');

    $api = new ApiFake('https://api.fake.test');
    $api->clear(); // Clear any existing data
    $api->bootFromParser($parser);
    $api->fake();

    // Add a faked user to ensure the nested resource can be accessed
    $userId = '123e4567-e89b-12d3-a456-426614174000';

    $api->addItem(
        path: '/v1/users',
        overrides: [
            'id' => $userId,
        ],
        scope: 'user-1'
    );

    // The YAML now includes /users/{id}/profile routes
    // Test GET /users/{id}/profile - use a proper UUID format
    $profileResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get("https://api.fake.test/v1/users/{$userId}/profile");

    expect($profileResponse->status())->toBe(200)
        ->and($profileResponse->json())->toBeArray();

    // Test PUT /users/{id}/profile
    $updateData = [
        'bio' => 'Updated bio from test',
        'location' => 'Test City',
    ];

    $updateResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->put("https://api.fake.test/v1/users/{$userId}/profile", $updateData);

    expect($updateResponse->status())->toBe(200);
});

test('manual nested resource registration works', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear(); // Clear any existing data

    // Register a custom nested resource operation
    $api->getRouteResolver()->registerNestedResourceOperation('users', 'settings', 'GET', 'show');
    $api->getRouteResolver()->registerNestedResourceOperation('users', 'settings', 'PUT', 'update');

    $api->fake();

    // Add a faked user to ensure the nested resource can be accessed
    $userId = '123e4567-e89b-12d3-a456-426614174000';
    $api->addItem(
        path: '/users',
        definition: [
            'id' => $userId,
            'theme' => 'light',
        ],
        overrides: [
            'id' => $userId,
        ],
    );

    // Test that the pattern matches
    $response = Http::get("https://api.fake.test/users/$userId/settings");

    expect($response->status())->toBe(200);

    // Test PUT request
    $putResponse = Http::put("https://api.fake.test/users/$userId/settings", ['theme' => 'dark']);

    expect($putResponse->status())->toBe(200);
});

test('custom nested handler method override works', function () {
    $api = new class('https://api.fake.test') extends ApiFake
    {
        // Override method for GET /users/{id}/profile -> getUsersResourceProfile
        public function getUsersResourceProfile(string $path, $request, string $method): PromiseInterface
        {
            return Http::response([
                'custom' => true,
                'path' => $path,
                'method' => $method,
                'message' => 'Custom nested handler called',
            ]);
        }
    };

    $api->clear();
    $api->getRouteResolver()->registerNestedResourceOperation('users', 'profile', 'GET', 'show');
    $api->fake();

    $response = Http::get('https://api.fake.test/users/123e4567-e89b-12d3-a456-426614174000/profile');

    expect($response->status())->toBe(200)
        ->and($response->json())->toHaveKey('custom', true)
        ->and($response->json())->toHaveKey('message', 'Custom nested handler called')
        ->and($response->json()['method'])->toBe('GET');
});

test('custom operation handler override works', function () {
    $api = new class('https://api.fake.test') extends ApiFake
    {
        // Override method for any operation named 'settings' -> handleSettings
        public function handleSettings(string $path, $request, string $method): PromiseInterface
        {
            return Http::response([
                'custom' => true,
                'path' => $path,
                'method' => $method,
                'message' => 'Custom operation handler called',
            ]);
        }
    };

    $api->clear();
    $api->getRouteResolver()->registerNestedResourceOperation('users', 'settings', 'GET', 'show');
    $api->fake();

    $response = Http::get('https://api.fake.test/users/123e4567-e89b-12d3-a456-426614174000/settings');

    expect($response->status())->toBe(200)
        ->and($response->json())->toHaveKey('custom', true)
        ->and($response->json())->toHaveKey('message', 'Custom operation handler called')
        ->and($response->json()['method'])->toBe('GET');
});

test('handler priority order works correctly', function () {
    $api = new class('https://api.fake.test') extends ApiFake
    {
        // This should have highest priority for nested resources
        public function getUsersResourceProfile(string $path, $request, string $method): PromiseInterface
        {
            return Http::response([
                'handler' => 'nested',
                'message' => 'Nested resource handler called',
            ]);
        }

        // This should be second priority for nested resources
        public function handleProfile(string $path, $request, string $method): PromiseInterface
        {
            return Http::response([
                'handler' => 'custom',
                'message' => 'Custom operation handler called',
            ]);
        }
    };

    $api->clear();
    $api->getRouteResolver()->registerNestedResourceOperation('users', 'profile', 'GET', 'show');
    $api->fake();

    $response = Http::get('https://api.fake.test/users/123e4567-e89b-12d3-a456-426614174000/profile');

    // Should call the nested handler (highest priority)
    expect($response->status())->toBe(200)
        ->and($response->json())->toHaveKey('handler', 'nested')
        ->and($response->json())->toHaveKey('message', 'Nested resource handler called');
});

test('custom operation handler is used when nested handler is not available', function () {
    $api = new class('https://api.fake.test') extends ApiFake
    {
        // Only define the custom operation handler, not the nested one
        public function handleSettings(string $path, $request, string $method): PromiseInterface
        {
            return Http::response([
                'handler' => 'custom',
                'message' => 'Custom operation handler called',
            ]);
        }
    };

    $api->clear();
    $api->getRouteResolver()->registerNestedResourceOperation('users', 'settings', 'GET', 'show');
    $api->fake();

    $response = Http::get('https://api.fake.test/users/123e4567-e89b-12d3-a456-426614174000/settings');

    // Should call the custom handler (second priority)
    expect($response->status())->toBe(200)
        ->and($response->json())->toHaveKey('handler', 'custom')
        ->and($response->json())->toHaveKey('message', 'Custom operation handler called');
});

test('ApiResponse class provides custom status codes', function () {
    $api = new class('https://api.fake.test') extends ApiFake
    {
        public function handleCustomStatus(string $path, $request, string $method): \mindtwo\TwoTility\Testing\Api\ApiResponse
        {
            return \mindtwo\TwoTility\Testing\Api\ApiResponse::notFound(['error' => 'Custom not found']);
        }
    };

    $api->clear();
    $api->getRouteResolver()->registerNestedResourceOperation('users', 'custom-status', 'GET', 'show');
    $api->fake();

    $response = Http::get('https://api.fake.test/users/123e4567-e89b-12d3-a456-426614174000/custom-status');

    expect($response->status())->toBe(404)
        ->and($response->json())->toHaveKey('error', 'Custom not found');
});

test('inferStatusCode method handles different response types correctly', function () {
    $api = new class('https://api.fake.test') extends ApiFake
    {
        public function handleNotFound(string $path, $request, string $method): array
        {
            return ['error' => 'Not found'];
        }

        public function handleUnauthorized(string $path, $request, string $method): array
        {
            return ['error' => 'Unauthorized'];
        }

        public function handleNull(string $path, $request, string $method): null
        {
            return null;
        }

        public function handleSuccess(string $path, $request, string $method): array
        {
            return ['data' => 'success'];
        }
    };

    $api->clear();
    $api->getRouteResolver()->registerNestedResourceOperation('test', 'not-found', 'GET', 'show');
    $api->getRouteResolver()->registerNestedResourceOperation('test', 'unauthorized', 'GET', 'show');
    $api->getRouteResolver()->registerNestedResourceOperation('test', 'null', 'GET', 'show');
    $api->getRouteResolver()->registerNestedResourceOperation('test', 'success', 'GET', 'show');
    $api->fake();

    // Test 404 for "Not found" error
    $notFoundResponse = Http::get('https://api.fake.test/test/123/not-found');
    expect($notFoundResponse->status())->toBe(404);

    // Test 401 for "Unauthorized" error
    $unauthorizedResponse = Http::get('https://api.fake.test/test/123/unauthorized');
    expect($unauthorizedResponse->status())->toBe(401);

    // Test 204 for null response
    $nullResponse = Http::get('https://api.fake.test/test/123/null');
    expect($nullResponse->status())->toBe(204);

    // Test 200 for successful response
    $successResponse = Http::get('https://api.fake.test/test/123/success');
    expect($successResponse->status())->toBe(200);
});

test('create operations return 201 status code by default', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear();
    $api->getRouteResolver()->registerOperationMatcher('create', '/test', 'POST');
    $api->fake();

    $response = Http::post('https://api.fake.test/test', ['name' => 'Test Item']);

    expect($response->status())->toBe(201)
        ->and($response->json())->toHaveKey('name', 'Test Item');
});

test('mixed parameter types work correctly with static and dynamic routes', function () {
    $file = __DIR__.'/../../../stubs/api_spec.yaml';

    $parser = new YamlFakeApiParser;
    $parser->parse($file);

    $api = new ApiFake('https://api.fake.test');
    $api->clear();
    $api->bootFromParser($parser);
    $api->fake();

    // Test static route: /v1/users/orders (no parameters)
    $staticResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get('https://api.fake.test/v1/users/orders');
    
    expect($staticResponse->status())->toBe(200)
        ->and($staticResponse->json())->toBeArray();

    // Create an order on the static route
    $orderData = ['total' => 99.50, 'status' => 'pending'];
    $createResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->post('https://api.fake.test/v1/users/orders', $orderData);
    
    expect($createResponse->status())->toBe(201)
        ->and($createResponse->json())->toHaveKey('total', 99.50)
        ->and($createResponse->json())->toHaveKey('id');

    // Test dynamic route: /v1/users/{id} 
    $userId = '123e4567-e89b-12d3-a456-426614174000';
    
    // First add a user to test against
    $api->addItem(
        path: '/v1/users',
        overrides: ['id' => $userId, 'name' => 'Test User'],
        scope: 'user-1'
    );

    $dynamicResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get("https://api.fake.test/v1/users/{$userId}");
    
    expect($dynamicResponse->status())->toBe(200)
        ->and($dynamicResponse->json())->toHaveKey('id', $userId)
        ->and($dynamicResponse->json())->toHaveKey('name', 'Test User');

    // Test mixed route: /v1/users/{id}/orders (parameter + static segment)
    $mixedResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get("https://api.fake.test/v1/users/{$userId}/orders");
    
    expect($mixedResponse->status())->toBe(200)
        ->and($mixedResponse->json())->toBeArray();

    // Create an order on the mixed route
    $userOrderData = ['product_name' => 'Test Product', 'quantity' => 2, 'price' => 29.99];
    $createMixedResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->post("https://api.fake.test/v1/users/{$userId}/orders", $userOrderData);
    
    expect($createMixedResponse->status())->toBe(201)
        ->and($createMixedResponse->json())->toHaveKey('product_name', 'Test Product')
        ->and($createMixedResponse->json())->toHaveKey('quantity', 2)
        ->and($createMixedResponse->json())->toHaveKey('id');

    // Verify the mixed route with different parameter works
    $differentUserId = '456e4567-e89b-12d3-a456-426614174111';
    $differentMixedResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get("https://api.fake.test/v1/users/{$differentUserId}/orders");
    
    expect($differentMixedResponse->status())->toBe(200)
        ->and($differentMixedResponse->json())->toBeArray();
});

test('hyphenated paths work correctly', function () {
    $file = __DIR__.'/../../../stubs/api_spec.yaml';

    $parser = new YamlFakeApiParser;
    $parser->parse($file);

    $api = new ApiFake('https://api.fake.test');
    $api->clear();
    $api->bootFromParser($parser);
    $api->fake();

    // Test static route with hyphens: /v1/users/active-carts
    $hyphenatedResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get('https://api.fake.test/v1/users/active-carts');
    
    expect($hyphenatedResponse->status())->toBe(200)
        ->and($hyphenatedResponse->json())->toBeArray();

    // Create an active cart on the hyphenated route
    $cartData = ['items' => 3, 'total' => 149.99];
    $createCartResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->post('https://api.fake.test/v1/users/active-carts', $cartData);
    
    expect($createCartResponse->status())->toBe(201)
        ->and($createCartResponse->json())->toHaveKey('items', 3)
        ->and($createCartResponse->json())->toHaveKey('total', 149.99)
        ->and($createCartResponse->json())->toHaveKey('id');

    // Verify the created cart can be retrieved
    $listCartsResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get('https://api.fake.test/v1/users/active-carts');
    
    expect($listCartsResponse->status())->toBe(200)
        ->and($listCartsResponse->json())->toHaveCount(1)
        ->and($listCartsResponse->json()[0])->toHaveKey('items', 3)
        ->and($listCartsResponse->json()[0])->toHaveKey('total', 149.99);

    // Test mixed route with hyphens: /v1/users/{id}/shopping-history
    $userId = '123e4567-e89b-12d3-a456-426614174000';
    
    // First add a user to test against
    $api->addItem(
        path: '/v1/users',
        overrides: ['id' => $userId, 'name' => 'Test User'],
        scope: 'user-1'
    );

    $mixedHyphenatedResponse = Http::withHeaders(['X-User-ID' => 'user-1'])
        ->get("https://api.fake.test/v1/users/{$userId}/shopping-history");
    
    expect($mixedHyphenatedResponse->status())->toBe(200)
        ->and($mixedHyphenatedResponse->json())->toBeArray();
});

test('ApiResponse static factory methods work correctly', function () {
    $api = new class('https://api.fake.test') extends ApiFake
    {
        public function handleCreated(string $path, $request, string $method): \mindtwo\TwoTility\Testing\Api\ApiResponse
        {
            return \mindtwo\TwoTility\Testing\Api\ApiResponse::created(['id' => 123, 'name' => 'Created']);
        }

        public function handleBadRequest(string $path, $request, string $method): \mindtwo\TwoTility\Testing\Api\ApiResponse
        {
            return \mindtwo\TwoTility\Testing\Api\ApiResponse::badRequest(['error' => 'Invalid data']);
        }

        public function handleCustom(string $path, $request, string $method): \mindtwo\TwoTility\Testing\Api\ApiResponse
        {
            return \mindtwo\TwoTility\Testing\Api\ApiResponse::withStatus(['message' => 'Custom'], 418, ['X-Custom' => 'teapot']);
        }
    };

    $api->clear();
    $api->getRouteResolver()->registerNestedResourceOperation('test', 'created', 'POST', 'create');
    $api->getRouteResolver()->registerNestedResourceOperation('test', 'bad-request', 'POST', 'create');
    $api->getRouteResolver()->registerNestedResourceOperation('test', 'custom', 'GET', 'show');
    $api->fake();

    // Test 201 Created
    $createdResponse = Http::post('https://api.fake.test/test/123/created');
    expect($createdResponse->status())->toBe(201)
        ->and($createdResponse->json())->toHaveKey('id', 123);

    // Test 400 Bad Request
    $badResponse = Http::post('https://api.fake.test/test/123/bad-request');
    expect($badResponse->status())->toBe(400)
        ->and($badResponse->json())->toHaveKey('error', 'Invalid data');

    // Test custom status with headers
    $customResponse = Http::get('https://api.fake.test/test/123/custom');
    expect($customResponse->status())->toBe(418)
        ->and($customResponse->json())->toHaveKey('message', 'Custom')
        ->and($customResponse->header('X-Custom'))->toBe('teapot');
});

test('formatResponseFunction still works with ApiResponse objects', function () {
    $api = new class('https://api.fake.test') extends ApiFake
    {
        public function handleFormatted(string $path, $request, string $method): \mindtwo\TwoTility\Testing\Api\ApiResponse
        {
            return \mindtwo\TwoTility\Testing\Api\ApiResponse::ok(['data' => 'original']);
        }
    };

    $api->clear();
    $api->formatResponseUsing(function ($data, $path, $method, $operation) {
        return ['formatted' => true, 'original' => $data];
    });
    $api->getRouteResolver()->registerNestedResourceOperation('test', 'formatted', 'GET', 'show');
    $api->fake();

    $response = Http::get('https://api.fake.test/test/123/formatted');

    expect($response->status())->toBe(200)
        ->and($response->json())->toHaveKey('formatted', true)
        ->and($response->json())->toHaveKey('original', ['data' => 'original']);
});
