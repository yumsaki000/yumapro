<?php
/** @var array $channels */
/** @var list<string> $errors */
/** @var string $newName */
?>
<section class="card">
    <h1>「どこで知りましたか」の選択肢</h1>
    <p class="text-muted">申込フォームと手入力で選ぶ選択肢です。使わなくなったものは「表示」を外します（過去の記録はそのまま残ります）。番号が小さい順に並びます。</p>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="/admin/channels" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="table-wrap">
            <table class="table table--compact">
                <thead><tr><th class="num">順</th><th>選択肢</th><th>表示</th></tr></thead>
                <tbody>
                    <?php foreach ($channels as $c): ?>
                        <tr class="<?= (int) $c['is_active'] === 1 ? '' : 'is-muted' ?>">
                            <td class="num"><input class="input--short" type="number" name="sort_order[<?= (int) $c['id'] ?>]" value="<?= (int) $c['sort_order'] ?>" inputmode="numeric"></td>
                            <td><?= e($c['name']) ?></td>
                            <td><input type="checkbox" name="is_active[<?= (int) $c['id'] ?>]" value="1"<?= (int) $c['is_active'] === 1 ? ' checked' : '' ?>></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="actions">
            <button type="submit" class="button button--primary">並び順と表示を保存する</button>
        </div>
    </form>
</section>

<section class="card">
    <h2>選択肢を追加</h2>
    <form method="post" action="/admin/channels" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label class="form__field">
            <span class="form__label">名前</span>
            <input type="text" name="name" value="<?= e($newName) ?>" maxlength="50" required>
        </label>
        <button type="submit" class="button">追加する</button>
    </form>
</section>
