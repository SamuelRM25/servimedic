<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

date_default_timezone_set('America/Guatemala');

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener compras
    $stmt = $conn->prepare("
        SELECT c.*, u.nombre as usuario_nombre, u.apellido as usuario_apellido
        FROM compras c
        LEFT JOIN usuarios u ON c.id_usuario = u.id
        ORDER BY c.fecha_compra DESC, c.id_compra DESC
    ");
    $stmt->execute();
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Proveedores registrados (autocompletado en el formulario de compra)
    $proveedoresLista = [];
    try {
        $proveedoresLista = $conn->query("SELECT nombre FROM proveedores ORDER BY nombre ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $proveedoresLista = [];
    }

    // Casas farmacéuticas registradas (autocompletado por medicamento)
    $casasFarmaceuticasLista = [];
    try {
        $casasFarmaceuticasLista = $conn->query("SELECT nombre FROM casas_farmaceuticas ORDER BY nombre ASC")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        $casasFarmaceuticasLista = [];
    }

    // Catálogo de medicamentos (código único -> datos del medicamento)
    $medicamentosLista = [];
    try {
        $medicamentosLista = $conn->query("SELECT codigo, nombre, molecula, presentacion, casa_farmaceutica FROM medicamentos ORDER BY codigo ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $medicamentosLista = [];
    }

    // Catálogo de proveedores con su visitador (nombre y teléfono)
    $proveedoresCatalogo = [];
    try {
        asegurarColumnasVisitador($conn);
        foreach ($conn->query("SELECT nombre, visitador_nombre, visitador_telefono, visitador_actualizado FROM proveedores")->fetchAll(PDO::FETCH_ASSOC) as $pc) {
            $proveedoresCatalogo[mb_strtolower(trim($pc['nombre']), 'UTF-8')] = $pc;
        }
    } catch (Exception $e) {
        error_log('Visitadores: ' . $e->getMessage());
    }

    // Agrupar compras por proveedor (respaldo: casa farmacéutica)
    $porProveedor = [];
    foreach ($compras as $compra) {
        $nombreProv = trim($compra['proveedor'] ?? '');
        if ($nombreProv === '') {
            $nombreProv = trim($compra['casa_farmaceutica'] ?? '');
        }
        if ($nombreProv === '') $nombreProv = 'Sin proveedor';
        $key = mb_strtolower($nombreProv, 'UTF-8');
        if (!isset($porProveedor[$key])) {
            $porProveedor[$key] = [
                'nombre' => $nombreProv,
                'total' => 0.0,
                'abonado' => 0.0,
                'saldo' => 0.0,
                'items' => []
            ];
        }
        $porProveedor[$key]['total'] += (float)$compra['total'];
        $porProveedor[$key]['abonado'] += (float)$compra['abonado'];
        $porProveedor[$key]['saldo'] += (float)$compra['saldo'];
        $porProveedor[$key]['items'][] = $compra;
    }
    uasort($porProveedor, fn($a, $b) => $b['total'] <=> $a['total']);

    // Proveedores del catálogo que aún no tienen compras (para poder registrar su visitador)
    foreach ($proveedoresCatalogo as $key => $pc) {
        if (!isset($porProveedor[$key]) && trim($pc['nombre']) !== '') {
            $porProveedor[$key] = ['nombre' => trim($pc['nombre']), 'total' => 0.0, 'abonado' => 0.0, 'saldo' => 0.0, 'items' => []];
        }
    }
    foreach ($porProveedor as $key => &$provRef) {
        $provRef['visitador'] = $proveedoresCatalogo[$key] ?? null;
    }
    unset($provRef);

    $provTotalGeneral = array_sum(array_column($porProveedor, 'total'));
    $provSaldoGeneral = array_sum(array_column($porProveedor, 'saldo'));

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$userRole = $_SESSION['rol'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras - CLINOVA</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-orange: #FF3D00;
            --color-orange-light: #FF6E40;
            --color-orange-dark: #E63400;
            --color-blue: #1FA8FF;
            --color-blue-light: #42B5FF;
            --color-blue-dark: #0277BD;
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

        /* Header */
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

        /* Chevron rotation */
        .group-header[aria-expanded="true"] .bi-chevron-down {
            transform: rotate(180deg);
        }
        .bi-chevron-down {
            transition: transform 0.3s ease;
        }

        /* Nested table styling */
        .nested-table-header th {
            background-color: #e9ecef !important;
            color: #495057 !important;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Bootstrap Collapse Minimal Styles */
        .collapse:not(.show) {
            display: none;
        }
        .collapsing {
            height: 0;
            overflow: hidden;
            transition: height 0.35s ease;
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
            background: linear-gradient(135deg, var(--color-orange), var(--color-orange-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        /* Stats */
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

        /* Table */
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

        .badge-factura { background: #BEE3F8; color: #2C5282; }
        .badge-nota { background: #C6F6D5; color: #22543D; }
        .badge-consumidor { background: #FED7D7; color: #742A2A; }
        
        .badge-contado { background: #C6F6D5; color: #22543D; }
        .badge-credito30 { background: #FEFCBF; color: #744210; }
        .badge-credito60 { background: #FED7D7; color: #742A2A; }
        
        .badge-pendiente { background: #FED7D7; color: #742A2A; }
        .badge-abonado { background: #FEFCBF; color: #744210; }
        .badge-pagado { background: #C6F6D5; color: #22543D; }
        .badge-entregado { background: #BEE3F8; color: #2C5282; }
        .badge-ingresado { background: #D6BCFA; color: #44337A; }

        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            margin: 0 0.25rem;
        }

        .action-btn-pay { background: #C6F6D5; color: #22543D; }
        .action-btn-pay:hover { background: #9AE6B4; }
        
        .action-btn-edit { background: #FEFCBF; color: #744210; }
        .action-btn-edit:hover { background: #FAF089; }

        .action-btn-delete { background: #FED7D7; color: #742A2A; }
        .action-btn-delete:hover { background: #FEB2B2; }

        /* Modal */
        /* Autocompletado (proveedores y casas farmacéuticas) */
        .autocomplete-results {
            position: fixed; z-index: 4000; display: none;
            background: var(--color-white); border: 2px solid var(--color-border);
            border-radius: 10px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            max-height: 220px; overflow-y: auto;
        }
        .autocomplete-results.show { display: block; }
        .autocomplete-item { padding: 0.55rem 0.9rem; font-size: 0.85rem; cursor: pointer; }
        .autocomplete-item:hover, .autocomplete-item.active { background: rgba(0, 0, 0, 0.06); }
        .autocomplete-results.med-results { max-height: 320px; }
        .autocomplete-item .ac-main { font-weight: 600; }
        .autocomplete-item .ac-sub { font-size: 0.75rem; opacity: 0.7; margin-top: 2px; }
        .autocomplete-item .ac-code { float: right; font-size: 0.72rem; font-weight: 600; opacity: 0.6; margin-left: 0.75rem; }
        .autocomplete-empty { padding: 0.6rem 0.9rem; font-size: 0.8rem; opacity: 0.7; font-style: italic; }
        .items-table input.codigo-auto { background: rgba(0, 0, 0, 0.04); color: inherit; opacity: 0.85; }

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
            max-width: 700px;
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

        @media (max-width: 768px) {
            .form-row, .form-row-3 {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1rem;
            }
        }

        .totals-section {
            background: var(--color-bg);
            padding: 1rem;
            border-radius: 12px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .total-row.main {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--color-orange);
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 2px solid var(--color-border);
        }

        /* Search Section */
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

        .no-results {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--color-text-light);
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
        }

        /* Batch Modal Styles */
        .modal.modal-wide {
            max-width: 1200px;
        }

        .items-table-container {
            margin-top: 1.5rem;
            border: 1px solid var(--color-border);
            border-radius: 10px;
            overflow: hidden;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            background: var(--color-bg);
            padding: 0.75rem;
            text-align: left;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--color-text-light);
            border-bottom: 1px solid var(--color-border);
        }

        .items-table td {
            padding: 0.5rem;
            border-bottom: 1px solid var(--color-border);
        }

        .items-table input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--color-border);
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .items-table input:focus {
            outline: none;
            border-color: var(--color-blue);
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-remove {
            background: #FED7D7;
            color: #742A2A;
        }

        .btn-remove:hover {
            background: #FEB2B2;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--color-orange);
            display: inline-block;
        }

        .badge-info { background: #BEE3F8; color: #2C5282; }
        .badge-secondary { background: #E2E8F0; color: #4A5568; }

        /* Pestañas */
        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            background: var(--color-white);
            padding: 0.5rem;
            border-radius: 14px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            width: fit-content;
        }

        .tab {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: var(--color-text-light);
            font-family: inherit;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
        }

        .tab:hover { background: var(--color-bg); color: var(--color-text); }
        .tab.active { background: var(--color-orange); color: #fff; box-shadow: 0 4px 12px rgba(255, 61, 0, 0.3); }

        .tab-count {
            background: rgba(0, 0, 0, 0.1);
            padding: 0.1rem 0.5rem;
            border-radius: 999px;
            font-size: 0.78rem;
        }

        .tab.active .tab-count { background: rgba(255, 255, 255, 0.25); }

        /* Panel por proveedor */
        .provider-summary {
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            background: linear-gradient(135deg, #FFF5F0, #F0F8FF);
            border-radius: 14px;
            border: 1px solid var(--color-border);
        }

        .provider-summary-item {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .provider-summary-item .label {
            font-size: 0.78rem;
            color: var(--color-text-light);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .provider-summary-item strong { font-size: 1.25rem; color: var(--color-text); }

        .provider-summary-hint {
            margin-left: auto;
            font-size: 0.82rem;
            color: var(--color-text-light);
        }

        .provider-card {
            background: var(--color-white);
            border: 1px solid var(--color-border);
            border-radius: 14px;
            margin-bottom: 0.85rem;
            overflow: hidden;
            transition: box-shadow 0.25s ease, border-color 0.25s ease;
        }

        .provider-card:hover { box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08); }
        .provider-card.open { border-color: var(--color-blue); box-shadow: 0 8px 24px rgba(31, 168, 255, 0.15); }

        .provider-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            cursor: pointer;
            user-select: none;
            transition: background 0.2s ease;
        }

        .provider-header:hover { background: #FAFCFF; }

        .provider-info { display: flex; align-items: center; gap: 0.9rem; }

        .provider-chevron {
            font-size: 0.9rem;
            color: var(--color-text-light);
            transition: transform 0.3s ease;
        }

        .provider-card.open .provider-chevron { transform: rotate(90deg); color: var(--color-blue); }

        .provider-avatar {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
            color: #fff;
            font-weight: 700;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .provider-name { font-size: 1.02rem; color: var(--color-text); }

        .provider-meta { font-size: 0.8rem; color: var(--color-text-light); margin-top: 0.15rem; }

        .provider-totals { display: flex; align-items: center; gap: 1.75rem; }

        .provider-total-item { display: flex; flex-direction: column; align-items: flex-end; gap: 0.1rem; }

        .provider-total-label {
            font-size: 0.72rem;
            color: var(--color-text-light);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .provider-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease;
            border-top: 1px solid transparent;
        }

        .provider-card.open .provider-body { border-top-color: var(--color-border); }

        .provider-detail-table { width: 100%; border-collapse: collapse; }

        .provider-detail-table th {
            padding: 0.7rem 0.75rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--color-text-light);
            text-transform: uppercase;
            background: #F8FAFC;
            border-bottom: 1px solid var(--color-border);
        }

        .provider-detail-table td {
            padding: 0.7rem 0.75rem;
            font-size: 0.88rem;
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
        }

        .provider-detail-total td { background: #F8FAFC; font-size: 0.9rem; }

        .provider-card.hidden-by-filter { display: none; }

        /* Visitador del proveedor */
        .provider-visitador {
            display: flex; align-items: center; flex-wrap: wrap; gap: 0.4rem;
            margin-top: 0.35rem; font-size: 0.8rem; color: var(--color-text);
        }
        .provider-visitador .vis-label { color: var(--color-text-light); }
        .provider-visitador .vis-empty { color: var(--color-text-light); font-style: italic; }
        .vis-phone-btn {
            display: inline-flex; align-items: center; gap: 0.3rem;
            padding: 0.15rem 0.55rem; border-radius: 999px;
            border: 1px solid #C6F6D5; background: #F0FFF4; color: #22543D;
            font: inherit; font-weight: 600; cursor: pointer;
        }
        .vis-phone-btn:hover { background: #C6F6D5; }
        .vis-edit-btn {
            display: inline-flex; align-items: center; gap: 0.25rem;
            padding: 0.15rem 0.5rem; border-radius: 999px;
            border: 1px solid var(--color-border); background: var(--color-white);
            color: var(--color-text-light); font: inherit; font-size: 0.75rem; cursor: pointer;
        }
        .vis-edit-btn:hover { color: var(--color-blue); border-color: var(--color-blue); }
        .vis-menu {
            position: fixed; z-index: 4500; display: none; min-width: 210px;
            background: var(--color-white); border: 1px solid var(--color-border);
            border-radius: 12px; box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15); padding: 0.35rem;
        }
        .vis-menu.show { display: block; }
        .vis-menu-title { padding: 0.45rem 0.7rem 0.3rem; font-size: 0.75rem; color: var(--color-text-light); }
        .vis-menu a {
            display: flex; align-items: center; gap: 0.6rem;
            padding: 0.6rem 0.7rem; border-radius: 8px;
            color: var(--color-text); text-decoration: none; font-size: 0.9rem;
        }
        .vis-menu a:hover { background: var(--color-bg); }
        .vis-menu a .bi-whatsapp { color: #25D366; font-size: 1.1rem; }
        .vis-menu a .bi-telephone-fill { color: var(--color-blue); font-size: 1rem; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="page-title-section">
                <a href="../dashboard/index.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="page-title">Gestión de Compras</h1>
            </div>
            <?php include '../../includes/clock.php'; ?>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="bi bi-plus-circle"></i>
                Nueva Compra
            </button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Compras</div>
                <div class="stat-value"><?php echo count($compras); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pendientes de Pago</div>
                <div class="stat-value">
                    <?php echo count(array_filter($compras, fn($c) => $c['saldo'] > 0)); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Adeudado</div>
                <div class="stat-value">
                    Q<?php echo number_format(array_sum(array_column($compras, 'saldo')), 2); ?>
                </div>
            </div>
        </div>

        <!-- Pestañas -->
        <div class="tabs">
            <button class="tab active" id="tabTodas" onclick="switchPurchasesTab('todas')">
                <i class="bi bi-list-ul"></i> Todas las Compras
                <span class="tab-count"><?php echo count($compras); ?></span>
            </button>
            <button class="tab" id="tabProveedores" onclick="switchPurchasesTab('proveedores')">
                <i class="bi bi-building"></i> Por Proveedor
                <span class="tab-count"><?php echo count($porProveedor); ?></span>
            </button>
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" id="searchInput" placeholder="Buscar compra...">
            </div>
            <div class="form-check form-switch ms-3">
                <input class="form-check-input" type="checkbox" id="hidePaidToggle">
                <label class="form-check-label" for="hidePaidToggle">Ocultar Pagados</label>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container" id="panelTodas">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Referencia</th>
                            <th>Proveedor / Items</th>
                            <th>Fecha</th>
                            <th>Tipo Doc.</th>
                            <th>Total Compra</th>
                            <th>Saldo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Group purchases by codigo_referencia
                        $groupedPurchases = [];
                        foreach ($compras as $compra) {
                            $ref = $compra['codigo_referencia'] ?? 'SIN-REF-' . $compra['id_compra'];
                            if (!isset($groupedPurchases[$ref])) {
                                $groupedPurchases[$ref] = [
                                    'ref' => $ref,
                                    'fecha' => $compra['fecha_compra'],
                                    'proveedor' => trim($compra['proveedor'] ?? '') !== '' ? $compra['proveedor'] : $compra['casa_farmaceutica'],
                                    'factura' => $compra['numero_factura'] ?? '',
                                    'tipo_factura' => $compra['tipo_factura'],
                                    'total' => 0,
                                    'saldo' => 0,
                                    'estado' => $compra['estado'], // Status of the batch? Or mixed?
                                    'items' => []
                                ];
                            }
                            $groupedPurchases[$ref]['items'][] = $compra;
                            $groupedPurchases[$ref]['total'] += $compra['total'];
                            $groupedPurchases[$ref]['saldo'] += $compra['saldo'];
                            
                            // Update invoice number if any item has it (from receiving)
                            if (!empty($compra['numero_factura'])) {
                                $groupedPurchases[$ref]['factura'] = $compra['numero_factura'];
                            }
                        }
                        
                        foreach ($groupedPurchases as $ref => $group): 
                            $uniqueId = 'collapse-' . $ref;
                        ?>
                        <!-- Summary Row -->
                        <tr class="group-header" data-bs-toggle="collapse" data-bs-target="#<?php echo $uniqueId; ?>" aria-expanded="false" style="cursor: pointer; background-color: #fff; border-bottom: 1px solid #dee2e6;">
                            <td>
                                <button class="btn btn-sm btn-link text-decoration-none p-0 me-2">
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                                <span class="badge badge-info"><?php echo htmlspecialchars($ref); ?></span>
                                <?php if($group['factura']): ?>
                                    <br><small class="text-muted ms-4">Fact: <?php echo htmlspecialchars($group['factura']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($group['proveedor']); ?></strong>
                                <br>
                                <small class="text-muted"><?php echo count($group['items']); ?> items</small>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($group['fecha'])); ?></td>
                            <td>
                                <span class="badge badge-secondary"><?php echo htmlspecialchars($group['tipo_factura']); ?></span>
                            </td>
                            <td><strong>Q<?php echo number_format($group['total'], 2); ?></strong></td>
                            <td>
                                <strong style="color: <?php echo $group['saldo'] > 0 ? 'var(--color-danger)' : 'var(--color-success)'; ?>">
                                    Q<?php echo number_format($group['saldo'], 2); ?>
                                </strong>
                            </td>
                            <td>
                                <?php 
                                // Simple status logic for group
                                $statusClass = 'badge-pendiente';
                                if ($group['saldo'] <= 0) $statusClass = 'badge-pagado';
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $group['saldo'] <= 0 ? 'Pagado' : 'Pendiente'; ?>
                                </span>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" onclick="event.stopPropagation()">
                                        Acciones
                                    </button>
                                    <ul class="dropdown-menu">
                                        <?php if ($group['saldo'] > 0): ?>
                                        <li><a class="dropdown-item" href="#" onclick="openPaymentModal(<?php echo $group['items'][0]['id_compra']; ?>, <?php echo $group['saldo']; ?>)">Abonar</a></li>
                                        <?php else: ?>
                                        <li><span class="dropdown-item-text text-muted">Pagado</span></li>
                                        <?php endif; ?>
                                        <!-- Delete All removed as requested -->
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <!-- Details Row -->
                        <tr>
                            <td colspan="8" class="p-0" style="border: none;">
                                <div id="<?php echo $uniqueId; ?>" class="collapse">
                                    <div class="p-4 bg-light">
                                        <div class="card shadow-sm border-0">
                                            <div class="card-body p-0">
                                                <table class="table table-hover mb-0">
                                                    <thead class="nested-table-header">
                                                        <tr>
                                                            <th class="ps-4">Medicamento</th>
                                                            <th>Código</th>
                                                            <th>Presentación</th>
                                                            <th>Cant.</th>
                                                            <th>P. Unit</th>
                                                            <th>Subtotal</th>
                                                            <th>Estado</th>
                                                            <th>Factura</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($group['items'] as $item): ?>
                                                        <tr>
                                                            <td class="ps-4"><?php echo htmlspecialchars($item['nombre_medicamento']); ?></td>
                                                            <td><?php echo !empty($item['codigo_medicamento']) ? '<span class="badge badge-info">' . htmlspecialchars($item['codigo_medicamento']) . '</span>' : '<span class="text-muted">-</span>'; ?></td>
                                                            <td><?php echo htmlspecialchars($item['presentacion']); ?></td>
                                                            <td><?php echo $item['cantidad']; ?></td>
                                                            <td>Q<?php echo number_format($item['precio_unitario'], 2); ?></td>
                                                            <td>Q<?php echo number_format($item['total'], 2); ?></td>
                                                            <td>
                                                                <span class="badge <?php echo $item['estado'] === 'Ingresado' ? 'badge-ingresado' : 'badge-pendiente'; ?>">
                                                                    <?php echo $item['estado']; ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($item['numero_factura'] ?? '-'); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="noResults" class="no-results" style="display: none;">
                    <i class="bi bi-search"></i>
                    <p>No se encontraron compras que coincidan con la búsqueda</p>
                </div>
            </div>
        </div>

        <!-- Panel: Por Proveedor -->
        <div class="table-container" id="panelProveedores" style="display: none;">
            <div class="provider-summary">
                <div class="provider-summary-item">
                    <span class="label">Proveedores</span>
                    <strong><?php echo count($porProveedor); ?></strong>
                </div>
                <div class="provider-summary-item">
                    <span class="label">Total Comprado</span>
                    <strong>Q<?php echo number_format($provTotalGeneral, 2); ?></strong>
                </div>
                <div class="provider-summary-item">
                    <span class="label">Saldo Pendiente</span>
                    <strong style="color: <?php echo $provSaldoGeneral > 0 ? 'var(--color-danger)' : 'var(--color-success)'; ?>;">
                        Q<?php echo number_format($provSaldoGeneral, 2); ?>
                    </strong>
                </div>
                <div class="provider-summary-hint">
                    <i class="bi bi-hand-index-thumb"></i> Haz clic en un proveedor para ver el detalle de sus compras
                </div>
            </div>

            <?php if (!$porProveedor): ?>
                <div class="no-results" style="display: block;">
                    <i class="bi bi-building"></i>
                    <p>No hay proveedores registrados</p>
                </div>
            <?php endif; ?>

            <?php foreach ($porProveedor as $prov): ?>
            <?php
                $provPagado = $prov['saldo'] <= 0.004;
                $numCompras = count($prov['items']);
                $facturasProv = array_unique(array_filter(array_map(fn($i) => $i['numero_factura'] ?? '', $prov['items'])));
            ?>
            <div class="provider-card" data-proveedor="<?php echo htmlspecialchars(mb_strtolower($prov['nombre'], 'UTF-8')); ?>" data-saldo="<?php echo $prov['saldo']; ?>">
                <div class="provider-header" onclick="toggleProvider(this)">
                    <div class="provider-info">
                        <i class="bi bi-chevron-right provider-chevron"></i>
                        <div class="provider-avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($prov['nombre'], 0, 2, 'UTF-8'), 'UTF-8')); ?></div>
                        <div>
                            <strong class="provider-name"><?php echo htmlspecialchars($prov['nombre']); ?></strong>
                            <div class="provider-meta">
                                <?php echo $numCompras; ?> compra<?php echo $numCompras === 1 ? '' : 's'; ?>
                                <?php if ($facturasProv): ?> &middot; <?php echo count($facturasProv); ?> factura<?php echo count($facturasProv) === 1 ? '' : 's'; ?><?php endif; ?>
                            </div>
                            <?php if (mb_strtolower($prov['nombre'], 'UTF-8') !== 'sin proveedor'):
                                $vis = $prov['visitador'] ?? null;
                                $visNombre = trim($vis['visitador_nombre'] ?? '');
                                $visTel = trim($vis['visitador_telefono'] ?? '');
                            ?>
                            <div class="provider-visitador"
                                 data-proveedor-nombre="<?php echo htmlspecialchars($prov['nombre']); ?>"
                                 data-vis-nombre="<?php echo htmlspecialchars($visNombre); ?>"
                                 data-vis-telefono="<?php echo htmlspecialchars($visTel); ?>"
                                 onclick="event.stopPropagation()">
                                <i class="bi bi-person-badge"></i>
                                <?php if ($visNombre !== ''): ?>
                                    <span class="vis-label">Visitador:</span>
                                    <strong><?php echo htmlspecialchars($visNombre); ?></strong>
                                    <?php if ($visTel !== ''): ?>
                                    <button type="button" class="vis-phone-btn" title="Llamar o escribir por WhatsApp" onclick="openVisitadorMenu(this)">
                                        <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($visTel); ?>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="vis-edit-btn" title="Cambiar visitador" onclick="openVisitadorModal(this)">
                                        <i class="bi bi-pencil"></i> Cambiar
                                    </button>
                                <?php else: ?>
                                    <span class="vis-empty">Sin visitador</span>
                                    <button type="button" class="vis-edit-btn" onclick="openVisitadorModal(this)">
                                        <i class="bi bi-plus-lg"></i> Agregar visitador
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="provider-totals">
                        <div class="provider-total-item">
                            <span class="provider-total-label">Total</span>
                            <strong>Q<?php echo number_format($prov['total'], 2); ?></strong>
                        </div>
                        <div class="provider-total-item">
                            <span class="provider-total-label">Saldo</span>
                            <strong style="color: <?php echo $provPagado ? 'var(--color-success)' : 'var(--color-danger)'; ?>;">
                                Q<?php echo number_format($prov['saldo'], 2); ?>
                            </strong>
                        </div>
                        <?php if ($numCompras === 0): ?>
                        <span class="badge" style="background: var(--color-bg); color: var(--color-text-light);">Sin compras</span>
                        <?php else: ?>
                        <span class="badge <?php echo $provPagado ? 'badge-pagado' : 'badge-pendiente'; ?>">
                            <?php echo $provPagado ? 'Pagado' : 'Pendiente'; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="provider-body">
                    <table class="table table-hover provider-detail-table">
                        <thead>
                            <tr>
                                <th class="ps-4">Fecha</th>
                                <th>Referencia</th>
                                <th>Factura</th>
                                <th>Medicamento</th>
                                <th>Código</th>
                                <th>Cant.</th>
                                <th>Total</th>
                                <th>Saldo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prov['items'] as $item): ?>
                            <tr>
                                <td class="ps-4"><?php echo date('d/m/Y', strtotime($item['fecha_compra'])); ?></td>
                                <td><span class="badge badge-info"><?php echo htmlspecialchars($item['codigo_referencia'] ?? '-'); ?></span></td>
                                <td><?php echo htmlspecialchars($item['numero_factura'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($item['nombre_medicamento']); ?></td>
                                <td><?php echo !empty($item['codigo_medicamento']) ? '<strong>' . htmlspecialchars($item['codigo_medicamento']) . '</strong>' : '<span class="text-muted">-</span>'; ?></td>
                                <td><?php echo $item['cantidad']; ?></td>
                                <td><strong>Q<?php echo number_format($item['total'], 2); ?></strong></td>
                                <td>
                                    <strong style="color: <?php echo $item['saldo'] > 0 ? 'var(--color-danger)' : 'var(--color-success)'; ?>;">
                                        Q<?php echo number_format($item['saldo'], 2); ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge <?php echo $item['saldo'] <= 0 ? 'badge-pagado' : 'badge-pendiente'; ?>">
                                        <?php echo $item['saldo'] <= 0 ? 'Pagado' : 'Pendiente'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($item['saldo'] > 0): ?>
                                    <button class="btn btn-sm btn-secondary" onclick="event.stopPropagation(); openPaymentModal(<?php echo $item['id_compra']; ?>, <?php echo $item['saldo']; ?>)">
                                        <i class="bi bi-cash-coin"></i> Abonar
                                    </button>
                                    <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.85rem;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="provider-detail-total">
                                <td colspan="6" style="text-align: right;">Total proveedor:</td>
                                <td><strong>Q<?php echo number_format($prov['total'], 2); ?></strong></td>
                                <td colspan="3">
                                    <strong style="color: <?php echo $provPagado ? 'var(--color-success)' : 'var(--color-danger)'; ?>;">
                                        Q<?php echo number_format($prov['saldo'], 2); ?>
                                    </strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

            <div id="noResultsProveedores" class="no-results" style="display: none;">
                <i class="bi bi-search"></i>
                <p>No se encontraron proveedores que coincidan con la búsqueda</p>
            </div>
        </div>
    </div>

    <!-- Add Purchase Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal modal-wide">
            <div class="modal-header">
                <h2 class="modal-title">Nueva Compra (Lote)</h2>
                <button class="modal-close" onclick="closeModal('addModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="batchPurchaseForm" onsubmit="submitBatchPurchase(event)">
                <div class="modal-body">
                    <!-- Master Section -->
                    <div class="section-title">Datos Generales</div>
                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">Fecha de Compra *</label>
                            <input type="date" name="fecha_compra" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tipo de Factura *</label>
                            <select name="tipo_factura" class="form-control" required>
                                <option value="Factura">Factura</option>
                                <option value="Nota de Envío">Nota de Envío</option>
                                <option value="Consumidor Final">Consumidor Final</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Número de Factura/Envío</label>
                            <input type="text" name="numero_factura" class="form-control" placeholder="Opcional">
                        </div>
                    </div>

                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">Tipo de Pago *</label>
                            <select name="tipo_pago" class="form-control" required>
                                <option value="Al Contado">Al Contado</option>
                                <option value="Crédito 30 días">Crédito 30 días</option>
                                <option value="Crédito 60 días">Crédito 60 días</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Proveedor *</label>
                            <input type="text" name="proveedor" id="proveedorInput" class="form-control" required autocomplete="off" placeholder="Buscar o escribir proveedor...">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Observaciones Generales</label>
                            <input type="text" name="observaciones" class="form-control">
                        </div>
                    </div>

                    <!-- Items Section -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                        <div class="section-title" style="margin-bottom: 0;">Medicamentos</div>
                        <button type="button" class="btn btn-primary" onclick="addPurchaseRow()" style="padding: 0.4rem 1rem; font-size: 0.85rem;">
                            <i class="bi bi-plus-lg"></i> Agregar Fila
                        </button>
                    </div>

                    <div class="items-table-container">
                        <table class="items-table" id="purchaseItemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 16%">Medicamento</th>
                                    <th style="width: 6%">Código</th>
                                    <th style="width: 9%">Molécula</th>
                                    <th style="width: 9%">Presentación</th>
                                    <th style="width: 9%">Casa Farm.</th>
                                    <th style="width: 7%">Lote</th>
                                    <th style="width: 7%">Vence</th>
                                    <th style="width: 6%">Cant.</th>
                                    <th style="width: 9%">P. Compra</th>
                                    <th style="width: 9%">P. Venta</th>
                                    <th style="width: 9%">Subtotal</th>
                                    <th style="width: 2%"></th>
                                </tr>
                            </thead>
                            <tbody id="purchaseItemsBody">
                                <!-- Rows will be added here -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="totals-section" style="margin-top: 1rem; text-align: right;">
                        <div class="total-row main" style="justify-content: flex-end; gap: 2rem;">
                            <span>Total Compra:</span>
                            <span id="totalCompraDisplay">Q0.00</span>
                        </div>
                    </div>
                </div>
                <div style="padding: 1.5rem; border-top: 1px solid var(--color-border);">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="bi bi-save"></i>
                        Guardar Compra Completa
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Modal -->
    <!-- Visitador del proveedor -->
    <div class="modal-overlay" id="visitadorModal">
        <div class="modal" style="max-width: 460px;">
            <div class="modal-header">
                <h2 class="modal-title" id="visitadorModalTitle">Visitador</h2>
                <button class="modal-close" onclick="closeModal('visitadorModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form id="visitadorForm" onsubmit="guardarVisitador(event)">
                <div class="modal-body">
                    <p style="margin-bottom: 1rem; color: var(--color-text-light); font-size: 0.9rem;">
                        Proveedor: <strong id="visitadorProveedor" style="color: var(--color-text);"></strong>
                    </p>
                    <div class="form-group">
                        <label class="form-label">Nombre del visitador *</label>
                        <input type="text" id="visitadorNombre" class="form-control" maxlength="150" required placeholder="Ej. Carlos López">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Teléfono</label>
                        <input type="tel" id="visitadorTelefono" class="form-control" maxlength="20" placeholder="Ej. 5555 1234" inputmode="tel">
                    </div>
                    <p style="font-size: 0.8rem; color: var(--color-text-light);">
                        Si cambió el visitador, escribe los datos del nuevo y guarda: se reemplaza el anterior.
                    </p>
                </div>
                <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--color-border); display: flex; gap: 0.75rem; justify-content: space-between;">
                    <button type="button" class="btn btn-secondary" id="visitadorQuitarBtn" onclick="quitarVisitador()">
                        <i class="bi bi-person-x"></i> Quitar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="vis-menu" id="visitadorMenu">
        <div class="vis-menu-title" id="visitadorMenuTitle"></div>
        <a href="#" id="visitadorMenuWhatsapp" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> Enviar WhatsApp</a>
        <a href="#" id="visitadorMenuLlamar"><i class="bi bi-telephone-fill"></i> Llamar</a>
    </div>

    <div class="modal-overlay" id="paymentModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Registrar Abono</h2>
                <button class="modal-close" onclick="closeModal('paymentModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form action="add_payment.php" method="POST" onsubmit="showLoading('Registrando abono...')">
                <input type="hidden" name="id_compra" id="payment_id_compra">
                <div class="modal-body">
                    <div class="totals-section" style="margin-bottom: 1.5rem;">
                        <div class="total-row">
                            <span>Saldo Pendiente:</span>
                            <strong style="color: var(--color-danger);">Q<span id="saldo_pendiente">0.00</span></strong>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Monto a Abonar *</label>
                            <input type="number" name="monto" id="payment_amount" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Fecha de Abono *</label>
                            <input type="date" name="fecha_abono" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Forma de Pago *</label>
                            <select name="forma_pago" class="form-control" required>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Tarjeta Débito">Tarjeta Débito</option>
                                <option value="Tarjeta Crédito">Tarjeta Crédito</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Referencia</label>
                            <input type="text" name="referencia" class="form-control" placeholder="Núm. transacción, cheque, etc.">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div style="padding: 1.5rem; border-top: 1px solid var(--color-border);">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="bi bi-cash-coin"></i>
                        Registrar Abono
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== Pestañas: Todas las Compras / Por Proveedor =====
        function switchPurchasesTab(tab) {
            const isTodas = tab === 'todas';
            document.getElementById('tabTodas').classList.toggle('active', isTodas);
            document.getElementById('tabProveedores').classList.toggle('active', !isTodas);
            document.getElementById('panelTodas').style.display = isTodas ? '' : 'none';
            document.getElementById('panelProveedores').style.display = isTodas ? 'none' : '';
            try { sessionStorage.setItem('purchasesTab', tab); } catch (e) {}
        }

        // Expandir/colapsar tarjeta de proveedor con animación
        function toggleProvider(header) {
            const card = header.closest('.provider-card');
            const body = card.querySelector('.provider-body');
            if (card.classList.contains('open')) {
                body.style.maxHeight = body.scrollHeight + 'px';
                requestAnimationFrame(() => { body.style.maxHeight = '0px'; });
                card.classList.remove('open');
            } else {
                card.classList.add('open');
                body.style.maxHeight = body.scrollHeight + 'px';
                const onEnd = function (e) {
                    if (e.propertyName === 'max-height' && card.classList.contains('open')) {
                        body.style.maxHeight = 'none';
                    }
                    body.removeEventListener('transitionend', onEnd);
                };
                body.addEventListener('transitionend', onEnd);
            }
        }

        const proveedoresData = <?php echo json_encode($proveedoresLista, JSON_UNESCAPED_UNICODE); ?>;
        const casasFarmaceuticasData = <?php echo json_encode($casasFarmaceuticasLista, JSON_UNESCAPED_UNICODE); ?>;
        const medicamentosData = <?php echo json_encode($medicamentosLista, JSON_UNESCAPED_UNICODE); ?>;

        let autocompleteDropdown = null;
        let autocompleteInput = null;
        let autocompleteSelect = null;

        function autocompleteEscape(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function getAutocompleteDropdown() {
            if (!autocompleteDropdown) {
                autocompleteDropdown = document.createElement('div');
                autocompleteDropdown.className = 'autocomplete-results';
                document.body.appendChild(autocompleteDropdown);
                autocompleteDropdown.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    const item = e.target.closest('.autocomplete-item');
                    if (item && autocompleteInput && autocompleteSelect) {
                        autocompleteSelect(item.dataset.value);
                        autocompleteDropdown.classList.remove('show');
                    }
                });
            }
            return autocompleteDropdown;
        }

        function showAutocomplete(input, matches, onSelect, opts = {}) {
            const dd = getAutocompleteDropdown();
            if (!matches.length && !opts.emptyText) { dd.classList.remove('show'); return; }
            autocompleteInput = input;
            autocompleteSelect = onSelect;
            dd.classList.toggle('med-results', !!opts.rich);
            dd.innerHTML = matches.length
                ? matches.map(m => '<div class="autocomplete-item" data-value="' + autocompleteEscape(m.value) + '">'
                    + (m.code ? '<span class="ac-code">' + autocompleteEscape(m.code) + '</span>' : '')
                    + (m.sub !== undefined
                        ? '<div class="ac-main">' + autocompleteEscape(m.label) + '</div><div class="ac-sub">' + autocompleteEscape(m.sub) + '</div>'
                        : autocompleteEscape(m.label))
                    + '</div>').join('')
                : '<div class="autocomplete-empty">' + autocompleteEscape(opts.emptyText) + '</div>';
            const r = input.getBoundingClientRect();
            dd.style.left = r.left + 'px';
            dd.style.top = (r.bottom + 4) + 'px';
            dd.style.width = Math.max(r.width, opts.minWidth || 200) + 'px';
            dd.classList.add('show');
        }

        // Navegación con teclado (flechas, Enter, Escape) en la lista desplegable
        function autocompleteKeydown(e, input) {
            const dd = autocompleteDropdown;
            if (!dd || !dd.classList.contains('show') || autocompleteInput !== input) return;
            const items = Array.from(dd.querySelectorAll('.autocomplete-item'));
            let idx = items.findIndex(i => i.classList.contains('active'));
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!items.length) return;
                idx = e.key === 'ArrowDown' ? Math.min(idx + 1, items.length - 1) : Math.max(idx - 1, 0);
                items.forEach(i => i.classList.remove('active'));
                items[idx].classList.add('active');
                items[idx].scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                if (idx >= 0) {
                    e.preventDefault();
                    autocompleteSelect(items[idx].dataset.value);
                    dd.classList.remove('show');
                } else {
                    e.preventDefault(); // no enviar el formulario al presionar Enter en la búsqueda
                    dd.classList.remove('show');
                }
            } else if (e.key === 'Escape') {
                dd.classList.remove('show');
            }
        }

        function attachAutocomplete(input, data) {
            if (!input || input.dataset.acAttached) return;
            input.dataset.acAttached = '1';
            input.setAttribute('autocomplete', 'off');

            const render = () => {
                const term = input.value.trim().toLowerCase();
                const matches = term ? data.filter(p => p.toLowerCase().includes(term)).slice(0, 8) : [];
                showAutocomplete(input, matches.map(p => ({ value: p, label: p })), (val) => { input.value = val; });
            };

            input.addEventListener('input', render);
            input.addEventListener('focus', render);
            input.addEventListener('keydown', (e) => autocompleteKeydown(e, input));
            input.addEventListener('blur', () => setTimeout(() => {
                if (autocompleteInput === input) autocompleteDropdown?.classList.remove('show');
            }, 150));
        }

        function normalizarTexto(s) {
            return (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]/g, '');
        }

        function inicialesCasa(casa) {
            const limpio = (casa || '').replace(/[^a-zA-Z0-9áéíóúüñÁÉÍÓÚÜÑ\s]/g, ' ').trim();
            const partes = limpio.split(/(?=[A-ZÁÉÍÓÚÑ])|\s+/).filter(p => p.trim() !== '').slice(0, 2);
            return partes.map(p => normalizarTexto(p).charAt(0)).join('');
        }

        function generarCodigoMedicamento(nombre, casa) {
            const baseNombre = normalizarTexto(nombre).slice(0, 3);
            if (!baseNombre) return '';
            const base = inicialesCasa(casa) + baseNombre;
            const usados = new Set(medicamentosData.map(m => m.codigo.toLowerCase()));
            document.querySelectorAll('#purchaseItemsBody input[name$="[codigo_medicamento]"]').forEach(i => {
                if (i.value.trim()) usados.add(i.value.trim().toLowerCase());
            });
            let n = 1;
            while (usados.has(base + String(n).padStart(2, '0'))) n++;
            return base + String(n).padStart(2, '0');
        }

        // Texto sin acentos y en minúsculas, conservando espacios (para buscar)
        function textoBusqueda(s) {
            return (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        }

        // Busca en el catálogo por nombre del medicamento (también por molécula,
        // casa farmacéutica o código). Todas las palabras escritas deben coincidir.
        function buscarMedicamentos(term) {
            const palabras = textoBusqueda(term).split(/\s+/).filter(Boolean);
            const lista = palabras.length
                ? medicamentosData.filter(m => {
                    const texto = textoBusqueda([m.nombre, m.molecula, m.presentacion, m.casa_farmaceutica, m.codigo].join(' '));
                    return palabras.every(p => texto.includes(p));
                })
                : medicamentosData.slice();
            const primera = palabras[0] || '';
            return lista.sort((a, b) => {
                // primero los que empiezan con lo escrito, luego orden alfabético
                const aIni = textoBusqueda(a.nombre).startsWith(primera) ? 0 : 1;
                const bIni = textoBusqueda(b.nombre).startsWith(primera) ? 0 : 1;
                return aIni - bIni || (a.nombre || '').localeCompare(b.nombre || '', 'es');
            });
        }

        function llenarFilaDesdeCatalogo(row, med) {
            row.querySelector('input[name$="[nombre_medicamento]"]').value = med.nombre || '';
            row.querySelector('input[name$="[codigo_medicamento]"]').value = med.codigo || '';
            row.querySelector('input[name$="[molecula]"]').value = med.molecula || '';
            row.querySelector('input[name$="[presentacion]"]').value = med.presentacion || '';
            row.querySelector('input[name$="[casa_farmaceutica]"]').value = med.casa_farmaceutica || '';
            row.dataset.catalogo = med.codigo || '';
        }

        function attachMedicamentoAutocomplete(input) {
            if (!input || input.dataset.medAttached) return;
            input.dataset.medAttached = '1';
            input.setAttribute('autocomplete', 'off');
            const row = input.closest('tr');

            const render = () => {
                const matches = buscarMedicamentos(input.value.trim()).slice(0, 50);
                showAutocomplete(input, matches.map(m => ({
                    value: m.codigo,
                    label: m.nombre,
                    code: m.codigo,
                    sub: [m.presentacion, m.molecula, m.casa_farmaceutica].filter(Boolean).join(' · ')
                })), (val) => {
                    const med = medicamentosData.find(m => m.codigo === val);
                    if (!med) return;
                    llenarFilaDesdeCatalogo(row, med);
                    // pasar directo a los datos de la compra
                    row.querySelector('input[name$="[lote]"]').focus();
                }, {
                    rich: true,
                    minWidth: 380,
                    emptyText: input.value.trim()
                        ? 'No está en el catálogo: se registrará como medicamento nuevo'
                        : 'Aún no hay medicamentos en el catálogo'
                });
            };

            input.addEventListener('input', () => {
                // si se cambia el nombre de un medicamento ya elegido, deja de ser ese medicamento
                if (row.dataset.catalogo) {
                    row.dataset.catalogo = '';
                    row.querySelector('input[name$="[codigo_medicamento]"]').value = '';
                }
                render();
            });
            input.addEventListener('focus', render);
            input.addEventListener('click', render);
            input.addEventListener('keydown', (e) => autocompleteKeydown(e, input));
            input.addEventListener('blur', () => setTimeout(() => {
                if (autocompleteInput === input) autocompleteDropdown?.classList.remove('show');
            }, 150));
        }

        document.addEventListener('scroll', () => {
            if (!autocompleteDropdown?.classList.contains('show') || !autocompleteInput) return;
            const r = autocompleteInput.getBoundingClientRect();
            autocompleteDropdown.style.left = r.left + 'px';
            autocompleteDropdown.style.top = (r.bottom + 4) + 'px';
        }, true);
        window.addEventListener('resize', () => autocompleteDropdown?.classList.remove('show'));

        attachAutocomplete(document.getElementById('proveedorInput'), proveedoresData);

        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
            
            // Set date to local time (Guatemala)
            const now = new Date();
            const localDate = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            document.querySelector('[name="fecha_compra"]').value = localDate;
            
            // Clear items and add one empty row
            document.getElementById('purchaseItemsBody').innerHTML = '';
            addPurchaseRow();
            updateTotalCompra();
        }

        function addPurchaseRow() {
            const tbody = document.getElementById('purchaseItemsBody');
            const rowCount = tbody.children.length;
            const row = document.createElement('tr');
            
            row.innerHTML = `
                <td><input type="text" name="items[${rowCount}][nombre_medicamento]" required placeholder="Buscar medicamento..."></td>
                <td><input type="text" name="items[${rowCount}][codigo_medicamento]" class="codigo-auto" placeholder="Auto" readonly tabindex="-1" title="Se asigna automáticamente"></td>
                <td><input type="text" name="items[${rowCount}][molecula]" required placeholder="Molécula"></td>
                <td><input type="text" name="items[${rowCount}][presentacion]" required placeholder="Presentación"></td>
                <td><input type="text" name="items[${rowCount}][casa_farmaceutica]" required placeholder="Casa Farm."></td>
                <td><input type="text" name="items[${rowCount}][lote]" placeholder="Lote"></td>
                <td><input type="date" name="items[${rowCount}][fecha_vencimiento]"></td>
                <td><input type="number" name="items[${rowCount}][cantidad]" min="1" value="1" required onchange="updateRowTotal(this)"></td>
                <td><input type="number" name="items[${rowCount}][precio_unitario]" step="0.01" min="0" required onchange="updateRowTotal(this)" placeholder="0.00"></td>
                <td><input type="number" name="items[${rowCount}][precio_venta]" step="0.01" min="0" required placeholder="0.00"></td>
                <td style="text-align: right; font-weight: 600;">Q<span class="row-total">0.00</span></td>
                <td>
                    <button type="button" class="btn-icon btn-remove" onclick="removePurchaseRow(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(row);

            const codigoInput = row.querySelector('input[name$="[codigo_medicamento]"]');
            const nombreInput = row.querySelector('input[name$="[nombre_medicamento]"]');
            const casaInput = row.querySelector('input[name$="[casa_farmaceutica]"]');

            attachAutocomplete(casaInput, casasFarmaceuticasData);
            attachMedicamentoAutocomplete(nombreInput);

            // Al salir del campo: si el nombre coincide exactamente con uno del catálogo, se toma ese;
            // si es un medicamento nuevo, se le asigna un código automático.
            const asignarCodigo = () => {
                if (row.dataset.catalogo || !nombreInput.value.trim()) return;
                const exactos = medicamentosData.filter(m =>
                    textoBusqueda(m.nombre).trim() === textoBusqueda(nombreInput.value).trim() &&
                    (!casaInput.value.trim() || textoBusqueda(m.casa_farmaceutica) === textoBusqueda(casaInput.value))
                );
                if (exactos.length === 1) {
                    llenarFilaDesdeCatalogo(row, exactos[0]);
                } else {
                    codigoInput.value = '';
                    codigoInput.value = generarCodigoMedicamento(nombreInput.value, casaInput.value);
                }
            };
            nombreInput.addEventListener('blur', () => setTimeout(asignarCodigo, 200));
            casaInput.addEventListener('blur', () => setTimeout(() => {
                // código de un medicamento nuevo: regenerar con la casa farmacéutica correcta
                if (!row.dataset.catalogo && nombreInput.value.trim()) {
                    codigoInput.value = '';
                    codigoInput.value = generarCodigoMedicamento(nombreInput.value, casaInput.value);
                }
            }, 200));
        }

        function removePurchaseRow(btn) {
            const tbody = document.getElementById('purchaseItemsBody');
            if (tbody.children.length > 1) {
                btn.closest('tr').remove();
                updateTotalCompra();
            } else {
                uiToast('Debe haber al menos un medicamento en la compra.', 'warning');
            }
        }

        function updateRowTotal(input) {
            const row = input.closest('tr');
            const cantidad = parseFloat(row.querySelector('[name*="[cantidad]"]').value) || 0;
            const precio = parseFloat(row.querySelector('[name*="[precio_unitario]"]').value) || 0;
            const total = cantidad * precio;
            
            row.querySelector('.row-total').textContent = total.toFixed(2);
            updateTotalCompra();
        }

        function updateTotalCompra() {
            let total = 0;
            document.querySelectorAll('.row-total').forEach(span => {
                total += parseFloat(span.textContent);
            });
            document.getElementById('totalCompraDisplay').textContent = 'Q' + total.toFixed(2);
        }

        async function submitBatchPurchase(e) {
            e.preventDefault();

            const ok = await uiConfirm('¿Está seguro de registrar esta compra?', {
                title: 'Registrar compra',
                confirmText: 'Sí, registrar',
                danger: false
            });
            if (!ok) return;

            const form = e.target;
            const formData = new FormData(form);

            try {
                const result = await withLoading('Registrando compra...', async () => {
                    const response = await fetch('save_purchase_batch.php', {
                        method: 'POST',
                        body: formData
                    });
                    return response.json();
                });

                if (result.success) {
                    uiToast(result.message || 'Compra registrada exitosamente', 'success');
                    setTimeout(() => location.reload(), 900);
                } else {
                    uiToast('Error: ' + result.message, 'error', 5000);
                }
            } catch (error) {
                console.error('Error:', error);
                uiToast('Ocurrió un error al procesar la solicitud.', 'error', 5000);
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // ===== Visitador del proveedor =====
        let visitadorProveedorActual = '';

        // Número para WhatsApp/llamada: si son 8 dígitos se asume Guatemala (+502)
        function telefonoInternacional(tel) {
            let t = String(tel || '').replace(/\D/g, '');
            if (t.length === 8) t = '502' + t;
            return t;
        }

        function openVisitadorMenu(btn) {
            const box = btn.closest('.provider-visitador');
            const tel = telefonoInternacional(box.dataset.visTelefono);
            const menu = document.getElementById('visitadorMenu');
            if (!tel) return;
            const saludo = 'Hola ' + (box.dataset.visNombre || '') + ', le escribimos de CLINOVA.';
            document.getElementById('visitadorMenuTitle').textContent =
                (box.dataset.visNombre || 'Visitador') + ' · ' + box.dataset.visTelefono;
            document.getElementById('visitadorMenuWhatsapp').href = 'https://wa.me/' + tel + '?text=' + encodeURIComponent(saludo);
            document.getElementById('visitadorMenuLlamar').href = 'tel:+' + tel;
            const r = btn.getBoundingClientRect();
            menu.classList.add('show');
            const left = Math.min(r.left, window.innerWidth - menu.offsetWidth - 8);
            let top = r.bottom + 6;
            if (top + menu.offsetHeight > window.innerHeight - 8) top = r.top - menu.offsetHeight - 6;
            menu.style.left = Math.max(8, left) + 'px';
            menu.style.top = top + 'px';
        }

        function closeVisitadorMenu() {
            document.getElementById('visitadorMenu').classList.remove('show');
        }
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#visitadorMenu') && !e.target.closest('.vis-phone-btn')) closeVisitadorMenu();
        });
        document.getElementById('visitadorMenu').addEventListener('click', (e) => {
            if (e.target.closest('a')) setTimeout(closeVisitadorMenu, 50);
        });
        window.addEventListener('scroll', closeVisitadorMenu, true);

        function openVisitadorModal(btn) {
            const box = btn.closest('.provider-visitador');
            visitadorProveedorActual = box.dataset.proveedorNombre;
            const tiene = !!box.dataset.visNombre;
            document.getElementById('visitadorModalTitle').textContent = tiene ? 'Cambiar visitador' : 'Agregar visitador';
            document.getElementById('visitadorProveedor').textContent = visitadorProveedorActual;
            document.getElementById('visitadorNombre').value = box.dataset.visNombre || '';
            document.getElementById('visitadorTelefono').value = box.dataset.visTelefono || '';
            document.getElementById('visitadorQuitarBtn').style.visibility = tiene ? 'visible' : 'hidden';
            document.getElementById('visitadorModal').classList.add('show');
            setTimeout(() => document.getElementById('visitadorNombre').focus(), 50);
        }

        async function enviarVisitador(nombre, telefono) {
            showLoading('Guardando visitador...');
            try {
                const res = await fetch('save_visitador.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        proveedor: visitadorProveedorActual,
                        visitador_nombre: nombre,
                        visitador_telefono: telefono
                    })
                });
                const result = await res.json();
                hideLoading();
                if (result.success) {
                    uiToast(result.message, 'success');
                    closeModal('visitadorModal');
                    try { sessionStorage.setItem('purchasesTab', 'proveedores'); } catch (e) {}
                    setTimeout(() => location.reload(), 700);
                } else {
                    uiToast('Error: ' + result.message, 'error', 5000);
                }
            } catch (err) {
                hideLoading();
                console.error(err);
                uiToast('No se pudo guardar el visitador', 'error', 5000);
            }
        }

        function guardarVisitador(e) {
            e.preventDefault();
            const nombre = document.getElementById('visitadorNombre').value.trim();
            const telefono = document.getElementById('visitadorTelefono').value.trim();
            const digitos = telefono.replace(/\D/g, '');
            if (telefono && digitos.length < 8) {
                uiToast('El teléfono debe tener al menos 8 dígitos', 'warning');
                return;
            }
            enviarVisitador(nombre, telefono);
        }

        async function quitarVisitador() {
            const ok = await uiConfirm('¿Quitar el visitador de ' + visitadorProveedorActual + '?', {
                title: 'Quitar visitador', confirmText: 'Sí, quitar'
            });
            if (ok) enviarVisitador('', '');
        }

        function openPaymentModal(idCompra, saldo) {
            document.getElementById('payment_id_compra').value = idCompra;
            document.getElementById('saldo_pendiente').textContent = parseFloat(saldo).toFixed(2);
            document.getElementById('payment_amount').max = saldo;
            document.getElementById('payment_amount').max = saldo;
            
            // Set date to local time
            const now = new Date();
            const localDate = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            document.querySelector('[name="fecha_abono"]').value = localDate;
            document.getElementById('paymentModal').classList.add('show');
        }

        function editPurchase(id) {
            window.location.href = 'edit_purchase.php?id=' + id;
        }

        async function deletePurchase(id) {
            const ok = await uiConfirm('¿Está seguro de eliminar esta compra?', {
                title: 'Eliminar compra',
                confirmText: 'Sí, eliminar'
            });
            if (ok) {
                showLoading('Eliminando compra...');
                window.location.href = 'delete_purchase.php?id=' + id;
            }
        }

        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });

        // Filter functionality (Search + Hide Paid)
        const searchInput = document.getElementById('searchInput');
        const hidePaidToggle = document.getElementById('hidePaidToggle');

        function filterPurchases() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const hidePaid = hidePaidToggle.checked;
            let visibleCount = 0;

            // Filtrar tarjetas de proveedor
            const providerCards = document.querySelectorAll('.provider-card');
            let visProv = 0;
            providerCards.forEach(card => {
                const texto = ((card.getAttribute('data-proveedor') || '') + ' ' + card.textContent).toLowerCase();
                const saldo = parseFloat(card.getAttribute('data-saldo')) || 0;
                let visible = true;
                if (searchTerm.length > 0 && !texto.includes(searchTerm)) visible = false;
                if (hidePaid && saldo <= 0.004) visible = false;
                card.style.display = visible ? '' : 'none';
                if (visible) visProv++;
            });
            const noResultsProv = document.getElementById('noResultsProveedores');
            if (noResultsProv) {
                noResultsProv.style.display = visProv === 0 ? 'block' : 'none';
            }
            
            const summaryRows = document.querySelectorAll('tr.group-header');

            summaryRows.forEach(row => {
                const summaryText = row.textContent.toLowerCase();
                const targetId = row.getAttribute('data-bs-target');
                const detailsRow = document.querySelector(targetId).closest('tr');
                const detailsText = detailsRow.textContent.toLowerCase();
                
                // Check payment status
                // We can check the badge text in the summary row
                const statusBadge = row.querySelector('.badge-pagado, .badge-pendiente');
                const isPaid = statusBadge && statusBadge.textContent.trim() === 'Pagado';

                // Determine visibility
                let isVisible = true;

                // 1. Search filter
                if (searchTerm.length > 0) {
                    const matches = summaryText.includes(searchTerm) || detailsText.includes(searchTerm);
                    if (!matches) isVisible = false;
                }

                // 2. Hide Paid filter
                if (hidePaid && isPaid) {
                    isVisible = false;
                }

                // Apply visibility
                if (isVisible) {
                    row.style.display = '';
                    if (row.nextElementSibling) {
                        row.nextElementSibling.style.display = '';
                    }
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                    if (row.nextElementSibling) {
                        row.nextElementSibling.style.display = 'none';
                    }
                }
            });

            // Show/hide no results message
            const noResults = document.getElementById('noResults');
            if (visibleCount === 0) {
                noResults.style.display = 'block';
            } else {
                noResults.style.display = 'none';
            }
        }

        searchInput.addEventListener('input', filterPurchases);
        hidePaidToggle.addEventListener('change', filterPurchases);

        // Restaurar pestaña activa
        try {
            const savedTab = sessionStorage.getItem('purchasesTab');
            if (savedTab === 'proveedores') switchPurchasesTab('proveedores');
        } catch (e) {}

        // Ensure dropdowns don't trigger collapse
        document.querySelectorAll('.dropdown-toggle').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        });
    </script>
    <?php include __DIR__ . '/../includes/ui_feedback.php'; ?>
</body>
</html>