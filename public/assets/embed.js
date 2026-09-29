/*
 * 公式サイトなど、ほかのサイトに MINATO のイベント一覧を出すための部品。
 * 出したい場所に次の2行を貼る（WordPress なら「カスタムHTML」ブロック）。
 *
 *   <div data-minato-events></div>
 *   <script src="https://event.minatocrew.com/assets/embed.js" async></script>
 *
 * data-type="joshikai" で形式を絞り、data-limit="3" で件数を絞れる。
 */
(function () {
    var script = document.currentScript || document.querySelector('script[src*="/assets/embed.js"]');
    if (!script) { return; }
    var origin = new URL(script.src, location.href).origin;
    var frames = [];

    document.querySelectorAll('[data-minato-events]').forEach(function (box) {
        if (box.dataset.minatoReady) { return; }
        box.dataset.minatoReady = '1';
        var params = new URLSearchParams();
        if (box.dataset.type) { params.set('type', box.dataset.type); }
        if (box.dataset.limit) { params.set('limit', box.dataset.limit); }
        var frame = document.createElement('iframe');
        frame.src = origin + '/embed/events' + (params.toString() ? '?' + params : '');
        frame.title = 'イベント一覧';
        frame.loading = 'lazy';
        frame.style.cssText = 'display:block;width:100%;height:480px;border:0;overflow:hidden;';
        frame.setAttribute('scrolling', 'no');
        box.appendChild(frame);
        frames.push(frame);
    });

    // 中身から届いた高さに合わせる（届いたのが自分の iframe からのときだけ）
    window.addEventListener('message', function (ev) {
        if (ev.origin !== origin || !ev.data || ev.data.type !== 'minato-embed-height') { return; }
        frames.forEach(function (frame) {
            if (frame.contentWindow === ev.source) {
                frame.style.height = Math.max(120, Math.ceil(ev.data.height)) + 'px';
            }
        });
    });
})();
