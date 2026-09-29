<?php

declare(strict_types=1);

namespace App;

/**
 * こくちーず・Instagram・LINE などに貼る告知文を、回の内容から作る。
 * 申込は掲示板のフォームに一本化するので、文の最後は必ず回のページへのリンクにする。
 */
final class Announce
{
    public static function text(array $event, string $url): string
    {
        $when = fmt_dt($event['starts_at']);
        if ($event['ends_at'] !== null) {
            $sameDay = date('Y-m-d', strtotime((string) $event['ends_at'])) === date('Y-m-d', strtotime((string) $event['starts_at']));
            $when .= '〜' . ($sameDay ? date('H:i', strtotime((string) $event['ends_at'])) : fmt_dt($event['ends_at']));
        }
        $place = Calendar::location($event);
        $fee = yen($event['fee']);
        if ($event['fee_male'] !== null || $event['fee_female'] !== null) {
            $fee = '男性 ' . yen($event['fee_male'] ?? $event['fee']) . '／女性 ' . yen($event['fee_female'] ?? $event['fee']);
        }
        if ($event['fee_crew'] !== null) {
            $fee .= '（クルーは ' . yen($event['fee_crew']) . '）';
        }

        $lines = ['【' . Settings::get('public_name') . '】' . $event['title'], ''];
        $summary = trim((string) $event['summary']);
        if ($summary !== '') {
            $lines[] = $summary;
            $lines[] = '';
        }
        $lines[] = '📅 日時：' . $when;
        $lines[] = '📍 場所：' . ($place !== '' ? $place : '追ってご案内') . '（くわしい場所はお申込み後にご案内します）';
        $lines[] = '💰 参加費：' . $fee . '・' . (Events::PAYMENT_TIMINGS[$event['payment_timing']] ?? '');
        if (trim((string) $event['belongings']) !== '') {
            $lines[] = '🎒 持ち物：' . trim((string) $event['belongings']);
        }
        if ($event['capacity'] !== null) {
            $lines[] = '👥 定員：' . (int) $event['capacity'] . '人';
        }
        $highlights = Markup::lines($event['highlights']);
        if ($highlights !== []) {
            $lines[] = '';
            foreach ($highlights as $item) {
                $lines[] = '✓ ' . $item;
            }
        }
        $lines[] = '';
        $lines[] = '▼ くわしい内容・お申込みはこちら';
        $lines[] = $url;
        return implode("\n", $lines);
    }
}
