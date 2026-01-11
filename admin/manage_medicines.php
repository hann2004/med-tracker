<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$conn = getDatabaseConnection();
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$sql = "SELECT m.medicine_id, m.medicine_name, m.generic_name, m.brand_name, m.manufacturer, c.category_name, m.requires_prescription
        FROM medicines m
        LEFT JOIN medicine_categories c ON m.category_id = c.category_id";
if ($search !== '') {
    $esc = $conn->real_escape_string($search);
    $sql .= " WHERE m.medicine_name LIKE '%$esc%' OR m.generic_name LIKE '%$esc%' OR m.brand_name LIKE '%$esc%'";
}
$sql .= " ORDER BY m.medicine_name ASC LIMIT 200";
$medicines = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Medicines - Admin</title>
    <link rel="stylesheet" href="../styles/admin.css?v=20260111">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                    <h2>Medicines</h2>
                    <form method="get" class="search-box" style="gap:8px;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="q" placeholder="Search medicines..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="notification-btn" style="width:auto; padding:6px 10px;">Filter</button>
                    </form>
                </div>
                <div class="section-card">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Generic</th>
                                    <th>Brand</th>
                                    <th>Manufacturer</th>
                                    <th>Category</th>
                                    <th>Rx</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medicines as $m): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($m['medicine_name']); ?></td>
                                    <td><?php echo htmlspecialchars($m['generic_name']); ?></td>
                                    <td><?php echo htmlspecialchars($m['brand_name']); ?></td>
                                    <td><?php echo htmlspecialchars($m['manufacturer']); ?></td>
                                    <td><?php echo htmlspecialchars($m['category_name'] ?? '-'); ?></td>
                                    <td><?php echo $m['requires_prescription'] ? 'Yes' : 'No'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <footer class="dashboard-footer">
            <div class="footer-content">
                <div class="system-info">
                    <span><i class="fas fa-database"></i> Database: Connected</span>
                </div>
                <div class="copyright">© <?php echo date('Y'); ?> MedTrack Arba Minch</div>
            </div>
        </footer>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.user-dropdown')) {
                document.querySelectorAll('.dropdown-menu.show').forEach(function(m) { m.classList.remove('show'); });
            }
        });
    });
    </script>
</body>
</html>
