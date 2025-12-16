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

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['id_orden'])) {
            throw new Exception("ID de orden requerido");
        }

        $id_orden = $data['id_orden'];
        $items = $data['items'] ?? []; // Allow empty items
        $necesita_farmacia = $data['necesita_farmacia'] ?? 0;

        // 0. Ensure Tables and Columns Exist
        $conn->query("CREATE TABLE IF NOT EXISTS recetas (
            id_receta INT AUTO_INCREMENT PRIMARY KEY,
            id_orden INT NOT NULL,
            id_medico INT NOT NULL,
            detalle_json TEXT,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        try {
            $conn->query("ALTER TABLE ordenes_cobro MODIFY COLUMN estado enum('Pendiente','Pagado','Atendido') NOT NULL DEFAULT 'Pendiente'");
        } catch (Exception $e) {}

        try {
            $conn->query("ALTER TABLE ordenes_cobro ADD COLUMN necesita_farmacia TINYINT(1) DEFAULT 0");
        } catch (Exception $e) {}

        $conn->beginTransaction();

        // 1. Save Prescription (only if items exist)
        $id_receta = null;
        if (!empty($items)) {
            $stmtReceta = $conn->prepare("INSERT INTO recetas (id_orden, id_medico, detalle_json) VALUES (?, ?, ?)");
            $stmtReceta->execute([$id_orden, $_SESSION['user_id'], json_encode($items)]);
            $id_receta = $conn->lastInsertId();
        }

        // 2. Mark as Attended and Set Pharmacy Flag
        $stmtUpdate = $conn->prepare("UPDATE ordenes_cobro SET estado = 'Atendido', necesita_farmacia = ? WHERE id_orden = ?");
        $stmtUpdate->execute([$necesita_farmacia, $id_orden]);

        $conn->commit();

         echo json_encode(['success' => true, 'id_receta' => $id_receta]);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
