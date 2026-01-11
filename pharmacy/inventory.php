<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_functions.php';

// Require pharmacy auth and verification
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'pharmacy') {
	header('Location: ../login.php');
	exit();
}

if (empty($_SESSION['is_verified'])) {
	header('Location: pending.php');
	exit();
}

$conn = getDatabaseConnection();
$userId = (int)$_SESSION['user_id'];

// Get pharmacy record
$pharmacyStmt = $conn->prepare("SELECT * FROM pharmacies WHERE owner_id = ? LIMIT 1");
$pharmacyStmt->bind_param('i', $userId);
$pharmacyStmt->execute();
$pharmacy = $pharmacyStmt->get_result()->fetch_assoc();
$pharmacyStmt->close();

if (!$pharmacy) {
	header('Location: ../login.php');
	exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? '';
	if ($action === 'add') {
		// Required core fields
		$medicineName = trim($_POST['medicine_name'] ?? '');
		$quantity = max(0, (int)($_POST['quantity'] ?? 0));
		$price = (float)($_POST['price'] ?? 0);
		$expiry = $_POST['expiry_date'] ?? '';
		$reorder = max(0, (int)($_POST['reorder_level'] ?? 10));

		// Optional medicine metadata
		$genericName = trim($_POST['generic_name'] ?? '');
		$brandName = trim($_POST['brand_name'] ?? '');
		$manufacturer = trim($_POST['manufacturer'] ?? '');
		$categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : null;
		$medicineType = $_POST['medicine_type'] ?? 'tablet';
		$strength = trim($_POST['strength'] ?? '');
		$packageSize = trim($_POST['package_size'] ?? '');
		$requiresPrescription = isset($_POST['requires_prescription']) ? 1 : 0;

		// Optional inventory metadata
		$batch = trim($_POST['batch_number'] ?? '');
		$mfgDate = $_POST['manufacturing_date'] ?? null;
		$discountPct = isset($_POST['discount_percentage']) ? (float)$_POST['discount_percentage'] : 0.0;
		$supplierName = trim($_POST['supplier_name'] ?? '');
		$isDiscounted = isset($_POST['is_discounted']) ? 1 : 0;
		$isFeatured = isset($_POST['is_featured']) ? 1 : 0;

		// Normalize optional fields
		$batch = $batch !== '' ? $batch : null;
		$mfgDate = $mfgDate !== '' ? $mfgDate : null;
		$supplierName = $supplierName !== '' ? $supplierName : null;
		$discountPct = $discountPct >= 0 ? $discountPct : 0;

		if ($medicineName === '' || $quantity <= 0 || $price <= 0 || $expiry === '') {
			$error = 'Please provide medicine name, positive quantity, price, and expiry date.';
		} else {
			// Find or create medicine record
			$medStmt = $conn->prepare("SELECT medicine_id FROM medicines WHERE medicine_name = ? LIMIT 1");
			$medStmt->bind_param('s', $medicineName);
			$medStmt->execute();
			$medResult = $medStmt->get_result()->fetch_assoc();
			$medStmt->close();

			if ($medResult) {
				$medicineId = (int)$medResult['medicine_id'];
			} else {
				$insertMed = $conn->prepare("INSERT INTO medicines (medicine_name, generic_name, brand_name, manufacturer, category_id, medicine_type, strength, package_size, requires_prescription, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
				$catParam = $categoryId > 0 ? $categoryId : null;
				$insertMed->bind_param('ssssisssi', $medicineName, $genericName, $brandName, $manufacturer, $catParam, $medicineType, $strength, $packageSize, $requiresPrescription);
				if ($insertMed->execute()) {
					$medicineId = $insertMed->insert_id;
				} else {
					$error = 'Could not create medicine record: ' . $insertMed->error;
				}
				$insertMed->close();
			}

			if (empty($error)) {
				$insertInv = $conn->prepare("INSERT INTO pharmacy_inventory (pharmacy_id, medicine_id, quantity, reorder_level, price, discount_percentage, batch_number, manufacturing_date, expiry_date, supplier_name, is_discounted, is_featured, last_restocked) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), reorder_level = VALUES(reorder_level), price = VALUES(price), discount_percentage = VALUES(discount_percentage), batch_number = VALUES(batch_number), manufacturing_date = VALUES(manufacturing_date), expiry_date = VALUES(expiry_date), supplier_name = VALUES(supplier_name), is_discounted = VALUES(is_discounted), is_featured = VALUES(is_featured), last_restocked = NOW()");
				$insertInv->bind_param('iiiiddssssii', $pharmacy['pharmacy_id'], $medicineId, $quantity, $reorder, $price, $discountPct, $batch, $mfgDate, $expiry, $supplierName, $isDiscounted, $isFeatured);
				if ($insertInv->execute()) {
					$message = 'Medicine saved to inventory.';
				} else {
					$error = 'Could not save inventory item: ' . $insertInv->error;
				}
				$insertInv->close();
			}
		}
	} elseif ($action === 'delete') {
		$inventoryId = (int)($_POST['inventory_id'] ?? 0);
		if ($inventoryId > 0) {
			// Look up medicine_id for this inventory row (scoped to this pharmacy)
			$lookup = $conn->prepare("SELECT medicine_id FROM pharmacy_inventory WHERE inventory_id = ? AND pharmacy_id = ? LIMIT 1");
			$lookup->bind_param('ii', $inventoryId, $pharmacy['pharmacy_id']);
			$lookup->execute();
			$medRow = $lookup->get_result()->fetch_assoc();
			$lookup->close();

			if (!$medRow) {
				$error = 'Item not found for this pharmacy.';
			} else {
				$medicineIdForDelete = (int)$medRow['medicine_id'];
				$del = $conn->prepare("DELETE FROM pharmacy_inventory WHERE inventory_id = ? AND pharmacy_id = ? LIMIT 1");
				$del->bind_param('ii', $inventoryId, $pharmacy['pharmacy_id']);
				if ($del->execute()) {
					if ($del->affected_rows > 0) {
						// If no other inventory rows use this medicine anywhere, remove the medicine master record
						$countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM pharmacy_inventory WHERE medicine_id = ?");
						$countStmt->bind_param('i', $medicineIdForDelete);
						$countStmt->execute();
						$countResult = $countStmt->get_result()->fetch_assoc();
						$countStmt->close();
						if (($countResult['cnt'] ?? 0) == 0) {
							$delMed = $conn->prepare("DELETE FROM medicines WHERE medicine_id = ? LIMIT 1");
							$delMed->bind_param('i', $medicineIdForDelete);
							$delMed->execute();
							$delMed->close();
						}
						$message = 'Medicine removed from inventory.';
					} else {
						$error = 'Item not found for this pharmacy.';
					}
				} else {
					$error = 'Unable to delete item: ' . $del->error;
				}
				$del->close();
			}
		}
	}
}

// Inventory list
// Filter view: all | low | expiring
$view = $_GET['view'] ?? 'all';
$query = "SELECT pi.inventory_id, pi.quantity, pi.price, pi.expiry_date, pi.reorder_level, m.medicine_name 
		  FROM pharmacy_inventory pi 
		  JOIN medicines m ON pi.medicine_id = m.medicine_id 
		  WHERE pi.pharmacy_id = ?";
if ($view === 'low') {
	$query .= " AND pi.quantity <= pi.reorder_level ORDER BY pi.quantity ASC";
} elseif ($view === 'expiring') {
	$query .= " AND pi.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY pi.expiry_date ASC";
} else {
	$query .= " ORDER BY m.medicine_name ASC";
}
$invStmt = $conn->prepare($query);
$invStmt->bind_param('i', $pharmacy['pharmacy_id']);
$invStmt->execute();
$inventory = $invStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$invStmt->close();

// Preload medicine names for suggestions
$medList = $conn->query("SELECT medicine_name FROM medicines WHERE is_active = 1 ORDER BY medicine_name ASC LIMIT 200")->fetch_all(MYSQLI_ASSOC);

// Load categories
$categories = $conn->query("SELECT category_id, category_name FROM medicine_categories WHERE is_active = 1 ORDER BY category_name ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Inventory - <?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?></title>
	<link rel="stylesheet" href="../styles/pharmacy.css?v=20260111">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<style>
		.page { max-width: 1100px; margin: 0 auto; padding: 20px; }
		.card { background: #fff; border: 1px solid #d9e7f6; border-radius: 14px; box-shadow: 0 8px 20px rgba(16,37,66,0.06); padding: 18px; margin-bottom: 18px; }
		.flex { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
		.badge { background: #eef3f8; border: 1px solid #d9e7f6; border-radius: 999px; padding: 6px 10px; font-weight: 700; color: #4b5b70; }
		.table { width:100%; border-collapse: collapse; }
		.table th, .table td { padding: 10px; border-bottom:1px solid #d9e7f6; text-align:left; }
		.table th { color:#4b5b70; }
		.actions { display:flex; gap:8px; }
		.btn { border:1px solid #d9e7f6; background:#f7fbff; padding:10px 12px; border-radius:10px; cursor:pointer; font-weight:700; color:#102542; }
		.btn.primary { background: #102542; color:#fff; border-color:#102542; }
		.btn.danger { color:#b91c1c; border-color:#f3c4c4; background:#fff7f7; }
		form.inline { display:flex; gap:10px; flex-wrap:wrap; }
		input, select { padding:10px; border:1px solid #d9e7f6; border-radius:10px; width:100%; }
		label { display:block; font-weight:600; color:#102542; margin-bottom:4px; }
		.grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:12px; }
		.field-note { font-size: 0.85rem; color:#4b5b70; margin-top:4px; }
		.section-title { font-weight:800; margin:12px 0 4px; color:#102542; }
		.alert { padding:10px 12px; border-radius:10px; border:1px solid #d9e7f6; background:#eef3f8; color:#102542; margin-bottom:12px; }
		.alert.error { background:#fff7f7; border-color:#f3c4c4; color:#b91c1c; }
	</style>
</head>
<body class="pharmacy-dashboard" style="background:#f7fbff;">
	<div class="page">
		<div class="flex" style="margin-bottom:12px;">
			<div>
				<h1 style="margin:0;">Inventory</h1>
				<p style="margin:4px 0 0; color:#4b5b70;">Manage your medicines, pricing, and quantities.</p>
			</div>
			<div class="actions">
				<a class="btn" href="dashboard.php">Back to Dashboard</a>
			</div>
		</div>

		<?php if ($message): ?>
			<div class="alert"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
		<?php endif; ?>
		<?php if ($error): ?>
			<div class="alert error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
		<?php endif; ?>

		<?php if ($view === 'add'): ?>
		<div class="card" id="add">
			<div class="flex" style="margin-bottom:10px;">
				<h3 style="margin:0;">Add / Update Medicine</h3>
				<span class="badge">Pharmacy: <?php echo htmlspecialchars($pharmacy['pharmacy_name']); ?></span>
			</div>
			<form method="POST" class="grid">
				<input type="hidden" name="action" value="add">
				<div>
					<label>Medicine Name</label>
					<input type="text" name="medicine_name" list="medicine-suggestions" required>
					<datalist id="medicine-suggestions">
						<?php foreach ($medList as $m): ?>
						<option value="<?php echo htmlspecialchars($m['medicine_name']); ?>"></option>
						<?php endforeach; ?>
					</datalist>
					<p class="field-note">Start typing to pick an existing medicine or enter a new one.</p>
				</div>
				<div>
					<label>Quantity</label>
					<input type="number" name="quantity" min="1" required>
				</div>
				<div>
					<label>Price (ETB)</label>
					<input type="number" name="price" step="0.01" min="0.01" required>
				</div>
				<div>
					<label>Reorder Level</label>
					<input type="number" name="reorder_level" min="0" value="10">
				</div>
				<div>
					<label>Expiry Date</label>
					<input type="date" name="expiry_date" required>
				</div>
				<div>
					<label>Medicine Type</label>
					<select name="medicine_type">
						<option value="tablet">Tablet</option>
						<option value="capsule">Capsule</option>
						<option value="syrup">Syrup</option>
						<option value="injection">Injection</option>
						<option value="ointment">Ointment</option>
						<option value="drops">Drops</option>
						<option value="cream">Cream</option>
						<option value="gel">Gel</option>
						<option value="spray">Spray</option>
						<option value="inhaler">Inhaler</option>
					</select>
					<p class="field-note">Used when creating a new medicine.</p>
				</div>
				<div>
					<label>Generic Name (optional)</label>
					<input type="text" name="generic_name" placeholder="e.g., Paracetamol">
				</div>
				<div>
					<label>Brand Name (optional)</label>
					<input type="text" name="brand_name" placeholder="e.g., Panadol">
				</div>
				<div>
					<label>Manufacturer (optional)</label>
					<input type="text" name="manufacturer" placeholder="e.g., GlaxoSmithKline">
				</div>
				<div>
					<label>Category (optional)</label>
					<select name="category_id">
						<option value="">-- Select Category --</option>
						<?php foreach ($categories as $cat): ?>
						<option value="<?php echo (int)$cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label>Strength (optional)</label>
					<input type="text" name="strength" placeholder="e.g., 500 mg">
				</div>
				<div>
					<label>Package Size (optional)</label>
					<input type="text" name="package_size" placeholder="e.g., 10 tablets">
				</div>
				<div style="display:flex; align-items:center; gap:8px;">
					<input type="checkbox" id="requires_prescription" name="requires_prescription">
					<label for="requires_prescription" style="margin:0;">Requires Prescription</label>
				</div>

				<div class="section-title" style="grid-column:1 / -1;">Batch & Supplier (optional)</div>
				<div>
					<label>Batch Number</label>
					<input type="text" name="batch_number" placeholder="e.g., BN-12345">
				</div>
				<div>
					<label>Manufacturing Date</label>
					<input type="date" name="manufacturing_date">
				</div>
				<div>
					<label>Supplier Name</label>
					<input type="text" name="supplier_name" placeholder="e.g., ABC Pharma Supplier">
				</div>
				<div>
					<label>Discount (%)</label>
					<input type="number" name="discount_percentage" step="0.01" min="0" max="100" placeholder="0">
					<p class="field-note">Optional; set discount and toggle if active.</p>
				</div>
				<div style="display:flex; align-items:center; gap:8px;">
					<input type="checkbox" id="is_discounted" name="is_discounted">
					<label for="is_discounted" style="margin:0;">Discount Active</label>
				</div>
				<div style="display:flex; align-items:center; gap:8px;">
					<input type="checkbox" id="is_featured" name="is_featured">
					<label for="is_featured" style="margin:0;">Featured Item</label>
				</div>
				<div style="grid-column:1 / -1; text-align:right;">
					<button class="btn primary" type="submit">Save Medicine</button>
				</div>
			</form>
		</div>
		<?php endif; ?>

		<div class="card">
			<div class="flex" style="margin-bottom:8px;">
				<h3 style="margin:0;">Inventory List<?php echo $view==='low' ? ' - Low Stock' : ($view==='expiring' ? ' - Expiring Soon' : ''); ?></h3>
				<span class="badge"><?php echo count($inventory); ?> items</span>
			</div>
			<?php if (count($inventory) === 0): ?>
				<p style="color:#4b5b70;">No medicines added yet.</p>
			<?php else: ?>
			<div class="table-responsive">
				<table class="table">
					<thead>
						<tr>
							<th>Medicine</th>
							<th>Quantity</th>
							<th>Price</th>
							<th>Expiry</th>
							<th>Reorder</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($inventory as $item): ?>
						<tr>
							<td><?php echo htmlspecialchars($item['medicine_name']); ?></td>
							<td><?php echo (int)$item['quantity']; ?></td>
							<td>ETB <?php echo number_format((float)$item['price'], 2); ?></td>
							<td><?php echo htmlspecialchars($item['expiry_date']); ?></td>
							<td><?php echo (int)$item['reorder_level']; ?></td>
							<td>
								<form method="POST" class="inline" onsubmit="return confirm('Delete this medicine from your inventory?');">
									<input type="hidden" name="action" value="delete">
									<input type="hidden" name="inventory_id" value="<?php echo (int)$item['inventory_id']; ?>">
									<button class="btn danger" type="submit"><i class="fas fa-trash"></i> Delete</button>
								</form>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>
		</div>
	</div>
</body>
</html>
