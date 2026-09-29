<?php
/** @var array $admin */
/** @var array $upcoming これからの回（直近） */
/** @var int $pendingReviews 出禁チェックの確認待ち */
/** @var int $pendingCrew クルーの申込 */
/** @var int $pendingPurchases 講座の入金待ち */
$menu = [
    ['/admin/events', '回の一覧', '回の作成・複製、申込者の一覧、当日受付'],
    ['/admin/customers', '顧客台帳', '顧客の検索、参加履歴、名寄せ'],
    ['/admin/bans', '出禁リスト', '出禁の登録・解除、申込時の判定の確認'],
    ['/admin/crew', 'クルー', '名簿、募集ページからの申込の承認'],
    ['/admin/courses', '講座・動画', 'クルー限定・購入のコンテンツ、YouTube動画、購入の入金確認'],
    ['/admin/accounting', '会計', '回ごとの収入・経費・収支'],
    ['/admin/stats', '集計', '窓口別・新規／リピート・男女の集客数'],
    ['/admin/settings', '設定', '掲示板・申込フォーム・メールの文言、選択肢'],
];
if ($admin['role'] === 'owner') {
    $menu[] = ['/admin/members', '運営メンバー', 'アカウントの追加・無効化・パスワード再設定'];
}
?>
<section class="card">
    <h1>ようこそ、<?= e($admin['display_name']) ?> さん</h1>
    <p class="text-muted">参加者向けの掲示板：<a href="/" target="_blank"><?= e(rtrim((string) App\Config::get('APP_URL', ''), '/')) ?>/</a>（「募集中」の回が出ます）</p>

    <?php if ($pendingReviews > 0): ?>
        <p class="warning-box">出禁チェックで確認待ちの申込が <?= $pendingReviews ?>件あります。<a href="/admin/bans">出禁リストで確認する</a></p>
    <?php endif; ?>
    <?php if ($pendingCrew > 0): ?>
        <p class="warning-box">クルーの申込が <?= $pendingCrew ?>件あります。<a href="/admin/crew?status=applied">承認する</a></p>
    <?php endif; ?>
    <?php if ($pendingPurchases > 0): ?>
        <p class="warning-box">講座の購入で入金待ちが <?= $pendingPurchases ?>件あります。<a href="/admin/purchases">確認する</a></p>
    <?php endif; ?>
    <?php if ($upcoming !== []): ?>
        <h2>これからの回</h2>
        <ul class="list">
            <?php foreach ($upcoming as $event): ?>
                <li class="list__item">
                    <div class="list__main">
                        <a class="list__title" href="/admin/events/<?= (int) $event['id'] ?>"><?= e($event['title']) ?></a>
                        <div class="list__sub"><?= e(fmt_dt($event['starts_at'])) ?>・申込 <?= (int) $event['applied_count'] ?>人<?= $event['capacity'] !== null ? '／定員 ' . (int) $event['capacity'] . '人' : '' ?></div>
                    </div>
                    <a class="button button--small" href="/admin/events/<?= (int) $event['id'] ?>/checkin">当日受付</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <ul class="menu">
        <?php foreach ($menu as [$href, $name, $description]): ?>
            <li>
                <a class="menu__item menu__item--link" href="<?= e($href) ?>">
                    <span class="menu__name"><?= e($name) ?></span>
                    <span class="menu__description"><?= e($description) ?></span>
                    <span class="menu__arrow">›</span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
