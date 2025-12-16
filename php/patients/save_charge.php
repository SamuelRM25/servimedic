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

        // Ensure table exists 
        $conn->exec("CREATE TABLE IF NOT EXISTS `ordenes_cobro` (
          `id_orden` int(11) NOT NULL AUTO_INCREMENT,
          `id_paciente` int(11) NOT NULL,
          `id_medico` int(11) NOT NULL,
          `monto` decimal(10,2) NOT NULL,
          `tipo_cobro` enum('Consulta','Procedimiento','Examen','Otro','EPS','IGS','MAWDY','PRIVADO') NOT NULL,
          `descripcion` text DEFAULT NULL,
          `estado` enum('Pendiente','Pagado') NOT NULL DEFAULT 'Pendiente',
          `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
          `fecha_pago` timestamp NULL DEFAULT NULL,
          PRIMARY KEY (`id_orden`),
          KEY `fk_orden_paciente` (`id_paciente`),
          KEY `fk_orden_medico` (`id_medico`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        // Attempt to alter enum if it doesn't match (Silent fail if it works, catch if error)
        try {
             $conn->exec("ALTER TABLE `ordenes_cobro` MODIFY COLUMN `tipo_cobro` enum('Consulta','Procedimiento','Examen','Otro','EPS','IGS','MAWDY','PRIVADO') NOT NULL DEFAULT 'Consulta'");
        } catch (Exception $e) {
            // Ignore (might already be altered or DB doesn't support easy alter)
        }

        // Validate input
        if (empty($_POST['id_paciente']) || empty($_POST['monto']) || empty($_POST['tipo_cobro'])) {
            throw new Exception("Faltan datos requeridos");
        }

        $stmt = $conn->prepare("
            INSERT INTO ordenes_cobro (id_paciente, id_medico, monto, tipo_cobro, descripcion, estado)
            VALUES (?, ?, ?, ?, ?, 'Pendiente')
        ");

        $stmt->execute([
            $_POST['id_paciente'],
            $_SESSION['user_id'],
            $_POST['monto'],
            $_POST['tipo_cobro'],
            $_POST['descripcion'] ?? ''
        ]);

        echo json_encode(['success' => true, 'message' => 'Cobro asignado correctamente']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
