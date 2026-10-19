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
    
    // Obtener datos del usuario
    $userRole = $_SESSION['rol'] ?? '';
    $userId = $_SESSION['user_id'] ?? 0;
    
    // Obtener sucursal del usuario (para todos los usuarios)
    $stmt = $conn->prepare("
        SELECT u.id_sucursal, s.nombre as nombre_sucursal 
        FROM usuarios u 
        LEFT JOIN sucursales s ON u.id_sucursal = s.id_sucursal 
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $userSucursal = $userData['id_sucursal'] ?? null;
    $nombreSucursalUsuario = $userData['nombre_sucursal'] ?? 'Sin Sucursal Asignada';
    
    // Consulta de inventario según el rol
    // Administrador ve todo. Los demás solo su sucursal.
    if ($userRole === 'Administrador') {
        $stmt = $conn->prepare("
            SELECT i.*, s.nombre as sucursal_nombre 
            FROM inventario i
            LEFT JOIN sucursales s ON i.id_sucursal = s.id_sucursal
            ORDER BY i.fecha_vencimiento ASC
        ");
        $stmt->execute();
    } else {
        if ($userSucursal) {
            $stmt = $conn->prepare("
                SELECT i.*, s.nombre as sucursal_nombre 
                FROM inventario i
                LEFT JOIN sucursales s ON i.id_sucursal = s.id_sucursal
                WHERE i.id_sucursal = ?
                ORDER BY i.fecha_vencimiento ASC
            ");
            $stmt->execute([$userSucursal]);
        } else {
            // Si el usuario no tiene sucursal asignada, no ve nada
            $stmt = $conn->prepare("SELECT * FROM inventario WHERE 1=0");
            $stmt->execute();
        }
    }
    
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener sucursales activas para los formularios
    $stmt = $conn->prepare("SELECT * FROM sucursales WHERE activa = 1 ORDER BY nombre");
    $stmt->execute();
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$apellido_usuario = $_SESSION['apellido'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - CLINOVA</title>
    
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
            --color-orange: #FF3D00;
            --color-orange-light: #FF6E40;
            --color-orange-dark: #E63400;
            --color-blue: #1FA8FF;
            --color-blue-light: #42B5FF;
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

        .header-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
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

        .btn-outline {
            background: var(--color-white);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }

        .btn-outline:hover {
            background: var(--color-bg);
        }

        /* Search Bar */
        .search-section {
            background: var(--color-white);
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            background: var(--color-bg);
            border-radius: 12px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .search-box:focus-within {
            border-color: var(--color-blue);
            background: var(--color-white);
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

        /* Stats Cards */
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

        /* Table Container */
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
            font-size: 0.9rem;
            color: var(--color-text);
        }

        .badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #C6F6D5;
            color: #22543D;
        }

        .badge-warning {
            background: #FEFCBF;
            color: #744210;
        }

        .badge-danger {
            background: #FED7D7;
            color: #742A2A;
        }

        .badge-info {
            background: #BEE3F8;
            color: #2C5282;
        }

        /* Action Buttons */
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

        .action-btn-edit {
            background: #BEE3F8;
            color: #2C5282;
        }

        .action-btn-edit:hover {
            background: #90CDF4;
        }

        .action-btn-delete {
            background: #FED7D7;
            color: #742A2A;
        }

        .action-btn-delete:hover {
            background: #FEB2B2;
        }

        .btn-transfer {
            background: #E9D8FD;
            color: #553C9A;
        }

        .btn-transfer:hover {
            background: #D6BCFA;
        }

        .btn-receive {
            background: #C6F6D5;
            color: #22543D;
        }

        .btn-receive:hover {
            background: #9AE6B4;
        }

        /* Modal Overlay */
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
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalIn 0.3s ease;
        }

        /* ... (rest of CSS) ... */

        /* Recepción automática al comprar — ya no se muestra el botón "Recibir" */

    <script>
        // ...
        
        function closeModal(modalId = 'medicineModal') {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');

                if (modalId === 'medicineModal') {
                    document.getElementById('medicineForm').reset();
                    document.getElementById('id_inventario').value = '';
                    document.getElementById('modalTitle').textContent = 'Agregar Medicamento';
                } else if (modalId === 'updateExpModal') {
                    document.getElementById('updateExp_id_inventario').value = '';
                    document.getElementById('updateExp_product_name').value = '';
                    document.getElementById('updateExp_fecha').value = '';
                }
            }
        }

        // Update the openModal to use the specific ID logic if needed, or keep as is since it targets medicineModal directly.
        // The issue might be that closeModal() was hardcoded to medicineModal.
        
        // Let's rewrite the closeModal function in the script section.
    </script>

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-text);
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: var(--color-bg);
            color: var(--color-text);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: var(--color-border);
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
            background: var(--color-white);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-blue);
            background: var(--color-white);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--color-border);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .btn-secondary {
            background: var(--color-bg);
            color: var(--color-text);
        }

        .btn-secondary:hover {
            background: var(--color-border);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.85rem;
            }

            thead th, tbody td {
                padding: 0.75rem 0.5rem;
            }
        }

        /* Dropdown Menu */
        .dropdown-container {
            position: relative;
            display: inline-block;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background: var(--color-white);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            min-width: 220px;
            z-index: 100;
            overflow: hidden;
        }

        .dropdown-menu.show {
            display: block;
            animation: dropdownIn 0.2s ease;
        }

        @keyframes dropdownIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            color: var(--color-text);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background: linear-gradient(90deg, rgba(255, 107, 53, 0.08), rgba(79, 195, 247, 0.08));
            color: var(--color-orange);
        }

        .dropdown-item i {
            font-size: 1.125rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }

        .empty-state i {
            font-size: 3rem;
            color: var(--color-text-light);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--color-text);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--color-text-light);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <a href="../dashboard/index.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="page-title">Inventario</h1>
                <p class="text-muted">
                    <?php if ($userRole === 'Administrador'): ?>
                        Vista Global (Administrador)
                    <?php else: ?>
                        Sucursal: <strong><?php echo htmlspecialchars($nombreSucursalUsuario); ?></strong>
                    <?php endif; ?>
                </p>
            </div>
            <div class="header-actions">
                <a href="verifier.php" class="btn btn-outline" target="_blank">
                    <i class="bi bi-upc-scan"></i>
                    <span>Verificador</span>
                </a>
                <button class="btn btn-outline" id="toggleExpiredBtn">
                    <i class="bi bi-eye"></i>
                    <span>Mostrar Vencidos</span>
                </button>
                <button class="btn btn-outline" id="togglePendingBtn">
                    <i class="bi bi-hourglass-split"></i>
                    <span>Ver Pendientes</span>
                </button>
                <div class="dropdown-container">
                    <button class="btn btn-outline" id="exportBtn">
                        <i class="bi bi-download"></i>
                        <span>Exportar</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu" id="exportMenu">
                        <a href="export_report.php?tipo=completo" class="dropdown-item">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                            Reporte Completo
                        </a>
                        <a href="export_report.php?tipo=factura" class="dropdown-item">
                            <i class="bi bi-receipt"></i>
                            Con Factura
                        </a>
                        <a href="export_report.php?tipo=consumidor" class="dropdown-item">
                            <i class="bi bi-person"></i>
                            Consumidor Final
                        </a>
                        <a href="export_report.php?tipo=nota" class="dropdown-item">
                            <i class="bi bi-clipboard-check"></i>
                            Nota de Envío
                        </a>
                    </div>
                </div>
                <button class="btn btn-primary" id="addMedicineBtn">
                    <i class="bi bi-plus-circle"></i>
                    <span>Agregar Medicamento</span>
                </button>
            </div>
        </div>

        <!-- Search -->
        <div class="search-section">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Buscar por nombre, molécula, casa farmacéutica o sucursal...">
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($userRole !== 'Administrador' && !$userSucursal): ?>
        <div style="background: #FED7D7; color: #742A2A; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; border-left: 5px solid #E53E3E;">
            <i class="bi bi-exclamation-triangle-fill"></i> <strong>Atención:</strong> 
            Tu usuario no tiene una sucursal asignada. No podrás ver ningún inventario. 
            Contacta al administrador.
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Medicamentos</div>
                <div class="stat-value"><?php echo count($medicamentos); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Por Vencer (60 días)</div>
                <div class="stat-value">
                    <?php 
                    $porVencer = 0;
                    $today = new DateTime();
                    foreach ($medicamentos as $med) {
                        if (!empty($med['fecha_vencimiento'])) {
                            $venc = new DateTime($med['fecha_vencimiento']);
                            $diff = $today->diff($venc)->days;
                            if ($diff <= 60 && $venc > $today) $porVencer++;
                        }
                    }
                    echo $porVencer;
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Stock Bajo (&lt; 50)</div>
                <div class="stat-value">
                    <?php echo count(array_filter($medicamentos, fn($m) => $m['cantidad_med'] < 50)); ?>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div class="table-wrapper">
                <?php if (count($medicamentos) > 0): ?>
                <table id="inventoryTable">
                    <thead>
                        <tr>
                            <th>ID Med</th>
                            <th>Medicamento</th>
                            <th>Molécula</th>
                            <th>Presentación</th>
                            <th>Casa Farmacéutica</th>
                            <th>Cantidad</th>
                            <?php if ($userRole === 'Administrador'): ?>
                            <th>Sucursal</th>
                            <?php endif; ?>
                            <th>Factura</th>
                            <th>F. Adquisición</th>
                            <th>F. Vencimiento</th>
                            <th>Estado</th>
                            <th>Recepción</th>
                            <?php if (in_array($userRole, ['Administrador', 'Farmacia', 'Mayoreo'])): ?>
                            <th>Acciones</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <tbody>
                        <?php foreach ($medicamentos as $med): 
                            $isDateMissing = empty($med['fecha_vencimiento']);
                            $isExpired = false;
                            $isCloseToExpiry = false;

                            if (!$isDateMissing) {
                                $expiry = new DateTime($med['fecha_vencimiento']);
                                $today = new DateTime();
                                $diff = $today->diff($expiry);
                                $isExpired = $expiry < $today;
                                $isCloseToExpiry = !$isExpired && $diff->days <= 30;
                            }
                        ?>
                        <tr data-expired="<?php echo $isExpired ? '1' : '0'; ?>" data-status="<?php echo $med['estado_ingreso'] ?? 'Ingresado'; ?>">
                            <td><span class="badge badge-info"><?php echo htmlspecialchars(!empty($med['codigo_medicamento']) ? $med['codigo_medicamento'] : (!empty($med['codigo_referencia']) ? $med['codigo_referencia'] : 'N/A')); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($med['nom_medicamento']); ?></strong></td>
                            <td><?php echo htmlspecialchars($med['mol_medicamento']); ?></td>
                            <td><?php echo htmlspecialchars($med['presentacion_med']); ?></td>
                            <td><?php echo htmlspecialchars($med['casa_farmaceutica']); ?></td>
                            <td>
                                <span class="badge <?php 
                                    if ($med['cantidad_med'] == 0) echo 'badge-danger';
                                    elseif ($med['cantidad_med'] < 50) echo 'badge-warning';
                                    else echo 'badge-success';
                                ?>">
                                    <?php echo $med['cantidad_med']; ?>
                                </span>
                            </td>
                            <?php if ($userRole === 'Administrador'): ?>
                            <td><?php echo htmlspecialchars($med['sucursal_nombre'] ?? 'Sin sucursal'); ?></td>
                            <?php endif; ?>
                            <td>
                                <span class="badge badge-info">
                                    <?php echo htmlspecialchars($med['tipo_factura']); ?>
                                </span>
                                <?php if ($med['numero_factura']): ?>
                                <br><small><?php echo htmlspecialchars($med['numero_factura']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($med['fecha_adquisicion']) ? date('d/m/Y', strtotime($med['fecha_adquisicion'])) : '-'; ?></td>
                            <td>
                                <?php if ($isDateMissing): ?>
                                    <span class="badge badge-warning">Pendiente</span>
                                    <button class="btn btn-sm btn-link p-0 ms-1" onclick="openUpdateDateModal(<?php echo $med['id_inventario']; ?>)" title="Agregar Fecha">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                <?php else: ?>
                                    <?php echo date('d/m/Y', strtotime($med['fecha_vencimiento'])); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isDateMissing): ?>
                                <span class="badge badge-secondary">Sin Fecha</span>
                                <?php elseif ($isExpired): ?>
                                <span class="badge badge-danger">Vencido</span>
                                <?php elseif ($isCloseToExpiry): ?>
                                <span class="badge badge-warning">Por vencer</span>
                                <?php else: ?>
                                <span class="badge badge-success">Vigente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                // Logic for Reception Column
                                $status = $med['estado_ingreso'] ?? 'Ingresado';
                                if ($status === 'Pendiente'): ?>
                                    <span class="badge badge-warning">Pendiente</span>
                                <?php elseif ($status === 'En Traslado'): ?>
                                    <span class="badge badge-info">En Traslado</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Ingresado</span>
                                <?php endif; ?>
                            </td>
                            <?php if (in_array($userRole, ['Administrador', 'Farmacia', 'Mayoreo'])): ?>
                            <td>
                                <button class="action-btn action-btn-update-exp" onclick="openUpdateExpModal(<?php echo $med['id_inventario']; ?>, '<?php echo htmlspecialchars(addslashes($med['nom_medicamento'])); ?>', '<?php echo $med['fecha_vencimiento'] ?? ''; ?>')" title="Actualizar fecha de vencimiento">
                                    <i class="bi bi-calendar-event"></i>
                                </button>
                                <button class="action-btn action-btn-edit edit-btn" 
                                    data-id="<?php echo $med['id_inventario']; ?>"
                                    data-med='<?php echo htmlspecialchars(json_encode($med)); ?>'
                                    title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="action-btn btn-transfer" 
                                    onclick="openTransferModal(<?php echo $med['id_inventario']; ?>, '<?php echo htmlspecialchars($med['nom_medicamento']); ?>', <?php echo $med['cantidad_med']; ?>)"
                                    title="Trasladar">
                                    <i class="bi bi-arrow-left-right"></i>
                                </button>
                                <?php if ($userRole === 'Administrador'): ?>
                                <button class="action-btn action-btn-delete delete-btn" 
                                    data-id="<?php echo $med['id_inventario']; ?>"
                                    title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h3>No hay medicamentos registrados</h3>
                    <p>Comienza agregando tu primer medicamento al inventario</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Update Expiration Date Modal -->
    <div class="modal-overlay" id="updateExpModal">
        <div class="modal" style="max-width:420px;">
            <div class="modal-header">
                <h2 class="modal-title"><i class="bi bi-calendar-event"></i> Actualizar Vencimiento</h2>
                <button class="modal-close" onclick="closeModal('updateExpModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form action="update_expiration.php" method="POST" onsubmit="showLoading('Actualizando vencimiento...')">
                <input type="hidden" name="id_inventario" id="updateExp_id_inventario">
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom:0.75rem;">
                        <label class="form-label">Producto</label>
                        <input type="text" id="updateExp_product_name" class="form-control" readonly style="background: var(--color-bg);">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nueva fecha de vencimiento</label>
                        <input type="date" name="fecha_vencimiento" id="updateExp_fecha" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('updateExpModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i>
                        Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add/Edit Medicine Modal -->
    <div class="modal-overlay" id="medicineModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Agregar Medicamento</h2>
                <button class="modal-close" onclick="closeModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="medicineForm" method="POST" action="save_medicine.php" onsubmit="showLoading('Guardando medicamento...')">
                <input type="hidden" name="id_inventario" id="id_inventario">
                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label class="form-label" style="display:flex; justify-content:space-between;">
                            Código de Barras 
                            <small class="text-muted"><i class="bi bi-upc-scan"></i> Escanear ahora</small>
                        </label>
                        <input type="text" name="codigo_barras" id="codigo_barras" class="form-control" placeholder="Escanear o escribir código..." autofocus>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label" style="display:flex; justify-content:space-between;">
                            Código de Referencia
                            <small class="text-muted">Código manual (único)</small>
                        </label>
                        <input type="text" name="codigo_referencia" id="codigo_referencia" class="form-control" placeholder="Ej: FA-001" maxlength="20">
                        <small class="text-muted">Déjelo vacío si no aplica. Se auto-completa al elegir un medicamento existente.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Nombre Comercial *</label>
                        <input type="text" name="nom_medicamento" id="nom_medicamento" class="form-control" list="medSuggest" autocomplete="off" required>
                        <datalist id="medSuggest"></datalist>
                        <small class="text-muted">Si el medicamento ya tiene historial, elija la sugerencia para auto-completar los códigos.</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Molécula *</label>
                            <input type="text" name="mol_medicamento" id="mol_medicamento" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Presentación *</label>
                            <input type="text" name="presentacion_med" id="presentacion_med" class="form-control" placeholder="Ej: Tabletas 500mg" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Casa Farmacéutica *</label>
                        <input type="text" name="casa_farmaceutica" id="casa_farmaceutica" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Cantidad *</label>
                            <input type="number" name="cantidad_med" id="cantidad_med" class="form-control" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Sucursal *</label>
                            <select name="id_sucursal" id="id_sucursal" class="form-control" required>
                                <option value="">Seleccionar...</option>
                                <?php foreach ($sucursales as $suc): ?>
                                <option value="<?php echo $suc['id_sucursal']; ?>">
                                    <?php echo htmlspecialchars($suc['nombre']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de Factura *</label>
                        <select name="tipo_factura" id="tipo_factura" class="form-control" required onchange="toggleFacturaNumber()">
                            <option value="Consumidor Final">Consumidor Final</option>
                            <option value="Con Factura">Con Factura</option>
                            <option value="Nota de Envío">Nota de Envío</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="numeroFacturaGroup" style="display: none;">
                        <label class="form-label">Número de Factura/Nota</label>
                        <input type="text" name="numero_factura" id="numero_factura" class="form-control" placeholder="Ej: FAC-2025-001">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Fecha Adquisición *</label>
                            <input type="date" name="fecha_adquisicion" id="fecha_adquisicion" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Fecha Vencimiento *</label>
                            <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Date Modal -->
    <div class="modal-overlay" id="updateDateModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Actualizar Fecha de Vencimiento</h2>
                <button class="modal-close" onclick="closeModal('updateDateModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form action="update_expiration.php" method="POST" onsubmit="showLoading('Actualizando fecha...')">
                <input type="hidden" name="id_inventario" id="update_date_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Nueva Fecha de Vencimiento *</label>
                        <input type="date" name="fecha_vencimiento" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('updateDateModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    </div>

    <!-- Transfer Modal -->
    <div class="modal-overlay" id="transferModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Trasladar Medicamento</h2>
                <button class="modal-close" onclick="closeModal('transferModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form action="transfer_product.php" method="POST" onsubmit="showLoading('Procesando traslado...')">
                <input type="hidden" name="id_inventario" id="transfer_id">
                <div class="modal-body">
                    <div class="alert alert-info" style="background: #ebf8ff; border-left: 4px solid #1FA8FF; padding: 10px; font-size: 0.9rem;">
                        <i class="bi bi-info-circle"></i> El estado cambiará a <strong>"En Traslado"</strong> y deberá ser recibido en la sucursal de destino.
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Producto</label>
                        <input type="text" id="transfer_product_name" class="form-control" readonly style="background: #f7fafc;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Cantidad a Trasladar</label>
                        <input type="number" name="cantidad_transferir" id="transfer_quantity" class="form-control" min="1" required>
                        <small class="text-muted">Disponible: <span id="transfer_max_display">0</span></small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sucursal Destino *</label>
                        <select name="id_sucursal_destino" class="form-control form-select" required>
                            <option value="">-- Seleccionar Destino --</option>
                            <?php foreach ($sucursales as $sucursal): ?>
                                <option value="<?php echo $sucursal['id_sucursal']; ?>">
                                    <?php echo htmlspecialchars($sucursal['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('transferModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-arrow-left-right"></i>
                        Confirmar Traslado
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openUpdateDateModal(id) {
            document.getElementById('update_date_id').value = id;
            document.getElementById('updateDateModal').classList.add('show');
        }
        // Modal functions
        function openModal(isEdit = false) {
            document.getElementById('medicineModal').classList.add('show');
            document.getElementById('modalTitle').textContent = isEdit ? 'Editar Medicamento' : 'Agregar Medicamento';
        }

        function closeModal(modalId = null) {
            const id = modalId || 'medicineModal';
            const modal = document.getElementById(id);

            if (modal) {
                modal.classList.remove('show');

                if (id === 'medicineModal') {
                    document.getElementById('medicineForm').reset();
                    document.getElementById('id_inventario').value = '';
                } else if (id === 'updateExpModal') {
                    document.getElementById('updateExp_id_inventario').value = '';
                    document.getElementById('updateExp_product_name').value = '';
                    document.getElementById('updateExp_fecha').value = '';
                }
            }
        }

        function toggleFacturaNumber() {
            const tipo = document.getElementById('tipo_factura').value;
            const group = document.getElementById('numeroFacturaGroup');
            const input = document.getElementById('numero_factura');
            
            if (tipo === 'Con Factura' || tipo === 'Nota de Envío') {
                group.style.display = 'block';
                input.required = true;
            } else {
                group.style.display = 'none';
                input.required = false;
                input.value = '';
            }
        }

        // Dropdown menu control
        const exportBtn = document.getElementById('exportBtn');
        const exportMenu = document.getElementById('exportMenu');

        exportBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            exportMenu.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', () => {
            exportMenu.classList.remove('show');
        });

        // Event listeners
        document.getElementById('addMedicineBtn').addEventListener('click', () => {
            openModal(false);
            // Set today's date as default
            // Set date to local time
            const now = new Date();
            const localDate = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            document.getElementById('fecha_adquisicion').value = localDate;
        });

        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const data = JSON.parse(this.dataset.med);
                document.getElementById('id_inventario').value = data.id_inventario;
                document.getElementById('codigo_barras').value = data.codigo_barras || '';
                document.getElementById('codigo_referencia').value = data.codigo_referencia || '';
                document.getElementById('nom_medicamento').value = data.nom_medicamento;
                document.getElementById('mol_medicamento').value = data.mol_medicamento;
                document.getElementById('presentacion_med').value = data.presentacion_med;
                document.getElementById('casa_farmaceutica').value = data.casa_farmaceutica;
                document.getElementById('cantidad_med').value = data.cantidad_med;
                document.getElementById('id_sucursal').value = data.id_sucursal;
                document.getElementById('tipo_factura').value = data.tipo_factura;
                document.getElementById('numero_factura').value = data.numero_factura || '';
                document.getElementById('fecha_adquisicion').value = data.fecha_adquisicion;
                document.getElementById('fecha_vencimiento').value = data.fecha_vencimiento;
                toggleFacturaNumber();
                openModal(true);
            });
        });

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const ok = await uiConfirm('¿Está seguro de eliminar este medicamento?', {
                    title: 'Eliminar medicamento',
                    confirmText: 'Sí, eliminar'
                });
                if (ok) {
                    showLoading('Eliminando medicamento...');
                    window.location.href = 'delete_medicine.php?id=' + this.dataset.id;
                }
            });
        });

        // Autocompletar códigos por historial de medicamentos
        const medSuggestCache = {};
        const nomMedInput = document.getElementById('nom_medicamento');
        const datalistMed = document.getElementById('medSuggest');
        let medSuggestTimer = null;

        nomMedInput.addEventListener('input', function() {
            const q = this.value.trim();
            if (q.length < 3) { datalistMed.innerHTML = ''; return; }
            if (medSuggestCache[q]) { renderMedSuggest(medSuggestCache[q]); return; }
            clearTimeout(medSuggestTimer);
            medSuggestTimer = setTimeout(() => fetchMedSuggest(q), 250);
        });

        nomMedInput.addEventListener('change', function() {
            const match = findSuggestMatch(this.value);
            if (match) {
                if (match.codigo_referencia && !document.getElementById('codigo_referencia').value) {
                    document.getElementById('codigo_referencia').value = match.codigo_referencia;
                }
                if (match.codigo_barras && !document.getElementById('codigo_barras').value) {
                    document.getElementById('codigo_barras').value = match.codigo_barras;
                }
            }
        });

        function findSuggestMatch(name) {
            for (const key in medSuggestCache) {
                const list = medSuggestCache[key];
                if (Array.isArray(list)) {
                    const found = list.find(i => i.nom_medicamento === name);
                    if (found) return found;
                }
            }
            return null;
        }

        function fetchMedSuggest(q) {
            fetch('search_by_name.php?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(rows => {
                    medSuggestCache[q] = rows;
                    renderMedSuggest(rows);
                })
                .catch(() => {});
        }

        function renderMedSuggest(rows) {
            datalistMed.innerHTML = rows.map(r =>
                '<option value="' + escapeHtml(r.nom_medicamento) + '">' +
                escapeHtml((r.codigo_referencia || r.codigo_barras || '') + ' - ' + (r.mol_medicamento || '')) +
                '</option>'
            ).join('');
        }

        function escapeHtml(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, c => (
                {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]
            ));
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchText = this.value.toLowerCase();
            const rows = document.querySelectorAll('#inventoryTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });

        // Toggle Pending
        const togglePendingBtn = document.getElementById('togglePendingBtn');
        let showPendingOnly = false;

        togglePendingBtn.addEventListener('click', function() {
            showPendingOnly = !showPendingOnly;
            const rows = document.querySelectorAll('#inventoryTable tbody tr');
            
            rows.forEach(row => {
                const status = row.getAttribute('data-status');
                if (showPendingOnly) {
                    if (status === 'Pendiente' || status === 'En Traslado') {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                } else {
                    row.style.display = '';
                }
            });
            
            this.classList.toggle('btn-primary');
            this.classList.toggle('btn-outline');
            this.innerHTML = showPendingOnly 
                ? '<i class="bi bi-list-ul"></i><span>Ver Todos</span>'
                : '<i class="bi bi-hourglass-split"></i><span>Ver Pendientes</span>';
        });

        // Toggle Expired
        let showExpired = true; // Keep this declaration here or move it if it's meant to be global for other functions
        const toggleExpiredBtn = document.getElementById('toggleExpiredBtn');
        toggleExpiredBtn.addEventListener('click', function() {
            showExpired = !showExpired;
            const rows = document.querySelectorAll('#inventoryTable tbody tr[data-expired="1"]');
            
            rows.forEach(row => {
                row.style.display = showExpired ? '' : 'none';
            });
            
            this.innerHTML = showExpired 
                ? '<i class="bi bi-eye-slash"></i><span>Ocultar Vencidos</span>'
                : '<i class="bi bi-eye"></i><span>Mostrar Vencidos</span>';
        });

        function openTransferModal(id, name, maxQty) {
            document.getElementById('transfer_id').value = id;
            document.getElementById('transfer_product_name').value = name;
            
            const qtyInput = document.getElementById('transfer_quantity');
            qtyInput.value = maxQty; // Default to all
            qtyInput.max = maxQty;
            document.getElementById('transfer_max_display').textContent = maxQty;
            
            document.getElementById('transferModal').classList.add('show');
        }

        function openUpdateExpModal(id, name, currentDate) {
            document.getElementById('updateExp_id_inventario').value = id;
            document.getElementById('updateExp_product_name').value = name;
            document.getElementById('updateExp_fecha').value = currentDate || '';
            document.getElementById('updateExpModal').classList.add('show');
        }

        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });
    </script>
    <?php include __DIR__ . '/../includes/whatsapp_float.php'; ?>
    <?php include __DIR__ . '/../includes/ui_feedback.php'; ?>
</body>
</html>