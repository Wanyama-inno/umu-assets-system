<?php
require_once '../includes/config.php';
requireLogin();

$conn = getDBConnection();
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_borrow' || $action === 'approve_borrow') {
        $asset_id = (int)$_POST['asset_id'];
        $borrower_id = (int)$_SESSION['id'];
        $purpose = sanitize($conn, $_POST['purpose']);
        $expected_return = sanitize($conn, $_POST['expected_return_date']);

        $assetCheck = $conn->prepare("SELECT * FROM assets WHERE id=? AND asset_type='borrowable' AND status='available'");
        $assetCheck->bind_param("i", $asset_id);
        $assetCheck->execute();
        $asset = $assetCheck->get_result()->fetch_assoc();

        if (!$asset) {
            $error = 'Asset not available for borrowing.';
        } elseif (empty($expected_return)) {
            $error = 'Please specify expected return date.';
        } elseif (strtotime($expected_return) < strtotime(date('Y-m-d'))) {
            $error = 'Return date must be in the future.';
        } else {
            $userInfo = $conn->query("SELECT full_name, role FROM users WHERE id=$borrower_id")->fetch_assoc();
            $bname = $userInfo['full_name'];
            $brole = $userInfo['role'];
            $cond = $asset['condition'];

            if ($action === 'request_borrow') {
                $stmt = $conn->prepare("INSERT INTO borrow_records (asset_id, borrower_id, borrowed_by_name, borrower_role, purpose, expected_return_date, status, condition_on_borrow) VALUES (?,?,?,?,?,?, 'pending', ?)");
                $stmt->bind_param("iissssi", $asset_id, $borrower_id, $bname, $brole, $purpose, $expected_return, $cond);
                $msg = "Request submitted! Waiting for admin approval.";
            } else {
                $borrow_id = (int)$_POST['borrow_id'];
                $stmt = $conn->prepare("UPDATE borrow_records SET status='active', approved_by=? WHERE id=? AND status='pending'");
                $stmt->bind_param("ii", $_SESSION['id'], $borrow_id);
                $conn->query("UPDATE assets SET status='borrowed' WHERE id=$asset_id");
                $msg = "Request approved and asset issued.";
            }

            if ($stmt->execute()) {
                $success = $msg;
            } else {
                $error = 'Failed to process request.';
            }
        }
    }
}

// Get available borrowable assets
$availableSearch = $_GET['avail_search'] ?? '';
$availWhere = "WHERE a.asset_type='borrowable' AND a.status='available' AND a.`condition`='good'";
if ($availableSearch) $availWhere .= " AND (a.asset_name LIKE '%$availableSearch%' OR a.asset_code LIKE '%$availableSearch%')";
$availableAssetsList = $conn->query("SELECT a.*, c.name as category_name FROM assets a LEFT JOIN asset_categories c ON a.category_id = c.id $availWhere ORDER BY a.asset_name");

$availableAssets = $conn->query("SELECT a.id, a.asset_name, a.asset_code, COALESCE(c.name, 'Uncategorized') as category_name FROM assets a LEFT JOIN asset_categories c ON a.category_id = c.id WHERE a.asset_type='borrowable' AND a.status='available' ORDER BY a.asset_name LIMIT 50");

// Get all users
$users = $conn->query("SELECT id, full_name, role, student_staff_id, department FROM users WHERE status='active' ORDER BY role, full_name");

