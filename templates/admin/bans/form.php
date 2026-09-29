<?php
/** @var string $step search か details */
?>
<?php if ($step === 'search'): ?>
    <?php /** @var string $q */ /** @var array $results */ ?>
    <section class="card">
        <h1>出禁に登録</h1>
        <p class="text-muted">まず顧客台帳から探します。台帳にいない人は、下のボタンからその場で登録できます。</p>
        <form method="get" action="/admin/bans/new" class="searchbar">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="名前・フリガナ・電話番号・メール・SNS" autofocus>
            <button type="submit" class="button">検索</button>
        </form>
        <?php if ($q !== '' && $results === []): ?><p>「<?= e($q) ?>」に当てはまる顧客はいません。</p><?php endif; ?>
        <ul class="list">
            <?php foreach ($results as $c): ?>
                <li class="list__item">
                    <div class="list__main">
                        <span class="list__title"><?= e($c['name']) ?></span>
                        <?php if ($c['name_kana'] !== null): ?><span class="text-muted"><?= e($c['name_kana']) ?></span><?php endif; ?>
                        <?php if ($c['banned_at'] !== null): ?><span class="badge badge--danger">出禁</span><?php endif; ?>
                        <div class="list__sub">電話 <?= e($c['phone'] ?? '—') ?>・メール <?= e($c['email'] ?? '—') ?>・SNS <?= e($c['sns_account'] ?? '—') ?>・参加 <?= (int) $c['registration_count'] ?>回</div>
                    </div>
                    <?php if ($c['banned_at'] === null): ?>
                        <a class="button button--small button--danger" href="/admin/bans/new?customer_id=<?= (int) $c['id'] ?>">この人を出禁にする</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="actions" style="margin-top: 16px;">
            <a class="button<?= $q !== '' && $results === [] ? ' button--primary' : '' ?>" href="/admin/bans/new?new=1&amp;name=<?= e(rawurlencode($q)) ?>">台帳にいない人を登録して出禁にする</a>
            <a class="button" href="/admin/bans">戻る</a>
        </div>
    </section>
<?php else: ?>
    <?php
    /** @var array|null $customer */
    /** @var array $values */
    /** @var array $customerValues */
    /** @var list<string> $errors */
    /** @var array $candidates */
    /** @var array $channels */
    ?>
    <section class="card">
        <h1>出禁に登録</h1>
        <?php if ($errors !== []): ?>
            <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="post" action="/admin/bans/new" class="form" autocomplete="off">
            <?= csrf_field() ?>
            <?php if ($customer !== null): ?>
                <input type="hidden" name="customer_id" value="<?= (int) $customer['id'] ?>">
                <fieldset>
                    <legend>出禁にする人</legend>
                    <strong><?= e($customer['name']) ?></strong><?= $customer['name_kana'] !== null ? '（' . e($customer['name_kana']) . '）' : '' ?>
                    <div class="text-muted">電話 <?= e($customer['phone'] ?? '—') ?>・メール <?= e($customer['email'] ?? '—') ?>・SNS <?= e($customer['sns_account'] ?? '—') ?>　<a href="/admin/customers/<?= (int) $customer['id'] ?>" target="_blank">顧客の詳細</a></div>
                    <p class="form__help">申込時の照合には電話・メール・SNSを使います。分かっていれば<a href="/admin/customers/<?= (int) $customer['id'] ?>/edit">顧客の編集</a>で入れておくと確実に止められます。</p>
                </fieldset>
            <?php else: ?>
                <input type="hidden" name="new" value="1">
                <fieldset>
                    <legend>台帳にいない人（分かる項目だけで構いません）</legend>
                    <div class="form">
                        <?php $values_backup = $values; $values = $customerValues; ?>
                        <?php require APP_ROOT . '/templates/admin/customers/_fields.php'; ?>
                        <?php $values = $values_backup; ?>
                        <?php if ($candidates !== []): ?>
                            <div class="warning-box">
                                <strong>台帳に同じ人かもしれない顧客がいます</strong>
                                <ul>
                                    <?php foreach ($candidates as $c): ?>
                                        <li><?= e($c['name']) ?><?= $c['name_kana'] !== null ? '（' . e($c['name_kana']) . '）' : '' ?>　電話 <?= e($c['phone'] ?? '—') ?>　メール <?= e($c['email'] ?? '—') ?>　<a class="button button--small button--danger" href="/admin/bans/new?customer_id=<?= (int) $c['id'] ?>">この人を出禁にする</a></li>
                                    <?php endforeach; ?>
                                </ul>
                                <label class="form__check"><input type="checkbox" name="confirm_duplicate" value="1"> <span>別の人として登録する</span></label>
                            </div>
                        <?php endif; ?>
                    </div>
                </fieldset>
            <?php endif; ?>
            <label class="form__field">
                <span class="form__label">理由（短く）</span>
                <input type="text" name="reason" value="<?= e($values['reason']) ?>" maxlength="255" placeholder="例：勧誘行為、無断キャンセルの繰り返し">
            </label>
            <label class="form__field">
                <span class="form__label">経緯・メモ（運営向け）</span>
                <textarea name="note" rows="4"><?= e($values['note']) ?></textarea>
                <span class="form__help">いつ・どの回で・何があったか。証拠の画像はここに貼らず、保管場所だけ書いてください</span>
            </label>
            <div class="actions">
                <button type="submit" class="button button--danger">出禁にする</button>
                <a class="button" href="/admin/bans/new">戻る</a>
            </div>
        </form>
    </section>
<?php endif; ?>
