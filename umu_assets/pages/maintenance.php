<?php
require_once '../includes/config.php';
requireLogin();

$conn = getDBConnection();
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_log' && isAdmin()) {
        $asset_id = (int)$_POST['asset_id'];
        $type = sanitize($conn, $_POST['maintenance_type']);
        $cond = sanitize($conn, $_POST['condition']);
        $desc = sanitize($conn, $_POST['description']);
        $cost = (float)$_POST['cost'];
        $tech = sanitize($conn, $_POST['technician_name']);
        $mdate = sanitize($conn, $_POST['maintenance_date']);
        $ndate = sanitize($conn, $_POST['next_maintenance_date']);
        $status = sanitize($conn, $_POST['status']);

        $stmt = $conn->prepare("INSERT INTO maintenance_logs (asset_id, logged_by, maintenance_type, `condition`, description, cost, technician_name, maintenance_date, next_maintenance_date, status) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iisssdssss", $asset_id, $_SESSION['user_id'], $type, $cond, $desc, $cost, $tech, $mdate, $ndate, $status);

        if ($stmt->execute()) {
            // Update asset condition
            $assetCond = $cond === 'needs_repair' ? 'damaged' : $cond;
            $assetStatus = $cond === 'under_maintenance' ? 'under_repair' : 'in_use';
            $conn->query("UPDATE assets SET `condition`='$assetCond', status='$assetStatus' WHERE id=$asset_id");
            $success = 'Maintenance log added successfully!';
        } else {
            $error = 'Failed to add maintenance log.';
        }
    } elseif ($action === 'update_status' && isAdmin()) {
        $log_id = (int)$_POST['log_id'];
        $new_status = sanitize($conn, $_POST['new_status']);
        $asset_id = (int)$_POST['asset_id'];
        $conn->query("UPDATE maintenance_logs SET status='$new_status' WHERE id=$log_id");
        if ($new_status === 'completed') {
            $conn->query("UPDATE assets SET status='in_use', `condition`='good' WHERE id=$asset_id");
        }
        $success = 'Maintenance status updated!';
    }
}

// Filter
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$where = "WHERE 1=1";
if ($statusFilter) $where .= " AND ml.status='$statusFilter'";
if ($typeFilter) $where .= " AND ml.maintenance_type='$typeFilter'";

