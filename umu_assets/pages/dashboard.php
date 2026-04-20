<?php
ini_set('max_execution_time', 300);
require_once '../includes/config.php';
requireLogin();

$conn = getDBConnection();

// Dashboard Stats
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as c FROM assets")->fetch_assoc()['c'];
$stats['borrowable'] = $conn->query("SELECT COUNT(*) as c FROM assets WHERE asset_type='borrowable'")->fetch_assoc()['c'];
$stats['non_borrowable'] = $conn->query("SELECT COUNT(*) as c FROM assets WHERE asset_type='non_borrowable'")->fetch_assoc()['c'];
$stats['available'] = $conn->query("SELECT COUNT(*) as c FROM assets WHERE status='available'")->fetch_assoc()['c'];
$stats['borrowed'] = $conn->query("SELECT COUNT(*) as c FROM assets WHERE status='borrowed'")->fetch_assoc()['c'];
$stats['maintenance'] = $conn->query("SELECT COUNT(*) as c FROM assets WHERE status='under_repair' OR `condition`='under_maintenance'")->fetch_assoc()['c'];
$stats['damaged'] = $conn->query("SELECT COUNT(*) as c FROM assets WHERE `condition`='damaged'")->fetch_assoc()['c'];
$stats['overdue'] = $conn->query("SELECT COUNT(*) as c FROM borrow_records WHERE status='active' AND expected_return_date < CURDATE()")->fetch_assoc()['c'];

