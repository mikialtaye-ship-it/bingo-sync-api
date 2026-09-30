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

    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reference_id TEXT UNIQUE NOT NULL,
        username TEXT NOT NULL,
        amount REAL NOT NULL,
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
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

$message = '';
$statusClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetUser = trim($_POST['topup_username'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    $admin = $_SESSION['username'] ?? 'root';

    if (!empty($targetUser) && $amount > 0) {
        try {
            $refId = 'TXN' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

            $stmt = $db->prepare("INSERT INTO transactions (reference_id, username, amount, status) 
                                  VALUES (:ref, :u, :amt, 'PENDING')");
            $stmt->execute([
                ':ref' => $refId,
                ':u' => $targetUser,
                ':amt' => $amount
            ]);

            $log = $db->prepare("INSERT INTO generated_licenses (username, alias_name, mode, action_details, topup_amount, created_at) 
                                 VALUES (:admin, :target, 'topup', :details, :amt, CURRENT_TIMESTAMP)");
            $log->execute([
                ':admin' => $admin,
                ':target' => $targetUser,
                ':details' => "Ref: $refId (Online Transfer)",
                ':amt' => $amount
            ]);

            $message = "✅ ETB " . number_format($amount, 2) . " ወደ ተጠቃሚ $targetUser በተሳካ ሁኔታ ተላልፏል! (Ref: $refId)";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATD Control | Transfer Balance</title>
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
            justify-content: center;
            align-items: flex-start;
        }

        .card {
            width: 100%;
            max-width: 460px;
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
            background: var(--primary);
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
        <div class="card">
            <h2>Transfer Balance to Client</h2>
            <?php if (!empty($message)): ?>
                <div class="msg <?= $statusClass ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <form method="POST">
                <label>የተጠቃሚ ስም (Client Username)</label>
                <input type="text" name="topup_username" value="adis" placeholder="Username" required>
                
                <label>የገንዘብ መጠን (Amount)</label>
                <input type="number" name="amount" value="500" step="0.01" min="1" placeholder="Amount in ETB" required>
                
                <button type="submit"><i class="fa-solid fa-paper-plane"></i> አስተላልፍ (Transfer)</button>
            </form>
        </div>
    </div>
</body>
</html>

