<?php
/**
 * 参加者向けの画面の共通の枠。公式サイト（minatocrew.com）と同じオレンジの帯・中央のロゴ・右上のメニュー・
 * オレンジのフッターにして、公式サイトから来ても同じサイトの続きに見えるようにする。
 *
 * @var string $title
 * @var string $content
 * @var array|null $meta  description / image / url / type / index / jsonld
 * @var array|null $hero
 */
$flashNotice = App\Session::flash('notice');
$flashError = App\Session::flash('error');
$me = App\CustomerAuth::current();
$path = current_path();
$siteName = App\Settings::get('public_name');
$officialUrl = App\Settings::get('official_site_url');
$lineUrl = App\Settings::get('official_line_url');
$instagramUrl = App\Settings::get('instagram_url');
$officialMenu = [];
foreach (preg_split('/\R/u', App\Settings::get('official_menu')) ?: [] as $line) {
    $parts = array_map('trim', explode('|', $line, 2));
    if (count($parts) === 2 && $parts[0] !== '' && preg_match('#\Ahttps?://#i', $parts[1])) {
        $officialMenu[] = $parts;
    }
}
$myUrl = $me !== null ? '/my/' . $me['access_token'] : '/login';
$nav = [['/', 'イベント'], ['/learn', '講座・動画'], ['/crew', 'クルー募集'], [$myUrl, $me !== null ? 'マイページ' : 'ログイン']];
$meta = $meta ?? [];
$pageTitle = ($title ?? '') !== '' ? $title . '｜' . $siteName : $siteName;
$description = (string) ($meta['description'] ?? '');
$image = $meta['image'] ?? app_url('/assets/og-default.png');
// 検索エンジンに載せるのは本番の掲示板と回のページだけ（申込・マイページ・ログインなどは載せない）
$indexable = !empty($meta['index']) && App\Config::get('APP_ENV') === 'production';
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="<?= $indexable ? 'index, follow' : 'noindex, nofollow' ?>">
    <meta name="theme-color" content="#dd9933">
    <title><?= e($pageTitle) ?></title>
    <?php if ($description !== ''): ?><meta name="description" content="<?= e($description) ?>"><?php endif; ?>
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($title ?? $siteName) ?>">
    <meta property="og:type" content="<?= e($meta['type'] ?? 'website') ?>">
    <?php if ($description !== ''): ?><meta property="og:description" content="<?= e($description) ?>"><?php endif; ?>
    <?php if (!empty($meta['url'])): ?>
        <meta property="og:url" content="<?= e($meta['url']) ?>">
        <link rel="canonical" href="<?= e($meta['url']) ?>">
    <?php endif; ?>
    <meta property="og:image" content="<?= e($image) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="/assets/logo.png">
    <link rel="apple-touch-icon" href="/assets/logo.png">
    <link rel="stylesheet" href="/assets/app.css">
    <?php if (!empty($meta['jsonld'])): ?>
        <script type="application/ld+json"><?= json_encode($meta['jsonld'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <?php endif; ?>
</head>
<body class="public">
    <header class="site-header">
        <div class="site-header__inner">
            <a class="site-header__official" href="<?= e($officialUrl) ?>">公式サイト</a>
            <a class="site-header__logo" href="/" aria-label="<?= e($siteName) ?> イベント一覧"><img src="/assets/logo-header.png" alt="<?= e($siteName) ?>" width="59" height="48"></a>
            <details class="site-menu">
                <summary aria-label="メニュー"><span class="site-menu__bars"></span></summary>
                <div class="site-menu__panel">
                    <div class="site-menu__group">
                        <p class="site-menu__title">イベントに参加する</p>
                        <?php foreach ($nav as [$href, $label]): ?>
                            <a href="<?= e($href) ?>"><?= e($label) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="site-menu__group">
                        <p class="site-menu__title"><?= e($siteName) ?>を知る（公式サイト）</p>
                        <a href="<?= e($officialUrl) ?>">公式サイト トップ</a>
                        <?php foreach ($officialMenu as [$label, $href]): ?>
                            <a href="<?= e($href) ?>"><?= e($label) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="site-menu__sns">
                        <?php if ($lineUrl !== ''): ?><a class="button button--small" href="<?= e($lineUrl) ?>" target="_blank" rel="noopener">公式LINE</a><?php endif; ?>
                        <?php if ($instagramUrl !== ''): ?><a class="button button--small" href="<?= e($instagramUrl) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
                    </div>
                </div>
            </details>
        </div>
    </header>
    <nav class="site-nav" aria-label="イベントのメニュー">
        <div class="site-nav__inner">
            <?php foreach ($nav as [$href, $label]): ?>
                <?php $active = $path === $href || ($href !== '/' && str_starts_with($path, $href)) || ($href === '/' && str_starts_with($path, '/e/')) || ($label === 'マイページ' && str_starts_with($path, '/my')); ?>
                <a class="site-nav__link<?= $active ? ' is-active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
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
            <p class="alert alert--error flash" role="alert"><?= nl2br(e($flashError)) ?></p>
        <?php endif; ?>
        <?= $content ?>
    </main>
    <footer class="site-footer">
        <a class="site-footer__logo" href="<?= e($officialUrl) ?>"><img src="/assets/logo-header.png" alt="<?= e($siteName) ?> 公式サイト" width="59" height="48"></a>
        <div class="site-footer__links">
            <a href="<?= e($officialUrl) ?>">公式サイト</a>
            <?php if (App\Settings::get('tokushoho_url') !== ''): ?><a href="<?= e(App\Settings::get('tokushoho_url')) ?>">特定商取引法に基づく表記</a><?php endif; ?>
            <a href="<?= e(App\Settings::get('privacy_url')) ?>">プライバシーポリシー</a>
            <?php if ($lineUrl !== ''): ?><a href="<?= e($lineUrl) ?>" target="_blank" rel="noopener">公式LINE</a><?php endif; ?>
            <?php if ($instagramUrl !== ''): ?><a href="<?= e($instagramUrl) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
        </div>
        <?php if ($me !== null): ?>
            <form method="post" action="/logout" class="inline-form"><?= csrf_field() ?><button type="submit" class="linklike">ログアウト（<?= e($me['name']) ?>）</button></form>
        <?php endif; ?>
        <p class="site-footer__copy">Copyright © <?= e($siteName) ?></p>
    </footer>
    <script>
    // メニューの外をタップしたら閉じる
    document.addEventListener('click', function (ev) {
        document.querySelectorAll('details.site-menu[open]').forEach(function (menu) {
            if (!menu.contains(ev.target)) { menu.removeAttribute('open'); }
        });
    });
    </script>
</body>
</html>
