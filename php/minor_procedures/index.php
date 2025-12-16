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
    
    // Obtener datos del usuario
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $userSucursal = $userData['id_sucursal'] ?? 1;
    
    // Obtener pacientes
    $stmt = $conn->prepare("SELECT id_paciente, CONCAT(nombre, ' ', apellido) as nombre_completo, tipo_paciente FROM pacientes ORDER BY nombre, apellido");
    $stmt->execute();
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener procedimientos recientes
    $stmt = $conn->prepare("
        SELECT p.*, u.nombre as usuario_nombre, s.nombre as sucursal_nombre
        FROM procedimientos_menores p
        LEFT JOIN usuarios u ON p.id_usuario = u.id
        LEFT JOIN sucursales s ON p.id_sucursal = s.id_sucursal
        ORDER BY p.fecha_procedimiento DESC
        LIMIT 50
    ");
    $stmt->execute();
    $procedimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $pacientes = [];
    $procedimientos = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Procedimientos Menores - Servimedic</title>
    
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

        .btn-secondary {
            background: var(--color-text-light);
            color: white;
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

        .form-card {
            background: var(--color-white);
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--color-border);
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
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-privado { background: #BEE3F8; color: #2C5282; }
        .badge-eps { background: #C6F6D5; color: #22543D; }
        .badge-seguro { background: #FEFCBF; color: #744210; }

        @media (max-width: 768px) {
            .form-row {
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
                <h1 class="page-title">Procedimientos Menores</h1>
            </div>
            <?php include '../../includes/clock.php'; ?>
            <div style="display: flex; gap: 0.75rem;">
                <a href="reportes.php" class="btn btn-secondary">
                    <i class="bi bi-file-earmark-bar-graph"></i>
                    Reportes
                </a>
                <button class="btn btn-primary" onclick="window.location.reload()">
                    <i class="bi bi-plus-circle"></i>
                    Nuevo Procedimiento
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Procedimientos</div>
                <div class="stat-value"><?php echo count($procedimientos); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pacientes Privados</div>
                <div class="stat-value">
                    <?php echo count(array_filter($procedimientos, fn($p) => $p['tipo_paciente'] === 'Privado')); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pacientes EPS</div>
                <div class="stat-value">
                    <?php echo count(array_filter($procedimientos, fn($p) => $p['tipo_paciente'] === 'EPS')); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Cobrado</div>
                <div class="stat-value">
                    Q<?php echo number_format(array_sum(array_column($procedimientos, 'monto')), 2); ?>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="form-card">
            <h3 class="form-section-title">Registrar Nuevo Procedimiento Menor</h3>
            <form action="save_procedure.php" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Paciente *</label>
                        <select name="id_paciente" id="pacienteSelect" class="form-control" required>
                            <option value="">Seleccionar paciente...</option>
                            <?php foreach ($pacientes as $p): ?>
                            <option value="<?php echo $p['id_paciente']; ?>" 
                                    data-nombre="<?php echo htmlspecialchars($p['nombre_completo']); ?>"
                                    data-tipo="<?php echo $p['tipo_paciente']; ?>">
                                <?php echo htmlspecialchars($p['nombre_completo']); ?> 
                                (<?php echo $p['tipo_paciente']; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="nombre_paciente" id="nombrePaciente">
                        <input type="hidden" name="tipo_paciente" id="tipoPaciente">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo de Pago *</label>
                        <select name="tipo_pago" class="form-control" required>
                            <option value="Privado">Pago Privado</option>
                            <option value="Seguro Manual">Seguro (Manual)</option>
                            <option value="EPS">EPS</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Procedimiento Realizado *</label>
                    <select name="procedimiento_realizado" class="form-control" required>
                        <option value="">Seleccionar procedimiento...</option>
                        <option value="Sutura">Sutura</option>
                        <option value="Curación de Heridas">Curación de Heridas</option>
                        <option value="Extracción de Cuerpo Extraño">Extracción de Cuerpo Extraño</option>
                        <option value="Drenaje de Absceso">Drenaje de Absceso</option>
                        <option value="Infiltración">Infiltración</option>
                        <option value="Nebulización">Nebulización</option>
                        <option value="Aplicación de Medicamento">Aplicación de Medicamento (IM/IV)</option>
                        <option value="Colocación de Sonda">Colocación de Sonda</option>
                        <option value="Retiro de Puntos">Retiro de Puntos</option>
                        <option value="Otro">Otro (Especifique en descripción)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Descripción del Procedimiento</label>
                    <textarea name="descripcion" class="form-control" rows="3" placeholder="Detalles del procedimiento realizado..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Monto (Q) *</label>
                        <input type="number" name="monto" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Método de Pago *</label>
                        <select name="metodo_pago" class="form-control" required>
                            <option value="Efectivo">Efectivo</option>
                            <option value="Tarjeta">Tarjeta</option>
                            <option value="Transferencia">Transferencia</option>
                            <option value="Cheque">Cheque</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="bi bi-save"></i>
                    Guardar y Generar Ticket
                </button>
            </form>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div style="padding: 1.5rem; border-bottom: 1px solid var(--color-border);">
                <h3 style="margin: 0; font-size: 1.125rem; font-weight: 600;">Historial de Procedimientos</h3>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Ticket</th>
                            <th>Fecha</th>
                            <th>Paciente</th>
                            <th>Tipo</th>
                            <th>Procedimiento</th>
                            <th>Tipo Pago</th>
                            <th>Método</th>
                            <th>Monto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($procedimientos as $proc): ?>
                        <tr>
                            <td><strong>#<?php echo str_pad($proc['id_procedimiento'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($proc['fecha_procedimiento'])); ?></td>
                            <td><?php echo htmlspecialchars($proc['nombre_paciente']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($proc['tipo_paciente']); ?>">
                                    <?php echo $proc['tipo_paciente']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($proc['procedimiento_realizado']); ?></td>
                            <td>
                                <?php 
                                $pagoClass = 'badge-privado';
                                if ($proc['tipo_pago'] === 'Seguro Manual') $pagoClass = 'badge-seguro';
                                if ($proc['tipo_pago'] === 'EPS') $pagoClass = 'badge-eps';
                                ?>
                                <span class="badge <?php echo $pagoClass; ?>">
                                    <?php echo $proc['tipo_pago']; ?>
                                </span>
                            </td>
                            <td><?php echo $proc['metodo_pago']; ?></td>
                            <td><strong>Q<?php echo number_format($proc['monto'], 2); ?></strong></td>
                            <td>
                                <button onclick='printTicket({
                                    ticketNumber: "<?php echo $proc["numero_ticket"] ?? str_pad($proc["id_procedimiento"], 6, "0", STR_PAD_LEFT); ?>",
                                    date: "<?php echo date("d/m/Y H:i", strtotime($proc["fecha_procedimiento"])); ?>",
                                    patientName: "<?php echo htmlspecialchars($proc["nombre_paciente"]); ?>",
                                    items: [{
                                        quantity: 1,
                                        description: "<?php echo htmlspecialchars($proc["procedimiento_realizado"]); ?>",
                                        total: <?php echo $proc["monto"]; ?>
                                    }],
                                    total: <?php echo $proc["monto"]; ?>
                                })' 
                                   style="background: none; border: none; color: var(--color-blue); cursor: pointer; text-decoration: none; padding: 0;">
                                    <i class="bi bi-printer"></i> Ticket
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
        const pacienteSelect = document.getElementById('pacienteSelect');
        const nombrePacienteInput = document.getElementById('nombrePaciente');
        const tipoPacienteInput = document.getElementById('tipoPaciente');

        pacienteSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            nombrePacienteInput.value = option.dataset.nombre || '';
            tipoPacienteInput.value = option.dataset.tipo || 'Privado';
        });
    </script>
</body>
</html>