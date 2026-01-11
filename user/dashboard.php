<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_functions.php';

// Dashboard deprecated: route users to homepage search
header('Location: ../index.php');
exit();

// Check user authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'user') {
    header('Location: ../login.php');
    exit();
}

$conn = getDatabaseConnection();
$user_id = $_SESSION['user_id'];

// Get user info
$userQuery = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$user = $userQuery->get_result()->fetch_assoc();

// Get user statistics
$stats = $conn->query("
    SELECT 
        COUNT(DISTINCT mr.request_id) as total_requests,
        COUNT(DISTINCT CASE WHEN mr.request_status = 'fulfilled' THEN mr.request_id END) as fulfilled_requests,
        COUNT(DISTINCT rr.review_id) as reviews_written,
        COUNT(DISTINCT s.search_id) as search_count
    FROM users u
    LEFT JOIN medicine_requests mr ON u.user_id = mr.user_id
    LEFT JOIN reviews_and_ratings rr ON u.user_id = rr.user_id
    LEFT JOIN searches s ON u.user_id = s.user_id
    WHERE u.user_id = $user_id
")->fetch_assoc();

// Get recent requests
$recentRequests = $conn->query("
    SELECT 
        mr.*,
        m.medicine_name,
        m.image_url,
        p.pharmacy_name,
        p.address as pharmacy_address
    FROM medicine_requests mr
    JOIN medicines m ON mr.medicine_id = m.medicine_id
    LEFT JOIN pharmacies p ON mr.fulfilled_by_pharmacy = p.pharmacy_id
    WHERE mr.user_id = $user_id
    ORDER BY mr.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get recent searches
$recentSearches = $conn->query("
    SELECT 
        s.*,
        m.medicine_name,
        m.image_url
    FROM searches s
    LEFT JOIN medicines m ON s.medicine_id = m.medicine_id
    WHERE s.user_id = $user_id
    ORDER BY s.search_date DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get saved pharmacies (from reviews)
$savedPharmacies = $conn->query("
    SELECT DISTINCT
        p.pharmacy_id,
        p.pharmacy_name,
        p.address,
        p.rating,
        p.review_count,
        p.featured_image
    FROM reviews_and_ratings rr
    JOIN pharmacies p ON rr.pharmacy_id = p.pharmacy_id
    WHERE rr.user_id = $user_id AND rr.rating >= 4
    LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

// Get nearby pharmacies (gracefully skip if user lat/long not available)
$nearbyPharmacies = [];
try {
    $userLocation = $conn->query("SELECT latitude, longitude FROM users WHERE user_id = $user_id")->fetch_assoc();
    if ($userLocation && !empty($userLocation['latitude']) && !empty($userLocation['longitude'])) {
        $nearbyPharmacies = $conn->query("
            SELECT 
                pharmacy_id,
                pharmacy_name,
                address,
                latitude,
                longitude,
                rating,
                emergency_services,
                delivery_available,
                (6371 * ACOS(
                    COS(RADIANS({$userLocation['latitude']})) * 
                    COS(RADIANS(latitude)) * 
                    COS(RADIANS(longitude) - RADIANS({$userLocation['longitude']})) + 
                    SIN(RADIANS({$userLocation['latitude']})) * 
                    SIN(RADIANS(latitude))
                )) as distance_km
            FROM pharmacies
            WHERE is_active = 1 AND is_verified = 1
            HAVING distance_km <= 5
            ORDER BY distance_km ASC
            LIMIT 3
        ")->fetch_all(MYSQLI_ASSOC);
    }
} catch (mysqli_sql_exception $e) {
    // If latitude/longitude columns are missing, skip nearby calculation
    $nearbyPharmacies = [];
}

// Get notifications
$notifications = $conn->query("
    SELECT *
    FROM notifications
    WHERE user_id = $user_id AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - MedTrack Arba Minch</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- User CSS -->
    <link rel="stylesheet" href="../styles/user.css">
    
    <style>
        :root {
            --user-primary: #3b82f6;
            --user-secondary: #8b5cf6;
            --user-success: #10b981;
            --user-warning: #f59e0b;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, var(--user-primary), var(--user-secondary));
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 1%);
            background-size: 20px 20px;
            opacity: 0.3;
            animation: float 20s linear infinite;
        }
        
        @keyframes float {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .quick-search {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .medicine-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .medicine-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            border-color: var(--user-primary);
        }
        
        .search-input-wrapper { position: relative; width: 100%; }
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            z-index: 40;
            display: none;
            max-height: 260px;
            overflow-y: auto;
        }
        .search-suggestions.show { display: block; }
        .search-suggestions .suggestion-item { padding: 12px 14px; cursor: pointer; display: flex; gap: 10px; align-items: center; }
        .search-suggestions .suggestion-item:hover { background: rgba(59,130,246,0.08); }
    </style>
</head>
<body class="user-dashboard">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="../index.php" class="logo">
                <i class="fas fa-capsules"></i>
                <span>MedTrack</span>
            </a>
            <div class="user-info">
                <div class="avatar">
                    <?php if($user['profile_image'] && $user['profile_image'] !== 'default_avatar.png'): ?>
                    <img src="../uploads/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                    <?php else: ?>
                    <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <span class="user-role">Member</span>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <div class="menu-section">
                <span class="section-title">Dashboard</span>
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="profile.php" class="menu-item">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
                <a href="settings.php" class="menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            
            <div class="menu-section">
                <span class="section-title">Medicine</span>
                <a href="search.php" class="menu-item">
                    <i class="fas fa-search"></i>
                    <span>Search Medicine</span>
                </a>
                <a href="requests.php" class="menu-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>My Requests</span>
                    <span class="badge"><?php echo $stats['total_requests']; ?></span>
                </a>
                <a href="prescriptions.php" class="menu-item">
                    <i class="fas fa-prescription"></i>
                    <span>Prescriptions</span>
                </a>
                <a href="saved.php" class="menu-item">
                    <i class="fas fa-bookmark"></i>
                    <span>Saved Items</span>
                </a>
            </div>
            
            <div class="menu-section">
                <span class="section-title">Pharmacies</span>
                <a href="pharmacies.php" class="menu-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Find Pharmacies</span>
                </a>
                <a href="reviews.php" class="menu-item">
                    <i class="fas fa-star"></i>
                    <span>My Reviews</span>
                    <span class="badge"><?php echo $stats['reviews_written']; ?></span>
                </a>
                <a href="favorites.php" class="menu-item">
                    <i class="fas fa-heart"></i>
                    <span>Favorite Pharmacies</span>
                </a>
            </div>
            
            <div class="menu-section">
                <span class="section-title">Help & Support</span>
                <a href="help.php" class="menu-item">
                    <i class="fas fa-question-circle"></i>
                    <span>Help Center</span>
                </a>
                <a href="contact.php" class="menu-item">
                    <i class="fas fa-headset"></i>
                    <span>Contact Support</span>
                </a>
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
                <h1 class="page-title">My Dashboard</h1>
            </div>
            <div class="topbar-right">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search for medicine..." id="quickSearch">
                </div>
                <div class="topbar-actions">
                    <button class="action-btn notification-btn" id="notificationBtn">
                        <i class="fas fa-bell"></i>
                        <?php if(count($notifications) > 0): ?>
                        <span class="badge"><?php echo count($notifications); ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="action-btn location-btn" onclick="getUserLocation()">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Nearby</span>
                    </button>
                    <div class="user-dropdown">
                        <button class="user-btn">
                            <div class="avatar-sm">
                                <?php if($user['profile_image'] && $user['profile_image'] !== 'default_avatar.png'): ?>
                                <img src="../uploads/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile">
                                <?php else: ?>
                                <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <span><?php echo htmlspecialchars($user['username']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                            <div class="divider"></div>
                            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Notifications Panel -->
        <div class="notifications-panel" id="notificationsPanel">
            <div class="panel-header">
                <h3>Notifications</h3>
                <button class="close-btn" onclick="closeNotifications()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="panel-body">
                <?php if(count($notifications) > 0): ?>
                <div class="notifications-list">
                    <?php foreach($notifications as $notification): ?>
                    <div class="notification-item">
                        <div class="notification-icon">
                            <i class="fas fa-<?php echo getNotificationIcon($notification['notification_type']); ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                            <div class="notification-time">
                                <?php echo time_elapsed_string($notification['created_at']); ?>
                            </div>
                        </div>
                        <button class="mark-read-btn" onclick="markAsRead(<?php echo $notification['notification_id']; ?>)">
                            <i class="fas fa-check"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p>No new notifications</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="panel-footer">
                <a href="notifications.php">View All Notifications</a>
            </div>
        </div>
        
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-content">
                    <h1>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>! 👋</h1>
                    <p class="welcome-text">Find medicines easily across Arba Minch pharmacies</p>
                    <div class="welcome-stats">
                        <div class="stat">
                            <div class="stat-value"><?php echo $stats['total_requests']; ?></div>
                            <div class="stat-label">Medicine Requests</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?php echo $stats['search_count']; ?></div>
                            <div class="stat-label">Searches Made</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?php echo $stats['fulfilled_requests']; ?></div>
                            <div class="stat-label">Fulfilled Requests</div>
                        </div>
                    </div>
                </div>
                <div class="welcome-actions">
                    <button class="btn-light" onclick="window.location.href='search.php'">
                        <i class="fas fa-search"></i> Search Medicine
                    </button>
                    <button class="btn-light" onclick="window.location.href='requests.php'">
                        <i class="fas fa-history"></i> View History
                    </button>
                </div>
            </div>
            
            <!-- Quick Search -->
            <div class="quick-search">
                <h3 class="section-title">Quick Medicine Search</h3>
                <div class="search-form">
                    <div class="search-input-wrapper">
                        <input type="text" placeholder="Enter medicine name..." id="medicineSearch" data-suggest="true">
                        <div class="search-suggestions" data-for="medicineSearch"></div>
                    </div>
                    <select id="locationSelect">
                        <option value="">All Arba Minch</option>
                        <option value="secha">Secha Area</option>
                        <option value="sikela">Sikela Area</option>
                        <option value="center">Town Center</option>
                        <option value="kulfo">Kulfo Area</option>
                    </select>
                    <button class="search-btn" onclick="performSearch()">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
                <div class="popular-searches">
                    <span class="label">Popular:</span>
                    <a href="search.php?q=Paracetamol" class="tag">Paracetamol</a>
                    <a href="search.php?q=Amoxicillin" class="tag">Amoxicillin</a>
                    <a href="search.php?q=Vitamin C" class="tag">Vitamin C</a>
                    <a href="search.php?q=Insulin" class="tag">Insulin</a>
                </div>
            </div>
            
            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Recent Requests -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Recent Requests</h3>
                        <a href="requests.php" class="btn-link">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if(count($recentRequests) > 0): ?>
                        <div class="requests-list">
                            <?php foreach($recentRequests as $request): ?>
                            <div class="request-item">
                                <div class="request-image">
                                    <?php if($request['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($request['image_url']); ?>" alt="<?php echo htmlspecialchars($request['medicine_name']); ?>">
                                    <?php else: ?>
                                    <div class="image-placeholder">
                                        <i class="fas fa-pills"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="request-info">
                                    <div class="medicine-name"><?php echo htmlspecialchars($request['medicine_name']); ?></div>
                                    <div class="request-details">
                                        <?php if($request['pharmacy_name']): ?>
                                        <span class="detail">
                                            <i class="fas fa-store"></i>
                                            <?php echo htmlspecialchars($request['pharmacy_name']); ?>
                                        </span>
                                        <?php endif; ?>
                                        <span class="detail">
                                            <i class="fas fa-clock"></i>
                                            <?php echo time_elapsed_string($request['created_at']); ?>
                                        </span>
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
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-cart"></i>
                            <p>No requests yet</p>
                            <a href="search.php" class="btn-sm">Search Medicine</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Nearby Pharmacies -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-map-marker-alt"></i> Nearby Pharmacies</h3>
                        <a href="pharmacies.php" class="btn-link">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if(count($nearbyPharmacies) > 0): ?>
                        <div class="pharmacies-list">
                            <?php foreach($nearbyPharmacies as $pharmacy): ?>
                            <div class="pharmacy-card">
                                <div class="pharmacy-header">
                                    <div class="pharmacy-name"><?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?></div>
                                    <div class="pharmacy-rating">
                                        <i class="fas fa-star"></i>
                                        <?php echo number_format($pharmacy['rating'], 1); ?>
                                    </div>
                                </div>
                                <div class="pharmacy-details">
                                    <div class="detail">
                                        <i class="fas fa-road"></i>
                                        <?php echo number_format($pharmacy['distance_km'], 1); ?> km away
                                    </div>
                                    <div class="detail">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($pharmacy['address']); ?>
                                    </div>
                                </div>
                                <div class="pharmacy-features">
                                    <?php if($pharmacy['emergency_services']): ?>
                                    <span class="feature-badge emergency">
                                        <i class="fas fa-ambulance"></i> 24/7
                                    </span>
                                    <?php endif; ?>
                                    <?php if($pharmacy['delivery_available']): ?>
                                    <span class="feature-badge delivery">
                                        <i class="fas fa-truck"></i> Delivery
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="pharmacy-actions">
                                    <button class="btn-sm btn-outline" onclick="viewPharmacy(<?php echo $pharmacy['pharmacy_id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn-sm btn-primary" onclick="searchAtPharmacy(<?php echo $pharmacy['pharmacy_id']; ?>)">
                                        <i class="fas fa-search"></i> Search Here
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-map-marker-alt"></i>
                            <p>Allow location access to see nearby pharmacies</p>
                            <button class="btn-sm" onclick="getUserLocation()">
                                <i class="fas fa-location-crosshairs"></i> Use My Location
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Searches -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-search"></i> Recent Searches</h3>
                        <a href="search_history.php" class="btn-link">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if(count($recentSearches) > 0): ?>
                        <div class="searches-list">
                            <?php foreach($recentSearches as $search): ?>
                            <div class="search-item" onclick="repeatSearch('<?php echo htmlspecialchars($search['search_query']); ?>')">
                                <div class="search-icon">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="search-info">
                                    <div class="search-term"><?php echo htmlspecialchars($search['search_query'] ?: $search['medicine_name']); ?></div>
                                    <div class="search-meta">
                                        <span class="meta">
                                            <i class="fas fa-clock"></i>
                                            <?php echo time_elapsed_string($search['search_date']); ?>
                                        </span>
                                        <?php if($search['results_found'] > 0): ?>
                                        <span class="meta">
                                            <i class="fas fa-check-circle"></i>
                                            <?php echo $search['results_found']; ?> results
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="search-action">
                                    <i class="fas fa-redo"></i>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-search"></i>
                            <p>No search history yet</p>
                            <a href="search.php" class="btn-sm">Start Searching</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Saved Pharmacies -->
                <div class="content-card">
                    <div class="card-header">
                        <h3><i class="fas fa-bookmark"></i> Saved Pharmacies</h3>
                        <a href="favorites.php" class="btn-link">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if(count($savedPharmacies) > 0): ?>
                        <div class="saved-list">
                            <?php foreach($savedPharmacies as $pharmacy): ?>
                            <div class="saved-item">
                                <div class="saved-image">
                                    <?php if($pharmacy['featured_image']): ?>
                                    <img src="<?php echo htmlspecialchars($pharmacy['featured_image']); ?>" alt="<?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?>">
                                    <?php else: ?>
                                    <div class="image-placeholder">
                                        <i class="fas fa-clinic-medical"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="saved-info">
                                    <div class="saved-name"><?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?></div>
                                    <div class="saved-rating">
                                        <div class="stars">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $pharmacy['rating'] ? 'filled' : ''; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="review-count">(<?php echo $pharmacy['review_count']; ?>)</span>
                                    </div>
                                    <div class="saved-address"><?php echo htmlspecialchars($pharmacy['address']); ?></div>
                                </div>
                                <div class="saved-actions">
                                    <button class="btn-icon" onclick="viewPharmacy(<?php echo $pharmacy['pharmacy_id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon" onclick="removeFavorite(<?php echo $pharmacy['pharmacy_id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bookmark"></i>
                            <p>No saved pharmacies yet</p>
                            <p class="small-text">Save pharmacies you like for quick access</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Health Tips -->
                <div class="content-card tips-card">
                    <div class="card-header">
                        <h3><i class="fas fa-heartbeat"></i> Health Tips</h3>
                    </div>
                    <div class="card-body">
                        <div class="tips-slider">
                            <div class="tip-item active">
                                <div class="tip-icon">
                                    <i class="fas fa-prescription-bottle-alt"></i>
                                </div>
                                <div class="tip-content">
                                    <h4>Prescription Safety</h4>
                                    <p>Always complete your antibiotic course as prescribed, even if you feel better.</p>
                                </div>
                            </div>
                            <div class="tip-item">
                                <div class="tip-icon">
                                    <i class="fas fa-thermometer"></i>
                                </div>
                                <div class="tip-content">
                                    <h4>Medicine Storage</h4>
                                    <p>Store medicines in a cool, dry place away from direct sunlight and moisture.</p>
                                </div>
                            </div>
                            <div class="tip-item">
                                <div class="tip-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="tip-content">
                                    <h4>Check Expiry Dates</h4>
                                    <p>Regularly check medicine expiry dates and dispose of expired medicines properly.</p>
                                </div>
                            </div>
                        </div>
                        <div class="tips-nav">
                            <button class="nav-btn prev-btn"><i class="fas fa-chevron-left"></i></button>
                            <div class="dots">
                                <span class="dot active"></span>
                                <span class="dot"></span>
                                <span class="dot"></span>
                            </div>
                            <button class="nav-btn next-btn"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                </div>
                
                <!-- Emergency Contacts -->
                <div class="content-card emergency-card">
                    <div class="card-header">
                        <h3><i class="fas fa-phone-alt"></i> Emergency Contacts</h3>
                    </div>
                    <div class="card-body">
                        <div class="emergency-list">
                            <div class="emergency-item">
                                <div class="emergency-icon ambulance">
                                    <i class="fas fa-ambulance"></i>
                                </div>
                                <div class="emergency-info">
                                    <div class="emergency-title">Ambulance Service</div>
                                    <div class="emergency-number">907</div>
                                </div>
                            </div>
                            <div class="emergency-item">
                                <div class="emergency-icon police">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="emergency-info">
                                    <div class="emergency-title">Police Emergency</div>
                                    <div class="emergency-number">911</div>
                                </div>
                            </div>
                            <div class="emergency-item">
                                <div class="emergency-icon fire">
                                    <i class="fas fa-fire"></i>
                                </div>
                                <div class="emergency-info">
                                    <div class="emergency-title">Fire Service</div>
                                    <div class="emergency-number">939</div>
                                </div>
                            </div>
                        </div>
                        <div class="emergency-note">
                            <i class="fas fa-exclamation-circle"></i>
                            For medical emergencies, proceed to the nearest hospital immediately.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <footer class="dashboard-footer">
            <div class="footer-content">
                <div class="user-status">
                    <span class="status-indicator online"></span>
                    <span>Last active: <?php echo $user['last_login'] ? time_elapsed_string($user['last_login']) : 'Never'; ?></span>
                </div>
                <div class="footer-links">
                    <a href="help.php">Help</a>
                    <a href="contact.php">Contact</a>
                    <a href="privacy.php">Privacy</a>
                </div>
            </div>
        </footer>
    </div>
    
    <!-- JavaScript -->
    <script src="../js/search.js"></script>
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
            
            // Quick search functionality
            const quickSearch = document.getElementById('quickSearch');
            const medicineSearch = document.getElementById('medicineSearch');
            
            if (quickSearch) {
                quickSearch.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        performQuickSearch(this.value);
                    }
                });
            }
            
            if (medicineSearch) {
                medicineSearch.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        performSearch();
                    }
                });
            }
            
            // Health tips slider
            const tipItems = document.querySelectorAll('.tip-item');
            const dots = document.querySelectorAll('.dot');
            let currentTip = 0;
            
            function showTip(index) {
                tipItems.forEach(item => item.classList.remove('active'));
                dots.forEach(dot => dot.classList.remove('active'));
                
                tipItems[index].classList.add('active');
                dots[index].classList.add('active');
                currentTip = index;
            }
            
            document.querySelector('.next-btn').addEventListener('click', function() {
                const nextIndex = (currentTip + 1) % tipItems.length;
                showTip(nextIndex);
            });
            
            document.querySelector('.prev-btn').addEventListener('click', function() {
                const prevIndex = (currentTip - 1 + tipItems.length) % tipItems.length;
                showTip(prevIndex);
            });
            
            // Auto-rotate tips every 5 seconds
            setInterval(() => {
                const nextIndex = (currentTip + 1) % tipItems.length;
                showTip(nextIndex);
            }, 5000);
            
            // Dot navigation
            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    showTip(index);
                });
            });
        });
        
        // Notifications panel
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationsPanel = document.getElementById('notificationsPanel');
        
        if (notificationBtn) {
            notificationBtn.addEventListener('click', function() {
                notificationsPanel.classList.toggle('show');
            });
        }
        
        function closeNotifications() {
            notificationsPanel.classList.remove('show');
        }
        
        function markAsRead(notificationId) {
            fetch(`../api/mark_notification_read.php?id=${notificationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove notification from UI
                        const notificationItem = document.querySelector(`button[onclick="markAsRead(${notificationId})"]`).closest('.notification-item');
                        notificationItem.style.opacity = '0.5';
                        setTimeout(() => {
                            notificationItem.remove();
                            
                            // Update badge count
                            const badge = document.querySelector('.notification-btn .badge');
                            if (badge) {
                                const count = parseInt(badge.textContent) - 1;
                                if (count > 0) {
                                    badge.textContent = count;
                                } else {
                                    badge.remove();
                                }
                            }
                            
                            // Show empty state if no notifications left
                            const notificationsList = document.querySelector('.notifications-list');
                            if (!notificationsList || notificationsList.children.length === 0) {
                                const emptyState = document.createElement('div');
                                emptyState.className = 'empty-state';
                                emptyState.innerHTML = `
                                    <i class="fas fa-bell-slash"></i>
                                    <p>No new notifications</p>
                                `;
                                document.querySelector('.panel-body').appendChild(emptyState);
                            }
                        }, 300);
                    }
                });
        }
        
        // Search functions
        function performQuickSearch(query) {
            if (query.trim()) {
                window.location.href = `../search.php?q=${encodeURIComponent(query.trim())}`;
            }
        }
        
        function performSearch() {
            const query = document.getElementById('medicineSearch').value;
            const location = document.getElementById('locationSelect').value;
            
            if (query.trim()) {
                let url = `../search.php?q=${encodeURIComponent(query.trim())}`;
                if (location) {
                    url += `&location=${location}`;
                }
                window.location.href = url;
            }
        }
        
        function repeatSearch(query) {
            if (query) {
                window.location.href = `../search.php?q=${encodeURIComponent(query)}`;
            }
        }
        
        function viewPharmacy(pharmacyId) {
            window.location.href = `pharmacy.php?id=${pharmacyId}`;
        }
        
        function searchAtPharmacy(pharmacyId) {
            window.location.href = `../search.php?pharmacy=${pharmacyId}`;
        }
        
        function removeFavorite(pharmacyId) {
            if (confirm('Remove this pharmacy from favorites?')) {
                fetch(`../api/remove_favorite.php?id=${pharmacyId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        }
                    });
            }
        }
        
        // Location functions
        function getUserLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        // Send location to server
                        fetch('../api/update_location.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                latitude: position.coords.latitude,
                                longitude: position.coords.longitude
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('Location updated! Refreshing nearby pharmacies...');
                                location.reload();
                            }
                        });
                    },
                    function(error) {
                        alert('Unable to get your location. Please enable location services.');
                    }
                );
            } else {
                alert('Geolocation is not supported by your browser.');
            }
        }
        
        // Close notifications panel when clicking outside
        document.addEventListener('click', function(event) {
            if (!notificationBtn.contains(event.target) && !notificationsPanel.contains(event.target)) {
                closeNotifications();
            }
        });
    </script>
</body>
</html>

<?php
// Helper functions
function getNotificationIcon($type) {
    $icons = [
        'system' => 'cog',
        'inventory' => 'boxes',
        'request' => 'shopping-cart',
        'security' => 'shield-alt',
        'promotion' => 'tag'
    ];
    return $icons[$type] ?? 'bell';
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