<?php
session_start();
require_once '../../config/database.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Asegurar tabla de proveedores (registro automático)
        $conn->exec("CREATE TABLE IF NOT EXISTS `proveedores` (
          `id_proveedor` int NOT NULL AUTO_INCREMENT,
          `nombre` varchar(150) NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id_proveedor`),
          UNIQUE KEY `uq_proveedor_nombre` (`nombre`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // Asegurar tabla de casas farmacéuticas (registro automático)
        $conn->exec("CREATE TABLE IF NOT EXISTS `casas_farmaceuticas` (
          `id_casa` int NOT NULL AUTO_INCREMENT,
          `nombre` varchar(150) NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id_casa`),
          UNIQUE KEY `uq_casa_nombre` (`nombre`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // Asegurar catálogo de medicamentos (código -> datos del medicamento)
        $conn->exec("CREATE TABLE IF NOT EXISTS `medicamentos` (
          `id_medicamento` int NOT NULL AUTO_INCREMENT,
          `codigo` varchar(20) NOT NULL,
          `nombre` varchar(200) NOT NULL,
          `molecula` varchar(200) DEFAULT NULL,
          `presentacion` varchar(100) DEFAULT NULL,
          `casa_farmaceutica` varchar(150) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id_medicamento`),
          UNIQUE KEY `uq_medicamento_codigo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // Iniciar transacción
        $conn->beginTransaction();

        // Códigos de medicamentos ya registrados (para autogenerar códigos únicos)
        $stmtCodigos = $conn->query("SELECT codigo FROM medicamentos");
        $codigosUsados = array_flip(array_map('strtolower', $stmtCodigos->fetchAll(PDO::FETCH_COLUMN)));

        $normalizarCodigo = function ($s) {
            $s = strtolower(trim((string)$s));
            $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
            return preg_replace('/[^a-z0-9]/', '', $s);
        };

        $inicialesCasa = function ($s) use ($normalizarCodigo) {
            $s = preg_replace('/[^a-zA-Z0-9áéíóúüñÁÉÍÓÚÜÑ\s]/u', ' ', trim((string)$s));
            $partes = array_values(array_filter(preg_split('/(?=[A-ZÁÉÍÓÚÑ])|\s+/u', $s), function ($p) { return trim($p) !== ''; }));
            $ini = '';
            foreach (array_slice($partes, 0, 2) as $p) {
                $ini .= substr($normalizarCodigo($p), 0, 1);
            }
            return $ini;
        };
        
        // Generar Código de Referencia Único (000001)
        $stmtRef = $conn->query("SELECT MAX(id_compra) as max_id FROM compras");
        $rowRef = $stmtRef->fetch(PDO::FETCH_ASSOC);
        $nextId = ($rowRef['max_id'] ?? 0) + 1;
        $codigoReferencia = str_pad($nextId, 6, '0', STR_PAD_LEFT);
        
        // Datos comunes de la compra
        $fechaCompra = $_POST['fecha_compra'];
        $tipoFactura = $_POST['tipo_factura'];
        $numeroFactura = $_POST['numero_factura'] ?? null; // Nuevo campo
        $tipoPago = $_POST['tipo_pago'];
        $observaciones = $_POST['observaciones'] ?? '';
        
        // Proveedor de la compra (registro automático si es nuevo)
        $proveedorCompra = trim($_POST['proveedor'] ?? '');
        if ($proveedorCompra !== '') {
            $stmtProv = $conn->prepare("INSERT IGNORE INTO proveedores (nombre) VALUES (?)");
            $stmtProv->execute([$proveedorCompra]);
        }
        
        // Procesar cada ítem
        $items = $_POST['items'] ?? [];
        $totalCompra = 0;
        
        foreach ($items as $item) {
            $cantidad = floatval($item['cantidad']);
            $precioUnitario = floatval($item['precio_unitario']);
            $precioVenta = floatval($item['precio_venta']);
            $total = $cantidad * $precioUnitario;
            $totalCompra += $total;

            // Código del medicamento (autogenerado si viene vacío: lab(2) + nombre(3) + secuencia)
            $codigoMed = trim($item['codigo_medicamento'] ?? '');
            if ($codigoMed === '') {
                $base = $inicialesCasa($item['casa_farmaceutica'] ?? '') . substr($normalizarCodigo($item['nombre_medicamento'] ?? ''), 0, 3);
                if ($base !== '') {
                    $n = 1;
                    do { $codigoMed = $base . str_pad((string)$n, 2, '0', STR_PAD_LEFT); $n++; } while (isset($codigosUsados[strtolower($codigoMed)]));
                }
            }
            if ($codigoMed !== '') {
                $codigosUsados[strtolower($codigoMed)] = true;
                $stmtMed = $conn->prepare("
                    INSERT INTO medicamentos (codigo, nombre, molecula, presentacion, casa_farmaceutica)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        molecula = IF(LOWER(nombre) = LOWER(VALUES(nombre)), VALUES(molecula), molecula),
                        presentacion = IF(LOWER(nombre) = LOWER(VALUES(nombre)), VALUES(presentacion), presentacion),
                        casa_farmaceutica = IF(LOWER(nombre) = LOWER(VALUES(nombre)), VALUES(casa_farmaceutica), casa_farmaceutica)
                ");
                $stmtMed->execute([$codigoMed, $item['nombre_medicamento'], $item['molecula'], $item['presentacion'], $item['casa_farmaceutica']]);
            }
            
            // Determinar valores de abonado y saldo por ítem (proporcional o individual)
            // En este caso, manejamos el pago por ítem para mantener la estructura actual,
            // aunque idealmente sería por la compra global.
            // Mantenemos la lógica actual: si es contado, se abona todo.
            $abonado = ($tipoPago === 'Al Contado') ? $total : 0;
            $saldo = $total - $abonado;
            
            // Estado
            $estado = 'Pendiente';
            if ($saldo == 0 && $tipoPago === 'Al Contado') {
                $estado = 'Pagado';
            }
            
            // Insertar compra
            $stmt = $conn->prepare("
                INSERT INTO compras (
                    id_usuario, codigo_referencia, codigo_medicamento, nombre_medicamento, molecula, presentacion, casa_farmaceutica,
                    proveedor, cantidad, precio_unitario, precio_venta, fecha_compra, tipo_factura, numero_factura,
                    tipo_pago, total, abonado, saldo, estado, fecha_vencimiento, lote, observaciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $codigoReferencia,
                $codigoMed === '' ? null : $codigoMed,
                $item['nombre_medicamento'],
                $item['molecula'],
                $item['presentacion'],
                $item['casa_farmaceutica'],
                $proveedorCompra === '' ? null : $proveedorCompra,
                $cantidad,
                $precioUnitario,
                $precioVenta,
                $fechaCompra,
                $tipoFactura,
                $numeroFactura,
                $tipoPago,
                $total,
                $abonado,
                $saldo,
                $estado,
                !empty($item['fecha_vencimiento']) ? $item['fecha_vencimiento'] : null,
                $item['lote'] ?? null,
                $observaciones
            ]);
            
            $idCompra = $conn->lastInsertId();

            // Registro automático de la casa farmacéutica (laboratorio)
            $casaNombre = trim($item['casa_farmaceutica'] ?? '');
            if ($casaNombre !== '') {
                $stmtCasa = $conn->prepare("INSERT IGNORE INTO casas_farmaceuticas (nombre) VALUES (?)");
                $stmtCasa->execute([$casaNombre]);
            }
            
            // CREAR REGISTRO EN INVENTARIO: siempre Ingresado (ingreso automático al comprar)
            $estadoInventario = 'Ingresado';
            
            $stmtInventario = $conn->prepare("
                INSERT INTO inventario (
                    id_compra, codigo_referencia, codigo_medicamento, nom_medicamento, mol_medicamento, presentacion_med, casa_farmaceutica,
                    cantidad_med, estado_ingreso, precio_costo, precio_venta,
                    id_sucursal, fecha_adquisicion, fecha_vencimiento, tipo_factura, numero_factura
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Obtener sucursal del usuario
            $stmtUser = $conn->prepare("SELECT id_sucursal FROM usuarios WHERE id = ?");
            $stmtUser->execute([$_SESSION['user_id']]);
            $userSucursal = $stmtUser->fetchColumn() ?: 1;
            
            $stmtInventario->execute([
                $idCompra,
                $codigoReferencia,
                $codigoMed === '' ? null : $codigoMed,
                $item['nombre_medicamento'],
                $item['molecula'],
                $item['presentacion'],
                $item['casa_farmaceutica'],
                $cantidad,
                $estadoInventario, // Aquí forzamos "Pendiente"
                $precioUnitario,
                $precioVenta,
                $userSucursal,
                $fechaCompra,
                !empty($item['fecha_vencimiento']) ? $item['fecha_vencimiento'] : null,
                $tipoFactura,
                $numeroFactura
            ]);
            
            // Registrar abono si es al contado
            if ($tipoPago === 'Al Contado' && $abonado > 0) {
                $stmtAbono = $conn->prepare("
                    INSERT INTO abonos_compras (
                        id_compra, id_usuario, monto, fecha_abono, forma_pago, observaciones
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $stmtAbono->execute([
                    $idCompra,
                    $_SESSION['user_id'],
                    $abonado,
                    $fechaCompra,
                    'Efectivo',
                    'Pago al contado - Compra #' . $codigoReferencia
                ]);
            }
        }
        
        // Commit transacción
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Compra registrada exitosamente. Referencia: ' . $codigoReferencia]);
        
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    header("Location: index.php");
    exit();
}
?>
