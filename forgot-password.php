<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$success = '';
$error = '';
require_once 'config/database.php';
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
$conn = getDatabaseConnection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['email'] ?? '');
    if (!$identifier) {
        $error = 'Please enter your email or username.';
    } else {
        $stmt = $conn->prepare("SELECT user_id, email FROM users WHERE email = ? OR username = ? LIMIT 1");
        $stmt->bind_param('ss', $identifier, $identifier);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
            $stmt = $conn->prepare("UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE user_id = ?");
            $stmt->bind_param('ssi', $token, $expires, $user['user_id']);
            $stmt->execute();
            $stmt->close();
            $resetLink = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/reset-password.php?token=' . $token;
            // Send email using PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'medicine.trackerarbaminch@gmail.com'; // <-- CHANGE THIS to your Gmail address
                $mail->Password = 'xukw hgxz odxb mgmh'; // <-- App Password
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                $mail->setFrom('medicine.trackerarbaminch@gmail.com', 'MedTracker'); // <-- CHANGE THIS
                $mail->addAddress($user['email']);
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request';
                $mail->Body = "Hello,<br><br>We received a request to reset your password. Click <a href='$resetLink'>here</a> to reset your password.<br><br>If you did not request this, please ignore this email.";
                $mail->send();
                $success = 'Password reset email sent! Check your inbox.';
            } catch (Exception $e) {
                $error = 'Email could not be sent. Mailer Error: ' . $mail->ErrorInfo;
            }
        } else {
            $error = 'No user found with that email or username.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | MedTrack</title>
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
            <h2>Forgot Password</h2>
            <p>Enter your email or username to receive a password reset link.</p>
            <?php if ($error): ?><div class="alert alert-error"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="email">Email or Username</label>
                    <input type="text" name="email" id="email" required>
                </div>
                <button type="submit" class="btn-primary">Send Reset Link</button>
            </form>
            <div style="margin-top:1.5rem; text-align:center;">
                <a href="login.php" style="color:#2563eb; font-weight:600;">Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
