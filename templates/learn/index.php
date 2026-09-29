<?php
/** @var string $intro */
/** @var array $courses */
/** @var array|null $customer */
$lockLabel = ['login' => 'ログインで見られます', 'crew' => 'クルー専用', 'pending' => '入金待ち', 'buy' => '購入で見られます', 'ok' => '見られます'];
?>
<div class="section-head">
    <h1>講座・動画</h1>
    <p><?= nl2br(e($intro)) ?></p>
</div>
<?php if ($courses === []): ?><section class="card"><p class="text-muted">公開中の講座はまだありません。</p></section><?php endif; ?>
<div class="event-grid">
    <?php foreach ($courses as $c): ?>
        <?php $thumb = App\Courses::thumbnailUrl($c['first_youtube_id']); ?>
        <a class="card event-card event-card--mini course-card" href="/learn/<?= e($c['slug']) ?>">
            <span class="event-card__photo course-card__thumb photo--blue"<?= $thumb !== null ? ' style="background-image:url(\'' . e($thumb) . '\')"' : '' ?>>
                <span class="tag-type <?= $c['access'] === 'crew' ? 'tag-type--navy' : ($c['access'] === 'paid' ? 'tag-type--orange' : 'tag-type--blue') ?>"><?= e(App\Courses::ACCESS[$c['access']]) ?><?= $c['access'] === 'paid' && $c['price'] !== null ? ' ' . e(yen($c['price'])) : '' ?></span>
                <?php if ($thumb !== null): ?><span class="course-card__play" aria-hidden="true">▶</span><?php endif; ?>
            </span>
            <span class="event-card__body">
                <span class="event-card__title"><?= e($c['title']) ?></span>
                <?php if ($c['description'] !== null): ?><span class="event-card__summary"><?= e(App\Markup::plain($c['description'], 90)) ?></span><?php endif; ?>
                <span class="event-card__row">
                    <span class="small text-muted">全<?= (int) $c['published_lesson_count'] ?>回<?= (int) $c['preview_count'] > 0 && $c['lock'] !== 'ok' ? '・お試し' . (int) $c['preview_count'] . '回' : '' ?></span>
                    <span class="seats<?= $c['lock'] === 'ok' ? '' : ' seats--full' ?>"><?= e($lockLabel[$c['lock']]) ?></span>
                </span>
            </span>
        </a>
    <?php endforeach; ?>
</div>
