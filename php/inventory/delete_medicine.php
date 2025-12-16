<?php
session_start();
require_once '../../config/database.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

if (isset($_GET['id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $id_inventario = $_GET['id'];
        
        // Eliminar medicamento
        $stmt = $conn->prepare("DELETE FROM inventario WHERE id_inventario = ?");
        $stmt->execute([$id_inventario]);
        
        $_SESSION['message'] = 'Medicamento eliminado exitosamente';
        $_SESSION['message_type'] = 'success';
        
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error al eliminar: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

header("Location: index.php");
exit();
?>
