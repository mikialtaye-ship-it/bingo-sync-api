<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$dbPath = DIR . '/ATDbingo.sqlite';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // የትራንዛክሽን ሰንጠረዥ (shop_id አምድ ያለው)
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

    // የተገናኙ ሱቆች እና የላይሰንስ ቁጥጥር ሰንጠረዥ
    $db->exec("CREATE TABLE IF NOT EXISTS shops (
        shop_id TEXT PRIMARY KEY,
        username TEXT NOT NULL,
        status TEXT DEFAULT 'ACTIVE',
        last_sync DATETIME DEFAULT CURRENT_TIMESTAMP
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
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

$message = '';
$statusClass = '';

// 1. የሱቅ ላይሰንስ ማገድ (BLOCK/CANCEL) ወይም መክፈት (UNBLOCK)
if (isset($_GET['action']) && isset($_GET['shop_id'])) {
    $action = $_GET['action'];
    $sId = trim($_GET['shop_id']);

    if ($action === 'block') {
        $stmt = $db->prepare("UPDATE shops SET status = 'BLOCKED' WHERE shop_id = :s");
        $stmt->execute([':s' => $sId]);
        $message = "ሱቅ {$sId} በተሳካ ሁኔታ ታግዷል (CANCELLED)! ደንበኛው ጋር ሲስተሙ ይዘጋል።";
        $statusClass = "success";
    } elseif ($action === 'unblock') {
        $stmt = $db->prepare("UPDATE shops SET status = 'ACTIVE' WHERE shop_id = :s");
        $stmt->execute([':s' => $sId]);
        $message = "ሱቅ {$sId} እገዳው ተነስቶለታል!";
        $statusClass = "success";
    }
}

// 2. ባላንስ ማስተላለፍ (TRANSFER BALANCE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUser = trim($_POST['topup_username'] ?? '');
    $shopId = trim($_POST['shop_id'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $admin = $_SESSION['username'] ?? 'root';

    if (!empty($targetUser) && $amount > 0) {
        try {
            $refId = 'TXN' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

            $stmt = $db->prepare("INSERT INTO transactions (reference_id, username, amount, shop_id, status) 
                                  VALUES (:ref, :u, :amt, :s, 'PENDING')");
            $stmt->execute([
                ':ref' => $refId,
                ':u' => $targetUser,
                ':amt' => $amount,
                ':s' => !empty($shopId) ? $shopId : null
            ]);

            $log = $db->prepare("INSERT INTO generated_licenses (username, alias_name, mode, action_details, topup_amount, created_at) 
                                 VALUES (:admin, :target, 'topup', :details, :amt, CURRENT_TIMESTAMP)");
            $log->execute([
                ':admin' => $admin,
                ':target' => $targetUser,
                ':details' => "Ref: $refId | Shop: " . (!empty($shopId) ? $shopId : 'All') . " (Online Transfer)",
                ':amt' => $amount
            ]);

            $message = "✅ ETB " . number_format($amount, 2) . " ወደ ተጠቃሚ $targetUser ተላልፏል! (Ref: $refId)";
            $statusClass = "success";
        } catch (Exception $e) {
            $message = "ስህተት፡ " . $e->getMessage();
            $statusClass = "error";
        }
    } else {
        $message = "እባክዎ ትክክለኛ ተጠቃሚ እና የብር መጠን ያስገቡ!";
        $statusClass = "error";
    }
}
[11/10/2026 5:49 PM] Adis dish: // የተመዘገቡ ሱቆችን ማውጣት
$shops = $db->query("SELECT * FROM shops ORDER BY last_sync DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATD Control | Transfer Balance & Shop Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --text-dark: #0f172a;
            --text-light: #64748b;
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
            margin: 0;
        }

        .sidebar {
            width: 250px;
            background-color: var(--sidebar-bg);
            color: #94a3b8;
            display: flex;
            flex-direction: column;
            padding: 20px;
            flex-shrink: 0;
        }

        .brand {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-link {
            color: #94a3b8;
            text-decoration: none;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 12px;
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
        }

        .nav-link.logout {
            margin-top: auto;
            color: #f87171;
        }

        .nav-link.logout:hover {
            background-color: rgba(239, 68, 68, 0.1);
        }

        .main {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
            overflow-y: auto;
        }

        .card {
            width: 100%;
            max-width: 580px;
            background: var(--card-bg);
            padding: 32px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 24px;
            text-align: center;
        }

        label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-light);
            display: block;
            margin-top: 16px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            margin-top: 8px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s;
        }

        input:focus {
            border-color: var(--primary);
        }

        button {
            width: 100%;
            padding: 14px;
            margin-top: 24px;
[11/10/2026 5:49 PM] Adis dish: background: var(--primary);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        button:hover {
            background: var(--primary-hover);
        }

        .msg {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 18px;
        }

        .msg.success {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .msg.error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        /* ሱቆች ቴብል ዲዛይን */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-top: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: #f1f5f9;
            color: var(--text-light);
            font-weight: 600;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
        }

        .badge-active {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-blocked {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-act {
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .btn-block {
            background: #ef4444;
            color: #ffffff;
        }

        .btn-block:hover {
            background: #dc2626;
        }

        .btn-unblock {
            background: #22c55e;
            color: #ffffff;
        }

        .btn-unblock:hover {
            background: #16a34a;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="brand"><i class="fa-solid fa-gamepad"></i> ATD Control</div>
        <a href="dashboard.php" class="nav-link"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
        <a href="generate_balance.php" class="nav-link active"><i class="fa-solid fa-paper-plane"></i> Transfer Balance</a>
        <a href="logout.php" class="nav-link logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="main">
        <!-- 1. ባላንስ ማስተላለፊያ ፎርም -->
        <div class="card">
            <h2><i class="fa-solid fa-paper-plane"></i> Transfer Balance to Client</h2>
            <?php if (!empty($message)): ?>
                <div class="msg <?= $statusClass ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <form method="POST">
                <label>የተጠቃሚ ስም (Client Username)</label>
                <input type="text" name="topup_username" value="adis" placeholder="Username" required>

                <label>የሱቅ መለያ (Shop ID) - አማራጭ</label>
                <input type="text" name="shop_id" placeholder="ምሳሌ፡ SHOP-XXXXXXXX (ለተወሰነ ማሽን ብቻ ከሆነ)">
                
                <label>የገንዘብ መጠን (Amount)</label>
                <input type="number" name="amount" value="500" step="0.01" min="1" placeholder="Amount in ETB" required>
                
                <button type="submit"><i class="fa-solid fa-paper-plane"></i> አስተላልፍ (Transfer)</button>
            </form>
        </div>
[11/10/2026 5:49 PM] Adis dish: <!-- 2. የተገናኙ ሱቆች እና የላይሰንስ ቁጥጥር (Cancel/Block) -->
        <div class="card">
            <h2><i class="fa-solid fa-store"></i> የተገናኙ ሱቆች እና ላይሰንስ ቁጥጥር</h2>
            <?php if (empty($shops)): ?>
                <p style="color: #64748b; font-size: 13px; text-align: center; margin-top: 10px;">እስካሁን የተገናኘ ሱቅ የለም። ደንበኛው ከዴስክቶፕ አፑ አንዴ ሲንክ ሲያደርግ በራሱ እዚህ ይመዘገባል።</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Shop ID</th>
                                <th>ተጠቃሚ</th>
                                <th>ሁኔታ</th>
                                <th>እርምጃ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($shops as $s): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($s['shop_id']) ?></strong></td>
                                    <td><?= htmlspecialchars($s['username']) ?></td>
                                    <td>
                                        <?php if ($s['status'] === 'ACTIVE'): ?>
                                            <span class="badge badge-active">ACTIVE</span>
                                        <?php else: ?>
                                            <span class="badge badge-blocked">BLOCKED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($s['status'] === 'ACTIVE'): ?>
                                            <a href="generate_balance.php?action=block&shop_id=<?= urlencode($s['shop_id']) ?>" class="btn-act btn-block" onclick="return confirm('ይህንን ሱቅ ማገድ ይፈልጋሉ? አፑ ወዲያውኑ ይዘጋል።')">አግድ (Cancel)</a>
                                        <?php else: ?>
                                            <a href="generate_balance.php?action=unblock&shop_id=<?= urlencode($s['shop_id']) ?>" class="btn-act btn-unblock">እገዳ አንሳ</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
