<?php

declare(strict_types=1);

namespace App;

/**
 * 回のページの文章を、書いたままの見た目に近い形で HTML にする。
 * 運営がスマホからでも書けるよう、記号は日本語で自然に使うものにしている。
 *
 *   ■ 見出し（◆ や ## でもよい）
 *   ・ 箇条書き（- や ✓ でもよい）
 *   **太字**
 *   --- 区切り線
 *   URL は自動でリンクになる。空行で段落を分ける。
 *
 * 文字はすべて先に e() を通すので、HTML を書いてもそのまま文字として出る。
 */
final class Markup
{
    private const HEADING = '/\A(?:■|◆|#{1,3}\s)\s*(.+)\z/u';
    private const BULLET = '/\A(?:・|-\s|\*\s|✓|✔|●)\s*(.+)\z/u';

    public static function render(?string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $text);
        if (trim($text) === '') {
            return '';
        }
        $html = [];
        $paragraph = [];
        $list = [];
        $flush = function () use (&$html, &$paragraph, &$list): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . implode('<br>', $paragraph) . '</p>';
                $paragraph = [];
            }
            if ($list !== []) {
                $html[] = '<ul>' . implode('', array_map(fn ($item) => "<li>{$item}</li>", $list)) . '</ul>';
                $list = [];
            }
        };

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                $flush();
                continue;
            }
            if (preg_match('/\A(?:-{3,}|ー{3,}|―{2,})\z/u', $line)) {
                $flush();
                $html[] = '<hr>';
                continue;
            }
            if (preg_match(self::HEADING, $line, $m)) {
                $flush();
                $html[] = '<h3>' . self::inline($m[1]) . '</h3>';
                continue;
            }
            if (preg_match(self::BULLET, $line, $m)) {
                if ($paragraph !== []) {
                    $html[] = '<p>' . implode('<br>', $paragraph) . '</p>';
                    $paragraph = [];
                }
                $list[] = self::inline($m[1]);
                continue;
            }
            if ($list !== []) {
                $flush();
            }
            $paragraph[] = self::inline($line);
        }
        $flush();
        return implode("\n", $html);
    }

    /**
     * 1行に1つ書く欄（安心ポイント・おすすめ）を配列にする。先頭の「・」などは取る。
     *
     * @return list<string>
     */
    public static function lines(?string $text): array
    {
        $items = [];
        foreach (preg_split('/\R/u', (string) $text) ?: [] as $line) {
            $line = trim((string) preg_replace('/\A(?:・|-\s|\*\s|✓|✔|●|■|◆)\s*/u', '', trim($line)));
            if ($line !== '') {
                $items[] = $line;
            }
        }
        return $items;
    }

    /**
     * タイムスケジュールを {time, text} の配列にする。
     * 「14:00 自己紹介」「14：00〜 自己紹介」「14時 自己紹介」の形を読む。
     * 時刻のない行は、直前の項目の続き（改行）として扱う。
     *
     * @return list<array{time: string, text: string}>
     */
    public static function timetable(?string $text): array
    {
        $items = [];
        foreach (preg_split('/\R/u', (string) $text) ?: [] as $line) {
            $line = trim(mb_convert_kana($line, 'as'));
            if ($line === '') {
                continue;
            }
            if (preg_match('/\A(\d{1,2})(?::|時)(\d{2})?(?:分)?\s*(?:(?:〜|~|-|ー)\s*(?:(\d{1,2})(?::|時)(\d{2})?(?:分)?)?)?\s*(.*)\z/u', $line, $m)) {
                $time = sprintf('%d:%s', (int) $m[1], $m[2] !== '' ? $m[2] : '00');
                if (($m[3] ?? '') !== '') {
                    $time .= sprintf('〜%d:%s', (int) $m[3], ($m[4] ?? '') !== '' ? $m[4] : '00');
                }
                $items[] = ['time' => $time, 'text' => trim($m[5] ?? '')];
                continue;
            }
            if ($items === []) {
                $items[] = ['time' => '', 'text' => $line];
            } else {
                $last = count($items) - 1;
                $items[$last]['text'] = trim($items[$last]['text'] . "\n" . $line);
            }
        }
        return $items;
    }

    /** 書式を取ったプレーンな文（メタ情報・告知文用）。長ければ切る */
    public static function plain(?string $text, int $limit = 0): string
    {
        $lines = [];
        foreach (preg_split('/\R/u', (string) $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/\A(?:-{3,}|ー{3,}|―{2,})\z/u', $line)) {
                continue;
            }
            $line = (string) preg_replace([self::HEADING, self::BULLET], '$1', $line);
            $lines[] = str_replace('**', '', $line);
        }
        $plain = implode(' ', $lines);
        if ($limit > 0 && mb_strlen($plain) > $limit) {
            $plain = mb_substr($plain, 0, $limit - 1) . '…';
        }
        return $plain;
    }

    /** 1行の中の書式：URL をリンクに、**〜** を太字に */
    private static function inline(string $text): string
    {
        $escaped = e($text);
        $escaped = (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $escaped);
        return (string) preg_replace('#(https?://[^\s<"]+)#u', '<a href="$1" target="_blank" rel="noopener">$1</a>', $escaped);
    }
}
