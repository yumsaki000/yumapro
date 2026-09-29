<?php

declare(strict_types=1);

namespace App;

/**
 * 「Q. …」「A. …」で始まる行の文章を、質問と答えの組にする。
 */
final class Faq
{
    /** @return list<array{q: string, a: string}> */
    public static function parse(string $text): array
    {
        $items = [];
        $current = null;
        foreach (preg_split('/\R/u', trim($text)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/\A[QqＱ][\.．:：]?\s*(.+)\z/u', $line, $m)) {
                if ($current !== null) {
                    $items[] = $current;
                }
                $current = ['q' => $m[1], 'a' => ''];
            } elseif (preg_match('/\A[AaＡ][\.．:：]?\s*(.+)\z/u', $line, $m) && $current !== null) {
                $current['a'] = $current['a'] === '' ? $m[1] : $current['a'] . "\n" . $m[1];
            } elseif ($current !== null) {
                $current['a'] = $current['a'] === '' ? $line : $current['a'] . "\n" . $line;
            }
        }
        if ($current !== null) {
            $items[] = $current;
        }
        return array_values(array_filter($items, fn ($i) => $i['a'] !== ''));
    }
}
