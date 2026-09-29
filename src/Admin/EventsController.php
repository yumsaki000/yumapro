<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Events;
use App\Form;
use App\Config;
use App\Registrations;
use App\Session;
use App\Surveys;
use App\View;

/**
 * 回の一覧・作成・編集・複製・詳細。
 */
final class EventsController
{
    public static function index(): void
    {
        $admin = Auth::requireAdmin();
        $scope = Form::choice($_GET, 'scope', ['upcoming', 'past'], 'upcoming');
        echo View::render('admin/events/index', [
            'title' => '回の一覧',
            'admin' => $admin,
            'scope' => $scope,
            'events' => Events::list($scope),
        ], 'admin/layout');
    }

    public static function create(): void
    {
        $admin = Auth::requireAdmin();
        $types = Events::types();
        $values = self::defaults($types);
        $errors = [];

        if (is_post()) {
            [$values, $errors] = self::read($_POST, $types);
            if ($errors === []) {
                if ($values['cancel_deadline'] === null && $values['payment_timing'] === 'prepaid') {
                    // 今のキャンセルポリシー（開催1週間前以降は返金不可）に合わせた既定
                    $values['cancel_deadline'] = date('Y-m-d H:i:00', strtotime($values['starts_at'] . ' -7 days'));
                }
                $id = Events::create($values, (int) $admin['id']);
                Session::flash('notice', "「{$values['title']}」を作りました。");
                redirect('/admin/events/' . $id);
                return;
            }
        }

        echo View::render('admin/events/form', [
            'title' => '新しい回',
            'admin' => $admin,
            'heading' => '新しい回',
            'action' => '/admin/events/new',
            'types' => $types,
            'values' => $values,
            'errors' => $errors,
            'event' => null,
        ], 'admin/layout');
    }

    public static function show(string $id): void
    {
        $admin = Auth::requireAdmin();
        $event = Events::find((int) $id) ?? abort_not_found();
        echo View::render('admin/events/show', [
            'title' => $event['title'],
            'admin' => $admin,
            'event' => $event,
            'registrations' => Registrations::forEvent((int) $event['id']),
            'survey' => Surveys::summaryForEvent((int) $event['id']),
            'baseUrl' => rtrim((string) Config::get('APP_URL', ''), '/'),
        ], 'admin/layout');
    }

    public static function edit(string $id): void
    {
        $admin = Auth::requireAdmin();
        $event = Events::find((int) $id) ?? abort_not_found();
        $types = Events::types();
        $values = array_intersect_key($event, array_flip(Events::FIELDS));
        $errors = [];

        if (is_post()) {
            [$values, $errors] = self::read($_POST, $types);
            if ($errors === []) {
                Events::update((int) $event['id'], $values);
                Session::flash('notice', "「{$values['title']}」を保存しました。");
                redirect('/admin/events/' . $event['id']);
                return;
            }
        }

        echo View::render('admin/events/form', [
            'title' => $event['title'] . ' の編集',
            'admin' => $admin,
            'heading' => '回の編集',
            'action' => '/admin/events/' . $event['id'] . '/edit',
            'types' => $types,
            'values' => $values,
            'errors' => $errors,
            'event' => $event,
        ], 'admin/layout');
    }

    public static function copy(string $id): void
    {
        $admin = Auth::requireAdmin();
        $event = Events::find((int) $id) ?? abort_not_found();
        $newId = Events::copy((int) $event['id'], (int) $admin['id']);
        Session::flash('notice', '複製しました。日時と内容を直して保存してください（下書きの状態です）。');
        redirect('/admin/events/' . $newId . '/edit');
    }

    public static function status(string $id): void
    {
        Auth::requireAdmin();
        $event = Events::find((int) $id) ?? abort_not_found();
        $status = Form::str($_POST, 'status');
        if (!isset(Events::STATUSES[$status])) {
            Session::flash('error', '状態が正しくありません。');
        } else {
            Events::setStatus((int) $event['id'], $status);
            Session::flash('notice', '「' . Events::STATUSES[$status] . '」にしました。');
        }
        redirect('/admin/events/' . $event['id']);
    }

