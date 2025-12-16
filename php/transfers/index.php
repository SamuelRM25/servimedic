<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

date_default_timezone_set('America/Guatemala');

verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener el tipo de usuario y sucursal
    $userType = $_SESSION['tipoUsuario'] ?? '';
    $userId = $_SESSION['user_id'] ?? 0;
    $userSucursal = $_SESSION['id_sucursal'] ?? 0;
    
    // Obtener sucursales
    $stmt = $conn->prepare("SELECT id_sucursal, nombre FROM sucursales WHERE activa = 1");
    $stmt->execute();
    $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener medicamentos del inventario actual con stock disponible
    $query = "
        SELECT i.id_inventario, i.nom_medicamento, i.presentacion_med, i.cantidad_med, 
               s.nombre as sucursal, s.id_sucursal
        FROM inventario i
        JOIN sucursales s ON i.id_sucursal = s.id_sucursal
        WHERE i.cantidad_med > 0
    ";
    
    // Si es farmacia, obtener solo medicamentos de su sucursal
    if ($userType === 'farmacia' && $userSucursal > 0) {
        $query .= " AND i.id_sucursal = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$userSucursal]);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->execute();
    }
    
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Verificar si hay medicamentos
    error_log("Medicamentos encontrados: " . count($medicamentos));
    
    $page_title = "Traslados de Medicamentos";
    include_once '../../includes/header.php';
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<div class="d-flex">
    <div class="main-content flex-grow-1">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center">
                    <a href="../dashboard/index.php" class="btn btn-outline-secondary me-3">
                        <i class="bi bi-arrow-left"></i> Regresar
                    </a>
                    <h2>Traslados de Medicamentos</h2>
                </div>
                <?php include '../../includes/clock.php'; ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newTransferModal">
                    <i class="bi bi-plus-circle me-2"></i> Nuevo Traslado
                </button>
            </div>

            <!-- Modal para nuevo traslado -->
                <div class="modal fade" id="newTransferModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Nuevo Traslado</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form id="transferForm" action="save_transfer.php" method="POST">
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="id_sucursal_origen" class="form-label">Sucursal Origen</label>
                                                <select class="form-select" id="id_sucursal_origen" name="id_sucursal_origen" required>
                                                    <option value="">Seleccionar sucursal...</option>
                                                    <?php foreach ($sucursales as $sucursal): ?>
                                                    <option value="<?php echo $sucursal['id_sucursal']; ?>">
                                                        <?php echo htmlspecialchars($sucursal['nombre']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="id_sucursal_destino" class="form-label">Sucursal Destino</label>
                                                <select class="form-select" id="id_sucursal_destino" name="id_sucursal_destino" required>
                                                    <option value="">Seleccionar sucursal...</option>
                                                    <?php foreach ($sucursales as $sucursal): ?>
                                                    <option value="<?php echo $sucursal['id_sucursal']; ?>">
                                                        <?php echo htmlspecialchars($sucursal['nombre']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="id_medicamento" class="form-label">Medicamento</label>
                                        <select class="form-select" id="id_medicamento" name="id_medicamento" required>
                                            <option value="">Seleccionar medicamento...</option>
                                            <?php 
                                            if (count($medicamentos) > 0):
                                                foreach ($medicamentos as $medicamento): 
                                            ?>
                                            <option value="<?php echo $medicamento['id_inventario']; ?>" 
                                                    data-sucursal="<?php echo $medicamento['id_sucursal']; ?>"
                                                    data-cantidad="<?php echo $medicamento['cantidad_med']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($medicamento['nom_medicamento']); ?>">
                                                <?php echo htmlspecialchars($medicamento['nom_medicamento'] . ' - ' . $medicamento['presentacion_med'] . ' (' . $medicamento['sucursal'] . ' - Stock: ' . $medicamento['cantidad_med'] . ')'); ?>
                                            </option>
                                            <?php 
                                                endforeach;
                                            else:
                                            ?>
                                            <option value="" disabled>No hay medicamentos disponibles</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="cantidad" class="form-label">Cantidad a transferir</label>
                                        <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" required>
                                        <div class="form-text">Stock disponible: <span id="cantidad_disponible">0</span></div>
                                    </div>
                                    
                                    <div id="alertContainer"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="submit" class="btn btn-primary">Realizar Traslado</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            <!-- Tabla de traslados -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Sucursal Origen</th>
                                    <th>Sucursal Destino</th>
                                    <th>Medicamento</th>
                                    <th>Cantidad</th>
                                    <th>Estado</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("
                                    SELECT t.*, 
                                           so.nombre as sucursal_origen, 
                                           sd.nombre as sucursal_destino, 
                                           i.nom_medicamento,
                                           u.nombre as usuario_nombre
                                    FROM traslados t
                                    JOIN sucursales so ON t.id_sucursal_origen = so.id_sucursal
                                    JOIN sucursales sd ON t.id_sucursal_destino = sd.id_sucursal
                                    JOIN inventario i ON t.id_medicamento = i.id_inventario
                                    JOIN usuarios u ON t.usuario_id = u.idUsuario
                                    ORDER BY t.fecha_traslado DESC
                                    LIMIT 50
                                ");
                                $stmt->execute();
                                $traslados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($traslados) > 0):
                                    foreach ($traslados as $traslado):
                                ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($traslado['fecha_traslado'])); ?></td>
                                    <td><?php echo htmlspecialchars($traslado['sucursal_origen']); ?></td>
                                    <td><?php echo htmlspecialchars($traslado['sucursal_destino']); ?></td>
                                    <td><?php echo htmlspecialchars($traslado['nom_medicamento']); ?></td>
                                    <td><?php echo $traslado['cantidad']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $traslado['estado'] === 'completado' ? 'success' : ($traslado['estado'] === 'pendiente' ? 'warning' : 'danger'); ?>">
                                            <?php echo ucfirst($traslado['estado']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($traslado['usuario_nombre']); ?></td>
                                </tr>
                                <?php
                                    endforeach;
                                else:
                                ?>
                                <tr>
                                    <td colspan="7" class="text-center">No hay traslados registrados</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectMedicamento = document.getElementById('id_medicamento');
    const selectOrigen = document.getElementById('id_sucursal_origen');
    const selectDestino = document.getElementById('id_sucursal_destino');
    const inputCantidad = document.getElementById('cantidad');
    const spanCantidadDisponible = document.getElementById('cantidad_disponible');
    const alertContainer = document.getElementById('alertContainer');

    // Función para mostrar alertas
    function showAlert(message, type = 'warning') {
        alertContainer.innerHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    }

    // Función para actualizar la cantidad disponible
    function actualizarCantidadDisponible() {
        const selectedOption = selectMedicamento.options[selectMedicamento.selectedIndex];
        
        if (selectedOption.value) {
            const cantidadDisponible = parseInt(selectedOption.getAttribute('data-cantidad'));
            const sucursalMedicamento = parseInt(selectedOption.getAttribute('data-sucursal'));
            const sucursalOrigen = parseInt(selectOrigen.value);
            
            spanCantidadDisponible.textContent = cantidadDisponible;
            inputCantidad.max = cantidadDisponible;
            
            // Verificar que el medicamento esté en la sucursal origen
            if (sucursalOrigen && sucursalMedicamento !== sucursalOrigen) {
                showAlert('Este medicamento no está disponible en la sucursal origen seleccionada.', 'warning');
                selectMedicamento.value = '';
                spanCantidadDisponible.textContent = '0';
                inputCantidad.max = '1';
            }
        } else {
            spanCantidadDisponible.textContent = '0';
            inputCantidad.max = '1';
        }
    }

    // Evento cuando cambia el medicamento seleccionado
    selectMedicamento.addEventListener('change', function() {
        actualizarCantidadDisponible();
        validarFormulario();
    });

    // Evento cuando cambia la sucursal origen
    selectOrigen.addEventListener('change', function() {
        // Filtrar medicamentos por sucursal
        const options = selectMedicamento.querySelectorAll('option');
        let hayMedicamentos = false;
        
        options.forEach(option => {
            if (option.value) {
                const sucursalMedicamento = parseInt(option.getAttribute('data-sucursal'));
                const sucursalOrigen = parseInt(selectOrigen.value);
                
                if (sucursalOrigen && sucursalMedicamento === sucursalOrigen) {
                    option.style.display = '';
                    hayMedicamentos = true;
                } else {
                    option.style.display = 'none';
                }
            }
        });
        
        // Limpiar selección actual
        selectMedicamento.value = '';
        spanCantidadDisponible.textContent = '0';
        
        if (!hayMedicamentos && selectOrigen.value) {
            showAlert('No hay medicamentos disponibles en esta sucursal.', 'info');
        }
        
        validarFormulario();
    });

    // Evento cuando cambia la sucursal destino
    selectDestino.addEventListener('change', function() {
        validarSucursales();
        validarFormulario();
    });

    // Evento cuando cambia la cantidad
    inputCantidad.addEventListener('input', function() {
        const max = parseInt(this.max);
        const value = parseInt(this.value);
        
        if (value > max) {
            this.value = max;
            showAlert('La cantidad no puede ser mayor al stock disponible.', 'warning');
        }
        
        validarFormulario();
    });

    // Validar que las sucursales sean diferentes
    function validarSucursales() {
        if (selectOrigen.value && selectDestino.value && selectOrigen.value === selectDestino.value) {
            showAlert('La sucursal origen y destino deben ser diferentes.', 'danger');
            return false;
        }
        return true;
    }

    // Validar el formulario completo
    function validarFormulario() {
        const medicamentoSeleccionado = selectMedicamento.value;
        const sucursalOrigen = selectOrigen.value;
        const sucursalDestino = selectDestino.value;
        const cantidad = parseInt(inputCantidad.value);
        const cantidadDisponible = parseInt(spanCantidadDisponible.textContent);
        
        const submitBtn = document.querySelector('#transferForm button[type="submit"]');
        
        if (medicamentoSeleccionado && sucursalOrigen && sucursalDestino && 
            cantidad > 0 && cantidad <= cantidadDisponible && validarSucursales()) {
            submitBtn.disabled = false;
            return true;
        } else {
            submitBtn.disabled = true;
            return false;
        }
    }

    // Validar formulario al enviar
    document.getElementById('transferForm').addEventListener('submit', function(e) {
        if (!validarFormulario()) {
            e.preventDefault();
            showAlert('Por favor complete todos los campos correctamente.', 'danger');
        }
    });

    // Limpiar alertas al cerrar el modal
    document.getElementById('newTransferModal').addEventListener('hidden.bs.modal', function() {
        alertContainer.innerHTML = '';
        selectMedicamento.value = '';
        selectOrigen.value = '';
        selectDestino.value = '';
        inputCantidad.value = '';
        spanCantidadDisponible.textContent = '0';
    });
});
</script>