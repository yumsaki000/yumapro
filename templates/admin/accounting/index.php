<?php
/** @var array{year: int, from: string, to: string, label: string} $period */
/** @var list<int> $years */
/** @var array $summary Books::summary() */
/** @var array $rows その年のイベントごとの収支 */
/** @var array $common その年のイベントに付かない経費 */
/** @var array $values */
$year = $period['year'];
$sum = fn (string $key) => array_sum(array_map(fn ($r) => (int) $r[$key], $rows));
$csv = fn (string $kind) => '/admin/accounting.csv?kind=' . $kind . '&year=' . $year;
?>
<section class="card">
    <div class="toolbar">
        <h1>会計 <span class="text-muted"><?= e($period['label']) ?></span></h1>
        <form method="get" action="/admin/accounting" class="inline-form">
            <label>年：
                <select name="year" onchange="this.form.submit()">
                    <?php foreach ($years as $y): ?><option value="<?= $y ?>"<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option><?php endforeach; ?>
                </select>
            </label>
            <noscript><button type="submit" class="button button--small">表示</button></noscript>
        </form>
    </div>
    <div class="stats">
        <div class="stat"><div class="stat__label">売上</div><div class="stat__value"><?= e(yen($summary['sales'])) ?></div><div class="text-muted small">イベント <?= e(yen($summary['sales_event'])) ?><br>講座 <?= e(yen($summary['sales_course'])) ?></div></div>
        <div class="stat"><div class="stat__label">経費</div><div class="stat__value"><?= e(yen($summary['expenses'])) ?></div></div>
        <div class="stat"><div class="stat__label">主催分</div><div class="stat__value"><?= e(yen($summary['organizer'])) ?></div></div>
        <div class="stat"><div class="stat__label">差し引き</div><div class="stat__value"><?= e(yen($summary['balance'])) ?></div></div>
    </div>
    <?php if ($summary['no_receipt'] > 0): ?>
        <p class="warning-box">領収書・レシートの「保管している」に印がない経費が <?= (int) $summary['no_receipt'] ?>件あります。確定申告の前に、手元にあるか確かめて「直す」から印を付けてください。</p>
    <?php endif; ?>

    <h2>確定申告の書き出し</h2>
    <p class="text-muted">この年の分を CSV（Excel・Googleスプレッドシート・会計ソフトで開ける形）で書き出します。税理士さんや会計ソフト（freee・弥生・マネーフォワードなど）に渡すときに使ってください。</p>
    <div class="actions">
        <a class="button button--primary" href="<?= e($csv('summary')) ?>">年間のまとめ（月別・勘定科目別）</a>
        <a class="button" href="<?= e($csv('sales')) ?>">売上の明細</a>
        <a class="button" href="<?= e($csv('expenses')) ?>">経費の明細</a>
        <a class="button" href="<?= e($csv('events')) ?>">イベントごとの収支</a>
    </div>
    <details class="help-box">
        <summary>数え方（売上・経費の日付の決め方）</summary>
        <ul>
            <li>売上：イベントの参加費は<strong>イベントの日</strong>の売上にします（前払いで先に受け取っていても）。前払いは入金を確認したもの、当日払いは受付で受け取った額です。中止のイベントは入れません。</li>
            <li>講座の売上は、入金を確認した日にします。</li>
            <li>経費：<strong>支払った日</strong>で数えます。支払った日が空の経費は、イベントの日にします。</li>
            <li>主催分：経費になるかは中身しだいです（外の人への支払いなら外注費など、運営の取り分なら経費ではない）。明細では「要確認」として別に出しています。</li>
            <li>勘定科目は、選ばなかったときは項目の名前から自動で当てています。最後は税理士さん・会計ソフトで確かめてください。</li>
            <li>年の区切り（1月始まり・4月始まり）は「設定」の「会計」で変えられます。</li>
        </ul>
    </details>
</section>

