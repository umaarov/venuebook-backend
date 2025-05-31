<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'api_version' => '1.0.0',
        'contact' => [
            'email' => 'hs.umarov21@gmail.com',
        ],
        'documentation' => url('/swagger'),
        'repo' => 'https://github.com/umaarov/venuebook-backend',
        'version' => '1.0.0',
        'framework' => 'Laravel',
        'framework_version' => app()->version(),
        'php_version' => PHP_VERSION,
        'environment' => app()->environment(),

        'app' => [
            'name' => config('app.name'),
            'url' => config('app.url'),
            'env' => config('app.env'),
            'locale' => config('app.locale'),
            'timezone' => config('app.timezone'),
        ],

        'server' => [
            'time' => now()->toIso8601String(),
        ],

        'security' => [
            'authentication' => 'Bearer Token',
        ],

        'test' => [
            'status' => 'ok',
            'message' => 'Test route is working! ..',
        ],
    ]);
});

