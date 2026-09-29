<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * 顧客（customers）。名寄せ・出禁・検索。
 */
final class Customers
{
    public const GENDERS = ['male' => '男性', 'female' => '女性'];
    public const PER_PAGE = 50;

    /** フォームで受け取る項目 */
    public const FIELDS = ['name', 'name_kana', 'phone', 'email', 'sns_account', 'gender', 'line_name', 'first_channel', 'note'];

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/\A[0-9a-f]{32}\z/', $token)) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM customers WHERE access_token = ?');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    /** 名寄せ：電話番号が同じ人 → メールが同じ人 の順で探す（docs/data-intake.md） */
    public static function findByPhoneOrEmail(?string $phone, ?string $email): ?array
    {
        $pdo = Database::pdo();
        foreach (['phone' => $phone, 'email' => $email] as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE {$column} = ? ORDER BY id LIMIT 1");
            $stmt->execute([$value]);
            $found = $stmt->fetch();
            if ($found) {
                return $found;
            }
        }
        return null;
    }

    /** 空いている項目だけ、申込フォームの値で埋める（入っている値は上書きしない） */
    public static function fillEmpty(int $id, array $values): void
    {
        $customer = self::find($id);
        if ($customer === null) {
            return;
        }
        $sets = [];
        $params = [];
        foreach (['name_kana', 'phone', 'email', 'sns_account', 'gender', 'line_name', 'first_channel'] as $column) {
            $new = $values[$column] ?? null;
            if (($customer[$column] === null || $customer[$column] === '') && $new !== null && $new !== '') {
                $sets[] = "{$column} = ?";
                $params[] = $new;
            }
        }
        if ($sets === []) {
            return;
        }
        $params[] = $id;
        Database::pdo()->prepare('UPDATE customers SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    }

    /**
     * 検索。電話番号らしければ電話、@ があればメール、それ以外は名前・フリガナ・SNS・LINE名で探す。
     *
     * @return array{items: array, total: int, page: int, pages: int}
     */
    public static function search(string $q, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $pdo = Database::pdo();
        [$where, $params] = self::searchCondition($q);
        $total = (int) self::run($pdo, "SELECT COUNT(*) FROM customers c {$where}", $params)->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;
        $items = self::run(
            $pdo,
            "SELECT c.*, (SELECT COUNT(*) FROM registrations r WHERE r.customer_id = c.id AND r.status <> 'cancelled') AS registration_count
             FROM customers c {$where}
             ORDER BY c.name_kana IS NULL, c.name_kana, c.name, c.id
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return array{string, array} */
    private static function searchCondition(string $q): array
    {
        [$clause, $params] = self::searchClause($q);
        return [$clause === '' ? '' : "WHERE {$clause}", $params];
    }

    /**
     * 検索の条件（顧客テーブルの別名は c）。空の検索なら ['', []]。
     *
     * @return array{string, array}
     */
    public static function searchClause(string $q): array
    {
        $q = trim($q);
        if ($q === '') {
            return ['', []];
        }
        if (preg_match('/\A[\d\-\s()+（）ー－―０-９]+\z/u', $q)) {
            $digits = Normalize::phone($q) ?? '';
            return ['c.phone LIKE ?', ['%' . ltrim($digits, '0') . '%']];
        }
        if (str_contains($q, '@')) {
            return ['c.email LIKE ?', ['%' . strtolower($q) . '%']];
        }
        $key = Normalize::matchKey($q);
        $like = '%' . $key . '%';
        return [
            "(REPLACE(c.name, ' ', '') LIKE ? OR REPLACE(c.name_kana, ' ', '') LIKE ? OR c.sns_account LIKE ? OR c.line_name LIKE ?)",
            [$like, $like, $like, $like],
        ];
    }

    /** 電話・メール・SNSのどれかが同じ顧客（名寄せ・出禁チェック用） */
    public static function findMatches(?string $phone, ?string $email, ?string $sns, ?int $excludeId = null): array
    {
        $conditions = [];
        $params = [];
        foreach (['phone' => $phone, 'email' => $email, 'sns_account' => $sns] as $column => $value) {
            if ($value !== null && $value !== '') {
                $conditions[] = "{$column} = ?";
                $params[] = $value;
            }
        }
        if ($conditions === []) {
            return [];
        }
        $sql = 'SELECT * FROM customers WHERE (' . implode(' OR ', $conditions) . ')';
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        return self::run(Database::pdo(), $sql . ' ORDER BY id', $params)->fetchAll();
    }

    /** 同じ人かもしれない顧客（電話・メール・SNSが同じ ＋ 名前が同じ）。$values は normalizeInput() の形 */
    public static function findCandidates(array $values, ?int $excludeId = null): array
    {
        $found = self::findMatches($values['phone'] ?? null, $values['email'] ?? null, $values['sns_account'] ?? null, $excludeId);
        $ids = array_map(fn ($c) => (int) $c['id'], $found);
        if (($values['name'] ?? null) !== null) {
            foreach (self::findSameName($values['name'], $excludeId) as $c) {
                if (!in_array((int) $c['id'], $ids, true)) {
                    $found[] = $c;
                }
            }
        }
        return $found;
    }

    /** 名前（空白を除いて比べる）が同じ顧客 */
    public static function findSameName(string $name, ?int $excludeId = null): array
    {
        $params = [Normalize::matchKey($name)];
        $sql = "SELECT * FROM customers WHERE REPLACE(name, ' ', '') = ?";
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        return self::run(Database::pdo(), $sql . ' ORDER BY id', $params)->fetchAll();
    }

    /**
     * フォームの値をそろえて、問題があれば理由を返す。
     *
     * @return array{values: array, errors: list<string>}
     */
    public static function normalizeInput(array $input): array
    {
        $errors = [];
        $values = [
            'name' => Normalize::name(Form::str($input, 'name')),
            'name_kana' => Normalize::kana(Form::str($input, 'name_kana')),
            'phone' => Normalize::phone(Form::str($input, 'phone')),
            'email' => null,
            'sns_account' => Normalize::sns(Form::str($input, 'sns_account')),
            'gender' => Form::choice($input, 'gender', array_keys(self::GENDERS), '') ?: null,
            'line_name' => Normalize::name(Form::str($input, 'line_name')),
            'first_channel' => Form::str($input, 'first_channel') ?: null,
            'note' => Form::str($input, 'note') ?: null,
        ];
        if ($values['name'] === null) {
            $errors[] = '名前を入れてください。';
        } elseif (mb_strlen($values['name']) > 100) {
            $errors[] = '名前は100文字までにしてください。';
        }
        if ($values['name_kana'] !== null && mb_strlen($values['name_kana']) > 100) {
            $errors[] = 'フリガナは100文字までにしてください。';
        }
        $rawEmail = Form::str($input, 'email');
        if ($rawEmail !== '') {
            $values['email'] = Normalize::email($rawEmail);
            if ($values['email'] === null) {
                $errors[] = 'メールアドレスの形が正しくありません。';
            }
        }
        $rawPhone = Form::str($input, 'phone');
        if ($rawPhone !== '' && ($values['phone'] === null || strlen($values['phone']) < 10 || strlen($values['phone']) > 15)) {
            $errors[] = '電話番号は10〜11桁の数字で入れてください。';
        }
        foreach (['sns_account' => 'SNSアカウント', 'line_name' => 'LINEの表示名'] as $field => $label) {
            if ($values[$field] !== null && mb_strlen($values[$field]) > 100) {
                $errors[] = "{$label}は100文字までにしてください。";
            }
        }
        if ($values['first_channel'] !== null && mb_strlen($values['first_channel']) > 50) {
            $errors[] = '「どこで知りましたか」は50文字までにしてください。';
        }
        return ['values' => $values, 'errors' => $errors];
    }

    public static function create(array $values): int
    {
        $pdo = Database::pdo();
        $columns = array_merge(self::FIELDS, ['access_token']);
        $sql = 'INSERT INTO customers (' . implode(', ', $columns) . ') VALUES (' . rtrim(str_repeat('?, ', count($columns)), ', ') . ')';
        $params = array_map(fn ($f) => $values[$f] ?? null, self::FIELDS);
        $params[] = bin2hex(random_bytes(16));
        $pdo->prepare($sql)->execute($params);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $values): void
    {
        $sets = implode(', ', array_map(fn ($f) => "{$f} = ?", self::FIELDS));
        $params = array_map(fn ($f) => $values[$f] ?? null, self::FIELDS);
        $params[] = $id;
        Database::pdo()->prepare("UPDATE customers SET {$sets} WHERE id = ?")->execute($params);
    }

    /** 案内メールの同意。同意したら同意日時を、やめたら停止日時を記録する */
    public static function setMailOptIn(int $id, bool $optIn): void
    {
        $customer = self::find($id);
        if ($customer === null) {
            return;
        }
        $currently = $customer['mail_opt_in_at'] !== null && $customer['mail_opt_out_at'] === null;
        if ($optIn === $currently) {
            return;
        }
        $sql = $optIn
            ? 'UPDATE customers SET mail_opt_in_at = NOW(), mail_opt_out_at = NULL WHERE id = ?'
            : 'UPDATE customers SET mail_opt_out_at = NOW() WHERE id = ?';
        Database::pdo()->prepare($sql)->execute([$id]);
    }

    public static function isMailOptedIn(array $customer): bool
    {
        return $customer['mail_opt_in_at'] !== null && $customer['mail_opt_out_at'] === null;
    }

    /** 出禁にする。すでに出禁なら日時は最初のまま。理由・経緯は渡したものだけ更新する（null なら今の値を残す） */
    public static function ban(int $id, ?string $reason, ?string $note = null, ?int $adminId = null, ?string $bannedAt = null): void
    {
        Database::pdo()->prepare(
            'UPDATE customers SET banned_at = COALESCE(banned_at, ?, NOW()), ban_reason = COALESCE(?, ban_reason),
                ban_note = COALESCE(?, ban_note), banned_by = COALESCE(banned_by, ?) WHERE id = ?'
        )->execute([$bannedAt, $reason, $note, $adminId, $id]);
    }

    public static function unban(int $id): void
    {
        Database::pdo()->prepare('UPDATE customers SET banned_at = NULL, ban_reason = NULL, ban_note = NULL, banned_by = NULL WHERE id = ?')->execute([$id]);
    }

    /**
     * 同じ人かもしれない組。電話・メールが同じ組は「確定に近い」、名前だけ同じ組は「要確認」。
     *
     * @return array{phone: list<array>, email: list<array>, name: list<array>}
     */
    public static function duplicateGroups(): array
    {
        $pdo = Database::pdo();
        $groups = [];
        foreach (['phone' => 'phone', 'email' => 'email', 'name' => "REPLACE(name, ' ', '')"] as $key => $expr) {
            $rows = $pdo->query(
                "SELECT c.* FROM customers c
                 JOIN (SELECT {$expr} AS k FROM customers WHERE {$expr} IS NOT NULL AND {$expr} <> '' GROUP BY k HAVING COUNT(*) > 1) d
                   ON {$expr} = d.k
                 ORDER BY d.k, c.id"
            )->fetchAll();
            $byKey = [];
            foreach ($rows as $row) {
                $k = $key === 'name' ? Normalize::matchKey($row['name']) : $row[$key];
                $byKey[$k][] = $row;
            }
            $groups[$key] = array_values($byKey);
        }
        return $groups;
    }

    /**
     * 顧客を統合する。$fromId の申込を $intoId に付け替え、空の項目を埋めてから $fromId を消す。
     * 同じ回に両方の申込があるときは統合しない（どちらを残すか人が決める）。
     *
     * @return ?string 統合できなかった理由
     */
    public static function merge(int $fromId, int $intoId): ?string
    {
        if ($fromId === $intoId) {
            return '同じ顧客です。';
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM customers WHERE id IN (?, ?) FOR UPDATE');
            $stmt->execute([$fromId, $intoId]);
            $rows = [];
            foreach ($stmt->fetchAll() as $row) {
                $rows[(int) $row['id']] = $row;
            }
            $from = $rows[$fromId] ?? null;
            $into = $rows[$intoId] ?? null;
            if ($from === null || $into === null) {
                $pdo->rollBack();
                return '顧客が見つかりません。';
            }
            $conflict = $pdo->prepare(
                'SELECT e.title FROM registrations a JOIN registrations b ON a.event_id = b.event_id
                 JOIN events e ON e.id = a.event_id WHERE a.customer_id = ? AND b.customer_id = ? LIMIT 1'
            );
            $conflict->execute([$fromId, $intoId]);
            $title = $conflict->fetchColumn();
            if ($title !== false) {
                $pdo->rollBack();
                return "「{$title}」に両方の申込があります。先にどちらかをキャンセルしてください。";
            }

            $pdo->prepare('UPDATE registrations SET customer_id = ? WHERE customer_id = ?')->execute([$intoId, $fromId]);
            $pdo->prepare('UPDATE inquiries SET customer_id = ? WHERE customer_id = ?')->execute([$intoId, $fromId]);

            // 残す側の空の項目を、消す側の値で埋める
            $fill = [];
            foreach (['name_kana', 'phone', 'email', 'sns_account', 'gender', 'line_name', 'first_channel', 'mail_opt_in_at', 'mail_opt_out_at', 'banned_at', 'ban_reason', 'legacy_no', 'legacy_data'] as $column) {
                if (($into[$column] === null || $into[$column] === '') && $from[$column] !== null && $from[$column] !== '') {
                    $fill[$column] = $from[$column];
                }
            }
            if ($from['note'] !== null && $from['note'] !== '') {
                $fill['note'] = $into['note'] === null || $into['note'] === '' ? $from['note'] : $into['note'] . "\n" . $from['note'];
            }
            // 消す側の番号を残す側に移すため、先に消す側から外す（番号は一意）
            $pdo->prepare('UPDATE customers SET legacy_no = NULL WHERE id = ?')->execute([$fromId]);
            if ($fill !== []) {
                $sets = implode(', ', array_map(fn ($c) => "{$c} = ?", array_keys($fill)));
                $params = array_values($fill);
                $params[] = $intoId;
                $pdo->prepare("UPDATE customers SET {$sets} WHERE id = ?")->execute($params);
            }
            $pdo->prepare('DELETE FROM customers WHERE id = ?')->execute([$fromId]);
            $pdo->commit();
            return null;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private static function run(PDO $pdo, string $sql, array $params): \PDOStatement
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
