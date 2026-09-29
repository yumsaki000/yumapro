<?php

declare(strict_types=1);

namespace App;

/**
 * 管理画面のログイン。アカウントは個人ごと（admins テーブル）。
 */
final class Auth
{
    private const SESSION_ID = '_admin_id';
    private const SESSION_ACTIVITY = '_admin_last_activity';

    /** ログインIDの形（半角英数字と . _ -、3〜64文字）。作るときも照合するときも同じ */
    public const LOGIN_ID_PATTERN = '/\A[A-Za-z0-9._-]{3,64}\z/';

    /** admin_login_attempts.login_id の長さ */
    private const LOGIN_ID_MAX_LENGTH = 64;

    /** この分数のあいだに失敗が続いたら一時的にログインを止める */
    private const LOCK_MINUTES = 15;
    private const MAX_FAILURES_PER_LOGIN_ID = 5;
    private const MAX_FAILURES_PER_IP = 20;

    public const OK = 'ok';
    public const INVALID = 'invalid';
    public const LOCKED = 'locked';

    private static ?array $current = null;

    public static function isValidLoginId(string $loginId): bool
    {
        return preg_match(self::LOGIN_ID_PATTERN, $loginId) === 1;
    }

    /**
     * ログインを試す。OK / INVALID / LOCKED を返す。
     * IDがない・形が違う・無効・パスワード違いは区別しない（どれも INVALID）。
     */
    public static function attempt(string $loginId, string $password, string $ip): string
    {
        $pdo = Database::pdo();
        $pdo->exec('DELETE FROM admin_login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');

        // 記録と回数の照合に使うID。列の長さに合わせて切る（長すぎる入力でDBエラーにしない）
        $attemptId = mb_substr($loginId, 0, self::LOGIN_ID_MAX_LENGTH);
        if (self::isLocked($attemptId, $ip)) {
            return self::LOCKED;
        }

        $admin = null;
        if (self::isValidLoginId($loginId)) {
            $stmt = $pdo->prepare('SELECT id, password_hash, is_active FROM admins WHERE login_id = ?');
            $stmt->execute([$loginId]);
            $admin = $stmt->fetch() ?: null;
        }

        // IDがなくてもパスワード照合は行い、応答時間でIDの有無が分からないようにする
        $verified = password_verify($password, $admin['password_hash'] ?? self::dummyHash());
        $ok = $admin !== null && $verified && (int) $admin['is_active'] === 1;

        $pdo->prepare('INSERT INTO admin_login_attempts (login_id, ip_address, succeeded) VALUES (?, ?, ?)')
            ->execute([$attemptId, $ip, $ok ? 1 : 0]);
        if (!$ok) {
            return self::INVALID;
        }

        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
        }
        $pdo->prepare('DELETE FROM admin_login_attempts WHERE login_id = ? AND succeeded = 0')->execute([$attemptId]);
        $pdo->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$admin['id']]);

        Session::regenerate();
        Csrf::rotate();
        $_SESSION[self::SESSION_ID] = (int) $admin['id'];
        $_SESSION[self::SESSION_ACTIVITY] = time();
        self::$current = null;
        return self::OK;
    }

    /**
     * ログイン中の管理者。いなければ null。
     */
    public static function user(): ?array
    {
        if (self::$current !== null) {
            return self::$current;
        }
        Session::start();
        $id = $_SESSION[self::SESSION_ID] ?? null;
        if ($id === null) {
            return null;
        }
        if (time() - (int) ($_SESSION[self::SESSION_ACTIVITY] ?? 0) > Session::MAX_IDLE_SECONDS) {
            self::logout();
            Session::flash('notice', 'しばらく操作がなかったため、ログアウトしました。');
            return null;
        }

        $stmt = Database::pdo()->prepare(
            'SELECT id, login_id, display_name, role FROM admins WHERE id = ? AND is_active = 1'
        );
        $stmt->execute([$id]);
        $admin = $stmt->fetch() ?: null;
        if ($admin === null) {
            self::logout();
            return null;
        }
        $_SESSION[self::SESSION_ACTIVITY] = time();
        return self::$current = $admin;
    }

    /**
     * ログインが必要な画面の先頭で呼ぶ。未ログインならログイン画面へ移して終える。
     */
    public static function requireAdmin(): array
    {
        $admin = self::user();
        if ($admin === null) {
            redirect('/admin/login?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin'));
            exit;
        }
        header('Cache-Control: no-store');
        return $admin;
    }

    /**
     * オーナーだけができる操作の先頭で呼ぶ（運営メンバーの管理など）
     */
    public static function requireOwner(): array
    {
        $admin = self::requireAdmin();
        if ($admin['role'] !== 'owner') {
            abort_forbidden('この操作はオーナーだけができます。');
        }
        return $admin;
    }

    public static function logout(): void
    {
        Session::start();
        unset($_SESSION[self::SESSION_ID], $_SESSION[self::SESSION_ACTIVITY]);
        Session::regenerate();
        Csrf::rotate();
        self::$current = null;
    }

    private static function isLocked(string $loginId, string $ip): bool
    {
        $pdo = Database::pdo();
        $sql = 'SELECT COUNT(*) FROM admin_login_attempts
                WHERE %s = ? AND succeeded = 0 AND attempted_at > NOW() - INTERVAL ' . self::LOCK_MINUTES . ' MINUTE';

        $stmt = $pdo->prepare(sprintf($sql, 'login_id'));
        $stmt->execute([$loginId]);
        if ((int) $stmt->fetchColumn() >= self::MAX_FAILURES_PER_LOGIN_ID) {
            return true;
        }
        $stmt = $pdo->prepare(sprintf($sql, 'ip_address'));
        $stmt->execute([$ip]);
        return (int) $stmt->fetchColumn() >= self::MAX_FAILURES_PER_IP;
    }

    private static function dummyHash(): string
    {
        static $hash = null;
        return $hash ??= password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    }
}
