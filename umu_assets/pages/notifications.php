<?php
require_once '../includes/config.php';
requireLogin();

$conn = getDBConnection();

$user_id = $_SESSION['id'];

// Handle mark read
if (isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $notif_id = (int)$_POST['notif_id'];
    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $notif_id AND user_id = $user_id");
    exit('OK');
}

// Get notifications
$notifications = $conn->query("SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 50");

$pageTitle = 'Notifications';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications — UMU Assets</title>
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
                    <h2>Notifications</h2>
                    <p>System alerts and updates</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title">🔔 Notifications (<?= $notifications->num_rows ?>)</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead><tr><th>Time</th><th>Title</th><th>Message</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php while($row = $notifications->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d M H:i', strtotime($row['created_at'])) ?></td>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars(substr($row['message'], 0, 100)) ?>...</td>
                                <td><span class="badge <?= $row['is_read'] ? 'badge-secondary' : 'badge-primary' ?>"><?= $row['is_read'] ? 'Read' : 'Unread' ?></span></td>
                                <td>
                                    <?php if (!$row['is_read']): ?>
                                    <button class="btn btn-sm btn-primary" onclick="markRead(<?= $row['id'] ?>)">Mark Read</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function markRead(id) {
    fetch('', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=mark_read&notif_id=' + id
    }).then(() => location.reload());
}
</script>
</body>
</html>
