<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $sql = "CREATE TABLE IF NOT EXISTS `ordenes_cobro` (
      `id_orden` int(11) NOT NULL AUTO_INCREMENT,
      `id_paciente` int(11) NOT NULL,
      `id_medico` int(11) NOT NULL,
      `monto` decimal(10,2) NOT NULL,
      `tipo_cobro` enum('Consulta','Procedimiento','Examen','Otro') NOT NULL,
      `descripcion` text DEFAULT NULL,
      `estado` enum('Pendiente','Pagado') NOT NULL DEFAULT 'Pendiente',
      `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
      `fecha_pago` timestamp NULL DEFAULT NULL,
      PRIMARY KEY (`id_orden`),
      KEY `fk_orden_paciente` (`id_paciente`),
      KEY `fk_orden_medico` (`id_medico`),
      CONSTRAINT `fk_orden_paciente` FOREIGN KEY (`id_paciente`) REFERENCES `pacientes` (`id_paciente`) ON DELETE CASCADE,
      CONSTRAINT `fk_orden_medico` FOREIGN KEY (`id_medico`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    $conn->exec($sql);
    echo "Table ordenes_cobro created successfully.";
    
} catch (PDOException $e) {
    echo "Error creating table: " . $e->getMessage();
}
?>
