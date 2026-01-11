<?php
session_start();
require_once __DIR__ . '/config/database.php';

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}

if (!isset($_SESSION['user_id'])) {
    $next = ($basePath ?: '') . '/medicine.php' . (isset($_GET['id']) ? '?id=' . urlencode($_GET['id']) : '');
    $_SESSION['redirect_after_login'] = $next;
    header('Location: ' . ($basePath ?: '') . '/login.php?next=1');
    exit;
}

$conn = getDatabaseConnection();
$medicineId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($medicineId <= 0) {
    http_response_code(400);
    echo 'Invalid medicine id';
    exit;
}

$medicineStmt = $conn->prepare("SELECT medicine_id, medicine_name, generic_name, brand_name, manufacturer, description, image_url, requires_prescription FROM medicines WHERE medicine_id = ? AND is_active = 1 LIMIT 1");
$medicineStmt->bind_param('i', $medicineId);
$medicineStmt->execute();
$medicine = $medicineStmt->get_result()->fetch_assoc();
$medicineStmt->close();

if (!$medicine) {
    http_response_code(404);
    echo 'Medicine not found';
    exit;
}

$availStmt = $conn->prepare("SELECT p.pharmacy_id, p.pharmacy_name, p.address, p.city, p.rating, p.review_count,
                                     pi.price, pi.quantity, pi.discount_percentage, pi.is_discounted, pi.last_restocked
                              FROM pharmacy_inventory pi
                              JOIN pharmacies p ON p.pharmacy_id = pi.pharmacy_id AND p.is_active = 1
                              WHERE pi.medicine_id = ? AND pi.quantity > 0
                              ORDER BY p.rating DESC, pi.price ASC");
$availStmt->bind_param('i', $medicineId);
$availStmt->execute();
$availability = $availStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$availStmt->close();

function resolveImage(array $row): string {
    $local = 'uploads/medicines/' . $row['medicine_id'] . '.jpg';
    if (file_exists(__DIR__ . '/' . $local)) {
        return '/' . $local;
    }
    if (!empty($row['image_url'])) {
        return $row['image_url'];
    }
    return 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=900&q=70';
}

function formatPrice($price): string {
    return number_format((float) $price, 2) . ' ETB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($medicine['medicine_name']); ?> | Availability</title>
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .page { max-width: 1100px; margin: 0 auto; padding: 2rem 1.25rem 3rem; }
        .hero-card { display: grid; grid-template-columns: 260px 1fr; gap: 1.5rem; background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 1.5rem; box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
        .hero-card img { width: 100%; border-radius: 12px; object-fit: cover; height: 240px; }
        .pill { padding: 6px 10px; border-radius: 999px; background: rgba(37,99,235,0.1); color: #1d4ed8; font-weight: 700; display: inline-flex; gap: 6px; align-items: center; }
        .pill.rx { background: rgba(239,68,68,0.12); color: #b91c1c; }
        .availability { margin-top: 1.5rem; display: grid; gap: 0.85rem; }
        .avail-row { display: grid; grid-template-columns: 1.15fr 0.5fr 0.4fr; gap: 0.75rem; align-items: center; border: 1px solid #e5e7eb; border-radius: 12px; padding: 0.9rem 1rem; background: #f8fafc; }
        .price { font-weight: 800; color: #1d4ed8; }
        .stock { font-weight: 600; }
        .stock.ok { color: #0f9d58; }
        .stock.low { color: #f59e0b; }
        @media (max-width: 900px) { .hero-card { grid-template-columns: 1fr; } .avail-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <nav class="system-nav" style="position:sticky;top:0;z-index:20;">
        <div class="nav-container">
            <a href="index.php" class="system-logo">
                <div class="logo-icon"><i class="fas fa-capsules"></i></div>
                <span>MedTrack Arba Minch</span>
            </a>
            <div class="nav-actions">
                <a href="index.php" class="btn-outline">Home</a>
                <a href="logout.php" class="btn-text"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </nav>

    <main class="page">
        <div class="hero-card">
            <img src="<?php echo htmlspecialchars(resolveImage($medicine)); ?>" alt="<?php echo htmlspecialchars($medicine['medicine_name']); ?>">
            <div>
                <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                    <h1 style="margin:0;"><?php echo htmlspecialchars($medicine['medicine_name']); ?></h1>
                    <?php if ($medicine['requires_prescription']): ?>
                        <span class="pill rx"><i class="fas fa-prescription-bottle-alt"></i> Prescription required</span>
                    <?php else: ?>
                        <span class="pill"><i class="fas fa-shopping-cart"></i> OTC</span>
                    <?php endif; ?>
                </div>
                <p style="margin:6px 0 10px; color:#475569;">Generic: <?php echo htmlspecialchars($medicine['generic_name'] ?: 'N/A'); ?><?php if ($medicine['brand_name']) echo ' • Brand: ' . htmlspecialchars($medicine['brand_name']); ?></p>
                <?php if (!empty($medicine['description'])): ?>
                    <p style="color:#334155; line-height:1.5;"><?php echo htmlspecialchars($medicine['description']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <section style="margin-top: 2rem;">
            <h2 style="margin-bottom: 0.5rem;">Availability</h2>
            <?php if (count($availability) === 0): ?>
                <div style="padding:1rem; border:1px dashed #e5e7eb; border-radius:12px; background:#f9fafb;">No pharmacies currently report stock for this medicine.</div>
            <?php else: ?>
                <div class="availability">
                    <?php foreach ($availability as $ph): ?>
                        <div class="avail-row">
                            <div>
                                <strong><?php echo htmlspecialchars($ph['pharmacy_name']); ?></strong>
                                <div style="color:#475569; font-size:0.95rem;"><?php echo htmlspecialchars($ph['address']); ?></div>
                                <div style="font-size:0.9rem; color:#475569;">Rating: <?php echo number_format($ph['rating'], 1); ?> (<?php echo (int) $ph['review_count']; ?>)</div>
                            </div>
                            <div class="price"><?php echo formatPrice($ph['price']); ?></div>
                            <div style="text-align:right;">
                                <div class="stock <?php echo ($ph['quantity'] > 10 ? 'ok' : 'low'); ?>"><?php echo (int) $ph['quantity']; ?> in stock</div>
                                <?php if (!empty($ph['discount_percentage']) && $ph['discount_percentage'] > 0): ?>
                                    <div style="color:#16a34a; font-weight:700; font-size:0.95rem;">-<?php echo $ph['discount_percentage']; ?>%</div>
                                <?php endif; ?>
                                <div style="font-size:0.85rem; color:#475569;">Updated: <?php echo htmlspecialchars($ph['last_restocked']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
