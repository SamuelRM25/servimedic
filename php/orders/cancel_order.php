<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $idPedido = (int) ($data['id_pedido'] ?? 0);
    if (!$idPedido) throw new Exception('ID inválido');

    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id_pedido = ?");
    $stmt->execute([$idPedido]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) throw new Exception('Pedido no encontrado');
    if ($pedido['estado_pedido'] === 'Completado') {
        throw new Exception('No se puede cancelar un pedido ya completado');
    }
    if ($pedido['estado_pedido'] === 'Cancelado') {
        throw new Exception('El pedido ya está cancelado');
    }

    // Solo cotizaciones cancelables desde este flujo
    if (!$pedido['es_cotizacion']) {
        throw new Exception('Solo se pueden cancelar cotizaciones desde esta opción');
    }

    $stmt = $conn->prepare("UPDATE pedidos SET estado_pedido = 'Cancelado', estado_pago = 'Pendiente' WHERE id_pedido = ?");
    $stmt->execute([$idPedido]);

    echo json_encode([
        'success' => true,
        'message' => 'Cotización cancelada'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
