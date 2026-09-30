<?php
$dbPath = __DIR__ . "/online_balance.sqlite";
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);

    if (!empty($username) && $amount > 0) {
        try {
            $db = new PDO("sqlite:$dbPath");
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $db->prepare("INSERT INTO pending_topups (username, amount, is_claimed) VALUES (:u, :a, 0)");
            $stmt->execute([':u' => $username, ':a' => $amount]);

            $message = "✅ ለ $username ብር $amount በተሳካ ሁኔታ ተልኳል!";
        } catch (Exception $e) {
            $message = "❌ ስህተት፡ " . $e->getMessage();
        }
    } else {
        $message = "⚠️ እባክዎ ትክክለኛ ስም እና የብር መጠን ያስገቡ።";
    }
}
?>
<!DOCTYPE html>
<html lang="am">
<head>
    <meta charset="UTF-8">
    <title>ባላንስ መላኪያ (Admin)</title>
    <style>
        body { font-family: sans-serif; background: #0f172a; color: white; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .box { background: #1e293b; padding: 30px; border-radius: 12px; width: 320px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        input { width: 100%; padding: 10px; margin: 8px 0 16px; border-radius: 6px; border: 1px solid #475569; background: #0f172a; color: white; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #22c55e; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        button:hover { background: #16a34a; }
        .msg { margin-bottom: 15px; font-size: 14px; text-align: center; }
    </style>
</head>
<body>
    <div class="box">
        <h3>ባላንስ መላኪያ (Top-Up)</h3>
        <?php if ($message): ?>
            <div class="msg"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="POST">
            <label>የተጠቃሚ ስም (Username)</label>
            <input type="text" name="username" placeholder="ለምሳሌ: adis" required>

            <label>የብር መጠን (Amount)</label>
            <input type="number" step="0.01" name="amount" placeholder="ለምሳሌ: 500" required>

            <button type="submit">ባላንስ ላክ</button>
        </form>
    </div>
</body>
</html>
