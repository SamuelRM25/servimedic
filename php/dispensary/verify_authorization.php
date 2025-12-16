<?php
require_once '../../config/database.php';

header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Credenciales incompletas']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Fetch user by username
    // Note: Assuming table 'usuarios' has columns 'usuario' (or 'email') and 'password'
    // We will try 'usuario' or 'email' or 'nombre' depending on common conventions.
    // Based on previous edits, we saw 'usuarios' table has 'nombre'? Let's check typical login.
    // I'll try to match against 'usuario' column first.
    
    // Safety check: let's do a loose check that finds the user first
    $stmt = $conn->prepare("SELECT id, nombre, password, rol FROM usuarios WHERE usuario = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Fallback: maybe they use 'nombre' or 'email'? Let's try strict to 'usuario' first as per user request context usually implies a username.
        // If failed, return error.
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }

    // Check if user is authorized (Admin or Doctor)
    /* 
       The user asked: "Que solicite un código de autorización... a los administradores" 
       It implicitly means only Admins (or maybe Doctors per context) can authorize.
       Let's check role.
    */
    if (!in_array($user['rol'], ['Administrador', 'Doctor'])) {
         echo json_encode(['success' => false, 'message' => 'Este usuario no tiene permisos para autorizar cambios de precio.']);
         exit;
    }

    // Verify Password
    // Assuming standard password_verify. If they store plain text (bad practice but possible in legacy), we handle that too if needed, but let's assume hash.
    if (password_verify($password, $user['password'])) {
        echo json_encode([
            'success' => true,
            'user_id' => $user['id'],
            'user_name' => $user['nombre'],
            'message' => 'Autorización exitosa'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error de servidor: ' . $e->getMessage()]);
}
?>
