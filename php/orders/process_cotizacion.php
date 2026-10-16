<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $idPedido = (int) ($data['id_pedido'] ?? 0);
    if (!$idPedido) throw new Exception('ID de cotización inválido');

    $metodoPago = $data['metodo_pago'] ?? 'Efectivo';
    $tipoDocumento = $data['tipo_documento'] ?? 'Nota de Envío';
    $tipoPagoEstado = $data['tipo_pago_estado'] ?? 'Al Contado';

    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id_pedido = ?");
    $stmt->execute([$idPedido]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) throw new Exception('Cotización no encontrada');
    if (!$pedido['es_cotizacion']) throw new Exception('El pedido no es una cotización');
    if ($pedido['estado_pedido'] === 'Completado') throw new Exception('La cotización ya fue procesada');
    if ($pedido['estado_pedido'] === 'Cancelado') throw new Exception('La cotización está cancelada');

    $stmt = $conn->prepare("SELECT * FROM detalle_pedidos WHERE id_pedido = ?");
    $stmt->execute([$idPedido]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$detalles) throw new Exception('La cotización no tiene productos');

    // Recalcular subtotal desde el servidor
    $subtotal = 0.0;
    foreach ($detalles as $det) {
        $subtotal += (float) $det['subtotal'];
    }
    $subtotal = round($subtotal, 2);

    // Descuento por método de pago: solo informativo para Reportes.
    // El cliente siempre paga el total completo.
    $desc = calcularDescuento($subtotal, $metodoPago);

    $conn->beginTransaction();

    // Descontar inventario
    $stmtInventario = $conn->prepare("
        UPDATE inventario
        SET cantidad_med = cantidad_med - ?
        WHERE id_inventario = ? AND cantidad_med >= ?
    ");
    foreach ($detalles as $det) {
        if (empty($det['id_inventario'])) continue;
        $stmtInventario->execute([$det['cantidad'], $det['id_inventario'], $det['cantidad']]);
        if ($stmtInventario->rowCount() === 0) {
            throw new Exception('Stock insuficiente para: ' . ($det['descripcion'] ?: $det['id_inventario']));
        }
    }

    // Actualizar el pedido con el método elegido y marcarlo completado.
    // total = subtotal (lo que cobra el cliente); descuento = solo reporte.
    $estadoPago = ($tipoPagoEstado === 'Crédito') ? 'Pendiente Crédito' : 'Pagado';

    $stmt = $conn->prepare("
        UPDATE pedidos SET
            metodo_pago = ?,
            tipo_documento = ?,
            tipo_pago_estado = ?,
            subtotal = ?,
            cargo_cod = 0.00,
            subtotal_original = ?,
            descuento = ?,
            total = ?,
            estado_pedido = 'Completado',
            estado_pago = ?
        WHERE id_pedido = ?
    ");
    $stmt->execute([
        $metodoPago,
        $tipoDocumento,
        $tipoPagoEstado,
        $subtotal,
        $subtotal,
        $desc['descuento'],
        $subtotal,
        $estadoPago,
        $idPedido
    ]);

    // Crear venta si es al contado
    $idVenta = null;
    if ($tipoPagoEstado === 'Al Contado') {
        $stmtVenta = $conn->prepare("
            INSERT INTO ventas (
                id_usuario, id_sucursal, cliente_nombre, cliente_nit, venta_personal,
                tipo_documento, es_credito, id_pedido, total, descuento, total_final, estado
            ) VALUES (?, ?, ?, ?, 0, ?, 0, ?, ?, ?, ?, 'Completada')
        ");
        $stmtVenta->execute([
            $pedido['id_usuario'],
            $pedido['id_sucursal'],
            $pedido['cliente_nombre'],
            'CF',
            $tipoDocumento,
            $idPedido,
            $subtotal,
            $desc['descuento'],
            $subtotal
        ]);
        $idVenta = $conn->lastInsertId();

        $stmtDetalleVenta = $conn->prepare("
            INSERT INTO detalle_ventas (id_venta, id_inventario, cantidad, precio_unitario, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($detalles as $det) {
            if (empty($det['id_inventario'])) continue;
            $stmtDetalleVenta->execute([
                $idVenta,
                (int) $det['id_inventario'],
                $det['cantidad'],
                $det['precio_unitario'],
                $det['subtotal']
            ]);
        }

        $stmtFormaPago = $conn->prepare("
            INSERT INTO formas_pago (id_venta, tipo_pago, monto)
            VALUES (?, ?, ?)
        ");
        $stmtFormaPago->execute([$idVenta, formaPagoDesdeMetodo($metodoPago), $subtotal]);

        $stmt = $conn->prepare("UPDATE pedidos SET id_venta = ? WHERE id_pedido = ?");
        $stmt->execute([$idVenta, $idPedido]);
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Cotización procesada como venta exitosamente',
        'id_pedido' => $idPedido,
        'id_venta' => $idVenta,
        'subtotal' => $subtotal,
        'descuento' => $desc['descuento'],
        'desglose' => $desc['desglose'],
        'total' => $subtotal
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
