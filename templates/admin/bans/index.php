<?php
/** @var string $q */
/** @var array $banned */
/** @var int $total */
/** @var array $pending 確認待ちの申込 */
?>
<?php if ($pending !== []): ?>
    <section class="card">
        <h2>確認待ちの申込（<?= count($pending) ?>件）</h2>
        <p class="text-muted">申込フォームの出禁チェックで印が付いた申込です。申込の詳細を開いて、「別人だった」か「同じ人だった」かを決めてください。</p>
        <ul class="list">
            <?php foreach ($pending as $r): ?>
                <li class="list__item">
                    <div class="list__main">
                        <a class="list__title" href="/admin/registrations/<?= (int) $r['id'] ?>/edit"><?= e($r['customer_name']) ?></a>
                        <span class="<?= e(App\Bans::CHECK_LABELS[$r['ban_check']][0] ?? 'badge') ?>"><?= e(App\Bans::CHECK_LABELS[$r['ban_check']][1] ?? $r['ban_check']) ?></span>
                        <span class="<?= e(App\Registrations::STATUS_BADGES[$r['status']] ?? 'badge') ?>"><?= e(App\Registrations::STATUSES[$r['status']] ?? $r['status']) ?></span>
                        <div class="list__sub"><?= e($r['event_title']) ?>（<?= e(fmt_dt($r['event_starts_at'], false)) ?>）・申込 <?= e(fmt_dt($r['applied_at'])) ?>・電話 <?= e($r['customer_phone'] ?? '—') ?></div>
                    </div>
                    <a class="button button--small" href="/admin/registrations/<?= (int) $r['id'] ?>/edit">確認する</a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="card">
    <div class="toolbar">
        <h1>出禁リスト <span class="text-muted"><?= $total ?>人</span></h1>
        <a class="button button--primary" href="/admin/bans/new">出禁に登録</a>
    </div>
    <p class="text-muted">ここにいる人は、申込フォームで電話・メール・SNSが一致すると自動でキャンセル待ちに止まり、運営に印が付きます。名前だけ一致した申込は「要確認」になります。手入力のときは警告が出ます。</p>
    <form method="get" action="/admin/bans" class="searchbar">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="名前・電話番号・メール・SNS">
        <button type="submit" class="button">検索</button>
        <?php if ($q !== ''): ?><a class="button" href="/admin/bans">全員</a><?php endif; ?>
    </form>
    <?php if ($banned === []): ?>
        <p class="text-muted"><?= $q === '' ? '出禁の人はいません。' : '当てはまる人がいません。' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>名前</th><th>電話</th><th>メール</th><th>SNS</th><th class="wrap">理由</th><th>登録</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($banned as $c): ?>
                        <tr>
                            <td><a href="/admin/customers/<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a><?php if ($c['name_kana'] !== null): ?><br><span class="text-muted"><?= e($c['name_kana']) ?></span><?php endif; ?></td>
                            <td><?= e($c['phone'] ?? '—') ?></td>
                            <td><?= e($c['email'] ?? '—') ?></td>
                            <td><?= e($c['sns_account'] ?? '—') ?></td>
                            <td class="wrap"><?= e($c['ban_reason'] ?? '—') ?><?php if ($c['ban_note'] !== null): ?><br><span class="text-muted small"><?= nl2br(e(mb_strimwidth($c['ban_note'], 0, 120, '…'))) ?></span><?php endif; ?></td>
                            <td><?= e(fmt_dt($c['banned_at'], false)) ?><?php if ($c['banned_by_name'] !== null): ?><br><span class="text-muted"><?= e($c['banned_by_name']) ?></span><?php endif; ?></td>
                            <td>
                                <form class="inline-form" method="post" action="/admin/customers/<?= (int) $c['id'] ?>/ban" onsubmit="return confirm('「<?= e($c['name']) ?>」の出禁を解除します。よろしいですか？');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="unban">
                                    <input type="hidden" name="back" value="/admin/bans">
                                    <button type="submit" class="button button--small">解除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
