<?php

declare(strict_types=1);

namespace App;

/**
 * 出禁リスト。出禁は顧客の印（customers.banned_at）なので、ここでは一覧と確認待ちの申込を扱う。
 */
final class Bans
{
    public const CHECK_LABELS = [
        'suspect' => ['badge badge--warn', '要確認（出禁の人と同じ名前）'],
        'confirmed' => ['badge badge--danger', '出禁該当（連絡先が一致）'],
        'cleared' => ['badge', '確認済み（別人）'],
    ];

    /** 出禁の人の一覧（新しい順）。$q で絞り込める */
    public static function list(string $q = ''): array
    {
        [$clause, $params] = Customers::searchClause($q);
        $where = 'c.banned_at IS NOT NULL' . ($clause === '' ? '' : " AND {$clause}");
        $stmt = Database::pdo()->prepare(
            "SELECT c.*, a.display_name AS banned_by_name FROM customers c LEFT JOIN admins a ON a.id = c.banned_by
             WHERE {$where} ORDER BY c.banned_at DESC, c.id DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM customers WHERE banned_at IS NOT NULL')->fetchColumn();
    }

    /** 申込時の出禁チェックで印が付き、運営の確認を待っている申込（これからの回だけ） */
    public static function pendingReviews(): array
    {
        return Database::pdo()->query(
            "SELECT r.id, r.status, r.ban_check, r.applied_at, r.customer_id, c.name AS customer_name, c.name_kana AS customer_kana,
                    c.phone AS customer_phone, c.email AS customer_email, e.id AS event_id, e.title AS event_title, e.starts_at AS event_starts_at
             FROM registrations r JOIN customers c ON c.id = r.customer_id JOIN events e ON e.id = r.event_id
             WHERE r.ban_check IN ('suspect', 'confirmed') AND r.status <> 'cancelled' AND e.status IN ('draft', 'open', 'closed')
             ORDER BY r.applied_at DESC"
        )->fetchAll();
    }

    /** 出禁チェックの印を運営が判断した結果に変える */
    public static function setCheck(int $registrationId, string $value): void
    {
        if (!in_array($value, ['none', 'suspect', 'confirmed', 'cleared'], true)) {
            throw new \InvalidArgumentException('出禁チェックの値が正しくありません');
        }
        Database::pdo()->prepare('UPDATE registrations SET ban_check = ? WHERE id = ?')->execute([$value, $registrationId]);
    }

    /** 名前だけ一致した申込について、同じ名前の出禁の人（見比べる用） */
    public static function sameNameBanned(string $name, int $excludeCustomerId): array
    {
        return array_values(array_filter(
            Customers::findSameName($name, $excludeCustomerId),
            fn ($c) => $c['banned_at'] !== null
        ));
    }
}
