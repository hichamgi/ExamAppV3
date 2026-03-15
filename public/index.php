<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;
use App\Core\Env;

define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . '/storage');
define('CONFIG_PATH', BASE_PATH . '/config');

require BASE_PATH . '/vendor/autoload.php';

Env::load(BASE_PATH . '/.env');

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

Config::load();

require BASE_PATH . '/src/Core/helpers.php';

$app = new App();
$app->run();