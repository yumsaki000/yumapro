<?php

declare(strict_types=1);

namespace App;

/**
 * 決まった時に送るメール（前日リマインド・翌日お礼）。cron から bin/send-mails.php で毎時流すか、管理画面から手で送る。
 * 送った申込は mail_log に残るので、何度流しても二度は送らない。
 */
final class MailJobs
{
    /**
     * 前日リマインド。$eventId を渡すとその回だけ（日付に関係なく）。
     *
     * @return array{sent: int, skipped: int}
     */
    public static function sendReminders(?int $eventId = null): array
    {
        $where = $eventId === null
            ? "e.status IN ('open', 'closed') AND DATE(e.starts_at) = CURDATE() + INTERVAL 1 DAY"
            : 'e.id = ' . (int) $eventId;
        return self::sendTo('reminder', "r.status = 'applied' AND {$where}");
    }

    /**
     * 翌日お礼。受付を使った回は到着した人だけ、使っていない回は申込の人全員。
     *
     * @return array{sent: int, skipped: int}
     */
    public static function sendThanks(?int $eventId = null): array
    {
        $where = $eventId === null
            ? "e.status IN ('open', 'closed', 'done') AND DATE(COALESCE(e.ends_at, e.starts_at)) = CURDATE() - INTERVAL 1 DAY"
            : 'e.id = ' . (int) $eventId;
        $attended = "(k.arrived_at IS NOT NULL OR NOT EXISTS (
            SELECT 1 FROM registrations r3 JOIN checkins k3 ON k3.registration_id = r3.id WHERE r3.event_id = e.id AND k3.arrived_at IS NOT NULL))";
        return self::sendTo('thanks', "r.status = 'applied' AND {$where} AND {$attended}");
    }

    /** @return array{sent: int, skipped: int} */
    private static function sendTo(string $kind, string $condition): array
    {
        $ids = Database::pdo()->query(
            "SELECT r.id FROM registrations r JOIN events e ON e.id = r.event_id JOIN customers c ON c.id = r.customer_id
             LEFT JOIN checkins k ON k.registration_id = r.id
             WHERE {$condition} AND c.email IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM mail_log m WHERE m.kind = '{$kind}' AND m.registration_id = r.id AND m.status = 'sent')
             ORDER BY r.id"
        )->fetchAll(\PDO::FETCH_COLUMN);
        $sent = 0;
        $skipped = 0;
        foreach ($ids as $id) {
            $registration = Registrations::find((int) $id);
            if ($registration !== null && MailTemplates::sendKind($kind, $registration)) {
                $sent++;
            } else {
                $skipped++;
            }
        }
        return ['sent' => $sent, 'skipped' => $skipped];
    }
}
