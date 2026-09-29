<?php
/** @var string $intro */
/** @var array $courses */
/** @var array|null $customer */
$lockLabel = ['login' => 'ログインで見られます', 'crew' => 'クルー限定', 'pending' => '入金待ち', 'buy' => '購入で見られます', 'ok' => ''];
?>
<section class="card">
    <h1>講座・動画</h1>
    <p><?= nl2br(e($intro)) ?></p>
    <?php if ($courses === []): ?><p class="text-muted">公開中の講座はまだありません。</p><?php endif; ?>
    <ul class="list">
        <?php foreach ($courses as $c): ?>
            <li class="list__item">
                <div class="list__main">
                    <a class="list__title" href="/learn/<?= e($c['slug']) ?>"><?= e($c['title']) ?></a>
                    <?php if ($c['lock'] !== 'ok'): ?><span class="badge<?= $c['lock'] === 'crew' ? ' badge--navy' : '' ?>"><?= e($lockLabel[$c['lock']]) ?></span><?php else: ?><span class="badge badge--ok">見られます</span><?php endif; ?>
                    <?php if ($c['access'] === 'paid' && $c['price'] !== null): ?><span class="badge"><?= e(yen($c['price'])) ?></span><?php endif; ?>
                    <div class="list__sub">全<?= (int) $c['published_lesson_count'] ?>回<?= $c['description'] !== null ? '・' . e(mb_strimwidth($c['description'], 0, 80, '…')) : '' ?></div>
                </div>
                <a class="button button--small" href="/learn/<?= e($c['slug']) ?>">見る</a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
