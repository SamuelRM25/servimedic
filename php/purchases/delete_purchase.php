<?php
/**
 * Elimina una compra completa (todas las filas de una referencia).
 *
 * - Solo Administradores.
 * - Solo si TODO lo comprado sigue en inventario (no se ha vendido nada), para
 *   que las ventas ya hechas no queden descuadradas.
 * - Borra los abonos de la compra (dejan de aparecer como "Pago a proveedor"
 *   en Reportes) y el producto de esa compra en Inventario.
 * - Si una cotización pendiente usa ese lote, se pasa a otro lote del mismo
 *   medicamento; si no hay otro, no se permite eliminar.
 */
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}
if (($_SESSION['rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo un Administrador puede eliminar compras']);
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($data['ids'] ?? [])))));
    if (!$ids) throw new Exception('Compra inválida');

    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();

    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("SELECT * FROM compras WHERE id_compra IN ($in) FOR UPDATE");
    $stmt->execute($ids);
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($compras) !== count($ids)) throw new Exception('La compra ya no existe. Recarga la página.');

    $refs = array_unique(array_map(fn($c) => (string) $c['codigo_referencia'], $compras));
    if (count($refs) > 1) throw new Exception('Los productos no pertenecen a la misma compra');
    $ref = reset($refs);
    if ($ref !== '') {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM compras WHERE codigo_referencia = ?");
        $stmt->execute([$ref]);
        if ((int) $stmt->fetchColumn() !== count($ids)) {
            throw new Exception('La compra cambió mientras la revisabas. Recarga la página.');
        }
    }
    $etiqueta = $ref !== '' ? $ref : (string) $ids[0];

    // 1) Verificar que todo lo comprado siga en inventario
    $stmtInv = $conn->prepare("SELECT * FROM inventario WHERE id_compra = ? FOR UPDATE");
    $lotesBorrar = [];
    $vendidos = [];
    foreach ($compras as $c) {
        $stmtInv->execute([$c['id_compra']]);
        $lotes = $stmtInv->fetchAll(PDO::FETCH_ASSOC);
        $enInventario = array_sum(array_map(fn($l) => (int) $l['cantidad_med'], $lotes));
        if ($lotes && $enInventario < (int) $c['cantidad']) {
            $vendidos[] = sprintf('%s (salieron %d de %d)', $c['nombre_medicamento'],
                (int) $c['cantidad'] - $enInventario, (int) $c['cantidad']);
        }
        foreach ($lotes as $l) $lotesBorrar[(int) $l['id_inventario']] = $l;
    }
    if ($vendidos) {
        throw new Exception('No se puede eliminar: ya se vendió o despachó producto de esta compra — '
            . implode('; ', $vendidos) . '.');
    }

    // 2) Referencias a esos lotes en ventas o cotizaciones
    if ($lotesBorrar) {
        $idsInv = array_keys($lotesBorrar);
        $inInv = implode(',', array_fill(0, count($idsInv), '?'));

        $stmt = $conn->prepare("SELECT COUNT(*) FROM detalle_ventas WHERE id_inventario IN ($inInv)");
        $stmt->execute($idsInv);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new Exception('No se puede eliminar: hay ventas registradas con producto de esta compra.');
        }

        // Cotizaciones/pedidos que apuntan a estos lotes: pasarlos a otro lote del mismo medicamento
        $stmt = $conn->prepare("
            SELECT d.id_detalle_pedido, d.id_inventario, p.cotizacion_numero, p.id_pedido, p.es_cotizacion, p.estado_pedido
            FROM detalle_pedidos d JOIN pedidos p ON p.id_pedido = d.id_pedido
            WHERE d.id_inventario IN ($inInv)
        ");
        $stmt->execute($idsInv);
        $refsPed = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($refsPed) {
            $todos = $conn->query("SELECT * FROM inventario")->fetchAll(PDO::FETCH_ASSOC);
            $stmtMover = $conn->prepare("UPDATE detalle_pedidos SET id_inventario = ? WHERE id_detalle_pedido = ?");
            foreach ($refsPed as $rp) {
                $origen = $lotesBorrar[(int) $rp['id_inventario']];
                $clave = claveGrupoInventario($origen);
                $destino = null;
                foreach ($todos as $t) {
                    if (!isset($lotesBorrar[(int) $t['id_inventario']]) && claveGrupoInventario($t) === $clave) {
                        $destino = (int) $t['id_inventario'];
                        break;
                    }
                }
                if (!$destino) {
                    $doc = $rp['cotizacion_numero'] ?: ('Pedido #' . $rp['id_pedido']);
                    throw new Exception(sprintf(
                        'No se puede eliminar: %s está en %s y no hay otro lote de ese medicamento. Elimina o edita ese documento primero.',
                        $origen['nom_medicamento'], $doc
                    ));
                }
                $stmtMover->execute([$destino, $rp['id_detalle_pedido']]);
            }
        }

        $conn->prepare("DELETE FROM inventario WHERE id_inventario IN ($inInv)")->execute($idsInv);
    }

    // 3) Abonos y compra
    $stmt = $conn->prepare("SELECT COALESCE(SUM(monto), 0) FROM abonos_compras WHERE id_compra IN ($in)");
    $stmt->execute($ids);
    $abonosBorrados = (float) $stmt->fetchColumn();
    $conn->prepare("DELETE FROM abonos_compras WHERE id_compra IN ($in)")->execute($ids);
    $conn->prepare("DELETE FROM compras WHERE id_compra IN ($in)")->execute($ids);

    $conn->commit();

    $total = array_sum(array_map(fn($c) => (float) $c['total'], $compras));
    error_log(sprintf('[CLINOVA] Usuario %d eliminó la compra %s (%d productos, total Q%.2f, abonos Q%.2f)',
        $_SESSION['user_id'], $etiqueta, count($compras), $total, $abonosBorrados));

    echo json_encode([
        'success' => true,
        'message' => 'Compra #' . $etiqueta . ' eliminada',
        'productos' => count($compras),
        'abonos_eliminados' => round($abonosBorrados, 2),
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
