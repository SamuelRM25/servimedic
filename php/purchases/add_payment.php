<?php
session_start();
require_once '../../config/database.php';
require_once 'logger.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        writeLog("Processing batch payment request", $_POST);
        
        // Iniciar transacción
        $conn->beginTransaction();
        
        $idCompra = $_POST['id_compra'];
        $montoTotalAbono = floatval($_POST['monto']);
        
        // 1. Obtener la referencia del grupo de compras
        $stmtRef = $conn->prepare("SELECT codigo_referencia FROM compras WHERE id_compra = ?");
        $stmtRef->execute([$idCompra]);
        $refRow = $stmtRef->fetch(PDO::FETCH_ASSOC);
        
        if (!$refRow) {
            throw new Exception('Compra no encontrada');
        }
        
        $codigoReferencia = $refRow['codigo_referencia'];
        
        // 2. Obtener TODOS los items de esa referencia que tengan saldo pendiente
        // Si no tiene referencia (compras antiguas), solo buscamos por ID
        if ($codigoReferencia) {
            $stmtBatch = $conn->prepare("
                SELECT id_compra, total, abonado, saldo 
                FROM compras 
                WHERE codigo_referencia = ? AND saldo > 0 
                ORDER BY id_compra ASC
            ");
            $stmtBatch->execute([$codigoReferencia]);
        } else {
            $stmtBatch = $conn->prepare("
                SELECT id_compra, total, abonado, saldo 
                FROM compras 
                WHERE id_compra = ? AND saldo > 0
            ");
            $stmtBatch->execute([$idCompra]);
        }
        
        $itemsPendientes = $stmtBatch->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. Calcular saldo total del lote
        $saldoTotalLote = 0;
        foreach ($itemsPendientes as $item) {
            $saldoTotalLote += $item['saldo'];
        }
        
        writeLog("Batch info", ['ref' => $codigoReferencia, 'items_count' => count($itemsPendientes), 'saldo_total' => $saldoTotalLote]);
        
        // 4. Validar monto
        if ($montoTotalAbono > $saldoTotalLote + 0.01) {
            writeLog("Validation failed", ['monto' => $montoTotalAbono, 'saldo_total' => $saldoTotalLote]);
            throw new Exception("El monto (Q$montoTotalAbono) excede el saldo total del lote (Q$saldoTotalLote)");
        }
        
        // 5. Distribuir el abono entre los items
        $montoRestante = $montoTotalAbono;
        
        foreach ($itemsPendientes as $item) {
            if ($montoRestante <= 0.001) break;
            
            $idItem = $item['id_compra'];
            $saldoItem = floatval($item['saldo']);
            
            // Cuánto aplicar a este item
            $montoAplicar = ($montoRestante >= $saldoItem) ? $saldoItem : $montoRestante;
            
            // Actualizar valores del item
            $nuevoAbonado = $item['abonado'] + $montoAplicar;
            $nuevoSaldo = $item['total'] - $nuevoAbonado;
            
            $nuevoEstado = 'Abonado';
            if ($nuevoSaldo <= 0.01) {
                $nuevoEstado = 'Pagado';
                $nuevoSaldo = 0;
            }
            
            writeLog("Applying payment to item", ['id' => $idItem, 'aplicado' => $montoAplicar, 'nuevo_saldo' => $nuevoSaldo]);
            
            // Registrar el abono individual para este item (para mantener consistencia de datos)
            $stmtInsert = $conn->prepare("
                INSERT INTO abonos_compras (
                    id_compra, id_usuario, monto, fecha_abono, forma_pago, referencia, observaciones
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmtInsert->execute([
                $idItem,
                $_SESSION['user_id'],
                $montoAplicar,
                $_POST['fecha_abono'],
                $_POST['forma_pago'],
                $_POST['referencia'] ?? null,
                $_POST['observaciones'] ?? null
            ]);
            
            // Actualizar el item en compras
            $stmtUpdate = $conn->prepare("
                UPDATE compras 
                SET abonado = ?, saldo = ?, estado = ?
                WHERE id_compra = ?
            ");
            $stmtUpdate->execute([$nuevoAbonado, $nuevoSaldo, $nuevoEstado, $idItem]);
            
            $montoRestante -= $montoAplicar;
        }
        
        // Commit transacción
        $conn->commit();
        writeLog("Batch payment completed successfully");
        
        $_SESSION['message'] = 'Abono registrado exitosamente. Se distribuyó en ' . count($itemsPendientes) . ' items.';
        $_SESSION['message_type'] = 'success';
        
    } catch (Exception $e) {
        if (isset($conn) && $conn->inTransaction()) {
            $conn->rollBack();
        }
        writeLog("Error in batch payment", $e->getMessage());
        $_SESSION['message'] = 'Error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }
}

header("Location: index.php");
exit();
?>
