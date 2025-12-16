<?php
session_start();
require_once '../../config/database.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Generar Código de Referencia Único (000001)
        $stmtRef = $conn->query("SELECT MAX(id_compra) as max_id FROM compras");
        $rowRef = $stmtRef->fetch(PDO::FETCH_ASSOC);
        $nextId = ($rowRef['max_id'] ?? 0) + 1;
        $codigoReferencia = str_pad($nextId, 6, '0', STR_PAD_LEFT);
        
        // Datos comunes de la compra
        $fechaCompra = $_POST['fecha_compra'];
        $tipoFactura = $_POST['tipo_factura'];
        $numeroFactura = $_POST['numero_factura'] ?? null; // Nuevo campo
        $tipoPago = $_POST['tipo_pago'];
        $observaciones = $_POST['observaciones'] ?? '';
        
        // Procesar cada ítem
        $items = $_POST['items'] ?? [];
        $totalCompra = 0;
        
        foreach ($items as $item) {
            $cantidad = floatval($item['cantidad']);
            $precioUnitario = floatval($item['precio_unitario']);
            $precioVenta = floatval($item['precio_venta']);
            $total = $cantidad * $precioUnitario;
            $totalCompra += $total;
            
            // Determinar valores de abonado y saldo por ítem (proporcional o individual)
            // En este caso, manejamos el pago por ítem para mantener la estructura actual,
            // aunque idealmente sería por la compra global.
            // Mantenemos la lógica actual: si es contado, se abona todo.
            $abonado = ($tipoPago === 'Al Contado') ? $total : 0;
            $saldo = $total - $abonado;
            
            // Estado
            $estado = 'Pendiente';
            if ($saldo == 0 && $tipoPago === 'Al Contado') {
                $estado = 'Pagado';
            }
            
            // Insertar compra
            $stmt = $conn->prepare("
                INSERT INTO compras (
                    id_usuario, codigo_referencia, nombre_medicamento, molecula, presentacion, casa_farmaceutica,
                    cantidad, precio_unitario, precio_venta, fecha_compra, tipo_factura, numero_factura,
                    tipo_pago, total, abonado, saldo, estado, fecha_vencimiento, lote, observaciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $codigoReferencia,
                $item['nombre_medicamento'],
                $item['molecula'],
                $item['presentacion'],
                $item['casa_farmaceutica'],
                $cantidad,
                $precioUnitario,
                $precioVenta,
                $fechaCompra,
                $tipoFactura,
                $numeroFactura,
                $tipoPago,
                $total,
                $abonado,
                $saldo,
                $estado,
                !empty($item['fecha_vencimiento']) ? $item['fecha_vencimiento'] : null,
                $item['lote'] ?? null,
                $observaciones
            ]);
            
            $idCompra = $conn->lastInsertId();
            
            // CREAR REGISTRO EN INVENTARIO COMO "PENDIENTE"
            // El usuario solicitó: "como producto en espera de que llegue... no se podrá utilizar"
            $estadoInventario = 'Pendiente';
            
            $stmtInventario = $conn->prepare("
                INSERT INTO inventario (
                    id_compra, codigo_referencia, nom_medicamento, mol_medicamento, presentacion_med, casa_farmaceutica,
                    cantidad_med, estado_ingreso, precio_costo, precio_venta,
                    id_sucursal, fecha_adquisicion, fecha_vencimiento, tipo_factura, numero_factura
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Obtener sucursal del usuario
            $stmtUser = $conn->prepare("SELECT id_sucursal FROM usuarios WHERE id = ?");
            $stmtUser->execute([$_SESSION['user_id']]);
            $userSucursal = $stmtUser->fetchColumn() ?: 1;
            
            $stmtInventario->execute([
                $idCompra,
                $codigoReferencia,
                $item['nombre_medicamento'],
                $item['molecula'],
                $item['presentacion'],
                $item['casa_farmaceutica'],
                $cantidad,
                $estadoInventario, // Aquí forzamos "Pendiente"
                $precioUnitario,
                $precioVenta,
                $userSucursal,
                $fechaCompra,
                !empty($item['fecha_vencimiento']) ? $item['fecha_vencimiento'] : null,
                $tipoFactura,
                $numeroFactura
            ]);
            
            // Registrar abono si es al contado
            if ($tipoPago === 'Al Contado' && $abonado > 0) {
                $stmtAbono = $conn->prepare("
                    INSERT INTO abonos_compras (
                        id_compra, id_usuario, monto, fecha_abono, forma_pago, observaciones
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmtAbono->execute([
                    $idCompra,
                    $_SESSION['user_id'],
                    $abonado,
                    $fechaCompra,
                    'Efectivo',
                    'Pago al contado - Compra #' . $codigoReferencia
                ]);
            }
        }
        
        // Commit transacción
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Compra registrada exitosamente. Referencia: ' . $codigoReferencia]);
        
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    header("Location: index.php");
    exit();
}
?>
