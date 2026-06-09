// assets/js/form-embed.js — Auto-resize iframe embed script
(function () {
    document.querySelectorAll('script[data-key]').forEach(function (script) {
        var key    = script.getAttribute('data-key');
        var target = script.getAttribute('data-target');
        if (!key) return;

        var base      = script.getAttribute('data-base') || window.location.origin;
        var container = target ? document.getElementById(target) : null;
        var wrapper   = container || document.createElement('div');

        var iframe         = document.createElement('iframe');
        iframe.src         = base + '/api/form/render.php?key=' + encodeURIComponent(key);
        iframe.style.width  = '100%';
        iframe.style.height = '520px';
        iframe.style.border = 'none';
        iframe.style.display = 'block';
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('scrolling', 'no');
        iframe.setAttribute('title', 'Contact Form');
        iframe.setAttribute('loading', 'lazy');

        wrapper.appendChild(iframe);
        if (!container) script.parentNode.insertBefore(wrapper, script);

        window.addEventListener('message', function (e) {
            if (e.data && e.data.type === 'af-form-resize' && typeof e.data.height === 'number') {
                iframe.style.height = e.data.height + 'px';
            }
        });
    });
})();
