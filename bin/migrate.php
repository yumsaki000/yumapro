<?php

declare(strict_types=1);

/*
 * DB の変更（database/migrations/*.sql）を、まだ流していないものだけ古い順に流す。
 * 流したファイルは schema_migrations に記録するので、何度実行しても同じ変更は二度流れない。
 *
 *   php bin/migrate.php                 まだのものを流す
 *   php bin/migrate.php --status        流したもの・まだのものを一覧にする
 *   php bin/migrate.php --pending-count まだのものの数だけを出す（make deploy が使う）
 *   php bin/migrate.php --init          空の DB に最初のテーブルを作る（database/init/*.sql。初回だけ）
 *
 * 本番では make deploy が、バックアップ（bin/backup-db.php）を取ってから自動で流す。
 * ファイルの書き方は database/migrations/README.md。
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Database;

$mode = $argv[1] ?? '';
if (!in_array($mode, ['', '--status', '--pending-count', '--init'], true)) {
    fwrite(STDERR, "使い方: php bin/migrate.php [--status | --pending-count | --init]\n");
    exit(1);
}

$pdo = Database::pdo();
// SQL ファイルには文が複数あるので、1回で送れるようにする（このコマンドの中だけ）
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$initialized = (bool) $pdo->query("SHOW TABLES LIKE 'events'")->fetchColumn();
$files = glob(APP_ROOT . '/database/migrations/*.sql') ?: [];
sort($files, SORT_STRING);
$names = array_map('basename', $files);

$ensureTable = function () use ($pdo): void {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            name        VARCHAR(191) NOT NULL,
            applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
};

/** SQL ファイルを流す。途中の文で失敗したら例外（後ろの文の失敗も見落とさない） */
$runFile = function (string $path) use ($pdo): void {
    $sql = (string) file_get_contents($path);
    if (trim($sql) === '') {
        return;
    }
    $stmt = $pdo->query($sql);
    do {
        // 結果を読み捨てて次の文へ進む。途中の文が失敗すると nextRowset() が例外を投げる
        $stmt->fetchAll();
    } while ($stmt->nextRowset());
    $stmt->closeCursor();
};

if ($mode === '--init') {
    if ($initialized) {
        fwrite(STDERR, "この DB にはもうテーブルがあります。最初のテーブル作りは空の DB にだけ行います。\n");
        exit(1);
    }
    foreach (glob(APP_ROOT . '/database/init/*.sql') ?: [] as $path) {
        echo '作成: ' . basename($path) . PHP_EOL;
        $runFile($path);
    }
    // init の SQL は最新の形なので、今ある変更ファイルは流したことにする
    $ensureTable();
    $mark = $pdo->prepare('INSERT IGNORE INTO schema_migrations (name) VALUES (?)');
    foreach ($names as $name) {
        $mark->execute([$name]);
    }
    echo "最初のテーブルを作りました。次に php bin/create-admin.php で管理画面のアカウントを作ってください。\n";
    exit(0);
}

if (!$initialized) {
    if ($mode === '--pending-count') {
        echo "init\n";
        exit(0);
    }
    fwrite(STDERR, "DB が空です。先に php bin/migrate.php --init で最初のテーブルを作ってください。\n");
    exit(1);
}

$ensureTable();
$applied = $pdo->query('SELECT name, applied_at FROM schema_migrations')->fetchAll(PDO::FETCH_KEY_PAIR);
$pending = array_values(array_filter($files, fn ($path) => !isset($applied[basename($path)])));

if ($mode === '--pending-count') {
    echo count($pending) . PHP_EOL;
    exit(0);
}

if ($mode === '--status') {
    foreach ($names as $name) {
        echo (isset($applied[$name]) ? "済  {$applied[$name]}  " : 'まだ                     ') . $name . PHP_EOL;
    }
    echo $pending === [] ? "まだ流していない変更はありません。\n" : 'まだ流していない変更：' . count($pending) . "件\n";
    exit(0);
}

if ($pending === []) {
    echo "まだ流していない変更はありません。\n";
    exit(0);
}
$mark = $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)');
foreach ($pending as $path) {
    $name = basename($path);
    echo "流す: {$name}\n";
    try {
        $runFile($path);
    } catch (PDOException $e) {
        fwrite(STDERR, "✗ {$name} の途中で失敗しました: " . $e->getMessage() . PHP_EOL);
        fwrite(STDERR, "  テーブルの変更（ALTER など）は元に戻らないため、どこまで流れたかを確かめてから直してください。\n");
        exit(1);
    }
    $mark->execute([$name]);
}
echo count($pending) . "件の変更を流しました。\n";
