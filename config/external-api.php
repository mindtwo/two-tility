<?php

use mindtwo\TwoTility\ExternalApiTokens\Eloquent\EloquentExternalApiTokenRepository;
use mindtwo\TwoTility\ExternalApiTokens\Redis\RedisExternalApiTokenRepository;

return [

    /**
     * Default repository implementation for external API tokens.
     */
    'defaults' => [
        // Optinal default api
        // 'api' => ''
        // Repository
        'repository' => 'eloquent'
    ],

    /**
     * Configuration for different external APIs.
     */
    'apis' => [
        // Example configuration:
        // 'google' => [
        //      // Optional - change repository
        //     'repository' => 'eloquent',
        //     'key_mapping' => [
        //         'access_token' => 'access_token',
        //         'refresh_token' => 'refresh_token',
        //         'expires_at' => 'expires_at',
        //         'expires_in' => 'expires_in',
        //         'refresh_token_valid_until' => 'refresh_token_valid_until',
        //     ],
        // ],
    ],

    'alias' => [

        'eloquent' => EloquentExternalApiTokenRepository::class,

        'redis' => RedisExternalApiTokenRepository::class,

    ],

];
