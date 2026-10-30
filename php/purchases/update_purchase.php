<?php
/**
 * Edita una compra existente (todas las filas de una misma referencia).
 *
 * Permite cambiar:
 *  - Datos generales: fecha, tipo y número de factura, proveedor, observaciones.
 *  - Tipo de pago (Al Contado / Crédito 30 / Crédito 60), recalculando todo lo
 *    que depende de él:
 *      · Al Contado crea un abono automático ("Pago al contado - Compra #REF")
 *        por lo que falte pagar, y deja saldo 0 / estado Pagado.
 *      · Crédito anula ese abono automático; se conservan los abonos manuales
 *        y el saldo/estado se recalculan con ellos.
 *  - Por producto: cantidad, precio de compra, precio de venta, lote y
 *    vencimiento. La diferencia de cantidad se ajusta en el inventario.
 */
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

const ABONO_CONTADO_PREFIJO = 'Pago al contado';
const TIPOS_PAGO = ['Al Contado', 'Crédito 30 días', 'Crédito 60 días'];
const TIPOS_FACTURA = ['Factura', 'Nota de Envío', 'Consumidor Final'];
const FORMAS_PAGO = ['Efectivo', 'Tarjeta Débito', 'Tarjeta Crédito', 'Transferencia', 'Cheque'];

