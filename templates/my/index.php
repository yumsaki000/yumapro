<?php
/** @var array $customer */
/** @var string $token */
/** @var array $upcoming */
/** @var array $past */
/** @var bool $optedIn */
/** @var string $bankAccount */
/** @var string $contact */
/** @var ?string $referralCode 友だち招待のリンク（使わない設定なら null） */
/** @var string $referralText */
/** @var int $referralCount */
$statusLabel = fn (array $r) => match ($r['status']) {
    'applied' => $r['event_payment_timing'] === 'prepaid' && $r['prepaid_at'] === null ? 'お申込み済み（お振込み待ち）' : 'お申込み済み',
    'waitlisted' => 'キャンセル待ち',
    default => 'キャンセル済み',
};
?>
<section class="card">
    <h1><?= e($customer['name']) ?> 様のお申込み</h1>
    <p class="text-muted">このページはご本人専用です。URLを他の人に教えないでください。</p>

    <p>
        <?php if ($isCrew): ?><span class="badge badge--ok">クルー</span> クルー限定の講座・動画は<a href="/learn">こちら</a><?php elseif ($crewStatus === 'applied'): ?><span class="badge badge--warn">クルー申込中</span> 運営からの連絡をお待ちください<?php else: ?><a href="/crew">クルーになると、参加費の割引や限定の講座が見られます</a><?php endif; ?>
    </p>

    <h2>これからのイベント</h2>
    <?php if ($upcoming === []): ?><p class="text-muted">お申込み中のイベントはありません。<a href="/">イベント一覧を見る</a></p><?php endif; ?>
    <?php foreach ($upcoming as $r): ?>
        <div class="ticket">
            <div class="ticket__title"><?= e($r['event_title']) ?></div>
            <div><?= e(fmt_dt($r['event_starts_at'])) ?></div>
            <div><span class="<?= e(App\Registrations::STATUS_BADGES[$r['status']] ?? 'badge') ?>"><?= e($statusLabel($r)) ?></span></div>
            <?php if ($r['status'] === 'applied'): ?>
                <dl class="kv" style="margin-top: 8px;">
                    <dt>会場</dt><dd><?= e($r['event_venue_name'] ?? '追ってご案内します') ?><?php if ($r['event_venue_address'] !== null): ?><br><?= e($r['event_venue_address']) ?><?php endif; ?><?php if ($r['event_venue_url'] !== null): ?><br><a href="<?= e($r['event_venue_url']) ?>" target="_blank" rel="noopener">地図</a><?php endif; ?></dd>
                    <dt>参加費</dt><dd><?= e(yen($r['fee'])) ?>（<?= e(App\Events::PAYMENT_TIMINGS[$r['event_payment_timing']] ?? '') ?>）</dd>
                    <?php if ($r['event_payment_timing'] === 'prepaid'): ?>
                        <dt>お支払い</dt><dd><?= $r['prepaid_at'] !== null ? '入金を確認しました。ありがとうございます。' : ($bankAccount !== '' ? '<pre class="plain">' . e($bankAccount) . '</pre>' : '確認メールの案内に沿ってお振込みください。') ?></dd>
                    <?php endif; ?>
                    <?php if ($r['event_cancel_deadline'] !== null): ?><dt>キャンセル期限</dt><dd><?= e(fmt_dt($r['event_cancel_deadline'])) ?></dd><?php endif; ?>
                </dl>
            <?php endif; ?>
            <?php if ($referralCode !== null && $r['status'] === 'applied'): ?>
                <p style="margin: 8px 0 0;"><button type="button" class="button button--small" data-invite="<?= e(app_url('/e/' . $r['event_slug'] . '?ref=' . $referralCode)) ?>" data-invite-text="<?= e($r['event_title'] . ' に一緒に行きませんか？') ?>">このイベントに友だちを誘う</button></p>
            <?php endif; ?>
            <?php if ($r['cancellable']): ?>
                <form method="post" action="/my/<?= e($token) ?>/cancel/<?= (int) $r['id'] ?>" style="margin-top: 8px;" data-confirm="「<?= e($r['event_title']) ?>」のお申込みをキャンセルします。よろしいですか？" onsubmit="return confirm(this.dataset.confirm);">
                    <?= csrf_field() ?>
                    <button type="submit" class="button button--small button--danger">キャンセルする</button>
                </form>
            <?php elseif ($r['status'] !== 'cancelled'): ?>
                <p class="text-muted" style="margin: 8px 0 0;">キャンセル期限を過ぎているため、ここからはキャンセルできません。運営までご連絡ください。</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if ($past !== []): ?>
        <h2>参加したイベント</h2>
        <ul class="list">
            <?php foreach ($past as $r): ?>
                <li class="list__item">
                    <div class="list__main">
                        <div class="list__title"><?= e($r['event_title']) ?></div>
                        <div class="list__sub"><?= e(fmt_dt($r['event_starts_at'], false)) ?></div>
                    </div>
                    <?php if ($r['status'] === 'applied'): ?>
                        <a class="button button--small" href="/my/<?= e($token) ?>/survey/<?= (int) $r['id'] ?>"><?= $r['survey'] !== null ? 'アンケートを直す' : 'アンケートに答える' ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if ($referralCode !== null): ?>
    <?php $inviteUrl = app_url('/?ref=' . $referralCode); ?>
    <section class="card invite">
        <h2>友だちを招待する</h2>
        <p><?= nl2br(e($referralText)) ?></p>
        <div class="form">
            <label class="form__field">
                <span class="form__label">あなたの招待リンク</span>
                <input type="text" readonly value="<?= e($inviteUrl) ?>" onclick="this.select()">
            </label>
        </div>
        <div class="actions" style="margin-top: 12px;">
            <button type="button" class="button button--primary button--small" data-invite="<?= e($inviteUrl) ?>" data-invite-text="<?= e(App\Settings::get('public_name') . ' のイベント、一緒に行きませんか？') ?>">リンクを送る・コピー</button>
            <a class="button button--small" href="https://line.me/R/share?text=<?= e(rawurlencode(App\Settings::get('public_name') . ' のイベント、一緒に行きませんか？' . "\n" . $inviteUrl)) ?>" target="_blank" rel="noopener">LINEで送る</a>
        </div>
        <?php if ($referralCount > 0): ?><p class="text-muted small">これまでに <?= (int) $referralCount ?>人があなたのリンクから申し込みました。ありがとうございます。</p><?php endif; ?>
    </section>
<?php endif; ?>

<section class="card">
    <h2>イベントの案内メール</h2>
    <form method="post" action="/my/<?= e($token) ?>/mail" class="form">
        <?= csrf_field() ?>
        <label class="form__check"><input type="checkbox" name="opt_in" value="1"<?= $optedIn ? ' checked' : '' ?>> <span>今後のイベント案内をメールで受け取る</span></label>
        <button type="submit" class="button button--small">保存する</button>
    </form>
    <p class="text-muted" style="margin-top: 16px;"><?= nl2br(e($contact)) ?></p>
</section>
<script>
// 招待リンク：スマホでは共有メニュー、使えなければコピー
document.querySelectorAll('[data-invite]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var url = btn.dataset.invite, text = btn.dataset.inviteText || '';
        if (navigator.share) {
            navigator.share({ title: text, text: text, url: url }).catch(function () {});
            return;
        }
        (navigator.clipboard ? navigator.clipboard.writeText(url) : Promise.reject()).then(function () {
            btn.textContent = 'リンクをコピーしました';
        }, function () { window.prompt('このリンクをコピーしてください', url); });
    });
});
</script>
