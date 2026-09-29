<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Events;
use App\Expenses;
use App\Form;
use App\Session;
use App\View;

/**
 * 会計：回ごとの収入・経費・主催分・収支。
 */
final class AccountingController
{
    public static function index(): void
    {
        $admin = Auth::requireAdmin();
        $rows = Events::listWithBalance();
        foreach ($rows as &$row) {
            $row['income'] = (int) $row['income_prepaid'] + (int) $row['income_onsite'];
            $row['balance'] = $row['income'] - (int) $row['expense_total'] - (int) $row['organizer_amount'];
        }
        unset($row);
        echo View::render('admin/accounting/index', [
            'title' => '会計',
            'admin' => $admin,
            'rows' => $rows,
        ], 'admin/layout');
    }

    public static function show(string $eventId): void
    {
        $admin = Auth::requireAdmin();
        $event = Events::find((int) $eventId) ?? abort_not_found();
        echo View::render('admin/accounting/show', [
            'title' => $event['title'] . ' の会計',
            'admin' => $admin,
            'event' => $event,
            'summary' => Expenses::summary((int) $event['id']),
            'expenses' => Expenses::forEvent((int) $event['id']),
            'items' => Events::expenseItemChoices($event),
        ], 'admin/layout');
    }

    public static function addExpense(string $eventId): void
    {
        $admin = Auth::requireAdmin();
        $event = Events::find((int) $eventId) ?? abort_not_found();
        $item = Form::str($_POST, 'item');
        $amount = Form::int($_POST, 'amount');
        $memo = Form::str($_POST, 'memo') ?: null;
        if ($item === '' || mb_strlen($item) > 100) {
            Session::flash('error', '項目は1〜100文字で入れてください。');
        } elseif (!is_int($amount) || $amount < 0) {
            Session::flash('error', '金額は0以上の数字で入れてください。');
        } elseif ($memo !== null && mb_strlen($memo) > 255) {
            Session::flash('error', 'メモは255文字までにしてください。');
        } else {
            Expenses::add((int) $event['id'], $item, $amount, $memo, (int) $admin['id']);
            Session::flash('notice', "「{$item}」" . yen($amount) . 'を追加しました。');
        }
        redirect('/admin/events/' . $event['id'] . '/accounting');
    }

    public static function deleteExpense(string $id): void
    {
        Auth::requireAdmin();
        $expense = Expenses::find((int) $id) ?? abort_not_found();
        Expenses::delete((int) $expense['id']);
        Session::flash('notice', "「{$expense['item']}」を消しました。");
        redirect('/admin/events/' . $expense['event_id'] . '/accounting');
    }

    public static function organizer(string $eventId): void
    {
        Auth::requireAdmin();
        $event = Events::find((int) $eventId) ?? abort_not_found();
        $amount = Form::int($_POST, 'organizer_amount');
        if ($amount === false || (is_int($amount) && $amount < 0)) {
            Session::flash('error', '主催分は0以上の数字で入れてください。');
        } else {
            Events::setOrganizerAmount((int) $event['id'], $amount ?? 0);
            Session::flash('notice', '主催分を保存しました。');
        }
        redirect('/admin/events/' . $event['id'] . '/accounting');
    }
}
