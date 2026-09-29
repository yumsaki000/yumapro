<?php
/** @var array $pending */
/** @var array $paid */
?>
<section class="card">
    <h1>講座の購入</h1>
    <p class="text-muted">購入は事前振込です。振込を確認したら「入金確認済みにする」を押すと、本人に視聴のご案内メールが届き、講座が見られるようになります。</p>
    <h2>入金待ち（<?= count($pending) ?>）</h2>
    <?php if ($pending === []): ?><p class="text-muted">入金待ちはありません。</p><?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>名前</th><th>講座</th><th class="num">金額</th><th>申込</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($pending as $p): ?>
                        <tr>
                            <td><a href="/admin/customers/<?= (int) $p['customer_id'] ?>"><?= e($p['customer_name']) ?></a><br><span class="text-muted"><?= e($p['customer_email'] ?? '—') ?></span></td>
                            <td><a href="/admin/courses/<?= (int) $p['course_id'] ?>"><?= e($p['course_title']) ?></a></td>
                            <td class="num"><?= e(yen($p['amount'])) ?></td>
                            <td><?= e(fmt_dt($p['created_at'])) ?></td>
                            <td>
                                <div class="actions">
                                    <form class="inline-form" method="post" action="/admin/purchases/<?= (int) $p['id'] ?>/paid"><?= csrf_field() ?><select name="payment_method" class="input--short" style="width:auto"><?php foreach (App\Registrations::PAYMENT_METHODS as $code => $label): ?><option value="<?= e($code) ?>"<?= $code === 'bank_transfer' ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select> <button type="submit" class="button button--small button--primary">入金確認済みにする</button></form>
                                    <form class="inline-form" method="post" action="/admin/purchases/<?= (int) $p['id'] ?>/cancel" onsubmit="return confirm('この購入を取り消します。よろしいですか？');"><?= csrf_field() ?><button type="submit" class="button button--small">取り消し</button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <h2>入金確認済み（最近）</h2>
    <?php if ($paid === []): ?><p class="text-muted">まだありません。</p><?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>名前</th><th>講座</th><th class="num">金額</th><th>入金確認</th></tr></thead>
                <tbody>
                    <?php foreach ($paid as $p): ?>
                        <tr><td><a href="/admin/customers/<?= (int) $p['customer_id'] ?>"><?= e($p['customer_name']) ?></a></td><td><?= e($p['course_title']) ?></td><td class="num"><?= e(yen($p['amount'])) ?></td><td><?= e(fmt_dt($p['paid_at'])) ?>（<?= e(App\Registrations::PAYMENT_METHODS[$p['payment_method']] ?? '') ?>）</td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
