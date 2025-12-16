<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $userRol = $_SESSION['rol'];
    $userId = $_SESSION['user_id'];

    if ($userRol === 'Doctor') {
        $stmt = $conn->prepare("
            SELECT COUNT(*) 
            FROM ordenes_cobro 
            WHERE estado = 'Pagado' 
            AND id_medico = ?
            AND fecha_pago >= CURDATE()
        ");
        $stmt->execute([$userId]);
    } else {
        $stmt = $conn->query("
            SELECT COUNT(*) 
            FROM ordenes_cobro 
            WHERE estado = 'Pagado' 
            AND fecha_pago >= CURDATE()
        ");
    }

    $count = $stmt->fetchColumn();
    echo json_encode(['count' => (int)$count]);

} catch (Exception $e) {
    echo json_encode(['count' => 0, 'error' => $e->getMessage()]);
}
?>
