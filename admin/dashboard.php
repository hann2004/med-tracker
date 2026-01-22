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
$user_id = $_SESSION['user_id'];

// Get comprehensive admin statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
    'active_pharmacies' => $conn->query("SELECT COUNT(*) as count FROM pharmacies WHERE is_active = 1")->fetch_assoc()['count'],
    'pending_verifications' => $conn->query("SELECT COUNT(*) as count FROM pharmacies WHERE is_verified = 0")->fetch_assoc()['count'],
    'total_medicines' => $conn->query("SELECT COUNT(*) as count FROM medicines")->fetch_assoc()['count'],
    'total_categories' => $conn->query("SELECT COUNT(*) as count FROM medicine_categories")->fetch_assoc()['count'],
    'total_reviews_all' => $conn->query("SELECT COUNT(*) as count FROM reviews_and_ratings")->fetch_assoc()['count'],
    'total_searches' => $conn->query("SELECT COUNT(*) as count FROM searches WHERE DATE(search_date) = CURDATE()")->fetch_assoc()['count'],
    'total_requests' => $conn->query("SELECT COUNT(*) as count FROM medicine_requests WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'],
    'recent_reviews' => $conn->query("SELECT COUNT(*) as count FROM reviews_and_ratings WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['count'],
    'system_health' => $conn->query("SELECT COUNT(*) as count FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)")->fetch_assoc()['count']
];

// Get recent activities
$recentActivities = $conn->query("
    SELECT 
        al.*,
        u.username,
        u.user_type
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.created_at DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Get expiring medicines
$expiringMedicines = $conn->query("
    SELECT 
        m.medicine_name,
        p.pharmacy_name,
        pi.expiry_date,
        pi.quantity,
        DATEDIFF(pi.expiry_date, CURDATE()) as days_left
    FROM pharmacy_inventory pi
    JOIN medicines m ON pi.medicine_id = m.medicine_id
    JOIN pharmacies p ON pi.pharmacy_id = p.pharmacy_id
    WHERE pi.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY pi.expiry_date ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get popular searches
$popularSearches = $conn->query("
    SELECT 
        search_query,
        COUNT(*) as search_count,
        COUNT(DISTINCT user_id) as unique_users,
        MAX(search_date) as last_searched
    FROM searches
    WHERE search_query IS NOT NULL AND search_query != ''
    GROUP BY search_query
    ORDER BY search_count DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get recent medicine requests
$recentRequests = $conn->query("
    SELECT mr.*, m.medicine_name, u.username, p.pharmacy_name
    FROM medicine_requests mr
    JOIN medicines m ON mr.medicine_id = m.medicine_id
    JOIN users u ON mr.user_id = u.user_id
    LEFT JOIN pharmacies p ON mr.pharmacy_id = p.pharmacy_id
    ORDER BY mr.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MedTrack Arba Minch</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Admin CSS -->
    <link rel="stylesheet" href="../styles/admin.css?v=20260111">
    
    <style>
        :root {
            --admin-primary: #4a5568;
            --admin-secondary: #6b7280;
            --admin-success: #7ccab3;
            --admin-warning: #f0b429;
            --admin-info: #6b7280;
            --admin-dark: #1f2a3d;
            --admin-light: #f4f7fb;
            --admin-border: #d9e1ed;
        }
        
        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            border-left: 4px solid #e2e8f0;
            transition: transform 0.2s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .metric-card.success { border-left-color: var(--admin-success); }
        .metric-card.warning { border-left-color: var(--admin-warning); }
        .metric-card.info { border-left-color: var(--admin-info); }
        
        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--admin-dark);
            line-height: 1;
        }
        
        .metric-label {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .metric-change {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .metric-change.positive { background: #d1fae5; color: var(--admin-success); }
        .metric-change.negative { background: #fee2e2; color: var(--admin-warning); }
    </style>
</head>
<body class="admin-dashboard">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="logo">
                <i class="fas fa-capsules"></i>
                <span>MedTrack</span>
            </a>
            <div class="user-info">
                <div class="avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div>
                    <h4><?php echo htmlspecialchars($_SESSION['full_name']); ?></h4>
                    <span class="user-role">Administrator</span>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <div class="menu-section">
                <span class="section-title">Main</span>
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="menu-section">
                <span class="section-title">Management</span>
                <a href="manage_users.php" class="menu-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                    <span class="badge"><?php echo $stats['total_users']; ?></span>
                </a>
                <a href="manage_pharmacies.php" class="menu-item">
                    <i class="fas fa-clinic-medical"></i>
                    <span>Pharmacies</span>
                    <span class="badge"><?php echo $stats['active_pharmacies']; ?></span>
                </a>
                <a href="manage_medicines.php" class="menu-item">
                    <i class="fas fa-pills"></i>
                    <span>Medicines</span>
                    <span class="badge"><?php echo $stats['total_medicines']; ?></span>
                </a>
                <a href="categories.php" class="menu-item">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                    <span class="badge"><?php echo $stats['total_categories']; ?></span>
                </a>
            </div>

            <div class="menu-section">
                <span class="section-title">Verification</span>
                <a href="manage_pharmacies.php" class="menu-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Verify Pharmacies</span>
                    <span class="badge badge-warning"><?php echo $stats['pending_verifications']; ?></span>
                </a>
                <a href="reviews.php" class="menu-item">
                    <i class="fas fa-star"></i>
                    <span>Reviews</span>
                    <span class="badge"><?php echo $stats['total_reviews_all']; ?></span>
                </a>
            </div>

            <div class="menu-section">
                <span class="section-title">Analytics</span>
                <a href="reports.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="search_analytics.php" class="menu-item">
                    <i class="fas fa-search"></i>
                    <span>Search Analytics</span>
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
            <div class="topbar-right" style="margin-left: auto;">
                <div class="user-dropdown">
                    <button class="user-btn">
                        <div class="avatar-sm">
                            <i class="fas fa-user-shield"></i>
                        </div>
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
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Stats Overview -->
            <div class="stats-overview">
                <div class="stats-header">
                    <h2>System Overview</h2>
                    <div class="date-range">
                        <select class="date-select">
                            <option>Today</option>
                            <option>Last 7 Days</option>
                            <option>Last 30 Days</option>
                            <option>This Year</option>
                        </select>
                    </div>
                </div>
                
                <div class="stats-grid">
                    <!-- Total Users -->
                    <div class="metric-card">
                        <div class="metric-header">
                            <div class="metric-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                        <div class="metric-value"><?php echo number_format($stats['total_users']); ?></div>
                        <div class="metric-label">
                            <span>Total Users</span>
                        </div>
                    </div>
                    
                    <!-- Active Pharmacies -->
                    <div class="metric-card success">
                        <div class="metric-header">
                            <div class="metric-icon">
                                <i class="fas fa-clinic-medical"></i>
                            </div>
                        </div>
                        <div class="metric-value"><?php echo $stats['active_pharmacies']; ?></div>
                        <div class="metric-label">
                            <span>Active Pharmacies</span>
                        </div>
                    </div>
                    
                    <!-- Pending Verifications -->
                    <div class="metric-card warning">
                        <div class="metric-header">
                            <div class="metric-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="metric-value"><?php echo $stats['pending_verifications']; ?></div>
                        <div class="metric-label">
                            <span>Pending Verifications</span>
                        </div>
                    </div>
                    
                    <!-- Daily Searches -->
                    <div class="metric-card info">
                        <div class="metric-header">
                            <div class="metric-icon">
                                <i class="fas fa-search"></i>
                            </div>
                        </div>
                        <div class="metric-value"><?php echo $stats['total_searches']; ?></div>
                        <div class="metric-label">
                            <span>Today's Searches</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Activity & Requests -->
            <div class="simple-grid">
                <!-- Recent Reviews Card -->
                <div class="section-card">
                    <div class="card-header" style="justify-content: space-between;">
                        <h3>Recent Reviews</h3>
                        <a href="reviews.php" class="btn-link">See all</a>
                    </div>
                    <div class="card-body">
                        <?php
                        $recentReviews = $conn->query('SELECT r.*, m.medicine_name, u.username, u.profile_image, p.pharmacy_name FROM reviews_and_ratings r LEFT JOIN medicines m ON r.medicine_id = m.medicine_id LEFT JOIN users u ON r.user_id = u.user_id LEFT JOIN pharmacies p ON r.pharmacy_id = p.pharmacy_id ORDER BY r.created_at DESC LIMIT 3')->fetch_all(MYSQLI_ASSOC);
                        if (count($recentReviews) === 0): ?>
                            <div style="color:#888;">No reviews yet.</div>
                        <?php else: ?>
                            <?php foreach ($recentReviews as $rev): ?>
                                <div class="review" style="display:flex; gap:14px; align-items:flex-start; background:var(--clinical-light); border:1px solid var(--clinical-border); border-radius:12px; padding:14px; margin-bottom:14px;">
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
                                            <strong><?php echo htmlspecialchars($rev['username']); ?></strong>
                                            <span style="color:#f0b429; font-size:1.1rem; font-weight:700;">
                                                <?php echo str_repeat('★', (int)$rev['rating']); ?><?php echo str_repeat('☆', max(0, 5-(int)$rev['rating'])); ?>
                                            </span>
                                            <span style="color:#475569; font-size:0.95rem;">for <?php echo htmlspecialchars($rev['medicine_name']); ?></span>
                                            <span style="color:#475569; font-size:0.95rem;">at <?php echo htmlspecialchars($rev['pharmacy_name']); ?></span>
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

                <!-- Recent Reports Card -->
                <div class="section-card">
                    <div class="card-header" style="justify-content: space-between;">
                        <h3>Recent Reports</h3>
                        <a href="reports.php" class="btn-link">See all</a>
                    </div>
                    <div class="card-body">
                        <?php
                        $recentReports = $conn->query('SELECT r.*, u.username, u.profile_image, p.pharmacy_name, m.medicine_name FROM reports r LEFT JOIN users u ON r.user_id = u.user_id LEFT JOIN pharmacies p ON r.pharmacy_id = p.pharmacy_id LEFT JOIN medicines m ON r.medicine_id = m.medicine_id ORDER BY r.created_at DESC LIMIT 3')->fetch_all(MYSQLI_ASSOC);
                        if (count($recentReports) === 0): ?>
                            <div style="color:#888;">No reports yet.</div>
                        <?php else: ?>
                            <?php foreach ($recentReports as $rep): ?>
                                <div class="review" style="display:flex; gap:14px; align-items:flex-start; background:var(--clinical-light); border:1px solid var(--clinical-border); border-radius:12px; padding:14px; margin-bottom:14px;">
                                    <div style="flex-shrink:0;">
                                        <?php if (!empty($rep['profile_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($rep['profile_image']); ?>" alt="avatar" style="width:44px; height:44px; border-radius:50%; object-fit:cover; border:2px solid #eaf4ff;">
                                        <?php else: ?>
                                            <div style="width:44px; height:44px; border-radius:50%; background:#eaf4ff; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:#4b5b70; border:2px solid #eaf4ff;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex:1;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <strong><?php echo htmlspecialchars($rep['username']); ?></strong>
                                            <span class="badge badge-info" style="margin-left:8px;">Type: <?php echo htmlspecialchars($rep['report_type']); ?></span>
                                            <?php if (!empty($rep['medicine_name'])): ?>
                                                <span style="color:#475569; font-size:0.95rem;">on <?php echo htmlspecialchars($rep['medicine_name']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($rep['pharmacy_name'])): ?>
                                                <span style="color:#475569; font-size:0.95rem;">at <?php echo htmlspecialchars($rep['pharmacy_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="margin:6px 0 2px 0; color:#102542; font-size:1.05rem; font-weight:500;">
                                            <?php echo htmlspecialchars($rep['description'] ?? ''); ?>
                                        </div>
                                        <div style="font-size:0.85rem; color:#888;">
                                            <i class="fas fa-clock"></i> <?php echo date('M d, Y', strtotime($rep['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Medicine Requests (unchanged) -->
                <div class="section-card">
                    <div class="card-header">
                        <h3>Recent Medicine Requests</h3>
                        <a href="manage_requests.php" class="btn-link">Manage All</a>
                    </div>
                    <div class="card-body">
                        <div class="requests-list">
                            <?php foreach($recentRequests as $request): ?>
                            <div class="request-item">
                                <div class="request-info">
                                    <div class="request-title">
                                        <span class="medicine-name"><?php echo htmlspecialchars($request['medicine_name']); ?></span>
                                        <span class="urgency-badge <?php echo $request['urgency_level']; ?>">
                                            <?php echo ucfirst($request['urgency_level']); ?>
                                        </span>
                                    </div>
                                    <div class="request-details">
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($request['username']); ?></span>
                                        <span><i class="fas fa-store"></i> <?php echo $request['pharmacy_name'] ?? 'Any Pharmacy'; ?></span>
                                        <span><i class="fas fa-clock"></i> <?php echo time_elapsed_string($request['created_at']); ?></span>
                                    </div>
                                </div>
                                <div class="request-status">
                                    <span class="status-badge <?php echo $request['request_status']; ?>">
                                        <?php echo ucfirst($request['request_status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="quick-stats">
                <!-- Expiring Medicines -->
                <div class="stat-card">
                    <div class="stat-header">
                        <h3><i class="fas fa-clock text-warning"></i> Expiring Soon</h3>
                        <span class="badge badge-warning"><?php echo count($expiringMedicines); ?> items</span>
                    </div>
                    <div class="stat-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Medicine</th>
                                        <th>Pharmacy</th>
                                        <th>Expiry Date</th>
                                        <th>Days Left</th>
                                        <th>Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($expiringMedicines as $medicine): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($medicine['medicine_name']); ?></td>
                                        <td><?php echo htmlspecialchars($medicine['pharmacy_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($medicine['expiry_date'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $medicine['days_left'] <= 7 ? 'badge-danger' : 'badge-warning'; ?>">
                                                <?php echo $medicine['days_left']; ?> days
                                            </span>
                                        </td>
                                        <td><?php echo $medicine['quantity']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Popular Searches -->
                <div class="stat-card">
                    <div class="stat-header">
                        <h3><i class="fas fa-search text-info"></i> Popular Searches</h3>
                        <span class="badge badge-info">Top 5</span>
                    </div>
                    <div class="stat-body">
                        <div class="search-list">
                            <?php foreach($popularSearches as $search): ?>
                            <div class="search-item">
                                <div class="search-term">
                                    <i class="fas fa-search"></i>
                                    <span><?php echo htmlspecialchars($search['search_query']); ?></span>
                                </div>
                                <div class="search-stats">
                                    <span class="search-count"><?php echo $search['search_count']; ?> searches</span>
                                    <span class="search-users"><?php echo $search['unique_users']; ?> users</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="dashboard-footer">
            <div class="footer-content">
                <div class="system-info">
                    <span><i class="fas fa-database"></i> Database: Connected</span>
                    <span><i class="fas fa-server"></i> Server: Online</span>
                    <span><i class="fas fa-clock"></i> Last Updated: <?php echo date('H:i:s'); ?></span>
                </div>
                <div class="copyright">
                    © <?php echo date('Y'); ?> MedTrack Arba Minch | Admin Dashboard v1.0
                </div>
            </div>
        </footer>
    </div>
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle sidebar (guard if toggle exists)
            const toggleBtn = document.querySelector('.sidebar-toggle');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('collapsed');
                    document.querySelector('.main-content').classList.toggle('expanded');
                });
            }

            // User dropdown (robust: scope to each dropdown, guard existence)
            document.querySelectorAll('.user-dropdown').forEach(function(dd) {
                const btn = dd.querySelector('.user-btn');
                const menu = dd.querySelector('.dropdown-menu');
                if (btn && menu) {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        menu.classList.toggle('show');
                    });
                }
            });

            // Close any open dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.closest('.user-dropdown')) {
                    document.querySelectorAll('.dropdown-menu.show').forEach(function(m) { m.classList.remove('show'); });
                }
            });
        });
        
        // Auto-refresh system status
        function updateSystemStatus() {
            const timeElement = document.querySelector('.system-info span:last-child');
            if (timeElement) {
                const now = new Date();
                timeElement.innerHTML = `<i class="fas fa-clock"></i> Last Updated: ${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}`;
            }
        }
        
        setInterval(updateSystemStatus, 30000); // Update every 30 seconds
    </script>
</body>
</html>

<?php
// Helper functions
function getActivityIcon($actionType) {
    $icons = [
        'LOGIN' => 'sign-in-alt',
        'LOGOUT' => 'sign-out-alt',
        'CREATE_USER' => 'user-plus',
        'UPDATE_INVENTORY' => 'boxes',
        'SEARCH' => 'search',
        'CREATE_REVIEW' => 'star',
        'CREATE_REQUEST' => 'shopping-cart'
    ];
    return $icons[$actionType] ?? 'bell';
}

function getActivityDescription($activity) {
    switch ($activity['action_type']) {
        case 'LOGIN':
            return 'Logged into the system';
        case 'CREATE_USER':
            return 'Created a new user account';
        case 'UPDATE_INVENTORY':
            return 'Updated pharmacy inventory';
        case 'SEARCH':
            return 'Searched for medicine';
        default:
            return 'Performed system action';
    }
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    $string = [
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second'
    ];
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }
    
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>