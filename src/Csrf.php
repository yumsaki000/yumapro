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
     * 送った量がサーバーの上限（post_max_size）を超えると、PHP は中身を全部捨てる（トークンも消える）。
     * そのときは「写真が大きすぎる」と分かるように案内する
     */
    public static function postTooLarge(): bool
    {
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $limit = self::bytes((string) ini_get('post_max_size'));
        return $_POST === [] && $limit > 0 && $length > $limit;
    }

    /** "8M" のような php.ini の値をバイト数にする */
    public static function bytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;
        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1024 ** 3,
            'M' => $number * 1024 ** 2,
            'K' => $number * 1024,
            default => $number,
        };
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
        echo View::render('errors/csrf', ['title' => '送信できませんでした', 'tooLarge' => self::postTooLarge()]);
        return false;
    }
}
