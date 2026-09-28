<?php

declare(strict_types=1);

use App\Auth;
use App\Config;
use App\Database;
use App\Router;
use App\Session;
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

// ── 管理画面 ─────────────────────────────────────────

$router->get('/admin/login', function (): void {
    if (Auth::user() !== null) {
        redirect('/admin');
        return;
    }
    header('Cache-Control: no-store');
    echo View::render('admin/login', [
        'title' => 'ログイン',
        'loginId' => '',
        'next' => safe_admin_path($_GET['next'] ?? null),
        'error' => Session::flash('error'),
        'notice' => Session::flash('notice'),
    ], 'admin/layout');
});

$router->post('/admin/login', function (): void {
    $loginId = trim((string) ($_POST['login_id'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $next = safe_admin_path($_POST['next'] ?? null);

    $result = ($loginId === '' || $password === '') ? Auth::INVALID : Auth::attempt($loginId, $password, client_ip());
    if ($result === Auth::OK) {
        redirect($next);
        return;
    }

    http_response_code($result === Auth::LOCKED ? 429 : 200);
    header('Cache-Control: no-store');
    echo View::render('admin/login', [
        'title' => 'ログイン',
        'loginId' => $loginId,
        'next' => $next,
        'error' => $result === Auth::LOCKED
            ? 'ログインに続けて失敗したため、しばらく止めています。15分ほどたってからお試しください。'
            : 'ログインIDまたはパスワードが違います。',
        'notice' => null,
    ], 'admin/layout');
});

$router->post('/admin/logout', function (): void {
    Auth::logout();
    Session::flash('notice', 'ログアウトしました。');
    redirect('/admin/login');
});

$router->get('/admin', function (): void {
    $admin = Auth::requireAdmin();
    echo View::render('admin/home', ['title' => '管理画面', 'admin' => $admin], 'admin/layout');
});

return $router;
