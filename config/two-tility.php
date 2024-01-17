<?php

return [

    /**
     * Options for cache helpers.
     */
    'cache' => [
        /**
         * Default cache key generator options used when no options are passed to cache_key() helper.
         */
        'default_options' => [],

        /**
         * Default events that will be listened to bust cache keys.
         */
        'bust_on' => [
            'updated',
            'deleted',
        ],

    ],

];
