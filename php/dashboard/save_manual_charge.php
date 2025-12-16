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

        // Validate inputs
        if (empty($_POST['id_paciente']) || empty($_POST['id_medico']) || empty($_POST['monto']) || empty($_POST['tipo_pago'])) {
            throw new Exception("Todos los campos son requeridos");
        }

        $id_paciente = $_POST['id_paciente'];
        $id_medico = $_POST['id_medico']; // Needed for Waiting Room
        $monto = $_POST['monto'];
        $tipo_pago = $_POST['tipo_pago']; // EPS, IGS, etc.
        $descripcion = $_POST['descripcion'] ?? 'Cobro Manual';

        $conn->beginTransaction();

        // 1. Update Patient's Insurance Type (to match the reporting requirement)
        $stmtUpdate = $conn->prepare("UPDATE pacientes SET tipo_paciente = ? WHERE id_paciente = ?");
        $stmtUpdate->execute([$tipo_pago, $id_paciente]);

        // 2. Insert into ordenes_cobro as 'Pagado'
        $stmtInsert = $conn->prepare("
            INSERT INTO ordenes_cobro 
            (id_paciente, id_medico, monto, tipo_cobro, descripcion, estado, fecha_creacion, fecha_pago) 
            VALUES (?, ?, ?, 'Consulta', ?, 'Pagado', NOW(), NOW())
        ");
        $stmtInsert->execute([$id_paciente, $id_medico, $monto, $descripcion]);
        
        $id_orden = $conn->lastInsertId();

        $conn->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Cobro registrado y pase a sala de espera generado.',
            'id_orden' => $id_orden
        ]);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
