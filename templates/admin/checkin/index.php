<?php
/** @var array $event */
/** @var string $q */
/** @var array $rows */
/** @var int $total */
/** @var int $arrived */
/** @var int $onsiteTotal */
$eventId = (int) $event['id'];
$back = '/admin/events/' . $eventId . '/checkin' . ($q !== '' ? '?q=' . rawurlencode($q) : '');
?>
<section class="card">
    <div class="toolbar">
        <h1>当日受付</h1>
        <a class="button button--small" href="/admin/events/<?= $eventId ?>">イベントの詳細</a>
    </div>
    <p class="text-muted"><?= e($event['title']) ?>　<?= e(fmt_dt($event['starts_at'])) ?>　<?= e(App\Events::PAYMENT_TIMINGS[$event['payment_timing']] ?? '') ?></p>
    <div class="stats">
        <div class="stat"><div class="stat__label">到着</div><div class="stat__value"><?= $arrived ?><span class="text-muted"> / <?= $total ?></span></div></div>
        <div class="stat"><div class="stat__label">当日の入金</div><div class="stat__value"><?= e(yen($onsiteTotal)) ?></div></div>
    </div>
    <form method="get" action="/admin/events/<?= $eventId ?>/checkin" class="searchbar">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="名前で絞り込む">
        <button type="submit" class="button">絞り込む</button>
        <?php if ($q !== ''): ?><a class="button" href="/admin/events/<?= $eventId ?>/checkin">全員</a><?php endif; ?>
    </form>

    <?php if ($rows === []): ?>
        <p class="text-muted"><?= $q === '' ? '申込がありません。' : '当てはまる人がいません。' ?></p>
    <?php endif; ?>
    <ul class="list">
        <?php foreach ($rows as $r): ?>
            <?php $rid = (int) $r['id']; $done = $r['arrived_at'] !== null; ?>
            <li class="list__item<?= $done ? ' list__item--done' : '' ?>" id="r<?= $rid ?>">
                <div class="list__main">
                    <div class="list__title">
                        <?= e($r['customer_name']) ?>
                        <?php if ($r['customer_gender'] !== null): ?><span class="badge"><?= e(App\Customers::GENDERS[$r['customer_gender']]) ?></span><?php endif; ?>
                        <?php if ($r['status'] === 'waitlisted'): ?><span class="badge badge--warn">キャンセル待ち</span><?php endif; ?>
                        <?php if ($r['customer_banned_at'] !== null): ?><span class="badge badge--danger">出禁</span><?php endif; ?>
                    </div>
                    <div class="list__sub">
                        <?= $r['customer_kana'] !== null ? e($r['customer_kana']) . '・' : '' ?>参加費 <?= e(yen($r['fee'])) ?>・<?= $r['prepaid_at'] !== null ? '前払い済' : ($event['payment_timing'] === 'onsite' ? '当日払い' : '<span class="badge badge--warn">未入金</span>') ?>
                        <?php if ($r['note'] !== null): ?>・<?= e($r['note']) ?><?php endif; ?>
                    </div>
                </div>
                <?php if (!$done): ?>
                    <form method="post" action="/admin/registrations/<?= $rid ?>/checkin" class="actions">
                        <?= csrf_field() ?>
                        <input type="hidden" name="back" value="<?= e($back) ?>">
                        <?php if ($r['prepaid_at'] === null): ?>
                            <label>
                                <input class="input--short" type="number" name="paid_amount" value="<?= (int) $r['fee'] ?>" min="0" inputmode="numeric"> 円
                            </label>
                            <select name="payment_method" class="input--short" style="width: auto;">
                                <?php foreach (App\Registrations::PAYMENT_METHODS as $code => $label): ?>
                                    <option value="<?= e($code) ?>"<?= $code === 'cash' ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <button type="submit" class="button button--accent">到着</button>
                    </form>
                <?php else: ?>
                    <span class="badge badge--ok">到着 <?= e(date('H:i', strtotime($r['arrived_at']))) ?></span>
                    <?php if ($r['paid_amount'] !== null): ?><span><?= e(yen($r['paid_amount'])) ?>（<?= e(App\Registrations::PAYMENT_METHODS[$r['checkin_payment_method']] ?? '') ?>）</span><?php endif; ?>
                    <form method="post" action="/admin/registrations/<?= $rid ?>/checkin/undo" class="inline-form" onsubmit="return confirm('到着を取り消します。よろしいですか？');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="back" value="<?= e($back) ?>">
                        <button type="submit" class="button button--small">取り消し</button>
                    </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="actions" style="margin-top: 16px;">
        <a class="button" href="/admin/events/<?= $eventId ?>/registrations/new">飛び入りの人を追加</a>
    </div>
</section>
