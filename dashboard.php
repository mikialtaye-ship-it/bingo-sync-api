<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$dbPath = __DIR__ . '/ATDbingo.sqlite';

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

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT,
        name TEXT,
        balance REAL NOT NULL DEFAULT 0.00,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // last_updated በነባር ቴብል ውስጥ ከሌለ ራሱ እንዲፈጥረው ያደርጋል
    try {
        $db->exec("ALTER TABLE users ADD COLUMN last_updated DATETIME DEFAULT CURRENT_TIMESTAMP");
    } catch (PDOException $ignored) {
        // ኮለምኑ አስቀድሞ ካለ ችላ ይለዋል
    }

    $totalUsers = (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalBalance = (float) $db->query("SELECT SUM(balance) FROM users")->fetchColumn();
    $totalLicenses = (int) $db->query("SELECT COUNT(*) FROM generated_licenses")->fetchColumn();
    $totalTopup = (float) $db->query("SELECT SUM(topup_amount) FROM generated_licenses WHERE mode = 'topup'")->fetchColumn();

    $stmtRecent = $db->query("SELECT * FROM generated_licenses ORDER BY id DESC LIMIT 6");
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
    <title>ATD Bingo Control Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --sidebar-bg: #0f172a;
            --sidebar-text: #94a3b8;
            --sidebar-hover: #1e293b;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #0f172a;
            --text-light: #64748b;
            --border-color: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        body {
            display: flex;
            min-height: 100vh;
            background-color: var(--bg-main);
            color: var(--text-dark);
        }

        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            padding: 24px 16px;
            flex-shrink: 0;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 12px 24px;
            border-bottom: 1px solid #1e293b;
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
        }

        .brand i {
            color: var(--primary);
            font-size: 22px;
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 24px;
            flex: 1;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 14px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--sidebar-text);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .nav-link:hover, .nav-link.active {
            background-color: var(--sidebar-hover);
            color: #ffffff;
        }

        .nav-link.active {
            background-color: var(--primary);
            color: #ffffff;
        }

        .nav-link.logout {
            color: #f87171;
            margin-top: auto;
        }

        .nav-link.logout:hover {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .main-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow-y: auto;
        }

        .topbar {
            background: #ffffff;
            border-bottom: 1px solid var(--border-color);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .btn-sync {
            background-color: var(--primary);
            color: #ffffff;
            border: none;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s ease;
        }

        .btn-sync:hover {
            background-color: var(--primary-hover);
        }

        .btn-sync:disabled {
            background-color: #94a3b8;
            cursor: not-allowed;
        }

        .user-tag {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-light);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e0e7ff;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .content {
            padding: 32px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 24px;
        }

        .sync-alert {
            display: none;
            padding: 12px 18px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            align-items: center;
            gap: 10px;
        }

        .sync-alert.success {
            display: flex;
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .sync-alert.error {
            display: flex;
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .metric-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .icon-blue { background: #dbeafe; color: #2563eb; }
        .icon-green { background: #dcfce7; color: #16a34a; }
        .icon-amber { background: #fef3c7; color: #d97706; }
        .icon-purple { background: #f3e8ff; color: #9333ea; }

        .metric-info h4 {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metric-info p {
            font-size: 22px;
            font-weight: 700;
            margin-top: 4px;
            color: var(--text-dark);
        }

        .table-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 16px;
            font-weight: 600;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        thead th {
            background: #f8fafc;
            color: var(--text-light);
            font-weight: 600;
            text-align: left;
            padding: 12px 24px;
            border-bottom: 1px solid var(--border-color);
        }

        tbody td {
            padding: 14px 24px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-dark);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-topup { background: #dcfce7; color: #15803d; }
        .badge-user { background: #e0e7ff; color: #4338ca; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="brand">
            <i class="fa-solid fa-gamepad"></i>
            <span>ATD Bingo Control</span>
        </div>
        <nav class="nav-links">
            <a href="dashboard.php" class="nav-link active">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
            <a href="generate_balance.php" class="nav-link">
                <i class="fa-solid fa-bolt"></i>
                <span>Generate Balance</span>
            </a>
            <a href="logout.php" class="nav-link logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <div class="main-wrapper">
        <header class="topbar">
            <div><strong>Control Center</strong></div>
            <div class="topbar-actions">
                <button id="syncBtn" class="btn-sync" onclick="syncOnlineBalance()">
                    <i id="syncIcon" class="fa-solid fa-rotate"></i>
                    <span id="syncText">Sync Online</span>
                </button>
                <div class="user-tag">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
                    </div>
                    <span><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
                </div>
            </div>
        </header>

        <main class="content">
            <div id="syncAlert" class="sync-alert"></div>

            <h2 class="page-title">Overview</h2>

            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon icon-blue">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div class="metric-info">
                        <h4>Total Balance</h4>
                        <p id="displayBalance">ETB <?= number_format($totalBalance, 2) ?></p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon icon-green">
                        <i class="fa-solid fa-arrow-trend-up"></i>
                    </div>
                    <div class="metric-info">
                        <h4>Top-Up Generated</h4>
                        <p>ETB <?= number_format($totalTopup, 2) ?></p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon icon-purple">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="metric-info">
                        <h4>Registered Users</h4>
                        <p><?= number_format($totalUsers) ?></p>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon icon-amber">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <div class="metric-info">
                        <h4>Licenses Created</h4>
                        <p><?= number_format($totalLicenses) ?></p>
                    </div>
                </div>
            </div>

            <div class="table-card">
                <div class="card-header">
                    <h3>Recent Generated Activity</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Alias / Target</th>
                            <th>Type</th>
                            <th>Details</th>
                            <th>Amount / MAC</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentRecords)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-light); padding: 24px;">
                                    No records available yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentRecords as $row): ?>
                                <tr>
                                    <td>#<?= htmlspecialchars($row['id']) ?></td>
                                    <td><strong><?= htmlspecialchars($row['alias_name'] ?? '-') ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?= strtolower($row['mode']) ?>">
                                            <?= htmlspecialchars($row['mode']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($row['action_details']) ?></td>
                                    <td>
                                        <?= $row['mode'] === 'topup' 
                                            ? 'ETB ' . number_format((float)($row['topup_amount'] ?? 0), 2) 
                                            : htmlspecialchars($row['mac_address'] ?? '-'); 
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <script>
        function syncOnlineBalance() {
            const targetUser = prompt("ባላንስ የሚገባለትን የተጠቃሚ ስም (Username) ያስገቡ:", "root");
            if (!targetUser) return;

            const rawAmount = prompt("የሚሞላውን የገንዘብ መጠን ያስገቡ (ለምሳሌ: 50000 ወይም 60000):", "50000");
            if (!rawAmount || isNaN(rawAmount) || parseFloat(rawAmount) <= 0) {
                alert("ትክክለኛ የገንዘብ መጠን አላስገቡም!");
                return;
            }

            const btn = document.getElementById('syncBtn');
            const icon = document.getElementById('syncIcon');
            const text = document.getElementById('syncText');
            const alertBox = document.getElementById('syncAlert');

            btn.disabled = true;
            icon.classList.add('fa-spin');
            text.innerText = 'Syncing...';

            const formData = new FormData();
            formData.append('username', targetUser);
            formData.append('amount', rawAmount);

            fetch('sync_now.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alertBox.className = 'sync-alert success';
                    alertBox.innerHTML = '<i class="fa-solid fa-circle-check"></i> <span>' + data.message + '</span>';
                    setTimeout(() => location.reload(), 1200);
                } else {
                    alertBox.className = 'sync-alert error';
                    alertBox.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> <span>' + data.message + '</span>';
                }
            })
            .catch(err => {
                alertBox.className = 'sync-alert error';
                alertBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> <span>የሰርቨር ምላሽ አልተገኘም! ፋይሎቹ በትክክል መጫናቸውን ያረጋግጡ።</span>';
            })
            .finally(() => {
                btn.disabled = false;
                icon.classList.remove('fa-spin');
                text.innerText = 'Sync Online';
            });
        }
    </script>
</body>
</html>
