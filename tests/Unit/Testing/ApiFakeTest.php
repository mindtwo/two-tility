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
        path: 'users',
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
        path: 'users',
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
        public function handleUsersProfileShow($request, $match, string $path, string $method): PromiseInterface
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
        public function handleUsersSettingsShow($request, $match, string $path, string $method): PromiseInterface
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

test('custom operation handler is used when nested handler is not available', function () {
    $api = new class('https://api.fake.test') extends ApiFake
    {
        // Only define the custom operation handler, not the nested one
        public function handleUsersSettingsShow($request, $match, string $path, string $method): PromiseInterface
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
        public function handleUsersCustomStatusShow($request, $match, string $path, string $method): \mindtwo\TwoTility\Testing\Api\ApiResponse
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
        public function handleTestNotFoundShow($request, $match, string $path, string $method): array
        {
            return ['error' => 'Not found'];
        }

        public function handleTestUnauthorizedShow($request, $match, string $path, string $method): array
        {
            return ['error' => 'Unauthorized'];
        }

        public function handleTestNullShow($request, $match, string $path, string $method): null
        {
            return null;
        }

        public function handleTestSuccessShow($request, $match, string $path, string $method): array
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
    $api->getRouteResolver()->registerOperationMatcher('create', '/test', 'POST', 'test');
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
        path: 'users',
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
        path: 'users',
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
        public function handleTestCreatedCreate($request, $match, string $path, string $method): \mindtwo\TwoTility\Testing\Api\ApiResponse
        {
            return \mindtwo\TwoTility\Testing\Api\ApiResponse::created(['id' => 123, 'name' => 'Created']);
        }

        public function handleTestBadRequestCreate($request, $match, string $path, string $method): \mindtwo\TwoTility\Testing\Api\ApiResponse
        {
            return \mindtwo\TwoTility\Testing\Api\ApiResponse::badRequest(['error' => 'Invalid data']);
        }

        public function handleTestCustomShow($request, $match, string $path, string $method): \mindtwo\TwoTility\Testing\Api\ApiResponse
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
        public function handleTestFormattedShow($request, $match, string $path, string $method): \mindtwo\TwoTility\Testing\Api\ApiResponse
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

test('temporary response override works for single call', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear();
    $api->getRouteResolver()->registerOperationMatcher('show', '/users/{id}', 'GET', 'users');
    $api->fake();

    $fakeUuid = '123e4567-e89b-12d3-a456-426614174000';

    // Add a temporary response for one call
    $api->addTemporaryResponse('/users/'.$fakeUuid, 'GET', ['error' => 'Service unavailable'], 1, 503);

    // First call should return the temporary response
    $response1 = Http::get('https://api.fake.test/users/'.$fakeUuid);
    expect($response1->status())->toBe(503)
        ->and($response1->json())->toHaveKey('error', 'Service unavailable');

    // Second call should fall back to normal behavior (404 since no item exists)
    $response2 = Http::get('https://api.fake.test/users/'.$fakeUuid);
    expect($response2->status())->toBe(404)
        ->and($response2->json())->toHaveKey('error', 'Not found');
});

test('temporary response override works for multiple calls', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear();
    $api->getRouteResolver()->registerOperationMatcher('show', '/users/{id}', 'GET', 'users');
    $api->fake();

    $fakeUuid = '123e4567-e89b-12d3-a456-426614174000';

    // Add a temporary response for 3 calls
    $api->addTemporaryResponse('/users/'.$fakeUuid, 'GET', ['error' => 'Rate limited'], 3, 429);

    // First three calls should return the temporary response
    for ($i = 1; $i <= 3; $i++) {
        $response = Http::get('https://api.fake.test/users/'.$fakeUuid);
        expect($response->status())->toBe(429)
            ->and($response->json())->toHaveKey('error', 'Rate limited');
    }

    // Fourth call should fall back to normal behavior
    $response4 = Http::get('https://api.fake.test/users/'.$fakeUuid);
    expect($response4->status())->toBe(404)
        ->and($response4->json())->toHaveKey('error', 'Not found');
});

test('temporary response override with custom headers', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear();
    $api->getRouteResolver()->registerOperationMatcher('show', '/users/{id}', 'GET', 'users');
    $api->fake();

    // Add a temporary response with custom headers
    $api->addTemporaryResponse('/users/123', 'GET',
        ['message' => 'Custom response'],
        1,
        200,
        ['X-Custom-Header' => 'test-value', 'X-Rate-Limit' => '10']
    );

    $response = Http::get('https://api.fake.test/users/123');
    expect($response->status())->toBe(200)
        ->and($response->json())->toHaveKey('message', 'Custom response')
        ->and($response->header('X-Custom-Header'))->toBe('test-value')
        ->and($response->header('X-Rate-Limit'))->toBe('10');
});

