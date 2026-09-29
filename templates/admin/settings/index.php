<?php
/** @var array<string, list<string>> $sections */
/** @var array<string, string> $values */
/** @var list<string> $errors */
/** @var string $fromAddress */
?>
<section class="card">
    <h1>設定</h1>
    <p class="text-muted">掲示板・申込フォーム・メールの文言です。空欄にすると既定の文に戻ります。メールの本文では {name} のような言葉が、その申込の内容に置き換わります。ほかの設定：<a href="/admin/channels">「どこで知りましたか」の選択肢</a></p>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="/admin/settings" class="form">
        <?= csrf_field() ?>
        <?php foreach ($sections as $heading => $keys): ?>
            <fieldset>
                <legend><?= e($heading) ?></legend>
                <div class="form">
                    <?php foreach ($keys as $key): ?>
                        <?php [$label, $help, $default, $multiline] = App\Settings::ITEMS[$key]; ?>
                        <label class="form__field">
                            <span class="form__label"><?= e($label) ?></span>
                            <?php if ($multiline): ?>
                                <textarea name="<?= e($key) ?>" rows="<?= str_contains($key, '_body') ? 10 : 3 ?>"><?= e($values[$key]) ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?= e($key) ?>" value="<?= e($values[$key]) ?>">
                            <?php endif; ?>
                            <?php if ($help !== '' || $key === 'mail_from_address'): ?>
                                <span class="form__help"><?= e($help) ?><?= $key === 'mail_from_address' ? '　今の差出人：' . e($fromAddress) : '' ?></span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endforeach; ?>
        <div class="actions">
            <button type="submit" class="button button--primary">保存する</button>
        </div>
    </form>
</section>
