<?php
/** @var array{phone: list<array>, email: list<array>, name: list<array>} $groups */
$sections = [
    ['phone', '同じ電話番号', '同じ人の可能性が高い組です。'],
    ['email', '同じメールアドレス', '同じ人の可能性が高い組です。'],
    ['name', '同じ名前', '同姓同名の別人のこともあります。フリガナ・電話・参加履歴を見て判断してください。'],
];
$total = array_sum(array_map('count', $groups));
?>
<section class="card">
    <h1>名寄せ <span class="text-muted"><?= $total ?>組</span></h1>
    <p class="text-muted">同じ人かもしれない顧客の組です。「残す顧客」を選んで「まとめる」を押すと、ほかの人の申込を残す人に付け替え、ほかの人は消えます。空いている項目は消える側の値で埋めます。同じイベントに両方の申込がある組はまとめられません（先にどちらかをキャンセルしてください）。</p>
    <?php if ($total === 0): ?><p>候補はありません。</p><?php endif; ?>
</section>

<?php foreach ($sections as [$key, $heading, $note]): ?>
    <?php if ($groups[$key] === []) { continue; } ?>
    <section class="card">
        <h2><?= e($heading) ?>（<?= count($groups[$key]) ?>組）</h2>
        <p class="text-muted"><?= e($note) ?></p>
        <?php foreach ($groups[$key] as $i => $group): ?>
            <form method="post" action="/admin/customers/merge" class="form" style="margin-bottom: 16px;" onsubmit="return confirm('選んだ顧客にまとめます。よろしいですか？');">
                <?= csrf_field() ?>
                <div class="table-wrap">
                    <table class="table table--compact">
                        <thead><tr><th>残す</th><th>名前</th><th>電話</th><th>メール</th><th>きっかけ</th><th>登録日</th></tr></thead>
                        <tbody>
                            <?php foreach ($group as $j => $c): ?>
                                <tr>
                                    <td><input type="radio" name="into_id" value="<?= (int) $c['id'] ?>"<?= $j === 0 ? ' checked' : '' ?>></td>
                                    <td><a href="/admin/customers/<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a><?= $c['name_kana'] !== null ? '<br><span class="text-muted">' . e($c['name_kana']) . '</span>' : '' ?></td>
                                    <td><?= e($c['phone'] ?? '—') ?></td>
                                    <td><?= e($c['email'] ?? '—') ?></td>
                                    <td><?= e($c['first_channel'] ?? '—') ?></td>
                                    <td><?= e(fmt_dt($c['created_at'], false)) ?></td>
                                </tr>
                                <input type="hidden" name="from_ids[]" value="<?= (int) $c['id'] ?>">
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div><button type="submit" class="button button--small">選んだ顧客にまとめる</button></div>
            </form>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>
