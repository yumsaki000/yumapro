<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Channels;
use App\Crew;
use App\Csv;
use App\Customers;
use App\Form;
use App\Registrations;
use App\Session;
use App\View;

/**
 * 顧客台帳：検索・詳細・登録・編集・出禁・名寄せ。
 */
final class CustomersController
{
    public static function index(): void
    {
        $admin = Auth::requireAdmin();
        $q = Form::str($_GET, 'q');
        $page = Form::int($_GET, 'page');
        echo View::render('admin/customers/index', [
            'title' => '顧客台帳',
            'admin' => $admin,
            'q' => $q,
            'result' => Customers::search($q, is_int($page) ? $page : 1),
            'total' => Customers::count(),
        ], 'admin/layout');
    }

    /**
     * 顧客台帳を CSV で書き出す。全員の連絡先が入るので、オーナーだけにする。
     * 書き出したファイルは運営の中だけで扱い、使い終わったら消す（docs/admin-guide.md）
     */
    public static function csv(): void
    {
        Auth::requireOwner();
        $rows = (function () {
            foreach (Customers::exportRows() as $c) {
                yield [
                    (int) $c['id'],
                    $c['name'],
                    $c['name_kana'],
                    Csv::phone($c['phone']),
                    $c['email'],
                    $c['sns_account'],
                    Customers::GENDERS[$c['gender'] ?? ''] ?? '',
                    $c['line_name'],
                    $c['first_channel'],
                    Customers::isMailOptedIn($c) ? '受け取る' : ($c['mail_opt_out_at'] !== null ? '停止' : ''),
                    $c['crew_status'] !== 'none' ? (Crew::STATUSES[$c['crew_status']] ?? $c['crew_status']) : '',
                    (int) $c['registration_count'],
                    (int) $c['attended_count'],
                    (int) $c['no_show_count'],
                    $c['last_event_at'] !== null ? date('Y-m-d', strtotime((string) $c['last_event_at'])) : '',
                    $c['banned_at'] !== null ? '出禁' : '',
                    $c['ban_reason'],
                    $c['note'],
                    date('Y-m-d', strtotime((string) $c['created_at'])),
                ];
            }
        })();
        Csv::download('顧客台帳_' . date('Ymd') . '.csv', [
            '番号', '名前', 'フリガナ', '電話', 'メール', 'SNS（@なし）', '性別', 'LINEの名前', '最初のきっかけ', '案内メール', 'クルー',
            '申込', '参加（到着）', '無断キャンセル', '最後のイベント', '出禁', '出禁の理由', 'メモ', '登録日',
        ], $rows);
    }

    public static function create(): void
    {
        $admin = Auth::requireAdmin();
        $values = array_fill_keys(Customers::FIELDS, null);
        $values['mail_opt_in'] = false;
        $errors = [];
        $candidates = [];

        if (is_post()) {
            ['values' => $values, 'errors' => $errors] = Customers::normalizeInput($_POST);
            $values['mail_opt_in'] = Form::checked($_POST, 'mail_opt_in');
            if ($errors === []) {
                $candidates = Customers::findCandidates($values);
                if ($candidates !== [] && !Form::checked($_POST, 'confirm_duplicate')) {
                    $errors[] = '同じ連絡先か同じ名前の顧客がいます。別の人なら「別の人として登録する」にチェックを入れて、もう一度保存してください。';
                } else {
                    $id = Customers::create($values);
                    if ($values['mail_opt_in']) {
                        Customers::setMailOptIn($id, true);
                    }
                    Session::flash('notice', "「{$values['name']}」を登録しました。");
                    redirect('/admin/customers/' . $id);
                    return;
                }
            }
        }

        echo View::render('admin/customers/form', [
            'title' => '顧客を登録',
            'admin' => $admin,
            'heading' => '顧客を登録',
            'action' => '/admin/customers/new',
            'customer' => null,
            'values' => $values,
            'errors' => $errors,
            'candidates' => $candidates,
            'channels' => Channels::activeNames($values['first_channel']),
        ], 'admin/layout');
    }

    public static function show(string $id): void
    {
        $admin = Auth::requireAdmin();
        $customer = Customers::find((int) $id) ?? abort_not_found();
        $legacy = $customer['legacy_data'] !== null ? json_decode($customer['legacy_data'], true) : null;
        echo View::render('admin/customers/show', [
            'title' => $customer['name'],
            'admin' => $admin,
            'customer' => $customer,
            'history' => Registrations::forCustomer((int) $customer['id']),
            'referred' => Registrations::referredBy((int) $customer['id']),
            'candidates' => Customers::findCandidates($customer, (int) $customer['id']),
            'legacy' => is_array($legacy) ? $legacy : [],
        ], 'admin/layout');
    }

