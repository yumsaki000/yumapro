<?php /** @var string $email */ ?>
<section class="card card--narrow">
    <h1>メールをお送りしました</h1>
    <p><strong><?= e($email) ?></strong> にログイン用のリンクをお送りしました。メールを開いてリンクを押すとログインできます（30分以内・1回だけ有効です）。</p>
    <p class="text-muted">届かないときは、迷惑メールフォルダを確認するか、お申込みに使ったメールアドレスかどうかをご確認ください。</p>
    <p><a href="/login">もう一度送る</a>　<a href="/">イベント一覧へ</a></p>
</section>
