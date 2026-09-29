<?php
/** @var array $events */
/** @var string $intro */
?>
<section class="card">
    <h1>イベント一覧</h1>
    <p><?= nl2br(e($intro)) ?></p>
    <?php if ($events === []): ?>
        <p class="text-muted">募集中のイベントはまだありません。公式LINEやInstagramでお知らせしますので、お待ちください。</p>
    <?php endif; ?>
    <ul class="list">
        <?php foreach ($events as $event): ?>
            <?php $remaining = App\Applications::remaining($event); $accepting = App\Applications::accepting($event); ?>
            <li class="list__item">
                <div class="list__main">
                    <a class="list__title" href="/e/<?= e($event['slug']) ?>"><?= e($event['title']) ?></a>
                    <span class="badge"><?= e($event['type_name']) ?></span>
                    <?php if (!$accepting): ?>
                        <span class="badge badge--warn">受付終了</span>
                    <?php elseif ($remaining === 0): ?>
                        <span class="badge badge--warn">満席（キャンセル待ち）</span>
                    <?php elseif ($remaining !== null && $remaining <= 3): ?>
                        <span class="badge badge--danger">残り<?= $remaining ?>席</span>
                    <?php endif; ?>
                    <div class="list__sub"><?= e(fmt_dt($event['starts_at'])) ?><?= $event['venue_name'] !== null ? '・' . e($event['venue_name']) : '' ?>・<?= e(yen($event['fee'])) ?></div>
                </div>
                <a class="button button--small" href="/e/<?= e($event['slug']) ?>">くわしく</a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
