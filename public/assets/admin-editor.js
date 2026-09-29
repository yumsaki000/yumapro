/*
 * 管理画面の入力を助ける部品（回の登録・講座の登録で共通）。
 *   .editor-bar[data-target]   本文の書式ボタン（■見出し・「・」箇条書き・太字・区切り線）
 *   [data-counter]             文字数を出す
 *   .suggest[data-target]      候補をタップで1行ずつ足す
 *   [data-youtube-preview]     YouTube の URL を貼ったら、その場でサムネイルを出す
 *   [data-show-when]           選んだ値のときだけ出す欄（例：有料のときだけ料金）
 */
(function () {
    document.querySelectorAll('.editor-bar').forEach(function (bar) {
        var area = document.getElementById(bar.dataset.target);
        bar.querySelectorAll('[data-insert]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var start = area.selectionStart, end = area.selectionEnd, text = area.value;
                var kind = btn.dataset.insert, insert, caret;
                if (kind === 'bold') {
                    var picked = text.slice(start, end) || '太字にすることば';
                    insert = '**' + picked + '**';
                    area.value = text.slice(0, start) + insert + text.slice(end);
                    caret = start + insert.length;
                } else {
                    var lineStart = text.lastIndexOf('\n', start - 1) + 1;
                    insert = kind === 'heading' ? '■ ' : kind === 'bullet' ? '・' : '---\n';
                    area.value = text.slice(0, lineStart) + insert + text.slice(lineStart);
                    caret = end + insert.length;
                }
                area.focus();
                area.setSelectionRange(caret, caret);
            });
        });
    });

    document.querySelectorAll('[data-counter]').forEach(function (area) {
        var out = document.getElementById(area.dataset.counter);
        if (!out) { return; }
        var show = function () { out.textContent = 'いま' + area.value.length + '文字'; };
        area.addEventListener('input', show);
        show();
    });

    document.querySelectorAll('.suggest').forEach(function (box) {
        var target = document.getElementById(box.dataset.target);
        box.querySelectorAll('.suggest__item').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var lines = target.value.split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
                if (lines.indexOf(btn.textContent) === -1) { lines.push(btn.textContent); }
                target.value = lines.join('\n');
                btn.classList.add('is-used');
            });
        });
    });

    function youtubeId(value) {
        value = (value || '').trim();
        if (/^[A-Za-z0-9_-]{11}$/.test(value)) { return value; }
        var m = value.match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/))([A-Za-z0-9_-]{11})/);
        return m ? m[1] : null;
    }
    document.querySelectorAll('[data-youtube-preview]').forEach(function (input) {
        var box = document.getElementById(input.dataset.youtubePreview);
        var show = function () {
            var id = youtubeId(input.value);
            if (!input.value.trim()) { box.innerHTML = ''; return; }
            box.innerHTML = id
                ? '<img src="https://i.ytimg.com/vi/' + id + '/mqdefault.jpg" alt=""><span>この動画を出します（ID：' + id + '）</span>'
                : '<span class="yt-preview__error">YouTube の URL の形ではないようです。YouTube の「共有」でコピーした URL を貼ってください</span>';
        };
        input.addEventListener('input', show);
        show();
    });

    document.querySelectorAll('[data-show-when]').forEach(function (block) {
        var parts = block.dataset.showWhen.split('=');
        var radios = document.querySelectorAll('input[name="' + parts[0] + '"]');
        var update = function () {
            var checked = document.querySelector('input[name="' + parts[0] + '"]:checked');
            block.hidden = !checked || checked.value !== parts[1];
        };
        radios.forEach(function (r) { r.addEventListener('change', update); });
        update();
    });
})();
