<?php

declare(strict_types=1);

namespace App;

/**
 * クルー（月額のメンバー）。状態は顧客の項目（customers.crew_status）。募集ページからの申込は crew_applications。
 */
final class Crew
{
    public const STATUSES = ['none' => '未加入', 'applied' => '申込中', 'active' => '加入中', 'left' => '脱退'];
    public const STATUS_BADGES = ['none' => 'badge', 'applied' => 'badge badge--warn', 'active' => 'badge badge--ok', 'left' => 'badge'];
    public const CONTACT_PREFS = ['line' => '公式LINE', 'email' => 'メール', 'phone' => '電話'];

    public static function isActive(?array $customer): bool
    {
        return $customer !== null && ($customer['crew_status'] ?? 'none') === 'active';
    }

    /** クルーの名簿。$status は active / applied / left / all（未加入以外） */
    public static function list(string $status = 'active', string $q = ''): array
    {
        [$clause, $params] = Customers::searchClause($q);
        $where = $status === 'all' ? "c.crew_status <> 'none'" : 'c.crew_status = ?';
        if ($status !== 'all') {
            $params = array_merge([$status], $params);
        }
        if ($clause !== '') {
            $where .= " AND {$clause}";
        }
        $stmt = Database::pdo()->prepare(
            "SELECT c.* FROM customers c WHERE {$where}
             ORDER BY c.crew_joined_at DESC, c.name_kana IS NULL, c.name_kana, c.name"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return array<string, int> */
    public static function counts(): array
    {
        $counts = array_fill_keys(array_keys(self::STATUSES), 0);
        foreach (Database::pdo()->query('SELECT crew_status, COUNT(*) AS n FROM customers GROUP BY crew_status')->fetchAll() as $row) {
            $counts[$row['crew_status']] = (int) $row['n'];
        }
        return $counts;
    }

    public static function setStatus(int $customerId, string $status, ?string $joinedAt, ?string $leftAt, ?string $note): void
    {
        if (!isset(self::STATUSES[$status])) {
            throw new \InvalidArgumentException('クルーの状態が正しくありません');
        }
        if ($status === 'active' && $joinedAt === null) {
            $joinedAt = date('Y-m-d');
        }
        if ($status === 'left' && $leftAt === null) {
            $leftAt = date('Y-m-d');
        }
        if ($status === 'active') {
            $leftAt = null;
        }
        Database::pdo()->prepare('UPDATE customers SET crew_status = ?, crew_joined_at = ?, crew_left_at = ?, crew_note = ? WHERE id = ?')
            ->execute([$status, $joinedAt, $leftAt, $note, $customerId]);
    }

    /**
     * 募集ページからの申込。申込中のものがあればそれを返す。
     *
     * @return array{id: int, existing: bool}
     */
    public static function apply(int $customerId, array $answers): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("SELECT id FROM crew_applications WHERE customer_id = ? AND status = 'applied'");
        $stmt->execute([$customerId]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return ['id' => (int) $existing, 'existing' => true];
        }
        $pdo->prepare('INSERT INTO crew_applications (customer_id, answers, consented_at) VALUES (?, ?, NOW())')
            ->execute([$customerId, $answers === [] ? null : json_encode($answers, JSON_UNESCAPED_UNICODE)]);
        $pdo->prepare("UPDATE customers SET crew_status = 'applied' WHERE id = ? AND crew_status IN ('none', 'left')")->execute([$customerId]);
        return ['id' => (int) $pdo->lastInsertId(), 'existing' => false];
    }

    public static function applications(string $status = 'applied'): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.*, c.name AS customer_name, c.name_kana AS customer_kana, c.phone AS customer_phone, c.email AS customer_email,
                    c.crew_status, d.display_name AS decided_by_name
             FROM crew_applications a JOIN customers c ON c.id = a.customer_id LEFT JOIN admins d ON d.id = a.decided_by
             WHERE a.status = ? ORDER BY a.created_at DESC'
        );
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }

    public static function findApplication(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.*, c.name AS customer_name, c.email AS customer_email, c.access_token AS customer_token
             FROM crew_applications a JOIN customers c ON c.id = a.customer_id WHERE a.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** 申込を承認（クルーにする）／お断り（未加入に戻す） */
    public static function decide(int $applicationId, string $decision, int $adminId, ?string $note): ?array
    {
        $application = self::findApplication($applicationId);
        if ($application === null || !in_array($decision, ['approved', 'declined'], true)) {
            return null;
        }
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE crew_applications SET status = ?, decided_by = ?, decided_at = NOW(), note = ? WHERE id = ?')
            ->execute([$decision, $adminId, $note, $applicationId]);
        $customer = Customers::find((int) $application['customer_id']);
        if ($decision === 'approved') {
            self::setStatus((int) $application['customer_id'], 'active', $customer['crew_joined_at'] ?? null, null, $customer['crew_note'] ?? null);
        } elseif (($customer['crew_status'] ?? '') === 'applied') {
            self::setStatus((int) $application['customer_id'], 'none', null, null, $customer['crew_note'] ?? null);
        }
        return self::findApplication($applicationId);
    }
}
