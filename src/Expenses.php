<?php

declare(strict_types=1);

namespace App;

/**
 * 経費（expenses）と回ごとの収支。
 */
final class Expenses
{
    /**
     * 勘定科目（確定申告の区分）と、どんな支払いに使うかの目安。
     * 青色申告決算書・収支内訳書の科目に合わせている。最終的な区分は税理士さん・会計ソフトで確かめる
     */
    public const ACCOUNTS = [
        '仕入高' => '参加者の飲食代・宿泊代など、イベントの中身として払うもの（店への支払い）',
        '賃借料' => '会場・スペース・備品のレンタル',
        '外注費' => '講師料・撮影・運営の手伝いへの謝礼',
        '旅費交通費' => '電車・バス・タクシー・ガソリン・駐車場、スタッフの宿泊',
        '広告宣伝費' => 'SNS広告・チラシ・こくちーずの有料掲載',
        '消耗品費' => '備品・文具・資料の印刷・10万円未満の道具',
        '通信費' => 'サーバー・ドメイン・公式LINE・切手',
        '会議費' => '打ち合わせの飲食',
        '接待交際費' => '取引先・協力者への手土産や会食',
        '支払手数料' => '振込手数料・決済手数料',
        '雑費' => 'どれにも当てはまらないもの',
    ];

    /** 項目の名前から勘定科目の見当を付ける（選ばれていないとき）。当てはまらなければ雑費 */
    public static function guessAccount(string $item): string
    {
        $rules = [
            '旅費交通費' => ['交通', '電車', 'バス', 'タクシー', 'ガソリン', '駐車', '高速'],
            '外注費' => ['講師', '謝礼', '外注', '撮影', 'カメラマン'],
            '賃借料' => ['会場', 'レンタル', 'スペース', '貸切'],
            '広告宣伝費' => ['広告', 'チラシ', 'ポスター', '掲載'],
            '支払手数料' => ['手数料'],
            '通信費' => ['サーバー', 'ドメイン', '通信', 'LINE', '切手'],
            '仕入高' => ['宿泊', '食費', '食事', '飲食', '店', 'ケータリング', '料理', 'ドリンク'],
            '消耗品費' => ['備品', '文具', '資料', '印刷', '消耗'],
            '会議費' => ['打ち合わせ', '打合せ', '会議'],
        ];
        foreach ($rules as $account => $words) {
            foreach ($words as $word) {
                if (mb_stripos($item, $word) !== false) {
                    return $account;
                }
            }
        }
        return '雑費';
    }

    public static function forEvent(int $eventId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT x.*, a.display_name AS created_by_name FROM expenses x
             LEFT JOIN admins a ON a.id = x.created_by WHERE x.event_id = ? ORDER BY x.id'
        );
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    /**
     * 期間の経費の明細（支払った日の順）。支払った日が空なら、イベントの日を使う。
     * 中止のイベントの経費も入れる（払ったお金は経費のため）
     *
     * @param string $from 期間の始め（この日を含む。Y-m-d）
     * @param string $to   期間の終わり（この日を含まない）
     */
    public static function forPeriod(string $from, string $to): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM (
                SELECT x.*, e.title AS event_title, e.starts_at AS event_starts_at, a.display_name AS created_by_name,
                    COALESCE(x.paid_on, DATE(e.starts_at), DATE(x.created_at)) AS spent_on
                FROM expenses x LEFT JOIN events e ON e.id = x.event_id LEFT JOIN admins a ON a.id = x.created_by
             ) t WHERE t.spent_on >= ? AND t.spent_on < ? ORDER BY t.spent_on, t.id"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM expenses WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * 経費を追加する。$eventId が null ならイベントに付かない経費。
     *
     * @param array{item: string, amount: int, account: ?string, paid_on: ?string, payee: ?string, has_receipt: bool, memo: ?string} $data
     */
    public static function add(?int $eventId, array $data, int $adminId): void
    {
        Database::pdo()->prepare(
            'INSERT INTO expenses (event_id, item, amount, account, paid_on, payee, has_receipt, memo, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $eventId, $data['item'], $data['amount'], $data['account'] ?? self::guessAccount($data['item']),
            $data['paid_on'], $data['payee'], $data['has_receipt'] ? 1 : 0, $data['memo'], $adminId,
        ]);
    }

    /** @param array{item: string, amount: int, account: ?string, paid_on: ?string, payee: ?string, has_receipt: bool, memo: ?string} $data */
    public static function update(int $id, array $data): void
    {
        Database::pdo()->prepare(
            'UPDATE expenses SET item = ?, amount = ?, account = ?, paid_on = ?, payee = ?, has_receipt = ?, memo = ? WHERE id = ?'
        )->execute([
            $data['item'], $data['amount'], $data['account'] ?? self::guessAccount($data['item']),
            $data['paid_on'], $data['payee'], $data['has_receipt'] ? 1 : 0, $data['memo'], $id,
        ]);
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
