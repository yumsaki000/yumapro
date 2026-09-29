<?php

declare(strict_types=1);

namespace App;

/**
 * 参加者のログイン。パスワードは持たず、メールで届くリンク（30分・1回だけ）でログインする。
 * 個人専用URL（/my/{token}）を開いたときもログイン状態になる。
 */
final class CustomerAuth
{
    private const SESSION_KEY = '_customer_id';
    public const LINK_MINUTES = 30;
    private const MAX_LINKS_PER_15MIN = 5;

    private static ?array $current = null;
    private static bool $loaded = false;

    public static function current(): ?array
    {
        if (self::$loaded) {
            return self::$current;
        }
        self::$loaded = true;
        Session::start();
        $id = $_SESSION[self::SESSION_KEY] ?? null;
        self::$current = $id === null ? null : Customers::find((int) $id);
        if (self::$current === null) {
            unset($_SESSION[self::SESSION_KEY]);
        }
        return self::$current;
    }

    public static function login(int $customerId): void
    {
        Session::regenerate();
        $_SESSION[self::SESSION_KEY] = $customerId;
        self::$loaded = false;
    }

    public static function logout(): void
    {
        Session::start();
        unset($_SESSION[self::SESSION_KEY]);
        Session::regenerate();
        self::$loaded = false;
    }

    /** ログインが要るページの先頭で呼ぶ。未ログインならログイン画面へ */
    public static function requireLogin(): array
    {
        $customer = self::current();
        if ($customer === null) {
            redirect('/login?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/my'));
            exit;
        }
        return $customer;
    }

    /**
     * ログイン用のリンクを作る。メールアドレスの顧客がいなければ null（送らない）。
     *
     * @return array{customer: array, url: string}|null
     */
    public static function issueLink(string $email): ?array
    {
        $email = Normalize::email($email);
        if ($email === null) {
            return null;
        }
        $customer = Customers::findByPhoneOrEmail(null, $email);
        if ($customer === null) {
            return null;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_tokens WHERE customer_id = ? AND created_at > NOW() - INTERVAL 15 MINUTE');
        $stmt->execute([$customer['id']]);
        if ((int) $stmt->fetchColumn() >= self::MAX_LINKS_PER_15MIN) {
            return null;
        }
        $token = bin2hex(random_bytes(24));
        $pdo->prepare('INSERT INTO login_tokens (customer_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL ' . self::LINK_MINUTES . ' MINUTE)')
            ->execute([$customer['id'], hash('sha256', $token)]);
        $pdo->exec('DELETE FROM login_tokens WHERE expires_at < NOW() - INTERVAL 1 DAY');
        return ['customer' => $customer, 'url' => rtrim((string) Config::get('APP_URL', ''), '/') . '/login/' . $token];
    }

    /** リンクの文字列を確かめて使用済みにし、顧客を返す。使えなければ null */
    public static function consume(string $token): ?array
    {
        if (!preg_match('/\A[0-9a-f]{48}\z/', $token)) {
            return null;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, customer_id FROM login_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()');
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $pdo->prepare('UPDATE login_tokens SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
        return Customers::find((int) $row['customer_id']);
    }

    /** ログイン後に戻る先。このサイトの中のパスだけ許す */
    public static function safeNext(mixed $next): string
    {
        return is_string($next) && preg_match('#\A/(?![/\\\\])[A-Za-z0-9/_\-?=&%.]*\z#', $next) ? $next : '/my';
    }
}
