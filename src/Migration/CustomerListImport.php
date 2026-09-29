<?php

declare(strict_types=1);

namespace App\Migration;

use App\Normalize;
use PDO;
use RuntimeException;

/**
 * 今のスプレッドシートの「声掛けリスト」を顧客（customers）に移す。
 *
 * - 名前・フリガナ・性別・集客媒体・備考は顧客の項目に入れる
 * - 使い道が決まっていない列（紹介者・仕事内容・クルー・わくMe など）も含め、元の行は legacy_data にそのまま残す
 * - 「フォームの回答」から、同じ名前の人に電話番号・メール・SNSを付ける
 * - 同じ名前・同じ電話番号の人は、自動でまとめずに候補として出す（名寄せのルール：docs/data-intake.md）
 * - 声掛けリストの「番号」をキーにするので、何度流しても顧客は増えない
 */
final class CustomerListImport
{
    /** 声掛けリストの見出し（別名も含む） → 顧客の項目 */
    private const LIST_COLUMNS = [
        'legacy_no' => ['番号', 'No', 'NO'],
        'name' => ['名前', '氏名'],
        'kana' => ['カタカナ', 'フリガナ', 'ふりがな'],
        'gender' => ['性別'],
        'first_channel' => ['集客媒体'],
        'note' => ['備考', '備考欄'],
    ];

    private const GENDERS = ['男性' => 'male', '男' => 'male', '女性' => 'female', '女' => 'female'];

    /**
     * 声掛けリストのCSVの行 → 顧客のレコード
     *
     * @param list<list<string>> $rows
     * @return array{records: list<array<string, mixed>>, skipped: list<string>}
     */
    public static function parseList(array $rows): array
    {
        [$headerIndex, $columns, $header] = self::findHeader($rows);

        $records = [];
        $skipped = [];
        $seenNo = [];
        foreach (array_slice($rows, $headerIndex + 1, null, true) as $i => $row) {
            $cell = fn (string $key) => isset($columns[$key]) ? trim($row[$columns[$key]] ?? '') : '';
            $name = Normalize::name($cell('name'));
            if ($name === null) {
                continue;
            }
            $lineNo = $i + 1;
            $no = $cell('legacy_no');
            if (!is_numeric($no) || (int) $no <= 0) {
                $skipped[] = "{$lineNo}行目: 番号がないため読み飛ばしました";
                continue;
            }
            $no = (int) $no;
            if (isset($seenNo[$no])) {
                $skipped[] = "{$lineNo}行目: 番号{$no}が重複しているため読み飛ばしました";
                continue;
            }
            $seenNo[$no] = true;

            $legacy = [];
            foreach ($header as $col => $label) {
                $value = trim($row[$col] ?? '');
                if ($label !== '' && $value !== '') {
                    $legacy[$label] = $value;
                }
            }

            $records[] = [
                'legacy_no' => $no,
                'name' => $name,
                'name_kana' => Normalize::kana($cell('kana')),
                'gender' => self::GENDERS[$cell('gender')] ?? null,
                'first_channel' => $cell('first_channel') ?: null,
                'note' => $cell('note') ?: null,
                'phone' => null,
                'email' => null,
                'sns_account' => null,
                'legacy_data' => $legacy,
            ];
        }
        return ['records' => $records, 'skipped' => $skipped];
    }

    /**
     * フォームの回答のCSVの行 → 名前ごとの連絡先（新しい回答を優先）
     *
     * @param list<list<string>> $rows
     * @return array<string, array{name: string, kana: string, phone: ?string, email: ?string, sns: ?string}>
     */
    public static function parseResponses(array $rows): array
    {
        $header = array_map('trim', $rows[0] ?? []);
        // 言葉の順に優先して探す（列の並びには頼らない）。$exclude を含む見出しは除く
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
        $kanaWords = ['フリガナ', 'カタカナ', 'ふりがな'];
        $col = [
            // 「お名前（カタカナ）」を名前の列と取り違えない
            'name' => $find(['漢字', '氏名', '名前'], $kanaWords),
            'kana' => $find($kanaWords),
            'phone' => $find(['電話']),
            'email' => $find(['メールアドレス', 'メール']),
            'sns' => $find(['SNS']),
        ];
        if ($col['name'] === null) {
            throw new RuntimeException('フォームの回答のCSVに名前の列が見つかりません');
        }

        $people = [];
        // 下の行ほど新しいので、上から順に上書きしていく
        foreach (array_slice($rows, 1) as $row) {
            $get = fn (string $key) => $col[$key] === null ? '' : trim($row[$col[$key]] ?? '');
            $name = Normalize::name($get('name'));
            if ($name === null) {
                continue;
            }
            $key = Normalize::matchKey($name);
            $current = $people[$key] ?? ['name' => $name, 'kana' => '', 'phone' => null, 'email' => null, 'sns' => null];
            $current['kana'] = Normalize::kana($get('kana')) ?? $current['kana'];
            $current['phone'] = Normalize::phone($get('phone')) ?? $current['phone'];
            $current['email'] = Normalize::email($get('email')) ?? $current['email'];
            $current['sns'] = Normalize::sns($get('sns')) ?? $current['sns'];
            $people[$key] = $current;
        }
        return $people;
    }

