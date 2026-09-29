<?php

declare(strict_types=1);

namespace App;

/**
 * 事後アンケート（1申込につき1回）。
 */
final class Surveys
{
    public static function find(int $registrationId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM surveys WHERE registration_id = ?');
        $stmt->execute([$registrationId]);
        return $stmt->fetch() ?: null;
    }

    public static function save(int $registrationId, int $satisfaction, string $intent, ?string $comment): void
    {
        Database::pdo()->prepare(
            'INSERT INTO surveys (registration_id, satisfaction, return_intent, comment) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE satisfaction = VALUES(satisfaction), return_intent = VALUES(return_intent), comment = VALUES(comment)'
        )->execute([$registrationId, $satisfaction, $intent, $comment]);
    }

    /**
     * 回ごとのまとめ。
     *
     * @return array{count: int, average: ?float, intents: array<string, int>, comments: list<array>}
     */
    public static function summaryForEvent(int $eventId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT s.satisfaction, s.return_intent, s.comment, s.created_at, c.name AS customer_name
             FROM surveys s JOIN registrations r ON r.id = s.registration_id JOIN customers c ON c.id = r.customer_id
             WHERE r.event_id = ? ORDER BY s.created_at'
        );
        $stmt->execute([$eventId]);
        $rows = $stmt->fetchAll();
        $intents = ['yes' => 0, 'maybe' => 0, 'no' => 0];
        $sum = 0;
        $comments = [];
        foreach ($rows as $row) {
            $sum += (int) $row['satisfaction'];
            $intents[$row['return_intent']] = ($intents[$row['return_intent']] ?? 0) + 1;
            if ($row['comment'] !== null && $row['comment'] !== '') {
                $comments[] = $row;
            }
        }
        return [
            'count' => count($rows),
            'average' => $rows === [] ? null : round($sum / count($rows), 1),
            'intents' => $intents,
            'comments' => $comments,
        ];
    }
}
