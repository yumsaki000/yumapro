<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Channels;
use App\Customers;
use App\Database;
use App\Events;
use App\Form;
use App\Registrations;
use App\Session;
use App\View;

/**
 * 申込の手入力（スタッフの声かけで決まった人）と、申込の変更・キャンセル・入金確認。
 */
final class RegistrationsController
{
    /**
     * 申込を追加。customer_id がなければ顧客を探す画面、あれば（または new=1 なら）内容を入れる画面。
     */
    public static function create(string $eventId): void
    {
        $admin = Auth::requireAdmin();
        $event = Events::find((int) $eventId) ?? abort_not_found();
        $source = is_post() ? $_POST : $_GET;
        $customerId = Form::int($source, 'customer_id');
        $isNew = Form::checked($source, 'new');

        if (!is_int($customerId) && !$isNew) {
            $q = Form::str($_GET, 'q');
            $results = $q === '' ? [] : Customers::search($q, 1, 30)['items'];
            foreach ($results as &$row) {
                $row['registered'] = Registrations::findByEventAndCustomer((int) $event['id'], (int) $row['id']) !== null;
            }
            unset($row);
            echo View::render('admin/registrations/new', [
                'title' => '申込を追加',
                'admin' => $admin,
                'event' => $event,
                'q' => $q,
                'results' => $results,
            ], 'admin/layout');
            return;
        }

        $customer = null;
        $existing = null;
        if (is_int($customerId)) {
            $customer = Customers::find($customerId) ?? abort_not_found();
            $existing = Registrations::findByEventAndCustomer((int) $event['id'], $customerId);
        }
        $values = [
            'fee' => Events::feeFor($event, $customer['gender'] ?? null),
            'channel' => null,
            'note' => null,
            'prepaid' => false,
            'payment_method' => 'bank_transfer',
        ];
        $customerValues = array_fill_keys(Customers::FIELDS, null);
        $customerValues['name'] = Form::str($_GET, 'name');
        $customerValues['mail_opt_in'] = false;
        $errors = [];
        $candidates = [];
        $full = Registrations::isFull(Database::pdo(), $event, $customer['gender'] ?? null);

        if (is_post()) {
            [$values, $errors] = self::read($_POST);
            if ($customer === null) {
                ['values' => $customerValues, 'errors' => $customerErrors] = Customers::normalizeInput($_POST);
                $customerValues['mail_opt_in'] = Form::checked($_POST, 'mail_opt_in');
                $errors = array_merge($customerErrors, $errors);
                if ($customerErrors === []) {
                    $candidates = Customers::findCandidates($customerValues);
                    if ($candidates !== [] && !Form::checked($_POST, 'confirm_duplicate')) {
                        $errors[] = '同じ連絡先か同じ名前の顧客がいます。その人なら戻って選び直し、別の人なら「別の人として登録する」にチェックを入れてください。';
                    }
                }
            } else {
                if ($existing !== null) {
                    $errors[] = 'この人はこの回にもう申し込んでいます。';
                }
                if ($customer['banned_at'] !== null && !Form::checked($_POST, 'confirm_ban')) {
                    $errors[] = 'この人は出禁です。それでも申し込むなら「出禁を確認したうえで申し込む」にチェックを入れてください。';
                }
            }
            if ($errors === []) {
                if ($customer === null) {
                    $customerId = Customers::create($customerValues);
                    if ($customerValues['mail_opt_in']) {
                        Customers::setMailOptIn($customerId, true);
                    }
                    $name = $customerValues['name'];
                } else {
                    $name = $customer['name'];
                }
                $result = Registrations::create(
                    (int) $event['id'],
                    $customerId,
                    $values + ['source' => 'manual'],
                    (int) $admin['id'],
                    Form::checked($_POST, 'force_apply')
                );
                Session::flash('notice', $result['status'] === 'applied'
                    ? "「{$name}」を申込に追加しました。"
                    : "定員に達しているため、「{$name}」をキャンセル待ちにしました。");
                redirect('/admin/events/' . $event['id']);
                return;
            }
        }

        echo View::render('admin/registrations/create', [
            'title' => '申込を追加',
            'admin' => $admin,
            'event' => $event,
            'customer' => $customer,
            'existing' => $existing,
            'values' => $values,
            'customerValues' => $customerValues,
            'errors' => $errors,
            'candidates' => $candidates,
            'full' => $full,
            'channels' => Channels::activeNames($values['channel'] ?? null),
            'customerChannels' => Channels::activeNames($customerValues['first_channel'] ?? null),
        ], 'admin/layout');
    }

