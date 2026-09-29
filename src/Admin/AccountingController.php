<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Books;
use App\Csv;
use App\Events;
use App\Expenses;
use App\Form;
use App\Session;
use App\View;

/**
 * 会計：年ごとの売上・経費（確定申告向けのまとめと明細）、イベントごとの収支、経費の入力。
 */
final class AccountingController
{
    public static function index(): void
    {
        $admin = Auth::requireAdmin();
        $period = Books::period(self::year());
        $rows = [];
        foreach (Events::listWithBalance() as $row) {
            if (Books::yearOf((string) $row['starts_at']) !== $period['year']) {
                continue;
            }
            $row['income'] = (int) $row['income_prepaid'] + (int) $row['income_onsite'];
            $row['balance'] = $row['income'] - (int) $row['expense_total'] - (int) $row['organizer_amount'];
            $rows[] = $row;
        }
        $common = array_values(array_filter(Expenses::forPeriod($period['from'], $period['to']), fn ($x) => $x['event_id'] === null));
        echo View::render('admin/accounting/index', [
            'title' => '会計',
            'admin' => $admin,
            'period' => $period,
            'years' => Books::years(),
            'summary' => Books::summary($period),
            'rows' => $rows,
            'common' => $common,
            'values' => self::emptyExpense(),
        ], 'admin/layout');
    }

