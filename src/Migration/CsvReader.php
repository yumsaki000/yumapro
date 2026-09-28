<?php

declare(strict_types=1);

namespace App\Migration;

use RuntimeException;

/**
 * CSVを行の配列として読む。文字コードは UTF-8（Googleスプレッドシート）と Shift_JIS（Excel）を自動で判定する。
 */
final class CsvReader
{
    /** @return list<list<string>> */
    public static function read(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("ファイルを読めません: {$path}");
        }
        return self::parse($content);
    }

    /** @return list<list<string>> */
    public static function parse(string $content): array
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'SJIS-win');
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $rows[] = array_map(fn ($v) => (string) $v, $row);
        }
        fclose($stream);
        return $rows;
    }
}
