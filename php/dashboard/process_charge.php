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

        // 1. Update order status
        $stmt = $conn->prepare("
            UPDATE ordenes_cobro 
            SET estado = 'Pagado', fecha_pago = NOW() 
            WHERE id_orden = ?
        ");
        $stmt->execute([$_POST['id_orden']]);

        // 2. Fetch order details for ticket
        $stmt = $conn->prepare("
            SELECT o.*, p.nombre as nombre_paciente, p.apellido as apellido_paciente
            FROM ordenes_cobro o
            JOIN pacientes p ON o.id_paciente = p.id_paciente
            WHERE o.id_orden = ?
        ");
        $stmt->execute([$_POST['id_orden']]);
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);

        // 3. Generate Ticket Number (Simple increment or use ID)
        $ticketNumber = str_pad($orden['id_orden'], 6, '0', STR_PAD_LEFT);

        echo json_encode([
            'success' => true,
            'message' => 'Cobro realizado correctamente',
            'ticket' => [
                'ticketNumber' => $ticketNumber,
                'date' => date('d/m/Y H:i'),
                'patientName' => $orden['nombre_paciente'] . ' ' . $orden['apellido_paciente'],
                'items' => [[
                    'quantity' => 1,
                    'description' => $orden['tipo_cobro'] . ($orden['descripcion'] ? ' - ' . $orden['descripcion'] : ''),
                    'total' => $orden['monto']
                ]],
                'total' => $orden['monto']
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
