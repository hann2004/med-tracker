<?php
session_start();
require_once 'config/database.php';
require_once 'includes/auth_functions.php';

$conn = getDatabaseConnection();
$error = '';

// Determine base path for safe redirects
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}

// Respect a `next` param by storing it as a post-login redirect (internal paths only)
if (!isset($_SESSION['user_id']) && isset($_GET['next'])) {
    $next = $_GET['next'];
    // Allow only internal redirects
    if (strpos($next, 'http://') === 0 || strpos($next, 'https://') === 0) {
        $next = 'index.php';
    }
    // Normalize to absolute app path
    if ($next && $next[0] !== '/') {
        $next = ($basePath ? $basePath . '/' : '/') . ltrim($next, '/');
    }
    $_SESSION['redirect_after_login'] = $next;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$identifier || !$password) {
        $error = 'Please enter email/username and password';
    } else {
        $result = authLogin($conn, $identifier, $password);
        if ($result['success']) {
            $redirect = $_SESSION['redirect_after_login'] ?? authRedirectForRole($_SESSION['user_type'] ?? 'user');
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        }
        $error = $result['message'];
        $pendingIdentifier = $identifier;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | MedTrack Arba Minch</title>
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .auth-shell {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-2xl) var(--space-lg);
        }
        .auth-card {
            width: 100%;
            max-width: 1100px;
            background: var(--clinical-white);
            border: 1px solid var(--clinical-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            overflow: hidden;
        }
        .auth-form-pane { padding: var(--space-3xl); }
        .auth-visual {
            background: var(--gradient-medical);
            padding: var(--space-3xl);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-left: 1px solid var(--clinical-border);
        }
        .auth-header h1 { margin: 0 0 var(--space-sm); }
        .auth-header p { margin: 0; color: var(--clinical-text-light); }
        .auth-logo { display: inline-flex; align-items: center; gap: var(--space-sm); font-weight: 800; color: var(--clinical-text); text-decoration: none; }
        .auth-logo-icon { width: 44px; height: 44px; border-radius: var(--radius-md); background: var(--gradient-accent); color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow-accent); }
        .form-stack { display: grid; gap: var(--space-lg); margin-top: var(--space-2xl); }
        .form-group { display: grid; gap: var(--space-xs); }
        .form-group label { font-weight: 600; color: var(--clinical-text); display: flex; align-items: center; gap: var(--space-xs); }
        .form-group input { border: 1px solid var(--clinical-border); border-radius: var(--radius-md); padding: 0.9rem 1rem; font-size: 1rem; background: var(--clinical-white); transition: var(--transition-fast); }
        .form-group input:focus { outline: none; border-color: var(--clinical-accent); box-shadow: 0 0 0 4px rgba(37,99,235,0.08); }
        .form-row { display: grid; gap: var(--space-lg); grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .actions { display: flex; justify-content: space-between; align-items: center; gap: var(--space-md); }
        .actions a { color: var(--clinical-accent); text-decoration: none; font-weight: 600; }
        .actions a:hover { text-decoration: underline; }
        .btn-primary { width: 100%; justify-content: center; padding: 0.95rem 1rem; font-size: 1.05rem; }
        .supporting { color: var(--clinical-text-light); font-size: 0.95rem; text-align: center; margin-top: var(--space-lg); }
        .pill-list { display: grid; gap: var(--space-sm); }
        .pill { display: inline-flex; align-items: center; gap: var(--space-sm); padding: 0.75rem 1rem; background: rgba(37,99,235,0.08); color: var(--clinical-text); border-radius: var(--radius-lg); border: 1px solid rgba(37,99,235,0.12); }
        .alert { padding: 0.85rem 1rem; border-radius: var(--radius-md); display: flex; align-items: center; gap: var(--space-sm); font-weight: 600; margin-top: var(--space-md); }
        .alert-error { background: rgba(239,68,68,0.08); color: #b91c1c; border: 1px solid rgba(239,68,68,0.25); }
        @media (max-width: 992px) { .auth-card { grid-template-columns: 1fr; } .auth-visual { display: none; } }
    </style>
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-form-pane">
                <a href="index.php" class="auth-logo">
                    <span class="auth-logo-icon"><i class="fas fa-capsules"></i></span>
                    <span>MedTrack Arba Minch</span>
                </a>
                <div class="auth-header" style="margin-top: var(--space-lg);">
                    <h1>Sign in</h1>
                    <p>Access real-time medicine availability</p>
                    <?php if (isset($_GET['next'])): ?>
                        <div class="alert" style="margin-top:12px; background: rgba(37,99,235,0.1); color:#1d4ed8; border:1px solid rgba(37,99,235,0.2);">
                            <i class="fas fa-lock"></i>
                            Please sign in to search medicines.
                        </div>
                    <?php endif; ?>
                    
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <form method="POST" action="">
                    <div class="form-stack">
                        <div class="form-group">
                            <label><i class="fas fa-envelope"></i> Email or Username</label>
                            <input type="text" name="email" placeholder="you@example.com or username" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Password</label>
                            <input type="password" name="password" placeholder="Enter your password" required>
                        </div>
                        <div class="actions">
                            <label style="display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" name="remember"> Remember me
                            </label>
                            <a href="forgot-password.php">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn-primary"><i class="fas fa-sign-in-alt"></i> Sign in</button>
                    </div>
                </form>
                <div class="supporting">
                    New here? <a href="register.php" style="color: var(--clinical-accent); font-weight: 700;">Create an account</a>
                </div>
            </div>
            <div class="auth-visual">
                <div>
                    <h2 style="margin-bottom: var(--space-md);">Why log in?</h2>
                    <p style="color: var(--clinical-text); margin-bottom: var(--space-xl);">Get live stock, RX requirements, and the nearest pharmacies in Arba Minch.</p>
                    <div class="pill-list">
                        <span class="pill"><i class="fas fa-check-circle"></i> Verified pharmacies</span>
                        <span class="pill"><i class="fas fa-check-circle"></i> Live availability</span>
                        <span class="pill"><i class="fas fa-check-circle"></i> Coverage in Secha, Sikela, Town Center</span>
                        <span class="pill"><i class="fas fa-check-circle"></i> Secure access</span>
                    </div>
                </div>
                <div style="margin-top: var(--space-2xl); color: var(--clinical-text-light); font-size: 0.95rem;">
                    Need an account for your pharmacy? <a href="register.php?type=pharmacy" style="color: var(--clinical-accent); font-weight: 700;">Register pharmacy</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = this.querySelector('input[name="email"]').value;
            const password = this.querySelector('input[name="password"]').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
            
            // Add loading animation
            const btn = this.querySelector('.login-btn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<div class="loading-spinner"></div>';
            btn.disabled = true;
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 2000);
        });
        
        // Add floating animation to form
        const form = document.querySelector('.login-form');
        setInterval(() => {
            form.style.transform = `translateY(${Math.sin(Date.now() / 1000) * 5}px)`;
        }, 50);
    </script>
</body>
</html>