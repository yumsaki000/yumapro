<?php
/** @var array $course */
/** @var array $lessons */
/** @var array|null $customer */
/** @var array|null $purchase */
/** @var string $lock */
/** @var bool $isCrew */
?>
<section class="card">
    <p><a href="/learn">← 講座一覧</a></p>
    <h1><?= e($course['title']) ?></h1>
    <p><span class="badge"><?= e(App\Courses::ACCESS[$course['access']] ?? '') ?></span><?php if ($course['access'] === 'paid' && $course['price'] !== null): ?> <span class="badge"><?= e(yen($course['price'])) ?></span><?php endif; ?><?php if ($course['access'] === 'paid' && (int) $course['crew_included'] === 1): ?> <span class="badge badge--navy">クルーは無料</span><?php endif; ?></p>
    <?php if ($course['description'] !== null): ?><div><?= App\Courses::formatBody($course['description']) ?></div><?php endif; ?>

    <?php if ($lock === 'login'): ?>
        <p class="warning-box">続きを見るには<a href="/login?next=<?= e(rawurlencode('/learn/' . $course['slug'])) ?>">ログイン</a>してください（メールでリンクが届きます）。<?php if ($course['access'] === 'crew'): ?>クルーの方がご覧いただけます。<a href="/crew">クルーになる</a><?php endif; ?></p>
    <?php elseif ($lock === 'crew'): ?>
        <p class="warning-box">この講座はクルー限定です。<a href="/crew">クルーに申し込む</a></p>
    <?php elseif ($lock === 'pending'): ?>
        <p class="alert alert--info">お申込みを受け付けています。入金を確認しましたらご覧いただけます（メールでお知らせします）。</p>
    <?php elseif ($lock === 'buy'): ?>
        <div class="warning-box">
            <p>この講座は購入するとすべての回が見られます（<?= e(yen($course['price'])) ?>・事前振込）。<?php if ((int) $course['crew_included'] === 1): ?>クルーの方は購入なしで見られます。<a href="/crew">クルーになる</a><?php endif; ?></p>
            <form method="post" action="/learn/<?= e($course['slug']) ?>/purchase" onsubmit="return confirm('この講座を購入します。振込先をメールでお送りします。よろしいですか？');">
                <?= csrf_field() ?>
                <button type="submit" class="button button--primary">購入する（振込先をメールで受け取る）</button>
            </form>
        </div>
    <?php endif; ?>

    <h2>内容（全<?= count($lessons) ?>回）</h2>
    <?php if ($lessons === []): ?><p class="text-muted">準備中です。</p><?php endif; ?>
    <ul class="list">
        <?php foreach ($lessons as $l): ?>
            <li class="list__item">
                <div class="list__main">
                    <?php if ($l['viewable']): ?>
                        <a class="list__title" href="/learn/<?= e($course['slug']) ?>/<?= (int) $l['id'] ?>"><?= (int) $l['no'] ?>. <?= e($l['title']) ?></a>
                        <?php if ((int) $l['is_preview'] === 1 && $lock !== 'ok'): ?><span class="badge badge--ok">お試し</span><?php endif; ?>
                    <?php else: ?>
                        <span class="list__title text-muted">🔒 <?= (int) $l['no'] ?>. <?= e($l['title']) ?></span>
                    <?php endif; ?>
                    <?php if ($l['youtube_id'] !== null): ?><span class="badge">動画</span><?php endif; ?>
                </div>
                <?php if ($l['viewable']): ?><a class="button button--small" href="/learn/<?= e($course['slug']) ?>/<?= (int) $l['id'] ?>">見る</a><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
