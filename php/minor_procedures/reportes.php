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
$tipo_paciente = $_GET['tipo_paciente'] ?? '';
$tipo_pago = $_GET['tipo_pago'] ?? '';

// Construir query
$sql = "
    SELECT p.*, u.nombre as usuario_nombre, s.nombre as sucursal_nombre
    FROM procedimientos_menores p
    LEFT JOIN usuarios u ON p.id_usuario = u.id
    LEFT JOIN sucursales s ON p.id_sucursal = s.id_sucursal
    WHERE DATE(p.fecha_procedimiento) BETWEEN ? AND ?
";

$params = [$fecha_inicio, $fecha_fin];

if ($tipo_paciente) {
    $sql .= " AND p.tipo_paciente = ?";
    $params[] = $tipo_paciente;
}

if ($tipo_pago) {
    $sql .= " AND p.tipo_pago = ?";
    $params[] = $tipo_pago;
}

$sql .= " ORDER BY p.fecha_procedimiento DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$procedimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$totalProcedimientos = count($procedimientos);
$totalCobrado = array_sum(array_column($procedimientos, 'monto'));
$privados = count(array_filter($procedimientos, fn($p) => $p['tipo_paciente'] === 'Privado'));
$eps = count(array_filter($procedimientos, fn($p) => $p['tipo_paciente'] === 'EPS'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Procedimientos - Servimedic</title>
    
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
                <h1 class="page-title">Reportes de Procedimientos Menores</h1>
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
                        <label class="form-label">Tipo Paciente</label>
                        <select name="tipo_paciente" class="form-control">
                            <option value="">Todos</option>
                            <option value="Privado" <?php echo $tipo_paciente === 'Privado' ? 'selected' : ''; ?>>Privado</option>
                            <option value="EPS" <?php echo $tipo_paciente === 'EPS' ? 'selected' : ''; ?>>EPS</option>
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
                <div class="stat-label">Total Procedimientos</div>
                <div class="stat-value"><?php echo $totalProcedimientos; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pacientes Privados</div>
                <div class="stat-value"><?php echo $privados; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pacientes EPS</div>
                <div class="stat-value"><?php echo $eps; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Cobrado</div>
                <div class="stat-value">Q<?php echo number_format($totalCobrado, 2); ?></div>
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
                            <th>Paciente</th>
                            <th>Tipo Pac.</th>
                            <th>Procedimiento</th>
                            <th>Tipo Pago</th>
                            <th>Método</th>
                            <th>Monto</th>
                            <th>Sucursal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedimientos as $p): ?>
                        <tr>
                            <td><?php echo $p['numero_ticket']; ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($p['fecha_procedimiento'])); ?></td>
                            <td><?php echo htmlspecialchars($p['nombre_paciente']); ?></td>
                            <td><?php echo $p['tipo_paciente']; ?></td>
                            <td><?php echo htmlspecialchars($p['procedimiento_realizado']); ?></td>
                            <td><?php echo $p['tipo_pago']; ?></td>
                            <td><?php echo $p['metodo_pago']; ?></td>
                            <td>Q<?php echo number_format($p['monto'], 2); ?></td>
                            <td><?php echo $p['sucursal_nombre']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="7" style="text-align: right;">TOTAL:</td>
                            <td>Q<?php echo number_format($totalCobrado, 2); ?></td>
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
            a.download = 'reporte_procedimientos_<?php echo date("Y-m-d"); ?>.csv';
            a.click();
        }
    </script>
</body>
</html>
