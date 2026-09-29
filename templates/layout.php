<?php
/** @var string $title */
/** @var string $content */
$flashNotice = App\Session::flash('notice');
$flashError = App\Session::flash('error');
$me = App\CustomerAuth::current();
$path = current_path();
$nav = [['/', 'イベント'], ['/learn', '講座・動画'], ['/crew', 'クルー募集']];
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
<body class="public">
    <header class="site-header">
        <div class="site-header__inner">
            <a class="site-header__brand" href="/"><img class="site-logo" src="/assets/logo.png" alt=""><?= e(APP_NAME) ?></a>
            <span class="site-header__sub">MINATO イベント</span>
        </div>
    </header>
    <nav class="site-nav">
        <div class="site-nav__inner">
            <?php foreach ($nav as [$href, $label]): ?>
                <?php $active = $path === $href || ($href !== '/' && str_starts_with($path, $href)); ?>
                <a class="site-nav__link<?= $active ? ' is-active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <?php if ($me !== null): ?>
                <a class="site-nav__link<?= str_starts_with($path, '/my') ? ' is-active' : '' ?>" href="/my/<?= e($me['access_token']) ?>">マイページ</a>
            <?php else: ?>
                <a class="site-nav__link<?= str_starts_with($path, '/login') ? ' is-active' : '' ?>" href="/login">ログイン</a>
            <?php endif; ?>
        </div>
    </nav>
    <?php if (!empty($hero)): ?>
        <section class="hero">
            <div class="hero__in">
                <h1><?= nl2br(e($hero['title'])) ?></h1>
                <?php if (($hero['lead'] ?? '') !== ''): ?><p><?= e($hero['lead']) ?></p><?php endif; ?>
            </div>
            <svg class="wave" viewBox="0 0 400 26" preserveAspectRatio="none" aria-hidden="true"><path d="M0 14 Q50 0 100 14 T200 14 T300 14 T400 14 V26 H0Z" fill="#fbf9f4"/></svg>
        </section>
    <?php endif; ?>
    <main class="container<?= !empty($wide) ? ' container--wide' : '' ?>">
        <?php if ($flashNotice !== null): ?>
            <p class="alert alert--info flash"><?= e($flashNotice) ?></p>
        <?php endif; ?>
        <?php if ($flashError !== null): ?>
            <p class="alert alert--error flash" role="alert"><?= e($flashError) ?></p>
        <?php endif; ?>
        <?= $content ?>
    </main>
    <footer class="site-footer">
        <img class="site-logo site-logo--foot" src="/assets/logo.png" alt="">
        <a href="https://minatocrew.com/" target="_blank" rel="noopener">MINATO 公式サイト</a>
        <a href="<?= e(App\Settings::get('privacy_url')) ?>" target="_blank" rel="noopener">プライバシーポリシー</a>
        <?php if ($me !== null): ?>
            <form method="post" action="/logout" class="inline-form"><?= csrf_field() ?><button type="submit" class="linklike">ログアウト（<?= e($me['name']) ?>）</button></form>
        <?php endif; ?>
    </footer>
</body>
</html>
