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

// システムの名前（船にちなむ）。掲示板＝DECK（甲板：参加者が集まる場所）、管理画面＝BRIDGE（船橋：運営が舵を取る場所）
define('APP_NAME', (string) Config::get('APP_NAME', 'MINATO DECK'));
define('ADMIN_NAME', (string) Config::get('ADMIN_NAME', 'BRIDGE'));

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
