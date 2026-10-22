/**
 * Generador de PDF de Cotizaciones y Ventas CLINOVA (jsPDF, media carta horizontal).
 *
 * Formato: 8.5 x 5.5 pulgadas (612 x 396 pt) para imprimir dos comprobantes
 * por hoja carta (2 páginas por hoja).
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

// Versión de los assets. Se toma del parámetro ?v= del propio <script> para que
// las imágenes (CLINOVA.png, logobanrural.png) también se invaliden: el servidor
// las envía con max-age de 7 días y el navegador las seguiría sirviendo viejas.
const COTIZACION_PDF_VERSION = (function () {
    try {
        return new URL(COTIZACION_PDF_BASE, window.location.href).searchParams.get('v') || '';
    } catch (e) {
        return '';
    }
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

async function loadPdfImage(relPath, maxW, quality) {
    try {
        const url = new URL(relPath, COTIZACION_PDF_BASE);
        if (COTIZACION_PDF_VERSION) url.searchParams.set('v', COTIZACION_PDF_VERSION);
        const blob = await fetch(url.href).then(r => {
            if (!r.ok) throw new Error('imagen no encontrada');
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

        // Reducir y comprimir (JPEG sobre fondo blanco) para que el PDF sea ligero
        const scale = Math.min(1, maxW / img.naturalWidth);
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#FFFFFF';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        return {
            dataUrl: canvas.toDataURL('image/jpeg', quality || 0.9),
            type: 'JPEG',
            w: canvas.width,
            h: canvas.height
        };
    } catch (e) {
        return null;
    }
}

function loadClinovaLogo() {
    return loadPdfImage('../img/CLINOVA.png', 650, 0.9);
}

function loadBanruralLogo() {
    return loadPdfImage('../img/logobanrural.png', 550, 0.9);
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

    // Media carta horizontal: 8.5 x 5.5 pulgadas = 215.9 x 139.7 mm
    const doc = new jsPDF({ unit: 'mm', orientation: 'landscape', format: [215.9, 139.7] });

    const PAGE_W = 215.9;
    const PAGE_H = 139.7;
    const ML = 10;
    const MR = PAGE_W - 10;
    const W = MR - ML;

    const [logo, banrural] = await Promise.all([loadClinovaLogo(), loadBanruralLogo()]);

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
    doc.setFillColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
    doc.rect(0, 0, PAGE_W, 4, 'F');
    doc.setFillColor(AZUL[0], AZUL[1], AZUL[2]);
    doc.rect(0, 4, PAGE_W, 0.8, 'F');

    // Logo grande
    let headerBottom = 33;
    if (logo) {
        const logoW = 32;
        const logoH = Math.min(22, logoW * (logo.h / logo.w));
        doc.addImage(logo.dataUrl, logo.type, ML, 6.5, logoW, logoH);
        headerBottom = Math.max(headerBottom, 6.5 + logoH + 2);
    } else {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(20);
        doc.setTextColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
        doc.text('CLINOVA', ML, 22);
    }

    // Título del documento
    doc.setFont('helvetica', 'bold');
    doc.setTextColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
    doc.setFontSize(esVenta ? 11.5 : 13);
    doc.text(titulo, MR, 13.2, { align: 'right' });

    // Número
    doc.setFontSize(8.4);
    doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
    doc.text(numero, MR, 18.7, { align: 'right' });

    // Fecha
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7);
    doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
    doc.text('Fecha: ' + fmtFecha(p.fecha_pedido), MR, 22.5, { align: 'right' });

    // Píldora de teléfonos
    const tels = [p.suc_telefono, p.suc_telefono2].filter(Boolean).join(' • ');
    if (tels) {
        const telText = 'Tel: ' + tels;
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(6.7);
        const pillW = doc.getTextWidth(telText) + 7;
        const pillH = 4.8;
        const pillY = 24.8;
        doc.setFillColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
        doc.roundedRect(MR - pillW, pillY, pillW, pillH, 2.4, 2.4, 'F');
        doc.setTextColor(BLANCO[0], BLANCO[1], BLANCO[2]);
        doc.text(telText, MR - pillW / 2, pillY + 3.2, { align: 'center' });
    }

    // Dirección de la sucursal (derecha, una línea)
    const direccion = (p.direccion_pdf || '').trim();
    if (direccion) {
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(6.5);
        doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
        const dirLines = doc.splitTextToSize(direccion, 150);
        doc.text(dirLines[0], MR, 31.8, { align: 'right' });
        headerBottom = Math.max(headerBottom, 33);
    }

    // Línea divisoria del encabezado
    doc.setDrawColor(LINEA[0], LINEA[1], LINEA[2]);
    doc.setLineWidth(0.3);
    doc.line(ML, headerBottom, MR, headerBottom);

    // ============ CAJAS DE DATOS ============
    function drawPanel(x, y, w, h, tituloPanel) {
        doc.setFillColor(255, 255, 255);
        doc.setDrawColor(LINEA[0], LINEA[1], LINEA[2]);
        doc.setLineWidth(0.25);
        doc.roundedRect(x, y, w, h, 1.9, 1.9, 'FD');
        if (tituloPanel) {
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(5.4);
            doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
            doc.text(tituloPanel, x + 3, y + 3.1);
        }
    }

    function drawKv(x, y, w, label, value, size = 6.6, color = TEXTO) {
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

    const boxY = headerBottom + 3.5;
    const boxH = 18;
    const clientW = 100;
    const docX = ML + clientW + 4;
    const docW = MR - docX;

    // Caja cliente
    drawPanel(ML, boxY, clientW, boxH, 'DATOS DEL CLIENTE');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
    doc.text(String(p.cliente_nombre || 'Cliente'), ML + 3, boxY + 7.8);

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(6.5);
    doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
    if (p.cliente_direccion) {
        const dl = doc.splitTextToSize(String(p.cliente_direccion), clientW - 6);
        doc.text(dl[0], ML + 3, boxY + 11.9);
    }
    if (p.cliente_telefono) {
        doc.text('Teléfono: ' + p.cliente_telefono, ML + 3, boxY + 15.9);
    }

    // Caja documento
    drawPanel(docX, boxY, docW, boxH, 'DATOS DEL DOCUMENTO');
    const kvX = docX + 3;
    const kvW = docW - 6;
    drawKv(kvX, boxY + 7.2, kvW, 'Fecha', fmtFecha(p.fecha_pedido));
    drawKv(kvX, boxY + 10.5, kvW, 'Condiciones', p.condiciones_pago || (p.tipo_pago_estado || '-'));

    if (esVenta) {
        drawKv(kvX, boxY + 13.8, kvW, 'Método', p.metodo_pago || '-');
        const estado = p.estado_pago || 'Pendiente';
        const estadoColor = (estado === 'Pagado') ? VERDE : AMBAR;
        drawKv(kvX, boxY + 17.1, kvW, 'Estado', estado, 6.4, estadoColor);
    } else {
        drawKv(kvX, boxY + 13.8, kvW, 'Validez', '30 días');
    }

    // ============ TABLA DE ITEMS ============
    const tableY = boxY + boxH + 4;
    const headerH = 6.3;
    const subtotalH = 6.3;
    const maxTableBottom = 101;

    const cols = [
        { key: 'codigo', label: 'CÓDIGO', w: 25, align: 'left' },
        { key: 'desc', label: 'DESCRIPCIÓN', w: 91.9, align: 'left' },
        { key: 'cant', label: 'CANT.', w: 20, align: 'center' },
        { key: 'punit', label: 'P. UNIT.', w: 28, align: 'right' },
        { key: 'total', label: 'TOTAL', w: 31, align: 'right' }
    ];

    // Filas mínimas para que el documento se vea completo
    const minRows = 5;
    const rowsCount = Math.max(items.length, minRows);
    const available = maxTableBottom - tableY - headerH - subtotalH;
    const rowH = Math.max(3.4, Math.min(5.8, available / rowsCount));

    // Encabezado de tabla
    doc.setFillColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
    doc.roundedRect(ML, tableY, W, headerH, 1.2, 1.2, 'F');
    doc.setTextColor(BLANCO[0], BLANCO[1], BLANCO[2]);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(6.4);
    let cx = ML;
    cols.forEach(col => {
        let tx = cx + 2;
        let align = 'left';
        if (col.align === 'center') { tx = cx + col.w / 2; align = 'center'; }
        if (col.align === 'right') { tx = cx + col.w - 2; align = 'right'; }
        doc.text(col.label, tx, tableY + 4.2, { align });
        cx += col.w;
    });

    // Filas
    let ry = tableY + headerH;
    for (let i = 0; i < rowsCount; i++) {
        const it = items[i];

        if (it && i % 2 === 1) {
            doc.setFillColor(FONDO_SUAVE[0], FONDO_SUAVE[1], FONDO_SUAVE[2]);
            doc.rect(ML, ry, W, rowH, 'F');
        }

        doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(6.9);

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

            let tx = cx + 2;
            let align = 'left';
            if (col.align === 'center') { tx = cx + col.w / 2; align = 'center'; }
            if (col.align === 'right') { tx = cx + col.w - 2; align = 'right'; }

            if (col.key === 'desc' && v) {
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

            if (v !== '') doc.text(v, tx, ry + rowH / 2 + 0.8, { align });
            cx += col.w;
        });

        doc.setDrawColor(LINEA[0], LINEA[1], LINEA[2]);
        doc.setLineWidth(0.12);
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
    doc.setLineWidth(0.25);
    doc.rect(ML, tableY, W, ry - tableY, 'S');

    // Fila SUB-TOTAL (única referencia de subtotal en el documento)
    doc.setFillColor(241, 245, 249);
    doc.rect(ML, ry, W, subtotalH, 'F');
    doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7.2);
    const stLabel = 'SUB-TOTAL:';
    const stVal = fmtQ(p.subtotal);
    doc.text(stLabel, MR - 2 - doc.getTextWidth(stVal) - doc.getTextWidth(stLabel) - 3, ry + 4.3, { align: 'left' });
    doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
    doc.text(stVal, MR - 2, ry + 4.3, { align: 'right' });
    doc.setDrawColor(LINEA[0], LINEA[1], LINEA[2]);
    doc.setLineWidth(0.12);
    doc.line(ML, ry, MR, ry);
    ry += subtotalH;

    // ============ VALOR EN LETRAS + TOTAL ============
    const totalsY = ry + 4;
    const sumW = 68;
    const sumX = MR - sumW;
    const letrasW = W - sumW - 5;
    const boxTotalH = 15;

    // Caja valor en letras
    drawPanel(ML, totalsY, letrasW, boxTotalH, 'VALOR EN LETRAS');
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(6.7);
    doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
    const letrasLines = doc.splitTextToSize(totalLetras || '', letrasW - 6).slice(0, 3);
    letrasLines.forEach((line, i) => {
        doc.text(line, ML + 3, totalsY + 7.8 + (i * 2.8));
    });

    // Caja TOTAL (sin repetir el subtotal)
    const totalBoxY = totalsY + 1.5;
    doc.setFillColor(AZUL_PROFUNDO[0], AZUL_PROFUNDO[1], AZUL_PROFUNDO[2]);
    doc.roundedRect(sumX, totalBoxY, sumW, 12.5, 1.6, 1.6, 'F');
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7.8);
    doc.setTextColor(255, 255, 255);
    doc.text('TOTAL', sumX + 3, totalBoxY + 7.9);
    doc.setFontSize(11.5);
    doc.text(fmtQ(p.total), MR - 3, totalBoxY + 8.2, { align: 'right' });

    // ============ TEXTOS LEGALES ============
    const legalY = totalsY + boxTotalH + 3;
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(5.2);
    doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);

    const legalIzq = (p.texto_legal_isr || 'SUJETO A PAGOS TRIMESTRALES ISR');
    doc.text(legalIzq, ML, legalY);

    const legalCentro = (p.texto_legal_iva || 'EXENTA DEL IVA (art. 7 núm. 15 Ley del IVA)');
    doc.text(legalCentro, (ML + MR) / 2, legalY, { align: 'center' });

    doc.text('IVA: ' + fmtQ(0), MR, legalY, { align: 'right' });

    // ============ PIE ============
    const footerLineY = Math.max(124, legalY + 2.5);
    doc.setDrawColor(LINEA[0], LINEA[1], LINEA[2]);
    doc.setLineWidth(0.3);
    doc.line(ML, footerLineY, MR, footerLineY);

    const footerY = footerLineY + 3;
    if (logo) {
        const fLogoW = 14;
        const fLogoH = Math.min(9.5, fLogoW * (logo.h / logo.w));
        doc.addImage(logo.dataUrl, logo.type, ML, footerY, fLogoW, fLogoH);
    } else {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
        doc.text('CLINOVA', ML, footerY + 5);
    }

    // Banco (logo Banrural si la sucursal usa ese banco)
    const banco = (p.banco || '').trim();
    const esBanrural = /banrural/i.test(banco);
    const bLogoW = 19;
    const bLogoH = banrural ? Math.min(10, bLogoW * (banrural.h / banrural.w)) : 0;
    if (esBanrural && banrural) {
        doc.addImage(banrural.dataUrl, banrural.type, MR - bLogoW, footerY, bLogoW, bLogoH);
    } else if (banco) {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        doc.setTextColor(TEXTO[0], TEXTO[1], TEXTO[2]);
        doc.text(banco, MR, footerY + 4, { align: 'right' });
    }

    // Cuenta bancaria (a la izquierda del logo del banco)
    const cuentaTipo = (p.cuenta_tipo || '').trim();
    const cuentaNumero = (p.cuenta_numero || '').trim();
    const cuentaText = [cuentaTipo, cuentaNumero].filter(Boolean).join(' ');

    if (cuentaText) {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(6.3);
        const pillW = doc.getTextWidth(cuentaText) + 6;
        const pillH = 4.9;
        const pillX = esBanrural && banrural ? (MR - bLogoW - 3 - pillW) : (MR - pillW);
        const pillY = footerY + 2.2;
        doc.setFillColor(AZUL_OSCURO[0], AZUL_OSCURO[1], AZUL_OSCURO[2]);
        doc.roundedRect(pillX, pillY, pillW, pillH, 2.45, 2.45, 'F');
        doc.setTextColor(BLANCO[0], BLANCO[1], BLANCO[2]);
        doc.text(cuentaText, pillX + pillW / 2, pillY + 3.25, { align: 'center' });
    }

    // Texto inferior centrado
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(6);
    doc.setTextColor(TEXTO_CLARO[0], TEXTO_CLARO[1], TEXTO_CLARO[2]);
    doc.text('Gracias por su preferencia', (ML + MR) / 2, PAGE_H - 2.4, { align: 'center' });

    return doc;
}
