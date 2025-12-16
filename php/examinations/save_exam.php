<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Obtener sucursal del usuario
        $stmt = $conn->prepare("SELECT id_sucursal FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userSucursal = $stmt->fetchColumn() ?: 1;
        
        // Procesar exámenes seleccionados
        $examenes = $_POST['examenes'] ?? [];
        if (!empty($_POST['otro_examen'])) {
            $examenes[] = $_POST['otro_examen'];
        }
        
        if (empty($examenes)) {
            throw new Exception('Debe seleccionar al menos un examen');
        }
        
        $examenesStr = implode(', ', $examenes);
        
        // Generar número de ticket
        $numeroTicket = 'EX-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insertar examen
        $stmt = $conn->prepare("
            INSERT INTO examenes (
                id_paciente, nombre_paciente, id_usuario, id_sucursal,
                tipo_paciente, examenes_realizados, tipo_pago, monto,
                metodo_pago, numero_ticket, observaciones
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['id_paciente'],
            $_POST['nombre_paciente'],
            $_SESSION['user_id'],
            $userSucursal,
            $_POST['tipo_paciente'],
            $examenesStr,
            $_POST['tipo_pago'],
            $_POST['monto'],
            $_POST['metodo_pago'],
            $numeroTicket,
            $_POST['observaciones'] ?? null
        ]);
        
        $idExamen = $conn->lastInsertId();
        
        // Redirigir a ticket
        header("Location: ticket.php?id=" . $idExamen);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
        header("Location: index.php");
        exit();
    }
}

header("Location: index.php");
exit();
?>
