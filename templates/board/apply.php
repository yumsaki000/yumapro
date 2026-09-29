<?php
/** @var array $event */
/** @var array $values */
/** @var array $raw 送信した生の値（追加項目・自由記述の再表示用） */
/** @var list<string> $errors */
/** @var ?string $from */
/** @var array $channels */
/** @var array $extraFields */
/** @var bool $needsGender */
/** @var ?int $remaining */
/** @var array $texts */
$v = fn (string $key) => e((string) ($values[$key] ?? ''));
$r = fn (string $key) => e(is_string($raw[$key] ?? null) ? $raw[$key] : '');
$checked = fn (string $key) => !empty($raw[$key]) ? ' checked' : '';
?>
<section class="card">
    <p><a href="/e/<?= e($event['slug']) ?>">← イベントの詳細に戻る</a></p>
    <h1>お申込み</h1>
    <p><strong><?= e($event['title']) ?></strong><br><?= e(fmt_dt($event['starts_at'])) ?>　参加費 <?= e(yen($event['fee'])) ?></p>
    <?php if ($remaining === 0): ?>
        <p class="alert alert--info">このイベントは満席のため、キャンセル待ちでのお申込みになります。空きが出たらメールでご連絡します。</p>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="/e/<?= e($event['slug']) ?>/apply" class="form">
        <?= csrf_field() ?>
        <?php if ($from !== null): ?><input type="hidden" name="from" value="<?= e($from) ?>"><?php endif; ?>
        <div class="hp" aria-hidden="true"><label>このまま空欄にしてください <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <label class="form__field">
            <span class="form__label">お名前（本名・フルネーム）<span class="req">必須</span></span>
            <input type="text" name="name" value="<?= $v('name') ?>" maxlength="100" required autocomplete="name">
        </label>
        <label class="form__field">
            <span class="form__label">フリガナ</span>
            <input type="text" name="name_kana" value="<?= $v('name_kana') ?>" maxlength="100">
        </label>
        <label class="form__field">
            <span class="form__label">メールアドレス<span class="req">必須</span></span>
            <input type="email" name="email" value="<?= $v('email') ?>" required autocomplete="email" inputmode="email">
            <span class="form__help">確認メールと当日のご案内をお送りします</span>
        </label>
        <label class="form__field">
            <span class="form__label">電話番号<span class="req">必須</span></span>
            <input type="tel" name="phone" value="<?= $v('phone') ?>" required autocomplete="tel" inputmode="tel">
        </label>
        <div class="form__field">
            <span class="form__label">性別<?php if ($needsGender): ?><span class="req">必須</span><?php endif; ?></span>
            <div class="actions">
                <?php foreach (App\Customers::GENDERS as $code => $label): ?>
                    <label class="form__check"><input type="radio" name="gender" value="<?= e($code) ?>"<?= ($values['gender'] ?? '') === $code ? ' checked' : '' ?><?= $needsGender ? ' required' : '' ?>> <?= e($label) ?></label>
                <?php endforeach; ?>
                <?php if (!$needsGender): ?><label class="form__check"><input type="radio" name="gender" value=""<?= ($values['gender'] ?? '') === '' ? ' checked' : '' ?>> 回答しない</label><?php endif; ?>
            </div>
        </div>
        <label class="form__field">
            <span class="form__label">SNSアカウント（タグ付け用・任意）</span>
            <input type="text" name="sns_account" value="<?= $v('sns_account') ?>" maxlength="100" autocapitalize="none" placeholder="@minato_community">
        </label>
        <label class="form__field">
            <span class="form__label">このイベントをどこで知りましたか<span class="req">必須</span></span>
            <select name="channel" required>
                <option value="">選んでください</option>
                <?php foreach ($channels as $name): ?>
                    <option value="<?= e($name) ?>"<?= ($values['channel'] ?? '') === $name ? ' selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="form__field">
            <span class="form__label">紹介者（いなければ空欄）</span>
            <input type="text" name="referrer" value="<?= $r('referrer') ?>" maxlength="100">
        </label>
        <?php foreach ($extraFields as $field): ?>
            <?php $name = 'extra_' . $field['key']; ?>
            <label class="form__field">
                <span class="form__label"><?= e($field['label']) ?><?php if ($field['required']): ?><span class="req">必須</span><?php endif; ?></span>
                <?php if ($field['type'] === 'textarea'): ?>
                    <textarea name="<?= e($name) ?>" rows="3"<?= $field['required'] ? ' required' : '' ?>><?= $r($name) ?></textarea>
                <?php elseif ($field['type'] === 'select'): ?>
                    <select name="<?= e($name) ?>"<?= $field['required'] ? ' required' : '' ?>>
                        <option value="">選んでください</option>
                        <?php foreach ($field['options'] as $option): ?>
                            <option value="<?= e($option) ?>"<?= ($raw[$name] ?? '') === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" name="<?= e($name) ?>" value="<?= $r($name) ?>" maxlength="1000"<?= $field['required'] ? ' required' : '' ?>>
                <?php endif; ?>
            </label>
        <?php endforeach; ?>
        <label class="form__field">
            <span class="form__label">意気込みをひとこと（任意）</span>
            <textarea name="message" rows="2" maxlength="1000"><?= $r('message') ?></textarea>
        </label>
        <label class="form__field">
            <span class="form__label">参加前の質問や不安なこと（任意）</span>
            <textarea name="questions" rows="2" maxlength="1000"><?= $r('questions') ?></textarea>
        </label>

        <fieldset>
            <legend>ご確認ください</legend>
            <div class="form">
                <div>
                    <pre class="plain text-muted"><?= e($texts['notice']) ?></pre>
                    <label class="form__check"><input type="checkbox" name="consent_notice" value="1" required<?= $checked('consent_notice') ?>> <span>注意事項に同意します<span class="req">必須</span></span></label>
                </div>
                <?php if ($event['payment_timing'] === 'prepaid'): ?>
                    <div>
                        <pre class="plain text-muted"><?= e($texts['payment']) ?></pre>
                        <label class="form__check"><input type="checkbox" name="consent_payment" value="1" required<?= $checked('consent_payment') ?>> <span>お支払いについて同意します<span class="req">必須</span></span></label>
                    </div>
                <?php endif; ?>
                <div>
                    <pre class="plain text-muted"><?= e($texts['cancel']) ?></pre>
                    <label class="form__check"><input type="checkbox" name="consent_cancel" value="1" required<?= $checked('consent_cancel') ?>> <span>キャンセルポリシーに同意します<span class="req">必須</span></span></label>
                </div>
                <label class="form__check"><input type="checkbox" name="mail_opt_in" value="1"<?= $checked('mail_opt_in') ?>> <span>今後のイベント案内をメールで受け取る（任意。いつでも停止できます）</span></label>
                <p class="form__help">入力いただいた情報は、このイベントの運営・ご連絡と、同意いただいた場合の案内に使います。<a href="<?= e($texts['privacy_url']) ?>" target="_blank" rel="noopener">プライバシーポリシー</a></p>
            </div>
        </fieldset>

        <button type="submit" class="button button--primary button--large button--block"><?= $remaining === 0 ? 'キャンセル待ちで申し込む' : 'この内容で申し込む' ?></button>
    </form>
</section>
