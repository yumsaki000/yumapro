<?php

declare(strict_types=1);

namespace App\Migration;

use App\Customers;
use App\Normalize;
use PDO;
use RuntimeException;

/**
 * 今のスプレッドシートの「出禁リスト」を顧客の出禁の印に移す（最初に1回だけ）。
 *
 * - 見出しは言葉で探す（氏名／電話／メール／SNS／理由／報告者／日付／スキップ）ので、列の並びは問わない
 * - 電話・メール・SNSが同じ顧客がいればその人に印を付け、いなければ名前が同じ人（1人だけのとき）、それもなければ新しく顧客を作る
 * - スキップ印（◯ など）が付いている行は印を付けない
 * - 使い道の決まっていない列は、経緯（ban_note）に「見出し: 値」の形で残す
 */
final class BanListImport
{
    private const KANA_WORDS = ['フリガナ', 'カタカナ', 'ふりがな'];

    /**
     * @param list<list<string>> $rows
     * @return array{records: list<array>, skipped: list<string>}
     */
    public static function parse(array $rows): array
    {
        [$headerIndex, $header] = self::findHeader($rows);
        $find = function (array $keywords, array $exclude = []) use ($header): ?int {
            foreach ($keywords as $keyword) {
                foreach ($header as $i => $label) {
                    if ($label === '' || !str_contains($label, $keyword)) {
                        continue;
                    }
                    foreach ($exclude as $word) {
                        if (str_contains($label, $word)) {
                            continue 2;
                        }
                    }
                    return $i;
                }
            }
            return null;
        };
        $col = [
            'name' => $find(['漢字', '氏名', '名前'], self::KANA_WORDS),
            'kana' => $find(self::KANA_WORDS),
            'phone' => $find(['電話']),
            'email' => $find(['メール']),
            'sns' => $find(['SNS', 'インスタ', 'Instagram', 'アカウント']),
            'reason' => $find(['理由', '内容', '経緯']),
            'reporter' => $find(['報告者', '登録者', '担当', '記入者']),
            'date' => $find(['日付', '日時', 'タイムスタンプ', '登録日']),
            'skip' => $find(['スキップ', '除外', '解除', '対象外']),
        ];
        if ($col['name'] === null) {
            throw new RuntimeException('出禁リストのCSVに名前の列が見つかりません');
        }
        $known = array_filter($col, fn ($i) => $i !== null);

        $records = [];
        $skipped = [];
        foreach (array_slice($rows, $headerIndex + 1, null, true) as $i => $row) {
            $get = fn (string $key) => $col[$key] === null ? '' : trim($row[$col[$key]] ?? '');
            $lineNo = $i + 1;
            $name = Normalize::name($get('name'));
            $phone = Normalize::phone($get('phone'));
            $email = Normalize::email($get('email'));
            $sns = Normalize::sns($get('sns'));
            if ($name === null && $phone === null && $email === null && $sns === null) {
                if (array_filter(array_map('trim', $row)) !== []) {
                    $skipped[] = "{$lineNo}行目: 名前も連絡先もないため読み飛ばしました";
                }
                continue;
            }
            $extra = [];
            foreach ($header as $c => $label) {
                $value = trim($row[$c] ?? '');
                if ($label !== '' && $value !== '' && !in_array($c, $known, true)) {
                    $extra[] = "{$label}: {$value}";
                }
            }
            $reporter = $get('reporter');
            $date = $get('date');
            $noteLines = array_filter(array_merge(
                $reporter !== '' ? ["報告者: {$reporter}"] : [],
                $date !== '' ? ["報告日: {$date}"] : [],
                $extra,
                ['（今のスプレッドシートの出禁リストから移行）']
            ));
            $records[] = [
                'line' => $lineNo,
                'name' => $name ?? '（名前なし）',
                'name_kana' => Normalize::kana($get('kana')),
                'phone' => $phone,
                'email' => $email,
                'sns_account' => $sns,
                'reason' => mb_substr($get('reason'), 0, 255) ?: null,
                'note' => implode("\n", $noteLines),
                'banned_at' => self::parseDate($date),
                'skip' => $get('skip') !== '',
            ];
        }
        return ['records' => $records, 'skipped' => $skipped];
    }

    /**
     * 各行をどう扱うかを決める（確認表示と書き込みで共通）。
     *
     * @return list<array{record: array, action: string, customer: ?array}>
     */
    public static function plan(array $records): array
    {
        $plan = [];
        foreach ($records as $record) {
            $customer = Customers::findMatches($record['phone'], $record['email'], $record['sns_account'])[0] ?? null;
            if ($customer === null && $record['name'] !== '（名前なし）') {
                $same = Customers::findSameName($record['name']);
                $customer = count($same) === 1 ? $same[0] : null;
            }
            if ($record['skip']) {
                $action = 'skip';
            } elseif ($customer !== null) {
                $action = $customer['banned_at'] !== null ? 'already' : 'mark';
            } else {
                $action = 'create';
            }
            $plan[] = ['record' => $record, 'action' => $action, 'customer' => $customer];
        }
        return $plan;
    }

    /**
     * @return array{mark: int, create: int, already: int, skip: int}
     */
    public static function commit(PDO $pdo, array $records, ?int $adminId): array
    {
        $counts = ['mark' => 0, 'create' => 0, 'already' => 0, 'skip' => 0];
        $pdo->beginTransaction();
        try {
            foreach (self::plan($records) as $item) {
                $r = $item['record'];
                $counts[$item['action']]++;
                if ($item['action'] === 'skip' || $item['action'] === 'already') {
                    continue;
                }
                $customerId = $item['customer'] !== null ? (int) $item['customer']['id'] : Customers::create([
                    'name' => $r['name'], 'name_kana' => $r['name_kana'], 'phone' => $r['phone'], 'email' => $r['email'],
                    'sns_account' => $r['sns_account'], 'gender' => null, 'line_name' => null, 'first_channel' => null,
                    'note' => null,
                ]);
                Customers::ban($customerId, $r['reason'], $r['note'], $adminId, $r['banned_at']);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $counts;
    }

    /** @return array{int, array<int, string>} */
    private static function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $i => $row) {
            $labels = array_map('trim', $row);
            foreach ($labels as $label) {
                if ($label !== '' && (str_contains($label, '氏名') || str_contains($label, '名前'))) {
                    return [$i, $labels];
                }
            }
        }
        throw new RuntimeException('出禁リストのCSVに「氏名」か「名前」の見出しが見つかりません');
    }

    private static function parseDate(string $value): ?string
    {
        $value = trim(mb_convert_kana($value, 'as'));
        if ($value === '') {
            return null;
        }
        $ts = strtotime(str_replace(['年', '月', '日'], ['/', '/', ''], $value));
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
    }
}
