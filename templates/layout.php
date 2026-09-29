<?php
/** @var string $title */
/** @var string $content */
$flashNotice = App\Session::flash('notice');
$flashError = App\Session::flash('error');
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? APP_NAME) ?><?= ($title ?? '') !== APP_NAME ? ' | ' . e(APP_NAME) : '' ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a class="site-header__brand" href="/">⚓ <?= e(APP_NAME) ?></a>
            <span class="site-header__sub">MINATO イベント</span>
        </div>
    </header>
    <main class="container">
        <?php if ($flashNotice !== null): ?>
            <p class="alert alert--info flash"><?= e($flashNotice) ?></p>
        <?php endif; ?>
        <?php if ($flashError !== null): ?>
            <p class="alert alert--error flash" role="alert"><?= e($flashError) ?></p>
        <?php endif; ?>
        <?= $content ?>
    </main>
    <footer class="site-footer">
        <a href="https://minatocrew.com/" target="_blank" rel="noopener">MINATO 公式サイト</a>
        <a href="<?= e(App\Settings::get('privacy_url')) ?>" target="_blank" rel="noopener">プライバシーポリシー</a>
    </footer>
</body>
</html>
