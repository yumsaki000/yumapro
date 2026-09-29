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
     * 運営への通知（申込フォームからの申込）。出禁に該当・要確認のときに送る。設定で「すべて」にもできる。
     */
    public static function notifyStaff(array $r): bool
    {
        $addresses = array_filter(array_map('trim', explode(',', Settings::get('staff_notify_email'))));
        if ($addresses === []) {
            return false;
        }
        $check = $r['ban_check'] ?? 'none';
        if ($check === 'none' && Settings::get('staff_notify_all') !== '1') {
            return false;
        }
        $base = rtrim((string) Config::get('APP_URL', ''), '/');
        $head = match ($check) {
            'confirmed' => '【出禁該当】',
            'suspect' => '【要確認】',
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
        $lines[] = '';
        $lines[] = '確認・処理はこちら：';
        $lines[] = "{$base}/admin/registrations/" . (int) $r['id'] . '/edit';
        $ok = true;
        foreach ($addresses as $to) {
            $ok = Mailer::send($to, $subject, implode("\n", $lines), 'staff_notify', (int) $r['id'], (int) $r['customer_id']) && $ok;
        }
        return $ok;
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
