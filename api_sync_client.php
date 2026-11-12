<?php
// ፋይል ስም፡ api_sync_client.php (GitHub ላይ ይህንን አስገባው)
$db = new PDO('sqlite:' . __DIR__ . '/online_sync.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("CREATE TABLE IF NOT EXISTS topups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    reference_id TEXT UNIQUE NOT NULL,
    shop_id TEXT NOT NULL,
    amount REAL NOT NULL,
    status TEXT DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// ==========================================
// 1. Desktop App (ተቀባይ) ሲጠይቅ (GET)
// ==========================================
if (isset($_GET['shop_id'])) {
    header('Content-Type: application/json');
    $shopId = strtoupper(trim($_GET['shop_id']));

    if (empty($shopId) || strpos($shopId, 'SHOP-') !== 0) {
        echo json_encode(['has_topup' => false, 'error' => 'Shop ID ግዴታ ነው!']);
        exit;
    }

    $stmt = $db->prepare("SELECT reference_id, shop_id, amount FROM topups WHERE UPPER(shop_id) = :sid AND status = 'pending' ORDER BY id ASC LIMIT 1");
    $stmt->execute([':sid' => $shopId]);
    $topup = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($topup) {
        echo json_encode([
            'has_topup' => true,
            'shop_id' => strtoupper($topup['shop_id']),
            'reference_id' => $topup['reference_id'],
            'amount' => (float)$topup['amount']
        ]);
    } else {
        echo json_encode(['has_topup' => false, 'shop_id' => $shopId]);
    }
    exit;
}

// ==========================================
// 2. Desktop App መድረሱን ሲያረጋግጥ (POST Confirm)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm') {
    header('Content-Type: application/json');
    $refId = trim($_POST['reference_id'] ?? '');
    $shopId = strtoupper(trim($_POST['shop_id'] ?? ''));

    $stmt = $db->prepare("UPDATE topups SET status = 'completed' WHERE reference_id = :ref AND UPPER(shop_id) = :sid");
    $stmt->execute([':ref' => $refId, ':sid' => $shopId]);
    echo json_encode(['status' => 'confirmed']);
    exit;
}

// ==========================================
// 3. አንተ በብራውዘር ከፍተህ ባላንስ ስትልክ (Admin Form)
// ==========================================
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_balance'])) {
    $shopId = strtoupper(trim($_POST['target_shop_id'] ?? ''));
    $amount = (float)($_POST['amount'] ?? 0);

    if (empty($shopId) || strpos($shopId, 'SHOP-') !== 0) {
        $msg = "❌ ስህተት፡ Shop ID በ SHOP- መጀመር አለበት! (ለምሳሌ SHOP-C3113EDD)";
    } elseif ($amount <= 0) {
        $msg = "❌ ስህተት፡ ትክክለኛ የብር መጠን ያስገቡ!";
    } else {
        $refId = 'REF-' . time() . '-' . rand(100, 999);
        $ins = $db->prepare("INSERT INTO topups (reference_id, shop_id, amount, status) VALUES (:ref, :sid, :amt, 'pending')");
        $ins->execute([':ref' => $refId, ':sid' => $shopId, ':amt' => $amount]);
        $msg = "✅ ETB " . number_format($amount, 2) . " ለ " . $shopId . " በተሳካ ሁኔታ ተልኳል! አሁን ኮምፒውተሩ ላይ Sync Balance ይንኩ።";
    }
}

$history = $db->query("SELECT * FROM topups ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATD Bingo - Send Balance by Shop ID</title>
    <style>
        body { font-family: sans-serif; background: #0f172a; color: #fff; padding: 20px; display: flex; flex-direction: column; align-items: center; }
        .box { background: #1e293b; padding: 25px; border-radius: 12px; width: 100%; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
        input, button { width: 100%; padding: 12px; margin-top: 10px; border-radius: 8px; border: none; font-size: 15px; box-sizing: border-box; }
        input { background: #334155; color: #fff; }
        button { background: #22c55e; color: #fff; font-weight: bold; cursor: pointer; }
        .msg { margin-top: 15px; padding: 10px; border-radius: 8px; background: #334155; font-size: 14px; }
        table { width: 100%; max-width: 400px; margin-top: 20px; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 8px; border-bottom: 1px solid #334155; text-align: left; }
    </style>
</head>
<body>
    <div class="box">
        <h3>🏪 Send Balance (Shop ID ግዴታ ነው)</h3>
        <form method="POST">
            <input type="text" name="target_shop_id" placeholder="Shop ID ያስገቡ (ለምሳሌ SHOP-C3113EDD)" required>
            <input type="number" step="0.01" name="amount" placeholder="የብር መጠን (ETB)" required>
            <button type="submit" name="send_balance">💸 ባላንስ ላክ</button>
        </form>
        <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    </div>

    <table>
        <tr><th>Shop ID</th><th>Amount</th><th>Status</th></tr>
        <?php foreach ($history as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['shop_id']) ?></td>
            <td><?= number_format($row['amount'], 2) ?></td>
            <td><?= $row['status'] === 'completed' ? '✅ ተወስዷል' : '⏳ በመጠባበቅ ላይ' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
