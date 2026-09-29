<?php
/** @var array $rows */
$sum = fn (string $key) => array_sum(array_map(fn ($r) => (int) $r[$key], $rows));
?>
<section class="card">
    <h1>会計</h1>
    <p class="text-muted">収入 ＝ 前払いの入金確認済みの参加費 ＋ 当日受付の入金。収支 ＝ 収入 − 経費 − 主催分。中止の回は除いています。</p>
    <?php if ($rows === []): ?><p>回がまだありません。</p><?php endif; ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>回</th><th>日付</th><th class="num">収入</th><th class="num">経費</th><th class="num">主催分</th><th class="num">収支</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><a href="/admin/events/<?= (int) $r['id'] ?>/accounting"><?= e($r['title']) ?></a> <span class="<?= e(App\Events::STATUS_BADGES[$r['status']] ?? 'badge') ?>"><?= e(App\Events::STATUSES[$r['status']] ?? $r['status']) ?></span></td>
                        <td><?= e(fmt_dt($r['starts_at'], false)) ?></td>
                        <td class="num"><?= e(yen($r['income'])) ?></td>
                        <td class="num"><?= e(yen($r['expense_total'])) ?></td>
                        <td class="num"><?= e(yen($r['organizer_amount'])) ?></td>
                        <td class="num"><strong><?= e(yen($r['balance'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($rows !== []): ?>
                <tfoot>
                    <tr><th>合計</th><th></th><th class="num"><?= e(yen($sum('income'))) ?></th><th class="num"><?= e(yen($sum('expense_total'))) ?></th><th class="num"><?= e(yen($sum('organizer_amount'))) ?></th><th class="num"><?= e(yen($sum('balance'))) ?></th></tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</section>
