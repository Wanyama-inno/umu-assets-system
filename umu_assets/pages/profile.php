<?php
require_once '../includes/config.php';
requireLogin();

$conn = getDBConnection();
$error = ''; $success = '';
$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $name = sanitize($conn, $_POST['full_name']);
        $phone = sanitize($conn, $_POST['phone']);
        $dept = sanitize($conn, $_POST['department']);
        $sid = sanitize($conn, $_POST['student_staff_id']);
        $conn->query("UPDATE users SET full_name='$name', phone='$phone', department='$dept', student_staff_id='$sid' WHERE user_id=$uid");
        $_SESSION['full_name'] = $name;
        $success = 'Profile updated!';
    } elseif ($action === 'change_password') {
        $old = $_POST['old_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        if ($new !== $confirm) $error = 'New passwords do not match.';
        elseif (strlen($new) < 6) $error = 'Password must be at least 6 characters.';
        else {
            $hashed = $new;
            $conn->query("UPDATE users SET password='$hashed' WHERE user_id=$uid");
            $success = 'Password changed successfully!';
        }
    }
}

// Fetch user with null-safe handling
$userQuery = $conn->query("SELECT * FROM users WHERE id=$uid");
$user = $userQuery && $userQuery->num_rows > 0 ? $userQuery->fetch_assoc() : null;

// Fetch role
$roleQuery = $conn->query("SELECT role FROM users WHERE id=$uid");
$roleRow = $roleQuery && $roleQuery->num_rows > 0 ? $roleQuery->fetch_assoc() : null;
$role = $roleRow ? $roleRow['role'] : 'staff';

// Fetch active borrow count
$borrowQuery = $conn->query("SELECT COUNT(*) as c FROM borrow_records WHERE borrower_id=$uid AND status='active'");
$borrowRow = $borrowQuery && $borrowQuery->num_rows > 0 ? $borrowQuery->fetch_assoc() : null;
$activeBorrowCount = ($role !== 'admin' && $borrowRow) ? (int)$borrowRow['c'] : 0;

// Fetch borrow history
$myBorrowsQuery = $conn->query("SELECT br.*, a.asset_name, a.asset_code FROM borrow_records br JOIN assets a ON br.asset_id=a.id WHERE br.borrower_id=$uid ORDER BY br.borrow_date DESC LIMIT 10");

$pageTitle = 'My Profile';
$pageBreadcrumb = 'Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile — UMU Assets</title>
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
                    <h2>My Profile</h2>
                    <p>Manage your account and view borrow history</p>
                </div>
            </div>

            <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

            <?php if (!$user): ?>
            <div class="alert alert-danger">User not found. <a href="logout.php">Login again</a></div>
            <?php else: ?>
            <div class="grid-2">
                <div style="display:flex;flex-direction:column;gap:20px;">
                    <div class="card">
                        <div class="card-header"><span class="card-title">👤 Profile Information</span></div>
                        <div class="card-body">
                            <div style="text-align:center;margin-bottom:24px;">
                                <div class="user-avatar" style="width:72px;height:72px;font-size:28px;font-weight:700;margin:0 auto 12px;background:linear-gradient(135deg,var(--primary),var(--accent));">
                                    <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                                </div>
                                <h3 style="font-family:var(--font-display);font-size:18px;margin-bottom:4px;"><?= htmlspecialchars($user['full_name']) ?></h3>
                                <span class="badge badge-<?= $role == 'admin' ? 'gold' : ($role == 'staff' ? 'primary' : 'success') ?>"><?= ucfirst($role) ?></span>
                                <?php if ($activeBorrowCount > 0): ?>
                                <br><br><span class="badge badge-warning">📤 <?= $activeBorrowCount ?> active borrow(s)</span>
                                <?php endif; ?>
                            </div>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="form-group">
                                    <label>Full Name *</label>
                                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>ID / Reg. No.</label>
                                        <input type="text" name="student_staff_id" class="form-control" value="<?= htmlspecialchars($user['student_staff_id'] ?? '') ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Department</label>
                                        <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($user['department'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-primary btn-block">💾 Update Profile</button>
                            </form>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header"><span class="card-title">🔑 Change Password</span></div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                <div class="form-group">
                                    <label>New Password (min 6 chars)</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-warning btn-block">🔑 Update Password</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span class="card-title">📋 Recent Borrows</span>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Date Borrowed</th>
                                    <th>Return By</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$myBorrowsQuery || $myBorrowsQuery->num_rows === 0): ?>
                                <tr>
                                    <td colspan="4" style="text-align:center;padding:40px;color:var(--gray-500);">
                                        <div style="font-size:48px;margin-bottom:12px;">📭</div>
                                        No borrow history
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php while($r = $myBorrowsQuery->fetch_assoc()): ?>
                                    <?php $isOverdue = ($r['status'] === 'active') && isOverdue($r['expected_return_date']); ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($r['asset_name']) ?></strong><br>
                                            <small style="color:var(--gray-500);"><?= htmlspecialchars($r['asset_code']) ?></small>
                                        </td>
                                        <td><?= date('M j', strtotime($r['borrow_date'])) ?></td>
                                        <td>
                                            <?= date('M j', strtotime($r['expected_return_date'])) ?>
                                            <?php if ($isOverdue): ?>
                                                <br><span class="badge badge-danger" style="font-size:12px;">⚠️ Overdue</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($r['status'] === 'returned'): ?>
                                                <span class="badge badge-success">✅ Returned</span>
                                            <?php elseif ($isOverdue): ?>
                                                <span class="badge badge-danger">⚠️ Overdue</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">📤 Active</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="../js/app.js"></script>
</body>
</html>

