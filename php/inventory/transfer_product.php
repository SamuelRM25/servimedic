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
        
        // Ensure transferencias table exists
        $conn->exec("CREATE TABLE IF NOT EXISTS `transferencias` (
          `id_transferencia` int(11) NOT NULL AUTO_INCREMENT,
          `id_inventario_origen` int(11) NOT NULL,
          `id_inventario_destino` int(11) NOT NULL,
          `id_sucursal_origen` int(11) NOT NULL,
          `id_sucursal_destino` int(11) NOT NULL,
          `cantidad` int(11) NOT NULL,
          `id_usuario` int(11) NOT NULL,
          `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp(),
          `estado` enum('En Camino','Recibido','Rechazado') NOT NULL DEFAULT 'En Camino',
          PRIMARY KEY (`id_transferencia`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $id = $_POST['id_inventario'];
        $sucursalDestino = $_POST['id_sucursal_destino'];
        $qtyToTransfer = (int)$_POST['cantidad_transferir'];
        $userId = $_SESSION['user_id'];
        
        if (empty($id) || empty($sucursalDestino) || $qtyToTransfer <= 0) {
            throw new Exception("Datos incompletos o cantidad inválida");
        }
        
        // Start Transaction
        $conn->beginTransaction();

        // 1. Obtener datos actuales del producto (Lock for update implies we want to be safe)
        // Using FOR UPDATE to prevent race conditions during read-modify-write
        $stmtGet = $conn->prepare("SELECT * FROM inventario WHERE id_inventario = ? FOR UPDATE");
        $stmtGet->execute([$id]);
        $product = $stmtGet->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) throw new Exception("Producto no encontrado");
        
        $currentQty = (int)$product['cantidad_med'];
        $sucursalOrigen = $product['id_sucursal'];
        $idInventarioDestino = 0; // To be determined
        
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
            $idInventarioDestino = $conn->lastInsertId();
            
        } else {
            // TRANSFERENCIA TOTAL (Mover todo el registro)
            $stmt = $conn->prepare("
                UPDATE inventario 
                SET id_sucursal = ?, 
                    estado_ingreso = 'En Traslado' 
                WHERE id_inventario = ?
            ");
            $stmt->execute([$sucursalDestino, $id]);
            $idInventarioDestino = $id; // Same ID, just moved
        }

        // 3. Registrar en Historial de Transferencias
        $stmtLog = $conn->prepare("
            INSERT INTO transferencias 
            (id_inventario_origen, id_inventario_destino, id_sucursal_origen, id_sucursal_destino, cantidad, id_usuario, estado)
            VALUES (?, ?, ?, ?, ?, ?, 'En Camino')
        ");
        $stmtLog->execute([
            $id, 
            $idInventarioDestino, 
            $sucursalOrigen, 
            $sucursalDestino, 
            $qtyToTransfer, 
            $userId
        ]);

        $conn->commit();
        
        $_SESSION['message'] = "Transferencia iniciada exitosamente ($qtyToTransfer unidades).";
        $_SESSION['message_type'] = "success";
        
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['message'] = "Error en traslado: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header("Location: index.php");
    exit();
}
?>
