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
        
        // Calcular total
        $cantidad = floatval($_POST['cantidad']);
        $precioUnitario = floatval($_POST['precio_unitario']);
        $total = $cantidad * $precioUnitario;
        
        // Determinar valores de abonado y saldo según tipo de pago
        $tipoPago = $_POST['tipo_pago'];
        $abonado = ($tipoPago === 'Al Contado') ? $total : 0;
        $saldo = $total - $abonado;
        
        // Determinar estado automático
        $estado = $_POST['estado'];
        if ($saldo == 0 && $tipoPago === 'Al Contado') {
            $estado = 'Pagado';
        }
        
        // Insertar compra
        $stmt = $conn->prepare("
            INSERT INTO compras (
                id_usuario, nombre_medicamento, molecula, presentacion, casa_farmaceutica,
                cantidad, precio_unitario, precio_venta, fecha_compra, tipo_factura,
                tipo_pago, total, abonado, saldo, estado, fecha_vencimiento, lote, observaciones
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['nombre_medicamento'],
            $_POST['molecula'],
            $_POST['presentacion'],
            $_POST['casa_farmaceutica'],
            $cantidad,
            $precioUnitario,
            floatval($_POST['precio_venta']),
            $_POST['fecha_compra'],
            $_POST['tipo_factura'],
            $tipoPago,
            $total,
            $abonado,
            $saldo,
            $estado,
            !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null,
            $_POST['lote'] ?? null,
            $_POST['observaciones'] ?? null
        ]);
        
        $idCompra = $conn->lastInsertId();
        
        
        // CREAR REGISTRO EN INVENTARIO COMO "PENDIENTE" (Opcional - requiere ejecutar update_inventario_integration.sql)
        try {
            $estadoInventario = 'Pendiente';
            
            if ($estado === 'Ingresado') {
                $estadoInventario = 'Ingresado';
            }
            
            $stmtInventario = $conn->prepare("
                INSERT INTO inventario (
                    id_compra, nom_medicamento, mol_medicamento, presentacion_med, casa_farmaceutica,
                    cantidad_med, estado_ingreso, precio_costo, precio_venta,
                    id_sucursal, fecha_adquisicion, fecha_vencimiento, tipo_factura
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Obtener sucursal del usuario
            $stmtUser = $conn->prepare("SELECT id_sucursal FROM usuarios WHERE id = ?");
            $stmtUser->execute([$_SESSION['user_id']]);
            $userSucursal = $stmtUser->fetchColumn() ?: 1;
            
            $stmtInventario->execute([
                $idCompra,
                $_POST['nombre_medicamento'],
                $_POST['molecula'],
                $_POST['presentacion'],
                $_POST['casa_farmaceutica'],
                $cantidad,
                $estadoInventario,
                $precioUnitario,
                floatval($_POST['precio_venta']),
                $userSucursal,
                $_POST['fecha_compra'],
                !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null,
                $_POST['tipo_factura']
            ]);
        } catch (Exception $eInv) {
            // Si falla la inserción en inventario, continuar con la compra
            // (probablemente porque no se han ejecutado las migraciones SQL)
            error_log("Advertencia: No se pudo crear inventario automático: " . $eInv->getMessage());
        }
        
        // Si es al contado, registrar el abono automáticamente
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
                $_POST['fecha_compra'],
                'Efectivo',
                'Pago al contado'
            ]);
        }
        
        // Commit transacción
        $conn->commit();
        
        $_SESSION['message'] = 'Compra registrada exitosamente';
        $_SESSION['message_type'] = 'success';
        
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

header("Location: index.php");
exit();
?>
