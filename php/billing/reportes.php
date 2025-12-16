<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$tipo = $_GET['tipo'] ?? '';
$tipo_pago = $_GET['tipo_pago'] ?? '';

// Construir query base
$whereClauses = ["DATE(fecha) BETWEEN ? AND ?"];
$params = [$fecha_inicio, $fecha_fin];

if ($tipo) {
    $whereClauses[] = "tipo = ?";
    $params[] = $tipo;
}

if ($tipo_pago) {
    $whereClauses[] = "tipo_pago = ?";
    $params[] = $tipo_pago;
}

$whereSQL = implode(' AND ', $whereClauses);

// Query combinado
$sql = "
    SELECT * FROM (
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
            s.nombre as sucursal_nombre,
            u.nombre as usuario_nombre
        FROM examenes e
        LEFT JOIN sucursales s ON e.id_sucursal = s.id_sucursal
        LEFT JOIN usuarios u ON e.id_usuario = u.id)
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
            s.nombre as sucursal_nombre,
            u.nombre as usuario_nombre
        FROM procedimientos_menores p
        LEFT JOIN sucursales s ON p.id_sucursal = s.id_sucursal
        LEFT JOIN usuarios u ON p.id_usuario = u.id)
    ) AS cobros_union
    WHERE $whereSQL
    ORDER BY fecha DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$cobros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$totalCobros = count($cobros);
$totalMonto = array_sum(array_column($cobros, 'monto'));
$examenes = count(array_filter($cobros, fn($c) => $c['tipo'] === 'Examen'));
$procedimientos = count(array_filter($cobros, fn($c) => $c['tipo'] === 'Procedimiento'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Cobros - Servimedic</title>
    
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
        @media print {
            .no-print { display: none; }
            body { background: white; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header no-print">
            <div class="page-title-section">
                <a href="index.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="page-title">Reportes de Cobros</h1>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button class="btn btn-secondary" onclick="window.print()">
                    <i class="bi bi-printer"></i>
                    Imprimir
                </button>
                <button class="btn btn-primary" onclick="exportToCSV()">
                    <i class="bi bi-file-earmark-spreadsheet"></i>
                    Exportar CSV
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card no-print">
            <form method="GET">
                <div class="filter-row">
                    <div class="form-group">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?php echo $fecha_inicio; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?php echo $fecha_fin; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo</label>
                        <select name="tipo" class="form-control">
                            <option value="">Todos</option>
                            <option value="Examen" <?php echo $tipo === 'Examen' ? 'selected' : ''; ?>>Exámenes</option>
                            <option value="Procedimiento" <?php echo $tipo === 'Procedimiento' ? 'selected' : ''; ?>>Procedimientos</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo Pago</label>
                        <select name="tipo_pago" class="form-control">
                            <option value="">Todos</option>
                            <option value="Privado" <?php echo $tipo_pago === 'Privado' ? 'selected' : ''; ?>>Privado</option>
                            <option value="Seguro Manual" <?php echo $tipo_pago === 'Seguro Manual' ? 'selected' : ''; ?>>Seguro Manual</option>
                            <option value="EPS" <?php echo $tipo_pago === 'EPS' ? 'selected' : ''; ?>>EPS</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i>
                    Filtrar
                </button>
            </form>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Cobros</div>
                <div class="stat-value"><?php echo $totalCobros; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Exámenes</div>
                <div class="stat-value"><?php echo $examenes; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Procedimientos</div>
                <div class="stat-value"><?php echo $procedimientos; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Cobrado</div>
                <div class="stat-value">Q<?php echo number_format($totalMonto, 2); ?></div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--color-border);">
                <h3 style="margin: 0;">Reporte: <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></h3>
            </div>
            <div class="table-wrapper">
                <table id="reportTable">
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cobros as $c): ?>
                        <tr>
                            <td><?php echo $c['numero_ticket']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($c['fecha'])); ?></td>
                            <td><?php echo $c['tipo']; ?></td>
                            <td><?php echo htmlspecialchars($c['nombre_paciente']); ?></td>
                            <td><?php echo htmlspecialchars($c['concepto']); ?></td>
                            <td><?php echo $c['tipo_pago']; ?></td>
                            <td><?php echo $c['metodo_pago']; ?></td>
                            <td>Q<?php echo number_format($c['monto'], 2); ?></td>
                            <td><?php echo $c['sucursal_nombre']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="7" style="text-align: right;">TOTAL:</td>
                            <td>Q<?php echo number_format($totalMonto, 2); ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function exportToCSV() {
            const table = document.getElementById('reportTable');
            let csv = [];
            
            for (let row of table.rows) {
                let rowData = [];
                for (let cell of row.cells) {
                    rowData.push('"' + cell.innerText.replace(/"/g, '""') + '"');
                }
                csv.push(rowData.join(','));
            }
            
            const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'reporte_cobros_<?php echo date("Y-m-d"); ?>.csv';
            a.click();
        }
    </script>
</body>
</html>
