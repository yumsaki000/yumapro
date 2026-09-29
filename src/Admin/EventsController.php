<?php

declare(strict_types=1);

namespace App\Admin;

use App\Auth;
use App\Events;
use App\Form;
use App\Config;
use App\Csv;
use App\Follows;
use App\Settings;
use App\Stats;
use App\Photos;
use App\Registrations;
use App\Session;
use App\Surveys;
use App\View;

/**
 * 回の一覧・作成・編集・複製・詳細。
 */
final class EventsController
{
    /** 掲載する内容の欄：項目 => [最大の文字数（null は制限なし）, 名前] */
    private const TEXT_FIELDS = [
        'summary' => [300, '一言紹介'],
        'highlights' => [1000, '安心ポイント'],
        'recommend' => [1000, 'こんな方におすすめ'],
        'timetable' => [1000, 'タイムスケジュール'],
        'belongings' => [300, '持ち物・服装'],
        'access' => [200, 'アクセス'],
        'map_query' => [200, '地図に出す場所'],
        'faq' => [5000, 'よくある質問'],
        'description' => [20000, '内容'],
    ];

    public static function index(): void
    {
        $admin = Auth::requireAdmin();
        $scope = Form::choice($_GET, 'scope', ['upcoming', 'past'], 'upcoming');
        echo View::render('admin/events/index', [
            'title' => 'イベント一覧',
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
            // ほかの入力に誤りがあるときは写真を保存しない（保存されないまま写真のファイルだけ残らないように）
            $upload = $errors === [] ? Photos::store($_FILES['photo'] ?? []) : ['name' => null, 'error' => null];
            if ($upload['error'] !== null) {
                $errors[] = $upload['error'];
            }
            if ($errors === []) {
                if ($values['cancel_deadline'] === null && $values['payment_timing'] === 'prepaid') {
                    // 今のキャンセルポリシー（開催1週間前以降は返金不可）に合わせた既定
                    $values['cancel_deadline'] = date('Y-m-d H:i:00', strtotime($values['starts_at'] . ' -7 days'));
                }
                $id = Events::create($values, (int) $admin['id']);
                if ($upload['name'] !== null) {
                    Events::setPhoto($id, $upload['name']);
                }
                $photoErrors = self::savePhotos($id, []);
                self::afterSave($id, "「{$values['title']}」を作りました。", $photoErrors);
                return;
            }
        }

        echo View::render('admin/events/form', [
            'title' => '新しいイベント',
            'admin' => $admin,
            'heading' => '新しいイベント',
            'action' => '/admin/events/new',
            'types' => $types,
            'values' => $values,
            'errors' => $errors,
            'event' => null,
            'photos' => [],
            'latest' => is_post() ? [] : Events::latestPerType(),
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
            'followEnabled' => Settings::get('follow_enabled') === '1',
            'followCount' => (int) (Follows::countsByType()[(int) $event['event_type_id']] ?? 0),
            'notArrived' => Registrations::countNotArrived((int) $event['id']),
        ], 'admin/layout');
    }

    /**
     * 開催が済んだイベントで、到着の記録がない申込をまとめて無断キャンセルにする。
     * 当日受付を使ったイベント（到着が1人以上）だけ。使っていないと全員に付いてしまうため
     */
    public static function markNoShows(string $id): void
    {
        Auth::requireAdmin();
        $event = Events::find((int) $id) ?? abort_not_found();
        if (strtotime((string) $event['starts_at']) > time()) {
            Session::flash('error', '開催の前は、まとめて無断キャンセルにできません。');
        } elseif ((int) $event['arrived_count'] === 0) {
            Session::flash('error', '当日受付の記録がないため、まとめては付けられません。来なかった人の「無断キャンセル」を1人ずつ押してください。');
        } else {
            $count = Registrations::markNoShows((int) $event['id']);
            Session::flash('notice', "到着の記録がない {$count}人を無断キャンセルにしました。連絡があった人は「外す」で戻せます。");
        }
        redirect('/admin/events/' . (int) $event['id']);
    }

    /** 申込者の一覧を CSV で書き出す（受付表の印刷や、ほかの表計算で使うとき） */
    public static function registrationsCsv(string $id): void
    {
        Auth::requireAdmin();
        $event = Events::find((int) $id) ?? abort_not_found();
        $registrations = Registrations::forEvent((int) $event['id']);
        $answerKeys = [];
        foreach ($registrations as $r) {
            $answers = $r['answers'] !== null ? json_decode((string) $r['answers'], true) : null;
            foreach (is_array($answers) ? array_keys($answers) : [] as $key) {
                $answerKeys[$key] = true;
            }
        }
        $answerLabels = ['submitted_name' => '申込時に入力した名前', 'referrer' => '紹介者（記入）', 'message' => '意気込み', 'questions' => '質問・不安'];
        $header = ['名前', 'フリガナ', '性別', '電話', 'メール', '状態', '参加費', '前払いの入金', '当日の入金', '到着', '無断キャンセル', '知った経路', '窓口', '紹介者（招待リンク）', '申込日時', 'メモ'];
        foreach (array_keys($answerKeys) as $key) {
            $header[] = $answerLabels[$key] ?? (string) $key;
        }
        $rows = (function () use ($registrations, $answerKeys) {
            foreach ($registrations as $r) {
                $answers = $r['answers'] !== null ? json_decode((string) $r['answers'], true) : [];
                $row = [
                    $r['customer_name'],
                    $r['customer_kana'],
                    \App\Customers::GENDERS[$r['customer_gender'] ?? ''] ?? '',
                    Csv::phone($r['customer_phone']),
                    $r['customer_email'],
                    Registrations::STATUSES[$r['status']] ?? $r['status'],
                    (int) $r['fee'],
                    $r['prepaid_at'] !== null ? date('Y-m-d', strtotime((string) $r['prepaid_at'])) : '',
                    $r['paid_amount'] !== null ? (int) $r['paid_amount'] : '',
                    $r['arrived_at'] !== null ? date('H:i', strtotime((string) $r['arrived_at'])) : '',
                    $r['no_show_at'] !== null ? '無断キャンセル' : '',
                    $r['channel'],
                    $r['entry_from'] !== null ? Stats::entryLabel($r['entry_from']) : '',
                    $r['referrer_name'],
                    date('Y-m-d H:i', strtotime((string) $r['applied_at'])),
                    $r['note'],
                ];
                foreach (array_keys($answerKeys) as $key) {
                    $value = is_array($answers) ? ($answers[$key] ?? '') : '';
                    $row[] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                yield $row;
            }
        })();
        Csv::download('申込者_' . date('Ymd', strtotime((string) $event['starts_at'])) . '_' . $event['title'] . '.csv', $header, $rows);
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
            // ほかの入力に誤りがあるときは写真を保存しない（保存されないまま写真のファイルだけ残らないように）
            $upload = $errors === [] ? Photos::store($_FILES['photo'] ?? []) : ['name' => null, 'error' => null];
            if ($upload['error'] !== null) {
                $errors[] = $upload['error'];
            }
            if ($errors === []) {
                $id = (int) $event['id'];
                Events::update($id, $values);
                if ($upload['name'] !== null) {
                    Photos::delete($event['photo']);
                    Events::setPhoto($id, $upload['name']);
                } elseif (Form::checked($_POST, 'remove_photo')) {
                    Photos::delete($event['photo']);
                    Events::setPhoto($id, null);
                }
                $photoErrors = self::savePhotos($id, Events::photos($id));
                self::afterSave($id, "「{$values['title']}」を保存しました。", $photoErrors);
                return;
            }
        }

        echo View::render('admin/events/form', [
            'title' => $event['title'] . ' の編集',
            'admin' => $admin,
            'heading' => 'イベントの編集',
            'action' => '/admin/events/' . $event['id'] . '/edit',
            'types' => $types,
            'values' => $values,
            'errors' => $errors,
            'event' => $event,
            'photos' => Events::photos((int) $event['id']),
            'latest' => [],
        ], 'admin/layout');
    }

    /**
     * 掲示板に出る形で回のページを見る（下書きでも見られる。申込はできない）
     */
    public static function preview(string $id): void
    {
        Auth::requireAdmin();
        $event = Events::find((int) $id) ?? abort_not_found();
        \App\Web\BoardController::render($event, true);
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

    /**
     * 並べる写真の追加・削除・表紙の入れ替え。うまくいかなかった写真の理由を返す。
     *
     * @param list<array{id: int|string, name: string}> $current 今ある写真
     * @return list<string>
     */
    private static function savePhotos(int $eventId, array $current): array
    {
        $ids = array_map(fn ($p) => (int) $p['id'], $current);
        $remove = array_map('intval', array_filter((array) ($_POST['remove_photos'] ?? []), 'is_numeric'));
        foreach (array_intersect($ids, $remove) as $photoId) {
            Events::removePhoto($eventId, $photoId);
        }
        $cover = Form::int($_POST, 'cover_photo');
        if (is_int($cover) && in_array($cover, $ids, true) && !in_array($cover, $remove, true)) {
            Events::makeCover($eventId, $cover);
        }
        $room = Events::MAX_GALLERY - count(Events::photos($eventId));
        $result = Photos::storeMany($_FILES['photos'] ?? [], max(0, $room));
        foreach ($result['names'] as $name) {
            Events::addPhoto($eventId, $name);
        }
        return $result['errors'];
    }

    /** 保存のあと：「保存してページを確認」ならプレビューへ、それ以外は回の画面へ */
    private static function afterSave(int $id, string $notice, array $photoErrors): void
    {
        Session::flash('notice', $notice);
        if ($photoErrors !== []) {
            Session::flash('error', implode("\n", $photoErrors));
        }
        redirect(Form::str($_POST, 'after') === 'preview' ? "/admin/events/{$id}/preview" : "/admin/events/{$id}");
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
            'status' => 'draft',
            'summary' => null,
            'highlights' => null,
            'recommend' => null,
            'timetable' => null,
            'belongings' => null,
            'access' => null,
            'map_query' => null,
            'faq' => null,
            'description' => null,
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
        ];
        // 掲載する内容。どれも空欄なら出さない
        foreach (self::TEXT_FIELDS as $field => [$max, $label]) {
            $value = Form::str($input, $field);
            if ($max !== null && mb_strlen($value) > $max) {
                $errors[] = "{$label}は{$max}文字までにしてください（いま" . mb_strlen($value) . '文字）。';
            }
            $values[$field] = $value === '' ? null : $value;
        }
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
