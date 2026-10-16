<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: php/dashboard/index.php");
    exit;
}
$page_title = "CLINOVA · Iniciar Sesión";
include_once 'includes/header.php';
?>

<!-- Google Fonts - Poppins para un look moderno -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/theme.css">

<div class="login-wrapper">
    <div class="login-container">
        <!-- Logo -->
        <div class="logo-section">
            <img src="assets/img/CLINOVA.png" alt="CLINOVA Logo" class="logo">
            <h1 class="brand-title">CLINOVA</h1>
            <div class="brand-tagline">Sistema de Gestión Clínica</div>
        </div>

        <!-- Login Card con Glassmorphism -->
        <div class="glass-card">
            <h2 class="welcome-text">Bienvenido</h2>
            <p class="welcome-sub">Ingresa tus credenciales para continuar</p>
            
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
            © <?php echo date('Y'); ?> CLINOVA · Sistema de Gestión Clínica
        </div>
    </div>
</div>

<style>
/* ============================================
   CLINOVA · Login screen
   On-brand dark theme matching the logo.
   ============================================ */

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: var(--font-family);
    min-height: 100vh;
    background: var(--dark-bg);
    color: var(--dark-text);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow-x: hidden;
    position: relative;
}

/* Animated neon radial backdrop */
body::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
        radial-gradient(900px 700px at 12% 18%, rgba(31, 168, 255, 0.22), transparent 60%),
        radial-gradient(900px 700px at 88% 82%, rgba(255, 61, 0, 0.22), transparent 60%),
        radial-gradient(600px 600px at 50% 50%, rgba(31, 168, 255, 0.06), transparent 70%);
    animation: neonDrift 18s ease-in-out infinite alternate;
    z-index: 0;
    pointer-events: none;
}

/* Subtle grid overlay */
body::after {
    content: '';
    position: fixed;
    inset: 0;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
    background-size: 48px 48px;
    z-index: 0;
    pointer-events: none;
    mask-image: radial-gradient(ellipse at center, black 30%, transparent 75%);
    -webkit-mask-image: radial-gradient(ellipse at center, black 30%, transparent 75%);
}

@keyframes neonDrift {
    0%   { background-position: 0% 0%, 100% 100%, 50% 50%; }
    50%  { background-position: 30% 20%, 70% 80%, 60% 40%; }
    100% { background-position: 0% 0%, 100% 100%, 50% 50%; }
}

/* Layout */
.login-wrapper {
    position: relative;
    z-index: 1;
    width: 100%;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-8) var(--space-4);
}

.login-container {
    width: 100%;
    max-width: 440px;
    animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Logo section */
.logo-section {
    text-align: center;
    margin-bottom: var(--space-8);
    animation: fadeIn 1s ease-out;
}

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.logo {
    width: 320px;
    height: auto;
    max-width: 100%;
    margin-bottom: var(--space-4);
    /* Neon drop-shadow on the logo */
    filter:
        drop-shadow(0 0 24px rgba(31, 168, 255, 0.35))
        drop-shadow(0 0 48px rgba(255, 61, 0, 0.25));
    animation: floatLogo 4s ease-in-out infinite;
}

@keyframes floatLogo {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-8px); }
}

.brand-title {
    font-size: 1.5rem;
    font-weight: 700;
    letter-spacing: 4px;
    color: transparent;
    background: linear-gradient(90deg, var(--color-blue-light), var(--color-orange-light));
    -webkit-background-clip: text;
    background-clip: text;
    text-transform: uppercase;
}

.brand-tagline {
    font-size: 0.75rem;
    color: var(--dark-text-muted);
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-top: var(--space-2);
}

/* Glass card */
.glass-card {
    background: rgba(17, 20, 24, 0.65);
    backdrop-filter: blur(24px) saturate(160%);
    -webkit-backdrop-filter: blur(24px) saturate(160%);
    border-radius: var(--radius-xl);
    padding: var(--space-10) var(--space-6);
    border: 1px solid var(--glass-border);
    box-shadow: var(--shadow-xl);
    animation: scaleIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.15s both;
    position: relative;
    overflow: hidden;
}

