<?php
/** @var array $admin */
/** @var array $upcoming これからの回（直近） */
$menu = [
    ['/admin/events', '回の一覧', '回の作成・複製、申込者の一覧、当日受付'],
    ['/admin/customers', '顧客台帳', '顧客の検索、参加履歴、名寄せ、出禁'],
    ['/admin/accounting', '会計', '回ごとの収入・経費・収支'],
    ['/admin/channels', '設定', '「どこで知りましたか」の選択肢'],
];
if ($admin['role'] === 'owner') {
    $menu[] = ['/admin/members', '運営メンバー', 'アカウントの追加・無効化・パスワード再設定'];
}
?>
<section class="card">
    <h1>ようこそ、<?= e($admin['display_name']) ?> さん</h1>

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
