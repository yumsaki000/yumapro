<?php

declare(strict_types=1);

namespace App;

/**
 * 運営メンバー（管理画面のアカウント）。画面とコマンド（bin/create-admin.php）の両方から使う。
 */
final class Admins
{
    public const MIN_PASSWORD_LENGTH = 10;
    public const ROLES = ['owner' => 'オーナー', 'staff' => 'スタッフ'];

    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT id, login_id, display_name, role, is_active, last_login_at, created_at
             FROM admins ORDER BY is_active DESC, role, display_name'
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, login_id, display_name, role, is_active, last_login_at, created_at FROM admins WHERE id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByLoginId(string $loginId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, login_id, display_name, role, is_active, last_login_at, created_at FROM admins WHERE login_id = ?'
        );
        $stmt->execute([$loginId]);
        return $stmt->fetch() ?: null;
    }

    public static function loginIdExists(string $loginId): bool
    {
        return self::findByLoginId($loginId) !== null;
    }

    public static function countActiveOwners(): int
    {
        return (int) Database::pdo()->query("SELECT COUNT(*) FROM admins WHERE role = 'owner' AND is_active = 1")->fetchColumn();
    }

    /** 自動で作るパスワード（16文字） */
    public static function generatePassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
    }

    /** 名前・権限・パスワードの形を確かめる。問題があれば日本語のメッセージの配列 */
    public static function validate(string $loginId, string $displayName, string $role, ?string $password, bool $isNew): array
    {
        $errors = [];
        if ($isNew) {
            if (!Auth::isValidLoginId($loginId)) {
                $errors[] = 'ログインIDは半角英数字と . _ - の3〜64文字にしてください。';
            } elseif (self::loginIdExists($loginId)) {
                $errors[] = "ログインID「{$loginId}」はもう使われています。";
            }
        }
        if ($displayName === '' || mb_strlen($displayName) > 100) {
            $errors[] = '表示名は1〜100文字で入れてください。';
        }
        if (!isset(self::ROLES[$role])) {
            $errors[] = '権限はオーナーかスタッフにしてください。';
        }
        if ($password !== null && $password !== '' && mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $errors[] = 'パスワードは' . self::MIN_PASSWORD_LENGTH . '文字以上にしてください。';
        }
        return $errors;
    }

    public static function create(string $loginId, string $displayName, string $role, string $password): int
    {
        $pdo = Database::pdo();
        $pdo->prepare('INSERT INTO admins (login_id, password_hash, display_name, role) VALUES (?, ?, ?, ?)')
            ->execute([$loginId, password_hash($password, PASSWORD_DEFAULT), $displayName, $role]);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $displayName, string $role, bool $isActive): void
    {
        Database::pdo()->prepare('UPDATE admins SET display_name = ?, role = ?, is_active = ? WHERE id = ?')
            ->execute([$displayName, $role, $isActive ? 1 : 0, $id]);
    }

    /** パスワードを設定し、ログイン失敗の記録も消す（ロックを解く） */
    public static function setPassword(int $id, string $password): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        $pdo->prepare('DELETE FROM admin_login_attempts WHERE login_id = (SELECT login_id FROM admins WHERE id = ?)')
            ->execute([$id]);
    }
}
