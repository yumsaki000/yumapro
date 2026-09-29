<?php

declare(strict_types=1);

use App\Applications;
use App\MailTemplates;
use App\Web\MyPageController;

test('申込：受付中かどうか（状態と締切）', function () {
    $open = ['status' => 'open', 'apply_deadline' => null];
    assert_true(Applications::accepting($open));
    assert_true(!Applications::accepting(['status' => 'closed', 'apply_deadline' => null]), '締切');
    assert_true(!Applications::accepting(['status' => 'draft', 'apply_deadline' => null]), '下書き');
    assert_true(!Applications::accepting(['status' => 'open', 'apply_deadline' => '2020-01-01 00:00:00']), '締切日時を過ぎた');
    assert_true(Applications::accepting(['status' => 'open', 'apply_deadline' => '2099-01-01 00:00:00']), '締切日時の前');
});

test('申込：残りの席数と性別の要否', function () {
    assert_same(null, Applications::remaining(['capacity' => null, 'applied_count' => 3]), '定員なし');
    assert_same(7, Applications::remaining(['capacity' => 10, 'applied_count' => 3]));
    assert_same(0, Applications::remaining(['capacity' => 10, 'applied_count' => 12]), '超えていても0');
    $plain = ['capacity_male' => null, 'capacity_female' => null, 'fee_male' => null, 'fee_female' => null];
    assert_true(!Applications::needsGender($plain));
    assert_true(Applications::needsGender(['capacity_male' => 5] + $plain));
    assert_true(Applications::needsGender(['fee_female' => 1000] + $plain));
});

test('申込：窓口の識別子は半角英数字だけ', function () {
    assert_same('instagram', Applications::entryFrom('Instagram'));
    assert_same('kokuchpro', Applications::entryFrom('kokuchpro'));
    assert_same(null, Applications::entryFrom('bad value!'));
    assert_same(null, Applications::entryFrom(str_repeat('a', 31)));
    assert_same(null, Applications::entryFrom(['x']));
    assert_same(null, Applications::entryFrom(null));
});

test('メール：文面の言葉を置き換える', function () {
    $out = MailTemplates::fill("{name} 様\n{event_title} {none}", ['name' => '山田 花子', 'event_title' => '女子会']);
    assert_same("山田 花子 様\n女子会 {none}", $out, '知らない言葉はそのまま');
});

test('個人ページ：本人がキャンセルできる条件', function () {
    $base = ['status' => 'applied', 'event_status' => 'open', 'event_starts_at' => '2099-01-01 19:00:00', 'event_cancel_deadline' => null];
    assert_true(MyPageController::cancellable($base));
    assert_true(MyPageController::cancellable(['event_cancel_deadline' => '2098-12-25 00:00:00'] + $base), '期限の前');
    assert_true(!MyPageController::cancellable(['event_cancel_deadline' => '2020-01-01 00:00:00'] + $base), '期限を過ぎた');
    assert_true(MyPageController::cancellable(['status' => 'waitlisted', 'event_cancel_deadline' => '2020-01-01 00:00:00'] + $base), 'キャンセル待ちは期限に関係なく取り消せる');
    assert_true(!MyPageController::cancellable(['status' => 'cancelled'] + $base));
    assert_true(!MyPageController::cancellable(['event_starts_at' => '2020-01-01 19:00:00'] + $base), '開催後');
    assert_true(!MyPageController::cancellable(['event_status' => 'cancelled'] + $base), '中止の回');
});
