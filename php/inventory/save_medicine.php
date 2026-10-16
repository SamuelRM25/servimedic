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

        $id_inventario = $_POST['id_inventario'] ?? null;
        $codigo_barras = trim($_POST['codigo_barras'] ?? '');
        $codigo_referencia = trim($_POST['codigo_referencia'] ?? '');
        $nom_medicamento = $_POST['nom_medicamento'];
        $mol_medicamento = $_POST['mol_medicamento'];
        $presentacion_med = $_POST['presentacion_med'];
        $casa_farmaceutica = $_POST['casa_farmaceutica'];
        $cantidad_med = $_POST['cantidad_med'];
        $id_sucursal = $_POST['id_sucursal'];
        $tipo_factura = $_POST['tipo_factura'];
        $numero_factura = $_POST['numero_factura'] ?? null;
        $fecha_adquisicion = $_POST['fecha_adquisicion'];
        $fecha_vencimiento = $_POST['fecha_vencimiento'];

        if (empty($numero_factura)) {
            $numero_factura = null;
        }
        if ($codigo_barras === '') {
            $codigo_barras = null;
        }
        if ($codigo_referencia === '') {
            $codigo_referencia = null;
        }

        // Validar unicidad de codigo_referencia (ignorando NULL)
        if ($codigo_referencia !== null) {
            $sql = "SELECT id_inventario FROM inventario WHERE codigo_referencia = ?";
            $params = [$codigo_referencia];
            if ($id_inventario) {
                $sql .= " AND id_inventario != ?";
                $params[] = $id_inventario;
            }
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch()) {
                throw new Exception('El código de referencia "' . $codigo_referencia . '" ya está en uso. Ingrese uno diferente o déjelo vacío.');
            }
        }

        if ($id_inventario) {
            // Actualizar medicamento existente
            $stmt = $conn->prepare("
                UPDATE inventario SET
                    codigo_barras = ?,
                    codigo_referencia = ?,
                    nom_medicamento = ?,
                    mol_medicamento = ?,
                    presentacion_med = ?,
                    casa_farmaceutica = ?,
                    cantidad_med = ?,
                    id_sucursal = ?,
                    tipo_factura = ?,
                    numero_factura = ?,
                    fecha_adquisicion = ?,
                    fecha_vencimiento = ?
                WHERE id_inventario = ?
            ");
            $stmt->execute([
                $codigo_barras,
                $codigo_referencia,
                $nom_medicamento,
                $mol_medicamento,
                $presentacion_med,
                $casa_farmaceutica,
                $cantidad_med,
                $id_sucursal,
                $tipo_factura,
                $numero_factura,
                $fecha_adquisicion,
                $fecha_vencimiento,
                $id_inventario
            ]);

            $_SESSION['message'] = 'Medicamento actualizado exitosamente';
        } else {
            // Nuevo medicamento
            $stmt = $conn->prepare("
                INSERT INTO inventario (
                    codigo_barras, codigo_referencia, nom_medicamento, mol_medicamento, presentacion_med,
                    casa_farmaceutica, cantidad_med, id_sucursal,
                    tipo_factura, numero_factura, fecha_adquisicion, fecha_vencimiento
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $codigo_barras,
                $codigo_referencia,
                $nom_medicamento,
                $mol_medicamento,
                $presentacion_med,
                $casa_farmaceutica,
                $cantidad_med,
                $id_sucursal,
                $tipo_factura,
                $numero_factura,
                $fecha_adquisicion,
                $fecha_vencimiento
            ]);

            $_SESSION['message'] = 'Medicamento agregado exitosamente';
        }

        $_SESSION['message_type'] = 'success';

    } catch (PDOException $e) {
        if ($e->getCode() == '23000') {
            $_SESSION['message'] = 'El código de referencia ya existe. Use uno diferente o déjelo vacío.';
        } else {
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
        }
        $_SESSION['message_type'] = 'error';
    } catch (Exception $e) {
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

header("Location: index.php");
exit();
