<?php
session_start();
require_once '../../config/database.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

date_default_timezone_set('America/Guatemala');

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener tipo de reporte y sucursal
    $tipo = $_GET['tipo'] ?? 'todos';
    $filterSucursal = $_GET['sucursal_id'] ?? null;
    
    // Obtener rol y sucursal del usuario
    $userRole = $_SESSION['rol'] ?? '';
    $userId = $_SESSION['user_id'] ?? 0;
    $userSucursal = null;
    
    // Si es usuario de farmacia, obtener su sucursal
    if ($userRole === 'Farmacia') {
        $stmt = $conn->prepare("SELECT id_sucursal FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        $userSucursal = $userData['id_sucursal'] ?? null;
    }
    
    // Construir consulta según el tipo
    $query = "
        SELECT 
            i.nom_medicamento as 'Medicamento',
            i.mol_medicamento as 'Molécula',
            i.presentacion_med as 'Presentación',
            i.casa_farmaceutica as 'Casa Farmacéutica',
            i.cantidad_med as 'Cantidad',
            s.nombre as 'Sucursal',
            i.tipo_factura as 'Tipo de Factura',
            i.numero_factura as 'Número Factura/Nota',
            DATE_FORMAT(i.fecha_adquisicion, '%d/%m/%Y') as 'Fecha Adquisición',
            DATE_FORMAT(i.fecha_vencimiento, '%d/%m/%Y') as 'Fecha Vencimiento',
            CASE 
                WHEN i.fecha_vencimiento < CURDATE() THEN 'Vencido'
                WHEN DATEDIFF(i.fecha_vencimiento, CURDATE()) <= 60 THEN 'Por Vencer'
                ELSE 'Vigente'
            END as 'Estado'
        FROM inventario i
        LEFT JOIN sucursales s ON i.id_sucursal = s.id_sucursal
    ";
    
    $params = [];
    $conditions = [];
    
    // Filtrar por sucursal si es farmacia
    if ($userRole === 'Farmacia' && $userSucursal) {
        $conditions[] = "i.id_sucursal = ?";
        $params[] = $userSucursal;
    }
    
    // Filtrar por tipo de factura
    switch ($tipo) {
        case 'factura':
            // USer has issues with exact match, using LIKE to be safer
            $conditions[] = "i.tipo_factura LIKE '%Factura%'";
            $reportName = 'Inventario_Con_Factura';
            break;
        case 'consumidor':
            $conditions[] = "i.tipo_factura = 'Consumidor Final'";
            $reportName = 'Inventario_Consumidor_Final';
            break;
        case 'nota':
            $conditions[] = "i.tipo_factura = 'Nota de Envío'";
            $reportName = 'Inventario_Nota_Envio';
            break;
        default:
            $reportName = 'Inventario_Completo';
            break;
    }
    
    // Filtrar por sucursal específica (si se solicta en reporte general)
    if ($filterSucursal) {
        $conditions[] = "i.id_sucursal = ?";
        $params[] = $filterSucursal;
    }
    
    // Agregar condiciones a la consulta
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY i.fecha_vencimiento ASC";
    
    // Ejecutar consulta
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generar nombre de archivo con fecha
    $fecha = date('Y-m-d_H-i-s');
    $filename = $reportName . '_' . $fecha . '.csv';
    
    // Configurar headers para descarga de CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Abrir output stream
    $output = fopen('php://output', 'w');
    
    // Agregar BOM para que Excel reconozca UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Agregar encabezado del reporte
    fputcsv($output, ['REPORTE DE INVENTARIO - SERVIMEDIC FAMILIAR']);
    fputcsv($output, ['Generado: ' . date('d/m/Y H:i:s')]);
    fputcsv($output, ['Usuario: ' . $_SESSION['nombre'] . ' ' . $_SESSION['apellido']]);
    fputcsv($output, ['Tipo: ' . ucwords(str_replace('_', ' ', $reportName))]);
    fputcsv($output, ['Total de registros: ' . count($results)]);
    fputcsv($output, []); // Línea vacía
    
    if (!empty($results)) {
        // Escribir encabezados de columnas
        fputcsv($output, array_keys($results[0]));
        
        // Escribir datos
        foreach ($results as $row) {
            fputcsv($output, $row);
        }
        
        // Agregar estadísticas al final
        fputcsv($output, []); // Línea vacía
        fputcsv($output, ['ESTADÍSTICAS']);
        
        // Calcular estadísticas
        $totalCantidad = array_sum(array_column($results, 'Cantidad'));
        $vencidos = count(array_filter($results, fn($r) => $r['Estado'] === 'Vencido'));
        $porVencer = count(array_filter($results, fn($r) => $r['Estado'] === 'Por Vencer'));
        $vigentes = count(array_filter($results, fn($r) => $r['Estado'] === 'Vigente'));
        
        fputcsv($output, ['Total de Medicamentos:', count($results)]);
        fputcsv($output, ['Cantidad Total:', $totalCantidad]);
        fputcsv($output, ['Medicamentos Vigentes:', $vigentes]);
        fputcsv($output, ['Medicamentos Por Vencer (60 días):', $porVencer]);
        fputcsv($output, ['Medicamentos Vencidos:', $vencidos]);
        
        // Agrupar por sucursal
        if ($userRole === 'Administrador') {
            fputcsv($output, []);
            fputcsv($output, ['DISTRIBUCIÓN POR SUCURSAL']);
            
            $porSucursal = [];
            foreach ($results as $row) {
                $sucursal = $row['Sucursal'] ?? 'Sin sucursal';
                if (!isset($porSucursal[$sucursal])) {
                    $porSucursal[$sucursal] = 0;
                }
                $porSucursal[$sucursal]++;
            }
            
            foreach ($porSucursal as $sucursal => $count) {
                fputcsv($output, [$sucursal . ':', $count]);
            }
        }
    } else {
        fputcsv($output, ['No se encontraron registros para exportar']);
    }
    
    fclose($output);
    exit();
    
} catch (Exception $e) {
    $_SESSION['message'] = 'Error al generar reporte: ' . $e->getMessage();
    $_SESSION['message_type'] = 'error';
    header("Location: index.php");
    exit();
}
?>
