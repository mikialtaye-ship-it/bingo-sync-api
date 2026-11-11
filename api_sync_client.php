[11/10/2026 5:02 PM] Adis dish: <?php
header('Content-Type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

// በሰርቨሩ ውስጥ የሚገኘው ትክክለኛ የዳታቤዝ መንገድ
$dbPath = '/var/www/html/ATDbingo.sqlite';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. የባንክ ዝውውር መቆጣጠሪያ ሰንጠረዥ (shop_id አምድ ተጨምሮበታል)
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

    // 2. የሱቆች እና የላይሰንስ ሁኔታ መቆጣጠሪያ ሰንጠረዥ
    $db->exec("CREATE TABLE IF NOT EXISTS shops (
        shop_id TEXT PRIMARY KEY,
        username TEXT NOT NULL,
        status TEXT DEFAULT 'ACTIVE', -- 'ACTIVE', 'BLOCKED', ወይም 'CANCELLED'
        last_sync DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $username = trim($_POST['username'] ?? $_GET['username'] ?? '');
    $shopId   = trim($_POST['shop_id'] ?? $_GET['shop_id'] ?? '');

    if (empty($username)) {
        echo json_encode(['status' => 'idle', 'message' => 'Service Active']);
        exit;
    }

    // 3. Shop ID ካለ ሁኔታውን መፈተሽ እና መመዝገብ
    if (!empty($shopId)) {
        $shopCheck = $db->prepare("SELECT status FROM shops WHERE shop_id = :s LIMIT 1");
        $shopCheck->execute([':s' => $shopId]);
        $shop = $shopCheck->fetch(PDO::FETCH_ASSOC);

        if (!$shop) {
            // አዲስ ሱቅ ከሆነ በ ACTIVE ሁኔታ መመዝገብ
            $addShop = $db->prepare("INSERT INTO shops (shop_id, username, status, last_sync) VALUES (:s, :u, 'ACTIVE', CURRENT_TIMESTAMP)");
            $addShop->execute([':s' => $shopId, ':u' => $username]);
        } else {
            // ሱቁ ከተሰረዘ ወይም ከታገደ ወዲያውኑ ማገድ
            if ($shop['status'] === 'BLOCKED' || $shop['status'] === 'CANCELLED') {
                echo json_encode([
                    'status' => 'blocked',
                    'is_blocked' => true,
                    'has_topup' => false,
                    'message' => 'ይህ ሲስተም/ላይሰንስ ከአድሚን ታግዷል! እባክዎ ያነጋግሩ።'
                ]);
                exit;
            }
            // ሱቁ ክፍት ከሆነ የመጨረሻ ግንኙነት ሰዓቱን ማደስ
            $updateSync = $db->prepare("UPDATE shops SET last_sync = CURRENT_TIMESTAMP WHERE shop_id = :s");
            $updateSync->execute([':s' => $shopId]);
        }
    }

    // 4. ቢንጎው ገንዘቡን ወስዶ ማረጋገጫ ሲልክ (Confirm Claim)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reference_id'])) {
        $ref = trim($_POST['reference_id']);
        
        $stmt = $db->prepare("UPDATE transactions 
                              SET status = 'CLAIMED', claimed_at = CURRENT_TIMESTAMP 
                              WHERE reference_id = :ref AND username = :u AND status = 'PENDING'");
        $stmt->execute([':ref' => $ref, ':u' => $username]);

        echo json_encode(['status' => 'success', 'message' => 'Transfer finalized']);
        exit;
    }

    // 5. ቢንጎው አዲስ ዝውውር መኖሩን ሲጠይቅ (Pending Topup Check)
    $sql = "SELECT reference_id, amount FROM transactions WHERE username = :u AND status = 'PENDING' ";
    $params = [':u' => $username];

    if (!empty($shopId)) {
        $sql .= "AND (shop_id = :s OR shop_id IS NULL OR shop_id = '') ";
        $params[':s'] = $shopId;
    }
    $sql .= "ORDER BY id ASC LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tx) {
        echo json_encode([
            'status' => 'success',
            'is_blocked' => false,
            'has_topup' => true,
            'reference_id' => $tx['reference_id'],
[11/10/2026 5:02 PM] Adis dish: 'amount' => (float)$tx['amount'],
            'new_topup' => (float)$tx['amount']
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'is_blocked' => false,
            'has_topup' => false,
            'amount' => 0.00,
            'new_topup' => 0.00
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Error: ' . $e->getMessage()]);
}
exit;
