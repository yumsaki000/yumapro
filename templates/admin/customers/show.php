<?php
/** @var array $customer */
/** @var array $history */
/** @var array $candidates */
/** @var array $legacy */
$id = (int) $customer['id'];
$optedIn = App\Customers::isMailOptedIn($customer);
?>
<section class="card">
    <div class="toolbar">
        <h1><?= e($customer['name']) ?><?php if ($customer['name_kana'] !== null): ?> <span class="text-muted"><?= e($customer['name_kana']) ?></span><?php endif; ?></h1>
        <span>
            <?php if ($customer['banned_at'] !== null): ?><span class="badge badge--danger">出禁</span><?php endif; ?>
            <?php if ($optedIn): ?><span class="badge badge--ok">案内メール同意</span><?php endif; ?>
        </span>
    </div>
    <dl class="kv">
        <dt>性別</dt><dd><?= e(App\Customers::GENDERS[$customer['gender']] ?? '—') ?></dd>
        <dt>電話</dt><dd><?= $customer['phone'] !== null ? '<a href="tel:' . e($customer['phone']) . '">' . e($customer['phone']) . '</a>' : '—' ?></dd>
        <dt>メール</dt><dd><?= e($customer['email'] ?? '—') ?></dd>
        <dt>SNS</dt><dd><?= e($customer['sns_account'] ?? '—') ?></dd>
        <dt>LINE名</dt><dd><?= e($customer['line_name'] ?? '—') ?></dd>
        <dt>きっかけ</dt><dd><?= e($customer['first_channel'] ?? '—') ?></dd>
        <dt>案内メール</dt><dd><?= $optedIn ? '同意（' . e(fmt_dt($customer['mail_opt_in_at'])) . '）' : ($customer['mail_opt_out_at'] !== null ? '停止（' . e(fmt_dt($customer['mail_opt_out_at'])) . '）' : '未同意') ?></dd>
        <?php if ($customer['banned_at'] !== null): ?>
            <dt>出禁</dt><dd><?= e(fmt_dt($customer['banned_at'])) ?><?= $customer['ban_reason'] !== null ? '：' . e($customer['ban_reason']) : '' ?><?php if ($customer['ban_note'] !== null): ?><br><pre class="plain text-muted"><?= e($customer['ban_note']) ?></pre><?php endif; ?></dd>
        <?php endif; ?>
        <dt>クルー</dt><dd><span class="<?= e(App\Crew::STATUS_BADGES[$customer['crew_status']] ?? 'badge') ?>"><?= e(App\Crew::STATUSES[$customer['crew_status']] ?? '') ?></span><?= $customer['crew_joined_at'] !== null ? '　加入 ' . e($customer['crew_joined_at']) : '' ?><?= $customer['crew_left_at'] !== null ? '　脱退 ' . e($customer['crew_left_at']) : '' ?><?= $customer['crew_note'] !== null ? '<br><span class="text-muted">' . e($customer['crew_note']) . '</span>' : '' ?></dd>
        <?php if ($customer['legacy_no'] !== null): ?><dt>移行元の番号</dt><dd><?= (int) $customer['legacy_no'] ?></dd><?php endif; ?>
        <dt>登録日</dt><dd><?= e(fmt_dt($customer['created_at'], false)) ?></dd>
        <?php if ($customer['note'] !== null): ?><dt>運営メモ</dt><dd><pre class="plain"><?= e($customer['note']) ?></pre></dd><?php endif; ?>
    </dl>
    <div class="actions" style="margin-top: 12px;">
        <a class="button button--primary" href="/admin/customers/<?= $id ?>/edit">編集</a>
        <a class="button" href="/admin/customers">一覧へ</a>
    </div>
</section>

