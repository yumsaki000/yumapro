<?php
/** @var array $event */
/** @var array $registrations */
/** @var array $survey */
/** @var string $baseUrl */
$id = (int) $event['id'];
$banLabels = ['suspect' => ['badge badge--warn', '要確認（出禁と同名）'], 'confirmed' => ['badge badge--danger', '出禁該当']];
$pendingCount = count(array_filter($registrations, fn ($r) => in_array($r['ban_check'], ['suspect', 'confirmed'], true) && $r['status'] !== 'cancelled'));
$capacity = $event['capacity'] !== null ? (int) $event['capacity'] : null;
$prepaidCount = count(array_filter($registrations, fn ($r) => $r['status'] !== 'cancelled' && $r['prepaid_at'] !== null));
?>
<section class="card">
    <div class="toolbar">
        <h1><?= e($event['title']) ?></h1>
        <span>
            <span class="badge"><?= e($event['type_name']) ?></span>
            <span class="<?= e(App\Events::STATUS_BADGES[$event['status']] ?? 'badge') ?>"><?= e(App\Events::STATUSES[$event['status']] ?? $event['status']) ?></span>
        </span>
    </div>

    <dl class="kv">
        <dt>日時</dt><dd><?= e(fmt_dt($event['starts_at'])) ?><?= $event['ends_at'] !== null ? ' 〜 ' . e(fmt_dt($event['ends_at'])) : '' ?></dd>
        <dt>会場</dt><dd><?= e($event['venue_name'] ?? '—') ?><?php if ($event['venue_url'] !== null): ?> <a href="<?= e($event['venue_url']) ?>" target="_blank" rel="noopener">地図</a><?php endif; ?><?php if ($event['venue_address'] !== null): ?><br><span class="text-muted"><?= e($event['venue_address']) ?></span><?php endif; ?></dd>
        <dt>参加費</dt><dd><?= e(yen($event['fee'])) ?><?php if ($event['fee_male'] !== null || $event['fee_female'] !== null): ?>（男性 <?= e(yen($event['fee_male'] ?? $event['fee'])) ?>／女性 <?= e(yen($event['fee_female'] ?? $event['fee'])) ?>）<?php endif; ?><?php if ($event['fee_crew'] !== null): ?>・クルー <?= e(yen($event['fee_crew'])) ?><?php endif; ?>・<?= e(App\Events::PAYMENT_TIMINGS[$event['payment_timing']] ?? '') ?></dd>
        <dt>定員</dt><dd><?= $capacity === null ? '上限なし' : $capacity . '人' ?><?php if ($event['capacity_male'] !== null || $event['capacity_female'] !== null): ?>（男性 <?= e((string) ($event['capacity_male'] ?? '—')) ?>／女性 <?= e((string) ($event['capacity_female'] ?? '—')) ?>）<?php endif; ?></dd>
        <dt>申込締切</dt><dd><?= e(fmt_dt($event['apply_deadline'])) ?></dd>
        <dt>キャンセル期限</dt><dd><?= e(fmt_dt($event['cancel_deadline'])) ?></dd>
        <dt>掲載内容</dt><dd><?php
            $filled = array_filter(['一言紹介' => 'summary', '安心ポイント' => 'highlights', '内容' => 'description', 'おすすめ' => 'recommend', 'スケジュール' => 'timetable', '持ち物' => 'belongings', '地図' => 'map_query'], fn ($f) => trim((string) $event[$f]) !== '');
            echo $filled === [] ? '<span class="text-muted">まだ入っていません（編集から）</span>' : e(implode('・', array_keys($filled)));
        ?></dd>
    </dl>

    <div class="stats">
        <div class="stat"><div class="stat__label">申込</div><div class="stat__value"><?= (int) $event['applied_count'] ?><?= $capacity !== null ? '<span class="text-muted"> / ' . $capacity . '</span>' : '' ?></div></div>
        <div class="stat"><div class="stat__label">キャンセル待ち</div><div class="stat__value"><?= (int) $event['waitlisted_count'] ?></div></div>
        <?php if ($event['payment_timing'] === 'prepaid'): ?>
            <div class="stat"><div class="stat__label">入金確認済み</div><div class="stat__value"><?= $prepaidCount ?></div></div>
        <?php endif; ?>
        <div class="stat"><div class="stat__label">到着</div><div class="stat__value"><?= (int) $event['arrived_count'] ?></div></div>
    </div>

    <div class="actions">
        <a class="button button--primary" href="/admin/events/<?= $id ?>/registrations/new">申込を追加</a>
        <a class="button" href="/admin/events/<?= $id ?>/checkin">当日受付</a>
        <a class="button" href="/admin/events/<?= $id ?>/accounting">会計</a>
        <a class="button" href="/admin/events/<?= $id ?>/edit">編集</a>
        <a class="button" href="/admin/events/<?= $id ?>/preview">ページを確認</a>
        <form class="inline-form" method="post" action="/admin/events/<?= $id ?>/copy">
            <?= csrf_field() ?>
            <button type="submit" class="button">複製</button>
        </form>
    </div>

    <form method="post" action="/admin/events/<?= $id ?>/status" class="actions" style="margin-top: 12px;" onsubmit="return this.status.value !== 'cancelled' || confirm('このイベントを中止にします。よろしいですか？');">
        <?= csrf_field() ?>
        <label>状態を変える：
            <select name="status">
                <?php foreach (App\Events::STATUSES as $code => $label): ?>
                    <option value="<?= e($code) ?>"<?= $event['status'] === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="button button--small">変更</button>
    </form>
