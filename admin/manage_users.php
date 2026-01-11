<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
	header('Location: ../login.php');
	exit();
}

$conn = getDatabaseConnection();
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

$where = [];
if ($q !== '') {
	$esc = $conn->real_escape_string($q);
	$where[] = "(username LIKE '%$esc%' OR email LIKE '%$esc%' OR full_name LIKE '%$esc%')";
}
if ($type !== '' && in_array($type, ['admin','pharmacy','user'])) {
	$where[] = "user_type = '" . $conn->real_escape_string($type) . "'";
}
$sql = "SELECT user_id, username, email, user_type, is_verified, full_name, phone_number, last_login, created_at FROM users";
if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY created_at DESC LIMIT 200';
$users = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$counts = [
	'total' => (int)$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'],
	'admins' => (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE user_type='admin'")->fetch_assoc()['c'],
	'pharmacies' => (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE user_type='pharmacy'")->fetch_assoc()['c'],
	'users' => (int)$conn->query("SELECT COUNT(*) AS c FROM users WHERE user_type='user'")->fetch_assoc()['c'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Manage Users - Admin</title>
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
					<h2>Users</h2>
					<div class="date-range" style="display:flex;gap:10px;align-items:center;">
						<span class="badge">Total: <?php echo $counts['total']; ?></span>
						<span class="badge">Admins: <?php echo $counts['admins']; ?></span>
						<span class="badge">Pharmacies: <?php echo $counts['pharmacies']; ?></span>
						<span class="badge">Users: <?php echo $counts['users']; ?></span>
					</div>
				</div>
				<div class="section-card">
					<div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
						<form method="get" class="search-box" style="gap:8px;">
							<i class="fas fa-search"></i>
							<input type="text" name="q" placeholder="Search username, email..." value="<?php echo htmlspecialchars($q); ?>">
							<select name="type" class="date-select">
								<option value="">All Types</option>
								<option value="admin" <?php echo $type==='admin'?'selected':''; ?>>Admin</option>
								<option value="pharmacy" <?php echo $type==='pharmacy'?'selected':''; ?>>Pharmacy</option>
								<option value="user" <?php echo $type==='user'?'selected':''; ?>>User</option>
							</select>
							<button class="notification-btn" style="width:auto; padding:6px 10px;">Filter</button>
						</form>
					</div>
					<div class="table-responsive">
						<table class="data-table">
							<thead>
								<tr>
									<th>Full Name</th>
									<th>Username</th>
									<th>Email</th>
									<th>Type</th>
									<th>Verified</th>
									<th>Last Login</th>
									<th>Joined</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($users as $u): ?>
								<tr>
									<td><?php echo htmlspecialchars($u['full_name']); ?></td>
									<td><?php echo htmlspecialchars($u['username']); ?></td>
									<td><?php echo htmlspecialchars($u['email']); ?></td>
									<td><?php echo htmlspecialchars($u['user_type']); ?></td>
									<td><?php echo ((int)$u['is_verified']===1) ? 'Yes' : 'No'; ?></td>
									<td><?php echo $u['last_login'] ? date('M d, Y H:i', strtotime($u['last_login'])) : '-'; ?></td>
									<td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
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
