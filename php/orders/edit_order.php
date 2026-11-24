<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

try {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) throw new Exception('ID inválido');

    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT p.*, s.nombre as sucursal_nombre, s.direccion_pdf, s.telefono as suc_telefono,
               s.telefono2 as suc_telefono2, s.banco, s.cuenta_tipo, s.cuenta_numero,
               s.texto_legal_isr, s.texto_legal_iva,
               (SELECT v.numero_venta FROM ventas v WHERE v.id_pedido = p.id_pedido ORDER BY v.id_venta DESC LIMIT 1) as numero_venta
        FROM pedidos p
        LEFT JOIN sucursales s ON p.id_sucursal = s.id_sucursal
        WHERE p.id_pedido = ?
    ");
    $stmt->execute([$id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) throw new Exception('Documento no encontrado');

    $stmt = $conn->prepare("
        SELECT d.*, i.nom_medicamento, i.codigo_medicamento, i.codigo_referencia, i.codigo_barras
        FROM detalle_pedidos d
        LEFT JOIN inventario i ON d.id_inventario = i.id_inventario
        WHERE d.id_pedido = ?
        ORDER BY d.id_detalle_pedido
    ");
    $stmt->execute([$id]);
    $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Una venta que usó varios lotes del mismo medicamento queda en varias líneas
    // seguidas: se juntan para mostrarlas (PDF, ticket) como una sola.
    $juntos = [];
    foreach ($detalles as $d) {
        $ult = count($juntos) - 1;
        if ($ult >= 0
            && (string) $juntos[$ult]['descripcion'] === (string) $d['descripcion']
            && (string) $juntos[$ult]['codigo_item'] === (string) $d['codigo_item']
            && (float) $juntos[$ult]['precio_unitario'] === (float) $d['precio_unitario']
            && (string) ($juntos[$ult]['codigo_medicamento'] ?? '') === (string) ($d['codigo_medicamento'] ?? '')) {
            $juntos[$ult]['cantidad'] = (int) $juntos[$ult]['cantidad'] + (int) $d['cantidad'];
            $juntos[$ult]['subtotal'] = round((float) $juntos[$ult]['subtotal'] + (float) $d['subtotal'], 2);
            continue;
        }
        $juntos[] = $d;
    }
    $detalles = $juntos;

    echo json_encode([
        'success' => true,
        'pedido' => $pedido,
        'productos' => $detalles,
        'total_letras' => numeroALetras((float) $pedido['total'])
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
