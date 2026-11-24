<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

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
    
    // Auto-Migration (antes de la transacción: un ALTER TABLE la cerraría): Verificar si existe la columna 'authorized_by' en detalle_ventas
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

    // Iniciar transacción
    $conn->beginTransaction();
    
    // Insertar venta
    $numeroVenta = generarNumeroVenta($conn);
    $stmt =

 $conn->prepare("
        INSERT INTO ventas (
            numero_venta, id_usuario, id_sucursal, cliente_nombre, cliente_nit,
            venta_personal, total, descuento, total_final, estado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Completada')
    ");
    
    $stmt->execute([
        $numeroVenta,
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
    
    // Insertar detalles de venta
    $stmtDetalle = $conn->prepare("
        INSERT INTO detalle_ventas (
            id_venta, id_inventario, tipo_factura_medicamento,
            cantidad, precio_unitario, subtotal, authorized_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Descontar inventario por medicamento, empezando por el lote que vence primero;
    // el detalle queda en una línea por lote usado.
    foreach ($data['items'] as $item) {
        $usados = descontarInventarioFEFO($conn, (int) $item['id_inventario'], (int) $item['cantidad'], (string) ($item['nombre'] ?? ''));
        $usados = repartirSubtotal($usados, (float) $item['precio_unitario'], (float) $item['subtotal']);
        foreach ($usados as $u) {
            $stmtDetalle->execute([
                $idVenta,
                $u['id_inventario'],
                $item['tipo_factura'] ?? null,
                $u['cantidad'],
                $item['precio_unitario'],
                $u['subtotal'],
                $item['authorized_by'] ?? null // Guardar ID del autorizador
            ]);
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
        'id_venta' => $idVenta,
        'numero_venta' => $numeroVenta
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
