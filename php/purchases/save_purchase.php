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

        // Código del medicamento (autogenerado si viene vacío: iniciales del lab + nombre(3) + secuencia)
        $codigoMed = trim($_POST['codigo_medicamento'] ?? '');
        if ($codigoMed === '') {
            $base = $inicialesCasa($_POST['casa_farmaceutica'] ?? '') . substr($normalizarCodigo($_POST['nombre_medicamento'] ?? ''), 0, 3);
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
            $stmtMed->execute([$codigoMed, $_POST['nombre_medicamento'], $_POST['molecula'], $_POST['presentacion'], $_POST['casa_farmaceutica']]);
        }
        
        // Calcular total
        $cantidad = floatval($_POST['cantidad']);
        $precioUnitario = floatval($_POST['precio_unitario']);
        $total = $cantidad * $precioUnitario;
        
        // Determinar valores de abonado y saldo según tipo de pago
        $tipoPago = $_POST['tipo_pago'];
        $abonado = ($tipoPago === 'Al Contado') ? $total : 0;
        $saldo = $total - $abonado;
        
        // Determinar estado automático
        $estado = $_POST['estado'];
        if ($saldo == 0 && $tipoPago === 'Al Contado') {
            $estado = 'Pagado';
        }
        
        // Proveedor de la compra (registro automático si es nuevo)
        $proveedorCompra = trim($_POST['proveedor'] ?? '');
        if ($proveedorCompra !== '') {
            $stmtProv = $conn->prepare("INSERT IGNORE INTO proveedores (nombre) VALUES (?)");
            $stmtProv->execute([$proveedorCompra]);
        }

        // Insertar compra
        $stmt = $conn->prepare("
            INSERT INTO compras (
                id_usuario, codigo_medicamento, nombre_medicamento, molecula, presentacion, casa_farmaceutica,
                proveedor, cantidad, precio_unitario, precio_venta, fecha_compra, tipo_factura,
                tipo_pago, total, abonado, saldo, estado, fecha_vencimiento, lote, observaciones
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $codigoMed === '' ? null : $codigoMed,
            $_POST['nombre_medicamento'],
            $_POST['molecula'],
            $_POST['presentacion'],
            $_POST['casa_farmaceutica'],
            $proveedorCompra === '' ? null : $proveedorCompra,
            $cantidad,
            $precioUnitario,
            floatval($_POST['precio_venta']),
            $_POST['fecha_compra'],
            $_POST['tipo_factura'],
            $tipoPago,
            $total,
            $abonado,
            $saldo,
            $estado,
            !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null,
            $_POST['lote'] ?? null,
            $_POST['observaciones'] ?? null
        ]);
        
        $idCompra = $conn->lastInsertId();

        // Registro automático de la casa farmacéutica (laboratorio)
        $casaNombre = trim($_POST['casa_farmaceutica'] ?? '');
        if ($casaNombre !== '') {
            $stmtCasa = $conn->prepare("INSERT IGNORE INTO casas_farmaceuticas (nombre) VALUES (?)");
            $stmtCasa->execute([$casaNombre]);
        }
        
        
        // CREAR REGISTRO EN INVENTARIO: siempre Ingresado (el ingreso es automático al comprar)
        try {
            $estadoInventario = 'Ingresado';
            
            $stmtInventario = $conn->prepare("
                INSERT INTO inventario (
                    id_compra, codigo_medicamento, nom_medicamento, mol_medicamento, presentacion_med, casa_farmaceutica,
                    cantidad_med, estado_ingreso, precio_costo, precio_venta,
                    id_sucursal, fecha_adquisicion, fecha_vencimiento, tipo_factura
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Obtener sucursal del usuario
            $stmtUser = $conn->prepare("SELECT id_sucursal FROM usuarios WHERE id = ?");
            $stmtUser->execute([$_SESSION['user_id']]);
            $userSucursal = $stmtUser->fetchColumn() ?: 1;
            
            $stmtInventario->execute([
                $idCompra,
                $codigoMed === '' ? null : $codigoMed,
                $_POST['nombre_medicamento'],
                $_POST['molecula'],
                $_POST['presentacion'],
                $_POST['casa_farmaceutica'],
                $cantidad,
                $estadoInventario,
                $precioUnitario,
                floatval($_POST['precio_venta']),
                $userSucursal,
                $_POST['fecha_compra'],
                !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null,
                $_POST['tipo_factura']
            ]);
        } catch (Exception $eInv) {
            // Si falla la inserción en inventario, continuar con la compra
            // (probablemente porque no se han ejecutado las migraciones SQL)
            error_log("Advertencia: No se pudo crear inventario automático: " . $eInv->getMessage());
        }
        
        // Si es al contado, registrar el abono automáticamente
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
                $_POST['fecha_compra'],
                'Efectivo',
                'Pago al contado'
            ]);
        }
        
        // Commit transacción
        $conn->commit();
        
        $_SESSION['message'] = 'Compra registrada exitosamente';
        $_SESSION['message_type'] = 'success';
        
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

header("Location: index.php");
exit();
?>
