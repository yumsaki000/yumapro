<?php

declare(strict_types=1);

namespace App;

/**
 * CSRF対策。POSTはすべて public/index.php でトークンを確かめる。
 * フォームには <?= csrf_field() ?> を入れるだけでよい。
 */
final class Csrf
{
    private const KEY = '_csrf';
    public const FIELD = '_csrf';

    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function validate(mixed $token): bool
    {
        Session::start();
        $expected = $_SESSION[self::KEY] ?? '';
        return is_string($token) && $expected !== '' && hash_equals($expected, $token);
    }

    /**
     * ログイン・ログアウト時にトークンを取り替える
     */
    public static function rotate(): void
    {
        Session::start();
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }

    /**
     * POSTのトークンを確かめる。合わなければ 403 の画面を出して false を返す。
     * （419 は標準のステータスコードではなく、Apache が 500 に変えてしまうため使わない）
     */
    public static function check(): bool
    {
        $token = $_POST[self::FIELD] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (self::validate($token)) {
            return true;
        }
        http_response_code(403);
        echo View::render('errors/csrf', ['title' => '送信できませんでした']);
        return false;
    }
}
