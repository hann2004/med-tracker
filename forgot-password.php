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
                    <h1>Forgot Password</h1>
                    <p>Enter your email or username to receive a password reset link.</p>
                </div>

                <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div><?php endif; ?>

                <form method="POST" class="form-stack">
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email or Username</label>
                        <input type="text" name="email" id="email" placeholder="Enter your registered identifier" required>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Send Reset Link</button>
                    
                    <div class="supporting">
                        Remembered your password? <a href="login.php" style="color: var(--clinical-accent); font-weight: 700;">Sign in</a>
                    </div>
                </form>
            </div>

            <div class="auth-visual">
                <div>
                    <h3 style="margin-bottom: var(--space-md);">Security First</h3>
                    <p style="color: var(--clinical-text-light); font-size: 0.95rem;">
                        Protecting your medical data is our priority. If you've forgotten your password, 
                        we'll send a secure, one-time link to your registered email address.
                    </p>
                    <div class="benefits" style="margin-top: 2rem;">
                        <span class="pill"><i class="fas fa-shield-alt"></i> Secure Reset</span>
                        <span class="pill"><i class="fas fa-clock"></i> 1-Hour Expiry</span>
                        <span class="pill"><i class="fas fa-user-check"></i> Verified Identity</span>
                    </div>
                </div>
                <div style="margin-top: var(--space-2xl); color: var(--clinical-text-light); font-size: 0.9rem; opacity: 0.8;">
                    Need help? Contact our system administrator or visit our Arba Minch support center.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
