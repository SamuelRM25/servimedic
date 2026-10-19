<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$id = (int)($_POST['id_pedido'] ?? 0);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'id_pedido requerido']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT id_pedido, estado_pago, metodo_pago, total FROM pedidos WHERE id_pedido = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['ok' => false, 'error' => 'Pedido no encontrado']);
        exit;
    }
    if ($row['estado_pago'] === 'Pagado') {
        echo json_encode(['ok' => true, 'id_pedido' => $id, 'estado_pago' => 'Pagado', 'unchanged' => true]);
        exit;
    }

    $conn->beginTransaction();

    $upd = $conn->prepare("UPDATE pedidos SET estado_pago = 'Pagado' WHERE id_pedido = ?");
    $upd->execute([$id]);

    // Si el pedido tiene venta registrada sin forma de pago (crédito),
    // registrar el cobro con el método del pedido para que aparezca
    // en los reportes por método de pago.
    $stmtV = $conn->prepare("
        SELECT v.id_venta
        FROM ventas v
        WHERE v.id_pedido = ?
        ORDER BY v.id_venta DESC LIMIT 1
    ");
    $stmtV->execute([$id]);
    $idVenta = $stmtV->fetchColumn();
    if ($idVenta) {
        $stmtP = $conn->prepare("SELECT COUNT(*) FROM formas_pago WHERE id_venta = ?");
        $stmtP->execute([$idVenta]);
        if ((int)$stmtP->fetchColumn() === 0) {
            $metodo = $row['metodo_pago'] ?: 'Efectivo';
            $stmtIn = $conn->prepare("INSERT INTO formas_pago (id_venta, tipo_pago, monto) VALUES (?, ?, ?)");
            $stmtIn->execute([$idVenta, formaPagoDesdeMetodo($metodo), (float)$row['total']]);
        }
    }

    $conn->commit();

    echo json_encode(['ok' => true, 'id_pedido' => $id, 'estado_pago' => 'Pagado']);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
