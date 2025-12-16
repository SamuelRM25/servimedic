<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

// Función para calcular edad
function calcularEdad($fechaNacimiento) {
    $nacimiento = new DateTime($fechaNacimiento);
    $hoy = new DateTime();
    return $hoy->diff($nacimiento)->y;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener datos del usuario
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRol = $userData['rol'] ?? '';
    
    // Obtener pacientes (si es doctor, solo sus pacientes)
    if ($userRol === 'Doctor') {
        $stmt = $conn->prepare("
            SELECT p.*, u.nombre as medico_nombre, u.apellido as medico_apellido, s.nombre as sucursal_nombre
            FROM pacientes p
            LEFT JOIN usuarios u ON p.id_medico = u.id
            LEFT JOIN sucursales s ON p.id_sucursal = s.id_sucursal
            WHERE p.id_medico = ?
            ORDER BY p.fecha_registro DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        $stmt = $conn->prepare("
            SELECT p.*, u.nombre as medico_nombre, u.apellido as medico_apellido, s.nombre as sucursal_nombre
            FROM pacientes p
            LEFT JOIN usuarios u ON p.id_medico = u.id
            LEFT JOIN sucursales s ON p.id_sucursal = s.id_sucursal
            ORDER BY p.fecha_registro DESC
        ");
        $stmt->execute();
    }
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ==========================================
    // DOCTOR WAITING ROOM (Sala de Espera)
    // ==========================================
    $waitingPatients = [];
    if ($userRol === 'Doctor' || $userRol === 'Administrador') { // Admin sees all or maybe just useful for testing
        $sqlWaiting = "
            SELECT o.*, p.nombre as nombre_paciente, p.apellido as apellido_paciente, p.fecha_nacimiento
            FROM ordenes_cobro o
            JOIN pacientes p ON o.id_paciente = p.id_paciente
            WHERE o.estado = 'Pagado' 
            AND o.fecha_pago >= CURDATE()
            ORDER BY o.fecha_pago ASC
        ";
        // If doctor, filter by their ID
        if ($userRol === 'Doctor') {
             $sqlWaiting = "
                SELECT o.*, p.nombre as nombre_paciente, p.apellido as apellido_paciente, p.fecha_nacimiento
                FROM ordenes_cobro o
                JOIN pacientes p ON o.id_paciente = p.id_paciente
                WHERE o.estado = 'Pagado' 
                AND o.id_medico = ?
                AND o.fecha_pago >= CURDATE()
                ORDER BY o.fecha_pago ASC
            ";
            $stmtWait = $conn->prepare($sqlWaiting);
            $stmtWait->execute([$_SESSION['user_id']]);
        } else {
            $stmtWait = $conn->query($sqlWaiting);
        }
        $waitingPatients = $stmtWait->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Attended Patients (Today)
        $sqlAttended = "
            SELECT o.*, p.nombre as nombre_paciente, p.apellido as apellido_paciente, p.fecha_nacimiento, p.tipo_paciente
            FROM ordenes_cobro o
            JOIN pacientes p ON o.id_paciente = p.id_paciente
            WHERE o.estado = 'Atendido' 
            AND o.fecha_pago >= CURDATE()
            ORDER BY o.fecha_pago DESC
        ";
        // Filter by doctor if needed, but usually seeing all attended today is fine or filter same as above
        if ($userRol === 'Doctor') {
             $sqlAttended = "
                SELECT o.*, p.nombre as nombre_paciente, p.apellido as apellido_paciente, p.fecha_nacimiento, p.tipo_paciente
                FROM ordenes_cobro o
                JOIN pacientes p ON o.id_paciente = p.id_paciente
                WHERE o.estado = 'Atendido' 
                AND o.id_medico = ?
                AND o.fecha_pago >= CURDATE()
                ORDER BY o.fecha_pago DESC
            ";
            $stmtAttended = $conn->prepare($sqlAttended);
            $stmtAttended->execute([$_SESSION['user_id']]);
        } else {
            $stmtAttended = $conn->query($sqlAttended);
        }
        $attendedPatients = $stmtAttended->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Obtener lista de doctores para el select
    $stmtDoctores = $conn->prepare("SELECT id, nombre, apellido FROM usuarios WHERE rol = 'Doctor'");
    $stmtDoctores->execute();
    $doctores = $stmtDoctores->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pacientes - Servimedic</title>
    
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

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--color-orange), #E85A2B);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
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
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-privado { background: #BEE3F8; color: #2C5282; }
        .badge-igs { background: #C6F6D5; color: #22543D; }
        .badge-epss { background: #D6BCFA; color: #44337A; }
        .badge-mawdy { background: #FEFCBF; color: #744210; }
        
        .badge-domicilio { background: #FED7D7; color: #742A2A; }
        .badge-reconsulta { background: #C6F6D5; color: #22543D; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: var(--color-white);
            border-radius: 20px;
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--color-text);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--color-border);
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: var(--font-family);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-blue);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: var(--color-bg);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .checkbox-group:hover {
            background: #E2E8F0;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-group label {
            cursor: pointer;
            font-size: 0.9rem;
            margin: 0;
        }

        @media (max-width: 768px) {
            .form-row, .form-row-3 {
                grid-template-columns: 1fr;
            }
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
                <h1 class="page-title">Gestión de Pacientes</h1>
            </div>
            <?php include '../../includes/clock.php'; ?>
            <button class="btn btn-primary" onclick="printDailyReport()">
                <i class="bi bi-file-earmark-text"></i>
                Reporte Diario
            </button>
            <button class="btn btn-primary" onclick="openNewPatientModal()">
                <i class="bi bi-person-plus"></i>
                Nuevo Paciente
            </button>
        </div>

        <!-- WAITING ROOM (SALA DE ESPERA) -->
        <?php if (!empty($waitingPatients)): ?>
        <div class="card mb-4" style="border-left: 5px solid var(--color-success); background: white; padding: 1.5rem; border-radius: 16px; margin-bottom: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid #eee; padding-bottom: 1rem;">
                <div class="card-title" style="color: #276749; font-weight: 700; font-size: 1.1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="bi bi-stopwatch-fill"></i> Sala de Espera - Pacientes Listos
                </div>
                <div class="search-box" style="padding: 0.25rem 0.75rem; background: #C6F6D5; border: none;">
                    <span class="badge" style="background: transparent; color: #22543D; padding: 0; font-size: 0.9rem;">
                        <?php echo count($waitingPatients); ?> paciente(s) en espera
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table" style="width: 100%;">
                        <thead>
                            <tr style="background: #F0FFF4;">
                                <th style="color: #276749; padding: 0.75rem;">Hora Pago</th>
                                <th style="color: #276749; padding: 0.75rem;">Paciente</th>
                                <th style="color: #276749; padding: 0.75rem;">Motivo/Servicio</th>
                                <th style="color: #276749; padding: 0.75rem;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($waitingPatients as $wp): ?>
                            <tr>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #eee;"><?php echo date('H:i', strtotime($wp['fecha_pago'])); ?></td>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #eee;">
                                    <strong><?php echo htmlspecialchars($wp['nombre_paciente'] . ' ' . $wp['apellido_paciente']); ?></strong>
                                    <br>
                                    <small class="text-muted" style="color: #718096;"><?php echo calcularEdad($wp['fecha_nacimiento']); ?> años</small>
                                </td>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($wp['tipo_cobro']); ?></td>
                                <td style="padding: 0.75rem; border-bottom: 1px solid #eee;">
                                    <button class="btn btn-success" onclick="openPrescriptionModal(<?php echo $wp['id_orden']; ?>, '<?php echo htmlspecialchars($wp['nombre_paciente'] . ' ' . $wp['apellido_paciente']); ?>')" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">
                                        <i class="bi bi-journal-medical"></i> Atender
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Prescription Modal -->
        <div class="modal-overlay" id="prescriptionModal">
            <div class="modal" style="max-width: 800px;">
                <div class="modal-header">
                    <h2 class="modal-title">Recetar Medicamento - <span id="prescPatientName"></span></h2>
                    <button class="modal-close" onclick="closeModal('prescriptionModal')">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div style="margin-bottom: 1rem; display: flex; justify-content: flex-end;">
                        <button type="button" class="btn btn-primary" onclick="addMedicineRow()">
                            <i class="bi bi-plus-circle"></i> Agregar Medicamento
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table" id="prescriptionTable">
                            <thead>
                                <tr>
                                    <th>Medicamento</th>
                                    <th style="width: 100px;">Cantidad</th>
                                    <th>Dosis / Instrucciones</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Dynamic Rows -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 1rem; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('prescriptionModal')">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="finalizeConsultation()">
                        <i class="bi bi-check-circle"></i> Finalizar
                    </button>
                </div>
            </div>
        </div>

        <!-- ATTENDED PATIENTS (PACIENTES ATENDIDOS) -->
        <?php if (!empty($attendedPatients)): ?>
        <div class="card mb-4 mt-4" id="attendedSection">
            <div class="card-header">
                <div class="card-title">
                    <i class="bi bi-check-circle-fill"></i> Pacientes Atendidos Hoy
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Tipo</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendedPatients as $ap): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ap['nombre_paciente'] . ' ' . $ap['apellido_paciente']); ?></td>
                                <td><span class="badge badge-<?php echo strtolower($ap['tipo_paciente']); ?>"><?php echo $ap['tipo_paciente']; ?></span></td>
                                <td><?php echo htmlspecialchars($ap['descripcion'] ?? $ap['tipo_cobro']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Hidden Report Section for Printing -->
        <div id="printReport" style="display: none;">
            <style>
                @media print {
                    @page { margin: 0; size: auto; }
                    body { margin: 0; padding: 0; font-family: 'Roboto', sans-serif; background: white; }
                    #printReport { display: block !important; padding: 40px; }
                    /* Hide everything else */
                    .sidebar, .main-content > *:not(#printReport), header, .btn, .no-print { display: none !important; }
                    .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
                    
                    /* Report Styles */
                    .report-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        border-bottom: 2px solid #2c3e50;
                        padding-bottom: 20px;
                        margin-bottom: 30px;
                    }
                    .company-info h1 { margin: 0; color: #2c3e50; font-size: 28px; letter-spacing: 1px; }
                    .report-meta { text-align: right; color: #34495e; }
                    .report-date { color: #e67e22; font-weight: bold; font-size: 16px; }
                    
                    .summary-grid { display: flex; gap: 20px; margin-bottom: 30px; }
                    .summary-box { flex: 1; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; text-align: center; }
                    .summary-label { font-size: 11px; text-transform: uppercase; color: #718096; letter-spacing: 0.5px; margin-bottom: 5px; }
                    .summary-value { font-size: 24px; font-weight: 700; color: #2d3748; }
                    
                    table { width: 100%; border-collapse: collapse; margin-bottom: 40px; font-size: 12px; }
                    th { background: #2c3e50; color: white; padding: 10px; text-align: left; text-transform: uppercase; }
                    td { padding: 10px; border-bottom: 1px solid #edf2f7; color: #4a5568; }
                    tr:nth-child(even) { background-color: #f7fafc; }
                    
                    .footer-signatures { display: flex; justify-content: space-between; margin-top: 60px; }
                    .signature-block { width: 40%; text-align: center; border-top: 1px solid #2d3748; padding-top: 10px; font-size: 12px; font-weight: bold; color: #2d3748; }
                }
            </style>
            
            <!-- Header -->
            <div class="report-header">
                <div class="company-info">
                    <h1>SERVIMEDIC</h1>
                    <div style="font-size: 11px; color: #718096; margin-top: 5px;">Sistema de Gestión Médica Integral</div>
                    <div style="font-size: 10px; color: #a0aec0;">8va calle 10-21 zona 5 | PBX: 3404 9600</div>
                </div>
                <div class="report-meta">
                    <div class="report-date"><?php echo date('d/m/Y'); ?></div>
                    <div style="font-size: 14px; font-weight: 600;">REPORTE DE PACIENTES</div>
                </div>
            </div>

            <!-- Doctor Info -->
            <div style="margin-bottom: 20px; background: #f8fafc; padding: 15px; border-left: 4px solid #4299e1;">
                <div style="font-size: 11px; color: #718096;">MÉDICO TRATANTE</div>
                <div style="font-size: 16px; font-weight: 700; color: #2c5282;">
                    Dr(a). <?php echo htmlspecialchars($userData['nombre'] . ' ' . $userData['apellido']); ?>
                </div>
            </div>
            
            <!-- Summary Blocks -->
            <?php 
            $stats = ['EPS' => 0, 'IGS' => 0, 'MAWDY' => 0, 'Privado' => 0];
            foreach ($attendedPatients as $ap) {
                $tipo = $ap['tipo_paciente'];
                if(isset($stats[$tipo])) $stats[$tipo]++;
                else $stats['Privado']++;
            }
            ?>
            <div class="summary-grid">
                <div class="summary-box">
                    <div class="summary-label">Total Pacientes</div>
                    <div class="summary-value" style="color: #4299e1;"><?php echo count($attendedPatients); ?></div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Privados</div>
                    <div class="summary-value"><?php echo $stats['Privado']; ?></div>
                </div>
                <div class="summary-box">
                    <div class="summary-label">Seguros (EPS/IGS/Mawdy)</div>
                    <div class="summary-value"><?php echo $stats['EPS'] + $stats['IGS'] + $stats['MAWDY']; ?></div>
                </div>
            </div>
            
            <!-- Detailed Table -->
            <table>
                <thead>
                    <tr>
                        <th style="width: 40%;">Paciente</th>
                        <th style="width: 20%;">Tipo</th>
                        <th style="width: 40%;">Servicio / Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendedPatients)): ?>
                    <tr><td colspan="3" style="text-align: center; padding: 20px;">No hay pacientes atendidos hoy.</td></tr>
                    <?php else: ?>
                        <?php foreach ($attendedPatients as $ap): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($ap['nombre_paciente'] . ' ' . $ap['apellido_paciente']); ?></strong>
                            </td>
                            <td>
                                <span style="
                                    padding: 2px 6px; 
                                    border-radius: 4px; 
                                    font-size: 10px; 
                                    font-weight: 600;
                                    background: <?php echo $ap['tipo_paciente'] == 'Privado' ? '#e2e8f0' : '#ebf8ff'; ?>;
                                ">
                                    <?php echo htmlspecialchars($ap['tipo_paciente']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($ap['descripcion'] ?? $ap['tipo_cobro']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Signatures -->
            <div class="footer-signatures">
                <div class="signature-block">
                    Firma del Médico
                    <div style="font-weight: 400; margin-top: 4px;">Dr(a). <?php echo htmlspecialchars($userData['nombre'] . ' ' . $userData['apellido']); ?></div>
                </div>
                <div class="signature-block">
                    Sello de Clínica
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 40px; font-size: 10px; color: #cbd5e0;">
                Generado por Servimedic &bull; <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Pacientes</div>
                <div class="stat-value"><?php echo count($pacientes); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Con Reconsulta Gratis</div>
                <div class="stat-value">
                    <?php echo count(array_filter($pacientes, fn($p) => $p['tiene_reconsulta_gratis'] == 1)); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Consultas a Domicilio</div>
                <div class="stat-value">
                    <?php echo count(array_filter($pacientes, fn($p) => $p['consulta_domicilio'] == 1)); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pacientes IGS/EPSS/Mawdy</div>
                <div class="stat-value">
                    <?php echo count(array_filter($pacientes, fn($p) => $p['tipo_paciente'] !== 'Privado')); ?>
                </div>
            </div>
        </div>

        <!-- Search -->
        <div class="search-section">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Buscar por nombre, apellido, teléfono o DPI...">
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Paciente</th>
                            <th>Edad</th>
                            <th>Género</th>
                            <th>Tipo</th>
                            <th>Teléfono</th>
                            <th>DPI</th>
                            <th>Opciones</th>
                            <th>Doctor</th>
                            <th>Registro</th>
                        </tr>
                    </thead>
                    <tbody id="pacientesTable">
                        <?php foreach ($pacientes as $paciente): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido']); ?></strong>
                                <?php if ($paciente['consulta_domicilio']): ?>
                                <br><span class="badge badge-domicilio"><i class="bi bi-house"></i> Domicilio</span>
                                <?php endif; ?>
                                <?php if ($paciente['tiene_reconsulta_gratis']): ?>
                                <span class="badge badge-reconsulta"><i class="bi bi-check-circle"></i> Reconsulta</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo calcularEdad($paciente['fecha_nacimiento']); ?> años</td>
                            <td><?php echo $paciente['genero']; ?></td>
                            <td>
                                <?php 
                                $tipoClass = 'badge-privado';
                                if ($paciente['tipo_paciente'] === 'IGS') $tipoClass = 'badge-igs';
                                if ($paciente['tipo_paciente'] === 'EPSS') $tipoClass = 'badge-epss';
                                if ($paciente['tipo_paciente'] === 'Mawdy') $tipoClass = 'badge-mawdy';
                                ?>
                                <span class="badge <?php echo $tipoClass; ?>">
                                    <?php echo $paciente['tipo_paciente']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($paciente['telefono'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($paciente['dpi'] ?? '-'); ?></td>
                            <td style="display: flex; gap: 0.5rem;">
                                <button onclick="openChargeModal(<?php echo $paciente['id_paciente']; ?>, '<?php echo htmlspecialchars($paciente['nombre']); ?>', '<?php echo htmlspecialchars($paciente['apellido']); ?>')" 
                                        class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; background: var(--color-success);">
                                    <i class="bi bi-cash"></i> Cobrar
                                </button>
                                <?php if ($paciente['tiene_reconsulta_gratis'] && $paciente['fecha_reconsulta_limite']): ?>
                                <div style="margin-top: 0.25rem;">
                                    <small style="color: var(--color-success); font-size: 0.7rem;">
                                        Reconsulta: <?php echo date('d/m/y', strtotime($paciente['fecha_reconsulta_limite'])); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($paciente['medico_nombre']): ?>
                                <small>Dr. <?php echo htmlspecialchars($paciente['medico_nombre'] . ' ' . $paciente['medico_apellido']); ?></small>
                                <?php else: ?>
                                <small style="color: var(--color-text-light);">Sin asignar</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($paciente['fecha_registro'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- New Patient Modal -->
    <div class="modal-overlay" id="newPatientModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Nuevo Paciente</h2>
                <button class="modal-close" onclick="closeModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form action="save_patient.php" method="POST">
                <div class="modal-body">
                    <h4 style="margin-bottom: 1rem; color: var(--color-text);">Información Personal</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Apellido *</label>
                            <input type="text" name="apellido" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">Fecha Nacimiento *</label>
                            <input type="date" name="fecha_nacimiento" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Género *</label>
                            <select name="genero" class="form-control" required>
                                <option value="">Seleccionar...</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">DPI</label>
                            <input type="text" name="dpi" class="form-control" placeholder="1234567890101">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="telefono" class="form-control" placeholder="1234-5678">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Correo</label>
                            <input type="email" name="correo" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Dirección</label>
                        <textarea name="direccion" class="form-control" rows="2"></textarea>
                    </div>

                    <h4 style="margin: 1.5rem 0 1rem; color: var(--color-text);">Tipo de Paciente y Opciones</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tipo de Paciente *</label>
                            <select name="tipo_paciente" class="form-control" required>
                                <option value="Privado">Privado</option>
                                <option value="IGS">IGS</option>
                                <option value="EPSS">EPSS</option>
                                <option value="Mawdy">Mawdy</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Doctor Asignado</label>
                            <select name="id_medico" class="form-control">
                                <option value="">Sin asignar</option>
                                <?php foreach ($doctores as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    Dr. <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="consulta_domicilio" id="consultaDomicilio" value="1">
                                <label for="consultaDomicilio">
                                    <i class="bi bi-house"></i> Requiere Consulta a Domicilio
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="tiene_reconsulta_gratis" id="reconsultaGratis" value="1">
                                <label for="reconsultaGratis">
                                    <i class="bi bi-check-circle"></i> Tiene Derecho a Reconsulta Gratis
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" id="fechaReconsultaGroup" style="display: none;">
                        <label class="form-label">Fecha Límite para Reconsulta</label>
                        <input type="date" name="fecha_reconsulta_limite" id="fechaReconsulta" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div style="padding: 1.5rem; border-top: 1px solid var(--color-border);">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="bi bi-save"></i>
                        Guardar Paciente
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openNewPatientModal() {
            document.getElementById('newPatientModal').classList.add('show');
        }

        function closeModal(modalId) {
            const id = modalId || 'newPatientModal';
            document.getElementById(id).classList.remove('show');
        }

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const tableRows = document.querySelectorAll('#pacientesTable tr');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();

            tableRows.forEach(row => {
                const paciente = row.cells[0].textContent.toLowerCase();
                const telefono = row.cells[4].textContent.toLowerCase();
                const dpi = row.cells[5].textContent.toLowerCase();

                const matches = paciente.includes(searchTerm) ||
                               telefono.includes(searchTerm) ||
                               dpi.includes(searchTerm);

                row.style.display = matches ? '' : 'none';
            });
        });

        // Show/hide fecha reconsulta based on checkbox
        const reconsultaCheckbox = document.getElementById('reconsultaGratis');
        const fechaReconsultaGroup = document.getElementById('fechaReconsultaGroup');
        const fechaReconsultaInput = document.getElementById('fechaReconsulta');

        reconsultaCheckbox.addEventListener('change', function() {
            if (this.checked) {
                fechaReconsultaGroup.style.display = 'block';
                // Set default date to 30 days from now
                const futureDate = new Date();
                futureDate.setDate(futureDate.getDate() + 30);
                const localDate = futureDate.getFullYear() + '-' + String(futureDate.getMonth() + 1).padStart(2, '0') + '-' + String(futureDate.getDate()).padStart(2, '0');
                fechaReconsultaInput.value = localDate;
            } else {
                fechaReconsultaGroup.style.display = 'none';
                fechaReconsultaInput.value = '';
            }
        });

        // Close on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });
    </script>
    <!-- Assign Charge Modal -->
    <div class="modal-overlay" id="chargeModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h2 class="modal-title">Asignar Cobro</h2>
                <button class="modal-close" onclick="closeModal('chargeModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="chargeForm">
                <input type="hidden" name="id_paciente" id="charge_id_paciente">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Paciente</label>
                        <input type="text" id="charge_paciente_nombre" class="form-control" readonly style="background: #f7fafc;">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de Cobro *</label>
                        <select name="tipo_cobro" class="form-control" required>
                            <option value="EPS">EPS</option>
                            <option value="IGS">IGS</option>
                            <option value="MAWDY">MAWDY</option>
                            <option value="PRIVADO">PRIVADO</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Monto (Q) *</label>
                        <input type="number" name="monto" class="form-control" step="0.01" min="0" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descripción / Observaciones</label>
                        <textarea name="descripcion" class="form-control" rows="3" placeholder="Detalles adicionales..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('chargeModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cobro</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openChargeModal(id, nombre, apellido) {
            document.getElementById('charge_id_paciente').value = id;
            document.getElementById('charge_paciente_nombre').value = nombre + ' ' + apellido;
            document.getElementById('chargeModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });

        // Handle Charge Form Submit
        document.getElementById('chargeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('save_charge.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Cobro asignado correctamente');
                    closeModal('chargeModal');
                    this.reset();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error(error);
                alert('Error al guardar el cobro');
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // ... (Existing scripts) 

        function attendPatient(idPaciente) {
            // Logic to open patient medical history or start consultation
            window.location.href = 'history.php?id=' + idPaciente;
        }

        // Use PHP variable safely
        let lastWaitingCount = <?php echo isset($waitingPatients) ? count($waitingPatients) : 0; ?>;
        
        let currentOrderId = null;
        let currentPatientName = '';

        function openPrescriptionModal(idOrden, nombrePaciente) {
            currentOrderId = idOrden;
            currentPatientName = nombrePaciente;
            document.getElementById('prescPatientName').innerText = nombrePaciente;
            
            // Clear table
            const tbody = document.querySelector('#prescriptionTable tbody');
            tbody.innerHTML = '';
            addMedicineRow(); // Add first row
            
            document.getElementById('prescriptionModal').classList.add('show');
        }

        function addMedicineRow() {
            const tbody = document.querySelector('#prescriptionTable tbody');
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" class="form-control" name="medicamento[]" placeholder="Nombre del medicamento" required></td>
                <td><input type="number" class="form-control" name="cantidad[]" value="1" min="1" required></td>
                <td><input type="text" class="form-control" name="dosis[]" placeholder="Cada 8 horas..." required></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>
            `;
            tbody.appendChild(row);
        }



        function finalizeConsultation() {
            const rows = document.querySelectorAll('#prescriptionTable tbody tr');
            const items = [];
            
            rows.forEach(row => {
                const med = row.querySelector('input[name="medicamento[]"]').value;
                const qty = row.querySelector('input[name="cantidad[]"]').value;
                const dose = row.querySelector('input[name="dosis[]"]').value;
                if(med) items.push({ medicamento: med, cantidad: qty, dosis: dose });
            });

            // Logic:
            // 1. If items empty -> Just mark attended (Confirm?)
            // 2. If items exist -> Ask: Print OR Pharmacy

            if (items.length === 0) {
                Swal.fire({
                    title: 'Sin Medicamentos',
                    text: 'No ha ingresado medicamentos. ¿Desea solo finalizar la consulta?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, Finalizar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        savePrescription(items, 0, false);
                    }
                });
                return;
            }

            // Ask user action
            Swal.fire({
                title: 'Finalizar Consulta',
                text: 'Seleccione una acción para la receta:',
                icon: 'question',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-printer"></i> Imprimir',
                denyButtonText: '<i class="bi bi-shop"></i> Enviar a Farmacia',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#3085d6',
                denyButtonColor: '#28a745'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Print
                    savePrescription(items, 0, true);
                } else if (result.isDenied) {
                    // Send to Pharmacy
                    savePrescription(items, 1, false);
                }
            });
        }

        function savePrescription(items, farmaciaFlag, shouldPrint) {
            Swal.fire({
                title: 'Procesando...',
                didOpen: () => Swal.showLoading()
            });

            fetch('save_prescription.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_orden: currentOrderId,
                    items: items,
                    necesita_farmacia: farmaciaFlag
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (shouldPrint && items.length > 0) {
                        printTicket(items);
                    }
                    Swal.fire('Éxito', 'Consulta finalizada correctamente', 'success')
                        .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }


        function printTicket(items) {
            const width = 300;
            const height = 600;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            const printWindow = window.open('', '_blank', `width=${width},height=${height},top=${top},left=${left}`);
            
            const date = new Date().toLocaleDateString();
            
            let itemsHtml = '';
            items.forEach((item, index) => {
                itemsHtml += `
                    <div style="margin-bottom: 10px; border-bottom: 1px dashed #ccc; padding-bottom: 5px;">
                        <strong>${index + 1}. ${item.medicamento}</strong> ( Cant: ${item.cantidad} )<br>
                        <small>${item.dosis}</small>
                    </div>
                `;
            });

            printWindow.document.write(`
                <html>
                <head>
                    <title>Receta Médica</title>
                    <style>
                        body { font-family: 'Courier New', monospace; font-size: 12px; padding: 10px; margin: 0; width: 280px; }
                        .header { text-align: center; margin-bottom: 20px; }
                        .info { margin-bottom: 15px; font-size: 11px; }
                        .items { margin-bottom: 20px; }
                        .footer { text-align: center; margin-top: 30px; font-size: 10px; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h3 style="margin:0;">SERVIMEDIC</h3>
                        <p style="margin:5px 0;">Receta Médica</p>
                    </div>
                    <div class="info">
                        <strong>Fecha:</strong> ${date}<br>
                        <strong>Paciente:</strong> ${currentPatientName}<br>
                        <strong>Dr:</strong> <?php echo $_SESSION['nombre'] . ' ' . $_SESSION['apellido']; ?>
                    </div>
                    <div class="items">
                        ${itemsHtml}
                    </div>
                    <div class="footer">
                        __________________________<br>
                        Firma Médico<br><br>
                        ¡Gracias por su preferencia!
                    </div>
                    <script>
                        window.print();
                        setTimeout(() => window.close(), 1000);
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }
        
        // Removed old markAsAttended as it is replaced by the prescription flow
        // function markAsAttended(idOrden) { ... }
        
        function printDailyReport() {
            // Check if there are attended patients
            const printContent = document.getElementById('printReport');
            if (!printContent) {
                 Swal.fire('Info', 'No hay pacientes atendidos hoy para generar reporte.', 'info');
                 return;
            }
            
            const originalContents = document.body.innerHTML;
            document.body.innerHTML = printContent.innerHTML;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload(); // Reload to restore events
        }

        // Real-time Polling for Waiting Room
        
        setInterval(() => {
            fetch('get_waiting_patients_count.php') // Fetch only the count
                .then(response => response.json())
                .then(data => {
                    const newWaitingCount = data.count;
                    
                    if (newWaitingCount > lastWaitingCount) {
                        // Play sound
                        new Audio('../../assets/audio/notification.mp3').play().catch(e => console.log(e));
                        
                        Swal.fire({
                            title: '¡Paciente Listo!',
                            text: 'Un paciente ha realizado su pago y está en sala de espera.',
                            icon: 'success',
                            toast: true,
                            position: 'top-end',
                            timer: 5000
                        });
                        
                        // Refresh page to show new list (or replace HTML dynamically if we want to be fancier)
                        setTimeout(() => location.reload(), 2000);
                    }
                    lastWaitingCount = newRows;
                });
        }, 10000); // Check every 10 seconds
    </script>
</body>
</html>