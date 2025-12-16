<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $conn = $database->getConnection();
    
    $usuario = sanitize_input($_POST['usuario']);
    $password = $_POST['password']; // Don't sanitize password before verification
    
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE usuario = ?");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify password - using plain text comparison as per current database setup
    // TODO: In production, switch to: if ($user && password_verify($password, $user['password']))
    if ($user && $password === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['apellido'] = $user['apellido'];
        $_SESSION['rol'] = $user['rol'];
        $_SESSION['telefono'] = $user['telefono'];
        
        header("Location: ../dashboard/index.php");
        exit();
    } else {
        header("Location: ../../index.php?error=1");
        exit();
    }
}
?>