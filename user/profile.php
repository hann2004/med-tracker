<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_functions.php';

// Require a signed-in regular user
authRequireLogin();
if (($_SESSION['user_type'] ?? '') !== 'user') {
	header('Location: ../index.php');
	exit;
}

$conn = getDatabaseConnection();
$userId = (int) $_SESSION['user_id'];
$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$fullName = trim($_POST['full_name'] ?? '');
	$phone    = trim($_POST['phone_number'] ?? '');
	$address  = trim($_POST['address'] ?? '');

	if ($fullName === '') {
		$error = 'Full name is required';
	} else {
		$stmt = $conn->prepare("UPDATE users SET full_name = ?, phone_number = ?, address = ? WHERE user_id = ? LIMIT 1");
		$stmt->bind_param('sssi', $fullName, $phone, $address, $userId);
		if ($stmt->execute()) {
			$success = 'Profile updated';
			$_SESSION['full_name'] = $fullName;
		} else {
			$error = 'Could not update profile. Please try again.';
		}
		$stmt->close();
	}
}

// Fetch latest user data
$stmt = $conn->prepare("SELECT username, email, full_name, phone_number, address, profile_image, user_type FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
	http_response_code(404);
	echo 'User not found';
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Profile | MedTrack</title>
	<link rel="stylesheet" href="../styles/main.css">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>
<body>
	<div class="profile-shell">
		<div class="top-nav">
			<a class="btn-secondary" href="../index.php"><i class="fas fa-arrow-left"></i> Home</a>
			<a class="btn-secondary" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
		</div>
		<div class="profile-card">
			<div class="profile-header">
				<div class="avatar-lg"><i class="fas fa-user"></i></div>
				<div>
					<h2 style="margin:0;">My Profile</h2>
					<div style="color:var(--clinical-text-light);">@<?php echo htmlspecialchars($user['username']); ?> • <?php echo htmlspecialchars($user['email']); ?></div>
				</div>
			</div>

			<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
			<?php if ($error): ?><div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

			<form method="POST" action="">
				<div class="grid-2">
					<div>
						<label>Full name</label>
						<input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
					</div>
					<div>
						<label>Phone number</label>
						<input type="text" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>">
					</div>
				</div>
				<div>
					<label>Address</label>
					<textarea name="address" placeholder="Where can pharmacies reach you?"><?php echo htmlspecialchars($user['address']); ?></textarea>
				</div>
				<div class="actions">
					<button class="btn-primary" type="submit"><i class="fas fa-save"></i> Save changes</button>
				</div>
			</form>
		</div>
	</div>
</body>
</html>
