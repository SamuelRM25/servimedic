<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $idInventario = $_POST['id_inventario'];
        $fechaVencimiento = $_POST['fecha_vencimiento'];
        
        $stmt = $conn->prepare("UPDATE inventario SET fecha_vencimiento = ? WHERE id_inventario = ?");
        $stmt->execute([$fechaVencimiento, $idInventario]);
        
        $_SESSION['message'] = 'Fecha de vencimiento actualizada exitosamente';
        $_SESSION['message_type'] = 'success';
        
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

header("Location: index.php");
exit();
?>
