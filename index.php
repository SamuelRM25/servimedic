<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: php/dashboard/index.php");
    exit;
}
$page_title = "Login - Clínica";
include_once 'includes/header.php';
?>

<!-- Google Fonts - Poppins para un look moderno -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<div class="login-wrapper">
    <div class="login-container">
        <!-- Logo -->
        <div class="logo-section">
            <img src="assets/img/Servimedic.png" alt="Servimedic Logo" class="logo">
            <h1 class="brand-title">Servimedic Familiar</h1>
        </div>

        <!-- Login Card con Glassmorphism -->
        <div class="glass-card">
            <h2 class="welcome-text">Bienvenido</h2>
            
            <form id="loginForm" action="php/auth/login.php" method="POST">
                <!-- Usuario Input -->
                <div class="input-wrapper">
                    <input type="text" id="usuario" name="usuario" class="modern-input" required autocomplete="username">
                    <label for="usuario" class="floating-label">Usuario</label>
                    <div class="input-line"></div>
                </div>

                <!-- Password Input -->
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="modern-input" required autocomplete="current-password">
                    <label for="password" class="floating-label">Contraseña</label>
                    <div class="input-line"></div>
                </div>

                <!-- Error Message -->
                <?php if(isset($_GET['error'])): ?>
                <div class="error-message">
                    <svg class="error-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                    </svg>
                    Credenciales inválidas
                </div>
                <?php endif; ?>

                <!-- Submit Button -->
                <button type="submit" class="login-button">
                    <span class="button-text">Iniciar Sesión</span>
                    <svg class="button-icon" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"/>
                    </svg>
                </button>
            </form>
        </div>

        <!-- Footer -->
        <div class="footer-text">
            © <?php echo date('Y'); ?> RS SOLUTION
        </div>
    </div>
</div>

<style>
/* Reset y Variables */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    /* Colores Principales - Naranja y Celeste */
    --color-orange: #FF6B35;
    --color-orange-light: #FF8C61;
    --color-orange-dark: #E85A2B;
    --color-blue: #4FC3F7;
    --color-blue-light: #81D4FA;
    --color-blue-dark: #0288D1;
    
    /* Colores de Soporte */
    --color-white: #FFFFFF;
    --color-text-light: rgba(255, 255, 255, 0.95);
    --color-text-muted: rgba(255, 255, 255, 0.7);
    --color-error: #FF5252;
    
    /* Glassmorphism */
    --glass-bg: rgba(255, 255, 255, 0.25);
    --glass-border: rgba(255, 255, 255, 0.3);
    
    /* Sombras */
    --shadow-sm: 0 2px 10px rgba(0, 0, 0, 0.1);
    --shadow-md: 0 8px 32px rgba(0, 0, 0, 0.15);
    --shadow-lg: 0 15px 50px rgba(0, 0, 0, 0.2);
    
    /* Tipografía */
    --font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

/* Body - Gradiente Animado Naranja a Celeste */
body {
    font-family: var(--font-family);
    min-height: 100vh;
    background: linear-gradient(-45deg, var(--color-orange), var(--color-orange-light), var(--color-blue), var(--color-blue-light));
    background-size: 400% 400%;
    animation: gradientShift 15s ease infinite;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow-x: hidden;
}

/* Animación del Gradiente de Fondo */
@keyframes gradientShift {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

/* Wrapper Principal */
.login-wrapper {
    width: 100%;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
}

/* Container del Login */
.login-container {
    width: 100%;
    max-width: 420px;
    animation: fadeInUp 0.8s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Logo Section */
.logo-section {
    text-align: center;
    margin-bottom: 2.5rem;
    animation: fadeIn 1s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.logo {
    width: 300px;
    height: auto;
    margin-bottom: 1rem;
    /* Sombra que sigue la forma del logo */
    filter: drop-shadow(0 8px 24px hsla(0, 0%, 100%, 0.35)) 
            drop-shadow(0 4px 12px hsla(0, 0%, 100%, 0.25))
            drop-shadow(0 2px 6px hsla(0, 0%, 100%, 0.2));
    animation: floatLogo 3s ease-in-out infinite;
    max-width: 200%;
}

@keyframes floatLogo {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-10px);
    }
}

.brand-title {
    font-size: 1.75rem;
    font-weight: 600;
    color: var(--color-white);
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    letter-spacing: -0.5px;
}

/* Glass Card - Efecto Glassmorphism */
.glass-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 2.5rem 2rem;
    border: 1px solid var(--glass-border);
    box-shadow: var(--shadow-lg);
    animation: scaleIn 0.6s ease-out 0.2s both;
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.welcome-text {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--color-white);
    margin-bottom: 2rem;
    text-align: center;
}

