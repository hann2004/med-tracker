<?php
session_start();
require_once 'config/database.php';

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath === '/' || $basePath === '.') {
    $basePath = '';
}
$conn = getDatabaseConnection();

// Get live statistics
$stats = [
    'pharmacies' => $conn->query("SELECT COUNT(*) as count FROM pharmacies WHERE is_active = 1")->fetch_assoc()['count'],
    'medicines' => $conn->query("SELECT COUNT(*) as count FROM medicines WHERE is_active = 1")->fetch_assoc()['count'],
    'stock_units' => $conn->query("SELECT SUM(quantity) as total FROM pharmacy_inventory")->fetch_assoc()['total'] ?? 0,
    'searches' => $conn->query("SELECT COUNT(*) as count FROM searches WHERE DATE(search_date) = CURDATE()")->fetch_assoc()['count']
];

// Get featured medicines
$featuredMedicines = $conn->query("
    SELECT m.*, COUNT(DISTINCT pi.pharmacy_id) as pharmacy_count
    FROM medicines m
    LEFT JOIN pharmacy_inventory pi ON m.medicine_id = pi.medicine_id
    WHERE m.is_active = 1
    GROUP BY m.medicine_id
    ORDER BY pharmacy_count DESC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// Area-specific pharmacy examples
$phSecha = $conn->query("SELECT pharmacy_name FROM pharmacies WHERE address LIKE '%Secha%' LIMIT 3")->fetch_all(MYSQLI_ASSOC);
$phSikela = $conn->query("SELECT pharmacy_name FROM pharmacies WHERE address LIKE '%Sikela%' LIMIT 3")->fetch_all(MYSQLI_ASSOC);
$phCenter = $conn->query("SELECT pharmacy_name FROM pharmacies WHERE address LIKE '%Center%' OR address LIKE '%City%' OR address LIKE '%Main%' LIMIT 3")->fetch_all(MYSQLI_ASSOC);

// Fallback area listings if database is empty
if (empty($phSecha)) {
    $phSecha = [
        ['pharmacy_name' => 'Covenant Drug Store']
    ];
}

if (empty($phSikela)) {
    $phSikela = [
        ['pharmacy_name' => 'Mihret Drug Store'],
        ['pharmacy_name' => 'Kana Drug Store'],
        ['pharmacy_name' => 'Amazon Drug Store']
    ];
}

if (empty($phCenter)) {
    $phCenter = [
        ['pharmacy_name' => 'Arbaminch Enat Pharmacy'],
        ['pharmacy_name' => 'Model Community Pharmacy'],
        ['pharmacy_name' => 'Beminet Drug Store']
    ];
}

// Build static OSM map with markers from available pharmacies
$markers = $conn->query("SELECT latitude, longitude, pharmacy_name FROM pharmacies WHERE latitude IS NOT NULL AND longitude IS NOT NULL LIMIT 12")->fetch_all(MYSQLI_ASSOC);
$fallbackMarkers = [
    ['latitude' => 6.0435, 'longitude' => 37.5470, 'pharmacy_name' => 'Covenant Drug Store'],
    ['latitude' => 6.0385, 'longitude' => 37.5485, 'pharmacy_name' => 'Arbaminch Enat Pharmacy'],
    ['latitude' => 6.0375, 'longitude' => 37.5510, 'pharmacy_name' => 'Model Community Pharmacy'],
    ['latitude' => 6.0390, 'longitude' => 37.5520, 'pharmacy_name' => 'Beminet Drug Store'],
    ['latitude' => 6.0405, 'longitude' => 37.5530, 'pharmacy_name' => 'Nechisar Drug Store'],
    ['latitude' => 6.0580, 'longitude' => 37.5480, 'pharmacy_name' => 'Mihret Drug Store'],
    ['latitude' => 6.0590, 'longitude' => 37.5495, 'pharmacy_name' => 'Kana Drug Store'],
    ['latitude' => 6.0602, 'longitude' => 37.5510, 'pharmacy_name' => 'Amazon Drug Store'],
    ['latitude' => 6.0370, 'longitude' => 37.5490, 'pharmacy_name' => 'GDA Model Community Pharmacy'],
    ['latitude' => 6.0345, 'longitude' => 37.5475, 'pharmacy_name' => 'EPSS ArbaMinch Hub']
];
if (empty($markers)) {
    $markers = $fallbackMarkers;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedTrack Arba Minch | Medicine Availability System</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Leaflet Map -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-sA+4J1H8yEHC3vC9i1kWeItseyX2VINeodZ6T7BHM3I=" crossorigin="" />
    
    <!-- CSS -->
    <link rel="stylesheet" href="styles/main.css">
    
    <style>
        /* Enhanced Clinical Animations */
        @keyframes pulse-gentle {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }
        
        /* Loading spinner */
        .loading-spinner {
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Suggestion items */
        .suggestion-item {
            padding: 12px 16px;
            cursor: pointer;
            border-bottom: 1px solid var(--clinical-border);
            transition: background 0.2s;
            color: var(--clinical-text-light);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .suggestion-item:hover {
            background: rgba(37, 99, 235, 0.05);
            color: var(--clinical-accent);
        }
        
        .suggestion-item i {
            color: var(--clinical-text-light);
            font-size: 0.875rem;
        }
        
        .suggestion-item:hover i {
            color: var(--clinical-accent);
        }
        
        /* Error message */
        .error-message {
            color: var(--clinical-error);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        /* Medical icon animations */
        .medical-icon-pulse {
            animation: pulse-gentle 2s ease-in-out infinite;
        }
        
        .medical-icon-float {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Enhanced card shimmer effect */
        .card-shimmer { position: relative; overflow: hidden; }
        .card-shimmer .card-content { position: relative; z-index: 1; }
        
        .card-shimmer::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 30%,
                rgba(255, 255, 255, 0.4) 50%,
                transparent 70%
            );
            transform: rotate(45deg);
            animation: shimmer 3s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }
        
        /* Keyboard navigation focus */
        .keyboard-navigation button:focus,
        .keyboard-navigation a:focus,
        .keyboard-navigation input:focus,
        .keyboard-navigation select:focus {
            outline: 3px solid var(--clinical-accent);
            outline-offset: 2px;
        }
        
        /* Emergency badge pulse */
        .emergency-pulse {
            animation: pulse-gentle 1.5s ease-in-out infinite;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.3);
        }
        
        /* Status indicators */
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        
        .status-online {
            background: var(--clinical-success);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
        }
        
        .status-updating {
            background: var(--clinical-warning);
            animation: pulse-gentle 1s ease-in-out infinite;
        }
        
        /* Smart search dropdown */
        .search-input-wrapper { position: relative; width: 100%; }
        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--clinical-white);
            border: 1px solid var(--clinical-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            z-index: 1000;
            display: none;
            max-height: 260px;
            overflow-y: auto;
        }
        .search-suggestions.show { display: block; }
        .search-suggestions .suggestion-item { padding: 12px 14px; cursor: pointer; display: flex; gap: 10px; align-items: center; }
        .search-suggestions .suggestion-item:hover { background: rgba(37,99,235,0.08); }
        
        /* User dropdown styles */
        .user-dropdown { position: relative; }
        .user-dropdown .user-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border: 1px solid var(--clinical-border);
            background: var(--clinical-white);
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
        }
        .user-dropdown .avatar-sm {
            width: 28px; height: 28px; border-radius: 50%;
            background: var(--gradient-accent); color: #fff;
            display: flex; align-items: center; justify-content: center;
        }
        .user-dropdown .dropdown-menu {
            position: absolute; right: 0; top: calc(100% + 8px);
            background: var(--clinical-white);
            border: 1px solid var(--clinical-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            min-width: 220px;
            display: none;
            overflow: hidden;
        }
        .user-dropdown .dropdown-menu.show { display: block; }
        .user-dropdown .dropdown-menu a {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; color: var(--clinical-text);
            text-decoration: none; border-bottom: 1px solid var(--clinical-border);
        }
        .user-dropdown .dropdown-menu a:hover { background: rgba(37,99,235,0.06); }
        .user-dropdown .dropdown-menu .divider { height: 1px; background: var(--clinical-border); }
    </style>
</head>
<body>
    <!-- System Navigation -->
    <nav class="system-nav">
        <div class="nav-container">
            <!-- Logo -->
            <a href="index.php" class="system-logo">
                <div class="logo-icon">
                    <i class="fas fa-capsules medical-icon-float"></i>
                </div>
                <span>MedTrack Arba Minch</span>
            </a>
            
            <!-- Navigation Menu -->
            <ul class="nav-menu" id="navMenu">
                <li><a href="#find-medicine" class="nav-link"><i class="fas fa-search-medical"></i> Find Medicine</a></li>
                <li><a href="#coverage" class="nav-link"><i class="fas fa-map-marker-alt"></i> Pharmacies</a></li>
                <li><a href="#how-it-works" class="nav-link"><i class="fas fa-play-circle"></i> How It Works</a></li>
                <li><a href="#stats" class="nav-link"><i class="fas fa-chart-line"></i> System Status</a></li>
            </ul>
            
            <!-- Navigation Actions -->
            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id']) && (($_SESSION['user_type'] ?? '') === 'user')): ?>
                    <div class="user-dropdown">
                        <button class="user-btn" aria-haspopup="true" aria-expanded="false">
                            <div class="avatar-sm">
                                <i class="fas fa-user"></i>
                            </div>
                            <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'Account')); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="index.php"><i class="fas fa-home"></i> Home</a>
                            <a href="user/profile.php"><i class="fas fa-user"></i> Profile</a>
                            <div class="divider"></div>
                            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn-text"><i class="fas fa-sign-in-alt"></i> Login</a>
                    <a href="register.php" class="btn-outline"><i class="fas fa-user-plus"></i> Register</a>
                <?php endif; ?>
            </div>
            
            <!-- Mobile Menu Toggle -->
            <button class="mobile-toggle" id="mobileToggle" aria-label="Toggle menu" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <!-- Hero Text Content -->
                <div class="hero-text animate-fade-in">
                    <div class="hero-tag">
                        <i class="fas fa-shield-alt medical-icon-pulse"></i>
                        <span>Live Inventory Tracking</span>
                    </div>
                    
                    <h1 class="hero-title">Real-Time Medicine Availability in Arba&nbsp;Minch</h1>
                    
                    <p class="hero-description">
                        Instantly search verified pharmacies, check prescription requirements, 
                        and locate essential medicines with live inventory data across Arba Minch.
                    </p>
                    
                    <!-- Search Console -->
                    <div class="search-console">
                        <div class="console-title">
                            <i class="fas fa-search-medical"></i>
                            <span>Medicine Availability Search</span>
                        </div>
                        
                        <form action="search.php" method="GET" class="search-form" id="heroSearchForm">
                            <!-- Medicine Name -->
                            <div class="search-field">
                                <label class="field-label">Medicine Name</label>
                                <div class="search-input-wrapper">
                                    <input type="text" name="medicine" class="search-input" 
                                           placeholder="Enter medicine name (e.g., Paracetamol)" 
                                           required
                                           autocomplete="off"
                                           id="heroSearchInput"
                                           data-suggest="true">
                                    <div class="search-suggestions" data-for="heroSearchInput"></div>
                                </div>
                            </div>
                            
                            <!-- Location (Locked to Arba Minch) -->
                            <div class="search-field field-locked">
                                <label class="field-label">Location</label>
                                <input type="text" class="search-input" value="Arba Minch" readonly>
                            </div>
                            
                            <!-- Prescription Filter -->
                            <div class="prescription-filter" id="prescriptionFilter" role="button" aria-pressed="false" tabindex="0">
                                <i class="fas fa-prescription-bottle-alt"></i>
                                <span>RX Filter</span>
                            </div>
                            
                            <!-- Search Button -->
                            <button type="submit" class="search-button">
                                <i class="fas fa-search"></i>
                                <span>Search</span>
                            </button>
                        </form>
                        
                        <div class="search-hint">
                            <i class="fas fa-lightbulb"></i>
                            <span>Popular: </span>
                            <a href="search.php?medicine=Paracetamol">Paracetamol</a>
                            <span> • </span>
                            <a href="search.php?medicine=Amoxicillin">Amoxicillin</a>
                            <span> • </span>
                            <a href="search.php?medicine=Insulin">Insulin</a>
                        </div>
                    </div>
                </div>
                
                <!-- Hero Visual: Live Map -->
                <div class="hero-visual animate-fade-in animate-delay-1">
                    <div class="hero-visual-card">
                        <div class="visual-label"><i class="fas fa-map-marked-alt"></i> Arba Minch Coverage Map</div>
                        <img id="heroMap" class="hero-map" src="/med-tracker/assets/images/mapp.png" alt="Arba Minch coverage map">
                        <div class="map-legend" style="margin-top: 1rem; display: flex; gap: 1rem; font-size: 0.875rem;">
                            <div><span style="display: inline-block; width: 10px; height: 10px; background: #2563eb; border-radius: 50%; margin-right: 0.5rem;"></span> 24/7 Pharmacy</div>
                            <div><span style="display: inline-block; width: 10px; height: 10px; background: #10b981; border-radius: 50%; margin-right: 0.5rem;"></span> Regular Pharmacy</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- System Snapshot -->
    <section id="stats" class="section">
        <div class="container">
            <div class="section-header">
                <h2>Live System Snapshot</h2>
                <p>Real-time tracking data from Arba Minch pharmacy network</p>
            </div>
            
            <div class="snapshot-grid">
                <!-- Active Pharmacies -->
                <div class="snapshot-card animate-fade-in">
                    <div class="snapshot-icon">
                        <i class="fas fa-clinic-medical"></i>
                    </div>
                    <div class="snapshot-value"><?php echo $stats['pharmacies']; ?></div>
                    <div class="snapshot-label">Active Pharmacies</div>
                    <div class="status-indicator status-online"></div>
                </div>
                
                <!-- Medicines Tracked -->
                <div class="snapshot-card animate-fade-in animate-delay-1">
                    <div class="snapshot-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                    <div class="snapshot-value"><?php echo $stats['medicines']; ?></div>
                    <div class="snapshot-label">Medicines Tracked</div>
                    <div class="status-indicator status-online"></div>
                </div>
                
                <!-- Stock Units -->
                <div class="snapshot-card animate-fade-in animate-delay-2">
                    <div class="snapshot-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="snapshot-value"><?php echo number_format($stats['stock_units']); ?></div>
                    <div class="snapshot-label">Stock Units Monitored</div>
                    <div class="status-indicator status-updating"></div>
                </div>
                
                <!-- Searches Today -->
                <div class="snapshot-card animate-fade-in animate-delay-3">
                    <div class="snapshot-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="snapshot-value"><?php echo $stats['searches']; ?></div>
                    <div class="snapshot-label">Searches Today</div>
                    <div class="status-indicator status-online"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Medicine Availability -->
    <section id="find-medicine" class="section">
        <div class="container">
            <div class="section-header">
                <h2>Featured Medicines</h2>
                <p>Current availability status for essential medicines in Arba Minch</p>
            </div>
            
            <div class="availability-grid">
                <?php foreach($featuredMedicines as $medicine): ?>
                <?php 
                    $localPath = 'uploads/medicines/' . $medicine['medicine_id'] . '.jpg';
                    if (file_exists($localPath)) {
                        $img = $localPath;
                    } elseif (!empty($medicine['image_url'])) {
                        $img = $medicine['image_url'];
                    } else {
                        $img = 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?auto=format&fit=crop&w=800&q=70';
                    }
                ?>
                <div class="medicine-card animate-fade-in card-shimmer">
                    <div class="card-content" style="position:relative; z-index:1;">
                        <div class="medicine-thumb">
                            <img src="<?php echo htmlspecialchars($img); ?>" 
                                 alt="<?php echo htmlspecialchars($medicine['medicine_name']); ?>"
                                 loading="lazy">
                        </div>
                        <div class="medicine-header">
                            <div class="medicine-icon">
                                <i class="fas fa-capsules"></i>
                            </div>
                            <div>
                                <div class="medicine-title"><?php echo htmlspecialchars($medicine['medicine_name']); ?></div>
                                <div class="medicine-type"><?php echo htmlspecialchars($medicine['generic_name']); ?></div>
                            </div>
                        </div>
                        
                        <?php if($medicine['requires_prescription']): ?>
                        <div class="availability-badge prescription">
                            <i class="fas fa-prescription-bottle-alt"></i>
                            Prescription Required
                        </div>
                        <?php else: ?>
                        <div class="availability-badge">
                            <i class="fas fa-shopping-cart"></i>
                            Available OTC
                        </div>
                        <?php endif; ?>
                        
                        <div class="pharmacy-count">
                            <i class="fas fa-store"></i>
                            <span>Available in <?php echo $medicine['pharmacy_count']; ?> pharmacies</span>
                        </div>
                        
                        <?php
                            $detailUrl = ($basePath ?: '') . '/medicine.php?id=' . $medicine['medicine_id'];
                            $ctaUrl = isset($_SESSION['user_id']) ? $detailUrl : ($basePath ?: '') . '/login.php?next=' . urlencode($detailUrl);
                        ?>
                        <a href="<?php echo htmlspecialchars($ctaUrl); ?>" class="view-link">
                            <span>View availability details</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Pharmacy Coverage -->
    <section id="coverage" class="section">
        <div class="container">
            <div class="section-header">
                <h2>Pharmacy Coverage in Arba Minch</h2>
                <p>Verified pharmacy network across key areas</p>
            </div>
            
            <div class="coverage-grid">
                <!-- Secha Area -->
                <div class="coverage-card animate-fade-in">
                    <div class="coverage-header">
                        <div class="coverage-icon">
                            <i class="fas fa-hospital"></i>
                        </div>
                        <div class="coverage-title">Secha Area</div>
                    </div>
                    <div class="coverage-pharmacies">
                        <div class="legend-title">Nearby Pharmacies</div>
                        <div class="legend-items">
                            <?php if (!empty($phSecha)) { foreach($phSecha as $row) { ?>
                                <div class="legend-item">
                                    <span class="legend-dot verified"></span>
                                    <span><?php echo htmlspecialchars($row['pharmacy_name']); ?></span>
                                </div>
                            <?php } } else { ?>
                                <div class="legend-item">
                                    <span class="legend-dot general"></span>
                                    <span>Data coming soon</span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                
                <!-- Sikela Area -->
                <div class="coverage-card animate-fade-in animate-delay-1">
                    <div class="coverage-header">
                        <div class="coverage-icon">
                            <i class="fas fa-clinic-medical"></i>
                        </div>
                        <div class="coverage-title">Sikela Area</div>
                    </div>
                    <div class="coverage-pharmacies">
                        <div class="legend-title">Nearby Pharmacies</div>
                        <div class="legend-items">
                            <?php if (!empty($phSikela)) { foreach($phSikela as $row) { ?>
                                <div class="legend-item">
                                    <span class="legend-dot verified"></span>
                                    <span><?php echo htmlspecialchars($row['pharmacy_name']); ?></span>
                                </div>
                            <?php } } else { ?>
                                <div class="legend-item">
                                    <span class="legend-dot general"></span>
                                    <span>Data coming soon</span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                
                <!-- Town Center -->
                <div class="coverage-card animate-fade-in animate-delay-2">
                    <div class="coverage-header">
                        <div class="coverage-icon">
                            <i class="fas fa-city"></i>
                        </div>
                        <div class="coverage-title">Town Center</div>
                    </div>
                    <div class="coverage-pharmacies">
                        <div class="legend-title">Nearby Pharmacies</div>
                        <div class="legend-items">
                            <?php if (!empty($phCenter)) { foreach($phCenter as $row) { ?>
                                <div class="legend-item">
                                    <span class="legend-dot verified"></span>
                                    <span><?php echo htmlspecialchars($row['pharmacy_name']); ?></span>
                                </div>
                            <?php } } else { ?>
                                <div class="legend-item">
                                    <span class="legend-dot general"></span>
                                    <span>Data coming soon</span>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="coverage-legend mt-5">
                <div class="legend-title">Coverage Legend</div>
                <div class="legend-items">
                    <div class="legend-item">
                        <span class="legend-dot verified"></span>
                        <span>Verified & Licensed</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot general"></span>
                        <span>General Pharmacy Services</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="section">
        <div class="container">
            <div class="section-header">
                <h2>How MedTrack Works</h2>
                <p>Simple three-step process to find medicines in Arba Minch</p>
            </div>
            
            <div class="process-steps">
                <!-- Step 1 -->
                <div class="step-card animate-fade-in">
                    <div class="step-number">1</div>
                    <h3 class="step-title">Search Medicine</h3>
                    <p class="step-description">
                        Enter medicine name to check real-time availability 
                        across the entire pharmacy network in Arba Minch.
                    </p>
                </div>
                
                <!-- Step 2 -->
                <div class="step-card animate-fade-in animate-delay-1">
                    <div class="step-number">2</div>
                    <h3 class="step-title">Review Details</h3>
                    <p class="step-description">
                        View prescription requirements, price ranges, 
                        exact pharmacy locations, and contact information.
                    </p>
                </div>
                
                <!-- Step 3 -->
                <div class="step-card animate-fade-in animate-delay-2">
                    <div class="step-number">3</div>
                    <h3 class="step-title">Visit Pharmacy</h3>
                    <p class="step-description">
                        Proceed directly to verified pharmacy with 
                        confirmed stock availability for physical purchase.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- System Integrity -->
    <section class="section">
        <div class="container">
            <div class="section-header">
                <h2>System Integrity</h2>
                <p>Professional standards for healthcare data management</p>
            </div>
            
            <div class="integrity-grid">
                <div class="integrity-item animate-fade-in">
                    <div class="integrity-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="integrity-content">
                        <h3 class="integrity-title">Verified Pharmacies</h3>
                        <p class="integrity-description">
                            All participating pharmacies are licensed and verified 
                            medical establishments with proper documentation.
                        </p>
                    </div>
                </div>
                
                <div class="integrity-item animate-fade-in animate-delay-1">
                    <div class="integrity-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="integrity-content">
                        <h3 class="integrity-title">Prescription Awareness</h3>
                        <p class="integrity-description">
                            Clear indication of prescription requirements for 
                            controlled medicines to ensure proper medical guidance.
                        </p>
                    </div>
                </div>
                
                <div class="integrity-item animate-fade-in animate-delay-2">
                    <div class="integrity-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="integrity-content">
                        <h3 class="integrity-title">No Online Sales</h3>
                        <p class="integrity-description">
                            System provides availability information only. 
                            All purchases require physical verification at pharmacies.
                        </p>
                    </div>
                </div>
                
                <div class="integrity-item animate-fade-in animate-delay-3">
                    <div class="integrity-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="integrity-content">
                        <h3 class="integrity-title">Local Data Focus</h3>
                        <p class="integrity-description">
                            Dedicated to Arba Minch community with accurate 
                            local pharmacy data and timely updates.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="final-cta">
        <div class="container">
            <div class="final-cta-card">
                <div class="cta-left">
                    <div class="cta-kicker">
                        <i class="fas fa-bolt"></i>
                        Instant access
                    </div>
                    <h2 class="cta-title">Check medicine availability before you go</h2>
                    <p class="cta-description">
                        Get live pharmacy coverage across Arba Minch, see RX requirements, and avoid stock-out trips.
                    </p>
                    <div class="cta-badges">
                        <span class="cta-pill"><i class="fas fa-check"></i> Live inventory signals</span>
                        <span class="cta-pill"><i class="fas fa-check"></i> Verified pharmacies</span>
                        <span class="cta-pill"><i class="fas fa-check"></i> 24/7 & daytime options</span>
                    </div>
                </div>
                <div class="cta-right">
                    <div class="cta-actions">
                        <a href="register.php" class="btn-primary">
                            <i class="fas fa-user-plus"></i>
                            Create User Account
                        </a>
                        <a href="register.php?type=pharmacy" class="btn-secondary">
                            <i class="fas fa-clinic-medical"></i>
                            Pharmacy Owner? Register
                        </a>
                    </div>
                    <div class="cta-mini">
                        <div><i class="fas fa-map-marker-alt"></i> Arba Minch-focused</div>
                        <div><i class="fas fa-shield-alt"></i> No online sales — availability only</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="system-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-info">
                    <a href="index.php" class="footer-logo">
                        <span>MedTrack Arba Minch</span>
                    </a>
                    <div class="footer-project">
                        Medicine Availability Tracking System | Arba Minch University Project
                    </div>
                </div>
                <div class="footer-year">
                    © <?php echo date('Y'); ?> Arba Minch University
                </div>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Indicator -->
    <div class="scroll-indicator" id="scrollTop" aria-label="Scroll to top">
        <i class="fas fa-arrow-up"></i>
    </div>

    <!-- JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-Vt3M9GZpU4J8V+RaH59RUW2KIiMzH6I0bYFZ3z2Rk8M=" crossorigin=""></script>
    <script>
        window.APP_BASE = '<?php echo $basePath; ?>';
        window.APP_SEARCH_API = (window.APP_BASE || '') + '/search_api.php';
        window.IS_AUTH = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
        window.LOGIN_URL = (window.APP_BASE || '') + '/login.php?next=1';
    </script>
    <script src="js/search.js"></script>
    <script>
        // Modern Clinical Interactions
        document.addEventListener('DOMContentLoaded', function() {

            // If not authenticated, intercept hero search submit to redirect to login
            if (!window.IS_AUTH) {
                const heroForm = document.getElementById('heroSearchForm');
                if (heroForm) {
                    heroForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        window.location.href = '/med-tracker/login.php?next=' + encodeURIComponent('/med-tracker/search.php');
                    });
                }
            }
            
            // Navbar scroll effect
            const navbar = document.querySelector('.system-nav');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 100) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });
            
            // Mobile menu toggle
            const mobileToggle = document.getElementById('mobileToggle');
            const navMenu = document.getElementById('navMenu');
            
            if (mobileToggle) {
                mobileToggle.addEventListener('click', () => {
                    navMenu.classList.toggle('active');
                    const icon = mobileToggle.querySelector('i');
                    icon.classList.toggle('fa-bars');
                    icon.classList.toggle('fa-times');
                    
                    // Toggle aria-expanded
                    const expanded = navMenu.classList.contains('active');
                    mobileToggle.setAttribute('aria-expanded', expanded);
                });
                
                // Close menu when clicking outside
                document.addEventListener('click', (e) => {
                    if (!navMenu.contains(e.target) && !mobileToggle.contains(e.target)) {
                        navMenu.classList.remove('active');
                        mobileToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
                        mobileToggle.setAttribute('aria-expanded', 'false');
                    }
                });
                
                // Close menu on escape key
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && navMenu.classList.contains('active')) {
                        navMenu.classList.remove('active');
                        mobileToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
                        mobileToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            }
            
            // Smooth scroll for navigation links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if (targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        // Close mobile menu if open
                        if (navMenu.classList.contains('active')) {
                            navMenu.classList.remove('active');
                            mobileToggle.querySelector('i').classList.replace('fa-times', 'fa-bars');
                            mobileToggle.setAttribute('aria-expanded', 'false');
                        }
                        
                        window.scrollTo({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                });
            });
            
            // Scroll to top button
            const scrollTop = document.getElementById('scrollTop');
            window.addEventListener('scroll', () => {
                if (window.pageYOffset > 300) {
                    scrollTop.classList.add('visible');
                } else {
                    scrollTop.classList.remove('visible');
                }
            });
            
            scrollTop.addEventListener('click', () => {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // Animate elements on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fade-in');
                    }
                });
            }, observerOptions);
            
            // Observe all cards and sections
            document.querySelectorAll('.snapshot-card, .medicine-card, .coverage-card, .step-card, .integrity-item').forEach(el => {
                el.style.opacity = '0';
                observer.observe(el);
            });
            
            // Prescription filter toggle
            const prescriptionFilter = document.getElementById('prescriptionFilter');
            if (prescriptionFilter) {
                prescriptionFilter.addEventListener('click', () => {
                    prescriptionFilter.classList.toggle('active');
                    const icon = prescriptionFilter.querySelector('i');
                    const text = prescriptionFilter.querySelector('span');
                    
                    if (prescriptionFilter.classList.contains('active')) {
                        icon.style.color = 'var(--clinical-warning)';
                        text.textContent = 'RX Only';
                        prescriptionFilter.setAttribute('aria-pressed', 'true');
                    } else {
                        icon.style.color = '';
                        text.textContent = 'RX Filter';
                        prescriptionFilter.setAttribute('aria-pressed', 'false');
                    }
                });
                
                // Support keyboard activation
                prescriptionFilter.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        prescriptionFilter.click();
                    }
                });
            }
            
            // Form validation with better UX
            const searchForm = document.querySelector('.search-form');
            if (searchForm) {
                const searchInput = searchForm.querySelector('input[name="medicine"]');
                
                searchForm.addEventListener('submit', function(e) {
                    if (!searchInput.value.trim()) {
                        e.preventDefault();
                        
                        // Add error state
                        searchInput.style.borderColor = 'var(--clinical-error)';
                        searchInput.style.boxShadow = '0 0 0 4px rgba(239, 68, 68, 0.1)';
                        
                        // Add error message
                        let errorMessage = searchInput.parentNode.querySelector('.error-message');
                        if (!errorMessage) {
                            errorMessage = document.createElement('div');
                            errorMessage.className = 'error-message';
                            errorMessage.style.cssText = `
                                color: var(--clinical-error);
                                font-size: 0.875rem;
                                margin-top: 0.5rem;
                            `;
                            searchInput.parentNode.appendChild(errorMessage);
                        }
                        errorMessage.textContent = 'Please enter a medicine name';
                        
                        // Focus on input
                        searchInput.focus();
                        
                        // Remove error state after 3 seconds
                        setTimeout(() => {
                            searchInput.style.borderColor = '';
                            searchInput.style.boxShadow = '';
                            if (errorMessage) errorMessage.remove();
                        }, 3000);
                        
                        return false;
                    }
                    
                    // Add loading state
                    const button = this.querySelector('.search-button');
                    const originalText = button.innerHTML;
                    button.innerHTML = `
                        <div class="loading-spinner"></div>
                        <span>Searching...</span>
                    `;
                    button.disabled = true;
                    
                    // Simulate API call
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }, 1500);
                });
                
                // Auto-suggestions
                const suggestions = [
                    'Paracetamol', 'Amoxicillin', 'Insulin', 'Cetirizine',
                    'Metformin', 'Ibuprofen', 'Omeprazole', 'Atorvastatin',
                    'Vitamin C', 'Diazepam', 'Salbutamol', 'Aspirin',
                    'Loratadine', 'Ranitidine', 'Simvastatin', 'Metronidazole'
                ];
                
                searchInput.addEventListener('input', function() {
                    const value = this.value.toLowerCase();
                    let suggestionsBox = document.getElementById('searchSuggestions');
                    
                    if (!suggestionsBox) {
                        const box = document.createElement('div');
                        box.id = 'searchSuggestions';
                        box.style.cssText = `
                            position: absolute;
                            top: 100%;
                            left: 0;
                            right: 0;
                            background: var(--clinical-white);
                            border: 1px solid var(--clinical-border);
                            border-radius: var(--radius-md);
                            margin-top: 4px;
                            z-index: 100;
                            display: none;
                            max-height: 200px;
                            overflow-y: auto;
                            box-shadow: var(--shadow-lg);
                        `;
                        this.parentNode.appendChild(box);
                        suggestionsBox = box;
                    }
                    
                    const filtered = suggestions.filter(s => s.toLowerCase().includes(value));
                    
                    if (value && filtered.length > 0) {
                        suggestionsBox.innerHTML = filtered.map(s => `
                            <div class="suggestion-item" role="option" tabindex="0">
                                <i class="fas fa-search"></i>
                                <span>${s}</span>
                            </div>
                        `).join('');
                        suggestionsBox.style.display = 'block';
                        
                        // Add click handlers to suggestions
                        suggestionsBox.querySelectorAll('.suggestion-item').forEach(item => {
                            item.addEventListener('click', function() {
                                searchInput.value = this.querySelector('span').textContent;
                                suggestionsBox.style.display = 'none';
                                searchInput.focus();
                            });
                            
                            // Keyboard navigation for suggestions
                            item.addEventListener('keydown', function(e) {
                                if (e.key === 'Enter') {
                                    searchInput.value = this.querySelector('span').textContent;
                                    suggestionsBox.style.display = 'none';
                                    searchInput.focus();
                                }
                            });
                        });
                    } else {
                        suggestionsBox.style.display = 'none';
                    }
                });
                
                // Close suggestions when clicking outside
                document.addEventListener('click', function(e) {
                    if (!searchInput.contains(e.target) && !suggestionsBox?.contains(e.target)) {
                        if (suggestionsBox) suggestionsBox.style.display = 'none';
                    }
                });
                
                // Close suggestions on escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && suggestionsBox && suggestionsBox.style.display === 'block') {
                        suggestionsBox.style.display = 'none';
                        searchInput.focus();
                    }
                });
            }
            
            // Counter animation for stats
            function animateCounters() {
                const counters = document.querySelectorAll('.snapshot-value');
                counters.forEach(counter => {
                    const target = parseInt(counter.textContent.replace(/,/g, ''));
                    const increment = target / 100;
                    let current = 0;
                    
                    const updateCounter = () => {
                        if (current < target) {
                            current += increment;
                            counter.textContent = Math.ceil(current).toLocaleString();
                            setTimeout(updateCounter, 20);
                        } else {
                            counter.textContent = target.toLocaleString();
                        }
                    };
                    
                    updateCounter();
                });
            }
            
            // Initialize counters when section is in view
            const statsSection = document.getElementById('stats');
            if (statsSection) {
                const statsObserver = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            setTimeout(animateCounters, 500);
                            statsObserver.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.5 });
                
                statsObserver.observe(statsSection);
            }
            
            // Add subtle hover effects to all interactive elements
            document.querySelectorAll('button, a, .medicine-card, .coverage-card, .snapshot-card').forEach(element => {
                element.addEventListener('mouseenter', () => {
                    element.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                });
            });
            
            // Add keyboard navigation support
            document.addEventListener('keydown', (e) => {
                // Tab navigation focus styles
                if (e.key === 'Tab') {
                    document.body.classList.add('keyboard-navigation');
                }
            });
            
            document.addEventListener('mousedown', () => {
                document.body.classList.remove('keyboard-navigation');
            });
            
            // Live stats update simulation
            function updateLiveStats() {
                // Simulate live data updates
                const statCards = document.querySelectorAll('.snapshot-card');
                statCards.forEach(card => {
                    const valueElement = card.querySelector('.snapshot-value');
                    const currentValue = parseInt(valueElement.textContent.replace(/,/g, ''));
                    
                    // Small random change (0-2)
                    const change = Math.floor(Math.random() * 3);
                    const newValue = Math.max(1, currentValue + change);
                    
                    // Only update if different
                    if (newValue !== currentValue) {
                        valueElement.textContent = newValue.toLocaleString();
                        
                        // Add visual feedback
                        card.style.transform = 'translateY(-2px)';
                        card.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
                        
                        setTimeout(() => {
                            card.style.transform = '';
                            card.style.boxShadow = '';
                        }, 500);
                    }
                });
            }
            
            // Update stats every 30 seconds
            setInterval(updateLiveStats, 30000);
            
            // Initialize Leaflet Map
            function initMap() {
                const mapEl = document.getElementById('heroMap');
                // If the hero map was replaced with a static image, skip initializing Leaflet
                if (!mapEl || mapEl.tagName === 'IMG') return;
                
                const markerData = <?php echo json_encode($markers); ?>;
                const defaultCenter = [6.040, 37.545];

                // Map status overlay for loading/fallback
                const statusEl = document.createElement('div');
                statusEl.className = 'map-status';
                statusEl.textContent = 'Loading map tiles...';
                mapEl.appendChild(statusEl);
                
                const map = L.map('heroMap', { 
                    scrollWheelZoom: false,
                    touchZoom: true,
                    dragging: true,
                    zoomControl: false
                }).setView(defaultCenter, 13);
                
                const layers = [
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors',
                        className: 'map-tiles'
                    }),
                    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors, ©Carto',
                        className: 'map-tiles'
                    }),
                    L.tileLayer('https://{s}.tile.openstreetmap.de/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors',
                        className: 'map-tiles'
                    })
                ];

                let layerIndex = 0;
                function activateLayer(idx) {
                    const layer = layers[idx];
                    layer.addTo(map);
                    statusEl.textContent = 'Loading map tiles...';

                    const onLoad = () => {
                        statusEl.style.display = 'none';
                        layer.off('tileload', onLoad);
                        layer.off('tileerror', onError);
                    };

                    const onError = () => {
                        layer.off('tileload', onLoad);
                        layer.off('tileerror', onError);
                        map.removeLayer(layer);
                        layerIndex += 1;
                        if (layerIndex < layers.length) {
                            statusEl.style.display = 'block';
                            statusEl.textContent = 'Primary tiles blocked, loading backup...';
                            activateLayer(layerIndex);
                        } else {
                            statusEl.style.display = 'block';
                            statusEl.textContent = 'Map tiles unavailable. Showing static fallback.';
                            mapEl.classList.add('map-fallback');
                        }
                    };

                    layer.on('tileload', onLoad);
                    layer.on('tileerror', onError);
                }

                activateLayer(layerIndex);
                
                // Custom icon for pharmacies
                const pharmacyIcon = L.divIcon({
                    className: 'pharmacy-marker',
                    html: '<i class="fas fa-clinic-medical"></i>',
                    iconSize: [30, 30],
                    iconAnchor: [15, 30],
                    popupAnchor: [0, -30]
                });
                
                // Add markers
                const markers = [];
                markerData.forEach(m => {
                    if (m.latitude && m.longitude) {
                        const marker = L.marker([m.latitude, m.longitude], {
                            icon: pharmacyIcon
                        }).addTo(map);
                        
                        if (m.pharmacy_name) {
                            marker.bindPopup(`
                                <div style="padding: 8px;">
                                    <strong>${m.pharmacy_name}</strong><br>
                                    <small>Arba Minch, Ethiopia</small>
                                </div>
                            `);
                        }
                        
                        markers.push(marker);
                    }
                });
                
                // Fit bounds to show all markers
                if (markers.length > 0) {
                    const group = new L.featureGroup(markers);
                    map.fitBounds(group.getBounds(), { 
                        padding: [50, 50],
                        maxZoom: 15
                    });
                } else {
                    // Default view if no markers
                    map.setView(defaultCenter, 13);
                }
                
                // Add custom styles for markers
                const style = document.createElement('style');
                style.textContent = `
                    .pharmacy-marker {
                        background: var(--gradient-accent);
                        border-radius: 50%;
                        width: 30px;
                        height: 30px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: white;
                        box-shadow: var(--shadow-md);
                        border: 2px solid white;
                        cursor: pointer;
                        transition: all 0.3s ease;
                    }
                    
                    .pharmacy-marker:hover {
                        transform: scale(1.2);
                        box-shadow: var(--shadow-lg);
                    }
                    
                    .pharmacy-marker i {
                        font-size: 14px;
                    }
                    
                    .map-tiles {
                        filter: grayscale(20%) brightness(1.1);
                    }
                `;
                document.head.appendChild(style);
            }
            
            // Initialize map when DOM is loaded
            setTimeout(initMap, 500);
            
            // Add system status indicator
            function createSystemStatus() {
                const statusEl = document.createElement('div');
                statusEl.className = 'system-status-indicator';
                statusEl.innerHTML = `
                    <span class="status-indicator status-online"></span>
                    <span>System: Online</span>
                `;
                statusEl.style.cssText = `
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    background: var(--clinical-white);
                    border: 1px solid var(--clinical-border);
                    padding: 8px 16px;
                    border-radius: var(--radius-md);
                    font-size: 0.875rem;
                    font-weight: 500;
                    z-index: 999;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    color: var(--clinical-text);
                    box-shadow: var(--shadow-md);
                    opacity: 0;
                    transform: translateY(10px);
                    transition: all 0.3s ease;
                `;
                
                document.body.appendChild(statusEl);
                
                // Show after page load
                setTimeout(() => {
                    statusEl.style.opacity = '1';
                    statusEl.style.transform = 'translateY(0)';
                }, 1000);
                
                // Simulate status updates
                const statuses = [
                    { text: 'Syncing Data', class: 'status-updating' },
                    { text: 'Online', class: 'status-online' },
                    { text: 'Connected', class: 'status-online' }
                ];
                
                let statusIndex = 0;
                setInterval(() => {
                    const status = statuses[statusIndex];
                    const indicator = statusEl.querySelector('.status-indicator');
                    const textSpan = statusEl.querySelector('span:last-child');
                    
                    indicator.className = `status-indicator ${status.class}`;
                    textSpan.textContent = `System: ${status.text}`;
                    
                    statusIndex = (statusIndex + 1) % statuses.length;
                }, 10000);
            }
            
            createSystemStatus();
            
            // Add emergency contact popup
            function createEmergencyPopup() {
                const emergencyBtn = document.createElement('button');
                emergencyBtn.className = 'emergency-btn';
                emergencyBtn.innerHTML = `
                    <i class="fas fa-phone-alt"></i>
                    <span>Emergency</span>
                `;
                emergencyBtn.style.cssText = `
                    position: fixed;
                    bottom: 80px;
                    right: 20px;
                    background: linear-gradient(135deg, #ef4444, #dc2626);
                    color: white;
                    border: none;
                    border-radius: var(--radius-md);
                    padding: 12px 16px;
                    font-family: var(--font-system);
                    font-weight: 600;
                    font-size: 0.875rem;
                    cursor: pointer;
                    z-index: 999;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
                    transition: all 0.3s ease;
                `;
                
                emergencyBtn.addEventListener('mouseenter', () => {
                    emergencyBtn.style.transform = 'translateY(-2px)';
                    emergencyBtn.style.boxShadow = '0 6px 20px rgba(220, 38, 38, 0.4)';
                });
                
                emergencyBtn.addEventListener('mouseleave', () => {
                    emergencyBtn.style.transform = '';
                    emergencyBtn.style.boxShadow = '0 4px 12px rgba(220, 38, 38, 0.3)';
                });
                
                emergencyBtn.addEventListener('click', () => {
                    const emergencyNumbers = [
                        'Ambulance: 907',
                        'Police: 911',
                        'Fire: 939',
                        'Emergency: 991'
                    ];
                    
                    const popup = document.createElement('div');
                    popup.className = 'emergency-popup';
                    popup.innerHTML = `
                        <div style="padding: 20px; max-width: 300px;">
                            <h3 style="margin-bottom: 15px; color: var(--clinical-text);">Emergency Contacts</h3>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                ${emergencyNumbers.map(num => `
                                    <li style="padding: 10px 0; border-bottom: 1px solid var(--clinical-border); color: var(--clinical-text);">
                                        <i class="fas fa-phone" style="color: #ef4444; margin-right: 10px;"></i>
                                        ${num}
                                    </li>
                                `).join('')}
                            </ul>
                            <div style="margin-top: 15px; font-size: 0.875rem; color: var(--clinical-text-light);">
                                For immediate medical assistance, proceed to the nearest hospital.
                            </div>
                        </div>
                    `;
                    
                    popup.style.cssText = `
                        position: fixed;
                        bottom: 140px;
                        right: 20px;
                        background: var(--clinical-white);
                        border: 1px solid var(--clinical-border);
                        border-radius: var(--radius-lg);
                        box-shadow: var(--shadow-xl);
                        z-index: 1000;
                        animation: fadeInUp 0.3s ease;
                    `;
                    
                    // Remove any existing popup
                    const existingPopup = document.querySelector('.emergency-popup');
                    if (existingPopup) existingPopup.remove();
                    
                    document.body.appendChild(popup);
                    
                    // Close popup when clicking outside
                    setTimeout(() => {
                        const closePopup = (e) => {
                            if (!popup.contains(e.target) && !emergencyBtn.contains(e.target)) {
                                popup.remove();
                                document.removeEventListener('click', closePopup);
                            }
                        };
                        document.addEventListener('click', closePopup);
                    }, 100);
                });
                
                document.body.appendChild(emergencyBtn);
            }
            
            // Uncomment to add emergency button
            // createEmergencyPopup();

            // Logged-in user dropdown toggle
            const userBtn = document.querySelector('.user-dropdown .user-btn');
            const userMenu = document.querySelector('.user-dropdown .dropdown-menu');
            if (userBtn && userMenu) {
                userBtn.addEventListener('click', () => {
                    const expanded = userBtn.getAttribute('aria-expanded') === 'true';
                    userBtn.setAttribute('aria-expanded', (!expanded).toString());
                    userMenu.classList.toggle('show');
                });
                document.addEventListener('click', (e) => {
                    if (!e.target.closest('.user-dropdown')) {
                        userMenu.classList.remove('show');
                        userBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            }
            
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>