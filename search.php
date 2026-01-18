<?php
session_start();
require_once __DIR__ . '/config/database.php';

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}

$conn = getDatabaseConnection();

// Require authentication before allowing search
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . $basePath . '/login.php?next=1');
    exit;
}

$query = trim($_GET['q'] ?? ($_GET['medicine'] ?? ''));
$location = trim($_GET['location'] ?? '');
$pharmacyId = isset($_GET['pharmacy']) ? (int) $_GET['pharmacy'] : null;

$results = [];
$suggestion = null;
$resultCount = 0;

if ($query !== '') {
    $results = searchMedicines($conn, $query, $location, $pharmacyId);
    $resultCount = count($results);
    if ($resultCount === 0) {
        $suggestion = findNearestMedicine($conn, $query);
    }
    logSearch($conn, $query, $resultCount, $results);
}

function searchMedicines(mysqli $conn, string $query, string $location, ?int $pharmacyId): array {
    $like = '%' . $query . '%';
    $sql = "SELECT 
                m.medicine_id, m.medicine_name, m.generic_name, m.brand_name, m.image_url, m.requires_prescription,
                pi.price, pi.quantity, pi.discount_percentage, pi.is_discounted, pi.last_restocked,
                p.pharmacy_id, p.pharmacy_name, p.address, p.city, p.rating, p.review_count
            FROM medicines m
            JOIN pharmacy_inventory pi ON pi.medicine_id = m.medicine_id AND pi.quantity > 0
            JOIN pharmacies p ON p.pharmacy_id = pi.pharmacy_id AND p.is_active = 1
            WHERE m.is_active = 1
              AND (m.medicine_name LIKE ? OR m.generic_name LIKE ? OR m.brand_name LIKE ?)";

    $params = [$like, $like, $like];
    $types  = 'sss';

    if ($location !== '') {
        $sql .= " AND (p.address LIKE ? OR p.city LIKE ? OR p.zone LIKE ? )";
        $locLike = '%' . $location . '%';
        $params[] = $locLike; $params[] = $locLike; $params[] = $locLike;
        $types   .= 'sss';
    }

    if ($pharmacyId) {
        $sql .= " AND p.pharmacy_id = ?";
        $params[] = $pharmacyId;
        $types   .= 'i';
    }

    $sql .= " ORDER BY m.medicine_name ASC, p.rating DESC LIMIT 80";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $grouped = [];
    while ($row = $res->fetch_assoc()) {
        $mid = (int) $row['medicine_id'];
        if (!isset($grouped[$mid])) {
            $grouped[$mid] = [
                'medicine_id' => $mid,
                'name' => $row['medicine_name'],
                'generic' => $row['generic_name'],
                'brand' => $row['brand_name'],
                'requires_prescription' => (bool) $row['requires_prescription'],
                'image' => resolveImage($row),
                'pharmacies' => []
            ];
        }
        $grouped[$mid]['pharmacies'][] = [
            'pharmacy_id' => (int) $row['pharmacy_id'],
            'pharmacy_name' => $row['pharmacy_name'],
            'address' => $row['address'],
            'city' => $row['city'],
            'rating' => $row['rating'],
            'review_count' => $row['review_count'],
            'price' => formatPrice($row['price']),
            'quantity' => (int) $row['quantity'],
            'discount_percentage' => $row['discount_percentage'],
            'is_discounted' => (bool) $row['is_discounted'],
            'last_restocked' => $row['last_restocked']
        ];
    }
    $stmt->close();

    return array_values($grouped);
}

function resolveImage(array $row): string {
    $local = 'uploads/medicines/' . $row['medicine_id'] . '.jpg';
    if (file_exists(__DIR__ . '/' . $local)) {
        return $local;
    }
    if (!empty($row['image_url'])) {
        return $row['image_url'];
    }
    return 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=800&q=70';
}

function formatPrice($price): string {
    return number_format((float) $price, 2) . ' ETB';
}

