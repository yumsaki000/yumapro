<?php

declare(strict_types=1);

namespace App;

/**
 * 申込に関するメールを、設定の文面から組み立てて送る。
 */
final class MailTemplates
{
    /** 申込の確認（applied）／キャンセル待ち（waitlisted）の受付メール */
    public static function sendConfirmation(array $registration): bool
    {
        $kind = $registration['status'] === 'waitlisted' ? 'waitlist' : 'confirm';
        return self::sendKind($kind, $registration);
    }

    /** kind: confirm / waitlist / promoted / cancelled / reminder / thanks */
    public static function sendKind(string $kind, array $registration): bool
    {
        $to = $registration['customer_email'] ?? null;
        if ($to === null || $to === '') {
            return false;
        }
        $vars = self::variables($registration, $kind);
        $subject = self::fill(Settings::get("mail_{$kind}_subject"), $vars);
        $body = self::fill(Settings::get("mail_{$kind}_body"), $vars) . "\n\n" . self::fill(Settings::get('mail_signature'), $vars);
        $body = (string) preg_replace("/\n{3,}/", "\n\n", $body);
        return Mailer::send($to, $subject, $body, $kind, (int) $registration['id'], (int) $registration['customer_id']);
    }

    /**
     * 文面に入れる言葉。会場の住所は、参加が確定した人（applied）にだけ入れる。
     *
     * @return array<string, string>
     */
    public static function variables(array $r, string $kind): array
    {
        $base = rtrim((string) Config::get('APP_URL', ''), '/');
        $confirmed = $r['status'] === 'applied';
        $prepaid = ($r['event_payment_timing'] ?? 'prepaid') === 'prepaid';
        $bank = trim(Settings::get('bank_account'));
        $cancelPolicy = trim((string) ($r['event_cancel_policy'] ?? '')) ?: Settings::get('cancel_policy_default');
        return [
            'name' => (string) $r['customer_name'],
            'event_title' => (string) $r['event_title'],
            'event_datetime' => fmt_dt($r['event_starts_at']) . (!empty($r['event_ends_at']) ? ' 〜 ' . date('H:i', strtotime($r['event_ends_at'])) : ''),
            'venue' => (string) ($r['event_venue_name'] ?? '追ってご案内します'),
            'venue_address' => $confirmed && !empty($r['event_venue_address']) ? '　' . $r['event_venue_address'] : '',
            'venue_url' => $confirmed && !empty($r['event_venue_url']) ? '　' . $r['event_venue_url'] : '',
            'fee' => yen($r['fee']),
            'payment' => $prepaid ? '事前振込' : '当日払い',
            'bank_account' => $prepaid && $confirmed && $bank !== '' && $r['prepaid_at'] === null ? "■ お振込先\n{$bank}\n" : '',
            'cancel_policy' => $cancelPolicy,
            'cancel_deadline' => !empty($r['event_cancel_deadline']) ? fmt_dt($r['event_cancel_deadline']) : '',
            'my_url' => $base . '/my/' . $r['customer_token'],
            'survey_url' => $base . '/my/' . $r['customer_token'] . '/survey/' . (int) $r['id'],
            'contact_text' => Settings::get('contact_text'),
            'official_line_url' => Settings::get('official_line_url'),
        ];
    }

    /**
     * 顧客宛てのメール（申込に紐づかないもの）：クルー申込・承認、ログイン用リンク、講座の購入。
     * kind は設定の mail_{kind}_subject / _body に対応する。
     */
    public static function sendCustomerMail(string $kind, array $customer, array $extra = []): bool
    {
        $to = $customer['email'] ?? null;
        if ($to === null || $to === '') {
            return false;
        }
        $base = rtrim((string) Config::get('APP_URL', ''), '/');
        $vars = $extra + [
            'name' => (string) $customer['name'],
            'my_url' => $base . '/my/' . $customer['access_token'],
            'login_url' => $base . '/login',
            'crew_fee_text' => Settings::get('crew_fee_text'),
            'contact_text' => Settings::get('contact_text'),
            'official_line_url' => Settings::get('official_line_url'),
        ];
        $subject = self::fill(Settings::get("mail_{$kind}_subject"), $vars);
        $body = self::fill(Settings::get("mail_{$kind}_body"), $vars) . "\n\n" . self::fill(Settings::get('mail_signature'), $vars);
        $body = (string) preg_replace("/\n{3,}/", "\n\n", $body);
        return Mailer::send($to, $subject, $body, $kind, null, (int) $customer['id']);
    }

