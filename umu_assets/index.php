<?php
require_once 'includes/config.php';

if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit();
}

$error = '';
$success = '';
$activeTab = 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $activeTab = 'login';
        $email = sanitize($conn, $_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
$stmt = $conn->prepare("SELECT id, full_name, email, password, role, status FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user && $password === $user['password']) {
                if ($user['status'] === 'inactive') {
                    $error = 'Your account has been deactivated. Contact the administrator.';
                } else {
$_SESSION['id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    header('Location: pages/dashboard.php');
                    exit();
                }
            } else {
                $error = 'Invalid email or password. Please try again.';
            }
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — UMU Assets Management System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-body">
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">
            <div class="auth-logo-icon">🏛️</div>
            <h1>Uganda Martyrs University</h1>
            <p>Assets Management System · Masaka Campus</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger">⚠️ <?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= $success ?></div>
        <?php endif; ?>

        <!-- LOGIN FORM ONLY -->
        <div class="tab-content active" id="tab-login">
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Email Address *</label>
                    <div class="input-group">
                        <span class="input-icon">✉️</span>
                        <input type="email" name="email" class="form-control" placeholder="your@umu.ac.ug" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <div class="input-group">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-top:8px;">
                    🔐 Sign In to System
                </button>
            </form>
            <div style="margin-top: 16px; padding: 12px; background: var(--gray-100); border-radius: var(--radius); font-size: 12px; color: var(--gray-700);">
                <strong>Demo Credentials:</strong><br>
                <strong>Admin:</strong> admin@umu.ac.ug / Admin@123<br>
                Contact admin to get account if needed.
            </div>
            <div style="margin-top: 20px; padding: 16px; background: var(--gray-50); border-radius: var(--radius); border-left: 4px solid var(--accent);">
                <strong>👑 Admin-only user management:</strong> Login as admin → Users page to add staff/students.
            </div>
        </div>
    </div>
</div>
<script>
function togglePassword(icon) {
  const pass = document.getElementById('login-password');
  if (pass.type === 'password') {
    pass.type = 'text';
    icon.textContent = '🙈';
  } else {
    pass.type = 'password';
    icon.textContent = '👁️';
  }
}
</script>
</body>
</html>