test('temporary response override works with wildcard paths', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear();
    $api->getRouteResolver()->registerOperationMatcher('show', '/users/{id}', 'GET', 'users');
    $api->fake();

    // Add a temporary response with wildcard pattern
    $api->addTemporaryResponse('/users/*', 'GET', ['error' => 'All users unavailable'], 2, 503);

    $fakeUuid = '123e4567-e89b-12d3-a456-426614174000';

    // Test different user IDs - both should match the wildcard
    $response1 = Http::get('https://api.fake.test/users/'.$fakeUuid);
    expect($response1->status())->toBe(503)
        ->and($response1->json())->toHaveKey('error', 'All users unavailable');

    $response2 = Http::get('https://api.fake.test/users/456e4567-e89b-12d3-a456-426614174111');
    expect($response2->status())->toBe(503)
        ->and($response2->json())->toHaveKey('error', 'All users unavailable');

    // Third call should fall back to normal behavior
    $response3 = Http::get('https://api.fake.test/users/456e4567-e89b-12d3-a456-426614174222');
    expect($response3->status())->toBe(404)
        ->and($response3->json())->toHaveKey('error', 'Not found');
});

test('temporary response override is method-specific', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear();
    $api->getRouteResolver()->registerOperationMatcher('show', '/users/{id}', 'GET', 'users');
    $api->getRouteResolver()->registerOperationMatcher('update', '/users/{id}', 'PUT', 'users');
    $api->fake();

    // Add temporary response only for GET requests
    $api->addTemporaryResponse('/users/123', 'GET', ['error' => 'GET unavailable'], 1, 503);

    // GET should return temporary response
    $getResponse = Http::get('https://api.fake.test/users/123');
    expect($getResponse->status())->toBe(503)
        ->and($getResponse->json())->toHaveKey('error', 'GET unavailable');

    // PUT should use normal behavior
    $putResponse = Http::put('https://api.fake.test/users/123', ['name' => 'Updated']);
    expect($putResponse->status())->toBe(404)
        ->and($putResponse->json())->toHaveKey('error', 'No route matched');
});

test('multiple temporary response overrides for different paths', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear();
    $api->getRouteResolver()->registerOperationMatcher('show', '/users/{id}', 'GET', 'users');
    $api->getRouteResolver()->registerOperationMatcher('list', '/posts', 'GET', 'posts');
    $api->fake();

    $fakeUuid = '123e4567-e89b-12d3-a456-426614174000';

    // Add temporary responses for different paths
    $api->addTemporaryResponse('/users/'.$fakeUuid, 'GET', ['error' => 'User unavailable'], 1, 503);
    $api->addTemporaryResponse('/posts', 'GET', ['error' => 'Posts unavailable'], 1, 503);

    // Both should return their respective temporary responses
    $userResponse = Http::get('https://api.fake.test/users/'.$fakeUuid);
    expect($userResponse->status())->toBe(503)
        ->and($userResponse->json())->toHaveKey('error', 'User unavailable');

    $postsResponse = Http::get('https://api.fake.test/posts');
    expect($postsResponse->status())->toBe(503)
        ->and($postsResponse->json())->toHaveKey('error', 'Posts unavailable');
});

