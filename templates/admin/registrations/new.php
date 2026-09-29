<?php
/** @var array $event */
/** @var string $q */
/** @var array $results */
$eventId = (int) $event['id'];
?>
<section class="card">
    <h1>申込を追加</h1>
    <p class="text-muted"><?= e($event['title']) ?>　<?= e(fmt_dt($event['starts_at'])) ?>　参加費 <?= e(yen($event['fee'])) ?></p>

    <form method="get" action="/admin/events/<?= $eventId ?>/registrations/new" class="searchbar">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="名前・フリガナ・電話番号" autofocus>
        <button type="submit" class="button">検索</button>
    </form>

    <?php if ($q !== ''): ?>
        <?php if ($results === []): ?>
            <p>「<?= e($q) ?>」に当てはまる顧客はいません。</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($results as $c): ?>
                    <li class="list__item">
                        <div class="list__main">
                            <span class="list__title"><?= e($c['name']) ?></span>
                            <?php if ($c['name_kana'] !== null): ?><span class="text-muted"><?= e($c['name_kana']) ?></span><?php endif; ?>
                            <?php if ($c['banned_at'] !== null): ?><span class="badge badge--danger">出禁</span><?php endif; ?>
                            <?php if ($c['registered']): ?><span class="badge badge--ok">申込済み</span><?php endif; ?>
                            <div class="list__sub"><?= e(App\Customers::GENDERS[$c['gender']] ?? '性別未設定') ?>・電話 <?= e($c['phone'] ?? '—') ?>・参加 <?= (int) $c['registration_count'] ?>回</div>
                        </div>
                        <?php if (!$c['registered']): ?>
                            <a class="button button--small button--primary" href="/admin/events/<?= $eventId ?>/registrations/new?customer_id=<?= (int) $c['id'] ?>">この人を申し込む</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>

    <div class="actions" style="margin-top: 16px;">
        <a class="button<?= $q !== '' && $results === [] ? ' button--primary' : '' ?>" href="/admin/events/<?= $eventId ?>/registrations/new?new=1&amp;name=<?= e(rawurlencode($q)) ?>">新しい顧客として登録して申し込む</a>
        <a class="button" href="/admin/events/<?= $eventId ?>">戻る</a>
    </div>
</section>
