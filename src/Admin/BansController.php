<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Bans;
use App\Customers;
use App\Form;
use App\MailTemplates;
use App\Registrations;
use App\Session;
use App\View;

/**
 * 出禁リスト：一覧・登録・要確認の申込の処理。
 */
final class BansController
{
    public static function index(): void
    {
        $admin = Auth::requireAdmin();
        $q = Form::str($_GET, 'q');
        echo View::render('admin/bans/index', [
            'title' => '出禁リスト',
            'admin' => $admin,
            'q' => $q,
            'banned' => Bans::list($q),
            'total' => Bans::count(),
            'pending' => Bans::pendingReviews(),
        ], 'admin/layout');
    }

    /**
     * 出禁に登録する。customer_id があればその人、new=1 ならその場で顧客を作って印を付ける。
     */
    public static function create(): void
    {
        $admin = Auth::requireAdmin();
        $source = is_post() ? $_POST : $_GET;
        $customerId = Form::int($source, 'customer_id');
        $isNew = Form::checked($source, 'new');

        if (!is_int($customerId) && !$isNew) {
            $q = Form::str($_GET, 'q');
            echo View::render('admin/bans/form', [
                'title' => '出禁に登録',
                'admin' => $admin,
                'step' => 'search',
                'q' => $q,
                'results' => $q === '' ? [] : Customers::search($q, 1, 30)['items'],
            ], 'admin/layout');
            return;
        }

        $customer = is_int($customerId) ? (Customers::find($customerId) ?? abort_not_found()) : null;
        $values = ['reason' => '', 'note' => ''];
        $customerValues = array_fill_keys(Customers::FIELDS, null);
        $customerValues['name'] = Form::str($_GET, 'name');
        $errors = [];
        $candidates = [];

        if (is_post()) {
            $values = ['reason' => Form::str($_POST, 'reason'), 'note' => rtrim(str_replace("\r\n", "\n", Form::raw($_POST, 'note')))];
            if (mb_strlen($values['reason']) > 255) {
                $errors[] = '理由は255文字までにしてください。';
            }
            if (mb_strlen($values['note']) > 5000) {
                $errors[] = '経緯は5000文字までにしてください。';
            }
            if ($customer === null) {
                ['values' => $customerValues, 'errors' => $customerErrors] = Customers::normalizeInput($_POST);
                $errors = array_merge($customerErrors, $errors);
                if ($customerErrors === []) {
                    $candidates = Customers::findCandidates($customerValues);
                    if ($candidates !== [] && !Form::checked($_POST, 'confirm_duplicate')) {
                        $errors[] = '同じ連絡先か同じ名前の顧客が台帳にいます。その人なら「この人を出禁にする」を押し、別の人なら「別の人として登録する」にチェックを入れてください。';
                    }
                }
            }
            if ($errors === []) {
                $id = $customer !== null ? (int) $customer['id'] : Customers::create($customerValues);
                $name = $customer['name'] ?? $customerValues['name'];
                Customers::ban($id, $values['reason'] !== '' ? $values['reason'] : null, $values['note'] !== '' ? $values['note'] : null, (int) $admin['id']);
                Session::flash('notice', "「{$name}」を出禁にしました。申込フォームと手入力で自動的に判定されます。");
                redirect('/admin/bans');
                return;
            }
        }

        echo View::render('admin/bans/form', [
            'title' => '出禁に登録',
            'admin' => $admin,
            'step' => 'details',
            'customer' => $customer,
            'values' => $values,
            'customerValues' => $customerValues,
            'errors' => $errors,
            'candidates' => $candidates,
            'channels' => [],
        ], 'admin/layout');
    }

    /**
     * 出禁チェックの印が付いた申込を、運営が確認して片づける。
     * clear: 別人だった（印を消す） / confirm: 同じ人だった（この顧客を出禁にして申込をキャンセル）
     */
    public static function review(string $id): void
    {
        $admin = Auth::requireAdmin();
        $registration = Registrations::find((int) $id) ?? abort_not_found();
        $action = Form::choice($_POST, 'action', ['clear', 'confirm'], '');
        if ($action === 'clear') {
            Bans::setCheck((int) $registration['id'], 'cleared');
            Session::flash('notice', "「{$registration['customer_name']}」の出禁チェックを「別人」として片づけました。" . ($registration['status'] === 'waitlisted' ? '申込にするときは「申込にする」を押してください。' : ''));
        } elseif ($action === 'confirm') {
            $customer = Customers::find((int) $registration['customer_id']);
            $reason = $customer !== null && $customer['banned_at'] !== null ? null : mb_substr(Form::str($_POST, 'reason') ?: '申込時の出禁チェックで確認', 0, 255);
            Customers::ban((int) $registration['customer_id'], $reason, null, (int) $admin['id']);
            Bans::setCheck((int) $registration['id'], 'confirmed');
            $promoted = Registrations::cancelAndPromote((int) $registration['id']);
            foreach ($promoted as $p) {
                MailTemplates::sendKind('promoted', $p);
            }
            Session::flash('notice', "「{$registration['customer_name']}」を出禁にし、申込をキャンセルにしました。本人への連絡は運営から行ってください。");
        } else {
            Session::flash('error', '操作が正しくありません。');
        }
        redirect(RegistrationsController::back('/admin/registrations/' . $registration['id'] . '/edit'));
    }
}
