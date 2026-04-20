<?php
// includes/header.php
// Usage: include with $pageTitle and $pageBreadcrumb set
$pageTitle = $pageTitle ?? 'Dashboard';
$today = date('D, d M Y');
?>
<header class="header">
    <div class="header-left">
        <button class="btn btn-icon btn-outline" id="sidebarToggle" title="Toggle Sidebar">☰</button>
        <div>
            <div class="page-title"><?= $pageTitle ?></div>
            <?php if (!empty($pageBreadcrumb)): ?>
            <div class="breadcrumb">
                <a href="../pages/dashboard.php">Home</a>
                <span>›</span>
                <span><?= $pageBreadcrumb ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="header-right">
        <div class="header-date"><?= $today ?></div>

        <a href="../pages/dashboard.php" class="btn btn-sm btn-primary">🏠 Dashboard</a>
    </div>

</header>
