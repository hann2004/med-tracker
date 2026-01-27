<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_functions.php';

// Check pharmacy authentication and verification
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'pharmacy') {
    header('Location: ../login.php');
    exit();
}

if (empty($_SESSION['is_verified'])) {
    header('Location: pending.php');
    exit();
}

$conn = getDatabaseConnection();
$user_id = $_SESSION['user_id'];

// Get pharmacy info
$pharmacyQuery = $conn->prepare("
    SELECT p.*, u.full_name as owner_name 
    FROM pharmacies p 
    JOIN users u ON p.owner_id = u.user_id 
    WHERE p.owner_id = ?
");
$pharmacyQuery->bind_param("i", $user_id);
$pharmacyQuery->execute();
$pharmacy = $pharmacyQuery->get_result()->fetch_assoc();

if (!$pharmacy) {
    header('Location: ../login.php');
    exit();
}

// Get pharmacy statistics (inventory-focused only)
$stats = $conn->query("
    SELECT 
        COUNT(DISTINCT pi.medicine_id) as total_medicines,
        COALESCE(SUM(pi.quantity),0) as total_stock,
        COALESCE(SUM(pi.quantity * pi.price),0) as inventory_value
    FROM pharmacy_inventory pi
    WHERE pi.pharmacy_id = {$pharmacy['pharmacy_id']}
")->fetch_assoc();
// Chart datasets
$categoryBreakdown = $conn->query("
    SELECT 
        COALESCE(c.category_name, 'Uncategorized') AS category_name,
        COALESCE(SUM(pi.quantity * pi.price), 0) AS value
    FROM pharmacy_inventory pi
    JOIN medicines m ON pi.medicine_id = m.medicine_id
    LEFT JOIN medicine_categories c ON m.category_id = c.category_id
    WHERE pi.pharmacy_id = {$pharmacy['pharmacy_id']}
    GROUP BY c.category_id, c.category_name
    ORDER BY value DESC
    LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Top medicines for bar chart
// Bar chart removed


// Get low stock items
$lowStock = $conn->query("
    SELECT 
        pi.*,
        m.medicine_name,
        m.generic_name,
        m.image_url,
        (pi.quantity <= pi.reorder_level) as is_low_stock
    FROM pharmacy_inventory pi
    JOIN medicines m ON pi.medicine_id = m.medicine_id
    WHERE pi.pharmacy_id = {$pharmacy['pharmacy_id']}
    AND pi.quantity <= pi.reorder_level
    ORDER BY pi.quantity ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get expiring medicines
$expiring = $conn->query("
    SELECT 
        pi.*,
        m.medicine_name,
        DATEDIFF(pi.expiry_date, CURDATE()) as days_left
    FROM pharmacy_inventory pi
    JOIN medicines m ON pi.medicine_id = m.medicine_id
    WHERE pi.pharmacy_id = {$pharmacy['pharmacy_id']}
    AND pi.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY pi.expiry_date ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// No online orders/requests shown on dashboard
$recentRequests = [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Dashboard - <?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?></title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Global Styles -->
    <link rel="stylesheet" href="../styles/main.css">
    
    <!-- Pharmacy CSS -->
    <link rel="stylesheet" href="../styles/pharmacy.css?v=20260127">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
</head>
<body class="pharmacy-dashboard">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="logo">
                <i class="fas fa-capsules"></i>
                <span>MedTrack</span>
            </a>
            <div class="pharmacy-info">
                <div class="avatar">
                    <i class="fas fa-clinic-medical"></i>
                </div>
                <div>
                    <h4><?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?></h4>
                    <span class="user-role">Pharmacy Owner</span>
                    <span class="pharmacy-status <?php echo $pharmacy['is_verified'] ? 'verified' : 'pending'; ?>">
                        <?php echo $pharmacy['is_verified'] ? '✓ Verified' : 'Pending Verification'; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <div class="menu-section">
                <span class="section-title">Dashboard</span>
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Overview</span>
                </a>
            </div>
            
            <div class="menu-section">
                <span class="section-title">Inventory</span>
                <a href="inventory.php" class="menu-item">
                    <i class="fas fa-boxes"></i>
                    <span>All Medicines</span>
                    <span class="badge"><?php echo $stats['total_medicines']; ?></span>
                </a>
                <a href="inventory.php?view=add" class="menu-item">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add Medicine</span>
                </a>
                <a href="inventory.php?view=low" class="menu-item">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Low Stock</span>
                    <span class="badge badge-warning"><?php echo count($lowStock); ?></span>
                </a>
                <a href="inventory.php?view=expiring" class="menu-item">
                    <i class="fas fa-clock"></i>
                    <span>Expiring Soon</span>
                    <span class="badge badge-warning"><?php echo count($expiring); ?></span>
                </a>
            </div>
            
            <div class="menu-section">
                <span class="section-title">Customer Relations</span>
                <a href="reviews.php" class="menu-item">
                    <i class="fas fa-star"></i>
                    <span>Reviews & Ratings</span>
                    <span class="badge"><?php echo $pharmacy['review_count']; ?></span>
                </a>
            </div>
            
            <div class="menu-section">
                <span class="section-title">System</span>
                <a href="../logout.php" class="menu-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-info">
                    <h1 class="page-title">Dashboard</h1>
                    <div class="breadcrumb">
                        <span><?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?></span>
                        <span class="separator">/</span>
                        <span class="active">Overview</span>
                    </div>
                </div>
            </div>
            <div class="topbar-right">
                <div class="topbar-actions">
                    <button class="action-btn add-btn" onclick="window.location.href='inventory.php?view=add'">
                        <i class="fas fa-plus"></i>
                        <span>Add Medicine</span>
                    </button>
                    <div class="user-dropdown">
                        <button class="user-btn">
                            <div class="avatar-sm">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <span><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <div class="divider"></div>
                            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Pharmacy Header -->
            <div class="pharmacy-header">
                <div class="header-content">
                    <div class="pharmacy-title">
                        <h1><?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?></h1>
                        <div class="pharmacy-meta">
                            <span class="pharmacy-badge">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($pharmacy['address']); ?>
                            </span>
                            <span class="pharmacy-badge">
                                <i class="fas fa-phone"></i>
                                <?php echo htmlspecialchars($pharmacy['phone']); ?>
                            </span>
                            <span class="pharmacy-badge">
                                <i class="fas fa-star"></i>
                                <?php echo number_format($pharmacy['rating'], 1); ?> (<?php echo $pharmacy['review_count']; ?> reviews)
                            </span>
                        </div>
                    </div>
                    <div class="pharmacy-actions">
                        <button class="btn-light" onclick="window.location.href='profile.php'">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                        <button class="btn-light" onclick="window.location.href='inventory.php'">
                            <i class="fas fa-box"></i> Go to Inventory
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Alerts Section -->
            <div class="alerts-section">
                <?php if(count($lowStock) > 0): ?>
                <div class="stock-alert">
                    <div class="alert-header">
                        <i class="fas fa-exclamation-triangle text-danger"></i>
                        <strong>Low Stock Alert</strong>
                        <span class="alert-count"><?php echo count($lowStock); ?> items</span>
                    </div>
                    <div class="alert-items">
                        <?php foreach($lowStock as $item): ?>
                        <span class="alert-item">
                            <?php echo htmlspecialchars($item['medicine_name']); ?> (<?php echo $item['quantity']; ?> left)
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <a href="inventory.php?view=low" class="alert-link">View All →</a>
                </div>
                <?php endif; ?>
                
                <?php if(count($expiring) > 0): ?>
                <div class="expiry-alert">
                    <div class="alert-header">
                        <i class="fas fa-clock text-warning"></i>
                        <strong>Expiring Soon</strong>
                        <span class="alert-count"><?php echo count($expiring); ?> items</span>
                    </div>
                    <div class="alert-items">
                        <?php foreach($expiring as $item): ?>
                        <span class="alert-item">
                            <?php echo htmlspecialchars($item['medicine_name']); ?> (<?php echo $item['days_left']; ?> days)
                        </span>
                        <?php endforeach; ?>
                    </div>
                    <a href="inventory.php?view=expiring" class="alert-link">View All →</a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Inventory Graphs -->
            <div class="stats-overview">
                <div class="stats-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:8px; align-items:start;">
                    <div class="card" style="min-height: 110px; padding: 12px;">
                        <div class="flex" style="margin-bottom:10px;">
                            <h3 style="margin:0;">Inventory Value by Category</h3>
                            <span class="badge">Top categories</span>
                        </div>
                        <canvas id="categoryChart" height="120" style="width:340px;"></canvas>
                            <?php $catColors = ['#a0c4ff','#bdb2ff','#ffc6ff','#caffbf','#ffd6a5','#fdffb6','#9bf6ff','#bde0fe']; ?>
                            <div class="legend-list">
                                <?php foreach ($categoryBreakdown as $i => $row): ?>
                                    <div class="legend-item">
                                        <span class="legend-swatch" style="background: <?php echo $catColors[$i % count($catColors)]; ?>;"></span>
                                        <span><?php echo htmlspecialchars($row['category_name']); ?></span>
                                        <span style="color:#4b5b70;">– ETB <?php echo number_format((float)$row['value'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Inventory CTA -->
            <div class="activity-section" style="margin-top:18px;">
                <div class="table-responsive" style="padding:12px;">
                    <p style="margin:0 0 10px; color:#4b5b70; font-weight:600;">Need to update stock?</p>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button class="action-btn add-btn" onclick="window.location.href='inventory.php?view=add'">
                            <i class="fas fa-plus-circle"></i>
                            <span>Add a Medicine</span>
                        </button>
                        <button class="action-btn" style="border-color: #d9e7f6; background:#f7fbff;" onclick="window.location.href='inventory.php'">
                            <i class="fas fa-list"></i>
                            <span>View Inventory</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recent Reviews Section -->
            <div class="card" style="margin:32px 0 0 0; padding:20px; max-width:700px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
                    <h2 style="margin:0; font-size:1.3rem;">Recent Reviews</h2>
                    <a href="reviews.php" class="badge" style="text-decoration:none; font-size:0.95rem;">See all</a>
                </div>
                <?php
                $pharmacyId = $pharmacy['pharmacy_id'];
                $recentReviews = $conn->query("SELECT r.*, u.username, u.full_name, u.profile_image, m.medicine_name FROM reviews_and_ratings r LEFT JOIN users u ON r.user_id = u.user_id LEFT JOIN medicines m ON r.medicine_id = m.medicine_id WHERE r.pharmacy_id = $pharmacyId ORDER BY r.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
                if (count($recentReviews) === 0): ?>
                    <div style="color:#888;">No reviews for your pharmacy yet.</div>
                <?php else: ?>
                    <?php foreach ($recentReviews as $rev): ?>
                        <div class="review" style="background:#f7fbff; border:1px solid #d9e7f6; border-radius:12px; padding:14px; margin-bottom:14px; display:flex; gap:14px; align-items:flex-start;">
                            <div style="flex-shrink:0;">
                                <?php if (!empty($rev['profile_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($rev['profile_image']); ?>" alt="avatar" style="width:44px; height:44px; border-radius:50%; object-fit:cover; border:2px solid #eaf4ff;">
                                <?php else: ?>
                                    <div style="width:44px; height:44px; border-radius:50%; background:#eaf4ff; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#4b5b70; border:2px solid #eaf4ff;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <strong><?php echo htmlspecialchars($rev['full_name'] ?: $rev['username']); ?></strong>
                                    <span style="color:#f0b429; font-size:1.1rem; font-weight:700;">
                                        <?php echo str_repeat('★', (int)$rev['rating']); ?><?php echo str_repeat('☆', max(0, 5-(int)$rev['rating'])); ?>
                                    </span>
                                    <span style="color:#475569; font-size:0.95rem;">for <?php echo htmlspecialchars($rev['medicine_name']); ?></span>
                                </div>
                                <div style="margin:6px 0 2px 0; color:#102542; font-size:1.05rem; font-weight:500;">
                                    <?php echo htmlspecialchars($rev['review_title'] ?? ''); ?>
                                </div>
                                <div style="color:#4b5b70; font-size:0.98rem; margin-bottom:4px;">
                                    <?php echo nl2br(htmlspecialchars($rev['review_text'])); ?>
                                </div>
                                <div style="font-size:0.85rem; color:#888;">
                                    <i class="fas fa-clock"></i> <?php echo date('M d, Y', strtotime($rev['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="dashboard-footer">
            <div class="footer-content">
                <div class="pharmacy-status">
                    <span class="status-indicator online"></span>
                    <span>Pharmacy: <?php echo $pharmacy['is_active'] ? 'Active' : 'Inactive'; ?></span>
                    <span class="separator">|</span>
                    <span class="last-updated" data-time="<?php echo date('H:i:s'); ?>">Last Updated: <?php echo date('H:i:s'); ?></span>
                </div>
                <div class="support-link">
                    <a href="../contact.php"><i class="fas fa-headset"></i> Support</a>
                </div>
            </div>
        </footer>
    </div>
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar
            document.querySelector('.sidebar-toggle').addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('collapsed');
                document.querySelector('.main-content').classList.toggle('expanded');
            });
            
            // User dropdown
            document.querySelector('.user-btn').addEventListener('click', function() {
                document.querySelector('.dropdown-menu').classList.toggle('show');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.closest('.user-dropdown')) {
                    document.querySelector('.dropdown-menu').classList.remove('show');
                }
            });

            // Charts data from PHP
            const categoryLabels = <?php echo json_encode(array_map(function($r){ return $r['category_name']; }, $categoryBreakdown)); ?>;
            const categoryValues = <?php echo json_encode(array_map(function($r){ return (float)$r['value']; }, $categoryBreakdown)); ?>;
            // Bar chart removed

            let catChart = null;
            if (document.getElementById('categoryChart')) {
                const catCtx = document.getElementById('categoryChart').getContext('2d');
                catChart = new Chart(catCtx, {
                    type: 'doughnut',
                    data: {
                        labels: categoryLabels,
                        datasets: [{
                            data: categoryValues,
                            backgroundColor: ['#a0c4ff','#bdb2ff','#ffc6ff','#caffbf','#ffd6a5','#fdffb6','#9bf6ff','#bde0fe'],
                            borderColor: '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                            responsive: false,
                            maintainAspectRatio: false,
                            animation: { duration: 600, easing: 'easeOutQuart' },
                        plugins: {
                                legend: { display: false },
                                tooltip: { enabled: true, callbacks: { label: (ctx) => `ETB ${ctx.formattedValue}` } }
                        },
                        cutout: '70%'
                    }
                });
            }

            // Bar chart removed

            // Auto-refresh charts every 60s from live data
            setInterval(async () => {
                try {
                    const res = await fetch('dashboard_data.php');
                    if (!res.ok) return;
                    const data = await res.json();
                    if (catChart && Array.isArray(data.categoryLabels) && Array.isArray(data.categoryValues)) {
                        catChart.data.labels = data.categoryLabels;
                        catChart.data.datasets[0].data = data.categoryValues.map(v => parseFloat(v));
                        catChart.update('none');
                    }
                    // Bar chart removed
                } catch (e) {
                    // Silent fail
                }
            }, 60000);
        });
        
        // Auto-refresh pharmacy status
        setInterval(() => {
            const timeElement = document.querySelector('.pharmacy-status .last-updated');
            if (timeElement) {
                const now = new Date();
                if (Number.isNaN(now.getTime())) {
                    timeElement.remove();
                    return;
                }
                const hh = now.getHours().toString().padStart(2, '0');
                const mm = now.getMinutes().toString().padStart(2, '0');
                const ss = now.getSeconds().toString().padStart(2, '0');
                timeElement.textContent = `Last Updated: ${hh}:${mm}:${ss}`;
            }
        }, 30000);
    </script>
</body>
</html>