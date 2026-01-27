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

</head>
<body>
    <div class="pharmacy-page">
        <a class="pharmacy-back-link" href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
        <div class="pharmacy-card-main">
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

        <!-- Reviews About This Pharmacy -->
        <div class="pharmacy-card-main" style="margin-top:2.5rem;">
            <h2>Reviews About Your Pharmacy</h2>
            <?php
            $pharmacyId = $pharmacy['pharmacy_id'];
            $reviews = $conn->query("SELECT r.*, u.username, m.medicine_name FROM reviews_and_ratings r LEFT JOIN users u ON r.user_id = u.user_id LEFT JOIN medicines m ON r.medicine_id = m.medicine_id WHERE r.pharmacy_id = $pharmacyId ORDER BY r.created_at DESC")->fetch_all(MYSQLI_ASSOC);
            if (count($reviews) === 0): ?>
                <div style="color:#888;">No reviews for your pharmacy yet.</div>
            <?php else: ?>
                <?php foreach ($reviews as $rev): ?>
                    <div style="border-bottom:1px solid #e5e7eb; padding:0.7rem 0;">
                        <strong><?php echo htmlspecialchars($rev['username']); ?></strong>
                        <span style="color:#f59e0b; font-weight:700;"> <?php echo str_repeat('★', (int)$rev['rating']); ?></span>
                        <span style="color:#475569; font-size:0.95rem;">for <?php echo htmlspecialchars($rev['medicine_name']); ?></span>
                        <div><?php echo htmlspecialchars($rev['review_text']); ?></div>
                        <div style="font-size:0.85rem; color:#888;">on <?php echo htmlspecialchars($rev['created_at']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