test('clearTemporaryResponses removes all overrides', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear();
    $api->getRouteResolver()->registerOperationMatcher('show', '/users/{id}', 'GET', 'users');
    $api->fake();

    $fakeUuid = '123e4567-e89b-12d3-a456-426614174000';
    $api->addItem('users', ['id' => $fakeUuid, 'name' => 'Test User']);

    // Add temporary responses
    $api->addTemporaryResponse('/users/'.$fakeUuid, 'GET', ['error' => 'Temporary'], 5, 503);
    $api->addTemporaryResponse('/users/456', 'GET', ['error' => 'Another'], 3, 502);

    // Verify they exist
    expect($api->getTemporaryResponses())->toHaveCount(2);

    // Clear all temporary responses
    $api->clearTemporaryResponses();

    // Verify they're gone
    expect($api->getTemporaryResponses())->toHaveCount(0);

    // Verify normal behavior is restored
    $response = Http::get('https://api.fake.test/users/'.$fakeUuid);
    expect($response->status())->toBe(200)
        ->and($response->json())->toHaveKey('id', $fakeUuid)
        ->and($response->json())->toHaveKey('name', 'Test User');
});

test('getTemporaryResponses returns current overrides with usage tracking', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear();
    $api->getRouteResolver()->registerOperationMatcher('show', '/users/{id}', 'GET', 'users');
    $api->fake();

    // Add temporary responses
    $api->addTemporaryResponse('/users/123', 'GET', ['data' => 'test'], 3, 200, ['X-Test' => 'value']);

    $overrides = $api->getTemporaryResponses();
    expect($overrides)->toHaveKey('/users/123')
        ->and($overrides['/users/123'])->toHaveKey('GET')
        ->and($overrides['/users/123']['GET']['response'])->toBe(['data' => 'test'])
        ->and($overrides['/users/123']['GET']['count'])->toBe(3)
        ->and($overrides['/users/123']['GET']['used'])->toBe(0)
        ->and($overrides['/users/123']['GET']['statusCode'])->toBe(200)
        ->and($overrides['/users/123']['GET']['headers'])->toBe(['X-Test' => 'value']);

    // Make a request to increment usage
    Http::get('https://api.fake.test/users/123');

    $overrides = $api->getTemporaryResponses();
    expect($overrides['/users/123']['GET']['used'])->toBe(1);
});

test('temporary response override takes priority over custom handlers', function () {
    $api = new class('https://api.fake.test') extends ApiFake
    {
        public function handleUsersProfileShow($request, $match, string $path, string $method): PromiseInterface
        {
            return Http::response(['handler' => 'custom']);
        }
    };

    $api->clear();
    $api->getRouteResolver()->registerNestedResourceOperation(
        collection: 'users',
        operation: 'profile',
        method: 'GET',
        crudOperation: 'show'
    );

    $api->fake();

    // Add temporary response - should override the custom handler
    $api->addTemporaryResponse('/users/123/profile', 'GET', ['handler' => 'temporary'], 1, 200);

    $response = Http::get('https://api.fake.test/users/123/profile');
    expect($response->status())->toBe(200)
        ->and($response->json())->toHaveKey('handler', 'temporary');

    // Second call should use the custom handler
    $response2 = Http::get('https://api.fake.test/users/123/profile');
    expect($response2->status())->toBe(200)
        ->and($response2->json())->toHaveKey('handler', 'custom');
});

test('path normalization works correctly for temporary responses', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear();
    $api->getRouteResolver()->registerOperationMatcher('show', '/users/{id}', 'GET', 'users');
    $api->fake();

    // Add temporary response with trailing slash
    $api->addTemporaryResponse('/users/123/', 'GET', ['normalized' => true], 1, 200);

    // Request without trailing slash should still match
    $response = Http::get('https://api.fake.test/users/123');
    expect($response->status())->toBe(200)
        ->and($response->json())->toHaveKey('normalized', true);
});

test('method normalization works correctly for temporary responses', function () {
    $api = new ApiFake('https://api.fake.test');
    $api->clear();
    $api->getRouteResolver()->registerOperationMatcher('show', '/users/{id}', 'GET', 'users');
    $api->fake();

    // Add temporary response with lowercase method
    $api->addTemporaryResponse('/users/123', 'get', ['method' => 'normalized'], 1, 200);

    // Request with uppercase method should still match
    $response = Http::get('https://api.fake.test/users/123');
    expect($response->status())->toBe(200)
        ->and($response->json())->toHaveKey('method', 'normalized');
});
