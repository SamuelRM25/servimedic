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

        $id_orden = (int) $_POST['id_orden'];

        // 1. Update order status
        $stmt = $conn->prepare("
            UPDATE ordenes_cobro
            SET estado = 'Pagado', fecha_pago = NOW()
            WHERE id_orden = ?
        ");
        $stmt->execute([$id_orden]);

        // 2. Fetch order + cliente for ticket
        $stmt = $conn->prepare("
            SELECT o.*, c.nombre as nombre_cliente
            FROM ordenes_cobro o
            JOIN clientes c ON o.id_cliente = c.id_cliente
            WHERE o.id_orden = ?
        ");
        $stmt->execute([$id_orden]);
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$orden) {
            throw new Exception("Orden no encontrada");
        }

        // 3. Generate Ticket Number
        $ticketNumber = str_pad($orden['id_orden'], 6, '0', STR_PAD_LEFT);

        echo json_encode([
            'success' => true,
            'message' => 'Cobro realizado correctamente',
            'ticket' => [
                'ticketNumber' => $ticketNumber,
                'date'         => date('d/m/Y H:i'),
                'patientName'  => $orden['nombre_cliente'],
                'items'        => [[
                    'quantity'    => 1,
                    'description' => $orden['tipo_cobro'] . ($orden['descripcion'] ? ' - ' . $orden['descripcion'] : ''),
                    'total'       => $orden['monto'],
                ]],
                'total' => $orden['monto'],
            ],
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
