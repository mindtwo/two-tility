<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use mindtwo\TwoTility\ExternalApiTokens\Eloquent\EloquentExternalApiTokenRepository;
use mindtwo\TwoTility\ExternalApiTokens\Eloquent\ExternalApiToken;
use mindtwo\TwoTility\ExternalApiTokens\ExternalApiTokens;
use mindtwo\TwoTility\Tests\Mock\User;

beforeEach(function () {
    config([
        'external-api.apis.test-api.repository' => 'eloquent',
        'external-api.apis.test-api.key_mapping' => [
            'access_token' => 'access_token',
            'refresh_token' => 'refresh_token',
            'expires_at' => 'expires_at',
            'expires_in' => 'expires_in',
            'refresh_token_valid_until' => 'refresh_token_valid_until',
        ],
    ]);

    Schema::create('users', function ($table) {
        $table->id();
        $table->string('email');
        $table->string('password');
        $table->timestamps();
    });

    Schema::create('external_api_tokens', function ($table) {
        $table->id();
        $table->morphs('authenticatable');
        $table->string('api_name');
        $table->text('token_data');
        $table->timestamp('valid_until')->nullable();
        $table->timestamps();
    });

    $this->user = User::create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);
});

afterEach(function () {
    Schema::dropIfExists('external_api_tokens');
    Schema::dropIfExists('users');
});

it('saves token with expires_at timestamp', function () {
    $expiresAt = now()->addHour();
    $tokenData = [
        'access_token' => 'test_access_token',
        'refresh_token' => 'test_refresh_token',
        'expires_at' => $expiresAt->toIso8601String(),
    ];

    $result = (repo())->save($this->user, $tokenData);

    expect($result)->toBeInstanceOf(ExternalApiToken::class)
        ->and($result->api_name)->toBe('test-api')
        ->and($result->token_data)->toBe($tokenData)
        ->and($result->valid_until->timestamp)->toBe($expiresAt->timestamp);
});

it('saves token with expires_in seconds', function () {
    $tokenData = [
        'access_token' => 'test_access_token',
        'expires_in' => 3600,
    ];

    $result = (repo())->save($this->user, $tokenData);

    expect($result)->toBeInstanceOf(ExternalApiToken::class)
        ->and($result->valid_until)->not->toBeNull()
        ->and($result->valid_until->isFuture())->toBeTrue();
});

it('throws exception when no expiration mapping configured', function () {
    $repository = new EloquentExternalApiTokenRepository('test-api', [
        'access_token' => 'access_token',
    ]);

    $tokenData = ['access_token' => 'test_token'];

    $repository->save($this->user, $tokenData);
})->throws(RuntimeException::class, 'No mapping configured for expires_at or expires_in');

it('retrieves current token data', function () {
    $tokenData = [
        'access_token' => 'test_access_token',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData);

    $current = (repo())->current($this->user);

    expect($current)->toBe($tokenData);
});

it('throws exception when retrieving non-existent token', function () {
    (repo())->current($this->user);
})->throws(RuntimeException::class, 'No token found for authenticatable');

it('invalidates existing token before saving new one', function () {
    $firstToken = [
        'access_token' => 'first_token',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $firstToken);

    $secondToken = [
        'access_token' => 'second_token',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $secondToken);

    $tokens = ExternalApiToken::query()
        ->forAuthenticatable($this->user)
        ->forApi('test-api')
        ->get();

    expect($tokens)->toHaveCount(2)
        ->and($tokens->first()->valid_until->isPast())->toBeTrue()
        ->and($tokens->last()->valid_until->isFuture())->toBeTrue();
});

it('invalidates token successfully', function () {
    $tokenData = [
        'access_token' => 'test_token',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData);

    $result = (repo())->invalidate($this->user);

    expect($result)->toBeTrue();

    $token = ExternalApiToken::query()
        ->forAuthenticatable($this->user)
        ->forApi('test-api')
        ->first();

    expect($token->valid_until->isPast())->toBeTrue();
});

it('returns false when invalidating non-existent token', function () {
    $result = (repo())->invalidate($this->user);

    expect($result)->toBeFalse();
});

it('checks if current token is valid', function () {
    $tokenData = [
        'access_token' => 'test_token',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData);

    $isValid = (repo())->isCurrentTokenValid($this->user);

    expect($isValid)->toBeTrue();
});

it('returns false for expired token', function () {
    $expiresAt = now()->subHour();
    $tokenData = [
        'access_token' => 'test_token',
        'expires_at' => $expiresAt->toIso8601String(),
    ];

    (repo())->save($this->user, $tokenData);

    $isValid = (repo())->isCurrentTokenValid($this->user);

    expect($isValid)->toBeFalse();
});

it('returns false when checking validity of non-existent token', function () {
    $isValid = (repo())->isCurrentTokenValid($this->user);

    expect($isValid)->toBeFalse();
});

it('checks if current token can be refreshed', function () {
    $tokenData = [
        'access_token' => 'test_token',
        'refresh_token' => 'refresh_token',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData);

    $canRefresh = (repo())->canRefreshCurrentToken($this->user);

    expect($canRefresh)->toBeTrue();
});

