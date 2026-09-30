<?php

declare(strict_types=1);

/*
 * DB のバックアップを storage/backups/ に取る（mysqldump → gzip）。新しい20個だけ残す。
 *
 *   php bin/backup-db.php
 *
 * make deploy が DB の変更を流す前に自動で実行する。手で取るときは make server-backup。
 * 中身は顧客の個人情報なので、storage/（Web から見えない場所）にだけ置き、Mac やメールに持ち出さない。
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Config;

const KEEP = 20;

$dir = APP_ROOT . '/storage/backups';
if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
    fwrite(STDERR, "storage/backups を作れません。\n");
    exit(1);
}
@chmod($dir, 0700);

// パスワードをコマンドの引数に出さないよう、本人だけが読める一時ファイルで渡す
$quote = fn (string $v) => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"';
$cnf = tempnam(sys_get_temp_dir(), 'minato-db');
chmod($cnf, 0600);
file_put_contents($cnf, implode("\n", [
    '[client]',
    'host=' . $quote((string) Config::get('DB_HOST', 'localhost')),
    'port=' . (int) Config::get('DB_PORT', '3306'),
    'user=' . $quote((string) Config::get('DB_USER', '')),
    'password=' . $quote((string) Config::get('DB_PASSWORD', '')),
    '',
]));

$file = $dir . '/minato-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.sql.gz';
// 書き終わるまでは別の名前にしておき、成功したときだけ本来の名前にする
$part = $file . '.part';
$command = [
    'mysqldump', '--defaults-extra-file=' . $cnf, '--single-transaction', '--default-character-set=utf8mb4',
    '--no-tablespaces', '--routines', (string) Config::get('DB_NAME', ''),
];
$process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($process)) {
    unlink($cnf);
    fwrite(STDERR, "mysqldump を起動できません。\n");
    exit(1);
}
$gz = gzopen($part, 'wb6');
while (!feof($pipes[1])) {
    $chunk = fread($pipes[1], 1 << 16);
    if ($chunk !== false && $chunk !== '') {
        gzwrite($gz, $chunk);
    }
}
gzclose($gz);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$status = proc_close($process);
unlink($cnf);
@chmod($part, 0600);

if ($status !== 0 || filesize($part) < 100) {
    @unlink($part);
    fwrite(STDERR, "バックアップに失敗しました: " . trim((string) $error) . PHP_EOL);
    exit(1);
}

rename($part, $file);

// 古いものを消す（新しい KEEP 個だけ残す）
$all = glob($dir . '/minato-*.sql.gz') ?: [];
rsort($all, SORT_STRING);
foreach (array_slice($all, KEEP) as $old) {
    unlink($old);
}

printf("バックアップを取りました: storage/backups/%s（%s KB）\n", basename($file), number_format((int) ceil(filesize($file) / 1024)));
