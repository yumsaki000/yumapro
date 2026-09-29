<?php
/** @var array $courses */
/** @var int $pendingPurchases */
?>
<section class="card">
    <div class="toolbar">
        <h1>講座・動画</h1>
        <span class="actions">
            <a class="button" href="/admin/purchases">購入の管理<?= $pendingPurchases > 0 ? '（入金待ち ' . $pendingPurchases . '）' : '' ?></a>
            <a class="button button--primary" href="/admin/courses/new">新しい講座</a>
        </span>
    </div>
    <p class="text-muted">講座は「公開」にすると参加者向けの「講座・動画」に出ます。見られる範囲は講座ごとに「全員／クルー限定／購入した人」から選び、各回に「お試し」の印を付けると誰でも見られます。動画はYouTubeに「限定公開」で上げて、URLを回に貼ります。</p>
    <?php if ($courses === []): ?><p>講座はまだありません。</p><?php endif; ?>
    <ul class="list">
        <?php foreach ($courses as $c): ?>
            <li class="list__item">
                <div class="list__main">
                    <a class="list__title" href="/admin/courses/<?= (int) $c['id'] ?>"><?= e($c['title']) ?></a>
                    <span class="<?= $c['status'] === 'published' ? 'badge badge--ok' : 'badge' ?>"><?= e(App\Courses::STATUSES[$c['status']]) ?></span>
                    <span class="badge"><?= e(App\Courses::ACCESS[$c['access']]) ?><?= $c['access'] === 'paid' && $c['price'] !== null ? ' ' . e(yen($c['price'])) : '' ?></span>
                    <div class="list__sub">全<?= (int) $c['lesson_count'] ?>回（公開 <?= (int) $c['published_lesson_count'] ?>）<?php if ($c['access'] === 'paid'): ?>・購入 <?= (int) $c['paid_count'] ?>人<?= (int) $c['pending_count'] > 0 ? '（入金待ち ' . (int) $c['pending_count'] . '）' : '' ?><?php endif; ?></div>
                </div>
                <a class="button button--small" href="/admin/courses/<?= (int) $c['id'] ?>">開く</a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
