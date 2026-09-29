<?php

declare(strict_types=1);

namespace App;

/**
 * 管理画面で変えられる文言・メールの設定。DB（settings）には変えた値だけ入り、なければ既定値。
 * 振込先のように、リポジトリに書けないものもここ（DB）に持つ。
 */
final class Settings
{
    /** 項目の定義：キー => [見出し, 説明, 既定値, 複数行か] */
    public const ITEMS = [
        // ── 掲示板 ──
        'site_intro' => ['掲示板トップの説明', '回の一覧の上に出す文', 'MINATOのイベント一覧です。参加したい回を選んでお申し込みください。', true],
        'contact_text' => ['問い合わせ先', '掲示板の下とメールの末尾に出す', 'ご不明な点は MINATO 公式LINE までお問い合わせください。', true],
        'official_line_url' => ['公式LINEのURL', '友だち追加のリンク。空欄なら出さない', '', false],
        'privacy_url' => ['プライバシーポリシーのURL', '申込フォームからリンクする', 'https://minatocrew.com/privacy-policy/', false],
        // ── 申込フォームの同意文 ──
        'notice_text' => ['注意事項（同意）', '申込フォームで同意してもらう文', '勧誘・営業目的でのご参加はお断りしています。イベント中の写真をSNSなどに掲載することがあります。', true],
        'payment_text' => ['お支払いについて（同意）', '前払いの回だけ出す', '参加費は事前振込制です。お申込み後に届く確認メールの案内に沿ってお振込みください。入金の確認をもって参加確定となります。', true],
        'cancel_policy_default' => ['キャンセルポリシー（既定）', '回にキャンセル規定がないときに出す文', '開催1週間前以降のキャンセルは返金できません。キャンセルや日程変更の際は必ずご連絡ください。', true],
        'bank_account' => ['振込先', '前払いの回の確認メールに入る（{bank_account}）。ここにだけ書き、ほかの資料には書かない', '', true],
        // ── メール ──
        'mail_from_name' => ['差出人の名前', 'メールの From に出る名前', 'MINATO', false],
        'mail_from_address' => ['差出人のメールアドレス', '空欄なら noreply@（このサイトのドメイン）。迷惑メール扱いを避けるため、このサーバーのドメインのアドレスにする', '', false],
        'mail_signature' => ['メールの署名', 'すべてのメールの末尾', "――――――――――\nMINATO\n{contact_text}\n{official_line_url}", true],
        'mail_confirm_subject' => ['確認メール：件名', '申込を受け付けたとき', '【MINATO】お申込みを受け付けました：{event_title}', false],
        'mail_confirm_body' => ['確認メール：本文', '使える言葉：{name} {event_title} {event_datetime} {venue} {venue_address} {venue_url} {fee} {payment} {bank_account} {cancel_policy} {cancel_deadline} {my_url}',
            "{name} 様\n\nお申込みありがとうございます。以下の内容で受け付けました。\n\n■ イベント：{event_title}\n■ 日時：{event_datetime}\n■ 会場：{venue}\n{venue_address}\n{venue_url}\n■ 参加費：{fee}（{payment}）\n{bank_account}\n■ キャンセルについて\n{cancel_policy}\n\nお申込み内容の確認・キャンセルはこちらから（ご本人専用のURLです。他の人に教えないでください）\n{my_url}\n\n当日お会いできるのを楽しみにしています。", true],
        'mail_waitlist_subject' => ['キャンセル待ちメール：件名', '定員に達していたとき', '【MINATO】キャンセル待ちで受け付けました：{event_title}', false],
        'mail_waitlist_body' => ['キャンセル待ちメール：本文', '使える言葉は確認メールと同じ',
            "{name} 様\n\nお申込みありがとうございます。この回は定員に達しているため、キャンセル待ちで受け付けました。\n\n■ イベント：{event_title}\n■ 日時：{event_datetime}\n\n空きが出ましたら、順番にメールでご案内します。\nお申込み内容の確認・取り消しはこちらから\n{my_url}", true],
        'mail_promoted_subject' => ['繰り上げメール：件名', 'キャンセル待ちから参加になったとき', '【MINATO】空きが出ました。ご参加いただけます：{event_title}', false],
        'mail_promoted_body' => ['繰り上げメール：本文', '使える言葉は確認メールと同じ',
            "{name} 様\n\nキャンセル待ちでお申込みいただいていた回に空きが出ました。ご参加いただけます。\n\n■ イベント：{event_title}\n■ 日時：{event_datetime}\n■ 会場：{venue}\n{venue_address}\n{venue_url}\n■ 参加費：{fee}（{payment}）\n{bank_account}\n■ キャンセルについて\n{cancel_policy}\n\nご都合が悪くなった場合は、こちらから早めにキャンセルをお願いします。\n{my_url}", true],
        'mail_cancelled_subject' => ['キャンセル確認メール：件名', 'ご本人がキャンセルしたとき', '【MINATO】キャンセルを受け付けました：{event_title}', false],
        'mail_cancelled_body' => ['キャンセル確認メール：本文', '',
            "{name} 様\n\n以下のお申込みのキャンセルを受け付けました。\n\n■ イベント：{event_title}\n■ 日時：{event_datetime}\n\nまたの機会にお会いできるのを楽しみにしています。", true],
        'mail_reminder_subject' => ['前日リマインド：件名', '開催前日に自動で送る', '【MINATO】明日はイベントです：{event_title}', false],
        'mail_reminder_body' => ['前日リマインド：本文', '',
            "{name} 様\n\n明日のイベントのご案内です。\n\n■ イベント：{event_title}\n■ 日時：{event_datetime}\n■ 会場：{venue}\n{venue_address}\n{venue_url}\n■ 参加費：{fee}（{payment}）\n\nご都合が悪くなった場合は、こちらからご連絡ください。\n{my_url}\n\nお気をつけてお越しください。", true],
        'mail_thanks_subject' => ['お礼メール：件名', '開催翌日に自動で送る', '【MINATO】ご参加ありがとうございました：{event_title}', false],
        'mail_thanks_body' => ['お礼メール：本文', '{survey_url} がアンケートのリンク',
            "{name} 様\n\n昨日は「{event_title}」にご参加いただき、ありがとうございました。\n\n今後の参考に、1分で終わるアンケートにご協力ください。\n{survey_url}\n\nまたお会いできるのを楽しみにしています。", true],
    ];

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    public static function get(string $key): string
    {
        if (!isset(self::ITEMS[$key])) {
            throw new \InvalidArgumentException("設定「{$key}」はありません");
        }
        self::load();
        return self::$cache[$key] ?? self::ITEMS[$key][2];
    }

    /** @return array<string, string> すべての値（既定値を含む） */
    public static function all(): array
    {
        self::load();
        $values = [];
        foreach (self::ITEMS as $key => $item) {
            $values[$key] = self::$cache[$key] ?? $item[2];
        }
        return $values;
    }

    /** @param array<string, string> $values */
    public static function save(array $values, int $adminId): void
    {
        $pdo = Database::pdo();
        $upsert = $pdo->prepare(
            'INSERT INTO settings (`key`, value, updated_by) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)'
        );
        $delete = $pdo->prepare('DELETE FROM settings WHERE `key` = ?');
        $pdo->beginTransaction();
        try {
            foreach ($values as $key => $value) {
                if (!isset(self::ITEMS[$key])) {
                    continue;
                }
                // 空欄か既定値と同じなら行を消して既定値に戻す（既定値を変えたときに追随できるように）
                if ($value === '' || $value === self::ITEMS[$key][2]) {
                    $delete->execute([$key]);
                } else {
                    $upsert->execute([$key, $value, $adminId]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        self::$cache = null;
    }

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        foreach (Database::pdo()->query('SELECT `key`, value FROM settings')->fetchAll() as $row) {
            self::$cache[$row['key']] = $row['value'];
        }
    }
}
