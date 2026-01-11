<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$conn = getDatabaseConnection();
$user_id = (int)$_SESSION['user_id'];
$user = $conn->query("SELECT username, email, full_name, phone_number, address, last_login FROM users WHERE user_id = $user_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - MedTrack</title>
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
                    <h2>Profile</h2>
                </div>
                <div class="section-card">
                    <div class="card-body">
                        <div class="request-item" style="display:flex;flex-direction:column;gap:10px;">
                            <div class="request-details">
                                <span><strong>Full Name:</strong> <?php echo htmlspecialchars($user['full_name']); ?></span>
                                <span><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></span>
                                <span><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></span>
                                <span><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone_number'] ?? '-'); ?></span>
                                <span><strong>Address:</strong> <?php echo htmlspecialchars($user['address'] ?? '-'); ?></span>
                                <span><strong>Last Login:</strong> <?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : '-'; ?></span>
                            </div>
                        </div>
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
