<?php
require_once '../includes/config.php';
requireLogin();

$conn = getDBConnection();
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'return_asset') {
        $borrow_id = (int)$_POST['borrow_id'];
        $cond_return = sanitize($conn, $_POST['condition_on_return']);
        $notes = sanitize($conn, $_POST['notes']);

        $borrow = $conn->query("SELECT * FROM borrow_records WHERE id=$borrow_id AND status='active'")->fetch_assoc();
        if (!$borrow) {
            $error = 'Borrow record not found or already returned.';
        } else {
            $conn->query("UPDATE borrow_records SET status='returned', actual_return_date=NOW(), condition_on_return='$cond_return', notes='$notes' WHERE id=$borrow_id");
            $newStatus = ($cond_return === 'damaged') ? 'under_repair' : 'available';
            $conn->query("UPDATE assets SET status='$newStatus', `condition`='$cond_return' WHERE id={$borrow['asset_id']}");
            $success = 'Asset returned and borrower cleared successfully!';
        }
    }
}

// Active borrows
$activeBorrows = $conn->query("
    SELECT br.*, a.asset_name, a.asset_code, u.full_name as borrower_name, u.student_staff_id, u.department
    FROM borrow_records br
JOIN assets a ON br.asset_id = a.id
JOIN users u ON br.borrower_id = u.id
    WHERE br.status='active'
    ORDER BY br.expected_return_date ASC
");

$pageTitle = 'Returns & Clearance';
$pageBreadcrumb = 'Returns';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns — UMU Assets</title>
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
                    <h2>Returns & Clearance</h2>
                    <p>Process returns and clear borrowers</p>
                </div>
            </div>

            <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>

            <?php
            $overdueCount = 0;
            $activeBorrows->data_seek(0);
            while($r = $activeBorrows->fetch_assoc()) {
                if (isOverdue($r['expected_return_date'])) $overdueCount++;
            }
            $activeBorrows->data_seek(0);
            if ($overdueCount > 0):
            ?>
            <div class="alert alert-danger">
                ⚠️ <strong><?= $overdueCount ?> asset(s) are overdue!</strong> Please contact the borrowers and process returns immediately.
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <span class="card-title">📥 Assets Currently Borrowed (<?= $activeBorrows->num_rows ?>)</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr>
                            <th>#</th>
                            <th>Asset</th>
                            <th>Borrower</th>
                            <th>Department</th>
                            <th>Borrowed On</th>
                            <th>Expected Return</th>
                            <th>Days Status</th>
<?php if (isAdmin()): ?><th>Action</th><?php endif; ?>
                        </tr></thead>
                        <tbody>
                        <?php if ($activeBorrows->num_rows === 0): ?>
                        <tr><td colspan="8"><div class="empty-state">
                            <div class="empty-icon">🎉</div>
                            <h3>All Clear!</h3>
                            <p>No assets are currently borrowed.</p>
                        </div></td></tr>
                        <?php else: $i=1; while($row = $activeBorrows->fetch_assoc()):
                            $isOver = isOverdue($row['expected_return_date']);
                            $daysLeft = (strtotime($row['expected_return_date']) - time()) / 86400;
                            $daysDiff = round(abs($daysLeft));
                        ?>
                        <tr style="<?= $isOver ? 'background:rgba(176,42,55,0.04);' : '' ?>">
                            <td style="color:var(--gray-500);"><?= $i++ ?></td>
                            <td>
                                <strong><?= htmlspecialchars($row['asset_name']) ?></strong><br>
                                <code style="font-size:11px;"><?= $row['asset_code'] ?></code>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($row['borrower_name']) ?></strong><br>
                                <small><?= htmlspecialchars($row['student_staff_id']) ?> · <span class="badge badge-secondary"><?= $row['borrower_role'] ?></span></small>
                            </td>
                            <td><small><?= htmlspecialchars($row['department'] ?? '—') ?></small></td>
                            <td><?= date('d M Y', strtotime($row['borrow_date'])) ?></td>
                            <td><?= date('d M Y', strtotime($row['expected_return_date'])) ?></td>
                            <td>
                                <?php if ($isOver): ?>
                                <span class="badge badge-danger">⚠️ <?= $daysDiff ?> days overdue</span>
                                <?php elseif ($daysLeft <= 1): ?>
                                <span class="badge badge-warning">⏰ Due today/tomorrow</span>
                                <?php else: ?>
                                <span class="badge badge-success">✅ <?= round($daysLeft) ?> days left</span>
                                <?php endif; ?>
                            </td>
                            <?php if (isAdmin()): ?>
                            <td>
                                <button class="btn btn-sm btn-success"
                                    onclick="processReturn(<?= $row['id'] ?>, '<?= htmlspecialchars($row['asset_name']) ?>', '<?= htmlspecialchars($row['borrower_name']) ?>')">
                                    📥 Process Return
                                </button>
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
<div class="modal-overlay" id="returnModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">📥 Process Asset Return</span>
            <button class="modal-close" onclick="closeModal('returnModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="return_asset">
            <input type="hidden" name="borrow_id" id="return_borrow_id">
            <div class="modal-body">
                <div class="alert alert-info">
                    📦 Asset: <strong id="return_asset_name"></strong><br>
                    👤 Borrower: <strong id="return_borrower_name"></strong>
                </div>
                <div class="form-group">
                    <label>Condition on Return *</label>
                    <select name="condition_on_return" class="form-control" required>
                        <option value="good">✅ Good — No damage</option>
                        <option value="damaged">❌ Damaged — Needs repair</option>
                        <option value="under_maintenance">🔧 Needs Maintenance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Return Notes (optional)</label>
                    <textarea name="notes" class="form-control" placeholder="Any observations on return..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('returnModal')">Cancel</button>
                <button type="submit" class="btn btn-success">✅ Confirm Return & Clear</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="../js/app.js"></script>
<script>
function processReturn(id, asset, borrower) {
    document.getElementById('return_borrow_id').value = id;
    document.getElementById('return_asset_name').textContent = asset;
    document.getElementById('return_borrower_name').textContent = borrower;
    openModal('returnModal');
}
</script>
</body>
</html>



