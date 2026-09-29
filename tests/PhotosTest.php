<?php

declare(strict_types=1);

use App\Events;
use App\Faq;
use App\Photos;

test('よくある質問：Q. と A. の行を組にする', function () {
    $items = Faq::parse("Q. 1人でも大丈夫？\nA. 大丈夫です。\nスタッフがご案内します。\nQ: 服装は？\nA: 自由です。\nQ. 答えのない質問\n");
    assert_same(2, count($items), '答えのない質問は出さない');
    assert_same('1人でも大丈夫？', $items[0]['q']);
    assert_same("大丈夫です。\nスタッフがご案内します。", $items[0]['a'], '続きの行は答えに足す');
    assert_same('自由です。', $items[1]['a']);
    assert_same([], Faq::parse(''));
});

test('形式の色：知らない値は紺にする', function () {
    assert_same('tag-type--pink', Events::colorClass('pink'));
    assert_same('tag-type--navy', Events::colorClass('unknown'));
    assert_same('tag-type--navy', Events::colorClass(null));
});

test('写真：名前の形を確かめ、画像は縮めて保存する', function () {
    assert_same(null, Photos::path('../../etc/passwd'), '変な名前は通さない');
    assert_same(null, Photos::path('abc.jpg'));
    assert_true(Photos::path(str_repeat('a', 24) . '.jpg') !== null);
    assert_same('/photos/x.jpg', Photos::url('x.jpg'));
    assert_same(null, Photos::url(null));

    if (!function_exists('imagecreatetruecolor')) {
        return;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'photo');
    $image = imagecreatetruecolor(2400, 1200);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 100, 50));
    imagepng($image, $tmp);
    imagedestroy($image);
    $name = Photos::saveImage($tmp);
    $path = Photos::path($name);
    assert_true($path !== null && is_file($path), '保存される');
    [$w] = getimagesize($path);
    assert_same(1600, $w, '横1600pxに縮む');
    Photos::delete($name);
    assert_true(!is_file($path), '消せる');
    unlink($tmp);

    file_put_contents($tmp, 'not an image');
    try {
        Photos::saveImage($tmp);
        assert_true(false, '画像でなければ例外');
    } catch (RuntimeException $e) {
        assert_true(str_contains($e->getMessage(), '画像'));
    }
    unlink($tmp);
});
