<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'pharmacy') {
    header('Location: ../login.php');
    exit();
}

// If already verified, send to dashboard
if (!empty($_SESSION['is_verified'])) {
    header('Location: dashboard.php');
    exit();
}

$conn = getDatabaseConnection();
$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT pharmacy_name, license_number, is_verified, is_active FROM pharmacies WHERE owner_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$pharmacy = $stmt->get_result()->fetch_assoc();
$stmt->close();

$pharmacyName = $pharmacy['pharmacy_name'] ?? 'Your Pharmacy';
$license = $pharmacy['license_number'] ?? 'Pending';
$isActive = (bool)($pharmacy['is_active'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Pending | <?php echo htmlspecialchars($pharmacyName); ?></title>
    <link rel="stylesheet" href="../styles/pharmacy.css?v=20260111">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8fafc; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .pending-shell { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .pending-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; box-shadow: 0 12px 30px rgba(0,0,0,0.06); max-width: 640px; width: 100%; padding: 2rem; text-align: center; }
        .badge { display: inline-flex; align-items: center; gap: 8px; padding: 0.45rem 0.9rem; border-radius: 999px; font-weight: 700; background: rgba(234,179,8,0.14); color: #b45309; border: 1px solid rgba(234,179,8,0.3); margin-bottom: 1rem; }
        .actions { display: flex; justify-content: center; gap: 0.75rem; margin-top: 1.5rem; flex-wrap: wrap; }
        .btn { padding: 0.75rem 1.2rem; border-radius: 10px; text-decoration: none; font-weight: 700; border: 1px solid transparent; }
        .btn-primary { background: linear-gradient(135deg, #1d4ed8, #2563eb); color: #fff; box-shadow: 0 10px 24px rgba(37,99,235,0.25); }
        .btn-outline { border-color: #e5e7eb; color: #0f172a; background: #fff; }
        .info-list { text-align: left; margin-top: 1rem; color: #475569; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="pending-shell">
        <div class="pending-card">
            <div class="badge"><i class="fas fa-hourglass-half"></i> Verification Pending</div>
            <h1 style="margin:0 0 0.5rem;">Hi <?php echo htmlspecialchars($_SESSION['full_name']); ?>, we're reviewing your pharmacy</h1>
            <p style="margin:0 0 1rem; color:#475569;">Pharmacy: <strong><?php echo htmlspecialchars($pharmacyName); ?></strong></p>
            <p style="margin:0 0 1rem; color:#475569;">License: <?php echo htmlspecialchars($license); ?></p>
            <p style="margin:0; color:#0f172a; font-weight:600;">We’ll email you as soon as your pharmacy is verified.</p>
            <div class="info-list">
                <ul>
                    <li>Our admin team will verify your license details.</li>
                    <li>You will be activated once approved.</li>
                    <li>We will notify you on your next login once verified.</li>
                </ul>
            </div>
            <div class="actions">
                <a class="btn btn-outline" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                <a class="btn btn-primary" href="dashboard.php"><i class="fas fa-redo"></i> Refresh Status</a>
            </div>
        </div>
    </div>
</body>
</html>
