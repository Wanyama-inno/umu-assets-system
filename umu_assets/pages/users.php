<?php
require_once '../includes/config.php';
requireAdmin();

$conn = getDBConnection();
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $name = sanitize($conn, $_POST['full_name']);
        $email = sanitize($conn, $_POST['email']);
        $password = $_POST['password'];
        $role = sanitize($conn, $_POST['role']);
        $sid = sanitize($conn, $_POST['student_staff_id'] ?? '');
        $dept = sanitize($conn, $_POST['department'] ?? '');
        $phone = sanitize($conn, $_POST['phone'] ?? '');

        $check = $conn->prepare("SELECT id FROM users WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Email already exists.';
        } else {
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, student_staff_id, department, phone) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param("sssssss", $name, $email, $password, $role, $sid, $dept, $phone);
            if ($stmt->execute()) $success = 'User created successfully!';
            else $error = 'Failed to create user.';
        }
    } elseif ($action === 'toggle_status') {
        $uid = sanitize($conn, $_POST['user_id'] ?? 0);
        $session_uid = $_SESSION['id'] ?? 0;
        $conn->query("UPDATE users SET status = IF(status='active','inactive','active') WHERE id='$uid' AND id != $session_uid");
        $success = 'User status updated.';
    } elseif ($action === 'delete_user') {
        $uid = sanitize($conn, $_POST['user_id'] ?? 0);
        $session_uid = $_SESSION['id'] ?? 0;
        if ($uid === $session_uid) { $error = 'You cannot delete your own account.'; }
        else $conn->query("DELETE FROM users WHERE id='$uid'");
    } elseif ($action === 'reset_password') {
        $uid = sanitize($conn, $_POST['user_id'] ?? 0);
        $np = $_POST['new_password'] ?? '';
        if (strlen($np) < 6) { $error = 'Password must be at least 6 characters.'; }
        else {
            $conn->query("UPDATE users SET password='$np' WHERE id='$uid'");
            $success = 'Password reset successfully.';
        }
    }
}

$users = $conn->query("SELECT u.*, COUNT(DISTINCT br.id) as active_borrows FROM users u LEFT JOIN borrow_records br ON u.id = br.borrower_id AND br.status='active' GROUP BY u.id ORDER BY u.role, u.full_name");

$_SESSION['user_id'] = $_SESSION['id'] ?? 1;

$pageTitle = 'Manage Users';
$pageBreadcrumb = 'Users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device=device-width, initial-scale=1.0">
    <title>Users — UMU Assets</title>
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
                    <h2>User Management</h2>
                    <p>Manage system users — Admins, Staff, and Students</p>
                </div>
                <button class="btn btn-primary" onclick="openModal('addUserModal')">👤 Add User</button>
            </div>

            <?php if ($error): ?><div class="alert alert-danger">⚠️ <?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <span class="card-title">👥 System Users (<?= $users ? $users->num_rows : 0 ?>)</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>ID / Reg. No.</th>
                            <th>Department</th>
                            <th>Active Borrows</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if ($users): $i=1; while($row = $users->fetch_assoc()): ?>
                        <tr>
                            <td style="color:var(--gray-500);"><?= $i++ ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="user-avatar" style="width:32px;height:32px;font-size:12px;background:<?= $row['role']==='admin'?'linear-gradient(135deg,var(--accent),var(--accent-light))':($row['role']==='staff'?'linear-gradient(135deg,var(--primary),var(--primary-light))':'linear-gradient(135deg,#28a745,#5cb85c)') ?>;">
                                        <?= strtoupper(substr($row['full_name'],0,1)) ?>
                                    </div>
                                    <strong><?= htmlspecialchars($row['full_name']) ?></strong>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td>
                                <?php $rb = ['admin'=>'badge-gold','staff'=>'badge-primary','student'=>'badge-success'];
                                $ri = ['admin'=>'👑','staff'=>'👔','student'=>'🎓']; ?>
                                <span class="badge <?= $rb[$row['role']] ?>"><?= $ri[$row['role']] ?> <?= ucfirst($row['role']) ?></span>
                            </td>
                            <td><code style="font-size:12px;"><?= htmlspecialchars($row['student_staff_id'] ?: '—') ?></code></td>
                            <td><?= htmlspecialchars($row['department'] ?: '—') ?></td>
                            <td>
                                <?php if ($row['active_borrows'] > 0): ?>
                                <span class="badge badge-warning">📤 <?= $row['active_borrows'] ?></span>
                                <?php else: ?>
                                <span style="color:var(--gray-500);">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row['status'] === 'active'): ?>
                                <span class="badge badge-success">● Active</span>
                                <?php else: ?>
                                <span class="badge badge-danger">● Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?= !empty($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '—' ?></td>
                            <td>
                                <div class="table-actions">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($row['id']) ?>">
                                        <button type="submit" class="btn btn-sm <?= $row['status']==='active'?'btn-warning':'btn-success' ?>" <?= $row['id']==$_SESSION['id']?'disabled':'' ?> title="Toggle Status">
                                            <?= $row['status']==='active'?'🚫':'✅' ?>
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-outline" title="Reset Password" onclick="resetPwd('<?= htmlspecialchars($row['id']) ?>', '<?= htmlspecialchars($row['full_name']) ?>')">🔑</button>
                                    <?php if ($row['id'] !== $_SESSION['id']): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete <?= htmlspecialchars($row['full_name']) ?>?')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?= htmlspecialchars($row['id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">👤 Add New User</span>
            <button class="modal-close" onclick="closeModal('addUserModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_user">
            <div class="modal-body">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role" class="form-control" required>
                            <option value="student">🎓 Student</option>
                            <option value="staff">👔 Staff</option>
                            <option value="admin">👑 Admin</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Student/Staff ID</label>
                        <input type="text" name="student_staff_id" class="form-control" placeholder="e.g. UMU/2024/001">
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" class="form-control" required placeholder="Min. 6 characters">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="resetPwdModal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">🔑 Reset Password</span>
            <button class="modal-close" onclick="closeModal('resetPwdModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="reset_user_id">
            <div class="modal-body">
                <p style="margin-bottom:16px;">Resetting password for: <strong id="reset_user_name"></strong></p>
                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" name="new_password" class="form-control" required placeholder="Min. 6 characters">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('resetPwdModal')">Cancel</button>
                <button type="submit" class="btn btn-warning">🔑 Reset Password</button>
            </div>
        </form>
    </div>
</div>

<script src="../js/app.js"></script>
<script>
function resetPwd(id, name) {
    document.getElementById('reset_user_id').value = id;
    document.getElementById('reset_user_name').textContent = name;
    openModal('resetPwdModal');
}
</script>
</body>
</html>
