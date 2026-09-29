<?php
/** @var array $registration */
/** @var array $values */
/** @var list<string> $errors */
/** @var array $channels */
$r = $registration;
$rid = (int) $r['id'];
?>
<section class="card">
    <h1>申込の詳細</h1>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <dl class="kv">
        <dt>回</dt><dd><a href="/admin/events/<?= (int) $r['event_id'] ?>"><?= e($r['event_title']) ?></a>　<?= e(fmt_dt($r['event_starts_at'])) ?></dd>
        <dt>申込者</dt><dd><a href="/admin/customers/<?= (int) $r['customer_id'] ?>"><?= e($r['customer_name']) ?></a><?= $r['customer_kana'] !== null ? '（' . e($r['customer_kana']) . '）' : '' ?><?php if ($r['customer_banned_at'] !== null): ?> <span class="badge badge--danger">出禁</span><?php endif; ?></dd>
        <dt>状態</dt><dd><span class="<?= e(App\Registrations::STATUS_BADGES[$r['status']] ?? 'badge') ?>"><?= e(App\Registrations::STATUSES[$r['status']] ?? $r['status']) ?></span></dd>
        <dt>申込元</dt><dd><?= e(App\Registrations::SOURCES[$r['source']] ?? $r['source']) ?>　<?= e(fmt_dt($r['applied_at'])) ?></dd>
        <dt>到着</dt><dd><?= $r['arrived_at'] !== null ? e(fmt_dt($r['arrived_at'])) . ($r['paid_amount'] !== null ? '・当日 ' . e(yen($r['paid_amount'])) : '') : '—' ?></dd>
    </dl>

    <form method="post" action="/admin/registrations/<?= $rid ?>/edit" class="form" style="margin-top: 16px;">
        <?= csrf_field() ?>
        <div class="form__row">
            <label class="form__field">
                <span class="form__label">参加費（円）</span>
                <input type="number" name="fee" value="<?= e((string) $values['fee']) ?>" min="0" inputmode="numeric" required>
            </label>
            <label class="form__field">
                <span class="form__label">どこで知りましたか</span>
                <select name="channel">
                    <option value="">—</option>
                    <?php foreach ($channels as $name): ?>
                        <option value="<?= e($name) ?>"<?= ($values['channel'] ?? '') === $name ? ' selected' : '' ?>><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="form__row">
            <label class="form__check">
                <input type="checkbox" name="prepaid" value="1"<?= $values['prepaid'] ? ' checked' : '' ?>>
                <span>前払いの入金を確認済み<?= $r['prepaid_at'] !== null ? '（' . e(fmt_dt($r['prepaid_at'])) . '）' : '' ?></span>
            </label>
            <label class="form__field">
                <span class="form__label">支払い方法</span>
                <select name="payment_method">
                    <?php foreach (App\Registrations::PAYMENT_METHODS as $code => $label): ?>
                        <option value="<?= e($code) ?>"<?= $values['payment_method'] === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label class="form__field">
            <span class="form__label">メモ（任意）</span>
            <input type="text" name="note" value="<?= e($values['note'] ?? '') ?>" maxlength="255">
        </label>
        <div class="actions">
            <button type="submit" class="button button--primary">保存する</button>
            <a class="button" href="/admin/events/<?= (int) $r['event_id'] ?>">戻る</a>
        </div>
    </form>
</section>

<section class="card">
    <h2>状態を変える</h2>
    <div class="actions">
        <?php if ($r['status'] !== 'cancelled'): ?>
            <form class="inline-form" method="post" action="/admin/registrations/<?= $rid ?>/cancel" onsubmit="return confirm('キャンセルにします。よろしいですか？');">
                <?= csrf_field() ?>
                <button type="submit" class="button button--danger">キャンセルにする</button>
            </form>
        <?php endif; ?>
        <?php if ($r['status'] !== 'applied'): ?>
            <form class="inline-form" method="post" action="/admin/registrations/<?= $rid ?>/restore">
                <?= csrf_field() ?>
                <button type="submit" class="button">申込にする（定員内なら）</button>
            </form>
            <form class="inline-form" method="post" action="/admin/registrations/<?= $rid ?>/restore">
                <?= csrf_field() ?>
                <input type="hidden" name="force_apply" value="1">
                <button type="submit" class="button">定員を超えて申込にする</button>
            </form>
        <?php endif; ?>
    </div>
</section>
