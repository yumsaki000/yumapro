<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Form;
use App\Mailer;
use App\Session;
use App\Settings;
use App\View;

/**
 * 設定：掲示板・申込フォーム・メールの文言。
 */
final class SettingsController
{
    private const SECTIONS = [
        '掲示板' => ['site_intro', 'contact_text', 'official_line_url', 'privacy_url'],
        '運営への通知' => ['staff_notify_email', 'staff_notify_all'],
        '申込フォームの同意文' => ['notice_text', 'payment_text', 'cancel_policy_default', 'bank_account'],
        'メールの差出人と署名' => ['mail_from_name', 'mail_from_address', 'mail_signature'],
        '確認メール（申込を受け付けたとき）' => ['mail_confirm_subject', 'mail_confirm_body'],
        'キャンセル待ちメール' => ['mail_waitlist_subject', 'mail_waitlist_body'],
        '繰り上げメール' => ['mail_promoted_subject', 'mail_promoted_body'],
        'キャンセル確認メール' => ['mail_cancelled_subject', 'mail_cancelled_body'],
        '前日リマインド' => ['mail_reminder_subject', 'mail_reminder_body'],
        '翌日お礼（アンケートの案内）' => ['mail_thanks_subject', 'mail_thanks_body'],
    ];

    public static function index(): void
    {
        $admin = Auth::requireAdmin();
        $errors = [];
        if (is_post()) {
            $values = [];
            foreach (Settings::ITEMS as $key => $item) {
                if (!array_key_exists($key, $_POST)) {
                    continue;
                }
                $value = $item[3] ? str_replace("\r\n", "\n", Form::raw($_POST, $key)) : Form::str($_POST, $key);
                $values[$key] = $item[3] ? rtrim($value) : $value;
            }
            if (($values['mail_from_address'] ?? '') !== '' && !filter_var($values['mail_from_address'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = '差出人のメールアドレスの形が正しくありません。';
            }
            foreach (array_filter(array_map('trim', explode(',', $values['staff_notify_email'] ?? ''))) as $address) {
                if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "運営への通知メールの宛先「{$address}」の形が正しくありません。";
                }
            }
            foreach (['official_line_url', 'privacy_url'] as $key) {
                if (($values[$key] ?? '') !== '' && !preg_match('#\Ahttps?://#i', $values[$key])) {
                    $errors[] = Settings::ITEMS[$key][0] . 'は http:// か https:// で始めてください。';
                }
            }
            if ($errors === []) {
                Settings::save($values, (int) $admin['id']);
                Session::flash('notice', '設定を保存しました。');
                redirect('/admin/settings');
                return;
            }
        }
        echo View::render('admin/settings/index', [
            'title' => '設定',
            'admin' => $admin,
            'sections' => self::SECTIONS,
            'values' => Settings::all(),
            'errors' => $errors,
            'fromAddress' => Mailer::fromAddress(),
        ], 'admin/layout');
    }
}
