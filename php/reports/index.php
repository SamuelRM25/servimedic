<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$database = new Database();
$conn = $database->getConnection();

// Obtener datos del usuario actual
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
$userRol = $userData['rol'];

// Filtros
$tipo_reporte = $_GET['tipo_reporte'] ?? 'diario'; // diario, turno1, turno2, personalizado
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$id_usuario = $_GET['id_usuario'] ?? '';

// Lógica de Turnos y Fechas
$datetime_inicio = $fecha_inicio . ' 00:00:00';
$datetime_fin = $fecha_fin . ' 23:59:59';

if ($tipo_reporte === 'turno1') {
    // Turno 1: 08:00 - 17:00 del mismo día
    $datetime_inicio = $fecha_inicio . ' 08:00:00';
    $datetime_fin = $fecha_inicio . ' 17:00:00';
} elseif ($tipo_reporte === 'turno2') {
    // Turno 2: 17:00 del día inicio - 08:00 del día siguiente
    $datetime_inicio = $fecha_inicio . ' 17:00:00';
    $datetime_fin = date('Y-m-d', strtotime($fecha_inicio . ' +1 day')) . ' 08:00:00';
}

// Obtener usuarios para filtro
$stmtUsuarios = $conn->prepare("SELECT id, nombre, apellido, rol FROM usuarios ORDER BY nombre");
$stmtUsuarios->execute();
$usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// 1. CONSULTAS Y SERVICIOS (Desde Cobros Pagados)
// ============================================
// Usamos ordenes_cobro con estado 'Pagado' para reflejar ingresos reales
$whereIngresos = ["estado = 'Pagado'", "fecha_pago BETWEEN ? AND ?"];
$paramsIngresos = [$datetime_inicio, $datetime_fin];

if ($id_usuario) {
    // Si filtramos por usuario, debemos unir con examenes/proc o confiar en id_medico de la orden
    $whereIngresos[] = "id_medico = ?";
    $paramsIngresos[] = $id_usuario;
} elseif ($userRol === 'Doctor') {
    $whereIngresos[] = "id_medico = ?";
    $paramsIngresos[] = $_SESSION['user_id'];
}

$whereIngresosSQL = implode(' AND ', $whereIngresos);

$sqlIngresos = "
    SELECT o.*, p.tipo_paciente
    FROM ordenes_cobro o
    LEFT JOIN pacientes p ON o.id_paciente = p.id_paciente
    WHERE $whereIngresosSQL
";
$stmtIngresos = $conn->prepare($sqlIngresos);
$stmtIngresos->execute($paramsIngresos);
$ingresosServicios = $stmtIngresos->fetchAll(PDO::FETCH_ASSOC);

$totalConsultas = count($ingresosServicios); // Total de transacciones
$montoConsultas = array_sum(array_column($ingresosServicios, 'monto'));

// Breakdown by Type
$breakdown = [
    'EPS' => 0,
    'IGS' => 0,
    'MAWDY' => 0,
    'Privado' => 0
];

foreach ($ingresosServicios as $ingreso) {
    // Normalize type (some might be lowercase or have different casing in DB)
    $tipo = ucfirst(strtolower($ingreso['tipo_paciente'] ?? 'Privado'));
    if ($tipo === 'Epss') $tipo = 'EPS'; // Handle typo if exists
    
    if (isset($breakdown[$tipo])) {
        $breakdown[$tipo] += $ingreso['monto'];
    } else {
        // Fallback for unexpected types
        $breakdown['Privado'] += $ingreso['monto'];
    }
}

// ============================================
// 2. VENTAS DE MEDICAMENTOS
// ============================================
$whereVentas = ["v.fecha_venta BETWEEN ? AND ?"];
$paramsVentas = [$datetime_inicio, $datetime_fin];

if ($id_usuario) {
    $whereVentas[] = "v.id_usuario = ?";
    $paramsVentas[] = $id_usuario;
}

$whereVentasSQL = implode(' AND ', $whereVentas);

$sqlVentas = "
    SELECT v.*, SUM(dv.subtotal) as total_venta,
           GROUP_CONCAT(fp.tipo_pago) as metodos_pago,
           GROUP_CONCAT(fp.monto) as montos_pago
    FROM ventas v
    LEFT JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
    LEFT JOIN formas_pago fp ON v.id_venta = fp.id_venta
    WHERE $whereVentasSQL
    GROUP BY v.id_venta
