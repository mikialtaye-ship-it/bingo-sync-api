 <?php
session_start();
$dbPath = '/var/www/html/ATDbingo.sqlite';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // የተጠቃሚዎች ላይሰንስ መቆጣጠሪያ ሰንጠረዥ
    $db->exec("CREATE TABLE IF NOT EXISTS user_licenses (
        username TEXT PRIMARY KEY,
        status TEXT DEFAULT 'ACTIVE', -- 'ACTIVE' ወይም 'CANCELED'
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // በሲስተሙ ውስጥ ያሉ ተጠቃሚዎችን በሙሉ (ከ generated_licenses እና transactions) በራስ-ሰር ወደ user_licenses ማስገባት
    $db->exec("INSERT OR IGNORE INTO user_licenses (username, status)
               SELECT DISTINCT username, 'ACTIVE' FROM transactions WHERE username != ''
               UNION
               SELECT DISTINCT alias_name, 'ACTIVE' FROM generated_licenses WHERE alias_name IS NOT NULL AND alias_name != ''");

} catch (Exception $e) {
    // DB Error
}

// ሀ) ዴስክቶፕ አፑ ኦንላይን ቼክ ሲያደርግ (API Check)
if (isset($_GET['username']) || isset($_POST['username'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $checkUser = trim($_POST['username'] ?? $_GET['username'] ?? '');

    $stmt = $db->prepare("SELECT status FROM user_licenses WHERE LOWER(username) = LOWER(:u) LIMIT 1");
    $stmt->execute([':u' => $checkUser]);
    $status = $stmt->fetchColumn();

    if ($status === 'CANCELED' || $status === 'BLOCKED') {
        echo json_encode([
            'status' => 'canceled',
            'is_blocked' => true,
            'message' => 'ይህ አካውንት/ላይሰንስ ከአስተዳዳሪው ታግዷል!'
        ]);
    } else {
        echo json_encode([
            'status' => 'active',
            'is_blocked' => false
        ]);
    }
    exit;
}

// ለ) አድሚን በብሮውዘር ገብቶ ተጠቃሚዎችን ሲቆጣጠር
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$message = '';
$statusClass = '';

// አዲስ ተጠቃሚ በእጅ መመዝገብ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $newUser = trim($_POST['new_username'] ?? '');
    if (!empty($newUser)) {
        $addStmt = $db->prepare("INSERT OR REPLACE INTO user_licenses (username, status, updated_at) VALUES (:u, 'ACTIVE', CURRENT_TIMESTAMP)");
        $addStmt->execute([':u' => $newUser]);
        $message = "ተጠቃሚ {$newUser} በተሳካ ሁኔታ ተመዝግቧል!";
        $statusClass = 'success';
    }
}

// ተጠቃሚን Cancel (ማገድ) ወይም Unblock ማድረግ
if (isset($_GET['action']) && isset($_GET['target'])) {
    $act = $_GET['action'];
    $target = trim($_GET['target']);

    if ($act === 'cancel') {
        $stmt = $db->prepare("UPDATE user_licenses SET status = 'CANCELED', updated_at = CURRENT_TIMESTAMP WHERE username = :u");
        $stmt->execute([':u' => $target]);
        $message = "ተጠቃሚ {$target} ታግዷል (CANCELED)! አፑ ወዲያውኑ ይዘጋል።";
        $statusClass = 'error';
    } elseif ($act === 'activate') {
        $stmt = $db->prepare("UPDATE user_licenses SET status = 'ACTIVE', updated_at = CURRENT_TIMESTAMP WHERE username = :u");
        $stmt->execute([':u' => $target]);
        $message = "ለተጠቃሚ {$target} እገዳው ተነስቷል (ACTIVE)!";
        $statusClass = 'success';
    }
}

$allUsers = $db->query("SELECT * FROM user_licenses ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATD Control | User License Cancellation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #2563eb; --sidebar-bg: #0f172a; --sidebar-hover: #1e293b; --bg-main: #f8fafc; --card-bg: #ffffff; --border: #e2e8f0; --text-dark: #0f172a; --text-light: #64748b; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
[11/10/2026 6:22 PM] Adis dish: body { display: flex; min-height: 100vh; background-color: var(--bg-main); color: var(--text-dark); margin: 0; }
        .sidebar { width: 250px; background-color: var(--sidebar-bg); color: #94a3b8; display: flex; flex-direction: column; padding: 20px; flex-shrink: 0; }
        .brand { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 30px; display: flex; align-items: center; gap: 10px; }
        .nav-link { color: #94a3b8; text-decoration: none; padding: 12px 14px; border-radius: 8px; margin-bottom: 6px; display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 500; }
        .nav-link:hover, .nav-link.active { background-color: var(--sidebar-hover); color: #fff; }
        .nav-link.active { background-color: var(--primary); }
        .nav-link.logout { margin-top: auto; color: #f87171; }
        .main { flex: 1; padding: 40px; display: flex; flex-direction: column; align-items: center; gap: 24px; overflow-y: auto; }
        .card { width: 100%; max-width: 720px; background: var(--card-bg); padding: 28px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        h2 { font-size: 19px; font-weight: 700; margin-bottom: 18px; }
        .msg { padding: 12px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
        .msg.success { background: #dcfce7; color: #15803d; }
        .msg.error { background: #fee2e2; color: #b91c1c; }
        .add-box { display: flex; gap: 10px; margin-bottom: 20px; }
        input { flex: 1; padding: 11px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; outline: none; }
        button.btn-add { padding: 11px 20px; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 10px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: #f8fafc; color: var(--text-light); }
        .badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; }
        .badge-active { background: #dcfce7; color: #15803d; }
        .badge-canceled { background: #fee2e2; color: #b91c1c; }
        .btn-act { padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; display: inline-block; }
        .btn-cancel { background: #ef4444; color: #fff; }
        .btn-active { background: #22c55e; color: #fff; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="brand"><i class="fa-solid fa-gamepad"></i> ATD Control</div>
        <a href="dashboard.php" class="nav-link"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
        <a href="generate_balance.php" class="nav-link"><i class="fa-solid fa-paper-plane"></i> Transfer Balance</a>
        <a href="check_status.php" class="nav-link active"><i class="fa-solid fa-ban"></i> Checking Status</a>
        <a href="logout.php" class="nav-link logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="main">
        <div class="card">
            <h2><i class="fa-solid fa-ban" style="color: #ef4444;"></i> የተጠቃሚዎች ላይሰንስ መቆጣጠሪያ (Cancel User License)</h2>
            
            <?php if (!empty($message)): ?>
                <div class="msg <?= $statusClass ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" class="add-box">
                <input type="text" name="new_username" placeholder="አዲስ ተጠቃሚ ስም አስገባ (ለምሳሌ፡ mafu, yabu...)" required>
                <button type="submit" name="add_user" class="btn-add"><i class="fa-solid fa-plus"></i> መዝግብ</button>
            </form>

            <table>
                <thead>
 <tr>
                        <th>ተጠቃሚ ስም (Username)</th>
                        <th>ሁኔታ (License Status)</th>
                        <th>እርምጃ (Action)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allUsers)): ?>
                        <tr><td colspan="3" style="text-align: center; color: #94a3b8; padding: 20px;">ምንም ተጠቃሚ አልተገኘም።</td></tr>
                    <?php else: ?>
                        <?php foreach ($allUsers as $u): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                <td>
                                    <?php if ($u['status'] === 'ACTIVE'): ?>
                                        <span class="badge badge-active">ACTIVE (ክፍት)</span>
                                    <?php else: ?>
                                        <span class="badge badge-canceled">CANCELED (የታገደ)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['status'] === 'ACTIVE'): ?>
                                        <a href="check_status.php?action=cancel&target=<?= urlencode($u['username']) ?>" class="btn-act btn-cancel" onclick="return confirm('እርግጠኛ ነህ ተጠቃሚ <?= htmlspecialchars($u['username']) ?> እንዳይሠራ Cancel/ማገድ ትፈልጋለህ?')"><i class="fa-solid fa-ban"></i> Cancel (አግድ)</a>
                                    <?php else: ?>
                                        <a href="check_status.php?action=activate&target=<?= urlencode($u['username']) ?>" class="btn-act btn-active"><i class="fa-solid fa-check"></i> እገዳ አንሳ (Activate)</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
