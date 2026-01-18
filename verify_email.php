<?php
$mysqli_report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;
mysqli_report($mysqli_report_mode);
require_once 'config/database.php';
$conn = getDatabaseConnection();
$token = $_GET['token'] ?? '';
$success = '';
$error = '';
if ($token) {
    $stmt = $conn->prepare("SELECT user_id, is_verified FROM users WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['is_verified']) {
            $success = 'Your email is already verified. You can log in.';
        } else {
            $update = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE user_id = ?");
            $update->bind_param("i", $row['user_id']);
            $update->execute();
            $success = 'Your email has been verified! You can now log in.';
        }
    } else {
        $error = 'Invalid or expired verification link.';
    }
} else {
    $error = 'No verification token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification</title>
    <link rel="stylesheet" href="styles/main.css">
    <style>body{display:flex;align-items:center;justify-content:center;height:100vh;background:#f7fbff;font-family:Inter,sans-serif;} .verify-box{background:#fff;padding:2.5rem 2rem;border-radius:18px;box-shadow:0 8px 32px rgba(16,37,66,0.13);max-width:400px;text-align:center;} .verify-box h2{color:#2563eb;} .verify-box .success{color:#10b981;font-weight:600;} .verify-box .error{color:#ef4444;font-weight:600;}</style>
</head>
<body>
    <div class="verify-box">
        <h2>Email Verification</h2>
        <?php if ($success): ?>
            <div class="success"><?php echo $success; ?></div>
            <a href="login.php" style="display:inline-block;margin-top:18px;color:#2563eb;font-weight:600;">Go to Login</a>
        <?php else: ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
