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
    asegurarColumnasDescuentoEspecial($conn);

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

    // Descuento especial guardado en la cotización (se recalcula sobre el subtotal actual)
    $esp = calcularDescuentoEspecial(
        $subtotal,
        (string) ($pedido['descuento_especial_tipo'] ?? 'porcentaje'),
        $pedido['descuento_especial_valor'] ?? 0
    );
    $neto = round($subtotal - $esp['monto'], 2);   // lo que paga el cliente

    // Descuento por método de pago: solo informativo para Reportes.
    $desc = calcularDescuento($neto, $metodoPago);

    $conn->beginTransaction();

    // Descontar inventario por medicamento, empezando por el lote que vence primero.
    // Si se usan varios lotes, la línea de la cotización se divide en una por lote.
    $stmtActDet = $conn->prepare("UPDATE detalle_pedidos SET id_inventario = ?, cantidad = ?, subtotal = ? WHERE id_detalle_pedido = ?");
    $stmtNuevoDet = $conn->prepare("
        INSERT INTO detalle_pedidos (id_pedido, id_inventario, codigo_item, descripcion, cantidad, precio_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $lineasVenta = [];
    foreach ($detalles as $det) {
        if (empty($det['id_inventario'])) continue;
        $usados = descontarInventarioFEFO($conn, (int) $det['id_inventario'], (int) $det['cantidad'], (string) ($det['descripcion'] ?? ''));
        $usados = repartirSubtotal($usados, (float) $det['precio_unitario'], (float) $det['subtotal']);
        foreach ($usados as $i => $u) {
            if ($i === 0) {
                $stmtActDet->execute([$u['id_inventario'], $u['cantidad'], $u['subtotal'], $det['id_detalle_pedido']]);
            } else {
                $stmtNuevoDet->execute([$idPedido, $u['id_inventario'], $det['codigo_item'], $det['descripcion'], $u['cantidad'], $det['precio_unitario'], $u['subtotal']]);
            }
            $lineasVenta[] = ['id_inventario' => $u['id_inventario'], 'cantidad' => $u['cantidad'], 'precio' => (float) $det['precio_unitario'], 'subtotal' => $u['subtotal']];
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
            descuento_especial = ?,
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
        $neto,
        $esp['monto'],
        $estadoPago,
        $idPedido
    ]);

    // Crear la venta del pedido (al contado o a crédito).
    // Las ventas a crédito también generan registro para que aparezcan en
    // Reportes y tengan su número VEN-...; su forma de pago se registra
    // cuando se marcan como Pagadas.
    $idVenta = null;
    $esCredito = ($tipoPagoEstado === 'Crédito');
    $numeroVenta = generarNumeroVenta($conn);
    $stmtVenta = $conn->prepare("
        INSERT INTO ventas (
            numero_venta, id_usuario, id_sucursal, cliente_nombre, cliente_nit, venta_personal,
            tipo_documento, es_credito, id_pedido, total, descuento, total_final, estado
        ) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, 'Completada')
    ");
    $stmtVenta->execute([
        $numeroVenta,
        $pedido['id_usuario'],
        $pedido['id_sucursal'],
        $pedido['cliente_nombre'],
        'CF',
        $tipoDocumento,
        $esCredito ? 1 : 0,
        $idPedido,
        $neto,
        $desc['descuento'],
        $neto
    ]);
    $idVenta = $conn->lastInsertId();

    $stmtDetalleVenta = $conn->prepare("
        INSERT INTO detalle_ventas (id_venta, id_inventario, cantidad, precio_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($lineasVenta as $lv) {
        $stmtDetalleVenta->execute([
            $idVenta,
            $lv['id_inventario'],
            $lv['cantidad'],
            $lv['precio'],
            $lv['subtotal']
        ]);
    }

    // La forma de pago solo se registra si el cobro ya se realizó (contado).
    if (!$esCredito) {
        $stmtFormaPago = $conn->prepare("
            INSERT INTO formas_pago (id_venta, tipo_pago, monto)
            VALUES (?, ?, ?)
        ");
        $stmtFormaPago->execute([$idVenta, formaPagoDesdeMetodo($metodoPago), $neto]);
    }

    $stmt = $conn->prepare("UPDATE pedidos SET id_venta = ? WHERE id_pedido = ?");
    $stmt->execute([$idVenta, $idPedido]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Cotización procesada como venta exitosamente',
        'id_pedido' => $idPedido,
        'id_venta' => $idVenta,
        'numero_venta' => $numeroVenta,
        'subtotal' => $subtotal,
        'descuento' => $desc['descuento'],
        'desglose' => $desc['desglose'],
        'descuento_especial' => $esp['monto'],
        'total' => $neto
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
