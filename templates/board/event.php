<?php
/** @var array $event */
/** @var bool $accepting */
/** @var ?int $remaining */
/** @var ?string $from */
/** @var string $cancelPolicy */
/** @var list<array{q: string, a: string}> $faq */
$applyUrl = '/e/' . $event['slug'] . '/apply' . ($from !== null ? '?from=' . rawurlencode($from) : '');
$photo = App\Photos::url($event['photo']);
$color = App\Events::colorClass($event['type_color'] ?? null);
?>
<p class="back"><a href="/">← イベント一覧</a></p>
<div class="event-detail">
    <section class="card">
        <?php if ($photo !== null): ?><div class="event-photo"><img src="<?= e($photo) ?>" alt=""></div><?php endif; ?>
        <span class="tag-type <?= e($color) ?>"><?= e($event['type_name']) ?></span>
        <h1 class="event-title"><?= e($event['title']) ?></h1>
        <dl class="kv">
            <dt>日時</dt><dd><?= e(fmt_dt($event['starts_at'])) ?><?= $event['ends_at'] !== null ? ' 〜 ' . e(date('H:i', strtotime($event['ends_at']))) : '' ?></dd>
            <dt>会場</dt><dd><?= e($event['venue_name'] ?? '追ってご案内します') ?><br><span class="text-muted">くわしい場所はお申込み後にご案内します</span></dd>
            <dt>参加費</dt><dd>
                <?php if ($event['fee_male'] !== null || $event['fee_female'] !== null): ?>
                    男性 <?= e(yen($event['fee_male'] ?? $event['fee'])) ?>／女性 <?= e(yen($event['fee_female'] ?? $event['fee'])) ?>
                <?php else: ?>
                    <?= e(yen($event['fee'])) ?>
                <?php endif; ?>
                <?php if ($event['fee_crew'] !== null): ?>／クルー <?= e(yen($event['fee_crew'])) ?><?php endif; ?>
                （<?= e(App\Events::PAYMENT_TIMINGS[$event['payment_timing']] ?? '') ?>）
            </dd>
            <?php if ($event['capacity'] !== null): ?><dt>定員</dt><dd><?= (int) $event['capacity'] ?>人</dd><?php endif; ?>
        </dl>
        <?php if ($event['description'] !== null): ?>
            <h2>内容</h2>
            <pre class="plain"><?= e($event['description']) ?></pre>
        <?php endif; ?>
        <?php if ($faq !== []): ?>
            <h2>よくある質問</h2>
            <dl class="faq">
                <?php foreach ($faq as $item): ?>
                    <dt><?= e($item['q']) ?></dt><dd><?= nl2br(e($item['a'])) ?></dd>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
        <h2>キャンセルについて</h2>
        <pre class="plain text-muted"><?= e($cancelPolicy) ?></pre>
    </section>

    <aside class="card event-aside">
        <div class="text-muted">参加費</div>
        <div class="price"><?= e(yen($event['fee'])) ?> <small><?= e(App\Events::PAYMENT_TIMINGS[$event['payment_timing']] ?? '') ?></small></div>
        <div class="event-aside__status">
            <?php if (!$accepting): ?>
                <span class="seats seats--full"><?= $event['status'] === 'done' ? '終了しました' : '受付を終了しました' ?></span>
            <?php elseif ($remaining === 0): ?>
                <span class="seats seats--full">満席（キャンセル待ちで受付中）</span>
            <?php elseif ($remaining !== null): ?>
                <span class="seats<?= $remaining <= 3 ? ' seats--few' : '' ?>">残り<?= $remaining ?>席</span>
            <?php else: ?>
                <span class="seats">受付中</span>
            <?php endif; ?>
            <?php if ($event['apply_deadline'] !== null): ?><span class="text-muted">・申込締切 <?= e(fmt_dt($event['apply_deadline'], false)) ?></span><?php endif; ?>
        </div>
        <?php if ($accepting): ?>
            <a class="button button--primary button--large button--block" href="<?= e($applyUrl) ?>"><?= $remaining === 0 ? 'キャンセル待ちで申し込む' : '申し込む' ?></a>
            <p class="text-muted small">お申込み後、確認メールが届きます。キャンセルは確認メールの個人専用ページから。</p>
        <?php endif; ?>
    </aside>
</div>
