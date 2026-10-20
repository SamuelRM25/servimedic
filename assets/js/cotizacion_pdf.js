/**
 * Generador de PDF de Cotizaciones y Ventas CLINOVA (jsPDF).
 *
 * Uso:
 *   const doc = await generarCotizacionPDF(data);                    // cotización
 *   const doc = await generarCotizacionPDF(data, { tipo: 'venta' }); // venta realizada
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

        // Reducir y comprimir el logo (JPEG sobre fondo blanco) para que el PDF sea ligero
        const maxW = 700;
        const scale = Math.min(1, maxW / img.naturalWidth);
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        return {
            dataUrl: canvas.toDataURL('image/jpeg', 0.9),
            type: 'JPEG',
            w: canvas.width,
            h: canvas.height
        };
    } catch (e) {
        return null;
    }
}

async function generarCotizacionPDF(data, opts = {}) {
    const { jsPDF } = window.jspdf;

    const p = data.pedido;
    const items = data.productos || [];
    const totalLetras = data.total_letras || '';
    const esVenta = opts.tipo === 'venta';

    // ============ PALETA CLINOVA ============
    const AZUL = [31, 168, 255];        // #1FA8FF
    const AZUL_OSCURO = [2, 119, 189];  // #0277BD
    const AZUL_PROFUNDO = [1, 87, 155]; // #01579B
    const TEXTO = [30, 41, 59];         // #1E293B
    const TEXTO_CLARO = [100, 116, 139];// #64748B
    const LINEA = [226, 232, 240];      // #E2E8F0
    const FONDO_SUAVE = [247, 250, 252];
    const VERDE = [22, 163, 74];
    const AMBAR = [217, 119, 6];
    const BLANCO = [255, 255, 255];

    const doc = new jsPDF({ unit: 'mm', format: 'letter' });
    const PAGE_W = 215.9;
    const PAGE_H = 279.4;
    const ML = 14;
    const MR = PAGE_W - 14;
    const W = MR - ML;

    const logo = await loadClinovaLogo();

    const titulo = esVenta ? 'COMPROBANTE DE VENTA' : 'COTIZACIÓN';
    const numero = esVenta
        ? (p.numero_venta || ('VEN-' + p.id_pedido))
        : (p.cotizacion_numero || ('COT-' + p.id_pedido));

    doc.setProperties({
        title: titulo + ' ' + numero,
        subject: esVenta ? 'Comprobante de venta CLINOVA' : 'Cotización CLINOVA',
        author: 'CLINOVA',
        creator: 'CLINOVA'
    });

    // ============ ENCABEZADO ============
    // Banda superior
    doc.setFillColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
    doc.rect(0, 0, PAGE_W, 5, 'F');
    doc.setFillColor(AZUL[0], AZUL[1], AZUL[2]);
    doc.rect(0, 5, PAGE_W, 1, 'F');

    // Logo grande
    let headerBottom = 48;
    if (logo) {
        const logoW = 56;
        const logoH = Math.min(36, logoW * (logo.h / logo.w));
        doc.addImage(logo.dataUrl, logo.type, ML, 11, logoW, logoH);
        headerBottom = Math.max(headerBottom, 11 + logoH + 3);
    } else {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(26);
        doc.setTextColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
        doc.text('CLINOVA', ML, 27);
    }

    // Título del documento
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
    doc.setFontSize(esVenta ? 17 : 20);
    doc.text(titulo, MR, 19, { align: 'right' });

    // Número
    doc.setFontSize(11);
    doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
    doc.text(numero, MR, 26, { align: 'right' });

    // Fecha
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8.8);
    doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
    doc.text('Fecha: ' + fmtFecha(p.fecha_pedido), MR, 31.5, { align: 'right' });

    // Píldora de teléfonos
    const tels = [p.suc_telefono, p.suc_telefono2].filter(Boolean).join(' • ');
    if (tels) {
        const telText = 'Teléfono: ' + tels;
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8.5);
        const pillW = doc.getTextWidth(telText) + 9;
        const pillH = 6.6;
        const pillY = 34.5;
        doc.setFillColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
        doc.roundedRect(MR - pillW, pillY, pillW, pillH, 3.3, 3.3, 'F');
        doc.setTextColor(BLANCO[0], BLANCO[1], BLANCO[2]);
        doc.text(telText, MR - pillW / 2, pillY + 4.4, { align: 'center' });
    }

    // Dirección de la sucursal (derecha)
    const direccion = (p.direccion_pdf || '').trim();
    if (direccion) {
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
        const dirLines = doc.splitTextToSize(direccion, 105).slice(0, 2);
        let dirY = 46;
        dirLines.forEach(line => {
            doc.text(line, MR, dirY, { align: 'right' });
            dirY += 3.6;
        });
        headerBottom = Math.max(headerBottom, dirY + 1);
    }

    // Línea divisoria del encabezado
    doc.setDrawColor(LINEA[0], LINEA[1], LINEA[2]);
    doc.setLineWidth(0.4);
    doc.line(ML, headerBottom, MR, headerBottom);

    // ============ CAJAS DE DATOS ============
    function drawPanel(x, y, w, h, tituloPanel) {
        doc.setFillColor(255, 255, 255);
        doc.setDrawColor(LINEA[0], LINEA[1], LINEA[2]);
        doc.setLineWidth(0.35);
        doc.roundedRect(x, y, w, h, 2.5, 2.5, 'FD');
        if (tituloPanel) {
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(6.8);
            doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
            doc.text(tituloPanel, x + 4, y + 4.6);
        }
    }

    function drawKv(x, y, w, label, value, size = 8.6, color = TEXTO) {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(size);
        doc.setTextColor(color[0], color[1], color[2]);
        const labelText = label + ': ';
        doc.text(labelText, x, y);
        const lw = doc.getTextWidth(labelText);
        doc.setFont('helvetica', 'normal');
        const val = String(value == null ? '' : value);
        if (!val) return;
        const lines = doc.splitTextToSize(val, Math.max(8, w - lw));
        doc.text(lines[0], x + lw, y);
    }

    const boxY = headerBottom + 5;
    const boxH = 27;
    const clientW = 118;
    const docX = ML + clientW + 5;
    const docW = MR - docX;

    // Caja cliente
    drawPanel(ML, boxY, clientW, boxH, 'DATOS DEL CLIENTE');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11.5);
    doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
    doc.text(String(p.cliente_nombre || 'Cliente'), ML + 4, boxY + 11.5);

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8.6);
    doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
    if (p.cliente_direccion) {
        const dl = doc.splitTextToSize(String(p.cliente_direccion), clientW - 8);
        doc.text(dl.slice(0, 2).join(' '), ML + 4, boxY + 17.2);
    }
    if (p.cliente_telefono) {
        doc.text('Teléfono: ' + p.cliente_telefono, ML + 4, boxY + 23.2);
    }

    // Caja documento
    drawPanel(docX, boxY, docW, boxH, 'DATOS DEL DOCUMENTO');
    const kvX = docX + 4;
    const kvW = docW - 8;
    drawKv(kvX, boxY + 11, kvW, 'Fecha', fmtFecha(p.fecha_pedido));
    drawKv(kvX, boxY + 16.4, kvW, 'Condiciones', p.condiciones_pago || (p.tipo_pago_estado || '-'));

    if (esVenta) {
        drawKv(kvX, boxY + 21.8, kvW, 'Método', p.metodo_pago || '-');
        const estado = p.estado_pago || 'Pendiente';
        const estadoColor = (estado === 'Pagado') ? VERDE : AMBAR;
        drawKv(kvX, boxY + 24.8, kvW, 'Estado', estado, 7.8, estadoColor);
    } else {
        drawKv(kvX, boxY + 21.8, kvW, 'Validez', '30 días');
    }

    // ============ TABLA DE ITEMS ============
    const tableY = boxY + boxH + 7;
    const headerH = 8.5;
    const subtotalH = 8.5;
    const maxTableBottom = 216;

    const cols = [
        { key: 'codigo', label: 'CÓDIGO', w: 24, align: 'left' },
        { key: 'desc', label: 'DESCRIPCIÓN', w: 76, align: 'left' },
        { key: 'cant', label: 'CANTIDAD', w: 21, align: 'center' },
        { key: 'punit', label: 'PRECIO UNIT.', w: 32, align: 'right' },
        { key: 'total', label: 'TOTAL', w: 34.9, align: 'right' }
    ];

    // Filas mínimas para que el documento se vea completo
    const minRows = 6;
    const rowsCount = Math.max(items.length, minRows);
    const available = maxTableBottom - tableY - headerH - subtotalH;
    const rowH = Math.max(4.4, Math.min(7, available / rowsCount));

    // Encabezado de tabla
    doc.setFillColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
    doc.roundedRect(ML, tableY, W, headerH, 1.5, 1.5, 'F');
    doc.setTextColor(BLANCO[0], BLANCO[1], BLANCO[2]);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7.8);
    let cx = ML;
    cols.forEach(col => {
        let tx = cx + 2.5;
        let align = 'left';
        if (col.align === 'center') { tx = cx + col.w / 2; align = 'center'; }
        if (col.align === 'right') { tx = cx + col.w - 2.5; align = 'right'; }
        doc.text(col.label, tx, tableY + 5.5, { align });
        cx += col.w;
    });

    // Filas
    let ry = tableY + headerH;
    const tableBodyTop = ry;
    for (let i = 0; i < rowsCount; i++) {
        const it = items[i];

        if (it && i % 2 === 1) {
            doc.setFillColor(FONDO_SUAVE[0], FONDO_SUAVE[1], FONDO_SUAVE[2]);
            doc.rect(ML, ry, W, rowH, 'F');
        }

        doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8.3);

        const codigo = it ? (it.codigo_medicamento || it.codigo_item || it.codigo_barras || it.codigo_referencia || '') : '';
        const desc = it ? (it.descripcion || it.nom_medicamento || '') : '';
        const cant = it ? String(it.cantidad) : '';
        const punit = it ? fmtQ(it.precio_unitario) : '';
        const totalItem = it ? fmtQ(it.subtotal) : '';

        const vals = [codigo, desc, cant, punit, totalItem];
        cx = ML;
        cols.forEach((col, ci) => {
            let v = vals[ci];
            if (!it) v = '';

            let tx = cx + 2.5;
            let align = 'left';
            if (col.align === 'center') { tx = cx + col.w / 2; align = 'center'; }
            if (col.align === 'right') { tx = cx + col.w - 2.5; align = 'right'; }

            if (col.key === 'desc' && v) {
                const maxW = col.w - 5;
                let line = String(v);
                if (doc.getTextWidth(line) > maxW) {
                    while (line.length > 1 && doc.getTextWidth(line + '…') > maxW) {
                        line = line.slice(0, -1);
                    }
                    line = line + '…';
                }
                v = line;
            }

            if (v !== '') doc.text(v, tx, ry + rowH / 2 + 1.1, { align });
            cx += col.w;
        });

        doc.setDrawColor(LINEA[0], LINEA[1], LINEA[2]);
        doc.setLineWidth(0.2);
        doc.line(ML, ry + rowH, MR, ry + rowH);

        // Separadores verticales (solo cuerpo)
        cx = ML;
        cols.forEach((col, ci) => {
            if (ci > 0) doc.line(cx, ry, cx, ry + rowH);
            cx += col.w;
        });

        ry += rowH;
    }

    // Borde exterior tabla
    doc.setDrawColor(LINEA[0], LINEA[1], LINEA[2]);
    doc.setLineWidth(0.35);
    doc.rect(ML, tableY, W, ry - tableY, 'S');

    // Fila SUB-TOTAL
    doc.setFillColor(241, 245, 249);
    doc.rect(ML, ry, W, subtotalH, 'F');
    doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8.8);
    const stLabel = 'SUB-TOTAL:';
    const stVal = fmtQ(p.subtotal);
    doc.text(stLabel, MR - 2.5 - doc.getTextWidth(stVal) - doc.getTextWidth(stLabel) - 4, ry + 5.8, { align: 'left' });
    doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
    doc.text(stVal, MR - 2.5, ry + 5.8, { align: 'right' });
    doc.setDrawColor(LINEA[0], LINEA[1], LINEA[2]);
    doc.setLineWidth(0.2);
    doc.line(ML, ry, MR, ry);
    ry += subtotalH;

    // ============ VALOR EN LETRAS + TOTAL ============
    const gap = 6;
    const sumW = 76;
    const sumX = MR - sumW;
    const letrasW = W - sumW - gap;
    const totalsY = ry + 6;

    const sumRows = 1;
    const totalsH = sumRows * 5.6 + 13;
    const boxTotalH = Math.max(totalsH + 4, 24);

    // Caja valor en letras
    drawPanel(ML, totalsY, letrasW, boxTotalH, 'VALOR EN LETRAS');
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8.2);
    doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
    const letrasLines = doc.splitTextToSize(totalLetras || '', letrasW - 8);
    doc.text(letrasLines.slice(0, 3).join(' '), ML + 4, totalsY + 11);

    // Detalle de totales (derecha)
    let sy = totalsY + 6;
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8.8);
    doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
    doc.text('Subtotal', sumX + 3, sy);
    doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
    doc.text(fmtQ(p.subtotal), MR - 3, sy, { align: 'right' });
    sy += 5.6;

    // Caja TOTAL
    const totalBoxY = sy - 1;
    doc.setFillColor(AZUL_PROFUNDO[0], AZUL_PROFUNDO[1], AZUL_PROFUNDO[2]);
    doc.roundedRect(sumX, totalBoxY, sumW, 12.5, 2, 2, 'F');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.setTextColor(255, 255, 255);
    doc.text('TOTAL', sumX + 4, totalBoxY + 7.8);
    doc.setFontSize(14);
    doc.text(fmtQ(p.total), MR - 4, totalBoxY + 8.2, { align: 'right' });

    // ============ TEXTOS LEGALES ============
    const legalY = totalsY + boxTotalH + 6;
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(6.8);
    doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);

    const legalIzq = (p.texto_legal_isr || 'SUJETO A PAGOS TRIMESTRALES ISR');
    doc.text(legalIzq, ML, legalY);

    const legalCentro = (p.texto_legal_iva || 'EXENTA DEL IVA (art. 7 núm. 15 Ley del IVA)');
    doc.text(legalCentro, (ML + MR) / 2, legalY, { align: 'center' });

    doc.text('IVA: ' + fmtQ(0), MR, legalY, { align: 'right' });

    // ============ PIE ============
    const footerLineY = Math.max(247, legalY + 8);
    doc.setDrawColor(LINEA[0], LINEA[1], LINEA[2]);
    doc.setLineWidth(0.4);
    doc.line(ML, footerLineY, MR, footerLineY);

    const footerY = footerLineY + 5;
    if (logo) {
        const fLogoW = 24;
        const fLogoH = Math.min(16, fLogoW * (logo.h / logo.w));
        doc.addImage(logo.dataUrl, logo.type, ML, footerY, fLogoW, fLogoH);
    } else {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(12);
        doc.setTextColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
        doc.text('CLINOVA', ML, footerY + 8);
    }

    // Banco + cuenta
    const banco = (p.banco || '').trim();
    const cuentaTipo = (p.cuenta_tipo || '').trim();
    const cuentaNumero = (p.cuenta_numero || '').trim();
    const cuentaText = [cuentaTipo, cuentaNumero].filter(Boolean).join(' ');

    if (banco) {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(11);
        doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
        doc.text(banco, MR, footerY + 4, { align: 'right' });
    }
    if (cuentaText) {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8.3);
        const pillW = doc.getTextWidth(cuentaText) + 9;
        const pillH = 6.4;
        const pillY = footerY + 7.5;
        doc.setFillColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
        doc.roundedRect(MR - pillW, pillY, pillW, pillH, 3.2, 3.2, 'F');
        doc.setTextColor(BLANCO[0], BLANCO[1], BLANCO[2]);
        doc.text(cuentaText, MR - pillW / 2, pillY + 4.3, { align: 'center' });
    }

    // Pie inferior centrado
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7);
    doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
    doc.text('Gracias por su preferencia', (ML + MR) / 2, PAGE_H - 7, { align: 'center' });
    doc.text('Página 1 de 1', MR, PAGE_H - 7, { align: 'right' });

    return doc;
}
