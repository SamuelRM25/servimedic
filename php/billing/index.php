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
    
    // Obtener todos los cobros (exámenes + procedimientos)
    // Union de ambas tablas para mostrar todos los cobros
    $stmt = $conn->prepare("
        (SELECT 
            e.id_examen as id,
            'Examen' as tipo,
            e.nombre_paciente,
            e.tipo_paciente,
            e.tipo_pago,
            e.monto,
            e.metodo_pago,
            e.numero_ticket,
            e.fecha_examen as fecha,
            e.examenes_realizados as concepto,
            s.nombre as sucursal_nombre
        FROM examenes e
        LEFT JOIN sucursales s ON e.id_sucursal = s.id_sucursal)
        UNION ALL
        (SELECT 
            p.id_procedimiento as id,
            'Procedimiento' as tipo,
            p.nombre_paciente,
            p.tipo_paciente,
            p.tipo_pago,
            p.monto,
            p.metodo_pago,
            p.numero_ticket,
            p.fecha_procedimiento as fecha,
            p.procedimiento_realizado as concepto,
            s.nombre as sucursal_nombre
        FROM procedimientos_menores p
        LEFT JOIN sucursales s ON p.id_sucursal = s.id_sucursal)
        UNION ALL
        (SELECT 
            o.id_orden as id,
            o.tipo_cobro as tipo,
            CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente,
            'Privado' as tipo_paciente,
            'Privado' as tipo_pago,
            o.monto,
            'Efectivo' as metodo_pago,
            LPAD(o.id_orden, 6, '0') as numero_ticket,
            o.fecha_pago as fecha,
            o.descripcion as concepto,
            'Central' as sucursal_nombre
        FROM ordenes_cobro o
        JOIN pacientes p ON o.id_paciente = p.id_paciente
        WHERE o.estado = 'Pagado')
        ORDER BY fecha DESC
        LIMIT 100
    ");
    $stmt->execute();
    $cobros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas
    $totalCobros = count($cobros);
    $totalMonto = array_sum(array_column($cobros, 'monto'));
    $cobrosPrivados = count(array_filter($cobros, fn($c) => $c['tipo_pago'] === 'Privado'));
    $cobrosSeguro = count(array_filter($cobros, fn($c) => $c['tipo_pago'] !== 'Privado'));
    
} catch (Exception $e) {
    $cobros = [];
    $totalCobros = 0;
    $totalMonto = 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cobros - Servimedic</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --color-orange: #FF6B35;
            --color-blue: #4FC3F7;
            --color-white: #FFFFFF;
            --color-text: #2D3748;
            --color-text-light: #718096;
            --color-bg: #F7FAFC;
            --color-border: #E2E8F0;
            --font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        body { font-family: var(--font-family); background: var(--color-bg); color: var(--color-text); }
        .container { max-width: 1800px; margin: 0 auto; padding: 2rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .page-title-section { display: flex; align-items: center; gap: 1rem; }
        .back-btn { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: var(--color-white); border: 1px solid var(--color-border); border-radius: 10px; color: var(--color-text); text-decoration: none; transition: all 0.3s ease; }
        .back-btn:hover { background: linear-gradient(135deg, var(--color-orange), var(--color-blue)); color: white; border-color: transparent; }
        .page-title { font-size: 1.75rem; font-weight: 700; color: var(--color-text); }
        .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: 10px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; border: none; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, var(--color-orange), #E85A2B); color: white; }
        .btn-secondary { background: var(--color-text-light); color: white; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: var(--color-white); padding: 1.25rem; border-radius: 16px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); }
        .stat-label { font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem; }
        .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--color-text); }
        .filter-card { background: var(--color-white); border-radius: 16px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); padding: 1.5rem; margin-bottom: 2rem; }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group { margin-bottom: 0; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: var(--color-text); }
        .form-control { width: 100%; padding: 0.75rem 1rem; border: 2px solid var(--color-border); border-radius: 10px; font-size: 0.9rem; font-family: var(--font-family); transition: all 0.3s ease; }
        .form-control:focus { outline: none; border-color: var(--color-blue); }
        .table-container { background: var(--color-white); border-radius: 16px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); overflow: hidden; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: linear-gradient(135deg, var(--color-orange), var(--color-blue)); }
        thead th { padding: 1rem; text-align: left; font-size: 0.875rem; font-weight: 600; color: white; white-space: nowrap; }
        tbody tr { border-bottom: 1px solid var(--color-border); transition: background 0.2s ease; }
        tbody tr:hover { background: var(--color-bg); }
        tbody td { padding: 1rem; font-size: 0.875rem; color: var(--color-text); }
        .badge { display: inline-block; padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
        .badge-examen { background: #BEE3F8; color: #2C5282; }
        .badge-procedimiento { background: #D6BCFA; color: #44337A; }
        .badge-privado { background: #C6F6D5; color: #22543D; }
        .badge-seguro { background: #FEFCBF; color: #744210; }
        .badge-eps { background: #FED7D7; color: #742A2A; }
        .search-box { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.25rem; background: var(--color-white); border-radius: 12px; border: 2px solid var(--color-border); transition: all 0.3s ease; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); margin-bottom: 1.5rem; }
        .search-box:focus-within { border-color: var(--color-blue); box-shadow: 0 4px 12px rgba(79, 195, 247, 0.2); }
        .search-box i { color: var(--color-text-light); font-size: 1.125rem; }
        .search-box input { flex: 1; border: none; background: transparent; outline: none; font-size: 0.95rem; color: var(--color-text); font-family: var(--font-family); }
        .search-box input::placeholder { color: var(--color-text-light); }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title-section">
                <a href="../dashboard/index.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="page-title">Gestión de Cobros</h1>
            </div>
            <?php include '../../includes/clock.php'; ?>
            <div style="display: flex; gap: 0.75rem;">
                <a href="reportes.php" class="btn btn-secondary">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    Reportes
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Cobros</div>
                <div class="stat-value"><?php echo $totalCobros; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pagos Privados</div>
                <div class="stat-value"><?php echo $cobrosPrivados; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Seguros/EPS</div>
                <div class="stat-value"><?php echo $cobrosSeguro; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Cobrado</div>
                <div class="stat-value">Q<?php echo number_format($totalMonto, 2); ?></div>
            </div>
        </div>

        <!-- Search -->
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="searchInput" placeholder="Buscar por paciente, ticket, concepto...">
        </div>

        <!-- Table -->
        <div class="table-container">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--color-border);">
                <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600;">Historial de Cobros</h3>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Paciente</th>
                            <th>Concepto</th>
                            <th>Tipo Pago</th>
                            <th>Método</th>
                            <th>Monto</th>
                            <th>Sucursal</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cobrosTable">
                        <?php foreach ($cobros as $cobro): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($cobro['numero_ticket']); ?></strong></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($cobro['fecha'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower(str_replace(' ', '', $cobro['tipo'])); ?>">
                                    <?php echo $cobro['tipo']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($cobro['nombre_paciente']); ?></td>
                            <td><small><?php echo htmlspecialchars($cobro['concepto']); ?></small></td>
                            <td>
                                <?php 
                                $pagoClass = 'badge-privado';
                                if ($cobro['tipo_pago'] === 'Seguro Manual') $pagoClass = 'badge-seguro';
                                if ($cobro['tipo_pago'] === 'EPS') $pagoClass = 'badge-eps';
                                ?>
                                <span class="badge <?php echo $pagoClass; ?>">
                                    <?php echo $cobro['tipo_pago']; ?>
                                </span>
                            </td>
                            <td><?php echo $cobro['metodo_pago']; ?></td>
                            <td><strong>Q<?php echo number_format($cobro['monto'], 2); ?></strong></td>
                            <td><small><?php echo htmlspecialchars($cobro['sucursal_nombre']); ?></small></td>
                            <td>
                                <button onclick='printBillingTicket(<?php echo json_encode($cobro); ?>)' 
                                   style="background: none; border: none; color: var(--color-blue); cursor: pointer; text-decoration: none; padding: 0;">
                                    <i class="bi bi-printer"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../../assets/js/ticket_printer.js"></script>
    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const tableRows = document.querySelectorAll('#cobrosTable tr');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();

            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        function printBillingTicket(cobro) {
            printTicket({
                ticketNumber: cobro.numero_ticket || '---',
                date: new Date(cobro.fecha).toLocaleString(),
                patientName: cobro.nombre_paciente,
                items: [{
                    quantity: 1,
                    description: cobro.concepto,
                    total: parseFloat(cobro.monto)
                }],
                total: parseFloat(cobro.monto)
            });
        }
    </script>
</body>
</html>