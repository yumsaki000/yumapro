<?php
/**
 * 公式サイトに埋め込む回の一覧（iframe の中身）。ヘッダー・フッターなし。
 * リンクは親のページ（公式サイト）ごと移るように target="_top"。高さは親に知らせる（public/assets/embed.js が受け取る）。
 *
 * @var array $events
 * @var string $moreUrl
 */
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="public embed">
    <?php if ($events === []): ?>
        <p class="text-muted">募集中のイベントは準備中です。公式LINEやInstagramでお知らせします。</p>
    <?php endif; ?>
    <div class="event-grid">
        <?php foreach ($events as $event): ?>
            <?php
            $remaining = App\Applications::remaining($event);
            $accepting = App\Applications::accepting($event);
            $color = App\Events::colorClass($event['type_color'] ?? null);
            $photo = App\Photos::url($event['photo']);
            $href = app_url('/e/' . $event['slug'] . '?from=site');
            ?>
            <a class="card event-card event-card--mini" href="<?= e($href) ?>" target="_top">
                <span class="event-card__photo <?= e(str_replace('tag-type', 'photo', $color)) ?>"<?= $photo !== null ? ' style="background-image:url(\'' . e($photo) . '\')"' : '' ?>>
                    <span class="tag-type <?= e($color) ?>"><?= e($event['type_name']) ?></span>
                </span>
                <span class="event-card__body">
                    <span class="event-card__date"><?= e(fmt_dt($event['starts_at'])) ?></span>
                    <span class="event-card__title"><?= e($event['title']) ?></span>
                    <span class="text-muted small"><?= e(App\Calendar::location($event) ?: '場所は追ってご案内') ?>・<?= e(yen($event['fee'])) ?></span>
                    <span class="event-card__row">
                        <?php if (!$accepting): ?><span class="seats seats--full">受付終了</span>
                        <?php elseif ($remaining === 0): ?><span class="seats seats--full">満席（キャンセル待ち）</span>
                        <?php elseif ($remaining !== null): ?><span class="seats<?= $remaining <= 3 ? ' seats--few' : '' ?>">残り<?= $remaining ?>席</span>
                        <?php else: ?><span class="seats">受付中</span><?php endif; ?>
                        <span class="button button--primary button--small">詳細・申込</span>
                    </span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
    <p class="embed__more"><a class="button" href="<?= e($moreUrl) ?>" target="_top">イベント一覧をすべて見る</a></p>
    <script>
    // 中身の高さを親（公式サイト）に知らせて、iframe の高さを合わせてもらう
    (function () {
        function send() {
            // documentElement.scrollHeight は枠の高さより小さくならないので、body の高さで測る
            var style = getComputedStyle(document.body);
            var height = document.body.getBoundingClientRect().height + parseFloat(style.marginTop) + parseFloat(style.marginBottom);
            parent.postMessage({ type: 'minato-embed-height', height: height }, '*');
        }
        window.addEventListener('load', send);
        window.addEventListener('resize', send);
        if (window.ResizeObserver) { new ResizeObserver(send).observe(document.body); }
        send();
    })();
    </script>
</body>
</html>
