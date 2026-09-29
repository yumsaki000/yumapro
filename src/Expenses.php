<?php

declare(strict_types=1);

namespace App;

/**
 * 経費（expenses）と回ごとの収支。
 */
final class Expenses
{
    public static function forEvent(int $eventId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT x.*, a.display_name AS created_by_name FROM expenses x
             LEFT JOIN admins a ON a.id = x.created_by WHERE x.event_id = ? ORDER BY x.id'
        );
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM expenses WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function add(int $eventId, string $item, int $amount, ?string $memo, int $adminId): void
    {
        Database::pdo()->prepare('INSERT INTO expenses (event_id, item, amount, memo, created_by) VALUES (?, ?, ?, ?, ?)')
            ->execute([$eventId, $item, $amount, $memo, $adminId]);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM expenses WHERE id = ?')->execute([$id]);
    }

    /**
     * 回の収支。収入 = 前払いの入金確認済みの参加費 ＋ 当日受付の入金。収支 = 収入 − 経費 − 主催分。
     *
     * @return array{income_prepaid: int, income_onsite: int, income: int, expense_total: int, organizer_amount: int, balance: int, prepaid_count: int, onsite_count: int}
     */
    public static function summary(int $eventId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(r.fee), 0) AS amount, COUNT(*) AS cnt FROM registrations r
             WHERE r.event_id = ? AND r.status <> 'cancelled' AND r.prepaid_at IS NOT NULL"
        );
        $stmt->execute([$eventId]);
        $prepaid = $stmt->fetch();

        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(k.paid_amount), 0) AS amount, COUNT(k.paid_amount) AS cnt
             FROM registrations r JOIN checkins k ON k.registration_id = r.id WHERE r.event_id = ?'
        );
        $stmt->execute([$eventId]);
        $onsite = $stmt->fetch();

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE event_id = ?');
        $stmt->execute([$eventId]);
        $expenseTotal = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT organizer_amount FROM events WHERE id = ?');
        $stmt->execute([$eventId]);
        $organizer = (int) $stmt->fetchColumn();

        $income = (int) $prepaid['amount'] + (int) $onsite['amount'];
        return [
            'income_prepaid' => (int) $prepaid['amount'],
            'income_onsite' => (int) $onsite['amount'],
            'income' => $income,
            'expense_total' => $expenseTotal,
            'organizer_amount' => $organizer,
            'balance' => $income - $expenseTotal - $organizer,
            'prepaid_count' => (int) $prepaid['cnt'],
            'onsite_count' => (int) $onsite['cnt'],
        ];
    }
}