    /**
     * 会計の書き出し（その年の分）。
     * summary＝年間のまとめ（月別・勘定科目別）、sales＝売上の明細、expenses＝経費の明細、events＝イベントごとの収支
     */
    public static function csv(): void
    {
        Auth::requireAdmin();
        $period = Books::period(self::year());
        $kind = Form::choice($_GET, 'kind', ['summary', 'sales', 'expenses', 'events'], 'summary');
        $suffix = '_' . $period['year'] . '.csv';

        if ($kind === 'sales') {
            $rows = (function () use ($period) {
                foreach (Books::sales($period['from'], $period['to']) as $s) {
                    yield [$s['date'], '売上高', $s['kind'], $s['subject'], $s['name'], $s['method'], $s['received_at'] !== null ? date('Y-m-d', strtotime($s['received_at'])) : '', $s['amount']];
                }
            })();
            Csv::download('売上の明細' . $suffix, ['日付', '勘定科目', '区分', '内容', '名前', '受け取り方法', '入金日', '金額'], $rows);
            return;
        }

        if ($kind === 'expenses') {
            $rows = (function () use ($period) {
                foreach (Expenses::forPeriod($period['from'], $period['to']) as $x) {
                    yield [
                        (string) $x['spent_on'],
                        ($x['account'] ?? '') !== '' ? $x['account'] : Expenses::guessAccount((string) $x['item']),
                        $x['item'],
                        $x['payee'],
                        $x['event_title'] ?? '（イベントに付かない経費）',
                        (int) $x['amount'],
                        $x['has_receipt'] ? 'あり' : 'なし',
                        $x['memo'],
                        $x['created_by_name'],
                    ];
                }
                foreach (Books::organizerAmounts($period['from'], $period['to']) as $o) {
                    yield [$o['date'], '（主催分・要確認）', '主催分', '', $o['title'], $o['amount'], '', '経費になるかは中身しだい（税理士さんに確認）', ''];
                }
            })();
            Csv::download('経費の明細' . $suffix, ['日付', '勘定科目', '項目', '支払先', 'イベント', '金額', '領収書', 'メモ', '入力した人'], $rows);
            return;
        }

        if ($kind === 'events') {
            $rows = (function () use ($period) {
                foreach (Events::listWithBalance() as $r) {
                    if (Books::yearOf((string) $r['starts_at']) !== $period['year']) {
                        continue;
                    }
                    $income = (int) $r['income_prepaid'] + (int) $r['income_onsite'];
                    yield [
                        date('Y-m-d', strtotime((string) $r['starts_at'])), $r['title'], $r['type_name'], Events::STATUSES[$r['status']] ?? $r['status'],
                        (int) $r['income_prepaid'], (int) $r['income_onsite'], $income, (int) $r['expense_total'], (int) $r['organizer_amount'],
                        $income - (int) $r['expense_total'] - (int) $r['organizer_amount'],
                    ];
                }
            })();
            Csv::download('イベントごとの収支' . $suffix, ['日付', 'イベント', '形式', '状態', '前払いの収入', '当日の収入', '収入', '経費', '主催分', '収支'], $rows);
            return;
        }

        $summary = Books::summary($period);
        $rows = (function () use ($summary, $period) {
            yield [$period['label']];
            yield [];
            yield ['■ 年間の合計'];
            yield ['売上（イベント参加費）', $summary['sales_event']];
            yield ['売上（講座）', $summary['sales_course']];
            yield ['売上の合計', $summary['sales']];
            yield ['経費の合計', $summary['expenses']];
            yield ['主催分（要確認）', $summary['organizer']];
            yield ['差し引き（売上 − 経費 − 主催分）', $summary['balance']];
            yield [];
            yield ['■ 勘定科目ごとの経費'];
            foreach ($summary['accounts'] as $account => $amount) {
                yield [$account, $amount];
            }
            yield [];
            yield ['■ 月ごと', '売上（イベント）', '売上（講座）', '経費', '主催分', '差し引き'];
            foreach ($summary['months'] as $month => $m) {
                yield [
                    date('Y年n月', strtotime($month . '-01')), $m['sales_event'], $m['sales_course'], $m['expenses'], $m['organizer'],
                    $m['sales_event'] + $m['sales_course'] - $m['expenses'] - $m['organizer'],
                ];
            }
        })();
        Csv::download('年間のまとめ' . $suffix, ['項目', '金額'], $rows);
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
            'values' => self::emptyExpense(),
        ], 'admin/layout');
    }

    /** イベントの経費を追加する */
    public static function addExpense(string $eventId): void
    {
        $admin = Auth::requireAdmin();
        $event = Events::find((int) $eventId) ?? abort_not_found();
        [$data, $errors] = self::readExpense($_POST, false);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
        } else {
            Expenses::add((int) $event['id'], $data, (int) $admin['id']);
            Session::flash('notice', "「{$data['item']}」" . yen($data['amount']) . 'を追加しました。');
        }
        redirect('/admin/events/' . $event['id'] . '/accounting');
    }

    /** イベントに付かない経費（サーバー代・広告費など）を追加する */
    public static function addCommonExpense(): void
    {
        $admin = Auth::requireAdmin();
        [$data, $errors] = self::readExpense($_POST, true);
        if ($errors !== []) {
            Session::flash('error', implode(' ', $errors));
        } else {
            Expenses::add(null, $data, (int) $admin['id']);
            Session::flash('notice', "「{$data['item']}」" . yen($data['amount']) . 'を追加しました。');
        }
        redirect('/admin/accounting?year=' . Books::yearOf((string) ($data['paid_on'] ?? date('Y-m-d'))) . '#common');
    }

    /** 経費を直す（勘定科目・支払った日・領収書の有無など） */
    public static function editExpense(string $id): void
    {
        $admin = Auth::requireAdmin();
        $expense = Expenses::find((int) $id) ?? abort_not_found();
        $event = $expense['event_id'] !== null ? Events::find((int) $expense['event_id']) : null;
        $values = $expense;
        $errors = [];
        if (is_post()) {
            [$data, $errors] = self::readExpense($_POST, $event === null);
            if ($errors === []) {
                Expenses::update((int) $expense['id'], $data);
                Session::flash('notice', "「{$data['item']}」を保存しました。");
                redirect(self::expenseBack($expense));
                return;
            }
            $values = $data + $expense;
        }
        echo View::render('admin/accounting/expense_form', [
            'title' => '経費を直す',
            'admin' => $admin,
            'expense' => $expense,
            'event' => $event,
            'values' => $values,
            'errors' => $errors,
            'items' => $event !== null ? Events::expenseItemChoices($event) : [],
            'back' => self::expenseBack($expense),
        ], 'admin/layout');
    }

    public static function deleteExpense(string $id): void
    {
        Auth::requireAdmin();
        $expense = Expenses::find((int) $id) ?? abort_not_found();
        Expenses::delete((int) $expense['id']);
        Session::flash('notice', "「{$expense['item']}」を消しました。");
        redirect(self::expenseBack($expense));
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

    /**
     * 経費の入力を読む。$needsDate のとき（イベントに付かない経費）は支払った日が必須。
     *
     * @return array{0: array, 1: list<string>}
     */
    private static function readExpense(array $input, bool $needsDate): array
    {
        $errors = [];
        $item = Form::str($input, 'item');
        $amount = Form::int($input, 'amount');
        $paidOn = Form::date($input, 'paid_on');
        $account = Form::str($input, 'account');
        $payee = Form::str($input, 'payee');
        $memo = Form::str($input, 'memo');
        if ($item === '' || mb_strlen($item) > 100) {
            $errors[] = '項目は1〜100文字で入れてください。';
        }
        if (!is_int($amount) || $amount < 0) {
            $errors[] = '金額は0以上の数字で入れてください。';
        }
        if ($paidOn === false) {
            $errors[] = '支払った日の形が正しくありません。';
        } elseif ($paidOn === null && $needsDate) {
            $errors[] = '支払った日を入れてください。';
        }
        if ($account !== '' && !isset(Expenses::ACCOUNTS[$account])) {
            $errors[] = '勘定科目を一覧から選んでください。';
        }
        if (mb_strlen($payee) > 100) {
            $errors[] = '支払先は100文字までにしてください。';
        }
        if (mb_strlen($memo) > 255) {
            $errors[] = 'メモは255文字までにしてください。';
        }
        return [[
            'item' => $item,
            'amount' => is_int($amount) ? $amount : 0,
            'account' => $account !== '' ? $account : null,
            'paid_on' => is_string($paidOn) ? $paidOn : null,
            'payee' => $payee !== '' ? $payee : null,
            'has_receipt' => Form::checked($input, 'has_receipt'),
            'memo' => $memo !== '' ? $memo : null,
        ], $errors];
    }

    private static function emptyExpense(): array
    {
        return ['item' => '', 'amount' => '', 'account' => null, 'paid_on' => null, 'payee' => null, 'has_receipt' => false, 'memo' => null];
    }

    private static function expenseBack(array $expense): string
    {
        if ($expense['event_id'] !== null) {
            return '/admin/events/' . (int) $expense['event_id'] . '/accounting';
        }
        return '/admin/accounting?year=' . Books::yearOf((string) ($expense['paid_on'] ?? $expense['created_at'])) . '#common';
    }

    private static function year(): int
    {
        $year = Form::int($_GET, 'year');
        return is_int($year) && $year >= 2000 && $year <= 2100 ? $year : Books::currentYear();
    }
}
