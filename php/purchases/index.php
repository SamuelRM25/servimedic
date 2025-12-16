<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

date_default_timezone_set('America/Guatemala');

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener compras
    $stmt = $conn->prepare("
        SELECT c.*, u.nombre as usuario_nombre, u.apellido as usuario_apellido
        FROM compras c
        LEFT JOIN usuarios u ON c.id_usuario = u.id
        ORDER BY c.fecha_compra DESC, c.id_compra DESC
    ");
    $stmt->execute();
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$userRole = $_SESSION['rol'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras - Servimedic</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-orange: #FF6B35;
            --color-orange-light: #FF8C61;
            --color-orange-dark: #E85A2B;
            --color-blue: #4FC3F7;
            --color-blue-light: #81D4FA;
            --color-blue-dark: #0288D1;
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

        /* Header */
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

        /* Chevron rotation */
        .group-header[aria-expanded="true"] .bi-chevron-down {
            transform: rotate(180deg);
        }
        .bi-chevron-down {
            transition: transform 0.3s ease;
        }

        /* Nested table styling */
        .nested-table-header th {
            background-color: #e9ecef !important;
            color: #495057 !important;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Bootstrap Collapse Minimal Styles */
        .collapse:not(.show) {
            display: none;
        }
        .collapsing {
            height: 0;
            overflow: hidden;
            transition: height 0.35s ease;
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
            background: linear-gradient(135deg, var(--color-orange), var(--color-orange-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        /* Stats */
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

        /* Table */
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
        .badge-consumidor { background: #FED7D7; color: #742A2A; }
        
        .badge-contado { background: #C6F6D5; color: #22543D; }
        .badge-credito30 { background: #FEFCBF; color: #744210; }
        .badge-credito60 { background: #FED7D7; color: #742A2A; }
        
        .badge-pendiente { background: #FED7D7; color: #742A2A; }
        .badge-abonado { background: #FEFCBF; color: #744210; }
        .badge-pagado { background: #C6F6D5; color: #22543D; }
        .badge-entregado { background: #BEE3F8; color: #2C5282; }
        .badge-ingresado { background: #D6BCFA; color: #44337A; }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            margin: 0 0.25rem;
        }

        .action-btn-pay { background: #C6F6D5; color: #22543D; }
        .action-btn-pay:hover { background: #9AE6B4; }
        
        .action-btn-edit { background: #FEFCBF; color: #744210; }
        .action-btn-edit:hover { background: #FAF089; }

        .action-btn-delete { background: #FED7D7; color: #742A2A; }
        .action-btn-delete:hover { background: #FEB2B2; }

        /* Modal */
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
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
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

        @media (max-width: 768px) {
            .form-row, .form-row-3 {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1rem;
            }
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
            font-size: 0.95rem;
        }

        .total-row.main {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--color-orange);
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 2px solid var(--color-border);
        }

        /* Search Section */
        .search-section {
            margin-bottom: 1.5rem;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            background: var(--color-white);
            border-radius: 12px;
            border: 2px solid var(--color-border);
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .search-box:focus-within {
            border-color: var(--color-blue);
            box-shadow: 0 4px 12px rgba(79, 195, 247, 0.2);
        }

        .search-box i {
            color: var(--color-text-light);
            font-size: 1.125rem;
        }

        .search-box input {
            flex: 1;
            border: none;
            background: transparent;
            outline: none;
            font-size: 0.95rem;
            color: var(--color-text);
            font-family: var(--font-family);
        }

        .search-box input::placeholder {
            color: var(--color-text-light);
        }

        .no-results {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--color-text-light);
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        /* Batch Modal Styles */
        .modal.modal-wide {
            max-width: 1200px;
        }

        .items-table-container {
            margin-top: 1.5rem;
            border: 1px solid var(--color-border);
            border-radius: 10px;
            overflow: hidden;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            background: var(--color-bg);
            padding: 0.75rem;
            text-align: left;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--color-text-light);
            border-bottom: 1px solid var(--color-border);
        }

        .items-table td {
            padding: 0.5rem;
            border-bottom: 1px solid var(--color-border);
        }

        .items-table input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .items-table input:focus {
            outline: none;
            border-color: var(--color-blue);
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-remove {
            background: #FED7D7;
            color: #742A2A;
        }

        .btn-remove:hover {
            background: #FEB2B2;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--color-orange);
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="page-title-section">
                <a href="../dashboard/index.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="page-title">Gestión de Compras</h1>
            </div>
            <?php include '../../includes/clock.php'; ?>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i>
                Nueva Compra
            </button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Compras</div>
                <div class="stat-value"><?php echo count($compras); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pendientes de Pago</div>
                <div class="stat-value">
                    <?php echo count(array_filter($compras, fn($c) => $c['saldo'] > 0)); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Adeudado</div>
                <div class="stat-value">
                    Q<?php echo number_format(array_sum(array_column($compras, 'saldo')), 2); ?>
                </div>
            </div>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Buscar compra...">
            </div>
            <div class="form-check form-switch ms-3">
                <input class="form-check-input" type="checkbox" id="hidePaidToggle">
                <label class="form-check-label" for="hidePaidToggle">Ocultar Pagados</label>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Proveedor / Items</th>
                            <th>Fecha</th>
                            <th>Tipo Doc.</th>
                            <th>Total Compra</th>
                            <th>Saldo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Group purchases by codigo_referencia
                        $groupedPurchases = [];
                        foreach ($compras as $compra) {
                            $ref = $compra['codigo_referencia'] ?? 'SIN-REF-' . $compra['id_compra'];
                            if (!isset($groupedPurchases[$ref])) {
                                $groupedPurchases[$ref] = [
                                    'ref' => $ref,
                                    'fecha' => $compra['fecha_compra'],
                                    'proveedor' => $compra['casa_farmaceutica'], // Assuming main supplier
                                    'factura' => $compra['numero_factura'] ?? '',
                                    'tipo_factura' => $compra['tipo_factura'],
                                    'total' => 0,
                                    'saldo' => 0,
                                    'estado' => $compra['estado'], // Status of the batch? Or mixed?
                                    'items' => []
                                ];
                            }
                            $groupedPurchases[$ref]['items'][] = $compra;
                            $groupedPurchases[$ref]['total'] += $compra['total'];
                            $groupedPurchases[$ref]['saldo'] += $compra['saldo'];
                            
                            // Update invoice number if any item has it (from receiving)
                            if (!empty($compra['numero_factura'])) {
                                $groupedPurchases[$ref]['factura'] = $compra['numero_factura'];
                            }
                        }
                        
                        foreach ($groupedPurchases as $ref => $group): 
                            $uniqueId = 'collapse-' . $ref;
                        ?>
                        <!-- Summary Row -->
                        <tr class="group-header" data-bs-toggle="collapse" data-bs-target="#<?php echo $uniqueId; ?>" aria-expanded="false" style="cursor: pointer; background-color: #fff; border-bottom: 1px solid #dee2e6;">
                            <td>
                                <button class="btn btn-sm btn-link text-decoration-none p-0 me-2">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <span class="badge badge-info"><?php echo htmlspecialchars($ref); ?></span>
                                <?php if($group['factura']): ?>
                                    <br><small class="text-muted ms-4">Fact: <?php echo htmlspecialchars($group['factura']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($group['proveedor']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo count($group['items']); ?> items</small>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($group['fecha'])); ?></td>
                            <td>
                                <span class="badge badge-secondary"><?php echo htmlspecialchars($group['tipo_factura']); ?></span>
                            </td>
                            <td><strong>Q<?php echo number_format($group['total'], 2); ?></strong></td>
                            <td>
                                <strong style="color: <?php echo $group['saldo'] > 0 ? 'var(--color-danger)' : 'var(--color-success)'; ?>">
                                    Q<?php echo number_format($group['saldo'], 2); ?>
                                </strong>
                            </td>
                            <td>
                                <?php 
                                // Simple status logic for group
                                $statusClass = 'badge-pendiente';
                                if ($group['saldo'] <= 0) $statusClass = 'badge-pagado';
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $group['saldo'] <= 0 ? 'Pagado' : 'Pendiente'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" onclick="event.stopPropagation()">
                                        Acciones
                                    </button>
                                    <ul class="dropdown-menu">
                                        <?php if ($group['saldo'] > 0): ?>
                                        <li><a class="dropdown-item" href="#" onclick="openPaymentModal(<?php echo $group['items'][0]['id_compra']; ?>, <?php echo $group['saldo']; ?>)">Abonar</a></li>
                                        <?php else: ?>
                                        <li><span class="dropdown-item-text text-muted">Pagado</span></li>
                                        <?php endif; ?>
                                        <!-- Delete All removed as requested -->
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <!-- Details Row -->
                        <tr>
                            <td colspan="8" class="p-0" style="border: none;">
                                <div id="<?php echo $uniqueId; ?>" class="collapse">
                                    <div class="p-4 bg-light">
                                        <div class="card shadow-sm border-0">
                                            <div class="card-body p-0">
                                                <table class="table table-hover mb-0">
                                                    <thead class="nested-table-header">
                                                        <tr>
                                                            <th class="ps-4">Medicamento</th>
                                                            <th>Presentación</th>
                                                            <th>Cant.</th>
                                                            <th>P. Unit</th>
                                                            <th>Subtotal</th>
                                                            <th>Estado</th>
                                                            <th>Factura</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($group['items'] as $item): ?>
                                                        <tr>
                                                            <td class="ps-4"><?php echo htmlspecialchars($item['nombre_medicamento']); ?></td>
                                                            <td><?php echo htmlspecialchars($item['presentacion']); ?></td>
                                                            <td><?php echo $item['cantidad']; ?></td>
                                                            <td>Q<?php echo number_format($item['precio_unitario'], 2); ?></td>
                                                            <td>Q<?php echo number_format($item['total'], 2); ?></td>
                                                            <td>
                                                                <span class="badge <?php echo $item['estado'] === 'Ingresado' ? 'badge-ingresado' : 'badge-pendiente'; ?>">
                                                                    <?php echo $item['estado']; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($item['numero_factura'] ?? '-'); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="noResults" class="no-results" style="display: none;">
                    <i class="bi bi-search"></i>
                    <p>No se encontraron compras que coincidan con la búsqueda</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Purchase Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal modal-wide">
            <div class="modal-header">
                <h2 class="modal-title">Nueva Compra (Lote)</h2>
                <button class="modal-close" onclick="closeModal('addModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="batchPurchaseForm" onsubmit="submitBatchPurchase(event)">
                <div class="modal-body">
                    <!-- Master Section -->
                    <div class="section-title">Datos Generales</div>
                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">Fecha de Compra *</label>
                            <input type="date" name="fecha_compra" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tipo de Factura *</label>
                            <select name="tipo_factura" class="form-control" required>
                                <option value="Factura">Factura</option>
                                <option value="Nota de Envío">Nota de Envío</option>
                                <option value="Consumidor Final">Consumidor Final</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Número de Factura/Envío</label>
                            <input type="text" name="numero_factura" class="form-control" placeholder="Opcional">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tipo de Pago *</label>
                            <select name="tipo_pago" class="form-control" required>
                                <option value="Al Contado">Al Contado</option>
                                <option value="Crédito 30 días">Crédito 30 días</option>
                                <option value="Crédito 60 días">Crédito 60 días</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Observaciones Generales</label>
                            <input type="text" name="observaciones" class="form-control">
                        </div>
                    </div>

                    <!-- Items Section -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                        <div class="section-title" style="margin-bottom: 0;">Medicamentos</div>
                        <button type="button" class="btn btn-primary" onclick="addPurchaseRow()" style="padding: 0.4rem 1rem; font-size: 0.85rem;">
                            <i class="bi bi-plus-lg"></i> Agregar Fila
                        </button>
                    </div>

                    <div class="items-table-container">
                        <table class="items-table" id="purchaseItemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 15%">Medicamento</th>
                                    <th style="width: 10%">Molécula</th>
                                    <th style="width: 10%">Presentación</th>
                                    <th style="width: 10%">Casa Farm.</th>
                                    <th style="width: 8%">Lote</th>
                                    <th style="width: 8%">Vence</th>
                                    <th style="width: 7%">Cant.</th>
                                    <th style="width: 10%">P. Compra</th>
                                    <th style="width: 10%">P. Venta</th>
                                    <th style="width: 10%">Subtotal</th>
                                    <th style="width: 2%"></th>
                                </tr>
                            </thead>
                            <tbody id="purchaseItemsBody">
                                <!-- Rows will be added here -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="totals-section" style="margin-top: 1rem; text-align: right;">
                        <div class="total-row main" style="justify-content: flex-end; gap: 2rem;">
                            <span>Total Compra:</span>
                            <span id="totalCompraDisplay">Q0.00</span>
                        </div>
                    </div>
                </div>
                <div style="padding: 1.5rem; border-top: 1px solid var(--color-border);">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="bi bi-save"></i>
                        Guardar Compra Completa
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal-overlay" id="paymentModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Registrar Abono</h2>
                <button class="modal-close" onclick="closeModal('paymentModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form action="add_payment.php" method="POST">
                <input type="hidden" name="id_compra" id="payment_id_compra">
                <div class="modal-body">
                    <div class="totals-section" style="margin-bottom: 1.5rem;">
                        <div class="total-row">
                            <span>Saldo Pendiente:</span>
                            <strong style="color: var(--color-danger);">Q<span id="saldo_pendiente">0.00</span></strong>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Monto a Abonar *</label>
                            <input type="number" name="monto" id="payment_amount" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Fecha de Abono *</label>
                            <input type="date" name="fecha_abono" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Forma de Pago *</label>
                            <select name="forma_pago" class="form-control" required>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Tarjeta Débito">Tarjeta Débito</option>
                                <option value="Tarjeta Crédito">Tarjeta Crédito</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Referencia</label>
                            <input type="text" name="referencia" class="form-control" placeholder="Núm. transacción, cheque, etc.">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div style="padding: 1.5rem; border-top: 1px solid var(--color-border);">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="bi bi-cash-coin"></i>
                        Registrar Abono
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
            
            // Set date to local time (Guatemala)
            const now = new Date();
            const localDate = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            document.querySelector('[name="fecha_compra"]').value = localDate;
            
            // Clear items and add one empty row
            document.getElementById('purchaseItemsBody').innerHTML = '';
            addPurchaseRow();
            updateTotalCompra();
        }

        function addPurchaseRow() {
            const tbody = document.getElementById('purchaseItemsBody');
            const rowCount = tbody.children.length;
            const row = document.createElement('tr');
            
            row.innerHTML = `
                <td><input type="text" name="items[${rowCount}][nombre_medicamento]" required placeholder="Nombre"></td>
                <td><input type="text" name="items[${rowCount}][molecula]" required placeholder="Molécula"></td>
                <td><input type="text" name="items[${rowCount}][presentacion]" required placeholder="Presentación"></td>
                <td><input type="text" name="items[${rowCount}][casa_farmaceutica]" required placeholder="Casa Farm."></td>
                <td><input type="text" name="items[${rowCount}][lote]" placeholder="Lote"></td>
                <td><input type="date" name="items[${rowCount}][fecha_vencimiento]"></td>
                <td><input type="number" name="items[${rowCount}][cantidad]" min="1" value="1" required onchange="updateRowTotal(this)"></td>
                <td><input type="number" name="items[${rowCount}][precio_unitario]" step="0.01" min="0" required onchange="updateRowTotal(this)" placeholder="0.00"></td>
                <td><input type="number" name="items[${rowCount}][precio_venta]" step="0.01" min="0" required placeholder="0.00"></td>
                <td style="text-align: right; font-weight: 600;">Q<span class="row-total">0.00</span></td>
                <td>
                    <button type="button" class="btn-icon btn-remove" onclick="removePurchaseRow(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(row);
        }

        function removePurchaseRow(btn) {
            const tbody = document.getElementById('purchaseItemsBody');
            if (tbody.children.length > 1) {
                btn.closest('tr').remove();
                updateTotalCompra();
            } else {
                alert('Debe haber al menos un medicamento en la compra.');
            }
        }

        function updateRowTotal(input) {
            const row = input.closest('tr');
            const cantidad = parseFloat(row.querySelector('[name*="[cantidad]"]').value) || 0;
            const precio = parseFloat(row.querySelector('[name*="[precio_unitario]"]').value) || 0;
            const total = cantidad * precio;
            
            row.querySelector('.row-total').textContent = total.toFixed(2);
            updateTotalCompra();
        }

        function updateTotalCompra() {
            let total = 0;
            document.querySelectorAll('.row-total').forEach(span => {
                total += parseFloat(span.textContent);
            });
            document.getElementById('totalCompraDisplay').textContent = 'Q' + total.toFixed(2);
        }

        async function submitBatchPurchase(e) {
            e.preventDefault();
            
            if (!confirm('¿Está seguro de registrar esta compra?')) return;
            
            const form = e.target;
            const formData = new FormData(form);
            
            try {
                const response = await fetch('save_purchase_batch.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Ocurrió un error al procesar la solicitud.');
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function openPaymentModal(idCompra, saldo) {
            document.getElementById('payment_id_compra').value = idCompra;
            document.getElementById('saldo_pendiente').textContent = parseFloat(saldo).toFixed(2);
            document.getElementById('payment_amount').max = saldo;
            document.getElementById('payment_amount').max = saldo;
            
            // Set date to local time
            const now = new Date();
            const localDate = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            document.querySelector('[name="fecha_abono"]').value = localDate;
            document.getElementById('paymentModal').classList.add('show');
        }

        function editPurchase(id) {
            window.location.href = 'edit_purchase.php?id=' + id;
        }

        function deletePurchase(id) {
            if (confirm('¿Está seguro de eliminar esta compra?')) {
                window.location.href = 'delete_purchase.php?id=' + id;
            }
        }

        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });

        // Filter functionality (Search + Hide Paid)
        const searchInput = document.getElementById('searchInput');
        const hidePaidToggle = document.getElementById('hidePaidToggle');

        function filterPurchases() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const hidePaid = hidePaidToggle.checked;
            let visibleCount = 0;
            
            const summaryRows = document.querySelectorAll('tr.group-header');

            summaryRows.forEach(row => {
                const summaryText = row.textContent.toLowerCase();
                const targetId = row.getAttribute('data-bs-target');
                const detailsRow = document.querySelector(targetId).closest('tr');
                const detailsText = detailsRow.textContent.toLowerCase();
                
                // Check payment status
                // We can check the badge text in the summary row
                const statusBadge = row.querySelector('.badge-pagado, .badge-pendiente');
                const isPaid = statusBadge && statusBadge.textContent.trim() === 'Pagado';

                // Determine visibility
                let isVisible = true;

                // 1. Search filter
                if (searchTerm.length > 0) {
                    const matches = summaryText.includes(searchTerm) || detailsText.includes(searchTerm);
                    if (!matches) isVisible = false;
                }

                // 2. Hide Paid filter
                if (hidePaid && isPaid) {
                    isVisible = false;
                }

                // Apply visibility
                if (isVisible) {
                    row.style.display = '';
                    if (row.nextElementSibling) {
                        row.nextElementSibling.style.display = '';
                    }
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                    if (row.nextElementSibling) {
                        row.nextElementSibling.style.display = 'none';
                    }
                }
            });

            // Show/hide no results message
            const noResults = document.getElementById('noResults');
            if (visibleCount === 0) {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }

        searchInput.addEventListener('input', filterPurchases);
        hidePaidToggle.addEventListener('change', filterPurchases);
        
        // Initialize Bootstrap collapse manually if needed, but data-bs attributes should work.
        // Ensure dropdowns don't trigger collapse
        document.querySelectorAll('.dropdown-toggle').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>