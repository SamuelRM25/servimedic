/**
 * Generador de PDF de Cotizaciones CLINOVA (jsPDF).
 * Layout basado en la cotización en PDF del cliente.
 *
 * Uso:
 *   const doc = await generarCotizacionPDF(data);
 *   doc.save('Cotizacion_COT-....pdf');
 *
 * data = { pedido: {...}, productos: [...], total_letras: '...' }
 * (respuesta de php/orders/edit_order.php)
 */

const COTIZACION_PDF_BASE = (function () {
    const s = document.currentScript;
    return s && s.src ? s.src : window.location.href;
})();

function fmtQ(n) {
    const v = Number(n) || 0;
    return 'Q' + v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtFecha(fecha) {
    if (!fecha) return '';
    let s = String(fecha).trim();
    // Los timestamps de la BD están en UTC: interpretarlos como UTC para
    // mostrar la fecha local (Guatemala) en el navegador.
    if (!/(Z|[+-]\d{2}:?\d{2})$/.test(s)) {
        s = s.replace(' ', 'T') + 'Z';
    }
    const d = new Date(s);
    if (isNaN(d)) return String(fecha);
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return dd + '/' + mm + '/' + d.getFullYear();
}

async function loadClinovaLogo() {
    try {
        const url = new URL('../img/CLINOVA.png', COTIZACION_PDF_BASE).href;
        const blob = await fetch(url).then(r => {
            if (!r.ok) throw new Error('logo no encontrado');
            return r.blob();
        });
        const dataUrl = await new Promise((resolve, reject) => {
            const fr = new FileReader();
            fr.onload = () => resolve(fr.result);
            fr.onerror = reject;
            fr.readAsDataURL(blob);
        });
        const img = new Image();
        await new Promise((resolve, reject) => {
            img.onload = resolve;
            img.onerror = reject;
            img.src = dataUrl;
        });

        // Reducir el logo (2MB) para que el PDF no quede pesado
        const maxW = 600;
        const scale = Math.min(1, maxW / img.naturalWidth);
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        return { dataUrl: canvas.toDataURL('image/png'), w: canvas.width, h: canvas.height };
    } catch (e) {
        return null;
    }
}

async function generarCotizacionPDF(data) {
    const { jsPDF } = window.jspdf;

    const p = data.pedido;
    const items = data.productos || [];
    const totalLetras = data.total_letras || '';

    // Colores CLINOVA
    const AZUL = [31, 168, 255];       // #1FA8FF (bordes)
    const AZUL_OSCURO = [2, 119, 189]; // #0277BD (encabezado tabla / píldoras)
    const TEXTO = [45, 55, 72];        // #2D3748
    const TEXTO_CLARO = [113, 128, 150]; // #718096
    const GRIS_CLARO = [226, 232, 240];// #E2E8F0 (líneas)
    const VERDE = [56, 161, 100];
    const BLANCO = [255, 255, 255];

    const doc = new jsPDF({ unit: 'mm', format: 'letter' });
    const ML = 15;                      // margen izquierdo
    const MR = 215.9 - 15;              // borde derecho útil (200.9)
    const W = MR - ML;                  // ancho útil (185.9)

    const logo = await loadClinovaLogo();

    // ============ ENCABEZADO ============
    if (logo) {
        const logoW = 33;
        const logoH = logoW * (logo.h / logo.w); // 1536x1024 → 22mm
        doc.addImage(logo.dataUrl, 'PNG', ML, 10, logoW, Math.min(logoH, 22));
    } else {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(20);
        doc.setTextColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
        doc.text('CLINOVA', ML, 22);
    }

    // Dirección (derecha, azul oscuro)
    const direccion = p.direccion_pdf || '';
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
    const dirLines = doc.splitTextToSize(direccion, 130);
    let dirY = 15;
    dirLines.forEach(line => {
        doc.text(line, MR, dirY, { align: 'right' });
        dirY += 5;
    });

    // Píldora de teléfonos
    const tels = [p.suc_telefono, p.suc_telefono2].filter(Boolean).join(' • ');
    const telText = tels ? 'Teléfono: ' + tels : '';
    if (telText) {
        doc.setFontSize(9);
        const pillW = doc.getTextWidth(telText) + 8;
        const pillH = 7;
        const pillY = dirY + 1;
        doc.setFillColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
        doc.roundedRect(MR - pillW, pillY, pillW, pillH, 3.5, 3.5, 'F');
        doc.setTextColor(BLANCO[0], BLANCO[1], BLANCO[2]);
        doc.text(telText, MR - pillW / 2, pillY + 4.7, { align: 'center' });
        dirY = pillY + pillH;
    }

    // Número de cotización (derecha, gris)
    const cotNumero = p.cotizacion_numero || ('COT-' + p.id_pedido);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
    doc.text(cotNumero, MR, dirY + 5, { align: 'right' });

    // ============ CAJAS DE DATOS ============
    const BOX_COLOR = AZUL;

    function drawBox(x, y, w, h) {
        doc.setDrawColor(BOX_COLOR[0], BOX_COLOR[1], BOX_COLOR[2]);
        doc.setLineWidth(0.4);
        doc.roundedRect(x, y, w, h, 3, 3, 'S');
    }

    // Dibuja "Etiqueta: valor" (valor puede envolverse a 2da línea)
    function drawField(x, y, w, label, value) {
        const labelText = label + ': ';
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9.5);
        doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
        doc.text(labelText, x + 3, y);
        const lw = doc.getTextWidth(labelText);

        doc.setFont('helvetica', 'normal');
        const val = String(value == null ? '' : value);
        if (!val) return;
        const lines = doc.splitTextToSize(val, Math.max(10, w - lw - 6));
        doc.text(lines[0], x + 3 + lw, y);
        if (lines.length > 1) {
            doc.text(lines.slice(1).join(' '), x + 3, y + 4.6);
        }
    }

    // Fila 1: Cliente / Teléfono
    const r1y = 37, r1h = 22;
    const col1W = 120, col2X = ML + col1W + 5, col2W = MR - col2X;

    drawBox(ML, r1y, col1W, r1h);
    drawField(ML, r1y + 7, col1W, 'Cliente', p.cliente_nombre);
    drawField(ML, r1y + 14.5, col1W, 'Dirección', p.cliente_direccion);

    drawBox(col2X, r1y, col2W, r1h);
    drawField(col2X, r1y + 7, col2W, 'Teléfono', p.cliente_telefono);

    // Fila 2: Fecha / Condiciones de Pago
    const r2y = r1y + r1h + 4, r2h = 18;
    drawBox(ML, r2y, col1W, r2h);
    drawField(ML, r2y + 7, col1W, 'Fecha', fmtFecha(p.fecha_pedido));

    drawBox(col2X, r2y, col2W, r2h);
    drawField(col2X, r2y + 7, col2W, 'Condiciones de Pago', p.condiciones_pago);

    // ============ TABLA DE ITEMS ============
    const tableY = r2y + r2h + 7;
    const headerH = 8;
    const subtotalH = 8;
    const descuentoH = (Number(p.descuento) > 0) ? 7 : 0;
    const maxTableBottom = 212; // espacio reservado para legales + cajas + pie

    const cols = [
        { key: 'codigo', label: 'Código', w: 24, align: 'left' },
        { key: 'desc', label: 'Descripción', w: 76, align: 'left' },
        { key: 'cant', label: 'Cantidad', w: 22, align: 'center' },
        { key: 'punit', label: 'Precio Unit.', w: 30, align: 'right' },
        { key: 'total', label: 'Total', w: 33.9, align: 'right' }
    ];

    // Filas mínimas para emular el formato del PDF (12 filas como mínimo)
    const minRows = 12;
    const rowsCount = Math.max(items.length, minRows);
    const available = maxTableBottom - tableY - headerH - subtotalH - descuentoH;
    const rowH = Math.max(4, Math.min(7, available / rowsCount));

    // Encabezado de tabla
    doc.setFillColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
    doc.rect(ML, tableY, W, headerH, 'F');
    doc.setTextColor(BLANCO[0], BLANCO[1], BLANCO[2]);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    let cx = ML;
    cols.forEach(col => {
        let tx = cx + 2;
        if (col.align === 'center') tx = cx + col.w / 2;
        if (col.align === 'right') tx = cx + col.w - 2;
        doc.text(col.label, tx, tableY + 5.3, {
            align: col.align === 'center' ? 'center' : (col.align === 'right' ? 'right' : 'left')
        });
        cx += col.w;
    });

    // Filas de items + filas vacías
    let ry = tableY + headerH;
    for (let i = 0; i < rowsCount; i++) {
        const it = items[i];

        // Fondo alternado muy suave
        if (i % 2 === 1) {
            doc.setFillColor(247, 250, 252);
            doc.rect(ML, ry, W, rowH, 'F');
        }

        doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8.5);

        const codigo = it ? (it.codigo_item || it.codigo_referencia || '') : '';
        const desc = it ? (it.descripcion || it.nom_medicamento || '') : '';
        const cant = it ? String(it.cantidad) : '';
        const punit = it ? fmtQ(it.precio_unitario) : '';
        const totalItem = it ? fmtQ(it.subtotal) : fmtQ(0);

        const vals = [codigo, desc, cant, punit, it ? totalItem : fmtQ(0)];
        cx = ML;
        cols.forEach((col, ci) => {
            let v = vals[ci];
            // Las filas vacías muestran Q0.00 solo en la columna Total
            if (!it && ci !== 4) v = '';
            if (!it && col.key === 'desc' && i >= items.length) v = '';

            let tx = cx + 2;
            let align = 'left';
            if (col.align === 'center') { tx = cx + col.w / 2; align = 'center'; }
            if (col.align === 'right') { tx = cx + col.w - 2; align = 'right'; }

            // Truncar descripción si no cabe
            if (col.key === 'desc') {
                const maxW = col.w - 4;
                let line = String(v);
                if (doc.getTextWidth(line) > maxW) {
                    while (line.length > 1 && doc.getTextWidth(line + '…') > maxW) {
                        line = line.slice(0, -1);
                    }
                    line = line + '…';
                }
                v = line;
            }

            doc.text(v, tx, ry + rowH / 2 + 1.2, { align });
            cx += col.w;
        });

        // Línea inferior de fila
        doc.setDrawColor(GRIS_CLARO[0], GRIS_CLARO[1], GRIS_CLARO[2]);
        doc.setLineWidth(0.2);
        doc.line(ML, ry + rowH, MR, ry + rowH);

        // Separadores verticales
        doc.setDrawColor(GRIS_CLARO[0], GRIS_CLARO[1], GRIS_CLARO[2]);
        cx = ML;
        cols.forEach((col, ci) => {
            if (ci > 0) doc.line(cx, ry, cx, ry + rowH);
            cx += col.w;
        });

        ry += rowH;
    }

    // Borde exterior de la tabla
    doc.setDrawColor(GRIS_CLARO[0], GRIS_CLARO[1], GRIS_CLARO[2]);
    doc.setLineWidth(0.3);
    doc.rect(ML, tableY, W, ry - tableY, 'S');

    // Fila SUB-TOTAL
    doc.setFillColor(237, 242, 247);
    doc.rect(ML, ry, W, subtotalH, 'F');
    doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9.5);
    const stVal = fmtQ(p.subtotal);
    doc.text(stVal, MR - 2, ry + 5.5, { align: 'right' });
    const stValW = doc.getTextWidth(stVal);
    doc.text('SUB-TOTAL:', MR - 2 - stValW - 4, ry + 5.5, { align: 'right' });
    doc.setDrawColor(GRIS_CLARO[0], GRIS_CLARO[1], GRIS_CLARO[2]);
    doc.setLineWidth(0.2);
    doc.line(ML, ry, MR, ry);
    ry += subtotalH;

    // Fila DESCUENTO (si aplica)
    if (descuentoH > 0) {
        doc.setFillColor(236, 253, 245);
        doc.rect(ML, ry, W, descuentoH, 'F');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        doc.setTextColor(VERDE[0], VERDE[1], VERDE[2]);
        const dsVal = '-' + fmtQ(p.descuento);
        doc.text(dsVal, MR - 2, ry + 4.8, { align: 'right' });
        const dsValW = doc.getTextWidth(dsVal);
        doc.text('DESCUENTO (' + (p.metodo_pago || '') + '):', MR - 2 - dsValW - 4, ry + 4.8, { align: 'right' });
        doc.setDrawColor(GRIS_CLARO[0], GRIS_CLARO[1], GRIS_CLARO[2]);
        doc.line(ML, ry, MR, ry);
        ry += descuentoH;
    }

    // ============ TEXTOS LEGALES ============
    let legalY = ry + 5;
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7);
    doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);

    const legalIzq = (p.texto_legal_isr || 'SUJETO A PAGOS TRIMESTRALES ISR');
    doc.text(legalIzq, ML, legalY);

    const legalCentro = (p.texto_legal_iva || 'EXENTA DEL IVA (art. 7 núm. 15 Ley del IVA)');
    doc.text(legalCentro, (ML + MR) / 2, legalY, { align: 'center' });

    doc.text('IVA: ' + fmtQ(0), MR, legalY, { align: 'right' });

    // ============ VALOR EN LETRAS + TOTAL ============
    const boxY = legalY + 5;
    const boxH = 17;

    // Caja: Valor en Letras
    drawBox(ML, boxY, col1W, boxH);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9.5);
    doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
    doc.text('Valor en Letras:', ML + 3, boxY + 6);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8.5);
    const letrasLines = doc.splitTextToSize(totalLetras, col1W - 6);
    doc.text(letrasLines.slice(0, 2).join(' '), ML + 3, boxY + 11.5);

    // Caja: Total
    drawBox(col2X, boxY, col2W, boxH);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(10);
    doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
    doc.text('Total:', col2X + 3, boxY + 6);
    doc.setFontSize(15);
    doc.setTextColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
    doc.text(fmtQ(p.total), MR - 3, boxY + 14, { align: 'right' });

    // ============ PIE ============
    const footerY = Math.max(247, boxY + boxH + 16);

    if (logo) {
        const fLogoW = 27;
        const fLogoH = fLogoW * (logo.h / logo.w);
        doc.addImage(logo.dataUrl, 'PNG', ML, footerY, fLogoW, Math.min(fLogoH, 18));
    } else {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(14);
        doc.setTextColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
        doc.text('CLINOVA', ML, footerY + 8);
    }

    // Banco: nombre + píldora con la cuenta
    const banco = (p.banco || '').trim();
    const cuentaTipo = (p.cuenta_tipo || '').trim();
    const cuentaNumero = (p.cuenta_numero || '').trim();
    const cuentaText = [cuentaTipo, cuentaNumero].filter(Boolean).join(' ');

    if (banco) {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(14);
        doc.setTextColor(30, 41, 59);
        doc.text(banco, MR, footerY + 5, { align: 'right' });
    }

    if (cuentaText) {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        const pillW = doc.getTextWidth(cuentaText) + 8;
        const pillH = 7;
        const pillY = footerY + 8;
        doc.setFillColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
        doc.roundedRect(MR - pillW, pillY, pillW, pillH, 3.5, 3.5, 'F');
        doc.setTextColor(BLANCO[0], BLANCO[1], BLANCO[2]);
        doc.text(cuentaText, MR - pillW / 2, pillY + 4.7, { align: 'center' });
    }

    return doc;
}
