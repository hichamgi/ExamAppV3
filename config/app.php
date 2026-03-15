<?php

return [

    'name' => env('APP_NAME', 'ExamAppV3'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'url' => env('APP_URL', 'http://localhost'),
    'base_url' => env('BASE_URL', '/'),

    'session' => [
        'student_timeout' => (int) env('SESSION_STUDENT_TIMEOUT', 90),
        'admin_timeout' => (int) env('SESSION_ADMIN_TIMEOUT', 240),
        'heartbeat_interval' => (int) env('SESSION_HEARTBEAT_INTERVAL', 30),
    ],

    'paths' => [
        'cache' => env('CACHE_PATH', 'storage/cache'),
        'logs' => env('LOG_PATH', 'storage/logs'),
        'pdf' => env('PDF_PATH', 'storage/pdf'),
    ],

];
