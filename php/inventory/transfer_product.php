<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $id = $_POST['id_inventario'];
        $sucursalDestino = $_POST['id_sucursal_destino'];
        $qtyToTransfer = (int)$_POST['cantidad_transferir'];
        
        if (empty($id) || empty($sucursalDestino) || $qtyToTransfer <= 0) {
            throw new Exception("Datos incompletos o cantidad inválida");
        }
        
        // 1. Obtener datos actuales del producto
        $stmtGet = $conn->prepare("SELECT * FROM inventario WHERE id_inventario = ?");
        $stmtGet->execute([$id]);
        $product = $stmtGet->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) throw new Exception("Producto no encontrado");
        
        $currentQty = (int)$product['cantidad_med'];
        
        if ($qtyToTransfer > $currentQty) {
             throw new Exception("La cantidad a trasladar excede la existencia actual ($currentQty)");
        }
        
        if ($qtyToTransfer < $currentQty) {
            // TRANSFERENCIA PARCIAL
            // 1. Restar del original
            $newOriginQty = $currentQty - $qtyToTransfer;
            $stmtUpdateOrigin = $conn->prepare("UPDATE inventario SET cantidad_med = ? WHERE id_inventario = ?");
            $stmtUpdateOrigin->execute([$newOriginQty, $id]);
            
            // 2. Crear nuevo registro para el destino
            // Copiamos todo excepto ID y actualizamos sucursal, cantidad y estado
            unset($product['id_inventario']);
            $product['id_sucursal'] = $sucursalDestino;
            $product['cantidad_med'] = $qtyToTransfer;
            $product['estado_ingreso'] = 'En Traslado';
            
            // Construir query de insert dinámica
            $columns = array_keys($product);
            $count = count($columns);
            $placeholders = implode(',', array_fill(0, $count, '?'));
            $colNames = implode(',', $columns);
            
            $stmtInsert = $conn->prepare("INSERT INTO inventario ($colNames) VALUES ($placeholders)");
            $stmtInsert->execute(array_values($product));
            
        } else {
            // TRANSFERENCIA TOTAL (Mover todo el registro)
            $stmt = $conn->prepare("
                UPDATE inventario 
                SET id_sucursal = ?, 
                    estado_ingreso = 'En Traslado' 
                WHERE id_inventario = ?
            ");
            $stmt->execute([$sucursalDestino, $id]);
        }
        
        $_SESSION['message'] = "Transferencia iniciada exitosamente ($qtyToTransfer unidades).";
        $_SESSION['message_type'] = "success";
        
    } catch (Exception $e) {
        $_SESSION['message'] = "Error en traslado: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header("Location: index.php");
    exit();
}
?>
