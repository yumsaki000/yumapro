<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * 申込（registrations）。定員の判定は同時操作を前提に、トランザクション＋FOR UPDATE で行う。
 */
final class Registrations
{
    public const STATUSES = ['applied' => '申込', 'waitlisted' => 'キャンセル待ち', 'cancelled' => 'キャンセル'];
    public const STATUS_BADGES = ['applied' => 'badge badge--ok', 'waitlisted' => 'badge badge--warn', 'cancelled' => 'badge badge--danger'];
    public const PAYMENT_METHODS = ['cash' => '現金', 'bank_transfer' => '振込', 'paypay' => 'PayPay', 'other' => 'その他'];
    public const SOURCES = ['own_form' => '申込フォーム', 'manual' => '手入力', 'legacy' => '移行'];

    private const SELECT = 'SELECT r.*, c.name AS customer_name, c.name_kana AS customer_kana, c.gender AS customer_gender,
            c.phone AS customer_phone, c.email AS customer_email, c.access_token AS customer_token, c.banned_at AS customer_banned_at,
            k.id AS checkin_id, k.arrived_at, k.paid_amount, k.payment_method AS checkin_payment_method, k.note AS checkin_note,
            e.title AS event_title, e.slug AS event_slug, e.starts_at AS event_starts_at, e.ends_at AS event_ends_at,
            e.payment_timing AS event_payment_timing, e.status AS event_status, e.venue_name AS event_venue_name,
            e.venue_address AS event_venue_address, e.venue_url AS event_venue_url, e.cancel_policy AS event_cancel_policy,
            e.cancel_deadline AS event_cancel_deadline, e.capacity AS event_capacity
        FROM registrations r
        JOIN customers c ON c.id = r.customer_id
        JOIN events e ON e.id = r.event_id
        LEFT JOIN checkins k ON k.registration_id = r.id';

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE r.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** 回の申込一覧（申込 → キャンセル待ち → キャンセルの順、その中はフリガナ順） */
    public static function forEvent(int $eventId): array
    {
        $stmt = Database::pdo()->prepare(
            self::SELECT . " WHERE r.event_id = ?
             ORDER BY FIELD(r.status, 'applied', 'waitlisted', 'cancelled'), c.name_kana IS NULL, c.name_kana, c.name, r.id"
        );
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    /** 顧客の参加履歴（新しい順） */
    public static function forCustomer(int $customerId): array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE r.customer_id = ? ORDER BY e.starts_at DESC, r.id DESC');
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public static function findByEventAndCustomer(int $eventId, int $customerId): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE r.event_id = ? AND r.customer_id = ?');
        $stmt->execute([$eventId, $customerId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 申込を追加する。定員に達していればキャンセル待ちにする（$forceApply なら定員を超えても申込にする）。
     *
     * @param array{fee: int, channel: ?string, note: ?string, source: string, prepaid: bool, payment_method: ?string,
     *              entry_from?: ?string, ban_check?: string, consented_at?: ?string, answers?: ?string} $data
     * @param ?string $forceStatus 'waitlisted' を渡すと定員に関係なくキャンセル待ちにする（出禁の疑いがあるとき）
     * @return array{id: int, status: string}
     */
    public static function create(int $eventId, int $customerId, array $data, ?int $adminId, bool $forceApply = false, ?string $forceStatus = null): array
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $event = self::lockEvent($pdo, $eventId);
            $gender = self::customerGender($pdo, $customerId);
            $status = $forceStatus ?? ((!$forceApply && self::isFull($pdo, $event, $gender)) ? 'waitlisted' : 'applied');
            $pdo->prepare(
                'INSERT INTO registrations (event_id, customer_id, source, status, fee, channel, note, prepaid_at, payment_method,
                    entry_from, ban_check, consented_at, answers, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $eventId, $customerId, $data['source'], $status, $data['fee'], $data['channel'], $data['note'],
                $data['prepaid'] ? date('Y-m-d H:i:s') : null, $data['prepaid'] ? $data['payment_method'] : null,
                $data['entry_from'] ?? null, $data['ban_check'] ?? 'none', $data['consented_at'] ?? null, $data['answers'] ?? null,
                $adminId,
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return ['id' => $id, 'status' => $status];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array{fee: int, channel: ?string, note: ?string} $data */
    public static function update(int $id, array $data): void
    {
        Database::pdo()->prepare('UPDATE registrations SET fee = ?, channel = ?, note = ? WHERE id = ?')
            ->execute([$data['fee'], $data['channel'], $data['note'], $id]);
    }

    public static function cancel(int $id): void
    {
        Database::pdo()->prepare("UPDATE registrations SET status = 'cancelled', cancelled_at = NOW() WHERE id = ? AND status <> 'cancelled'")
            ->execute([$id]);
    }

    /**
     * キャンセルにして、空いた分だけキャンセル待ちの人を繰り上げる。
     *
     * @return list<array> 繰り上がった申込（メールを送るため）
     */
    public static function cancelAndPromote(int $id): array
    {
        $registration = self::find($id);
        if ($registration === null) {
            return [];
        }
        self::cancel($id);
        return $registration['status'] === 'applied' ? self::promoteWaitlist((int) $registration['event_id']) : [];
    }

    /**
     * 定員に空きがあるあいだ、キャンセル待ちの人を申込順に繰り上げる（出禁が確定している人は飛ばす）。
     *
     * @return list<array> 繰り上がった申込
     */
    public static function promoteWaitlist(int $eventId): array
    {
        $pdo = Database::pdo();
        $promoted = [];
        $pdo->beginTransaction();
        try {
            $event = self::lockEvent($pdo, $eventId);
            if (!in_array($event['status'], ['open', 'closed'], true)) {
                $pdo->commit();
                return [];
            }
            $stmt = $pdo->prepare(
                "SELECT r.id, c.gender FROM registrations r JOIN customers c ON c.id = r.customer_id
                 WHERE r.event_id = ? AND r.status = 'waitlisted' AND r.ban_check <> 'confirmed'
                 ORDER BY r.applied_at, r.id FOR UPDATE"
            );
            $stmt->execute([$eventId]);
            foreach ($stmt->fetchAll() as $row) {
                if (self::isFull($pdo, $event, $row['gender'])) {
                    continue;
                }
                $pdo->prepare("UPDATE registrations SET status = 'applied', cancelled_at = NULL WHERE id = ?")->execute([$row['id']]);
                $promoted[] = (int) $row['id'];
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return array_values(array_filter(array_map(fn ($id) => self::find($id), $promoted)));
    }

    /** キャンセル・キャンセル待ちから申込に戻す。定員に達していればキャンセル待ち（$forceApply なら申込） */
    public static function restore(int $id, bool $forceApply = false): string
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM registrations WHERE id = ? FOR UPDATE');
            $stmt->execute([$id]);
            $registration = $stmt->fetch() ?: null;
            if ($registration === null) {
                $pdo->rollBack();
                throw new \RuntimeException('申込がありません');
            }
            $event = self::lockEvent($pdo, (int) $registration['event_id']);
            $gender = self::customerGender($pdo, (int) $registration['customer_id']);
            $status = (!$forceApply && self::isFull($pdo, $event, $gender)) ? 'waitlisted' : 'applied';
            $pdo->prepare('UPDATE registrations SET status = ?, cancelled_at = NULL WHERE id = ?')->execute([$status, $id]);
            $pdo->commit();
            return $status;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** 前払いの入金確認を付ける／外す */
    public static function setPrepaid(int $id, bool $paid, ?string $method): void
    {
        $sql = $paid
            ? 'UPDATE registrations SET prepaid_at = COALESCE(prepaid_at, NOW()), payment_method = ? WHERE id = ?'
            : 'UPDATE registrations SET prepaid_at = NULL, payment_method = NULL WHERE id = ?';
        Database::pdo()->prepare($sql)->execute($paid ? [$method, $id] : [$id]);
    }

    /** 定員に達しているか（回の行をロックしたあとで呼ぶ） */
    public static function isFull(PDO $pdo, array $event, ?string $gender): bool
    {
        $capacity = Events::capacityFor($event, $gender);
        if ($capacity === null) {
            return false;
        }
        $useGender = $gender !== null && $event['capacity_' . $gender] !== null;
        $sql = "SELECT COUNT(*) FROM registrations r JOIN customers c ON c.id = r.customer_id
                WHERE r.event_id = ? AND r.status = 'applied'" . ($useGender ? ' AND c.gender = ?' : '');
        $stmt = $pdo->prepare($sql);
        $stmt->execute($useGender ? [$event['id'], $gender] : [$event['id']]);
        return (int) $stmt->fetchColumn() >= $capacity;
    }

    private static function lockEvent(PDO $pdo, int $eventId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ? FOR UPDATE');
        $stmt->execute([$eventId]);
        $event = $stmt->fetch() ?: null;
        if ($event === null) {
            throw new \RuntimeException('回がありません');
        }
        return $event;
    }

    private static function customerGender(PDO $pdo, int $customerId): ?string
    {
        $stmt = $pdo->prepare('SELECT gender FROM customers WHERE id = ?');
        $stmt->execute([$customerId]);
        $gender = $stmt->fetchColumn();
        return $gender === false ? null : $gender;
    }
}
