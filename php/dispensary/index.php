<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

date_default_timezone_set('America/Guatemala');

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener usuario
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['rol'] ?? '';
    
    // Obtener sucursal del usuario
    $stmt = $conn->prepare("SELECT id_sucursal FROM usuarios WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $userSucursal = $userData['id_sucursal'] ?? 1;
    
    // Obtener inventario disponible con tipo de factura y precios
    // Solo mostrar inventario que ya fue ingresado (no pendientes)
    $stmt = $conn->prepare("
        SELECT i.*, s.nombre as sucursal_nombre,
               COALESCE(i.precio_venta, 10.00) as precio_venta_sugerido,
               COALESCE(i.precio_costo, 0) as precio_costo
        FROM inventario i
        LEFT JOIN sucursales s ON i.id_sucursal = s.id_sucursal
        WHERE i.cantidad_med > 0 AND i.estado_ingreso = 'Ingresado'
        ORDER BY i.nom_medicamento
    ");
    $stmt->execute();
    $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$apellido_usuario = $_SESSION['apellido'] ?? '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Despacho - Servimedic</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --color-orange: #FF6B35;
            --color-orange-light: #FF8C61;
            --color-orange-dark: #E85A2B;
            --color-blue: #4FC3F7;
            --color-blue-light: #81D4FA;
            --color-blue-dark: #0288D1;
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
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Header */
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

        /* Main Grid */
        .dispensary-grid {
            display: grid;
            grid-template-columns: 1fr 450px;
            gap: 1.5rem;
        }

        .card {
            background: var(--color-white);
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--color-border);
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
        }

        .card-header h3 {
            color: white;
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Search Section */
        .search-box {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .search-input-wrapper {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.25rem;
            background: var(--color-bg);
            border-radius: 12px;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .search-input-wrapper:focus-within {
            border-color: var(--color-blue);
            background: var(--color-white);
        }

        .search-input-wrapper i {
            color: var(--color-text-light);
            font-size: 1.125rem;
        }

        .search-input-wrapper input {
            flex: 1;
            border: none;
            background: transparent;
            outline: none;
            font-size: 0.95rem;
            color: var(--color-text);
            font-family: var(--font-family);
        }

        .search-input-wrapper input::placeholder {
            color: var(--color-text-light);
        }

        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 0.5rem;
            background: var(--color-white);
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            max-height: 400px;
            overflow-y: auto;
            z-index: 100;
            display: none;
        }

        .search-results.show {
            display: block;
        }

        .search-result-item {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--color-border);
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background: linear-gradient(90deg, rgba(255, 107, 53, 0.05), rgba(79, 195, 247, 0.05));
        }

        .search-result-name {
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 0.25rem;
        }

        .search-result-meta {
            font-size: 0.875rem;
            color: var(--color-text-light);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-factura {
            background: #BEE3F8;
            color: #2C5282;
        }

        .badge-nota {
            background: #C6F6D5;
            color: #22543D;
        }

        .badge-consumidor {
            background: #FED7D7;
            color: #742A2A;
        }

        .badge-stock {
            background: #E6FFFA;
            color: #234E52;
        }

        /* Cart */
        .cart-items {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 1rem;
        }

        .cart-item {
            padding: 1rem;
            border: 1px solid var(--color-border);
            border-radius: 12px;
            margin-bottom: 0.75rem;
            background: var(--color-bg);
        }

        .cart-item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.75rem;
        }

        .cart-item-name {
            font-weight: 600;
            color: var(--color-text);
            flex: 1;
        }

        .cart-item-remove {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            border: none;
            background: #FED7D7;
            color: #742A2A;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .cart-item-remove:hover {
            background: #FEB2B2;
        }

        .cart-item-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
            color: var(--color-text-light);
        }

        .cart-item-price {
            font-weight: 600;
            color: var(--color-orange);
            font-size: 1rem;
        }

        /* Cart Empty */
        .cart-empty {
            text-align: center;
            padding: 3rem 1rem;
        }

        .cart-empty i {
            font-size: 3rem;
            color: var(--color-text-light);
            margin-bottom: 1rem;
        }

        .cart-empty p {
            color: var(--color-text-light);
        }

        /* Totals */
        .totals-section {
            border-top: 2px solid var(--color-border);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }

        .total-row.final {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--color-orange);
            margin-top: 0.5rem;
        }

        /* Form Controls */
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

        /* Toggle Switch */
        .toggle-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--color-bg);
            border-radius: 12px;
            margin-bottom: 1.25rem;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 26px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        .toggle-switch input:checked + .toggle-slider {
            background: linear-gradient(135deg, var(--color-orange), var(--color-blue));
        }

        .toggle-switch input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }

        .toggle-label {
            font-weight: 600;
            color: var(--color-text);
        }

        /* Payment Methods */
        .payment-methods {
            margin-bottom: 1.5rem;
        }

        .payment-method-item {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            margin-bottom: 0.75rem;
            padding: 0.75rem;
            background: var(--color-bg);
            border-radius: 10px;
        }

        .payment-method-item select {
            flex: 1;
        }

        .payment-method-item input[type="number"] {
            flex: 1;
        }

        .payment-method-item button {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: none;
            background: #FED7D7;
            color: #742A2A;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--color-orange), var(--color-orange-dark));
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: var(--color-bg);
            color: var(--color-text);
        }

        .btn-secondary:hover {
            background: var(--color-border);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--color-success), #38A169);
            color: white;
        }

        .btn-success:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .dispensary-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
        }

        .reserved-badge {
            background: #FEFCBF;
            color: #744210;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div class="page-title-section">
                <a href="../dashboard/index.php" class="back-btn">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="page-title">Despacho / Ventas</h1>
            </div>
            <?php include '../../includes/clock.php'; ?>
        </div>

        <!-- Main Grid -->
        <div class="dispensary-grid">
            <!-- Left: Product Search -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="bi bi-search me-2"></i>Buscar Medicamento</h3>
                </div>
                <div class="card-body">
                    <!-- Scanner Input -->
                    <div style="background: #ebf8ff; border: 1px dashed #4299e1; padding: 15px; border-radius: 12px; margin-bottom: 20px;">
                        <label style="font-size: 0.85rem; color: #2b6cb0; font-weight: 600; display: block; margin-bottom: 8px;">
                            <i class="bi bi-upc-scan me-1"></i> Escáner Inteligente (Modo Rápido)
                        </label>
                        <input type="text" id="barcodeScanner" class="form-control" placeholder="Escanear código de barras aquí..." autofocus 
                               style="border-color: #bee3f8; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <small class="text-muted" style="font-size: 0.75rem; margin-top: 4px; display: block;">
                            Presiona ENTER para agregar automáticamente al carrito.
                        </small>
                    </div>

                    <div class="search-box">
                        <div class="search-input-wrapper">
                            <i class="bi bi-search"></i>
                            <input type="text" id="searchInput" placeholder="Buscar por nombre o molécula...">
                        </div>
                        <div class="search-results" id="searchResults"></div>
                    </div>
                    
                    <div id="productDetails" style="display: none;">
                        <h4 style="margin-bottom: 1rem; color: var(--color-text);">Producto Seleccionado</h4>
                        <div style="padding: 1rem; background: var(--color-bg); border-radius: 12px; margin-bottom: 1rem;">
                            <div style="font-weight: 600; margin-bottom: 0.5rem;" id="selectedProductName"></div>
                            <div style="font-size: 0.875rem; color: var(--color-text-light); margin-bottom: 0.5rem;" id="selectedProductMeta"></div>
                            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;" id="selectedProductBadges"></div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Cantidad</label>
                                <input type="number" id="quantityInput" class="form-control" min="1" value="1">
                                <small style="color: var(--color-text-light); margin-top: 0.25rem; display: block;">
                                    Disponible: <span id="availableStock">0</span> 
                                    <span id="reservedStock"></span>
                                </small>
                            </div>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label">Precio Unitario</label>
                                <div class="input-group">
                                    <span class="input-group-text">Q</span>
                                    <input type="number" id="priceInput" class="form-control" step="0.01" readonly style="background-color: #e2e8f0; cursor: not-allowed;">
                                    <button class="btn btn-outline-secondary" type="button" id="unlockPriceBtn" title="Desbloquear Precio">
                                        <i class="bi bi-lock-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <button class="btn btn-primary" id="addToCartBtn">
                            <i class="bi bi-cart-plus"></i>
                            Agregar al Carrito
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right: Cart & Checkout -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="bi bi-cart3 me-2"></i>Carrito de Venta</h3>
                </div>
                <div class="card-body">
                    <!-- Staff Sale Toggle -->
                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'Administrador'): ?>
                    <div class="toggle-container">
                        <label class="toggle-switch">
                            <input type="checkbox" id="staffSaleToggle">
                            <span class="toggle-slider"></span>
                        </label>
                        <span class="toggle-label">Venta a Personal (precio costo)</span>
                    </div>
                    <?php endif; ?>

                    <!-- Customer Info -->
                    <div class="form-group">
                        <label class="form-label">Cliente</label>
                        <input type="text" id="customerName" class="form-control" placeholder="Nombre del cliente" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">NIT (Opcional)</label>
                        <input type="text" id="customerNit" class="form-control" placeholder="CF o NIT" value="CF">
                    </div>

                    <!-- Cart Items -->
                    <div class="cart-items" id="cartItems">
                        <div class="cart-empty">
                            <i class="bi bi-cart-x"></i>
                            <p>El carrito está vacío</p>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="totals-section">
                        <div class="total-row">
                            <span>Subtotal:</span>
                            <span>Q<span id="subtotal">0.00</span></span>
                        </div>
                        <div class="total-row" id="discountRow" style="display: none; color: var(--color-success);">
                            <span>Descuento (10%):</span>
                            <span>-Q<span id="discount">0.00</span></span>
                        </div>
                        <div class="total-row final">
                            <span>TOTAL:</span>
                            <span>Q<span id="totalFinal">0.00</span></span>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="form-group">
                        <label class="form-label">Formas de Pago</label>
                        <div class="payment-methods" id="paymentMethods">
                            <div class="payment-method-item">
                                <select class="form-control payment-type">
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Tarjeta Débito">Tarjeta Débito</option>
                                    <option value="Tarjeta Crédito">Tarjeta Crédito</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Seguro Médico">Seguro Médico</option>
                                </select>
                                <input type="number" class="form-control payment-amount" placeholder="Monto" step="0.01" min="0">
                            </div>
                        </div>
                        <button type="button" class="btn btn-secondary" id="addPaymentBtn" style="margin-top: 0.5rem;">
                            <i class="bi bi-plus-circle"></i>
                            Agregar Forma de Pago
                        </button>
                    </div>

                    <!-- Complete Sale Button -->
                    <button class="btn btn-success" id="completeSaleBtn" disabled>
                        <i class="bi bi-check-circle"></i>
                        Completar Venta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/ticket_printer.js"></script>
    <!-- Authorization Modal -->
    <div class="modal-overlay" id="authModal" style="z-index: 2000;">
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h2 class="modal-title"><i class="bi bi-shield-lock"></i> Autorización Requerida</h2>
                <button class="modal-close" onclick="closeModal('authModal')">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Se requiere autorización de un Administrador para modificar el precio.</p>
                
                <div class="alert alert-danger" id="authError" style="display: none; margin-bottom: 1rem; padding: 0.5rem;"></div>

                <div class="form-group">
                    <label class="form-label">Usuario Administrador</label>
                    <input type="text" id="authUsername" class="form-control" placeholder="Usuario">
                </div>
                <div class="form-group">
                    <label class="form-label">Contraseña</label>
                    <input type="password" id="authPassword" class="form-control" placeholder="Contraseña">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('authModal')">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmAuthBtn">Autorizar</button>
            </div>
        </div>
    </div>

    <script>
        // ... (Existing variables)
        let authorizedBy = null; // Store ID of authorizer if price is changed
        // Data
        const inventario = <?php echo json_encode($inventario); ?>;
        const userId = <?php echo $userId; ?>;
        const userSucursal = <?php echo $userSucursal; ?>;
        
        let cart = [];
        let selectedProduct = null;
        let reservedStock = {}; // Track reserved quantities

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');

        searchInput.addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            searchResults.innerHTML = '';

            if (term.length < 2) {
                searchResults.classList.remove('show');
                return;
            }

            const filtered = inventario.filter(item => 
                item.nom_medicamento.toLowerCase().includes(term) ||
                item.mol_medicamento.toLowerCase().includes(term)
            );

            if (filtered.length > 0) {
                filtered.slice(0, 8).forEach(item => {
                    // Calculate reserved quantity for this item
                    const reserved = reservedStock[item.id_inventario] || 0;
                    const available = item.cantidad_med - reserved;

                    const div = document.createElement('div');
                    div.className = 'search-result-item';
                    div.innerHTML = `
                        <div class="search-result-name">${item.nom_medicamento}</div>
                        <div class="search-result-meta">
                            <span>${item.mol_medicamento}</span>
                            <span class="badge badge-stock">Stock: ${available}</span>
                            ${item.tipo_factura === 'Con Factura' ? '<span class="badge badge-factura">Con Factura</span>' : ''}
                            ${item.tipo_factura === 'Nota de Envío' ? '<span class="badge badge-nota">Nota de Envío</span>' : ''}
                            ${item.tipo_factura === 'Consumidor Final' ? '<span class="badge badge-consumidor">Consumidor Final</span>' : ''}
                            ${reserved > 0 ? `<span class="reserved-badge">Reservado: ${reserved}</span>` : ''}
                        </div>
                    `;
                    
                    div.addEventListener('click', () => selectProduct(item));
                    searchResults.appendChild(div);
                });
                searchResults.classList.add('show');
            } else {
                searchResults.innerHTML = '<div class="search-result-item">No se encontraron resultados</div>';
                searchResults.classList.add('show');
            }
        });

        // Click outside to close search
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.remove('show');
            }
        });

        // Smart Scanner Logic
        const barcodeScanner = document.getElementById('barcodeScanner');
        
        barcodeScanner.addEventListener('keydown', async (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = barcodeScanner.value.trim();
                if (!code) return;
                
                // Audio feedback helpers
                const playSuccess = () => { /* Play beep if available */ };
                const playError = () => { alert('Producto no encontrado'); };

                try {
                    const res = await fetch(`search_by_barcode.php?code=${encodeURIComponent(code)}`);
                    const data = await res.json();
                    
                    if (data.success && data.product) {
                        // Logic to add 1 unit automatically
                        selectProduct(data.product); // Set as selected
                        
                        // Force quantity 1 and predefined price
                        document.getElementById('quantityInput').value = 1;

                        // DO NOT Auto-add. Focus quantity so user can edit it or price.
                        // User request: "Quiero que se pueda editar la cantidad... igual que el precio"
                        
                        setTimeout(() => {
                           document.getElementById('quantityInput').select();
                           // Clear scanner for next item
                           barcodeScanner.value = '';
                        }, 100);
                        
                    } else {
                        playError();
                        barcodeScanner.select();
                    }
                } catch (err) {
                    console.error(err);
                    playError();
                }
            }
        });

        // Enter key on Quantity or Price adds to cart
        ['quantityInput', 'priceInput'].forEach(id => {
             document.getElementById(id).addEventListener('keydown', (e) => {
                 if (e.key === 'Enter') {
                     e.preventDefault();
                     document.getElementById('addToCartBtn').click();
                     // Refocus scanner for next item
                     document.getElementById('barcodeScanner').focus();
                 }
             });
        });

        // Select product
        function selectProduct(product) {
            selectedProduct = product;
            const reserved = reservedStock[product.id_inventario] || 0;
            const available = product.cantidad_med - reserved;

            document.getElementById('selectedProductName').textContent = product.nom_medicamento;
            document.getElementById('selectedProductMeta').textContent = `${product.mol_medicamento} • ${product.presentacion_med}`;
            
            let badges = `<span class="badge badge-stock">Stock: ${available}</span>`;
            if (product.tipo_factura === 'Con Factura') badges += '<span class="badge badge-factura">Con Factura</span>';
            if (product.tipo_factura === 'Nota de Envío') badges += '<span class="badge badge-nota">Nota de Envío</span>';
            if (product.tipo_factura === 'Consumidor Final') badges += '<span class="badge badge-consumidor">Consumidor Final</span>';
            if (reserved > 0) badges += `<span class="reserved-badge">Reservado: ${reserved}</span>`;

            document.getElementById('selectedProductBadges').innerHTML = badges;
            document.getElementById('availableStock').textContent = available;
            document.getElementById('reservedStock').innerHTML = reserved > 0 ? `<span class="reserved-badge">Reservado: ${reserved}</span>` : '';
            document.getElementById('quantityInput').max = available;
            
            // Establecer precio desde el inventario
            // Si es venta a personal, usar precio_costo, si no, usar precio_venta
            const isStaffSale = document.getElementById('staffSaleToggle').checked;
            const precioDefault = isStaffSale ? 
                (parseFloat(product.precio_costo) || 10.00) : 
                (parseFloat(product.precio_venta_sugerido) || 10.00);
            
            document.getElementById('priceInput').value = precioDefault.toFixed(2);
            
            document.getElementById('productDetails').style.display = 'block';
            searchResults.classList.remove('show');
            searchInput.value = '';
        }

        // Add to cart with real-time reservation
        // Unlock Price Logic
        const unlockPriceBtn = document.getElementById('unlockPriceBtn');
        const priceInput = document.getElementById('priceInput');
        const authModal = document.getElementById('authModal');
        const confirmAuthBtn = document.getElementById('confirmAuthBtn');

        unlockPriceBtn.addEventListener('click', () => {
            // Check if already unlocked
            if (!priceInput.readOnly) {
                // Lock it back
                priceInput.readOnly = true;
                priceInput.style.backgroundColor = '#e2e8f0';
                priceInput.style.cursor = 'not-allowed';
                unlockPriceBtn.innerHTML = '<i class="bi bi-lock-fill"></i>';
                unlockPriceBtn.classList.remove('btn-warning');
                unlockPriceBtn.classList.add('btn-outline-secondary');
                authorizedBy = null; // Reset authorization
            } else {
                // Open auth modal
                document.getElementById('authUsername').value = '';
                document.getElementById('authPassword').value = '';
                document.getElementById('authError').style.display = 'none';
                authModal.classList.add('show');
                document.getElementById('authUsername').focus();
            }
        });

        confirmAuthBtn.addEventListener('click', async () => {
            const user = document.getElementById('authUsername').value;
            const pass = document.getElementById('authPassword').value;

            if (!user || !pass) {
                showAuthError('Ingrese usuario y contraseña');
                return;
            }

            try {
                confirmAuthBtn.disabled = true;
                confirmAuthBtn.textContent = 'Verificando...';

                const res = await fetch('verify_authorization.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({username: user, password: pass})
                });
                const data = await res.json();

                if (data.success) {
                    // Success!
                    closeModal('authModal');
                    
                    // Unlock Field
                    priceInput.readOnly = false;
                    priceInput.style.backgroundColor = '#fff';
                    priceInput.style.cursor = 'text';
                    priceInput.select();
                    
                    // Update Button Visuals
                    unlockPriceBtn.innerHTML = '<i class="bi bi-unlock-fill"></i>';
                    unlockPriceBtn.classList.remove('btn-outline-secondary');
                    unlockPriceBtn.classList.add('btn-warning');
                    
                    // Store Logic
                    authorizedBy = data.user_id;
                    // TODO: Send authorizedBy to addToCart logic
                    
                } else {
                    showAuthError(data.message);
                }
            } catch (err) {
                showAuthError('Error de conexión');
            } finally {
                confirmAuthBtn.disabled = false;
                confirmAuthBtn.textContent = 'Autorizar';
            }
        });

        function showAuthError(msg) {
            const err = document.getElementById('authError');
            err.textContent = msg;
            err.style.display = 'block';
        }

        // Add to cart logic adjustment needed        // Add to cart with real-time reservation
        document.getElementById('addToCartBtn').addEventListener('click', () => {
            if (!selectedProduct) return;

            const quantity = parseInt(document.getElementById('quantityInput').value);
            const price = parseFloat(document.getElementById('priceInput').value);
            
            if (isNaN(quantity) || quantity <= 0) {
                alert('Ingrese una cantidad válida');
                return;
            }

            const reserved = reservedStock[selectedProduct.id_inventario] || 0;
            if (quantity + reserved > selectedProduct.cantidad_med) {
                alert('No hay suficiente stock disponible');
                return;
            }

            // Add to cart array
            const item = {
                id_inventario: selectedProduct.id_inventario,
                nombre: selectedProduct.nom_medicamento,
                presentacion: selectedProduct.presentacion_med,
                tipo_factura: selectedProduct.tipo_factura,
                cantidad: quantity,
                precio_unitario: price,
                subtotal: price * quantity,
                authorized_by: authorizedBy // Capture ID if authorized
            };

            // Check if product already in cart
            const existingIndex = cart.findIndex(cartItem => cartItem.id_inventario === selectedProduct.id_inventario);

            if (existingIndex >= 0) {
                cart[existingIndex].cantidad += quantity;
                cart[existingIndex].subtotal = cart[existingIndex].cantidad * cart[existingIndex].precio_unitario;
                    subtotal: quantity * price
                });
            }

            // Reserve stock
            if (!reservedStock[selectedProduct.id_inventario]) {
                reservedStock[selectedProduct.id_inventario] = 0;
            }
            reservedStock[selectedProduct.id_inventario] += quantity;

            updateCart();
            document.getElementById('productDetails').style.display = 'none';
            selectedProduct = null;
        });

        // Remove from cart
        function removeFromCart(index) {
            const item = cart[index];
            // Release reserved stock
            reservedStock[item.id_inventario] -= item.cantidad;
            if (reservedStock[item.id_inventario] <= 0) {
                delete reservedStock[item.id_inventario];
            }
            cart.splice(index, 1);
            updateCart();
        }

        // Update cart display
        function updateCart() {
            const container = document.getElementById('cartItems');
            
            if (cart.length === 0) {
                container.innerHTML = `
                    <div class="cart-empty">
                        <i class="bi bi-cart-x"></i>
                        <p>El carrito está vacío</p>
                    </div>
                `;
                document.getElementById('completeSaleBtn').disabled = true;
            } else {
                container.innerHTML = cart.map((item, index) => `
                    <div class="cart-item">
                        <div class="cart-item-header">
                            <div class="cart-item-name">
                                ${item.nombre}
                                ${item.tipo_factura !== 'Consumidor Final' ? `<span class="badge ${item.tipo_factura === 'Con Factura' ? 'badge-factura' : 'badge-nota'}">${item.tipo_factura}</span>` : ''}
                            </div>
                            <button class="cart-item-remove" onclick="removeFromCart(${index})">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                        <div class="cart-item-details">
                            <span>Cant: ${item.cantidad} x Q${item.precio_unitario.toFixed(2)}</span>
                            <span class="cart-item-price">Q${item.subtotal.toFixed(2)}</span>
                        </div>
                    </div>
                `).join('');
                
                document.getElementById('completeSaleBtn').disabled = false;
            }

            // Calculate totals (sin descuento, la diferencia está en el precio usado)
            const subtotal = cart.reduce((sum, item) => sum + item.subtotal, 0);
            const total = subtotal; // Ya no hay descuento, el precio ya está ajustado

            document.getElementById('subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('discount').textContent = '0.00';
            document.getElementById('totalFinal').textContent = total.toFixed(2);
            document.getElementById('discountRow').style.display = 'none'; // Ocultar fila de descuento
        }

        // Staff sale toggle - actualizar precio del producto seleccionado
        document.getElementById('staffSaleToggle').addEventListener('change', function() {
            // Si hay un producto seleccionado, actualizar su precio
            if (selectedProduct) {
                const isStaffSale = this.checked;
                const precioDefault = isStaffSale ? 
                    (parseFloat(selectedProduct.precio_costo) || 10.00) : 
                    (parseFloat(selectedProduct.precio_venta_sugerido) || 10.00);
                
                document.getElementById('priceInput').value = precioDefault.toFixed(2);
            }
            updateCart();
        });

        // Add payment method
        document.getElementById('addPaymentBtn').addEventListener('click', () => {
            const container = document.getElementById('paymentMethods');
            const div = document.createElement('div');
            div.className = 'payment-method-item';
            div.innerHTML = `
                <select class="form-control payment-type">
                    <option value="Efectivo">Efectivo</option>
                    <option value="Tarjeta Débito">Tarjeta Débito</option>
                    <option value="Tarjeta Crédito">Tarjeta Crédito</option>
                    <option value="Transferencia">Transferencia</option>
                    <option value="Cheque">Cheque</option>
                    <option value="Seguro Médico">Seguro Médico</option>
                </select>
                <input type="number" class="form-control payment-amount" placeholder="Monto" step="0.01" min="0">
                <button type="button" onclick="this.parentElement.remove()"><i class="bi bi-x"></i></button>
            `;
            container.appendChild(div);
        });

        // Complete sale
        document.getElementById('completeSaleBtn').addEventListener('click', async () => {
            const customerName = document.getElementById('customerName').value.trim();
            const customerNit = document.getElementById('customerNit').value.trim();

            if (!customerName) {
                alert('Ingrese el nombre del cliente');
                return;
            }

            if (cart.length === 0) {
                alert('El carrito está vacío');
                return;
            }

            // Get payment methods
            const paymentMethods = [];
            document.querySelectorAll('.payment-method-item').forEach(item => {
                const type = item.querySelector('.payment-type').value;
                const amount = parseFloat(item.querySelector('.payment-amount').value || 0);
                if (amount > 0) {
                    paymentMethods.push({ tipo_pago: type, monto: amount });
                }
            });

            if (paymentMethods.length === 0) {
                alert('Agregue al menos una forma de pago');
                return;
            }

            const totalPayment = paymentMethods.reduce((sum, p) => sum + p.monto, 0);
            const totalSale = parseFloat(document.getElementById('totalFinal').textContent);

            if (Math.abs(totalPayment - totalSale) > 0.01) {
                alert(`El total de pagos (Q${totalPayment.toFixed(2)}) no coincide con el total de la venta (Q${totalSale.toFixed(2)})`);
                return;
            }

            // Prepare sale data
            const saleData = {
                id_usuario: userId,
                id_sucursal: userSucursal,
                cliente_nombre: customerName,
                cliente_nit: customerNit,
                venta_personal: document.getElementById('staffSaleToggle').checked ? 1 : 0,
                total: parseFloat(document.getElementById('subtotal').textContent),
                descuento: parseFloat(document.getElementById('discount').textContent),
                total_final: totalSale,
                items: cart,
                formas_pago: paymentMethods
            };

            try {
                const response = await fetch('save_sale.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(saleData)
                });

                const result = await response.json();
                
                if (result.success) {
                    alert('Venta completada exitosamente');
                    
                    // Print Ticket
                    printTicket({
                        ticketNumber: result.ticket_number || '---',
                        date: new Date().toLocaleString(),
                        patientName: customerName,
                        items: cart.map(item => ({
                            quantity: item.cantidad,
                            description: item.nombre,
                            total: item.subtotal
                        })),
                        total: totalSale
                    });

                    // Reset
                    cart = [];
                    reservedStock = {};
                    updateCart();
                    document.getElementById('customerName').value = '';
                    document.getElementById('customerNit').value = 'CF';
                    document.getElementById('staffSaleToggle').checked = false;
                    document.getElementById('paymentMethods').innerHTML = `
                        <div class="payment-method-item">
                            <select class="form-control payment-type">
                                <option value="Efectivo">Efectivo</option>
                                <option value="Tarjeta Débito">Tarjeta Débito</option>
                                <option value="Tarjeta Crédito">Tarjeta Crédito</option>
                                <option value="Transferencia">Transferencia</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Seguro Médico">Seguro Médico</option>
                            </select>
                            <input type="number" class="form-control payment-amount" placeholder="Monto" step="0.01" min="0">
                        </div>
                    `;
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al procesar la venta');
            }
        });
    </script>
</body>
</html>