<?php
/**
 * @var array $follow
 * @var array $types
 * @var list<int> $selected
 */
$stopped = $follow['unsubscribed_at'] !== null;
?>
<section class="card card--narrow">
    <h1>次回のお知らせ</h1>
    <p class="text-muted"><?= e($follow['email']) ?> 宛て</p>
    <?php if ($stopped): ?>
        <p>お知らせの受け取りは止まっています（<?= e(fmt_dt($follow['unsubscribed_at'], false)) ?>）。</p>
        <form method="post" action="/follow/<?= e($follow['token']) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="resume">
            <button type="submit" class="button button--primary">受け取りを再開する</button>
        </form>
    <?php else: ?>
        <p>選んだ形式のイベントの募集が始まったら、メールでお知らせします。</p>
        <form method="post" action="/follow/<?= e($follow['token']) ?>" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <fieldset>
                <legend>お知らせを受け取る形式</legend>
                <div class="follow-box__types">
                    <?php foreach ($types as $type): ?>
                        <label class="form__check"><input type="checkbox" name="types[]" value="<?= (int) $type['id'] ?>"<?= in_array((int) $type['id'], $selected, true) ? ' checked' : '' ?>> <span><?= e($type['name']) ?></span></label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div><button type="submit" class="button button--primary">保存する</button></div>
        </form>
        <form method="post" action="/follow/<?= e($follow['token']) ?>" class="follow-stop">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="stop">
            <button type="submit" class="button">お知らせを止める</button>
        </form>
    <?php endif; ?>
    <p><a href="/">イベント一覧へ</a></p>
</section>
