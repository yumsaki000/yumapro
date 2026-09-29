<?php

declare(strict_types=1);

/*
 * 管理画面のアカウントを作る（またはパスワードを再設定する）。
 * 管理画面の「運営メンバー」でも同じことができる。最初の1人を作るときと、全員ログインできなくなったときに使う。
 *
 *   php bin/create-admin.php           新しく作る
 *   php bin/create-admin.php --reset   既存のアカウントのパスワードを再設定する
 *
 * ローカルでは `make admin`、本番（Xserver）では SSH で実行する。
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Admins;
use App\Auth;

function ask(string $label, bool $secret = false): string
{
    fwrite(STDOUT, $label);
    $hide = $secret && stream_isatty(STDIN);
    if ($hide) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($hide) {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }
    return $line === false ? '' : trim($line);
}

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

/**
 * パスワードを聞く。空のままなら、ランダムなものを作って表示する。
 */
function askPassword(): string
{
    $password = ask('パスワード（' . Admins::MIN_PASSWORD_LENGTH . '文字以上。空のままEnterで自動作成）: ', true);
    if ($password === '') {
        $password = Admins::generatePassword();
        fwrite(STDOUT, "自動で作ったパスワード: {$password}\n（この画面にしか出ません。本人に安全な方法で伝えてください）\n");
        return $password;
    }
    if (mb_strlen($password) < Admins::MIN_PASSWORD_LENGTH) {
        fail('パスワードは' . Admins::MIN_PASSWORD_LENGTH . '文字以上にしてください。');
    }
    if (ask('もう一度入力: ', true) !== $password) {
        fail('パスワードが一致しません。');
    }
    return $password;
}

$reset = in_array('--reset', $argv, true);

$loginId = ask('ログインID（半角英数字と . _ -、3〜64文字）: ');
if (!Auth::isValidLoginId($loginId)) {
    fail('ログインIDは半角英数字と . _ - の3〜64文字にしてください。');
}

if ($reset) {
    $member = Admins::findByLoginId($loginId) ?? fail("ログインID「{$loginId}」のアカウントはありません。");
    Admins::setPassword((int) $member['id'], askPassword());
    fwrite(STDOUT, "「{$loginId}」のパスワードを再設定しました。\n");
    exit(0);
}

if (Admins::loginIdExists($loginId)) {
    fail("ログインID「{$loginId}」はもう使われています。パスワードの再設定は --reset を付けて実行してください。");
}

$displayName = ask('表示名（画面に出る名前）: ');
$isFirst = Admins::all() === [];
$defaultRole = $isFirst ? 'owner' : 'staff';
$role = ask("権限（owner＝メンバー管理もできる / staff）[{$defaultRole}]: ") ?: $defaultRole;

$errors = Admins::validate($loginId, $displayName, $role, null, true);
if ($errors !== []) {
    fail(implode(PHP_EOL, $errors));
}

Admins::create($loginId, $displayName, $role, askPassword());
fwrite(STDOUT, "アカウント「{$loginId}」（{$displayName}・{$role}）を作りました。\n");
