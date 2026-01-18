<?php
// Authentication helpers for login/logout and protected pages
require_once __DIR__ . '/../config/database.php';

const AUTH_MAX_ATTEMPTS = 5;
const AUTH_LOCK_MINUTES = 15;

function authFindUserByIdentifier(mysqli $conn, string $identifier): ?array {
	$stmt = $conn->prepare("SELECT user_id, username, email, password_hash, user_type, full_name, is_verified, failed_login_attempts, account_locked_until FROM users WHERE email = ? OR username = ? LIMIT 1");
	$stmt->bind_param('ss', $identifier, $identifier);
	$stmt->execute();
	$user = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $user ?: null;
}

function authRecordFailed(mysqli $conn, int $userId): void {
	$attempts = AUTH_MAX_ATTEMPTS;
	$lock = AUTH_LOCK_MINUTES;
	$stmt = $conn->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1, account_locked_until = CASE WHEN failed_login_attempts + 1 >= ? THEN DATE_ADD(NOW(), INTERVAL ? MINUTE) ELSE account_locked_until END WHERE user_id = ?");
	$stmt->bind_param('iii', $attempts, $lock, $userId);
	$stmt->execute();
	$stmt->close();
}

function authResetAttempts(mysqli $conn, int $userId): void {
	$stmt = $conn->prepare("UPDATE users SET failed_login_attempts = 0, account_locked_until = NULL WHERE user_id = ?");
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$stmt->close();
}

function authUpdateLoginMeta(mysqli $conn, int $userId): void {
	$ip = $_SERVER['REMOTE_ADDR'] ?? null;
	$stmt = $conn->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE user_id = ?");
	$stmt->bind_param('si', $ip, $userId);
	$stmt->execute();
	$stmt->close();
}

function authLogin(mysqli $conn, string $identifier, string $password): array {
	$user = authFindUserByIdentifier($conn, $identifier);
	if (!$user) {
		return ['success' => false, 'message' => 'Invalid credentials'];
	}

	if (!empty($user['account_locked_until']) && strtotime($user['account_locked_until']) > time()) {
		return ['success' => false, 'message' => 'Account locked. Try again later'];
	}

	if (!password_verify($password, $user['password_hash'])) {
		authRecordFailed($conn, (int) $user['user_id']);
		return ['success' => false, 'message' => 'Invalid credentials'];
	}

	// Block login for unverified users (except pharmacy, who go to pending page)
	if (!$user['is_verified']) {
		if ($user['user_type'] === 'pharmacy') {
			// Allow login but redirect to pending page
		} else {
			return ['success' => false, 'message' => 'Please verify your email before logging in.'];
		}
	}

	authResetAttempts($conn, (int) $user['user_id']);
	authUpdateLoginMeta($conn, (int) $user['user_id']);

	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}

	$_SESSION['user_id'] = $user['user_id'];
	$_SESSION['username'] = $user['username'];
	$_SESSION['user_type'] = $user['user_type'];
	$_SESSION['full_name'] = $user['full_name'];
	$_SESSION['is_verified'] = (bool) $user['is_verified'];

	return ['success' => true, 'message' => 'Login successful'];
}

function authRedirectForRole(string $userType): string {
	if ($userType === 'admin') {
		return 'admin/dashboard.php';
	}
	if ($userType === 'pharmacy') {
		return ($_SESSION['is_verified'] ?? false) ? 'pharmacy/dashboard.php' : 'pharmacy/pending.php';
	}
	// For regular users, go to the user dashboard
	return 'user/dashboard.php';
}

function authRequireLogin(): void {
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
	if (!isset($_SESSION['user_id'])) {
		header('Location: /login.php');
		exit;
	}
}
