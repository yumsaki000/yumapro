<?php

declare(strict_types=1);

use App\Normalize;

test('電話番号：ハイフン・全角・国番号をそろえる', function () {
    assert_same('09012345678', Normalize::phone('090-1234-5678'));
    assert_same('09012345678', Normalize::phone('０９０ １２３４ ５６７８'));
    assert_same('09012345678', Normalize::phone('+81 90-1234-5678'));
    assert_same(null, Normalize::phone(''));
});

test('電話番号：表計算で消えた先頭の0を補う', function () {
    assert_same('09012345678', Normalize::phone('9012345678'));
    assert_same('0312345678', Normalize::phone('312345678'));
});

test('メール：小文字にし、形が正しくなければ空にする', function () {
    assert_same('hanako@example.com', Normalize::email(' Hanako@Example.COM '));
    assert_same(null, Normalize::email('なし'));
    assert_same(null, Normalize::email(''));
});

test('名前：間の空白を半角1つにそろえる', function () {
    assert_same('山田 花子', Normalize::name(' 山田　 花子 '));
    assert_same(null, Normalize::name('　'));
});

test('フリガナ：ひらがな・半角カナを全角カタカナにする', function () {
    assert_same('ヤマダ ハナコ', Normalize::kana('やまだ　はなこ'));
    assert_same('ヤマダ ハナコ', Normalize::kana('ﾔﾏﾀﾞ ﾊﾅｺ'));
});

test('名寄せのキー：空白と全角半角の違いを無視する', function () {
    assert_same(Normalize::matchKey('山田 花子'), Normalize::matchKey('山田　花子'));
    assert_same(Normalize::matchKey('ＹＡＭＡＤＡ'), Normalize::matchKey('yamada'));
});

test('SNS：@ やURLを取り、小文字にする', function () {
    assert_same('minato_community', Normalize::sns('@Minato_Community'));
    assert_same('minato_community', Normalize::sns('https://www.instagram.com/minato_community/'));
    assert_same(null, Normalize::sns(' '));
});