/* Input Wrapper - Material Design Style */
.input-wrapper {
    position: relative;
    margin-bottom: 2rem;
}

.modern-input {
    width: 100%;
    padding: 1rem 0 0.5rem;
    font-size: 1rem;
    color: var(--color-white);
    background: transparent;
    border: none;
    border-bottom: 2px solid rgba(255, 255, 255, 0.3);
    outline: none;
    font-family: var(--font-family);
    transition: all 0.3s ease;
}

.modern-input::placeholder {
    color: transparent;
}

.modern-input:-webkit-autofill,
.modern-input:-webkit-autofill:hover,
.modern-input:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--color-white);
    -webkit-box-shadow: 0 0 0px 1000px transparent inset;
    transition: background-color 5000s ease-in-out 0s;
}

/* Floating Label */
.floating-label {
    position: absolute;
    left: 0;
    top: 1rem;
    font-size: 1.125rem;  /* Más grande para mejor legibilidad */
    color: var(--color-white);  /* Blanco para mejor visibilidad */
    pointer-events: none;
    transition: all 0.3s ease;
    font-weight: 500;  /* Semi-bold para más claridad */
}

.modern-input:focus ~ .floating-label,
.modern-input:not(:placeholder-shown) ~ .floating-label {
    top: -0.5rem;
    font-size: 0.875rem;  /* Tamaño cuando está flotando arriba */
    color: var(--color-white);  /* Blanco cuando está activo */
    font-weight: 600;  /* Bold cuando está activo */
}

/* Línea animada debajo del input */
.input-line {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--color-orange), var(--color-blue));
    transition: width 0.4s ease;
}

.modern-input:focus ~ .input-line {
    width: 100%;
}

/* Error Message */
.error-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: rgba(255, 82, 82, 0.15);
    border: 1px solid rgba(255, 82, 82, 0.3);
    border-radius: 12px;
    color: var(--color-white);
    font-size: 0.875rem;
    margin-bottom: 1.5rem;
    animation: shakeX 0.5s ease;
}

@keyframes shakeX {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}

.error-icon {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}

/* Login Button - Gradiente Naranja */
.login-button {
    width: 100%;
    padding: 1rem;
    margin-top: 1rem;
    font-size: 1rem;
    font-weight: 600;
    color: var(--color-white);
    background: linear-gradient(135deg, var(--color-orange), var(--color-orange-dark));
    border: none;
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(255, 107, 53, 0.4);
    transition: all 0.3s ease;
    font-family: var(--font-family);
    position: relative;
    overflow: hidden;
}

.login-button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.login-button:hover::before {
    left: 100%;
}

.login-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 25px rgba(255, 107, 53, 0.5);
}

.login-button:active {
    transform: translateY(0);
}

.button-icon {
    width: 20px;
    height: 20px;
    transition: transform 0.3s ease;
}

.login-button:hover .button-icon {
    transform: translateX(4px);
}

/* Footer Text */
.footer-text {
    text-align: center;
    margin-top: 2rem;
    color: var(--color-text-muted);
    font-size: 0.875rem;
    font-weight: 300;
}

/* Loading State */
.login-button.loading {
    pointer-events: none;
    opacity: 0.7;
}

.login-button.loading .button-text::after {
    content: '';
    display: inline-block;
    width: 12px;
    height: 12px;
    margin-left: 8px;
    border: 2px solid var(--color-white);
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 480px) {
    .login-container {
        max-width: 100%;
    }
    
    .glass-card {
        padding: 2rem 1.5rem;
        border-radius: 20px;
    }
    
    .brand-title {
        font-size: 1.5rem;
    }
    
    .welcome-text {
        font-size: 1.25rem;
    }
    
    .logo {
        width: 250px;
    }
}

@media (max-width: 360px) {
    .glass-card {
        padding: 1.5rem 1.25rem;
    }
    
    .brand-title {
        font-size: 1.25rem;
    }
    
    .logo {
        width: 250px;
    }
}

/* Prefer Reduced Motion */
@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginButton = loginForm.querySelector('.login-button');
    const inputs = document.querySelectorAll('.modern-input');
    
    // Animación al enviar el formulario
    loginForm.addEventListener('submit', function(e) {
        loginButton.classList.add('loading');
        const buttonText = loginButton.querySelector('.button-text');
        buttonText.textContent = 'Iniciando';
    });
    
    // Mejorar experiencia de autofill
    inputs.forEach(input => {
        // Detectar autofill
        input.addEventListener('animationstart', function(e) {
            if (e.animationName === 'onAutoFillStart') {
                input.classList.add('autofilled');
            }
        });
        
        // Limpiar estado al cambiar
        input.addEventListener('input', function() {
            input.classList.remove('autofilled');
        });
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>