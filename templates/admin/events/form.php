<?php
/** @var string $heading */
/** @var string $action */
/** @var array $types */
/** @var array $values */
/** @var list<string> $errors */
/** @var array|null $event 編集のとき */
$v = fn (string $key) => e($values[$key] ?? '');
?>
<section class="card">
    <h1><?= e($heading) ?></h1>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="<?= e($action) ?>" class="form">
        <?= csrf_field() ?>

        <div class="form__row">
            <label class="form__field">
                <span class="form__label">形式</span>
                <select name="event_type_id" id="event_type_id">
                    <?php foreach ($types as $type): ?>
                        <option value="<?= (int) $type['id'] ?>" data-payment="<?= e($type['payment_timing']) ?>"<?= (int) ($values['event_type_id'] ?? 0) === (int) $type['id'] ? ' selected' : '' ?>><?= e($type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="form__field">
                <span class="form__label">状態</span>
                <select name="status">
                    <?php foreach (App\Events::STATUSES as $code => $label): ?>
                        <option value="<?= e($code) ?>"<?= ($values['status'] ?? 'draft') === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="form__help">「募集中」にすると掲示板に出ます（掲示板は準備中）</span>
            </label>
        </div>

        <div class="form__row">
            <label class="form__field">
                <span class="form__label">タイトル</span>
                <input type="text" name="title" value="<?= $v('title') ?>" maxlength="200" required>
            </label>
            <label class="form__field">
                <span class="form__label">第n回（任意）</span>
                <input type="number" name="round_no" value="<?= $v('round_no') ?>" min="0" inputmode="numeric">
            </label>
        </div>

        <div class="form__row">
            <label class="form__field">
                <span class="form__label">開始日時</span>
                <input type="datetime-local" name="starts_at" value="<?= e(dt_input($values['starts_at'] ?? null)) ?>" required>
            </label>
            <label class="form__field">
                <span class="form__label">終了日時（任意）</span>
                <input type="datetime-local" name="ends_at" value="<?= e(dt_input($values['ends_at'] ?? null)) ?>">
            </label>
        </div>

        <fieldset>
            <legend>会場</legend>
            <div class="form">
                <label class="form__field">
                    <span class="form__label">会場名</span>
                    <input type="text" name="venue_name" value="<?= $v('venue_name') ?>" maxlength="200">
                </label>
                <label class="form__field">
                    <span class="form__label">住所（申込が確定した人にだけ見せる）</span>
                    <input type="text" name="venue_address" value="<?= $v('venue_address') ?>" maxlength="255">
                </label>
                <label class="form__field">
                    <span class="form__label">地図などのURL</span>
                    <input type="url" name="venue_url" value="<?= $v('venue_url') ?>" maxlength="500" inputmode="url">
                </label>
            </div>
        </fieldset>

        <fieldset>
            <legend>定員と参加費</legend>
            <div class="form">
                <div class="form__row">
                    <label class="form__field">
                        <span class="form__label">定員（空欄なら上限なし）</span>
                        <input type="number" name="capacity" value="<?= $v('capacity') ?>" min="0" inputmode="numeric">
                    </label>
                    <label class="form__field">
                        <span class="form__label">参加費（円）</span>
                        <input type="number" name="fee" value="<?= $v('fee') ?>" min="0" inputmode="numeric" required>
                    </label>
                </div>
                <div class="form__row">
                    <label class="form__field">
                        <span class="form__label">男性の定員（合コンなど。任意）</span>
                        <input type="number" name="capacity_male" value="<?= $v('capacity_male') ?>" min="0" inputmode="numeric">
                    </label>
                    <label class="form__field">
                        <span class="form__label">女性の定員（任意）</span>
                        <input type="number" name="capacity_female" value="<?= $v('capacity_female') ?>" min="0" inputmode="numeric">
                    </label>
                </div>
                <div class="form__row">
                    <label class="form__field">
                        <span class="form__label">男性の参加費（円。任意）</span>
                        <input type="number" name="fee_male" value="<?= $v('fee_male') ?>" min="0" inputmode="numeric">
                    </label>
                    <label class="form__field">
                        <span class="form__label">女性の参加費（円。任意）</span>
                        <input type="number" name="fee_female" value="<?= $v('fee_female') ?>" min="0" inputmode="numeric">
                    </label>
                </div>
                <label class="form__field">
                    <span class="form__label">クルー料金（円。任意）</span>
                    <input type="number" name="fee_crew" value="<?= $v('fee_crew') ?>" min="0" inputmode="numeric">
                    <span class="form__help">加入中のクルーが申し込むと、自動でこの料金になります</span>
                </label>
                <label class="form__field">
                    <span class="form__label">支払い</span>
                    <select name="payment_timing" id="payment_timing">
                        <?php foreach (App\Events::PAYMENT_TIMINGS as $code => $label): ?>
                            <option value="<?= e($code) ?>"<?= ($values['payment_timing'] ?? '') === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </fieldset>

        <fieldset>
            <legend>締切とキャンセル</legend>
            <div class="form">
                <div class="form__row">
                    <label class="form__field">
                        <span class="form__label">申込締切（任意）</span>
                        <input type="datetime-local" name="apply_deadline" value="<?= e(dt_input($values['apply_deadline'] ?? null)) ?>">
                    </label>
                    <label class="form__field">
                        <span class="form__label">キャンセル期限（任意）</span>
                        <input type="datetime-local" name="cancel_deadline" value="<?= e(dt_input($values['cancel_deadline'] ?? null)) ?>">
                        <?php if ($event === null): ?><span class="form__help">前払いの回で空欄なら、開始の7日前を入れます</span><?php endif; ?>
                    </label>
                </div>
                <label class="form__field">
                    <span class="form__label">キャンセル規定（参加者に見せる文）</span>
                    <textarea name="cancel_policy" rows="3"><?= $v('cancel_policy') ?></textarea>
                </label>
            </div>
        </fieldset>

        <label class="form__field">
            <span class="form__label">説明文（掲示板に出す）</span>
            <textarea name="description" rows="6"><?= $v('description') ?></textarea>
        </label>

        <label class="form__field">
            <span class="form__label">主催分（円）</span>
            <input type="number" name="organizer_amount" value="<?= $v('organizer_amount') ?>" min="0" inputmode="numeric">
            <span class="form__help">収支 ＝ 収入 − 経費 − 主催分。会計の画面でも変えられます</span>
        </label>

        <div class="actions">
            <button type="submit" class="button button--primary">保存する</button>
            <a class="button" href="<?= $event === null ? '/admin/events' : '/admin/events/' . (int) $event['id'] ?>">戻る</a>
        </div>
    </form>
</section>
<?php if ($event === null): ?>
<script>
// 形式を変えたら、その形式の既定の支払い方法にする（新規のときだけ）
document.getElementById('event_type_id').addEventListener('change', function () {
    var payment = this.options[this.selectedIndex].dataset.payment;
    if (payment) { document.getElementById('payment_timing').value = payment; }
});
</script>
<?php endif; ?>
