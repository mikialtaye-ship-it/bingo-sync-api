<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$dbPath = "/var/www/html/ATDbingo.sqlite";
$message = '';
$statusClass = '';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. የተመዘገቡ ተጠቃሚዎችና ማዕከላት ሙሉ መረጃ ሰንጠረዥ
    $db->exec("CREATE TABLE IF NOT EXISTS bingo_centers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        center_name TEXT NOT NULL,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        mac_address TEXT NOT NULL,
        owner_name TEXT,
        phone TEXT,
        additional_contact TEXT,
        region TEXT,
        address TEXT,
        notes TEXT,
        initial_balance REAL DEFAULT 0,
        status TEXT DEFAULT 'ACTIVE',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reference_id TEXT UNIQUE NOT NULL,
        username TEXT NOT NULL,
        amount REAL NOT NULL,
        shop_id TEXT,
        status TEXT DEFAULT 'PENDING',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        claimed_at DATETIME
    )");

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

    $db->exec("CREATE TABLE IF NOT EXISTS shops (
        shop_id TEXT PRIMARY KEY,
        username TEXT NOT NULL,
        status TEXT DEFAULT 'ACTIVE',
        last_sync DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS user_licenses (
        username TEXT PRIMARY KEY,
        status TEXT DEFAULT 'ACTIVE',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. የድሮ ዝውውሮችን እና ታሪኮችን በሙሉ ማጽጃ (Reset Transfer History)
    if (isset($_POST['reset_transactions'])) {
        $db->exec("DELETE FROM transactions");
        $db->exec("DELETE FROM generated_licenses");
        $message = "የቆዩ የትራንስፈር መረጃዎች በሙሉ በተሳካ ሁኔታ ተሰርዘዋል! ዳታቤዙ እንደ አዲስ ጀምሯል።";
        $statusClass = 'success';
    }

    // 3. ሱቅ ማገድ ወይም እገዳ ማንሳት
    if (isset($_GET['action']) && isset($_GET['shop_id'])) {
        $action = $_GET['action'];
        $sId = trim($_GET['shop_id']);

        if ($action === 'block') {
            $stmt = $db->prepare("UPDATE shops SET status = 'BLOCKED' WHERE shop_id = :s");
            $stmt->execute([':s' => $sId]);
        } elseif ($action === 'unblock') {
            $stmt = $db->prepare("UPDATE shops SET status = 'ACTIVE' WHERE shop_id = :s");
            $stmt->execute([':s' => $sId]);
        }
        header("Location: dashboard.php");
        exit();
    }

    // ስታትስቲክስ
    $totalTransferred = (float)$db->query("SELECT SUM(amount) FROM transactions WHERE status = 'CLAIMED'")->fetchColumn();
    $pendingCount = (int)$db->query("SELECT COUNT(*) FROM transactions WHERE status = 'PENDING'")->fetchColumn();
    $centersCount = (int)$db->query("SELECT COUNT(*) FROM bingo_centers")->fetchColumn();

    // መረጃዎችን ማውጣት
    $centers = $db->query("SELECT * FROM bingo_centers ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $recentTransfers = $db->query("SELECT * FROM transactions ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATD Control | Master Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --sidebar-bg: #0f172a;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --text-dark: #0f172a;
            --text-light: #64748b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { display: flex; min-height: 100vh; background-color: var(--bg-main); color: var(--text-dark); }
        .sidebar { width: 250px; background-color: var(--sidebar-bg); color: #94a3b8; display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .brand { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
        .nav-link { color: #94a3b8; text-decoration: none; padding: 12px; border-radius: 8px; margin-bottom: 5px; display: flex; align-items: center; gap: 10px; font-size: 14px; }
        .nav-link.active, .nav-link:hover { background-color: #1e293b; color: #fff; }
        .nav-link.active { background-color: var(--primary); }
        .nav-link.logout { margin-top: auto; color: #f87171; }
        
        .main { flex: 1; padding: 30px; overflow-y: auto; }
        .header-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: gap: 15px; }
        
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .metric-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .metric-card h4 { font-size: 12px; color: var(--text-light); text-transform: uppercase; margin: 0; }
        .metric-card p { font-size: 24px; font-weight: 700; margin: 8px 0 0 0; }

        .table-card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid var(--border); font-weight: 600; font-size: 16px; display: flex; justify-content: space-between; align-items: center; }
        
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 14px 18px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: #f8fafc; color: var(--text-light); font-weight: 600; }
        
        .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-warning { background: #fef9c3; color: #a16207; }

        .btn-reset { background: #ef4444; color: #fff; border: none; padding: 8px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .btn-reset:hover { background: #dc2626; }
        .btn-view { background: #0284c7; color: white; border: none; padding: 5px 10px; border-radius: 6px; font-size: 11px; cursor: pointer; }

        .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }

        /* Modal Details */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); justify-content: center; align-items: center; z-index: 100; }
        .modal-box { background: white; border-radius: 12px; max-width: 500px; width: 90%; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .modal-box h3 { margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; font-size: 17px; }
        .detail-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
        .detail-row span:first-child { color: #64748b; font-weight: 600; }
        .close-btn { width: 100%; margin-top: 20px; background: #0f172a; color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="brand"><i class="fa-solid fa-gamepad"></i> ATD Control</div>
        <a href="dashboard.php" class="nav-link active"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
        <a href="generate_user.php" class="nav-link"><i class="fa-solid fa-user-plus"></i> New Center (User)</a>
        <a href="generate_balance.php" class="nav-link"><i class="fa-solid fa-paper-plane"></i> Transfer Balance</a>
        <a href="check_status.php" class="nav-link"><i class="fa-solid fa-shield-halved"></i> Checking Status</a>
        <a href="logout.php" class="nav-link logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="main">
        <div class="header-bar">
            <h2>System Overview</h2>
            <!-- ዳታቤዝ ማጽጃ ፎርም -->
            <form method="POST" onsubmit="return confirm('እርግጠኛ ነህ? የነበሩትን የትራንስፈር መረጃዎች በሙሉ አጥፍቶ ዳታቤዙን እንደ አዲስ ያስጀምረዋል!')">
                <button type="submit" name="reset_transactions" class="btn-reset"><i class="fa-solid fa-trash-can"></i> የቆዩ ትራንስፈሮችን አጥፋ (Reset History)</button>
            </form>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $statusClass ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="metrics-grid">
            <div class="metric-card">
                <h4>Total Transferred</h4>
                <p>ETB <?= number_format($totalTransferred, 2) ?></p>
            </div>
            <div class="metric-card">
                <h4>Pending Transfers</h4>
                <p><?= (int)$pendingCount ?></p>
            </div>
            <div class="metric-card">
                <h4>Registered Centers</h4>
                <p style="color: #2563eb;"><?= (int)$centersCount ?></p>
            </div>
        </div>

        <!-- 1. የተመዘገቡ ሰዎችና ማዕከላት ዝርዝር (ከነ ስልክ እና ቴሌግራም አድራሻቸው) -->
        <div class="table-card">
            <div class="card-header">
                <span><i class="fa-solid fa-address-book" style="color: #2563eb;"></i> የተመዘገቡ ተጠቃሚዎችና ማዕከላት (Registered Centers)</span>
                <a href="generate_user.php" style="font-size: 13px; color: #2563eb; text-decoration: none; font-weight: 600;"><i class="fa-solid fa-plus"></i> አዲስ ጨምር</a>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Center Name</th>
                            <th>Username</th>
                            <th>Phone</th>
                            <th>Telegram / Alt Contact</th>
                            <th>Region / Address</th>
                            <th>MAC Address</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($centers)): ?>
                            <tr><td colspan="7" style="text-align: center; color: #94a3b8; padding: 20px;">እስካሁን የተመዘገበ ተጠቃሚ የለም። «New Center (User)» የሚለውን ተጭነው ይመዝግቡ።</td></tr>
                        <?php else: ?>
                            <?php foreach ($centers as $c): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($c['center_name']) ?></strong></td>
                                    <td><code><?= htmlspecialchars($c['username']) ?></code></td>
                                    <td><?= htmlspecialchars($c['phone'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($c['additional_contact'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars(($c['region'] ? $c['region'] . ', ' : '') . ($c['address'] ?: '-')) ?></td>
                                    <td><small><?= htmlspecialchars($c['mac_address']) ?></small></td>
                                    <td>
                                        <button class="btn-view" onclick='showDetails(<?= json_encode($c) ?>)'><i class="fa-solid fa-eye"></i> View</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. የቅርብ ጊዜ የገንዘብ ዝውውሮች -->
        <div class="table-card">
            <div class="card-header">
                <span><i class="fa-solid fa-clock-rotate-left"></i> የቅርብ ጊዜ ትራንስፈሮች (Recent Transfers)</span>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Ref ID</th>
                            <th>Client Username</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentTransfers)): ?>
                            <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 20px;">ምንም የትራንስፈር እንቅስቃሴ የለም። ዳታቤዙ ንጹህ ነው።</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentTransfers as $tx): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($tx['reference_id']) ?></code></td>
                                    <td><strong><?= htmlspecialchars($tx['username']) ?></strong></td>
                                    <td>
                                        <?php if ($tx['status'] === 'CLAIMED'): ?>
                                            <span class="badge badge-success">CLAIMED</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">PENDING</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong>ETB <?= number_format($tx['amount'], 2) ?></strong></td>
                                    <td><?= htmlspecialchars($tx['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- የደንበኛ ዝርዝር መረጃ ፖፕ-አፕ (Detail Modal) -->
    <div id="detailModal" class="modal">
        <div class="modal-box">
            <h3><i class="fa-solid fa-id-card"></i> የደንበኛ ዝርዝር መረጃ</h3>
            <div class="detail-row"><span>Center Name:</span><span id="m_center"></span></div>
            <div class="detail-row"><span>Username:</span><span id="m_user"></span></div>
            <div class="detail-row"><span>Password:</span><span id="m_pass"></span></div>
            <div class="detail-row"><span>Owner Name:</span><span id="m_owner"></span></div>
            <div class="detail-row"><span>Phone Number:</span><span id="m_phone"></span></div>
            <div class="detail-row"><span>Telegram / Alt Contact:</span><span id="m_alt"></span></div>
            <div class="detail-row"><span>Region / City:</span><span id="m_region"></span></div>
            <div class="detail-row"><span>Address:</span><span id="m_address"></span></div>
            <div class="detail-row"><span>MAC Address:</span><span id="m_mac"></span></div>
            <div class="detail-row"><span>Notes:</span><span id="m_notes"></span></div>
            <div class="detail-row"><span>Registered Date:</span><span id="m_date"></span></div>
            <button class="close-btn" onclick="document.getElementById('detailModal').style.display='none'">ዝጋ (Close)</button>
        </div>
    </div>

    <script>
        function showDetails(data) {
            document.getElementById('m_center').innerText = data.center_name || '-';
            document.getElementById('m_user').innerText = data.username || '-';
            document.getElementById('m_pass').innerText = data.password || '-';
            document.getElementById('m_owner').innerText = data.owner_name || '-';
            document.getElementById('m_phone').innerText = data.phone || '-';
            document.getElementById('m_alt').innerText = data.additional_contact || '-';
            document.getElementById('m_region').innerText = data.region || '-';
            document.getElementById('m_address').innerText = data.address || '-';
            document.getElementById('m_mac').innerText = data.mac_address || '-';
            document.getElementById('m_notes').innerText = data.notes || '-';
            document.getElementById('m_date').innerText = data.created_at || '-';
            document.getElementById('detailModal').style.display = 'flex';
        }
    </script>
</body>
</html>
