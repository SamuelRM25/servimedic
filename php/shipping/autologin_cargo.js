/* Auto-login Cargo Expreso (inyectado por portal_proxy.php).
   El portal hace su flujo OIDC con IDCS. Pre-calentamos la sesion
   (esperando a que la pagina termine su onLoad) y luego completamos
   el formulario. Si falla con "creds incorrectas" reintentamos una
   vez (puede ser timing de la sesion IDCS). Reportamos por postMessage. */
(function () {
    var cfg = window.__SHIPPING_PORTAL;
    if (!cfg || cfg.service !== 'cargo') return;
    var done = false;
    var clickCount = 0;
    var lastClickAt = 0;
    var errorSeenAt = 0;
    var start = Date.now();

    function postResult(state, detail) {
        try {
            window.parent.postMessage({
                type: 'shipping-autologin',
                service: 'cargo',
                state: state,
                detail: detail || ''
            }, '*');
        } catch (e) {}
    }

    function visible(el) {
        return !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
    }

    function setNative(el, val) {
        var setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
        setter.call(el, val);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function findPassword() {
        var inputs = document.querySelectorAll('input');
        for (var i = 0; i < inputs.length; i++) {
            if (inputs[i].type === 'password' && visible(inputs[i])) return inputs[i];
        }
        return null;
    }

    function findEmail(pass) {
        var inputs = document.querySelectorAll('input');
        for (var j = 0; j < inputs.length; j++) {
            var el = inputs[j];
            if (el === pass || !visible(el)) continue;
            if (el.type === 'hidden' || el.type === 'checkbox' || el.type === 'radio' || el.type === 'password') continue;
            if (el.type === 'email' || el.type === 'text' || el.type === 'tel' || el.autocomplete === 'username') {
                return el;
            }
        }
        return null;
    }

    function findSubmitBtn() {
        var btns = document.querySelectorAll('button, [role="button"], a');
        for (var k = 0; k < btns.length; k++) {
            var t = (btns[k].textContent || '').replace(/\s+/g, ' ').trim();
            if (/^Comenzar$/i.test(t) && visible(btns[k])) return btns[k];
        }
        return null;
    }

    function isLoggedIn() {
        return !findPassword();
    }

    function detectError() {
        var txt = document.body ? document.body.innerText : '';
        if (/credenciales incorrect|contrase\u00f1a incorrect|usuario ocontrase/i.test(txt)) {
            return 'creds_incorrectas';
        }
        if (/cuenta.*(desactiv|bloquead|expirad)/i.test(txt)) return 'cuenta_bloqueada';
        if (/Fallo de conexi[oó]n/i.test(txt)) return 'creds_incorrectas';
        return null;
    }

    function tryLogin() {
        var pass = findPassword();
        if (!pass) return false;
        var email = findEmail(pass);
        if (!email) return false;
        if (!email.value) setNative(email, cfg.email);
        if (!pass.value) setNative(pass, cfg.pass);
        if (!email.value || !pass.value) return false;
        var btn = findSubmitBtn();
        if (!btn || btn.disabled) return false;
        clickCount++;
        lastClickAt = Date.now();
        btn.click();
        return true;
    }

    function tick() {
        if (done) return;

        // Exito: ya no hay formulario
        if (isLoggedIn() && clickCount > 0) {
            done = true;
            postResult('success', 'login completado');
            return;
        }

        // Error visible
        var err = detectError();
        if (err) {
            // Reintentar una vez si es la primera vez y han pasado >3s desde el click
            if (err === 'creds_incorrectas' && clickCount === 1 && (Date.now() - lastClickAt) > 3000 && (Date.now() - errorSeenAt) < 100) {
                errorSeenAt = Date.now();
                return; // dar tiempo a que se asiente el error antes de reintentar
            }
            if (err === 'creds_incorrectas' && clickCount < 3) {
                // limpiar el error y reintentar (la sesion IDCS puede no estar lista)
                errorSeenAt = Date.now();
                clickCount++;
                lastClickAt = Date.now();
                // Limpiar campos y reintentar
                var pass = findPassword();
                var email = pass && findEmail(pass);
                if (email) { email.value = ''; email.dispatchEvent(new Event('input', {bubbles:true})); setNative(email, cfg.email); }
                if (pass) { pass.value = ''; pass.dispatchEvent(new Event('input', {bubbles:true})); setNative(pass, cfg.pass); }
                setTimeout(function() {
                    var b = findSubmitBtn();
                    if (b && !b.disabled) b.click();
                }, 4000);
                return;
            }
            done = true;
            postResult('error', err);
            return;
        }

        // Intentar login si el boton esta habilitado
        if (clickCount === 0) {
            tryLogin();
        }
    }

    tick();
    var timer = setInterval(function () {
        tick();
        if (done) clearInterval(timer);
    }, 600);
    setTimeout(function () {
        if (!done) {
            clearInterval(timer);
            postResult('timeout', 'no se detecto login completado en 45s');
        }
    }, 45000);
})();
