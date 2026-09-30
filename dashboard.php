[9/30/2026 4:43 AM] Adis dish: <?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$dbPath = DIR . '/ATDbingo.sqlite';

$totalTopup = 0.00;
$activeTransfers = 0;
$recentRecords = [];

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS generated_licenses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        alias_name TEXT,
        mode TEXT NOT NULL,
        action_details TEXT NOT NULL,
        mac_address TEXT,
        topup_amount REAL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reference_id TEXT UNIQUE NOT NULL,
        username TEXT NOT NULL,
        amount REAL NOT NULL,
        status TEXT DEFAULT 'PENDING',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        claimed_at DATETIME
    )");

    $sum = $db->query("SELECT SUM(topup_amount) FROM generated_licenses WHERE mode = 'topup'")->fetchColumn();
    $totalTopup = $sum !== false ? (float)$sum : 0.00;

    $count = $db->query("SELECT COUNT(*) FROM transactions WHERE status = 'PENDING'")->fetchColumn();
    $activeTransfers = $count !== false ? (int)$count : 0;

    $stmtRecent = $db->query("SELECT * FROM generated_licenses ORDER BY id DESC LIMIT 8");
    $recentRecords = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATD Control Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #2563eb; --sidebar-bg: #0f172a; --bg-main: #f8fafc; --card-bg: #ffffff; --border: #e2e8f0; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { display: flex; min-height: 100vh; background-color: var(--bg-main); color: #0f172a; margin: 0; }
        .sidebar { width: 250px; background-color: var(--sidebar-bg); color: #94a3b8; display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .brand { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
        .nav-link { color: #94a3b8; text-decoration: none; padding: 12px; border-radius: 8px; margin-bottom: 5px; display: flex; align-items: center; gap: 10px; }
        .nav-link.active, .nav-link:hover { background-color: #1e293b; color: #fff; }
        .nav-link.logout { margin-top: auto; color: #f87171; }
        .main { flex: 1; padding: 30px; }
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .metric-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .metric-card h4 { font-size: 13px; color: #64748b; text-transform: uppercase; margin: 0; }
        .metric-card p { font-size: 24px; font-weight: 700; margin: 8px 0 0 0; }
        .table-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card-header { padding: 18px 24px; border-bottom: 1px solid var(--border); font-weight: 600; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 14px 24px; text-align: left; border-bottom: 1px solid var(--border); }
[9/30/2026 4:43 AM] Adis dish: th { background: #f8fafc; color: #64748b; font-weight: 600; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #dcfce7; color: #15803d; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="brand"><i class="fa-solid fa-gamepad"></i> ATD Control</div>
        <a href="dashboard.php" class="nav-link active"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
        <a href="generate_balance.php" class="nav-link"><i class="fa-solid fa-paper-plane"></i> Transfer Balance</a>
        <a href="logout.php" class="nav-link logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
    <div class="main">
        <h2 style="margin-bottom: 20px;">System Overview</h2>
        <div class="metrics-grid">
            <div class="metric-card">
                <h4>Total Transferred</h4>
                <p>ETB <?= number_format($totalTopup, 2) ?></p>
            </div>
            <div class="metric-card">
                <h4>Pending Transfers</h4>
                <p><?= (int)$activeTransfers ?></p>
            </div>
        </div>

        <div class="table-card">
            <div class="card-header">Recent Transfer Activities</div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Target Client</th>
                        <th>Status</th>
                        <th>Details</th>
                        <th>Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentRecords)): ?>
                        <tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 24px;">ምንም የተላለፈ መረጃ የለም።</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentRecords as $r): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($r['id']) ?></td>
                                <td><strong><?= htmlspecialchars($r['alias_name'] ?? '-') ?></strong></td>
                                <td><span class="badge">Success</span></td>
                                <td><?= htmlspecialchars($r['action_details']) ?></td>
                                <td>ETB <?= number_format((float)($r['topup_amount'] ?? 0), 2) ?></td>
                                <td><?= htmlspecialchars($r['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
