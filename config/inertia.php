<?php

return [

    'devtools' => [
        'enabled' => (bool) env('INERTIA_DEVTOOLS_ENABLED', false),
        'except' => ['login', 'logout', 'm/*', 'retirada*', 'entrega*', 'caixa/entregas/*', 'salao/mesas/*/qr*', 'broadcasting/auth', 'telescope*', 'horizon*', '_inertia/devtools*'],
        'storage' => [
            'path' => storage_path('inertia-devtools'),
            'ttl' => 24,
            'prune_interval' => 300,
            'limit' => 100,
        ],
        'middleware' => ['web'],
        'gate' => env('INERTIA_DEVTOOLS_GATE'),
        'redact' => [
            'keys' => [
                'password', 'password_confirmation', 'current_password',
                'pin', 'pin_confirmation', 'current_pin', 'pin_hash',
                'phone', 'address', 'contact', 'client_hash',
                'token', '_token', 'access_token', 'refresh_token',
                'secret', 'client_secret', 'api_key',
            ],
            'headers' => [
                'cookie', 'set-cookie', 'authorization', 'proxy-authorization',
                'x-xsrf-token', 'x-csrf-token',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render each initial request made to your application's pages
    | so that server rendered HTML is delivered for the user's browser.
    |
    | See: https://inertiajs.com/server-side-rendering
    |
    */

    'ssr' => [
        'enabled' => true,
        'url' => 'http://127.0.0.1:13714',
        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | These options configure how Inertia discovers page components on the
    | filesystem. The paths and extensions are used to locate components
    | when rendering responses and during testing assertions.
    |
    */

    'pages' => [

        'paths' => [
            resource_path('js/pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];
