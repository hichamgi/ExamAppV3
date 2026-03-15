<?php

namespace App\Core;

final class Config
{
    private static array $config = [];

    public static function load(): void
    {
        $path = dirname(__DIR__, 2) . '/config';

        foreach (glob($path . '/*.php') as $file) {

            $key = basename($file, '.php');

            self::$config[$key] = require $file;
        }
    }

    public static function get(string $key, $default = null)
    {
        $segments = explode('.', $key);

        $value = self::$config;

        foreach ($segments as $segment) {

            if (!isset($value[$segment])) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}