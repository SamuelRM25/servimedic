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

        if (empty($_POST['id_cliente']) || empty($_POST['id_medico']) || empty($_POST['monto']) || empty($_POST['tipo_pago'])) {
            throw new Exception("Todos los campos son requeridos");
        }

        $id_cliente = (int) $_POST['id_cliente'];
        $id_medico  = (int) $_POST['id_medico'];
        $monto      = (float) $_POST['monto'];
        $tipo_pago  = $_POST['tipo_pago']; // EPS, IGS, etc.
        $descripcion = $_POST['descripcion'] ?? 'Cobro Manual';

        // Verify cliente exists
        $stmtCheck = $conn->prepare("SELECT id_cliente FROM clientes WHERE id_cliente = ?");
        $stmtCheck->execute([$id_cliente]);
        if (!$stmtCheck->fetchColumn()) {
            throw new Exception("Cliente no encontrado");
        }

        $conn->beginTransaction();

        // Insert into ordenes_cobro as 'Pagado'
        $stmtInsert = $conn->prepare("
            INSERT INTO ordenes_cobro
            (id_cliente, id_medico, monto, tipo_cobro, descripcion, estado, fecha_creacion, fecha_pago)
            VALUES (?, ?, ?, 'Consulta', ?, 'Pagado', NOW(), NOW())
        ");
        $stmtInsert->execute([$id_cliente, $id_medico, $monto, $descripcion]);
        $id_orden = (int) $conn->lastInsertId();

        $conn->commit();

        echo json_encode([
            'success'  => true,
            'message'  => 'Cobro registrado correctamente.',
            'id_orden' => $id_orden,
        ]);
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