    private static function defaults(array $types): array
    {
        $first = $types[0] ?? null;
        return [
            'event_type_id' => $first['id'] ?? null,
            'title' => '',
            'round_no' => null,
            'starts_at' => null,
            'ends_at' => null,
            'venue_name' => null,
            'venue_address' => null,
            'venue_url' => null,
            'capacity' => null,
            'capacity_male' => null,
            'capacity_female' => null,
            'fee' => 0,
            'fee_male' => null,
            'fee_female' => null,
            'fee_crew' => null,
            'payment_timing' => $first['payment_timing'] ?? 'prepaid',
            'apply_deadline' => null,
            'cancel_deadline' => null,
            'cancel_policy' => null,
            'organizer_amount' => 0,
            'description' => null,
            'status' => 'draft',
        ];
    }

    /**
     * フォームの値を読んで確かめる。
     *
     * @return array{array, list<string>}
     */
    private static function read(array $input, array $types): array
    {
        $errors = [];
        $typeIds = array_map(fn ($t) => (int) $t['id'], $types);
        $typeId = Form::int($input, 'event_type_id');
        if (!is_int($typeId) || !in_array($typeId, $typeIds, true)) {
            $errors[] = '形式を選んでください。';
        }

        $values = [
            'event_type_id' => $typeId,
            'title' => Form::str($input, 'title'),
            'status' => Form::choice($input, 'status', array_keys(Events::STATUSES), 'draft'),
            'payment_timing' => Form::choice($input, 'payment_timing', array_keys(Events::PAYMENT_TIMINGS), 'prepaid'),
            'venue_name' => Form::str($input, 'venue_name') ?: null,
            'venue_address' => Form::str($input, 'venue_address') ?: null,
            'venue_url' => Form::str($input, 'venue_url') ?: null,
            'cancel_policy' => Form::str($input, 'cancel_policy') ?: null,
            'description' => Form::str($input, 'description') ?: null,
        ];
        if ($values['title'] === '' || mb_strlen($values['title']) > 200) {
            $errors[] = 'タイトルは1〜200文字で入れてください。';
        }
        foreach (['venue_name' => [200, '会場名'], 'venue_address' => [255, '会場の住所'], 'venue_url' => [500, '会場のURL']] as $field => [$max, $label]) {
            if ($values[$field] !== null && mb_strlen($values[$field]) > $max) {
                $errors[] = "{$label}は{$max}文字までにしてください。";
            }
        }
        if ($values['venue_url'] !== null && !preg_match('#\Ahttps?://#i', $values['venue_url'])) {
            $errors[] = '会場のURLは http:// か https:// で始めてください。';
        }

        foreach (['starts_at' => '開始日時', 'ends_at' => '終了日時', 'apply_deadline' => '申込締切', 'cancel_deadline' => 'キャンセル期限'] as $field => $label) {
            $value = Form::datetime($input, $field);
            if ($value === false) {
                $errors[] = "{$label}の形が正しくありません。";
                $value = null;
            }
            $values[$field] = $value;
        }
        if ($values['starts_at'] === null) {
            $errors[] = '開始日時を入れてください。';
        } elseif ($values['ends_at'] !== null && $values['ends_at'] < $values['starts_at']) {
            $errors[] = '終了日時は開始日時より後にしてください。';
        }

        foreach ([
            'round_no' => ['第n回', false], 'capacity' => ['定員', false], 'capacity_male' => ['男性の定員', false], 'capacity_female' => ['女性の定員', false],
            'fee' => ['参加費', true], 'fee_male' => ['男性の参加費', false], 'fee_female' => ['女性の参加費', false], 'fee_crew' => ['クルー料金', false], 'organizer_amount' => ['主催分', true],
        ] as $field => [$label, $required]) {
            $value = Form::int($input, $field);
            if ($value === false || (is_int($value) && $value < 0)) {
                $errors[] = "{$label}は0以上の数字で入れてください。";
                $value = null;
            }
            if ($value === null && $required) {
                $value = 0;
            }
            $values[$field] = $value;
        }
        return [$values, $errors];
    }
}
