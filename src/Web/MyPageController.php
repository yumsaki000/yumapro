<?php

declare(strict_types=1);

namespace App\Web;

use App\Applications;
use App\Crew;
use App\CustomerAuth;
use App\Customers;
use App\Form;
use App\MailTemplates;
use App\Registrations;
use App\Session;
use App\Settings;
use App\Surveys;
use App\View;

/**
 * 個人専用ページ（確認メールのURLから開く）：申込の確認・キャンセル・案内メールの設定・アンケート。
 */
final class MyPageController
{
    /** /my：ログイン中の人の個人専用ページへ */
    public static function mine(): void
    {
        $customer = CustomerAuth::requireLogin();
        redirect('/my/' . $customer['access_token']);
    }

    public static function show(string $token): void
    {
        $customer = Customers::findByToken($token) ?? abort_not_found();
        $current = CustomerAuth::current();
        if ($current === null || (int) $current['id'] !== (int) $customer['id']) {
            // 本人専用のURLを開いた＝本人なので、講座なども見られるようログイン状態にする
            CustomerAuth::login((int) $customer['id']);
        }
        $upcoming = [];
        $past = [];
        foreach (Registrations::forCustomer((int) $customer['id']) as $r) {
            if ($r['status'] === 'cancelled' && strtotime($r['event_starts_at']) < time()) {
                continue;
            }
            $r['cancellable'] = self::cancellable($r);
            $r['survey'] = Surveys::find((int) $r['id']);
            if (strtotime($r['event_starts_at']) >= time() - 6 * 3600) {
                $upcoming[] = $r;
            } else {
                $past[] = $r;
            }
        }
        echo View::render('my/index', [
            'title' => 'お申込みの確認',
            'customer' => $customer,
            'token' => $token,
            'upcoming' => array_reverse($upcoming),
            'past' => $past,
            'optedIn' => Customers::isMailOptedIn($customer),
            'bankAccount' => trim(Settings::get('bank_account')),
            'contact' => Settings::get('contact_text'),
            'crewStatus' => $customer['crew_status'] ?? 'none',
            'isCrew' => Crew::isActive($customer),
        ]);
    }

    public static function cancel(string $token, string $id): void
    {
        $customer = Customers::findByToken($token) ?? abort_not_found();
        $registration = self::own($customer, $id);
        if (!self::cancellable($registration)) {
            Session::flash('error', 'この申込はここからはキャンセルできません。運営までご連絡ください。');
            redirect('/my/' . $token);
            return;
        }
        $promoted = Registrations::cancelAndPromote((int) $registration['id']);
        $registration['status'] = 'cancelled';
        MailTemplates::sendKind('cancelled', $registration);
        foreach ($promoted as $p) {
            MailTemplates::sendKind('promoted', $p);
        }
        Session::flash('notice', "「{$registration['event_title']}」のお申込みをキャンセルしました。");
        redirect('/my/' . $token);
    }

    /** 案内メールの受け取りを切り替える */
    public static function mail(string $token): void
    {
        $customer = Customers::findByToken($token) ?? abort_not_found();
        $optIn = Form::checked($_POST, 'opt_in');
        Customers::setMailOptIn((int) $customer['id'], $optIn);
        Session::flash('notice', $optIn ? '案内メールを受け取る設定にしました。' : '案内メールの配信を停止しました。');
        redirect('/my/' . $token);
    }

    public static function survey(string $token, string $id): void
    {
        $customer = Customers::findByToken($token) ?? abort_not_found();
        $registration = self::own($customer, $id);
        if (strtotime($registration['event_starts_at']) > time()) {
            abort_not_found();
        }
        $existing = Surveys::find((int) $registration['id']);
        $values = ['satisfaction' => $existing['satisfaction'] ?? '', 'return_intent' => $existing['return_intent'] ?? '', 'comment' => $existing['comment'] ?? ''];
        $errors = [];
        if (is_post()) {
            $satisfaction = Form::int($_POST, 'satisfaction');
            $intent = Form::choice($_POST, 'return_intent', array_keys(Applications::RETURN_INTENTS), '');
            $comment = Form::str($_POST, 'comment');
            $values = ['satisfaction' => $satisfaction, 'return_intent' => $intent, 'comment' => $comment];
            if (!is_int($satisfaction) || $satisfaction < 1 || $satisfaction > 5) {
                $errors[] = '満足度を選んでください。';
            }
            if ($intent === '') {
                $errors[] = 'また参加したいかを選んでください。';
            }
            if (mb_strlen($comment) > 2000) {
                $errors[] = 'コメントは2000文字までにしてください。';
            }
            if ($errors === []) {
                Surveys::save((int) $registration['id'], $satisfaction, $intent, $comment !== '' ? $comment : null);
                echo View::render('my/survey_done', ['title' => 'ありがとうございました', 'token' => $token]);
                return;
            }
        }
        echo View::render('my/survey', [
            'title' => 'アンケート',
            'registration' => $registration,
            'token' => $token,
            'values' => $values,
            'errors' => $errors,
            'answered' => $existing !== null,
        ]);
    }

    private static function own(array $customer, string $id): array
    {
        $registration = Registrations::find((int) $id);
        if ($registration === null || (int) $registration['customer_id'] !== (int) $customer['id']) {
            abort_not_found();
        }
        return $registration;
    }

    /** 本人がキャンセルできるか：申込中で、開催前で、キャンセル期限内 */
    public static function cancellable(array $r): bool
    {
        if (!in_array($r['status'], ['applied', 'waitlisted'], true) || !in_array($r['event_status'], ['open', 'closed'], true)) {
            return false;
        }
        if (strtotime($r['event_starts_at']) <= time()) {
            return false;
        }
        return $r['status'] === 'waitlisted' || $r['event_cancel_deadline'] === null || strtotime($r['event_cancel_deadline']) > time();
    }
}
