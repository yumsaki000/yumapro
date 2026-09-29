<?php
/** @var array $values */
/** @var list<string> $errors */
?>
<section class="card card--narrow">
    <h1>メンバーを追加</h1>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="/admin/members/new" class="form" autocomplete="off">
        <?= csrf_field() ?>
        <label class="form__field">
            <span class="form__label">ログインID</span>
            <input type="text" name="login_id" value="<?= e($values['login_id']) ?>" autocapitalize="none" spellcheck="false" required>
            <span class="form__help">半角英数字と . _ - の3〜64文字。あとから変えられません</span>
        </label>
        <label class="form__field">
            <span class="form__label">表示名</span>
            <input type="text" name="display_name" value="<?= e($values['display_name']) ?>" required>
        </label>
        <label class="form__field">
            <span class="form__label">権限</span>
            <select name="role">
                <?php foreach (App\Admins::ROLES as $code => $label): ?>
                    <option value="<?= e($code) ?>"<?= $values['role'] === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="form__help">オーナーはメンバーの追加・無効化・パスワード再設定もできます</span>
        </label>
        <label class="form__field">
            <span class="form__label">パスワード</span>
            <input type="text" name="password" autocomplete="new-password" autocapitalize="none" spellcheck="false">
            <span class="form__help"><?= App\Admins::MIN_PASSWORD_LENGTH ?>文字以上。空のままにすると自動で作り、次の画面に1回だけ表示します</span>
        </label>
        <div class="actions">
            <button type="submit" class="button button--primary">追加する</button>
            <a class="button" href="/admin/members">戻る</a>
        </div>
    </form>
</section>
