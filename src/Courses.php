<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * 講座（courses）・各回（lessons）・購入（course_purchases）。誰が見られるかの判定もここ。
 */
final class Courses
{
    public const ACCESS = ['public' => '全員に公開', 'crew' => 'クルー専用', 'paid' => '有料'];

    /** 登録画面で「見られる人」の下に出す説明 */
    public const ACCESS_HELP = [
        'public' => '誰でも見られます（ログインも不要）。集客用の動画に',
        'crew' => '加入中のクルーだけが見られます。ほかの人には「クルーになる」を案内します',
        'paid' => '購入した人が見られます（事前振込・運営が入金を確認）。クルーは無料にもできます',
    ];
    public const STATUSES = ['draft' => '下書き', 'published' => '公開'];
    public const FIELDS = ['title', 'description', 'access', 'price', 'crew_included', 'status', 'sort_order'];
    public const LESSON_FIELDS = ['title', 'body', 'youtube_id', 'is_preview', 'status', 'sort_order'];

    private const SELECT = 'SELECT k.*,
            (SELECT COUNT(*) FROM lessons l WHERE l.course_id = k.id) AS lesson_count,
            (SELECT COUNT(*) FROM lessons l WHERE l.course_id = k.id AND l.status = \'published\') AS published_lesson_count,
            (SELECT COUNT(*) FROM course_purchases p WHERE p.course_id = k.id AND p.status = \'paid\') AS paid_count,
            (SELECT COUNT(*) FROM course_purchases p WHERE p.course_id = k.id AND p.status = \'pending\') AS pending_count,
            (SELECT COUNT(*) FROM lessons l WHERE l.course_id = k.id AND l.status = \'published\' AND l.is_preview = 1) AS preview_count,
            (SELECT l.youtube_id FROM lessons l WHERE l.course_id = k.id AND l.status = \'published\' AND l.youtube_id IS NOT NULL
                ORDER BY l.sort_order, l.id LIMIT 1) AS first_youtube_id
        FROM courses k';

    public static function all(): array
    {
        return Database::pdo()->query(self::SELECT . ' ORDER BY k.sort_order, k.id')->fetchAll();
    }

    public static function published(): array
    {
        return Database::pdo()->query(self::SELECT . " WHERE k.status = 'published' ORDER BY k.sort_order, k.id")->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE k.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        if (!preg_match('/\A[0-9a-f]{12}\z/', $slug)) {
            return null;
        }
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE k.slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data, int $adminId): int
    {
        $pdo = Database::pdo();
        $columns = array_merge(self::FIELDS, ['slug', 'created_by']);
        $values = array_map(fn ($f) => $data[$f] ?? null, self::FIELDS);
        $values[] = self::newSlug($pdo);
        $values[] = $adminId;
        $pdo->prepare('INSERT INTO courses (' . implode(', ', $columns) . ') VALUES (' . rtrim(str_repeat('?, ', count($columns)), ', ') . ')')->execute($values);
        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn ($f) => "{$f} = ?", self::FIELDS));
        $values = array_map(fn ($f) => $data[$f] ?? null, self::FIELDS);
        $values[] = $id;
        Database::pdo()->prepare("UPDATE courses SET {$sets} WHERE id = ?")->execute($values);
    }

    public static function delete(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM courses WHERE id = ?')->execute([$id]);
    }

    // ── 各回 ─────────────────────────────────────────

    public static function lessons(int $courseId, bool $publishedOnly = false): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM lessons WHERE course_id = ?' . ($publishedOnly ? " AND status = 'published'" : '') . ' ORDER BY sort_order, id'
        );
        $stmt->execute([$courseId]);
        return $stmt->fetchAll();
    }