.glass-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--color-blue), var(--color-orange), transparent);
    opacity: 0.7;
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.95); }
    to   { opacity: 1; transform: scale(1); }
}

.welcome-text {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--dark-text);
    margin-bottom: var(--space-2);
    text-align: center;
}

.welcome-sub {
    font-size: 0.875rem;
    color: var(--dark-text-muted);
    text-align: center;
    margin-bottom: var(--space-8);
}

/* Inputs */
.input-wrapper {
    position: relative;
    margin-bottom: var(--space-6);
}

.modern-input {
    width: 100%;
    padding: var(--space-4) 0 var(--space-2);
    font-size: 1rem;
    color: var(--dark-text);
    background: transparent;
    border: none;
    border-bottom: 2px solid rgba(255, 255, 255, 0.12);
    outline: none;
    font-family: var(--font-family);
    transition: border-color var(--transition-base);
}

.modern-input::placeholder { color: transparent; }

.modern-input:-webkit-autofill,
.modern-input:-webkit-autofill:hover,
.modern-input:-webkit-autofill:focus {
    -webkit-text-fill-color: var(--dark-text);
    -webkit-box-shadow: 0 0 0px 1000px transparent inset;
    transition: background-color 5000s ease-in-out 0s;
}

.floating-label {
    position: absolute;
    left: 0;
    top: var(--space-4);
    font-size: 1rem;
    color: var(--dark-text-muted);
    pointer-events: none;
    transition: all var(--transition-base);
    font-weight: 500;
}

.modern-input:focus ~ .floating-label,
.modern-input:not(:placeholder-shown) ~ .floating-label {
    top: -0.5rem;
    font-size: 0.75rem;
    color: var(--color-blue-light);
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.input-line {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 2px;
    background: linear-gradient(90deg, var(--color-blue), var(--color-orange));
    transition: width var(--transition-slow);
    box-shadow: 0 0 12px rgba(31, 168, 255, 0.5);
}

.modern-input:focus ~ .input-line { width: 100%; }

/* Error message */
.error-message {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-4);
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: var(--radius-md);
    color: #FCA5A5;
    font-size: 0.875rem;
    margin-bottom: var(--space-6);
    animation: shakeX 0.5s ease;
}

@keyframes shakeX {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
    20%, 40%, 60%, 80% { transform: translateX(5px); }
}

.error-icon { width: 20px; height: 20px; flex-shrink: 0; }

/* Login button */
.login-button {
    width: 100%;
    padding: var(--space-4);
    margin-top: var(--space-2);
    font-size: 1rem;
    font-weight: 600;
    color: var(--color-white);
    background: linear-gradient(135deg, var(--color-orange), var(--color-orange-dark));
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    box-shadow: 0 8px 24px rgba(255, 61, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.15);
    transition: transform var(--transition-base), box-shadow var(--transition-base);
    font-family: var(--font-family);
    position: relative;
    overflow: hidden;
    letter-spacing: 0.3px;
}

.login-button::before {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.login-button:hover::before { left: 100%; }

.login-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(255, 61, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.login-button:active { transform: translateY(0); }

.button-icon {
    width: 20px;
    height: 20px;
    transition: transform var(--transition-base);
}

.login-button:hover .button-icon { transform: translateX(4px); }

/* Footer */
.footer-text {
    text-align: center;
    margin-top: var(--space-8);
    color: var(--dark-text-muted);
    font-size: 0.8125rem;
    font-weight: 400;
    letter-spacing: 0.5px;
}

/* Loading state */
.login-button.loading { pointer-events: none; opacity: 0.8; }

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

@keyframes spin { to { transform: rotate(360deg); } }

/* Responsive */
@media (max-width: 480px) {
    .login-container { max-width: 100%; }
    .glass-card { padding: var(--space-8) var(--space-5); border-radius: var(--radius-lg); }
    .brand-title { font-size: 1.25rem; }
    .welcome-text { font-size: 1.25rem; }
    .logo { width: 260px; }
}

@media (max-width: 360px) {
    .glass-card { padding: var(--space-6) var(--space-4); }
    .brand-title { font-size: 1.1rem; letter-spacing: 3px; }
    .logo { width: 220px; }
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