<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener el tipo de usuario
    $userType = $_SESSION['tipoUsuario'] ?? '';
    $userId = $_SESSION['user_id'] ?? 0;

    // Si es doctor, obtener solo sus citas
    if ($userType === 'doc') {
        $stmt = $conn->prepare("
            SELECT c.*, CONCAT(u.nombre, ' ', u.apellido) as medico_nombre 
            FROM citas c
            LEFT JOIN usuarios u ON c.id_medico = u.idUsuario
            WHERE c.id_medico = ?
            ORDER BY c.fecha_cita, c.hora_cita
        ");
        $stmt->execute([$userId]);
    } else {
        // Si es admin o user, obtener todas las citas
        $stmt = $conn->prepare("
            SELECT c.*, CONCAT(u.nombre, ' ', u.apellido) as medico_nombre 
            FROM citas c
            LEFT JOIN usuarios u ON c.id_medico = u.idUsuario
            ORDER BY c.fecha_cita, c.hora_cita
        ");
        $stmt->execute();
    }
    
    $events = [];
    while ($row = $stmt->fetch()) {
        $events[] = [
            'id' => $row['id_cita'],
            'title' => $row['nombre_pac'] . ' ' . $row['apellido_pac'] . ($userType !== 'doc' && $row['medico_nombre'] ? ' - Dr. ' . $row['medico_nombre'] : ''),
            'start' => $row['fecha_cita'] . 'T' . $row['hora_cita'],
            'backgroundColor' => '#007bff',
            'borderColor' => '#0056b3'
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($events);
} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al cargar las citas']);
}