<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

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
$tipo_reporte = $_GET['tipo_reporte'] ?? 'mensual'; // mensual, diario, turno1, turno2, personalizado
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}
$id_usuario = $_GET['id_usuario'] ?? '';

// Lógica de Mes, Turnos y Fechas
$datetime_inicio = $fecha_inicio . ' 00:00:00';
$datetime_fin = $fecha_fin . ' 23:59:59';

if ($tipo_reporte === 'mensual') {
    // Mes completo
    $datetime_inicio = $mes . '-01 00:00:00';
    $datetime_fin = date('Y-m-t', strtotime($mes . '-01')) . ' 23:59:59';
} elseif ($tipo_reporte === 'turno1') {
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
    SELECT o.*, 'Privado' as tipo_cliente
    FROM ordenes_cobro o
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
    $tipo = ucfirst(strtolower($ingreso['tipo_cliente'] ?? 'Privado'));
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
    SELECT v.*, MAX(p.metodo_pago) as metodo_pedido,
           MAX(p.estado_pago) as estado_pago_pedido,
           MAX(p.tipo_pago_estado) as tipo_pago_estado_pedido,
           SUM(dv.subtotal) as total_venta,
           GROUP_CONCAT(fp.tipo_pago) as metodos_pago,
           GROUP_CONCAT(fp.monto) as montos_pago
    FROM ventas v
    LEFT JOIN detalle_ventas dv ON v.id_venta = dv.id_venta
    LEFT JOIN formas_pago fp ON v.id_venta = fp.id_venta
    LEFT JOIN pedidos p ON v.id_pedido = p.id_pedido
    WHERE $whereVentasSQL
    GROUP BY v.id_venta
";
$stmtVentas = $conn->prepare($sqlVentas);
$stmtVentas->execute($paramsVentas);
$ventas = $stmtVentas->fetchAll(PDO::FETCH_ASSOC);

$totalVentas = count($ventas);
$montoVentasTotal = 0;   // total cobrado (el cliente siempre paga el total)
$montoDescuentos = 0;    // descuentos por método de pago (solo reporte)
$montoEfectivo = 0;
$montoTransferencia = 0;
$montoTarjeta = 0;
$montoVentasReal = 0;    // total cobrado (formas de pago)

// Totales por método y estado de pago (para la sección "Totales")
$totEfectivo = 0;        // suma de pagos en efectivo del periodo
$totTransferencia = 0;   // suma de pagos en transferencia del periodo
$totTarjeta = 0;         // suma de pagos con tarjeta del periodo
$totCOD = 0;             // ventas con método COD
$totCredito = 0;         // ventas a crédito (tipo_pago_estado='Crédito')
$totPagado = 0;          // ventas con estado_pago='Pagado' (lo que efectivamente se cobró)
$totPendientePago = 0;   // ventas con estado_pago pendiente (Pendiente / Pendiente Crédito)

// Detalle por venta: total, descuento tarjeta y descuento COD (solo reporte;
// nunca se descuenta de lo que cobra el cliente)
$ventasDetalle = [];
$totDescTarjeta = 0.0;
$totDescCOD = 0.0;

foreach ($ventas as $venta) {
    $montoVentasTotal += floatval($venta['total']);
    $montoDescuentos += floatval($venta['descuento']);

    $estadoPagoVenta = $venta['estado_pago_pedido'] ?? '';
    $esPagado = ($estadoPagoVenta === 'Pagado');
    $tipoPagoVenta = strtolower($venta['tipo_pago_estado_pedido'] ?? '');
    $esCredito = ($tipoPagoVenta === 'crédito' || $tipoPagoVenta === 'credito');
    $metodoVenta = (string) ($venta['metodo_pedido'] ?? '');
    if ($metodoVenta === 'COD') $totCOD += floatval($venta['total']);
    if ($esCredito) $totCredito += floatval($venta['total']);

    // Total Pagado: solo si la venta está marcada como Pagada
    if ($esPagado) {
        $totPagado += floatval($venta['total']);
    } else {
        $totPendientePago += floatval($venta['total']);
    }

    if ($venta['metodos_pago']) {
        $metodos = explode(',', $venta['metodos_pago']);
        $montos = explode(',', $venta['montos_pago']);

        foreach ($metodos as $index => $metodo) {
            $monto = floatval($montos[$index] ?? 0);
            $montoVentasReal += $monto;

            if ($metodo === 'Efectivo') {
                $montoEfectivo += $monto;
                if ($esPagado) $totEfectivo += $monto;
            } elseif (strpos($metodo, 'Transferencia') !== false) {
                $montoTransferencia += $monto;
                if ($esPagado) $totTransferencia += $monto;
            } elseif (strpos($metodo, 'Tarjeta') !== false) {
                $montoTarjeta += $monto;
                if ($esPagado) $totTarjeta += $monto;
            }
        }
    }

    // Método de pago de la venta (para clasificar el descuento)
    $metodoVenta = (string) ($venta['metodo_pedido'] ?? '');
    if ($metodoVenta === '') {
        $mp = (string) ($venta['metodos_pago'] ?? '');
        if (strpos($mp, 'Tarjeta') !== false) {
            $metodoVenta = 'Tarjeta';
        } elseif (strpos($mp, 'Transferencia') !== false) {
            $metodoVenta = 'Transferencia';
        } else {
            $metodoVenta = 'Efectivo';
        }
    }

    $descVenta = calcularDescuento(floatval($venta['total']), $metodoVenta);
    $venta['metodo_reporte'] = $metodoVenta;
    $venta['desc_tarjeta'] = ($metodoVenta === 'Tarjeta') ? $descVenta['descuento'] : 0.0;
    $venta['desc_cod']     = ($metodoVenta === 'COD') ? $descVenta['descuento'] : 0.0;
    $totDescTarjeta += $venta['desc_tarjeta'];
    $totDescCOD     += $venta['desc_cod'];
    $ventasDetalle[] = $venta;
}

// Descuentos por método de pago (desde pedidos/cotizaciones procesadas)
$descuentosPorMetodo = [];
$sqlDescuentos = "
    SELECT metodo_pago, COUNT(*) as cantidad, SUM(descuento) as total_descuento
    FROM pedidos
    WHERE descuento > 0 AND fecha_pedido BETWEEN ? AND ?
    GROUP BY metodo_pago
    ORDER BY total_descuento DESC
";
$stmtDesc = $conn->prepare($sqlDescuentos);
$stmtDesc->execute([$datetime_inicio, $datetime_fin]);
$descuentosPorMetodo = $stmtDesc->fetchAll(PDO::FETCH_ASSOC);

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
// Ensure table exists (esquema real)
$conn->exec("CREATE TABLE IF NOT EXISTS `caja_chica` (
  `id_movimiento` int NOT NULL AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `id_sucursal` int NOT NULL,
  `tipo_movimiento` enum('Ingreso','Egreso') NOT NULL,
  `categoria` varchar(100) NOT NULL,
  `concepto` varchar(255) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `forma_pago` enum('Efectivo','Transferencia','Cheque','Tarjeta') NOT NULL DEFAULT 'Efectivo',
  `referencia` varchar(100) DEFAULT NULL,
  `comprobante` varchar(100) DEFAULT NULL,
  `fecha_movimiento` date NOT NULL,
  `observaciones` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_movimiento`),
  KEY `idx_tipo_movimiento` (`tipo_movimiento`),
  KEY `idx_fecha_movimiento` (`fecha_movimiento`)
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
// 6. CAJA CHICA (DINERO ESPERADO EN CUENTA)
// ============================================
/**
 * Calcula los movimientos y saldos de Caja Chica.
 * Ingresos: ventas cobradas (Pagado) + ingresos manuales.
 * Egresos: abonos a proveedores, comisión tarjeta (6.2%), cargo COD
 * (courier Forza/Cargo) y gastos manuales de caja chica.
 * Si $fi/$ff son null, calcula el histórico completo.
 */
function calcularCajaChica(PDO $conn, ?string $fi, ?string $ff): array
{
    $fiDate = $fi ? substr($fi, 0, 10) : null;
    $ffDate = $ff ? substr($ff, 0, 10) : null;

    $mov = [];
    $ingresosVentas = 0.0;
    $ingresosManual = 0.0;
    $abonosProv = 0.0;
    $comisionTarjeta = 0.0;
    $cargoCOD = 0.0;
    $gastosManual = 0.0;
    $porCobrar = 0.0;
    $ventasCobradas = 0;
    $ventasEfectivo = 0.0;
    $ventasTransferencia = 0.0;
    $ventasTarjeta = 0.0;
    $ventasCOD = 0.0;

    // --- Ventas (cobradas y por cobrar) ---
    $sqlVentas = "
        SELECT v.id_venta, v.numero_venta, v.cliente_nombre, v.fecha_venta, v.total,
               MAX(p.metodo_pago) as metodo_pago,
               MAX(p.estado_pago) as estado_pago,
               MAX(p.transportista) as transportista,
               GROUP_CONCAT(fp.tipo_pago) as metodos_pago
        FROM ventas v
        LEFT JOIN formas_pago fp ON fp.id_venta = v.id_venta
        LEFT JOIN pedidos p ON p.id_pedido = v.id_pedido
    ";
    $paramsVentas = [];
    if ($fi && $ff) {
        $sqlVentas .= " WHERE v.fecha_venta BETWEEN ? AND ?";
        $paramsVentas = [$fi, $ff];
    }
    $sqlVentas .= " GROUP BY v.id_venta ORDER BY v.fecha_venta ASC";
    $stmtV = $conn->prepare($sqlVentas);
    $stmtV->execute($paramsVentas);

    foreach ($stmtV->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $total = (float) $v['total'];
        if (($v['estado_pago'] ?? '') !== 'Pagado') {
            $porCobrar += $total;
            continue;
        }

        // Método de pago (igual que el reporte general)
        $metodo = (string) ($v['metodo_pago'] ?? '');
        if ($metodo === '') {
            $mp = (string) ($v['metodos_pago'] ?? '');
            if (strpos($mp, 'Tarjeta') !== false) {
                $metodo = 'Tarjeta';
            } elseif (strpos($mp, 'Transferencia') !== false) {
                $metodo = 'Transferencia';
            } else {
                $metodo = 'Efectivo';
            }
        }

        $doc = !empty($v['numero_venta']) ? $v['numero_venta'] : ('#' . $v['id_venta']);
        $fechaGT = fechaGT($v['fecha_venta'], 'Y-m-d H:i:s');
        $ventasCobradas++;
        $ingresosVentas += $total;
        if ($metodo === 'Efectivo') $ventasEfectivo += $total;
        elseif ($metodo === 'Transferencia') $ventasTransferencia += $total;
        elseif ($metodo === 'Tarjeta') $ventasTarjeta += $total;
        elseif ($metodo === 'COD') $ventasCOD += $total;

        $mov[] = [
            'fecha_sort' => $fechaGT,
            'fecha_txt'  => fechaGT($v['fecha_venta'], 'd/m/Y H:i'),
            'tipo'       => 'Ingreso',
            'categoria'  => 'Venta ' . $metodo,
            'concepto'   => $doc . ' — ' . ($v['cliente_nombre'] ?: 'Cliente'),
            'monto'      => $total,
        ];

        // Comisión tarjeta: dinero que se paga a la procesadora/banco
        if ($metodo === 'Tarjeta') {
            $desc = calcularDescuento($total, 'Tarjeta');
            if ($desc['descuento'] > 0) {
                $comisionTarjeta += $desc['descuento'];
                $mov[] = [
                    'fecha_sort' => $fechaGT,
                    'fecha_txt'  => fechaGT($v['fecha_venta'], 'd/m/Y H:i'),
                    'tipo'       => 'Egreso',
                    'categoria'  => 'Comisión tarjeta',
                    'concepto'   => 'Comisión 6.2% — ' . $doc,
                    'monto'      => -$desc['descuento'],
                ];
            }
        }

        // Cargo COD: dinero que se paga al courier (Forza / Cargo)
        if ($metodo === 'COD') {
            $desc = calcularDescuento($total, 'COD');
            if ($desc['descuento'] > 0) {
                $cargoCOD += $desc['descuento'];
                $courier = $v['transportista'] ?: 'Forza/Cargo';
                $mov[] = [
                    'fecha_sort' => $fechaGT,
                    'fecha_txt'  => fechaGT($v['fecha_venta'], 'd/m/Y H:i'),
                    'tipo'       => 'Egreso',
                    'categoria'  => 'Cargo COD (courier)',
                    'concepto'   => 'Cargo COD ' . $courier . ' — ' . $doc,
                    'monto'      => -$desc['descuento'],
                ];
            }
        }
    }

    // --- Abonos a proveedores ---
    $sqlAbonos = "
        SELECT a.monto, a.fecha_abono, a.forma_pago, a.referencia,
               c.casa_farmaceutica
        FROM abonos_compras a
        JOIN compras c ON a.id_compra = c.id_compra
    ";
    $paramsAbonos = [];
    if ($fiDate && $ffDate) {
        $sqlAbonos .= " WHERE a.fecha_abono BETWEEN ? AND ?";
        $paramsAbonos = [$fiDate, $ffDate];
    }
    $sqlAbonos .= " ORDER BY a.fecha_abono ASC, a.id_abono ASC";
    $stmtA = $conn->prepare($sqlAbonos);
    $stmtA->execute($paramsAbonos);

    foreach ($stmtA->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $monto = (float) $a['monto'];
        $abonosProv += $monto;
        $prov = $a['casa_farmaceutica'] ?: 'Proveedor';
        $mov[] = [
            'fecha_sort' => $a['fecha_abono'] . ' 12:00:00',
            'fecha_txt'  => date('d/m/Y', strtotime($a['fecha_abono'])),
            'tipo'       => 'Egreso',
            'categoria'  => 'Pago a proveedor',
            'concepto'   => 'Abono a ' . $prov
                . ($a['referencia'] ? ' (' . $a['referencia'] . ')' : '')
                . ' — ' . $a['forma_pago'],
            'monto'      => -$monto,
        ];
    }

    // --- Movimientos manuales de caja chica ---
    $sqlCC = "SELECT * FROM caja_chica";
    $paramsCC = [];
    if ($fiDate && $ffDate) {
        $sqlCC .= " WHERE fecha_movimiento BETWEEN ? AND ?";
        $paramsCC = [$fiDate, $ffDate];
    }
    $sqlCC .= " ORDER BY fecha_movimiento ASC, id_movimiento ASC";
    $stmtC = $conn->prepare($sqlCC);
    $stmtC->execute($paramsCC);

    foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $esIngreso = ($m['tipo_movimiento'] === 'Ingreso');
        $monto = (float) $m['monto'];
        if ($esIngreso) {
            $ingresosManual += $monto;
        } else {
            $gastosManual += $monto;
        }
        $mov[] = [
            'fecha_sort' => $m['fecha_movimiento'] . ' 12:30:00',
            'fecha_txt'  => fechaGT($m['fecha_movimiento'] . ' 12:30:00', 'd/m/Y'),
            'tipo'       => $m['tipo_movimiento'],
            'categoria'  => $m['categoria'] ?: ($esIngreso ? 'Ingreso manual' : 'Gasto caja chica'),
            'concepto'   => $m['concepto'],
            'monto'      => $esIngreso ? $monto : -$monto,
        ];
    }

    // Orden cronológico y saldo acumulado
    usort($mov, fn($x, $y) => strcmp($x['fecha_sort'], $y['fecha_sort']));
    $saldo = 0.0;
    foreach ($mov as &$m) {
        $saldo += $m['monto'];
        $m['saldo_acum'] = $saldo;
    }
    unset($m);

    return [
        'movimientos'         => $mov,
        'ventas_cobradas'     => $ventasCobradas,
        'ingresos_ventas'     => round($ingresosVentas, 2),
        'ingresos_manual'     => round($ingresosManual, 2),
        'ingresos_total'      => round($ingresosVentas + $ingresosManual, 2),
        'ventas_efectivo'     => round($ventasEfectivo, 2),
        'ventas_transferencia'=> round($ventasTransferencia, 2),
        'ventas_tarjeta'      => round($ventasTarjeta, 2),
        'ventas_cod'          => round($ventasCOD, 2),
        'abonos_proveedores'  => round($abonosProv, 2),
        'comision_tarjeta'    => round($comisionTarjeta, 2),
        'cargo_cod'           => round($cargoCOD, 2),
        'gastos_manual'       => round($gastosManual, 2),
        'egresos_total'       => round($abonosProv + $comisionTarjeta + $cargoCOD + $gastosManual, 2),
        'saldo'               => round($ingresosVentas + $ingresosManual - $abonosProv - $comisionTarjeta - $cargoCOD - $gastosManual, 2),
        'por_cobrar'          => round($porCobrar, 2),
    ];
}

$cajaPeriodo = calcularCajaChica($conn, $datetime_inicio, $datetime_fin);
$cajaTotal   = calcularCajaChica($conn, null, null);

// Saldo pendiente con proveedores (histórico)
$stmtProvPend = $conn->query("SELECT SUM(saldo) FROM compras");
$saldoProveedores = (float) ($stmtProvPend->fetchColumn() ?: 0);


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
    // Clientes vistos (Pagados)
    $sqlPacientesHoy = "SELECT COUNT(DISTINCT id_cliente) FROM ordenes_cobro WHERE id_medico = ? AND DATE(fecha_pago) = CURDATE() AND estado = 'Pagado'";
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
    <title>Reportes Avanzados - CLINOVA</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --color-orange: #FF3D00;
            --color-blue: #1FA8FF;
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
        .btn-primary { background: linear-gradient(135deg, var(--color-orange), #E63400); color: white; }
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
                <i class="bi bi-cash-coin"></i> Registrar Movimiento
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
                            <option value="mensual" <?php echo $tipo_reporte == 'mensual' ? 'selected' : ''; ?>>Mensual (Mes completo)</option>
                            <option value="diario" <?php echo $tipo_reporte == 'diario' ? 'selected' : ''; ?>>Diario (Todo el día)</option>
                            <option value="turno1" <?php echo $tipo_reporte == 'turno1' ? 'selected' : ''; ?>>Turno 1 (08:00 - 17:00)</option>
                            <option value="turno2" <?php echo $tipo_reporte == 'turno2' ? 'selected' : ''; ?>>Turno 2 (17:00 - 08:00 Sig.)</option>
                            <option value="personalizado" <?php echo $tipo_reporte == 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
                        </select>
                    </div>
                    <?php if ($tipo_reporte == 'mensual'): ?>
                    <div class="form-group">
                        <label class="form-label">Mes</label>
                        <input type="month" name="mes" class="form-control" value="<?php echo htmlspecialchars($mes); ?>">
                    </div>
                    <?php else: ?>
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
            <li><button class="nav-link active" data-tab="general" onclick="openTab('general')"><i class="bi bi-bar-chart"></i> General</button></li>
            <li><button class="nav-link" data-tab="caja" onclick="openTab('caja')"><i class="bi bi-cash-coin"></i> Caja Chica</button></li>
        </ul>

        <!-- Pestaña General -->
        <div id="general" class="tab-content active">
            <div class="stats-grid">
                <div class="stat-card orange">
                    <div class="stat-label">Total Ingresos</div>
                    <div class="stat-value">Q<?php echo number_format($totalIngresos, 2); ?></div>
                    <div class="stat-sub">Ventas del periodo</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Ventas Farmacia</div>
                    <div class="stat-value"><?php echo $totalVentas; ?></div>
                    <div class="stat-sub">Total: Q<?php echo number_format($montoVentasTotal, 2); ?></div>
                </div>
                <div class="stat-card green">
                    <div class="stat-label">Total Pagado</div>
                    <div class="stat-value">Q<?php echo number_format($totPagado, 2); ?></div>
                    <div class="stat-sub">Efectivamente cobrado</div>
                </div>
                <div class="stat-card" style="background: linear-gradient(135deg, #fbd38d, #f6ad55); color: white;">
                    <div class="stat-label" style="color: white;">Descuentos Tarjeta / COD</div>
                    <div class="stat-value">Q<?php echo number_format($montoDescuentos, 2); ?></div>
                    <div class="stat-sub" style="color: white;">Total Ventas: Q<?php echo number_format($montoVentasTotal, 2); ?></div>
                </div>
            </div>

            <div class="table-container">
                <h3 style="margin-bottom: 1rem;"><i class="bi bi-receipt"></i> Detalle de Ventas (Total y Descuentos por Método)</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th># Venta</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Método</th>
                            <th>Total de la Venta</th>
                            <th>Descuento Tarjeta</th>
                            <th>Descuento COD</th>
                            <th>Total con Descuento</th>
                            <th>Total Pagado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ventasDetalle)): ?>
                        <tr><td colspan="9" style="text-align: center;">No hay ventas en este periodo</td></tr>
                        <?php else: ?>
                        <?php foreach ($ventasDetalle as $v): ?>
                        <?php
                            $descuentoVenta = $v['desc_tarjeta'] + $v['desc_cod'];
                            $totalConDesc = $v['total'] - $descuentoVenta;
                            $esPagadoVenta = ($v['estado_pago_pedido'] ?? '') === 'Pagado';
                            $totalPagadoVenta = $esPagadoVenta ? $totalConDesc : 0.0;
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo !empty($v['numero_venta']) ? htmlspecialchars($v['numero_venta']) : '#' . $v['id_venta']; ?></strong>
                                <?php if (!empty($v['numero_venta'])): ?>
                                <br><small style="color:#a0aec0;">#<?php echo $v['id_venta']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo fechaGT($v['fecha_venta'], 'd/m/Y H:i'); ?></td>
                            <td><?php echo htmlspecialchars($v['cliente_nombre']); ?></td>
                            <td><span class="badge" style="background: #edf2f7;"><?php echo htmlspecialchars($v['metodo_reporte']); ?></span></td>
                            <td><strong>Q<?php echo number_format($v['total'], 2); ?></strong></td>
                            <td>
                                <?php if ($v['desc_tarjeta'] > 0): ?>
                                    <span style="color: var(--color-success); font-weight: 700;">-Q<?php echo number_format($v['desc_tarjeta'], 2); ?></span>
                                <?php else: ?>
                                    <span style="color: #a0aec0;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($v['desc_cod'] > 0): ?>
                                    <span style="color: var(--color-success); font-weight: 700;">-Q<?php echo number_format($v['desc_cod'], 2); ?></span>
                                <?php else: ?>
                                    <span style="color: #a0aec0;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><strong>Q<?php echo number_format($totalConDesc, 2); ?></strong></td>
                            <td>
                                <?php if ($esPagadoVenta): ?>
                                    <span style="color: var(--color-success); font-weight: 700;">Q<?php echo number_format($totalPagadoVenta, 2); ?></span>
                                <?php else: ?>
                                    <span style="color: #a0aec0;">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php
                            $sumTotal = array_sum(array_column($ventasDetalle, 'total'));
                            $sumConDesc = $sumTotal - ($totDescTarjeta + $totDescCOD);
                            $sumPagado = 0.0;
                            foreach ($ventasDetalle as $vv) {
                                if (($vv['estado_pago_pedido'] ?? '') === 'Pagado') {
                                    $sumPagado += $vv['total'] - ($vv['desc_tarjeta'] + $vv['desc_cod']);
                                }
                            }
                        ?>
                        <tr style="font-weight: 700; background: #f7fafc;">
                            <td colspan="4">TOTAL</td>
                            <td>Q<?php echo number_format($sumTotal, 2); ?></td>
                            <td>-Q<?php echo number_format($totDescTarjeta, 2); ?></td>
                            <td>-Q<?php echo number_format($totDescCOD, 2); ?></td>
                            <td>Q<?php echo number_format($sumConDesc, 2); ?></td>
                            <td>Q<?php echo number_format($sumPagado, 2); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Sección Totales -->
            <div class="table-container" style="background: linear-gradient(135deg, #f0fff4, #ebf8ff); border: 2px solid #68d391;">
                <h3 style="margin-bottom: 1rem; color: #22543d;">
                    <i class="bi bi-bar-chart-line"></i> Totales
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                    <div class="stat-card" style="background: white; border-left: 4px solid #38a169;">
                        <div class="stat-label">Total Ventas del Mes</div>
                        <div class="stat-value" style="color:#22543d;">Q<?php echo number_format($montoVentasTotal, 2); ?></div>
                        <div class="stat-sub"><?php echo $totalVentas; ?> ventas</div>
                    </div>
                    <div class="stat-card" style="background: white; border-left: 4px solid #38a169;">
                        <div class="stat-label">Total Efectivo</div>
                        <div class="stat-value" style="color:#22543d;">Q<?php echo number_format($totEfectivo, 2); ?></div>
                        <div class="stat-sub">Cobrado en efectivo</div>
                    </div>
                    <div class="stat-card" style="background: white; border-left: 4px solid #3182ce;">
                        <div class="stat-label">Total Transferencia</div>
                        <div class="stat-value" style="color:#2c5282;">Q<?php echo number_format($totTransferencia, 2); ?></div>
                        <div class="stat-sub">Cobrado por transferencia</div>
                    </div>
                    <div class="stat-card" style="background: white; border-left: 4px solid #d69e2e;">
                        <div class="stat-label">Total Tarjeta</div>
                        <div class="stat-value" style="color:#744210;">Q<?php echo number_format($totTarjeta, 2); ?></div>
                        <div class="stat-sub">Cobrado con tarjeta</div>
                    </div>
                    <div class="stat-card" style="background: white; border-left: 4px solid #ed8936;">
                        <div class="stat-label">Total COD</div>
                        <div class="stat-value" style="color:#7b341e;">Q<?php echo number_format($totCOD, 2); ?></div>
                        <div class="stat-sub">Ventas contra entrega</div>
                    </div>
                    <div class="stat-card" style="background: white; border-left: 4px solid #805ad5;">
                        <div class="stat-label">Total Crédito</div>
                        <div class="stat-value" style="color:#44337a;">Q<?php echo number_format($totCredito, 2); ?></div>
                        <div class="stat-sub">Ventas a crédito</div>
                    </div>
                    <div class="stat-card" style="background: white; border-left: 4px solid #e53e3e;">
                        <div class="stat-label">Pendiente de Pago</div>
                        <div class="stat-value" style="color:#742a2a;">Q<?php echo number_format($totPendientePago, 2); ?></div>
                        <div class="stat-sub">Aún no cobrado</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #38a169, #2f855a); color: white; border-left: 4px solid #22543d;">
                        <div class="stat-label" style="color:white;">Total Pagado</div>
                        <div class="stat-value" style="color:white;">Q<?php echo number_format($totPagado, 2); ?></div>
                        <div class="stat-sub" style="color:white;">Efectivamente cobrado</div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <h3 style="margin-bottom: 1rem;"><i class="bi bi-percent"></i> Descuentos por Método de Pago</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Método</th>
                            <th>Cantidad</th>
                            <th>Descuento Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($descuentosPorMetodo)): ?>
                        <tr><td colspan="3" style="text-align: center;">No hay descuentos en este periodo</td></tr>
                        <?php else: ?>
                        <?php foreach ($descuentosPorMetodo as $dm): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($dm['metodo_pago']); ?></strong></td>
                            <td><?php echo $dm['cantidad']; ?></td>
                            <td style="color: var(--color-success); font-weight: 700;">-Q<?php echo number_format($dm['total_descuento'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pestaña Caja Chica -->
        <div id="caja" class="tab-content">
            <div class="stats-grid">
                <div class="stat-card <?php echo $cajaPeriodo['saldo'] >= 0 ? 'green' : 'red'; ?>" style="background: linear-gradient(135deg, <?php echo $cajaPeriodo['saldo'] >= 0 ? '#38a169, #2f855a' : '#e53e3e, #c53030'; ?>); color: white;">
                    <div class="stat-label" style="color: white;">Saldo esperado del periodo</div>
                    <div class="stat-value" style="color: white;">Q<?php echo number_format($cajaPeriodo['saldo'], 2); ?></div>
                    <div class="stat-sub" style="color: white;">Dinero que debería haber en caja/cuenta en este periodo</div>
                </div>
                <div class="stat-card" style="border-left-color: #3182ce;">
                    <div class="stat-label">Ingresos del periodo</div>
                    <div class="stat-value" style="color:#2c5282;">Q<?php echo number_format($cajaPeriodo['ingresos_total'], 2); ?></div>
                    <div class="stat-sub"><?php echo $cajaPeriodo['ventas_cobradas']; ?> ventas cobradas<?php echo $cajaPeriodo['ingresos_manual'] > 0 ? ' + ingresos manuales' : ''; ?></div>
                </div>
                <div class="stat-card red">
                    <div class="stat-label">Egresos del periodo</div>
                    <div class="stat-value" style="color:#742a2a;">Q<?php echo number_format($cajaPeriodo['egresos_total'], 2); ?></div>
                    <div class="stat-sub">Proveedores + comisiones + gastos</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-label">Saldo acumulado (histórico)</div>
                    <div class="stat-value" style="color:#7b341e;">Q<?php echo number_format($cajaTotal['saldo'], 2); ?></div>
                    <div class="stat-sub">Todos los movimientos registrados</div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;" id="cajaDesglose">
                <div class="table-container">
                    <h3 style="margin-bottom: 1rem; color: #22543d;"><i class="bi bi-arrow-down-circle"></i> Ingresos del periodo</h3>
                    <table class="table">
                        <tbody>
                            <tr><td>Ventas en efectivo</td><td style="text-align:right;"><strong>Q<?php echo number_format($cajaPeriodo['ventas_efectivo'], 2); ?></strong></td></tr>
                            <tr><td>Ventas por transferencia</td><td style="text-align:right;"><strong>Q<?php echo number_format($cajaPeriodo['ventas_transferencia'], 2); ?></strong></td></tr>
                            <tr><td>Ventas con tarjeta</td><td style="text-align:right;"><strong>Q<?php echo number_format($cajaPeriodo['ventas_tarjeta'], 2); ?></strong></td></tr>
                            <tr><td>Ventas COD (contra entrega)</td><td style="text-align:right;"><strong>Q<?php echo number_format($cajaPeriodo['ventas_cod'], 2); ?></strong></td></tr>
                            <?php if ($cajaPeriodo['ingresos_manual'] > 0): ?>
                            <tr><td>Ingresos manuales de caja</td><td style="text-align:right;"><strong>Q<?php echo number_format($cajaPeriodo['ingresos_manual'], 2); ?></strong></td></tr>
                            <?php endif; ?>
                            <tr style="font-weight: 700; background: #f7fafc;">
                                <td>TOTAL INGRESOS</td>
                                <td style="text-align:right;">Q<?php echo number_format($cajaPeriodo['ingresos_total'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <p style="margin-top: .75rem; font-size: .85rem; color: var(--color-text-light);">
                        <i class="bi bi-info-circle"></i> Por cobrar del periodo (aún no pagado): <strong>Q<?php echo number_format($cajaPeriodo['por_cobrar'], 2); ?></strong>
                    </p>
                </div>

                <div class="table-container">
                    <h3 style="margin-bottom: 1rem; color: #742a2a;"><i class="bi bi-arrow-up-circle"></i> Egresos del periodo</h3>
                    <table class="table">
                        <tbody>
                            <tr><td><i class="bi bi-truck"></i> Pagos a proveedores (abonos)</td><td style="text-align:right;"><strong>Q<?php echo number_format($cajaPeriodo['abonos_proveedores'], 2); ?></strong></td></tr>
                            <tr><td><i class="bi bi-credit-card"></i> Comisión tarjeta (6.2%)</td><td style="text-align:right;"><strong>Q<?php echo number_format($cajaPeriodo['comision_tarjeta'], 2); ?></strong></td></tr>
                            <tr><td><i class="bi bi-box-seam"></i> Cargo COD courier (Forza/Cargo)</td><td style="text-align:right;"><strong>Q<?php echo number_format($cajaPeriodo['cargo_cod'], 2); ?></strong></td></tr>
                            <tr><td><i class="bi bi-receipt"></i> Gastos de caja chica</td><td style="text-align:right;"><strong>Q<?php echo number_format($cajaPeriodo['gastos_manual'], 2); ?></strong></td></tr>
                            <tr style="font-weight: 700; background: #f7fafc;">
                                <td>TOTAL EGRESOS</td>
                                <td style="text-align:right;">Q<?php echo number_format($cajaPeriodo['egresos_total'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <p style="margin-top: .75rem; font-size: .85rem; color: var(--color-text-light);">
                        <i class="bi bi-info-circle"></i> Saldo pendiente con proveedores (histórico): <strong>Q<?php echo number_format($saldoProveedores, 2); ?></strong>
                    </p>
                </div>
            </div>

            <div class="table-container">
                <h3 style="margin-bottom: 1rem;"><i class="bi bi-journal-text"></i> Movimientos de Caja Chica del Periodo</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Categoría</th>
                            <th>Concepto</th>
                            <th style="text-align:right;">Monto</th>
                            <th style="text-align:right;">Saldo acumulado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cajaPeriodo['movimientos'])): ?>
                        <tr><td colspan="6" style="text-align: center;">No hay movimientos de caja en este periodo</td></tr>
                        <?php else: ?>
                        <?php foreach ($cajaPeriodo['movimientos'] as $m): ?>
                        <tr>
                            <td><?php echo $m['fecha_txt']; ?></td>
                            <td>
                                <span class="badge" style="background: <?php echo $m['tipo'] === 'Ingreso' ? '#c6f6d5; color:#22543d' : '#fed7d7; color:#742a2a'; ?>; padding: .25rem .6rem; border-radius: 999px; font-size: .78rem; font-weight: 600;">
                                    <?php echo $m['tipo']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($m['categoria']); ?></td>
                            <td><?php echo htmlspecialchars($m['concepto']); ?></td>
                            <td style="text-align:right; font-weight: 700; color: <?php echo $m['monto'] >= 0 ? '#22543d' : '#742a2a'; ?>;">
                                <?php echo ($m['monto'] >= 0 ? '+ ' : '− ') . 'Q' . number_format(abs($m['monto']), 2); ?>
                            </td>
                            <td style="text-align:right;">Q<?php echo number_format($m['saldo_acum'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="font-weight: 700; background: #f7fafc;">
                            <td colspan="4">TOTAL DEL PERIODO</td>
                            <td style="text-align:right; color: <?php echo $cajaPeriodo['saldo'] >= 0 ? '#22543d' : '#742a2a'; ?>;">
                                <?php echo 'Q' . number_format($cajaPeriodo['saldo'], 2); ?>
                            </td>
                            <td style="text-align:right;">Q<?php echo number_format($cajaPeriodo['saldo'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <p style="margin-top: .75rem; font-size: .85rem; color: var(--color-text-light);">
                    <i class="bi bi-lightbulb"></i> Los abonos a proveedores se registran en <strong>Compras → Abonar</strong>. Los cargos COD y comisiones de tarjeta se calculan automáticamente de las ventas cobradas. Usa <strong>Registrar Movimiento</strong> para gastos o ingresos manuales de la caja.
                </p>
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
                    <div class="stat-label">Clientes Atendidos Hoy</div>
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
        // ============ Pestañas ============
        function openTab(tab) {
            document.querySelectorAll('.nav-tabs .nav-link').forEach(b => {
                b.classList.toggle('active', b.dataset.tab === tab);
            });
            document.querySelectorAll('.tab-content').forEach(p => {
                p.classList.toggle('active', p.id === tab);
            });
            try { sessionStorage.setItem('reportTab', tab); } catch (e) {}
        }

        (function () {
            try {
                if (sessionStorage.getItem('reportTab') === 'caja') openTab('caja');
            } catch (e) {}
        })();

        function openExpenseModal() {
            document.getElementById('expenseModal').classList.add('show');
        }

        function closeExpenseModal() {
                document.getElementById('expenseModal').classList.remove('show');
            }

        document.getElementById('expenseForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            try {
                const data = await withLoading('Registrando gasto...', async () => {
                    const response = await fetch('save_expense.php', {
                        method: 'POST',
                        body: formData
                    });
                    return response.json();
                });

                if (data.success) {
                    uiToast('Gasto registrado', 'success');
                    setTimeout(() => location.reload(), 800);
                } else {
                    uiToast('Error: ' + data.message, 'error', 5000);
                }
            } catch (err) {
                uiToast('Error al registrar el gasto', 'error', 5000);
            }
        });

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay?.addEventListener('click', function(e) {
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
                <h2 class="modal-title">Registrar Movimiento de Caja Chica</h2>
                <button class="modal-close" onclick="closeExpenseModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="expenseForm">
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Tipo de Movimiento</label>
                        <select name="tipo" class="form-control">
                            <option value="Egreso">Gasto (sale dinero de la caja)</option>
                            <option value="Ingreso">Ingreso (entra dinero a la caja)</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Categoría</label>
                        <select name="categoria" class="form-control">
                            <option value="Servicios (luz, agua, internet)">Servicios (luz, agua, internet)</option>
                            <option value="Transporte / Envíos">Transporte / Envíos</option>
                            <option value="Alimentación">Alimentación</option>
                            <option value="Limpieza">Limpieza</option>
                            <option value="Mantenimiento">Mantenimiento</option>
                            <option value="Papelería">Papelería</option>
                            <option value="Sueldos">Sueldos</option>
                            <option value="Aporte de capital">Aporte de capital</option>
                            <option value="Préstamo recibido">Préstamo recibido</option>
                            <option value="Otros gastos">Otros gastos</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Concepto</label>
                        <input type="text" name="concepto" class="form-control" placeholder="Ej. Pago de energía eléctrica, almuerzo, envío..." required maxlength="255">
                    </div>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Monto (Q)</label>
                        <input type="number" name="monto" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Forma de Pago / Recepción</label>
                        <select name="forma_pago" class="form-control">
                            <option value="Efectivo">Efectivo</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Tarjeta">Tarjeta</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeExpenseModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Movimiento</button>
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
    <?php include __DIR__ . '/../includes/whatsapp_float.php'; ?>
    <?php include __DIR__ . '/../includes/ui_feedback.php'; ?>
</body>
</html>