<section class="card">
    <h2>月ごと</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>月</th><th class="num">売上（イベント）</th><th class="num">売上（講座）</th><th class="num">経費</th><th class="num">主催分</th><th class="num">差し引き</th></tr></thead>
            <tbody>
                <?php foreach ($summary['months'] as $month => $m): ?>
                    <?php $net = $m['sales_event'] + $m['sales_course'] - $m['expenses'] - $m['organizer']; $empty = $m['sales_event'] + $m['sales_course'] + $m['expenses'] + $m['organizer'] === 0; ?>
                    <tr class="<?= $empty ? 'is-muted' : '' ?>">
                        <td><?= e(date('Y年n月', strtotime($month . '-01'))) ?></td>
                        <td class="num"><?= e(yen($m['sales_event'])) ?></td>
                        <td class="num"><?= e(yen($m['sales_course'])) ?></td>
                        <td class="num"><?= e(yen($m['expenses'])) ?></td>
                        <td class="num"><?= e(yen($m['organizer'])) ?></td>
                        <td class="num"><strong><?= e(yen($net)) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><th>合計</th><th class="num"><?= e(yen($summary['sales_event'])) ?></th><th class="num"><?= e(yen($summary['sales_course'])) ?></th><th class="num"><?= e(yen($summary['expenses'])) ?></th><th class="num"><?= e(yen($summary['organizer'])) ?></th><th class="num"><?= e(yen($summary['balance'])) ?></th></tr>
            </tfoot>
        </table>
    </div>

    <h2>勘定科目ごとの経費</h2>
    <?php if ($summary['accounts'] === []): ?>
        <p class="text-muted">この年の経費はまだありません。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>勘定科目</th><th class="num">金額</th></tr></thead>
                <tbody>
                    <?php foreach ($summary['accounts'] as $account => $amount): ?>
                        <tr><td><?= e($account) ?> <span class="text-muted small"><?= e(App\Expenses::ACCOUNTS[$account] ?? '') ?></span></td><td class="num"><?= e(yen($amount)) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th>合計</th><th class="num"><?= e(yen($summary['expenses'])) ?></th></tr></tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2>イベントごとの収支</h2>
    <p class="text-muted">収入 ＝ 前払いの入金確認済みの参加費 ＋ 当日受付の入金。収支 ＝ 収入 − 経費 − 主催分。中止のイベントは除いています。経費はイベント名から入れます。</p>
    <?php if ($rows === []): ?><p>この年のイベントはありません。</p><?php endif; ?>
    <?php if ($rows !== []): ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>イベント</th><th>日付</th><th class="num">収入</th><th class="num">経費</th><th class="num">主催分</th><th class="num">収支</th></tr></thead>
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
                <tfoot>
                    <tr><th>合計</th><th></th><th class="num"><?= e(yen($sum('income'))) ?></th><th class="num"><?= e(yen($sum('expense_total'))) ?></th><th class="num"><?= e(yen($sum('organizer_amount'))) ?></th><th class="num"><?= e(yen($sum('balance'))) ?></th></tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card" id="common">
    <h2>イベントに付かない経費</h2>
    <p class="text-muted">サーバー代・ドメイン代・公式LINEの料金・広告費・振込手数料など、どのイベントのものとも言えない経費です。</p>
    <?php if ($common !== []): ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>支払った日</th><th>項目</th><th>勘定科目</th><th class="num">金額</th><th>領収書</th><th>支払先・メモ</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($common as $x): ?>
                        <tr>
                            <td><?= e(fmt_dt($x['spent_on'], false)) ?></td>
                            <td><?= e($x['item']) ?></td>
                            <td><?= e($x['account'] ?? App\Expenses::guessAccount((string) $x['item'])) ?></td>
                            <td class="num"><?= e(yen($x['amount'])) ?></td>
                            <td><?= $x['has_receipt'] ? '<span class="badge badge--ok">あり</span>' : '<span class="badge badge--warn">なし</span>' ?></td>
                            <td class="wrap"><?= e(implode('　', array_filter([$x['payee'] ?? '', $x['memo'] ?? '']))) ?></td>
                            <td>
                                <div class="actions">
                                    <a class="button button--small" href="/admin/expenses/<?= (int) $x['id'] ?>/edit">直す</a>
                                    <form class="inline-form" method="post" action="/admin/expenses/<?= (int) $x['id'] ?>/delete" onsubmit="return confirm('この経費を消します。よろしいですか？');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="button button--small button--danger">消す</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <form method="post" action="/admin/accounting/expenses" class="form" style="margin-top: 12px;">
        <?= csrf_field() ?>
        <?= App\View::render('admin/accounting/_expense_fields', ['values' => $values, 'items' => ['サーバー代', 'ドメイン代', '公式LINEの料金', 'SNS広告', '振込手数料'], 'needsDate' => true, 'prefix' => 'common'], null) ?>
        <div><button type="submit" class="button button--primary">追加する</button></div>
    </form>
</section>
