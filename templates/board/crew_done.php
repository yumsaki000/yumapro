<?php
/** @var array|null $applied */
/** @var string $contact */
?>
<section class="card">
    <h1>お申込みありがとうございます</h1>
    <?php if ($applied === null): ?>
        <p>お申込みを受け付けました。</p>
    <?php elseif ($applied['state'] === 'already'): ?>
        <p>すでにクルーとしてご登録があります。クルー限定の講座は<a href="/learn">こちら</a>からご覧ください。</p>
    <?php elseif ($applied['state'] === 'existing'): ?>
        <p>すでにお申込みをいただいています。運営からの連絡をお待ちください。</p>
    <?php else: ?>
        <p>クルーへのお申込みを受け付けました。運営で内容を確認のうえ、月額のお支払い方法などをご案内します。</p>
    <?php endif; ?>
    <?php if ($applied !== null && $applied['email'] !== ''): ?>
        <p><strong><?= e($applied['email']) ?></strong> に確認メールをお送りしました。</p>
    <?php endif; ?>
    <?php if ($applied !== null && $applied['token'] !== ''): ?><p><a class="button" href="/my/<?= e($applied['token']) ?>">マイページを見る</a></p><?php endif; ?>
    <p class="text-muted"><?= nl2br(e($contact)) ?></p>
</section>
