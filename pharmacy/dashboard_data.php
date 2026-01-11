<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'pharmacy') {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit();
}
if (empty($_SESSION['is_verified'])) {
    http_response_code(403);
    echo json_encode(['error' => 'unverified']);
    exit();
}

$conn = getDatabaseConnection();
$userId = (int)$_SESSION['user_id'];

// Get pharmacy id
$pharmacyStmt = $conn->prepare('SELECT pharmacy_id FROM pharmacies WHERE owner_id = ? LIMIT 1');
$pharmacyStmt->bind_param('i', $userId);
$pharmacyStmt->execute();
$pharmacy = $pharmacyStmt->get_result()->fetch_assoc();
$pharmacyStmt->close();

if (!$pharmacy) {
    http_response_code(404);
    echo json_encode(['error' => 'pharmacy_not_found']);
    exit();
}
$pid = (int)$pharmacy['pharmacy_id'];

// Category breakdown
$categoryRows = $conn->query(
    "SELECT COALESCE(c.category_name, 'Uncategorized') AS category_name, COALESCE(SUM(pi.quantity * pi.price), 0) AS value
     FROM pharmacy_inventory pi
     JOIN medicines m ON pi.medicine_id = m.medicine_id
     LEFT JOIN medicine_categories c ON m.category_id = c.category_id
     WHERE pi.pharmacy_id = $pid
     GROUP BY c.category_id, c.category_name
     ORDER BY value DESC
     LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

// Top medicines
// Top medicines
// Bar chart removed

$response = [
    'categoryLabels' => array_map(fn($r) => $r['category_name'], $categoryRows),
    'categoryValues' => array_map(fn($r) => (float)$r['value'], $categoryRows),
    // Bar chart removed
    'updatedAt' => date('c')
];

echo json_encode($response);
