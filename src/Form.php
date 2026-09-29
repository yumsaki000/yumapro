<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;

/**
 * フォームの値の受け取り方。$_POST や $_GET から、型をそろえて取り出す。
 */
final class Form
{
    /** 文字列（前後の空白を取る）。文字列でなければ空文字 */
    public static function str(array $source, string $key): string
    {
        $value = $source[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    /** 文字列（空白をそのまま残す。パスワード用） */
    public static function raw(array $source, string $key): string
    {
        $value = $source[$key] ?? '';
        return is_string($value) ? $value : '';
    }

    /** 整数。空なら null、数字でなければ false */
    public static function int(array $source, string $key): int|false|null
    {
        $value = mb_convert_kana(self::str($source, $key), 'n');
        $value = str_replace([',', '円'], '', $value);
        if ($value === '') {
            return null;
        }
        return preg_match('/\A-?\d{1,9}\z/', $value) === 1 ? (int) $value : false;
    }

    /** チェックボックス */
    public static function checked(array $source, string $key): bool
    {
        return self::str($source, $key) !== '' && self::str($source, $key) !== '0';
    }

    /**
     * 日時。<input type="datetime-local"> の "2026-10-03T19:00" や "2026-10-03 19:00" を "2026-10-03 19:00:00" に。
     * 空なら null、形が違えば false
     */
    public static function datetime(array $source, string $key): string|false|null
    {
        $value = self::str($source, $key);
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s'] as $format) {
            $dt = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($dt !== false && $dt->format($format) === $value) {
                return $dt->format('Y-m-d H:i:00');
            }
        }
        return false;
    }

    /** 日付。"2026-10-03" を "2026-10-03" に。空なら null、形が違えば false */
    public static function date(array $source, string $key): string|false|null
    {
        $value = self::str($source, $key);
        if ($value === '') {
            return null;
        }
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return ($dt !== false && $dt->format('Y-m-d') === $value) ? $value : false;
    }

    /** 選択肢のどれか。合わなければ既定値 */
    public static function choice(array $source, string $key, array $choices, string $default): string
    {
        $value = self::str($source, $key);
        return in_array($value, $choices, true) ? $value : $default;
    }
}
