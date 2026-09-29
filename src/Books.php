<?php

declare(strict_types=1);

namespace App;

/**
 * 確定申告向けの帳簿：年（会計年度）ごとの売上・経費の明細と、月別・勘定科目別のまとめ。
 *
 * 売上の日付はイベントの日（参加費はイベントを開いた日の売上）。講座は入金を確認した日。
 * 経費の日付は支払った日（空ならイベントの日）。中止のイベントの参加費は売上に入れない（返金する前提）。
 */
final class Books
{
    /**
     * 会計年度の期間。始まりの月は設定の fiscal_year_start_month（1 なら 1月〜12月＝個人の確定申告）
     *
     * @return array{year: int, from: string, to: string, label: string}
     */
    public static function period(int $year): array
    {
        $month = self::startMonth();
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = date('Y-m-d', strtotime($from . ' +1 year'));
        $label = $month === 1
            ? "{$year}年（1月〜12月）"
            : sprintf('%d年度（%d年%d月〜%d年%d月）', $year, $year, $month, $year + 1, $month === 1 ? 12 : $month - 1);
        return ['year' => $year, 'from' => $from, 'to' => $to, 'label' => $label];
    }

    /** 今日が入っている会計年度 */
    public static function currentYear(): int
    {
        $year = (int) date('Y');
        return (int) date('n') >= self::startMonth() ? $year : $year - 1;
    }

