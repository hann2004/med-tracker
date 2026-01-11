<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$conn = getDatabaseConnection();

// Basic stats to show in header
$todaySearches = $conn->query("SELECT COUNT(*) as count FROM searches WHERE DATE(search_date)=CURDATE()")->fetch_assoc()['count'];
$uniqueQueries = $conn->query("SELECT COUNT(DISTINCT search_query) as count FROM searches WHERE search_query IS NOT NULL AND search_query!=''")->fetch_assoc()['count'];
$uniqueUsers = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM searches WHERE user_id IS NOT NULL")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Analytics - MedTrack Admin</title>
    <link rel="stylesheet" href="../styles/admin.css?v=20260111">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="admin-dashboard">
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-right" style="margin-left: auto;">
                <div class="user-dropdown">
                    <button class="user-btn">
                        <div class="avatar-sm"><i class="fas fa-user-shield"></i></div>
                        <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                        <div class="divider"></div>
                        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>
        
        <div class="dashboard-content">
            <div class="stats-overview">
                <div class="stats-header">
                    <h2>Search Analytics</h2>
                    <div class="date-range">
                        <span class="badge">Today: <?php echo $todaySearches; ?> searches</span>
                        <span class="badge">Unique queries: <?php echo $uniqueQueries; ?></span>
                        <span class="badge">Unique users: <?php echo $uniqueUsers; ?></span>
                    </div>
                </div>
                <div class="analytics-section" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <div class="section-card">
                        <div class="card-header"><h3>Searches by Day (14d)</h3></div>
                        <div class="card-body">
                            <canvas id="searchesByDay" height="160"></canvas>
                        </div>
                    </div>
                    <div class="section-card">
                        <div class="card-header"><h3>Top Search Terms</h3></div>
                        <div class="card-body">
                            <canvas id="topQueries" height="160"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <footer class="dashboard-footer">
            <div class="footer-content">
                <div class="system-info">
                    <span><i class="fas fa-database"></i> Database: Connected</span>
                    <span><i class="fas fa-clock"></i> Last Updated: <span id="lastUpdated">--:--:--</span></span>
                </div>
                <div class="copyright">© <?php echo date('Y'); ?> MedTrack Arba Minch | Analytics</div>
            </div>
        </footer>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const userBtn = document.querySelector('.user-btn');
        if (userBtn) {
            userBtn.addEventListener('click', () => document.querySelector('.dropdown-menu').classList.toggle('show'));
        }
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.user-dropdown')) {
                const dd = document.querySelector('.dropdown-menu');
                if (dd) dd.classList.remove('show');
            }
        });
    });

    let searchesChart, topQueriesChart;
    async function loadAnalytics() {
        const res = await fetch('search_analytics_data.php');
        const data = await res.json();
        const ts = new Date();
        document.getElementById('lastUpdated').textContent = ts.toTimeString().slice(0,8);

        const dayCtx = document.getElementById('searchesByDay').getContext('2d');
        const topCtx = document.getElementById('topQueries').getContext('2d');

        const dayConfig = {
            type: 'line',
            data: {
                labels: data.searchesByDay.labels,
                datasets: [{
                    label: 'Searches',
                    data: data.searchesByDay.values,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                    tension: 0.2,
                    fill: true,
                    pointRadius: 2
                }]
            },
            options: {
                responsive: true,
                animation: false,
                plugins: { legend: { display: false }, tooltip: { enabled: true } },
                scales: { y: { beginAtZero: true } }
            }
        };

        const topConfig = {
            type: 'bar',
            data: {
                labels: data.topQueries.labels,
                datasets: [{
                    label: 'Search Count',
                    data: data.topQueries.values,
                    backgroundColor: '#10b981'
                }]
            },
            options: {
                responsive: true,
                animation: false,
                plugins: { legend: { display: false }, tooltip: { enabled: true } },
                scales: { y: { beginAtZero: true } }
            }
        };

        if (!searchesChart) { searchesChart = new Chart(dayCtx, dayConfig); } else { searchesChart.data = dayConfig.data; searchesChart.update('none'); }
        if (!topQueriesChart) { topQueriesChart = new Chart(topCtx, topConfig); } else { topQueriesChart.data = topConfig.data; topQueriesChart.update('none'); }
    }

    loadAnalytics();
    setInterval(loadAnalytics, 60000);
    </script>
</body>
</html>
