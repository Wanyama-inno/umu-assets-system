<?php
require_once '../includes/config.php';
requireAdmin();

$conn = getDBConnection();

$reportType = $_GET['report'] ?? 'all_assets';
$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo = $_GET['to'] ?? date('Y-m-d');

// Summary stats
$totalAssets = $conn->query("SELECT COUNT(*) as c FROM assets")->fetch_assoc()['c'];
$borrowable = $conn->query("SELECT COUNT(*) as c FROM assets WHERE asset_type='borrowable'")->fetch_assoc()['c'];
$nonBorrowable = $conn->query("SELECT COUNT(*) as c FROM assets WHERE asset_type='non_borrowable'")->fetch_assoc()['c'];
$available = $conn->query("SELECT COUNT(*) as c FROM assets WHERE status='available'")->fetch_assoc()['c'];
$borrowed = $conn->query("SELECT COUNT(*) as c FROM assets WHERE status='borrowed'")->fetch_assoc()['c'];
$damaged = $conn->query("SELECT COUNT(*) as c FROM assets WHERE `condition`='damaged'")->fetch_assoc()['c'];
$maintenance = $conn->query("SELECT COUNT(*) as c FROM assets WHERE `condition`='under_maintenance'")->fetch_assoc()['c'];
$overdue = $conn->query("SELECT COUNT(*) as c FROM borrow_records WHERE status='active' AND expected_return_date < CURDATE()")->fetch_assoc()['c'];

// Report data
switch($reportType) {
    case 'borrowed':
        $data = $conn->query("SELECT br.*, a.asset_name, a.asset_code, u.full_name as borrower_name, u.student_staff_id, u.department FROM borrow_records br JOIN assets a ON br.asset_id=a.id JOIN users u ON br.borrower_id=u.id WHERE br.status='active' ORDER BY br.expected_return_date ASC");
        $reportTitle = 'Currently Borrowed Assets';
        break;
    case 'overdue':
        $data = $conn->query("SELECT br.*, a.asset_name, a.asset_code, u.full_name as borrower_name, u.student_staff_id, u.phone, u.department FROM borrow_records br JOIN assets a ON br.asset_id=a.id JOIN users u ON br.borrower_id=u.id WHERE br.status='active' AND br.expected_return_date < CURDATE() ORDER BY br.expected_return_date ASC");
        $reportTitle = 'Overdue Assets Report';
        break;
    case 'returned':
        $data = $conn->query("SELECT br.*, a.asset_name, a.asset_code, u.full_name as borrower_name, u.student_staff_id FROM borrow_records br JOIN assets a ON br.asset_id=a.id JOIN users u ON br.borrower_id=u.id WHERE br.status='returned' AND DATE(br.actual_return_date) BETWEEN '$dateFrom' AND '$dateTo' ORDER BY br.actual_return_date DESC");
        $reportTitle = 'Returned Assets Report';
        break;
    case 'maintenance':
        $data = $conn->query("SELECT ml.*, a.asset_name, a.asset_code, u.full_name as logged_by_name FROM maintenance_logs ml JOIN assets a ON ml.asset_id=a.id JOIN users u ON ml.logged_by=u.id WHERE DATE(ml.maintenance_date) BETWEEN '$dateFrom' AND '$dateTo' ORDER BY ml.maintenance_date DESC");
        $reportTitle = 'Maintenance Report';
        break;
    case 'damaged':
        $data = $conn->query("SELECT a.*, COALESCE(c.name, 'Uncategorized') as category_name FROM assets a LEFT JOIN asset_categories c ON a.category_id=c.id WHERE a.`condition`='damaged' OR a.status='under_repair' ORDER BY a.asset_name");
        $reportTitle = 'Damaged / Under Repair Assets';
        break;
    default:
        $data = $conn->query("SELECT a.*, COALESCE(c.name, 'Uncategorized') as category_name FROM assets a LEFT JOIN asset_categories c ON a.category_id=c.id ORDER BY a.asset_type, a.asset_name");
        $reportTitle = 'All Assets Report';
}

$pageTitle = 'Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — UMU Assets</title>
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
                    <h2>Reports & Analytics</h2>
                    <p>Asset management reports</p>
                </div>
                <button class="btn btn-primary" onclick="window.print()">🖨️ Print</button>
                <button class="btn btn-success" onclick="exportCSV()">📥 CSV</button>
            </div>

            <!-- Summary Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">🗄️</div>
                    <div class="stat-body">
                        <h3><?= $totalAssets ?></h3>
                        <p>Total Assets</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">✅</div>
                    <div class="stat-body">
                        <h3><?= $available ?></h3>
                        <p>Available</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow">📤</div>
                    <div class="stat-body">
                        <h3><?= $borrowed ?></h3>
                        <p>Borrowed</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">🔧</div>
                    <div class="stat-body">
                        <h3><?= $damaged + $maintenance ?></h3>
                        <p>Issues</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card">
                <form method="GET" class="filter-bar">
                    <select name="report">
                        <option value="all_assets" <?= $reportType=='all_assets'?'selected':'' ?>>All Assets</option>
                        <option value="borrowed" <?= $reportType=='borrowed'?'selected':'' ?>>Borrowed</option>
                        <option value="overdue" <?= $reportType=='overdue'?'selected':'' ?>>Overdue</option>
                        <option value="returned" <?= $reportType=='returned'?'selected':'' ?>>Returned</option>
                        <option value="maintenance" <?= $reportType=='maintenance'?'selected':'' ?>>Maintenance</option>
                        <option value="damaged" <?= $reportType=='damaged'?'selected':'' ?>>Damaged</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Generate</button>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <span class="card-title"><?= $reportTitle ?> (<?= $data->num_rows ?>)</span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <?php if ($reportType === 'all_assets'): ?>
                        <thead><tr><th>ID</th><th>Name</th><th>Code</th><th>Type</th><th>Status</th><th>Value</th></tr></thead>
                        <tbody>
                            <?php $i=1; $data->data_seek(0); while($r = $data->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($r['asset_name']) ?></td>
                                <td><?= htmlspecialchars($r['asset_code']) ?></td>
                                <td><?= $r['asset_type'] ?></td>
                                <td><?= $r['status'] ?></td>
                                <td><?= number_format($r['purchase_value'] ?? 0) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <?php elseif ($reportType === 'borrowed'): ?>
                        <thead><tr><th>ID</th><th>Asset</th><th>User</th><th>Due</th></tr></thead>
                        <tbody>
                            <?php $i=1; $data->data_seek(0); while($r = $data->fetch_assoc()): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($r['asset_name']) ?></td>
                                <td><?= htmlspecialchars($r['borrower_name']) ?></td>
                                <td><?= $r['expected_return_date'] ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <?php endif; ?>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function exportCSV() {
  let csv = [];
  const rows = document.querySelectorAll('.table-wrapper table tbody tr');
  for(let row of rows) {
    let cols = row.querySelectorAll('td');
    let csvRow = [];
    for(let col of cols) csvRow.push('"' + col.innerText.replace(/"/g, '""') + '"');
    csv.push(csvRow.join(','));
  }
  const csvContent = csv.join('\n');
  const csvFile = 'report_<?= $reportType ?>_' + Date.now() + '.csv';
  const blob = new Blob([csvContent], {type: 'text/csv'});
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = csvFile;
  a.click();
}
</script>
</body>
</html>

