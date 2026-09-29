<?php
/** @var array $course */
/** @var array $lessons */
/** @var array $purchases */
/** @var string $baseUrl */
/** @var array|null $addValues 回の追加で誤りがあったときの入力 */
/** @var list<string> $addErrors */
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
    <p class="access-note"><?= e(App\Courses::ACCESS_HELP[$course['access']] ?? '') ?><?php if ($course['access'] === 'paid'): ?>（<?= e(yen($course['price'])) ?><?= (int) $course['crew_included'] === 1 ? '・クルーは無料' : '' ?>）<?php endif; ?>。お試しの回：<?= (int) $course['preview_count'] ?>回</p>
    <?php if ($course['description'] !== null): ?><div class="rich text-muted"><?= App\Courses::formatBody($course['description']) ?></div><?php endif; ?>
    <p class="text-muted">公開ページ：<code><?= e($baseUrl) ?>/learn/<?= e($course['slug']) ?></code></p>
    <div class="actions">
        <a class="button" href="/admin/courses/<?= $id ?>/edit">編集</a>
        <a class="button" href="/learn/<?= e($course['slug']) ?>" target="_blank">公開ページを見る</a>
        <form class="inline-form" method="post" action="/admin/courses/<?= $id ?>/delete" onsubmit="return confirm('この講座と、講座の回をすべて消します。よろしいですか？');"><?= csrf_field() ?><button type="submit" class="button button--danger">消す</button></form>
    </div>
</section>

<section class="card" id="lessons">
    <h2>講座の回（<?= count($lessons) ?>）</h2>
    <?php if ($lessons === []): ?><p class="text-muted">まだ講座の回がありません。下の「講座の回を追加」から、YouTube の URL と説明を入れて追加してください。</p><?php else: ?>
        <p class="text-muted small">↑↓で順番を入れ替えられます。「お試し」の回は誰でも見られます。</p>
        <ul class="lesson-list">
            <?php foreach ($lessons as $i => $l): ?>
                <li class="lesson-list__item<?= $l['status'] !== 'published' ? ' is-draft' : '' ?>">
                    <?php $thumb = App\Courses::thumbnailUrl($l['youtube_id']); ?>
                    <span class="lesson-list__thumb"><?php if ($thumb !== null): ?><img src="<?= e($thumb) ?>" alt="" loading="lazy"><?php else: ?><span>文章</span><?php endif; ?></span>
                    <span class="lesson-list__main">
                        <span class="lesson-list__title"><?= $i + 1 ?>. <?= e($l['title']) ?></span>
                        <span>
                            <?php if ((int) $l['is_preview'] === 1): ?><span class="badge badge--ok">お試し（無料公開）</span><?php else: ?><span class="badge"><?= e(App\Courses::ACCESS[$course['access']]) ?></span><?php endif; ?>
                            <?php if ($l['status'] !== 'published'): ?><span class="badge badge--warn">下書き</span><?php endif; ?>
                        </span>
                        <?php if ($l['body'] !== null): ?><span class="list__sub"><?= e(App\Markup::plain($l['body'], 60)) ?></span><?php endif; ?>
                    </span>
                    <span class="lesson-list__actions">
                        <form class="inline-form" method="post" action="/admin/lessons/<?= (int) $l['id'] ?>/move"><?= csrf_field() ?><input type="hidden" name="direction" value="up"><button type="submit" class="button button--small" aria-label="上へ"<?= $i === 0 ? ' disabled' : '' ?>>↑</button></form>
                        <form class="inline-form" method="post" action="/admin/lessons/<?= (int) $l['id'] ?>/move"><?= csrf_field() ?><input type="hidden" name="direction" value="down"><button type="submit" class="button button--small" aria-label="下へ"<?= $i === count($lessons) - 1 ? ' disabled' : '' ?>>↓</button></form>
                        <a class="button button--small" href="/admin/lessons/<?= (int) $l['id'] ?>/edit">編集</a>
                        <form class="inline-form" method="post" action="/admin/lessons/<?= (int) $l['id'] ?>/delete" onsubmit="return confirm('この回を消します。よろしいですか？');"><?= csrf_field() ?><button type="submit" class="button button--small button--danger">消す</button></form>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="card" id="add-lesson">
    <h2>講座の回を追加</h2>
    <?php if (($addErrors ?? []) !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($addErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="/admin/courses/<?= $id ?>/lessons" class="form">
        <?= csrf_field() ?>
        <?= App\View::render('admin/courses/_lesson_fields', ['course' => $course, 'values' => $addValues ?? ['is_preview' => $lessons === [] ? 1 : 0, 'status' => 'published'], 'prefix' => 'new'], null) ?>
        <div class="actions">
            <button type="submit" class="button button--primary">この回を追加する</button>
            <?php if ($lessons === []): ?><span class="text-muted small">最初の回は「お試し」にしておくと、講座の入口になります</span><?php endif; ?>
        </div>
    </form>
</section>
<script src="/assets/admin-editor.js"></script>

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
