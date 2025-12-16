<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->prepare("
        SELECT e.*, s.nombre as sucursal_nombre, s.direccion as sucursal_direccion, s.telefono as sucursal_telefono
        FROM examenes e
        LEFT JOIN sucursales s ON e.id_sucursal = s.id_sucursal
        WHERE e.id_examen = ?
    ");
    $stmt->execute([$_GET['id']]);
    $examen = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$examen) {
        die('Examen no encontrado');
    }
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo str_pad($examen['id_examen'], 6, '0', STR_PAD_LEFT); ?></title>
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; padding: 20px; max-width: 80mm; margin: 0 auto; }
        .ticket { border: 2px dashed #333; padding: 15px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 10px; }
        .header h1 { font-size: 18px; margin-bottom: 5px; }
        .header p { font-size: 10px; margin: 2px 0; }
        .section { margin: 10px 0; padding: 8px 0; border-bottom: 1px dashed #666; }
        .section:last-child { border-bottom: none; }
        .label { font-weight: bold; font-size: 11px; }
        .value { font-size: 11px; margin-left: 5px; }
        .total { font-size: 16px; font-weight: bold; text-align: center; margin: 15px 0; padding: 10px; background: #f0f0f0; }
        .footer { text-align: center; font-size: 10px; margin-top: 15px; }
        @media print {
            body { padding: 10px; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 14px; cursor: pointer;">
            Imprimir Ticket
        </button>
        <button onclick="window.close()" style="padding: 10px 20px; font-size: 14px; cursor: pointer; margin-left: 10px;">
            Cerrar
        </button>
    </div>

    <div class="ticket">
        <div class="header">
            <h1>SERVIMEDIC</h1>
            <p><?php echo htmlspecialchars($examen['sucursal_nombre']); ?></p>
            <p><?php echo htmlspecialchars($examen['sucursal_direccion']); ?></p>
            <p>Tel: <?php echo htmlspecialchars($examen['sucursal_telefono']); ?></p>
        </div>

        <div class="section" style="text-align: center;">
            <div class="label">COMPROBANTE DE EXAMEN</div>
            <div class="value">Ticket: <?php echo $examen['numero_ticket']; ?></div>
        </div>

        <div class="section">
            <div><span class="label">Fecha:</span><span class="value"><?php echo date('d/m/Y H:i', strtotime($examen['fecha_examen'])); ?></span></div>
            <div><span class="label">Paciente:</span><span class="value"><?php echo htmlspecialchars($examen['nombre_paciente']); ?></span></div>
            <div><span class="label">Tipo:</span><span class="value"><?php echo $examen['tipo_paciente']; ?></span></div>
        </div>

        <div class="section">
            <div class="label">EXÁMENES REALIZADOS:</div>
            <div class="value"><?php echo htmlspecialchars($examen['examenes_realizados']); ?></div>
        </div>

        <div class="section">
            <div><span class="label">Tipo de Pago:</span><span class="value"><?php echo $examen['tipo_pago']; ?></span></div>
            <div><span class="label">Método:</span><span class="value"><?php echo $examen['metodo_pago']; ?></span></div>
        </div>

        <div class="total">
            TOTAL: Q<?php echo number_format($examen['monto'], 2); ?>
        </div>

        <?php if ($examen['observaciones']): ?>
        <div class="section">
            <div class="label">Observaciones:</div>
            <div class="value"><?php echo htmlspecialchars($examen['observaciones']); ?></div>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>Gracias por su visita</p>
            <p>Este es su comprobante de pago</p>
            <p><?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>

    <script>
        // Auto print on load (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
