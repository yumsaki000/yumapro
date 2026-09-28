<?php
/** @var string $loginId */
/** @var string $next */
/** @var string|null $error */
/** @var string|null $notice */
?>
<section class="card card--narrow">
    <h1>ログイン</h1>

    <?php if ($notice): ?>
        <p class="alert alert--info"><?= e($notice) ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="alert alert--error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/admin/login" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">

        <label class="form__field">
            <span class="form__label">ログインID</span>
            <input type="text" name="login_id" value="<?= e($loginId) ?>"
                   autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
        </label>

        <label class="form__field">
            <span class="form__label">パスワード</span>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <button type="submit" class="button button--primary button--block">ログイン</button>
    </form>

    <p class="form__hint">アカウントは運営メンバー1人ずつに発行しています。パスワードを忘れたときは、管理者に再設定を頼んでください。</p>
</section>
