<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['code'])) {
    echo json_encode(['success' => false, 'message' => 'No code provided']);
    exit;
}

$code = $_GET['code'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT i.*, 
               COALESCE(i.precio_venta, 10.00) as precio_venta_sugerido,
               COALESCE(i.precio_costo, 0) as precio_costo
        FROM inventario i
        WHERE i.codigo_barras = ? AND i.cantidad_med > 0 AND i.estado_ingreso = 'Ingresado'
        LIMIT 1
    ");
    $stmt->execute([$code]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        // Existencia total del medicamento (todos sus lotes en la sucursal)
        $stmtG = $conn->prepare("
            SELECT i.*, COALESCE(i.precio_venta, 10.00) as precio_venta_sugerido, COALESCE(i.precio_costo, 0) as precio_costo
            FROM inventario i
            WHERE i.id_sucursal <=> ? AND i.cantidad_med > 0 AND i.estado_ingreso = 'Ingresado'
              AND (i.codigo_medicamento = ? OR i.nom_medicamento = ?)
        ");
        $stmtG->execute([$product['id_sucursal'], (string) ($product['codigo_medicamento'] ?? ''), $product['nom_medicamento']]);
        $clave = claveGrupoInventario($product);
        foreach (agruparInventarioDisponible($stmtG->fetchAll(PDO::FETCH_ASSOC)) as $g) {
            if (claveGrupoInventario($g) === $clave) { $product = $g; break; }
        }

        echo json_encode(['success' => true, 'product' => $product]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado o sin stock']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
