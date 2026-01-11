<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_functions.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$conn = getDatabaseConnection();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pharmacyId = (int)($_POST['pharmacy_id'] ?? 0);

    if ($action === 'add') {
        $ownerId = (int)($_POST['owner_id'] ?? 0);
        $pharmacyName = trim($_POST['pharmacy_name'] ?? '');
        $license = trim($_POST['license'] ?? '');
        $address = trim($_POST['address'] ?? '');
        if ($ownerId <= 0 || $pharmacyName === '' || $license === '' || $address === '') {
            $error = 'All fields are required to add a pharmacy.';
        } else {
            $stmt = $conn->prepare("INSERT INTO pharmacies (owner_id, pharmacy_name, license_number, address, city, latitude, longitude, phone, is_verified, is_active) VALUES (?, ?, ?, ?, 'Arba Minch', 6.0395, 37.5445, '', 0, 0)");
            $stmt->bind_param('isss', $ownerId, $pharmacyName, $license, $address);
            if ($stmt->execute()) {
                $message = 'Pharmacy added and marked pending verification.';
            } else {
                $error = 'Could not add pharmacy (license may already exist).';
            }
            $stmt->close();
        }
    } elseif ($pharmacyId > 0) {
        if ($action === 'approve') {
            // Approve pharmacy
            $stmt = $conn->prepare("UPDATE pharmacies SET is_verified = 1, is_active = 1 WHERE pharmacy_id = ? LIMIT 1");
            $stmt->bind_param('i', $pharmacyId);
            $stmt->execute();
            $stmt->close();

            // Also mark the owner user as verified so login redirects to dashboard
            $ownerStmt = $conn->prepare("SELECT owner_id FROM pharmacies WHERE pharmacy_id = ? LIMIT 1");
            $ownerStmt->bind_param('i', $pharmacyId);
            $ownerStmt->execute();
            $ownerRow = $ownerStmt->get_result()->fetch_assoc();
            $ownerStmt->close();
            if ($ownerRow && (int)$ownerRow['owner_id'] > 0) {
                $u = $conn->prepare("UPDATE users SET is_verified = 1 WHERE user_id = ? LIMIT 1");
                $ownerId = (int)$ownerRow['owner_id'];
                $u->bind_param('i', $ownerId);
                $u->execute();
                $u->close();
            }

            $message = 'Pharmacy approved.';
        } elseif ($action === 'deactivate') {
            $stmt = $conn->prepare("UPDATE pharmacies SET is_active = 0 WHERE pharmacy_id = ? LIMIT 1");
            $stmt->bind_param('i', $pharmacyId);
            $stmt->execute();
            $stmt->close();
            $message = 'Pharmacy deactivated.';
        } elseif ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM pharmacies WHERE pharmacy_id = ? LIMIT 1");
            $stmt->bind_param('i', $pharmacyId);
            $stmt->execute();
            $stmt->close();
            $message = 'Pharmacy deleted.';
        }
    }
}

