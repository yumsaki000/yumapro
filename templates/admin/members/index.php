<?php /** @var array $members */ ?>
<section class="card">
    <div class="toolbar">
        <h1>運営メンバー</h1>
        <a class="button button--primary" href="/admin/members/new">追加</a>
    </div>
    <p class="text-muted">アカウントは1人ずつ発行します。使わなくなった人は「無効」にします（消しません）。</p>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>表示名</th><th>ログインID</th><th>権限</th><th>最終ログイン</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                    <tr class="<?= (int) $m['is_active'] === 1 ? '' : 'is-muted' ?>">
                        <td>
                            <?= e($m['display_name']) ?>
                            <?php if ((int) $m['is_active'] !== 1): ?><span class="badge">無効</span><?php endif; ?>
                        </td>
                        <td><?= e($m['login_id']) ?></td>
                        <td><?= e(App\Admins::ROLES[$m['role']] ?? $m['role']) ?></td>
                        <td><?= e(fmt_dt($m['last_login_at'])) ?></td>
                        <td><a href="/admin/members/<?= (int) $m['id'] ?>">編集</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
