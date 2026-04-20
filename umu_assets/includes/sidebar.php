<?php
// includes/sidebar.php
if (!isLoggedIn()) { header('Location: ../index.php'); exit(); }
$role = $_SESSION['role'];
$userName = $_SESSION['full_name'];
$userInitials = strtoupper(substr($userName, 0, 1) . (strpos($userName, ' ') !== false ? substr(strrchr($userName, ' '), 1, 1) : ''));

$conn = getDBConnection();

// Get badge counts
$overdueResult = $conn->query("SELECT COUNT(*) as cnt FROM borrow_records WHERE status='active' AND expected_return_date < CURDATE()");
$overdueCount = $overdueResult->fetch_assoc()['cnt'];

$maintenanceResult = $conn->query("SELECT COUNT(*) as cnt FROM maintenance_logs WHERE status='in_progress'");
$maintenanceCount = $maintenanceResult->fetch_assoc()['cnt'];
$conn->close();

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="../pages/dashboard.php" class="sidebar-brand">
            <div class="sidebar-brand-icon">🏛️</div>
            <div class="sidebar-brand-text">
                <h2>UMU Assets</h2>
                <span>Masaka Campus</span>
            </div>
        </a>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?= $userInitials ?></div>
        <div class="user-info">
            <h4><?= htmlspecialchars(explode(' ', $userName)[0]) ?></h4>
            <span><?= ucfirst($role) ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="../pages/dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span> Dashboard
        </a>

        <?php if ($role === 'admin'): ?>
        <div class="nav-section">Management</div>
        <a href="../pages/users.php" class="nav-item <?= $currentPage == 'users.php' ? 'active' : '' ?>">
            <span class="nav-icon">👥</span> Manage Users
        </a>
        <a href="../pages/assets.php" class="nav-item <?= $currentPage == 'assets.php' ? 'active' : '' ?>">
            <span class="nav-icon">🗄️</span> Manage Assets
        </a>
        <?php endif; ?>

        <div class="nav-section">Operations</div>
        <a href="../pages/borrow.php" class="nav-item <?= $currentPage == 'borrow.php' ? 'active' : '' ?>">
            <span class="nav-icon">📤</span> Borrow Assets
        </a>
        <a href="../pages/returns.php" class="nav-item <?= $currentPage == 'returns.php' ? 'active' : '' ?>">
            <span class="nav-icon">📥</span> Returns
            <?php if ($overdueCount > 0): ?>
            <span class="nav-badge"><?= $overdueCount ?></span>
            <?php endif; ?>
        </a>
        <a href="../pages/maintenance.php" class="nav-item <?= $currentPage == 'maintenance.php' ? 'active' : '' ?>">
            <span class="nav-icon">🔧</span> Maintenance
            <?php if ($maintenanceCount > 0): ?>
            <span class="nav-badge"><?= $maintenanceCount ?></span>
            <?php endif; ?>
        </a>


        <?php if ($role === 'admin'): ?>
        <div class="nav-section">Reports</div>
        <a href="../pages/reports.php" class="nav-item <?= $currentPage == 'reports.php' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span> Reports
        </a>
        <a href="../pages/activity_log.php" class="nav-item <?= $currentPage == 'activity_log.php' ? 'active' : '' ?>">
            <span class="nav-icon">📈</span> Activity Log
        </a>
        <a href="../pages/notifications.php" class="nav-item <?= $currentPage == 'notifications.php' ? 'active' : '' ?>">
            <span class="nav-icon">🔔</span> Notifications
        </a>
        <?php endif; ?>
    </nav>


    <div class="sidebar-footer">
        <a href="../logout.php" class="btn btn-outline btn-block" style="color: rgba(255,255,255,0.7); border-color: rgba(255,255,255,0.2); font-size: 12px;">
            🚪 Sign Out
        </a>
    </div>
</aside>
