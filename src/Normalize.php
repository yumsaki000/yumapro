<?php

declare(strict_types=1);

namespace App;

/**
 * 名寄せ・出禁チェック・移行で使う値のそろえ方。保存するときも比べるときもこれを通す。
 */
final class Normalize
{
    /**
     * 電話番号 → 数字だけ。「+81」は 0 に、表計算で消えた先頭の 0 は補う。
     */
    public static function phone(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', mb_convert_kana((string) $value, 'n'));
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '81') && strlen($digits) >= 11) {
            $digits = '0' . substr($digits, 2);
        }
        if ($digits[0] !== '0' && (strlen($digits) === 9 || strlen($digits) === 10)) {
            $digits = '0' . $digits;
        }
        return $digits;
    }

    /**
     * メールアドレス → 前後空白なし・小文字。形が正しくなければ null。
     */
    public static function email(mixed $value): ?string
    {
        $email = strtolower(trim(mb_convert_kana((string) $value, 'as')));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * 表示用の名前 → 前後の空白を取り、間の空白は半角1つに。
     */
    public static function name(mixed $value): ?string
    {
        $name = trim((string) preg_replace('/[\s　]+/u', ' ', (string) $value));
        return $name === '' ? null : $name;
    }

    /**
     * フリガナ → 全角カタカナ（ひらがな・半角カナも変換）。間の空白は半角1つに。
     */
    public static function kana(mixed $value): ?string
    {
        return self::name(mb_convert_kana((string) $value, 'KVC'));
    }

    /**
     * 名前・フリガナを比べるための形 → 全角半角をそろえ、空白を全部取る。
     */
    public static function matchKey(mixed $value): string
    {
        $key = mb_convert_kana((string) $value, 'asKVC');
        return mb_strtolower((string) preg_replace('/[\s　]+/u', '', $key));
    }

    /**
     * SNSアカウント → @ とURLの前の部分を取り、小文字に。
     */
    public static function sns(mixed $value): ?string
    {
        $sns = trim(mb_convert_kana((string) $value, 'as'));
        if (preg_match('#^https?://#i', $sns)) {
            $path = trim((string) parse_url($sns, PHP_URL_PATH), '/');
            $sns = $path === '' ? $sns : basename($path);
        }
        $sns = strtolower(ltrim($sns, '@'));
        return $sns === '' ? null : $sns;
    }
}
