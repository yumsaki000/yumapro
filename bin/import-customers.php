<?php

declare(strict_types=1);

/*
 * 今のスプレッドシートの「声掛けリスト」を顧客に移す（最初に1回だけ使う）。
 *
 *   php bin/import-customers.php <声掛けリストのCSV> [--responses=<フォームの回答のCSV>] [--commit] [--verbose]
 *
 * - 何も付けなければ確認だけ（DBには書かない）。結果を見てから --commit で書き込む
 * - CSVはGoogleスプレッドシートで各シートを開き「ファイル → ダウンロード → カンマ区切り形式（.csv）」で出す
 * - CSVは個人情報なので storage/import/ に置く（Gitには入らない）。移し終えたら消す
 * - 手順の詳細は docs/migration.md
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Database;
use App\Migration\CsvReader;
use App\Migration\CustomerListImport;

$files = [];
$options = ['responses' => null, 'commit' => false, 'verbose' => false];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--responses=')) {
        $options['responses'] = substr($arg, strlen('--responses='));
    } elseif ($arg === '--commit' || $arg === '--verbose') {
        $options[substr($arg, 2)] = true;
    } elseif ($arg !== '') {
        $files[] = $arg;
    }
}
if (count($files) !== 1) {
    fwrite(STDERR, "使い方: php bin/import-customers.php <声掛けリストのCSV> [--responses=<フォームの回答のCSV>] [--commit] [--verbose]\n");
    exit(1);
}

$out = fn (string $line = '') => fwrite(STDOUT, $line . PHP_EOL);
$list = fn (array $items) => $options['verbose'] ? array_map(fn ($s) => $out("    - {$s}"), $items) : null;

// ── 声掛けリスト ─────────────────────────────────
['records' => $records, 'skipped' => $skipped] = CustomerListImport::parseList(CsvReader::read($files[0]));
$genders = array_count_values(array_map(fn ($r) => $r['gender'] ?? 'none', $records));
$out('■ 声掛けリスト');
$out(sprintf('  顧客にする人: %d人（男性 %d／女性 %d／性別なし %d）',
    count($records), $genders['male'] ?? 0, $genders['female'] ?? 0, $genders['none'] ?? 0));
$out('  フリガナなし: ' . count(array_filter($records, fn ($r) => $r['name_kana'] === null)) . '人');
if ($skipped !== []) {
    $out('  読み飛ばした行: ' . count($skipped));
    array_map(fn ($s) => $out("    - {$s}"), $skipped);
}

// ── フォームの回答から連絡先を付ける ─────────────────
if ($options['responses'] !== null) {
    $people = CustomerListImport::parseResponses(CsvReader::read($options['responses']));
    $result = CustomerListImport::enrich($records, $people);
    $count = fn (string $field) => count(array_filter($records, fn ($r) => $r[$field] !== null));
    $out();
    $out('■ フォームの回答');
    $out('  回答した人: ' . count($people) . '人');
    $out("  連絡先を付けた人: {$result['matched']}人（電話 {$count('phone')}／メール {$count('email')}／SNS {$count('sns_account')}）");
    $out('  同じ名前の顧客が複数いて、どの人か決められず付けなかった人: ' . count($result['ambiguous']) . '人');
    $list($result['ambiguous']);
    $out('  声掛けリストにいない人（移さない）: ' . count($result['unmatched']) . '人');
    $list($result['unmatched']);
}

// ── 名寄せ候補 ─────────────────────────────────
$candidates = CustomerListImport::duplicateCandidates($records);
$describe = fn (array $group) => implode('・', array_map(fn ($r) => "番号{$r['legacy_no']} {$r['name']}", $group));
$out();
$out('■ 同じ人かもしれない組（自動ではまとめない。移したあと管理画面で確認する）');
$out('  同じ名前: ' . count($candidates['name']) . '組');
$list(array_map($describe, $candidates['name']));
$out('  同じ電話番号: ' . count($candidates['phone']) . '組');
$list(array_map($describe, $candidates['phone']));
if (!$options['verbose']) {
    $out('  （名前の一覧は --verbose を付けると出ます）');
}

// ── 書き込み ─────────────────────────────────
$out();
if (!$options['commit']) {
    $out('確認だけ行いました（DBには書いていません）。書き込むときは --commit を付けて実行してください。');
    exit(0);
}
$counts = CustomerListImport::commit(Database::pdo(), $records);
$out("DBに書き込みました: 追加 {$counts['inserted']}人／更新 {$counts['updated']}人／変更なし {$counts['unchanged']}人");
