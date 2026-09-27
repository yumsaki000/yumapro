<?php

declare(strict_types=1);

use App\Config;
use App\Database;
use App\Router;
use App\View;

$router = new Router();

// 公開トップ（準備中）
$router->get('/', function (): void {
    echo View::render('home', ['title' => 'MINATO イベント']);
});

// 動作確認用：アプリとDBがつながっているか
$router->get('/health', function (): void {
    try {
        Database::pdo()->query('SELECT 1');
        json_response(['app' => 'ok', 'db' => 'ok']);
    } catch (PDOException $e) {
        error_log((string) $e);
        $detail = Config::bool('APP_DEBUG') ? $e->getMessage() : 'error';
        json_response(['app' => 'ok', 'db' => $detail], 503);
    }
});

return $router;
