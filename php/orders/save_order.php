<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['productos'])) {
        throw new Exception('Datos inválidos');
    }

    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener sucursal del usuario
    $stmt = $conn->prepare("SELECT id_sucursal FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userSucursal = $stmt->fetchColumn() ?: 1;
    
    $conn->beginTransaction();
    
    // Insertar pedido
    $stmt = $conn->prepare("
        INSERT INTO pedidos (
            id_usuario, id_sucursal, cliente_nombre, cliente_telefono, cliente_direccion,
            tipo_documento, tipo_pago_estado, metodo_pago, subtotal, cargo_cod, total,
            estado_pedido, estado_pago
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente', ?)
    ");
    
    // Determinar estado de pago
    $estadoPago = ($data['tipo_pago_estado'] === 'Crédito') ? 'Pendiente Crédito' : 'Pendiente';
    
    $stmt->execute([
        $_SESSION['user_id'],
        $userSucursal,
        $data['cliente_nombre'],
        $data['cliente_telefono'],
        $data['cliente_direccion'] ?? null,
        $data['tipo_documento'],
        $data['tipo_pago_estado'],
        $data['metodo_pago'],
        $data['subtotal'],
        $data['cargo_cod'],
        $data['total'],
        $estadoPago
    ]);
    
    $idPedido = $conn->lastInsertId();
    
    // Insertar productos del pedido
    $stmtDetalle = $conn->prepare("
        INSERT INTO detalle_pedidos (id_pedido, id_inventario, cantidad, precio_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    // Actualizar inventario
    $stmtInventario = $conn->prepare("
        UPDATE inventario 
        SET cantidad_med = cantidad_med - ?
        WHERE id_inventario = ? AND cantidad_med >= ?
    ");
    
    foreach ($data['productos'] as $producto) {
        // Insertar detalle
        $stmtDetalle->execute([
            $idPedido,
            $producto['id_inventario'],
            $producto['cantidad'],
            $producto['precio'],
            $producto['subtotal']
        ]);
        
        // Actualizar inventario
        $stmtInventario->execute([
            $producto['cantidad'],
            $producto['id_inventario'],
            $producto['cantidad']
        ]);
        
        if ($stmtInventario->rowCount() === 0) {
            throw new Exception('Stock insuficiente para: ' . $producto['nombre']);
        }
    }
    
    // Crear venta automáticamente si el pedido es al contado
    if ($data['tipo_pago_estado'] === 'Al Contado') {
        $stmtVenta = $conn->prepare("
            INSERT INTO ventas (
                id_usuario, id_sucursal, cliente_nombre, cliente_nit, venta_personal,
                tipo_documento, es_credito, id_pedido, total, total_final, estado
            ) VALUES (?, ?, ?, ?, 0, ?, 0, ?, ?, ?, 'Completada')
        ");
        
        $stmtVenta->execute([
            $_SESSION['user_id'],
            $userSucursal,
            $data['cliente_nombre'],
            'CF',
            $data['tipo_documento'],
            $idPedido,
            $data['total'],
            $data['total']
        ]);
        
        $idVenta = $conn->lastInsertId();
        
        // Insertar detalles de venta
        $stmtDetalleVenta = $conn->prepare("
            INSERT INTO detalle_ventas (id_venta, id_inventario, cantidad, precio_unitario, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        foreach ($data['productos'] as $producto) {
            $stmtDetalleVenta->execute([
                $idVenta,
                $producto['id_inventario'],
                $producto['cantidad'],
                $producto['precio'],
                $producto['subtotal']
            ]);
        }
        
        // Insertar forma de pago
        $stmtFormaPago = $conn->prepare("
            INSERT INTO formas_pago (id_venta, tipo_pago, monto)
            VALUES (?, ?, ?)
        ");
        
        $tipoPagoVenta = ($data['metodo_pago'] === 'COD') ? 'Efectivo' : $data['metodo_pago'];
        $stmtFormaPago->execute([$idVenta, $tipoPagoVenta, $data['total']]);
        
        // Actualizar pedido con id_venta
        $stmtUpdatePedido = $conn->prepare("UPDATE pedidos SET id_venta = ? WHERE id_pedido = ?");
        $stmtUpdatePedido->execute([$idVenta, $idPedido]);
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pedido creado exitosamente',
        'id_pedido' => $idPedido
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
?>
