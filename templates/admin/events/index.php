<?php
/** @var string $scope */
/** @var array $events */
?>
<section class="card">
    <div class="toolbar">
        <h1>イベント一覧</h1>
        <a class="button button--primary" href="/admin/events/new">新しいイベント</a>
    </div>
    <div class="tabs">
        <a href="/admin/events?scope=upcoming" class="<?= $scope === 'upcoming' ? 'is-active' : '' ?>">これから</a>
        <a href="/admin/events?scope=past" class="<?= $scope === 'past' ? 'is-active' : '' ?>">終わったイベント</a>
    </div>
    <?php if ($events === []): ?>
        <p class="text-muted"><?= $scope === 'past' ? '終わったイベントはまだありません。' : 'これからのイベントはまだありません。「新しいイベント」から作るか、終わったイベントを複製してください。' ?></p>
    <?php endif; ?>
    <ul class="list">
        <?php foreach ($events as $event): ?>
            <li class="list__item">
                <div class="list__main">
                    <a class="list__title" href="/admin/events/<?= (int) $event['id'] ?>"><?= e($event['title']) ?></a>
                    <span class="badge"><?= e($event['type_name']) ?></span>
                    <span class="<?= e(App\Events::STATUS_BADGES[$event['status']] ?? 'badge') ?>"><?= e(App\Events::STATUSES[$event['status']] ?? $event['status']) ?></span>
                    <div class="list__sub">
                        <?= e(fmt_dt($event['starts_at'])) ?>
                        <?php if ($event['venue_name'] !== null): ?>・<?= e($event['venue_name']) ?><?php endif; ?>
                        ・申込 <?= (int) $event['applied_count'] ?>人<?= $event['capacity'] !== null ? '／定員 ' . (int) $event['capacity'] . '人' : '' ?>
                        <?php if ((int) $event['waitlisted_count'] > 0): ?>（キャンセル待ち <?= (int) $event['waitlisted_count'] ?>）<?php endif; ?>
                    </div>
                </div>
                <?php if ($scope === 'upcoming'): ?>
                    <a class="button button--small" href="/admin/events/<?= (int) $event['id'] ?>/checkin">当日受付</a>
                <?php else: ?>
                    <a class="button button--small" href="/admin/events/<?= (int) $event['id'] ?>/accounting">会計</a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
