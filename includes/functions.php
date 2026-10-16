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
