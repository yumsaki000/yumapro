<?php
/** @var array $registration */
/** @var string $token */
/** @var array $values */
/** @var list<string> $errors */
/** @var bool $answered */
?>
<section class="card">
    <h1>アンケート</h1>
    <p><strong><?= e($registration['event_title']) ?></strong>（<?= e(fmt_dt($registration['event_starts_at'], false)) ?>）にご参加いただき、ありがとうございました。今後の参考に教えてください。</p>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="/my/<?= e($token) ?>/survey/<?= (int) $registration['id'] ?>" class="form">
        <?= csrf_field() ?>
        <div class="form__field">
            <span class="form__label">満足度</span>
            <div class="actions rating">
                <?php foreach ([5 => 'とても良かった', 4 => '良かった', 3 => 'ふつう', 2 => 'いまいち', 1 => '良くなかった'] as $n => $label): ?>
                    <label class="form__check"><input type="radio" name="satisfaction" value="<?= $n ?>"<?= (string) $values['satisfaction'] === (string) $n ? ' checked' : '' ?> required> <?= $n ?>　<?= e($label) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form__field">
            <span class="form__label">また参加したいですか</span>
            <div class="actions">
                <?php foreach (App\Applications::RETURN_INTENTS as $code => $label): ?>
                    <label class="form__check"><input type="radio" name="return_intent" value="<?= e($code) ?>"<?= $values['return_intent'] === $code ? ' checked' : '' ?> required> <?= e($label) ?></label>
                <?php endforeach; ?>
            </div>
        </div>
        <label class="form__field">
            <span class="form__label">感想・良かったこと・改善してほしいこと（任意）</span>
            <textarea name="comment" rows="4" maxlength="2000"><?= e((string) $values['comment']) ?></textarea>
        </label>
        <button type="submit" class="button button--primary button--block"><?= $answered ? '回答を更新する' : '送信する' ?></button>
    </form>
</section>
