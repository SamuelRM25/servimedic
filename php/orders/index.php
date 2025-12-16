<?php
session_start();
require_once '../../config/database.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener datos del usuario
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $userSucursal = $userData['id_sucursal'] ?? 1;
    
    // Obtener inventario disponible
    $stmt = $conn->prepare("
        SELECT i.*, 
               COALESCE(i.precio_venta, 10.00) as precio_venta_sugerido
        FROM inventario i
        WHERE i.cantidad_med > 0 AND i.estado_ingreso = 'Ingresado'
        ORDER BY i.nom_medicamento
    ");
    $stmt->execute();
    $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener pedidos
    $stmt = $conn->prepare("
        SELECT p.*, u.nombre as usuario_nombre, s.nombre as sucursal_nombre
        FROM pedidos p
        LEFT JOIN usuarios u ON p.id_usuario = u.id
        LEFT JOIN sucursales s ON p.id_sucursal = s.id_sucursal
        ORDER BY p.fecha_pedido DESC
    ");
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Servimedic</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-orange: #FF6B35;
            --color-blue: #4FC3F7;
            --color-white: #FFFFFF;
            --color-text: #2D3748;
            --color-text-light: #718096;
            --color-bg: #F7FAFC;
            --color-border: #E2E8F0;
            --color-success: #48BB78;
            --color-warning: #ECC94B;
            --color-danger: #F56565;
            --font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            font-family: var(--font-family);
            background: var(--color-bg);
            color: var(--color-text);
        }

        .container {
            max-width: 1800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .back-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: var(--color-white);
            border: 1px solid var(--color-border);
            border-radius: 10px;
            color: var(--color-text);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
            color: white;
            border-color: transparent;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--color-text);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--color-orange), #E85A2B);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--color-white);
            padding: 1.25rem;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--color-text-light);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--color-text);
        }

        .table-container {
            background: var(--color-white);
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
        }

        thead th {
            padding: 1rem;
            text-align: left;
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid var(--color-border);
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: var(--color-bg);
        }

        tbody td {
            padding: 1rem;
            font-size: 0.875rem;
            color: var(--color-text);
        }

        .badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-factura { background: #BEE3F8; color: #2C5282; }
        .badge-nota { background: #C6F6D5; color: #22543D; }
        
        .badge-efectivo { background: #C6F6D5; color: #22543D; }
        .badge-transferencia { background: #BEE3F8; color: #2C5282; }
        .badge-cod { background: #FEFCBF; color: #744210; }
        
        .badge-pendiente { background: #FED7D7; color: #742A2A; }
        .badge-procesando { background: #FEFCBF; color: #744210; }
        .badge-enviado { background: #BEE3F8; color: #2C5282; }
        .badge-entregado { background: #D6BCFA; color: #44337A; }
        .badge-completado { background: #C6F6D5; color: #22543D; }
        .badge-cancelado { background: #FED7D7; color: #742A2A; }

        .badge-pagado { background: #C6F6D5; color: #22543D; }
        .badge-pendiente-credito { background: #FEFCBF; color: #744210; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: var(--color-white);
            border-radius: 20px;
            max-width: 1000px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--color-text);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--color-border);
            border-radius: 10px;
            font-size: 0.9rem;
            font-family: var(--font-family);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-blue);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        .search-results {
            position: relative;
            background: var(--color-white);
            border: 2px solid var(--color-border);
            border-radius: 12px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 0.5rem;
            display: none;
        }

        .search-results.show {
            display: block;
        }

        .search-result-item {
            padding: 1rem;
            border-bottom: 1px solid var(--color-border);
            cursor: pointer;
            transition: background 0.2s;
        }

        .search-result-item:hover {
            background: var(--color-bg);
        }

        .cart-section {
            background: var(--color-bg);
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .cart-item {
            background: var(--color-white);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .totals-section {
            background: var(--color-bg);
            padding: 1rem;
            border-radius: 12px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .total-row.main {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-orange);
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 2px solid var(--color-border);
        }

        @media (max-width: 768px) {
            .form-row, .form-row-3 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <div class="page-title-section">
                <a href="../dashboard/index.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="page-title">Pedidos por Mayor</h1>
            </div>
            <?php include '../../includes/clock.php'; ?>
            <button class="btn btn-primary" onclick="openNewOrderModal()">
                <i class="bi bi-plus-circle"></i>
                Nuevo Pedido
            </button>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Pedidos</div>
                <div class="stat-value"><?php echo count($pedidos); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pendientes</div>
                <div class="stat-value">
                    <?php echo count(array_filter($pedidos, fn($p) => $p['estado_pedido'] === 'Pendiente' || $p['estado_pedido'] === 'Procesando')); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Ventas</div>
                <div class="stat-value">
                    Q<?php echo number_format(array_sum(array_column($pedidos, 'total')), 2); ?>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Teléfono</th>
                            <th>Tipo Doc.</th>
                            <th>Pago</th>
                            <th>Método</th>
                            <th>Subtotal</th>
                            <th>Cargo COD</th>
                            <th>Total</th>
                            <th>Estado Pedido</th>
                            <th>Estado Pago</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td><?php echo $pedido['id_pedido']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($pedido['fecha_pedido'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($pedido['cliente_nombre']); ?></strong></td>
                            <td><?php echo htmlspecialchars($pedido['cliente_telefono']); ?></td>
                            <td>
                                <span class="badge <?php echo $pedido['tipo_documento'] === 'Factura' ? 'badge-factura' : 'badge-nota'; ?>">
                                    <?php echo $pedido['tipo_documento']; ?>
                                </span>
                            </td>
                            <td><?php echo $pedido['tipo_pago_estado']; ?></td>
                            <td>
                                <?php 
                                $metodoClass = 'badge-efectivo';
                                if ($pedido['metodo_pago'] === 'Transferencia') $metodoClass = 'badge-transferencia';
                                if ($pedido['metodo_pago'] === 'COD') $metodoClass = 'badge-cod';
                                ?>
                                <span class="badge <?php echo $metodoClass; ?>">
                                    <?php echo $pedido['metodo_pago']; ?>
                                </span>
                            </td>
                            <td>Q<?php echo number_format($pedido['subtotal'], 2); ?></td>
                            <td><?php echo $pedido['cargo_cod'] > 0 ? 'Q' . number_format($pedido['cargo_cod'], 2) : '-'; ?></td>
                            <td><strong>Q<?php echo number_format($pedido['total'], 2); ?></strong></td>
                            <td>
                                <?php 
                                $statusClass = 'badge-' . strtolower($pedido['estado_pedido']);
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo $pedido['estado_pedido']; ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $pagoClass = $pedido['estado_pago'] === 'Pagado' ? 'badge-pagado' : 'badge-pendiente-credito';
                                ?>
                                <span class="badge <?php echo $pagoClass; ?>">
                                    <?php echo $pedido['estado_pago']; ?>
                                </span>
                            </td>
                            <td>
                                <button onclick='printOrderTicket(<?php echo json_encode($pedido); ?>)' 
                                   style="background: none; border: none; color: var(--color-blue); cursor: pointer; text-decoration: none; padding: 0;">
                                    <i class="bi bi-printer"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- New Order Modal -->
    <div class="modal-overlay" id="newOrderModal">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">Nuevo Pedido por Mayor</h2>
                <button class="modal-close" onclick="closeModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="newOrderForm">
                    <!-- Customer Info -->
                    <h4 style="margin-bottom: 1rem;">Información del Cliente</h4>
                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">Nombre/Razón Social *</label>
                            <input type="text" name="cliente_nombre" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Teléfono *</label>
                            <input type="text" name="cliente_telefono" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="cliente_direccion" class="form-control">
                        </div>
                    </div>

                    <!-- Payment Info -->
                    <h4 style="margin: 1.5rem 0 1rem;">Configuración de Pago</h4>
                    <div class="form-row-3">
                        <div class="form-group">
                            <label class="form-label">Tipo de Documento *</label>
                            <select name="tipo_documento" class="form-control" required>
                                <option value="Nota de Envío">Nota de Envío</option>
                                <option value="Factura">Factura</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Estado de Pago *</label>
                            <select name="tipo_pago_estado" class="form-control" required>
                                <option value="Al Contado">Al Contado</option>
                                <option value="Crédito">Crédito</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Método de Pago *</label>
                            <select name="metodo_pago" id="metodoPago" class="form-control" required>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="COD">COD (Contra Entrega)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Products Section -->
                    <h4 style="margin: 1.5rem 0 1rem;">Productos</h4>
                    <div class="form-group">
                        <label class="form-label">Buscar Medicamento</label>
                        <input type="text" id="searchMedicine" class="form-control" placeholder="Buscar por nombre o molécula...">
                        <div id="searchResults" class="search-results"></div>
                    </div>

                    <div class="cart-section">
                        <h5 style="margin-bottom: 1rem;">Productos en el Pedido</h5>
                        <div id="cartItems"></div>
                        <p id="emptyCart" style="text-align: center; color: var(--color-text-light);">No hay productos agregados</p>
                    </div>

                    <!-- Totals -->
                    <div class="totals-section">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <strong>Q<span id="subtotal">0.00</span></strong>
                        </div>
                        <div class="total-row" id="codRow" style="display: none;">
                            <span>Cargo COD (Q26 + 3.5%):</span>
                            <strong style="color: var(--color-warning);">Q<span id="cargoCOD">0.00</span></strong>
                        </div>
                        <div class="total-row main">
                            <span>TOTAL:</span>
                            <span>Q<span id="total">0.00</span></span>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="bi bi-check-circle"></i>
                            Crear Pedido
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../assets/js/ticket_printer.js"></script>
    <script>
        const inventario = <?php echo json_encode($inventario); ?>;
        let cart = [];

        const searchInput = document.getElementById('searchMedicine');
        const searchResults = document.getElementById('searchResults');
        const cartItemsDiv = document.getElementById('cartItems');
        const emptyCartMsg = document.getElementById('emptyCart');
        const metodoPagoSelect = document.getElementById('metodoPago');

        // Search functionality
        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase();
            if (term.length < 2) {
                searchResults.classList.remove('show');
                return;
            }

            const filtered = inventario.filter(item => 
                item.nom_medicamento.toLowerCase().includes(term) ||
                item.mol_medicamento.toLowerCase().includes(term)
            );

            if (filtered.length > 0) {
                searchResults.innerHTML = filtered.map(item => `
                    <div class="search-result-item" onclick="addToCart(${item.id_inventario})">
                        <strong>${item.nom_medicamento}</strong><br>
                        <small>${item.mol_medicamento} • Stock: ${item.cantidad_med} • Q${parseFloat(item.precio_venta_sugerido).toFixed(2)}</small>
                    </div>
                `).join('');
                searchResults.classList.add('show');
            } else {
                searchResults.classList.remove('show');
            }
        });

        function addToCart(idInventario) {
            const item = inventario.find(i => i.id_inventario == idInventario);
            if (!item) return;

            const existing = cart.find(c => c.id_inventario == idInventario);
            if (existing) {
                if (existing.cantidad < item.cantidad_med) {
                    existing.cantidad++;
                    existing.subtotal = existing.cantidad * existing.precio;
                }
            } else {
                cart.push({
                    id_inventario: item.id_inventario,
                    nombre: item.nom_medicamento,
                    precio: parseFloat(item.precio_venta_sugerido),
                    cantidad: 1,
                    stock: item.cantidad_med,
                    subtotal: parseFloat(item.precio_venta_sugerido)
                });
            }

            searchInput.value = '';
            searchResults.classList.remove('show');
            updateCartDisplay();
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        }

        function updateQuantity(index, cantidad) {
            if (cantidad < 1) return;
            const item = cart[index];
            if (cantidad <= item.stock) {
                item.cantidad = parseInt(cantidad);
                item.subtotal = item.cantidad * item.precio;
                updateCartDisplay();
            }
        }

        function updateCartDisplay() {
            if (cart.length === 0) {
                cartItemsDiv.innerHTML = '';
                emptyCartMsg.style.display = 'block';
            } else {
                emptyCartMsg.style.display = 'none';
                cartItemsDiv.innerHTML = cart.map((item, index) => `
                    <div class="cart-item">
                        <div style="flex: 1;">
                            <strong>${item.nombre}</strong><br>
                            <small>Precio: Q${item.precio.toFixed(2)} • Stock: ${item.stock}</small>
                        </div>
                        <div style="display: flex; gap: 1rem; align-items: center;">
                            <input type="number" value="${item.cantidad}" min="1" max="${item.stock}" 
                                   style="width: 80px; padding: 0.5rem; border: 2px solid var(--color-border); border-radius: 8px;"
                                   onchange="updateQuantity(${index}, this.value)">
                            <strong>Q${item.subtotal.toFixed(2)}</strong>
                            <button type="button" onclick="removeFromCart(${index})" 
                                    style="background: #FED7D7; color: #742A2A; border: none; border-radius: 8px; width: 32px; height: 32px; cursor: pointer;">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            }

            calculateTotals();
        }

        function calculateTotals() {
            const subtotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
            const isCOD = metodoPagoSelect.value === 'COD';
            const cargoCOD = isCOD ? 26 + (subtotal * 0.035) : 0;
            const total = subtotal + cargoCOD;

            document.getElementById('subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('cargoCOD').textContent = cargoCOD.toFixed(2);
            document.getElementById('total').textContent = total.toFixed(2);
            document.getElementById('codRow').style.display = isCOD ? 'flex' : 'none';
        }

        metodoPagoSelect.addEventListener('change', calculateTotals);

        function openNewOrderModal() {
            document.getElementById('newOrderModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('newOrderModal').classList.remove('show');
        }

        // Form submission
        document.getElementById('newOrderForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            if (cart.length === 0) {
                alert('Debe agregar al menos un producto al pedido');
                return;
            }

            const formData = new FormData(this);
            const data = {
                cliente_nombre: formData.get('cliente_nombre'),
                cliente_telefono: formData.get('cliente_telefono'),
                cliente_direccion: formData.get('cliente_direccion'),
                tipo_documento: formData.get('tipo_documento'),
                tipo_pago_estado: formData.get('tipo_pago_estado'),
                metodo_pago: formData.get('metodo_pago'),
                productos: cart,
                subtotal: parseFloat(document.getElementById('subtotal').textContent),
                cargo_cod: parseFloat(document.getElementById('cargoCOD').textContent),
                total: parseFloat(document.getElementById('total').textContent)
            };

            try {
                const response = await fetch('save_order.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                
                if (result.success) {
                    alert('Pedido creado exitosamente');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error al procesar el pedido');
                console.error(error);
            }
        });

        // Close modal on overlay click
        document.querySelector('.modal-overlay').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        function printOrderTicket(pedido) {
            // Parse items if they are not already parsed (depends on how data comes from DB)
            // In this view, we don't have the items in the main table loop easily available as an array unless we fetch them.
            // However, the user wants a ticket. For the order list, maybe just the total?
            // Or I can fetch details. 
            // Let's look at the data available: $pedidos query doesn't join details.
            // I'll print a summary ticket.
            
            printTicket({
                ticketNumber: pedido.id_pedido,
                date: new Date(pedido.fecha_pedido).toLocaleString(),
                patientName: pedido.cliente_nombre,
                items: [{
                    quantity: 1,
                    description: "Pedido por Mayor - " + pedido.tipo_documento,
                    total: parseFloat(pedido.total)
                }],
                total: parseFloat(pedido.total)
            });
        }
    </script>
</body>
</html>
