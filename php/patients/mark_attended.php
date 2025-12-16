<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        if (empty($_POST['id_orden'])) {
            throw new Exception("ID de orden requerido");
        }

        $id_orden = $_POST['id_orden'];

        // 0. Ensure 'Atendido' exists in ENUM (Schema Migration on the fly)
        // This is a bit hacky but ensures functionality without manual SQL intervention
        try {
            $conn->query("ALTER TABLE ordenes_cobro MODIFY COLUMN estado enum('Pendiente','Pagado','Atendido') NOT NULL DEFAULT 'Pendiente'");
        } catch (Exception $e) {
            // Ignore if already exists or permission issues (usually implies it's fine or we catch update error)
        }

        // 1. Update order status
        $stmt = $conn->prepare("
            UPDATE ordenes_cobro 
            SET estado = 'Atendido'
            WHERE id_orden = ?
        ");
        $stmt->execute([$id_orden]);

        echo json_encode(['success' => true, 'message' => 'Paciente marcado como atendido']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
