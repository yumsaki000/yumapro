<?php

declare(strict_types=1);

/*
 * 依存なしの小さなテスト実行器。`make test`（= php tests/run.php）で tests/*Test.php を全部流す。
 * DBを使わないテストだけを置く。
 */

require dirname(__DIR__) . '/src/bootstrap.php';

$tests = [];

function test(string $name, callable $fn): void
{
    global $tests;
    $tests[$name] = $fn;
}

function assert_same(mixed $expected, mixed $actual, string $label = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            "%s\n      期待: %s\n      実際: %s",
            $label,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

function assert_true(bool $condition, string $label = ''): void
{
    assert_same(true, $condition, $label);
}

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    require $file;
}

$failed = 0;
foreach ($tests as $name => $fn) {
    try {
        $fn();
        fwrite(STDOUT, "  ok  {$name}\n");
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDOUT, "  NG  {$name}\n      {$e->getMessage()}\n");
    }
}

fwrite(STDOUT, sprintf("\n%d件中 %d件成功%s\n", count($tests), count($tests) - $failed, $failed ? "、{$failed}件失敗" : ''));
exit($failed === 0 ? 0 : 1);
