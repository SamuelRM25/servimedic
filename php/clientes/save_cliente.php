<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$nombre    = trim($_POST['nombre']    ?? '');
$telefono  = trim($_POST['telefono']  ?? '');
$direccion = trim($_POST['direccion'] ?? '');

if ($nombre === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        INSERT INTO clientes (nombre, telefono, direccion)
        VALUES (:nombre, :telefono, :direccion)
    ");
    $stmt->execute([
        ':nombre'    => $nombre,
        ':telefono'  => $telefono  !== '' ? $telefono  : null,
        ':direccion' => $direccion !== '' ? $direccion : null,
    ]);

    echo json_encode([
        'success'    => true,
        'message'    => 'Cliente agregado correctamente',
        'id_cliente' => (int) $conn->lastInsertId(),
    ]);
} catch (PDOException $e) {
    error_log('save_cliente: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar el cliente']);
}
