<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$apellido_usuario = $_SESSION['apellido'] ?? '';
$rol_usuario = $_SESSION['rol'] ?? 'Usuario';

// Fetch Dashboard Data
$doctores = []; // Initialize
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // 0. Fetch Doctors (Prioritized)
    try {
        $stmtDoctores = $conn->query("SELECT id, nombre, apellido FROM usuarios WHERE rol IN ('Doctor', 'Medico', 'Médico') ORDER BY nombre ASC");
        $stmtDoctores->execute();
        $doctores = $stmtDoctores->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Silent fail for doctors if table missing (unlikely)
        $doctores = [];
    }

    // 0.5 Fetch All Patients for Dropdown (Optimized for name only)
    try {
        $stmtPacientes = $conn->query("SELECT id_paciente, nombre, apellido FROM pacientes ORDER BY nombre ASC");
        $stmtPacientes->execute();
        $pacientes = $stmtPacientes->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $pacientes = [];
    }

    // 1. Turnos (Citas de hoy) - This section is now effectively replaced by Pending Charges for display
    // 4. Check Pending Charges
    $stmtCobros = $conn->prepare("
        SELECT o.*, p.nombre as nombre_paciente, p.apellido as apellido_paciente, u.nombre as doctor_nombre
        FROM ordenes_cobro o
        JOIN pacientes p ON o.id_paciente = p.id_paciente
        LEFT JOIN usuarios u ON o.id_medico = u.id
        WHERE o.estado = 'Pendiente'
        ORDER BY o.fecha_creacion ASC
    ");
    $stmtCobros->execute();
    $cobrosPendientes = $stmtCobros->fetchAll(PDO::FETCH_ASSOC);

    // 5. Pharmacy Queue (Pacientes enviados a Farmacia)
    $stmtFarmacia = $conn->query("
        SELECT o.*, p.nombre as nombre_paciente, p.apellido as apellido_paciente
        FROM ordenes_cobro o
        JOIN pacientes p ON o.id_paciente = p.id_paciente
        WHERE o.necesita_farmacia = 1
        AND o.estado IN ('Pagado', 'Atendido')
        AND o.fecha_pago >= CURDATE()
        ORDER BY o.fecha_pago ASC
    ");
    $stmtFarmacia->execute();
    $farmaciaQueue = $stmtFarmacia->fetchAll(PDO::FETCH_ASSOC);

    // 2. Stats
    // Medicamentos por vencer (< 60 dias)
    $stmt = $conn->query("SELECT COUNT(*) FROM inventario WHERE fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)");
    $expiringCount = $stmt->fetchColumn();

    // Stock Bajo (< 20 unidades)
    $stmt = $conn->query("SELECT COUNT(*) FROM inventario WHERE cantidad_med < 20");
    $lowStockCount = $stmt->fetchColumn();

    // Pacientes en espera (Turnos restantes hoy)
    // This now refers to the count of pending charges
    $waitingCount = count($cobrosPendientes);

    // 3. Cobros Pendientes (This section is now handled by $cobrosPendientes above)
    // Ensure table exists first (just in case)
    $conn->exec("CREATE TABLE IF NOT EXISTS `ordenes_cobro` (
      `id_orden` int(11) NOT NULL AUTO_INCREMENT,
      `id_paciente` int(11) NOT NULL,
      `id_medico` int(11) NOT NULL,
      `monto` decimal(10,2) NOT NULL,
      `tipo_cobro` enum('Consulta','Procedimiento','Examen','Otro') NOT NULL,
      `descripcion` text DEFAULT NULL,
      `estado` enum('Pendiente','Pagado') NOT NULL DEFAULT 'Pendiente',
      `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
      `fecha_pago` timestamp NULL DEFAULT NULL,
      PRIMARY KEY (`id_orden`),
      KEY `fk_orden_paciente` (`id_paciente`),
      KEY `fk_orden_medico` (`id_medico`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

    // The original query for cobrosPendientes is now replaced by $stmtCobros above.
    // This block is kept for table creation, but the fetch is moved.
    // $stmt = $conn->query("
    //     SELECT o.*, p.nombre as nombre_paciente, p.apellido as apellido_paciente
    //     FROM ordenes_cobro o
    //     JOIN pacientes p ON o.id_paciente = p.id_paciente
    //     WHERE o.estado = 'Pendiente'
    //     ORDER BY o.fecha_creacion DESC
    // ");
    // $cobrosPendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Fetch Doctors for Dropdown (Include variants of Doctor/Medico)
    // Moved to top
    // $stmtDoctores = $conn->query("SELECT id, nombre, apellido FROM usuarios WHERE rol IN ('Doctor', 'Medico', 'Médico') ORDER BY nombre ASC");
    // $stmtDoctores->execute();
    // $doctores = $stmtDoctores->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Fallback if DB error
    $turnos = []; // Keep this for compatibility if needed elsewhere, though it's not used in the dashboard queue anymore
    $expiringCount = 0;
    $lowStockCount = 0;
    $waitingCount = 0;
    $cobrosPendientes = [];
    $farmaciaQueue = [];
    // Ensure doctores is defined if main try fails
    if (!isset($doctores)) { $doctores = []; }
    if (!isset($pacientes)) { $pacientes = []; }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Servimedic</title>
    
    <!-- Google Fonts - Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border;
        }

        :root {
            /* Colores - Naranja y Celeste */
            --color-orange: #FF6B35;
            --color-orange-light: #FF8C61;
            --color-orange-dark: #E85A2B;
            --color-blue: #4FC3F7;
            --color-blue-light: #81D4FA;
            --color-blue-dark: #0288D1;
            
            /* Colores de soporte */
            --color-white: #FFFFFF;
            --color-text: #2D3748;
            --color-text-light: #718096;
            --color-bg: #F7FAFC;
            --color-sidebar-bg: #FFFFFF;
            
            /* Sombras */
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.12);
            
            --font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --sidebar-width: 280px;
        }

        body {
            font-family: var(--font-family);
            background: var(--color-bg);
            color: var(--color-text);
            overflow-x: hidden;
        }

        /* Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--color-sidebar-bg);
            box-shadow: var(--shadow-md);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
            text-align: center;
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-white);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .sidebar-logo i {
            font-size: 1.75rem;
        }

        .sidebar-role {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 400;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1.5rem 0;
        }

        .nav-section-title {
            padding: 0 1.5rem;
            margin-bottom: 0.75rem;
            margin-top: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--color-text-light);
        }

        .nav-section-title:first-child {
            margin-top: 0;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin: 0.25rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.875rem 1.5rem;
            color: var(--color-text);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-link i {
            font-size: 1.25rem;
            width: 24px;
            transition: transform 0.3s ease;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 0;
            background: linear-gradient(180deg, var(--color-orange), var(--color-blue));
            border-radius: 0 4px 4px 0;
            transition: height 0.3s ease;
        }

        .nav-link:hover {
            background: linear-gradient(90deg, rgba(255, 107, 53, 0.05), rgba(79, 195, 247, 0.05));
            color: var(--color-orange);
        }

        .nav-link:hover i {
            transform: translateX(4px);
        }

        .nav-link:hover::before {
            height: 70%;
        }

        .nav-link.active {
            background: linear-gradient(90deg, rgba(255, 107, 53, 0.1), rgba(79, 195, 247, 0.1));
            color: var(--color-orange);
            font-weight: 600;
        }

        .nav-link.active::before {
            height: 100%;
        }

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: var(--color-bg);
            border-radius: 12px;
            margin-bottom: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1rem;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--color-text);
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--color-text-light);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, var(--color-orange), var(--color-orange-dark));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Welcome Section */
        .welcome-card {
            background: var(--color-white);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: var(--shadow-md);
            text-align: center;
            max-width: 600px;
            margin: 4rem auto;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--color-orange), var(--color-blue));
        }

        .welcome-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.3);
        }

        .welcome-icon i {
            font-size: 3rem;
            color: white;
        }

        .welcome-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 1rem;
        }

        .welcome-subtitle {
            font-size: 1.125rem;
            color: var(--color-text-light);
            margin-bottom: 0.5rem;
        }

        .welcome-description {
            font-size: 0.95rem;
            color: var(--color-text-light);
            max-width: 400px;
            margin: 0 auto;
        }

        /* Responsive */
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 0px;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1.5rem;
            }

            .welcome-card {
                padding: 2rem;
                margin: 2rem auto;
            }

            .welcome-title {
                font-size: 1.5rem;
            }
        }

        /* Scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.2);
        }

        /* Premium Button Style */
        .btn-new-patient {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--color-orange), var(--color-orange-dark));
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-new-patient::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(255,255,255,0.2), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .btn-new-patient:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 53, 0.4);
        }

        .btn-new-patient:hover::before {
            opacity: 1;
        }

        .btn-new-patient .icon-wrapper {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-new-patient i {
            font-size: 1.1rem;
        }

        /* Modal Enhancements */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 2000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background: white;
            border-radius: 20px;
            width: 90%;
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .modal-overlay.show .modal {
            transform: scale(1);
        }

        .modal-header {
            padding: 1.5rem 2rem;
            background: linear-gradient(135deg, var(--color-orange), var(--color-orange-light));
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .modal-close {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid var(--color-border, #e2e8f0);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            background: #f8fafc;
            border-radius: 0 0 20px 20px;
        }

        /* Form Controls Enhancement */
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--color-blue);
            box-shadow: 0 0 0 3px rgba(79, 195, 247, 0.2);
            outline: none;
        }

        .form-label {
            font-weight: 500;
            color: var(--color-text);
            margin-bottom: 0.5rem;
            display: block;
        }

        /* Tabs Styling */
        .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
            gap: 1rem;
            padding: 0;
            margin: 0 0 1.5rem 0;
            list-style: none;
            display: flex;
        }

        .nav-tabs .nav-item {
            margin: 0;
        }

        .nav-tabs .nav-link {
            border: none;
            color: var(--color-text-light);
            font-weight: 500;
            padding: 1rem 1.5rem;
            border-radius: 10px 10px 0 0;
            transition: all 0.2s ease;
            background: transparent;
        }

        .nav-tabs .nav-link:hover {
            color: var(--color-orange);
            background: rgba(255, 107, 53, 0.05);
        }

        .nav-tabs .nav-link.active {
            color: var(--color-orange);
            background: white;
            border-bottom: 3px solid var(--color-orange);
            font-weight: 600;
        }

        /* Section Headers */
        .section-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--color-text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
            margin-top: 1.5rem;
        }
        
        .section-title:first-child {
            margin-top: 0;
        }

        /* Dashboard Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 2rem;
            margin-top: 1rem;
        }

        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Queue Section */
        .queue-container {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            max-height: 80vh;
            overflow-y: auto;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--color-bg);
        }

        .section-title-lg {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .current-date {
            background: var(--color-bg);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            color: var(--color-text-light);
            font-size: 0.9rem;
        }

        .queue-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .queue-card {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: var(--color-bg);
            border-radius: 16px;
            border-left: 5px solid var(--color-blue);
            transition: all 0.3s ease;
        }

        .queue-card:hover {
            transform: translateX(5px);
            background: white;
            box-shadow: var(--shadow-sm);
        }

        .queue-card.past {
            opacity: 0.6;
            border-left-color: var(--color-text-light);
            background: #f1f5f9;
        }

        .queue-time {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--color-text);
            padding-right: 1.5rem;
            border-right: 2px solid rgba(0,0,0,0.05);
            min-width: 100px;
            text-align: center;
        }

        .queue-details {
            flex: 1;
            padding: 0 1.5rem;
        }

        .queue-details h4 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--color-text);
        }

        .queue-details p {
            font-size: 0.85rem;
            color: var(--color-text-light);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-waiting {
            background: #EBF8FF;
            color: var(--color-blue-dark);
        }

        .status-past {
            background: #EDF2F7;
            color: var(--color-text-light);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--color-text-light);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
            opacity: 0.5;
        }

        /* Stats Widgets */
        .stats-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .stat-widget {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            box-shadow: var(--shadow-md);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-widget:hover {
            transform: translateY(-5px);
        }

        .stat-widget::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 6px;
        }

        .stat-widget.warning::before { background: #ECC94B; }
        .stat-widget.danger::before { background: #F56565; }
        .stat-widget.info::before { background: var(--color-blue); }

        .widget-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            flex-shrink: 0;
        }

        .stat-widget.warning .widget-icon { background: #FEFCBF; color: #D69E2E; }
        .stat-widget.danger .widget-icon { background: #FED7D7; color: #C53030; }
        .stat-widget.info .widget-icon { background: #EBF8FF; color: var(--color-blue-dark); }

        .widget-content {
            flex: 1;
        }

        .widget-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--color-text);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .widget-label {
            font-size: 1rem;
            font-weight: 600;
            color: var(--color-text);
        }

        .widget-sub {
            font-size: 0.8rem;
            color: var(--color-text-light);
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <!-- Header -->
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="bi bi-hospital"></i>
                    <span>Servimedic</span>
                </div>
                <div class="sidebar-role">Sistema de Gestión</div>
            </div>

            <!-- Navigation -->
            <nav class="sidebar-nav">
                <div class="nav-section-title">Principal</div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link active">
                            <i class="bi bi-house-door"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                </ul>

                <div class="nav-section-title">Módulos</div>
                <ul class="nav-menu">
                    <?php if (in_array($rol_usuario, ['Administrador', 'Farmacia', 'Mayoreo'])): ?>
                    <li class="nav-item">
                        <a href="../inventory/index.php" class="nav-link">
                            <i class="bi bi-box-seam"></i>
                            <span>Inventario</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (in_array($rol_usuario, ['Administrador', 'Farmacia'])): ?>
                    <li class="nav-item">
                        <a href="../dispensary/index.php" class="nav-link">
                            <i class="bi bi-calendar-check"></i>
                            <span>Despacho</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (in_array($rol_usuario, ['Administrador', 'Doctor'])): ?>
                    <li class="nav-item">
                        <a href="../patients/index.php" class="nav-link">
                            <i class="bi bi-people"></i>
                            <span>Pacientes</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (in_array($rol_usuario, ['Administrador', 'Farmacia'])): ?>
                    <li class="nav-item">
                        <a href="../examinations/index.php" class="nav-link">
                            <i class="bi bi-file-earmark-medical"></i>
                            <span>Exámenes</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../minor_procedures/index.php" class="nav-link">
                            <i class="bi bi-bandaid"></i>
                            <span>Procedimientos Menores</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($rol_usuario === 'Administrador'): ?>
                    <li class="nav-item">
                        <a href="../sales/index.php" class="nav-link">
                            <i class="bi bi-shop"></i>
                            <span>Ventas</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../purchases/index.php" class="nav-link">
                            <i class="bi bi-cart-plus"></i>
                            <span>Compras</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (in_array($rol_usuario, ['Administrador', 'Mayoreo'])): ?>
                    <li class="nav-item">
                        <a href="../orders/index.php" class="nav-link">
                            <i class="bi bi-clipboard-check"></i>
                            <span>Pedidos</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (in_array($rol_usuario, ['Administrador'])): ?>
                    <li class="nav-item">
                        <a href="../billing/index.php" class="nav-link">
                            <i class="bi bi-cash-coin"></i>
                            <span>Cobros</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($rol_usuario === 'Administrador'): ?>
                    <li class="nav-item">
                        <a href="../reports/index.php" class="nav-link">
                            <i class="bi bi-file-earmark-text"></i>
                            <span>Reportes</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- Footer -->
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($nombre_usuario, 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($nombre_usuario . ' ' . $apellido_usuario); ?></div>
                        <div class="user-role"><?php echo htmlspecialchars($rol_usuario); ?></div>
                    </div>
                </div>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <?php if (in_array($rol_usuario, ['Administrador', 'Mayoreo', 'Farmacia', 'Doctor'])): ?>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-new-patient" onclick="openNewPatientModal()">
                        <div class="icon-wrapper">
                            <i class="bi bi-person-plus-fill"></i>
                        </div>
                        <span>Nuevo Paciente</span>
                    </button>
                    <button class="btn-new-patient" onclick="openManualChargeModal()" style="background: linear-gradient(135deg, var(--color-blue), var(--color-blue-dark));">
                        <div class="icon-wrapper">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <span>Realizar Cobro</span>
                    </button>
                </div>
                <?php else: ?>
                <div></div>
                <?php endif; ?>
                
                <?php include '../../includes/clock.php'; ?>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Left: Queue System -->
                <div class="dashboard-grid-col">
                    <!-- PHARMACY QUEUE -->
                    <?php if (!empty($farmaciaQueue)): ?>
                    <div class="section-header">
                        <h2 class="section-title-lg"><i class="bi bi-shop"></i> Farmacia - Entregas Pendientes</h2>
                        <span class="current-date"><?php echo count($farmaciaQueue); ?></span>
                    </div>
                    <div class="queue-container">
                        <div class="queue-list">
                            <?php foreach ($farmaciaQueue as $fq): ?>
                            <div class="queue-card" onclick="openPharmacyModal(<?php echo $fq['id_orden']; ?>, '<?php echo htmlspecialchars($fq['nombre_paciente'] . ' ' . $fq['apellido_paciente']); ?>')">
                                <div class="queue-time">
                                    <?php echo date('H:i', strtotime($fq['fecha_pago'])); ?>
                                </div>
                                <div class="queue-details">
                                    <h4><?php echo htmlspecialchars($fq['nombre_paciente'] . ' ' . $fq['apellido_paciente']); ?></h4>
                                    <p><i class="bi bi-prescription"></i> Ver Receta</p>
                                </div>
                                <div class="queue-status">
                                    <i class="bi bi-chevron-right"></i>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <br>
                    <?php endif; ?>

                    <!-- PENDING CHARGES QUEUE -->
                    <div class="section-header">
                        <h3 class="section-title-lg"><i class="bi bi-cash-stack"></i> Cobros Pendientes </h3>
                        <span class="current-date" id="chargeCountBadge"><?php echo count($cobrosPendientes); ?></span>
                    </div>
                    
                    <div class="queue-list" id="pendingChargesList">
                        <?php if (empty($cobrosPendientes)): ?>
                            <div class="empty-state">
                                <i class="bi bi-cash-coin"></i>
                                <p>No hay cobros pendientes actualmente.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($cobrosPendientes as $cobro): 
                                $statusClass = 'status-waiting'; // All are pending
                                $statusText = 'Pendiente';
                            ?>
                            <div class="queue-card" onclick="processCharge(<?php echo $cobro['id_orden']; ?>)">
                                <div class="queue-time">
                                    <?php echo date('g:i A', strtotime($cobro['fecha_creacion'])); ?>
                                </div>
                                <div class="queue-details">
                                    <h4><?php echo htmlspecialchars($cobro['nombre_paciente'] . ' ' . $cobro['apellido_paciente']); ?></h4>
                                    <p><i class="bi bi-person-badge"></i> Dr. <?php echo htmlspecialchars($cobro['doctor_nombre'] ?? 'No asignado'); ?></p>
                                    <p><i class="bi bi-tag"></i> <?php echo htmlspecialchars($cobro['tipo_cobro']); ?> - Q<?php echo number_format($cobro['monto'], 2); ?></p>
                                </div>
                                <div class="queue-status">
                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right: Stats Widgets -->
                <div class="stats-container">
                    <h3 class="section-title-lg"><i class="bi bi-activity"></i> Resumen en Tiempo Real</h3>
                    
                    <div class="stat-widget warning">
                        <div class="widget-icon">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                        </div>
                        <div class="widget-content">
                            <div class="widget-value"><?php echo $expiringCount; ?></div>
                            <div class="widget-label">Medicamentos por Vencer</div>
                            <div class="widget-sub">Próximos 60 días</div>
                        </div>
                    </div>

                    <div class="stat-widget danger">
                        <div class="widget-icon">
                            <i class="bi bi-box-seam-fill"></i>
                        </div>
                        <div class="widget-content">
                            <div class="widget-value"><?php echo $lowStockCount; ?></div>
                            <div class="widget-label">Stock Bajo</div>
                            <div class="widget-sub">Menos de 20 unidades</div>
                        </div>
                    </div>

                    <div class="stat-widget info">
                        <div class="widget-icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="widget-content">
                            <div class="widget-value"><?php echo $waitingCount; ?></div>
                            <div class="widget-label">Pacientes en Espera</div>
                            <div class="widget-sub">Cobros pendientes hoy</div>
                        </div>
                    </div>                    
                </div>
            </div>
        </main>
    </div>
    <!-- New Patient Modal -->
    <div class="modal-overlay" id="newPatientModal">
        <div class="modal" style="max-width: 900px;">
            <div class="modal-header">
                <h2 class="modal-title">Nuevo Paciente - Admisión y Anamnesis</h2>
                <button class="modal-close" onclick="closeModal('newPatientModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="newPatientForm" onsubmit="submitPatientForm(event)">
                <input type="hidden" name="ajax" value="1">
                <div class="modal-body">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3" id="patientTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic" type="button" role="tab">Información Principal</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="patientTabsContent">
                        <!-- Basic Info Tab -->
                        <div class="tab-pane fade show active" id="basic" role="tabpanel">
                            <div class="section-title">Información Personal</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre *</label>
                                    <input type="text" name="nombre" class="form-control" placeholder="Ej: Juan Carlos" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Apellido *</label>
                                    <input type="text" name="apellido" class="form-control" placeholder="Ej: Pérez López" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Fecha Nacimiento *</label>
                                    <input type="date" name="fecha_nacimiento" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Género *</label>
                                    <select name="genero" class="form-control" required>
                                        <option value="Masculino">Masculino</option>
                                        <option value="Femenino">Femenino</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" style="color: var(--color-blue-dark);"><i class="bi bi-person-fill-add"></i> Doctor Asignado</label>
                                    <select name="id_medico" class="form-control" style="border-color: var(--color-blue-light); background-color: #EBF8FF;">
                                        <option value="">-- Seleccionar Doctor --</option>
                                        <?php if(empty($doctores)): ?>
                                        <option value="" disabled>No hay doctores disponibles</option>
                                        <?php endif; ?>
                                        <?php foreach ($doctores as $doctor): ?>
                                        <option value="<?php echo $doctor['id']; ?>">
                                            Dr. <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">DPI/Cédula</label>
                                    <input type="text" name="dpi" class="form-control" placeholder="0000 00000 0000">
                                </div>
                            </div>

                            <div class="section-title">Contacto y Dirección</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono</label>
                                    <input type="text" name="telefono" class="form-control" placeholder="Ej: 5555-5555">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Correo Electrónico</label>
                                    <input type="email" name="correo" class="form-control" placeholder="ejemplo@correo.com">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Dirección Completa</label>
                                    <input type="text" name="direccion" class="form-control" placeholder="Dirección de residencia">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tipo Paciente</label>
                                    <select name="tipo_paciente" class="form-control">
                                        <option value="Privado">Privado</option>
                                        <option value="IGS">IGS</option>
                                        <option value="EPSS">EPSS</option>
                                        <option value="Mawdy">Mawdy</option>
                                    </select>
                                </div>
                            </div>

                            <div class="section-title">En Caso de Emergencia</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Nombre Responsable</label>
                                    <input type="text" name="contacto_emergencia_nombre" class="form-control" placeholder="Nombre del familiar">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Teléfono Responsable</label>
                                    <input type="text" name="contacto_emergencia_telefono" class="form-control" placeholder="Teléfono de contacto">
                                </div>
                            </div>
                        </div>

                        <!-- Anamnesis Tab -->
                        <div class="tab-pane fade" id="anamnesis" role="tabpanel">
                            <div class="section-title">Consulta Actual</div>
                            <div class="row g-3 mb-4">
                                <div class="col-12">
                                    <label class="form-label">Motivo de Consulta *</label>
                                    <textarea name="motivo_consulta" class="form-control" rows="2" placeholder="¿Por qué acude el paciente hoy?" required></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Síntomas Detallados</label>
                                    <textarea name="sintomas" class="form-control" rows="3" placeholder="Descripción detallada de los síntomas..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('newPatientModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Paciente</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Manual Charge Modal -->
    <div class="modal-overlay" id="manualChargeModal">
        <div class="modal" style="max-width: 550px; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: linear-gradient(135deg, #FF6B35 0%, #FF8C61 100%); padding: 25px 30px;">
                <div>
                    <h2 class="modal-title" style="margin: 0; font-size: 1.6rem; color: white; font-weight: 700;">Cobro Directo</h2>
                    <p style="margin: 5px 0 0; font-size: 0.95rem; opacity: 0.9; color: rgba(255,255,255,0.9); font-weight: 400;">Registrar pago y pase a espera</p>
                </div>
                <button class="modal-close" onclick="closeModal('manualChargeModal')" style="color: white; opacity: 0.8; hover: opacity: 1;">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="manualChargeForm" onsubmit="submitManualCharge(event)">
                <div class="modal-body" style="padding: 30px; background: #f8f9fa;">
                    <style>
                        .premium-input-group {
                            background: white;
                            border: 1px solid #e0e0e0;
                            border-radius: 12px;
                            display: flex;
                            align-items: center;
                            transition: all 0.2s;
                            padding: 2px;
                        }
                        .premium-input-group:focus-within {
                            border-color: #FF6B35;
                            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.1);
                            transform: translateY(-1px);
                        }
                        .premium-input-group .input-group-text {
                            background: transparent;
                            border: none;
                            color: #FF6B35;
                            padding: 10px 0 10px 15px; /* Adjust padding */
                            font-size: 1.1rem;
                        }
                        .premium-input-group .form-control, 
                        .premium-input-group .form-select {
                            border: none;
                            box-shadow: none !important;
                            padding: 12px 15px 12px 10px;
                            font-size: 0.95rem;
                            background: transparent;
                            height: auto; /* Ensure height adjusts */
                        }
                        .premium-label {
                            font-size: 0.75rem;
                            font-weight: 700;
                            text-transform: uppercase;
                            letter-spacing: 0.8px;
                            color: #8898aa;
                            margin-bottom: 6px;
                            display: block;
                        }
                        .btn-modal-action {
                            padding: 12px 25px;
                            border-radius: 10px;
                            font-weight: 600;
                            letter-spacing: 0.3px;
                            transition: all 0.2s;
                            border: none;
                        }
                        .btn-cancel {
                            background: #f1f3f5;
                            color: #636e72;
                        }
                        .btn-cancel:hover {
                            background: #e9ecef;
                            color: #2d3436;
                        }
                        .btn-submit {
                            background: linear-gradient(135deg, #FF6B35 0%, #E85A2B 100%);
                            color: white;
                            box-shadow: 0 4px 12px rgba(232, 90, 43, 0.25);
                        }
                        .btn-submit:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 8px 18px rgba(232, 90, 43, 0.35);
                        }
                    </style>
                    <div class="row g-4">
                        <div class="col-12">
                            <label class="premium-label">Paciente</label>
                            <div class="input-group premium-input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <select name="id_paciente" class="form-select" required>
                                    <option value="">Seleccionar Paciente...</option>
                                    <?php foreach ($pacientes as $paciente): ?>
                                    <option value="<?php echo $paciente['id_paciente']; ?>">
                                        <?php echo htmlspecialchars($paciente['nombre'] . ' ' . $paciente['apellido']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                             <label class="premium-label">Doctor (Sala de Espera)</label>
                             <div class="input-group premium-input-group">
                                <span class="input-group-text"><i class="bi bi-heart-pulse"></i></span>
                                <select name="id_medico" class="form-select" required>
                                    <option value="">Asignar Doctor...</option>
                                    <?php foreach ($doctores as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        Dr. <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="premium-label">Monto (Q)</label>
                            <div class="input-group premium-input-group">
                                <span class="input-group-text">Q</span>
                                <input type="number" name="monto" step="0.01" class="form-control" placeholder="0.00" required style="font-weight: 600; color: #2D3748;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="premium-label">Método de Pago</label>
                            <div class="input-group premium-input-group">
                                <span class="input-group-text"><i class="bi bi-credit-card"></i></span>
                                <select name="tipo_pago" class="form-select" required>
                                    <option value="Privado">Privado</option>
                                    <option value="EPS">EPS</option>
                                    <option value="IGS">IGS</option>
                                    <option value="MAWDY">MAWDY</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12">
                             <label class="premium-label">Concepto / Motivo</label>
                             <div class="input-group premium-input-group">
                                <span class="input-group-text"><i class="bi bi-pencil-square"></i></span>
                                <input type="text" name="descripcion" class="form-control" placeholder="Ej: Consulta General, Control, etc.">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 20px 30px; background: white; border-top: 1px solid #f0f0f0; justify-content: space-between;">
                    <button type="button" class="btn-modal-action btn-cancel" onclick="closeModal('manualChargeModal')">Cancelar</button>
                    <button type="submit" class="btn-modal-action btn-submit px-5"><i class="bi bi-wallet2 me-2"></i>Realizar Cobro</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Pharmacy Modal -->
    <div class="modal-overlay" id="pharmacyModal">
        <div class="modal" style="max-width: 700px;">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title" style="margin:0; font-size: 1.5rem;">Despacho Farmacia</h2>
                    <p style="margin:0; font-size: 0.9rem; opacity: 0.9; font-weight: 400;">Revisión de Receta</p>
                </div>
                <button class="modal-close" onclick="closeModal('pharmacyModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body p-0">
                <div class="p-4 bg-light border-bottom">
                    <h5 class="text-primary mb-0" id="pharmPatientName">Paciente...</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="pharmacyTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Medicamento</th>
                                <th>Cant</th>
                                <th>Dosis</th>
                            </tr>
                        </thead>
                        <tbody class="align-middle"></tbody>
                    </table>
            <div class="modal-body p-0">
                <div class="p-4 border-bottom text-center" style="background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); color: white;">
                    <div style="font-size: 3rem; margin-bottom: 0.5rem;"><i class="bi bi-capsule"></i></div>
                    <h4 id="pharmPatientName" style="font-weight: 700; margin:0; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">Paciente...</h4>
                    <p style="margin:0; opacity: 0.9;">Revisión de Medicamentos</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="pharmacyTable">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Medicamento</th>
                                <th>Cant</th>
                                <th>Dosis</th>
                            </tr>
                        </thead>
                        <tbody class="align-middle"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light">
                 <button class="btn btn-outline-dark" onclick="printPharmacyTicket()">
                    <i class="bi bi-printer me-2"></i> Imprimir Ticket
                </button>
                <button class="btn btn-success" onclick="goToDispensary()">
                    <i class="bi bi-check-lg me-2"></i> Ingresar a Venta
                </button>
            </div>
        </div>
    </div>



    <!-- Bootstrap Bundle with Popper (Required for Tabs) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="../../assets/js/ticket_printer.js"></script>
    <script>
        function openNewPatientModal() {
            document.getElementById('newPatientModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });

        function submitPatientForm(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);

            // Show loading
            Swal.fire({
                title: 'Guardando...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('../patients/save_patient.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Paciente agregado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        closeModal('newPatientModal');
                        form.reset();
                    });
                } else {
                    throw new Error(data.message || 'Error desconocido');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo guardar el paciente: ' + error.message
                });
            });
        }
        function processCharge(idOrden) {
            if (!confirm('¿Confirmar cobro?')) return;

            const formData = new FormData();
            formData.append('id_orden', idOrden);

            fetch('process_charge.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Cobro realizado exitosamente');
                    
                    // Print Ticket
                    printTicket(result.ticket);
                    
                    // Reload to update list
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            })
            .catch(error => {
                console.error(error);
                alert('Error al procesar el cobro');
            });
        }
    </script>
    <script>
        function logoutWithEffect(e) {
            e.preventDefault();
            
            // Effect 1: Fade out body
            document.body.style.transition = 'opacity 0.8s ease';
            document.body.style.opacity = '0';
            
            Swal.fire({
                title: '¡Hasta Pronto!',
                text: 'Cerrando sesión de forma segura...',
                icon: 'success',
                showConfirmButton: false,
                timer: 1500,
                timerProgressBar: true,
                background: '#ffffff',
                backdrop: `
                    rgba(0,0,0,0.8)
                    url("https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExcjR5dGdpYnJ5ZmJ5cnZ5cnZ5cnZ5cnZ5cnZ5cnZ5/3o7bu3XilJ5BOiSGic/giphy.gif")
                    center left
                    no-repeat
                `
            }).then(() => {
                window.location.href = '../auth/logout.php';
            });
        }

        // Real-time Pending Charges Notification
        let lastWaitingCount = <?php echo count($cobrosPendientes); ?>;
        const notificationSound = new Audio('../../assets/audio/notification.mp3'); 
        // Use a publicly available sound as fallback if local one is empty/invalid
        const fallbackSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');

        function playNotificationSound() {
            notificationSound.play().catch(e => {
                console.log("Local audio failed, trying fallback", e);
                fallbackSound.play().catch(err => console.log("Audio play failed", err));
            });
        }

        setInterval(() => {
            fetch('check_charges.php')
                .then(response => response.json())
                .then(data => {
                    const newRows = data.count;
                    const listContainer = document.querySelector('.queue-list');
                    
                    // Always update the list content to ensure it's in sync
                    if (listContainer) {
                        listContainer.innerHTML = data.html;
                    }

                    if (newRows > lastWaitingCount) {
                        // New charge detected!
                        playNotificationSound();
                        
                        Swal.fire({
                            title: '¡Nuevo Cobro Pendiente!',
                            text: 'Se ha asignado un nuevo cobro a un paciente.',
                            icon: 'info',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 5000,
                            timerProgressBar: true
                        });
                    }
                    lastWaitingCount = newRows;
                });
        }, 10000); // Check every 10 seconds

        // Pharmacy Functions
        let currentPharmItems = [];
        let currentPharmPatient = '';
        let currentPharmacyOrdenId = null;

        function openPharmacyModal(idOrden, patientName) {
            currentPharmacyOrdenId = idOrden;
            currentPharmPatient = patientName;
            document.getElementById('pharmPatientName').innerText = patientName;
            
            // Show loading state
            const tbody = document.querySelector('#pharmacyTable tbody');
            tbody.innerHTML = '<tr><td colspan="3" class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></td></tr>';
            document.getElementById('pharmacyModal').classList.add('show');
            
            // Fetch prescription details
            fetch('get_prescription.php?id_orden=' + idOrden)
                .then(r => r.json())
                .then(items => {
                    currentPharmItems = items;
                    tbody.innerHTML = '';
                    if (items.length > 0) {
                        items.forEach(item => {
                            tbody.innerHTML += `
                                <tr>
                                    <td class="ps-4" style="font-weight: 500;">
                                        <i class="bi bi-capsule-pill me-2 text-warning"></i> ${item.medicamento}
                                    </td>
                                    <td><span class="badge bg-primary rounded-pill">${item.cantidad}</span></td>
                                    <td class="text-muted small">${item.dosis}</td>
                                </tr>
                            `;
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted p-4">Sin medicamentos (Consulta sin receta)</td></tr>';
                    }
                })
                .catch(err => {
                    console.error(err);
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Error al cargar receta</td></tr>';
                });
        }

        function printPharmacyTicket() {
             if (currentPharmItems.length === 0) {
                 Swal.fire('Info', 'No hay medicamentos para imprimir', 'info');
                 return;
             }
             
            const width = 300;
            const height = 600;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;
            const printWindow = window.open('', '_blank', `width=${width},height=${height},top=${top},left=${left}`);
            
            const date = new Date().toLocaleDateString();
            
            let itemsHtml = '';
            currentPharmItems.forEach((item, index) => {
                itemsHtml += `
                    <div style="margin-bottom: 10px; border-bottom: 1px dashed #ccc; padding-bottom: 5px;">
                        <strong>${index + 1}. ${item.medicamento}</strong> ( Cant: ${item.cantidad} )<br>
                        <small>${item.dosis}</small>
                    </div>
                `;
            });

            printWindow.document.open();
            printWindow.document.write('<!DOCTYPE html>');
            printWindow.document.write(`
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Despacho Farmacia</title>
                    <style>
                        body { font-family: 'Courier New', monospace; font-size: 12px; padding: 10px; margin: 0; width: 280px; }
                        .header { text-align: center; margin-bottom: 20px; }
                        .info { margin-bottom: 15px; font-size: 11px; }
                        .items { margin-bottom: 20px; }
                        .footer { text-align: center; margin-top: 30px; font-size: 10px; border-top: 1px solid #000; padding-top: 5px; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h3 style="margin:0;">SERVIMEDIC</h3>
                        <p style="margin:5px 0;">Despacho de Farmacia</p>
                    </div>
                    <div class="info">
                        <strong>Fecha:</strong> ${date}<br>
                        <strong>Paciente:</strong> ${currentPharmPatient}
                    </div>
                    <div class="items">
                        ${itemsHtml}
                    </div>
                    <div class="footer">
                        Verificado por Farmacia
                    </div>
                    <script>
                        window.onload = function() {
                            window.print();
                            // setTimeout(() => window.close(), 1000);
                        }
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        function goToDispensary() {
            if (!currentPharmacyOrdenId) return;
            
            // Mark as delivered/processed
            const fd = new FormData();
            fd.append('id_orden', currentPharmacyOrdenId);
            
            fetch('finish_pharmacy_delivery.php', {
                method: 'POST',
                body: fd
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    window.location.href = '../dispensary/index.php';
                } else {
                    alert('Error al procesar: ' + res.message);
                }
            })
            .catch(e => {
                console.error(e);
                // Fallback redirect even if error
                window.location.href = '../dispensary/index.php';
            });
        }

        function openManualChargeModal() {
            document.getElementById('manualChargeModal').classList.add('show');
        }

        function submitManualCharge(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);

            Swal.fire({
                title: 'Procesando...',
                text: 'Registrando cobro y asignando turno...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch('save_manual_charge.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Cobro Exitoso!',
                        text: 'Paciente enviado a Sala de Espera',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        closeModal('manualChargeModal');
                        form.reset();
                        // No need to reload page immediately unless we want to update stats, 
                        // but the waiting room is on another page anyway.
                        // Ideally relocate to Patients or just stay here.
                    });
                } else {
                    throw new Error(data.message);
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });
            });
        }
    </script>
</body>
</html>