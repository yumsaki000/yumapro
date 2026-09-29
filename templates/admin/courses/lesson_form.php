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
        <label class="form__field"><span class="form__label">回のタイトル</span><input type="text" name="title" value="<?= e($values['title'] ?? '') ?>" maxlength="200" required></label>
        <label class="form__field"><span class="form__label">YouTubeの動画URL（任意）</span><input type="url" name="youtube" value="<?= ($values['youtube_id'] ?? null) !== null ? 'https://youtu.be/' . e($values['youtube_id']) : '' ?>" inputmode="url"></label>
        <label class="form__field"><span class="form__label">本文（任意）</span><textarea name="body" rows="10"><?= e($values['body'] ?? '') ?></textarea></label>
        <div class="form__row">
            <label class="form__check"><input type="checkbox" name="is_preview" value="1"<?= (int) ($values['is_preview'] ?? 0) === 1 ? ' checked' : '' ?>> <span>お試し（誰でも見られる）</span></label>
            <label class="form__field"><span class="form__label">状態</span><select name="status"><?php foreach (App\Courses::STATUSES as $code => $label): ?><option value="<?= e($code) ?>"<?= ($values['status'] ?? 'published') === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
        </div>
        <label class="form__field"><span class="form__label">並び順（小さいほど上）</span><input type="number" name="sort_order" value="<?= e((string) ($values['sort_order'] ?? '')) ?>" inputmode="numeric"></label>
        <div class="actions">
            <button type="submit" class="button button--primary">保存する</button>
            <a class="button" href="/admin/courses/<?= (int) $course['id'] ?>">戻る</a>
        </div>
    </form>
</section>
