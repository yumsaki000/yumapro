<?php

declare(strict_types=1);

namespace App;

/**
 * 回を参加者のカレンダーに入れる（Googleカレンダーのリンクと、iPhone などで開ける .ics）。
 * 場所は公開してよい目安（アクセス・会場名）だけを入れ、住所は入れない。
 */
final class Calendar
{
    /** 終了日時がないときの長さ（時間） */
    private const DEFAULT_HOURS = 2;

    public static function googleUrl(array $event, string $pageUrl): string
    {
        [$start, $end] = self::range($event);
        return 'https://calendar.google.com/calendar/render?' . http_build_query([
            'action' => 'TEMPLATE',
            'text' => $event['title'],
            'dates' => $start . '/' . $end,
            'details' => $pageUrl,
            'location' => self::location($event),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public static function ics(array $event, string $pageUrl): string
    {
        [$start, $end] = self::range($event);
        $host = parse_url($pageUrl, PHP_URL_HOST) ?: 'localhost';
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//MINATO//BRIDGE//JA',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:event-' . $event['slug'] . '@' . $host,
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $start,
            'DTEND:' . $end,
            'SUMMARY:' . self::escape((string) $event['title']),
            'LOCATION:' . self::escape(self::location($event)),
            'DESCRIPTION:' . self::escape($pageUrl),
            'URL:' . $pageUrl,
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    /** 公開してよい場所の目安 */
    public static function location(array $event): string
    {
        return trim((string) ($event['access'] ?? '') ?: (string) ($event['venue_name'] ?? ''));
    }

    /** @return array{string, string} UTC の開始・終了（20261011T100000Z の形） */
    private static function range(array $event): array
    {
        $start = strtotime((string) $event['starts_at']);
        $end = $event['ends_at'] !== null ? strtotime((string) $event['ends_at']) : $start + self::DEFAULT_HOURS * 3600;
        return [gmdate('Ymd\THis\Z', $start), gmdate('Ymd\THis\Z', max($end, $start))];
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }

    /** 1行75バイトごとに折り返す（.ics の決まり。日本語の途中で切らない） */
    private static function fold(string $line): string
    {
        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            // 2行目からは先頭の空白1文字ぶんを引く
            if (strlen($current . $char) > ($out === '' ? 75 : 74)) {
                $out .= $current . "\r\n ";
                $current = '';
            }
            $current .= $char;
        }
        return $out . $current;
    }
}
