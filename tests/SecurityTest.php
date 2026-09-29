<?php

declare(strict_types=1);

use App\Csrf;

test('CSRF：正しいトークンだけ通す', function () {
    $token = Csrf::token();
    assert_same($token, Csrf::token(), '同じセッションでは同じトークン');
    assert_true(Csrf::validate($token), '正しいトークン');
    assert_true(!Csrf::validate('wrong'), '違うトークン');
    assert_true(!Csrf::validate(null), 'トークンなし');
    assert_true(!Csrf::validate(['array']), '配列');
});

test('CSRF：取り替えたら古いトークンは通さない', function () {
    $old = Csrf::token();
    Csrf::rotate();
    assert_true(!Csrf::validate($old));
    assert_true(Csrf::validate(Csrf::token()));
});

test('ログイン後の戻り先：管理画面の中だけ許す', function () {
    assert_same('/admin', safe_admin_path(null));
    assert_same('/admin/events', safe_admin_path('/admin/events'));
    assert_same('/admin/events?page=2', safe_admin_path('/admin/events?page=2'));
    assert_same('/admin', safe_admin_path('https://evil.example.com/admin'));
    assert_same('/admin', safe_admin_path('//evil.example.com'));
    assert_same('/admin', safe_admin_path('/administrator'));
    assert_same('/admin', safe_admin_path('/admin/../../etc'));
});

test('ログインID：半角英数字と . _ - の3〜64文字だけ通す', function () {
    assert_true(App\Auth::isValidLoginId('yuma'));
    assert_true(App\Auth::isValidLoginId('hayashi.h_01-a'));
    assert_true(!App\Auth::isValidLoginId('ab'), '短すぎる');
    assert_true(!App\Auth::isValidLoginId(str_repeat('a', 65)), '長すぎる（DBの列を超える）');
    assert_true(!App\Auth::isValidLoginId('yuma sakai'), '空白');
    assert_true(!App\Auth::isValidLoginId('ゆま'), '全角');
    assert_true(!App\Auth::isValidLoginId("yuma\n"), '改行');
});
