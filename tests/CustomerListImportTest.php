<?php

declare(strict_types=1);

use App\Migration\CsvReader;
use App\Migration\CustomerListImport;

function load_fixture_records(): array
{
    $list = CustomerListImport::parseList(CsvReader::read(__DIR__ . '/fixtures/koekake_list.csv'));
    $people = CustomerListImport::parseResponses(CsvReader::read(__DIR__ . '/fixtures/form_responses.csv'));
    $result = CustomerListImport::enrich($list['records'], $people);
    return [$list, $result];
}

test('移行：見出しの行を見つけ、名前のある行を顧客にする', function () {
    [$list] = load_fixture_records();
    assert_same(4, count($list['records']), '番号がある4人');
    assert_same(2, count($list['skipped']), '番号なし・番号重複を読み飛ばす');

    $hanako = $list['records'][0];
    assert_same(1, $hanako['legacy_no']);
    assert_same('山田 花子', $hanako['name']);
    assert_same('ヤマダハナコ', $hanako['name_kana']);
    assert_same('female', $hanako['gender']);
    assert_same('知り合い', $hanako['first_channel']);
    assert_same('よく来る', $hanako['note']);
    assert_same('クルー加入', $hanako['legacy_data']['クルー勧誘'], '使い道が決まっていない列も残す');
    assert_same('サトウタロウ', $list['records'][1]['name_kana'], 'ひらがなのフリガナはカタカナに');
    assert_same("改行を\n含む備考", $list['records'][3]['note'], '改行を含むセル');
});

test('移行：フォームの回答から連絡先を付ける（新しい回答を優先）', function () {
    [$list, $result] = load_fixture_records();
    $hanako = $list['records'][0];
    assert_same('09011113333', $hanako['phone'], '新しい回答の電話番号');
    assert_same('hanako@example.com', $hanako['email']);
    assert_same('hanako_old', $hanako['sns_account'], '新しい回答が空なら前の回答の値');
    assert_same('08012345678', $list['records'][1]['phone'], '先頭の0を補う');

    assert_same(2, $result['matched']);
    assert_same(['鈴木 一郎'], $result['ambiguous'], '同じ名前・同じフリガナが2人いるので付けない');
    assert_same(['田中 未登録'], $result['unmatched'], '声掛けリストにいない人は移さない');
});

test('移行：同じ名前の人を候補として出す', function () {
    [$list] = load_fixture_records();
    $candidates = CustomerListImport::duplicateCandidates($list['records']);
    assert_same(1, count($candidates['name']));
    assert_same([3, 4], array_column($candidates['name'][0], 'legacy_no'));
    assert_same([], $candidates['phone']);
});

test('CSV：Shift_JIS でも読める', function () {
    $sjis = mb_convert_encoding("番号,名前,カタカナ\n1,山田 花子,ヤマダハナコ\n", 'SJIS-win', 'UTF-8');
    $rows = CsvReader::parse($sjis);
    assert_same('山田 花子', $rows[1][1]);
});