$logs = $conn->query("
    SELECT ml.*, a.asset_name, a.asset_code, a.asset_type, u.full_name as logged_by_name
    FROM maintenance_logs ml
JOIN assets a ON ml.asset_id = a.id
JOIN users u ON ml.logged_by = u.id
    $where
    ORDER BY ml.created_at DESC
");

// Non-borrowable assets for logging
$nonBorrowable = $conn->query("SELECT id, asset_name, asset_code FROM assets WHERE asset_type='non_borrowable' ORDER BY asset_name");

$pageTitle = 'Maintenance Logs';
$pageBreadcrumb = 'Maintenance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance — UMU Assets</title>
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
                    <h2>Maintenance & Monitoring</h2>
                    <p>Track condition and maintenance history of all assets</p>
                </div>
                <?php if (isAdmin()): ?>
                <button class="btn btn-primary" onclick="openModal('addLogModal')">🔧 Log Maintenance</button>
                <?php endif; ?>
            </div>

            <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>

            <!-- Filters -->
            <div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;">
                <a href="maintenance.php" class="btn btn-sm <?= !$statusFilter?'btn-primary':'btn-outline' ?>">All</a>
                <a href="?status=pending" class="btn btn-sm <?= $statusFilter=='pending'?'btn-warning':'btn-outline' ?>">⏳ Pending</a>
                <a href="?status=in_progress" class="btn btn-sm <?= $statusFilter=='in_progress'?'btn-accent':'btn-outline' ?>">🔧 In Progress</a>
                <a href="?status=completed" class="btn btn-sm <?= $statusFilter=='completed'?'btn-success':'btn-outline' ?>">✅ Completed</a>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title">🔧 Maintenance History (<?= $logs->num_rows ?> records)</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr>
                            <th>#</th>
                            <th>Asset</th>
                            <th>Type</th>
                            <th>Condition Reported</th>
                            <th>Description</th>
                            <th>Technician</th>
                            <th>Date</th>
                            <th>Next Check</th>
                            <th>Cost (UGX)</th>
                            <th>Status</th>
                            <?php if (isAdmin()): ?><th>Actions</th><?php endif; ?>
                        </tr></thead>
                        <tbody>
                        <?php if ($logs->num_rows === 0): ?>
                        <tr><td colspan="11"><div class="empty-state"><div class="empty-icon">🔧</div><h3>No Logs Yet</h3><p>No maintenance records found.</p></div></td></tr>
                        <?php else: $i=1; while($row = $logs->fetch_assoc()): ?>
                        <tr>
                            <td style="color:var(--gray-500);"><?= $i++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($row['asset_name']) ?></strong><br>
                                <code style="font-size:11px;"><?= $row['asset_code'] ?></code>
                            </td>
                            <td>
                                <?php
                                $types = ['routine_check'=>'🔍 Routine','repair'=>'🔨 Repair','upgrade'=>'⬆️ Upgrade','inspection'=>'📋 Inspection'];
                                echo $types[$row['maintenance_type']] ?? $row['maintenance_type'];
                                ?>
                            </td>
                            <td>
                                <?php
                                $c = $row['condition'];
                                $cb = $c === 'good' ? 'badge-success' : ($c === 'needs_repair' ? 'badge-danger' : 'badge-warning');
                                $cl = ['good'=>'✅ Good','needs_repair'=>'❌ Needs Repair','under_maintenance'=>'🔧 In Maintenance'];
                                ?>
                                <span class="badge <?= $cb ?>"><?= $cl[$c] ?? $c ?></span>
                            </td>
                            <td style="max-width:200px;"><small><?= htmlspecialchars(substr($row['description'], 0, 80)) ?>...</small></td>
                            <td><?= htmlspecialchars($row['technician_name'] ?: '—') ?></td>
                            <td><?= date('d M Y', strtotime($row['maintenance_date'])) ?></td>
                            <td>
                                <?php if ($row['next_maintenance_date']): ?>
                                <?= date('d M Y', strtotime($row['next_maintenance_date'])) ?>
                                <?php if (isOverdue($row['next_maintenance_date']) && $row['status'] === 'completed'): ?>
                                <br><span style="color:var(--danger);font-size:11px;font-weight:600;">⚠️ Overdue</span>
                                <?php endif; ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td><?= $row['cost'] ? number_format($row['cost'], 0) : '—' ?></td>
                            <td>
                                <?php
                                $s = $row['status'];
                                $sb = ['pending'=>'badge-secondary','in_progress'=>'badge-warning','completed'=>'badge-success'];
                                $sl = ['pending'=>'⏳ Pending','in_progress'=>'🔧 In Progress','completed'=>'✅ Completed'];
                                ?>
                                <span class="badge <?= $sb[$s] ?>"><?= $sl[$s] ?></span>
                            </td>
                            <?php if (isAdmin()): ?>
                            <td>
                                <?php if ($row['status'] !== 'completed'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="log_id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="asset_id" value="<?= $row['asset_id'] ?>">
                                    <select name="new_status" class="form-control" style="width:auto;display:inline;font-size:12px;padding:5px 8px;" onchange="this.form.submit()">
                                        <option value="">Update →</option>
                                        <?php if ($row['status'] === 'pending'): ?>
                                        <option value="in_progress">🔧 Start</option>
                                        <?php endif; ?>
                                        <option value="completed">✅ Complete</option>
                                    </select>
                                </form>
                                <?php else: ?>
                                <span style="color:var(--gray-500);font-size:12px;">Done</span>
                                <?php endif; ?>
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
<div class="modal-overlay" id="addLogModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <span class="modal-title">🔧 Log Maintenance Activity</span>
            <button class="modal-close" onclick="closeModal('addLogModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_log">
            <div class="modal-body">
                <div class="alert alert-info">ℹ️ Maintenance logs are primarily for <strong>Non-Borrowable (Fixed) Assets</strong>. You can also log maintenance for borrowable assets when needed.</div>
                <div class="form-group">
                    <label>Asset *</label>
                    <select name="asset_id" class="form-control" required>
                        <option value="">— Select Asset —</option>
                        <?php while($a = $nonBorrowable->fetch_assoc()): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['asset_name']) ?> (<?= $a['asset_code'] ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Maintenance Type *</label>
                        <select name="maintenance_type" class="form-control" required>
                            <option value="routine_check">🔍 Routine Check</option>
                            <option value="repair">🔨 Repair</option>
                            <option value="upgrade">⬆️ Upgrade</option>
                            <option value="inspection">📋 Inspection</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Condition Found *</label>
                        <select name="condition" class="form-control" required>
                            <option value="good">✅ Good</option>
                            <option value="needs_repair">❌ Needs Repair</option>
                            <option value="under_maintenance">🔧 Under Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description / Findings *</label>
                    <textarea name="description" class="form-control" required placeholder="Describe maintenance work done or findings..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Technician Name</label>
                        <input type="text" name="technician_name" class="form-control" placeholder="Who performed the maintenance?">
                    </div>
                    <div class="form-group">
                        <label>Cost (UGX)</label>
                        <input type="number" name="cost" class="form-control" value="0" min="0">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Maintenance Date *</label>
                        <input type="date" name="maintenance_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Next Maintenance Date</label>
                        <input type="date" name="next_maintenance_date" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="pending">⏳ Pending</option>
                        <option value="in_progress">🔧 In Progress</option>
                        <option value="completed">✅ Completed</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addLogModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save Maintenance Log</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="../js/app.js"></script>
</body>
</html>



