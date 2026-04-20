<?php
require_once '../includes/config.php';
requireAdmin();

$conn = getDBConnection();

$filter = $_GET['filter'] ?? '';
$where = '';
if ($filter === 'today') $where = "WHERE DATE(created_at) = CURDATE()";
else if ($filter === 'week') $where = "WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
else if ($filter === 'month') $where = "WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 MONTH)";

$logs = $conn->query("
    SELECT al.*, u.full_name, u.role
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $where
    ORDER BY al.created_at DESC
    LIMIT 100
");

$pageTitle = 'Activity Log';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log — UMU Assets</title>
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
                    <h2>Activity Log</h2>
                    <p>System audit trail and user actions</p>
                </div>
            </div>

            <div class="filter-bar" style="margin-bottom:20px;">
                <a href="activity_log.php" class="btn btn-outline <?= empty($filter)?'btn-primary':'' ?>">All</a>
                <a href="activity_log.php?filter=today" class="btn btn-outline <?= $filter=='today'?'btn-primary':'' ?>">Today</a>
                <a href="activity_log.php?filter=week" class="btn btn-outline <?= $filter=='week'?'btn-primary':'' ?>">Last 7 Days</a>
                <a href="activity_log.php?filter=month" class="btn btn-outline <?= $filter=='month'?'btn-primary':'' ?>">Last Month</a>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title">📋 Recent Activity (<?= $logs->num_rows ?> records)</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
                        <tbody>
                            <?php while($row = $logs->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d M Y H:i', strtotime($row['created_at'])) ?></td>
                                <td><?= htmlspecialchars($row['full_name'] ?? 'System') ?> <span class="badge badge-secondary"><?= ucfirst($row['role'] ?? '') ?></span></td>
                                <td><strong><?= htmlspecialchars($row['action']) ?></strong></td>
                                <td><?= htmlspecialchars(json_decode($row['details'], true)['message'] ?? $row['details']) ?></td>
                                <td><?= htmlspecialchars($row['ip']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
