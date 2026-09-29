<?php
/** @var string $next */
/** @var string $email */
/** @var string|null $error */
?>
<section class="card card--narrow">
    <h1>ログイン</h1>
    <p class="text-muted">イベントやクルーのお申込みに使ったメールアドレスを入れてください。ログイン用のリンクをメールでお送りします（パスワードはありません）。</p>
    <?php if ($error !== null): ?><p class="alert alert--error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="/login" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="hp" aria-hidden="true"><label>このまま空欄にしてください <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <label class="form__field">
            <span class="form__label">メールアドレス</span>
            <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email" inputmode="email" autofocus>
        </label>
        <button type="submit" class="button button--primary button--block">ログイン用のリンクを送る</button>
    </form>
    <p class="form__hint">届かないときは迷惑メールフォルダをご確認ください。お申込みのないメールアドレスには届きません。</p>
</section>
