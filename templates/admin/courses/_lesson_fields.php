<?php
/**
 * 講座の回の入力欄（追加と編集で共通）。動画の URL を貼り、動画の下に出す説明を書く。
 *
 * @var array $course
 * @var array $values
 * @var string $prefix 同じ画面に2つあっても id がぶつからないように
 */
// 誤りで戻ったときは書いたままの URL を、そうでなければ保存した動画の URL を出す
$youtubeValue = $values['youtube_raw'] ?? (($values['youtube_id'] ?? null) !== null ? 'https://youtu.be/' . $values['youtube_id'] : '');
$preview = (int) ($values['is_preview'] ?? 0) === 1;
?>
<label class="form__field">
    <span class="form__label">講座の回のタイトル <span class="req">必須</span></span>
    <input type="text" name="title" value="<?= e($values['title'] ?? '') ?>" maxlength="200" required placeholder="例：第1回 自分のタイプを知ろう">
</label>
<label class="form__field">
    <span class="form__label">YouTube の動画（URL を貼る）</span>
    <input type="text" name="youtube" value="<?= e($youtubeValue) ?>" placeholder="https://youtu.be/xxxxxxxxxxx" inputmode="url" data-youtube-preview="<?= e($prefix) ?>-yt">
    <span class="yt-preview" id="<?= e($prefix) ?>-yt"></span>
    <span class="form__help">YouTube で「限定公開」にした動画の「共有」→「コピー」の URL。動画なし（文章だけの回）でも大丈夫です</span>
</label>
<div class="form__field">
    <label class="form__label" for="<?= e($prefix) ?>-body">動画の下に出す説明（内容・ポイント・資料など）</label>
    <div class="editor-bar" data-target="<?= e($prefix) ?>-body">
        <button type="button" class="button button--small" data-insert="heading">■ 見出し</button>
        <button type="button" class="button button--small" data-insert="bullet">・ 箇条書き</button>
        <button type="button" class="button button--small" data-insert="bold">太字</button>
        <button type="button" class="button button--small" data-insert="line">区切り線</button>
    </div>
    <textarea name="body" id="<?= e($prefix) ?>-body" rows="8" placeholder="例：&#10;■ この回の内容&#10;・4つのタイプの特徴&#10;・自分のタイプの見つけ方&#10;&#10;■ ワーク&#10;動画を見ながら、ワークシートに書き込んでみましょう。&#10;https://example.com/worksheet"><?= e($values['body'] ?? '') ?></textarea>
    <span class="form__help">行の頭に「■」で見出し、「・」で箇条書き、**ことば** で太字。URL はリンクになります</span>
</div>
<div class="form__field">
    <span class="form__label">この回を見られる人</span>
    <div class="status-choice status-choice--wide">
        <label class="status-choice__item">
            <input type="radio" name="is_preview" value="0"<?= !$preview ? ' checked' : '' ?>>
            <span><strong>講座の設定どおり（<?= e(App\Courses::ACCESS[$course['access']] ?? '') ?>）</strong><small><?= e(App\Courses::ACCESS_HELP[$course['access']] ?? '') ?></small></span>
        </label>
        <label class="status-choice__item">
            <input type="radio" name="is_preview" value="1"<?= $preview ? ' checked' : '' ?>>
            <span><strong>お試し（無料公開）</strong><small>この回だけ誰でも見られます。続きを見たくなる入口に</small></span>
        </label>
    </div>
</div>
<div class="form__field">
    <span class="form__label">公開</span>
    <div class="status-choice">
        <?php foreach (['published' => '講座のページに出ます', 'draft' => '準備中。参加者には出ません'] as $code => $help): ?>
            <label class="status-choice__item">
                <input type="radio" name="status" value="<?= e($code) ?>"<?= ($values['status'] ?? 'published') === $code ? ' checked' : '' ?>>
                <span><strong><?= e(App\Courses::STATUSES[$code]) ?></strong><small><?= e($help) ?></small></span>
            </label>
        <?php endforeach; ?>
    </div>
</div>
