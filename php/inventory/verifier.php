<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificador de Precios - Servimedic</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #f0f2f5; height: 100vh; display: flex; flex-direction: column; overflow: hidden; }
        
        .header {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            z-index: 10;
        }
        
        .back-btn {
            text-decoration: none;
            color: #4a5568;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .back-btn:hover { background: #e2e8f0; color: #2d3748; }
        
        .main-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
        }
        
        .scanner-section {
            width: 100%;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .scanner-input {
            width: 100%;
            max-width: 600px;
            padding: 20px 25px;
            font-size: 1.2rem;
            border: 3px solid #e2e8f0;
            border-radius: 50px;
            text-align: center;
            transition: all 0.3s;
            outline: none;
            background: white url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="%23cbd5e0" viewBox="0 0 16 16"><path d="M1.5 1a.5.5 0 0 0-.5.5v3a.5.5 0 0 1-1 0v-3A1.5 1.5 0 0 1 1.5 0h3a.5.5 0 0 1 0 1h-3zM11 .5a.5.5 0 0 1 .5-.5h3A1.5 1.5 0 0 1 16 1.5v3a.5.5 0 0 1-1 0v-3a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 1-.5-.5zM.5 11a.5.5 0 0 1 .5.5v3a.5.5 0 0 0 .5.5h3a.5.5 0 0 1 0 1h-3A1.5 1.5 0 0 1 0 14.5v-3a.5.5 0 0 1 .5-.5zm15 0a.5.5 0 0 1 .5.5v3a1.5 1.5 0 0 1-1.5 1.5h-3a.5.5 0 0 1 0-1h3a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 1 .5-.5z"/></svg>') no-repeat 20px center;
        }
        
        .scanner-input:focus {
            border-color: #4299e1;
            box-shadow: 0 0 0 5px rgba(66, 153, 225, 0.2);
        }
        
        .result-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            padding: 40px;
            text-align: center;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.4s ease;
            display: none;
        }
        
        .result-card.active {
            opacity: 1;
            transform: translateY(0);
            display: block;
        }
        
        .product-code {
            display: inline-block;
            background: #edf2f7;
            padding: 5px 15px;
            border-radius: 20px;
            color: #718096;
            font-size: 0.9rem;
            margin-bottom: 20px;
            font-family: monospace;
        }
        
        .product-name {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 10px;
            line-height: 1.2;
        }
        
        .product-meta {
            font-size: 1.1rem;
            color: #718096;
            margin-bottom: 30px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 30px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        
        .info-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #a0aec0;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4a5568;
        }
        
        .info-value.price { color: #48bb78; font-size: 1.8rem; }
        .info-value.stock { color: #4299e1; }
        
        .alert-box {
            padding: 20px;
            border-radius: 12px;
            color: white;
            margin-top: 20px;
            display: none;
            width: 100%;
            text-align: center;
            font-weight: 500;
        }
        
        .alert-error {
            background: #f56565;
            box-shadow: 0 5px 15px rgba(245, 101, 101, 0.3);
        }
        
        .pulse-animation {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(66, 153, 225, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(66, 153, 225, 0); }
            100% { box-shadow: 0 0 0 0 rgba(66, 153, 225, 0); }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="index.php" class="back-btn">
            <i class="bi bi-arrow-left"></i> Volver al Inventario
        </a>
        <div style="font-weight: 600; color: #2d3748;">
            <i class="bi bi-qr-code-scan me-2"></i>Verificador de Productos
        </div>
    </div>
    
    <div class="main-container">
        <div class="scanner-section">
            <h3 style="margin-bottom: 20px; color: #4a5568;">Escanea el código de barras</h3>
            <input type="text" id="barcodeInput" class="scanner-input pulse-animation" placeholder="Esperando lectura..." autofocus>
            <div style="margin-top: 15px; color: #a0aec0; font-size: 0.9rem;">
                El cursor debe estar en la caja de texto para escanear
            </div>
        </div>
        
        <div id="alertBox" class="alert-box alert-error"></div>
        
        <div id="resultCard" class="result-card">
            <span id="resCode" class="product-code">740123456789</span>
            <h1 id="resName" class="product-name">Nombre del Medicamento</h1>
            <div id="resMeta" class="product-meta">500mg • Tabletas • Pfizer</div>
            
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Precio Venta</span>
                    <span id="resPrice" class="info-value price">Q150.00</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Existencia</span>
                    <span id="resStock" class="info-value stock">45</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Vencimiento</span>
                    <span id="resDate" class="info-value">20/12/2025</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        const input = document.getElementById('barcodeInput');
        const alertBox = document.getElementById('alertBox');
        const resultCard = document.getElementById('resultCard');
        
        // Sound effects
        const beep = new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU'); // Placeholder or real b64
        
        input.addEventListener('keydown', async (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = input.value.trim();
                if(!code) return;
                
                input.disabled = true;
                alertBox.style.display = 'none';
                resultCard.classList.remove('active');
                
                try {
                    // Reuse the dispensary search logic
                    const res = await fetch(`../dispensary/search_by_barcode.php?code=${encodeURIComponent(code)}`);
                    const data = await res.json();
                    
                    if(data.success && data.product) {
                        const p = data.product;
                        
                        // Populate Data
                        document.getElementById('resCode').textContent = p.codigo_barras || code;
                        document.getElementById('resName').textContent = p.nom_medicamento;
                        document.getElementById('resMeta').textContent = `${p.mol_medicamento} • ${p.presentacion_med} • ${p.casa_farmaceutica}`;
                        
                        document.getElementById('resPrice').textContent = `Q${parseFloat(p.precio_venta_sugerido).toFixed(2)}`;
                        document.getElementById('resStock').textContent = p.cantidad_med;
                        
                        const date = new Date(p.fecha_vencimiento);
                        document.getElementById('resDate').textContent = date.toLocaleDateString('es-GT');
                        
                        // Expired check
                        const today = new Date();
                        if(date < today) {
                            document.getElementById('resDate').style.color = '#e53e3e';
                            document.getElementById('resDate').innerHTML += ' <br><span style="font-size:0.7rem; color:#e53e3e">(VENCIDO)</span>';
                        } else {
                            document.getElementById('resDate').style.color = '#4a5568';
                        }
                        
                        resultCard.classList.add('active');
                        // beep.play();
                    } else {
                        showError('Producto no encontrado');
                    }
                } catch (err) {
                    console.error(err);
                    showError('Error al consultar el producto');
                }
                
                input.value = '';
                input.disabled = false;
                input.focus();
            }
        });
        
        function showError(msg) {
            alertBox.textContent = msg;
            alertBox.style.display = 'block';
            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 3000);
        }
        
        // Keep focus
        document.addEventListener('click', () => {
            input.focus();
        });
    </script>
</body>
</html>
