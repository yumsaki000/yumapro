<?php
/** @var bool $enabled */
/** @var array $follows */
/** @var array $types */
/** @var array<int, int> $counts 形式ごとの受け取る人数 */
$state = function (array $f): array {
    if ($f['unsubscribed_at'] !== null) {
        return ['badge', '停止'];
    }
    return $f['confirmed_at'] !== null ? ['badge badge--ok', '受け取る'] : ['badge badge--warn', '確認待ち'];
};
?>
<section class="card">
    <h1>次回のお知らせの登録者</h1>
    <?php if (!$enabled): ?>
        <p class="alert alert--info">いまは登録欄を出していません。使うときは「設定」の「使う機能」で「次回のお知らせ」の登録を出すにチェックを入れてください。</p>
    <?php endif; ?>
    <p class="text-muted">掲示板の登録欄から、形式ごとに登録した人です。確認メールのリンクを開いた人（受け取る）にだけ、イベント詳細の「お知らせを送る」でメールが届きます。</p>
    <div class="stats">
        <?php foreach ($types as $type): ?>
            <div class="stat"><div class="stat__label"><?= e($type['name']) ?></div><div class="stat__value"><?= (int) ($counts[(int) $type['id']] ?? 0) ?><span class="text-muted">人</span></div></div>
        <?php endforeach; ?>
    </div>
    <?php if ($follows === []): ?>
        <p class="text-muted">まだ登録はありません。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>メールアドレス</th><th>形式</th><th>状態</th><th>登録日</th></tr></thead>
                <tbody>
                    <?php foreach ($follows as $f): ?>
                        <?php [$badge, $label] = $state($f); ?>
                        <tr class="<?= $f['unsubscribed_at'] !== null ? 'is-muted' : '' ?>">
                            <td class="wrap"><?= e($f['email']) ?></td>
                            <td class="wrap"><?= e($f['type_names'] ?? '—') ?></td>
                            <td><span class="<?= e($badge) ?>"><?= e($label) ?></span></td>
                            <td><?= e(fmt_dt($f['created_at'], false)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