    public static function edit(string $id): void
    {
        $admin = Auth::requireAdmin();
        $customer = Customers::find((int) $id) ?? abort_not_found();
        $values = array_intersect_key($customer, array_flip(Customers::FIELDS));
        $values['mail_opt_in'] = Customers::isMailOptedIn($customer);
        $errors = [];
        $candidates = [];

        if (is_post()) {
            ['values' => $values, 'errors' => $errors] = Customers::normalizeInput($_POST);
            $values['mail_opt_in'] = Form::checked($_POST, 'mail_opt_in');
            if ($errors === []) {
                $candidates = Customers::findCandidates($values, (int) $customer['id']);
                if ($candidates !== [] && !Form::checked($_POST, 'confirm_duplicate')) {
                    $errors[] = '同じ連絡先か同じ名前の別の顧客がいます。別の人でよければ「別の人として登録する」にチェックを入れて、もう一度保存してください。同じ人なら、保存せずに下の「名寄せ」でまとめてください。';
                } else {
                    Customers::update((int) $customer['id'], $values);
                    Customers::setMailOptIn((int) $customer['id'], $values['mail_opt_in']);
                    Session::flash('notice', "「{$values['name']}」を保存しました。");
                    redirect('/admin/customers/' . $customer['id']);
                    return;
                }
            }
        }

        echo View::render('admin/customers/form', [
            'title' => $customer['name'] . ' の編集',
            'admin' => $admin,
            'heading' => '顧客の編集',
            'action' => '/admin/customers/' . $customer['id'] . '/edit',
            'customer' => $customer,
            'values' => $values,
            'errors' => $errors,
            'candidates' => $candidates,
            'channels' => Channels::activeNames($values['first_channel']),
        ], 'admin/layout');
    }

    public static function ban(string $id): void
    {
        $admin = Auth::requireAdmin();
        $customer = Customers::find((int) $id) ?? abort_not_found();
        $back = RegistrationsController::back('/admin/customers/' . $customer['id']);
        if (Form::str($_POST, 'action') === 'unban') {
            Customers::unban((int) $customer['id']);
            Session::flash('notice', "「{$customer['name']}」の出禁を解除しました。");
        } else {
            $reason = Form::str($_POST, 'reason') ?: null;
            $note = rtrim(str_replace("\r\n", "\n", Form::raw($_POST, 'note'))) ?: null;
            if (($reason !== null && mb_strlen($reason) > 255) || ($note !== null && mb_strlen($note) > 5000)) {
                Session::flash('error', '理由は255文字、経緯は5000文字までにしてください。');
                redirect($back);
                return;
            }
            Customers::ban((int) $customer['id'], $reason, $note, (int) $admin['id']);
            Session::flash('notice', "「{$customer['name']}」を出禁にしました。申込フォームと手入力で自動的に判定されます。");
        }
        redirect($back);
    }

    /** 名寄せ：from_ids の顧客を into_id にまとめる */
    public static function merge(): void
    {
        Auth::requireAdmin();
        $intoId = Form::int($_POST, 'into_id');
        $fromIds = is_array($_POST['from_ids'] ?? null) ? array_filter(array_map('intval', $_POST['from_ids'])) : [];
        $into = is_int($intoId) ? Customers::find($intoId) : null;
        if ($into === null || $fromIds === []) {
            Session::flash('error', 'まとめる顧客を選んでください。');
            redirect('/admin/customers/duplicates');
            return;
        }
        $merged = 0;
        $problems = [];
        foreach ($fromIds as $fromId) {
            if ($fromId === (int) $into['id']) {
                continue;
            }
            $from = Customers::find($fromId);
            if ($from === null) {
                continue;
            }
            $problem = Customers::merge($fromId, (int) $into['id']);
            if ($problem === null) {
                $merged++;
            } else {
                $problems[] = "「{$from['name']}」: {$problem}";
            }
        }
        if ($merged > 0) {
            Session::flash('notice', "{$merged}人を「{$into['name']}」にまとめました。");
        }
        if ($problems !== []) {
            Session::flash('error', implode(' ', $problems));
        }
        redirect('/admin/customers/' . $into['id']);
    }

    public static function duplicates(): void
    {
        $admin = Auth::requireAdmin();
        echo View::render('admin/customers/duplicates', [
            'title' => '名寄せ',
            'admin' => $admin,
            'groups' => Customers::duplicateGroups(),
        ], 'admin/layout');
    }
}
