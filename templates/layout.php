<?php
/** @var string $title */
/** @var string $content */
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'MINATO イベント') ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <main class="container">
        <?= $content ?>
    </main>
</body>
</html>
