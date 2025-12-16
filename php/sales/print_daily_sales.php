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
    
    // Get distinct active cashiers/users for today's sales if needed, or just all sales
    $today = date('Y-m-d');
    
    // Fetch today's sales
    $stmt = $conn->prepare("
        SELECT v.*, 
               u.nombre as usuario_nombre, 
               u.apellido as usuario_apellido,
               p.cliente_nombre as pedido_cliente
        FROM ventas v
        LEFT JOIN usuarios u ON v.id_usuario = u.id
        LEFT JOIN pedidos p ON v.id_pedido = p.id_pedido
        WHERE DATE(v.fecha_venta) = CURRENT_DATE()
        ORDER BY v.fecha_venta ASC
    ");
    $stmt->execute();
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totalVentas = 0;
    $totalEfectivo = 0;
    $totalTarjeta = 0;
    
    // Note: Assuming 'metodo_pago' exists or inferred. If not, we just sum total_final.
    // Based on previous code, we might not have payment method column explicitly shown in simple view, 
    // but usually sales table has it. If not, we'll just sum total.
    
    foreach ($ventas as $v) {
        $totalVentas += $v['total_final'];
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Cierre Diario - <?php echo date('d/m/Y'); ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Font Awesome (for icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #525659; /* PDF viewer background feel */
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        #report-container {
            width: 210mm; /* A4 width */
            min-height: 297mm; /* A4 height */
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
            box-sizing: border-box;
            position: relative;
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .company-info h1 {
            color: #2c3e50;
            margin: 0;
            font-size: 28px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .company-info .subtitle {
            color: #7f8c8d;
            margin: 5px 0 0;
            font-size: 14px;
        }

        .report-meta {
            text-align: right;
            color: #34495e;
        }

        .report-meta div {
            margin-bottom: 5px;
            font-size: 13px;
        }

        .report-meta .date {
            font-size: 16px;
            font-weight: bold;
            color: #e67e22;
        }

        /* Summary Cards */
        .summary-section {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e1e4e8;
        }

        .summary-card {
            flex: 1;
            text-align: center;
            border-right: 1px solid #e1e4e8;
        }

        .summary-card:last-child {
            border-right: none;
        }

        .summary-card .label {
            font-size: 12px;
            text-transform: uppercase;
            color: #7f8c8d;
            margin-bottom: 5px;
            letter-spacing: 0.5px;
        }

        .summary-card .value {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
        }

        .summary-card .value.total {
            color: #27ae60;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 40px;
        }

        thead {
            background-color: #2c3e50;
            color: white;
        }

        thead th {
            padding: 12px 10px;
            text-align: left;
            font-weight: 500;
            text-transform: uppercase;
        }

        tbody td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            color: #444;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Footer / Signatures */
        .signatures {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }

        .signature-box {
            width: 40%;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin-bottom: 10px;
        }

        .signature-title {
            font-weight: bold;
            font-size: 12px;
            color: #2c3e50;
            text-transform: uppercase;
        }

        /* Controls */
        .controls {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .btn {
            background: #2c3e50;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
            transition: transform 0.2s;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-2px);
            background: #34495e;
        }

        .btn-pdf {
            background: #e74c3c;
        }
        
        @media print {
            body { background: none; padding: 0; }
            #report-container { box-shadow: none; width: 100%; padding: 20px; }
            .controls { display: none; }
        }
    </style>
    <!-- HTML2PDF Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>

    <div class="controls">
        <button onclick="generatePDF()" class="btn btn-pdf">
            <i class="fas fa-file-pdf"></i> Descargar PDF
        </button>
        <button onclick="window.print()" class="btn">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <button onclick="window.close()" class="btn" style="background: #95a5a6;">
            <i class="fas fa-times"></i> Cerrar
        </button>
    </div>

    <div id="report-container">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <h1>SERVIMEDIC</h1>
                <p class="subtitle">Sistema de Gestión Médica Integral</p>
                <div style="margin-top:10px; font-size:12px; color:#555;">
                    8va calle 10-21 zona 5 huehuetenango<br>
                    PBX: 3404 9600 | servimedic.com
                </div>
            </div>
            <div class="report-meta">
                <div class="date"><?php echo date('d/m/Y'); ?></div>
                <div style="font-size: 18px; font-weight: bold;">REPORTE DE CIERRE</div>
                <div>Generado por: <?php echo htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?></div>
            </div>
        </div>

        <!-- Summary -->
        <div class="summary-section">
            <div class="summary-card">
                <div class="label">Total Ventas</div>
                <div class="value total">Q<?php echo number_format($totalVentas, 2); ?></div>
            </div>
            <div class="summary-card">
                <div class="label">Transacciones</div>
                <div class="value"><?php echo count($ventas); ?></div>
            </div>
        </div>

        <!-- Table -->
        <table>
            <thead>
                <tr>
                    <th>Hora</th>
                    <th># Documento</th>
                    <th>Cliente / Paciente</th>
                    <th>Usuario</th>
                    <th>Método</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($ventas)): ?>
                <tr>
                    <td colspan="6" class="text-center" style="padding: 30px;">
                        No se han registrado ventas el día de hoy.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($ventas as $venta): ?>
                    <tr>
                        <td style="color:#7f8c8d;"><?php echo date('H:i', strtotime($venta['fecha_venta'])); ?></td>
                        <td><?php echo str_pad($venta['id_venta'], 6, '0', STR_PAD_LEFT); ?></td>
                        <td>
                            <strong>
                            <?php 
                            echo !empty($venta['pedido_cliente']) 
                                ? htmlspecialchars($venta['pedido_cliente']) 
                                : 'Cliente General'; 
                            ?>
                            </strong>
                        </td>
                        <td><?php echo htmlspecialchars($venta['usuario_nombre']); ?></td>
                        <td>
                            <?php 
                                // Logic to detect payment type if column exists, else generic
                                echo isset($venta['tipo_pago']) ? $venta['tipo_pago'] : 'Contado';
                            ?>
                        </td>
                        <td class="text-right" style="font-weight:500;">Q<?php echo number_format($venta['total_final'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Totals & Notes -->
        <div style="border-top: 2px solid #2c3e50; padding-top: 10px; margin-bottom: 40px;">
            <div style="text-align: right; font-size: 18px;">
                <span style="margin-right: 20px; font-weight: bold; color: #7f8c8d;">TOTAL CIERRE:</span>
                <span style="font-weight: bold; color: #2c3e50;">Q<?php echo number_format($totalVentas, 2); ?></span>
            </div>
        </div>

        <!-- Signatures -->
        <div class="signatures">
            <div class="signature-box">
                <div style="height: 40px;"></div>
                <div class="signature-line"></div>
                <div class="signature-title">Firma Cajero(a)</div>
                <div style="font-size: 11px; margin-top: 5px;"><?php echo htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?></div>
            </div>
            <div class="signature-box">
                <div style="height: 40px;"></div>
                <div class="signature-line"></div>
                <div class="signature-title">Firma Supervisor / Gerente</div>
            </div>
        </div>

        <div style="margin-top: 40px; text-align: center; font-size: 10px; color: #bdc3c7;">
            Este reporte fue generado automáticamente por el sistema Servimedic el <?php echo date('d/m/Y H:i:s'); ?>
        </div>
    </div>

    <script>
        function generatePDF() {
            const element = document.getElementById('report-container');
            const opt = {
                margin:       0,
                filename:     'Cierre_Diario_<?php echo date('Y-m-d'); ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2 },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            // Hide buttons just in case
            document.querySelector('.controls').style.display = 'none';

            html2pdf().set(opt).from(element).save().then(() => {
                // Show buttons back
                document.querySelector('.controls').style.display = 'flex';
            });
        }
    </script>
</body>
</html>
