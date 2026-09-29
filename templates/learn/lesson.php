<?php
/** @var array $course */
/** @var array $lesson */
/** @var ?int $no */
/** @var array|null $prev */
/** @var array|null $next */
/** @var bool $viewable */
/** @var string $lock */
?>
<section class="card">
    <p><a href="/learn/<?= e($course['slug']) ?>">← <?= e($course['title']) ?></a></p>
    <h1><?= $no !== null ? $no . '. ' : '' ?><?= e($lesson['title']) ?></h1>
    <?php if (!$viewable): ?>
        <?php if ($lock === 'login'): ?>
            <p class="warning-box">この回を見るには<a href="/login?next=<?= e(rawurlencode('/learn/' . $course['slug'] . '/' . (int) $lesson['id'])) ?>">ログイン</a>してください。</p>
        <?php elseif ($lock === 'crew'): ?>
            <p class="warning-box">この回はクルー限定です。<a href="/crew">クルーに申し込む</a></p>
        <?php elseif ($lock === 'pending'): ?>
            <p class="alert alert--info">入金を確認しましたらご覧いただけます。</p>
        <?php else: ?>
            <p class="warning-box">この回は講座を購入すると見られます。<a href="/learn/<?= e($course['slug']) ?>">講座のページへ</a></p>
        <?php endif; ?>
    <?php else: ?>
        <?php if ($lesson['youtube_id'] !== null): ?>
            <div class="video"><iframe src="https://www.youtube-nocookie.com/embed/<?= e($lesson['youtube_id']) ?>?rel=0" title="<?= e($lesson['title']) ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>
        <?php endif; ?>
        <?php if ($lesson['body'] !== null && $lesson['body'] !== ''): ?><div class="lesson-body"><?= App\Courses::formatBody($lesson['body']) ?></div><?php endif; ?>
    <?php endif; ?>
    <div class="actions" style="margin-top: 16px;">
        <?php if ($prev !== null): ?><a class="button button--small" href="/learn/<?= e($course['slug']) ?>/<?= (int) $prev['id'] ?>">← 前の回</a><?php endif; ?>
        <?php if ($next !== null): ?><a class="button button--small" href="/learn/<?= e($course['slug']) ?>/<?= (int) $next['id'] ?>">次の回 →</a><?php endif; ?>
    </div>
</section>
