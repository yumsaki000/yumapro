<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * 開催回（events）。
 */
final class Events
{
    public const STATUSES = [
        'draft' => '下書き',
        'open' => '募集中',
        'closed' => '締切',
        'done' => '終了',
        'cancelled' => '中止',
    ];

    public const STATUS_BADGES = [
        'draft' => 'badge',
        'open' => 'badge badge--ok',
        'closed' => 'badge badge--warn',
        'done' => 'badge badge--navy',
        'cancelled' => 'badge badge--danger',
    ];

    public const PAYMENT_TIMINGS = ['prepaid' => '前払い（事前振込）', 'onsite' => '当日払い'];

    /** フォームで受け取る項目（この順で保存する） */
    public const FIELDS = [
        'event_type_id', 'title', 'round_no', 'starts_at', 'ends_at',
        'venue_name', 'venue_address', 'venue_url',
        'capacity', 'capacity_male', 'capacity_female',
        'fee', 'fee_male', 'fee_female', 'fee_crew', 'payment_timing',
        'apply_deadline', 'cancel_deadline', 'cancel_policy',
        'organizer_amount', 'description', 'status',
    ];

    private const SELECT = 'SELECT e.*, t.name AS type_name, t.code AS type_code, t.expense_items AS type_expense_items,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = \'applied\') AS applied_count,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id AND r.status = \'waitlisted\') AS waitlisted_count,
            (SELECT COUNT(*) FROM registrations r JOIN checkins c ON c.registration_id = r.id
                WHERE r.event_id = e.id AND c.arrived_at IS NOT NULL) AS arrived_count
        FROM events e JOIN event_types t ON t.id = e.event_type_id';

    public static function types(): array
    {
        return Database::pdo()->query('SELECT * FROM event_types ORDER BY sort_order, id')->fetchAll();
    }