it('returns false when no refresh token available', function () {
    $tokenData = [
        'access_token' => 'test_token',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData);

    $canRefresh = (repo())->canRefreshCurrentToken($this->user);

    expect($canRefresh)->toBeFalse();
});

it('retrieves access token', function () {
    $tokenData = [
        'access_token' => 'test_access_token',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData);

    $accessToken = (repo())->accessToken($this->user);

    expect($accessToken)->toBe('test_access_token');
});

it('throws exception when retrieving access token from non-existent token', function () {
    (repo())->accessToken($this->user);
})->throws(RuntimeException::class, 'No token found for authenticatable');

it('throws exception when access token key not mapped', function () {
    $repository = new EloquentExternalApiTokenRepository('test-api', [
        'expires_in' => 'expires_in',
    ]);

    $tokenData = [
        'token' => 'test_token',
        'expires_in' => 3600,
    ];

    $repository->save($this->user, $tokenData);
    $repository->accessToken($this->user);
})->throws(RuntimeException::class, 'No mapping configured for access_token');

it('retrieves expires at timestamp', function () {
    $expiresAt = now()->addHour();
    $tokenData = [
        'access_token' => 'test_token',
        'expires_at' => $expiresAt->toIso8601String(),
    ];

    (repo())->save($this->user, $tokenData);

    $retrievedExpiresAt = (repo())->expiresAt($this->user);

    expect($retrievedExpiresAt)->toBeInstanceOf(Carbon::class)
        ->and($retrievedExpiresAt->timestamp)->toBe($expiresAt->timestamp);
});

it('throws exception when retrieving expires at from non-existent token', function () {
    (repo())->expiresAt($this->user);
})->throws(RuntimeException::class, 'No token found for authenticatable');

it('retrieves refresh token', function () {
    $tokenData = [
        'access_token' => 'test_access_token',
        'refresh_token' => 'test_refresh_token',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData);

    $refreshToken = (repo())->refreshToken($this->user);

    expect($refreshToken)->toBe('test_refresh_token');
});

it('returns null when no refresh token exists', function () {
    $tokenData = [
        'access_token' => 'test_access_token',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData);

    $refreshToken = (repo())->refreshToken($this->user);

    expect($refreshToken)->toBeNull();
});

it('retrieves refresh token valid until timestamp', function () {
    $validUntil = now()->addDay();
    $tokenData = [
        'access_token' => 'test_access_token',
        'refresh_token' => 'test_refresh_token',
        'refresh_token_valid_until' => $validUntil->toIso8601String(),
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData);

    $retrievedValidUntil = (repo())->refreshTokenValidUntil($this->user);

    expect($retrievedValidUntil)->toBeInstanceOf(Carbon::class)
        ->and($retrievedValidUntil->timestamp)->toBe($validUntil->timestamp);
});

it('returns null when no refresh token valid until timestamp exists', function () {
    $tokenData = [
        'access_token' => 'test_access_token',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData);

    $validUntil = (repo())->refreshTokenValidUntil($this->user);

    expect($validUntil)->toBeNull();
});

it('handles multiple users with separate tokens', function () {
    $user2 = User::create([
        'email' => 'user2@example.com',
        'password' => 'password',
    ]);

    $tokenData1 = [
        'access_token' => 'token_for_user1',
        'expires_in' => 3600,
    ];

    $tokenData2 = [
        'access_token' => 'token_for_user2',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData1);
    (repo())->save($user2, $tokenData2);

    expect((repo())->accessToken($this->user))->toBe('token_for_user1')
        ->and((repo())->accessToken($user2))->toBe('token_for_user2');
});

it('handles multiple APIs for same user', function () {
    config([
        'external-api.apis.another-api.repository' => 'eloquent',
        'external-api.apis.another-api.key_mapping' => config('external-api.apis.test-api.key_mapping'),
    ]);

    $repository2 = app(ExternalApiTokens::class)->repository('another-api');

    $tokenData1 = [
        'access_token' => 'token_for_test_api',
        'expires_in' => 3600,
    ];

    $tokenData2 = [
        'access_token' => 'token_for_another_api',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData1);
    $repository2->save($this->user, $tokenData2);

    expect((repo())->accessToken($this->user))->toBe('token_for_test_api')
        ->and($repository2->accessToken($this->user))->toBe('token_for_another_api');
});

it('caches retrieved token in current property', function () {
    $tokenData = [
        'access_token' => 'test_token',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData);

    // First call queries database
    $token1 = (repo())->accessToken($this->user);

    // Second call should use cached value
    $token2 = (repo())->accessToken($this->user);

    expect($token1)->toBe($token2)
        ->and($token1)->toBe('test_token');
});

it('refresh method returns false by default', function () {
    $tokenData = [
        'access_token' => 'test_token',
        'refresh_token' => 'refresh_token',
        'expires_in' => 3600,
    ];

    (repo())->save($this->user, $tokenData);

    $result = (repo())->refresh($this->user);

    expect($result)->toBeFalse();
});
