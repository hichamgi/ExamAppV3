<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Request;

if (!function_exists('request')) {
    function request(): Request
    {
        return Request::capture();
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $baseUrl = rtrim((string) Config::get('app.base_url', ''), '/');

        if ($path === '') {
            return $baseUrl !== '' ? $baseUrl : '/';
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        return base_url('assets/' . ltrim($path, '/'));
    }
}