";
$stmtVentas = $conn->prepare($sqlVentas);
$stmtVentas->execute($paramsVentas);
$ventas = $stmtVentas->fetchAll(PDO::FETCH_ASSOC);

$totalVentas = count($ventas);
$montoVentasTotal = 0;
$montoEfectivo = 0;
$montoTransferencia = 0;
$montoTarjeta = 0;
$montoTarjetaReal = 0;

foreach ($ventas as $venta) {
    if ($venta['metodos_pago']) {
        $metodos = explode(',', $venta['metodos_pago']);
        $montos = explode(',', $venta['montos_pago']);
        
        foreach ($metodos as $index => $metodo) {
            $monto = floatval($montos[$index] ?? 0);
            $montoVentasTotal += $monto;
            
            if ($metodo === 'Efectivo') {
                $montoEfectivo += $monto;
            } elseif (strpos($metodo, 'Transferencia') !== false) {
                $montoTransferencia += $monto;
            } elseif (strpos($metodo, 'Tarjeta') !== false) {
                $montoTarjeta += $monto;
                $montoTarjetaReal += $monto * 0.938; // Descuento 6.2%
            }
        }
    }
}

$montoVentasReal = $montoEfectivo + $montoTransferencia + $montoTarjetaReal;
$descuentoTarjeta = $montoTarjeta - $montoTarjetaReal;

// ============================================
// 3. CONTABILIDAD (EGRESOS)
// ============================================
// Compras realizadas en el periodo
$whereCompras = ["fecha_compra BETWEEN ? AND ?"];
$paramsCompras = [$datetime_inicio, $datetime_fin];
$sqlCompras = "SELECT SUM(total) as total_compras FROM compras WHERE " . implode(' AND ', $whereCompras);
$stmtCompras = $conn->prepare($sqlCompras);
$stmtCompras->execute($paramsCompras);
$totalCompras = $stmtCompras->fetchColumn() ?: 0;

