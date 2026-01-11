<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$conn = getDatabaseConnection();
$cats = $conn->query("SELECT c.category_id, c.category_name, c.is_active, COUNT(m.medicine_id) as medicine_count FROM medicine_categories c LEFT JOIN medicines m ON m.category_id = c.category_id GROUP BY c.category_id, c.category_name, c.is_active ORDER BY c.category_name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Admin</title>
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
                    <h2>Categories</h2>
                </div>
                <div class="section-card">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Active</th>
                                    <th>Medicines</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cats as $c): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($c['category_name']); ?></td>
                                    <td><?php echo ((int)$c['is_active'] === 1) ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo (int)$c['medicine_count']; ?></td>
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
