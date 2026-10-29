<?php
/**
 * Elimina definitivamente un pedido, venta o cotización.
 *
 * - Solo Administradores.
 * - Si el documento ya había descontado inventario (venta directa o cotización
 *   procesada), devuelve las cantidades al inventario.
 * - Borra la venta asociada (ventas, detalle_ventas, formas_pago) para que
 *   deje de contar en Reportes.
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
    echo json_encode(['success' => false, 'message' => 'Solo un Administrador puede eliminar documentos']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $idPedido = (int) ($data['id_pedido'] ?? 0);
    if (!$idPedido) throw new Exception('ID inválido');

    $database = new Database();
    $conn = $database->getConnection();

    $conn->beginTransaction();

    $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id_pedido = ? FOR UPDATE");
    $stmt->execute([$idPedido]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) throw new Exception('Documento no encontrado');

    // ¿Este documento descontó inventario?
    // - Venta directa: se descuenta al crearla.
    // - Cotización: solo cuando se procesa (estado Completado).
    $descontoInventario = $pedido['estado_pedido'] !== 'Cancelado'
        && (!$pedido['es_cotizacion'] || $pedido['estado_pedido'] === 'Completado');

    $unidadesDevueltas = 0;
    if ($descontoInventario) {
        $stmt = $conn->prepare("
            SELECT id_inventario, SUM(cantidad) AS cantidad
            FROM detalle_pedidos
            WHERE id_pedido = ? AND id_inventario IS NOT NULL
            GROUP BY id_inventario
        ");
        $stmt->execute([$idPedido]);
        $stmtDevolver = $conn->prepare("UPDATE inventario SET cantidad_med = cantidad_med + ? WHERE id_inventario = ?");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $det) {
            $stmtDevolver->execute([(int) $det['cantidad'], (int) $det['id_inventario']]);
            $unidadesDevueltas += (int) $det['cantidad'];
        }
    }

    // Ventas generadas por este pedido (detalle_ventas y formas_pago se borran en cascada)
    $stmt = $conn->prepare("SELECT id_venta FROM ventas WHERE id_pedido = ?");
    $stmt->execute([$idPedido]);
    $idsVenta = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($pedido['id_venta'])) $idsVenta[] = (int) $pedido['id_venta'];
    $idsVenta = array_values(array_unique(array_map('intval', $idsVenta)));
    if ($idsVenta) {
        $in = implode(',', array_fill(0, count($idsVenta), '?'));
        $conn->prepare("DELETE FROM formas_pago WHERE id_venta IN ($in)")->execute($idsVenta);
        $conn->prepare("DELETE FROM detalle_ventas WHERE id_venta IN ($in)")->execute($idsVenta);
        $conn->prepare("DELETE FROM ventas WHERE id_venta IN ($in)")->execute($idsVenta);
    }

    // Registro de contactos por WhatsApp (la tabla puede no existir)
    try {
        $conn->prepare("DELETE FROM contact_log WHERE id_pedido = ?")->execute([$idPedido]);
    } catch (Exception $e) { /* sin tabla contact_log */ }

    // Documentos que apuntan a este como cotización de origen
    $conn->prepare("UPDATE pedidos SET cotizacion_origen_id = NULL WHERE cotizacion_origen_id = ?")->execute([$idPedido]);

    $conn->prepare("DELETE FROM detalle_pedidos WHERE id_pedido = ?")->execute([$idPedido]);
    $conn->prepare("DELETE FROM pedidos WHERE id_pedido = ?")->execute([$idPedido]);

    $conn->commit();

    error_log(sprintf(
        '[CLINOVA] Usuario %d eliminó pedido %d (%s, %s, total Q%s, %d unidades devueltas al inventario)',
        $_SESSION['user_id'], $idPedido,
        $pedido['es_cotizacion'] ? 'cotización' : 'venta',
        $pedido['estado_pedido'], $pedido['total'], $unidadesDevueltas
    ));

    echo json_encode([
        'success' => true,
        'message' => 'Documento eliminado',
        'unidades_devueltas' => $unidadesDevueltas,
        'ventas_eliminadas' => count($idsVenta)
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
