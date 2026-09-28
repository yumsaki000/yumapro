<?php

declare(strict_types=1);

namespace App;

/**
 * セッション。必要になったとき（CSRF・ログイン）に始める。
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
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
