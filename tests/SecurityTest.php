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
