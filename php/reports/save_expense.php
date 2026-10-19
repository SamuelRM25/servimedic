<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        if (!tienePermisoReportes($conn, (int)$_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Sin permiso para el módulo de Reportes']);
            exit;
        }

        // Ensure table exists (Self-repair, esquema real)
        $conn->exec("CREATE TABLE IF NOT EXISTS `caja_chica` (
          `id_movimiento` int NOT NULL AUTO_INCREMENT,
          `id_usuario` int NOT NULL,
          `id_sucursal` int NOT NULL,
          `tipo_movimiento` enum('Ingreso','Egreso') NOT NULL,
          `categoria` varchar(100) NOT NULL,
          `concepto` varchar(255) NOT NULL,
          `monto` decimal(10,2) NOT NULL,
          `forma_pago` enum('Efectivo','Transferencia','Cheque','Tarjeta') NOT NULL DEFAULT 'Efectivo',
          `referencia` varchar(100) DEFAULT NULL,
          `comprobante` varchar(100) DEFAULT NULL,
          `fecha_movimiento` date NOT NULL,
          `observaciones` text,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id_movimiento`),
          KEY `idx_tipo_movimiento` (`tipo_movimiento`),
          KEY `idx_fecha_movimiento` (`fecha_movimiento`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $tipo = ($_POST['tipo'] ?? 'Egreso') === 'Ingreso' ? 'Ingreso' : 'Egreso';
        $categoria = trim($_POST['categoria'] ?? '');
        $concepto = trim($_POST['concepto'] ?? $_POST['descripcion'] ?? '');
        $monto = floatval($_POST['monto'] ?? 0);
        $formaPago = $_POST['forma_pago'] ?? 'Efectivo';
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        $referencia = trim($_POST['referencia'] ?? '');

        if (!in_array($formaPago, ['Efectivo', 'Transferencia', 'Cheque', 'Tarjeta'], true)) {
            $formaPago = 'Efectivo';
        }

        if ($concepto === '' || $monto <= 0) {
            throw new Exception('Concepto y monto son requeridos');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d');
        }

        if ($categoria === '') {
            $categoria = $tipo === 'Ingreso' ? 'Ingreso manual' : 'Otros gastos';
        }

        // Sucursal del usuario
        $stmtSuc = $conn->prepare("SELECT id_sucursal FROM usuarios WHERE id = ?");
        $stmtSuc->execute([$_SESSION['user_id']]);
        $idSucursal = $stmtSuc->fetchColumn() ?: 1;

        $stmt = $conn->prepare("
            INSERT INTO caja_chica (
                id_usuario, id_sucursal, tipo_movimiento, categoria, concepto,
                monto, forma_pago, referencia, fecha_movimiento
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $idSucursal,
            $tipo,
            $categoria,
            $concepto,
            round($monto, 2),
            $formaPago,
            $referencia !== '' ? $referencia : null,
            $fecha,
        ]);

        echo json_encode([
            'success' => true,
            'message' => $tipo === 'Ingreso' ? 'Ingreso registrado correctamente' : 'Gasto registrado correctamente',
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
