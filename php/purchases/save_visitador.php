<?php
/**
 * Guarda (o reemplaza) el visitador de un proveedor: nombre y teléfono.
 * Si el proveedor aún no está en el catálogo `proveedores`, se agrega.
 * Enviar nombre y teléfono vacíos quita el visitador.
 */
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $proveedor = trim((string) ($data['proveedor'] ?? ''));
    $nombre    = trim((string) ($data['visitador_nombre'] ?? ''));
    $telefono  = trim((string) ($data['visitador_telefono'] ?? ''));

    if ($proveedor === '' || mb_strtolower($proveedor, 'UTF-8') === 'sin proveedor') {
        throw new Exception('Proveedor inválido');
    }
    $proveedor = mb_substr($proveedor, 0, 150);
    $nombre    = mb_substr($nombre, 0, 150);

    // Teléfono: solo dígitos, espacios, guiones y + inicial
    $telefono = preg_replace('/[^\d+\-\s]/', '', $telefono);
    $digitos  = preg_replace('/\D/', '', $telefono);
    if ($telefono !== '' && (strlen($digitos) < 8 || strlen($digitos) > 15)) {
        throw new Exception('El teléfono debe tener al menos 8 dígitos');
    }
    if ($nombre === '' && $telefono !== '') {
        throw new Exception('Escribe el nombre del visitador');
    }

    $database = new Database();
    $conn = $database->getConnection();
    asegurarColumnasVisitador($conn);

    $nombre   = $nombre !== '' ? $nombre : null;
    $telefono = $telefono !== '' ? substr($telefono, 0, 20) : null;

    $stmt = $conn->prepare("
        INSERT INTO proveedores (nombre, visitador_nombre, visitador_telefono, visitador_actualizado)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            visitador_nombre = VALUES(visitador_nombre),
            visitador_telefono = VALUES(visitador_telefono),
            visitador_actualizado = NOW()
    ");
    $stmt->execute([$proveedor, $nombre, $telefono]);

    echo json_encode([
        'success' => true,
        'message' => $nombre ? 'Visitador guardado' : 'Visitador eliminado',
        'visitador_nombre' => $nombre,
        'visitador_telefono' => $telefono
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
