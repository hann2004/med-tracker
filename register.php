<?php

require_once 'config/database.php';
require_once __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$conn = getDatabaseConnection();
$success = '';
$error = '';
$user_type = isset($_GET['type']) ? $_GET['type'] : 'user';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $user_type = $_POST['user_type'];
    $terms = isset($_POST['terms']);

    $isPharmacy = ($user_type === 'pharmacy');
    $pharmacy_name = trim($_POST['pharmacy_name'] ?? '');
    $license = trim($_POST['license'] ?? '');
    $pharmacy_address = trim($_POST['pharmacy_address'] ?? '');
    $pharmacy_phone = trim($_POST['pharmacy_phone'] ?? '');
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? floatval($_POST['latitude']) : 6.0395;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? floatval($_POST['longitude']) : 37.5445;

    if (!$terms) {
        $error = 'You must accept the Terms and Privacy Policy.';
    } elseif (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($isPharmacy && (empty($pharmacy_name) || empty($license) || empty($pharmacy_address) || empty($pharmacy_phone))) {
        $error = 'Pharmacy name, license, phone, and address are required for pharmacy registration.';
    } else {
        $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
        $checkStmt->bind_param("ss", $username, $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $error = 'Username or email already exists';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            // Always require email verification for both user and pharmacy
            $is_verified = 0;
            $verification_token = bin2hex(random_bytes(32));

            $insertStmt = $conn->prepare("INSERT INTO users (username, email, password_hash, user_type, full_name, phone_number, address, is_verified, verification_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insertStmt->bind_param("sssssssis", $username, $email, $password_hash, $user_type, $full_name, $phone, $address, $is_verified, $verification_token);

            if ($insertStmt->execute()) {
                $user_id = $conn->insert_id;
                if ($isPharmacy) {
                    $pharmacyStmt = $conn->prepare("INSERT INTO pharmacies (owner_id, pharmacy_name, license_number, address, latitude, longitude, phone) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $pharmacyStmt->bind_param("isssdds", $user_id, $pharmacy_name, $license, $pharmacy_address, $latitude, $longitude, $pharmacy_phone);
                    $pharmacyStmt->execute();
                    $pharmacyStmt->close();
                }

                if ($user_type === 'pharmacy') {
                    // Do NOT send activation/verification email yet
                    // Show pending message and redirect to pending page
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['user_type'] = $user_type;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['is_verified'] = false;
                    $success = 'Registration successful! Your pharmacy is pending admin approval. You will be notified by email when approved or declined.';
                    header('Location: pharmacy/pending.php');
                    exit;
                } else {
                    // Send verification email for normal users only
                    $mail = new PHPMailer(true);
                    try {
                        // Enable verbose debug output (comment out in production)
                        $mail->SMTPDebug = 2; // 0 = off, 1 = client, 2 = client and server
                        $mail->Debugoutput = function($str, $level) {
                            error_log("PHPMailer [$level]: $str");
                        };
                        
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'medicine.trackerarbaminch@gmail.com';
                        $mail->Password = 'xukw hgxz odxb mgmh'; // App Password
                        $mail->SMTPSecure = 'tls';
                        $mail->Port = 587;
                        $mail->Timeout = 30; // Increase timeout to 30 seconds

                        $mail->setFrom('medicine.trackerarbaminch@gmail.com', 'MedTracker');
                        $mail->addAddress($email, $full_name);
                        $mail->isHTML(true);
                        $mail->Subject = 'Verify your email address';
                        $verifyUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verify_email.php?token=$verification_token";
                        $mail->Body = "<h2>Welcome to MedTrack!</h2><p>To activate your account, please verify your email by clicking the link below:</p><p><a href='$verifyUrl'>$verifyUrl</a></p><p>If you did not register, please ignore this email.</p>";
                        $mail->AltBody = "Welcome to MedTrack! To activate your account, visit: $verifyUrl";
                        $mail->send();
                        $success = 'Registration successful! Please check your email to verify your account.';
                    } catch (Exception $e) {
                        // Log the actual error for debugging
                        error_log("Email sending failed: " . $e->getMessage());
                        $error = 'Registration succeeded, but verification email could not be sent. Error: ' . $e->getMessage();
                    }
                }
            } else {
                $error = 'Registration failed. Please try again.';
            }

            $insertStmt->close();
        }
        $checkStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | MedTrack Arba Minch</title>
    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .auth-shell { min-height: 100vh; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); display: flex; align-items: center; justify-content: center; padding: var(--space-2xl) var(--space-lg); }
        .auth-card { width: 100%; max-width: 1200px; background: var(--clinical-white); border: 1px solid var(--clinical-border); border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); display: grid; grid-template-columns: 1.2fr 0.8fr; overflow: hidden; }
        .auth-form-pane { padding: var(--space-3xl); }
        .auth-visual { background: var(--gradient-medical); padding: var(--space-3xl); border-left: 1px solid var(--clinical-border); display: flex; flex-direction: column; justify-content: space-between; }
        .auth-logo { display: inline-flex; align-items: center; gap: var(--space-sm); font-weight: 800; color: var(--clinical-text); text-decoration: none; }
        .auth-logo-icon { width: 44px; height: 44px; border-radius: var(--radius-md); background: var(--gradient-accent); color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow-accent); }
        .auth-header h1 { margin: 0 0 var(--space-sm); }
        .auth-header p { margin: 0; color: var(--clinical-text-light); }
        .type-toggle { display: inline-flex; gap: var(--space-sm); background: var(--clinical-light); padding: var(--space-xs); border-radius: var(--radius-full); margin-top: var(--space-lg); }
        .type-btn { border: 1px solid var(--clinical-border); background: #fff; border-radius: var(--radius-full); padding: 0.5rem 1rem; cursor: pointer; font-weight: 600; color: var(--clinical-text); }
        .type-btn.active { background: var(--gradient-accent); color: #fff; border-color: var(--clinical-accent); }
        .form-stack { display: grid; gap: var(--space-lg); margin-top: var(--space-2xl); }
        .form-row { display: grid; gap: var(--space-lg); grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .form-group { display: grid; gap: var(--space-xs); }
        .form-group label { font-weight: 600; color: var(--clinical-text); display: flex; gap: var(--space-xs); align-items: center; }
        .form-group input { border: 1px solid var(--clinical-border); border-radius: var(--radius-md); padding: 0.9rem 1rem; font-size: 1rem; transition: var(--transition-fast); }
        .form-group input:focus { outline: none; border-color: var(--clinical-accent); box-shadow: 0 0 0 4px rgba(37,99,235,0.08); }
        .hidden { display: none; }
        .btn-primary { width: 100%; justify-content: center; padding: 0.95rem 1rem; font-size: 1.05rem; margin-top: var(--space-lg); }
        .alert { padding: 0.85rem 1rem; border-radius: var(--radius-md); display: flex; align-items: center; gap: var(--space-sm); font-weight: 600; margin-top: var(--space-md); }
        .alert-success { background: rgba(16,185,129,0.08); color: #047857; border: 1px solid rgba(16,185,129,0.25); }
        .alert-error { background: rgba(239,68,68,0.08); color: #b91c1c; border: 1px solid rgba(239,68,68,0.25); }
        .benefits { display: grid; gap: var(--space-sm); }
        .pill { display: inline-flex; align-items: center; gap: var(--space-sm); padding: 0.75rem 1rem; background: rgba(37,99,235,0.08); color: var(--clinical-text); border-radius: var(--radius-lg); border: 1px solid rgba(37,99,235,0.12); }
        @media (max-width: 992px) { .auth-card { grid-template-columns: 1fr; } .auth-visual { display: none; } }
        @media (max-width: 640px) {
            .auth-shell { padding: var(--space-xl) var(--space-md); }
            .auth-card { border-radius: var(--radius-lg); box-shadow: var(--shadow-md); }
            .auth-form-pane { padding: var(--space-xl); }
            .form-row { grid-template-columns: 1fr; }
            .type-toggle { width: 100%; justify-content: space-between; }
        }
    </style>
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-form-pane">
                <a href="index.php" class="auth-logo">
                    <span class="auth-logo-icon"><i class="fas fa-capsules"></i></span>
                    <span>MedTrack Arba Minch</span>
                </a>
                <div class="auth-header" style="margin-top: var(--space-lg);">
                    <h1>Create account</h1>
                    <p>Access live medicine availability and pharmacy tools</p>
                </div>

                <div class="type-toggle">
                    <button type="button" class="type-btn <?php echo $user_type === 'user' ? 'active' : ''; ?>" data-type="user">Patient</button>
                    <button type="button" class="type-btn <?php echo $user_type === 'pharmacy' ? 'active' : ''; ?>" data-type="pharmacy">Pharmacy</button>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
                <?php elseif ($error): ?>
                    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="" class="form-stack" id="registrationForm">
                    <input type="hidden" name="user_type" id="userType" value="<?php echo htmlspecialchars($user_type); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" name="full_name" placeholder="Your full name" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Username *</label>
                            <input type="text" name="username" placeholder="Choose a username" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address *</label>
                        <input type="email" name="email" placeholder="you@example.com" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Password *</label>
                            <input type="password" name="password" placeholder="At least 8 characters" required minlength="8">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Confirm Password *</label>
                            <input type="password" name="confirm_password" placeholder="Re-enter password" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone</label>
                            <input type="tel" name="phone" placeholder="+251 900 000 000">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Address</label>
                            <input type="text" name="address" placeholder="Your address in Arba Minch">
                        </div>
                    </div>

                    <div id="pharmacyFields" class="<?php echo $user_type === 'pharmacy' ? '' : 'hidden'; ?>">
                        <div class="form-group">
                            <label><i class="fas fa-clinic-medical"></i> Pharmacy Name *</label>
                            <input type="text" name="pharmacy_name" placeholder="Pharmacy name" <?php echo $user_type === 'pharmacy' ? 'required' : ''; ?>>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-file-certificate"></i> License Number *</label>
                                <input type="text" name="license" placeholder="License number" <?php echo $user_type === 'pharmacy' ? 'required' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Pharmacy Phone *</label>
                                <input type="tel" name="pharmacy_phone" placeholder="Pharmacy phone" <?php echo $user_type === 'pharmacy' ? 'required' : ''; ?>>
                            </div>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-map-marker-alt"></i> Pharmacy Address *</label>
                            <input type="text" name="pharmacy_address" placeholder="Full pharmacy address" <?php echo $user_type === 'pharmacy' ? 'required' : ''; ?>>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-globe"></i> Latitude</label>
                                <input type="number" step="any" name="latitude" placeholder="6.0395" value="6.0395">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-globe"></i> Longitude</label>
                                <input type="number" step="any" name="longitude" placeholder="37.5445" value="37.5445">
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="display:flex; gap:10px; align-items:center;">
                        <input type="checkbox" name="terms" id="terms" required>
                        <label for="terms" style="margin:0; font-weight:500;">I agree to the Terms of Service and Privacy Policy</label>
                    </div>

                    <button type="submit" class="btn-primary"><i class="fas fa-user-plus"></i> Create account</button>

                    <div style="color: var(--clinical-text-light); text-align:center; font-size:0.95rem;">Already have an account? <a href="login.php" style="color: var(--clinical-accent); font-weight:700;">Sign in</a></div>
                </form>
            </div>
            <div class="auth-visual">
                <div>
                    <h3 style="margin-bottom: var(--space-md);">Why join?</h3>
                    <div class="benefits">
                        <span class="pill"><i class="fas fa-check-circle"></i> Find medicines fast</span>
                        <span class="pill"><i class="fas fa-check-circle"></i> Real-time stock visibility</span>
                        <span class="pill"><i class="fas fa-check-circle"></i> Coverage: Secha, Sikela, Town Center</span>
                        <span class="pill"><i class="fas fa-check-circle"></i> Secure accounts with verification</span>
                    </div>
                </div>
                <div style="margin-top: var(--space-2xl); color: var(--clinical-text-light); font-size: 0.95rem;">
                    Pharmacy owner? Gain visibility, manage inventory, and appear on the live map.
                </div>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('.type-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const type = btn.dataset.type;
                document.getElementById('userType').value = type;
                document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const phFields = document.getElementById('pharmacyFields');
                if (type === 'pharmacy') {
                    phFields.classList.remove('hidden');
                    phFields.querySelectorAll('input').forEach(i => i.required = true);
                } else {
                    phFields.classList.add('hidden');
                    phFields.querySelectorAll('input').forEach(i => i.required = false);
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>