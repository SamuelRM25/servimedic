<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener datos del usuario
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $userSucursal = $userData['id_sucursal'] ?? 1;

    // Obtener inventario disponible
    $stmt = $conn->prepare("
        SELECT i.*,
               COALESCE(i.precio_venta, 10.00) as precio_venta_sugerido
        FROM inventario i
        WHERE i.cantidad_med > 0 AND i.estado_ingreso = 'Ingresado'
        ORDER BY i.nom_medicamento
    ");
    $stmt->execute();
    $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Sucursales activas (datos de empresa que se usan en la cotización)
    $stmt = $conn->prepare("SELECT * FROM sucursales WHERE activa = 1 ORDER BY nombre");
    $stmt->execute();
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Clientes registrados (autocompletado del nombre en el modal)
    $stmt = $conn->prepare("SELECT id_cliente, nombre, telefono, direccion FROM clientes ORDER BY nombre");
    $stmt->execute();
    $clientesRegistrados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener pedidos
    $stmt = $conn->prepare("
        SELECT p.*, u.nombre as usuario_nombre, s.nombre as sucursal_nombre,
               (SELECT v.numero_venta FROM ventas v WHERE v.id_pedido = p.id_pedido ORDER BY v.id_venta DESC LIMIT 1) as numero_venta
        FROM pedidos p
        LEFT JOIN usuarios u ON p.id_usuario = u.id
        LEFT JOIN sucursales s ON p.id_sucursal = s.id_sucursal
        ORDER BY p.fecha_pedido DESC
    ");
    $stmt->execute();
    $pedidos = array_values($stmt->fetchAll(PDO::FETCH_ASSOC));

    // Separar en pestañas.
    // Una cotización procesada (estado Completado) ya es funcionalmente una
    // venta: se muestra en la pestaña Ventas para que el usuario la encuentre
    // tras procesarla. Las cotizaciones pendientes siguen en Cotizaciones.
    $cotizaciones = array_values(array_filter($pedidos, fn($p) => $p['es_cotizacion'] && $p['estado_pedido'] !== 'Cancelado' && $p['estado_pedido'] !== 'Completado'));
    $ventas = array_values(array_filter($pedidos, fn($p) => (!$p['es_cotizacion'] || $p['estado_pedido'] === 'Completado') && $p['estado_pedido'] !== 'Cancelado'));
    $cancelados = array_values(array_filter($pedidos, fn($p) => $p['estado_pedido'] === 'Cancelado'));

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos y Cotizaciones - CLINOVA</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-orange: #FF3D00;
            --color-blue: #1FA8FF;
            --color-blue-dark: #0277BD;
            --color-white: #FFFFFF;
            --color-text: #2D3748;
            --color-text-light: #718096;
            --color-bg: #F7FAFC;
            --color-border: #E2E8F0;
            --color-success: #48BB78;
            --color-warning: #ECC94B;
            --color-danger: #F56565;
            --font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            font-family: var(--font-family);
            background: var(--color-bg);
            color: var(--color-text);
        }

        .container {
            max-width: 1800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .back-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--color-white);
            border: 1px solid var(--color-border);
            border-radius: 10px;
            color: var(--color-text);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
            color: white;
            border-color: transparent;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--color-text);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--color-orange), #E63400);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        .btn-secondary {
            background: var(--color-white);
            color: var(--color-text);
            border: 2px solid var(--color-border);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--color-white);
            padding: 1.25rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--color-text-light);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--color-text);
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .tab {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            border: 2px solid var(--color-border);
            background: var(--color-white);
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--color-text-light);
            cursor: pointer;
            font-family: var(--font-family);
            transition: all 0.2s ease;
        }

        .tab:hover {
            border-color: var(--color-blue);
            color: var(--color-blue);
        }

        .tab.active {
            background: linear-gradient(135deg, var(--color-blue), var(--color-blue-dark));
            border-color: transparent;
            color: white;
        }

        .tab-count {
            background: rgba(0, 0, 0, 0.08);
            padding: 0.1rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
        }

        .tab.active .tab-count {
            background: rgba(255, 255, 255, 0.25);
        }

        .table-container {
            background: var(--color-white);
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
        }

        thead th {
            padding: 1rem;
            text-align: left;
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid var(--color-border);
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: var(--color-bg);
        }

        tbody td {
            padding: 1rem;
            font-size: 0.875rem;
            color: var(--color-text);
            white-space: nowrap;
        }

        .badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-factura { background: #BEE3F8; color: #2C5282; }
        .badge-nota { background: #C6F6D5; color: #22543D; }
        .badge-cotizacion { background: #E9D8FD; color: #44337A; }

        .badge-efectivo { background: #C6F6D5; color: #22543D; }
        .badge-transferencia { background: #BEE3F8; color: #2C5282; }
        .badge-cod { background: #FEFCBF; color: #744210; }
        .badge-tarjeta { background: #E9D8FD; color: #44337A; }

        .badge-pendiente { background: #FED7D7; color: #742A2A; }
        .badge-procesando { background: #FEFCBF; color: #744210; }
        .badge-enviado { background: #BEE3F8; color: #2C5282; }
        .badge-entregado { background: #D6BCFA; color: #44337A; }
        .badge-completado { background: #C6F6D5; color: #22543D; }
        .badge-cancelado { background: #FED7D7; color: #742A2A; }

        .badge-pagado { background: #C6F6D5; color: #22543D; }
        .badge-pendiente-credito { background: #FEFCBF; color: #744210; }

        .text-negative {
            color: var(--color-success);
            font-weight: 700;
        }

        .action-group {
            display: flex;
            gap: 0.35rem;
        }

        .act-btn {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 8px;
            background: var(--color-bg);
            color: var(--color-blue-dark);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .act-btn:hover {
            background: var(--color-blue);
            color: white;
        }

        .act-btn.act-success:hover { background: var(--color-success); }
        .act-btn.act-danger { color: var(--color-danger); }
        .act-btn.act-danger:hover { background: var(--color-danger); color: white; }
        .act-btn.act-warning:hover { background: var(--color-warning); color: #744210; }

        .empty-row td {
            text-align: center;
            color: var(--color-text-light);
            padding: 2rem;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: var(--color-white);
            border-radius: 20px;
            max-width: 1000px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-sm {
            max-width: 560px;
        }

        /* ============ Modal de Guía de Envío (portal embebido) ============ */
        .guia-modal {
            max-width: 1500px;
            width: 96vw;
            height: 92vh;
            max-height: 92vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .guia-modal .modal-body {
            overflow-y: auto;
        }

        .guia-frame-wrap {
            flex: 1;
            min-height: 0;
            display: flex;
            flex-direction: column;
        }

        .guia-frame-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0.55rem 1.25rem;
            background: var(--color-bg);
            border-bottom: 1px solid var(--color-border);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .guia-frame {
            flex: 1;
            width: 100%;
            min-height: 0;
            border: 0;
            background: #fff;
        }

        .guia-hint {
            margin-top: 1rem;
            font-size: 0.8rem;
            color: var(--color-text-light);
            text-align: center;
        }

        .btn-sm {
            padding: 0.35rem 0.85rem;
            font-size: 0.8rem;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--color-border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--color-text);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--color-border);
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: var(--font-family);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-blue);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        .section-title {
            margin: 1.5rem 0 1rem;
            font-size: 1rem;
            font-weight: 700;
            color: var(--color-text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tipo-toggle {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .tipo-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.875rem;
            border-radius: 12px;
            border: 2px solid var(--color-border);
            background: var(--color-white);
            font-family: var(--font-family);
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--color-text-light);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .tipo-btn:hover {
            border-color: var(--color-blue);
        }

        .tipo-btn.active {
            background: linear-gradient(135deg, var(--color-blue), var(--color-blue-dark));
            border-color: transparent;
            color: white;
            box-shadow: 0 4px 12px rgba(31, 168, 255, 0.3);
        }

        .search-results {
            position: relative;
            background: var(--color-white);
            border: 2px solid var(--color-border);
            border-radius: 12px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 0.5rem;
            display: none;
        }

        .search-results.show {
            display: block;
        }

        .search-result-item {
            padding: 1rem;
            border-bottom: 1px solid var(--color-border);
            cursor: pointer;
            transition: background 0.2s;
        }

        .search-result-item:hover {
            background: var(--color-bg);
        }

        .cliente-autocomplete {
            position: relative;
        }

        .cliente-search-results {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            z-index: 60;
            background: var(--color-white);
            border: 2px solid var(--color-border);
            border-radius: 12px;
            max-height: 240px;
            overflow-y: auto;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .15);
            display: none;
        }

        .cliente-search-results.show { display: block; }

        .cliente-search-results .search-result-item { padding: .65rem .9rem; }

        .search-result-empty {
            padding: .7rem .9rem;
            color: var(--color-text-muted);
            font-size: .85rem;
        }

        .cliente-selected-note {
            margin-top: .3rem;
            font-size: .75rem;
            font-weight: 600;
            color: var(--color-success);
            display: none;
        }

        .cliente-selected-note.show { display: block; }

        .cart-section {
            background: var(--color-bg);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .cart-item {
            background: var(--color-white);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .cart-item-info {
            flex: 1;
            min-width: 0;
        }

        .cart-item-code {
            display: inline-block;
            background: var(--color-blue-dark);
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            margin-right: 0.4rem;
        }

        .cart-controls {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .cart-input {
            padding: 0.4rem 0.5rem;
            border: 2px solid var(--color-border);
            border-radius: 8px;
            font-family: var(--font-family);
            font-size: 0.85rem;
            text-align: right;
        }

        .cart-input:focus {
            outline: none;
            border-color: var(--color-blue);
        }

        .cart-remove {
            background: #FED7D7;
            color: #742A2A;
            border: none;
            border-radius: 8px;
            width: 32px;
            height: 32px;
            cursor: pointer;
        }

        .manual-line-form {
            display: grid;
            grid-template-columns: 110px 1fr 110px 90px auto;
            gap: 0.5rem;
            align-items: end;
            background: var(--color-white);
            padding: 0.75rem;
            border-radius: 10px;
            margin-top: 0.75rem;
        }

        .manual-line-form .form-group {
            margin-bottom: 0;
        }

        .manual-line-form .form-label {
            font-size: 0.7rem;
            margin-bottom: 0.25rem;
        }

        .manual-line-form .form-control {
            padding: 0.5rem 0.6rem;
            font-size: 0.8rem;
        }

        .btn-add-line {
            height: 38px;
            padding: 0 1rem;
            background: var(--color-blue-dark);
            color: white;
            border: none;
            border-radius: 8px;
            font-family: var(--font-family);
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-add-line:hover {
            background: var(--color-blue);
        }

        .totals-section {
            background: var(--color-bg);
            padding: 1rem;
            border-radius: 12px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .total-row.main {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-orange);
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 2px solid var(--color-border);
        }

        .cot-note {
            margin-top: 0.75rem;
            padding: 0.6rem 0.75rem;
            background: #E9D8FD;
            color: #44337A;
            border-radius: 8px;
            font-size: 0.8rem;
        }

        .send-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1.25rem;
        }

        .send-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            padding: 1.5rem 1rem;
            border: 2px solid var(--color-border);
            border-radius: 14px;
            background: var(--color-white);
            cursor: pointer;
            font-family: var(--font-family);
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--color-text);
            transition: all 0.2s ease;
        }

        .send-option i {
            font-size: 2rem;
            color: var(--color-blue-dark);
        }

        .send-option:hover {
            border-color: var(--color-blue);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(31, 168, 255, 0.2);
        }

        .send-option.whatsapp:hover {
            border-color: #25D366;
            box-shadow: 0 6px 16px rgba(37, 211, 102, 0.25);
        }

        .send-option.whatsapp i {
            color: #25D366;
        }

        .proc-info {
            background: var(--color-bg);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.9rem;
        }

        .proc-info strong {
            color: var(--color-blue-dark);
        }

        @media (max-width: 768px) {
            .form-row, .form-row-3 {
                grid-template-columns: 1fr;
            }
            .manual-line-form {
                grid-template-columns: 1fr 1fr;
            }
            .cart-item {
                flex-direction: column;
                align-items: stretch;
            }
            .send-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title-section">
                <a href="../dashboard/index.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="page-title">Pedidos y Cotizaciones</h1>
            </div>
            <?php include '../../includes/clock.php'; ?>
            <button class="btn btn-primary" onclick="openNewOrder()">
                <i class="bi bi-plus-circle"></i>
                Nuevo Documento
            </button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label" id="statLabel1">Total Cotizaciones</div>
                <div class="stat-value" id="statValue1">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-label" id="statLabel2">Monto Total</div>
                <div class="stat-value" id="statValue2">Q0.00</div>
            </div>
            <div class="stat-card">
                <div class="stat-label" id="statLabel3">Pendientes</div>
                <div class="stat-value" id="statValue3">0</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" data-tab="cotizaciones" onclick="switchTab('cotizaciones')">
                <i class="bi bi-file-earmark-text"></i> Cotizaciones
                <span class="tab-count"><?php echo count($cotizaciones); ?></span>
            </button>
            <button class="tab" data-tab="ventas" onclick="switchTab('ventas')">
                <i class="bi bi-cart-check"></i> Ventas
                <span class="tab-count"><?php echo count($ventas); ?></span>
            </button>
            <button class="tab" data-tab="cancelados" onclick="switchTab('cancelados')">
                <i class="bi bi-x-circle"></i> Cancelados
                <span class="tab-count"><?php echo count($cancelados); ?></span>
            </button>
        </div>

        <!-- Tabla Cotizaciones -->
        <div class="table-container tab-panel" id="panel-cotizaciones">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th># Cotización</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Condiciones</th>
                            <th>Subtotal</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$cotizaciones): ?>
                        <tr class="empty-row"><td colspan="9">No hay cotizaciones registradas</td></tr>
                        <?php endif; ?>
                        <?php foreach ($cotizaciones as $p): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($p['cotizacion_numero'] ?: ('COT-' . $p['id_pedido'])); ?></strong></td>
                            <td><?php echo fechaGT($p['fecha_pedido']); ?></td>
                            <td><strong><?php echo htmlspecialchars($p['cliente_nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['cliente_telefono']); ?></td>
                            <td><?php echo htmlspecialchars($p['condiciones_pago'] ?: '-'); ?></td>
                            <td>Q<?php echo number_format($p['subtotal'], 2); ?></td>
                            <td><strong>Q<?php echo number_format($p['total'], 2); ?></strong></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($p['estado_pedido']); ?>">
                                    <?php echo $p['estado_pedido']; ?>
                                    <?php if ($p['id_venta']): ?>
                                        &rarr; Venta #<?php echo $p['id_venta']; ?>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-group">
                                    <button class="act-btn" title="Descargar PDF" onclick="descargarCotizacionPDF(<?php echo $p['id_pedido']; ?>)">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </button>
                                    <button class="act-btn" title="Enviar por WhatsApp" onclick="enviarCotizacionWhatsApp(<?php echo $p['id_pedido']; ?>)">
                                        <i class="bi bi-whatsapp"></i>
                                    </button>
                                    <?php if ($p['estado_pedido'] === 'Pendiente'): ?>
                                    <button class="act-btn act-warning" title="Editar" onclick="openEditOrder(<?php echo $p['id_pedido']; ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="act-btn act-success" title="Procesar venta" onclick="openProcessModal(<?php echo $p['id_pedido']; ?>)">
                                        <i class="bi bi-cart-check"></i>
                                    </button>
                                    <button class="act-btn act-danger" title="Cancelar" onclick="cancelarCotizacion(<?php echo $p['id_pedido']; ?>)">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="act-btn" title="Imprimir ticket" onclick='printOrderTicket(<?php echo json_encode($p); ?>)'>
                                        <i class="bi bi-printer"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabla Ventas -->
        <div class="table-container tab-panel" id="panel-ventas" style="display: none;">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Tipo Doc.</th>
                            <th>Pago</th>
                            <th>Método</th>
                            <th>Subtotal</th>
                            <th>Total</th>
                            <th>Estado Pedido</th>
                            <th>Estado Pago</th>
                            <th>Guía de Envío</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$ventas): ?>
                        <tr class="empty-row"><td colspan="13">No hay ventas registradas en este módulo</td></tr>
                        <?php endif; ?>
                        <?php foreach ($ventas as $p): ?>
                        <tr>
                            <td>
                                <?php if ($p['es_cotizacion']): ?>
                                    <span title="Venta procesada desde cotización"><?php echo htmlspecialchars($p['cotizacion_numero'] ?: 'COT-'.$p['id_pedido']); ?></span>
                                    <br><small style="color:#888;"><?php echo !empty($p['numero_venta']) ? htmlspecialchars($p['numero_venta']) : 'Venta #'.$p['id_pedido']; ?></small>
                                <?php elseif (!empty($p['numero_venta'])): ?>
                                    <strong><?php echo htmlspecialchars($p['numero_venta']); ?></strong>
                                    <br><small style="color:#888;">Pedido #<?php echo $p['id_pedido']; ?></small>
                                <?php else: ?>
                                    <?php echo $p['id_pedido']; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo fechaGT($p['fecha_pedido']); ?></td>
                            <td><strong><?php echo htmlspecialchars($p['cliente_nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($p['cliente_telefono']); ?></td>
                            <td>
                                <span class="badge <?php echo $p['tipo_documento'] === 'Factura' ? 'badge-factura' : 'badge-nota'; ?>">
                                    <?php echo $p['tipo_documento']; ?>
                                </span>
                            </td>
                            <td><?php echo $p['tipo_pago_estado']; ?></td>
                            <td>
                                <?php
                                $metodoClass = [
                                    'Efectivo' => 'badge-efectivo',
                                    'Transferencia' => 'badge-transferencia',
                                    'COD' => 'badge-cod',
                                    'Tarjeta' => 'badge-tarjeta',
                                ][$p['metodo_pago']] ?? 'badge-efectivo';
                                ?>
                                <span class="badge <?php echo $metodoClass; ?>">
                                    <?php echo $p['metodo_pago']; ?>
                                </span>
                            </td>
                            <td>Q<?php echo number_format($p['subtotal'], 2); ?></td>
                            <td><strong>Q<?php echo number_format($p['total'], 2); ?></strong></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($p['estado_pedido']); ?>">
                                    <?php echo $p['estado_pedido']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($p['estado_pago'] === 'Pagado'): ?>
                                    <span class="badge badge-pagado"><?php echo $p['estado_pago']; ?></span>
                                <?php else: ?>
                                    <button class="badge badge-pendiente-credito estado-pago-btn" style="border:none; cursor:pointer; font-size:inherit;"
                                            onclick="marcarPagado(<?php echo (int)$p['id_pedido']; ?>, this)"
                                            title="Clic para marcar como Pagado">
                                        <i class="bi bi-circle"></i> <?php echo $p['estado_pago']; ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($p['guia_numero'])): ?>
                                    <div style="font-size:0.8rem;">
                                        <strong><?php echo htmlspecialchars($p['transportista'] ?? ''); ?></strong><br>
                                        <code><?php echo htmlspecialchars($p['guia_numero']); ?></code><br>
                                        <span class="badge badge-<?php echo strtolower(str_replace(' ', '-', $p['guia_estado'])); ?>" style="font-size:0.7rem;">
                                            <?php echo htmlspecialchars($p['guia_estado']); ?>
                                        </span>
                                    </div>
                                <?php else: ?>
                                    <span style="color:#999; font-size:0.85rem;">Sin guía</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-group">
                                    <button class="act-btn" title="PDF / Imprimir / WhatsApp" onclick="openSalePdfModal(<?php echo (int)$p['id_pedido']; ?>)">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </button>
                                    <button class="act-btn" title="Imprimir ticket" onclick='printOrderTicket(<?php echo json_encode($p); ?>)'>
                                        <i class="bi bi-printer"></i>
                                    </button>
                                    <button class="act-btn" title="Generar guía de envío" onclick="openGuiaModalForVenta(<?php echo (int)$p['id_pedido']; ?>)">
                                        <i class="bi bi-truck"></i>
                                    </button>
                                    <button class="act-btn act-info" title="Registrar/editar guía" onclick="openGuiaEditModal(<?php echo (int)$p['id_pedido']; ?>)">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tabla Cancelados -->
        <div class="table-container tab-panel" id="panel-cancelados" style="display: none;">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tipo</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Subtotal</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$cancelados): ?>
                        <tr class="empty-row"><td colspan="8">No hay documentos cancelados</td></tr>
                        <?php endif; ?>
                        <?php foreach ($cancelados as $p): ?>
                        <tr>
                            <td><?php echo $p['es_cotizacion'] ? htmlspecialchars($p['cotizacion_numero'] ?: ('COT-' . $p['id_pedido'])) : $p['id_pedido']; ?></td>
                            <td>
                                <span class="badge <?php echo $p['es_cotizacion'] ? 'badge-cotizacion' : 'badge-nota'; ?>">
                                    <?php echo $p['es_cotizacion'] ? 'Cotización' : 'Pedido'; ?>
                                </span>
                            </td>
                            <td><?php echo fechaGT($p['fecha_pedido']); ?></td>
                            <td><strong><?php echo htmlspecialchars($p['cliente_nombre']); ?></strong></td>
                            <td>Q<?php echo number_format($p['subtotal'], 2); ?></td>
                            <td><strong>Q<?php echo number_format($p['total'], 2); ?></strong></td>
                            <td><span class="badge badge-cancelado">Cancelado</span></td>
                            <td>
                                <?php if ($p['es_cotizacion']): ?>
                                <div class="action-group">
                                    <button class="act-btn" title="Descargar PDF" onclick="descargarCotizacionPDF(<?php echo $p['id_pedido']; ?>)">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </button>
                                </div>
                                <?php else: ?>
                                <span style="color: var(--color-text-light);">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- New / Edit Order Modal -->
    <div class="modal-overlay" id="orderModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title" id="orderModalTitle">Nueva Cotización</h2>
                <button class="modal-close" onclick="closeOrderModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="orderForm">
                    <input type="hidden" name="id_pedido" id="id_pedido">

                    <!-- Tipo de documento: Cotización vs Venta directa -->
                    <div class="tipo-toggle" id="tipoToggle">
                        <button type="button" class="tipo-btn active" id="btnCotizacion" onclick="setTipo('cotizacion')">
                            <i class="bi bi-file-earmark-text"></i> Cotización
                        </button>
                        <button type="button" class="tipo-btn" id="btnVenta" onclick="setTipo('venta')">
                            <i class="bi bi-cart-check"></i> Venta Directa
                        </button>
                    </div>

                    <!-- Customer Info -->
                    <h4 class="section-title"><i class="bi bi-person"></i> Información del Cliente</h4>
                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">Nombre/Razón Social *</label>
                            <div class="cliente-autocomplete">
                                <input type="text" name="cliente_nombre" id="cliente_nombre" class="form-control" required autocomplete="off" placeholder="Buscar cliente registrado o escribir uno nuevo...">
                                <div id="clienteSearchResults" class="cliente-search-results"></div>
                            </div>
                            <input type="hidden" name="id_cliente" id="id_cliente">
                            <div class="cliente-selected-note" id="clienteVinculoNota"><i class="bi bi-link-45deg"></i> Vinculado a cliente registrado</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Teléfono *</label>
                            <input type="text" name="cliente_telefono" id="cliente_telefono" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="cliente_direccion" id="cliente_direccion" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Condiciones de Pago</label>
                            <input type="text" name="condiciones_pago" id="condiciones_pago" class="form-control" placeholder="Ej: Contado, Crédito a 15 días">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sucursal (datos de la empresa en el PDF) *</label>
                            <select name="id_sucursal" id="id_sucursal" class="form-control" required>
                                <?php foreach ($sucursales as $suc): ?>
                                <option value="<?php echo $suc['id_sucursal']; ?>" <?php echo $suc['id_sucursal'] == $userSucursal ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($suc['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Payment Info -->
                    <h4 class="section-title"><i class="bi bi-credit-card"></i> Configuración de Pago</h4>
                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">Tipo de Documento *</label>
                            <select name="tipo_documento" id="tipo_documento" class="form-control" required>
                                <option value="Nota de Envío">Nota de Envío</option>
                                <option value="Factura">Factura</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Estado de Pago *</label>
                            <select name="tipo_pago_estado" id="tipo_pago_estado" class="form-control" required>
                                <option value="Al Contado">Al Contado</option>
                                <option value="Crédito">Crédito</option>
                            </select>
                        </div>
                        <div class="form-group" id="metodoPagoGroup">
                            <label class="form-label">Método de Pago *</label>
                            <select name="metodo_pago" id="metodoPago" class="form-control" required>
                                <option value="Efectivo">Efectivo (sin descuento)</option>
                                <option value="Transferencia">Transferencia (sin descuento)</option>
                                <option value="Tarjeta">Tarjeta (6.2% descuento)</option>
                                <option value="COD">COD (contra entrega, ver regla)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Products Section -->
                    <h4 class="section-title"><i class="bi bi-box-seam"></i> Productos</h4>
                    <div class="form-group">
                        <label class="form-label">Buscar Medicamento</label>
                        <input type="text" id="searchMedicine" class="form-control" placeholder="Buscar por nombre o molécula..." autocomplete="off">
                        <div id="searchResults" class="search-results"></div>
                    </div>

                    <div class="cart-section">
                        <h5 style="margin-bottom: 1rem;">Productos en el Documento</h5>
                        <div id="cartItems"></div>
                        <p id="emptyCart" style="text-align: center; color: var(--color-text-light);">No hay productos agregados</p>

                        <div class="manual-line-form">
                            <div class="form-group">
                                <label class="form-label">Código</label>
                                <input type="text" id="linea_codigo" class="form-control" value="EN-001" maxlength="20">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Descripción</label>
                                <input type="text" id="linea_descripcion" class="form-control" value="ENVIO">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Precio Q</label>
                                <input type="number" id="linea_precio" class="form-control" value="40.00" min="0" step="0.01">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Cant.</label>
                                <input type="number" id="linea_cantidad" class="form-control" value="1" min="1">
                            </div>
                            <button type="button" class="btn-add-line" onclick="addManualLine()">
                                <i class="bi bi-plus-lg"></i> Línea manual
                            </button>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="totals-section">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <strong>Q<span id="subtotal">0.00</span></strong>
                        </div>
                        <div class="total-row" id="descRow" style="display: none;">
                            <span>Descuento <span id="descMetodo">-</span> (solo Reportes): <small id="descDetalle" style="color: var(--color-text-light);"></small></span>
                            <strong style="color: var(--color-success);">Q<span id="descValor">0.00</span></strong>
                        </div>
                        <div class="total-row main">
                            <span>TOTAL:</span>
                            <span>Q<span id="total">0.00</span></span>
                        </div>
                        <div class="cot-note" id="cotNote">
                            <i class="bi bi-info-circle"></i>
                            El cliente siempre paga el TOTAL. El descuento por método de pago (Tarjeta 6.2% / COD) se calcula y muestra únicamente en Reportes.
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;" id="submitBtn">
                            <i class="bi bi-send"></i>
                            <span id="submitBtnText">Guardar y Enviar</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Process Cotización Modal -->
    <div class="modal-overlay" id="processModal">
        <div class="modal modal-sm">
            <div class="modal-header">
                <h2 class="modal-title">Procesar Cotización como Venta</h2>
                <button class="modal-close" onclick="closeModal('processModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="proc-info" id="procInfo"></div>
                <form id="processForm">
                    <input type="hidden" id="proc_id_pedido">
                    <div class="form-group">
                        <label class="form-label">Método de Pago *</label>
                        <select id="proc_metodo_pago" class="form-control" required onchange="updateProcessTotals()">
                            <option value="Efectivo">Efectivo (sin descuento)</option>
                            <option value="Transferencia">Transferencia (sin descuento)</option>
                            <option value="Tarjeta">Tarjeta (6.2% descuento)</option>
                            <option value="COD">COD (contra entrega, ver regla)</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tipo de Documento *</label>
                            <select id="proc_tipo_documento" class="form-control" required>
                                <option value="Nota de Envío">Nota de Envío</option>
                                <option value="Factura">Factura</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Estado de Pago *</label>
                            <select id="proc_tipo_pago_estado" class="form-control" required>
                                <option value="Al Contado">Al Contado</option>
                                <option value="Crédito">Crédito</option>
                            </select>
                        </div>
                    </div>

                    <div class="totals-section">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <strong>Q<span id="proc_subtotal">0.00</span></strong>
                        </div>
                        <div class="total-row">
                            <span>Descuento (solo Reportes): <small id="proc_desglose" style="color: var(--color-text-light);"></small></span>
                            <strong style="color: var(--color-success);">Q<span id="proc_descuento">0.00</span></strong>
                        </div>
                        <div class="total-row main">
                            <span>TOTAL A COBRAR:</span>
                            <span>Q<span id="proc_total">0.00</span></span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('processModal')">Cancelar</button>
                <button class="btn btn-primary" onclick="confirmProcess()">
                    <i class="bi bi-check-circle"></i> Confirmar Venta
                </button>
            </div>
        </div>
    </div>

    <!-- Send Cotización Modal -->
    <div class="modal-overlay" id="sendModal">
        <div class="modal modal-sm">
            <div class="modal-header">
                <h2 class="modal-title">Cotización Guardada</h2>
                <button class="modal-close" onclick="closeSendModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="proc-info">
                    <strong id="sendNumero">COT-00000000-0000</strong><br>
                    Cliente: <span id="sendCliente"></span><br>
                    Total: <span id="sendTotal"></span>
                </div>
                <div class="send-options">
                    <button type="button" class="send-option" onclick="sendDownloadPDF()">
                        <i class="bi bi-file-earmark-pdf"></i>
                        Descargar PDF
                    </button>
                    <button type="button" class="send-option whatsapp" onclick="sendWhatsApp()">
                        <i class="bi bi-whatsapp"></i>
                        Enviar por WhatsApp
                    </button>
                </div>
                <p style="margin-top: 1rem; font-size: 0.8rem; color: var(--color-text-light); text-align: center;">
                    Al enviar por WhatsApp se descarga el PDF para que lo adjunte en la conversación.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeSendModal()">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- PDF de Venta Modal -->
    <div class="modal-overlay" id="salePdfModal">
        <div class="modal modal-sm">
            <div class="modal-header">
                <h2 class="modal-title">Venta Realizada</h2>
                <button class="modal-close" onclick="closeSalePdfModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="proc-info">
                    <strong id="salePdfNumero">VEN-00000000-0000</strong>
                    <span id="salePdfEstado" class="badge badge-pagado" style="margin-left: 0.4rem;">Pagado</span><br>
                    Cliente: <span id="salePdfCliente"></span><br>
                    Total: <span id="salePdfTotal"></span>
                </div>
                <div class="send-options">
                    <button type="button" class="send-option" onclick="descargarVentaPDF()">
                        <i class="bi bi-file-earmark-pdf"></i>
                        Descargar PDF
                    </button>
                    <button type="button" class="send-option" onclick="imprimirVentaPDF()">
                        <i class="bi bi-printer"></i>
                        Imprimir PDF
                    </button>
                    <button type="button" class="send-option whatsapp" onclick="enviarVentaWhatsApp()">
                        <i class="bi bi-whatsapp"></i>
                        Enviar por WhatsApp
                    </button>
                </div>
                <p style="margin-top: 1rem; font-size: 0.8rem; color: var(--color-text-light); text-align: center;">
                    Al enviar por WhatsApp se descarga el PDF para que lo adjunte en la conversación.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeSalePdfModal()">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Guía de Envío Modal (portal del transportista embebido con sesión iniciada) -->
    <div class="modal-overlay" id="guiaModal">
        <div class="modal guia-modal">
            <div class="modal-header">
                <h2 class="modal-title">Generar Guía de Envío</h2>
                <button class="modal-close" onclick="closeGuiaModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body" id="guiaStep1">
                <div class="proc-info" id="guiaInfo"></div>
                <div class="send-options">
                    <button type="button" class="send-option" onclick="selectGuiaService('forza')">
                        <i class="bi bi-box-seam"></i>
                        Forza Delivery
                    </button>
                    <button type="button" class="send-option" onclick="selectGuiaService('cargo')">
                        <i class="bi bi-truck"></i>
                        Cargo Expreso
                    </button>
                </div>
                <p class="guia-hint">
                    Se abrirá el portal del transportista dentro de esta ventana, con la sesión ya iniciada.
                    Genere la guía allí y cierre esta ventana al terminar.
                </p>
            </div>
            <div class="guia-frame-wrap" id="guiaCargoNotice" style="display: none;">
                <div class="guia-frame-bar">
                    <span id="guiaFrameLabel">Portal: Cargo Expreso</span>
                    <button class="btn btn-secondary btn-sm" onclick="guiaBack()">
                        <i class="bi bi-arrow-left"></i> Elegir otro transportista
                    </button>
                </div>
                <div style="padding:1.5rem; background:#e7f3ff; border:1px solid #b6daff; border-radius:6px; color:#1a4d80;">
                    <h4 style="margin-top:0;"><i class="bi bi-box-arrow-up-right"></i> Guía de Cargo Expreso</h4>
                    <p>Se abrió el portal de <strong>portal.cargo-expreso.com</strong> en una pestaña nueva para que generes la guía allá.</p>
                    <p>El iframe no funciona con el login de Oracle IDCS de Cargo (cookies de sesión bloqueadas), por eso se abre en una pestaña aparte donde el login funciona normalmente.</p>
                    <ol style="margin-left:1.2rem;">
                        <li>En la pestaña abierta, inicia sesión con tu correo y contraseña</li>
                        <li>Genera la guía de envío con los datos del pedido</li>
                        <li>Cuando termines, vuelve a esta ventana y ciérrala</li>
                    </ol>
                    <div style="margin-top:1rem; display:flex; gap:.5rem; flex-wrap:wrap;">
                        <button class="btn btn-primary" onclick="window.open('https://portal.cargo-expreso.com', '_blank')">
                            <i class="bi bi-box-arrow-up-right"></i> Abrir portal de nuevo
                        </button>
                        <button class="btn btn-secondary" onclick="guiaBack()">
                            <i class="bi bi-arrow-left"></i> Elegir otro
                        </button>
                    </div>
                </div>
            </div>
            <div class="guia-frame-wrap" id="guiaStep2" style="display: none;">
                <div class="guia-frame-bar">
                    <span id="guiaFrameLabel">Portal</span>
                    <button class="btn btn-secondary btn-sm" onclick="guiaBack()">
                        <i class="bi bi-arrow-left"></i> Elegir otro transportista
                    </button>
                </div>
                <iframe id="guiaFrame" class="guia-frame" src="about:blank" title="Portal de guías"></iframe>
                <div id="guiaFallback" style="display:none; padding:1.5rem; background:#fff3cd; border:1px solid #ffc107; border-radius:6px; margin-top:.75rem; color:#856404;">
                    <strong><i class="bi bi-exclamation-triangle"></i> No se pudo iniciar sesión automáticamente</strong>
                    <p style="margin:.5rem 0 0; font-size:.9rem;">El portal requiere cookies o estado del navegador que el iframe no puede establecer. Abre el portal en una pestaña nueva para iniciar sesión manualmente; la sesión quedará en este mismo navegador y al volver podrás generar la guía.</p>
                    <div style="margin-top:.75rem; display:flex; gap:.5rem; flex-wrap:wrap;">
                        <button class="btn btn-primary btn-sm" id="guiaOpenPortalBtn">
                            <i class="bi bi-box-arrow-up-right"></i> Abrir portal en nueva pestaña
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="document.getElementById('guiaFrame').src=document.getElementById('guiaFrame').src.split('#')[0]+'#r='+Date.now();">
                            <i class="bi bi-arrow-clockwise"></i> Reintentar
                        </button>
                        <button class="btn btn-secondary btn-sm" onclick="guiaBack()">
                            <i class="bi bi-arrow-left"></i> Elegir otro
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Registrar/Editar Guía de Envío (datos de la guía creada en el portal externo) -->
    <div class="modal-overlay" id="guiaEditModal">
        <div class="modal" style="max-width:520px;">
            <div class="modal-header">
                <h2 class="modal-title"><i class="bi bi-truck"></i> Registrar Guía de Envío</h2>
                <button class="modal-close" onclick="closeGuiaEditModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="guiaEditForm" onsubmit="return saveGuiaEdit(event)">
                    <input type="hidden" id="guiaEditIdPedido" name="id_pedido">
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Transportista</label>
                        <select id="guiaEditTransportista" name="transportista" class="form-control">
                            <option value="">-- Sin guía --</option>
                            <option value="Forza Delivery">Forza Delivery</option>
                            <option value="Cargo Expreso">Cargo Expreso</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Número de guía</label>
                        <input type="text" id="guiaEditNumero" name="guia_numero" class="form-control"
                               placeholder="Ej: FE-2024-001234">
                    </div>
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Estado</label>
                        <select id="guiaEditEstado" name="guia_estado" class="form-control">
                            <option value="Pendiente">Pendiente</option>
                            <option value="Creada">Creada</option>
                            <option value="En tránsito">En tránsito</option>
                            <option value="Entregada">Entregada</option>
                            <option value="Devuelta">Devuelta</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Observaciones</label>
                        <textarea id="guiaEditObservaciones" name="guia_observaciones" class="form-control" rows="3"
                                  placeholder="Notas sobre la entrega, transportista, etc."></textarea>
                    </div>
                    <div id="guiaEditMsg" style="display:none; padding:.5rem .75rem; border-radius:6px; margin-bottom:.5rem;"></div>
                    <div style="display:flex; gap:.5rem; justify-content:flex-end; margin-top:1rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeGuiaEditModal()">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="guiaEditSaveBtn">
                            <i class="bi bi-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../assets/js/ticket_printer.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="../../assets/js/cotizacion_pdf.js"></script>
    <script>
        const inventario = <?php echo json_encode($inventario); ?>;
        const pedidosData = <?php echo json_encode($pedidos); ?>;
        const clientesData = <?php echo json_encode($clientesRegistrados); ?>;
        const tabsData = {
            cotizaciones: <?php echo json_encode($cotizaciones); ?>,
            ventas: <?php echo json_encode($ventas); ?>,
            cancelados: <?php echo json_encode($cancelados); ?>
        };

        let cart = [];
        let currentTipo = 'cotizacion';
        let editingId = null;
        let currentTab = 'cotizaciones';
        let lastSavedCotizacion = null;

        const searchInput = document.getElementById('searchMedicine');
        const searchResults = document.getElementById('searchResults');
        const cartItemsDiv = document.getElementById('cartItems');
        const emptyCartMsg = document.getElementById('emptyCart');
        const metodoPagoSelect = document.getElementById('metodoPago');
        const manualLineForm = document.querySelector('.manual-line-form');

        // ============ Reglas de descuento (espejo de PHP) ============
        function calcularDescuento(subtotal, metodo) {
            let descuento = 0;
            let desglose = 'Sin descuento';
            if (metodo === 'Tarjeta') {
                descuento = Math.round(subtotal * 0.062 * 100) / 100;
                desglose = 'Tarjeta: 6.2% sobre subtotal';
            } else if (metodo === 'COD') {
                const base = Math.max(0, subtotal - 24);
                descuento = Math.min(subtotal, Math.round((24 + base * 0.04) * 100) / 100);
                desglose = 'COD: -Q24 + 4% sobre el resto';
            }
            return {
                descuento: descuento,
                total: Math.round((subtotal - descuento) * 100) / 100,
                desglose: desglose
            };
        }

        // ============ Pestañas ============
        function switchTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
            document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
            document.getElementById('panel-' + tab).style.display = '';
            updateStats(tab);
        }

        // Al cargar, si veníamos de procesar una cotización, activar Ventas
        (function () {
            try {
                const t = sessionStorage.getItem('pendingTab');
                if (t && tabsData[t]) {
                    sessionStorage.removeItem('pendingTab');
                    switchTab(t);
                }
            } catch (e) {}
        })();

        function updateStats(tab) {
            const list = tabsData[tab];
            const monto = list.reduce((s, p) => s + parseFloat(p.total), 0);
            if (tab === 'cotizaciones') {
                document.getElementById('statLabel1').textContent = 'Total Cotizaciones';
                document.getElementById('statValue1').textContent = list.length;
                document.getElementById('statLabel2').textContent = 'Monto Total Cotizaciones';
                document.getElementById('statValue2').textContent = 'Q' + monto.toFixed(2);
                document.getElementById('statLabel3').textContent = 'Pendientes';
                document.getElementById('statValue3').textContent = list.filter(p => p.estado_pedido === 'Pendiente').length;
            } else if (tab === 'ventas') {
                document.getElementById('statLabel1').textContent = 'Total Ventas';
                document.getElementById('statValue1').textContent = list.length;
                document.getElementById('statLabel2').textContent = 'Monto Total Ventas';
                document.getElementById('statValue2').textContent = 'Q' + monto.toFixed(2);
                document.getElementById('statLabel3').textContent = 'Descuentos (solo Reportes)';
                document.getElementById('statValue3').textContent = '\u2014';
            } else {
                document.getElementById('statLabel1').textContent = 'Total Cancelados';
                document.getElementById('statValue1').textContent = list.length;
                document.getElementById('statLabel2').textContent = 'Monto Cancelado';
                document.getElementById('statValue2').textContent = 'Q' + monto.toFixed(2);
                document.getElementById('statLabel3').textContent = '-';
                document.getElementById('statValue3').textContent = '0';
            }
        }

        // ============ Búsqueda de productos ============
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            if (term.length < 2) {
                searchResults.classList.remove('show');
                return;
            }

            const filtered = inventario.filter(item =>
                item.nom_medicamento.toLowerCase().includes(term) ||
                (item.mol_medicamento || '').toLowerCase().includes(term) ||
                (item.codigo_medicamento || '').toLowerCase().includes(term) ||
                (item.codigo_referencia || '').toLowerCase().includes(term)
            );

            if (filtered.length > 0) {
                searchResults.innerHTML = filtered.map(item => `
                    <div class="search-result-item" onclick="addToCart(${item.id_inventario})">
                        <strong>${escapeHtml(item.nom_medicamento)}</strong>
                        ${(item.codigo_medicamento || item.codigo_referencia) ? '<span class="cart-item-code">' + escapeHtml(item.codigo_medicamento || item.codigo_referencia) + '</span>' : ''}
                        <br>
                        <small>${escapeHtml(item.mol_medicamento || '')} • Stock: ${item.cantidad_med} • Q${parseFloat(item.precio_venta_sugerido).toFixed(2)}</small>
                    </div>
                `).join('');
                searchResults.classList.add('show');
            } else {
                searchResults.classList.remove('show');
            }
        });

        // ============ Búsqueda/autocompletado de clientes ============
        const clienteNombreInput = document.getElementById('cliente_nombre');
        const clienteSearchResults = document.getElementById('clienteSearchResults');
        const idClienteHidden = document.getElementById('id_cliente');
        const clienteVinculoNota = document.getElementById('clienteVinculoNota');

        clienteNombreInput.addEventListener('input', function() {
            idClienteHidden.value = '';
            clienteVinculoNota.classList.remove('show');

            const term = this.value.trim().toLowerCase();
            if (term.length < 1) {
                clienteSearchResults.classList.remove('show');
                return;
            }

            const filtered = clientesData.filter(c =>
                c.nombre.toLowerCase().includes(term) ||
                (c.telefono || '').toLowerCase().includes(term)
            ).slice(0, 10);

            if (filtered.length > 0) {
                clienteSearchResults.innerHTML = filtered.map(c => `
                    <div class="search-result-item" onclick="seleccionarCliente(${c.id_cliente})">
                        <strong>${escapeHtml(c.nombre)}</strong>
                        ${c.telefono ? '<br><small><i class="bi bi-telephone"></i> ' + escapeHtml(c.telefono) + '</small>' : ''}
                        ${c.direccion ? '<br><small>' + escapeHtml(c.direccion) + '</small>' : ''}
                    </div>
                `).join('');
            } else {
                clienteSearchResults.innerHTML = '<div class="search-result-empty">Sin coincidencias: se usará el nombre escrito (sin vincular a un cliente registrado).</div>';
            }
            clienteSearchResults.classList.add('show');
        });

        function seleccionarCliente(idCliente) {
            const c = clientesData.find(x => x.id_cliente == idCliente);
            if (!c) return;
            clienteNombreInput.value = c.nombre;
            document.getElementById('cliente_telefono').value = c.telefono || '';
            document.getElementById('cliente_direccion').value = c.direccion || '';
            idClienteHidden.value = c.id_cliente;
            clienteVinculoNota.classList.add('show');
            clienteSearchResults.classList.remove('show');
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.cliente-autocomplete')) {
                clienteSearchResults.classList.remove('show');
            }
        });

        function addToCart(idInventario) {
            const item = inventario.find(i => i.id_inventario == idInventario);
            if (!item) return;

            const existing = cart.find(c => c.id_inventario == idInventario);
            if (existing) {
                if (existing.cantidad < item.cantidad_med) {
                    existing.cantidad++;
                    existing.subtotal = existing.cantidad * existing.precio;
                }
            } else {
                cart.push({
                    id_inventario: item.id_inventario,
                    codigo: item.codigo_medicamento || item.codigo_referencia || item.codigo_barras || '',
                    nombre: item.nom_medicamento,
                    precio: parseFloat(item.precio_venta_sugerido),
                    cantidad: 1,
                    stock: item.cantidad_med,
                    subtotal: parseFloat(item.precio_venta_sugerido)
                });
            }

            searchInput.value = '';
            searchResults.classList.remove('show');
            updateCartDisplay();
        }

        function addManualLine() {
            const codigo = document.getElementById('linea_codigo').value.trim() || 'EN-001';
            const descripcion = document.getElementById('linea_descripcion').value.trim() || 'ENVIO';
            const precio = parseFloat(document.getElementById('linea_precio').value) || 0;
            const cantidad = Math.max(1, parseInt(document.getElementById('linea_cantidad').value) || 1);

            cart.push({
                id_inventario: null,
                codigo: codigo,
                nombre: descripcion,
                precio: precio,
                cantidad: cantidad,
                stock: null,
                subtotal: precio * cantidad
            });
            updateCartDisplay();
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        }

        function updateQuantity(index, cantidad) {
            cantidad = parseInt(cantidad);
            if (isNaN(cantidad) || cantidad < 1) return;
            const item = cart[index];
            if (item.stock === null || cantidad <= item.stock) {
                item.cantidad = cantidad;
                item.subtotal = item.cantidad * item.precio;
                updateCartDisplay();
            } else {
                alert('Stock disponible: ' + item.stock);
                updateCartDisplay();
            }
        }

        function updatePrecio(index, precio) {
            precio = parseFloat(precio);
            if (isNaN(precio) || precio < 0) return;
            const item = cart[index];
            item.precio = precio;
            item.subtotal = item.cantidad * item.precio;
            updateCartDisplay();
        }

        function updateCartDisplay() {
            if (cart.length === 0) {
                cartItemsDiv.innerHTML = '';
                emptyCartMsg.style.display = 'block';
            } else {
                emptyCartMsg.style.display = 'none';
                cartItemsDiv.innerHTML = cart.map((item, index) => `
                    <div class="cart-item">
                        <div class="cart-item-info">
                            ${item.codigo ? '<span class="cart-item-code">' + escapeHtml(item.codigo) + '</span>' : ''}
                            <strong>${escapeHtml(item.nombre)}</strong>
                            <br>
                            <small>${item.id_inventario ? 'Stock: ' + item.stock : 'Línea manual'}</small>
                        </div>
                        <div class="cart-controls">
                            <label style="font-size: 0.75rem; color: var(--color-text-light);">Q</label>
                            <input type="number" class="cart-input" style="width: 90px;" value="${item.precio.toFixed(2)}"
                                   min="0" step="0.01" onchange="updatePrecio(${index}, this.value)">
                            <input type="number" class="cart-input" style="width: 70px;" value="${item.cantidad}"
                                   min="1" ${item.stock !== null ? 'max="' + item.stock + '"' : ''} onchange="updateQuantity(${index}, this.value)">
                            <strong style="min-width: 90px; text-align: right;">Q${item.subtotal.toFixed(2)}</strong>
                            <button type="button" class="cart-remove" onclick="removeFromCart(${index})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            }

            // El formulario de línea manual se oculta cuando la línea ya está
            // agregada al carrito (para no poder agregar dos veces).
            if (manualLineForm) {
                const tieneManual = cart.some(c => c.id_inventario === null);
                manualLineForm.style.display = tieneManual ? 'none' : '';
            }

            calculateTotals();
        }

        function calculateTotals() {
            const subtotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
            const roundedSubtotal = Math.round(subtotal * 100) / 100;

            let descuento = 0;
            let desglose = '';

            if (currentTipo === 'venta') {
                const res = calcularDescuento(roundedSubtotal, metodoPagoSelect.value);
                descuento = res.descuento;
                desglose = res.desglose;
            }

            // El cliente siempre paga el total completo (sin descuento).
            // El descuento por método de pago es informativo (solo Reportes).
            document.getElementById('subtotal').textContent = roundedSubtotal.toFixed(2);
            document.getElementById('total').textContent = roundedSubtotal.toFixed(2);
            document.getElementById('descValor').textContent = descuento.toFixed(2);
            document.getElementById('descMetodo').textContent = currentTipo === 'venta' ? metodoPagoSelect.value : '-';
            document.getElementById('descDetalle').textContent = desglose;

            const showDesc = currentTipo === 'venta' && descuento > 0;
            document.getElementById('descRow').style.display = showDesc ? 'flex' : 'none';
        }

        metodoPagoSelect.addEventListener('change', calculateTotals);

        // ============ Tipo: Cotización vs Venta ============
        function setTipo(tipo) {
            currentTipo = tipo;
            document.getElementById('btnCotizacion').classList.toggle('active', tipo === 'cotizacion');
            document.getElementById('btnVenta').classList.toggle('active', tipo === 'venta');

            const metodoGroup = document.getElementById('metodoPagoGroup');
            const cotNote = document.getElementById('cotNote');
            const submitText = document.getElementById('submitBtnText');

            if (tipo === 'cotizacion') {
                metodoGroup.style.display = 'none';
                metodoPagoSelect.required = false;
                cotNote.style.display = 'block';
                submitText.textContent = editingId ? 'Guardar Cambios' : 'Guardar y Enviar';
                document.getElementById('orderModalTitle').textContent = editingId ? 'Editar Cotización' : 'Nueva Cotización';
            } else {
                metodoGroup.style.display = '';
                metodoPagoSelect.required = true;
                cotNote.style.display = 'block';
                submitText.textContent = 'Procesar Venta';
                document.getElementById('orderModalTitle').textContent = 'Nueva Venta Directa';
            }
            calculateTotals();
        }

        // ============ Modal Nuevo / Editar ============
        function openNewOrder() {
            editingId = null;
            document.getElementById('orderForm').reset();
            document.getElementById('id_pedido').value = '';
            clienteSearchResults.classList.remove('show');
            clienteVinculoNota.classList.remove('show');
            cart = [];
            updateCartDisplay();
            setTipo('cotizacion');

            const now = new Date();
            const localDate = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            document.getElementById('orderModal').classList.add('show');
        }

        function openEditOrder(id) {
            fetch('edit_order.php?id=' + id)
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        alert(res.message || 'Error al cargar la cotización');
                        return;
                    }
                    const p = res.pedido;
                    if (p.estado_pedido !== 'Pendiente') {
                        alert('Solo se pueden editar cotizaciones pendientes');
                        return;
                    }

                    editingId = id;
                    document.getElementById('id_pedido').value = id;
                    document.getElementById('cliente_nombre').value = p.cliente_nombre || '';
                    document.getElementById('cliente_telefono').value = p.cliente_telefono || '';
                    document.getElementById('cliente_direccion').value = p.cliente_direccion || '';
                    document.getElementById('id_cliente').value = p.id_cliente || '';
                    clienteVinculoNota.classList.toggle('show', !!p.id_cliente);
                    document.getElementById('condiciones_pago').value = p.condiciones_pago || '';
                    document.getElementById('id_sucursal').value = p.id_sucursal;
                    document.getElementById('tipo_documento').value = p.tipo_documento;
                    document.getElementById('tipo_pago_estado').value = p.tipo_pago_estado;
                    document.getElementById('metodoPago').value = p.metodo_pago || 'Efectivo';

                    cart = res.productos.map(d => ({
                        id_inventario: d.id_inventario,
                        codigo: d.codigo_medicamento || d.codigo_item || d.codigo_referencia || '',
                        nombre: d.descripcion || d.nom_medicamento || '',
                        precio: parseFloat(d.precio_unitario),
                        cantidad: parseInt(d.cantidad),
                        stock: null,
                        subtotal: parseFloat(d.subtotal)
                    }));

                    // Bloquear selector de sucursal en edición
                    document.getElementById('id_sucursal').disabled = true;
                    document.getElementById('tipoToggle').style.display = 'none';

                    updateCartDisplay();
                    setTipo('cotizacion');
                    document.getElementById('orderModalTitle').textContent = 'Editar Cotización ' + (p.cotizacion_numero || '');
                    document.getElementById('submitBtnText').textContent = 'Guardar Cambios';
                    document.getElementById('orderModal').classList.add('show');
                })
                .catch(err => {
                    console.error(err);
                    alert('Error al cargar la cotización');
                });
        }

        function closeOrderModal() {
            document.getElementById('orderModal').classList.remove('show');
            document.getElementById('id_sucursal').disabled = false;
            document.getElementById('tipoToggle').style.display = '';
            editingId = null;
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }

        // ============ Envío del formulario ============
        document.getElementById('orderForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            if (cart.length === 0) {
                uiToast('Debe agregar al menos un producto', 'warning');
                return;
            }

            const formData = new FormData(this);
            const data = {
                id_pedido: formData.get('id_pedido') || null,
                es_cotizacion: currentTipo === 'cotizacion',
                id_cliente: formData.get('id_cliente') || null,
                cliente_nombre: formData.get('cliente_nombre'),
                cliente_telefono: formData.get('cliente_telefono'),
                cliente_direccion: formData.get('cliente_direccion'),
                condiciones_pago: formData.get('condiciones_pago'),
                id_sucursal: formData.get('id_sucursal'),
                tipo_documento: formData.get('tipo_documento'),
                tipo_pago_estado: formData.get('tipo_pago_estado'),
                metodo_pago: currentTipo === 'venta' ? formData.get('metodo_pago') : 'Efectivo',
                productos: cart.map(c => ({
                    id_inventario: c.id_inventario,
                    codigo: c.codigo,
                    nombre: c.nombre,
                    cantidad: c.cantidad,
                    precio: c.precio,
                    subtotal: c.subtotal
                }))
            };

            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            showLoading(data.es_cotizacion ? 'Guardando cotización...' : 'Guardando venta...');

            try {
                const response = await fetch('save_order.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    if (result.es_cotizacion && !data.id_pedido) {
                        // Nueva cotización: mostrar modal de envío
                        lastSavedCotizacion = {
                            id: result.id_pedido,
                            numero: result.cotizacion_numero,
                            cliente: data.cliente_nombre,
                            telefono: data.cliente_telefono,
                            total: result.total
                        };
                        document.getElementById('sendNumero').textContent = result.cotizacion_numero;
                        document.getElementById('sendCliente').textContent = data.cliente_nombre;
                        document.getElementById('sendTotal').textContent = 'Q' + parseFloat(result.total).toFixed(2);
                        closeOrderModal();
                        document.getElementById('sendModal').classList.add('show');
                        // Recargar al cerrar el modal de envío para que aparezca la nueva cotización
                        sendModalReloadPending = true;
                    } else if (!data.es_cotizacion) {
                        // Venta directa creada: ofrecer generación de guía
                        closeOrderModal();
                        openGuiaModal(
                            '<strong>' + escapeHtml(result.message) + '</strong><br>' +
                            'Cliente: ' + escapeHtml(data.cliente_nombre) +
                            '<br>Total: Q' + parseFloat(result.total).toFixed(2),
                            true
                        );
                    } else {
                        uiToast(result.message, 'success');
                        setTimeout(() => location.reload(), 800);
                    }
                } else {
                    uiToast('Error: ' + result.message, 'error', 5000);
                }
            } catch (error) {
                uiToast('Error al procesar la solicitud', 'error', 5000);
                console.error(error);
            } finally {
                hideLoading();
                btn.disabled = false;
            }
        });

        // ============ Modal de envío (PDF / WhatsApp) ============
        async function sendDownloadPDF() {
            if (!lastSavedCotizacion) return;
            await descargarCotizacionPDF(lastSavedCotizacion.id);
        }

        async function sendWhatsApp() {
            if (!lastSavedCotizacion) return;
            await descargarCotizacionPDF(lastSavedCotizacion.id);
            abrirWhatsApp(lastSavedCotizacion.telefono, construirMensaje(lastSavedCotizacion));
        }

        function construirMensaje(cot) {
            return `Hola ${cot.cliente}, le compartimos la cotización ${cot.numero} por Q${parseFloat(cot.total).toFixed(2)}. Adjunto el PDF con los detalles.`;
        }

        function abrirWhatsApp(telefono, mensaje) {
            let tel = String(telefono || '').replace(/\D/g, '');
            if (tel.length === 8) tel = '502' + tel;
            if (!tel) {
                uiToast('El documento no tiene teléfono registrado para WhatsApp', 'warning');
                return;
            }
            window.open(`https://wa.me/${tel}?text=${encodeURIComponent(mensaje)}`, '_blank');
        }

        // ============ PDF / WhatsApp desde la lista ============
        async function descargarCotizacionPDF(id) {
            try {
                const res = await fetch('edit_order.php?id=' + id);
                const data = await res.json();
                if (!data.success) {
                    uiToast(data.message || 'Error al cargar la cotización', 'error');
                    return;
                }
                const doc = await generarCotizacionPDF(data);
                const numero = data.pedido.cotizacion_numero || ('COT-' + data.pedido.id_pedido);
                doc.save('Cotizacion_' + numero + '.pdf');
            } catch (err) {
                console.error(err);
                uiToast('Error al generar el PDF', 'error');
            }
        }

        async function enviarCotizacionWhatsApp(id) {
            try {
                const res = await fetch('edit_order.php?id=' + id);
                const data = await res.json();
                if (!data.success) {
                    uiToast(data.message || 'Error al cargar la cotización', 'error');
                    return;
                }
                const doc = await generarCotizacionPDF(data);
                const numero = data.pedido.cotizacion_numero || ('COT-' + data.pedido.id_pedido);
                doc.save('Cotizacion_' + numero + '.pdf');

                const msg = construirMensaje({
                    cliente: data.pedido.cliente_nombre,
                    telefono: data.pedido.cliente_telefono,
                    numero: numero,
                    total: data.pedido.total
                });
                abrirWhatsApp(data.pedido.cliente_telefono, msg);
            } catch (err) {
                console.error(err);
                uiToast('Error al generar el PDF', 'error');
            }
        }

        // ============ PDF de la venta realizada ============
        let currentSalePDF = null;

        async function openSalePdfModal(id) {
            try {
                const res = await fetch('edit_order.php?id=' + id);
                const data = await res.json();
                if (!data.success) {
                    uiToast(data.message || 'Error al cargar la venta', 'error');
                    return;
                }
                currentSalePDF = data;
                const p = data.pedido;
                const numero = p.numero_venta || ('Venta #' + p.id_pedido);

                document.getElementById('salePdfNumero').textContent = numero;
                document.getElementById('salePdfCliente').textContent = p.cliente_nombre;
                document.getElementById('salePdfTotal').textContent = fmtQ(p.total);

                const estado = document.getElementById('salePdfEstado');
                estado.textContent = p.estado_pago || 'Pendiente';
                estado.className = 'badge ' + (p.estado_pago === 'Pagado' ? 'badge-pagado' : 'badge-pendiente-credito');
                estado.style.marginLeft = '0.4rem';

                document.getElementById('salePdfModal').classList.add('show');
            } catch (err) {
                console.error(err);
                uiToast('Error al cargar la venta', 'error');
            }
        }

        function closeSalePdfModal() {
            document.getElementById('salePdfModal').classList.remove('show');
        }

        function nombreArchivoVenta(p) {
            return 'Venta_' + (p.numero_venta || p.id_pedido) + '.pdf';
        }

        function mensajeVenta(p) {
            const numero = p.numero_venta || ('Venta #' + p.id_pedido);
            return `Hola ${p.cliente_nombre}, le compartimos el comprobante de su compra ${numero} por ${fmtQ(p.total)}. Adjunto el PDF con los detalles.`;
        }

        async function descargarVentaPDF() {
            if (!currentSalePDF) return;
            try {
                const doc = await generarCotizacionPDF(currentSalePDF, { tipo: 'venta' });
                doc.save(nombreArchivoVenta(currentSalePDF.pedido));
            } catch (err) {
                console.error(err);
                uiToast('Error al generar el PDF', 'error');
            }
        }

        async function imprimirVentaPDF() {
            if (!currentSalePDF) return;
            try {
                const doc = await generarCotizacionPDF(currentSalePDF, { tipo: 'venta' });
                doc.autoPrint();
                window.open(doc.output('bloburl'), '_blank');
            } catch (err) {
                console.error(err);
                uiToast('Error al generar el PDF', 'error');
            }
        }

        async function enviarVentaWhatsApp() {
            if (!currentSalePDF) return;
            try {
                const doc = await generarCotizacionPDF(currentSalePDF, { tipo: 'venta' });
                doc.save(nombreArchivoVenta(currentSalePDF.pedido));
                abrirWhatsApp(currentSalePDF.pedido.cliente_telefono, mensajeVenta(currentSalePDF.pedido));
            } catch (err) {
                console.error(err);
                uiToast('Error al generar el PDF', 'error');
            }
        }

        // ============ Procesar cotización ============
        function parseFechaUtc(s) {
            if (!s) return null;
            let v = String(s).trim();
            if (!/(Z|[+-]\d{2}:?\d{2})$/.test(v)) v = v.replace(' ', 'T') + 'Z';
            const d = new Date(v);
            return isNaN(d) ? null : d;
        }

        function openProcessModal(id) {
            const p = pedidosData.find(x => x.id_pedido == id);
            if (!p) return;

            document.getElementById('proc_id_pedido').value = id;
            document.getElementById('proc_metodo_pago').value = p.metodo_pago || 'Efectivo';
            document.getElementById('proc_tipo_documento').value = p.tipo_documento;
            document.getElementById('proc_tipo_pago_estado').value = p.tipo_pago_estado || 'Al Contado';
            const fechaProc = parseFechaUtc(p.fecha_pedido);
            document.getElementById('procInfo').innerHTML =
                `<strong>${p.cotizacion_numero || ('COT-' + p.id_pedido)}</strong> — ${escapeHtml(p.cliente_nombre)}<br>` +
                `Fecha: ${fechaProc ? fechaProc.toLocaleDateString() : ''}`;

            document.getElementById('proc_subtotal').textContent = parseFloat(p.subtotal).toFixed(2);
            updateProcessTotals();
            document.getElementById('processModal').classList.add('show');
        }

        function updateProcessTotals() {
            const id = document.getElementById('proc_id_pedido').value;
            const p = pedidosData.find(x => x.id_pedido == id);
            if (!p) return;
            const subtotal = parseFloat(p.subtotal);
            const metodo = document.getElementById('proc_metodo_pago').value;
            const res = calcularDescuento(subtotal, metodo);
            // Total a cobrar = subtotal completo; el descuento es solo para Reportes.
            document.getElementById('proc_subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('proc_descuento').textContent = res.descuento.toFixed(2);
            document.getElementById('proc_total').textContent = subtotal.toFixed(2);
            document.getElementById('proc_desglose').textContent = res.desglose;
        }

        async function confirmProcess() {
            const id = document.getElementById('proc_id_pedido').value;
            showLoading('Procesando cotización...');
            try {
                const res = await fetch('process_cotizacion.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        id_pedido: parseInt(id),
                        metodo_pago: document.getElementById('proc_metodo_pago').value,
                        tipo_documento: document.getElementById('proc_tipo_documento').value,
                        tipo_pago_estado: document.getElementById('proc_tipo_pago_estado').value
                    })
                });
                const result = await res.json();
                hideLoading();
                if (result.success) {
                    const p = pedidosData.find(x => x.id_pedido == id);
                    closeModal('processModal');
                    // Tras procesar, al recargar ir directo a la pestaña Ventas
                    try { sessionStorage.setItem('pendingTab', 'ventas'); } catch (e) {}
                    openGuiaModal(
                        '<strong>Venta procesada' + (p && p.cotizacion_numero ? ' — ' + escapeHtml(p.cotizacion_numero) : '') + '</strong><br>' +
                        'Total cobrado: Q' + parseFloat(result.total).toFixed(2) +
                        ' | Descuento (Reportes): Q' + parseFloat(result.descuento).toFixed(2),
                        true
                    );
                } else {
                    uiToast('Error: ' + result.message, 'error', 5000);
                }
            } catch (err) {
                hideLoading();
                console.error(err);
                uiToast('Error al procesar la cotización', 'error', 5000);
            }
        }

        // ============ Marcar pedido como Pagado ============
        async function marcarPagado(id, btn) {
            const ok = await uiConfirm('¿Marcar este pedido como Pagado?', {
                title: 'Confirmar pago',
                confirmText: 'Sí, marcar pagado',
                danger: false
            });
            if (!ok) return;
            const orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> ...';
            try {
                const fd = new FormData();
                fd.append('id_pedido', id);
                const res = await fetch('mark_paid.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.ok) {
                    uiToast('Pedido marcado como Pagado', 'success');
                    setTimeout(() => location.reload(), 700);
                } else {
                    uiToast('Error: ' + (data.error || 'desconocido'), 'error', 5000);
                    btn.disabled = false;
                    btn.innerHTML = orig;
                }
            } catch (e) {
                uiToast('Error de red: ' + e.message, 'error', 5000);
                btn.disabled = false;
                btn.innerHTML = orig;
            }
        }

        // ============ Cancelar cotización ============
        async function cancelarCotizacion(id) {
            const ok = await uiConfirm('¿Está seguro de cancelar esta cotización?', {
                title: 'Cancelar cotización',
                confirmText: 'Sí, cancelar'
            });
            if (!ok) return;
            try {
                const res = await fetch('cancel_order.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id_pedido: parseInt(id) })
                });
                const result = await res.json();
                if (result.success) {
                    uiToast('Cotización cancelada', 'success');
                    setTimeout(() => location.reload(), 700);
                } else {
                    uiToast('Error: ' + result.message, 'error', 5000);
                }
            } catch (err) {
                console.error(err);
                uiToast('Error al cancelar la cotización', 'error', 5000);
            }
        }

        // ============ Guía de envío (portal del transportista embebido) ============
        let guiaReloadPending = false;
        let sendModalReloadPending = false;

        function openGuiaModal(infoHtml, reloadPending) {
            guiaReloadPending = !!reloadPending;
            document.getElementById('guiaInfo').innerHTML = infoHtml || '';
            document.getElementById('guiaStep1').style.display = '';
            document.getElementById('guiaStep2').style.display = 'none';
            document.getElementById('guiaFrame').src = 'about:blank';
            document.getElementById('guiaModal').classList.add('show');
        }

        function selectGuiaService(svc) {
            const labels = { forza: 'Forza Delivery', cargo: 'Cargo Expreso' };
            document.getElementById('guiaFrameLabel').textContent = 'Portal: ' + (labels[svc] || svc);
            document.getElementById('guiaFallback').style.display = 'none';
            const openBtn = document.getElementById('guiaOpenPortalBtn');
            if (openBtn) {
                openBtn.onclick = () => {
                    const url = '../shipping/portal_proxy.php/' + svc + '/';
                    const w = window.open(url, '_blank');
                    if (!w) alert('El navegador bloqueó la ventana emergente. Permite ventanas emergentes para CLINOVA.');
                };
            }
            // Cargo Expreso: el iframe no puede completar el OIDC de Oracle
            // IDCS (third-party cookies, integración OIC que no responde bien).
            // Abrimos el portal directamente en nueva pestaña (top-level,
            // login nativo del navegador) y mostramos un mensaje en el modal
            // indicando que la guía se está creando allá.
            if (svc === 'cargo') {
                const w = window.open('https://portal.cargo-expreso.com', '_blank');
                if (!w) {
                    alert('El navegador bloqueó la ventana emergente. Permite ventanas emergentes para CLINOVA.');
                }
                document.getElementById('guiaStep1').style.display = 'none';
                document.getElementById('guiaStep2').style.display = 'none';
                document.getElementById('guiaCargoNotice').style.display = 'block';
                return;
            }
            document.getElementById('guiaCargoNotice').style.display = 'none';
            document.getElementById('guiaFrame').src = '../shipping/portal_proxy.php/' + svc + '/';
            document.getElementById('guiaStep1').style.display = 'none';
            document.getElementById('guiaStep2').style.display = 'flex';
        }

        function cargoStartIframe() {
            document.getElementById('guiaCargoNotice').style.display = 'none';
            document.getElementById('guiaStep2').style.display = 'flex';
            document.getElementById('guiaFrame').src = '../shipping/portal_proxy.php/cargo/';
        }

        // El script de auto-login del iframe nos avisa por postMessage cuando
        // falla; mostramos el fallback con "abrir en nueva pestaña" para que
        // el usuario complete el login manualmente en ese mismo navegador.
        window.addEventListener('message', function (ev) {
            const d = ev.data || {};
            if (d.type !== 'shipping-autologin') return;
            const fb = document.getElementById('guiaFallback');
            if (!fb) return;
            if (d.state === 'success') {
                fb.style.display = 'none';
                return;
            }
            let msg = 'No se pudo iniciar sesión automáticamente en el portal.';
            if (d.detail === 'creds_incorrectas') {
                msg = 'El portal rechazó las credenciales. Verifícalas en config/shipping.php.';
            } else if (d.detail === 'cuenta_bloqueada') {
                msg = 'La cuenta parece estar bloqueada o desactivada en el portal.';
            } else if (d.detail === 'timeout') {
                msg = 'El portal no mostró el formulario de inicio de sesión a tiempo.';
            }
            fb.firstElementChild.nextElementSibling.textContent = msg;
            fb.style.display = 'block';
        });

        function guiaBack() {
            document.getElementById('guiaFrame').src = 'about:blank';
            document.getElementById('guiaStep2').style.display = 'none';
            document.getElementById('guiaStep1').style.display = '';
        }

        function closeGuiaModal() {
            document.getElementById('guiaFrame').src = 'about:blank';
            closeModal('guiaModal');
            location.reload();
        }

        // ====== Modal para registrar/editar datos de la guía ======
        // La guía se crea en el portal externo (Forza/Cargo); aquí solo
        // guardamos el número y el estado para darle seguimiento.
        function openGuiaEditModal(id) {
            const v = (tabsData.ventas || []).find(x => x.id_pedido == id);
            document.getElementById('guiaEditIdPedido').value = id;
            document.getElementById('guiaEditTransportista').value = v && v.transportista ? v.transportista : '';
            document.getElementById('guiaEditNumero').value = v && v.guia_numero ? v.guia_numero : '';
            document.getElementById('guiaEditEstado').value = v && v.guia_estado ? v.guia_estado : 'Pendiente';
            document.getElementById('guiaEditObservaciones').value = v && v.guia_observaciones ? v.guia_observaciones : '';
            const msg = document.getElementById('guiaEditMsg');
            msg.style.display = 'none';
            msg.textContent = '';
            document.getElementById('guiaEditModal').classList.add('show');
        }

        // Al escribir un número de guía, el estado pasa automáticamente a "Creada";
        // al borrarlo, vuelve a "Pendiente".
        (function initGuiaAutoEstado() {
            const numInput = document.getElementById('guiaEditNumero');
            const estadoSel = document.getElementById('guiaEditEstado');
            if (!numInput || !estadoSel) return;
            numInput.addEventListener('input', function () {
                const tieneNumero = this.value.trim() !== '';
                if (tieneNumero && estadoSel.value === 'Pendiente') {
                    estadoSel.value = 'Creada';
                } else if (!tieneNumero && estadoSel.value === 'Creada') {
                    estadoSel.value = 'Pendiente';
                }
            });
        })();

        function closeGuiaEditModal() {
            document.getElementById('guiaEditModal').classList.remove('show');
        }

        function saveGuiaEdit(ev) {
            ev.preventDefault();
            const fd = new FormData(document.getElementById('guiaEditForm'));
            const btn = document.getElementById('guiaEditSaveBtn');
            const msg = document.getElementById('guiaEditMsg');
            btn.disabled = true;
            btn.textContent = 'Guardando...';
            msg.style.display = 'none';
            showLoading('Guardando guía...');
            fetch('save_guia.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    hideLoading();
                    if (data.ok) {
                        msg.style.background = '#d4edda';
                        msg.style.color = '#155724';
                        msg.textContent = '✓ Guía guardada correctamente';
                        msg.style.display = 'block';
                        setTimeout(() => location.reload(), 800);
                    } else {
                        msg.style.background = '#f8d7da';
                        msg.style.color = '#721c24';
                        msg.textContent = 'Error: ' + (data.error || 'desconocido');
                        msg.style.display = 'block';
                        btn.disabled = false;
                        btn.innerHTML = '<i class="bi bi-save"></i> Guardar';
                    }
                })
                .catch(err => {
                    hideLoading();
                    msg.style.background = '#f8d7da';
                    msg.style.color = '#721c24';
                    msg.textContent = 'Error de red: ' + err.message;
                    msg.style.display = 'block';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-save"></i> Guardar';
                });
            return false;
        }

        function openGuiaModalForVenta(id) {
            const v = (tabsData.ventas || []).find(x => x.id_pedido == id);
            const info = v
                ? '<strong>Venta #' + v.id_pedido + '</strong><br>Cliente: ' + escapeHtml(v.cliente_nombre) +
                  '<br>Total: Q' + parseFloat(v.total).toFixed(2)
                : '';
            openGuiaModal(info, false);
        }

        // ============ Utilidades ============
        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, c => (
                {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]
            ));
        }

        function closeSendModal() {
            closeModal('sendModal');
            if (sendModalReloadPending) {
                sendModalReloadPending = false;
                location.reload();
            }
        }

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (this.id === 'guiaModal') closeGuiaModal();
                    else if (this.id === 'sendModal') closeSendModal();
                    else this.classList.remove('show');
                }
            });
        });

        function printOrderTicket(pedido) {
            printTicket({
                ticketNumber: pedido.id_pedido,
                date: (() => { const d = parseFechaUtc(pedido.fecha_pedido); return d ? d.toLocaleString() : ''; })(),
                patientName: pedido.cliente_nombre,
                items: [{
                    quantity: 1,
                    description: (pedido.es_cotizacion ? 'Cotización ' + (pedido.cotizacion_numero || '') + ' - ' : 'Pedido - ') + pedido.tipo_documento,
                    total: parseFloat(pedido.total)
                }],
                total: parseFloat(pedido.total)
            });
        }

        updateStats('cotizaciones');

        if (new URLSearchParams(location.search).get('nueva_venta') === '1') {
            openNewOrder();
            setTipo('venta');
            history.replaceState(null, '', 'index.php');
        }
    </script>
    <?php include __DIR__ . '/../includes/whatsapp_float.php'; ?>
    <?php include __DIR__ . '/../includes/ui_feedback.php'; ?>
</body>
</html>
