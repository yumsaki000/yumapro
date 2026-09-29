<?php

declare(strict_types=1);

namespace App;

/**
 * セッション。必要になったとき（CSRF・ログイン）に始める。
 */
final class Session
{
    /** 操作がないまま、この秒数がたつとセッションを捨てる（当日受付で長く使うので長め） */
    public const MAX_IDLE_SECONDS = 8 * 60 * 60;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        // PHPの既定（24分）のままだと、8時間たつ前にサーバー側でセッションが消されることがある
        ini_set('session.gc_maxlifetime', (string) self::MAX_IDLE_SECONDS);
        // 古いセッションの掃除はこのアプリ自身で行う（サーバー側で掃除が止められていても残り続けないように）
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        // 共用サーバーでは、他のサイトと同じ場所に置くとそちらの掃除で消されることがあるので、自分の場所に置く
        $path = APP_ROOT . '/storage/sessions';
        if ((is_dir($path) || @mkdir($path, 0700, true)) && is_writable($path)) {
            ini_set('session.save_path', $path);
        }
        session_name('minato_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            // 本番（https）では https でしか送らない
            'secure' => str_starts_with((string) Config::get('APP_URL', ''), 'https://'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /**
     * ログイン・ログアウトのたびにセッションIDを取り替える（セッション固定攻撃の対策）
     */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    /**
     * 次の画面で1回だけ出すメッセージ。値を渡すと保存、渡さないと取り出して消す。
     */
    public static function flash(string $key, ?string $value = null): ?string
    {
        self::start();
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $stored = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $stored;
    }
}
