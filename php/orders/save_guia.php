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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$id_pedido = (int)($_POST['id_pedido'] ?? 0);
$transportista = $_POST['transportista'] ?? null;
$guia_numero = trim($_POST['guia_numero'] ?? '');
$guia_estado = $_POST['guia_estado'] ?? 'Pendiente';
$guia_observaciones = trim($_POST['guia_observaciones'] ?? '');

// Regla de negocio: si hay número de guía y el estado sigue en "Pendiente",
// se marca automáticamente como "Creada".
if ($guia_numero !== '' && $guia_estado === 'Pendiente') {
    $guia_estado = 'Creada';
}

if ($id_pedido <= 0) {
    echo json_encode(['ok' => false, 'error' => 'id_pedido requerido']);
    exit;
}

$transportistas_validos = ['Forza Delivery', 'Cargo Expreso', 'Otro', null];
$estados_validos = ['Pendiente', 'Creada', 'En tránsito', 'Entregada', 'Devuelta'];

if (!in_array($transportista, $transportistas_validos, true)) {
    echo json_encode(['ok' => false, 'error' => 'Transportista inválido']);
    exit;
}
if (!in_array($guia_estado, $estados_validos, true)) {
    echo json_encode(['ok' => false, 'error' => 'Estado inválido']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT id_pedido, guia_numero FROM pedidos WHERE id_pedido = ?");
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) {
        echo json_encode(['ok' => false, 'error' => 'Pedido no encontrado']);
        exit;
    }

    $setFecha = '';
    if (!empty($guia_numero) && empty($pedido['guia_numero'])) {
        $setFecha = ', guia_fecha_creacion = NOW()';
    } elseif (empty($guia_numero)) {
        $setFecha = ', guia_fecha_creacion = NULL';
    }

    $sql = "UPDATE pedidos SET
                transportista = " . ($transportista ? "?" : "NULL") . ",
                guia_numero = " . ($guia_numero !== '' ? "?" : "NULL") . ",
                guia_estado = ?,
                guia_observaciones = " . ($guia_observaciones !== '' ? "?" : "NULL") . "
                $setFecha
            WHERE id_pedido = ?";

    $params = [];
    if ($transportista) $params[] = $transportista;
    if ($guia_numero !== '') $params[] = $guia_numero;
    $params[] = $guia_estado;
    if ($guia_observaciones !== '') $params[] = $guia_observaciones;
    $params[] = $id_pedido;

    $upd = $conn->prepare($sql);
    $upd->execute($params);

    echo json_encode(['ok' => true, 'id_pedido' => $id_pedido]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al guardar: ' . $e->getMessage()]);
}
