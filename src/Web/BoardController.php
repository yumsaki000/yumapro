<?php

declare(strict_types=1);

namespace App\Web;

use App\Applications;
use App\Channels;
use App\Customers;
use App\Events;
use App\Form;
use App\MailTemplates;
use App\Session;
use App\Settings;
use App\View;

/**
 * 参加者向けの掲示板（DECK）：回の一覧・詳細・申込フォーム。
 */
final class BoardController
{
    public static function index(): void
    {
        echo View::render('board/index', [
            'title' => APP_NAME,
            'events' => Events::publicList(),
            'intro' => Settings::get('site_intro'),
        ]);
    }

    public static function show(string $slug): void
    {
        $event = self::findPublic($slug);
        echo View::render('board/event', [
            'title' => $event['title'],
            'event' => $event,
            'accepting' => Applications::accepting($event),
            'remaining' => Applications::remaining($event),
            'from' => Applications::entryFrom($_GET['from'] ?? null),
            'cancelPolicy' => trim((string) $event['cancel_policy']) ?: Settings::get('cancel_policy_default'),
        ]);
    }

    public static function apply(string $slug): void
    {
        $event = self::findPublic($slug);
        if (!Applications::accepting($event)) {
            redirect('/e/' . $event['slug']);
            return;
        }
        $from = Applications::entryFrom(is_post() ? ($_POST['from'] ?? null) : ($_GET['from'] ?? null));
        $values = ['name' => '', 'name_kana' => '', 'email' => '', 'phone' => '', 'sns_account' => '', 'gender' => '', 'channel' => '', 'mail_opt_in' => false];
        $raw = [];
        $errors = [];

        if (is_post()) {
            // ロボット避け：見えない欄に何か入っていたら、受け付けたふりをして終える
            if (Form::str($_POST, 'website') !== '') {
                redirect('/e/' . $event['slug'] . '/done');
                return;
            }
            ['values' => $values, 'answers' => $answers, 'errors' => $errors] = Applications::validate($event, $_POST);
            $raw = $_POST;
            if ($errors === []) {
                $result = Applications::submit($event, $values, $answers, $from);
                $registration = $result['registration'];
                if ($registration !== []) {
                    MailTemplates::sendConfirmation($registration);
                }
                Session::flash('applied', json_encode([
                    'status' => $registration['status'] ?? 'applied',
                    'existing' => $result['existing'],
                    'email' => $values['email'],
                    'token' => $registration['customer_token'] ?? '',
                ]));
                redirect('/e/' . $event['slug'] . '/done');
                return;
            }
        }

        echo View::render('board/apply', [
            'title' => 'お申込み：' . $event['title'],
            'event' => $event,
            'values' => $values,
            'raw' => $raw,
            'errors' => $errors,
            'from' => $from,
            'channels' => Channels::activeNames(),
            'extraFields' => Applications::extraFields($event),
            'needsGender' => Applications::needsGender($event),
            'remaining' => Applications::remaining($event),
            'texts' => [
                'notice' => Settings::get('notice_text'),
                'payment' => Settings::get('payment_text'),
                'cancel' => trim((string) $event['cancel_policy']) ?: Settings::get('cancel_policy_default'),
                'privacy_url' => Settings::get('privacy_url'),
            ],
        ]);
    }

    public static function done(string $slug): void
    {
        $event = self::findPublic($slug);
        $applied = json_decode((string) Session::flash('applied'), true);
        echo View::render('board/done', [
            'title' => 'お申込みありがとうございます',
            'event' => $event,
            'applied' => is_array($applied) ? $applied : null,
            'contact' => Settings::get('contact_text'),
            'lineUrl' => Settings::get('official_line_url'),
        ]);
    }

    private static function findPublic(string $slug): array
    {
        $event = preg_match('/\A[0-9a-f]{12}\z/', $slug) ? Events::findBySlug($slug) : null;
        if ($event === null || !in_array($event['status'], ['open', 'closed', 'done'], true)) {
            abort_not_found();
        }
        return $event;
    }
}
