<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Validar campos requeridos
        if (empty($_POST['id_sucursal_origen']) || empty($_POST['id_sucursal_destino']) || 
            empty($_POST['id_medicamento']) || empty($_POST['cantidad'])) {
            throw new Exception("Todos los campos son obligatorios");
        }
        
        $sucursalOrigen = $_POST['id_sucursal_origen'];
        $sucursalDestino = $_POST['id_sucursal_destino'];
        $medicamentoId = $_POST['id_medicamento'];
        $cantidad = intval($_POST['cantidad']);
        $usuarioId = $_SESSION['user_id'];
        
        // Verificar que la sucursal origen y destino sean diferentes
        if ($sucursalOrigen == $sucursalDestino) {
            throw new Exception("La sucursal origen y destino deben ser diferentes");
        }
        
        // Verificar que haya suficiente cantidad en la sucursal origen
        $stmt = $conn->prepare("SELECT cantidad_med, nom_medicamento FROM inventario WHERE id_inventario = ? AND id_sucursal = ?");
        $stmt->execute([$medicamentoId, $sucursalOrigen]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$resultado) {
            throw new Exception("El medicamento no existe en la sucursal origen");
        }
        
        if ($resultado['cantidad_med'] < $cantidad) {
            throw new Exception("No hay suficiente cantidad disponible. Stock actual: " . $resultado['cantidad_med']);
        }
        
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Restar la cantidad de la sucursal origen
        $stmt = $conn->prepare("UPDATE inventario SET cantidad_med = cantidad_med - ? WHERE id_inventario = ?");
        $stmt->execute([$cantidad, $medicamentoId]);
        
        // Verificar si el medicamento existe en la sucursal destino
        $stmt = $conn->prepare("
            SELECT id_inventario, cantidad_med 
            FROM inventario 
            WHERE nom_medicamento = (SELECT nom_medicamento FROM inventario WHERE id_inventario = ?) 
            AND id_sucursal = ?
        ");
        $stmt->execute([$medicamentoId, $sucursalDestino]);
        $medicamentoDestino = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($medicamentoDestino) {
            // Si existe, sumar la cantidad
            $stmt = $conn->prepare("UPDATE inventario SET cantidad_med = cantidad_med + ? WHERE id_inventario = ?");
            $stmt->execute([$cantidad, $medicamentoDestino['id_inventario']]);
        } else {
            // Si no existe, crear un nuevo registro
            $stmt = $conn->prepare("
                INSERT INTO inventario (nom_medicamento, mol_medicamento, presentacion_med, casa_farmaceutica, cantidad_med, fecha_adquisicion, fecha_vencimiento, id_sucursal)
                SELECT nom_medicamento, mol_medicamento, presentacion_med, casa_farmaceutica, ?, fecha_adquisicion, fecha_vencimiento, ?
                FROM inventario
                WHERE id_inventario = ?
            ");
            $stmt->execute([$cantidad, $sucursalDestino, $medicamentoId]);
        }
        
        // Registrar el traslado
        $stmt = $conn->prepare("
            INSERT INTO traslados (id_sucursal_origen, id_sucursal_destino, id_medicamento, cantidad, usuario_id, estado)
            VALUES (?, ?, ?, ?, ?, 'completado')
        ");
        $stmt->execute([$sucursalOrigen, $sucursalDestino, $medicamentoId, $cantidad, $usuarioId]);
        
        // Confirmar transacción
        $conn->commit();
        
        $_SESSION['transfer_message'] = "Traslado realizado correctamente";
        $_SESSION['transfer_status'] = "success";
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        if (isset($conn)) {
            $conn->rollBack();
        }
        
        $_SESSION['transfer_message'] = "Error: " . $e->getMessage();
        $_SESSION['transfer_status'] = "error";
    }
    
    header("Location: index.php");
    exit;
}
?>