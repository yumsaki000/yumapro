<?php
/** @var array $events */
/** @var array<string, string> $types 形式のコード => 名前（回があるものだけ） */
/** @var ?string $type 絞り込み中の形式 */
/** @var ?string $from 来たところ（公式サイトなど。申込の集計に使う） */
/** @var string $intro */
$query = fn (array $params) => ($q = http_build_query(array_filter($params, fn ($v) => $v !== null))) === '' ? '' : '?' . $q;
?>
<div class="section-head">
    <h1>これからのイベント <small><?= count($events) ?>件</small></h1>
    <p><?= nl2br(e($intro)) ?></p>
</div>
<?php if (count($types) > 1 || $type !== null): ?>
    <nav class="type-filter" aria-label="形式で絞り込む">
        <a class="type-filter__item<?= $type === null ? ' is-active' : '' ?>" href="/<?= e($query(['from' => $from])) ?>">すべて</a>
        <?php foreach ($types as $code => $name): ?>
            <a class="type-filter__item<?= $type === $code ? ' is-active' : '' ?>" href="/<?= e($query(['type' => $code, 'from' => $from])) ?>"><?= e($name) ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
<?php if ($events === []): ?>
    <section class="card"><p class="text-muted">募集中のイベントはまだありません。公式LINEやInstagramでお知らせしますので、お待ちください。</p></section>
<?php endif; ?>
<div class="event-grid">
    <?php foreach ($events as $event): ?>
        <?php
        $remaining = App\Applications::remaining($event);
        $accepting = App\Applications::accepting($event);
        $photo = App\Photos::url($event['photo']);
        $color = App\Events::colorClass($event['type_color'] ?? null);
        $href = '/e/' . $event['slug'] . $query(['from' => $from]);
        $highlights = array_slice(App\Markup::lines($event['highlights']), 0, 3);
        ?>
        <article class="card event-card">
            <a class="event-card__photo <?= e(str_replace('tag-type', 'photo', $color)) ?>" href="<?= e($href) ?>"<?= $photo !== null ? ' style="background-image:url(\'' . e($photo) . '\')"' : '' ?>>
                <span class="tag-type <?= e($color) ?>"><?= e($event['type_name']) ?></span>
            </a>
            <div class="event-card__body">
                <div class="event-card__date"><?= e(fmt_dt($event['starts_at'])) ?></div>
                <a class="event-card__title" href="<?= e($href) ?>"><?= e($event['title']) ?></a>
                <?php if (trim((string) $event['summary']) !== ''): ?>
                    <p class="event-card__summary"><?= e($event['summary']) ?></p>
                <?php endif; ?>
                <?php if ($highlights !== []): ?>
                    <ul class="chips chips--small"><?php foreach ($highlights as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <div class="text-muted small"><?= e(App\Calendar::location($event) ?: '場所は追ってご案内') ?>・<?= e(yen($event['fee'])) ?></div>
                <div class="event-card__row">
                    <?php if (!$accepting): ?>
                        <span class="seats seats--full">受付終了</span>
                    <?php elseif ($remaining === 0): ?>
                        <span class="seats seats--full">満席（キャンセル待ち）</span>
                    <?php elseif ($remaining !== null && $remaining <= 3): ?>
                        <span class="seats seats--few">残り<?= $remaining ?>席</span>
                    <?php elseif ($remaining !== null): ?>
                        <span class="seats">残り<?= $remaining ?>席</span>
                    <?php else: ?>
                        <span class="seats">受付中</span>
                    <?php endif; ?>
                    <a class="button<?= $accepting ? ' button--primary' : '' ?>" href="<?= e($href) ?>"><?= $accepting && $remaining !== 0 ? '詳細・申込' : 'くわしく' ?></a>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>
