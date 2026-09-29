<?php
/** @var array $event */
/** @var array|null $customer */
/** @var array|null $existing */
/** @var array $values */
/** @var array $customerValues */
/** @var list<string> $errors */
/** @var array $candidates */
/** @var bool $full */
/** @var array $channels */
/** @var array $customerChannels */
$eventId = (int) $event['id'];
?>
<section class="card">
    <h1>申込を追加</h1>
    <p class="text-muted"><?= e($event['title']) ?>　<?= e(fmt_dt($event['starts_at'])) ?>　参加費 <?= e(yen($event['fee'])) ?>　<?= e(App\Events::PAYMENT_TIMINGS[$event['payment_timing']] ?? '') ?></p>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="/admin/events/<?= $eventId ?>/registrations/new" class="form" autocomplete="off">
        <?= csrf_field() ?>
        <?php if ($customer !== null): ?>
            <input type="hidden" name="customer_id" value="<?= (int) $customer['id'] ?>">
            <fieldset>
                <legend>申し込む人</legend>
                <strong><?= e($customer['name']) ?></strong><?= $customer['name_kana'] !== null ? '（' . e($customer['name_kana']) . '）' : '' ?>
                <?php if ($customer['banned_at'] !== null): ?><span class="badge badge--danger">出禁</span><?php endif; ?>
                <div class="text-muted"><?= e(App\Customers::GENDERS[$customer['gender']] ?? '性別未設定') ?>・電話 <?= e($customer['phone'] ?? '—') ?>・メール <?= e($customer['email'] ?? '—') ?>　<a href="/admin/customers/<?= (int) $customer['id'] ?>" target="_blank">顧客の詳細</a></div>
                <?php if ($existing !== null): ?>
                    <p class="alert alert--error">この人はこの回にもう申し込んでいます（<?= e(App\Registrations::STATUSES[$existing['status']] ?? $existing['status']) ?>）。<a href="/admin/registrations/<?= (int) $existing['id'] ?>/edit">申込の詳細へ</a></p>
                <?php elseif ($customer['banned_at'] !== null): ?>
                    <div class="warning-box">
                        <strong>出禁の人です</strong><?= $customer['ban_reason'] !== null ? '：' . e($customer['ban_reason']) : '' ?>（<?= e(fmt_dt($customer['banned_at'], false)) ?>）
                        <label class="form__check"><input type="checkbox" name="confirm_ban" value="1"> <span>出禁を確認したうえで申し込む</span></label>
                    </div>
                <?php endif; ?>
            </fieldset>
        <?php else: ?>
            <input type="hidden" name="new" value="1">
            <fieldset>
                <legend>新しい顧客</legend>
                <div class="form">
                    <?php $values_backup = $values; $values = $customerValues; $channels_backup = $channels; $channels = $customerChannels; ?>
                    <?php require APP_ROOT . '/templates/admin/customers/_fields.php'; ?>
                    <?php $values = $values_backup; $channels = $channels_backup; ?>
                    <?php if ($candidates !== []): ?>
                        <div class="warning-box">
                            <strong>同じ人かもしれない顧客がいます</strong>
                            <ul>
                                <?php foreach ($candidates as $c): ?>
                                    <li><a href="/admin/events/<?= $eventId ?>/registrations/new?customer_id=<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a><?= $c['name_kana'] !== null ? '（' . e($c['name_kana']) . '）' : '' ?>　電話 <?= e($c['phone'] ?? '—') ?>　メール <?= e($c['email'] ?? '—') ?><?php if ($c['banned_at'] !== null): ?> <span class="badge badge--danger">出禁</span><?php endif; ?>　← この人ならここを押す</li>
                                <?php endforeach; ?>
                            </ul>
                            <label class="form__check"><input type="checkbox" name="confirm_duplicate" value="1"> <span>別の人として登録する</span></label>
                        </div>
                    <?php endif; ?>
                </div>
            </fieldset>
        <?php endif; ?>

        <?php if ($existing === null): ?>
            <fieldset>
                <legend>申込の内容</legend>
                <div class="form">
                    <div class="form__row">
                        <label class="form__field">
                            <span class="form__label">参加費（円）</span>
                            <input type="number" name="fee" value="<?= e((string) $values['fee']) ?>" min="0" inputmode="numeric" required>
                            <span class="form__help">クルー割引などがあればここで変えます</span>
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
                    <?php if ($event['payment_timing'] === 'prepaid'): ?>
                        <div class="form__row">
                            <label class="form__check">
                                <input type="checkbox" name="prepaid" value="1"<?= $values['prepaid'] ? ' checked' : '' ?>>
                                <span>入金を確認済み</span>
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
                    <?php endif; ?>
                    <label class="form__field">
                        <span class="form__label">メモ（任意）</span>
                        <input type="text" name="note" value="<?= e($values['note'] ?? '') ?>" maxlength="255">
                    </label>
                    <?php if ($full): ?>
                        <div class="warning-box">
                            <strong>定員に達しています。</strong>このまま追加するとキャンセル待ちになります。
                            <label class="form__check"><input type="checkbox" name="force_apply" value="1"> <span>定員を超えて申込にする</span></label>
                        </div>
                    <?php endif; ?>
                </div>
            </fieldset>
            <div class="actions">
                <button type="submit" class="button button--primary">申込を追加する</button>
                <a class="button" href="/admin/events/<?= $eventId ?>/registrations/new">戻る</a>
            </div>
        <?php else: ?>
            <div class="actions"><a class="button" href="/admin/events/<?= $eventId ?>/registrations/new">戻る</a></div>
        <?php endif; ?>
    </form>
</section>