<section class="card">
    <h2>参加履歴（<?= count($history) ?>回）</h2>
    <?php if ($history === []): ?>
        <p class="text-muted">まだ申込がありません。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>イベント</th><th>状態</th><th class="num">参加費</th><th>入金</th><th>到着</th></tr></thead>
                <tbody>
                    <?php foreach ($history as $r): ?>
                        <tr class="<?= $r['status'] === 'cancelled' ? 'is-muted' : '' ?>">
                            <td><a href="/admin/events/<?= (int) $r['event_id'] ?>"><?= e($r['event_title']) ?></a><br><span class="text-muted"><?= e(fmt_dt($r['event_starts_at'])) ?></span></td>
                            <td><span class="<?= e(App\Registrations::STATUS_BADGES[$r['status']] ?? 'badge') ?>"><?= e(App\Registrations::STATUSES[$r['status']] ?? $r['status']) ?></span></td>
                            <td class="num"><?= e(yen($r['fee'])) ?></td>
                            <td><?= $r['prepaid_at'] !== null ? '前払い済' : ($r['paid_amount'] !== null ? '当日 ' . e(yen($r['paid_amount'])) : '—') ?></td>
                            <td><?= $r['arrived_at'] !== null ? e(fmt_dt($r['arrived_at'])) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($candidates !== []): ?>
    <section class="card">
        <h2>同じ人かもしれない顧客</h2>
        <p class="text-muted">連絡先か名前が同じ顧客です。同じ人なら、こちらにまとめます（相手の申込をこの人に付け替え、相手は消えます。空いている項目は相手の値で埋めます）。</p>
        <ul class="list">
            <?php foreach ($candidates as $c): ?>
                <li class="list__item">
                    <div class="list__main">
                        <a class="list__title" href="/admin/customers/<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a><?= $c['name_kana'] !== null ? ' <span class="text-muted">' . e($c['name_kana']) . '</span>' : '' ?>
                        <div class="list__sub">電話 <?= e($c['phone'] ?? '—') ?>　メール <?= e($c['email'] ?? '—') ?>　SNS <?= e($c['sns_account'] ?? '—') ?></div>
                    </div>
                    <form class="inline-form" method="post" action="/admin/customers/merge" onsubmit="return confirm('「<?= e($c['name']) ?>」をこちらにまとめます。よろしいですか？');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="into_id" value="<?= $id ?>">
                        <input type="hidden" name="from_ids[]" value="<?= (int) $c['id'] ?>">
                        <button type="submit" class="button button--small">こちらにまとめる</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section class="card">
    <h2>クルー</h2>
    <form method="post" action="/admin/crew/<?= $id ?>/status" class="form">
        <?= csrf_field() ?>
        <div class="form__row">
            <label class="form__field">
                <span class="form__label">状態</span>
                <select name="crew_status">
                    <?php foreach (App\Crew::STATUSES as $code => $label): ?><option value="<?= e($code) ?>"<?= $customer['crew_status'] === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="form__field"><span class="form__label">加入日</span><input type="date" name="crew_joined_at" value="<?= e($customer['crew_joined_at'] ?? '') ?>"></label>
            <label class="form__field"><span class="form__label">脱退日</span><input type="date" name="crew_left_at" value="<?= e($customer['crew_left_at'] ?? '') ?>"></label>
        </div>
        <label class="form__field"><span class="form__label">メモ（連絡の希望など）</span><input type="text" name="crew_note" value="<?= e($customer['crew_note'] ?? '') ?>" maxlength="255"></label>
        <button type="submit" class="button">保存する</button>
        <span class="form__help">「加入中」にすると、クルー料金のあるイベントに申し込んだとき自動でクルー料金になり、クルー限定の講座が見られます</span>
    </form>
</section>

<section class="card">
    <h2>出禁</h2>
    <?php if ($customer['banned_at'] !== null): ?>
        <p>この人は出禁です。申込フォームでは電話・メール・SNSが一致するとキャンセル待ちに止まり、手入力では警告が出ます。<a href="/admin/bans">出禁リスト</a></p>
        <form method="post" action="/admin/customers/<?= $id ?>/ban" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="unban">
            <button type="submit" class="button">出禁を解除する</button>
        </form>
    <?php else: ?>
        <form method="post" action="/admin/customers/<?= $id ?>/ban" class="form" onsubmit="return confirm('この人を出禁にします。よろしいですか？');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="ban">
            <label class="form__field">
                <span class="form__label">理由（短く）</span>
                <input type="text" name="reason" maxlength="255">
            </label>
            <label class="form__field">
                <span class="form__label">経緯・メモ（運営向け）</span>
                <textarea name="note" rows="3"></textarea>
            </label>
            <button type="submit" class="button button--danger">出禁にする</button>
        </form>
    <?php endif; ?>
</section>

<?php if ($legacy !== []): ?>
    <section class="card">
        <h2>移行元のデータ（今のスプレッドシートの行）</h2>
        <dl class="kv">
            <?php foreach ($legacy as $label => $value): ?>
                <dt><?= e((string) $label) ?></dt><dd><?= e(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE)) ?></dd>
            <?php endforeach; ?>
        </dl>
    </section>
<?php endif; ?>
