<?php

declare(strict_types=1);

use App\Csv;
use App\Customers;
use App\Expenses;

test('CSV：カンマ・改行・引用符を含む値はくくる', function () {
    assert_same('"山田, 花子"', Csv::cell('山田, 花子'));
    assert_same("\"1行目\n2行目\"", Csv::cell("1行目\n2行目"));
    assert_same('"「""こんにちは""」"', Csv::cell('「"こんにちは"」'));
    assert_same('', Csv::cell(null));
    assert_same('3000', Csv::cell(3000));
    assert_same("a,b\r\n", Csv::line(['a', 'b']));
});

test('CSV：数式として動く値は先頭に \' を付ける（マイナスの数はそのまま）', function () {
    assert_same("\"'=HYPERLINK(\"\"x\"\")\"", Csv::cell('=HYPERLINK("x")'));
    assert_same("'+81", Csv::cell('+81'));
    assert_same("'@mention", Csv::cell('@mention'));
    assert_same("'-cmd", Csv::cell('-cmd'));
    assert_same('-960', Csv::cell(-960));
    assert_same('-12.5', Csv::cell('-12.5'));
});

test('CSV：電話番号は先頭の0が消えない形にする', function () {
    assert_same('090-1234-5678', Csv::phone('09012345678'));
    assert_same('050-1234-5678', Csv::phone('05012345678'));
    assert_same('03-1234-5678', Csv::phone('0312345678'));
    assert_same('0120-123-456', Csv::phone('0120123456'));
    assert_same('045-123-4567', Csv::phone('0451234567'));
    assert_same('06-1234-5678', Csv::phone('0612345678'));
    assert_same('', Csv::phone(null));
});

test('招待コード：読み間違えやすい文字を使わない8文字だけ通す', function () {
    assert_true(Customers::isReferralCode('5e6xzsyt'));
    assert_true(!Customers::isReferralCode('5e6xzsy'));
    assert_true(!Customers::isReferralCode('5E6XZSYT'));
    assert_true(!Customers::isReferralCode('5e6xzsy1'));
    assert_true(!Customers::isReferralCode(['5e6xzsyt']));
    assert_true(!Customers::isReferralCode(null));
});

test('勘定科目：項目の名前から見当を付け、当てはまらなければ雑費', function () {
    assert_same('旅費交通費', Expenses::guessAccount('交通費'));
    assert_same('仕入高', Expenses::guessAccount('店への支払い'));
    assert_same('仕入高', Expenses::guessAccount('宿泊費'));
    assert_same('賃借料', Expenses::guessAccount('会場費'));
    assert_same('外注費', Expenses::guessAccount('講師料'));
    assert_same('消耗品費', Expenses::guessAccount('備品'));
    assert_same('通信費', Expenses::guessAccount('サーバー代'));
    assert_same('広告宣伝費', Expenses::guessAccount('Instagram広告'));
    assert_same('雑費', Expenses::guessAccount('その他'));
    foreach (['交通費', '店への支払い', '会場費', 'その他'] as $item) {
        assert_true(isset(Expenses::ACCOUNTS[Expenses::guessAccount($item)]));
    }
});
