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

    /** @param array<string, string> $vars */
    public static function fill(string $template, array $vars): string
    {
        return strtr($template, array_combine(
            array_map(fn ($k) => '{' . $k . '}', array_keys($vars)),
            array_values($vars)
        ));
    }
}
