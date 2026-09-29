<?php

declare(strict_types=1);

namespace App\Web;

use App\Crew;
use App\Customers;
use App\Form;
use App\MailTemplates;
use App\Normalize;
use App\Session;
use App\Settings;
use App\View;

/**
 * クルー募集ページと申込フォーム。
 */
final class CrewController
{
    public static function page(): void
    {
        $values = ['name' => '', 'name_kana' => '', 'email' => '', 'phone' => '', 'gender' => '', 'region' => '', 'contact_pref' => 'line', 'comment' => ''];
        $errors = [];

        if (is_post()) {
            if (Form::str($_POST, 'website') !== '') {
                redirect('/crew/done');
                return;
            }
            $values = [
                'name' => Form::str($_POST, 'name'),
                'name_kana' => Form::str($_POST, 'name_kana'),
                'email' => Form::str($_POST, 'email'),
                'phone' => Form::str($_POST, 'phone'),
                'gender' => Form::choice($_POST, 'gender', array_keys(Customers::GENDERS), ''),
                'region' => Form::str($_POST, 'region'),
                'contact_pref' => Form::choice($_POST, 'contact_pref', array_keys(Crew::CONTACT_PREFS), 'line'),
                'comment' => Form::str($_POST, 'comment'),
            ];
            $name = Normalize::name($values['name']);
            $email = Normalize::email($values['email']);
            $phone = Normalize::phone($values['phone']);
            if ($name === null || mb_strlen($name) > 100) {
                $errors[] = 'お名前を入れてください（100文字まで）。';
            }
            if ($email === null) {
                $errors[] = 'メールアドレスを正しく入れてください。';
            }
            if ($phone === null || strlen($phone) < 10 || strlen($phone) > 15) {
                $errors[] = '電話番号を入れてください（10〜11桁の数字）。';
            }
            if (mb_strlen($values['region']) > 100 || mb_strlen($values['comment']) > 1000) {
                $errors[] = '地域は100文字、コメントは1000文字までにしてください。';
            }
            if (!Form::checked($_POST, 'consent_crew')) {
                $errors[] = '利用規約とクルーポリシーに同意してください。';
            }
            if ($errors === []) {
                $customer = Customers::findByPhoneOrEmail($phone, $email);
                $fields = ['name' => $name, 'name_kana' => Normalize::kana($values['name_kana']), 'phone' => $phone, 'email' => $email,
                    'sns_account' => null, 'gender' => $values['gender'] ?: null, 'line_name' => null, 'first_channel' => null, 'note' => null];
                if ($customer !== null) {
                    Customers::fillEmpty((int) $customer['id'], $fields);
                    $customerId = (int) $customer['id'];
                } else {
                    $customerId = Customers::create($fields);
                }
                if (Form::checked($_POST, 'mail_opt_in')) {
                    Customers::setMailOptIn($customerId, true);
                }
                $customer = Customers::find($customerId);
                $state = 'applied';
                if (Crew::isActive($customer)) {
                    $state = 'already';
                } else {
                    $answers = array_filter([
                        'region' => $values['region'],
                        'contact_pref' => Crew::CONTACT_PREFS[$values['contact_pref']],
                        'comment' => $values['comment'],
                    ]);
                    $result = Crew::apply($customerId, $answers);
                    $state = $result['existing'] ? 'existing' : 'applied';
                    MailTemplates::sendCustomerMail('crew_applied', $customer);
                    if (!$result['existing']) {
                        MailTemplates::notifyStaffText(
                            "【クルー申込】{$customer['name']}",
                            [
                                'クルー募集ページから申込がありました。',
                                '',
                                "■ 名前：{$customer['name']}" . ($customer['name_kana'] !== null ? "（{$customer['name_kana']}）" : ''),
                                '■ 電話：' . ($customer['phone'] ?? '—'),
                                '■ メール：' . ($customer['email'] ?? '—'),
                                '■ 地域：' . ($values['region'] !== '' ? $values['region'] : '—'),
                                '■ 連絡の希望：' . Crew::CONTACT_PREFS[$values['contact_pref']],
                                '■ コメント：' . ($values['comment'] !== '' ? $values['comment'] : '—'),
                                '',
                                '承認・お断りはこちら：',
                                rtrim((string) \App\Config::get('APP_URL', ''), '/') . '/admin/crew?status=applied',
                            ],
                            $customerId
                        );
                    }
                }
                Session::flash('crew_applied', json_encode(['state' => $state, 'email' => $email, 'token' => $customer['access_token']]));
                redirect('/crew/done');
                return;
            }
        }

        echo View::render('board/crew', [
            'title' => 'クルー募集',
            'intro' => Settings::get('crew_intro'),
            'benefits' => array_values(array_filter(array_map('trim', explode("\n", Settings::get('crew_benefits'))))),
            'feeText' => Settings::get('crew_fee_text'),
            'termsUrl' => Settings::get('crew_terms_url'),
            'policyUrl' => Settings::get('crew_policy_url'),
            'noticeText' => Settings::get('crew_notice_text'),
            'privacyUrl' => Settings::get('privacy_url'),
            'values' => $values,
            'raw' => $_POST,
            'errors' => $errors,
        ]);
    }

    public static function done(): void
    {
        $applied = json_decode((string) Session::flash('crew_applied'), true);
        echo View::render('board/crew_done', [
            'title' => 'お申込みありがとうございます',
            'applied' => is_array($applied) ? $applied : null,
            'contact' => Settings::get('contact_text'),
        ]);
    }
}
