<?php
/** @var array $event */
/** @var bool $accepting */
/** @var ?int $remaining */
/** @var ?string $from */
/** @var string $cancelPolicy */
$applyUrl = '/e/' . $event['slug'] . '/apply' . ($from !== null ? '?from=' . rawurlencode($from) : '');
?>
<section class="card">
    <p><a href="/">← イベント一覧</a></p>
    <h1><?= e($event['title']) ?></h1>
    <p>
        <span class="badge"><?= e($event['type_name']) ?></span>
        <?php if (!$accepting): ?>
            <span class="badge badge--warn"><?= $event['status'] === 'done' ? '終了しました' : '受付を終了しました' ?></span>
        <?php elseif ($remaining === 0): ?>
            <span class="badge badge--warn">満席（キャンセル待ちで受付中）</span>
        <?php elseif ($remaining !== null): ?>
            <span class="badge badge--ok">残り<?= $remaining ?>席</span>
        <?php else: ?>
            <span class="badge badge--ok">受付中</span>
        <?php endif; ?>
    </p>
    <dl class="kv">
        <dt>日時</dt><dd><?= e(fmt_dt($event['starts_at'])) ?><?= $event['ends_at'] !== null ? ' 〜 ' . e(date('H:i', strtotime($event['ends_at']))) : '' ?></dd>
        <dt>会場</dt><dd><?= e($event['venue_name'] ?? '追ってご案内します') ?><br><span class="text-muted">くわしい場所はお申込み後にご案内します</span></dd>
        <dt>参加費</dt><dd>
            <?php if ($event['fee_male'] !== null || $event['fee_female'] !== null): ?>
                男性 <?= e(yen($event['fee_male'] ?? $event['fee'])) ?>／女性 <?= e(yen($event['fee_female'] ?? $event['fee'])) ?>
            <?php else: ?>
                <?= e(yen($event['fee'])) ?>
            <?php endif; ?>
            （<?= e(App\Events::PAYMENT_TIMINGS[$event['payment_timing']] ?? '') ?>）
        </dd>
        <?php if ($event['capacity'] !== null): ?><dt>定員</dt><dd><?= (int) $event['capacity'] ?>人</dd><?php endif; ?>
        <?php if ($event['apply_deadline'] !== null): ?><dt>申込締切</dt><dd><?= e(fmt_dt($event['apply_deadline'])) ?></dd><?php endif; ?>
    </dl>
    <?php if ($event['description'] !== null): ?>
        <h2>内容</h2>
        <pre class="plain"><?= e($event['description']) ?></pre>
    <?php endif; ?>
    <h2>キャンセルについて</h2>
    <pre class="plain text-muted"><?= e($cancelPolicy) ?></pre>
    <?php if ($accepting): ?>
        <p style="margin-top: 20px;">
            <a class="button button--primary button--large button--block" href="<?= e($applyUrl) ?>"><?= $remaining === 0 ? 'キャンセル待ちで申し込む' : '申し込む' ?></a>
        </p>
    <?php endif; ?>
</section>
