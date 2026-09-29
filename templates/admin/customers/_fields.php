<?php
/**
 * 顧客の入力欄（登録・編集・申込の手入力で共通）。
 * @var array $values
 * @var array $channels
 */
$v = fn (string $key) => e($values[$key] ?? '');
?>
<div class="form__row">
    <label class="form__field">
        <span class="form__label">名前</span>
        <input type="text" name="name" value="<?= $v('name') ?>" maxlength="100" required>
    </label>
    <label class="form__field">
        <span class="form__label">フリガナ</span>
        <input type="text" name="name_kana" value="<?= $v('name_kana') ?>" maxlength="100">
        <span class="form__help">ひらがなでも半角でも、カタカナにそろえて保存します</span>
    </label>
</div>
<div class="form__field">
    <span class="form__label">性別</span>
    <div class="actions">
        <?php foreach (App\Customers::GENDERS as $code => $label): ?>
            <label class="form__check"><input type="radio" name="gender" value="<?= e($code) ?>"<?= ($values['gender'] ?? '') === $code ? ' checked' : '' ?>> <?= e($label) ?></label>
        <?php endforeach; ?>
        <label class="form__check"><input type="radio" name="gender" value=""<?= ($values['gender'] ?? '') === '' || $values['gender'] === null ? ' checked' : '' ?>> 未設定</label>
    </div>
</div>
<div class="form__row">
    <label class="form__field">
        <span class="form__label">電話番号</span>
        <input type="tel" name="phone" value="<?= $v('phone') ?>" inputmode="tel" autocomplete="off">
        <span class="form__help">ハイフンはあってもなくても構いません（数字だけにそろえます）</span>
    </label>
    <label class="form__field">
        <span class="form__label">メールアドレス</span>
        <input type="email" name="email" value="<?= $v('email') ?>" inputmode="email" autocomplete="off">
    </label>
</div>
<div class="form__row">
    <label class="form__field">
        <span class="form__label">SNSアカウント（タグ付け用）</span>
        <input type="text" name="sns_account" value="<?= $v('sns_account') ?>" maxlength="100" autocapitalize="none">
    </label>
    <label class="form__field">
        <span class="form__label">LINEの表示名</span>
        <input type="text" name="line_name" value="<?= $v('line_name') ?>" maxlength="100">
    </label>
</div>
<label class="form__field">
    <span class="form__label">最初に知ったきっかけ</span>
    <select name="first_channel">
        <option value="">—</option>
        <?php foreach ($channels as $name): ?>
            <option value="<?= e($name) ?>"<?= ($values['first_channel'] ?? '') === $name ? ' selected' : '' ?>><?= e($name) ?></option>
        <?php endforeach; ?>
    </select>
</label>
<label class="form__check">
    <input type="checkbox" name="mail_opt_in" value="1"<?= !empty($values['mail_opt_in']) ? ' checked' : '' ?>>
    <span>案内メールの受け取りに同意している（本人の同意があるときだけ。同意した日時を記録します）</span>
</label>
<label class="form__field">
    <span class="form__label">運営メモ</span>
    <textarea name="note" rows="3"><?= $v('note') ?></textarea>
</label>
