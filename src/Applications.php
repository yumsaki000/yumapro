<?php

declare(strict_types=1);

namespace App;

/**
 * 掲示板の申込フォームの受け付け。入力の確認 → 名寄せ → 出禁チェック → 申込の作成。
 */
final class Applications
{
    public const RETURN_INTENTS = ['yes' => 'また参加したい', 'maybe' => 'わからない', 'no' => 'たぶん参加しない'];

    /** この回は今、申込を受け付けているか */
    public static function accepting(array $event): bool
    {
        if ($event['status'] !== 'open') {
            return false;
        }
        return $event['apply_deadline'] === null || strtotime($event['apply_deadline']) > time();
    }

    /** 残りの席数。定員なしなら null */
    public static function remaining(array $event): ?int
    {
        if ($event['capacity'] === null) {
            return null;
        }
        return max(0, (int) $event['capacity'] - (int) $event['applied_count']);
    }

    /** 男女別の定員か料金がある回では性別を必須にする */
    public static function needsGender(array $event): bool
    {
        return $event['capacity_male'] !== null || $event['capacity_female'] !== null
            || $event['fee_male'] !== null || $event['fee_female'] !== null;
    }

    /**
     * 形式ごとの追加項目（event_types.form_fields）。
     *
     * @return list<array{key: string, label: string, type: string, required: bool, options: list<string>}>
     */
    public static function extraFields(array $event): array
    {
        $type = Events::findType((int) $event['event_type_id']);
        $fields = json_decode((string) ($type['form_fields'] ?? '[]'), true);
        $result = [];
        foreach (is_array($fields) ? $fields : [] as $field) {
            if (!is_array($field) || !isset($field['key'], $field['label']) || !preg_match('/\A[a-z0-9_]{1,30}\z/', (string) $field['key'])) {
                continue;
            }
            $result[] = [
                'key' => (string) $field['key'],
                'label' => (string) $field['label'],
                'type' => in_array($field['type'] ?? 'text', ['text', 'textarea', 'select'], true) ? $field['type'] : 'text',
                'required' => (bool) ($field['required'] ?? false),
                'options' => array_values(array_filter((array) ($field['options'] ?? []), 'is_string')),
            ];
        }
        return $result;
    }

    /**
     * 申込フォームの値を確かめる。
     *
     * @return array{values: array, answers: array, errors: list<string>}
     */
    public static function validate(array $event, array $input): array
    {
        $errors = [];
        $values = [
            'name' => Normalize::name(Form::str($input, 'name')),
            'name_kana' => Normalize::kana(Form::str($input, 'name_kana')),
            'email' => Normalize::email(Form::str($input, 'email')),
            'phone' => Normalize::phone(Form::str($input, 'phone')),
            'sns_account' => Normalize::sns(Form::str($input, 'sns_account')),
            'gender' => Form::choice($input, 'gender', array_keys(Customers::GENDERS), '') ?: null,
            'channel' => Form::str($input, 'channel'),
            'mail_opt_in' => Form::checked($input, 'mail_opt_in'),
        ];
        if ($values['name'] === null || mb_strlen($values['name']) > 100) {
            $errors[] = 'お名前を入れてください（100文字まで）。';
        }
        if ($values['name_kana'] !== null && mb_strlen($values['name_kana']) > 100) {
            $errors[] = 'フリガナは100文字までにしてください。';
        }
        if ($values['email'] === null) {
            $errors[] = 'メールアドレスを正しく入れてください（確認メールをお送りします）。';
        }
        if ($values['phone'] === null || strlen($values['phone']) < 10 || strlen($values['phone']) > 15) {
            $errors[] = '電話番号を入れてください（10〜11桁の数字）。';
        }
        if ($values['sns_account'] !== null && mb_strlen($values['sns_account']) > 100) {
            $errors[] = 'SNSアカウントは100文字までにしてください。';
        }
        if (self::needsGender($event) && $values['gender'] === null) {
            $errors[] = '性別を選んでください。';
        }
        if ($values['channel'] === '' || !in_array($values['channel'], Channels::activeNames(), true)) {
            $errors[] = '「このイベントをどこで知りましたか」を選んでください。';
        }

        $answers = [];
        foreach (['referrer' => ['紹介者', 100, false], 'message' => ['意気込み', 1000, false], 'questions' => ['質問・不安なこと', 1000, false]] as $key => [$label, $max, $required]) {
            $value = Form::str($input, $key);
            if (mb_strlen($value) > $max) {
                $errors[] = "{$label}は{$max}文字までにしてください。";
            }
            if ($value !== '') {
                $answers[$key] = $value;
            }
        }
        foreach (self::extraFields($event) as $field) {
            $value = Form::str($input, 'extra_' . $field['key']);
            if ($field['type'] === 'select' && $value !== '' && !in_array($value, $field['options'], true)) {
                $value = '';
            }
            if ($field['required'] && $value === '') {
                $errors[] = "「{$field['label']}」を入れてください。";
            }
            if (mb_strlen($value) > 1000) {
                $errors[] = "「{$field['label']}」は1000文字までにしてください。";
            }
            if ($value !== '') {
                $answers[$field['key']] = $value;
            }
        }

        if (!Form::checked($input, 'consent_notice')) {
            $errors[] = '注意事項に同意してください。';
        }
        if ($event['payment_timing'] === 'prepaid' && !Form::checked($input, 'consent_payment')) {
            $errors[] = 'お支払いについて同意してください。';
        }
        if (!Form::checked($input, 'consent_cancel')) {
            $errors[] = 'キャンセルポリシーに同意してください。';
        }
        return ['values' => $values, 'answers' => $answers, 'errors' => $errors];
    }

