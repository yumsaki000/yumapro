<?php
/** @var array $member */
/** @var bool $isSelf */
/** @var array $values */
/** @var list<string> $errors */
/** @var string|null $generatedPassword */
$id = (int) $member['id'];
?>
<section class="card card--narrow">
    <h1><?= e($member['display_name']) ?></h1>
    <?php if ($generatedPassword !== null): ?>
        <div class="alert alert--info">
            <strong>パスワード：</strong><code class="secret"><?= e($generatedPassword) ?></code><br>
            この画面にしか出ません。本人に安全な方法で伝えてください。
        </div>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <p class="text-muted">ログインID：<?= e($member['login_id']) ?>　最終ログイン：<?= e(fmt_dt($member['last_login_at'])) ?></p>

    <form method="post" action="/admin/members/<?= $id ?>" class="form">
        <?= csrf_field() ?>
        <label class="form__field">
            <span class="form__label">表示名</span>
            <input type="text" name="display_name" value="<?= e($values['display_name']) ?>" required>
        </label>
        <label class="form__field">
            <span class="form__label">権限</span>
            <select name="role"<?= $isSelf ? ' disabled' : '' ?>>
                <?php foreach (App\Admins::ROLES as $code => $label): ?>
                    <option value="<?= e($code) ?>"<?= $values['role'] === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($isSelf): ?><span class="form__help">自分の権限は変えられません（別のオーナーに頼んでください）</span><?php endif; ?>
        </label>
        <label class="form__check">
            <input type="checkbox" name="is_active" value="1"<?= $values['is_active'] ? ' checked' : '' ?><?= $isSelf ? ' disabled' : '' ?>>
            <span>有効（チェックを外すとログインできなくなります）</span>
        </label>
        <div class="actions">
            <button type="submit" class="button button--primary">保存する</button>
            <a class="button" href="/admin/members">戻る</a>
        </div>
    </form>
</section>

<section class="card card--narrow">
    <h2>パスワードを再設定</h2>
    <form method="post" action="/admin/members/<?= $id ?>/password" class="form" autocomplete="off">
        <?= csrf_field() ?>
        <label class="form__field">
            <span class="form__label">新しいパスワード</span>
            <input type="text" name="password" autocomplete="new-password" autocapitalize="none" spellcheck="false">
            <span class="form__help"><?= App\Admins::MIN_PASSWORD_LENGTH ?>文字以上。空のままにすると自動で作り、次の画面に1回だけ表示します。ログインの一時停止も解除されます</span>
        </label>
        <button type="submit" class="button">再設定する</button>
    </form>
</section>
