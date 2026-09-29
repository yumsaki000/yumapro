<?php
/** @var string $title */
/** @var string $content */
/** @var array|null $admin ログイン中の管理者（ログイン画面では null） */
$admin = $admin ?? App\Auth::user();
$path = current_path();
$nav = [
    ['/admin/events', '回'],
    ['/admin/customers', '顧客'],
    ['/admin/accounting', '会計'],
    ['/admin/channels', '設定'],
];
if ($admin !== null && $admin['role'] === 'owner') {
    $nav[] = ['/admin/members', 'メンバー'];
}
$flashNotice = App\Session::flash('notice');
$flashError = App\Session::flash('error');
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? '管理画面') ?> | MINATO イベント 管理画面</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="admin">
    <header class="admin-header">
        <div class="admin-header__inner">
            <a class="admin-header__brand" href="/admin">⚓ MINATO イベント 管理画面</a>
            <?php if ($admin !== null): ?>
                <form class="admin-header__logout" method="post" action="/admin/logout">
                    <?= csrf_field() ?>
                    <span class="admin-header__name"><?= e($admin['display_name']) ?> さん</span>
                    <button type="submit" class="button button--small button--ghost">ログアウト</button>
                </form>
            <?php endif; ?>
        </div>
    </header>
    <?php if ($admin !== null): ?>
        <nav class="admin-nav">
            <div class="admin-nav__inner">
                <?php foreach ($nav as [$href, $label]): ?>
                    <?php $active = $path === $href || str_starts_with($path, $href . '/'); ?>
                    <a class="admin-nav__link<?= $active ? ' is-active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
            </div>
        </nav>
    <?php endif; ?>
    <main class="container container--wide">
        <?php if ($flashNotice !== null): ?>
            <p class="alert alert--info flash"><?= e($flashNotice) ?></p>
        <?php endif; ?>
        <?php if ($flashError !== null): ?>
            <p class="alert alert--error flash" role="alert"><?= e($flashError) ?></p>
        <?php endif; ?>
        <?= $content ?>
    </main>
</body>
</html>
