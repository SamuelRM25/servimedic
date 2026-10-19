<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$nombre_usuario   = $_SESSION['nombre']   ?? 'Usuario';
$apellido_usuario = $_SESSION['apellido'] ?? '';
$rol_usuario      = $_SESSION['rol']      ?? 'Usuario';

try {
    $database = new Database();
    $conn = $database->getConnection();

    $search = trim($_GET['q'] ?? '');

    if ($search !== '') {
        $stmt = $conn->prepare("
            SELECT id_cliente, nombre, telefono, direccion, fecha_registro
            FROM clientes
            WHERE nombre LIKE :s OR telefono LIKE :s OR direccion LIKE :s
            ORDER BY nombre ASC
        ");
        $stmt->execute([':s' => "%$search%"]);
    } else {
        $stmt = $conn->query("
            SELECT id_cliente, nombre, telefono, direccion, fecha_registro
            FROM clientes
            ORDER BY nombre ASC
        ");
    }
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $clientes = [];
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clientes - CLINOVA</title>
    <link rel="icon" type="image/png" href="../../assets/img/CLINOVA.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/theme.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --color-orange:        #FF3D00;
            --color-orange-light:  #FF6E40;
            --color-orange-dark:   #E63400;
            --color-blue:          #1FA8FF;
            --color-blue-light:    #42B5FF;
            --color-blue-dark:     #0277BD;
            --color-white:         #FFFFFF;
            --color-text:          #1A202C;
            --color-text-light:    #4A5568;
            --color-text-muted:    #718096;
            --color-bg:            #F4F6FA;
            --color-border:        #E2E8F0;
            --color-success:       #10B981;
            --color-success-bg:    #D1FAE5;
            --color-warning:       #F59E0B;
            --color-warning-bg:    #FEF3C7;
            --color-danger:        #EF4444;
            --color-danger-bg:     #FEE2E2;
            --shadow-sm: 0 2px 10px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 18px rgba(0, 0, 0, 0.08);
            --font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        body {
            font-family: var(--font-family);
            background: var(--color-bg);
            color: var(--color-text);
        }
        .layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 260px;
            background: var(--color-white);
            box-shadow: var(--shadow-md);
            position: fixed;
            inset: 0 auto 0 0;
            overflow-y: auto;
            z-index: 100;
            display: flex;
            flex-direction: column;
        }
        .sidebar-header {
            padding: 1.75rem 1.5rem;
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
            color: white;
            text-align: center;
        }
        .sidebar-header h1 {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            margin-bottom: .25rem;
        }
        .sidebar-header small { opacity: .9; font-size: .8rem; }
        .sidebar-nav { flex: 1; padding: 1.5rem 0; }
        .nav-section-title {
            padding: 0 1.5rem;
            margin: 1rem 0 .5rem;
            font-size: .7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--color-text-muted);
        }
        .nav-section-title:first-child { margin-top: 0; }
        .nav-menu { list-style: none; }
        .nav-item { margin: .15rem 0; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: .85rem;
            padding: .75rem 1.5rem;
            color: var(--color-text);
            text-decoration: none;
            font-size: .92rem;
            font-weight: 500;
            transition: background .2s, color .2s;
            position: relative;
        }
        .nav-link i { font-size: 1.1rem; width: 22px; text-align: center; }
        .nav-link::before {
            content: '';
            position: absolute;
            left: 0; top: 50%;
            transform: translateY(-50%);
            width: 3px; height: 0;
            background: linear-gradient(180deg, var(--color-orange), var(--color-blue));
            border-radius: 0 3px 3px 0;
            transition: height .25s;
        }
        .nav-link:hover {
            background: linear-gradient(90deg, rgba(255, 61, 0, .05), rgba(31, 168, 255, .05));
            color: var(--color-orange);
        }
        .nav-link:hover::before { height: 70%; }
        .nav-link.active {
            background: linear-gradient(90deg, rgba(255, 61, 0, .08), rgba(31, 168, 255, .08));
            color: var(--color-orange);
            font-weight: 600;
        }
        .nav-link.active::before { height: 100%; }
        .sidebar-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid rgba(0, 0, 0, .05);
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .65rem;
            background: var(--color-bg);
            border-radius: 10px;
            margin-bottom: .65rem;
        }
        .user-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
            color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: 600;
        }
        .user-name { font-size: .85rem; font-weight: 600; }
        .user-role { font-size: .72rem; color: var(--color-text-muted); }
        .logout-btn {
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            width: 100%;
            padding: .65rem;
            background: linear-gradient(135deg, var(--color-orange), var(--color-orange-dark));
            color: white;
            border: none;
            border-radius: 8px;
            font-size: .85rem; font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: transform .2s, box-shadow .2s;
        }
        .logout-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(255, 61, 0, .35);
        }
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 2rem;
        }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .page-title { font-size: 1.5rem; font-weight: 700; }
        .page-subtitle { font-size: .85rem; color: var(--color-text-muted); margin-top: .15rem; }
        .card {
            background: var(--color-white);
            border-radius: 16px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--color-border);
        }
        .toolbar {
            display: flex; gap: .75rem; align-items: center;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--color-border);
            flex-wrap: wrap;
        }
        .search-box {
            position: relative;
            flex: 1;
            min-width: 240px;
        }
        .search-box i {
            position: absolute; left: .85rem; top: 50%; transform: translateY(-50%);
            color: var(--color-text-muted);
        }
        .search-box input {
            width: 100%;
            padding: .6rem .85rem .6rem 2.4rem;
            border: 1px solid var(--color-border);
            border-radius: 10px;
            font-family: var(--font-family);
            font-size: .92rem;
            transition: border-color .2s, box-shadow .2s;
        }
        .search-box input:focus {
            outline: none;
            border-color: var(--color-blue);
            box-shadow: 0 0 0 3px rgba(31, 168, 255, .15);
        }
        .btn {
            display: inline-flex; align-items: center; gap: .5rem;
            padding: .6rem 1.1rem;
            border: none;
            border-radius: 10px;
            font-family: var(--font-family);
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform .15s, box-shadow .2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--color-orange), var(--color-orange-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(255, 61, 0, .3);
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255, 61, 0, .4); }
        .btn-secondary {
            background: var(--color-bg);
            color: var(--color-text);
        }
        .btn-secondary:hover { background: #e9ecef; }
        .btn-ghost {
            background: transparent;
            color: var(--color-text-light);
            padding: .35rem .55rem;
        }
        .btn-ghost:hover { color: var(--color-orange); }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left;
            padding: .85rem 1.25rem;
            font-size: .75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--color-text-muted);
            background: var(--color-bg);
            border-bottom: 1px solid var(--color-border);
        }
        tbody td {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--color-border);
            font-size: .92rem;
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr { transition: background .15s; }
        tbody tr:hover { background: rgba(31, 168, 255, .04); }
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--color-text-muted);
        }
        .empty-state i { font-size: 3.5rem; opacity: .4; display: block; margin-bottom: .75rem; }
        .empty-state p { font-size: .95rem; }
        .badge-count {
            background: linear-gradient(135deg, var(--color-orange), var(--color-orange-dark));
            color: white;
            padding: .25rem .65rem;
            border-radius: 999px;
            font-size: .75rem;
            font-weight: 700;
        }
        /* Modal */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0, 0, 0, .5);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden;
            transition: opacity .25s, visibility .25s;
        }
        .modal-overlay.show { opacity: 1; visibility: visible; }
        .modal {
            background: white;
            border-radius: 18px;
            width: 90%;
            max-width: 520px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, .25);
            transform: scale(.95);
            transition: transform .25s cubic-bezier(.16, 1, .3, 1);
            overflow: hidden;
        }
        .modal-overlay.show .modal { transform: scale(1); }
        .modal-header {
            padding: 1.25rem 1.5rem;
            background: linear-gradient(135deg, var(--color-orange), var(--color-orange-light));
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h2 { font-size: 1.15rem; font-weight: 600; margin: 0; }
        .modal-close {
            background: rgba(255, 255, 255, .2);
            border: none;
            color: white;
            width: 30px; height: 30px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
        }
        .modal-body { padding: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-label {
            display: block;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--color-text-muted);
            margin-bottom: .35rem;
        }
        .form-control {
            width: 100%;
            padding: .65rem .85rem;
            border: 1.5px solid var(--color-border);
            border-radius: 10px;
            font-family: var(--font-family);
            font-size: .95rem;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--color-blue);
            box-shadow: 0 0 0 3px rgba(31, 168, 255, .15);
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .modal-footer {
            padding: 1rem 1.5rem;
            background: #f8fafc;
            display: flex;
            justify-content: flex-end;
            gap: .5rem;
            border-top: 1px solid var(--color-border);
        }
        .btn-sm { padding: .4rem .75rem; font-size: .8rem; border-radius: 8px; }
        /* Modal: pedidos del cliente */
        .cliente-head {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem 1.5rem;
            justify-content: space-between;
            background: linear-gradient(135deg, rgba(255, 61, 0, .06), rgba(31, 168, 255, .08));
            border: 1px solid var(--color-border);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
        }
        .cliente-head .nombre { font-size: 1.1rem; font-weight: 700; }
        .cliente-head .dato { font-size: .85rem; color: var(--color-text-light); margin-top: .25rem; }
        .cliente-head .dato i { color: var(--color-blue); margin-right: .3rem; }
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .75rem;
            margin-bottom: 1.25rem;
        }
        .resumen-card {
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: 12px;
            padding: .85rem 1rem;
        }
        .resumen-card .label {
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--color-text-muted);
        }
        .resumen-card .value { font-size: 1.15rem; font-weight: 700; margin-top: .2rem; }
        .resumen-card .value.ok { color: var(--color-success); }
        .resumen-card .value.warn { color: var(--color-warning); }
        .info-alert {
            display: flex;
            gap: .6rem;
            align-items: flex-start;
            padding: .85rem 1rem;
            border-radius: 10px;
            font-size: .88rem;
            margin-bottom: 1rem;
        }
        .info-alert i { margin-top: .1rem; }
        .info-alert.warn { background: var(--color-warning-bg); color: #92400E; border: 1px solid #FCD34D; }
        .info-alert.info { background: #E0F2FE; color: #075985; border: 1px solid #7DD3FC; }
        .status-badge {
            display: inline-block;
            padding: .22rem .6rem;
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .st-green { background: var(--color-success-bg); color: #065F46; }
        .st-amber { background: var(--color-warning-bg); color: #92400E; }
        .st-red { background: var(--color-danger-bg); color: #991B1B; }
        .st-blue { background: #DBEAFE; color: #1E40AF; }
        .st-grey { background: #E5E7EB; color: #374151; }
        .st-orange { background: #FFE4D6; color: #9A3412; }
        .tabla-pedidos th, .tabla-pedidos td { padding: .7rem 1rem; font-size: .85rem; }
        .fila-cancelada { opacity: .55; }
        .loading-state { text-align: center; padding: 3rem 1rem; color: var(--color-text-muted); }
        .loading-state i { font-size: 2rem; display: block; margin-bottom: .5rem; animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform .25s; }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1.25rem; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h1><i class="bi bi-hospital-fill"></i> CLINOVA</h1>
            <small>Sistema de Gestión</small>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section-title">Principal</div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="../dashboard/index.php" class="nav-link"><i class="bi bi-house-door"></i> Dashboard</a></li>
            </ul>
            <div class="nav-section-title">Módulos</div>
            <ul class="nav-menu">
                <?php if (in_array($rol_usuario, ['Administrador', 'Farmacia', 'Mayoreo'])): ?>
                <li class="nav-item"><a href="../inventory/index.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventario</a></li>
                <?php endif; ?>
                <?php if (in_array($rol_usuario, ['Administrador', 'Farmacia'])): ?>
                <li class="nav-item"><a href="../dispensary/index.php" class="nav-link"><i class="bi bi-calendar-check"></i> Despacho</a></li>
                <?php endif; ?>
                <?php if (in_array($rol_usuario, ['Administrador', 'Doctor'])): ?>
                <li class="nav-item"><a href="index.php" class="nav-link active"><i class="bi bi-people"></i> Clientes</a></li>
                <?php endif; ?>
                <?php if (in_array($rol_usuario, ['Administrador', 'Mayoreo'])): ?>
                <li class="nav-item"><a href="../orders/index.php" class="nav-link"><i class="bi bi-clipboard-check"></i> Pedidos</a></li>
                <?php endif; ?>
                <?php if ($rol_usuario === 'Administrador'): ?>
                <li class="nav-item"><a href="../sales/index.php" class="nav-link"><i class="bi bi-shop"></i> Ventas</a></li>
                <li class="nav-item"><a href="../purchases/index.php" class="nav-link"><i class="bi bi-cart-plus"></i> Compras</a></li>
                <li class="nav-item"><a href="../reports/index.php" class="nav-link"><i class="bi bi-file-earmark-text"></i> Reportes</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($nombre_usuario, 0, 1)); ?></div>
                <div>
                    <div class="user-name"><?php echo htmlspecialchars($nombre_usuario . ' ' . $apellido_usuario); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($rol_usuario); ?></div>
                </div>
            </div>
            <a href="../auth/logout.php" class="logout-btn">
                <i class="bi bi-box-arrow-right"></i> Cerrar Sesión
            </a>
        </div>
    </aside>

    <!-- Main -->
    <main class="main-content">
        <div class="page-header">
            <div>
                <h2 class="page-title">Clientes</h2>
                <p class="page-subtitle">Directorio de clientes del sistema</p>
            </div>
            <div style="display: flex; gap: .5rem; align-items: center;">
                <span class="badge-count"><?php echo count($clientes); ?> registros</span>
                <button class="btn btn-primary" onclick="openClienteModal()">
                    <i class="bi bi-person-plus-fill"></i> Nuevo Cliente
                </button>
            </div>
        </div>

        <div class="card">
            <form class="toolbar" method="get">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Buscar por nombre, teléfono o dirección..." autofocus>
                </div>
                <button type="submit" class="btn btn-secondary"><i class="bi bi-funnel"></i> Filtrar</button>
                <?php if ($search !== ''): ?>
                <a href="index.php" class="btn btn-ghost"><i class="bi bi-x-circle"></i> Limpiar</a>
                <?php endif; ?>
            </form>

            <?php if (empty($clientes)): ?>
            <div class="empty-state">
                <i class="bi bi-person-x"></i>
                <p><?php echo $search !== '' ? 'No se encontraron clientes con esa búsqueda.' : 'Aún no hay clientes registrados.'; ?></p>
            </div>
            <?php else: ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Dirección</th>
                            <th>Registrado</th>
                            <th style="text-align:right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $c): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($c['nombre']); ?></strong></td>
                            <td>
                                <?php if (!empty($c['telefono'])): ?>
                                <i class="bi bi-telephone-fill" style="color: var(--color-blue); margin-right: .35rem;"></i>
                                <a href="tel:<?php echo htmlspecialchars($c['telefono']); ?>" style="color: inherit; text-decoration: none;">
                                    <?php echo htmlspecialchars($c['telefono']); ?>
                                </a>
                                <?php else: ?>
                                <span style="color: var(--color-text-muted);">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($c['direccion']) ? htmlspecialchars($c['direccion']) : '<span style="color: var(--color-text-muted);">—</span>'; ?></td>
                            <td style="color: var(--color-text-muted); font-size: .85rem;">
                                <?php echo fechaGT($c['fecha_registro']); ?>
                            </td>
                            <td style="text-align:right;">
                                <button class="btn btn-secondary btn-sm" onclick="verPedidosCliente(<?php echo (int) $c['id_cliente']; ?>)">
                                    <i class="bi bi-clipboard-check"></i> Ver pedidos
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- New client modal -->
<div class="modal-overlay" id="clienteModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Nuevo Cliente</h2>
            <button class="modal-close" onclick="closeModal('clienteModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form id="clienteForm" onsubmit="submitClienteForm(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" required maxlength="150" autocomplete="off" placeholder="Nombre completo del cliente">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control" maxlength="20" inputmode="tel" autocomplete="off" placeholder="0000-0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" class="form-control" maxlength="255" autocomplete="off" placeholder="Dirección">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('clienteModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar Cliente</button>
            </div>
        </form>
    </div>
</div>

<!-- Client orders modal -->
<div class="modal-overlay" id="pedidosClienteModal">
    <div class="modal" style="max-width:820px;">
        <div class="modal-header">
            <h2 id="pedidosClienteTitle">Pedidos del Cliente</h2>
            <button class="modal-close" onclick="closeModal('pedidosClienteModal')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body" id="pedidosClienteBody" style="max-height:72vh; overflow-y:auto;"></div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function escHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, ch => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]
    ));
}
function fmtFecha(f) {
    if (!f) return '—';
    const d = String(f).slice(0, 10).split('-');
    return d.length === 3 ? d[2] + '/' + d[1] + '/' + d[0] : f;
}
function fmtQ(n) { return 'Q' + Number(n || 0).toFixed(2); }

function badgePago(estado) {
    if (estado === 'Pagado') return '<span class="status-badge st-green"><i class="bi bi-check-circle"></i> Pagado</span>';
    if (estado === 'Pendiente Crédito') return '<span class="status-badge st-orange"><i class="bi bi-clock-history"></i> Pendiente Crédito</span>';
    return '<span class="status-badge st-amber"><i class="bi bi-hourglass-split"></i> Pendiente de pago</span>';
}
function badgePedido(estado) {
    const map = {
        'Pendiente':  'st-grey',
        'Procesando': 'st-amber',
        'Enviado':    'st-blue',
        'Entregado':  'st-green',
        'Completado': 'st-green',
        'Cancelado':  'st-red'
    };
    return '<span class="status-badge ' + (map[estado] || 'st-grey') + '">' + escHtml(estado) + '</span>';
}

function verPedidosCliente(idCliente) {
    const modal = document.getElementById('pedidosClienteModal');
    const body = document.getElementById('pedidosClienteBody');
    document.getElementById('pedidosClienteTitle').textContent = 'Pedidos del Cliente';
    body.innerHTML = '<div class="loading-state"><i class="bi bi-arrow-repeat"></i><p>Cargando pedidos...</p></div>';
    modal.classList.add('show');

    fetch('get_cliente_pedidos.php?id=' + encodeURIComponent(idCliente))
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Error al cargar los pedidos');
            renderPedidosCliente(data);
        })
        .catch(err => {
            body.innerHTML = '<div class="empty-state"><i class="bi bi-exclamation-triangle"></i><p>' + escHtml(err.message) + '</p></div>';
        });
}

function renderPedidosCliente(data) {
    const c = data.cliente;
    const t = data.totales;
    const body = document.getElementById('pedidosClienteBody');

    document.getElementById('pedidosClienteTitle').textContent = 'Pedidos de ' + c.nombre;

    let html = '';
    html += '<div class="cliente-head">';
    html += '<div><div class="nombre">' + escHtml(c.nombre) + '</div>';
    html += '<div class="dato"><i class="bi bi-telephone-fill"></i>' + (c.telefono ? escHtml(c.telefono) : 'Sin teléfono') + '</div>';
    html += '<div class="dato"><i class="bi bi-geo-alt-fill"></i>' + (c.direccion ? escHtml(c.direccion) : 'Sin dirección') + '</div></div>';
    html += '<div style="text-align:right;"><div class="dato">Cliente desde</div>';
    html += '<div style="font-weight:700;">' + (c.fecha_registro_fmt || fmtFecha(c.fecha_registro)) + '</div></div>';
    html += '</div>';

    if (t.total_pendiente > 0) {
        html += '<div class="info-alert warn"><i class="bi bi-exclamation-triangle-fill"></i><div>' +
            '<strong>Saldo pendiente de pago: ' + fmtQ(t.total_pendiente) + '</strong><br>' +
            t.pendientes_pago + ' pedido(s) sin pagar. Verificar antes de otorgar crédito o entregar mercadería.</div></div>';
    }
    if (t.cotizaciones_abiertas > 0) {
        html += '<div class="info-alert info"><i class="bi bi-file-earmark-text-fill"></i><div>' +
            '<strong>' + t.cotizaciones_abiertas + ' cotización(es) sin procesar por ' + fmtQ(t.monto_cotizado) + '</strong><br>' +
            'Aún no se han convertido en venta.</div></div>';
    }

    html += '<div class="resumen-grid">';
    html += '<div class="resumen-card"><div class="label">Pedidos realizados</div><div class="value">' + t.compras + '</div></div>';
    html += '<div class="resumen-card"><div class="label">Total comprado</div><div class="value">' + fmtQ(t.total_comprado) + '</div></div>';
    html += '<div class="resumen-card"><div class="label">Pagado</div><div class="value ok">' + fmtQ(t.total_pagado) + '</div></div>';
    html += '<div class="resumen-card"><div class="label">Pendiente de pago</div><div class="value ' + (t.total_pendiente > 0 ? 'warn' : '') + '">' + fmtQ(t.total_pendiente) + '</div></div>';
    html += '</div>';

    if (!data.pedidos.length) {
        html += '<div class="empty-state"><i class="bi bi-clipboard-x"></i><p>Este cliente aún no tiene pedidos ni cotizaciones.</p></div>';
    } else {
        html += '<div style="overflow-x:auto;"><table class="tabla-pedidos"><thead><tr>' +
            '<th>Documento</th><th>Fecha</th><th>Tipo</th><th>Total</th><th>Estado pedido</th><th>Estado pago</th>' +
            '</tr></thead><tbody>';
        data.pedidos.forEach(p => {
            const doc = p.es_cotizacion
                ? (p.cotizacion_numero || ('COT-' + p.id_pedido))
                : (p.numero_venta || ('Pedido #' + p.id_pedido));
            const cancelado = p.estado_pedido === 'Cancelado';
            html += '<tr' + (cancelado ? ' class="fila-cancelada"' : '') + '>';
            html += '<td><strong>' + escHtml(doc) + '</strong><br><small style="color:var(--color-text-muted);">Pedido #' + p.id_pedido + '</small></td>';
            html += '<td>' + (p.fecha_pedido_fmt || fmtFecha(p.fecha_pedido)) + '</td>';
            html += '<td>' + (p.es_cotizacion ? 'Cotización' : 'Venta') + '<br><small style="color:var(--color-text-muted);">' + escHtml(p.tipo_documento || '') + '</small></td>';
            html += '<td><strong>' + fmtQ(p.total) + '</strong></td>';
            html += '<td>' + badgePedido(p.estado_pedido) + '</td>';
            html += '<td>' + badgePago(p.estado_pago) + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
    }

    body.innerHTML = html;
}

function openClienteModal() {
    document.getElementById('clienteModal').classList.add('show');
    setTimeout(() => document.querySelector('#clienteForm input[name="nombre"]').focus(), 200);
}
function closeModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('show'); });
});

function submitClienteForm(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    Swal.fire({
        title: 'Guardando...',
        text: 'Por favor espere',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch('save_cliente.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Cliente guardado!',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    closeModal('clienteModal');
                    form.reset();
                    location.reload();
                });
            } else {
                throw new Error(data.message || 'Error desconocido');
            }
        })
        .catch(err => {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message });
            submitBtn.disabled = false;
        });
}
</script>
</body>
</html>
