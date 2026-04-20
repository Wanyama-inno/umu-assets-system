<?php
require_once '../includes/config.php';
requireLogin();

$conn = getDBConnection();
$error = ''; $success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_asset' || $action === 'edit_asset') {
        $name = sanitize($conn, $_POST['asset_name']);
        $code = sanitize($conn, $_POST['asset_code']);
        $cat = (int)$_POST['category_id'];
        $type = sanitize($conn, $_POST['asset_type']);
        $cond = sanitize($conn, $_POST['condition']);
        $status = sanitize($conn, $_POST['status']);
        $loc = sanitize($conn, $_POST['location']);
        $desc = sanitize($conn, $_POST['description']);
        $pdate = sanitize($conn, $_POST['purchase_date']);
        $pval = (float)$_POST['purchase_value'];
        $image_path = NULL;

        if (isset($_FILES['asset_image']) && $_FILES['asset_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if ($_FILES['asset_image']['size'] <= $max_size && in_array($_FILES['asset_image']['type'], $allowed_types)) {
                $image_ext = pathinfo($_FILES['asset_image']['name'], PATHINFO_EXTENSION);
                $image_name = uniqid('asset_') . '.' . $image_ext;
                if (move_uploaded_file($_FILES['asset_image']['tmp_name'], $upload_dir . $image_name)) {
                    $image_path = $upload_dir . $image_name;
                } else {
                    $error = 'Failed to upload image.';
                }
            } else {
                $error = 'Invalid image type or size too large.';
            }
        }

        if ($action === 'add_asset') {
            $stmt = $conn->prepare("INSERT INTO assets (asset_name, asset_code, category_id, asset_type, `condition`, status, location, description, purchase_date, purchase_value, image_path, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssisssssdisii", $name, $code, $cat, $type, $cond, $status, $loc, $desc, $pdate, $pval, $image_path, $_SESSION['user_id']);
            if ($stmt->execute()) $success = 'Asset added successfully!';
            else $error = 'Failed to add asset. Asset code may already exist.';
        } else {
            $id = (int)$_POST['asset_id'];
            $stmt = $conn->prepare("UPDATE assets SET asset_name=?, asset_code=?, category_id=?, asset_type=?, `condition`=?, status=?, location=?, description=?, purchase_date=?, purchase_value=?, image_path=? WHERE id=?");
            $stmt->bind_param("ssisssssdiss", $name, $code, $cat, $type, $cond, $status, $loc, $desc, $pdate, $pval, $image_path, $id);
            if ($stmt->execute()) $success = 'Asset updated successfully!';
            else $error = 'Failed to update asset.';
        }
    } elseif ($action === 'delete_asset') {
        $id = (int)$_POST['asset_id'];
        if ($conn->query("DELETE FROM assets WHERE id=$id")) $success = 'Asset deleted.';
        else $error = 'Cannot delete asset with active borrow records.';
    }
}
$search = sanitize($conn, $_GET['search'] ?? '');
$typeFilter = $_GET['type'] ?? '';
$condFilter = $_GET['cond'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$where = "WHERE 1=1";
if ($search) $where .= " AND (a.asset_name LIKE '%$search%' OR a.asset_code LIKE '%$search%')";
if ($typeFilter) $where .= " AND a.asset_type='$typeFilter'";
if ($condFilter) $where .= " AND a.`condition`='$condFilter'";
if ($statusFilter) $where .= " AND a.status='$statusFilter'";

$countWhere = ($search || $typeFilter || $condFilter || $statusFilter) ? "WHERE " . ltrim($where, "WHERE ") : "";
$totalQuery = $conn->query("SELECT COUNT(*) as total FROM assets a $countWhere");
$totalAssets = $totalQuery->fetch_assoc()['total'];
$page = (int)($_GET['page'] ?? 1);
$perPage = 25;
$offset = ($page - 1) * $perPage;
$assets = $conn->query("
    SELECT a.*, c.name as category_name 
    FROM assets a 
LEFT JOIN asset_categories c ON a.category_id = c.id
    $where
    ORDER BY a.created_at DESC
    LIMIT $perPage OFFSET $offset
");

$categories = $conn->query("SELECT * FROM asset_categories ORDER BY name");

$pageTitle = 'Manage Assets';
$pageBreadcrumb = 'Assets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assets — UMU Assets</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/header.php'; ?>
        <div class="page-content">

            <div class="page-header">
                <div class="page-header-left">
                    <h2>University Assets</h2>
                    <p>Manage all assets across Masaka Campus</p>
                </div>
                <?php if (isAdmin()): ?>
                <button class="btn btn-primary" onclick="openModal('addAssetModal')">+ Add Asset</button>
                <?php endif; ?>
            </div>

            <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>

            <!-- Filters -->
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="padding: 16px 20px;">
                    <form method="GET" class="filter-bar">
                        <div class="search-box">
                            <span class="search-icon">🔍</span>
                            <input type="text" name="search" class="form-control" placeholder="Search assets by name or code..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <select name="type" class="form-control" style="width:auto;">
                            <option value="">All Types</option>
                            <option value="borrowable" <?= $typeFilter=='borrowable'?'selected':'' ?>>Borrowable</option>
                            <option value="non_borrowable" <?= $typeFilter=='non_borrowable'?'selected':'' ?>>Non-Borrowable</option>
                        </select>
                        <select name="cond" class="form-control" style="width:auto;">
                            <option value="">All Conditions</option>
                            <option value="good" <?= $condFilter=='good'?'selected':'' ?>>Good</option>
                            <option value="damaged" <?= $condFilter=='damaged'?'selected':'' ?>>Damaged</option>
                            <option value="under_maintenance" <?= $condFilter=='under_maintenance'?'selected':'' ?>>Under Maintenance</option>
                        </select>
                        <select name="status" class="form-control" style="width:auto;">
                            <option value="">All Status</option>
                            <option value="available" <?= $statusFilter=='available'?'selected':'' ?>>Available</option>
                            <option value="borrowed" <?= $statusFilter=='borrowed'?'selected':'' ?>Borrowed</option>
                            <option value="in_use" <?= $statusFilter=='in_use'?'selected':'' ?>In Use</option>
                            <option value="under_repair" <?= $statusFilter=='under_repair'?'selected':'' ?>Under Repair</option>
                        </select>
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <a href="assets.php" class="btn btn-outline">✕ Clear</a>
                    </form>
                </div>
            </div>

            <!-- Assets Table -->
            <div class="card">
                <div class="card-header">
<span class="card-title">🗄️ Assets List (<?= $assets->num_rows ?> of <?= $totalAssets ?> found)</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr>
                            <th>#</th>
                            <th>Asset Name</th>
                            <th>Asset Code</th>
                            <th>Category</th>
                            <th>Type</th>
                            <th>Condition</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Value (UGX)</th>
                            <?php if (isAdmin()): ?><th>Actions</th><?php endif; ?>
                        </tr></thead>
                        <tbody>
                        <?php if ($assets->num_rows === 0): ?>
                        <tr><td colspan="10"><div class="empty-state"><div class="empty-icon">🗄️</div><h3>No Assets Found</h3><p>No assets match your search criteria.</p></div></td></tr>
                        <?php else: $i=1; while($row = $assets->fetch_assoc()): ?>
                        <tr>
                            <td style="color:var(--gray-500);"><?= $i++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($row['asset_name']) ?></strong>
                                <?php if (!empty($row['description'])): ?>
                                <br><small style="color:var(--gray-500);"><?= htmlspecialchars(substr($row['description'], 0, 45)) ?>...</small>
                                <?php endif; ?>
                                <?php if (!empty($row['image_path'])): ?>
                                <br><img src="<?= htmlspecialchars($row['image_path']) ?>" style="max-width:60px;max-height:40px;border-radius:4px;margin-top:4px;" alt="Asset Image" title="Image">
                                <?php endif; ?>
                            </td>
                            <td><code style="background:var(--gray-100);padding:3px 7px;border-radius:5px;font-family:var(--font-mono);font-size:12px;"><?= $row['asset_code'] ?></code></td>
                            <td><?= htmlspecialchars($row['category_name'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($row['asset_type'] === 'borrowable'): ?>
                                <span class="badge badge-success">📤 Borrowable</span>
                                <?php else: ?>
                                <span class="badge badge-info">🔒 Fixed Asset</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $c = $row['condition'];
                                $cb = $c === 'good' ? 'badge-success' : ($c === 'damaged' ? 'badge-danger' : 'badge-warning');
                                $cl = ['good'=>'✅ Good','damaged'=>'❌ Damaged','under_maintenance'=>'🔧 Maintenance'];
                                ?>
                                <span class="badge <?= $cb ?>"><?= $cl[$c] ?? $c ?></span>
                            </td>
                            <td>
                                <?php
                                $s = $row['status'];
                                $sb = ['available'=>'badge-success','borrowed'=>'badge-warning','in_use'=>'badge-primary','under_repair'=>'badge-danger'];
                                $sl = ['available'=>'✅ Available','borrowed'=>'📤 Borrowed','in_use'=>'⚙️ In Use','under_repair'=>'🔧 Under Repair'];
                                ?>
                                <span class="badge <?= $sb[$s] ?? 'badge-secondary' ?>"><?= $sl[$s] ?? ucfirst($s) ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['location'] ?? '—') ?></td>
                            <td><?= $row['purchase_value'] ? number_format($row['purchase_value'], 0) : '—' ?></td>
                            <?php if (isAdmin()): ?>
                            <td>
                                <div class="table-actions">
                                    <button class="btn btn-sm btn-outline" title="Edit"
                                        onclick="editAsset(<?= htmlspecialchars(json_encode($row)) ?>)">✏️</button>
                                    <button class="btn btn-sm btn-danger" title="Delete"
                                        onclick="deleteAsset(<?= $row['id'] ?>, '<?= htmlspecialchars($row['asset_name']) ?>')">🗑️</button>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<?php if (isAdmin()): ?>
<!-- Add Asset Modal -->
<div class="modal-overlay" id="addAssetModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span class="modal-title">➕ Add New Asset</span>
            <button class="modal-close" onclick="closeModal('addAssetModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_asset">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Asset Name *</label>
                        <input type="text" name="asset_name" class="form-control" required placeholder="e.g. Dell Laptop XPS 15">
                    </div>
                    <div class="form-group">
                        <label>Asset Code/ID *</label>
                        <input type="text" name="asset_code" class="form-control" required placeholder="e.g. UMU-LAP-003">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">Select Category</option>
                            <?php $categories->data_seek(0); while($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Asset Type *</label>
                        <select name="asset_type" class="form-control" required>
                            <option value="borrowable">Borrowable (can be issued)</option>
                            <option value="non_borrowable">Non-Borrowable (fixed/monitored)</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Condition</label>
                        <select name="condition" class="form-control">
                            <option value="good">Good</option>
                            <option value="damaged">Damaged</option>
                            <option value="under_maintenance">Under Maintenance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="available">Available</option>
                            <option value="in_use">In Use</option>
                            <option value="under_repair">Under Repair</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" class="form-control" placeholder="e.g. ICT Lab, Block A">
                    </div>
                    <div class="form-group">
                        <label>Purchase Value (UGX)</label>
                        <input type="number" name="purchase_value" class="form-control" placeholder="0">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Purchase Date</label>
                        <input type="date" name="purchase_date" class="form-control">
                    </div>
                    <div class="form-group"></div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" placeholder="Brief description of the asset..."></textarea>
                </div>
                <div class="form-group">
                    <label>Asset Image (optional, JPG/PNG up to 5MB)</label>
                    <input type="file" name="asset_image" class="form-control" accept="image/*">
                    <small class="text-muted">Image will be uploaded to uploads/</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addAssetModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Asset Modal -->
<div class="modal-overlay" id="editAssetModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span class="modal-title">✏️ Edit Asset</span>
            <button class="modal-close" onclick="closeModal('editAssetModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_asset">
            <input type="hidden" name="asset_id" id="edit_asset_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Asset Name *</label>
                        <input type="text" name="asset_name" id="edit_asset_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Asset Code *</label>
                        <input type="text" name="asset_code" id="edit_asset_code" class="form-control" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category_id" id="edit_category_id" class="form-control">
                            <?php $categories->data_seek(0); while($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Asset Type</label>
                        <select name="asset_type" id="edit_asset_type" class="form-control">
                            <option value="borrowable">Borrowable</option>
                            <option value="non_borrowable">Non-Borrowable</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Condition</label>
                        <select name="condition" id="edit_condition" class="form-control">
                            <option value="good">Good</option>
                            <option value="damaged">Damaged</option>
                            <option value="under_maintenance">Under Maintenance</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="available">Available</option>
                            <option value="borrowed">Borrowed</option>
                            <option value="in_use">In Use</option>
                            <option value="under_repair">Under Repair</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" id="edit_location" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Purchase Value (UGX)</label>
                        <input type="number" name="purchase_value" id="edit_purchase_value" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Purchase Date</label>
                    <input type="date" name="purchase_date" id="edit_purchase_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label>Asset Image</label>
                    <input type="file" name="asset_image" id="edit_asset_image" class="form-control" accept="image/*">
                    <small class="text-muted">Current: <span id="current_image">None</span></small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editAssetModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Update Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete_asset">
    <input type="hidden" name="asset_id" id="delete_asset_id">
</form>
<?php endif; ?>

<script src="../js/app.js"></script>
<script>
function editAsset(asset) {
    document.getElementById('edit_asset_id').value = asset.id;
    document.getElementById('edit_asset_name').value = asset.asset_name;
    document.getElementById('edit_asset_code').value = asset.asset_code;
    document.getElementById('edit_category_id').value = asset.category_id;
    document.getElementById('edit_asset_type').value = asset.asset_type;
    document.getElementById('edit_condition').value = asset.condition;
    document.getElementById('edit_status').value = asset.status;
    document.getElementById('edit_location').value = asset.location || '';
    document.getElementById('edit_purchase_value').value = asset.purchase_value || '';
    document.getElementById('edit_purchase_date').value = asset.purchase_date || '';
    document.getElementById('edit_description').value = asset.description || '';
    document.getElementById('current_image').textContent = asset.image_path || 'None';
    openModal('editAssetModal');
}
function deleteAsset(id, name) {
    if (confirm('Delete asset "' + name + '"? This cannot be undone.')) {
        document.getElementById('delete_asset_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
</script>
</body>
</html>