// Caja Chica (Gastos)
// Ensure table exists
$conn->exec("CREATE TABLE IF NOT EXISTS `caja_chica` (
  `id_gasto` int(11) NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(255) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `tipo_movimiento` enum('Ingreso','Egreso') NOT NULL DEFAULT 'Egreso',
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(11) NOT NULL,
  PRIMARY KEY (`id_gasto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$whereGastos = ["fecha_movimiento BETWEEN ? AND ? AND tipo_movimiento = 'Egreso'"];
$paramsGastos = [$datetime_inicio, $datetime_fin];
$sqlGastos = "SELECT SUM(monto) as total_gastos FROM caja_chica WHERE " . implode(' AND ', $whereGastos);
$stmtGastos = $conn->prepare($sqlGastos);
$stmtGastos->execute($paramsGastos);
$totalGastos = $stmtGastos->fetchColumn() ?: 0;

$totalEgresos = $totalCompras + $totalGastos;
$totalIngresos = $montoConsultas + $montoVentasReal;
$balanceNeto = $totalIngresos - $totalEgresos;

// ============================================
// 4. BIG DATA (ANÁLISIS DE MEDICAMENTOS)
// ============================================
$topMedicamentos = [];
$lowMedicamentos = [];

if ($userRol === 'Administrador' || $userRol === 'Farmacia') {
    // Top 5 Más Vendidos
    $sqlTop = "
        SELECT i.nom_medicamento, SUM(dv.cantidad) as total_vendido, SUM(dv.subtotal) as total_ingreso
        FROM detalle_ventas dv
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        JOIN ventas v ON dv.id_venta = v.id_venta
        WHERE v.fecha_venta BETWEEN ? AND ?
        GROUP BY i.id_inventario
        ORDER BY total_vendido DESC
        LIMIT 5
    ";
    $stmtTop = $conn->prepare($sqlTop);
    $stmtTop->execute([$datetime_inicio, $datetime_fin]);
    $topMedicamentos = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

    // Top 5 Menos Vendidos (De los que se han vendido al menos 1 vez, para simplificar)
    // O mejor, items con stock pero sin ventas recientes (más complejo, haremos simple por ahora)
    $sqlLow = "
        SELECT i.nom_medicamento, SUM(dv.cantidad) as total_vendido
        FROM detalle_ventas dv
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        JOIN ventas v ON dv.id_venta = v.id_venta
        WHERE v.fecha_venta BETWEEN ? AND ?
        GROUP BY i.id_inventario
        ORDER BY total_vendido ASC
        LIMIT 5
    ";
    $stmtLow = $conn->prepare($sqlLow);
    $stmtLow->execute([$datetime_inicio, $datetime_fin]);
    $lowMedicamentos = $stmtLow->fetchAll(PDO::FETCH_ASSOC);
}

// Top Servicios (Procedimientos/Examenes más rentables) ESTE REPORTE ES NUEVO PARA BIG DATA
$topServicios = [];
if ($userRol === 'Administrador' || $userRol === 'Farmacia') {
    $sqlTopServ = "
        SELECT tipo_cobro, COUNT(*) as cantidad, SUM(monto) as total_generado
        FROM ordenes_cobro
        WHERE estado = 'Pagado' AND fecha_pago BETWEEN ? AND ?
        GROUP BY tipo_cobro
        ORDER BY total_generado DESC
    ";
    $stmtTopServ = $conn->prepare($sqlTopServ);
    $stmtTopServ->execute([$datetime_inicio, $datetime_fin]);
    $topServicios = $stmtTopServ->fetchAll(PDO::FETCH_ASSOC);
}

// ============================================
// 5. ESTADÍSTICAS POR USUARIO (DOCTOR)
// ============================================
$doctorStats = [];
if ($userRol === 'Doctor') {
    // Pacientes vistos (Pagados)
    $sqlPacientesHoy = "SELECT COUNT(DISTINCT id_paciente) FROM ordenes_cobro WHERE id_medico = ? AND DATE(fecha_pago) = CURDATE() AND estado = 'Pagado'";
    $stmtPH = $conn->prepare($sqlPacientesHoy);
    $stmtPH->execute([$_SESSION['user_id']]);
    $doctorStats['pacientes_hoy'] = $stmtPH->fetchColumn();
    
    // Total generado en el periodo
    $doctorStats['ingresos_periodo'] = $montoConsultas;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Avanzados - Servimedic</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
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
            --color-success: #48BB78;
            --color-danger: #F56565;
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
        
        .filter-card { background: var(--color-white); border-radius: 16px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); padding: 1.5rem; margin-bottom: 2rem; }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: var(--color-text); }
        .form-control { width: 100%; padding: 0.75rem 1rem; border: 2px solid var(--color-border); border-radius: 10px; font-size: 0.9rem; font-family: var(--font-family); transition: all 0.3s ease; }
        
        /* Tabs */
        .nav-tabs { display: flex; gap: 1rem; border-bottom: 2px solid var(--color-border); margin-bottom: 2rem; list-style: none; }
        .nav-link { padding: 1rem 1.5rem; font-weight: 600; color: var(--color-text-light); cursor: pointer; border-bottom: 3px solid transparent; transition: all 0.3s ease; background: none; border: none; font-size: 1rem; }
        .nav-link.active { color: var(--color-orange); border-bottom-color: var(--color-orange); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Stats Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--color-white); padding: 1.5rem; border-radius: 16px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border-left: 5px solid var(--color-blue); }
        .stat-card.orange { border-left-color: var(--color-orange); }
        .stat-card.green { border-left-color: var(--color-success); }
        .stat-card.red { border-left-color: var(--color-danger); }
        .stat-label { font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem; }
        .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--color-text); }
        .stat-sub { font-size: 0.8rem; color: var(--color-text-light); margin-top: 0.5rem; }

        /* Tables */
        .table-container { background: var(--color-white); border-radius: 16px; padding: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05); overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--color-border); }
        .table th { font-weight: 600; color: var(--color-text-light); font-size: 0.875rem; text-transform: uppercase; }
        
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .container { max-width: 100%; padding: 0; }
            .nav-tabs { display: none; }
            .tab-content { display: block !important; margin-bottom: 2rem; page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header no-print">
            <div class="page-title-section">
                <a href="../dashboard/index.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="page-title">Reportes Avanzados</h1>
            </div>
            <?php include '../../includes/clock.php'; ?>
            <button class="btn btn-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i> Imprimir
            </button>
            <?php if ($userRol === 'Administrador'): ?>
            <button class="btn btn-primary" onclick="openExpenseModal()">
                <i class="bi bi-dash-circle"></i> Registrar Gasto
            </button>
            <?php endif; ?>
        </div>

        <!-- Filtros -->
        <div class="filter-card no-print">
            <form method="GET">
                <div class="filter-row">
                    <div class="form-group">
                        <label class="form-label">Tipo de Reporte</label>
                        <select name="tipo_reporte" class="form-control" onchange="this.form.submit()">
                            <option value="diario" <?php echo $tipo_reporte == 'diario' ? 'selected' : ''; ?>>Diario (Todo el día)</option>
                            <option value="turno1" <?php echo $tipo_reporte == 'turno1' ? 'selected' : ''; ?>>Turno 1 (08:00 - 17:00)</option>
                            <option value="turno2" <?php echo $tipo_reporte == 'turno2' ? 'selected' : ''; ?>>Turno 2 (17:00 - 08:00 Sig.)</option>
                            <option value="personalizado" <?php echo $tipo_reporte == 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" value="<?php echo $fecha_inicio; ?>">
                    </div>
                    <?php if ($tipo_reporte == 'personalizado'): ?>
                    <div class="form-group">
                        <label class="form-label">Fecha Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" value="<?php echo $fecha_fin; ?>">
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($userRol === 'Administrador'): ?>
                    <div class="form-group">
                        <label class="form-label">Usuario</label>
                        <select name="id_usuario" class="form-control">
                            <option value="">Todos los usuarios</option>
                            <?php foreach ($usuarios as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $id_usuario == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-funnel"></i> Actualizar Reporte
                </button>
            </form>
        </div>

        <!-- Header del Reporte -->
        <div style="margin-bottom: 2rem; padding: 1.5rem; background: linear-gradient(135deg, var(--color-orange), var(--color-blue)); border-radius: 16px; color: white;">
            <h2 style="font-size: 1.5rem; margin-bottom: 0.5rem;">Reporte: <?php echo ucfirst($tipo_reporte); ?></h2>
            <p style="opacity: 0.9;">
                Desde: <strong><?php echo date('d/m/Y H:i', strtotime($datetime_inicio)); ?></strong> <br>
                Hasta: <strong><?php echo date('d/m/Y H:i', strtotime($datetime_fin)); ?></strong>
            </p>
        </div>

        <!-- Navegación de Pestañas -->
        <ul class="nav-tabs no-print">
            <li><button class="nav-link active" onclick="openTab('general')">General</button></li>
            <li><button class="nav-link" onclick="openTab('contabilidad')">Contabilidad</button></li>
            <?php if ($userRol === 'Administrador' || $userRol === 'Farmacia'): ?>
            <li><button class="nav-link" onclick="openTab('bigdata')">Big Data (Medicamentos)</button></li>
            <?php endif; ?>
            <?php if ($userRol === 'Doctor'): ?>
            <li><button class="nav-link" onclick="openTab('doctor')">Mi Rendimiento</button></li>
            <?php endif; ?>
        </ul>

        <!-- Pestaña General -->
        <div id="general" class="tab-content active">
            <div class="stats-grid">
                <div class="stat-card orange">
                    <div class="stat-label">Total Ingresos</div>
                    <div class="stat-value">Q<?php echo number_format($totalIngresos, 2); ?></div>
                    <div class="stat-sub">Consultas + Ventas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Consultas Realizadas</div>
                    <div class="stat-value"><?php echo $totalConsultas; ?></div>
                    <div class="stat-sub">Monto: Q<?php echo number_format($montoConsultas, 2); ?></div>
                    <div style="margin-top: 0.5rem; font-size: 0.75rem; color: #718096;">
                        <?php foreach ($breakdown as $type => $amount): ?>
                        <div><strong><?php echo $type; ?>:</strong> Q<?php echo number_format($amount, 2); ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Ventas Farmacia</div>
                    <div class="stat-value"><?php echo $totalVentas; ?></div>
                    <div class="stat-sub">Monto Real: Q<?php echo number_format($montoVentasReal, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Pestaña Contabilidad -->
        <div id="contabilidad" class="tab-content">
            <div class="stats-grid">
                <div class="stat-card green">
                    <div class="stat-label">Ingresos Totales</div>
                    <div class="stat-value">Q<?php echo number_format($totalIngresos, 2); ?></div>
                </div>
                <div class="stat-card red">
                    <div class="stat-label">Egresos Totales</div>
                    <div class="stat-value">Q<?php echo number_format($totalEgresos, 2); ?></div>
                    <div class="stat-sub">Compras + Gastos</div>
                </div>
                <div class="stat-card <?php echo $balanceNeto >= 0 ? 'green' : 'red'; ?>">
                    <div class="stat-label">Balance Neto</div>
                    <div class="stat-value">Q<?php echo number_format($balanceNeto, 2); ?></div>
                </div>
            </div>

            <div class="table-container">
                <h3 style="margin-bottom: 1rem;">Desglose de Egresos</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Compras de Medicamentos</td>
                            <td>Q<?php echo number_format($totalCompras, 2); ?></td>
                        </tr>
                        <tr>
                            <td>Gastos de Caja Chica</td>
                            <td>Q<?php echo number_format($totalGastos, 2); ?></td>
                        </tr>
                        <tr style="font-weight: bold; background: #f8f9fa;">
                            <td>TOTAL EGRESOS</td>
                            <td>Q<?php echo number_format($totalEgresos, 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pestaña Big Data -->
        <?php if ($userRol === 'Administrador' || $userRol === 'Farmacia'): ?>
        <div id="bigdata" class="tab-content">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="table-container">
                    <h3 style="margin-bottom: 1rem; color: var(--color-success);"><i class="bi bi-graph-up-arrow"></i> Más Vendidos</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Medicamento</th>
                                <th>Cant.</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topMedicamentos as $med): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($med['nom_medicamento']); ?></td>
                                <td><?php echo $med['total_vendido']; ?></td>
                                <td>Q<?php echo number_format($med['total_ingreso'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($topMedicamentos)): ?>
                            <tr><td colspan="3" style="text-align: center;">No hay datos en este periodo</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-container">
                    <h3 style="margin-bottom: 1rem; color: var(--color-danger);"><i class="bi bi-graph-down-arrow"></i> Menos Vendidos</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Medicamento</th>
                                <th>Cant.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowMedicamentos as $med): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($med['nom_medicamento']); ?></td>
                                <td><?php echo $med['total_vendido']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($lowMedicamentos)): ?>
                            <tr><td colspan="2" style="text-align: center;">No hay datos en este periodo</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Servicios Profitability -->
            <div class="table-container" style="margin-top: 2rem;">
                <h3 style="margin-bottom: 1rem; color: var(--color-blue);"><i class="bi bi-pie-chart"></i> Rentabilidad por Servicio</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Servicio / Concepto</th>
                            <th>Cantidad</th>
                            <th>Total Generado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topServicios as $serv): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($serv['tipo_cobro']); ?></td>
                            <td><?php echo $serv['cantidad']; ?></td>
                            <td>Q<?php echo number_format($serv['total_generado'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($topServicios)): ?>
                        <tr><td colspan="3" style="text-align: center;">No hay datos en este periodo</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Pestaña Doctor -->
        <?php if ($userRol === 'Doctor'): ?>
        <div id="doctor" class="tab-content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Pacientes Vistos Hoy</div>
                    <div class="stat-value"><?php echo $doctorStats['pacientes_hoy']; ?></div>
                </div>
                <div class="stat-card green">
                    <div class="stat-label">Ingresos Generados (Periodo)</div>
                    <div class="stat-value">Q<?php echo number_format($doctorStats['ingresos_periodo'], 2); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
    <script>
    
        function openExpenseModal() {
            document.getElementById('expenseModal').classList.add('show');
        }

        function closeExpenseModal() {
                document.getElementById('expenseModal').classList.remove('show');
            }

        document.getElementById('expenseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('save_expense.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    alert('Gasto registrado');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });
    </script>            

    <!-- Expense Modal -->
    <div class="modal-overlay" id="expenseModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Registrar Gasto (Caja Chica)</h2>
                <button class="modal-close" onclick="closeExpenseModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="expenseForm">
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Descripción del Gasto</label>
                        <input type="text" name="descripcion" class="form-control" placeholder="Ej. Almuerzo, Transporte, Limpieza" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monto (Q)</label>
                        <input type="number" name="monto" class="form-control" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeExpenseModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Gasto</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
        /* Modal Styles Reuse */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(5px);
            z-index: 2000; display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: all 0.3s ease;
        }
        .modal-overlay.show { opacity: 1; visibility: visible; }
        .modal {
            background: white; border-radius: 20px; width: 90%; max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: scale(0.9); transition: transform 0.3s ease;
        }
        .modal-overlay.show .modal { transform: scale(1); }
        .modal-header { padding: 1.5rem; background: var(--color-orange); color: white; display: flex; justify-content: space-between; align-items: center; border-radius: 20px 20px 0 0; }
        .modal-title { font-size: 1.25rem; font-weight: 600; margin: 0; }
        .modal-close { background: rgba(255,255,255,0.2); border: none; color: white; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; }
        .modal-body { padding: 2rem; }
        .modal-footer { padding: 1.5rem; border-top: 1px solid var(--color-border); display: flex; justify-content: flex-end; gap: 1rem; background: #f8fafc; border-radius: 0 0 20px 20px; }
    </style>
</body>
</html>