</section>

<section class="card">
    <h2>告知に使うリンクと文面</h2>
    <?php if ($event['status'] !== 'open'): ?>
        <p class="alert alert--info">いまは「<?= e(App\Events::STATUSES[$event['status']] ?? '') ?>」です。状態を「募集中」にすると、掲示板に出て申し込めるようになります。</p>
    <?php endif; ?>
    <p class="text-muted">貼る場所を選ぶと、その場所用のリンク（どこから申込が来たかが「集計」で分かる）に変わります。こくちーずの説明欄や Instagram・LINE にそのまま貼れます。</p>
    <div class="announce form" data-base="<?= e($baseUrl . '/e/' . $event['slug']) ?>">
        <label class="form__field">
            <span class="form__label">貼る場所</span>
            <select class="announce__channel">
                <option value="">共通（場所を分けない）</option>
                <?php foreach (App\Stats::ENTRY_KEYS as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="form__field">
            <span class="form__label">リンク</span>
            <input type="text" class="announce__url" readonly value="<?= e($baseUrl . '/e/' . $event['slug']) ?>">
        </label>
        <label class="form__field">
            <span class="form__label">告知文</span>
            <textarea class="announce__text" rows="12" readonly data-template="<?= e(App\Announce::text($event, '{URL}')) ?>"><?= e(App\Announce::text($event, $baseUrl . '/e/' . $event['slug'])) ?></textarea>
        </label>
        <div class="actions">
            <button type="button" class="button button--small" data-copy-from=".announce__url">リンクをコピー</button>
            <button type="button" class="button button--small" data-copy-from=".announce__text">告知文をコピー</button>
        </div>
    </div>
    <script>
    document.querySelectorAll('.announce').forEach(function (box) {
        var select = box.querySelector('.announce__channel'), url = box.querySelector('.announce__url'), text = box.querySelector('.announce__text');
        select.addEventListener('change', function () {
            var link = box.dataset.base + (select.value ? '?from=' + select.value : '');
            url.value = link;
            text.value = text.dataset.template.split('{URL}').join(link);
        });
        box.querySelectorAll('[data-copy-from]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var field = box.querySelector(btn.dataset.copyFrom), label = btn.textContent;
                var done = function () { btn.textContent = 'コピーしました'; setTimeout(function () { btn.textContent = label; }, 1500); };
                if (navigator.clipboard) { navigator.clipboard.writeText(field.value).then(done, function () { field.select(); document.execCommand('copy'); done(); }); }
                else { field.select(); document.execCommand('copy'); done(); }
            });
        });
    });
    </script>

    <h2>メール</h2>
    <p class="text-muted">前日のリマインドと翌日のお礼は自動で送ります（サーバーの定期実行）。今すぐ送りたいときはこちら。すでに送った人には送りません。</p>
    <div class="actions">
        <form class="inline-form" method="post" action="/admin/events/<?= $id ?>/mails" onsubmit="return confirm('申込の人にリマインドメールを送ります。よろしいですか？');">
            <?= csrf_field() ?><input type="hidden" name="kind" value="reminder">
            <button type="submit" class="button button--small">リマインドを今すぐ送る</button>
        </form>
        <form class="inline-form" method="post" action="/admin/events/<?= $id ?>/mails" onsubmit="return confirm('参加した人にお礼メール（アンケート付き）を送ります。よろしいですか？');">
            <?= csrf_field() ?><input type="hidden" name="kind" value="thanks">
            <button type="submit" class="button button--small">お礼メールを今すぐ送る</button>
        </form>
    </div>

    <h2>アンケート（<?= (int) $survey['count'] ?>件）</h2>
    <?php if ($survey['count'] === 0): ?>
        <p class="text-muted">回答はまだありません。お礼メールにアンケートのリンクが入ります。</p>
    <?php else: ?>
        <div class="stats">
            <div class="stat"><div class="stat__label">満足度（5点満点）</div><div class="stat__value"><?= e((string) $survey['average']) ?></div></div>
            <div class="stat"><div class="stat__label">また参加したい</div><div class="stat__value"><?= (int) $survey['intents']['yes'] ?><span class="text-muted"> / <?= (int) $survey['count'] ?></span></div></div>
        </div>
        <?php if ($survey['comments'] !== []): ?>
            <ul class="list">
                <?php foreach ($survey['comments'] as $c): ?>
                    <li class="list__item"><div class="list__main"><div class="list__sub"><?= e($c['customer_name']) ?>・満足度 <?= (int) $c['satisfaction'] ?></div><pre class="plain"><?= e($c['comment']) ?></pre></div></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="card">
    <h2>申込者（<?= count($registrations) ?>人）</h2>
    <?php if ($pendingCount > 0): ?>
        <p class="warning-box">出禁チェックで印が付いた申込が <?= $pendingCount ?>件あります。「詳細」から確認してください。</p>
    <?php endif; ?>
    <?php if ($registrations === []): ?>
        <p class="text-muted">まだ申込がありません。</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>名前</th><th>状態</th><th class="num">参加費</th><th>入金</th><th>到着</th><th>知った経路</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($registrations as $r): ?>
                        <?php $rid = (int) $r['id']; ?>
                        <tr class="<?= $r['status'] === 'cancelled' ? 'is-muted' : '' ?>">
                            <td>
                                <a href="/admin/customers/<?= (int) $r['customer_id'] ?>"><?= e($r['customer_name']) ?></a>
                                <?php if ($r['customer_kana'] !== null): ?><br><span class="text-muted"><?= e($r['customer_kana']) ?></span><?php endif; ?>
                                <?php if ($r['customer_banned_at'] !== null): ?><span class="badge badge--danger">出禁</span><?php endif; ?>
                                <?php if (isset($banLabels[$r['ban_check']])): ?><span class="<?= e($banLabels[$r['ban_check']][0]) ?>"><?= e($banLabels[$r['ban_check']][1]) ?></span><?php endif; ?>
                            </td>
                            <td><span class="<?= e(App\Registrations::STATUS_BADGES[$r['status']] ?? 'badge') ?>"><?= e(App\Registrations::STATUSES[$r['status']] ?? $r['status']) ?></span></td>
                            <td class="num"><?= e(yen($r['fee'])) ?></td>
                            <td>
                                <?php if ($r['prepaid_at'] !== null): ?>
                                    <span class="badge badge--ok">前払い済</span>
                                <?php elseif ($r['paid_amount'] !== null): ?>
                                    <span class="badge badge--ok">当日 <?= e(yen($r['paid_amount'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $r['arrived_at'] !== null ? e(date('H:i', strtotime($r['arrived_at']))) : '<span class="text-muted">—</span>' ?></td>
                            <td class="wrap"><?= e($r['channel'] ?? '—') ?></td>
                            <td>
                                <div class="actions">
                                    <a class="button button--small" href="/admin/registrations/<?= $rid ?>/edit">詳細</a>
                                    <?php if ($r['status'] === 'cancelled' || $r['status'] === 'waitlisted'): ?>
                                        <form class="inline-form" method="post" action="/admin/registrations/<?= $rid ?>/restore"><?= csrf_field() ?><button type="submit" class="button button--small"><?= $r['status'] === 'cancelled' ? '申込に戻す' : '繰り上げ' ?></button></form>
                                    <?php else: ?>
                                        <form class="inline-form" method="post" action="/admin/registrations/<?= $rid ?>/cancel" onsubmit="return confirm('キャンセルにします。よろしいですか？');"><?= csrf_field() ?><button type="submit" class="button button--small button--danger">キャンセル</button></form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
