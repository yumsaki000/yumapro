<?php
/** @var array $event */
/** @var array|null $applied */
/** @var string $contact */
/** @var string $lineUrl */
?>
<section class="card">
    <h1>お申込みありがとうございます</h1>
    <?php if ($applied === null): ?>
        <p>お申込みを受け付けました。</p>
    <?php elseif ($applied['existing']): ?>
        <p>「<?= e($event['title']) ?>」には、すでにお申込みをいただいています。確認メールをもう一度お送りしました。</p>
    <?php elseif ($applied['status'] === 'waitlisted'): ?>
        <p>「<?= e($event['title']) ?>」を<strong>キャンセル待ち</strong>で受け付けました。空きが出ましたら、順番にメールでご案内します。</p>
    <?php else: ?>
        <p>「<?= e($event['title']) ?>」のお申込みを受け付けました。</p>
        <?php if ($event['payment_timing'] === 'prepaid'): ?>
            <p>参加費は事前振込制です。確認メールに振込先を記載していますので、期日までにお振込みをお願いします。</p>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($applied !== null && $applied['email'] !== ''): ?>
        <p><strong><?= e($applied['email']) ?></strong> に確認メールをお送りしました。届かない場合は、迷惑メールフォルダをご確認ください。</p>
    <?php endif; ?>
    <?php if ($applied !== null && $applied['token'] !== ''): ?>
        <p><a class="button" href="/my/<?= e($applied['token']) ?>">お申込み内容を確認する</a></p>
    <?php endif; ?>
    <?php if ($lineUrl !== ''): ?>
        <p>当日の連絡は公式LINEでも行います。<a href="<?= e($lineUrl) ?>" target="_blank" rel="noopener">公式LINEを友だち追加</a></p>
    <?php endif; ?>
    <p class="text-muted"><?= nl2br(e($contact)) ?></p>
    <p><a href="/">イベント一覧へ戻る</a></p>
</section>
