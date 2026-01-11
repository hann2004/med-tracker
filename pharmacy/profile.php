<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'pharmacy') {
    header('Location: ../login.php');
    exit();
}

if (empty($_SESSION['is_verified'])) {
    header('Location: pending.php');
    exit();
}

$conn = getDatabaseConnection();
$userId = (int)$_SESSION['user_id'];

$pharmacyStmt = $conn->prepare("SELECT * FROM pharmacies WHERE owner_id = ? LIMIT 1");
$pharmacyStmt->bind_param('i', $userId);
$pharmacyStmt->execute();
$pharmacy = $pharmacyStmt->get_result()->fetch_assoc();
$pharmacyStmt->close();

if (!$pharmacy) {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['pharmacy_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '' || $phone === '' || $address === '') {
        $error = 'Name, phone, and address are required.';
    } else {
        $stmt = $conn->prepare("UPDATE pharmacies SET pharmacy_name = ?, phone = ?, address = ?, description = ? WHERE pharmacy_id = ? LIMIT 1");
        $stmt->bind_param('ssssi', $name, $phone, $address, $description, $pharmacy['pharmacy_id']);
        if ($stmt->execute()) {
            $message = 'Profile updated.';
            // Refresh local copy
            $pharmacy['pharmacy_name'] = $name;
            $pharmacy['phone'] = $phone;
            $pharmacy['address'] = $address;
            $pharmacy['description'] = $description;
        } else {
            $error = 'Could not update profile.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pharmacy Profile</title>
    <link rel="stylesheet" href="../styles/pharmacy.css?v=20260111">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background:#f7fbff; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin:0; }
        .page { max-width: 720px; margin: 40px auto; padding: 0 16px; }
        .card { background:#fff; border:1px solid #d9e7f6; border-radius:14px; box-shadow:0 8px 20px rgba(16,37,66,0.06); padding:18px; }
        label { display:block; font-weight:700; color:#102542; margin-bottom:6px; }
        input, textarea { width:100%; padding:10px; border:1px solid #d9e7f6; border-radius:10px; margin-bottom:12px; }
        textarea { min-height: 100px; resize: vertical; }
        .actions { display:flex; justify-content:flex-end; gap:10px; }
        .btn { border:1px solid #d9e7f6; background:#f7fbff; padding:10px 12px; border-radius:10px; cursor:pointer; font-weight:700; color:#102542; }
        .btn.primary { background:#102542; color:#fff; border-color:#102542; }
        .alert { padding:10px 12px; border-radius:10px; border:1px solid #d9e7f6; background:#eef3f8; color:#102542; margin-bottom:12px; }
        .alert.error { background:#fff7f7; border-color:#f3c4c4; color:#b91c1c; }
        a.back { color:#4b5b70; text-decoration:none; font-weight:700; display:inline-flex; align-items:center; gap:6px; margin-bottom:12px; }
    </style>
</head>
<body>
    <div class="page">
        <a class="back" href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
        <div class="card">
            <h1 style="margin-top:0;">Edit Pharmacy Profile</h1>
            <p style="color:#4b5b70; margin-top:4px;">Update your visible pharmacy details.</p>

            <?php if ($message): ?><div class="alert"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

            <form method="POST">
                <label>Pharmacy Name</label>
                <input type="text" name="pharmacy_name" value="<?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?>" required>

                <label>Phone</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($pharmacy['phone']); ?>" required>

                <label>Address</label>
                <input type="text" name="address" value="<?php echo htmlspecialchars($pharmacy['address']); ?>" required>

                <label>Description</label>
                <textarea name="description" placeholder="Short description (optional)"><?php echo htmlspecialchars($pharmacy['description'] ?? ''); ?></textarea>

                <div class="actions">
                    <button type="submit" class="btn primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
