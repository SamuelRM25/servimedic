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
        
        // Ensure table exists (Self-repair)
        $conn->exec("CREATE TABLE IF NOT EXISTS `caja_chica` (
          `id_gasto` int(11) NOT NULL AUTO_INCREMENT,
          `descripcion` varchar(255) NOT NULL,
          `monto` decimal(10,2) NOT NULL,
          `tipo_movimiento` enum('Ingreso','Egreso') NOT NULL DEFAULT 'Egreso',
          `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
          `id_usuario` int(11) NOT NULL,
          PRIMARY KEY (`id_gasto`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $descripcion = $_POST['descripcion'] ?? '';
        $monto = $_POST['monto'] ?? 0;
        $tipo = 'Egreso'; // Default to Egreso for now

        if (empty($descripcion) || empty($monto)) {
            throw new Exception("Descripción y monto son requeridos");
        }

        $stmt = $conn->prepare("INSERT INTO caja_chica (descripcion, monto, tipo_movimiento, id_usuario) VALUES (?, ?, ?, ?)");
        $stmt->execute([$descripcion, $monto, $tipo, $_SESSION['user_id']]);

        echo json_encode(['success' => true, 'message' => 'Gasto registrado correctamente']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
