<?php

declare(strict_types=1);

namespace App;

/**
 * 次回のお知らせの登録（follows / follow_types）。こくちーずの「興味ありリスト」にあたる。
 * 形式ごとに登録しておくと、その形式のイベントの募集を始めたときにメールが届く。
 * 受け取りの同意は、確認メールのリンクを開いたとき（confirmed_at）に成立する。
 */
final class Follows
{
    /** 確認メールを続けて送らない間隔（分） */
    private const RESEND_MINUTES = 10;

    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/\A[0-9a-f]{32}\z/', $token)) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM follows WHERE token = ?');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    /** @return list<int> 登録している形式の id */
    public static function typeIds(int $followId): array
    {
        $stmt = Database::pdo()->prepare('SELECT event_type_id FROM follow_types WHERE follow_id = ? ORDER BY event_type_id');
        $stmt->execute([$followId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * 登録する。すでに同じメールがあれば、選んだ形式を足す（止めていた人は選び直し）。
     * 確認がまだの人（初めて・止めたあとの再登録）は needsConfirm を返す。確認メールは呼ぶ側で送る。
     *
     * @param list<int> $typeIds
     * @return array{follow: array, needsConfirm: bool}
     */
    public static function subscribe(string $email, array $typeIds): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM follows WHERE email = ?');
        $stmt->execute([$email]);
        $follow = $stmt->fetch() ?: null;
        if ($follow === null) {
            $pdo->prepare('INSERT INTO follows (email, token) VALUES (?, ?)')->execute([$email, bin2hex(random_bytes(16))]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $id = (int) $follow['id'];
            if ($follow['unsubscribed_at'] !== null) {
                // 止めたあとに登録し直したときは、もう一度確かめる
                $pdo->prepare('UPDATE follows SET unsubscribed_at = NULL, confirmed_at = NULL WHERE id = ?')->execute([$id]);
            } else {
                // 選んだ形式を足すだけにする（外すのは本人だけが開ける変更ページで）
                $typeIds = array_merge(self::typeIds($id), $typeIds);
            }
        }
        self::setTypes($id, $typeIds);
        $stmt = $pdo->prepare('SELECT * FROM follows WHERE id = ?');
        $stmt->execute([$id]);
        $follow = $stmt->fetch();
        return ['follow' => $follow, 'needsConfirm' => $follow['confirmed_at'] === null];
    }

    /** @param list<int> $typeIds */
    public static function setTypes(int $followId, array $typeIds): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM follow_types WHERE follow_id = ?')->execute([$followId]);
        $insert = $pdo->prepare('INSERT INTO follow_types (follow_id, event_type_id) VALUES (?, ?)');
        foreach (array_unique($typeIds) as $typeId) {
            $insert->execute([$followId, $typeId]);
        }
    }

    /** 確認メールを送る。直前に送っていれば送らない（いたずらで何度も送らせないため） */
    public static function sendConfirm(array $follow): bool
    {
        if ($follow['confirm_sent_at'] !== null && strtotime((string) $follow['confirm_sent_at']) > time() - self::RESEND_MINUTES * 60) {
            return false;
        }
        Database::pdo()->prepare('UPDATE follows SET confirm_sent_at = NOW() WHERE id = ?')->execute([(int) $follow['id']]);
        $vars = [
            'types' => implode('・', self::typeNames(self::typeIds((int) $follow['id']))),
            'confirm_url' => app_url('/follow/' . $follow['token']),
        ];
        return Mailer::send(
            (string) $follow['email'],
            MailTemplates::fill(Settings::get('mail_follow_confirm_subject'), $vars),
            MailTemplates::fill(Settings::get('mail_follow_confirm_body'), $vars) . MailTemplates::signature(),
            'follow_confirm'
        );
    }

    public static function confirm(int $id): void
    {
        Database::pdo()->prepare('UPDATE follows SET confirmed_at = NOW() WHERE id = ? AND confirmed_at IS NULL')->execute([$id]);
    }

    public static function unsubscribe(int $id): void
    {
        Database::pdo()->prepare('UPDATE follows SET unsubscribed_at = NOW() WHERE id = ?')->execute([$id]);
    }

    /** 止めていた人が、本人のリンクから受け取りを再開する */
    public static function resume(int $id): void
    {
        Database::pdo()->prepare('UPDATE follows SET unsubscribed_at = NULL, confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ?')->execute([$id]);
    }

    /**
     * 画面から送られた形式の id を、今ある形式だけに絞る
     *
     * @return list<int>
     */
    public static function validTypeIds(mixed $input): array
    {
        $valid = array_map(fn ($t) => (int) $t['id'], Events::types());
        $ids = [];
        foreach ((array) $input as $value) {
            if (is_string($value) && ctype_digit($value) && in_array((int) $value, $valid, true)) {
                $ids[] = (int) $value;
            }
        }
        return array_values(array_unique($ids));
    }

    /** お知らせを受け取る人（確認済み・停止していない・その形式を選んでいる） */
    public static function recipients(int $typeId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT f.* FROM follows f JOIN follow_types t ON t.follow_id = f.id
             WHERE t.event_type_id = ? AND f.confirmed_at IS NOT NULL AND f.unsubscribed_at IS NULL ORDER BY f.id'
        );
        $stmt->execute([$typeId]);
        return $stmt->fetchAll();
    }

    /** 形式ごとの受け取る人数：event_type_id => 人数 */
    public static function countsByType(): array
    {
        return Database::pdo()->query(
            'SELECT t.event_type_id, COUNT(*) FROM follows f JOIN follow_types t ON t.follow_id = f.id
             WHERE f.confirmed_at IS NOT NULL AND f.unsubscribed_at IS NULL GROUP BY t.event_type_id'
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** 管理画面の一覧（新しい順） */
    public static function all(): array
    {
        return Database::pdo()->query(
            "SELECT f.*, GROUP_CONCAT(et.name ORDER BY et.sort_order SEPARATOR '・') AS type_names
             FROM follows f LEFT JOIN follow_types t ON t.follow_id = f.id LEFT JOIN event_types et ON et.id = t.event_type_id
             GROUP BY f.id ORDER BY f.created_at DESC LIMIT 500"
        )->fetchAll();
    }

    /**
     * 募集を始めたイベントを、その形式の登録者に知らせる。送った人数を返す。
     * 一度送ったイベント（announced_at あり）には送らない。
     *
     * @return array{sent: int, failed: int}
     */
    public static function announce(array $event): array
    {
        $pdo = Database::pdo();
        // 二重に送らないよう、先に印を付けてから送る（同時に押されても片方だけが送る）
        $stmt = $pdo->prepare('UPDATE events SET announced_at = NOW() WHERE id = ? AND announced_at IS NULL');
        $stmt->execute([(int) $event['id']]);
        if ($stmt->rowCount() === 0) {
            return ['sent' => 0, 'failed' => 0];
        }
        @set_time_limit(0);
        $vars = [
            'event_title' => (string) $event['title'],
            'event_datetime' => fmt_dt($event['starts_at']),
            'place' => Calendar::location($event) !== '' ? Calendar::location($event) : '追ってご案内します',
            'fee' => yen($event['fee']),
            'summary' => trim((string) $event['summary']) !== '' ? trim((string) $event['summary']) : Markup::plain($event['description'], 200),
            'event_url' => app_url('/e/' . $event['slug'] . '?from=follow'),
        ];
        $sent = 0;
        $failed = 0;
        $subject = MailTemplates::fill(Settings::get('mail_follow_notice_subject'), $vars);
        foreach (self::recipients((int) $event['event_type_id']) as $follow) {
            $body = MailTemplates::fill(Settings::get('mail_follow_notice_body'), $vars + ['manage_url' => app_url('/follow/' . $follow['token'])]);
            if (Mailer::send((string) $follow['email'], $subject, $body . MailTemplates::signature(), 'follow_notice')) {
                $sent++;
            } else {
                $failed++;
            }
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @param list<int> $typeIds
     * @return list<string>
     */
    private static function typeNames(array $typeIds): array
    {
        $names = [];
        foreach (Events::types() as $type) {
            if (in_array((int) $type['id'], $typeIds, true)) {
                $names[] = (string) $type['name'];
            }
        }
        return $names;
    }
}
