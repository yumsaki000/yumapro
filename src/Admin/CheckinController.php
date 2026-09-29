<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Checkins;
use App\Events;
use App\Form;
use App\Normalize;
use App\Registrations;
use App\Session;
use App\View;

/**
 * 当日受付：到着と入金をスマホから記録する。
 */
final class CheckinController
{
    public static function index(string $eventId): void
    {
        $admin = Auth::requireAdmin();
        $event = Events::find((int) $eventId) ?? abort_not_found();
        $q = Form::str($_GET, 'q');
        $key = Normalize::matchKey($q);
        $rows = array_values(array_filter(
            Registrations::forEvent((int) $event['id']),
            fn ($r) => $r['status'] !== 'cancelled'
                && ($key === '' || str_contains(Normalize::matchKey($r['customer_name']), $key) || str_contains(Normalize::matchKey($r['customer_kana'] ?? ''), $key))
        ));
        $all = Registrations::forEvent((int) $event['id']);
        $active = array_filter($all, fn ($r) => $r['status'] !== 'cancelled');
        echo View::render('admin/checkin/index', [
            'title' => '当日受付',
            'admin' => $admin,
            'event' => $event,
            'q' => $q,
            'rows' => $rows,
            'total' => count($active),
            'arrived' => count(array_filter($active, fn ($r) => $r['arrived_at'] !== null)),
            'onsiteTotal' => array_sum(array_map(fn ($r) => (int) ($r['paid_amount'] ?? 0), $active)),
        ], 'admin/layout');
    }

    public static function arrive(string $id): void
    {
        $admin = Auth::requireAdmin();
        $registration = Registrations::find((int) $id) ?? abort_not_found();
        $back = RegistrationsController::back('/admin/events/' . $registration['event_id'] . '/checkin');
        if ($registration['status'] === 'cancelled') {
            Session::flash('error', "「{$registration['customer_name']}」はキャンセルの申込です。先に申込に戻してください。");
            redirect($back);
            return;
        }
        $paid = Form::int($_POST, 'paid_amount');
        if ($paid === false || (is_int($paid) && $paid < 0)) {
            Session::flash('error', '入金額は0以上の数字で入れてください。');
            redirect($back);
            return;
        }
        $method = $paid === null ? null : Form::choice($_POST, 'payment_method', array_keys(Registrations::PAYMENT_METHODS), 'cash');
        if ($registration['status'] === 'waitlisted') {
            // 来た人はそのまま参加にする
            Registrations::restore((int) $registration['id'], true);
        }
        Checkins::arrive((int) $registration['id'], $paid, $method, (int) $admin['id']);
        Session::flash('notice', "到着：{$registration['customer_name']} さん" . ($paid !== null ? '（' . yen($paid) . '）' : ''));
        redirect($back);
    }

    public static function undo(string $id): void
    {
        Auth::requireAdmin();
        $registration = Registrations::find((int) $id) ?? abort_not_found();
        Checkins::undo((int) $registration['id']);
        Session::flash('notice', "「{$registration['customer_name']}」の到着を取り消しました。");
        redirect(RegistrationsController::back('/admin/events/' . $registration['event_id'] . '/checkin'));
    }
}
