<?php

declare(strict_types=1);

// すべてのリクエストはここを通る（public/.htaccess で転送）

// PHP の内蔵サーバー（php -S）で動かすときは、CSS や画像などの実在するファイルはそのまま返す
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/src/bootstrap.php';

// POST はすべてここでCSRFトークンを確かめる（各画面で個別に書かない）
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !App\Csrf::check()) {
    exit;
}

$router = require dirname(__DIR__) . '/src/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
