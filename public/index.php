<?php

declare(strict_types=1);

// すべてのリクエストはここを通る（public/.htaccess で転送）
require dirname(__DIR__) . '/src/bootstrap.php';

$router = require dirname(__DIR__) . '/src/routes.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
