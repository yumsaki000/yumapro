<?php
/** @var string $intro */
/** @var list<string> $benefits */
/** @var string $feeText */
/** @var string $termsUrl */
/** @var string $policyUrl */
/** @var string $noticeText */
/** @var string $privacyUrl */
/** @var array $values */
/** @var array $raw */
/** @var list<string> $errors */
$v = fn (string $key) => e((string) ($values[$key] ?? ''));
?>
<section class="card">
    <h1>MINATO クルー募集</h1>
    <p><?= nl2br(e($intro)) ?></p>
    <?php if ($benefits !== []): ?>
        <h2>クルーになると</h2>
        <ul class="benefits">
            <?php foreach ($benefits as $b): ?><li><?= e($b) ?></li><?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($feeText !== ''): ?><p><strong><?= e($feeText) ?></strong></p><?php endif; ?>
    <p class="text-muted"><a href="<?= e($termsUrl) ?>" target="_blank" rel="noopener">利用規約</a><?php if ($policyUrl !== ''): ?>　<a href="<?= e($policyUrl) ?>" target="_blank" rel="noopener">クルーポリシー</a><?php endif; ?></p>
</section>

<section class="card">
    <h2 id="apply">クルーに申し込む</h2>
    <p class="text-muted">お申込み後、運営から月額のお支払い方法などをご案内します。</p>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="/crew" class="form">
        <?= csrf_field() ?>
        <div class="hp" aria-hidden="true"><label>このまま空欄にしてください <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <label class="form__field"><span class="form__label">お名前（本名・フルネーム）<span class="req">必須</span></span><input type="text" name="name" value="<?= $v('name') ?>" maxlength="100" required autocomplete="name"></label>
        <label class="form__field"><span class="form__label">フリガナ</span><input type="text" name="name_kana" value="<?= $v('name_kana') ?>" maxlength="100"></label>
        <label class="form__field"><span class="form__label">メールアドレス<span class="req">必須</span></span><input type="email" name="email" value="<?= $v('email') ?>" required autocomplete="email" inputmode="email"></label>
        <label class="form__field"><span class="form__label">電話番号<span class="req">必須</span></span><input type="tel" name="phone" value="<?= $v('phone') ?>" required autocomplete="tel" inputmode="tel"></label>
        <div class="form__field">
            <span class="form__label">性別（任意）</span>
            <div class="actions">
                <?php foreach (App\Customers::GENDERS as $code => $label): ?>
                    <label class="form__check"><input type="radio" name="gender" value="<?= e($code) ?>"<?= $values['gender'] === $code ? ' checked' : '' ?>> <?= e($label) ?></label>
                <?php endforeach; ?>
                <label class="form__check"><input type="radio" name="gender" value=""<?= $values['gender'] === '' ? ' checked' : '' ?>> 回答しない</label>
            </div>
        </div>
        <label class="form__field"><span class="form__label">お住まいの地域（任意）</span><input type="text" name="region" value="<?= $v('region') ?>" maxlength="100" placeholder="例：東京都・神奈川県"></label>
        <label class="form__field">
            <span class="form__label">初回の連絡はどれがよいですか</span>
            <select name="contact_pref">
                <?php foreach (App\Crew::CONTACT_PREFS as $code => $label): ?>
                    <option value="<?= e($code) ?>"<?= $values['contact_pref'] === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="form__field"><span class="form__label">ひとこと（任意）</span><textarea name="comment" rows="3" maxlength="1000"><?= $v('comment') ?></textarea></label>
        <fieldset>
            <legend>ご確認ください</legend>
            <div class="form">
                <pre class="plain text-muted"><?= e($noticeText) ?></pre>
                <label class="form__check"><input type="checkbox" name="consent_crew" value="1" required<?= !empty($raw['consent_crew']) ? ' checked' : '' ?>> <span><a href="<?= e($termsUrl) ?>" target="_blank" rel="noopener">利用規約</a><?php if ($policyUrl !== ''): ?>と<a href="<?= e($policyUrl) ?>" target="_blank" rel="noopener">クルーポリシー</a><?php endif; ?>に同意します<span class="req">必須</span></span></label>
                <label class="form__check"><input type="checkbox" name="mail_opt_in" value="1"<?= !empty($raw['mail_opt_in']) ? ' checked' : '' ?>> <span>イベントの案内をメールで受け取る（任意）</span></label>
                <p class="form__help">入力いただいた情報は、クルーの運営とご連絡に使います。<a href="<?= e($privacyUrl) ?>" target="_blank" rel="noopener">プライバシーポリシー</a></p>
            </div>
        </fieldset>
        <button type="submit" class="button button--primary button--large button--block">クルーに申し込む</button>
    </form>
</section>