// Personal borrows
$user_id = $_SESSION['id'];
$myBorrows = $conn->query("
    SELECT br.*, a.asset_name, a.asset_code 
    FROM borrow_records br 
    JOIN assets a ON br.asset_id = a.id 
    WHERE br.borrower_id = $user_id 
    ORDER BY br.created_at DESC LIMIT 20
");

// All borrows
$borrowFilter = $_GET['filter'] ?? 'all';
$whereFilter = $borrowFilter === 'all' ? '' : "WHERE br.status='$borrowFilter'";
$borrows = $conn->query("
    SELECT br.*, a.asset_name, a.asset_code, u.full_name as borrower_name, u.student_staff_id, u.role as borrower_role
    FROM borrow_records br
JOIN assets a ON br.asset_id = a.id
JOIN users u ON br.borrower_id = u.id
    $whereFilter
    ORDER BY br.created_at DESC
");

$pageTitle = 'Borrow Assets';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Assets — UMU Assets</title>
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
                    <h2>Borrow Assets</h2>
                    <p>Request, approve, and manage asset borrows</p>
                </div>
                <?php if ($_SESSION['role'] !== 'admin'): ?>
                <button class="btn btn-primary" onclick="openModal('studentRequestModal')">📤 Request Asset</button>
                <?php endif; ?>
            </div>

            <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>

            <!-- Personal Borrows -->
            <?php if ($_SESSION['role'] !== 'admin'): ?>
            <div class="card">
                <div class="card-header">
                    <span class="card-title">📋 My Borrows (<?= $myBorrows->num_rows ?>)</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Asset</th><th>Date</th><th>Due</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php if ($myBorrows->num_rows === 0): ?>
                            <tr><td colspan="4" style="text-align:center;padding:20px;"><small>No borrows yet</small></td></tr>
                            <?php else: $myBorrows->data_seek(0); while($row = $myBorrows->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['asset_name']) ?></td>
                                <td><?= date('d M', strtotime($row['borrow_date'])) ?></td>
                                <td><?= date('d M', strtotime($row['expected_return_date'])) ?></td>
                                <td><span class="badge badge-<?= $row['status']=='returned'?'success':'warning' ?>"><?= ucfirst($row['status']) ?></span></td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- All Borrows -->
            <div class="card">
                <div class="card-header">
                    <span class="card-title">📋 All Records (<?= $borrows->num_rows ?>)</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr>
                            <th>#</th>
                            <th>Asset</th>
                            <th>Borrower</th>
                            <th>Purpose</th>
                            <th>Date</th>
                            <th>Due</th>
                            <th>Status</th>
                            <?php if (isAdmin()): ?><th>Actions</th><?php endif; ?>
                        </tr></thead>
                        <tbody>
                            <?php if ($borrows->num_rows === 0): ?>
                            <tr><td colspan="8"><div class="empty-state">No records</div></td></tr>
                            <?php else: $i=1; $borrows->data_seek(0); while($row = $borrows->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($row['asset_name']) ?> (<?= $row['asset_code'] ?>)</td>
                                <td><?= htmlspecialchars($row['borrower_name']) ?></td>
                                <td><?= htmlspecialchars(substr($row['purpose'], 0, 50)) ?></td>
                                <td><?= date('d M Y', strtotime($row['borrow_date'])) ?></td>
                                <td><?= date('d M Y', strtotime($row['expected_return_date'])) ?></td>
                                <td><span class="badge badge-<?= $row['status']=='returned'?'success':'warning' ?>"><?= ucfirst($row['status']) ?></span></td>
                                <?php if (isAdmin() && $row['status'] === 'pending'): ?>
                                <td><button class="btn btn-sm btn-success" onclick="approveBorrow(<?= $row['id'] ?>)">Approve</button></td>
                                <?php elseif (isAdmin() && $row['status'] === 'active'): ?>
                                <td><button class="btn btn-sm btn-primary" onclick="processReturn(<?= $row['id'] ?> , '<?= addslashes($row['asset_name']) ?>', '<?= addslashes($row['borrower_name']) ?>')">Return</button></td>
                                <?php else: ?>
                                <td>—</td>
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

<!-- Request Modal -->
<div class="modal-overlay" id="studentRequestModal">
    <div class="modal">
        <span class="modal-title">📤 Request Asset</span>
        <button class="modal-close">✕</button>
        <form method="POST">
            <input type="hidden" name="action" value="request_borrow">
            <div class="form-group">
                <label>Asset</label>
                <select name="asset_id" required>
                    <?php $availableAssets->data_seek(0); while($a = $availableAssets->fetch_assoc()): ?>
                    <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['asset_name']) ?> (<?= $a['asset_code'] ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Purpose</label>
                <textarea name="purpose" required></textarea>
            </div>
            <div class="form-group">
                <label>Expected Return</label>
                <input type="date" name="expected_return_date" min="<?= date('Y-m-d') ?>" required>
            </div>
            <button type="submit">Submit Request</button>
        </form>
    </div>
</div>

<script src="../js/app.js"></script>
</body>
</html>

