<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

try {
    // Leer datos JSON
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Datos inválidos');
    }

    $database = new Database();
    $conn = $database->getConnection();
    
    // Iniciar transacción
    $conn->beginTransaction();
    
    // Insertar venta
    $stmt =

 $conn->prepare("
        INSERT INTO ventas (
            id_usuario, id_sucursal, cliente_nombre, cliente_nit,
            venta_personal, total, descuento, total_final, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Completada')
    ");
    
    $stmt->execute([
        $data['id_usuario'],
        $data['id_sucursal'],
        $data['cliente_nombre'],
        $data['cliente_nit'],
        $data['venta_personal'],
        $data['total'],
        $data['descuento'],
        $data['total_final']
    ]);
    
    $idVenta = $conn->lastInsertId();
    
    // Auto-Migration: Verificar si existe la columna 'authorized_by' en detalle_ventas
    // Esto es un parche para entornos de desarrollo donde no podemos correr migraciones manuales
    try {
        $conn->query("SELECT authorized_by FROM detalle_ventas LIMIT 1");
    } catch (Exception $e) {
        // Si falla, asumimos que no existe (o tabla vacía, pero el SELECT LIMIT 1 sobre columna específica fallará si columna no existe)
        // Agregamos la columna
        try {
            $conn->exec("ALTER TABLE detalle_ventas ADD COLUMN authorized_by INT NULL DEFAULT NULL");
        } catch (Exception $e2) {
            // Ignorar si ya existe o error irrelevante
        }
    }

    // Insertar detalles de venta
    $stmtDetalle = $conn->prepare("
        INSERT INTO detalle_ventas (
            id_venta, id_inventario, tipo_factura_medicamento,
            cantidad, precio_unitario, subtotal, authorized_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Actualizar inventario
    $stmtInventario = $conn->prepare("
        UPDATE inventario 
        SET cantidad_med = cantidad_med - ?
        WHERE id_inventario = ? AND cantidad_med >= ?
    ");
    
    foreach ($data['items'] as $item) {
        // Insertar detalle
        $stmtDetalle->execute([
            $idVenta,
            $item['id_inventario'],
            $item['tipo_factura'] ?? null,
            $item['cantidad'],
            $item['precio_unitario'],
            $item['subtotal'],
            $item['authorized_by'] ?? null // Guardar ID del autorizador
        ]);
        
        // Actualizar inventario
        $stmtInventario->execute([
            $item['cantidad'],
            $item['id_inventario'],
            $item['cantidad']
        ]);
        
        // Verificar que se actualizó
        if ($stmtInventario->rowCount() === 0) {
            throw new Exception('Stock insuficiente para: ' . $item['nombre']);
        }
    }
    
    // Insertar formas de pago
    $stmtPago = $conn->prepare("
        INSERT INTO formas_pago (id_venta, tipo_pago, monto, referencia)
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($data['formas_pago'] as $pago) {
        $stmtPago->execute([
            $idVenta,
            $pago['tipo_pago'],
            $pago['monto'],
            $pago['referencia'] ?? null
        ]);
    }
    
    // Commit transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Venta registrada exitosamente',
        'id_venta' => $idVenta
    ]);
    
} catch (Exception $e) {
    // Rollback en caso de error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
