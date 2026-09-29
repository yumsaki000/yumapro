<?php
/**
 * 講座の作成・編集。「誰が見られるか」を最初に選び、有料のときだけ料金を入れる。
 *
 * @var string $heading
 * @var string $action
 * @var array|null $course
 * @var array $values
 * @var list<string> $errors
 */
$access = $values['access'] ?? 'crew';
?>
<section class="card">
    <h1><?= e($heading) ?></h1>
    <?php if ($errors !== []): ?><div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="<?= e($action) ?>" class="form">
        <?= csrf_field() ?>
        <label class="form__field">
            <span class="form__label">講座のタイトル <span class="req">必須</span></span>
            <input type="text" name="title" value="<?= e($values['title'] ?? '') ?>" maxlength="200" required placeholder="例：タイプ分けと5つの欲求">
        </label>

        <div class="form__field">
            <span class="form__label">この講座を見られる人</span>
            <div class="status-choice status-choice--wide">
                <?php foreach (App\Courses::ACCESS as $code => $label): ?>
                    <label class="status-choice__item">
                        <input type="radio" name="access" value="<?= e($code) ?>"<?= $access === $code ? ' checked' : '' ?>>
                        <span><strong><?= e($label) ?></strong><small><?= e(App\Courses::ACCESS_HELP[$code]) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <span class="form__help">どの設定でも、各回を「お試し」にすると、その回だけ誰でも見られます（例：第1回だけお試しにして、続きはクルー専用）</span>
        </div>

        <div class="paid-box" data-show-when="access=paid">
            <label class="form__field">
                <span class="form__label">料金（円）</span>
                <input type="number" name="price" value="<?= e((string) ($values['price'] ?? '')) ?>" min="0" inputmode="numeric" placeholder="例：5000">
                <span class="form__help">買いたい人には振込先をメールで送り、運営が入金を確認すると見られるようになります（「購入」の画面で確認）</span>
            </label>
            <label class="form__check"><input type="checkbox" name="crew_included" value="1"<?= (int) ($values['crew_included'] ?? 1) === 1 ? ' checked' : '' ?>> <span>クルーは買わずに見られる（クルーの特典にする）</span></label>
        </div>

        <div class="form__field">
            <label class="form__label" for="course-description">講座の紹介（一覧と講座のページに出す）</label>
            <div class="editor-bar" data-target="course-description">
                <button type="button" class="button button--small" data-insert="heading">■ 見出し</button>
                <button type="button" class="button button--small" data-insert="bullet">・ 箇条書き</button>
                <button type="button" class="button button--small" data-insert="bold">太字</button>
            </div>
            <textarea name="description" id="course-description" rows="6" placeholder="例：&#10;自分の行動のクセと、心の奥にある5つの欲求を知る講座です。&#10;&#10;■ こんな方に&#10;・人間関係で同じところでつまずく&#10;・自分に合った働き方を知りたい"><?= e($values['description'] ?? '') ?></textarea>
            <span class="form__help">行の頭に「■」で見出し、「・」で箇条書き。一覧のカードには最初の数行が出ます</span>
        </div>

        <div class="form__field">
            <span class="form__label">公開</span>
            <div class="status-choice">
                <?php foreach (['draft' => '準備中は下書き。参加者には出ません', 'published' => '「講座・動画」に出ます'] as $code => $help): ?>
                    <label class="status-choice__item">
                        <input type="radio" name="status" value="<?= e($code) ?>"<?= ($values['status'] ?? 'draft') === $code ? ' checked' : '' ?>>
                        <span><strong><?= e(App\Courses::STATUSES[$code]) ?></strong><small><?= e($help) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <details class="form__more">
            <summary>講座の並び順を変える</summary>
            <label class="form__field"><span class="form__label">並び順（小さいほど上）</span><input type="number" name="sort_order" value="<?= (int) ($values['sort_order'] ?? 0) ?>" inputmode="numeric"></label>
        </details>
        <div class="form-actions">
            <button type="submit" class="button button--primary"><?= $course === null ? '作って、回を追加する' : '保存する' ?></button>
            <a class="button button--ghost" href="<?= $course === null ? '/admin/courses' : '/admin/courses/' . (int) $course['id'] ?>">戻る</a>
        </div>
    </form>
</section>
<script src="/assets/admin-editor.js"></script>
