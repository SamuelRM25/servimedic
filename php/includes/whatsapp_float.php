<?php
/**
 * Botón flotante de WhatsApp con lista de chats pendientes.
 * Muestra clientes con pedidos pendientes de pago o cotizaciones
 * sin responder. Permite registrar contactos sin salir de la app.
 */
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['user_id'])) return; // solo usuarios logueados
?>
<style>
.wa-float-btn {
    position: fixed; bottom: 24px; right: 24px; z-index: 9998;
    width: 60px; height: 60px; border-radius: 50%;
    background: #25D366; color: white; border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    cursor: pointer; font-size: 28px; display: flex;
    align-items: center; justify-content: center;
    transition: transform 0.2s;
}
.wa-float-btn:hover { transform: scale(1.08); }
.wa-float-btn .badge {
    position: absolute; top: -4px; right: -4px;
    background: #e53e3e; color: white; border-radius: 50%;
    min-width: 22px; height: 22px; padding: 0 6px;
    font-size: 12px; font-weight: 700; display: flex;
    align-items: center; justify-content: center;
    border: 2px solid white;
}
.wa-panel {
    position: fixed; bottom: 96px; right: 24px; z-index: 9998;
    width: 360px; max-height: 70vh; background: white;
    border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.25);
    display: none; flex-direction: column; overflow: hidden;
    border: 1px solid #e2e8f0;
}
.wa-panel.open { display: flex; }
.wa-panel-header {
    background: #075E54; color: white; padding: 12px 16px;
    display: flex; justify-content: space-between; align-items: center;
}
.wa-panel-header h3 { margin: 0; font-size: 16px; }
.wa-panel-header button {
    background: none; border: none; color: white; font-size: 20px;
    cursor: pointer; padding: 0; line-height: 1;
}
.wa-panel-body { flex: 1; overflow-y: auto; padding: 8px; background: #ECE5DD; }
.wa-empty { text-align: center; padding: 30px 10px; color: #718096; }
.wa-item {
    background: white; border-radius: 8px; padding: 10px 12px;
    margin-bottom: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.08);
}
.wa-item .wa-name { font-weight: 600; font-size: 14px; color: #1a202c; }
.wa-item .wa-meta { font-size: 12px; color: #718096; margin: 2px 0 6px; }
.wa-item .wa-amount { font-weight: 700; color: #c53030; font-size: 13px; }
.wa-item .wa-actions {
    display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap;
}
.wa-item .wa-actions button {
    flex: 1; padding: 6px 8px; font-size: 12px;
    border: none; border-radius: 6px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 4px;
    min-width: 0; white-space: nowrap;
}
.wa-btn-chat { background: #25D366; color: white; }
.wa-btn-done { background: #e2e8f0; color: #2d3748; }
.wa-btn-done.done { background: #c6f6d5; color: #22543d; }
.wa-item.dismissed { opacity: 0.5; }
.wa-item.dismissed .wa-amount { color: #38a169; }
</style>

<button class="wa-float-btn" id="waFloatBtn" onclick="waTogglePanel()" title="Chats pendientes">
    <i class="bi bi-whatsapp"></i>
    <span class="badge" id="waBadge" style="display:none;">0</span>
</button>

<div class="wa-panel" id="waPanel">
    <div class="wa-panel-header">
        <h3><i class="bi bi-whatsapp"></i> Chats pendientes</h3>
        <button onclick="waTogglePanel()">×</button>
    </div>
    <div class="wa-panel-body" id="waPanelBody">
        <div class="wa-empty">Cargando…</div>
    </div>
</div>

<script>
const WA_API = '../includes/whatsapp_chats_api.php';  // ruta relativa a la página que incluye este widget

async function waLoadChats() {
    try {
        const r = await fetch(WA_API);
        const data = await r.json();
        const body = document.getElementById('waPanelBody');
        if (!data.chats || data.chats.length === 0) {
            body.innerHTML = '<div class="wa-empty">✓ No hay chats pendientes</div>';
            document.getElementById('waBadge').style.display = 'none';
            return;
        }
        document.getElementById('waBadge').textContent = data.chats.length;
        document.getElementById('waBadge').style.display = 'flex';
        body.innerHTML = data.chats.map(c => `
            <div class="wa-item ${c.contacted_at ? 'dismissed' : ''}" data-id="${c.id_pedido}">
                <div class="wa-name">${escapeHtml(c.cliente_nombre || 'Sin nombre')}</div>
                <div class="wa-meta">${c.telefono ? '📞 ' + escapeHtml(c.telefono) : 'Sin teléfono'} · ${c.tipo} · ${c.dias}d</div>
                <div class="wa-amount">${c.estado_pago === 'Pagado' ? '✓ Pagado' : 'Q ' + parseFloat(c.total).toFixed(2)}</div>
                <div class="wa-actions">
                    <button class="wa-btn-chat" onclick="waOpenChat('${escapeQuotes(c.telefono || '')}', '${escapeQuotes(c.cliente_nombre || '')}')">
                        <i class="bi bi-whatsapp"></i> Abrir
                    </button>
                    <button class="wa-btn-done ${c.contacted_at ? 'done' : ''}" onclick="waMarkContacted(${c.id_pedido}, this)">
                        <i class="bi bi-${c.contacted_at ? 'check-circle-fill' : 'check2'}"></i> ${c.contacted_at ? 'Contactado' : 'Marcar'}
                    </button>
                </div>
            </div>
        `).join('');
    } catch (e) {
        document.getElementById('waPanelBody').innerHTML = '<div class="wa-empty">Error al cargar</div>';
    }
}

function waTogglePanel() {
    const p = document.getElementById('waPanel');
    p.classList.toggle('open');
    if (p.classList.contains('open')) waLoadChats();
}

function waOpenChat(telefono, nombre) {
    if (!telefono) {
        if (typeof window.uiToast === 'function') { uiToast('Este cliente no tiene teléfono registrado', 'warning'); }
        return;
    }
    const tel = telefono.replace(/[^0-9]/g, '');
    const url = `https://wa.me/${tel}?text=${encodeURIComponent('Hola ' + nombre + ', le escribimos de CLINOVA Central. Le saluda cordialmente.')}`;
    window.open(url, '_blank');
    // No redirige la app, solo abre wa.me en nueva pestaña
}

async function waMarkContacted(idPedido, btn) {
    btn.disabled = true;
    try {
        const fd = new FormData();
        fd.append('id_pedido', idPedido);
        const r = await fetch(WA_API, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) waLoadChats();
    } catch (e) {
        btn.disabled = false;
    }
}

function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function escapeQuotes(s) {
    return String(s || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

// Refrescar cada 60s
setInterval(() => {
    if (document.getElementById('waPanel').classList.contains('open')) waLoadChats();
}, 60000);
</script>
