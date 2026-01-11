<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

header('Content-Type: application/json');
$conn = getDatabaseConnection();

// Searches by day for last 14 days
$byDay = $conn->query("SELECT DATE(search_date) as d, COUNT(*) as c FROM searches WHERE search_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(search_date) ORDER BY d ASC")->fetch_all(MYSQLI_ASSOC);
$labelsDay = [];
$valuesDay = [];

// Ensure continuous days even if zero
for ($i = 13; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} day"));
    $labelsDay[] = date('M d', strtotime($date));
    $found = 0;
    foreach ($byDay as $row) {
        if ($row['d'] === $date) { $found = (int)$row['c']; break; }
    }
    $valuesDay[] = $found;
}

// Top queries (limit 8)
$top = $conn->query("SELECT search_query, COUNT(*) as c FROM searches WHERE search_query IS NOT NULL AND search_query!='' GROUP BY search_query ORDER BY c DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
$labelsTop = array_map(fn($r) => $r['search_query'], $top);
$valuesTop = array_map(fn($r) => (int)$r['c'], $top);

// Return
echo json_encode([
    'searchesByDay' => [ 'labels' => $labelsDay, 'values' => $valuesDay ],
    'topQueries' => [ 'labels' => $labelsTop, 'values' => $valuesTop ]
]);
