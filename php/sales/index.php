<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener ventas con información detallada
    $stmt = $conn->prepare("
        SELECT v.*, 
               u.nombre as usuario_nombre, 
               u.apellido as usuario_apellido,
               s.nombre as sucursal_nombre,
               p.cliente_nombre as pedido_cliente
        FROM ventas v
        LEFT JOIN usuarios u ON v.id_usuario = u.id
        LEFT JOIN sucursales s ON v.id_sucursal = s.id_sucursal
        LEFT JOIN pedidos p ON v.id_pedido = p.id_pedido
        ORDER BY v.fecha_venta DESC
    ");
    $stmt->execute();
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - Servimedic</title>
    
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
            --color-orange: #FF6B35;
            --color-blue: #4FC3F7;
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
        .badge-recibo { background: #FED7D7; color: #742A2A; }
        
        .badge-contado { background: #C6F6D5; color: #22543D; }
        .badge-credito { background: #FEFCBF; color: #744210; }
        
        .badge-personal { background: #D6BCFA; color: #44337A; }
        .badge-pedido { background: #BEE3F8; color: #2C5282; }
        .badge-mostrador { background: #C6F6D5; color: #22543D; }
        
        .badge-completada { background: #C6F6D5; color: #22543D; }
        .badge-pendiente { background: #FEFCBF; color: #744210; }
        .badge-cancelada { background: #FED7D7; color: #742A2A; }

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

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            .header-actions {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }
        }

        /* Header Actions */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Premium Buttons */
        .btn-premium {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }

        .btn-premium:active {
            transform: translateY(0);
        }

        .btn-report {
            background: linear-gradient(135deg, #718096, #4A5568);
        }

        .btn-action {
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
        }
        
        .btn-premium i {
            font-size: 1.1rem;
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
                <h1 class="page-title">Registro de Ventas</h1>
            </div>
            <div class="header-actions">
                <button class="btn-premium btn-report" onclick="window.open('print_daily_sales.php', '_blank', 'width=800,height=800')">
                    <i class="bi bi-printer-fill"></i> Reporte Diario
                </button>
                <button class="btn-premium btn-action" onclick="openNewSaleModal()">
                    <i class="bi bi-plus-lg"></i> Nueva Venta
                </button>
                <?php include '../../includes/clock.php'; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Ventas</div>
                <div class="stat-value"><?php echo count($ventas); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Ventas Hoy</div>
                <div class="stat-value">
                    <?php echo count(array_filter($ventas, fn($v) => date('Y-m-d', strtotime($v['fecha_venta'])) === date('Y-m-d'))); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Vendido</div>
                <div class="stat-value">
                    Q<?php echo number_format(array_sum(array_column($ventas, 'total_final')), 2); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Ventas a Crédito</div>
                <div class="stat-value">
                    <?php echo count(array_filter($ventas, fn($v) => $v['es_credito'] == 1)); ?>
                </div>
            </div>
        </div>

        <!-- Search -->
        <div class="search-section">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Buscar por cliente, NIT o usuario...">
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>NIT</th>
                            <th>Tipo Documento</th>
                            <th>Tipo Venta</th>
                            <th>Pago</th>
                            <th>Subtotal</th>
                            <th>Descuento</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody id="ventasTable">
                        <?php foreach ($ventas as $venta): ?>
                        <tr>
                            <td><?php echo $venta['id_venta']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($venta['cliente_nombre']); ?></strong>
                                <?php if ($venta['venta_personal']): ?>
                                <br><small style="color: var(--color-text-light);">Personal</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($venta['cliente_nit'] ?? 'CF'); ?></td>
                            <td>
                                <?php 
                                $docClass = 'badge-recibo';
                                if ($venta['tipo_documento'] === 'Factura') $docClass = 'badge-factura';
                                if ($venta['tipo_documento'] === 'Nota de Envío') $docClass = 'badge-nota';
                                ?>
                                <span class="badge <?php echo $docClass; ?>">
                                    <?php echo $venta['tipo_documento'] ?? 'Recibo de Venta'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($venta['venta_personal']): ?>
                                <span class="badge badge-personal">Venta Personal</span>
                                <?php elseif ($venta['id_pedido']): ?>
                                <span class="badge badge-pedido">Desde Pedido</span>
                                <?php else: ?>
                                <span class="badge badge-mostrador">Mostrador</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $venta['es_credito'] ? 'badge-credito' : 'badge-contado'; ?>">
                                    <?php echo $venta['es_credito'] ? 'Crédito' : 'Contado'; ?>
                                </span>
                            </td>
                            <td>Q<?php echo number_format($venta['total'], 2); ?></td>
                            <td><?php echo $venta['descuento'] > 0 ? 'Q' . number_format($venta['descuento'], 2) : '-'; ?></td>
                            <td><strong>Q<?php echo number_format($venta['total_final'], 2); ?></strong></td>
                            <td>
                                <?php 
                                $statusClass = 'badge-completada';
                                if ($venta['estado'] === 'Pendiente') $statusClass = 'badge-pendiente';
                                if ($venta['estado'] === 'Cancelada') $statusClass = 'badge-cancelada';
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $venta['estado']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($venta['usuario_nombre'] . ' ' . $venta['usuario_apellido']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="noResults" class="no-results" style="display: none;">
                    <i class="bi bi-search"></i>
                    <p>No se encontraron ventas que coincidan con la búsqueda</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const tableRows = document.querySelectorAll('#ventasTable tr');
        const noResults = document.getElementById('noResults');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            let visibleCount = 0;

            tableRows.forEach(row => {
                const cliente = row.cells[2].textContent.toLowerCase();
                const nit = row.cells[3].textContent.toLowerCase();
                const usuario = row.cells[11].textContent.toLowerCase();

                const matches = cliente.includes(searchTerm) ||
                               nit.includes(searchTerm) ||
                               usuario.includes(searchTerm);

                if (matches) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (visibleCount === 0 && searchTerm.length > 0) {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        });
    </script>
    <style>
        @media print {
            .no-print, .btn-new, .modal, .sidebar, .page-header button {
                display: none !important;
            }
            .sidebar { width: 0; }
            .main-content { margin-left: 0; padding: 0; }
            body { background: white; }
            .card { box-shadow: none; border: none; }
            
            /* Add print header */
            .page-header::after {
                content: "Reporte de Ventas - " attr(data-date);
                display: block;
                font-size: 20px;
                font-weight: bold;
                text-align: center;
                width: 100%;
                margin-bottom: 20px;
            }
        }
    </style>
    <script>
        // Set date for print header
        document.querySelector('.page-header').setAttribute('data-date', new Date().toLocaleDateString());
    </script>
</body>
</html>