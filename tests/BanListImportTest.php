<?php

declare(strict_types=1);

use App\Migration\BanListImport;
use App\Migration\CsvReader;

test('出禁リストの移行：見出しを言葉で見つけ、連絡先をそろえ、スキップ印を読む', function () {
    $rows = CsvReader::parse(
        "タイムスタンプ,氏名,フリガナ,電話番号,メールアドレス,SNSアカウント,理由,報告者,証拠,スキップ\n"
        . "2026/03/01 12:00:00,山田 花子,ヤマダ ハナコ,090-1111-2222,Hanako@Example.com,@hanako_x,勧誘行為,スタッフA,写真あり（保管済み）,\n"
        . "2026/04/02 9:30:00,佐藤 太郎,,,,,無断キャンセル3回,スタッフB,,◯\n"
        . ",,,,,,,,,\n"
        . "2026/05/03 10:00:00,,,080-3333-4444,,,,,,\n"
    );
    ['records' => $records, 'skipped' => $skipped] = BanListImport::parse($rows);
    assert_same(3, count($records), '名前か連絡先がある3行');
    assert_same([], $skipped, '空の行は数えない');

    $hanako = $records[0];
    assert_same('山田 花子', $hanako['name']);
    assert_same('ヤマダ ハナコ', $hanako['name_kana']);
    assert_same('09011112222', $hanako['phone']);
    assert_same('hanako@example.com', $hanako['email']);
    assert_same('hanako_x', $hanako['sns_account']);
    assert_same('勧誘行為', $hanako['reason']);
    assert_same('2026-03-01 12:00:00', $hanako['banned_at'], '報告日を出禁の日時にする');
    assert_true(str_contains($hanako['note'], '報告者: スタッフA'), '報告者は経緯に残す');
    assert_true(str_contains($hanako['note'], '証拠: 写真あり（保管済み）'), '使い道の決まっていない列も経緯に残す');
    assert_true(!$hanako['skip']);

    assert_true($records[1]['skip'], 'スキップ印がある');
    assert_same('（名前なし）', $records[2]['name'], '連絡先だけの行');
});

test('出禁リストの移行：名前の列がなければ止まる', function () {
    try {
        BanListImport::parse(CsvReader::parse("電話番号,理由\n090,x\n"));
        assert_true(false, '例外になるはず');
    } catch (RuntimeException $e) {
        assert_true(str_contains($e->getMessage(), '見出し'));
    }
});
