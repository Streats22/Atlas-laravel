(function () {
    'use strict';
    var script = document.currentScript;
    var delay = Math.max(0, parseInt((script && script.dataset.delay) || '5', 10)) * 1000;
    var key = @json($key);
    try { if (localStorage.getItem(key)) return; } catch (e) {}

    function close(overlay) {
        overlay.remove();
        try { localStorage.setItem(key, String(Date.now())); } catch (e) {}
    }

    setTimeout(function () {
        var overlay = document.createElement('div');
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483647;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;padding:16px';
        var box = document.createElement('div');
        box.style.cssText = 'position:relative;width:100%;max-width:460px;height:min(560px,90vh);background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3)';
        var frame = document.createElement('iframe');
        frame.src = @json($url);
        frame.title = 'Subscribe';
        frame.style.cssText = 'width:100%;height:100%;border:0';
        var button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('aria-label', 'Close');
        button.textContent = '×';
        button.style.cssText = 'position:absolute;top:6px;right:10px;font-size:26px;line-height:1;background:none;border:0;cursor:pointer;color:#555';
        button.onclick = function () { close(overlay); };
        overlay.onclick = function (e) { if (e.target === overlay) close(overlay); };
        document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { close(overlay); document.removeEventListener('keydown', esc); } });
        box.appendChild(frame);
        box.appendChild(button);
        overlay.appendChild(box);
        document.body.appendChild(overlay);
    }, delay);
})();
