<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id_orden'])) {
    echo json_encode([]);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Fetch recipe from receitas table via orden_id
    $stmt = $conn->prepare("SELECT detalle_json FROM recetas WHERE id_orden = ?");
    $stmt->execute([$_GET['id_orden']]);
    $receta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($receta && $receta['detalle_json']) {
        echo $receta['detalle_json'];
    } else {
        echo json_encode([]);
    }

} catch (Exception $e) {
    echo json_encode([]);
}
?>