    public static function findLesson(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM lessons WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function addLesson(int $courseId, array $data): int
    {
        $pdo = Database::pdo();
        if (($data['sort_order'] ?? null) === null) {
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM lessons WHERE course_id = ?');
            $stmt->execute([$courseId]);
            $data['sort_order'] = (int) $stmt->fetchColumn();
        }
        $columns = array_merge(['course_id'], self::LESSON_FIELDS);
        $values = array_merge([$courseId], array_map(fn ($f) => $data[$f] ?? null, self::LESSON_FIELDS));
        $pdo->prepare('INSERT INTO lessons (' . implode(', ', $columns) . ') VALUES (' . rtrim(str_repeat('?, ', count($columns)), ', ') . ')')->execute($values);
        return (int) $pdo->lastInsertId();
    }

    public static function updateLesson(int $id, array $data): void
    {
        $sets = implode(', ', array_map(fn ($f) => "{$f} = ?", self::LESSON_FIELDS));
        $values = array_map(fn ($f) => $data[$f] ?? null, self::LESSON_FIELDS);
        $values[] = $id;
        Database::pdo()->prepare("UPDATE lessons SET {$sets} WHERE id = ?")->execute($values);
    }

    public static function deleteLesson(int $id): void
    {
        Database::pdo()->prepare('DELETE FROM lessons WHERE id = ?')->execute([$id]);
    }

    /**
     * 回の順番を1つ上（up）か下（down）へ動かす。並び順を 10, 20, 30… に振り直してから入れ替える
     */
    public static function moveLesson(int $id, string $direction): void
    {
        $lesson = self::findLesson($id);
        if ($lesson === null || !in_array($direction, ['up', 'down'], true)) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id FROM lessons WHERE course_id = ? ORDER BY sort_order, id FOR UPDATE');
            $stmt->execute([(int) $lesson['course_id']]);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            $pos = array_search($id, $ids, true);
            $swap = $direction === 'up' ? $pos - 1 : $pos + 1;
            if ($pos !== false && isset($ids[$swap])) {
                [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
            }
            $update = $pdo->prepare('UPDATE lessons SET sort_order = ? WHERE id = ?');
            foreach ($ids as $i => $lessonId) {
                $update->execute([($i + 1) * 10, $lessonId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // ── 見られるかの判定 ─────────────────────────────────

    /** この回を、この人（未ログインなら null）が見られるか */
    public static function canView(array $course, array $lesson, ?array $customer, ?array $purchase): bool
    {
        if ((int) ($lesson['is_preview'] ?? 0) === 1 || $course['access'] === 'public') {
            return true;
        }
        if ($customer === null) {
            return false;
        }
        $crew = Crew::isActive($customer);
        if ($course['access'] === 'crew') {
            return $crew;
        }
        return ($purchase !== null && $purchase['status'] === 'paid') || ((int) ($course['crew_included'] ?? 0) === 1 && $crew);
    }

    /** 見られないときの理由：login（ログインが要る）／crew（クルー限定）／pending（入金待ち）／buy（購入が要る）／ok */
    public static function lockReason(array $course, ?array $customer, ?array $purchase): string
    {
        if ($course['access'] === 'public') {
            return 'ok';
        }
        if ($customer === null) {
            return 'login';
        }
        $crew = Crew::isActive($customer);
        if ($course['access'] === 'crew') {
            return $crew ? 'ok' : 'crew';
        }
        if (($purchase !== null && $purchase['status'] === 'paid') || ((int) ($course['crew_included'] ?? 0) === 1 && $crew)) {
            return 'ok';
        }
        return $purchase !== null && $purchase['status'] === 'pending' ? 'pending' : 'buy';
    }

    // ── 購入 ─────────────────────────────────────────

    public static function purchase(int $courseId, int $customerId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM course_purchases WHERE course_id = ? AND customer_id = ?');
        $stmt->execute([$courseId, $customerId]);
        return $stmt->fetch() ?: null;
    }

    /** @return array{purchase: array, created: bool} */
    public static function requestPurchase(int $courseId, int $customerId, int $amount): array
    {
        $existing = self::purchase($courseId, $customerId);
        if ($existing !== null && $existing['status'] !== 'cancelled') {
            return ['purchase' => $existing, 'created' => false];
        }
        $pdo = Database::pdo();
        $pdo->prepare(
            "INSERT INTO course_purchases (course_id, customer_id, amount, status) VALUES (?, ?, ?, 'pending')
             ON DUPLICATE KEY UPDATE amount = VALUES(amount), status = 'pending', paid_at = NULL, payment_method = NULL, confirmed_by = NULL"
        )->execute([$courseId, $customerId, $amount]);
        return ['purchase' => self::purchase($courseId, $customerId), 'created' => true];
    }

    public static function purchases(string $status = 'pending'): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, c.name AS customer_name, c.email AS customer_email, c.access_token AS customer_token, k.title AS course_title, k.slug AS course_slug
             FROM course_purchases p JOIN customers c ON c.id = p.customer_id JOIN courses k ON k.id = p.course_id
             WHERE p.status = ? ORDER BY p.created_at DESC LIMIT 200'
        );
        $stmt->execute([$status]);
        return $stmt->fetchAll();
    }

    public static function findPurchase(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, c.name AS customer_name, c.email AS customer_email, c.access_token AS customer_token, k.title AS course_title, k.slug AS course_slug
             FROM course_purchases p JOIN customers c ON c.id = p.customer_id JOIN courses k ON k.id = p.course_id WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function markPaid(int $id, string $method, int $adminId): void
    {
        Database::pdo()->prepare("UPDATE course_purchases SET status = 'paid', paid_at = NOW(), payment_method = ?, confirmed_by = ? WHERE id = ?")
            ->execute([$method, $adminId, $id]);
    }

    public static function cancelPurchase(int $id): void
    {
        Database::pdo()->prepare("UPDATE course_purchases SET status = 'cancelled' WHERE id = ?")->execute([$id]);
    }

    /** YouTube のURLか動画IDから動画IDを取り出す。読めなければ null */
    public static function youtubeId(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        if (preg_match('/\A[A-Za-z0-9_-]{11}\z/', $input)) {
            return $input;
        }
        if (preg_match('#(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{11})#', $input, $m)) {
            return $m[1];
        }
        return null;
    }

    /** 本文（動画の下の説明）を表示用の HTML にする。■見出し・「・」箇条書き・**太字**・URL のリンクが使える */
    public static function formatBody(?string $body): string
    {
        return Markup::render($body);
    }

    /** YouTube のサムネイル画像（一覧のカード・登録画面の確認用） */
    public static function thumbnailUrl(?string $youtubeId): ?string
    {
        return $youtubeId === null || $youtubeId === '' ? null : 'https://i.ytimg.com/vi/' . rawurlencode($youtubeId) . '/hqdefault.jpg';
    }

    private static function newSlug(PDO $pdo): string
    {
        $stmt = $pdo->prepare('SELECT 1 FROM courses WHERE slug = ?');
        do {
            $slug = substr(bin2hex(random_bytes(8)), 0, 12);
            $stmt->execute([$slug]);
        } while ($stmt->fetchColumn());
        return $slug;
    }
}