// Recent Borrows
$recentBorrows = $conn->query("
    SELECT br.*, a.asset_name, a.asset_code, u.full_name as borrower_name
    FROM borrow_records br
JOIN assets a ON br.asset_id = a.id
JOIN users u ON br.borrower_id = u.id
    ORDER BY br.borrow_date DESC LIMIT 8
");

// Assets needing attention
$attention = $conn->query("
    SELECT * FROM assets 
    WHERE `condition` != 'good' OR status = 'under_repair'
    ORDER BY updated_at DESC LIMIT 5
");

// Recent activity logs
$recentLogs = $conn->query("
    SELECT al.*, u.full_name, u.role 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC LIMIT 5
");

// Unread notifications
$unreadCount = getUnreadCount($conn, $_SESSION['id']);

// Recent maintenance
$recentMaintenance = $conn->query("
    SELECT ml.*, a.asset_name, a.asset_code, u.full_name as logged_by_name
    FROM maintenance_logs ml
JOIN assets a ON ml.asset_id = a.id
JOIN users u ON ml.logged_by = u.id
    ORDER BY ml.created_at DESC LIMIT 5
");

$pageTitle = 'Dashboard';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — UMU Assets</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="app-layout">
    <?php include '../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php include '../includes/header.php'; ?>
        <div class="page-content">

            <!-- Overdue Alert -->
            <?php if ($stats['overdue'] > 0): ?>
            <div class="alert alert-danger">
                ⚠️ <strong><?= $stats['overdue'] ?> overdue borrowed asset(s)</strong> need immediate attention.
                <a href="returns.php" style="font-weight:700; text-decoration:underline;">View & process returns →</a>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">🗄️</div>
                    <div class="stat-body">
                        <h3><?= $stats['total'] ?></h3>
                        <p>Total Assets</p>
                        <span><?= $stats['borrowable'] ?> borrowable · <?= $stats['non_borrowable'] ?> fixed</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">✅</div>
                    <div class="stat-body">
                        <h3><?= $stats['available'] ?></h3>
                        <p>Available Assets</p>
                        <span>Ready for borrowing</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow">📤</div>
                    <div class="stat-body">
                        <h3><?= $stats['borrowed'] ?></h3>
                        <p>Currently Borrowed</p>
                        <span><?= $stats['overdue'] ?> overdue</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">🔧</div>
                    <div class="stat-body">
                        <h3><?= $stats['maintenance'] ?></h3>
                        <p>Under Maintenance</p>
                        <span><?= $stats['damaged'] ?> damaged</span>
                    </div>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="grid-2" style="gap:22px;">

                <!-- Recent Borrows -->
                <div class="card">
                    <div class="card-header">
                        <span class="card-title">📤 Recent Borrow Records</span>
                        <a href="borrow.php" class="btn btn-sm btn-primary">+ New Borrow</a>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead><tr>
                                <th>Asset</th>
                                <th>Borrower</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr></thead>
                            <tbody>
                            <?php if ($recentBorrows->num_rows === 0): ?>
                            <tr><td colspan="4"><div class="empty-state"><div class="empty-icon">📭</div><p>No borrow records yet</p></div></td></tr>
                            <?php else: while($row = $recentBorrows->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($row['asset_name']) ?></strong><br>
                                    <small style="color:var(--gray-500); font-family: var(--font-mono);"><?= $row['asset_code'] ?></small>
                                </td>
                                <td><?= htmlspecialchars($row['borrower_name']) ?><br>
                                <small><span class="badge badge-secondary"><?= $row['borrower_role'] ?></span></small></td>
                                <td>
                                    <?= date('d M Y', strtotime($row['expected_return_date'])) ?>
                                    <?php if ($row['status'] === 'active' && isOverdue($row['expected_return_date'])): ?>
                                    <br><span class="overdue-tag">⚠️ Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'returned'): ?>
                                    <span class="badge badge-success">✅ Returned</span>
                                    <?php elseif ($row['status'] === 'active' && isOverdue($row['expected_return_date'])): ?>
                                    <span class="badge badge-danger">⚠️ Overdue</span>
                                    <?php else: ?>
                                    <span class="badge badge-warning">📤 Active</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
        <!-- Notifications -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">🔔 Notifications (<?= $unreadCount ?> unread)</span>
            </div>
            <div class="card-body" style="padding:0;">
                <div style="padding:16px;">
                    <?php if ($unreadCount === 0): ?>
                    <div class="empty-state">
                        <p>No new notifications</p>
                    </div>
                    <?php else: ?>
                    <div style="max-height:200px;overflow-y:auto;">
                        All notifications viewable <a href="notifications.php">here</a>.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
    <!-- Right Column -->
    <div style="display:flex; flex-direction:column; gap:22px;">

                    <!-- Assets Needing Attention -->
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">⚠️ Needs Attention</span>
                            <a href="maintenance.php" class="btn btn-sm btn-warning">View All</a>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <?php if ($attention->num_rows === 0): ?>
                            <div class="empty-state" style="padding: 24px;">
                                <div class="empty-icon">🎉</div>
                                <p>All assets in good condition!</p>
                            </div>
                            <?php else: while($row = $attention->fetch_assoc()): ?>
                            <div style="display:flex; align-items:center; gap:12px; padding: 12px 18px; border-bottom: 1px solid var(--gray-200);">
                                <div style="flex:1;">
                                    <strong style="font-size:13px;"><?= htmlspecialchars($row['asset_name']) ?></strong><br>
                                    <small style="color:var(--gray-500);"><?= $row['asset_code'] ?></small>
                                </div>
                                <?php
                                $cond = $row['condition'];
                                $badge = $cond === 'good' ? 'badge-success' : ($cond === 'damaged' ? 'badge-danger' : 'badge-warning');
                                ?>
                                <span class="badge <?= $badge ?>"><?= ucfirst(str_replace('_', ' ', $cond)) ?></span>
                            </div>
                            <?php endwhile; endif; ?>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">📈 Recent Activity</span>
                            <a href="activity_log.php" class="btn btn-sm btn-outline">View Log</a>
                        </div>
                        <div class="card-body" style="padding:0;">
                            <?php if ($recentLogs->num_rows === 0): ?>
                            <div class="empty-state" style="padding:24px;"><p>No activity yet</p></div>
                            <?php else: while($row = $recentLogs->fetch_assoc()): ?>
                            <div style="padding: 12px 18px; border-bottom: 1px solid var(--gray-200);">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <strong style="font-size:13px;"><?= htmlspecialchars($row['full_name'] ?? 'System') ?></strong>
                                        <br><small style="color:var(--gray-500);"><?= htmlspecialchars($row['action']) ?></small>
                                    </div>
                                    <small style="color:var(--gray-500);"><?= timeAgo($row['created_at']) ?></small>
                                </div>
                            </div>
                            <?php endwhile; endif; ?>
                        </div>
                    </div>
                    <!-- Recent Maintenance -->
                    <div class="card">
                        <div class="card-header">
                            <span class="card-title">🔧 Maintenance Log</span>
                            <a href="maintenance.php" class="btn btn-sm btn-outline">View All</a>
                        </div>
                        <div class="card-body" style="padding:0;">
                            <?php if ($recentMaintenance->num_rows === 0): ?>
                            <div class="empty-state" style="padding:24px;"><p>No maintenance logged yet</p></div>
                            <?php else: while($row = $recentMaintenance->fetch_assoc()): ?>
                            <div style="padding: 12px 18px; border-bottom: 1px solid var(--gray-200);">
                                <div style="display:flex; justify-content:space-between;">
                                    <strong style="font-size:13px;"><?= htmlspecialchars($row['asset_name']) ?></strong>
                                    <?php
                                    $s = $row['status'];
                                    $sb = $s === 'completed' ? 'badge-success' : ($s === 'in_progress' ? 'badge-warning' : 'badge-secondary');
                                    ?>
                                    <span class="badge <?= $sb ?>"><?= ucfirst(str_replace('_', ' ', $s)) ?></span>
                                </div>
                                <small style="color:var(--gray-500);"><?= htmlspecialchars(substr($row['description'], 0, 65)) ?>...</small>
                            </div>
                            <?php endwhile; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="../js/app.js"></script>
</body>
</html>
<?php /* Connection closed */ ?>
