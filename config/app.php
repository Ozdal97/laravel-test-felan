<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Laravel Test Felan'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    'providers' => [
        App\Providers\AppServiceProvider::class,
        App\Providers\RepositoryServiceProvider::class,
    ],
];
