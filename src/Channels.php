<?php

declare(strict_types=1);

namespace App;

/**
 * 「どこで知りましたか」の選択肢。
 */
final class Channels
{
    public static function all(): array
    {
        return Database::pdo()->query('SELECT id, name, sort_order, is_active FROM channels ORDER BY sort_order, id')->fetchAll();
    }

    /** 選択肢に出す名前の一覧（有効なものだけ）。$keep を渡すと、無効でも今の値は残す */
    public static function activeNames(?string $keep = null): array
    {
        $names = array_column(array_filter(self::all(), fn ($c) => (int) $c['is_active'] === 1), 'name');
        if ($keep !== null && $keep !== '' && !in_array($keep, $names, true)) {
            $names[] = $keep;
        }
        return $names;
    }

    public static function exists(string $name): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM channels WHERE name = ?');
        $stmt->execute([$name]);
        return (bool) $stmt->fetchColumn();
    }

    public static function add(string $name): void
    {
        $pdo = Database::pdo();
        $order = (int) $pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM channels WHERE name <> 'その他'")->fetchColumn() + 10;
        $pdo->prepare('INSERT INTO channels (name, sort_order) VALUES (?, ?)')->execute([$name, $order]);
    }

    /** @param array<int, array{sort_order: int, is_active: bool}> $rows id → 値 */
    public static function updateAll(array $rows): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE channels SET sort_order = ?, is_active = ? WHERE id = ?');
        $pdo->beginTransaction();
        try {
            foreach ($rows as $id => $row) {
                $stmt->execute([$row['sort_order'], $row['is_active'] ? 1 : 0, $id]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
