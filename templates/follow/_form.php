<?php
/**
 * 「次回のお知らせ」の登録欄（イベント一覧・イベントページの下に出す）。設定で「使う」にしたときだけ出す。
 *
 * @var string $back 登録のあとに戻るページ
 * @var ?int $preselect 最初から選んでおく形式（イベントページではそのイベントの形式）
 */
if (App\Settings::get('follow_enabled') !== '1') {
    return;
}
$followTypes = App\Events::types();
$followMe = App\CustomerAuth::current();
?>
<section class="card follow-box" id="follow">
    <h2>次回のお知らせを受け取る</h2>
    <p class="text-muted"><?= nl2br(e(App\Settings::get('follow_intro'))) ?></p>
    <form method="post" action="/follow" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e($back) ?>">
        <div class="hp" aria-hidden="true"><label>このまま空欄にしてください <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <fieldset>
            <legend>お知らせを受け取る形式</legend>
            <div class="follow-box__types">
                <?php foreach ($followTypes as $followType): ?>
                    <label class="form__check"><input type="checkbox" name="types[]" value="<?= (int) $followType['id'] ?>"<?= ($preselect ?? null) === (int) $followType['id'] ? ' checked' : '' ?>> <span><?= e($followType['name']) ?></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <label class="form__field">
            <span class="form__label">メールアドレス</span>
            <input type="email" name="email" required autocomplete="email" inputmode="email" value="<?= e($followMe['email'] ?? '') ?>">
        </label>
        <label class="form__check"><input type="checkbox" name="consent" value="1" required> <span>お知らせのメールを受け取ることに同意します（いつでも止められます）</span></label>
        <div><button type="submit" class="button button--primary">登録する</button></div>
        <p class="text-muted small">確認のメールをお送りします。メールのリンクを開くと登録が完了します。</p>
    </form>
</section>
