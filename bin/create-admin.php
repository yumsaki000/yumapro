<?php

declare(strict_types=1);

/*
 * 管理画面のアカウントを作る（またはパスワードを再設定する）。
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

use App\Database;

const MIN_PASSWORD_LENGTH = 10;

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
    $password = ask('パスワード（' . MIN_PASSWORD_LENGTH . '文字以上。空のままEnterで自動作成）: ', true);
    if ($password === '') {
        $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
        fwrite(STDOUT, "自動で作ったパスワード: {$password}\n（この画面にしか出ません。本人に安全な方法で伝えてください）\n");
        return $password;
    }
    if (mb_strlen($password) < MIN_PASSWORD_LENGTH) {
        fail('パスワードは' . MIN_PASSWORD_LENGTH . '文字以上にしてください。');
    }
    if (ask('もう一度入力: ', true) !== $password) {
        fail('パスワードが一致しません。');
    }
    return $password;
}

$reset = in_array('--reset', $argv, true);
$pdo = Database::pdo();

$loginId = ask('ログインID（半角英数字と . _ -、3〜64文字）: ');
if (!preg_match('/\A[A-Za-z0-9._-]{3,64}\z/', $loginId)) {
    fail('ログインIDは半角英数字と . _ - の3〜64文字にしてください。');
}

$stmt = $pdo->prepare('SELECT id FROM admins WHERE login_id = ?');
$stmt->execute([$loginId]);
$existingId = $stmt->fetchColumn();

if ($reset) {
    if ($existingId === false) {
        fail("ログインID「{$loginId}」のアカウントはありません。");
    }
    $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')
        ->execute([password_hash(askPassword(), PASSWORD_DEFAULT), $existingId]);
    $pdo->prepare('DELETE FROM admin_login_attempts WHERE login_id = ?')->execute([$loginId]);
    fwrite(STDOUT, "「{$loginId}」のパスワードを再設定しました。\n");
    exit(0);
}

if ($existingId !== false) {
    fail("ログインID「{$loginId}」はもう使われています。パスワードの再設定は --reset を付けて実行してください。");
}

$displayName = ask('表示名（画面に出る名前）: ');
if ($displayName === '' || mb_strlen($displayName) > 100) {
    fail('表示名は1〜100文字で入れてください。');
}

$isFirst = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0;
$defaultRole = $isFirst ? 'owner' : 'staff';
$role = ask("権限（owner＝アカウント管理もできる / staff）[{$defaultRole}]: ") ?: $defaultRole;
if (!in_array($role, ['owner', 'staff'], true)) {
    fail('権限は owner か staff にしてください。');
}

$pdo->prepare('INSERT INTO admins (login_id, password_hash, display_name, role) VALUES (?, ?, ?, ?)')
    ->execute([$loginId, password_hash(askPassword(), PASSWORD_DEFAULT), $displayName, $role]);
fwrite(STDOUT, "アカウント「{$loginId}」（{$displayName}・{$role}）を作りました。\n");
