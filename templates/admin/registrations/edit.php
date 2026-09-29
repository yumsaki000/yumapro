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
        <dt>イベント</dt><dd><a href="/admin/events/<?= (int) $r['event_id'] ?>"><?= e($r['event_title']) ?></a>　<?= e(fmt_dt($r['event_starts_at'])) ?></dd>
        <dt>申込者</dt><dd><a href="/admin/customers/<?= (int) $r['customer_id'] ?>"><?= e($r['customer_name']) ?></a><?= $r['customer_kana'] !== null ? '（' . e($r['customer_kana']) . '）' : '' ?><?php if ($r['customer_banned_at'] !== null): ?> <span class="badge badge--danger">出禁</span><?php endif; ?></dd>
        <dt>状態</dt><dd><span class="<?= e(App\Registrations::STATUS_BADGES[$r['status']] ?? 'badge') ?>"><?= e(App\Registrations::STATUSES[$r['status']] ?? $r['status']) ?></span></dd>
        <dt>申込元</dt><dd><?= e(App\Registrations::SOURCES[$r['source']] ?? $r['source']) ?>　<?= e(fmt_dt($r['applied_at'])) ?></dd>
        <dt>到着</dt><dd><?= $r['arrived_at'] !== null ? e(fmt_dt($r['arrived_at'])) . ($r['paid_amount'] !== null ? '・当日 ' . e(yen($r['paid_amount'])) : '') : '—' ?></dd>
        <?php if ($r['entry_from'] !== null): ?><dt>窓口</dt><dd><?= e(App\Stats::ENTRY_KEYS[$r['entry_from']] ?? $r['entry_from']) ?></dd><?php endif; ?>
        <?php if ($r['ban_check'] !== 'none'): ?><dt>出禁チェック</dt><dd><span class="<?= e(App\Bans::CHECK_LABELS[$r['ban_check']][0] ?? 'badge') ?>"><?= e(App\Bans::CHECK_LABELS[$r['ban_check']][1] ?? $r['ban_check']) ?></span></dd><?php endif; ?>
        <?php if ($r['consented_at'] !== null): ?><dt>同意</dt><dd>注意事項などに同意（<?= e(fmt_dt($r['consented_at'])) ?>）</dd><?php endif; ?>
        <?php $answers = $r['answers'] !== null ? json_decode($r['answers'], true) : null; ?>
        <?php if (is_array($answers) && $answers !== []): ?>
            <?php $labels = ['submitted_name' => '申込時に入力した名前（台帳と違う）', 'referrer' => '紹介者', 'message' => '意気込み', 'questions' => '質問・不安']; ?>
            <?php foreach ($answers as $key => $value): ?>
                <dt><?= e($labels[$key] ?? (string) $key) ?></dt><dd><pre class="plain"><?= e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)) ?></pre></dd>
            <?php endforeach; ?>
        <?php endif; ?>
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

<?php if (in_array($r['ban_check'], ['suspect', 'confirmed'], true)): ?>
    <section class="card">
        <h2>出禁チェックの確認</h2>
        <?php if ($r['ban_check'] === 'suspect'): ?>
            <p>この申込の名前が、出禁リストの人と同じです（連絡先は一致していません）。見比べて、別人なら印を消し、同じ人なら出禁にしてキャンセルしてください。</p>
            <?php $same = App\Bans::sameNameBanned($r['customer_name'], (int) $r['customer_id']); ?>
            <?php if ($same !== []): ?>
                <div class="table-wrap">
                    <table class="table table--compact">
                        <thead><tr><th></th><th>名前</th><th>フリガナ</th><th>電話</th><th>メール</th><th>SNS</th><th class="wrap">理由</th></tr></thead>
                        <tbody>
                            <tr><td>今回の申込</td><td><?= e($r['customer_name']) ?></td><td><?= e($r['customer_kana'] ?? '—') ?></td><td><?= e($r['customer_phone'] ?? '—') ?></td><td><?= e($r['customer_email'] ?? '—') ?></td><td>—</td><td></td></tr>
                            <?php foreach ($same as $b): ?>
                                <tr class="is-muted"><td>出禁リスト</td><td><a href="/admin/customers/<?= (int) $b['id'] ?>"><?= e($b['name']) ?></a></td><td><?= e($b['name_kana'] ?? '—') ?></td><td><?= e($b['phone'] ?? '—') ?></td><td><?= e($b['email'] ?? '—') ?></td><td><?= e($b['sns_account'] ?? '—') ?></td><td class="wrap"><?= e($b['ban_reason'] ?? '—') ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <p>この申込の連絡先が、出禁リストの人と一致しています。申込はキャンセル待ちに止めてあり、本人には普通のキャンセル待ちの案内だけ届いています。</p>
        <?php endif; ?>
        <div class="actions">
            <form class="inline-form" method="post" action="/admin/registrations/<?= $rid ?>/ban-check">
                <?= csrf_field() ?><input type="hidden" name="action" value="clear">
                <button type="submit" class="button">別人だった（印を消す）</button>
            </form>
            <form class="inline-form" method="post" action="/admin/registrations/<?= $rid ?>/ban-check" onsubmit="return confirm('この人を出禁にして、申込をキャンセルにします。よろしいですか？');">
                <?= csrf_field() ?><input type="hidden" name="action" value="confirm">
                <button type="submit" class="button button--danger">同じ人だった（出禁にしてキャンセル）</button>
            </form>
        </div>
    </section>
<?php endif; ?>

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
