<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Crew;
use App\Customers;
use App\Form;
use App\MailTemplates;
use App\Session;
use App\View;

/**
 * クルーの名簿と申込の承認。
 */
final class CrewController
{
    public static function index(): void
    {
        $admin = Auth::requireAdmin();
        $status = Form::choice($_GET, 'status', ['active', 'applied', 'left', 'all'], 'active');
        $q = Form::str($_GET, 'q');
        echo View::render('admin/crew/index', [
            'title' => 'クルー',
            'admin' => $admin,
            'status' => $status,
            'q' => $q,
            'members' => Crew::list($status, $q),
            'counts' => Crew::counts(),
            'applications' => Crew::applications('applied'),
        ], 'admin/layout');
    }

    /** 顧客のクルー状態を変える（顧客の詳細から） */
    public static function status(string $customerId): void
    {
        Auth::requireAdmin();
        $customer = Customers::find((int) $customerId) ?? abort_not_found();
        $status = Form::choice($_POST, 'crew_status', array_keys(Crew::STATUSES), '');
        $joined = Form::date($_POST, 'crew_joined_at');
        $left = Form::date($_POST, 'crew_left_at');
        $note = Form::str($_POST, 'crew_note') ?: null;
        if ($status === '' || $joined === false || $left === false || ($note !== null && mb_strlen($note) > 255)) {
            Session::flash('error', 'クルーの状態か日付の形が正しくありません。');
        } else {
            Crew::setStatus((int) $customer['id'], $status, $joined, $left, $note);
            Session::flash('notice', "「{$customer['name']}」のクルーの状態を「" . Crew::STATUSES[$status] . '」にしました。');
        }
        redirect(RegistrationsController::back('/admin/customers/' . $customer['id']));
    }

    /** 申込を承認／お断り */
    public static function decide(string $id): void
    {
        $admin = Auth::requireAdmin();
        $decision = Form::choice($_POST, 'action', ['approved', 'declined'], '');
        $note = Form::str($_POST, 'note') ?: null;
        $application = $decision === '' ? null : Crew::decide((int) $id, $decision, (int) $admin['id'], $note);
        if ($application === null) {
            Session::flash('error', '申込が見つからないか、操作が正しくありません。');
        } elseif ($decision === 'approved') {
            $customer = Customers::find((int) $application['customer_id']);
            $sent = $customer !== null && MailTemplates::sendCustomerMail('crew_approved', $customer);
            Session::flash('notice', "「{$application['customer_name']}」をクルーにしました。" . ($sent ? '加入のご案内メールを送りました。' : 'メールアドレスがないため、ご案内は運営から直接お願いします。'));
        } else {
            Session::flash('notice', "「{$application['customer_name']}」の申込をお断りにしました。本人への連絡は運営から行ってください。");
        }
        redirect('/admin/crew?status=applied');
    }
}
