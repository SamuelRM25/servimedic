<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

try {
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) throw new Exception('ID de cliente inválido');

    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id_cliente = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cliente) throw new Exception('Cliente no encontrado');

    // Pedidos vinculados por id_cliente o, para registros antiguos sin vincular,
    // por coincidencia exacta de nombre.
    $stmt = $conn->prepare("
        SELECT p.id_pedido, p.es_cotizacion, p.cotizacion_numero, p.cliente_nombre,
               p.condiciones_pago, p.tipo_documento, p.tipo_pago_estado, p.metodo_pago,
               p.total, p.estado_pedido, p.estado_pago, p.fecha_pedido,
               v.numero_venta
        FROM pedidos p
        LEFT JOIN ventas v ON v.id_venta = p.id_venta
        WHERE p.id_cliente = ? OR (p.id_cliente IS NULL AND p.cliente_nombre = ?)
        ORDER BY p.fecha_pedido DESC, p.id_pedido DESC
    ");
    $stmt->execute([$id, $cliente['nombre']]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fechas: la BD guarda UTC; convertir a hora de Guatemala para mostrar
    $cliente['fecha_registro_fmt'] = fechaGT($cliente['fecha_registro']);

    // Totales: los cancelados no cuentan. Las cotizaciones abiertas (pendientes
    // de procesar) se reportan aparte porque aún no son una compra.
    $compras = 0;
    $totalComprado = 0.0;
    $totalPagado = 0.0;
    $pendientesPago = 0;
    $cotizacionesAbiertas = 0;
    $montoCotizado = 0.0;

    foreach ($pedidos as &$p) {
        $p['total'] = (float) $p['total'];
        $p['fecha_pedido_fmt'] = fechaGT($p['fecha_pedido']);
        if ($p['estado_pedido'] === 'Cancelado') continue;

        if ($p['es_cotizacion'] && !in_array($p['estado_pedido'], ['Completado', 'Cancelado'], true)) {
            $cotizacionesAbiertas++;
            $montoCotizado += $p['total'];
            continue;
        }

        $compras++;
        $totalComprado += $p['total'];
        if ($p['estado_pago'] === 'Pagado') {
            $totalPagado += $p['total'];
        } else {
            $pendientesPago++;
        }
    }
    unset($p);

    echo json_encode([
        'success' => true,
        'cliente' => $cliente,
        'pedidos' => $pedidos,
        'totales' => [
            'compras'               => $compras,
            'total_comprado'        => round($totalComprado, 2),
            'total_pagado'          => round($totalPagado, 2),
            'total_pendiente'       => round($totalComprado - $totalPagado, 2),
            'pendientes_pago'       => $pendientesPago,
            'cotizaciones_abiertas' => $cotizacionesAbiertas,
            'monto_cotizado'        => round($montoCotizado, 2),
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