    /** 運営への通知（申込以外：クルー申込・講座の購入）。宛先が設定されていれば送る */
    public static function notifyStaffText(string $subject, array $lines, ?int $customerId = null): bool
    {
        $addresses = array_filter(array_map('trim', explode(',', Settings::get('staff_notify_email'))));
        if ($addresses === []) {
            return false;
        }
        $ok = true;
        foreach ($addresses as $to) {
            $ok = Mailer::send($to, $subject, implode("\n", $lines), 'staff_notify', null, $customerId) && $ok;
        }
        return $ok;
    }

    /**
     * 運営への通知（申込フォームからの申込）。出禁に該当・要確認のときに送る。設定で「すべて」にもできる。
     */
    public static function notifyStaff(array $r): bool
    {
        $addresses = array_filter(array_map('trim', explode(',', Settings::get('staff_notify_email'))));
        if ($addresses === []) {
            return false;
        }
        $check = $r['ban_check'] ?? 'none';
        $noShows = (int) ($r['customer_no_shows'] ?? 0);
        $threshold = (int) Settings::get('no_show_warn_count');
        $noShowWarn = $threshold > 0 && $noShows >= $threshold;
        if ($check === 'none' && !$noShowWarn && Settings::get('staff_notify_all') !== '1') {
            return false;
        }
        $base = rtrim((string) Config::get('APP_URL', ''), '/');
        $head = match (true) {
            $check === 'confirmed' => '【出禁該当】',
            $check === 'suspect' => '【要確認】',
            $noShowWarn => "【無断キャンセル{$noShows}回】",
            default => '【申込】',
        };
        $subject = "{$head}{$r['event_title']}：{$r['customer_name']}";
        $lines = [
            "掲示板の申込フォームから申込がありました。",
            '',
            "■ イベント：{$r['event_title']}（" . fmt_dt($r['event_starts_at']) . '）',
            "■ 名前：{$r['customer_name']}" . (!empty($r['customer_kana']) ? "（{$r['customer_kana']}）" : ''),
            '■ 電話：' . ($r['customer_phone'] ?? '—'),
            '■ メール：' . ($r['customer_email'] ?? '—'),
            '■ 状態：' . (Registrations::STATUSES[$r['status']] ?? $r['status']),
        ];
        if ($check === 'confirmed') {
            $lines[] = '■ 出禁チェック：電話・メール・SNSが出禁リストの人と一致しました。申込はキャンセル待ちに止めてあります（本人には普通のキャンセル待ちの案内だけ届いています）。';
        } elseif ($check === 'suspect') {
            $lines[] = '■ 出禁チェック：名前が出禁リストの人と同じです（連絡先は一致していません）。別人かどうか確認してください。';
        }
        if ($noShows > 0) {
            $lines[] = "■ 無断キャンセル：これまでに{$noShows}回あります。申込は受け付けています。必要なら本人に連絡してください。";
        }
        if (!empty($r['referrer_name'])) {
            $lines[] = "■ 紹介：{$r['referrer_name']} さんの招待リンクから";
        }
        $lines[] = '';
        $lines[] = '確認・処理はこちら：';
        $lines[] = "{$base}/admin/registrations/" . (int) $r['id'] . '/edit';
        $ok = true;
        foreach ($addresses as $to) {
            $ok = Mailer::send($to, $subject, implode("\n", $lines), 'staff_notify', (int) $r['id'], (int) $r['customer_id']) && $ok;
        }
        return $ok;
    }

    /** メールの末尾の署名（設定の「署名」。前に空行を入れる） */
    public static function signature(): string
    {
        return "\n\n" . self::fill(Settings::get('mail_signature'), ['contact_text' => Settings::get('contact_text'), 'official_line_url' => Settings::get('official_line_url')]);
    }

    /** @param array<string, string> $vars */
    public static function fill(string $template, array $vars): string
    {
        return strtr($template, array_combine(
            array_map(fn ($k) => '{' . $k . '}', array_keys($vars)),
            array_values($vars)
        ));
    }
}
