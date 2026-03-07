<?php
/**
 * Bansari Homeopathy – Admin Login
 * AJAX-powered with structured error handling
 */

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../backend-php/logs/php_errors.log');

// Custom error handler to catch PHP errors during login
function loginErrorHandler($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];
    $type = $errorTypes[$errno] ?? 'Unknown Error';
    error_log("Login PHP Error [$type]: $errstr in $errfile on line $errline");
    return true;
}

// Set custom error handler
set_error_handler('loginErrorHandler');

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal Login Error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        // If this was an AJAX request, try to return JSON error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'type' => 'server', 'message' => 'Server configuration error. Please contact administrator.']);
        }
    }
});

// Load secure session configuration
require_once __DIR__ . '/../backend-php/security/session_config.php';
session_start();

if (isset($_SESSION['clinic_admin']['id'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';

// ─── AJAX Login Handler ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');

    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Validate empty fields
    if (empty($email) && empty($password)) {
        echo json_encode(['success' => false, 'type' => 'validation', 'message' => 'Please enter both email and password.']);
        exit;
    }
    if (empty($email)) {
        echo json_encode(['success' => false, 'type' => 'validation', 'field' => 'email', 'message' => 'Please enter your email address.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'type' => 'validation', 'field' => 'email', 'message' => 'Please enter a valid email address.']);
        exit;
    }
    if (empty($password)) {
        echo json_encode(['success' => false, 'type' => 'validation', 'field' => 'password', 'message' => 'Please enter your password.']);
        exit;
    }

    try {
        $db = getClinicDB();
        $stmt = $db->prepare("SELECT id, name, email, password, role FROM admins WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if (!$admin) {
            echo json_encode(['success' => false, 'type' => 'credentials', 'message' => 'No account found with this email address.']);
            exit;
        }

        if (!password_verify($password, $admin['password'])) {
            error_log(date('[Y-m-d H:i:s]') . " LOGIN FAIL | email={$email}");
            
            // Track failed login attempt
            $db->prepare("INSERT INTO login_attempts (email, ip_address, user_agent, success) VALUES (?, ?, ?, 0)")
               ->execute([$email, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
            
            // Check for brute force (10 failed attempts in 15 minutes)
            $stmt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $stmt->execute([$email]);
            $recentFails = (int)$stmt->fetchColumn();
            
            if ($recentFails >= 10) {
                echo json_encode(['success' => false, 'type' => 'lockout', 'message' => 'Too many failed attempts. Please try again after 15 minutes.']);
                exit;
            }

            echo json_encode(['success' => false, 'type' => 'credentials', 'message' => 'Incorrect password. Please try again.']);
            exit;
        }

        // Authentication successful – track successful login
        $db->prepare("INSERT INTO login_attempts (email, ip_address, user_agent, success) VALUES (?, ?, ?, 1)")
           ->execute([$email, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);

        $_SESSION['clinic_admin'] = [
            'id'    => (int)$admin['id'],
            'name'  => $admin['name'],
            'email' => $admin['email'],
            'role'  => $admin['role'],
        ];
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $db->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?")->execute([$admin['id']]);
        session_regenerate_id(true);

        echo json_encode(['success' => true, 'message' => 'Login successful! Redirecting...', 'redirect' => 'dashboard.php']);
        exit;

    } catch (PDOException $e) {
        error_log('Login DB error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'type' => 'server', 'message' => 'A server error occurred. Please try again later.']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login – Bansari Homeopathy</title>
    <script>
        (function(){
            try { var t = localStorage.getItem('admin-theme');
            if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme:dark)').matches))
                document.documentElement.setAttribute('data-theme','dark');
            } catch(e){}
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Login error animation */
        .login-error {
            opacity: 0;
            max-height: 0;
            overflow: hidden;
            transition: opacity 0.35s ease, max-height 0.35s ease, margin 0.35s ease, padding 0.35s ease;
            margin: 0;
            padding: 0 0.75rem;
            font-size: 0.825rem;
            border-radius: 8px;
        }
        .login-error.show {
            opacity: 1;
            max-height: 60px;
            margin-top: 0.25rem;
            padding: 0.5rem 0.75rem;
        }
        .login-error.error-global {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.25);
        }
        .login-error i { margin-right: 0.35rem; }
        .field-error { border-color: #ef4444 !important; box-shadow: 0 0 0 0.2rem rgba(239, 68, 68, 0.15) !important; }
        .login-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: loginSpin 0.6s linear infinite;
            margin-right: 0.5rem;
            vertical-align: middle;
        }
        @keyframes loginSpin { to { transform: rotate(360deg); } }
        .btn-success:disabled { opacity: 0.7; cursor: not-allowed; }
        .login-success { color: #10b981; }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="brand-icon mx-auto mb-3" style="width:50px;height:50px;font-size:1.5rem;">B</div>
            <h2>Bansari Homeopathy</h2>
            <p class="text-muted">Admin Dashboard Login</p>
        </div>

        <?php if (isset($_GET['timeout'])): ?>
        <div class="alert alert-warning py-2 small">
            <i class="bi bi-clock-history"></i> Session expired. Please log in again.
        </div>
        <?php endif; ?>

        <form id="loginForm" autocomplete="off" novalidate>
            <div class="mb-3">
                <label class="form-label fw-semibold">Email Address</label>
                <input type="email" id="loginEmail" name="email" class="form-control form-control-lg" 
                       required autofocus placeholder="admin@bansari.com">
                <div class="login-error text-danger" id="emailError"></div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Password</label>
                <div class="position-relative">
                    <input type="password" id="loginPassword" name="password" class="form-control form-control-lg" 
                           required placeholder="Enter password">
                    <button type="button" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-muted pe-3" 
                            id="togglePasswordVisibility" tabindex="-1" style="text-decoration:none;">
                        <i class="bi bi-eye" id="pwdToggleIcon"></i>
                    </button>
                </div>
                <div class="login-error text-danger" id="passwordError"></div>
            </div>

            <!-- Global error (credentials / server) -->
            <div class="login-error text-danger error-global" id="globalError"></div>

            <div class="mt-4">
                <button type="submit" id="loginBtn" class="btn btn-success btn-lg w-100 fw-semibold">
                    Sign In
                </button>
            </div>
        </form>

        <div class="text-center mt-4">
            <small class="text-muted">Default: admin@bansari.com / Admin@123</small>
        </div>
    </div>
</div>

<script>
(function() {
    const form       = document.getElementById('loginForm');
    const emailInput = document.getElementById('loginEmail');
    const passInput  = document.getElementById('loginPassword');
    const loginBtn   = document.getElementById('loginBtn');
    const emailErr   = document.getElementById('emailError');
    const passErr    = document.getElementById('passwordError');
    const globalErr  = document.getElementById('globalError');
    const pwdToggle  = document.getElementById('togglePasswordVisibility');
    const pwdIcon    = document.getElementById('pwdToggleIcon');

    const btnDefaultText = 'Sign In';

    // Toggle password visibility
    pwdToggle.addEventListener('click', () => {
        const isPassword = passInput.type === 'password';
        passInput.type = isPassword ? 'text' : 'password';
        pwdIcon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    // Clear errors on input
    emailInput.addEventListener('input', () => {
        hideError(emailErr);
        emailInput.classList.remove('field-error');
        hideError(globalErr);
    });
    passInput.addEventListener('input', () => {
        hideError(passErr);
        passInput.classList.remove('field-error');
        hideError(globalErr);
    });

    function showError(el, msg) {
        el.innerHTML = '<i class="bi bi-exclamation-circle"></i>' + msg;
        el.classList.add('show');
    }
    function hideError(el) {
        el.classList.remove('show');
    }
    function setLoading(loading) {
        loginBtn.disabled = loading;
        if (loading) {
            loginBtn.innerHTML = '<span class="login-spinner"></span>Authenticating...';
        } else {
            loginBtn.textContent = btnDefaultText;
        }
    }

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Clear previous errors
        hideError(emailErr); hideError(passErr); hideError(globalErr);
        emailInput.classList.remove('field-error');
        passInput.classList.remove('field-error');

        const email    = emailInput.value.trim();
        const password = passInput.value;

        // Client-side validation
        if (!email && !password) {
            showError(globalErr, 'Please enter both email and password.');
            emailInput.classList.add('field-error');
            passInput.classList.add('field-error');
            emailInput.focus();
            return;
        }
        if (!email) {
            showError(emailErr, 'Please enter your email address.');
            emailInput.classList.add('field-error');
            emailInput.focus();
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showError(emailErr, 'Please enter a valid email address.');
            emailInput.classList.add('field-error');
            emailInput.focus();
            return;
        }
        if (!password) {
            showError(passErr, 'Please enter your password.');
            passInput.classList.add('field-error');
            passInput.focus();
            return;
        }

        setLoading(true);

        try {
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);

            const response = await fetch('login.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });

            if (!response.ok) {
                throw new Error('Server returned ' + response.status);
            }

            const data = await response.json();

            if (data.success) {
                // Show success briefly then redirect
                loginBtn.innerHTML = '<i class="bi bi-check-circle login-success"></i> ' + data.message;
                loginBtn.classList.replace('btn-success', 'btn-outline-success');
                setTimeout(() => {
                    window.location.href = data.redirect || 'dashboard.php';
                }, 600);
            } else {
                // Show error based on type
                if (data.field === 'email') {
                    showError(emailErr, data.message);
                    emailInput.classList.add('field-error');
                    emailInput.focus();
                } else if (data.field === 'password') {
                    showError(passErr, data.message);
                    passInput.classList.add('field-error');
                    passInput.focus();
                } else {
                    showError(globalErr, data.message);
                    if (data.type === 'credentials') {
                        passInput.classList.add('field-error');
                        passInput.select();
                    }
                }
                setLoading(false);
            }
        } catch (err) {
            console.error('Login error:', err);
            // More specific error message based on error type
            let errorMessage = 'Network error. Please check your connection and try again.';
            
            if (err.name === 'TypeError' && err.message === 'Failed to fetch') {
                errorMessage = 'Unable to connect to server. Please ensure Apache/PHP is running.';
            } else if (err.message.includes('NetworkError') || err.message.includes('network failure')) {
                errorMessage = 'Network connection failed. Please check your internet connection.';
            } else if (err.status === 0 || err.status === 404) {
                errorMessage = 'Server not found. Please contact the administrator.';
            } else if (err.status === 500) {
                errorMessage = 'Server error. Please try again later.';
            }
            
            showError(globalErr, errorMessage);
            setLoading(false);
        }
    });
})();
</script>
</body>
</html>
