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
        
        $idInventario = $_POST['id_inventario'];
        $numeroFactura = $_POST['numero_factura'];
        
        if (empty($idInventario) || empty($numeroFactura)) {
            throw new Exception("Faltan datos requeridos");
        }
        
        $codigoBarras = $_POST['codigo_barras'] ?? null;
        $fechaVencimiento = $_POST['fecha_vencimiento'] ?? null;
        $idSucursal = $_POST['id_sucursal'] ?? null;
        
        // Iniciar transacción
        $conn->beginTransaction();
        
        // 1. Actualizar Inventario
        $sql = "UPDATE inventario SET 
                    estado_ingreso = 'Ingresado', 
                    numero_factura = ?";
        
        $params = [$numeroFactura];
        
        if ($codigoBarras) {
            $sql .= ", codigo_barras = ?";
            $params[] = $codigoBarras;
        }
        
        if ($fechaVencimiento) {
            $sql .= ", fecha_vencimiento = ?";
            $params[] = $fechaVencimiento;
        }
        
        if ($idSucursal) {
            $sql .= ", id_sucursal = ?";
            $params[] = $idSucursal;
        }
        
        $sql .= " WHERE id_inventario = ?";
        $params[] = $idInventario;
        
        $stmtInv = $conn->prepare($sql);
        $stmtInv->execute($params);
        
            // 2. Obtener id_compra y codigo_referencia para actualizar
            $stmtGet = $conn->prepare("SELECT id_compra, codigo_referencia FROM inventario WHERE id_inventario = ?");
            $stmtGet->execute([$idInventario]);
            $row = $stmtGet->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $idCompra = $row['id_compra'];
                $codigoReferencia = $row['codigo_referencia'];
                
                // Actualizar Compra
                // Si tenemos codigo_referencia, actualizamos TODOS los items de esa referencia con el número de factura
                // Esto cumple con "se actualice también el número de referencia a el número de envio o factura"
                // (Interpretado como asignar el número de factura a todo el lote)
                
                if ($codigoReferencia) {
                    $stmtCompra = $conn->prepare("
                        UPDATE compras 
                        SET estado = 'Ingresado',
                            numero_factura = ?
                        WHERE codigo_referencia = ?
                    ");
                    $stmtCompra->execute([$numeroFactura, $codigoReferencia]);
                } else {
                    // Fallback para items antiguos sin referencia
                    $stmtCompra = $conn->prepare("
                        UPDATE compras 
                        SET estado = 'Ingresado',
                            numero_factura = ?
                        WHERE id_compra = ?
                    ");
                    $stmtCompra->execute([$numeroFactura, $idCompra]);
                }
            }
        
        $conn->commit();
        
        $_SESSION['message'] = 'Producto recibido exitosamente';
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