    public static function edit(string $id): void
    {
        $admin = Auth::requireAdmin();
        $registration = Registrations::find((int) $id) ?? abort_not_found();
        $values = [
            'fee' => (int) $registration['fee'],
            'channel' => $registration['channel'],
            'note' => $registration['note'],
            'prepaid' => $registration['prepaid_at'] !== null,
            'payment_method' => $registration['payment_method'] ?? 'bank_transfer',
        ];
        $errors = [];

        if (is_post()) {
            [$values, $errors] = self::read($_POST);
            if ($errors === []) {
                Registrations::update((int) $registration['id'], $values);
                Registrations::setPrepaid((int) $registration['id'], $values['prepaid'], $values['payment_method']);
                Session::flash('notice', "「{$registration['customer_name']}」の申込を保存しました。");
                redirect('/admin/events/' . $registration['event_id']);
                return;
            }
        }

        echo View::render('admin/registrations/edit', [
            'title' => '申込の詳細',
            'admin' => $admin,
            'registration' => $registration,
            'values' => $values,
            'errors' => $errors,
            'channels' => Channels::activeNames($values['channel'] ?? null),
        ], 'admin/layout');
    }

    public static function cancel(string $id): void
    {
        Auth::requireAdmin();
        $registration = Registrations::find((int) $id) ?? abort_not_found();
        Registrations::cancel((int) $registration['id']);
        Session::flash('notice', "「{$registration['customer_name']}」をキャンセルにしました。");
        redirect(self::back('/admin/events/' . $registration['event_id']));
    }

    public static function restore(string $id): void
    {
        Auth::requireAdmin();
        $registration = Registrations::find((int) $id) ?? abort_not_found();
        $status = Registrations::restore((int) $registration['id'], Form::checked($_POST, 'force_apply'));
        Session::flash('notice', $status === 'applied'
            ? "「{$registration['customer_name']}」を申込にしました。"
            : "定員に達しているため、「{$registration['customer_name']}」はキャンセル待ちのままです。定員を超えて入れるときは、申込の詳細から「定員を超えて申込にする」を使ってください。");
        redirect(self::back('/admin/events/' . $registration['event_id']));
    }

    /** 前払いの入金確認を付ける／外す */
    public static function prepaid(string $id): void
    {
        Auth::requireAdmin();
        $registration = Registrations::find((int) $id) ?? abort_not_found();
        $paid = Form::checked($_POST, 'paid');
        $method = Form::choice($_POST, 'payment_method', array_keys(Registrations::PAYMENT_METHODS), 'bank_transfer');
        Registrations::setPrepaid((int) $registration['id'], $paid, $method);
        Session::flash('notice', $paid
            ? "「{$registration['customer_name']}」の入金を確認済みにしました。"
            : "「{$registration['customer_name']}」の入金確認を外しました。");
        redirect(self::back('/admin/events/' . $registration['event_id']));
    }

    /**
     * 申込の項目を読む。
     *
     * @return array{array, list<string>}
     */
    private static function read(array $input): array
    {
        $errors = [];
        $fee = Form::int($input, 'fee');
        if (!is_int($fee) || $fee < 0) {
            $errors[] = '参加費は0以上の数字で入れてください。';
            $fee = 0;
        }
        $channel = Form::str($input, 'channel');
        $note = Form::str($input, 'note');
        if (mb_strlen($note) > 255) {
            $errors[] = 'メモは255文字までにしてください。';
        }
        $values = [
            'fee' => $fee,
            'channel' => $channel !== '' && mb_strlen($channel) <= 50 ? $channel : null,
            'note' => $note !== '' ? $note : null,
            'prepaid' => Form::checked($input, 'prepaid'),
            'payment_method' => Form::choice($input, 'payment_method', array_keys(Registrations::PAYMENT_METHODS), 'bank_transfer'),
        ];
        return [$values, $errors];
    }

    /** 操作のあとに戻る先（受付画面など）。管理画面の中だけ許す */
    public static function back(string $default): string
    {
        $back = Form::str($_POST, 'back');
        return $back === '' ? $default : safe_admin_path($back);
    }
}
