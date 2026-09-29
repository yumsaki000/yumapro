<?php
/**
 * 回のページ。こくちーずの掲載ページと同じ流れ（写真 → 一言紹介 → 安心ポイント → 基本情報 → 内容 → おすすめ →
 * タイムスケジュール → よくある質問 → アクセス → キャンセル → 主催について → シェア）で並べる。
 *
 * @var array $event
 * @var bool $preview 管理画面からの確認（申込はできない）
 * @var bool $accepting
 * @var ?int $remaining
 * @var ?string $from
 * @var string $cancelPolicy
 * @var list<array{q: string, a: string}> $faq
 * @var list<array{id: int, name: string}> $photos 表紙のほかの写真
 * @var array $others ほかのイベント
 * @var string $pageUrl
 * @var string $googleCalendarUrl
 * @var string $community
 * @var string $aboutUrl
 * @var string $lineUrl
 */
$siteName = App\Settings::get('public_name');
$applyUrl = '/e/' . $event['slug'] . '/apply' . ($from !== null ? '?from=' . rawurlencode($from) : '');
$cover = App\Photos::url($event['photo']);
$gallery = array_values(array_filter(array_merge(
    $cover !== null ? [$cover] : [],
    array_map(fn ($p) => App\Photos::url($p['name']), $photos)
)));
$color = App\Events::colorClass($event['type_color'] ?? null);
$highlights = App\Markup::lines($event['highlights']);
$recommend = App\Markup::lines($event['recommend']);
$timetable = App\Markup::timetable($event['timetable']);
$location = App\Calendar::location($event);
$mapQuery = trim((string) $event['map_query']);
$capacity = $event['capacity'] !== null ? (int) $event['capacity'] : null;
$paymentLabel = App\Events::PAYMENT_TIMINGS[$event['payment_timing']] ?? '';
$startDay = date('Y-m-d', strtotime($event['starts_at']));
$when = fmt_dt($event['starts_at']);
if ($event['ends_at'] !== null) {
    $when .= '〜' . (date('Y-m-d', strtotime($event['ends_at'])) === $startDay ? date('H:i', strtotime($event['ends_at'])) : fmt_dt($event['ends_at']));
}
$statusNote = match (true) {
    $event['status'] === 'done' => '終了しました',
    !$preview && !$accepting => '受付を終了しました',
    $remaining === 0 => '満席（キャンセル待ちで受付中）',
    default => null,
};
$seatsLabel = match (true) {
    $statusNote !== null => $statusNote,
    $remaining !== null => '残り' . $remaining . '席',
    default => '受付中',
};
$seatsClass = $statusNote !== null ? 'seats seats--full' : ($remaining !== null && $remaining <= 3 ? 'seats seats--few' : 'seats');
$fillRatio = $capacity !== null && $capacity > 0 ? min(100, (int) round((int) $event['applied_count'] / $capacity * 100)) : null;
$feeText = function () use ($event): string {
    if ($event['fee_male'] !== null || $event['fee_female'] !== null) {
        return '男性 ' . yen($event['fee_male'] ?? $event['fee']) . '／女性 ' . yen($event['fee_female'] ?? $event['fee']);
    }
    return yen($event['fee']);
};
$buttonLabel = $remaining === 0 ? 'キャンセル待ちで申し込む' : 'このイベントに申し込む';
?>
<?php if ($preview): ?>
    <div class="preview-bar" role="status">
        <strong>プレビュー</strong>（いまの状態：<?= e(App\Events::STATUSES[$event['status']] ?? $event['status']) ?>）参加者にはこう見えます。申込ボタンはここでは押せません。
        <span class="preview-bar__links">
            <a href="/admin/events/<?= (int) $event['id'] ?>/edit">編集に戻る</a>
            <a href="/admin/events/<?= (int) $event['id'] ?>">回の画面へ</a>
        </span>
    </div>
<?php endif; ?>
<p class="back"><a href="/">← イベント一覧</a></p>

