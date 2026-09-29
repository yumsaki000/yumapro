<?php
/** @var string $heading */
/** @var string $action */
/** @var array|null $course */
/** @var array $values */
/** @var list<string> $errors */
?>
<section class="card">
    <h1><?= e($heading) ?></h1>
    <?php if ($errors !== []): ?><div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="<?= e($action) ?>" class="form">
        <?= csrf_field() ?>
        <label class="form__field"><span class="form__label">タイトル</span><input type="text" name="title" value="<?= e($values['title'] ?? '') ?>" maxlength="200" required></label>
        <label class="form__field"><span class="form__label">説明（一覧と講座ページに出す）</span><textarea name="description" rows="4"><?= e($values['description'] ?? '') ?></textarea></label>
        <div class="form__row">
            <label class="form__field">
                <span class="form__label">誰が見られるか</span>
                <select name="access">
                    <?php foreach (App\Courses::ACCESS as $code => $label): ?><option value="<?= e($code) ?>"<?= ($values['access'] ?? '') === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
                <span class="form__help">「お試し」の印を付けた回は、この設定に関係なく誰でも見られます</span>
            </label>
            <label class="form__field">
                <span class="form__label">料金（円。「購入した人」のとき）</span>
                <input type="number" name="price" value="<?= e((string) ($values['price'] ?? '')) ?>" min="0" inputmode="numeric">
                <span class="form__help">購入は事前振込。運営が入金を確認すると見られるようになります</span>
            </label>
        </div>
        <label class="form__check"><input type="checkbox" name="crew_included" value="1"<?= (int) ($values['crew_included'] ?? 1) === 1 ? ' checked' : '' ?>> <span>「購入した人」の講座を、クルーは購入なしで見られる</span></label>
        <div class="form__row">
            <label class="form__field">
                <span class="form__label">状態</span>
                <select name="status">
                    <?php foreach (App\Courses::STATUSES as $code => $label): ?><option value="<?= e($code) ?>"<?= ($values['status'] ?? 'draft') === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="form__field"><span class="form__label">並び順（小さいほど上）</span><input type="number" name="sort_order" value="<?= (int) ($values['sort_order'] ?? 0) ?>" inputmode="numeric"></label>
        </div>
        <div class="actions">
            <button type="submit" class="button button--primary">保存する</button>
            <a class="button" href="<?= $course === null ? '/admin/courses' : '/admin/courses/' . (int) $course['id'] ?>">戻る</a>
        </div>
    </form>
</section>