    /** @return list<int> 選べる年（イベント・経費・講座の入金がある年と今年。新しい順） */
    public static function years(): array
    {
        $dates = Database::pdo()->query(
            "SELECT MIN(d) FROM (
                SELECT MIN(DATE(starts_at)) AS d FROM events
                UNION ALL SELECT MIN(COALESCE(paid_on, DATE(created_at))) FROM expenses
                UNION ALL SELECT MIN(DATE(paid_at)) FROM course_purchases
             ) t"
        )->fetchColumn();
        $current = self::currentYear();
        $first = $current;
        if (is_string($dates) && $dates !== '') {
            $first = self::yearOf($dates);
        }
        $last = max($current, self::yearOf((string) (Database::pdo()->query('SELECT MAX(DATE(starts_at)) FROM events')->fetchColumn() ?: date('Y-m-d'))));
        return range($last, min($first, $current));
    }

    /** その日が入っている会計年度 */
    public static function yearOf(string $date): int
    {
        $time = strtotime($date);
        $year = (int) date('Y', $time);
        return (int) date('n', $time) >= self::startMonth() ? $year : $year - 1;
    }

    /**
     * 売上の明細（日付の順）
     *
     * @return list<array{date: string, kind: string, subject: string, name: string, method: string, received_at: ?string, amount: int}>
     */
    public static function sales(string $from, string $to): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT DATE(e.starts_at) AS sold_on, 'イベント参加費（前払い）' AS kind, e.title AS subject, c.name AS customer_name,
                    r.fee AS amount, r.payment_method AS method, r.prepaid_at AS received_at
             FROM registrations r JOIN events e ON e.id = r.event_id JOIN customers c ON c.id = r.customer_id
             WHERE r.status <> 'cancelled' AND r.prepaid_at IS NOT NULL AND e.status <> 'cancelled'
               AND e.starts_at >= ? AND e.starts_at < ?
             UNION ALL
             SELECT DATE(e.starts_at), 'イベント参加費（当日）', e.title, c.name, k.paid_amount, k.payment_method, k.arrived_at
             FROM registrations r JOIN checkins k ON k.registration_id = r.id JOIN events e ON e.id = r.event_id JOIN customers c ON c.id = r.customer_id
             WHERE k.paid_amount IS NOT NULL AND k.paid_amount > 0 AND e.status <> 'cancelled'
               AND e.starts_at >= ? AND e.starts_at < ?
             UNION ALL
             SELECT DATE(p.paid_at), '講座', co.title, c.name, p.amount, p.payment_method, p.paid_at
             FROM course_purchases p JOIN courses co ON co.id = p.course_id JOIN customers c ON c.id = p.customer_id
             WHERE p.status = 'paid' AND p.paid_at >= ? AND p.paid_at < ?
             ORDER BY sold_on, subject, kind"
        );
        $stmt->execute([$from, $to, $from, $to, $from, $to]);
        $rows = [];
        foreach ($stmt->fetchAll() as $r) {
            $rows[] = [
                'date' => (string) $r['sold_on'],
                'kind' => (string) $r['kind'],
                'subject' => (string) $r['subject'],
                'name' => (string) $r['customer_name'],
                'method' => Registrations::PAYMENT_METHODS[$r['method'] ?? ''] ?? (string) ($r['method'] ?? ''),
                'received_at' => $r['received_at'] !== null ? (string) $r['received_at'] : null,
                'amount' => (int) $r['amount'],
            ];
        }
        return $rows;
    }

    /**
     * 期間の主催分（イベントごと。イベントの日の順）
     *
     * @return list<array{date: string, title: string, amount: int}>
     */
    public static function organizerAmounts(string $from, string $to): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT DATE(starts_at) AS d, title, organizer_amount FROM events
             WHERE organizer_amount > 0 AND status <> 'cancelled' AND starts_at >= ? AND starts_at < ? ORDER BY starts_at"
        );
        $stmt->execute([$from, $to]);
        return array_map(fn ($r) => ['date' => (string) $r['d'], 'title' => (string) $r['title'], 'amount' => (int) $r['organizer_amount']], $stmt->fetchAll());
    }

    /**
     * 1年分のまとめ：合計・月別・勘定科目別
     *
     * @return array{sales: int, sales_event: int, sales_course: int, expenses: int, organizer: int, balance: int,
     *               months: array<string, array{sales_event: int, sales_course: int, expenses: int, organizer: int}>,
     *               accounts: array<string, int>, no_receipt: int}
     */
    public static function summary(array $period): array
    {
        $months = [];
        $cursor = $period['from'];
        while ($cursor < $period['to']) {
            $months[substr($cursor, 0, 7)] = ['sales_event' => 0, 'sales_course' => 0, 'expenses' => 0, 'organizer' => 0];
            $cursor = date('Y-m-d', strtotime($cursor . ' +1 month'));
        }
        $accounts = array_fill_keys(array_keys(Expenses::ACCOUNTS), 0);
        $noReceipt = 0;
        foreach (self::sales($period['from'], $period['to']) as $s) {
            $key = $s['kind'] === '講座' ? 'sales_course' : 'sales_event';
            $months[substr($s['date'], 0, 7)][$key] += $s['amount'];
        }
        foreach (Expenses::forPeriod($period['from'], $period['to']) as $x) {
            $months[substr((string) $x['spent_on'], 0, 7)]['expenses'] += (int) $x['amount'];
            $account = (string) ($x['account'] ?? '');
            $account = $account !== '' ? $account : Expenses::guessAccount((string) $x['item']);
            $accounts[$account] = ($accounts[$account] ?? 0) + (int) $x['amount'];
            if (!$x['has_receipt']) {
                $noReceipt++;
            }
        }
        foreach (self::organizerAmounts($period['from'], $period['to']) as $o) {
            $months[substr($o['date'], 0, 7)]['organizer'] += $o['amount'];
        }
        $salesEvent = array_sum(array_column($months, 'sales_event'));
        $salesCourse = array_sum(array_column($months, 'sales_course'));
        $expenses = array_sum(array_column($months, 'expenses'));
        $organizer = array_sum(array_column($months, 'organizer'));
        return [
            'sales' => $salesEvent + $salesCourse,
            'sales_event' => $salesEvent,
            'sales_course' => $salesCourse,
            'expenses' => $expenses,
            'organizer' => $organizer,
            'balance' => $salesEvent + $salesCourse - $expenses - $organizer,
            'months' => $months,
            'accounts' => array_filter($accounts, fn ($v) => $v !== 0),
            'no_receipt' => $noReceipt,
        ];
    }

    private static function startMonth(): int
    {
        $month = (int) Settings::get('fiscal_year_start_month');
        return $month >= 1 && $month <= 12 ? $month : 1;
    }
}
