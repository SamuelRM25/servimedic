<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_orden = $_POST['id_orden'] ?? null;

    if (!$id_orden) {
        echo json_encode(['success' => false, 'message' => 'ID Orden Missing']);
        exit;
    }

    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Update needs_pharmacy to 2 (Delivered/Processed)
        // This removes it from the "Pending" list (which checks for needs_pharmacy = 1)
        $stmt = $conn->prepare("UPDATE ordenes_cobro SET necesita_farmacia = 2 WHERE id_orden = ?");
        $stmt->execute([$id_orden]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