function fechaValida(?string $f): bool {
    if (!$f) return false;
    $d = DateTime::createFromFormat('Y-m-d', $f);
    return $d && $d->format('Y-m-d') === $f;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) throw new Exception('Datos inválidos');

    // ---------- Validación de datos generales ----------
    $fechaCompra   = (string) ($data['fecha_compra'] ?? '');
    $tipoFactura   = (string) ($data['tipo_factura'] ?? '');
    $numeroFactura = trim((string) ($data['numero_factura'] ?? ''));
    $tipoPago      = (string) ($data['tipo_pago'] ?? '');
    $proveedor     = trim((string) ($data['proveedor'] ?? ''));
    $observaciones = trim((string) ($data['observaciones'] ?? ''));
    $formaPagoContado = (string) ($data['forma_pago_contado'] ?? 'Efectivo');

    if (!fechaValida($fechaCompra)) throw new Exception('Fecha de compra inválida');
    if (!in_array($tipoFactura, TIPOS_FACTURA, true)) throw new Exception('Tipo de factura inválido');
    if (!in_array($tipoPago, TIPOS_PAGO, true)) throw new Exception('Tipo de pago inválido');
    if (!in_array($formaPagoContado, FORMAS_PAGO, true)) $formaPagoContado = 'Efectivo';
    if ($proveedor === '') throw new Exception('El proveedor es obligatorio');

    $itemsIn = $data['items'] ?? [];
    if (!is_array($itemsIn) || !$itemsIn) throw new Exception('La compra no tiene productos');

    $itemsPorId = [];
    foreach ($itemsIn as $it) {
        $id = (int) ($it['id_compra'] ?? 0);
        $cant = (int) ($it['cantidad'] ?? 0);
        $pu = round((float) ($it['precio_unitario'] ?? -1), 2);
        $pv = round((float) ($it['precio_venta'] ?? -1), 2);
        if (!$id) throw new Exception('Producto inválido');
        if ($cant < 1) throw new Exception('La cantidad debe ser al menos 1');
        if ($pu < 0 || $pv < 0) throw new Exception('Los precios no pueden ser negativos');
        $venc = trim((string) ($it['fecha_vencimiento'] ?? ''));
        if ($venc !== '' && !fechaValida($venc)) throw new Exception('Fecha de vencimiento inválida');
        $itemsPorId[$id] = [
            'cantidad' => $cant,
            'precio_unitario' => $pu,
            'precio_venta' => $pv,
            'lote' => mb_substr(trim((string) ($it['lote'] ?? '')), 0, 50),
            'fecha_vencimiento' => $venc !== '' ? $venc : null,
        ];
    }

    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();

    // ---------- Filas actuales de la compra ----------
    $ids = array_keys($itemsPorId);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT * FROM compras WHERE id_compra IN ($in) FOR UPDATE");
    $stmt->execute($ids);
    $actuales = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $actuales[(int) $row['id_compra']] = $row;
    if (count($actuales) !== count($ids)) throw new Exception('Alguno de los productos de la compra ya no existe');

    $referencias = array_unique(array_map(fn($r) => (string) $r['codigo_referencia'], $actuales));
    if (count($referencias) > 1) throw new Exception('Los productos no pertenecen a la misma compra');
    $referencia = reset($referencias);
    if ($referencia !== '') {
        // Deben venir todos los productos de la referencia
        $stmt = $conn->prepare("SELECT COUNT(*) FROM compras WHERE codigo_referencia = ?");
        $stmt->execute([$referencia]);
        if ((int) $stmt->fetchColumn() !== count($ids)) {
            throw new Exception('La compra cambió mientras la editabas. Recarga la página e inténtalo de nuevo.');
        }
    }
    $etiquetaRef = $referencia !== '' ? $referencia : (string) $ids[0];

    // Proveedor: registrar en el catálogo si es nuevo
    $conn->prepare("INSERT IGNORE INTO proveedores (nombre) VALUES (?)")->execute([$proveedor]);

    $stmtInv = $conn->prepare("
        SELECT id_inventario, cantidad_med FROM inventario
        WHERE id_compra = ? ORDER BY id_inventario ASC LIMIT 1 FOR UPDATE
    ");
    $stmtInvUpd = $conn->prepare("
        UPDATE inventario SET cantidad_med = cantidad_med + ? WHERE id_inventario = ?
    ");
    $stmtInvDatos = $conn->prepare("
        UPDATE inventario SET precio_costo = ?, precio_venta = ?, fecha_vencimiento = ?,
               fecha_adquisicion = ?, tipo_factura = ?, numero_factura = ?
        WHERE id_compra = ?
    ");
    $stmtBorrarAutos = $conn->prepare("
        DELETE FROM abonos_compras WHERE id_compra = ? AND observaciones LIKE ?
    ");
    $stmtManual = $conn->prepare("SELECT COALESCE(SUM(monto), 0) FROM abonos_compras WHERE id_compra = ?");
    $stmtNuevoAbono = $conn->prepare("
        INSERT INTO abonos_compras (id_compra, id_usuario, monto, fecha_abono, forma_pago, observaciones)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtCompra = $conn->prepare("
        UPDATE compras SET
            fecha_compra = ?, tipo_factura = ?, numero_factura = ?, proveedor = ?, observaciones = ?,
            tipo_pago = ?, cantidad = ?, precio_unitario = ?, precio_venta = ?, lote = ?, fecha_vencimiento = ?,
            total = ?, abonado = ?, saldo = ?, estado = ?
        WHERE id_compra = ?
    ");

    $resumen = ['total' => 0.0, 'abonado' => 0.0, 'saldo' => 0.0];

    foreach ($itemsPorId as $id => $it) {
        $act = $actuales[$id];

        // --- Inventario: ajustar por la diferencia de cantidad ---
        $delta = $it['cantidad'] - (int) $act['cantidad'];
        if ($delta !== 0) {
            $stmtInv->execute([$id]);
            $inv = $stmtInv->fetch(PDO::FETCH_ASSOC);
            if ($inv) {
                if ((int) $inv['cantidad_med'] + $delta < 0) {
                    $minimo = (int) $act['cantidad'] - (int) $inv['cantidad_med'];
                    throw new Exception(sprintf(
                        '%s: no se puede bajar a %d unidades porque ya salieron %d del inventario (mínimo %d).',
                        $act['nombre_medicamento'], $it['cantidad'], $minimo, $minimo
                    ));
                }
                $stmtInvUpd->execute([$delta, $inv['id_inventario']]);
            }
        }
        $stmtInvDatos->execute([
            $it['precio_unitario'], $it['precio_venta'], $it['fecha_vencimiento'],
            $fechaCompra, $tipoFactura, $numeroFactura !== '' ? $numeroFactura : null, $id
        ]);

        // --- Pagos ---
        $total = round($it['cantidad'] * $it['precio_unitario'], 2);

        // Quitar el abono automático de "al contado" (se vuelve a crear si sigue siendo contado)
        $stmtBorrarAutos->execute([$id, ABONO_CONTADO_PREFIJO . '%']);

        $stmtManual->execute([$id]);
        $abonosManuales = round((float) $stmtManual->fetchColumn(), 2);

        if ($abonosManuales > $total + 0.009) {
            throw new Exception(sprintf(
                '%s: ya tiene abonos por Q%s, más que el nuevo total (Q%s).',
                $act['nombre_medicamento'], number_format($abonosManuales, 2), number_format($total, 2)
            ));
        }

        if ($tipoPago === 'Al Contado') {
            $falta = round($total - $abonosManuales, 2);
            if ($falta > 0.009) {
                $stmtNuevoAbono->execute([
                    $id, $_SESSION['user_id'], $falta, $fechaCompra, $formaPagoContado,
                    ABONO_CONTADO_PREFIJO . ' - Compra #' . $etiquetaRef
                ]);
            }
            $abonado = $total;
        } else {
            $abonado = $abonosManuales;
        }

        $saldo = round($total - $abonado, 2);
        if ($saldo <= 0.009) {
            $saldo = 0.0;
            $estado = 'Pagado';
        } elseif ($abonado > 0.009) {
            $estado = 'Abonado';
        } else {
            $estado = 'Pendiente';
        }

        $stmtCompra->execute([
            $fechaCompra, $tipoFactura, $numeroFactura !== '' ? $numeroFactura : null, $proveedor, $observaciones,
            $tipoPago, $it['cantidad'], $it['precio_unitario'], $it['precio_venta'],
            $it['lote'] !== '' ? $it['lote'] : null, $it['fecha_vencimiento'],
            $total, $abonado, $saldo, $estado, $id
        ]);

        $resumen['total'] += $total;
        $resumen['abonado'] += $abonado;
        $resumen['saldo'] += $saldo;
    }

    $conn->commit();

    error_log(sprintf('[CLINOVA] Usuario %d editó la compra %s (%s, total Q%.2f, saldo Q%.2f)',
        $_SESSION['user_id'], $etiquetaRef, $tipoPago, $resumen['total'], $resumen['saldo']));

    echo json_encode([
        'success' => true,
        'message' => 'Compra #' . $etiquetaRef . ' actualizada',
        'total' => round($resumen['total'], 2),
        'abonado' => round($resumen['abonado'], 2),
        'saldo' => round($resumen['saldo'], 2),
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
