<?php

declare(strict_types=1);

use App\Config;
use App\View;

define('APP_ROOT', dirname(__DIR__));

// App\Foo\Bar → src/Foo/Bar.php
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = APP_ROOT . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_ROOT . '/src/helpers.php';

Config::load(APP_ROOT . '/.env');

date_default_timezone_set('Asia/Tokyo');
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', Config::bool('APP_DEBUG') ? '1' : '0');

if (PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'エラー: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (Config::bool('APP_DEBUG')) {
        echo '<pre>' . e((string) $e) . '</pre>';
        return;
    }
    echo View::render('errors/500', ['title' => 'エラーが発生しました']);
});
