<?php
/** @var array $events */
/** @var string $intro */
?>
<div class="section-head">
    <h1>これからのイベント <small><?= count($events) ?>件</small></h1>
    <p><?= nl2br(e($intro)) ?></p>
</div>
<?php if ($events === []): ?>
    <section class="card"><p class="text-muted">募集中のイベントはまだありません。公式LINEやInstagramでお知らせしますので、お待ちください。</p></section>
<?php endif; ?>
<div class="event-grid">
    <?php foreach ($events as $event): ?>
        <?php
        $remaining = App\Applications::remaining($event);
        $accepting = App\Applications::accepting($event);
        $photo = App\Photos::url($event['photo']);
        $color = App\Events::colorClass($event['type_color'] ?? null);
        ?>
        <article class="card event-card">
            <a class="event-card__photo <?= e(str_replace('tag-type', 'photo', $color)) ?>" href="/e/<?= e($event['slug']) ?>"<?= $photo !== null ? ' style="background-image:url(\'' . e($photo) . '\')"' : '' ?>>
                <span class="tag-type <?= e($color) ?>"><?= e($event['type_name']) ?></span>
            </a>
            <div class="event-card__body">
                <a class="event-card__title" href="/e/<?= e($event['slug']) ?>"><?= e($event['title']) ?></a>
                <div class="text-muted"><?= e(fmt_dt($event['starts_at'])) ?><?= $event['venue_name'] !== null ? '・' . e($event['venue_name']) : '' ?>・<?= e(yen($event['fee'])) ?></div>
                <div class="event-card__row">
                    <?php if (!$accepting): ?>
                        <span class="seats seats--full">受付終了</span>
                    <?php elseif ($remaining === 0): ?>
                        <span class="seats seats--full">満席（キャンセル待ち）</span>
                    <?php elseif ($remaining !== null && $remaining <= 3): ?>
                        <span class="seats seats--few">残り<?= $remaining ?>席</span>
                    <?php elseif ($remaining !== null): ?>
                        <span class="seats">残り<?= $remaining ?>席</span>
                    <?php else: ?>
                        <span class="seats">受付中</span>
                    <?php endif; ?>
                    <a class="button<?= $accepting ? ' button--primary' : '' ?>" href="/e/<?= e($event['slug']) ?>"><?= $accepting && $remaining !== 0 ? '申し込む' : 'くわしく' ?></a>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
