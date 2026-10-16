<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || empty($data['productos'])) {
        throw new Exception('Debe agregar al menos un producto');
    }

    $database = new Database();
    $conn = $database->getConnection();

    // Obtener sucursal del usuario
    $stmt = $conn->prepare("SELECT id_sucursal FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userSucursal = $stmt->fetchColumn() ?: 1;

    $esCotizacion = !empty($data['es_cotizacion']);
    $idPedidoEditar = !empty($data['id_pedido']) ? (int) $data['id_pedido'] : null;

    // Recalcular montos en servidor (no confiar en los del cliente)
    $subtotal = 0.0;
    foreach ($data['productos'] as $idx => $producto) {
        $cant   = max(1, (int) ($producto['cantidad'] ?? 1));
        $precio = round((float) ($producto['precio'] ?? 0), 2);
        $data['productos'][$idx]['cantidad'] = $cant;
        $data['productos'][$idx]['precio']   = $precio;
        $data['productos'][$idx]['subtotal'] = round($cant * $precio, 2);
        $subtotal += $data['productos'][$idx]['subtotal'];
    }
    $subtotal = round($subtotal, 2);

    $metodoPago = $data['metodo_pago'] ?? 'Efectivo';
    // Descuento por método de pago: solo informativo para Reportes.
    // Al cliente siempre se le cobra el total completo.
    $desc = calcularDescuento($subtotal, $metodoPago);

    $conn->beginTransaction();

    if ($idPedidoEditar) {
        // ---------- EDITAR COTIZACIÓN EXISTENTE ----------
        $stmt = $conn->prepare("
            SELECT id_pedido, es_cotizacion, estado_pedido, id_usuario, id_sucursal
            FROM pedidos WHERE id_pedido = ?
        ");
        $stmt->execute([$idPedidoEditar]);
        $pedidoActual = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pedidoActual) throw new Exception('Pedido no encontrado');
        if (!$pedidoActual['es_cotizacion']) throw new Exception('Solo se pueden editar cotizaciones');
        if ($pedidoActual['estado_pedido'] !== 'Pendiente') {
            throw new Exception('Solo se pueden editar cotizaciones pendientes');
        }

        // Las cotizaciones se guardan sin descuento (el descuento por método
        // de pago se calcula únicamente al ver los Reportes)
        $descuento = 0.0;
        $total = $subtotal;
        $cargoCod = 0.0;

        $stmt = $conn->prepare("
            UPDATE pedidos SET
                cliente_nombre = ?, cliente_telefono = ?, cliente_direccion = ?,
                condiciones_pago = ?, tipo_documento = ?, tipo_pago_estado = ?,
                metodo_pago = ?, subtotal = ?, cargo_cod = 0.00,
                subtotal_original = ?, descuento = 0.00, total = ?
            WHERE id_pedido = ?
        ");
        $stmt->execute([
            $data['cliente_nombre'],
            $data['cliente_telefono'] ?? null,
            $data['cliente_direccion'] ?? null,
            $data['condiciones_pago'] ?? null,
            $data['tipo_documento'],
            $data['tipo_pago_estado'],
            $metodoPago,
            $subtotal,
            $subtotal,
            $total,
            $idPedidoEditar
        ]);

        // Reemplazar items
        $stmt = $conn->prepare("DELETE FROM detalle_pedidos WHERE id_pedido = ?");
        $stmt->execute([$idPedidoEditar]);

        $stmtDetalle = $conn->prepare("
            INSERT INTO detalle_pedidos (id_pedido, id_inventario, codigo_item, descripcion, cantidad, precio_unitario, subtotal)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($data['productos'] as $producto) {
            $stmtDetalle->execute([
                $idPedidoEditar,
                !empty($producto['id_inventario']) ? (int) $producto['id_inventario'] : null,
                $producto['codigo'] ?? null,
                $producto['nombre'] ?? null,
                $producto['cantidad'],
                $producto['precio'],
                $producto['subtotal']
            ]);
        }

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Cotización actualizada exitosamente',
            'id_pedido' => $idPedidoEditar
        ]);
        exit();
    }

    // ---------- CREAR NUEVO PEDIDO / COTIZACIÓN ----------
    if ($esCotizacion) {
        $cotizacionNumero = generarNumeroCotizacion($conn);
        $estadoPedido = 'Pendiente';
        $estadoPago = 'Pendiente';
        $descuento = 0.0;        // el descuento se aplica al procesar la venta
        $total = $subtotal;
        $cargoCod = 0.0;
    } else {
        $cotizacionNumero = null;
        $estadoPedido = 'Pendiente';
        $estadoPago = ($data['tipo_pago_estado'] === 'Crédito') ? 'Pendiente Crédito' : 'Pendiente';
        // El cliente siempre paga el total completo; el descuento por método
        // de pago se guarda solo para mostrarlo en Reportes.
        $descuento = $desc['descuento'];
        $total = $subtotal;
        $cargoCod = 0.0;
    }

    $stmt = $conn->prepare("
        INSERT INTO pedidos (
            id_usuario, id_sucursal, es_cotizacion, cotizacion_numero,
            cliente_nombre, cliente_telefono, cliente_direccion, condiciones_pago,
            tipo_documento, tipo_pago_estado, metodo_pago,
            subtotal, cargo_cod, subtotal_original, descuento, total,
            estado_pedido, estado_pago
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $userSucursal,
        $esCotizacion ? 1 : 0,
        $cotizacionNumero,
        $data['cliente_nombre'],
        $data['cliente_telefono'] ?? null,
        $data['cliente_direccion'] ?? null,
        $data['condiciones_pago'] ?? null,
        $data['tipo_documento'],
        $data['tipo_pago_estado'],
        $metodoPago,
        $subtotal,
        $cargoCod,
        $subtotal,
        $descuento,
        $total,
        $estadoPedido,
        $estadoPago
    ]);

    $idPedido = $conn->lastInsertId();

    $stmtDetalle = $conn->prepare("
        INSERT INTO detalle_pedidos (id_pedido, id_inventario, codigo_item, descripcion, cantidad, precio_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    // Actualizar inventario (solo para ventas directas y solo items con inventario)
    $stmtInventario = $conn->prepare("
        UPDATE inventario
        SET cantidad_med = cantidad_med - ?
        WHERE id_inventario = ? AND cantidad_med >= ?
    ");

    foreach ($data['productos'] as $producto) {
        $idInv = !empty($producto['id_inventario']) ? (int) $producto['id_inventario'] : null;

        $stmtDetalle->execute([
            $idPedido,
            $idInv,
            $producto['codigo'] ?? null,
            $producto['nombre'] ?? null,
            $producto['cantidad'],
            $producto['precio'],
            $producto['subtotal']
        ]);

        if (!$esCotizacion && $idInv) {
            $stmtInventario->execute([
                $producto['cantidad'],
                $idInv,
                $producto['cantidad']
            ]);
            if ($stmtInventario->rowCount() === 0) {
                throw new Exception('Stock insuficiente para: ' . ($producto['nombre'] ?? ''));
            }
        }
    }

    // Crear venta automáticamente si el pedido es al contado (no cotizaciones)
    if (!$esCotizacion && $data['tipo_pago_estado'] === 'Al Contado') {
        $stmtVenta = $conn->prepare("
            INSERT INTO ventas (
                id_usuario, id_sucursal, cliente_nombre, cliente_nit, venta_personal,
                tipo_documento, es_credito, id_pedido, total, descuento, total_final, estado
            ) VALUES (?, ?, ?, ?, 0, ?, 0, ?, ?, ?, ?, 'Completada')
        ");
        $stmtVenta->execute([
            $_SESSION['user_id'],
            $userSucursal,
            $data['cliente_nombre'],
            'CF',
            $data['tipo_documento'],
            $idPedido,
            $subtotal,
            $descuento,
            $total
        ]);

        $idVenta = $conn->lastInsertId();

        $stmtDetalleVenta = $conn->prepare("
            INSERT INTO detalle_ventas (id_venta, id_inventario, cantidad, precio_unitario, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($data['productos'] as $producto) {
            if (empty($producto['id_inventario'])) continue;
            $stmtDetalleVenta->execute([
                $idVenta,
                (int) $producto['id_inventario'],
                $producto['cantidad'],
                $producto['precio'],
                $producto['subtotal']
            ]);
        }

        $stmtFormaPago = $conn->prepare("
            INSERT INTO formas_pago (id_venta, tipo_pago, monto)
            VALUES (?, ?, ?)
        ");
        $stmtFormaPago->execute([$idVenta, formaPagoDesdeMetodo($metodoPago), $total]);

        $stmtUpdatePedido = $conn->prepare("UPDATE pedidos SET id_venta = ? WHERE id_pedido = ?");
        $stmtUpdatePedido->execute([$idVenta, $idPedido]);
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => $esCotizacion ? 'Cotización creada exitosamente' : 'Pedido creado exitosamente',
        'id_pedido' => $idPedido,
        'es_cotizacion' => $esCotizacion,
        'cotizacion_numero' => $cotizacionNumero,
        'subtotal' => $subtotal,
        'descuento' => $descuento,
        'total' => $total
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