function findNearestMedicine(mysqli $conn, string $query): ?string {
    $stmt = $conn->prepare("SELECT medicine_name FROM medicines WHERE is_active = 1 LIMIT 200");
    $stmt->execute();
    $res = $stmt->get_result();
    $best = null;
    $bestScore = 0;
    while ($row = $res->fetch_assoc()) {
        similar_text(mb_strtolower($query), mb_strtolower($row['medicine_name']), $percent);
        if ($percent > $bestScore) {
            $bestScore = $percent;
            $best = $row['medicine_name'];
        }
    }
    $stmt->close();
    return ($bestScore >= 55 && mb_strtolower($best) !== mb_strtolower($query)) ? $best : null;
}

function logSearch(mysqli $conn, string $query, int $resultCount, array $results): void {
    $userId = $_SESSION['user_id'] ?? null;
    $sessionId = session_id();
    $medicineId = $results[0]['medicine_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = $conn->prepare("INSERT INTO searches (user_id, session_id, medicine_id, search_query, search_location, results_found, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $location = trim($_GET['location'] ?? '');
    $stmt->bind_param('isissis', $userId, $sessionId, $medicineId, $query, $location, $resultCount, $ip);
    $stmt->execute();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Medicines | MedTrack Arba Minch</title>
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .search-layout { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem 3rem; }
        .search-header { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; }
        .search-form-wide { width: 100%; display: grid; grid-template-columns: 1.2fr 0.8fr auto; gap: 0.75rem; }
        .search-input-wrapper { position: relative; }
        .search-suggestions { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 12px 32px rgba(0,0,0,0.12); max-height: 260px; overflow-y: auto; display: none; z-index: 50; }
        .search-suggestions.show { display: block; }
        .search-suggestions .suggestion-item { padding: 12px 14px; display: flex; gap: 10px; align-items: center; cursor: pointer; }
        .search-suggestions .suggestion-item:hover { background: rgba(37,99,235,0.08); }
        .badge-pill { padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.85rem; background: rgba(37,99,235,0.1); color: #1d4ed8; }
        .results-grid { display: grid; gap: 1rem; }
        .result-card { border: 1px solid #e5e7eb; border-radius: 14px; padding: 1rem; display: grid; grid-template-columns: 160px 1fr; gap: 1rem; background: #fff; box-shadow: 0 6px 16px rgba(0,0,0,0.04); }
        .result-card img { width: 100%; height: 140px; object-fit: cover; border-radius: 12px; }
        .pharmacy-list { display: grid; gap: 0.65rem; margin-top: 0.5rem; }
        .pharmacy-row { display: grid; grid-template-columns: 1.1fr 0.5fr 0.4fr; align-items: center; padding: 0.65rem 0.75rem; border: 1px solid #e5e7eb; border-radius: 10px; background: #f8fafc; }
        .pharmacy-row strong { display: block; }
        .price { font-weight: 800; color: #1d4ed8; }
        .stock-ok { color: #0f9d58; font-weight: 600; }
        .stock-low { color: #f59e0b; font-weight: 600; }
        .empty-state { text-align: center; padding: 3rem 1rem; border: 1px dashed #e5e7eb; border-radius: 14px; background: #f9fafb; }
        @media (max-width: 900px) {
            .search-layout { padding: 1rem 0.5rem 2rem; }
            .search-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
            .search-form-wide { grid-template-columns: 1fr; gap: 0.5rem; }
            .result-card { grid-template-columns: 1fr; }
            .pharmacy-row { grid-template-columns: 1fr; gap: 0.35rem; }
        }
        @media (max-width: 640px) {
            .search-layout { padding: 0.5rem 0.25rem 1rem; }
            .result-card { padding: 0.5rem; }
            .result-card img { height: 90px; }
            .pharmacy-list { gap: 0.35rem; }
            .search-header h1 { font-size: 1.2rem; }
            .badge-pill { font-size: 0.75rem; padding: 0.15rem 0.5rem; }
        }
    </style>
</head>
<body>
    <nav class="system-nav" style="position:sticky;top:0;z-index:20;">
        <div class="nav-container">
            <a href="index.php" class="system-logo">
                <div class="logo-icon"><i class="fas fa-capsules"></i></div>
                <span>MedTrack Arba Minch</span>
            </a>
            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php#find-medicine" class="nav-link"><i class="fas fa-search"></i> Find Medicine</a></li>
                <li><a href="index.php#coverage" class="nav-link"><i class="fas fa-map-marker-alt"></i> Pharmacies</a></li>
                <li><a href="index.php#stats" class="nav-link"><i class="fas fa-chart-line"></i> Stats</a></li>
            </ul>
            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="index.php" class="btn-outline">Home</a>
                <?php else: ?>
                    <a href="login.php" class="btn-text"><i class="fas fa-sign-in-alt"></i> Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="search-layout">
        <header class="search-header">
            <div>
                <h1 style="margin:0;">Search Medicines</h1>
                <p style="margin:0; color:#475569;">Smart search with photos, pricing, and exact pharmacy availability.</p>
            </div>
            <span class="badge-pill"><?php echo $resultCount; ?> results</span>
        </header>

        <form class="search-form-wide" method="GET" action="search.php">
            <div class="search-input-wrapper">
                <input type="text" name="q" id="pageSearchInput" data-suggest="true" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search medicine name or brand" required>
                <div class="search-suggestions" data-for="pageSearchInput"></div>
            </div>
            <input type="text" name="location" value="<?php echo htmlspecialchars($location); ?>" placeholder="Filter by area (e.g., Secha, Sikela)">
            <button class="btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
        </form>

        <?php if ($suggestion): ?>
            <div style="margin: 1rem 0; padding: 0.85rem 1rem; background: #fff7ed; border: 1px solid #f59e0b; border-radius: 10px; color: #b45309;">
                <i class="fas fa-magic"></i> Did you mean <a href="search.php?q=<?php echo urlencode($suggestion); ?>" style="font-weight:700; color:#b45309;"><?php echo htmlspecialchars($suggestion); ?></a>?
            </div>
        <?php endif; ?>

        <?php if ($query === ''): ?>
            <div class="empty-state">
                <h3>Start by typing a medicine name</h3>
                <p>We will suggest matches and show exact pharmacies with pricing.</p>
            </div>
        <?php elseif ($resultCount === 0): ?>
            <div class="empty-state">
                <h3>No matches found</h3>
                <p>Try a different spelling or a nearby area.</p>
            </div>
        <?php else: ?>
            <div class="results-grid">
                <?php foreach ($results as $item): ?>
                <div class="result-card">
                    <div>
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                    </div>
                    <div>
                        <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                            <h3 style="margin:0;"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <?php if ($item['requires_prescription']): ?>
                                <span class="badge-pill" style="background:rgba(239,68,68,0.12);color:#b91c1c;"><i class="fas fa-prescription-bottle-alt"></i> RX</span>
                            <?php else: ?>
                                <span class="badge-pill" style="background:rgba(16,185,129,0.12);color:#0f9d58;"><i class="fas fa-shopping-cart"></i> OTC</span>
                            <?php endif; ?>
                        </div>
                        <p style="color:#475569; margin: 6px 0 12px;">Generic: <?php echo htmlspecialchars($item['generic'] ?: 'N/A'); ?></p>
                        <div class="pharmacy-list">
                            <?php foreach ($item['pharmacies'] as $ph): ?>
                            <div class="pharmacy-row">
                                <div>
                                    <strong><?php echo htmlspecialchars($ph['pharmacy_name']); ?></strong>
                                    <span style="color:#475569; font-size:0.95rem;"><?php echo htmlspecialchars($ph['address']); ?></span>
                                </div>
                                <div class="price"><?php echo $ph['price']; ?></div>
                                <div style="text-align:right;">
                                    <div class="<?php echo $ph['quantity'] > 10 ? 'stock-ok' : 'stock-low'; ?>">
                                        <?php echo $ph['quantity']; ?> in stock
                                    </div>
                                    <div style="font-size:0.85rem; color:#475569;">Rating: <?php echo number_format($ph['rating'], 1); ?> (<?php echo $ph['review_count']; ?>)</div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        window.APP_BASE = '<?php echo $basePath; ?>';
        window.APP_SEARCH_API = (window.APP_BASE || '') + '/search_api.php';
        window.IS_AUTH = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
        window.LOGIN_URL = (window.APP_BASE || '') + '/login.php?next=1';
        window.REGISTER_URL = (window.APP_BASE || '') + '/register.php';
    </script>
    <script src="js/search.js"></script>
</body>
</html>
