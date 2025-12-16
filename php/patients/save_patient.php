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
        
        // Preparar datos
        $consultaDomicilio = isset($_POST['consulta_domicilio']) ? 1 : 0;
        $tieneReconsulta = isset($_POST['tiene_reconsulta_gratis']) ? 1 : 0;
        $fechaReconsulta = $tieneReconsulta && !empty($_POST['fecha_reconsulta_limite']) ? $_POST['fecha_reconsulta_limite'] : null;
        $idMedico = !empty($_POST['id_medico']) ? $_POST['id_medico'] : null;
        
        // Insertar paciente
        $stmt = $conn->prepare("
            INSERT INTO pacientes (
                nombre, apellido, fecha_nacimiento, genero, direccion, telefono, correo, dpi,
                tipo_paciente, consulta_domicilio, tiene_reconsulta_gratis, fecha_reconsulta_limite,
                id_medico, id_sucursal, observaciones,
                motivo_consulta, sintomas, historia_clinica, medicacion_actual, alergias,
                historial_familiar, estilo_vida, contacto_emergencia_nombre, contacto_emergencia_telefono
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $params = [
            $_POST['nombre'],
            $_POST['apellido'],
            $_POST['fecha_nacimiento'],
            $_POST['genero'],
            $_POST['direccion'] ?? null,
            $_POST['telefono'] ?? null,
            $_POST['correo'] ?? null,
            $_POST['dpi'] ?? null,
            $_POST['tipo_paciente'],
            $consultaDomicilio,
            $tieneReconsulta,
            $fechaReconsulta,
            $idMedico,
            $userSucursal,
            $_POST['observaciones'] ?? null,
            $_POST['motivo_consulta'] ?? null,
            $_POST['sintomas'] ?? null,
            $_POST['historia_clinica'] ?? null,
            $_POST['medicacion_actual'] ?? null,
            $_POST['alergias'] ?? null,
            $_POST['historial_familiar'] ?? null,
            $_POST['estilo_vida'] ?? null,
            $_POST['contacto_emergencia_nombre'] ?? null,
            $_POST['contacto_emergencia_telefono'] ?? null
        ];

        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            // Si el error es por columnas faltantes (1054)
            if ($e->getCode() == '42S22' || strpos($e->getMessage(), 'Unknown column') !== false) {
                
                $columnsToAdd = [
                    "motivo_consulta TEXT DEFAULT NULL",
                    "sintomas TEXT DEFAULT NULL",
                    "historia_clinica TEXT DEFAULT NULL",
                    "medicacion_actual TEXT DEFAULT NULL",
                    "alergias TEXT DEFAULT NULL",
                    "historial_familiar TEXT DEFAULT NULL",
                    "estilo_vida TEXT DEFAULT NULL",
                    "contacto_emergencia_nombre VARCHAR(200) DEFAULT NULL",
                    "contacto_emergencia_telefono VARCHAR(20) DEFAULT NULL"
                ];

                foreach ($columnsToAdd as $colDef) {
                    try {
                        $conn->exec("ALTER TABLE pacientes ADD COLUMN $colDef");
                    } catch (PDOException $ex) {
                        // Ignorar error si la columna ya existe (1060: Duplicate column name)
                        if ($ex->getCode() != '42S21' && strpos($ex->getMessage(), 'Duplicate column') === false) {
                            // Si es otro error, lo registramos pero intentamos continuar
                            error_log("Error adding column: " . $ex->getMessage());
                        }
                    }
                }
                
                // Reintentar insert
                $stmt->execute($params);
            } else {
                throw $e;
            }
        }
        
        if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Paciente registrado exitosamente']);
            exit();
        }

        $_SESSION['message'] = 'Paciente registrado exitosamente';
        $_SESSION['message_type'] = 'success';
        
    } catch (Exception $e) {
        if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit();
        }
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

header("Location: index.php");
exit();
?>