$pending = $conn->query("SELECT p.*, u.full_name, u.email FROM pharmacies p JOIN users u ON p.owner_id = u.user_id WHERE p.is_verified = 0 ORDER BY p.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$all = $conn->query("SELECT p.*, u.full_name, u.email FROM pharmacies p JOIN users u ON p.owner_id = u.user_id ORDER BY p.created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
$pharmacyOwners = $conn->query("SELECT user_id, full_name, email FROM users WHERE user_type = 'pharmacy' ORDER BY created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pharmacies</title>
    <link rel="stylesheet" href="../styles/admin.css?v=20260111">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background:#f8fafc; font-family:'Inter',system-ui; }
        .page { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:1.25rem; box-shadow:0 10px 24px rgba(0,0,0,0.04); margin-bottom:1.5rem; }
        table { width:100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; border-bottom:1px solid #e5e7eb; text-align:left; }
        th { background:#f1f5f9; font-weight:700; color:#0f172a; }
        .badge { padding: 0.35rem 0.7rem; border-radius:999px; font-weight:700; font-size:0.85rem; }
        .badge.pending { background: rgba(234,179,8,0.15); color:#b45309; }
        .badge.verified { background: rgba(16,185,129,0.15); color:#065f46; }
        .badge.inactive { background: rgba(239,68,68,0.12); color:#b91c1c; }
        .actions { display:flex; gap:0.5rem; flex-wrap:wrap; }
        button { padding:0.5rem 0.9rem; border-radius:10px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; font-weight:700; }
        button.approve { background: linear-gradient(135deg,#16a34a,#22c55e); color:#fff; border:none; }
        button.deactivate { background:#fff; color:#b91c1c; }
        .alert { padding:0.9rem 1rem; border-radius:10px; margin-bottom:1rem; background:rgba(16,185,129,0.1); color:#065f46; border:1px solid rgba(16,185,129,0.3); }
    </style>
</head>
<body>
    <div class="page">
        <h1 style="margin:0 0 1rem;">Manage Pharmacies</h1>
        <?php if ($message): ?><div class="alert" style="background:rgba(16,185,129,0.1); color:#065f46; border:1px solid rgba(16,185,129,0.3);"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert" style="background:rgba(239,68,68,0.08); color:#b91c1c; border:1px solid rgba(239,68,68,0.2);"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <div class="card">
            <h2 style="margin-top:0;">Add Pharmacy</h2>
            <form method="POST" style="display:grid; gap:0.75rem; grid-template-columns: repeat(auto-fit,minmax(220px,1fr));">
                <input type="hidden" name="action" value="add">
                <div>
                    <label>Owner</label>
                    <select name="owner_id" required style="width:100%; padding:0.65rem; border-radius:10px; border:1px solid #e2e8f0;">
                        <option value="">Select pharmacy owner</option>
                        <?php foreach ($pharmacyOwners as $o): ?>
                            <option value="<?php echo (int)$o['user_id']; ?>"><?php echo htmlspecialchars($o['full_name'] . ' (' . $o['email'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Pharmacy Name</label>
                    <input type="text" name="pharmacy_name" required style="width:100%; padding:0.65rem; border-radius:10px; border:1px solid #e2e8f0;">
                </div>
                <div>
                    <label>License</label>
                    <input type="text" name="license" required style="width:100%; padding:0.65rem; border-radius:10px; border:1px solid #e2e8f0;">
                </div>
                <div>
                    <label>Address</label>
                    <input type="text" name="address" required style="width:100%; padding:0.65rem; border-radius:10px; border:1px solid #e2e8f0;">
                </div>
                <div style="grid-column:1/-1; text-align:right;">
                    <button type="submit" class="approve" style="border:none; padding:0.7rem 1.2rem; border-radius:10px; background:linear-gradient(135deg,#16a34a,#22c55e); color:#fff; font-weight:700;">Add Pharmacy</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">Pending Verification</h2>
            <?php if (count($pending) === 0): ?>
                <p style="color:#475569;">No pending pharmacies.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Pharmacy</th>
                        <th>Owner</th>
                        <th>Email</th>
                        <th>License</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['pharmacy_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['license_number']); ?></td>
                        <td><span class="badge pending">Pending</span></td>
                        <td class="actions">
                            <form method="POST" style="margin:0; display:flex; gap:6px; flex-wrap:wrap;">
                                <input type="hidden" name="pharmacy_id" value="<?php echo (int)$row['pharmacy_id']; ?>">
                                <button type="submit" name="action" value="approve" class="approve">Approve</button>
                                <button type="submit" name="action" value="deactivate" class="deactivate">Deactivate</button>
                                <button type="submit" name="action" value="delete" class="deactivate" style="color:#b91c1c; border-color:#fca5a5;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 style="margin-top:0;">All Pharmacies</h2>
            <table>
                <thead>
                    <tr>
                        <th>Pharmacy</th>
                        <th>Owner</th>
                        <th>Email</th>
                        <th>License</th>
                        <th>Status</th>
                        <th>Active</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['pharmacy_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['license_number']); ?></td>
                        <td><span class="badge <?php echo $row['is_verified'] ? 'verified' : 'pending'; ?>"><?php echo $row['is_verified'] ? 'Verified' : 'Pending'; ?></span></td>
                        <td><span class="badge <?php echo $row['is_active'] ? 'verified' : 'inactive'; ?>"><?php echo $row['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                        <td class="actions">
                            <form method="POST" style="margin:0; display:flex; gap:6px; flex-wrap:wrap;">
                                <input type="hidden" name="pharmacy_id" value="<?php echo (int)$row['pharmacy_id']; ?>">
                                <button type="submit" name="action" value="approve" class="approve">Approve</button>
                                <button type="submit" name="action" value="deactivate" class="deactivate">Deactivate</button>
                                <button type="submit" name="action" value="delete" class="deactivate" style="color:#b91c1c; border-color:#fca5a5;">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
