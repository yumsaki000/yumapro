<?php
/**
 * 回の作成・編集。こくちーずに載せる感覚で、上から順に埋めれば回のページができるように並べる。
 * 掲載する内容はどれも任意（空欄なら回のページに出ない）。
 *
 * @var string $heading
 * @var string $action
 * @var array $types
 * @var array $values
 * @var list<string> $errors
 * @var array|null $event 編集のとき
 * @var list<array{id: int, name: string}> $photos 表紙のほかの写真
 * @var array $latest 形式ごとの一番新しい回（新規のとき「前の回をもとに作る」に出す）
 */
$v = fn (string $key) => e($values[$key] ?? '');
$isNew = $event === null;
$galleryRoom = App\Events::MAX_GALLERY - count($photos);
$steps = ['basic' => '基本', 'photos' => '写真', 'content' => '掲載する内容', 'place' => '場所', 'fee' => '定員と参加費', 'deadline' => '締切とキャンセル', 'publish' => '公開'];
?>
<?php if ($isNew && $latest !== []): ?>
    <section class="card">
        <h2>前の回をもとに作る（おすすめ）</h2>
        <p class="text-muted">同じ形式の前回の内容（説明・写真・料金など）をそのまま写して、下書きを作ります。日時と変わるところだけ直せば完成です。</p>
        <ul class="base-list">
            <?php foreach ($latest as $base): ?>
                <li>
                    <span><span class="badge"><?= e($base['type_name']) ?></span> <?= e($base['title']) ?> <span class="text-muted small"><?= e(fmt_dt($base['starts_at'], false)) ?></span></span>
                    <form class="inline-form" method="post" action="/admin/events/<?= (int) $base['id'] ?>/copy">
                        <?= csrf_field() ?>
                        <button type="submit" class="button button--small">これをもとに作る</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="text-muted small">ほかの回をもとにするときは、回の画面の「複製」から。白紙から作るときは、下のフォームに入れてください。</p>
    </section>
<?php endif; ?>

