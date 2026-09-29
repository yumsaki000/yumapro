<?php

declare(strict_types=1);

namespace App;

/**
 * 集客の集計（窓口別・知った経路別・新規／リピート・男女）。要件の「10. 効果測定」に使う。
 */
final class Stats
{
    /** 「新規」＝この回より前に（キャンセル以外の）申込がない人 */
    private const IS_NEW = "NOT EXISTS (
        SELECT 1 FROM registrations r2 JOIN events e2 ON e2.id = r2.event_id
        WHERE r2.customer_id = r.customer_id AND r2.status <> 'cancelled' AND r2.id <> r.id
          AND (e2.starts_at < e.starts_at OR (e2.starts_at = e.starts_at AND e2.id < e.id)))";

    /**
     * 回ごとの集計（新しい順）。
     *
     * @return list<array>
     */
    public static function perEvent(int $limit = 30): array
    {
        $pdo = Database::pdo();
        $events = $pdo->query(
            "SELECT e.id, e.title, e.starts_at, e.status, e.capacity, t.name AS type_name
             FROM events e JOIN event_types t ON t.id = e.event_type_id
             WHERE e.status <> 'cancelled' ORDER BY e.starts_at DESC LIMIT " . (int) $limit
        )->fetchAll();
        if ($events === []) {
            return [];
        }
        $ids = array_map(fn ($e) => (int) $e['id'], $events);
        $in = implode(',', $ids);
        $rows = $pdo->query(
            "SELECT r.event_id, r.status, r.entry_from, r.channel, r.source, c.gender, (" . self::IS_NEW . ") AS is_new
             FROM registrations r JOIN events e ON e.id = r.event_id JOIN customers c ON c.id = r.customer_id
             WHERE r.event_id IN ({$in}) AND r.status <> 'cancelled'"
        )->fetchAll();

        $byEvent = [];
        foreach ($events as $event) {
            $byEvent[(int) $event['id']] = $event + [
                'applied' => 0, 'waitlisted' => 0, 'female' => 0, 'male' => 0, 'new' => 0, 'repeat' => 0,
                'by_from' => [], 'by_channel' => [], 'by_source' => [],
            ];
        }
        foreach ($rows as $row) {
            $e = &$byEvent[(int) $row['event_id']];
            $e[$row['status'] === 'waitlisted' ? 'waitlisted' : 'applied']++;
            if ($row['gender'] === 'female') {
                $e['female']++;
            } elseif ($row['gender'] === 'male') {
                $e['male']++;
            }
            $e[(int) $row['is_new'] === 1 ? 'new' : 'repeat']++;
            $from = $row['entry_from'] !== null ? self::entryLabel($row['entry_from']) : '（直接）';
            $e['by_from'][$from] = ($e['by_from'][$from] ?? 0) + 1;
            $channel = $row['channel'] ?? '（未回答）';
            $e['by_channel'][$channel] = ($e['by_channel'][$channel] ?? 0) + 1;
            $source = Registrations::SOURCES[$row['source']] ?? $row['source'];
            $e['by_source'][$source] = ($e['by_source'][$source] ?? 0) + 1;
            unset($e);
        }
        foreach ($byEvent as &$e) {
            arsort($e['by_from']);
            arsort($e['by_channel']);
            arsort($e['by_source']);
        }
        unset($e);
        return array_values($byEvent);
    }

    /**
     * 月ごとの集計（開催月。直近 n か月）。
     *
     * @return list<array{month: string, applied: int, new: int, repeat: int, female: int, events: int}>
     */
    public static function monthly(int $months = 12): array
    {
        $rows = Database::pdo()->query(
            "SELECT DATE_FORMAT(e.starts_at, '%Y-%m') AS month,
                COUNT(*) AS applied,
                SUM(CASE WHEN c.gender = 'female' THEN 1 ELSE 0 END) AS female,
                SUM(CASE WHEN " . self::IS_NEW . " THEN 1 ELSE 0 END) AS new_count,
                COUNT(DISTINCT e.id) AS events
             FROM registrations r JOIN events e ON e.id = r.event_id JOIN customers c ON c.id = r.customer_id
             WHERE r.status = 'applied' AND e.status <> 'cancelled'
               AND e.starts_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL " . ((int) $months - 1) . " MONTH)
             GROUP BY month ORDER BY month DESC"
        )->fetchAll();
        return array_map(fn ($r) => [
            'month' => $r['month'],
            'applied' => (int) $r['applied'],
            'new' => (int) $r['new_count'],
            'repeat' => (int) $r['applied'] - (int) $r['new_count'],
            'female' => (int) $r['female'],
            'events' => (int) $r['events'],
        ], $rows);
    }

    /**
     * 友だち招待のリンクから申し込んでもらった人数の多い順（キャンセルは数えない）
     *
     * @return list<array{id: int, name: string, count: int, last: string}>
     */
    public static function referrers(int $limit = 10): array
    {
        $rows = Database::pdo()->query(
            "SELECT rc.id, rc.name, COUNT(*) AS cnt, MAX(r.applied_at) AS last_at
             FROM registrations r JOIN customers rc ON rc.id = r.referrer_customer_id
             WHERE r.status <> 'cancelled'
             GROUP BY rc.id, rc.name ORDER BY cnt DESC, last_at DESC LIMIT " . (int) $limit
        )->fetchAll();
        return array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'count' => (int) $r['cnt'], 'last' => (string) $r['last_at']], $rows);
    }

    /** 窓口の表示名（?from= の値）。「次回のお知らせ」のメールから来た申込は follow */
    public static function entryLabel(string $key): string
    {
        return self::ENTRY_KEYS[$key] ?? ($key === 'follow' ? '次回のお知らせメール' : $key);
    }

    /** 窓口別リンクの候補（イベントの詳細に出す） */
    public const ENTRY_KEYS = [
        'line' => 'MINATO公式LINE',
        'site' => '公式サイト',
        'kokuchpro' => 'こくちーず',
        'instagram' => 'Instagram',
        'x' => 'X',
        'threads' => 'Threads',
    ];
}
