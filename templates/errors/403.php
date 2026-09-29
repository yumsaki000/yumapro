<?php /** @var string $message */ ?>
<section class="card">
    <h1>権限がありません</h1>
    <p><?= e($message ?? 'この操作をする権限がありません。') ?></p>
    <p><a href="/admin">管理画面のトップへ戻る</a></p>
</section>
