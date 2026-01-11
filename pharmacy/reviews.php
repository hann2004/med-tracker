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

// Get pharmacy
$phStmt = $conn->prepare('SELECT * FROM pharmacies WHERE owner_id = ? LIMIT 1');
$phStmt->bind_param('i', $userId);
$phStmt->execute();
$pharmacy = $phStmt->get_result()->fetch_assoc();
$phStmt->close();
if (!$pharmacy) { header('Location: ../login.php'); exit(); }

// Reviews for this pharmacy
$rvStmt = $conn->prepare('SELECT review_id, rating, review_title, review_text, created_at, user_id FROM reviews_and_ratings WHERE pharmacy_id = ? ORDER BY created_at DESC LIMIT 50');
$rvStmt->bind_param('i', $pharmacy['pharmacy_id']);
$rvStmt->execute();
$reviews = $rvStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rvStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews & Ratings - <?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?></title>
    <link rel="stylesheet" href="../styles/pharmacy.css?v=20260111">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .card { background: #fff; border: 1px solid #d9e7f6; border-radius: 14px; box-shadow: 0 8px 20px rgba(16,37,66,0.06); padding: 18px; margin-bottom: 18px; }
        .review { border:1px solid #d9e7f6; border-radius: 12px; padding: 12px; margin-bottom: 10px; background:#f7fbff; }
        .review-header { display:flex; align-items:center; justify-content:space-between; }
        .rating { color:#f0b429; }
        .muted { color:#4b5b70; font-size:0.9rem; }
    </style>
</head>
<body class="pharmacy-dashboard" style="background:#f7fbff;">
<div class="page">
    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between;">
            <h2 style="margin:0;">Reviews & Ratings</h2>
            <span class="badge">Pharmacy: <?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?></span>
        </div>
        <?php if (count($reviews) === 0): ?>
            <p class="muted">No reviews yet.</p>
        <?php else: ?>
            <?php foreach($reviews as $r): ?>
                <div class="review">
                    <div class="review-header">
                        <strong><?php echo htmlspecialchars($r['review_title'] ?? 'Review'); ?></strong>
                        <span class="rating">
                            <?php echo str_repeat('★', (int)$r['rating']); ?><?php echo str_repeat('☆', max(0, 5-(int)$r['rating'])); ?>
                        </span>
                    </div>
                    <p style="margin:8px 0;"><?php echo nl2br(htmlspecialchars($r['review_text'] ?? '')); ?></p>
                    <div class="muted"><i class="fas fa-clock"></i> <?php echo date('M d, Y', strtotime($r['created_at'])); ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