    public static function findType(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM event_types WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE e.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE e.slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    /** 掲示板に出す回（募集中と締切。今日以降、近い順） */
    public static function publicList(): array
    {
        return Database::pdo()->query(
            self::SELECT . " WHERE e.status IN ('open', 'closed') AND e.starts_at >= CURDATE() ORDER BY e.starts_at"
        )->fetchAll();
    }

    /** これからの回（今日以降。中止・終了は除く） */
    public static function upcoming(int $limit): array
    {
        $stmt = Database::pdo()->prepare(
            self::SELECT . " WHERE e.starts_at >= CURDATE() AND e.status IN ('draft', 'open', 'closed')
             ORDER BY e.starts_at LIMIT " . (int) $limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** 一覧。$scope は upcoming（今日以降・近い順）か past（昨日まで・新しい順） */
    public static function list(string $scope): array
    {
        $where = $scope === 'past' ? 'e.starts_at < CURDATE() ORDER BY e.starts_at DESC' : 'e.starts_at >= CURDATE() ORDER BY e.starts_at';
        return Database::pdo()->query(self::SELECT . ' WHERE ' . $where)->fetchAll();
    }

    /** 会計の一覧用：回ごとの収入・経費・収支（終了・締切・募集中の順に新しいものから） */
    public static function listWithBalance(): array
    {
        return Database::pdo()->query(
            'SELECT e.id, e.title, e.starts_at, e.status, e.organizer_amount, t.name AS type_name,
                (SELECT COALESCE(SUM(r.fee), 0) FROM registrations r
                    WHERE r.event_id = e.id AND r.status <> \'cancelled\' AND r.prepaid_at IS NOT NULL) AS income_prepaid,
                (SELECT COALESCE(SUM(c.paid_amount), 0) FROM registrations r JOIN checkins c ON c.registration_id = r.id
                    WHERE r.event_id = e.id) AS income_onsite,
                (SELECT COALESCE(SUM(x.amount), 0) FROM expenses x WHERE x.event_id = e.id) AS expense_total
             FROM events e JOIN event_types t ON t.id = e.event_type_id
             WHERE e.status <> \'cancelled\'
             ORDER BY e.starts_at DESC'
        )->fetchAll();
    }

    public static function create(array $data, int $adminId): int
    {
        $pdo = Database::pdo();
        $columns = array_merge(self::FIELDS, ['slug', 'created_by']);
        $sql = 'INSERT INTO events (' . implode(', ', $columns) . ') VALUES (' . rtrim(str_repeat('?, ', count($columns)), ', ') . ')';
        $values = array_map(fn ($f) => $data[$f] ?? null, self::FIELDS);
        $values[] = self::newSlug($pdo);
        $values[] = $adminId;
        $pdo->prepare($sql)->execute($values);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn ($f) => "{$f} = ?", self::FIELDS));
        $values = array_map(fn ($f) => $data[$f] ?? null, self::FIELDS);
        $values[] = $id;
        Database::pdo()->prepare("UPDATE events SET {$sets} WHERE id = ?")->execute($values);
    }

    /** 回を複製する（下書き・申込なし）。第n回は +1、日時はそのままなので、複製後に直す */
    public static function copy(int $id, int $adminId): int
    {
        $source = self::find($id);
        if ($source === null) {
            throw new \RuntimeException('複製元の回がありません');
        }
        $data = array_intersect_key($source, array_flip(self::FIELDS));
        $data['round_no'] = $source['round_no'] === null ? null : (int) $source['round_no'] + 1;
        $data['status'] = 'draft';
        $pdo = Database::pdo();
        $columns = array_merge(self::FIELDS, ['slug', 'created_by', 'copied_from_id']);
        $sql = 'INSERT INTO events (' . implode(', ', $columns) . ') VALUES (' . rtrim(str_repeat('?, ', count($columns)), ', ') . ')';
        $values = array_map(fn ($f) => $data[$f] ?? null, self::FIELDS);
        $values[] = self::newSlug($pdo);
        $values[] = $adminId;
        $values[] = $id;
        $pdo->prepare($sql)->execute($values);
        return (int) $pdo->lastInsertId();
    }

    public static function setStatus(int $id, string $status): void
    {
        if (!isset(self::STATUSES[$status])) {
            throw new \InvalidArgumentException('状態が正しくありません');
        }
        Database::pdo()->prepare('UPDATE events SET status = ? WHERE id = ?')->execute([$status, $id]);
    }

    public static function setOrganizerAmount(int $id, int $amount): void
    {
        Database::pdo()->prepare('UPDATE events SET organizer_amount = ? WHERE id = ?')->execute([$amount, $id]);
    }

    /** この人の参加費。クルー（加入中）でクルー料金があればそれ、次に男女別料金、それ以外は共通の料金 */
    public static function feeFor(array $event, ?string $gender, bool $isCrew = false): int
    {
        if ($isCrew && ($event['fee_crew'] ?? null) !== null) {
            return (int) $event['fee_crew'];
        }
        if ($gender === 'male' && $event['fee_male'] !== null) {
            return (int) $event['fee_male'];
        }
        if ($gender === 'female' && $event['fee_female'] !== null) {
            return (int) $event['fee_female'];
        }
        return (int) $event['fee'];
    }

    /** この性別の人に効く定員（男女別定員があればそれ、なければ合計）。NULL は上限なし */
    public static function capacityFor(array $event, ?string $gender): ?int
    {
        if ($gender === 'male' && $event['capacity_male'] !== null) {
            return (int) $event['capacity_male'];
        }
        if ($gender === 'female' && $event['capacity_female'] !== null) {
            return (int) $event['capacity_female'];
        }
        return $event['capacity'] === null ? null : (int) $event['capacity'];
    }

    /** 経費項目の候補（形式の設定＋この回で使った項目） */
    public static function expenseItemChoices(array $event): array
    {
        $items = json_decode((string) ($event['type_expense_items'] ?? '[]'), true);
        return is_array($items) ? array_values(array_filter($items, 'is_string')) : [];
    }

    private static function newSlug(PDO $pdo): string
    {
        $stmt = $pdo->prepare('SELECT 1 FROM events WHERE slug = ?');
        do {
            $slug = substr(bin2hex(random_bytes(8)), 0, 12);
            $stmt->execute([$slug]);
        } while ($stmt->fetchColumn());
        return $slug;
    }
}
