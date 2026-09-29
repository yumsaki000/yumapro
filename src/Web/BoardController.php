<?php

declare(strict_types=1);

namespace App\Web;

use App\Applications;
use App\Calendar;
use App\Channels;
use App\Customers;
use App\Events;
use App\Faq;
use App\Form;
use App\MailTemplates;
use App\Markup;
use App\Photos;
use App\Session;
use App\Settings;
use App\View;

/**
 * 参加者向けの掲示板：回の一覧・詳細・申込フォーム。
 */
final class BoardController
{
    public static function index(): void
    {
        $types = Events::publicTypes();
        $type = is_string($_GET['type'] ?? null) && isset($types[$_GET['type']]) ? $_GET['type'] : null;
        $from = Applications::entryFrom($_GET['from'] ?? null);
        $name = Settings::get('public_name');
        echo View::render('board/index', [
            'title' => 'イベント一覧',
            'events' => Events::publicList($type),
            'types' => $types,
            'type' => $type,
            'from' => $from,
            'intro' => Settings::get('site_intro'),
            'hero' => ['title' => Settings::get('site_tagline'), 'lead' => Settings::get('site_lead')],
            'wide' => true,
            'meta' => [
                'description' => Markup::plain(Settings::get('site_intro'), 120),
                'url' => app_url('/'),
                'index' => true,
            ],
        ]);
    }

    public static function show(string $slug): void
    {
        self::render(self::findPublic($slug), false);
    }

    /**
     * 回のページを出す。$preview のときは管理画面からの確認用（下書きでも出す。申込はできない）
     */
    public static function render(array $event, bool $preview): void
    {
        $url = app_url('/e/' . $event['slug']);
        $photos = Events::photos((int) $event['id']);
        $faq = Faq::parse(trim((string) $event['faq']) !== '' ? (string) $event['faq'] : Settings::get('event_faq'));
        $summary = trim((string) $event['summary']) !== '' ? (string) $event['summary'] : Markup::plain($event['description'], 120);
        $accepting = !$preview && Applications::accepting($event);
        echo View::render('board/event', [
            'title' => $event['title'],
            'event' => $event,
            'preview' => $preview,
            'accepting' => $accepting,
            'remaining' => Applications::remaining($event),
            'from' => Applications::entryFrom($_GET['from'] ?? null),
            'cancelPolicy' => trim((string) $event['cancel_policy']) ?: Settings::get('cancel_policy_default'),
            'faq' => $faq,
            'photos' => $photos,
            'others' => Events::others($event, 3),
            'pageUrl' => $url,
            'googleCalendarUrl' => Calendar::googleUrl($event, $url),
            'community' => Settings::get('community_intro'),
            'aboutUrl' => Settings::get('official_about_url'),
            'lineUrl' => Settings::get('official_line_url'),
            'wide' => true,
            'meta' => [
                'description' => Markup::plain($summary, 120),
                'image' => Photos::absoluteUrl($event['photo']),
                'url' => $url,
                'type' => 'article',
                'index' => !$preview && in_array($event['status'], ['open', 'closed'], true),
                'jsonld' => $preview ? null : self::structuredData($event, $url, $summary),
            ],
        ]);
    }

    /** カレンダーに入れる .ics（iPhone のカレンダーなどで開ける） */
    public static function calendar(string $slug): void
    {
        $event = self::findPublic($slug);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="minato-' . $event['slug'] . '.ics"');
        echo Calendar::ics($event, app_url('/e/' . $event['slug']));
    }

    /**
     * 公式サイトに埋め込む回の一覧（iframe の中身）。ヘッダーやフッターは付けない。
     * ?type=joshikai で形式を絞り、?limit=3 で件数を絞れる
     */
    public static function embed(): void
    {
        $types = Events::publicTypes();
        $type = is_string($_GET['type'] ?? null) && isset($types[$_GET['type']]) ? $_GET['type'] : null;
        $limit = Form::int($_GET, 'limit');
        $limit = is_int($limit) && $limit > 0 ? min($limit, 30) : 0;
        self::allowFraming();
        echo View::render('board/embed', [
            'title' => 'イベント一覧',
            'events' => Events::publicList($type, $limit),
            'moreUrl' => app_url('/?from=site' . ($type !== null ? '&type=' . rawurlencode($type) : '')),
        ], null);
    }

    /** 回の一覧の RSS（公式サイトの WordPress の「RSS」ブロックなどで表示できる） */
    public static function feed(): void
    {
        header('Content-Type: application/rss+xml; charset=utf-8');
        header('Cache-Control: public, max-age=600');
        echo View::render('board/feed', [
            'events' => Events::publicList(),
            'name' => Settings::get('public_name'),
            'siteUrl' => app_url('/'),
            'intro' => Markup::plain(Settings::get('site_intro')),
        ], null);
    }

    /** 埋め込みのページだけ、設定したサイトの iframe の中に出すのを許す */
    private static function allowFraming(): void
    {
        $origins = array_filter(
            preg_split('/\s+/', Settings::get('embed_origins')) ?: [],
            fn ($o) => preg_match('#\Ahttps?://[A-Za-z0-9.-]+(:\d+)?\z#', $o) === 1
        );
        header_remove('X-Frame-Options');
        header("Content-Security-Policy: frame-ancestors 'self' " . implode(' ', $origins));
    }

    /** 検索エンジン向けのイベント情報（schema.org の Event） */
    private static function structuredData(array $event, string $url, string $summary): array
    {
        $location = Calendar::location($event);
        $remaining = Applications::remaining($event);
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event['title'],
            'startDate' => date('c', strtotime((string) $event['starts_at'])),
            'eventStatus' => $event['status'] === 'cancelled' ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'description' => Markup::plain($summary, 300),
            'url' => $url,
            'organizer' => ['@type' => 'Organization', 'name' => Settings::get('public_name'), 'url' => Settings::get('official_site_url')],
            'offers' => [
                '@type' => 'Offer',
                'price' => (int) $event['fee'],
                'priceCurrency' => 'JPY',
                'url' => $url,
                'availability' => $remaining === 0 ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
            ],
        ];
        if ($event['ends_at'] !== null) {
            $data['endDate'] = date('c', strtotime((string) $event['ends_at']));
        }
        if ($location !== '') {
            $data['location'] = ['@type' => 'Place', 'name' => $location, 'address' => $location];
        }
        $image = Photos::absoluteUrl($event['photo']);
        if ($image !== null) {
            $data['image'] = [$image];
        }
        return $data;
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
                    if (!$result['existing']) {
                        MailTemplates::notifyStaff($registration);
                    }
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