    /**
     * 同じ名前の顧客に連絡先を付ける。同じ名前が複数いるときはフリガナで見分け、それでも決まらなければ付けない。
     * （フリガナがどの人とも合わない場合も「決まらない」扱い。声掛けリストにいない人とは分けて数える）
     *
     * @param list<array<string, mixed>> $records
     * @param array<string, array> $people parseResponses() の結果
     * @return array{matched: int, ambiguous: list<string>, unmatched: list<string>}
     */
    public static function enrich(array &$records, array $people): array
    {
        $byName = [];
        foreach ($records as $i => $record) {
            $byName[Normalize::matchKey($record['name'])][] = $i;
        }

        $matched = 0;
        $ambiguous = [];
        $unmatched = [];
        foreach ($people as $key => $person) {
            $candidates = $byName[$key] ?? [];
            if ($candidates === []) {
                $unmatched[] = $person['name'];
                continue;
            }
            if (count($candidates) > 1 && $person['kana'] !== '') {
                $candidates = array_values(array_filter(
                    $candidates,
                    fn ($i) => Normalize::matchKey($records[$i]['name_kana'] ?? '') === Normalize::matchKey($person['kana'])
                ));
            }
            if (count($candidates) !== 1) {
                $ambiguous[] = $person['name'];
                continue;
            }
            $i = $candidates[0];
            $records[$i]['phone'] ??= $person['phone'];
            $records[$i]['email'] ??= $person['email'];
            $records[$i]['sns_account'] ??= $person['sns'];
            $matched++;
        }
        return ['matched' => $matched, 'ambiguous' => $ambiguous, 'unmatched' => $unmatched];
    }

    /**
     * 同じ人かもしれない組（自動ではまとめない）
     *
     * @param list<array<string, mixed>> $records
     * @return array{name: list<list<array>>, phone: list<list<array>>}
     */
    public static function duplicateCandidates(array $records): array
    {
        $groups = ['name' => [], 'phone' => []];
        foreach ($records as $record) {
            $groups['name'][Normalize::matchKey($record['name'])][] = $record;
            if ($record['phone'] !== null) {
                $groups['phone'][$record['phone']][] = $record;
            }
        }
        return array_map(
            fn ($byKey) => array_values(array_filter($byKey, fn ($group) => count($group) > 1)),
            $groups
        );
    }

    /**
     * 顧客に書き込む。番号が同じ顧客がいれば更新する（連絡先は空のときだけ埋める）。
     *
     * @param list<array<string, mixed>> $records
     * @return array{inserted: int, updated: int, unchanged: int}
     */
    public static function commit(PDO $pdo, array $records): array
    {
        $sql = 'INSERT INTO customers
                    (legacy_no, name, name_kana, gender, first_channel, note, phone, email, sns_account, legacy_data, access_token)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    name_kana = VALUES(name_kana),
                    gender = VALUES(gender),
                    first_channel = VALUES(first_channel),
                    note = VALUES(note),
                    legacy_data = VALUES(legacy_data),
                    phone = COALESCE(phone, VALUES(phone)),
                    email = COALESCE(email, VALUES(email)),
                    sns_account = COALESCE(sns_account, VALUES(sns_account))';
        $stmt = $pdo->prepare($sql);

        $counts = ['inserted' => 0, 'updated' => 0, 'unchanged' => 0];
        $pdo->beginTransaction();
        try {
            foreach ($records as $r) {
                try {
                    $stmt->execute([
                        $r['legacy_no'], $r['name'], $r['name_kana'], $r['gender'], $r['first_channel'], $r['note'],
                        $r['phone'], $r['email'], $r['sns_account'],
                        json_encode($r['legacy_data'], JSON_UNESCAPED_UNICODE),
                        bin2hex(random_bytes(16)),
                    ]);
                } catch (\PDOException $e) {
                    // どの人で失敗したか分かるようにする（列の長さ超えなど）
                    throw new RuntimeException("番号{$r['legacy_no']}（{$r['name']}）を書き込めませんでした: " . $e->getMessage(), 0, $e);
                }
                // MariaDB: 1 = 追加、2 = 更新、0 = 変更なし
                $counts[match ($stmt->rowCount()) { 1 => 'inserted', 2 => 'updated', default => 'unchanged' }]++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $counts;
    }

    /**
     * 見出しの行を探す（「名前」と「カタカナ」がある最初の行）
     *
     * @return array{int, array<string, int>, array<int, string>}
     */
    private static function findHeader(array $rows): array
    {
        foreach (array_slice($rows, 0, 10, true) as $i => $row) {
            $labels = array_map('trim', $row);
            $columns = [];
            foreach (self::LIST_COLUMNS as $key => $aliases) {
                foreach ($aliases as $alias) {
                    $col = array_search($alias, $labels, true);
                    if ($col !== false) {
                        $columns[$key] = $col;
                        break;
                    }
                }
            }
            if (isset($columns['name'], $columns['kana'], $columns['legacy_no'])) {
                return [$i, $columns, $labels];
            }
        }
        throw new RuntimeException('声掛けリストのCSVに「番号」「名前」「カタカナ」の見出しが見つかりません');
    }
}