<section class="card">
    <h1><?= e($heading) ?></h1>
    <?php if ($errors !== []): ?>
        <div class="alert alert--error" role="alert"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <p class="text-muted small">写真を選んでいたときは、お手数ですがもう一度選んでください。</p>
    <?php endif; ?>

    <nav class="step-nav" aria-label="入力の段落">
        <?php $n = 1; foreach ($steps as $id => $label): ?>
            <a href="#step-<?= e($id) ?>"><?= $n++ ?>. <?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="<?= e($action) ?>" class="form event-form" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <h2 class="step" id="step-basic"><span>1</span>基本</h2>
        <div class="form__row">
            <label class="form__field">
                <span class="form__label">形式</span>
                <select name="event_type_id" id="event_type_id">
                    <?php foreach ($types as $type): ?>
                        <option value="<?= (int) $type['id'] ?>" data-payment="<?= e($type['payment_timing']) ?>" data-code="<?= e($type['code']) ?>"<?= (int) ($values['event_type_id'] ?? 0) === (int) $type['id'] ? ' selected' : '' ?>><?= e($type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="form__field">
                <span class="form__label">第n回（任意）</span>
                <input type="number" name="round_no" value="<?= $v('round_no') ?>" min="0" inputmode="numeric">
            </label>
        </div>
        <label class="form__field">
            <span class="form__label">タイトル <span class="req">必須</span></span>
            <input type="text" name="title" value="<?= $v('title') ?>" maxlength="200" required placeholder="例：【渋谷】自分の可能性が広がる女子会｜価値観カードでワクワクトーク｜初参加・おひとり歓迎">
            <span class="form__help">場所・中身・安心できること（初参加歓迎など）を入れると、こくちーずや検索で見つけてもらいやすくなります</span>
        </label>
        <div class="form__row">
            <label class="form__field">
                <span class="form__label">開始日時 <span class="req">必須</span></span>
                <input type="datetime-local" name="starts_at" value="<?= e(dt_input($values['starts_at'] ?? null)) ?>" required>
            </label>
            <label class="form__field">
                <span class="form__label">終了日時（任意）</span>
                <input type="datetime-local" name="ends_at" value="<?= e(dt_input($values['ends_at'] ?? null)) ?>">
            </label>
        </div>

        <h2 class="step" id="step-photos"><span>2</span>写真</h2>
        <div class="form__field">
            <span class="form__label">表紙の写真（一覧のカードと回のページの一番上）</span>
            <?php if (!$isNew && $event['photo'] !== null): ?>
                <img class="photo-preview" src="<?= e(App\Photos::url($event['photo'])) ?>" alt="">
                <label class="form__check"><input type="checkbox" name="remove_photo" value="1"> <span>表紙の写真を消す</span></label>
            <?php endif; ?>
            <input type="file" name="photo" accept="image/*">
            <span class="form__help">スマホの写真をそのまま選べます（8MBまで。自動で縮めます）。ないときは形式の色で埋めます</span>
        </div>
        <div class="form__field">
            <span class="form__label">そのほかの写真（回のページに並べる。<?= App\Events::MAX_GALLERY ?>枚まで）</span>
            <?php if ($photos !== []): ?>
                <div class="photo-manage">
                    <?php foreach ($photos as $photo): ?>
                        <div class="photo-manage__item">
                            <img src="<?= e(App\Photos::url($photo['name'])) ?>" alt="">
                            <label class="form__check"><input type="radio" name="cover_photo" value="<?= (int) $photo['id'] ?>"> <span>表紙にする</span></label>
                            <label class="form__check"><input type="checkbox" name="remove_photos[]" value="<?= (int) $photo['id'] ?>"> <span>消す</span></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($galleryRoom > 0): ?>
                <input type="file" name="photos[]" accept="image/*" multiple>
                <span class="form__help">まとめて選べます（あと<?= $galleryRoom ?>枚）。前回の様子の写真があると、初めての人が安心します</span>
            <?php else: ?>
                <span class="form__help">上限まで入っています。足すときは、どれかを消してください</span>
            <?php endif; ?>
        </div>

        <h2 class="step" id="step-content"><span>3</span>掲載する内容 <small>どれも任意。空欄の欄は回のページに出ません</small></h2>
        <p class="form__help">
            <button type="button" class="button button--small" id="fill-template">ひな形を入れる</button>
            空いている欄に、書き方の見本（こくちーずの掲載ページと同じ流れ）を入れます
        </p>
        <label class="form__field">
            <span class="form__label">一言紹介</span>
            <textarea name="summary" rows="3" maxlength="300" data-counter="summary-count" placeholder="例：価値観カードで自分の「好き」や「大切」を言葉にする女子会。手作りお菓子つき、少人数でゆったり。初参加・おひとり参加も歓迎です。"><?= $v('summary') ?></textarea>
            <span class="form__help">一覧のカード・回のページの上・LINEなどで共有したときに出ます。<span id="summary-count"></span>（60〜120文字くらいがおすすめ）</span>
        </label>
        <div class="form__field">
            <label class="form__label" for="highlights">安心ポイント・特徴（1行に1つ）</label>
            <textarea name="highlights" id="highlights" rows="4" placeholder="初参加歓迎&#10;おひとり参加歓迎&#10;強引な勧誘は一切なし"><?= $v('highlights') ?></textarea>
            <div class="suggest" data-target="highlights">
                <span class="form__help">タップで追加：</span>
                <?php foreach (App\Events::HIGHLIGHT_SUGGESTIONS as $word): ?>
                    <button type="button" class="suggest__item"><?= e($word) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="form__field">
            <label class="form__label" for="description">イベント内容</label>
            <div class="editor-bar" data-target="description">
                <button type="button" class="button button--small" data-insert="heading">■ 見出し</button>
                <button type="button" class="button button--small" data-insert="bullet">・ 箇条書き</button>
                <button type="button" class="button button--small" data-insert="bold">太字</button>
                <button type="button" class="button button--small" data-insert="line">区切り線</button>
            </div>
            <textarea name="description" id="description" rows="12" placeholder="例：&#10;「最近、なんだかモヤモヤする」そんな感覚、ありませんか？&#10;&#10;■ 当日の進め方&#10;・質問カードを1枚引きます&#10;・カードから自分の答えを選びます&#10;&#10;■ 参加すると得られるもの&#10;・自分の価値観が言語化される"><?= $v('description') ?></textarea>
            <span class="form__help">行の頭に「■」で見出し、「・」で箇条書き、**ことば** で太字。URL はリンクになります。空行で段落が分かれます</span>
        </div>
        <label class="form__field">
            <span class="form__label">こんな方におすすめ（1行に1つ）</span>
            <textarea name="recommend" rows="4" placeholder="自分の人生を楽しみたい人&#10;新しい挑戦をしている／したい人"><?= $v('recommend') ?></textarea>
        </label>
        <label class="form__field">
            <span class="form__label">タイムスケジュール（1行に「時刻 内容」）</span>
            <textarea name="timetable" rows="5" placeholder="14:00 自己紹介&#10;14:30 価値観カードでワクワクトーク&#10;15:50 写真撮影&#10;16:00 終了"><?= $v('timetable') ?></textarea>
            <span class="form__help">「14:00〜 受付」「14時 開始」の書き方でも読めます。時刻のない行は、前の行の続きになります</span>
        </label>
        <label class="form__field">
            <span class="form__label">持ち物・服装</span>
            <input type="text" name="belongings" value="<?= $v('belongings') ?>" maxlength="300" placeholder="例：特にありません（手ぶらでOK）">
        </label>
        <details class="form__more"<?= trim((string) ($values['faq'] ?? '')) !== '' ? ' open' : '' ?>>
            <summary>よくある質問をこの回だけ変える</summary>
            <label class="form__field">
                <span class="form__label">よくある質問（Q. と A. の行を交互に）</span>
                <textarea name="faq" rows="6" placeholder="Q. 一人で参加しても大丈夫？&#10;A. はい、ほとんどの方が一人参加です。"><?= $v('faq') ?></textarea>
                <span class="form__help">空欄なら「設定」の共通のよくある質問を出します</span>
            </label>
        </details>

        <h2 class="step" id="step-place"><span>4</span>場所</h2>
        <div class="form__row">
            <label class="form__field">
                <span class="form__label">アクセス（ページに出す目安）</span>
                <input type="text" name="access" value="<?= $v('access') ?>" maxlength="200" placeholder="例：駒込駅 徒歩3分">
            </label>
            <label class="form__field">
                <span class="form__label">地図に出す場所</span>
                <input type="text" name="map_query" value="<?= $v('map_query') ?>" maxlength="200" placeholder="例：駒込駅">
                <span class="form__help">駅名などを入れると地図が出ます。空欄なら地図なし</span>
            </label>
        </div>
        <label class="form__field">
            <span class="form__label">会場名</span>
            <input type="text" name="venue_name" value="<?= $v('venue_name') ?>" maxlength="200" placeholder="例：駒込のコミュニティスペース">
        </label>
        <div class="private-box">
            <p class="private-box__title">申込んだ人にだけ見せる（ページには出ません）</p>
            <label class="form__field">
                <span class="form__label">住所</span>
                <input type="text" name="venue_address" value="<?= $v('venue_address') ?>" maxlength="255">
            </label>
            <label class="form__field">
                <span class="form__label">地図・道順のURL</span>
                <input type="url" name="venue_url" value="<?= $v('venue_url') ?>" maxlength="500" inputmode="url">
            </label>
        </div>

        <h2 class="step" id="step-fee"><span>5</span>定員と参加費</h2>
        <div class="form__row">
            <label class="form__field">
                <span class="form__label">定員（空欄なら上限なし）</span>
                <input type="number" name="capacity" value="<?= $v('capacity') ?>" min="0" inputmode="numeric">
            </label>
            <label class="form__field">
                <span class="form__label">参加費（円） <span class="req">必須</span></span>
                <input type="number" name="fee" value="<?= $v('fee') ?>" min="0" inputmode="numeric" required>
            </label>
        </div>
        <div class="form__row">
            <label class="form__field">
                <span class="form__label">クルー料金（円。任意）</span>
                <input type="number" name="fee_crew" value="<?= $v('fee_crew') ?>" min="0" inputmode="numeric">
                <span class="form__help">加入中のクルーが申し込むと、自動でこの料金になります</span>
            </label>
            <label class="form__field">
                <span class="form__label">支払い</span>
                <select name="payment_timing" id="payment_timing">
                    <?php foreach (App\Events::PAYMENT_TIMINGS as $code => $label): ?>
                        <option value="<?= e($code) ?>"<?= ($values['payment_timing'] ?? '') === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <details class="form__more"<?= ($values['capacity_male'] ?? null) !== null || ($values['fee_male'] ?? null) !== null || ($values['capacity_female'] ?? null) !== null || ($values['fee_female'] ?? null) !== null ? ' open' : '' ?>>
            <summary>男女で定員・参加費を分ける（合コンなど）</summary>
            <div class="form__row">
                <label class="form__field">
                    <span class="form__label">男性の定員</span>
                    <input type="number" name="capacity_male" value="<?= $v('capacity_male') ?>" min="0" inputmode="numeric">
                </label>
                <label class="form__field">
                    <span class="form__label">女性の定員</span>
                    <input type="number" name="capacity_female" value="<?= $v('capacity_female') ?>" min="0" inputmode="numeric">
                </label>
            </div>
            <div class="form__row">
                <label class="form__field">
                    <span class="form__label">男性の参加費（円）</span>
                    <input type="number" name="fee_male" value="<?= $v('fee_male') ?>" min="0" inputmode="numeric">
                </label>
                <label class="form__field">
                    <span class="form__label">女性の参加費（円）</span>
                    <input type="number" name="fee_female" value="<?= $v('fee_female') ?>" min="0" inputmode="numeric">
                </label>
            </div>
        </details>

        <h2 class="step" id="step-deadline"><span>6</span>締切とキャンセル</h2>
        <div class="form__row">
            <label class="form__field">
                <span class="form__label">申込締切（任意）</span>
                <input type="datetime-local" name="apply_deadline" value="<?= e(dt_input($values['apply_deadline'] ?? null)) ?>">
            </label>
            <label class="form__field">
                <span class="form__label">キャンセル期限（任意）</span>
                <input type="datetime-local" name="cancel_deadline" value="<?= e(dt_input($values['cancel_deadline'] ?? null)) ?>">
                <?php if ($isNew): ?><span class="form__help">前払いの回で空欄なら、開始の7日前を入れます</span><?php endif; ?>
            </label>
        </div>
        <label class="form__field">
            <span class="form__label">キャンセル規定（この回だけ変えるとき）</span>
            <textarea name="cancel_policy" rows="3" placeholder="空欄なら「設定」の共通のキャンセル規定を出します"><?= $v('cancel_policy') ?></textarea>
        </label>

        <h2 class="step" id="step-publish"><span>7</span>公開</h2>
        <div class="status-choice">
            <?php foreach (App\Events::STATUSES as $code => $label): ?>
                <label class="status-choice__item">
                    <input type="radio" name="status" value="<?= e($code) ?>"<?= ($values['status'] ?? 'draft') === $code ? ' checked' : '' ?>>
                    <span><strong><?= e($label) ?></strong><small><?= e(App\Events::STATUS_HELP[$code] ?? '') ?></small></span>
                </label>
            <?php endforeach; ?>
        </div>
        <label class="form__field">
            <span class="form__label">主催分（円）</span>
            <input type="number" name="organizer_amount" value="<?= $v('organizer_amount') ?>" min="0" inputmode="numeric">
            <span class="form__help">収支 ＝ 収入 − 経費 − 主催分。会計の画面でも変えられます</span>
        </label>

        <div class="form-actions">
            <button type="submit" class="button button--primary">保存する</button>
            <button type="submit" class="button" name="after" value="preview">保存してページを確認</button>
            <a class="button button--ghost" href="<?= $isNew ? '/admin/events' : '/admin/events/' . (int) $event['id'] ?>">戻る</a>
        </div>
    </form>
</section>

<script src="/assets/admin-editor.js"></script>
<script>
(function () {
    var typeSelect = document.getElementById('event_type_id');
    <?php if ($isNew): ?>
    // 形式を変えたら、その形式の既定の支払い方法にする（新規のときだけ）
    typeSelect.addEventListener('change', function () {
        var payment = this.options[this.selectedIndex].dataset.payment;
        if (payment) { document.getElementById('payment_timing').value = payment; }
    });
    <?php endif; ?>

    // ひな形：空いている欄にだけ、書き方の見本を入れる
    var templates = {
        summary: '（どんな回かを1〜2文で。例：少人数でゆったり話せる女子会です。初参加・おひとり参加も歓迎です。）',
        highlights: '初参加歓迎\nおひとり参加歓迎\n強引な勧誘は一切なし',
        description: '（読む人が「自分のことだ」と思える問いかけを1〜2行）\n\n■ どんなイベント？\n（何をするか、どんな雰囲気か）\n\n■ 当日の進め方\n・\n・\n・\n\n■ 参加すると得られるもの\n・\n・',
        recommend: '（どんな人に来てほしいか）\n（1行に1つ）',
        timetable: '19:00 受付・自己紹介\n19:30 メインの内容\n20:50 写真撮影\n21:00 終了',
        belongings: '特にありません（手ぶらでOK）'
    };
    document.getElementById('fill-template').addEventListener('click', function () {
        var filled = 0;
        Object.keys(templates).forEach(function (name) {
            var field = document.querySelector('[name="' + name + '"]');
            if (field && field.value.trim() === '') { field.value = templates[name]; filled++; }
        });
        this.textContent = filled ? 'ひな形を入れました（（ ）の中を書きかえてください）' : '空いている欄がありません';
    });
})();
</script>
