<?php

declare(strict_types=1);

// すべてのリクエストはここを通る（public/.htaccess で転送）
require dirname(__DIR__) . '/src/bootstrap.php';

// POST はすべてここでCSRFトークンを確かめる（各画面で個別に書かない）
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !App\Csrf::check()) {
    exit;
}

$router = require dirname(__DIR__) . '/src/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
