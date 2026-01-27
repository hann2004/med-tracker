<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
// Handle review submission
$reviewSuccess = '';
$reviewError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = (int) ($_POST['rating'] ?? 0);
    $review_text = trim($_POST['review_text'] ?? '');
    if ($rating < 1 || $rating > 5) {
        $reviewError = 'Please select a rating.';
    } elseif (strlen($review_text) < 5) {
        $reviewError = 'Review text is too short.';
    } else {
        $stmt = $conn->prepare("INSERT INTO reviews_and_ratings (user_id, medicine_id, rating, review_text, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param('iiis', $_SESSION['user_id'], $medicineId, $rating, $review_text);
        if ($stmt->execute()) {
            $reviewSuccess = 'Review submitted!';
        } else {
            $reviewError = 'Failed to submit review.';
        }
        $stmt->close();
    }
}

// Handle report submission (medicine or review)
$reportSuccess = '';
$reportError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $report_type = trim($_POST['report_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : null;
    if (!$report_type || strlen($description) < 5) {
        $reportError = 'Please select a type and enter a valid description.';
    } else {
        if ($review_id) {
            $stmt = $conn->prepare("INSERT INTO reports (user_id, review_id, report_type, description, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param('iiss', $_SESSION['user_id'], $review_id, $report_type, $description);
        } else {
            $stmt = $conn->prepare("INSERT INTO reports (user_id, medicine_id, report_type, description, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param('iiss', $_SESSION['user_id'], $medicineId, $report_type, $description);
        }
        if ($stmt->execute()) {
            $reportSuccess = 'Report submitted!';
        } else {
            $reportError = 'Failed to submit report.';
        }
        $stmt->close();
    }
}

// Fetch existing reviews for this medicine
$reviews = $conn->query("SELECT r.*, u.username FROM reviews_and_ratings r JOIN users u ON r.user_id = u.user_id WHERE r.medicine_id = $medicineId ORDER BY r.created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($medicine['medicine_name']); ?> | Availability</title>
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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

    <main class="medicine-page">
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
            <!-- Modern Review/Report Buttons -->
            <section style="margin-top:2.5rem; display: flex; gap: 1.5rem;">
                <button id="openReviewModal" class="btn-primary" style="padding:0.8rem 2.2rem; font-size:1.1rem; font-weight:700; border-radius:10px; background:#2563eb; color:#fff; border:none; cursor:pointer;">Leave a Review</button>
                <button id="openReportModal" class="btn-primary" style="padding:0.8rem 2.2rem; font-size:1.1rem; font-weight:700; border-radius:10px; background:#dc2626; color:#fff; border:none; cursor:pointer;">Report an Issue</button>
            </section>

            <!-- Review Modal -->
            <div id="reviewModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(30,41,59,0.18); z-index:1000; align-items:center; justify-content:center;">
                <div style="background:#fff; border-radius:14px; max-width:400px; width:95vw; box-shadow:0 8px 32px rgba(0,0,0,0.13); padding:2rem 1.5rem; position:relative;">
                    <button id="closeReviewModal" style="position:absolute; top:10px; right:12px; background:none; border:none; font-size:1.3rem; color:#888; cursor:pointer;"><i class="fas fa-times"></i></button>
                    <h3 style="margin-top:0; color:#2563eb; display:flex;align-items:center;gap:0.5rem;"><i class="fas fa-star"></i> Write a Review</h3>
                    <?php if ($reviewSuccess): ?><div class="alert alert-success" style="margin-bottom:1rem;"> <?php echo $reviewSuccess; ?> </div><?php endif; ?>
                    <?php if ($reviewError): ?><div class="alert alert-error" style="margin-bottom:1rem;"> <?php echo $reviewError; ?> </div><?php endif; ?>
                    <form method="POST" id="reviewForm">
                        <label for="rating" style="font-weight:600;">Your Rating:</label>
                        <div id="star-rating-modal" style="font-size:1.7rem; color:#f59e0b; margin-bottom:0.5rem; cursor:pointer;">
                            <?php for ($i=1; $i<=5; $i++): ?>
                                <span class="star-modal" data-value="<?php echo $i; ?>">&#9733;</span>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="rating-modal" required>
                        <label for="review_text" style="font-weight:600;">Your Review:</label>
                        <textarea name="review_text" id="review_text" rows="3" required style="width:100%;border-radius:8px;"></textarea>
                        <button type="submit" name="submit_review" class="btn-primary" style="margin-top:0.7rem; width:100%;">Submit Review</button>
                    </form>
                </div>
            </div>

            <!-- Report Modal -->
            <div id="reportModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(30,41,59,0.18); z-index:1000; align-items:center; justify-content:center;">
                <div style="background:#fff; border-radius:14px; max-width:400px; width:95vw; box-shadow:0 8px 32px rgba(0,0,0,0.13); padding:2rem 1.5rem; position:relative;">
                    <button id="closeReportModalMain" style="position:absolute; top:10px; right:12px; background:none; border:none; font-size:1.3rem; color:#888; cursor:pointer;"><i class="fas fa-times"></i></button>
                    <h3 style="margin-top:0; color:#dc2626; display:flex;align-items:center;gap:0.5rem;"><i class="fas fa-flag"></i> Report an Issue</h3>
                    <?php if ($reportSuccess): ?><div class="alert alert-success" style="margin-bottom:1rem;"> <?php echo $reportSuccess; ?> </div><?php endif; ?>
                    <?php if ($reportError): ?><div class="alert alert-error" style="margin-bottom:1rem;"> <?php echo $reportError; ?> </div><?php endif; ?>
                    <form method="POST" id="reportForm">
                        <label for="report_type" style="font-weight:600;">Type of Issue:</label>
                        <select name="report_type" id="report_type" required style="border-radius:8px;">
                            <option value="">Select</option>
                            <option value="Incorrect Info">Incorrect Info</option>
                            <option value="Fake Medicine">Fake Medicine</option>
                            <option value="Other">Other</option>
                        </select>
                        <label for="description" style="font-weight:600;">Description:</label>
                        <textarea name="description" id="description" rows="3" required style="width:100%;border-radius:8px;"></textarea>
                        <button type="submit" name="submit_report" class="btn-primary" style="margin-top:0.7rem; width:100%; background:#dc2626;">Submit Report</button>
                    </form>
                </div>
            </div>

            <!-- Modern Reviews List with Report Button (Professional UI) -->
            <section style="margin-top:2.5rem;">
                <h2 style="font-size:1.2rem; margin-bottom:1rem;">Recent Reviews</h2>
                <div style="display:grid; gap:1.1rem;">
                    <?php if (count($reviews) === 0): ?>
                        <div style="color:#888;">No reviews yet.</div>
                    <?php else: ?>
                        <?php foreach ($reviews as $rev): ?>
                            <div style="background:#fff; border-radius:12px; padding:1.2rem 1.5rem; border:1px solid #e5e7eb; box-shadow:0 2px 8px rgba(0,0,0,0.04); position:relative; display:flex; align-items:flex-start; gap:1.1rem;">
                                <div style="width:44px; height:44px; border-radius:50%; background:#e0e7ef; display:flex; align-items:center; justify-content:center; font-size:1.3rem; font-weight:700; color:#2563eb;">
                                    <?php echo strtoupper(substr($rev['username'],0,1)); ?>
                                </div>
                                <div style="flex:1;">
                                    <div style="display:flex; align-items:center; gap:0.7rem; margin-bottom:0.2rem;">
                                        <strong><?php echo htmlspecialchars($rev['username']); ?></strong>
                                        <span style="color:#f59e0b; font-size:1.1rem; font-weight:700;"> <?php echo str_repeat('★', (int)$rev['rating']); ?></span>
                                        <span style="font-size:0.9rem; color:#888;">on <?php echo htmlspecialchars($rev['created_at']); ?></span>
                                        <button class="report-review-btn" data-review-id="<?php echo $rev['review_id']; ?>" style="margin-left:auto; background:none; border:none; color:#dc2626; cursor:pointer; font-size:1.1rem;" title="Report this review"><i class="fas fa-flag"></i></button>
                                    </div>
                                    <div style="color:#334155; font-size:1.08rem; margin-bottom:0.2rem;">"<?php echo htmlspecialchars($rev['review_text']); ?>"</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Report Review Modal -->
            <div id="reportReviewModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(30,41,59,0.18); z-index:1000; align-items:center; justify-content:center;">
                <div style="background:#fff; border-radius:14px; max-width:370px; width:95vw; box-shadow:0 8px 32px rgba(0,0,0,0.13); padding:2rem 1.5rem; position:relative;">
                    <button id="closeReportModal" style="position:absolute; top:10px; right:12px; background:none; border:none; font-size:1.3rem; color:#888; cursor:pointer;"><i class="fas fa-times"></i></button>
                    <h3 style="margin-top:0; color:#dc2626; display:flex;align-items:center;gap:0.5rem;"><i class="fas fa-flag"></i> Report Review</h3>
                    <form id="reportReviewForm" method="POST">
                        <input type="hidden" name="review_id" id="modal_review_id">
                        <label for="modal_report_type" style="font-weight:600;">Reason:</label>
                        <select name="report_type" id="modal_report_type" required style="border-radius:8px; width:100%;">
                            <option value="">Select</option>
                            <option value="Spam or fake">Spam or fake</option>
                            <option value="Offensive or abusive">Offensive or abusive</option>
                            <option value="Irrelevant">Irrelevant</option>
                            <option value="Other">Other</option>
                        </select>
                        <label for="modal_description" style="font-weight:600;">Details:</label>
                        <textarea name="description" id="modal_description" rows="3" required style="width:100%;border-radius:8px;"></textarea>
                        <button type="submit" name="submit_report" class="btn-primary" style="margin-top:0.7rem; width:100%; background:#dc2626;">Submit Report</button>
                    </form>
                </div>
            </div>

            <script>
            // Modal logic for review/report
            document.addEventListener('DOMContentLoaded', function() {
                // Review modal
                const openReviewBtn = document.getElementById('openReviewModal');
                const reviewModal = document.getElementById('reviewModal');
                const closeReviewModal = document.getElementById('closeReviewModal');
                openReviewBtn.addEventListener('click', function() { reviewModal.style.display = 'flex'; });
                closeReviewModal.addEventListener('click', function() { reviewModal.style.display = 'none'; });
                reviewModal.addEventListener('click', function(e) { if (e.target === reviewModal) reviewModal.style.display = 'none'; });

                // Report modal
                const openReportBtn = document.getElementById('openReportModal');
                const reportModal = document.getElementById('reportModal');
                const closeReportModalMain = document.getElementById('closeReportModalMain');
                openReportBtn.addEventListener('click', function() { reportModal.style.display = 'flex'; });
                closeReportModalMain.addEventListener('click', function() { reportModal.style.display = 'none'; });
                reportModal.addEventListener('click', function(e) { if (e.target === reportModal) reportModal.style.display = 'none'; });

                // Star rating for review modal
                const starsModal = document.querySelectorAll('#star-rating-modal .star-modal');
                const ratingInputModal = document.getElementById('rating-modal');
                let selectedModal = 0;
                starsModal.forEach(star => {
                    star.addEventListener('mouseover', function() {
                        const val = parseInt(this.getAttribute('data-value'));
                        highlightStarsModal(val);
                    });
                    star.addEventListener('mouseout', function() {
                        highlightStarsModal(selectedModal);
                    });
                    star.addEventListener('click', function() {
                        selectedModal = parseInt(this.getAttribute('data-value'));
                        ratingInputModal.value = selectedModal;
                        highlightStarsModal(selectedModal);
                    });
                });
                function highlightStarsModal(val) {
                    starsModal.forEach(star => {
                        if (parseInt(star.getAttribute('data-value')) <= val) {
                            star.style.color = '#f59e0b';
                        } else {
                            star.style.color = '#e5e7eb';
                        }
                    });
                }
                highlightStarsModal(selectedModal);

                // Report Review Modal logic (for reporting reviews)
                const reportBtns = document.querySelectorAll('.report-review-btn');
                const reportReviewModal = document.getElementById('reportReviewModal');
                const closeModal = document.getElementById('closeReportModal');
                const modalReviewId = document.getElementById('modal_review_id');
                reportBtns.forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        modalReviewId.value = btn.getAttribute('data-review-id');
                        reportReviewModal.style.display = 'flex';
                    });
                });
                closeModal.addEventListener('click', function() {
                    reportReviewModal.style.display = 'none';
                });
                reportReviewModal.addEventListener('click', function(e) {
                    if (e.target === reportReviewModal) reportReviewModal.style.display = 'none';
                });
            });
            </script>
    </main>
</body>
</html>
