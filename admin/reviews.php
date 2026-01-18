<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_functions.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$conn = getDatabaseConnection();

// Fetch all reviews and ratings
$reviews = $conn->query('
    SELECT r.*, m.medicine_name, u.username, p.pharmacy_name
    FROM reviews_and_ratings r
    LEFT JOIN medicines m ON r.medicine_id = m.medicine_id
    LEFT JOIN users u ON r.user_id = u.user_id
    LEFT JOIN pharmacies p ON r.pharmacy_id = p.pharmacy_id
    ORDER BY r.created_at DESC
')->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reviews - Admin | MedTrack</title>
    <link rel="stylesheet" href="../styles/admin.css">
</head>
<body>
    <div class="admin-container">
        <h1>All Reviews & Ratings</h1>
        <div style="max-width:900px; margin:0 auto;">
        <?php if (count($reviews) === 0): ?>
            <div style="color:#888;">No reviews found.</div>
        <?php else: ?>
            <?php foreach ($reviews as $rev): ?>
                <div class="review" style="display:flex; gap:14px; align-items:flex-start; background:var(--clinical-light); border:1px solid var(--clinical-border); border-radius:12px; padding:14px; margin-bottom:18px;">
                    <div style="flex-shrink:0;">
                        <div style="width:44px; height:44px; border-radius:50%; background:#eaf4ff; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#4b5b70; border:2px solid #eaf4ff;">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                    <div style="flex:1;">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <strong><?= htmlspecialchars($rev['username']) ?></strong>
                            <span style="color:#f0b429; font-size:1.1rem; font-weight:700;">
                                <?= str_repeat('★', (int)$rev['rating']) ?><?= str_repeat('☆', max(0, 5-(int)$rev['rating'])) ?>
                            </span>
                            <span style="color:#475569; font-size:0.95rem;">for <?= htmlspecialchars($rev['medicine_name']) ?></span>
                            <span style="color:#475569; font-size:0.95rem;">at <?= htmlspecialchars($rev['pharmacy_name']) ?></span>
                        </div>
                        <div style="margin:6px 0 2px 0; color:#102542; font-size:1.05rem; font-weight:500;">
                            <?= htmlspecialchars($rev['review_title'] ?? '') ?>
                        </div>
                        <div style="color:#4b5b70; font-size:0.98rem; margin-bottom:4px;">
                            <?= nl2br(htmlspecialchars($rev['review_text'])) ?>
                        </div>
                        <div style="font-size:0.85rem; color:#888;">
                            <i class="fas fa-clock"></i> <?= date('M d, Y', strtotime($rev['created_at'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
    </div>
</body>
</html>
