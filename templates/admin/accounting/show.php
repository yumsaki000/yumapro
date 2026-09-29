<?php
/** @var array $event */
/** @var array $summary */
/** @var array $expenses */
/** @var list<string> $items */
$eventId = (int) $event['id'];
?>
<section class="card">
    <div class="toolbar">
        <h1>会計：<?= e($event['title']) ?></h1>
        <a class="button button--small" href="/admin/events/<?= $eventId ?>">イベントの詳細</a>
    </div>
    <p class="text-muted"><?= e(fmt_dt($event['starts_at'])) ?>　<?= e(App\Events::PAYMENT_TIMINGS[$event['payment_timing']] ?? '') ?>　参加費 <?= e(yen($event['fee'])) ?></p>
    <div class="stats">
        <div class="stat"><div class="stat__label">収入</div><div class="stat__value"><?= e(yen($summary['income'])) ?></div><div class="text-muted">前払い <?= $summary['prepaid_count'] ?>件 <?= e(yen($summary['income_prepaid'])) ?><br>当日 <?= $summary['onsite_count'] ?>件 <?= e(yen($summary['income_onsite'])) ?></div></div>
        <div class="stat"><div class="stat__label">経費</div><div class="stat__value"><?= e(yen($summary['expense_total'])) ?></div></div>
        <div class="stat"><div class="stat__label">主催分</div><div class="stat__value"><?= e(yen($summary['organizer_amount'])) ?></div></div>
        <div class="stat"><div class="stat__label">収支</div><div class="stat__value"><?= e(yen($summary['balance'])) ?></div></div>
    </div>
</section>

<section class="card">
    <h2>経費</h2>
    <?php if ($expenses === []): ?>
        <p class="text-muted">経費はまだありません。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>項目</th><th class="num">金額</th><th>メモ</th><th>入力者</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($expenses as $x): ?>
                        <tr>
                            <td><?= e($x['item']) ?></td>
                            <td class="num"><?= e(yen($x['amount'])) ?></td>
                            <td class="wrap"><?= e($x['memo'] ?? '') ?></td>
                            <td class="text-muted"><?= e($x['created_by_name'] ?? '—') ?></td>
                            <td>
                                <form class="inline-form" method="post" action="/admin/expenses/<?= (int) $x['id'] ?>/delete" onsubmit="return confirm('この経費を消します。よろしいですか？');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="button button--small button--danger">消す</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2>経費を追加</h2>
    <form method="post" action="/admin/events/<?= $eventId ?>/expenses" class="form">
        <?= csrf_field() ?>
        <div class="form__row">
            <label class="form__field">
                <span class="form__label">項目</span>
                <input type="text" name="item" list="expense-items" maxlength="100" required>
                <datalist id="expense-items">
                    <?php foreach ($items as $item): ?><option value="<?= e($item) ?>"><?php endforeach; ?>
                </datalist>
            </label>
            <label class="form__field">
                <span class="form__label">金額（円）</span>
                <input type="number" name="amount" min="0" inputmode="numeric" required>
            </label>
        </div>
        <label class="form__field">
            <span class="form__label">メモ（任意）</span>
            <input type="text" name="memo" maxlength="255">
        </label>
        <button type="submit" class="button button--primary">追加する</button>
    </form>
</section>

<section class="card">
    <h2>主催分</h2>
    <form method="post" action="/admin/events/<?= $eventId ?>/organizer" class="form">
        <?= csrf_field() ?>
        <label class="form__field">
            <span class="form__label">主催分（円）</span>
            <input type="number" name="organizer_amount" value="<?= (int) $event['organizer_amount'] ?>" min="0" inputmode="numeric">
            <span class="form__help">収支 ＝ 収入 − 経費 − 主催分</span>
        </label>
        <button type="submit" class="button">保存する</button>
    </form>
</section>
