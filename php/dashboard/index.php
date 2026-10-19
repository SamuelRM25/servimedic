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
try {
    $database = new Database();
    $conn = $database->getConnection();
    $permisoReportes = tienePermisoReportes($conn, (int)$_SESSION['user_id']);

    // Transferencias (Notificaciones)
    $transferenciasEnviadas = [];
    $transferenciasEntrantes = [];
    
    // Obtener ID de sucursal del usuario actual (Helper query if not already known)
    if (!isset($userSucursal) && isset($_SESSION['user_id'])) {
        $stmtUserSuc = $conn->prepare("SELECT id_sucursal FROM usuarios WHERE id = ?");
        $stmtUserSuc->execute([$_SESSION['user_id']]);
        $userSuc = $stmtUserSuc->fetch(PDO::FETCH_ASSOC);
        $userSucursalId = $userSuc['id_sucursal'] ?? 0;
    } else {
        // If userSucursal is not set here (it's set in inventory/index.php but not necessarily here), fetch it.
        // Actually, dashboard/index.php doesn't seem to fetch the user's branch ID explicitly in the view code above, 
        // let's grab it for filtering.
        $userSucursalId = $_SESSION['id_sucursal'] ?? 0; // Assuming it might be in session, or we fetch it.
        if ($userSucursalId == 0 && isset($_SESSION['user_id'])) {
             $stmtUserSuc = $conn->prepare("SELECT id_sucursal FROM usuarios WHERE id = ?");
             $stmtUserSuc->execute([$_SESSION['user_id']]);
             $userSuc = $stmtUserSuc->fetch(PDO::FETCH_ASSOC);
             $userSucursalId = $userSuc['id_sucursal'] ?? 0;
        }
    }

    try {
        // Transferencias Enviadas (Recientes, éxito)
        $stmtSent = $conn->prepare("
            SELECT t.*, s.nombre as sucursal_destino_nombre, i.nom_medicamento
            FROM transferencias t
            JOIN sucursales s ON t.id_sucursal_destino = s.id_sucursal
            JOIN inventario i ON t.id_inventario_origen = i.id_inventario
            WHERE t.id_sucursal_origen = ? 
            ORDER BY t.fecha_envio DESC LIMIT 5
        ");
        $stmtSent->execute([$userSucursalId]);
        $transferenciasEnviadas = $stmtSent->fetchAll(PDO::FETCH_ASSOC);

        // Transferencias Entrantes (En Camino)
        $stmtIncoming = $conn->prepare("
            SELECT t.*, s.nombre as sucursal_origen_nombre, i.nom_medicamento
            FROM transferencias t
            JOIN sucursales s ON t.id_sucursal_origen = s.id_sucursal
            JOIN inventario i ON t.id_inventario_origen = i.id_inventario
            WHERE t.id_sucursal_destino = ? AND t.estado = 'En Camino'
            ORDER BY t.fecha_envio ASC
        ");
        $stmtIncoming->execute([$userSucursalId]);
        $transferenciasEntrantes = $stmtIncoming->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table might not exist yet if transfer_product.php hasn't been run or lazy init failed.
        // Just ignore notifications in that case.
    }

    // 2. Stats
    // Medicamentos por vencer (< 60 dias)
    $stmt = $conn->query("SELECT COUNT(*) FROM inventario WHERE fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY)");
    $expiringCount = $stmt->fetchColumn();

    // Stock Bajo (< 20 unidades)
    $stmt = $conn->query("SELECT COUNT(*) FROM inventario WHERE cantidad_med < 20");
    $lowStockCount = $stmt->fetchColumn();

    // Pedidos en el mes
    $stmt = $conn->query("SELECT COUNT(*) FROM pedidos WHERE YEAR(fecha_pedido) = YEAR(CURDATE()) AND MONTH(fecha_pedido) = MONTH(CURDATE())");
    $pedidosMesCount = (int) $stmt->fetchColumn();

} catch (Exception $e) {
    // Fallback if DB error
    $expiringCount = 0;
    $lowStockCount = 0;
    $pedidosMesCount = 0;
    $transferenciasEnviadas = [];
    $transferenciasEntrantes = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CLINOVA</title>
    
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
            --color-orange: #FF3D00;
            --color-orange-light: #FF6E40;
            --color-orange-dark: #E63400;
            --color-blue: #1FA8FF;
            --color-blue-light: #42B5FF;
            --color-blue-dark: #0277BD;
            
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

        <!-- Notifications Section -->
        <?php if (!empty($transferenciasEntrantes) || !empty($transferenciasEnviadas)): ?>
        <div style="margin-bottom: 2rem;">
            <?php if (!empty($transferenciasEntrantes)): ?>
                <?php foreach ($transferenciasEntrantes as $trans): ?>
                <div style="background: #EBF8FF; border-left: 5px solid #1FA8FF; border-radius: 10px; padding: 1rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="background: #1FA8FF; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-truck"></i>
                        </div>
                        <div>
                            <h4 style="font-size: 1rem; color: #2C5282; margin-bottom: 0.2rem;">Transferencia en Camino</h4>
                            <p style="font-size: 0.9rem; color: #4A5568;">
                                Vienen <strong><?php echo $trans['cantidad']; ?></strong> unidades de <strong><?php echo htmlspecialchars($trans['nom_medicamento']); ?></strong> desde <strong><?php echo htmlspecialchars($trans['sucursal_origen_nombre']); ?></strong>.
                            </p>
                        </div>
                    </div>
                    <span style="font-size: 0.8rem; color: #718096; background: white; padding: 0.25rem 0.5rem; border-radius: 4px;">
                        <?php echo date('d/m H:i', strtotime($trans['fecha_envio'])); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($transferenciasEnviadas)): ?>
                <?php foreach ($transferenciasEnviadas as $trans): ?>
                <div style="background: #F0FFF4; border-left: 5px solid #48BB78; border-radius: 10px; padding: 1rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div style="background: #48BB78; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-check-lg"></i>
                        </div>
                        <div>
                            <h4 style="font-size: 1rem; color: #276749; margin-bottom: 0.2rem;">Transferencia Enviada Correctamente</h4>
                            <p style="font-size: 0.9rem; color: #4A5568;">
                                Se enviaron <strong><?php echo $trans['cantidad']; ?></strong> unidades de <strong><?php echo htmlspecialchars($trans['nom_medicamento']); ?></strong> a <strong><?php echo htmlspecialchars($trans['sucursal_destino_nombre']); ?></strong>.
                            </p>
                        </div>
                    </div>
                     <span style="font-size: 0.8rem; color: #718096; background: white; padding: 0.25rem 0.5rem; border-radius: 4px;">
                        <?php echo date('d/m H:i', strtotime($trans['fecha_envio'])); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

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
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .stats-container > h3 {
            grid-column: 1 / -1;
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
                    <span>CLINOVA</span>
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
                        <a href="../clientes/index.php" class="nav-link">
                            <i class="bi bi-people"></i>
                            <span>Clientes</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (in_array($rol_usuario, ['Administrador', 'Farmacia'])): ?>
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

                    <?php if (!empty($permisoReportes)): ?>
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
                    <button class="btn-new-patient" onclick="openNewClienteModal()">
                        <div class="icon-wrapper">
                            <i class="bi bi-person-plus-fill"></i>
                        </div>
                        <span>Nuevo Cliente</span>
                    </button>
                </div>
                <?php else: ?>
                <div></div>
                <?php endif; ?>
                
                <?php include '../../includes/clock.php'; ?>
            </div>

            <!-- Stats Widgets -->
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
                            <i class="bi bi-clipboard-check-fill"></i>
                        </div>
                        <div class="widget-content">
                            <div class="widget-value"><?php echo $pedidosMesCount; ?></div>
                            <div class="widget-label">Pedidos en el mes</div>
                            <div class="widget-sub"><?php echo date('F Y'); ?></div>
                        </div>
                    </div>
            </div>
        </main>
    </div>
    <!-- New Cliente Modal -->
    <div class="modal-overlay" id="newClienteModal">
        <div class="modal" style="max-width: 520px;">
            <div class="modal-header">
                <h2 class="modal-title">Nuevo Cliente</h2>
                <button class="modal-close" onclick="closeModal('newClienteModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="newClienteForm" onsubmit="submitClienteForm(event)">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nombre *</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Nombre completo del cliente" required maxlength="150" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control" placeholder="0000-0000" maxlength="20" inputmode="tel" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="direccion" class="form-control" placeholder="Dirección" maxlength="255" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('newClienteModal')">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cliente</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper (Required for Tabs) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        function openNewClienteModal() {
            document.getElementById('newClienteModal').classList.add('show');
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

        function submitClienteForm(e) {
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

            fetch('../clientes/save_cliente.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Cliente agregado correctamente',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        closeModal('newClienteModal');
                        form.reset();
                        location.reload();
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
                    text: 'No se pudo guardar el cliente: ' + error.message
                });
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

    </script>
    <?php include __DIR__ . '/../includes/ui_feedback.php'; ?>
</body>
</html>