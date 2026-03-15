<?php

return [

    'driver' => 'mysql',

    'host' => env('DB_HOST', '127.0.0.1'),

    'port' => (int) env('DB_PORT', 3306),

    'database' => env('DB_NAME', ''),

    'username' => env('DB_USER', ''),

    'password' => env('DB_PASS', ''),

    'charset' => env('DB_CHARSET', 'utf8mb4'),

    'options' => [

        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        PDO::ATTR_EMULATE_PREPARES => false,

        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"

    ],

];