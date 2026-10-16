/* Auto-login Forza Delivery (inyectado por portal_proxy.php).
   Flujo real: pantalla de país -> formulario Usuario Corporativo
   (Código / Usuario / Contraseña) -> botón ion-button "Iniciar sesión".
   El propio SPA guarda su token; solo automatizamos el formulario. */
(function () {
    var cfg = window.__SHIPPING_PORTAL;
    if (!cfg || cfg.service !== 'forza') return;
    var done = false;
    var lastCountryClick = 0;

    function visible(el) {
        return !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
    }

    function setNative(el, val) {
        var setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
        setter.call(el, val);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function q(sel) { return document.querySelector(sel); }

    function firstVisible(list, test) {
        for (var i = 0; i < list.length; i++) {
            if (visible(list[i]) && (!test || test(list[i]))) return list[i];
        }
        return null;
    }

    // opción de país: hojas con texto exacto (SPAN/DIV/A), nunca el BUTTON del dropdown
    function countryOption(text) {
        var tags = ['span', 'em', 'strong', 'li', 'div', 'p', 'a'];
        for (var t = 0; t < tags.length; t++) {
            var els = document.querySelectorAll(tags[t]);
            for (var i = 0; i < els.length; i++) {
                var el = els[i];
                if (el.tagName === 'BUTTON' || !visible(el)) continue;
                if (el.childElementCount > 0 && tags[t] !== 'a') continue;
                if (el.textContent.trim() === text) return el;
            }
        }
        return null;
    }

    function tick() {
        if (done) return;
        try {
            var code = q('input[placeholder="Ingresar Código"]');
            var user = q('input[placeholder="Ingresar Usuario"]');
            var pass = q('input[type="password"]');
            var anyInput = firstVisible(document.querySelectorAll('input'), function (el) {
                return el.type !== 'hidden';
            });

            if (!anyInput) {
                // pantalla de selección de país (solo clickear cada poco para no spamear)
                if (Date.now() - lastCountryClick > 2500) {
                    var opt = countryOption('Guatemala');
                    if (opt) { lastCountryClick = Date.now(); opt.click(); }
                }
                return;
            }

            if (user && pass && !code) {
                // pestaña equivocada (Usuario Individual, sin código): cambiar a Corporativo
                var corp = firstVisible(document.querySelectorAll('a'), function (a) {
                    return a.textContent.trim() === 'Usuario Corporativo';
                });
                if (corp) { corp.click(); return; }
            }

            if (code && user && pass) {
                if (!code.value) setNative(code, cfg.code);
                if (!user.value) setNative(user, cfg.user);
                if (!pass.value) setNative(pass, cfg.pass);
                if (!code.value || !user.value || !pass.value) return;
                var btn = firstVisible(document.querySelectorAll('ion-button,button'), function (b) {
                    return /^iniciar sesi[óo]n$/i.test(b.textContent.replace(/\s+/g, ' ').trim()) && !b.disabled;
                });
                if (btn) { done = true; btn.click(); }
            }
        } catch (e) { /* seguimos intentando */ }
    }

    tick();
    var timer = setInterval(function () {
        tick();
        if (done) clearInterval(timer);
    }, 400);
    setTimeout(function () { clearInterval(timer); }, 45000);
})();
