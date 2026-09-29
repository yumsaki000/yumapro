<?php
/** @var string $q */
/** @var array{items: array, total: int, page: int, pages: int} $result */
/** @var int $total */
?>
<section class="card">
    <div class="toolbar">
        <h1>顧客台帳 <span class="text-muted"><?= $total ?>人</span></h1>
        <span class="actions">
            <a class="button" href="/admin/bans">出禁リスト</a>
            <a class="button" href="/admin/customers/duplicates">名寄せ</a>
            <?php if (($admin['role'] ?? '') === 'owner'): ?><a class="button" href="/admin/customers.csv">CSVで書き出す</a><?php endif; ?>
            <a class="button button--primary" href="/admin/customers/new">登録</a>
        </span>
    </div>
    <form method="get" action="/admin/customers" class="searchbar">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="名前・フリガナ・電話番号・メール・SNS">
        <button type="submit" class="button">検索</button>
    </form>
    <?php if ($q !== ''): ?>
        <p class="text-muted"><?= $result['total'] ?>人が見つかりました。<a href="/admin/customers">すべて表示</a></p>
    <?php endif; ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>名前</th><th>性別</th><th>電話</th><th>メール</th><th class="num">参加</th></tr></thead>
            <tbody>
                <?php foreach ($result['items'] as $c): ?>
                    <tr>
                        <td>
                            <a href="/admin/customers/<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a>
                            <?php if ($c['banned_at'] !== null): ?><span class="badge badge--danger">出禁</span><?php endif; ?>
                            <?php if ($c['name_kana'] !== null): ?><br><span class="text-muted"><?= e($c['name_kana']) ?></span><?php endif; ?>
                        </td>
                        <td><?= e(App\Customers::GENDERS[$c['gender']] ?? '—') ?></td>
                        <td><?= e($c['phone'] ?? '—') ?></td>
                        <td><?= e($c['email'] ?? '—') ?></td>
                        <td class="num"><?= (int) $c['registration_count'] ?>回</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($result['pages'] > 1): ?>
        <nav class="pagination">
            <?php if ($result['page'] > 1): ?><a class="button button--small" href="?q=<?= e(rawurlencode($q)) ?>&page=<?= $result['page'] - 1 ?>">前へ</a><?php endif; ?>
            <span class="text-muted"><?= $result['page'] ?> / <?= $result['pages'] ?> ページ</span>
            <?php if ($result['page'] < $result['pages']): ?><a class="button button--small" href="?q=<?= e(rawurlencode($q)) ?>&page=<?= $result['page'] + 1 ?>">次へ</a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
