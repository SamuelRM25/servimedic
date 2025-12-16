<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Validate required fields
        if (empty($_POST['nombre_pac']) || empty($_POST['apellido_pac']) || empty($_POST['fecha_cita']) || empty($_POST['hora_cita'])) {
            throw new Exception("Los campos de nombre, apellido, fecha y hora son obligatorios");
        }
        
        // Get the next appointment number
        $stmt = $conn->query("SELECT MAX(num_cita) as max_num FROM citas");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $num_cita = ($result['max_num'] ?? 0) + 1;
        
        // Obtener el médico asignado
        $id_medico = $_POST['id_medico'] ?? $_SESSION['user_id'] ?? 0;
        
        // Verificar si el paciente ya existe (por nombre y apellido)
        $stmt = $conn->prepare("SELECT id_paciente FROM pacientes WHERE nombre = ? AND apellido = ?");
        $stmt->execute([$_POST['nombre_pac'], $_POST['apellido_pac']]);
        $paciente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($paciente) {
            $id_paciente = $paciente['id_paciente'];
        } else {
            // Si no existe, crear un nuevo paciente temporal
            $stmt = $conn->prepare("
                INSERT INTO pacientes (nombre, apellido, fecha_nacimiento, genero, id_medico) 
                VALUES (?, ?, '2000-01-01', 'Masculino', ?)
            ");
            $stmt->execute([$_POST['nombre_pac'], $_POST['apellido_pac'], $id_medico]);
            $id_paciente = $conn->lastInsertId();
        }
        
        // Prepare SQL statement
        $sql = "INSERT INTO citas (nombre_pac, apellido_pac, num_cita, fecha_cita, hora_cita, telefono, id_medico, id_paciente) 
                VALUES (:nombre_pac, :apellido_pac, :num_cita, :fecha_cita, :hora_cita, :telefono, :id_medico, :id_paciente)";
        
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':nombre_pac', $_POST['nombre_pac']);
        $stmt->bindParam(':apellido_pac', $_POST['apellido_pac']);
        $stmt->bindParam(':num_cita', $num_cita);
        $stmt->bindParam(':fecha_cita', $_POST['fecha_cita']);
        $stmt->bindParam(':hora_cita', $_POST['hora_cita']);
        $stmt->bindParam(':telefono', $_POST['telefono']);
        $stmt->bindParam(':id_medico', $id_medico);
        $stmt->bindParam(':id_paciente', $id_paciente);
        
        // Execute the statement
        if ($stmt->execute()) {
            $_SESSION['appointment_message'] = "Cita guardada correctamente";
            $_SESSION['appointment_status'] = "success";
        } else {
            throw new Exception("Error al guardar la cita");
        }
        
    } catch (Exception $e) {
        $_SESSION['appointment_message'] = "Error: " . $e->getMessage();
        $_SESSION['appointment_status'] = "error";
    }
    
    // Redirect back to the appointments page
    header("Location: index.php");
    exit;
}