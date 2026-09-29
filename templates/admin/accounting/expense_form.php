<?php
/** @var array $expense */
/** @var ?array $event */
/** @var array $values */
/** @var list<string> $errors */
/** @var list<string> $items */
/** @var string $back */
?>
<section class="card card--narrow">
    <h1>経費を直す</h1>
    <p class="text-muted"><?= $event !== null ? e($event['title']) . '（' . e(fmt_dt($event['starts_at'], false)) . '）の経費' : 'イベントに付かない経費' ?></p>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="/admin/expenses/<?= (int) $expense['id'] ?>/edit" class="form">
        <?= csrf_field() ?>
        <?= App\View::render('admin/accounting/_expense_fields', ['values' => $values, 'items' => $items, 'needsDate' => $event === null, 'prefix' => 'edit'], null) ?>
        <div class="actions">
            <button type="submit" class="button button--primary">保存する</button>
            <a class="button" href="<?= e($back) ?>">戻る</a>
        </div>
    </form>
</section>
