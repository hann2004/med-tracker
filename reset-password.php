<?php
require_once 'config/database.php';
$conn = getDatabaseConnection();
$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (!$token) {
    $error = 'Invalid or missing token.';
} else {
    $stmt = $conn->prepare("SELECT user_id, password_reset_expires FROM users WHERE password_reset_token = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user || strtotime($user['password_reset_expires']) < time()) {
        $error = 'This reset link is invalid or expired.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newPassword = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!$newPassword || strlen($newPassword) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($newPassword !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE user_id = ?");
            $stmt->bind_param('si', $hash, $user['user_id']);
            $stmt->execute();
            $stmt->close();
            $success = 'Your password has been reset. <a href=\'login.php\'>Sign in</a>.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | MedTrack</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles/main.css">
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
                    <h1>Reset Password</h1>
                    <p>Create a strong, secure password for your MedTrack account.</p>
                </div>

                <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>

                <?php if (!$success && !$error): ?>
                <form method="POST" class="form-stack">
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" name="password" id="password" placeholder="Min. 8 characters" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder="Verify your new password" required minlength="8">
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-key"></i> Update Password</button>
                    
                    <div class="supporting">
                        Back to <a href="login.php" style="color: var(--clinical-accent); font-weight: 700;">Sign in</a>
                    </div>
                </form>
                <?php endif; ?>
            </div>

            <div class="auth-visual">
                <div>
                    <h3 style="margin-bottom: var(--space-md);">Security Check</h3>
                    <p style="color: var(--clinical-text-light); font-size: 0.95rem;">
                        A strong password helps keep your pharmacy data and history secure. 
                        Make sure your new password is unique.
                    </p>
                    <div class="benefits" style="margin-top: 2rem;">
                        <span class="pill"><i class="fas fa-shield-virus"></i> High Security</span>
                        <span class="pill"><i class="fas fa-fingerprint"></i> Data Encrypted</span>
                        <span class="pill"><i class="fas fa-user-lock"></i> Account Protected</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
