<?php
/** @var array $events */
/** @var array $monthly */
/** @var array $referrers */
$pairs = fn (array $counts) => implode('、', array_map(fn ($k, $v) => "{$k} {$v}", array_keys($counts), $counts));
?>
<section class="card">
    <h1>集計</h1>
    <p class="text-muted">「新規」はそのイベントより前に申込がない人、「リピート」は前にも申込がある人です（キャンセルは数えません）。「窓口」は申込フォームへのリンクの ?from= の値、「知った経路」は本人が選んだ回答です。</p>

    <h2>月ごと（開催月・申込ベース）</h2>
    <?php if ($monthly === []): ?><p class="text-muted">まだ申込がありません。</p><?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>月</th><th class="num">イベント数</th><th class="num">申込</th><th class="num">新規</th><th class="num">リピート</th><th class="num">女性</th></tr></thead>
                <tbody>
                    <?php foreach ($monthly as $m): ?>
                        <tr>
                            <td><?= e($m['month']) ?></td>
                            <td class="num"><?= $m['events'] ?></td>
                            <td class="num"><?= $m['applied'] ?></td>
                            <td class="num"><?= $m['new'] ?></td>
                            <td class="num"><?= $m['repeat'] ?></td>
                            <td class="num"><?= $m['female'] ?><?= $m['applied'] > 0 ? ' <span class="text-muted">(' . round($m['female'] * 100 / $m['applied']) . '%)</span>' : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($referrers !== []): ?>
    <section class="card">
        <h2>友だち招待（紹介の多い人）</h2>
        <p class="text-muted">マイページの招待リンクから、友だちに申し込んでもらった人数です（キャンセルは数えません）。</p>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>紹介した人</th><th class="num">申込</th><th>最後の申込</th></tr></thead>
                <tbody>
                    <?php foreach ($referrers as $ref): ?>
                        <tr><td><a href="/admin/customers/<?= $ref['id'] ?>"><?= e($ref['name']) ?></a></td><td class="num"><?= $ref['count'] ?></td><td><?= e(fmt_dt($ref['last'], false)) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<section class="card">
    <h2>イベントごと（新しい順・30件まで）</h2>
    <?php if ($events === []): ?><p class="text-muted">イベントがまだありません。</p><?php endif; ?>
    <?php foreach ($events as $ev): ?>
        <div class="stat-row">
            <div class="list__title"><a href="/admin/events/<?= (int) $ev['id'] ?>"><?= e($ev['title']) ?></a> <span class="text-muted"><?= e(fmt_dt($ev['starts_at'], false)) ?></span></div>
            <div class="stats">
                <div class="stat"><div class="stat__label">申込</div><div class="stat__value"><?= $ev['applied'] ?><?= $ev['capacity'] !== null ? '<span class="text-muted"> / ' . (int) $ev['capacity'] . '</span>' : '' ?></div></div>
                <div class="stat"><div class="stat__label">新規／リピート</div><div class="stat__value"><?= $ev['new'] ?> / <?= $ev['repeat'] ?></div></div>
                <div class="stat"><div class="stat__label">女性／男性</div><div class="stat__value"><?= $ev['female'] ?> / <?= $ev['male'] ?></div></div>
                <div class="stat"><div class="stat__label">キャンセル待ち</div><div class="stat__value"><?= $ev['waitlisted'] ?></div></div>
            </div>
            <dl class="kv">
                <dt>窓口</dt><dd><?= $ev['by_from'] === [] ? '—' : e($pairs($ev['by_from'])) ?></dd>
                <dt>知った経路</dt><dd><?= $ev['by_channel'] === [] ? '—' : e($pairs($ev['by_channel'])) ?></dd>
                <dt>申込元</dt><dd><?= $ev['by_source'] === [] ? '—' : e($pairs($ev['by_source'])) ?></dd>
            </dl>
        </div>
    <?php endforeach; ?>
</section>