<div class="event-detail">
    <article class="card event-main">
        <?php if ($gallery !== []): ?>
            <div class="event-photo"><img id="event-photo-main" src="<?= e($gallery[0]) ?>" alt=""></div>
            <?php if (count($gallery) > 1): ?>
                <div class="event-thumbs">
                    <?php foreach ($gallery as $i => $src): ?>
                        <a href="<?= e($src) ?>" data-photo="<?= e($src) ?>"<?= $i === 0 ? ' class="is-active"' : '' ?>><img src="<?= e($src) ?>" alt="" loading="lazy"></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="event-photo event-photo--blank <?= e(str_replace('tag-type', 'photo', $color)) ?>" aria-hidden="true"></div>
        <?php endif; ?>

        <div class="event-head">
            <span class="tag-type <?= e($color) ?>"><?= e($event['type_name']) ?></span>
            <?php if ($statusNote !== null): ?><span class="seats seats--full"><?= e($statusNote) ?></span><?php endif; ?>
            <h1 class="event-title"><?= e($event['title']) ?></h1>
            <?php if (trim((string) $event['summary']) !== ''): ?>
                <p class="event-lead"><?= nl2br(e($event['summary'])) ?></p>
            <?php endif; ?>
            <?php if ($highlights !== []): ?>
                <ul class="chips"><?php foreach ($highlights as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>

        <dl class="event-info">
            <dt>日時</dt>
            <dd>
                <?= e($when) ?>
                <span class="event-info__links">
                    <a href="<?= e($googleCalendarUrl) ?>" target="_blank" rel="noopener">Googleカレンダーに追加</a>
                    <a href="/e/<?= e($event['slug']) ?>/calendar.ics">iPhoneなどのカレンダーに追加</a>
                </span>
            </dd>
            <dt>場所</dt>
            <dd>
                <?= e($location !== '' ? $location : '追ってご案内します') ?>
                <?php if ($event['venue_name'] !== null && $location !== $event['venue_name']): ?><br><span class="text-muted"><?= e($event['venue_name']) ?></span><?php endif; ?>
                <br><span class="text-muted small">くわしい場所はお申込み後にご案内します</span>
            </dd>
            <dt>参加費</dt>
            <dd>
                <?= e($feeText()) ?>
                <?php if ($event['fee_crew'] !== null): ?>（クルーは <?= e(yen($event['fee_crew'])) ?>）<?php endif; ?>
                <span class="text-muted">・<?= e($paymentLabel) ?></span>
            </dd>
            <?php if (trim((string) $event['belongings']) !== ''): ?>
                <dt>持ち物</dt><dd><?= nl2br(e($event['belongings'])) ?></dd>
            <?php endif; ?>
            <?php if ($capacity !== null): ?>
                <dt>定員</dt><dd><?= $capacity ?>人</dd>
            <?php endif; ?>
            <?php if ($event['apply_deadline'] !== null): ?>
                <dt>申込締切</dt><dd><?= e(fmt_dt($event['apply_deadline'])) ?></dd>
            <?php endif; ?>
        </dl>

        <?php if (trim((string) $event['description']) !== ''): ?>
            <section class="event-section">
                <h2>イベント内容</h2>
                <div class="rich"><?= App\Markup::render($event['description']) ?></div>
            </section>
        <?php endif; ?>

        <?php if ($recommend !== []): ?>
            <section class="event-section">
                <h2>こんな方におすすめ</h2>
                <ul class="checklist"><?php foreach ($recommend as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
            </section>
        <?php endif; ?>

        <?php if ($timetable !== []): ?>
            <section class="event-section">
                <h2>当日のタイムスケジュール</h2>
                <ol class="timeline">
                    <?php foreach ($timetable as $row): ?>
                        <li><span class="timeline__time"><?= e($row['time']) ?></span><span class="timeline__text"><?= nl2br(e($row['text'])) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endif; ?>

        <?php if ($faq !== []): ?>
            <section class="event-section">
                <h2>初めての方へ｜よくある質問</h2>
                <dl class="faq">
                    <?php foreach ($faq as $item): ?>
                        <dt><?= e($item['q']) ?></dt><dd><?= nl2br(e($item['a'])) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endif; ?>

        <?php if ($mapQuery !== ''): ?>
            <section class="event-section">
                <h2>アクセス</h2>
                <?php if ($location !== ''): ?><p><?= e($location) ?></p><?php endif; ?>
                <div class="map">
                    <iframe src="https://www.google.com/maps?q=<?= e(rawurlencode($mapQuery)) ?>&amp;output=embed&amp;hl=ja&amp;z=15" title="地図" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                <p class="small"><a href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($mapQuery)) ?>" target="_blank" rel="noopener">Googleマップで開く</a>・くわしい場所はお申込み後にご案内します</p>
            </section>
        <?php endif; ?>

        <section class="event-section">
            <h2>キャンセルについて</h2>
            <pre class="plain text-muted"><?= e($cancelPolicy) ?></pre>
        </section>

        <?php if (trim($community) !== ''): ?>
            <section class="event-section organizer">
                <h2><?= e($siteName) ?>ってどんなコミュニティ？</h2>
                <div class="organizer__body">
                    <img class="organizer__logo" src="/assets/logo.png" alt="">
                    <div class="rich"><?= App\Markup::render($community) ?></div>
                </div>
                <div class="actions">
                    <?php if ($aboutUrl !== ''): ?><a class="button" href="<?= e($aboutUrl) ?>"><?= e($siteName) ?>について（公式サイト）</a><?php endif; ?>
                    <?php if ($lineUrl !== ''): ?><a class="button" href="<?= e($lineUrl) ?>" target="_blank" rel="noopener">次回のお知らせを公式LINEで受け取る</a><?php endif; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="event-section share">
            <h2>このイベントをシェア</h2>
            <div class="share__buttons">
                <a class="button button--small share--line" href="https://social-plugins.line.me/lineit/share?url=<?= e(rawurlencode($pageUrl)) ?>" target="_blank" rel="noopener">LINEで送る</a>
                <a class="button button--small" href="https://twitter.com/intent/tweet?text=<?= e(rawurlencode($event['title'])) ?>&amp;url=<?= e(rawurlencode($pageUrl)) ?>" target="_blank" rel="noopener">Xでシェア</a>
                <a class="button button--small" href="https://www.facebook.com/sharer/sharer.php?u=<?= e(rawurlencode($pageUrl)) ?>" target="_blank" rel="noopener">Facebook</a>
                <button type="button" class="button button--small" data-copy="<?= e($pageUrl) ?>">リンクをコピー</button>
            </div>
        </section>
    </article>

    <aside class="card event-aside" id="apply">
        <div class="text-muted small">参加費</div>
        <div class="price"><?= e($feeText()) ?> <small><?= e($paymentLabel) ?></small></div>
        <?php if ($event['fee_crew'] !== null): ?><div class="text-muted small">クルーは <?= e(yen($event['fee_crew'])) ?></div><?php endif; ?>
        <div class="event-aside__status">
            <span class="<?= e($seatsClass) ?>"><?= e($seatsLabel) ?></span>
            <?php if ($event['apply_deadline'] !== null && $statusNote === null): ?><span class="text-muted small">申込締切 <?= e(fmt_dt($event['apply_deadline'], false)) ?></span><?php endif; ?>
        </div>
        <?php if ($fillRatio !== null && $statusNote === null): ?>
            <div class="seat-bar" aria-hidden="true"><span style="width: <?= $fillRatio ?>%"></span></div>
        <?php endif; ?>
        <?php if ($accepting): ?>
            <a class="button button--primary button--large button--block" href="<?= e($applyUrl) ?>"><?= e($buttonLabel) ?></a>
            <p class="text-muted small">お申込み後、確認メールが届きます。キャンセルは確認メールの個人専用ページから。</p>
        <?php elseif ($preview): ?>
            <span class="button button--large button--block is-disabled"><?= e($buttonLabel) ?>（プレビュー）</span>
        <?php endif; ?>
        <dl class="event-aside__summary">
            <dt>日時</dt><dd><?= e($when) ?></dd>
            <dt>場所</dt><dd><?= e($location !== '' ? $location : '追ってご案内します') ?></dd>
        </dl>
    </aside>
</div>

<?php if ($others !== []): ?>
    <section class="others">
        <h2>ほかのイベント</h2>
        <div class="event-grid">
            <?php foreach ($others as $other): ?>
                <?php $otherColor = App\Events::colorClass($other['type_color'] ?? null); $otherPhoto = App\Photos::url($other['photo']); ?>
                <a class="card event-card event-card--mini" href="/e/<?= e($other['slug']) ?>">
                    <span class="event-card__photo <?= e(str_replace('tag-type', 'photo', $otherColor)) ?>"<?= $otherPhoto !== null ? ' style="background-image:url(\'' . e($otherPhoto) . '\')"' : '' ?>>
                        <span class="tag-type <?= e($otherColor) ?>"><?= e($other['type_name']) ?></span>
                    </span>
                    <span class="event-card__body">
                        <span class="event-card__date"><?= e(fmt_dt($other['starts_at'])) ?></span>
                        <span class="event-card__title"><?= e($other['title']) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($accepting): ?>
    <div class="apply-bar">
        <div class="apply-bar__info">
            <strong><?= e(yen($event['fee'])) ?></strong>
            <span class="<?= e($seatsClass) ?>"><?= e($seatsLabel) ?></span>
        </div>
        <a class="button button--primary" href="<?= e($applyUrl) ?>"><?= $remaining === 0 ? 'キャンセル待ち' : '申し込む' ?></a>
    </div>
    <div class="apply-bar-spacer" aria-hidden="true"></div>
<?php endif; ?>

<script>
// 写真の切り替え
document.querySelectorAll('.event-thumbs a').forEach(function (a) {
    a.addEventListener('click', function (ev) {
        ev.preventDefault();
        document.getElementById('event-photo-main').src = a.dataset.photo;
        document.querySelectorAll('.event-thumbs a').forEach(function (x) { x.classList.toggle('is-active', x === a); });
    });
});
// リンクのコピー（スマホで共有メニューが使えるときはそちらを出す）
document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var url = btn.dataset.copy;
        if (navigator.share) {
            navigator.share({ title: document.title, url: url }).catch(function () {});
            return;
        }
        (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () {
            btn.textContent = 'コピーしました';
        }, function () { window.prompt('このリンクをコピーしてください', url); });
    });
});
</script>
