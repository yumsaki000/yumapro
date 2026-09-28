<?php
/** @var array $admin */
$menu = [
    ['回の一覧', '回の作成・複製、申込者の一覧'],
    ['顧客台帳', '顧客の検索、参加履歴、名寄せ'],
    ['当日受付', '到着と入金の記録'],
    ['会計', '経費の入力と収支'],
    ['問い合わせ', '問い合わせの対応状況'],
];
?>
<section class="card">
    <h1>ようこそ、<?= e($admin['display_name']) ?> さん</h1>
    <p>ここから各機能に進みます。機能は順番に追加していきます。</p>

    <ul class="menu">
        <?php foreach ($menu as [$name, $description]): ?>
            <li class="menu__item menu__item--disabled">
                <span class="menu__name"><?= e($name) ?></span>
                <span class="menu__description"><?= e($description) ?></span>
                <span class="menu__badge">準備中</span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
