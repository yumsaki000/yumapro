<?php
/** @var string $heading */
/** @var string $action */
/** @var array|null $customer */
/** @var array $values */
/** @var list<string> $errors */
/** @var array $candidates 同じ人かもしれない顧客 */
/** @var array $channels */
?>
<section class="card">
    <h1><?= e($heading) ?></h1>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="<?= e($action) ?>" class="form" autocomplete="off">
        <?= csrf_field() ?>
        <?php require APP_ROOT . '/templates/admin/customers/_fields.php'; ?>

        <?php if ($candidates !== []): ?>
            <div class="warning-box">
                <strong>同じ人かもしれない顧客がいます</strong>
                <ul>
                    <?php foreach ($candidates as $c): ?>
                        <li><a href="/admin/customers/<?= (int) $c['id'] ?>" target="_blank"><?= e($c['name']) ?></a><?= $c['name_kana'] !== null ? '（' . e($c['name_kana']) . '）' : '' ?>　電話 <?= e($c['phone'] ?? '—') ?>　メール <?= e($c['email'] ?? '—') ?></li>
                    <?php endforeach; ?>
                </ul>
                <label class="form__check"><input type="checkbox" name="confirm_duplicate" value="1"> <span>別の人として登録する</span></label>
            </div>
        <?php endif; ?>

        <div class="actions">
            <button type="submit" class="button button--primary">保存する</button>
            <a class="button" href="<?= $customer === null ? '/admin/customers' : '/admin/customers/' . (int) $customer['id'] ?>">戻る</a>
        </div>
    </form>
</section>
