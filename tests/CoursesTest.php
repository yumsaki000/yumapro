<?php

declare(strict_types=1);

use App\Courses;
use App\CustomerAuth;

test('講座：誰が見られるか（全員／クルー限定／購入した人／お試し）', function () {
    $lesson = ['is_preview' => 0];
    $preview = ['is_preview' => 1];
    $crew = ['crew_status' => 'active'];
    $guest = ['crew_status' => 'none'];
    $paid = ['status' => 'paid'];
    $pending = ['status' => 'pending'];

    $public = ['access' => 'public', 'crew_included' => 1];
    assert_true(Courses::canView($public, $lesson, null, null), '全員の講座は未ログインでも見られる');

    $crewOnly = ['access' => 'crew', 'crew_included' => 1];
    assert_true(!Courses::canView($crewOnly, $lesson, null, null), '未ログインは見られない');
    assert_true(!Courses::canView($crewOnly, $lesson, $guest, null), '未加入は見られない');
    assert_true(Courses::canView($crewOnly, $lesson, $crew, null), 'クルーは見られる');
    assert_true(Courses::canView($crewOnly, $preview, null, null), 'お試しの回は誰でも見られる');
    assert_same('login', Courses::lockReason($crewOnly, null, null));
    assert_same('crew', Courses::lockReason($crewOnly, $guest, null));
    assert_same('ok', Courses::lockReason($crewOnly, $crew, null));

    $paidCourse = ['access' => 'paid', 'crew_included' => 1, 'price' => 5000];
    assert_true(!Courses::canView($paidCourse, $lesson, $guest, null), '購入していない');
    assert_true(!Courses::canView($paidCourse, $lesson, $guest, $pending), '入金待ちはまだ');
    assert_true(Courses::canView($paidCourse, $lesson, $guest, $paid), '入金確認済み');
    assert_true(Courses::canView($paidCourse, $lesson, $crew, null), 'クルーは購入なしで見られる設定');
    assert_true(!Courses::canView(['access' => 'paid', 'crew_included' => 0] + $paidCourse, $lesson, $crew, null), 'クルー無料でない設定');
    assert_same('buy', Courses::lockReason($paidCourse, $guest, null));
    assert_same('pending', Courses::lockReason($paidCourse, $guest, $pending));
    assert_same('ok', Courses::lockReason($paidCourse, $guest, $paid));
});

test('講座：YouTubeのURLから動画IDを取り出す', function () {
    assert_same('dQw4w9WgXcQ', Courses::youtubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
    assert_same('dQw4w9WgXcQ', Courses::youtubeId('https://youtu.be/dQw4w9WgXcQ?si=abc'));
    assert_same('dQw4w9WgXcQ', Courses::youtubeId('https://www.youtube.com/embed/dQw4w9WgXcQ'));
    assert_same('dQw4w9WgXcQ', Courses::youtubeId('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
    assert_same('dQw4w9WgXcQ', Courses::youtubeId('dQw4w9WgXcQ'), '動画IDそのまま');
    assert_same(null, Courses::youtubeId('https://example.com/video'), 'YouTube以外');
    assert_same(null, Courses::youtubeId(''));
});

test('講座：本文のURLはリンクにし、HTMLは無害にする', function () {
    $html = Courses::formatBody("見てね https://example.com/a?b=1\n<b>太字</b>");
    assert_true(str_contains($html, '<a href="https://example.com/a?b=1"'), 'URLがリンクになる');
    assert_true(str_contains($html, '&lt;b&gt;'), 'HTMLはそのまま出さない');
    assert_true(str_contains($html, '<br'), '改行を残す');
});

test('ログイン：戻り先はこのサイトの中だけ', function () {
    assert_same('/learn/abc', CustomerAuth::safeNext('/learn/abc'));
    assert_same('/my', CustomerAuth::safeNext('https://evil.example.com/'));
    assert_same('/my', CustomerAuth::safeNext('//evil.example.com'));
    assert_same('/my', CustomerAuth::safeNext(null));
});
