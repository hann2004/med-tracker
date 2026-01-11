<?php
session_start();
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

// Require authentication for search suggestions
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$conn = getDatabaseConnection();
$action = $_GET['action'] ?? 'suggest';
$query  = trim($_GET['q'] ?? '');

if ($action === 'suggest') {
    echo json_encode(handleSuggest($conn, $query));
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unsupported action']);
exit;

function handleSuggest(mysqli $conn, string $query): array {
    $normalized = mb_substr($query, 0, 120);
    if ($normalized === '') {
        return ['success' => true, 'items' => []];
    }

    $like = '%' . $normalized . '%';
    $stmt = $conn->prepare(
        "SELECT m.medicine_id, m.medicine_name, m.generic_name, m.image_url,
                COUNT(DISTINCT pi.pharmacy_id) AS pharmacy_count,
                MIN(pi.price) AS min_price
         FROM medicines m
         LEFT JOIN pharmacy_inventory pi ON pi.medicine_id = m.medicine_id AND pi.quantity > 0
         WHERE m.is_active = 1 AND (m.medicine_name LIKE ? OR m.generic_name LIKE ? OR m.brand_name LIKE ?)
         GROUP BY m.medicine_id
         ORDER BY pharmacy_count DESC, m.medicine_name ASC
         LIMIT 8"
    );
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = formatSuggestRow($row);
    }
    $stmt->close();

    $suggested = null;
    if (count($items) === 0) {
        $suggested = findNearestMedicine($conn, $normalized);
    }

    return [
        'success' => true,
        'query' => $normalized,
        'items' => $items,
        'suggested' => $suggested,
    ];
}

function formatSuggestRow(array $row): array {
    return [
        'id' => (int) $row['medicine_id'],
        'name' => $row['medicine_name'],
        'generic' => $row['generic_name'],
        'image' => resolveImage($row),
        'pharmacies' => (int) ($row['pharmacy_count'] ?? 0),
        'min_price' => formatPrice($row['min_price'] ?? null),
    ];
}

function resolveImage(array $row): string {
    $local = 'uploads/medicines/' . $row['medicine_id'] . '.jpg';
    if (file_exists(__DIR__ . '/' . $local)) {
        return '/' . $local;
    }
    if (!empty($row['image_url'])) {
        return $row['image_url'];
    }
    return 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=400&q=60';
}

function formatPrice($price): ?string {
    if ($price === null) return null;
    return number_format((float) $price, 2) . ' ETB';
}

function findNearestMedicine(mysqli $conn, string $query): ?string {
    $stmt = $conn->prepare("SELECT medicine_name FROM medicines WHERE is_active = 1 LIMIT 120");
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
