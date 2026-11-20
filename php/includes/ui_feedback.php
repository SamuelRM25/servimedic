<?php /* CLINOVA - UI Feedback: toasts, dialogs animados y overlay de carga */ ?>
<style>
    #uiToastContainer {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 100000;
        display: flex;
        flex-direction: column;
        gap: 12px;
        pointer-events: none;
        max-width: 380px;
        width: calc(100% - 48px);
    }
    .ui-toast {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        background: #FFFFFF;
        border-radius: 14px;
        padding: 14px 16px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.18);
        border-left: 5px solid #1FA8FF;
        pointer-events: auto;
        animation: uiToastIn 0.35s cubic-bezier(0.21, 1.02, 0.73, 1) both;
        font-family: 'Poppins', system-ui, -apple-system, sans-serif;
    }
    .ui-toast.ui-toast-out {
        animation: uiToastOut 0.3s ease both;
    }
    .ui-toast-icon { font-size: 1.35rem; line-height: 1; margin-top: 1px; }
    .ui-toast-body { flex: 1; min-width: 0; }
    .ui-toast-title { font-weight: 600; font-size: 0.9rem; color: #2D3748; margin-bottom: 2px; }
    .ui-toast-msg { font-size: 0.85rem; color: #4A5568; word-wrap: break-word; white-space: pre-line; }
    .ui-toast-close {
        background: none; border: none; cursor: pointer; color: #A0AEC0; font-size: 1rem;
        padding: 0; line-height: 1; transition: color 0.2s;
    }
    .ui-toast-close:hover { color: #4A5568; }
    .ui-toast.ui-success { border-left-color: #48BB78; }
    .ui-toast.ui-success .ui-toast-icon { color: #48BB78; }
    .ui-toast.ui-error { border-left-color: #F56565; }
    .ui-toast.ui-error .ui-toast-icon { color: #F56565; }
    .ui-toast.ui-warning { border-left-color: #ED8936; }
    .ui-toast.ui-warning .ui-toast-icon { color: #ED8936; }
    .ui-toast.ui-info { border-left-color: #1FA8FF; }
    .ui-toast.ui-info .ui-toast-icon { color: #1FA8FF; }

    @keyframes uiToastIn {
        from { opacity: 0; transform: translateX(60px) scale(0.95); }
        to { opacity: 1; transform: translateX(0) scale(1); }
    }
    @keyframes uiToastOut {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(60px); }
    }

    .ui-dialog-backdrop {
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55);
        z-index: 100001; display: flex; align-items: center; justify-content: center;
        padding: 20px; animation: uiFadeIn 0.25s ease both;
        font-family: 'Poppins', system-ui, -apple-system, sans-serif;
    }
    .ui-dialog-backdrop.ui-dialog-out { animation: uiFadeOut 0.22s ease both; }
    .ui-dialog {
        background: #FFFFFF; border-radius: 20px; width: 100%; max-width: 420px;
        padding: 28px 26px 22px; box-shadow: 0 24px 64px rgba(15, 23, 42, 0.3);
        animation: uiDialogIn 0.35s cubic-bezier(0.21, 1.02, 0.73, 1) both;
        text-align: center;
    }
    .ui-dialog-backdrop.ui-dialog-out .ui-dialog { animation: uiDialogOut 0.22s ease both; }
    .ui-dialog-icon {
        width: 64px; height: 64px; border-radius: 50%; margin: 0 auto 16px;
        display: flex; align-items: center; justify-content: center; font-size: 1.9rem;
    }
    .ui-dialog-icon.ui-type-info { background: #EBF8FF; color: #1FA8FF; }
    .ui-dialog-icon.ui-type-success { background: #F0FFF4; color: #48BB78; }
    .ui-dialog-icon.ui-type-warning { background: #FFFAF0; color: #ED8936; }
    .ui-dialog-icon.ui-type-error { background: #FFF5F5; color: #F56565; }
    .ui-dialog-icon.ui-shake { animation: uiShake 0.5s ease both; }
    .ui-dialog-title { font-size: 1.15rem; font-weight: 700; color: #2D3748; margin-bottom: 8px; }
    .ui-dialog-msg { font-size: 0.92rem; color: #4A5568; line-height: 1.55; white-space: pre-line; }
    .ui-dialog-actions { display: flex; gap: 10px; margin-top: 22px; }
    .ui-dialog-actions .ui-btn {
        flex: 1; border: none; border-radius: 12px; padding: 12px 16px; font-weight: 600;
        font-size: 0.92rem; cursor: pointer; transition: transform 0.15s, box-shadow 0.2s, background 0.2s;
        font-family: inherit;
    }
    .ui-dialog-actions .ui-btn:active { transform: scale(0.97); }
    .ui-btn-confirm { background: #1FA8FF; color: #fff; box-shadow: 0 6px 16px rgba(31, 168, 255, 0.35); }
    .ui-btn-confirm:hover { background: #0D8FE6; }
    .ui-btn-confirm.ui-btn-danger { background: #F56565; box-shadow: 0 6px 16px rgba(245, 101, 101, 0.35); }
    .ui-btn-confirm.ui-btn-danger:hover { background: #E53E3E; }
    .ui-btn-cancel { background: #EDF2F7; color: #4A5568; }
    .ui-btn-cancel:hover { background: #E2E8F0; }

    @keyframes uiFadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes uiFadeOut { from { opacity: 1; } to { opacity: 0; } }
    @keyframes uiDialogIn {
        from { opacity: 0; transform: scale(0.85) translateY(20px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    @keyframes uiDialogOut {
        from { opacity: 1; transform: scale(1); }
        to { opacity: 0; transform: scale(0.9) translateY(12px); }
    }
    @keyframes uiShake {
        0%, 100% { transform: translateX(0); }
        20% { transform: translateX(-8px); }
        40% { transform: translateX(8px); }
        60% { transform: translateX(-5px); }
        80% { transform: translateX(5px); }
    }

    #uiLoadingOverlay {
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45);
        z-index: 100002; display: none; align-items: center; justify-content: center;
        flex-direction: column; gap: 18px; backdrop-filter: blur(2px);
        animation: uiFadeIn 0.2s ease both;
        font-family: 'Poppins', system-ui, -apple-system, sans-serif;
    }
    #uiLoadingOverlay.ui-visible { display: flex; }
    .ui-loading-card {
        background: #FFFFFF; border-radius: 18px; padding: 28px 40px;
        display: flex; flex-direction: column; align-items: center; gap: 14px;
        box-shadow: 0 24px 64px rgba(15, 23, 42, 0.35);
        animation: uiDialogIn 0.3s cubic-bezier(0.21, 1.02, 0.73, 1) both;
        min-width: 220px;
    }
    .ui-spinner {
        width: 46px; height: 46px; border-radius: 50%;
        border: 4px solid #EBF8FF; border-top-color: #1FA8FF;
        animation: uiSpin 0.8s linear infinite;
    }
    .ui-loading-text { font-size: 0.95rem; font-weight: 600; color: #2D3748; }
    .ui-loading-sub { font-size: 0.78rem; color: #A0AEC0; }
    @keyframes uiSpin { to { transform: rotate(360deg); } }

    /* ===== Protección contra doble clic ===== */
    /* Capa invisible que bloquea clics mientras se guarda algo */
    #uiBusyShield {
        position: fixed; inset: 0; z-index: 100001; display: none;
        cursor: progress; background: transparent;
    }
    #uiBusyShield.ui-visible { display: block; }
    /* Spinner con logo/puntos: aparece si la operación tarda más de un instante */
    .ui-loading-dots { display: flex; gap: 6px; margin-top: -4px; }
    .ui-loading-dots span {
        width: 7px; height: 7px; border-radius: 50%; background: #1FA8FF;
        animation: uiDot 1.1s ease-in-out infinite both;
    }
    .ui-loading-dots span:nth-child(2) { animation-delay: 0.15s; background: #FF6B35; }
    .ui-loading-dots span:nth-child(3) { animation-delay: 0.3s; }
    @keyframes uiDot {
        0%, 80%, 100% { transform: scale(0.5); opacity: 0.4; }
        40% { transform: scale(1); opacity: 1; }
    }
    /* Botón que se presionó: queda deshabilitado con un mini spinner */
    .ui-btn-loading {
        position: relative !important; pointer-events: none !important;
        opacity: 0.75 !important; cursor: progress !important;
    }
    .ui-btn-loading::after {
        content: ''; display: inline-block; width: 0.9em; height: 0.9em;
        margin-left: 0.5em; vertical-align: -0.12em; border-radius: 50%;
        border: 2px solid currentColor; border-right-color: transparent;
        animation: uiSpin 0.7s linear infinite;
    }
</style>

<div id="uiToastContainer" aria-live="polite"></div>

<div id="uiLoadingOverlay" aria-hidden="true">
    <div class="ui-loading-card">
        <div class="ui-spinner"></div>
        <div class="ui-loading-text" id="uiLoadingText">Procesando...</div>
        <div class="ui-loading-dots"><span></span><span></span><span></span></div>
        <div class="ui-loading-sub">Por favor espere, no recargue la página</div>
    </div>
</div>
<div id="uiBusyShield" aria-hidden="true"></div>

<script>
(function () {
    'use strict';

    var toastContainer = document.getElementById('uiToastContainer');
    var loadingOverlay = document.getElementById('uiLoadingOverlay');
    var loadingTextEl = document.getElementById('uiLoadingText');
    var loadingCount = 0;
    var loadingTimer = null;

    var ICONS = {
        success: 'bi bi-check-circle-fill',
        error: 'bi bi-x-circle-fill',
        warning: 'bi bi-exclamation-triangle-fill',
        info: 'bi bi-info-circle-fill'
    };
    var TITLES = {
        success: 'Listo',
        error: 'Error',
        warning: 'Atención',
        info: 'Información'
    };

    function esc(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function uiToast(message, type, duration) {
        type = ICONS[type] ? type : 'info';
        duration = typeof duration === 'number' ? duration : 3500;
        var t = document.createElement('div');
        t.className = 'ui-toast ui-' + type;
        t.innerHTML =
            '<span class="ui-toast-icon"><i class="' + ICONS[type] + '"></i></span>' +
            '<div class="ui-toast-body">' +
                '<div class="ui-toast-title">' + TITLES[type] + '</div>' +
                '<div class="ui-toast-msg">' + esc(message) + '</div>' +
            '</div>' +
            '<button class="ui-toast-close" aria-label="Cerrar">&times;</button>';
        var remove = function () {
            if (t.classList.contains('ui-toast-out')) return;
            t.classList.add('ui-toast-out');
            setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 300);
        };
        t.querySelector('.ui-toast-close').addEventListener('click', remove);
        toastContainer.appendChild(t);
        if (duration > 0) setTimeout(remove, duration);
        return t;
    }

    function buildDialog(opts) {
        opts = opts || {};
        var type = opts.type || 'info';
        var iconMap = {
            success: ICONS.success, error: ICONS.error, warning: ICONS.warning, info: ICONS.info
        };
        var backdrop = document.createElement('div');
        backdrop.className = 'ui-dialog-backdrop';
        backdrop.innerHTML =
            '<div class="ui-dialog" role="dialog" aria-modal="true">' +
                '<div class="ui-dialog-icon ui-type-' + (iconMap[type] ? type : 'info') + (opts.shake ? ' ui-shake' : '') + '">' +
                    '<i class="' + (iconMap[type] || ICONS.info) + '"></i>' +
                '</div>' +
                '<div class="ui-dialog-title">' + esc(opts.title || TITLES[type] || 'Información') + '</div>' +
                '<div class="ui-dialog-msg">' + esc(opts.message || '') + '</div>' +
                '<div class="ui-dialog-actions"></div>' +
            '</div>';
        return backdrop;
    }

    function uiAlert(message, opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            var type = opts.type || 'info';
            var backdrop = buildDialog({
                type: type,
                title: opts.title || (type === 'error' ? 'Error' : type === 'success' ? 'Listo' : 'Información'),
                message: message,
                shake: type === 'error'
            });
            var actions = backdrop.querySelector('.ui-dialog-actions');
            var okBtn = document.createElement('button');
            okBtn.className = 'ui-btn ui-btn-confirm' + (type === 'error' ? ' ui-btn-danger' : '');
            okBtn.textContent = opts.confirmText || 'Aceptar';
            actions.appendChild(okBtn);
            var close = function () {
                if (backdrop.classList.contains('ui-dialog-out')) return;
                backdrop.classList.add('ui-dialog-out');
                setTimeout(function () { if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop); }, 220);
                document.removeEventListener('keydown', onKey);
                resolve();
            };
            var onKey = function (e) { if (e.key === 'Escape' || e.key === 'Enter') close(); };
            okBtn.addEventListener('click', close);
            backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(); });
            document.addEventListener('keydown', onKey);
            document.body.appendChild(backdrop);
            setTimeout(function () { okBtn.focus(); }, 60);
        });
    }

    function uiConfirm(message, opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            var danger = opts.danger !== false;
            var backdrop = buildDialog({
                type: opts.type || (danger ? 'warning' : 'info'),
                title: opts.title || 'Confirmar acción',
                message: message
            });
            var actions = backdrop.querySelector('.ui-dialog-actions');
            var cancelBtn = document.createElement('button');
            cancelBtn.className = 'ui-btn ui-btn-cancel';
            cancelBtn.textContent = opts.cancelText || 'Cancelar';
            var okBtn = document.createElement('button');
            okBtn.className = 'ui-btn ui-btn-confirm' + (danger ? ' ui-btn-danger' : '');
            okBtn.textContent = opts.confirmText || 'Sí, continuar';
            actions.appendChild(cancelBtn);
            actions.appendChild(okBtn);
            var done = false;
            var close = function (result) {
                if (done) return;
                done = true;
                backdrop.classList.add('ui-dialog-out');
                setTimeout(function () { if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop); }, 220);
                document.removeEventListener('keydown', onKey);
                resolve(result);
            };
            var onKey = function (e) {
                if (e.key === 'Escape') close(false);
                if (e.key === 'Enter') close(true);
            };
            okBtn.addEventListener('click', function () { close(true); });
            cancelBtn.addEventListener('click', function () { close(false); });
            backdrop.addEventListener('click', function (e) { if (e.target === backdrop) close(false); });
            document.addEventListener('keydown', onKey);
            document.body.appendChild(backdrop);
            setTimeout(function () { okBtn.focus(); }, 60);
        });
    }

    function showLoading(text) {
        loadingCount++;
        if (text) loadingTextEl.textContent = text;
        loadingOverlay.classList.add('ui-visible');
        loadingOverlay.setAttribute('aria-hidden', 'false');
        if (loadingTimer) clearTimeout(loadingTimer);
        loadingTimer = setTimeout(function () {
            loadingCount = 0;
            loadingOverlay.classList.remove('ui-visible');
            loadingOverlay.setAttribute('aria-hidden', 'true');
        }, 45000);
    }

    function hideLoading() {
        loadingCount = Math.max(0, loadingCount - 1);
        if (loadingCount === 0) {
            loadingOverlay.classList.remove('ui-visible');
            loadingOverlay.setAttribute('aria-hidden', 'true');
            if (loadingTimer) { clearTimeout(loadingTimer); loadingTimer = null; }
        }
    }

    function withLoading(text, fn) {
        showLoading(text);
        var p;
        try {
            p = Promise.resolve(fn());
        } catch (e) {
            hideLoading();
            throw e;
        }
        return p.finally(hideLoading);
    }

    window.uiToast = uiToast;
    window.uiAlert = uiAlert;
    window.uiConfirm = uiConfirm;
    window.showLoading = showLoading;
    window.hideLoading = hideLoading;
    window.withLoading = withLoading;

    var nativeAlert = window.alert;
    window.alert = function (msg) {
        if (msg == null) { nativeAlert.call(window, msg); return; }
        var text = String(msg);
        var type = 'info';
        if (/^error|error al|no se pudo|no se puede|incorrect|inv[aá]lid/i.test(text)) type = 'error';
        else if (/exitos|guardad|eliminad|actualizad|cread|registrad|correctamente|listo/i.test(text)) type = 'success';
        else if (/atenci[oó]n|cuidado|stock|stock bajo|advertencia/i.test(text)) type = 'warning';
        uiToast(text, type, type === 'error' ? 5000 : 3500);
    };

    window.uiAlertErrorFromFetch = function (err) {
        uiToast('Error de conexión: ' + (err && err.message ? err.message : 'intente de nuevo'), 'error', 5000);
    };
})();
</script>

<script>
/*
 * Protección global contra doble clic / doble envío.
 *
 * 1. Toda petición fetch que guarda algo (POST, PUT, PATCH, DELETE) bloquea la
 *    pantalla al instante y muestra "Guardando..." si tarda más de un momento.
 * 2. Si llega una petición idéntica mientras la primera sigue en curso, no se
 *    vuelve a enviar al servidor: recibe la misma respuesta.
 * 3. Al terminar, la pantalla sigue bloqueada un momento (la mayoría de pantallas
 *    recargan justo después), para que no se pueda volver a presionar el botón.
 * 4. Un formulario no se puede enviar dos veces seguidas.
 * 5. El botón presionado se deshabilita y muestra un mini spinner.
 *
 * Para una petición en segundo plano que no deba bloquear: fetch(url, { uiSilent: true, ... }).
 */
(function () {
    'use strict';
    if (window.__uiAntiDoble) return;
    window.__uiAntiDoble = true;

    var shield = document.getElementById('uiBusyShield');
    var overlay = document.getElementById('uiLoadingOverlay');
    var textEl = document.getElementById('uiLoadingText');
    var nativeFetch = window.fetch.bind(window);

    var pendientes = 0;           // peticiones que guardan, en curso
    var enCurso = {};             // clave -> promesa (para no repetir la misma)
    var visualTimer = null;
    var cooldownTimer = null;
    var mostrandoVisual = false;
    var ultimoBoton = null, ultimoBotonT = 0;
    var botonesCargando = [];
    var navegando = false;

    var COOLDOWN_MS = 1200;       // bloqueo tras terminar (las pantallas recargan a los 700-900 ms)
    var VISUAL_DELAY_MS = 180;    // evitar parpadeo en peticiones muy rápidas

    // Recordar el último botón presionado (para ponerle spinner)
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('button, input[type=submit], .btn, a.dropdown-item');
        if (b) { ultimoBoton = b; ultimoBotonT = Date.now(); }
    }, true);
    document.addEventListener('submit', function (e) {
        var b = e.submitter || (e.target.querySelector && e.target.querySelector('[type=submit]'));
        if (b) { ultimoBoton = b; ultimoBotonT = Date.now(); }
    }, true);

    function textoPorDefecto(url, metodo) {
        var u = String(url).toLowerCase();
        if (/delete|elimin|cancel/.test(u)) return 'Eliminando...';
        if (/process|mark_paid|payment|pago/.test(u)) return 'Procesando...';
        if (/verify|search/.test(u)) return 'Verificando...';
        return 'Guardando...';
    }

    function bloquear(texto) {
        if (cooldownTimer) { clearTimeout(cooldownTimer); cooldownTimer = null; }
        shield.classList.add('ui-visible');
        if (!overlay.classList.contains('ui-visible') && !visualTimer) {
            visualTimer = setTimeout(function () {
                visualTimer = null;
                if (pendientes > 0 && !overlay.classList.contains('ui-visible')) {
                    if (texto) textEl.textContent = texto;
                    overlay.classList.add('ui-visible');
                    overlay.setAttribute('aria-hidden', 'false');
                    mostrandoVisual = true;
                }
            }, VISUAL_DELAY_MS);
        }
        // Spinner en el botón presionado justo antes
        if (ultimoBoton && Date.now() - ultimoBotonT < 3000 && document.contains(ultimoBoton)
            && botonesCargando.indexOf(ultimoBoton) === -1) {
            ultimoBoton.classList.add('ui-btn-loading');
            // solo lo deshabilitamos nosotros si estaba habilitado (si la pantalla ya lo maneja, no se toca)
            if ('disabled' in ultimoBoton && !ultimoBoton.disabled) { ultimoBoton.disabled = true; ultimoBoton.dataset.uiDisabledByUs = '1'; }
            botonesCargando.push(ultimoBoton);
        }
    }

    function liberar() {
        if (pendientes > 0 || navegando) return;
        if (visualTimer) { clearTimeout(visualTimer); visualTimer = null; }
        if (mostrandoVisual) {
            mostrandoVisual = false;
            // solo ocultar si nadie más lo está usando con showLoading()
            if (!overlay.dataset.uiManual) {
                overlay.classList.remove('ui-visible');
                overlay.setAttribute('aria-hidden', 'true');
            }
        }
        // Mantener el bloqueo invisible un momento más
        if (cooldownTimer) clearTimeout(cooldownTimer);
        cooldownTimer = setTimeout(function () {
            cooldownTimer = null;
            if (pendientes > 0 || navegando) return;
            shield.classList.remove('ui-visible');
            botonesCargando.forEach(function (b) {
                b.classList.remove('ui-btn-loading');
                if (b.dataset.uiDisabledByUs) { b.disabled = false; delete b.dataset.uiDisabledByUs; }
            });
            botonesCargando = [];
        }, COOLDOWN_MS);
    }

    function claveDe(url, opts) {
        var body = opts && opts.body;
        var b = '';
        if (body == null) b = '';
        else if (typeof body === 'string') b = body;
        else if (typeof FormData !== 'undefined' && body instanceof FormData) {
            var partes = [];
            body.forEach(function (v, k) {
                partes.push(k + '=' + (typeof v === 'string' ? v : ('[archivo:' + (v.name || '') + ':' + (v.size || 0) + ']')));
            });
            b = partes.join('&');
        } else if (typeof URLSearchParams !== 'undefined' && body instanceof URLSearchParams) b = body.toString();
        else return null; // cuerpo desconocido: no deduplicar
        return String(url) + '|' + b;
    }

    window.fetch = function (input, init) {
        var opts = init || {};
        var url = (typeof input === 'string') ? input : (input && input.url) || '';
        var metodo = String(opts.method || (input && input.method) || 'GET').toUpperCase();
        var silencioso = !!opts.uiSilent;
        if (opts.uiSilent !== undefined) { opts = Object.assign({}, opts); delete opts.uiSilent; }

        if (silencioso || metodo === 'GET' || metodo === 'HEAD') {
            return nativeFetch(input, opts);
        }

        // Petición idéntica ya en curso: no se reenvía
        var clave = claveDe(metodo + ' ' + url, opts);
        if (clave && enCurso[clave]) {
            console.warn('[CLINOVA] Envío duplicado evitado:', url);
            return enCurso[clave].then(function (r) { return r.clone(); });
        }

        pendientes++;
        bloquear(textoPorDefecto(url, metodo));

        var base = nativeFetch(input, opts);
        if (clave) enCurso[clave] = base;
        var fin = function () {
            pendientes = Math.max(0, pendientes - 1);
            if (clave) delete enCurso[clave];
            liberar();
        };
        base.then(fin, fin);
        return base.then(function (r) { return r.clone(); });
    };

    // showLoading()/hideLoading() de las pantallas siguen funcionando igual
    var showOrig = window.showLoading, hideOrig = window.hideLoading;
    window.showLoading = function (t) { overlay.dataset.uiManual = '1'; return showOrig.apply(this, arguments); };
    window.hideLoading = function () {
        var r = hideOrig.apply(this, arguments);
        if (!overlay.classList.contains('ui-visible')) delete overlay.dataset.uiManual;
        // si aún hay peticiones en curso, mantener el aviso visible
        if (pendientes > 0 && !overlay.classList.contains('ui-visible')) {
            overlay.classList.add('ui-visible'); mostrandoVisual = true;
        }
        return r;
    };

    // Formularios: no permitir un segundo envío mientras el primero se procesa
    document.addEventListener('submit', function (e) {
        var form = e.target;
        // Formularios de búsqueda/filtros (method="GET") no se tocan
        if (!form || (form.getAttribute('method') || '').toLowerCase() === 'get') return;
        if (form.__uiEnviando) {
            e.preventDefault();
            e.stopImmediatePropagation();
            return;
        }
        form.__uiEnviando = true;
        // Revisar después de que la pantalla procese el envío
        setTimeout(function () {
            if (!e.defaultPrevented) {
                // Envío normal (la página navega): bloquear hasta que cargue la siguiente
                navegando = true;
                pendientes++;
                bloquear('Guardando...');
                return;
            }
            // Envío por fetch: liberar cuando ya no haya peticiones en curso
            (function esperar() {
                if (pendientes > 0 || cooldownTimer) return setTimeout(esperar, 150);
                form.__uiEnviando = false;
            })();
        }, 0);
    }, true);

    // Al salir de la página (recarga tras guardar), mostrar "Cargando..."
    window.addEventListener('beforeunload', function () {
        navegando = true;
        shield.classList.add('ui-visible');
    });
    // Si el navegador vuelve con "atrás" desde la caché, quitar el bloqueo
    window.addEventListener('pageshow', function (ev) {
        if (ev.persisted) {
            navegando = false; pendientes = 0;
            shield.classList.remove('ui-visible');
            overlay.classList.remove('ui-visible');
            document.querySelectorAll('form').forEach(function (f) { f.__uiEnviando = false; });
        }
    });
})();
</script>
