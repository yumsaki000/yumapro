<?php
/** @var string $title */
/** @var string $content */
/** @var array|null $admin ログイン中の管理者（ログイン画面では未設定） */
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
            <?php if (!empty($admin)): ?>
                <form class="admin-header__logout" method="post" action="/admin/logout">
                    <?= csrf_field() ?>
                    <span class="admin-header__name"><?= e($admin['display_name']) ?> さん</span>
                    <button type="submit" class="button button--small button--ghost">ログアウト</button>
                </form>
            <?php endif; ?>
        </div>
    </header>
    <main class="container">
        <?= $content ?>
    </main>
</body>
</html>
