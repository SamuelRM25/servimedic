<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit();
}

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 3) {
    echo json_encode([]);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT id_inventario, nom_medicamento, mol_medicamento, presentacion_med,
               codigo_referencia, codigo_barras
        FROM inventario
        WHERE nom_medicamento LIKE ?
        ORDER BY nom_medicamento
        LIMIT 20
    ");
    $stmt->execute(['%' . $q . '%']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de búsqueda']);
}
