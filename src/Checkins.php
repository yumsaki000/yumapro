<?php

declare(strict_types=1);

namespace App;

/**
 * 当日受付（checkins）。1申込につき1行。到着と入金を同時に記録する。
 */
final class Checkins
{
    /**
     * 到着を記録する（すでにあれば上書き）。同じ人を2人が同時に操作しても1行にする。
     */
    public static function arrive(int $registrationId, ?int $paidAmount, ?string $paymentMethod, int $adminId, ?string $note = null): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, status FROM registrations WHERE id = ? FOR UPDATE');
            $stmt->execute([$registrationId]);
            $registration = $stmt->fetch() ?: null;
            if ($registration === null) {
                throw new \RuntimeException('申込がありません');
            }
            $pdo->prepare(
                'INSERT INTO checkins (registration_id, arrived_at, paid_amount, payment_method, checked_in_by, note)
                 VALUES (?, NOW(), ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    arrived_at = COALESCE(arrived_at, NOW()),
                    paid_amount = VALUES(paid_amount),
                    payment_method = VALUES(payment_method),
                    checked_in_by = VALUES(checked_in_by),
                    note = VALUES(note)'
            )->execute([$registrationId, $paidAmount, $paymentMethod, $adminId, $note]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** 到着の記録を取り消す（間違えて押したとき） */
    public static function undo(int $registrationId): void
    {
        Database::pdo()->prepare('DELETE FROM checkins WHERE registration_id = ?')->execute([$registrationId]);
    }
}
