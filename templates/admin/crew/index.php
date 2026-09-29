<?php
/** @var string $status */
/** @var string $q */
/** @var array $members */
/** @var array<string, int> $counts */
/** @var array $applications */
$tabs = ['active' => '加入中', 'applied' => '申込中', 'left' => '脱退', 'all' => '全員'];
?>
<?php if ($applications !== []): ?>
    <section class="card">
        <h2>クルーの申込（<?= count($applications) ?>件）</h2>
        <p class="text-muted">募集ページからの申込です。承認するとクルー（加入中）になり、加入のご案内メールが届きます。月額の支払い方法は運営からご案内してください。</p>
        <?php foreach ($applications as $a): ?>
            <?php $answers = $a['answers'] !== null ? json_decode($a['answers'], true) : []; ?>
            <div class="list__item">
                <div class="list__main">
                    <a class="list__title" href="/admin/customers/<?= (int) $a['customer_id'] ?>"><?= e($a['customer_name']) ?></a><?= $a['customer_kana'] !== null ? ' <span class="text-muted">' . e($a['customer_kana']) . '</span>' : '' ?>
                    <div class="list__sub">申込 <?= e(fmt_dt($a['created_at'])) ?>・電話 <?= e($a['customer_phone'] ?? '—') ?>・メール <?= e($a['customer_email'] ?? '—') ?></div>
                    <?php if (is_array($answers) && $answers !== []): ?>
                        <div class="list__sub"><?= e(implode('　', array_map(fn ($k, $v) => (['region' => '地域', 'contact_pref' => '連絡の希望', 'comment' => 'ひとこと'][$k] ?? $k) . '：' . $v, array_keys($answers), $answers))) ?></div>
                    <?php endif; ?>
                </div>
                <form class="inline-form" method="post" action="/admin/crew/applications/<?= (int) $a['id'] ?>"><?= csrf_field() ?><input type="hidden" name="action" value="approved"><button type="submit" class="button button--small button--primary">承認してクルーにする</button></form>
                <form class="inline-form" method="post" action="/admin/crew/applications/<?= (int) $a['id'] ?>" onsubmit="return confirm('この申込をお断りにします。よろしいですか？');"><?= csrf_field() ?><input type="hidden" name="action" value="declined"><button type="submit" class="button button--small">お断り</button></form>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<section class="card">
    <div class="toolbar">
        <h1>クルー <span class="text-muted">加入中 <?= (int) $counts['active'] ?>人</span></h1>
        <a class="button" href="/crew" target="_blank">募集ページを見る</a>
    </div>
    <div class="tabs">
        <?php foreach ($tabs as $code => $label): ?>
            <a href="/admin/crew?status=<?= e($code) ?>" class="<?= $status === $code ? 'is-active' : '' ?>"><?= e($label) ?><?php if ($code !== 'all'): ?>（<?= (int) $counts[$code] ?>）<?php endif; ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get" action="/admin/crew" class="searchbar">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="名前・電話番号・メール">
        <button type="submit" class="button">検索</button>
    </form>
    <?php if ($members === []): ?><p class="text-muted">該当する人はいません。</p><?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>名前</th><th>状態</th><th>加入日</th><th>脱退日</th><th>電話</th><th>メール</th><th class="wrap">メモ</th></tr></thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr>
                            <td><a href="/admin/customers/<?= (int) $m['id'] ?>"><?= e($m['name']) ?></a><?php if ($m['name_kana'] !== null): ?><br><span class="text-muted"><?= e($m['name_kana']) ?></span><?php endif; ?></td>
                            <td><span class="<?= e(App\Crew::STATUS_BADGES[$m['crew_status']] ?? 'badge') ?>"><?= e(App\Crew::STATUSES[$m['crew_status']] ?? '') ?></span></td>
                            <td><?= e($m['crew_joined_at'] ?? '—') ?></td>
                            <td><?= e($m['crew_left_at'] ?? '—') ?></td>
                            <td><?= e($m['phone'] ?? '—') ?></td>
                            <td><?= e($m['email'] ?? '—') ?></td>
                            <td class="wrap"><?= e($m['crew_note'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <p class="text-muted" style="margin-top: 12px;">クルーの状態は、顧客の詳細の「クルー」から変えられます。加入中の人は、クルー料金のある回に申し込むと自動でクルー料金になります。</p>
</section>
