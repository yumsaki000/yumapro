<?php
/** @var array $course */
/** @var array $lessons */
/** @var array $purchases */
/** @var string $baseUrl */
$id = (int) $course['id'];
?>
<section class="card">
    <div class="toolbar">
        <h1><?= e($course['title']) ?></h1>
        <span>
            <span class="<?= $course['status'] === 'published' ? 'badge badge--ok' : 'badge' ?>"><?= e(App\Courses::STATUSES[$course['status']]) ?></span>
            <span class="badge"><?= e(App\Courses::ACCESS[$course['access']]) ?><?= $course['access'] === 'paid' && $course['price'] !== null ? ' ' . e(yen($course['price'])) : '' ?></span>
        </span>
    </div>
    <?php if ($course['description'] !== null): ?><pre class="plain text-muted"><?= e($course['description']) ?></pre><?php endif; ?>
    <p class="text-muted">公開ページ：<code><?= e($baseUrl) ?>/learn/<?= e($course['slug']) ?></code></p>
    <div class="actions">
        <a class="button" href="/admin/courses/<?= $id ?>/edit">編集</a>
        <a class="button" href="/learn/<?= e($course['slug']) ?>" target="_blank">公開ページを見る</a>
        <form class="inline-form" method="post" action="/admin/courses/<?= $id ?>/delete" onsubmit="return confirm('この講座と各回を消します。よろしいですか？');"><?= csrf_field() ?><button type="submit" class="button button--danger">消す</button></form>
    </div>
</section>

<section class="card">
    <h2>各回（<?= count($lessons) ?>）</h2>
    <?php if ($lessons === []): ?><p class="text-muted">まだ回がありません。下から追加してください。</p><?php else: ?>
        <ul class="list">
            <?php foreach ($lessons as $i => $l): ?>
                <li class="list__item">
                    <div class="list__main">
                        <span class="list__title"><?= $i + 1 ?>. <?= e($l['title']) ?></span>
                        <?php if ((int) $l['is_preview'] === 1): ?><span class="badge badge--ok">お試し</span><?php endif; ?>
                        <?php if ($l['status'] !== 'published'): ?><span class="badge">下書き</span><?php endif; ?>
                        <?php if ($l['youtube_id'] !== null): ?><span class="badge">動画</span><?php endif; ?>
                        <div class="list__sub">並び順 <?= (int) $l['sort_order'] ?><?= $l['body'] !== null ? '・' . e(mb_strimwidth($l['body'], 0, 60, '…')) : '' ?></div>
                    </div>
                    <a class="button button--small" href="/admin/lessons/<?= (int) $l['id'] ?>/edit">編集</a>
                    <form class="inline-form" method="post" action="/admin/lessons/<?= (int) $l['id'] ?>/delete" onsubmit="return confirm('この回を消します。よろしいですか？');"><?= csrf_field() ?><button type="submit" class="button button--small button--danger">消す</button></form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>回を追加</h2>
    <form method="post" action="/admin/courses/<?= $id ?>/lessons" class="form">
        <?= csrf_field() ?>
        <label class="form__field"><span class="form__label">回のタイトル</span><input type="text" name="title" maxlength="200" required></label>
        <label class="form__field"><span class="form__label">YouTubeの動画URL（任意）</span><input type="url" name="youtube" placeholder="https://youtu.be/xxxxxxxxxxx" inputmode="url"><span class="form__help">YouTube側で「限定公開」にした動画のURLを貼ります</span></label>
        <label class="form__field"><span class="form__label">本文（任意）</span><textarea name="body" rows="5"></textarea><span class="form__help">改行はそのまま出ます。URLはリンクになります</span></label>
        <div class="form__row">
            <label class="form__check"><input type="checkbox" name="is_preview" value="1"> <span>お試し（誰でも見られる）</span></label>
            <label class="form__field"><span class="form__label">状態</span><select name="status"><option value="published">公開</option><option value="draft">下書き</option></select></label>
        </div>
        <button type="submit" class="button button--primary">追加する</button>
    </form>
</section>

<?php if ($course['access'] === 'paid'): ?>
    <section class="card">
        <h2>この講座の購入（<?= count($purchases) ?>）</h2>
        <?php if ($purchases === []): ?><p class="text-muted">購入はまだありません。</p><?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>名前</th><th>状態</th><th class="num">金額</th><th>申込</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($purchases as $p): ?>
                            <tr>
                                <td><a href="/admin/customers/<?= (int) $p['customer_id'] ?>"><?= e($p['customer_name']) ?></a></td>
                                <td><span class="<?= $p['status'] === 'paid' ? 'badge badge--ok' : 'badge badge--warn' ?>"><?= $p['status'] === 'paid' ? '入金確認済み' : '入金待ち' ?></span></td>
                                <td class="num"><?= e(yen($p['amount'])) ?></td>
                                <td><?= e(fmt_dt($p['created_at'])) ?></td>
                                <td>
                                    <?php if ($p['status'] === 'pending'): ?>
                                        <form class="inline-form" method="post" action="/admin/purchases/<?= (int) $p['id'] ?>/paid"><?= csrf_field() ?><input type="hidden" name="back" value="/admin/courses/<?= $id ?>"><button type="submit" class="button button--small button--primary">入金確認済みにする</button></form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
