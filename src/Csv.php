<?php

declare(strict_types=1);

namespace App;

/**
 * CSV の書き出し。Excel でそのまま開けるように UTF-8（BOM 付き）・改行 CRLF にする。
 * 名前などが = + - @ で始まっても、Excel で数式として動かないよう先頭に ' を付ける。
 */
final class Csv
{
    /**
     * CSV をダウンロードさせる。
     *
     * @param list<string> $header
     * @param iterable<list<mixed>> $rows
     */
    public static function download(string $filename, array $header, iterable $rows): void
    {
        // ファイル名に使えない文字（\ / : * ? " < > | と改行）は _ にする
        $filename = preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]/u', '_', $filename) ?? 'export.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="export.csv"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Cache-Control: no-store');
        echo "\xEF\xBB\xBF";
        echo self::line($header);
        foreach ($rows as $row) {
            echo self::line($row);
        }
    }

    /** @param list<mixed> $cells */
    public static function line(array $cells): string
    {
        return implode(',', array_map([self::class, 'cell'], $cells)) . "\r\n";
    }

    public static function cell(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        $text = (string) $value;
        // 数式として解釈される文字で始まるときは ' を前に付ける（マイナスの数だけはそのまま）
        if ($text !== '' && str_contains("=+-@\t\r", $text[0]) && !preg_match('/\A-\d+(\.\d+)?\z/', $text)) {
            $text = "'" . $text;
        }
        if (preg_match('/[",\r\n]/', $text)) {
            $text = '"' . str_replace('"', '""', $text) . '"';
        }
        return $text;
    }

    /**
     * 電話番号を Excel で先頭の 0 が消えない形（ハイフン入り）にする。
     * 保存している形（数字だけ）から、携帯・IP電話は 3-4-4、フリーダイヤルは 0120-123-456、
     * 東京・大阪（03・06）は 2-4-4、ほかの固定電話は 3-3-4 に分ける（市外局番の長さは地域で違うが、数字は変わらない）
     */
    public static function phone(?string $digits): string
    {
        if ($digits === null || $digits === '') {
            return '';
        }
        $patterns = [
            '/\A(0[5789]0)(\d{4})(\d{4})\z/',
            '/\A(0120)(\d{3})(\d{3})\z/',
            '/\A(0800)(\d{3})(\d{4})\z/',
            '/\A(0[36])(\d{4})(\d{4})\z/',
            '/\A(0\d{2})(\d{3})(\d{4})\z/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $digits, $m)) {
                return "{$m[1]}-{$m[2]}-{$m[3]}";
            }
        }
        return $digits;
    }
}
