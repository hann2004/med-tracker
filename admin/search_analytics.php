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
            <div id="analytics-loading" style="display:none;position:fixed;z-index:1000;top:0;left:0;width:100vw;height:100vh;background:rgba(24,28,35,0.18);backdrop-filter:blur(2px);align-items:center;justify-content:center;">
                <div style="background:#232733;padding:32px 38px;border-radius:18px;box-shadow:0 8px 32px rgba(16,37,66,0.18);display:flex;flex-direction:column;align-items:center;gap:18px;">
                    <div class="spinner" style="width:48px;height:48px;border:6px solid #b8dcff;border-top:6px solid #2563eb;border-radius:50%;animation:spin 1s linear infinite;"></div>
                    <div style="color:#b8dcff;font-size:1.1rem;letter-spacing:0.04em;">Loading analytics...</div>
                </div>
            </div>
            <div class="stats-overview">
                <div class="stats-header">
                    <h2>Search Analytics</h2>
                    <div class="date-range">
                        <span class="badge">Today: <?php echo $todaySearches; ?> searches</span>
                        <span class="badge">Unique queries: <?php echo $uniqueQueries; ?></span>
                        <span class="badge">Unique users: <?php echo $uniqueUsers; ?></span>
                        <span class="time-selector" style="margin-left:18px;">
                            <button class="time-btn active" data-days="7">7d</button>
                            <button class="time-btn" data-days="14">14d</button>
                            <button class="time-btn" data-days="30">30d</button>
                        </span>
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

    <style>
    @keyframes spin { 0% { transform: rotate(0deg);} 100% { transform: rotate(360deg);} }
    </style>
    <script>
    // Dropdown menu logic
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

    // Chart.js modern enhancements
    let searchesChart, topQueriesChart;
    // Value label plugin for Chart.js v4+
    const valueLabelPlugin = {
        id: 'valueLabel',
        afterDatasetsDraw(chart) {
            const {ctx, data, chartArea, scales} = chart;
            ctx.save();
            chart.data.datasets.forEach((dataset, i) => {
                chart.getDatasetMeta(i).data.forEach((point, j) => {
                    if (point && dataset.data[j] != null) {
                        ctx.font = 'bold 12px Inter, sans-serif';
                        ctx.fillStyle = '#2563eb';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'bottom';
                        let val = dataset.data[j];
                        if (typeof val === 'number') val = val.toLocaleString();
                        ctx.fillText(val, point.x, point.y - 8);
                    }
                });
            });
            ctx.restore();
        }
    };

    // Helper for gradient backgrounds
    function getLineGradient(ctx, area) {
        if (!area) return 'rgba(37,99,235,0.12)';
        const grad = ctx.createLinearGradient(0, area.top, 0, area.bottom);
        grad.addColorStop(0, 'rgba(37,99,235,0.32)');
        grad.addColorStop(1, 'rgba(37,99,235,0.05)');
        return grad;
    }
    function getBarGradient(ctx, area) {
        if (!area) return '#10b981';
        const grad = ctx.createLinearGradient(0, area.top, 0, area.bottom);
        grad.addColorStop(0, '#10b981');
        grad.addColorStop(1, '#2563eb');
        return grad;
    }

    let selectedDays = 7;
    async function loadAnalytics() {
        const loading = document.getElementById('analytics-loading');
        loading.style.display = 'flex';
        document.body.style.cursor = 'progress';
        try {
            const res = await fetch('search_analytics_data.php?days=' + selectedDays);
            const data = await res.json();
            const ts = new Date();
            document.getElementById('lastUpdated').textContent = ts.toTimeString().slice(0,8);

            const dayCanvas = document.getElementById('searchesByDay');
            const topCanvas = document.getElementById('topQueries');
            const dayCtx = dayCanvas.getContext('2d');
            const topCtx = topCanvas.getContext('2d');

            // Gradients must be created after chart area is known, so use callbacks
            const dayConfig = {
                type: 'line',
                data: {
                    labels: data.searchesByDay.labels,
                    datasets: [{
                        label: 'Searches',
                        data: data.searchesByDay.values,
                        borderColor: '#2563eb',
                        backgroundColor: (ctx) => getLineGradient(ctx.chart.ctx, ctx.chart.chartArea),
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#2563eb',
                        pointHoverRadius: 7,
                        pointHoverBackgroundColor: '#10b981',
                        borderWidth: 3,
                        shadowOffsetX: 0,
                        shadowOffsetY: 2,
                        shadowBlur: 8,
                        shadowColor: 'rgba(37,99,235,0.18)'
                    }]
                },
                options: {
                    responsive: true,
                    animation: { duration: 1200, easing: 'easeOutQuart' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: true,
                            backgroundColor: '#232733',
                            titleColor: '#b8dcff',
                            bodyColor: '#e5e7eb',
                            borderColor: '#2563eb',
                            borderWidth: 1,
                            padding: 12,
                            callbacks: {
                                label: ctx => `Searches: ${ctx.parsed.y}`
                            }
                        },
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(37,99,235,0.08)' } },
                        x: { grid: { display: false } }
                    },
                    hover: { mode: 'nearest', intersect: true }
                },
                plugins: [valueLabelPlugin]
            };

            const topConfig = {
                type: 'bar',
                data: {
                    labels: data.topQueries.labels,
                    datasets: [{
                        label: 'Search Count',
                        data: data.topQueries.values,
                        backgroundColor: (ctx) => getBarGradient(ctx.chart.ctx, ctx.chart.chartArea),
                        borderRadius: 8,
                        borderSkipped: false,
                        maxBarThickness: 38,
                        minBarLength: 2
                    }]
                },
                options: {
                    responsive: true,
                    animation: { duration: 1200, easing: 'easeOutQuart' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            enabled: true,
                            backgroundColor: '#232733',
                            titleColor: '#b8dcff',
                            bodyColor: '#e5e7eb',
                            borderColor: '#10b981',
                            borderWidth: 1,
                            padding: 12,
                            callbacks: {
                                label: ctx => `Count: ${ctx.parsed.y}`
                            }
                        },
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(16,185,129,0.08)' } },
                        x: { grid: { display: false } }
                    },
                    hover: { mode: 'nearest', intersect: true }
                },
                plugins: [valueLabelPlugin]
            };

            if (!searchesChart) {
                searchesChart = new Chart(dayCtx, dayConfig);
            } else {
                searchesChart.data = dayConfig.data;
                searchesChart.options = dayConfig.options;
                searchesChart.update();
            }
            if (!topQueriesChart) {
                topQueriesChart = new Chart(topCtx, topConfig);
            } else {
                topQueriesChart.data = topConfig.data;
                topQueriesChart.options = topConfig.options;
                topQueriesChart.update();
            }
        } finally {
            loading.style.display = 'none';
            document.body.style.cursor = '';
        }
    }

    // Time range selector logic
    document.querySelectorAll('.time-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.time-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            selectedDays = parseInt(this.getAttribute('data-days'));
            loadAnalytics();
        });
    });

    loadAnalytics();
    setInterval(loadAnalytics, 60000);
    </script>
</body>
</html>