    /**
     * 出禁チェック（今のApps Scriptと同じ判定）。連絡先の一致＝確定、名前だけの一致（3文字以上）＝要確認。
     */
    public static function banCheck(?array $customer, array $values): string
    {
        if ($customer !== null && $customer['banned_at'] !== null) {
            return 'confirmed';
        }
        foreach (Customers::findMatches($values['phone'], $values['email'], $values['sns_account']) as $match) {
            if ($match['banned_at'] !== null) {
                return 'confirmed';
            }
        }
        if (mb_strlen(Normalize::matchKey($values['name'])) >= 3) {
            foreach (Customers::findSameName($values['name']) as $match) {
                if ($match['banned_at'] !== null) {
                    return 'suspect';
                }
            }
        }
        return 'none';
    }

    /**
     * 申込を作る。同じ人がこの回にすでに申し込んでいれば、その申込を返す（existing = true）。
     *
     * @return array{registration: array, existing: bool}
     */
    public static function submit(array $event, array $values, array $answers, ?string $entryFrom): array
    {
        $customer = Customers::findByPhoneOrEmail($values['phone'], $values['email']);
        if ($customer !== null) {
            $existing = Registrations::findByEventAndCustomer((int) $event['id'], (int) $customer['id']);
            if ($existing !== null && $existing['status'] !== 'cancelled') {
                return ['registration' => $existing, 'existing' => true];
            }
            Customers::fillEmpty((int) $customer['id'], $values + ['first_channel' => $values['channel']]);
            $customerId = (int) $customer['id'];
            if (Normalize::matchKey($customer['name']) !== Normalize::matchKey($values['name'])) {
                // 台帳の名前と違う名前で申し込んだ（本人の改名か、連絡先を共有する別人か）。あとで見比べられるように残す
                $answers = ['submitted_name' => $values['name']] + $answers;
            }
        } else {
            $customerId = Customers::create($values + ['first_channel' => $values['channel'], 'line_name' => null, 'note' => null]);
        }
        if ($values['mail_opt_in']) {
            Customers::setMailOptIn($customerId, true);
        }
        $banCheck = self::banCheck($customer, $values);

        // 一度キャンセルした人が申し込み直したときは、古い申込を消して作り直す（回×顧客は1件のため）
        if ($customer !== null && isset($existing) && $existing !== null) {
            Database::pdo()->prepare('DELETE FROM registrations WHERE id = ?')->execute([$existing['id']]);
        }

        $isCrew = $customer !== null && $customer['crew_status'] === 'active';
        $result = Registrations::create((int) $event['id'], $customerId, [
            'fee' => Events::feeFor($event, $isCrew ? ($customer['gender'] ?? $values['gender']) : $values['gender'], $isCrew),
            'channel' => $values['channel'],
            'note' => null,
            'source' => 'own_form',
            'prepaid' => false,
            'payment_method' => null,
            'entry_from' => $entryFrom,
            'ban_check' => $banCheck,
            'consented_at' => date('Y-m-d H:i:s'),
            'answers' => $answers === [] ? null : json_encode($answers, JSON_UNESCAPED_UNICODE),
        ], null, false, $banCheck === 'confirmed' ? 'waitlisted' : null);

        $registration = Registrations::find($result['id']);
        return ['registration' => $registration ?? [], 'existing' => false];
    }

    /** ?from= の値（窓口の識別子）。半角英数字だけ許す */
    public static function entryFrom(mixed $value): ?string
    {
        return is_string($value) && preg_match('/\A[A-Za-z0-9_-]{1,30}\z/', $value) ? strtolower($value) : null;
    }
}
