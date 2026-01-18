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
    <link rel="stylesheet" href="styles/main.css">
    <style>
        .auth-shell { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f8fafc; }
        .auth-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 16px rgba(0,0,0,0.08); padding: 2rem; max-width: 400px; width: 100%; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { font-weight: 600; margin-bottom: 0.5rem; display: block; }
        .form-group input { width: 100%; padding: 0.9rem 1rem; border-radius: 10px; border: 1px solid #e2e8f0; }
        .btn-primary { width: 100%; padding: 0.95rem 1rem; font-size: 1.05rem; background: #2563eb; color: #fff; border: none; border-radius: 10px; font-weight: 700; }
        .alert { margin-bottom: 1rem; padding: 0.85rem 1rem; border-radius: 10px; font-weight: 600; }
        .alert-error { background: #fee2e2; color: #b91c1c; }
        .alert-success { background: #d1fae5; color: #047857; }
        @media (max-width: 640px) { .auth-card { padding: 1rem; } }
    </style>
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <h2>Reset Password</h2>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <?php if (!$success && !$error): ?>
            <form method="POST">
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" name="password" id="password" required minlength="8">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required minlength="8">
                </div>
                <button type="submit" class="btn-primary">Reset Password</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
