<?php
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function verify_session() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../index.php");
        exit();
    }
}

function time_ago($datetime, $full = false) {
    date_default_timezone_set('America/Guatemala');
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $diff->d += $weeks * 7;
    $diff->d -= $weeks * 7;

    $string = array(
        'y' => 'año',
        'm' => 'mes',
        'w' => 'semana',
        'd' => 'día',
        'h' => 'hora',
        'i' => 'minuto',
        's' => 'segundo',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'hace ' . implode(', ', $string) : 'justo ahora';
}

/**
 * Calculates discount based on payment method.
 *
 * Rules:
 *   - Efectivo / Transferencia: no discount.
 *   - Tarjeta: 6.2% off subtotal.
 *   - COD: -Q24 then 4% on the remainder.
 *
 * @param float  $subtotal
 * @param string $metodoPago  Efectivo|Transferencia|Tarjeta|COD
 * @return array{descuento:float,total:float,desglose:string}
 */
function calcularDescuento(float $subtotal, string $metodoPago): array {
    $descuento = 0.0;
    $total     = round($subtotal, 2);
    $desglose  = 'Sin descuento';

    switch ($metodoPago) {
        case 'Tarjeta':
            $descuento = round($subtotal * 0.062, 2);
            $total     = round($subtotal - $descuento, 2);
            $desglose  = 'Tarjeta: 6.2% sobre subtotal';
            break;
        case 'COD':
            $base = $subtotal - 24;
            if ($base < 0) $base = 0;
            $descuento = round(24 + ($base * 0.04), 2);
            if ($descuento > $subtotal) $descuento = $subtotal;
            $total    = round($subtotal - $descuento, 2);
            $desglose = 'COD: -Q24 + 4% sobre el resto';
            break;
        case 'Efectivo':
        case 'Transferencia':
        default:
            // sin descuento
            break;
    }

    return [
        'descuento' => $descuento,
        'total'     => $total,
        'desglose'  => $desglose,
    ];
}

/**
 * Convert a numeric amount to its Spanish word representation.
 * Example: 940.50 -> "Novecientos Cuarenta Quetzales con Cincuenta Centavos"
 *
 * @param float $numero
 * @param string $monedaSingular  default 'Quetzal'
 * @param string $monedaPlural    default 'Quetzales'
 * @return string
 */
function numeroALetras(float $numero, string $monedaSingular = 'Quetzal', string $monedaPlural = 'Quetzales'): string {
    $entero  = (int) floor($numero);
    $centavos = (int) round(($numero - $entero) * 100);

    if ($centavos > 0) {
        $centStr = ' con ' . numeroALetrasEntero($centavos) . ($centavos === 1 ? ' Centavo' : ' Centavos');
    } else {
        $centStr = ' exactos';
    }

    $letras = numeroALetrasEntero($entero);
    $moneda = ($entero === 1) ? $monedaSingular : $monedaPlural;

    return $letras . ' ' . $moneda . $centStr;
}

/**
 * Convert an integer (0 to 999,999,999) to its Spanish word representation.
 * Internal helper for numeroALetras().
 */
function numeroALetrasEntero(int $n): string {
    if ($n < 0) return 'Menos ' . numeroALetrasEntero(-$n);
    if ($n === 0) return 'Cero';

    $unidades = ['', 'Uno', 'Dos', 'Tres', 'Cuatro', 'Cinco', 'Seis', 'Siete', 'Ocho', 'Nueve',
                 'Diez', 'Once', 'Doce', 'Trece', 'Catorce', 'Quince', 'Dieciséis', 'Diecisiete',
                 'Dieciocho', 'Diecinueve', 'Veinte', 'Veintiuno', 'Veintidós', 'Veintitrés',
                 'Veinticuatro', 'Veinticinco', 'Veintiséis', 'Veintisiete', 'Veintiocho', 'Veintinueve'];
    $decenas = ['', '', '', 'Treinta', 'Cuarenta', 'Cincuenta', 'Sesenta', 'Setenta', 'Ochenta', 'Noventa'];
    $centenas = ['', 'Ciento', 'Doscientos', 'Trescientos', 'Cuatrocientos', 'Quinientos',
                 'Seiscientos', 'Setecientos', 'Ochocientos', 'Novecientos'];

    $partes = [];

    // Millones
    if ($n >= 1000000) {
        $m = intdiv($n, 1000000);
        $partes[] = ($m === 1 ? 'Un Millón' : numeroALetrasEntero($m) . ' Millones');
        $n = $n % 1000000;
    }

    // Miles
    if ($n >= 1000) {
        $k = intdiv($n, 1000);
        $partes[] = ($k === 1 ? 'Mil' : numeroALetrasEntero($k) . ' Mil');
        $n = $n % 1000;
    }

    // Centenas / decenas / unidades
    if ($n >= 100) {
        $c = intdiv($n, 100);
        if ($n === 100) {
            $partes[] = 'Cien';
        } else {
            $partes[] = $centenas[$c];
        }
        $n = $n % 100;
    }

    if ($n > 0) {
        if ($n < 30) {
            $partes[] = $unidades[$n];
        } else {
            $d = intdiv($n, 10);
            $u = $n % 10;
            $partes[] = $decenas[$d] . ($u > 0 ? ' y ' . $unidades[$u] : '');
        }
    }

    return implode(' ', array_filter($partes));
}

/**
 * Generate the next cotizacion number for today.
 * Format: COT-YYYYMMDD-#### with daily reset (fecha de negocio en Guatemala).
 */
function generarNumeroCotizacion(PDO $conn): string {
    $tzOriginal = date_default_timezone_get();
    date_default_timezone_set('America/Guatemala');
    $today = date('Ymd');
    date_default_timezone_set($tzOriginal);

    $prefix = 'COT-' . $today . '-';
    $stmt = $conn->prepare("
        SELECT cotizacion_numero FROM pedidos
        WHERE cotizacion_numero LIKE ?
        ORDER BY id_pedido DESC LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $next = 1;
    if ($last && preg_match('/-(\d+)$/', $last, $m)) {
        $next = ((int) $m[1]) + 1;
    }
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function generarNumeroVenta(PDO $conn): string {
    $tzOriginal = date_default_timezone_get();
    date_default_timezone_set('America/Guatemala');
    $today = date('Ymd');
    date_default_timezone_set($tzOriginal);

    $prefix = 'VEN-' . $today . '-';
    $stmt = $conn->prepare("
        SELECT numero_venta FROM ventas
        WHERE numero_venta LIKE ?
        ORDER BY id_venta DESC LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();
    $next = 1;
    if ($last && preg_match('/-(\d+)$/', $last, $m)) {
        $next = ((int) $m[1]) + 1;
    }
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

/**
 * Formatea un timestamp de la BD (guardado en UTC) como fecha d/m/y en
 * la zona horaria de negocio (America/Guatemala).
 */
function fechaGT(?string $fecha, string $formato = 'd/m/Y'): string {
    if (!$fecha) return '';
    try {
        $d = new DateTime($fecha, new DateTimeZone('UTC'));
    } catch (Exception $e) {
        return $fecha;
    }
    $d->setTimezone(new DateTimeZone('America/Guatemala'));
    return $d->format($formato);
}

/**
 * Maps a payment method to the formas_pago.tipo_pago ENUM value.
 * formas_pago accepts: Efectivo, Tarjeta Débito, Tarjeta Crédito, Transferencia, Cheque, Seguro Médico
 */
function formaPagoDesdeMetodo(string $metodoPago): string {
    switch ($metodoPago) {
        case 'COD':
            return 'Efectivo';
        case 'Tarjeta':
            return 'Tarjeta Crédito';
        case 'Transferencia':
            return 'Transferencia';
        case 'Efectivo':
        default:
            return 'Efectivo';
    }
}

/**
 * Indica si un usuario tiene permiso de acceso al módulo de Reportes.
 * Basado en usuarios.permiso_reportes (1 = permitido, 0 = denegado).
 */
function tienePermisoReportes(PDO $conn, int $idUsuario): bool {
    $stmt = $conn->prepare("SELECT permiso_reportes FROM usuarios WHERE id = ?");
    $stmt->execute([$idUsuario]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Agrega a una tabla las columnas que falten (migración automática, como en el
 * resto del sistema). $columnas = ['nombre_columna' => 'definición SQL'].
 */
function asegurarColumnas(PDO $conn, string $tabla, array $columnas): void {
    static $revisadas = [];
    if (isset($revisadas[$tabla])) return;
    $stmt = $conn->prepare("
        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->execute([$tabla]);
    $existentes = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
    foreach ($columnas as $nombre => $definicion) {
        if (!in_array(strtolower($nombre), $existentes, true)) {
            $conn->exec("ALTER TABLE `$tabla` ADD COLUMN `$nombre` $definicion");
        }
    }
    $revisadas[$tabla] = true;
}

/** Columnas del descuento especial (precios especiales para clientes) en pedidos. */
function asegurarColumnasDescuentoEspecial(PDO $conn): void {
    asegurarColumnas($conn, 'pedidos', [
        'descuento_especial'        => "DECIMAL(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Monto del descuento especial (sí se rebaja del total)'",
        'descuento_especial_tipo'   => "ENUM('porcentaje','monto') NOT NULL DEFAULT 'porcentaje'",
        'descuento_especial_valor'  => "DECIMAL(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Porcentaje o monto ingresado'",
        'descuento_especial_motivo' => "VARCHAR(150) NULL DEFAULT NULL",
    ]);
}

/** Columnas del visitador médico de cada proveedor. */
function asegurarColumnasVisitador(PDO $conn): void {
    asegurarColumnas($conn, 'proveedores', [
        'visitador_nombre'      => "VARCHAR(150) NULL DEFAULT NULL",
        'visitador_telefono'    => "VARCHAR(20) NULL DEFAULT NULL",
        'visitador_actualizado' => "DATETIME NULL DEFAULT NULL",
    ]);
}

/**
 * Calcula el descuento especial (precio especial para un cliente).
 * A diferencia del descuento por método de pago, este SÍ se rebaja del total.
 *
 * @return array{monto:float,tipo:string,valor:float}
 */
function calcularDescuentoEspecial(float $subtotal, string $tipo, $valor): array {
    $tipo  = ($tipo === 'monto') ? 'monto' : 'porcentaje';
    $valor = max(0.0, round((float) $valor, 2));
    if ($tipo === 'porcentaje') {
        $valor = min($valor, 100.0);
        $monto = round($subtotal * $valor / 100, 2);
    } else {
        $monto = $valor;
    }
    $monto = min(max(0.0, $monto), round($subtotal, 2));
    return ['monto' => $monto, 'tipo' => $tipo, 'valor' => $valor];
}

/**
 * Clave para agrupar lotes del mismo medicamento en una sucursal:
 * por código de medicamento; si no tiene código, por nombre + presentación + casa.
 */
function claveGrupoInventario(array $lote): string {
    $codigo = trim((string) ($lote['codigo_medicamento'] ?? ''));
    $base = $codigo !== ''
        ? 'C:' . mb_strtolower($codigo, 'UTF-8')
        : 'N:' . mb_strtolower(trim((string) ($lote['nom_medicamento'] ?? '')) . '|'
            . trim((string) ($lote['presentacion_med'] ?? '')) . '|'
            . trim((string) ($lote['casa_farmaceutica'] ?? '')), 'UTF-8');
    return ($lote['id_sucursal'] ?? '0') . '|' . $base;
}

/**
 * Junta los lotes disponibles en una sola opción por medicamento (para los
 * buscadores de Pedidos y Despacho). Cada opción toma los datos del lote que
 * vence primero y la existencia total del grupo.
 */
function agruparInventarioDisponible(array $lotes): array {
    $grupos = [];
    foreach ($lotes as $l) {
        $k = claveGrupoInventario($l);
        if (!isset($grupos[$k])) {
            $grupos[$k] = $l;
            $grupos[$k]['cantidad_med'] = 0;
            $grupos[$k]['lotes_count'] = 0;
            $grupos[$k]['_venc'] = null;
        }
        $grupos[$k]['cantidad_med'] += (int) $l['cantidad_med'];
        $grupos[$k]['lotes_count']++;
        // El lote "representante" es el que vence primero (sin fecha al final)
        $v = $l['fecha_vencimiento'] ?: '9999-12-31';
        if ($grupos[$k]['_venc'] === null || $v < $grupos[$k]['_venc']) {
            $cant = $grupos[$k]['cantidad_med'];
            $cnt = $grupos[$k]['lotes_count'];
            $grupos[$k] = array_merge($grupos[$k], $l);
            $grupos[$k]['cantidad_med'] = $cant;
            $grupos[$k]['lotes_count'] = $cnt;
            $grupos[$k]['_venc'] = $v;
        }
    }
    foreach ($grupos as &$g) unset($g['_venc']);
    unset($g);
    $res = array_values($grupos);
    usort($res, fn($a, $b) => strcasecmp((string) $a['nom_medicamento'], (string) $b['nom_medicamento']));
    return $res;
}

/**
 * Descuenta $cantidad unidades del medicamento al que pertenece el lote
 * $idInventario, repartiendo entre los lotes de la misma sucursal y el mismo
 * código, empezando por el que vence primero (FEFO).
 * Debe llamarse dentro de una transacción.
 *
 * @return array<int,array{id_inventario:int,cantidad:int}> lotes usados y cuánto de cada uno
 */
function descontarInventarioFEFO(PDO $conn, int $idInventario, int $cantidad, string $nombre = ''): array {
    if ($cantidad <= 0) return [];
    $stmt = $conn->prepare("SELECT * FROM inventario WHERE id_inventario = ? FOR UPDATE");
    $stmt->execute([$idInventario]);
    $ref = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ref) throw new Exception('Producto no encontrado en inventario: ' . $nombre);

    $codigo = trim((string) ($ref['codigo_medicamento'] ?? ''));
    if ($codigo !== '') {
        $where = "codigo_medicamento = ?";
        $params = [$codigo];
    } else {
        $where = "(codigo_medicamento IS NULL OR codigo_medicamento = '') AND nom_medicamento = ?
                  AND presentacion_med <=> ? AND casa_farmaceutica <=> ?";
        $params = [$ref['nom_medicamento'], $ref['presentacion_med'], $ref['casa_farmaceutica']];
    }
    $params[] = $ref['id_sucursal'];
    $stmt = $conn->prepare("
        SELECT id_inventario, cantidad_med FROM inventario
        WHERE $where AND id_sucursal <=> ? AND estado_ingreso = 'Ingresado' AND cantidad_med > 0
        ORDER BY (fecha_vencimiento IS NULL), fecha_vencimiento ASC, id_inventario ASC
        FOR UPDATE
    ");
    $stmt->execute($params);
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $disponible = array_sum(array_map(fn($l) => (int) $l['cantidad_med'], $lotes));
    if ($disponible < $cantidad) {
        throw new Exception(sprintf('Stock insuficiente para: %s (disponible: %d)',
            $nombre !== '' ? $nombre : $ref['nom_medicamento'], $disponible));
    }

    $upd = $conn->prepare("UPDATE inventario SET cantidad_med = cantidad_med - ? WHERE id_inventario = ? AND cantidad_med >= ?");
    $usados = [];
    $falta = $cantidad;
    foreach ($lotes as $l) {
        if ($falta <= 0) break;
        $toma = min($falta, (int) $l['cantidad_med']);
        $upd->execute([$toma, $l['id_inventario'], $toma]);
        if ($upd->rowCount() === 0) throw new Exception('Stock insuficiente para: ' . ($nombre ?: $ref['nom_medicamento']));
        $usados[] = ['id_inventario' => (int) $l['id_inventario'], 'cantidad' => $toma];
        $falta -= $toma;
    }
    return $usados;
}

/** Reparte un subtotal entre varias cantidades (el último lleva el ajuste de centavos). */
function repartirSubtotal(array $usados, float $precio, float $subtotal): array {
    $acum = 0.0;
    $n = count($usados);
    foreach ($usados as $i => &$u) {
        $u['subtotal'] = ($i === $n - 1) ? round($subtotal - $acum, 2) : round($u['cantidad'] * $precio, 2);
        $acum += $u['subtotal'];
    }
    unset($u);
    return $usados;
}
