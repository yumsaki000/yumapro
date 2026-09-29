<?php
/** @var array $course */
/** @var array $lesson */
/** @var array $values */
/** @var list<string> $errors */
?>
<section class="card">
    <p><a href="/admin/courses/<?= (int) $course['id'] ?>">← <?= e($course['title']) ?></a></p>
    <h1>回の編集</h1>
    <?php if ($errors !== []): ?><div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="/admin/lessons/<?= (int) $lesson['id'] ?>/edit" class="form">
        <?= csrf_field() ?>
        <?= App\View::render('admin/courses/_lesson_fields', ['course' => $course, 'values' => $values, 'prefix' => 'edit'], null) ?>
        <input type="hidden" name="sort_order" value="<?= e((string) ($values['sort_order'] ?? '')) ?>">
        <div class="form-actions">
            <button type="submit" class="button button--primary">保存する</button>
            <?php if ($course['status'] === 'published' && ($values['status'] ?? '') === 'published'): ?>
                <a class="button" href="/learn/<?= e($course['slug']) ?>/<?= (int) $lesson['id'] ?>" target="_blank" rel="noopener">公開ページで見る</a>
            <?php endif; ?>
            <a class="button button--ghost" href="/admin/courses/<?= (int) $course['id'] ?>">戻る</a>
        </div>
    </form>
</section>
<script src="/assets/admin-editor.js"></script>
