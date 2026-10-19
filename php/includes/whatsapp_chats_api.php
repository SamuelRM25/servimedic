<?php
session_start();
require_once '../../config/database.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// POST: registrar contacto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pedido = (int)($_POST['id_pedido'] ?? 0);
    if ($id_pedido <= 0) {
        echo json_encode(['ok' => false, 'error' => 'id_pedido requerido']);
        exit;
    }
    try {
        $stmt = $conn->prepare("SELECT cliente_nombre, cliente_telefono FROM pedidos WHERE id_pedido = ?");
        $stmt->execute([$id_pedido]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['ok' => false, 'error' => 'Pedido no encontrado']);
            exit;
        }
        $ins = $conn->prepare("INSERT INTO contact_log (id_pedido, telefono, cliente_nombre, tipo, created_by) VALUES (?, ?, ?, 'whatsapp', ?)");
        $ins->execute([$id_pedido, $row['cliente_telefono'] ?? '', $row['cliente_nombre'] ?? '', $_SESSION['user_id']]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// GET: lista de chats pendientes
// Criterio: pedidos con estado_pago != Pagado O pedidos pendientes de entrega
//           que no tengan contacto registrado en los últimos 7 días
try {
    $sql = "
        SELECT p.id_pedido, p.cliente_nombre, p.cliente_telefono,
               p.estado_pago, p.total, p.metodo_pago, p.tipo_pago_estado,
               p.fecha_pedido, p.estado_pedido,
               (SELECT MAX(created_at) FROM contact_log WHERE id_pedido = p.id_pedido) as contacted_at
        FROM pedidos p
        WHERE p.cliente_telefono IS NOT NULL AND p.cliente_telefono != ''
          AND p.estado_pedido != 'Cancelado'
          AND (p.estado_pago != 'Pagado' OR p.estado_pedido IN ('Pendiente','Procesando'))
        ORDER BY
          CASE WHEN p.estado_pago = 'Pendiente Crédito' THEN 0
               WHEN p.estado_pago = 'Pendiente' THEN 1
               ELSE 2 END,
          p.fecha_pedido ASC
        LIMIT 50
    ";
    $rows = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $now = new DateTime();
    $chats = [];
    foreach ($rows as $r) {
        $dias = 0;
        if (!empty($r['fecha_pedido'])) {
            $fp = new DateTime($r['fecha_pedido']);
            $dias = (int)$fp->diff($now)->format('%r%a');
        }
        // Si fue contactado en los últimos 2 días, mostrar pero marcado
        $contacted_at = $r['contacted_at'];
        $reciente = false;
        if ($contacted_at) {
            $ca = new DateTime($contacted_at);
            $reciente = (int)$ca->diff($now)->format('%r%a') <= 2;
        }
        if (!$reciente) {
            $chats[] = [
                'id_pedido' => (int)$r['id_pedido'],
                'cliente_nombre' => $r['cliente_nombre'],
                'telefono' => $r['cliente_telefono'],
                'estado_pago' => $r['estado_pago'],
                'metodo_pago' => $r['metodo_pago'],
                'tipo_pago' => $r['tipo_pago_estado'],
                'total' => $r['total'],
                'tipo' => $r['estado_pago'] === 'Pendiente Crédito' ? 'Crédito' : ($r['metodo_pago'] === 'COD' ? 'COD' : 'Venta'),
                'dias' => $dias,
                'contacted_at' => $contacted_at,
            ];
        }
    }
    echo json_encode(['ok' => true, 'chats' => $chats, 'total' => count($chats)]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
