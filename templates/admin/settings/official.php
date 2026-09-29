<?php
/**
 * 公式サイトとの連携：公式サイト（WordPress）側で差し替えるリンクと、貼るコード。
 *
 * @var string $appUrl このアプリのURL（APP_URL）
 * @var string $officialUrl
 * @var array $types
 */
$official = rtrim($officialUrl, '/');
$links = [
    ['ヘッダーの「イベント参加」ボタン', $official . '/registration/', $appUrl . '/?from=site', 'イベント一覧（掲示板）へ。Googleフォームの代わり'],
    ['ヘッダーの「クルー募集」ボタン', $official . '/crew/', $appUrl . '/crew?from=site', 'クルーの申込フォームへ'],
    ['メニューの「イベントスケジュール」', $official . '/schedule/', $appUrl . '/?from=site', 'Googleカレンダーの代わり。ページに下の埋め込みを貼ってもよい'],
    ['メニューの「MINATOイベント内容」の下のほう', $official . '/event-lists/', $appUrl . '/?from=site', '「イベント一覧を見る」ボタンを足す'],
    ['「5つの基本的欲求チェック」などの講座の案内', '', $appUrl . '/learn', '講座・動画の一覧へ'],
];
$embedAll = '<div data-minato-events></div>' . "\n" . '<script src="' . $appUrl . '/assets/embed.js" async></script>';
$embedTop = '<div data-minato-events data-limit="3"></div>' . "\n" . '<script src="' . $appUrl . '/assets/embed.js" async></script>';
?>
<section class="card">
    <h1>公式サイトとの連携</h1>
    <p>公式サイトは <strong>MINATOを知る場所</strong>、このアプリ（MINATO BRIDGE）は <strong>イベントに参加する場所</strong> です。参加者から見ると、ロゴ・色・メニューが同じひとつのサイトに見えるようにしています。</p>
    <ul class="role-list">
        <li><strong>公式サイト</strong>：MINATOとは・代表挨拶・イベントの考え方・サービス・よくある質問・ブログ・会社概要・規約</li>
        <li><strong>MINATO BRIDGE</strong>：イベント一覧と回のページ・申込・マイページ・クルーの申込・講座と動画</li>
    </ul>
    <p class="text-muted small">参加者向けの画面の名前・メニューに並べる公式サイトのページ・フッターのリンクは<a href="/admin/settings">設定</a>の「公式サイトとのつながり」で変えられます。</p>
</section>

<section class="card">
    <h2>1. 公式サイトのボタン・メニューのリンク先を変える</h2>
    <p class="text-muted">WordPress の管理画面（外観 → メニュー、または各ページの編集）で、次のリンク先に差し替えます。末尾の <code>?from=site</code> で、公式サイトから来た申込が「集計」で分かります。</p>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>場所</th><th>今のリンク先</th><th>新しいリンク先</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($links as $i => [$label, $current, $new, $note]): ?>
                    <tr>
                        <td class="wrap"><?= e($label) ?><br><span class="text-muted small"><?= e($note) ?></span></td>
                        <td class="wrap small"><?= e($current !== '' ? $current : '—') ?></td>
                        <td class="wrap"><code id="link-<?= $i ?>"><?= e($new) ?></code></td>
                        <td><button type="button" class="button button--small" data-copy-target="link-<?= $i ?>">コピー</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>2. 公式サイトのページに、回の一覧を出す（埋め込み）</h2>
    <p class="text-muted">WordPress のページの編集で「カスタムHTML」のブロックを足し、次のコードを貼ります。回を登録・更新すると、公式サイト側も自動で変わります（公式サイトの手直しは不要）。</p>
    <label class="form__field">
        <span class="form__label">募集中の回をすべて出す（「イベントスケジュール」のページなどに）</span>
        <textarea id="embed-all" rows="3" readonly><?= e($embedAll) ?></textarea>
    </label>
    <button type="button" class="button button--small" data-copy-target="embed-all">コピー</button>
    <label class="form__field" style="margin-top: 14px;">
        <span class="form__label">近い3件だけ出す（トップページなどに）</span>
        <textarea id="embed-top" rows="3" readonly><?= e($embedTop) ?></textarea>
    </label>
    <button type="button" class="button button--small" data-copy-target="embed-top">コピー</button>
    <p class="text-muted small" style="margin-top: 12px;">
        形式を絞るときは <code>data-type="…"</code> を足します：
        <?php foreach ($types as $type): ?><code>data-type="<?= e($type['code']) ?>"</code>（<?= e($type['name']) ?>） <?php endforeach; ?>
        <br>埋め込みを出せるサイトは、設定の「埋め込みを許すサイト」に入っているサイトだけです。
    </p>
    <p><a class="button button--small" href="/embed/events?limit=3" target="_blank" rel="noopener">埋め込みの中身を見る</a></p>
</section>

<section class="card">
    <h2>3. 新着として出す（RSS）</h2>
    <p class="text-muted">WordPress の「RSS」ブロックにこの URL を入れると、募集中の回が新着の一覧として出ます（コードを貼れないときに）。</p>
    <p><code id="rss-url"><?= e($appUrl) ?>/feed.xml</code> <button type="button" class="button button--small" data-copy-target="rss-url">コピー</button></p>
</section>

<section class="card">
    <h2>4. 公式LINE から</h2>
    <p class="text-muted">公式LINEの管理画面（LINE Official Account Manager）で、リッチメニュー（トーク画面の下のボタン）やあいさつメッセージに次のリンクを入れます。<code>?from=line</code> で、LINEから来た申込が「集計」で分かります。</p>
    <?php
    $lineLinks = [
        ['イベント一覧（申込）', $appUrl . '/?from=line'],
        ['マイページ（申込の確認・キャンセル）', $appUrl . '/my'],
        ['クルー募集', $appUrl . '/crew?from=line'],
        ['講座・動画', $appUrl . '/learn'],
    ];
    ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>ボタン</th><th>リンク先</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($lineLinks as $i => [$label, $url]): ?>
                    <tr>
                        <td class="wrap"><?= e($label) ?></td>
                        <td class="wrap"><code id="line-<?= $i ?>"><?= e($url) ?></code></td>
                        <td><button type="button" class="button button--small" data-copy-target="line-<?= $i ?>">コピー</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="text-muted small">回ごとのお知らせは、回の画面の「告知に使うリンクと文面」で貼る場所を「MINATO公式LINE」にすると、LINE 用のリンク入りの文面ができます。</p>
</section>

<section class="card">
    <h2>5. こくちーず・SNS から</h2>
    <p class="text-muted">こくちーずは「掲載」だけに使い、申込は回のページに集めます。回の画面の「告知に使うリンクと文面」から、こくちーず用・Instagram 用のリンク入りの告知文をコピーできます。</p>
</section>

<script>
document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var el = document.getElementById(btn.dataset.copyTarget);
        var text = el.value !== undefined ? el.value : el.textContent;
        var label = btn.textContent;
        var done = function () { btn.textContent = 'コピーしました'; setTimeout(function () { btn.textContent = label; }, 1500); };
        if (navigator.clipboard) { navigator.clipboard.writeText(text).then(done); } else { window.prompt('コピーしてください', text); }
    });
});
</script>
