<?php

declare(strict_types=1);

/*
 * 今のスプレッドシートの「出禁リスト」を顧客の出禁の印に移す（最初に1回だけ使う）。
 *
 *   php bin/import-banned.php <出禁リストのCSV> [--commit] [--verbose]
 *
 * - 何も付けなければ確認だけ（DBには書かない）。結果を見てから --commit で書き込む
 * - 先に声掛けリストを移しておく（bin/import-customers.php）。連絡先や名前で同じ人を見つけて印を付けるため
 * - CSVは個人情報なので storage/import/ に置く（Gitには入らない）。移し終えたら消す
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Database;
use App\Migration\BanListImport;
use App\Migration\CsvReader;

$files = [];
$options = ['commit' => false, 'verbose' => false];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--commit' || $arg === '--verbose') {
        $options[substr($arg, 2)] = true;
    } elseif ($arg !== '') {
        $files[] = $arg;
    }
}
if (count($files) !== 1) {
    fwrite(STDERR, "使い方: php bin/import-banned.php <出禁リストのCSV> [--commit] [--verbose]\n");
    exit(1);
}

$out = fn (string $line = '') => fwrite(STDOUT, $line . PHP_EOL);
['records' => $records, 'skipped' => $skipped] = BanListImport::parse(CsvReader::read($files[0]));
$plan = BanListImport::plan($records);
$labels = ['mark' => '台帳にいる人に印を付ける', 'create' => '台帳にいないので新しく登録して印を付ける', 'already' => 'すでに出禁の印がある', 'skip' => 'スキップ印があるので付けない'];
$counts = array_fill_keys(array_keys($labels), 0);
foreach ($plan as $item) {
    $counts[$item['action']]++;
}

$out('■ 出禁リスト');
$out('  読み取った行: ' . count($records));
foreach ($labels as $action => $label) {
    $out("  {$label}: {$counts[$action]}人");
    if ($options['verbose']) {
        foreach ($plan as $item) {
            if ($item['action'] === $action) {
                $r = $item['record'];
                $who = $item['customer'] !== null ? "→ 顧客ID {$item['customer']['id']} {$item['customer']['name']}" : '';
                $out("    - {$r['line']}行目 {$r['name']}（電話 " . ($r['phone'] ?? '—') . "） {$who}");
            }
        }
    }
}
if ($skipped !== []) {
    $out('  読み飛ばした行: ' . count($skipped));
    array_map(fn ($s) => $out("    - {$s}"), $skipped);
}
if (!$options['verbose']) {
    $out('  （名前の一覧は --verbose を付けると出ます）');
}

$out();
if (!$options['commit']) {
    $out('確認だけ行いました（DBには書いていません）。書き込むときは --commit を付けて実行してください。');
    exit(0);
}
$result = BanListImport::commit(Database::pdo(), $records, null);
$out("DBに書き込みました: 印を付けた {$result['mark']}人／新しく登録して印を付けた {$result['create']}人／すでに出禁 {$result['already']}人／スキップ {$result['skip']}人");
