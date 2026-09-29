<?php

declare(strict_types=1);

use App\Events;
use App\Form;

test('フォーム：数字はカンマ・全角・円を取り除いて整数にする', function () {
    assert_same(1000, Form::int(['v' => '1,000円'], 'v'));
    assert_same(12, Form::int(['v' => '１２'], 'v'));
    assert_same(-5, Form::int(['v' => '-5'], 'v'));
    assert_same(null, Form::int(['v' => ''], 'v'), '空は null');
    assert_same(null, Form::int([], 'v'), 'なしは null');
    assert_same(false, Form::int(['v' => 'abc'], 'v'), '数字でなければ false');
    assert_same(false, Form::int(['v' => '1.5'], 'v'), '小数は false');
    assert_same('', Form::str(['v' => ['x']], 'v'), '配列は空文字');
});

test('フォーム：日時は datetime-local の形を DB の形にする', function () {
    assert_same('2026-10-03 19:00:00', Form::datetime(['v' => '2026-10-03T19:00'], 'v'));
    assert_same('2026-10-03 19:00:00', Form::datetime(['v' => '2026-10-03 19:00'], 'v'));
    assert_same(null, Form::datetime(['v' => ''], 'v'));
    assert_same(false, Form::datetime(['v' => '2026-13-01T00:00'], 'v'), 'ない月');
    assert_same(false, Form::datetime(['v' => '2026-02-30T10:00'], 'v'), 'ない日');
    assert_same(false, Form::datetime(['v' => 'abc'], 'v'));
    assert_same('2026-10-03', Form::date(['v' => '2026-10-03'], 'v'));
    assert_same(false, Form::date(['v' => '2026-02-30'], 'v'));
});

test('フォーム：選択肢とチェックボックス', function () {
    assert_same('open', Form::choice(['s' => 'open'], 's', ['draft', 'open'], 'draft'));
    assert_same('draft', Form::choice(['s' => 'evil'], 's', ['draft', 'open'], 'draft'));
    assert_true(Form::checked(['c' => '1'], 'c'));
    assert_true(!Form::checked(['c' => '0'], 'c'));
    assert_true(!Form::checked([], 'c'));
});

test('表示：金額と日時', function () {
    assert_same('3,000円', yen(3000));
    assert_same('0円', yen(0));
    assert_same('—', yen(null));
    assert_same('2026/10/03（土）19:00', fmt_dt('2026-10-03 19:00:00'));
    assert_same('2026/10/03（土）', fmt_dt('2026-10-03 19:00:00', false));
    assert_same('—', fmt_dt(null));
    assert_same('2026-10-03T19:00', dt_input('2026-10-03 19:00:00'));
    assert_same('', dt_input(null));
});

test('回：男女別の参加費と定員', function () {
    $event = ['fee' => 3000, 'fee_male' => 5000, 'fee_female' => null, 'capacity' => 10, 'capacity_male' => 4, 'capacity_female' => null];
    assert_same(5000, Events::feeFor($event, 'male'));
    assert_same(3000, Events::feeFor($event, 'female'), '女性の料金がなければ共通の料金');
    assert_same(3000, Events::feeFor($event, null));
    assert_same(4, Events::capacityFor($event, 'male'));
    assert_same(10, Events::capacityFor($event, 'female'));
    assert_same(null, Events::capacityFor(['capacity' => null, 'capacity_male' => null, 'capacity_female' => null], 'male'), '上限なし');
});
