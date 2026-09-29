<?php

declare(strict_types=1);

use App\Calendar;
use App\Markup;

test('本文：■で見出し、・で箇条書き、空行で段落、**で太字', function () {
    $html = Markup::render("はじめの行\n続きの行\n\n■ 進め方\n・カードを引く\n- 答えを選ぶ\n\nおわり **大事** です");
    assert_same(
        "<p>はじめの行<br>続きの行</p>\n<h3>進め方</h3>\n<ul><li>カードを引く</li><li>答えを選ぶ</li></ul>\n<p>おわり <strong>大事</strong> です</p>",
        $html
    );
    assert_same('', Markup::render("  \n "), '空なら何も出さない');
    assert_same("<hr>", Markup::render('---'));
});

test('本文：HTML はそのまま文字として出し、URL はリンクにする', function () {
    $html = Markup::render('<script>alert(1)</script> https://example.com/a?b=1&c=2');
    assert_true(!str_contains($html, '<script>'), 'タグは出さない');
    assert_true(str_contains($html, '&lt;script&gt;'), '文字として出す');
    assert_true(str_contains($html, '<a href="https://example.com/a?b=1&amp;c=2" target="_blank" rel="noopener">'), 'URL はリンク');
    $html = Markup::render('**<b>x</b>**');
    assert_same('<p><strong>&lt;b&gt;x&lt;/b&gt;</strong></p>', $html, '太字の中も文字として出す');
});

test('1行に1つの欄：先頭の記号と空行を取る', function () {
    assert_same(['初参加歓迎', 'おひとり参加歓迎', '勧誘なし'], Markup::lines("・初参加歓迎\n\n- おひとり参加歓迎\n ✓ 勧誘なし \n"));
    assert_same([], Markup::lines(null));
});

test('タイムスケジュール：時刻と内容に分け、時刻のない行は前の続きにする', function () {
    $rows = Markup::timetable("14:00 自己紹介\n１４：３０〜 価値観カードを使った\nワクワクトーク\n15時 写真撮影\n16:00-16:30 片付け");
    assert_same([
        ['time' => '14:00', 'text' => '自己紹介'],
        ['time' => '14:30', 'text' => "価値観カードを使った\nワクワクトーク"],
        ['time' => '15:00', 'text' => '写真撮影'],
        ['time' => '16:00〜16:30', 'text' => '片付け'],
    ], $rows);
    assert_same([['time' => '', 'text' => '時刻なしの行']], Markup::timetable('時刻なしの行'));
});

test('プレーンな文：書式を取って、長ければ切る', function () {
    assert_same('進め方 カードを引く 大事', Markup::plain("■ 進め方\n・カードを引く\n\n**大事**"));
    assert_same('あいう…', Markup::plain('あいうえお', 4));
});

test('カレンダー：UTC に直し、場所は公開してよい目安だけ入れる', function () {
    $event = [
        'slug' => 'abc123abc123', 'title' => '女子会, vol.13; 秋', 'starts_at' => '2026-10-11 19:00:00', 'ends_at' => null,
        'access' => '渋谷駅 徒歩5分', 'venue_name' => '渋谷のカフェ', 'venue_address' => '東京都渋谷区1-2-3',
    ];
    $ics = Calendar::ics($event, 'https://event.example.com/e/abc123abc123');
    assert_true(str_contains($ics, "DTSTART:20261011T100000Z\r\n"), '日本時間 19:00 は UTC 10:00');
    assert_true(str_contains($ics, "DTEND:20261011T120000Z\r\n"), '終了がなければ2時間');
    assert_true(str_contains($ics, 'SUMMARY:女子会\, vol.13\; 秋'), 'カンマとセミコロンを逃がす');
    assert_true(!str_contains($ics, '1-2-3'), '住所は入れない');
    foreach (explode("\r\n", $ics) as $line) {
        assert_true(strlen($line) <= 75, '1行は75バイトまで：' . $line);
    }
    $url = Calendar::googleUrl($event, 'https://event.example.com/e/abc123abc123');
    assert_true(str_contains($url, 'dates=20261011T100000Z%2F20261011T120000Z'), 'Googleカレンダーの日時');
    assert_same('渋谷のカフェ', Calendar::location(['access' => null, 'venue_name' => '渋谷のカフェ']));
});

test('送信の上限：php.ini の値をバイト数にする', function () {
    assert_same(8 * 1024 * 1024, App\Csrf::bytes('8M'));
    assert_same(2 * 1024 ** 3, App\Csrf::bytes('2G'));
    assert_same(512 * 1024, App\Csrf::bytes('512K'));
    assert_same(1000, App\Csrf::bytes('1000'));
});
