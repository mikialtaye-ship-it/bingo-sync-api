 <?php
@ini_set('display_errors', '0');
header('Content-Type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

$dbPath = '/var/www/html/ATDbingo.sqlite';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Transactions ሰንጠረዥ
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

    // የቆየ ዳታቤዝ ከሆነ shop_id አምድ ካልነበረው በራስ-ሰር መጨመር
    try {
        $db->exec("ALTER TABLE transactions ADD COLUMN shop_id TEXT");
    } catch (Exception $e) {}

    // 2. የሱቆች ሰንጠረዥ
    $db->exec("CREATE TABLE IF NOT EXISTS shops (
        shop_id TEXT PRIMARY KEY,
        username TEXT NOT NULL,
        status TEXT DEFAULT 'ACTIVE',
        last_sync DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 3. የተጠቃሚዎች ላይሰንስ ሰንጠረዥ (Cancellation)
    $db->exec("CREATE TABLE IF NOT EXISTS user_licenses (
        username TEXT PRIMARY KEY,
        status TEXT DEFAULT 'ACTIVE',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $username = trim($_POST['username'] ?? $_GET['username'] ?? '');
    $shopId   = trim($_POST['shop_id'] ?? $_GET['shop_id'] ?? '');

    if (empty($username)) {
        echo json_encode(['status' => 'idle', 'message' => 'Service Active']);
        exit;
    }

    // 4. የተጠቃሚው ወይም የሱቁ እገዳ (Blocked/Cancelled Check)
    if (!empty($username)) {
        $uCheck = $db->prepare("SELECT status FROM user_licenses WHERE LOWER(username) = LOWER(:u) LIMIT 1");
        $uCheck->execute([':u' => $username]);
        $uStatus = $uCheck->fetchColumn();

        if ($uStatus === 'CANCELED' || $uStatus === 'BLOCKED') {
            echo json_encode([
                'status' => 'blocked',
                'is_blocked' => true,
                'has_topup' => false,
                'message' => 'ይህ አካውንት ከአስተዳዳሪው ታግዷል!'
            ]);
            exit;
        }
    }

    if (!empty($shopId)) {
        $sCheck = $db->prepare("SELECT status FROM shops WHERE shop_id = :s LIMIT 1");
        $sCheck->execute([':s' => $shopId]);
        $sRow = $sCheck->fetch(PDO::FETCH_ASSOC);

        if (!$sRow) {
            $ins = $db->prepare("INSERT INTO shops (shop_id, username, status, last_sync) VALUES (:s, :u, 'ACTIVE', CURRENT_TIMESTAMP)");
            $ins->execute([':s' => $shopId, ':u' => $username]);
        } else {
            if ($sRow['status'] === 'BLOCKED' || $sRow['status'] === 'CANCELLED') {
                echo json_encode([
                    'status' => 'blocked',
                    'is_blocked' => true,
                    'has_topup' => false,
                    'message' => 'ይህ ሱቅ ከአስተዳዳሪው ታግዷል!'
                ]);
                exit;
            }
            $upd = $db->prepare("UPDATE shops SET last_sync = CURRENT_TIMESTAMP, username = :u WHERE shop_id = :s");
            $upd->execute([':u' => $username, ':s' => $shopId]);
        }
    }

    // 5. ቢንጎው ብሩን ወስዶ ሲያረጋግጥ (Confirm Claim)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reference_id'])) {
        $ref = trim($_POST['reference_id']);
        $stmt = $db->prepare("UPDATE transactions SET status = 'CLAIMED', claimed_at = CURRENT_TIMESTAMP WHERE reference_id = :ref AND LOWER(username) = LOWER(:u) AND status = 'PENDING'");
        $stmt->execute([':ref' => $ref, ':u' => $username]);

        echo json_encode(['status' => 'success', 'message' => 'Transfer finalized']);
        exit;
    }

    // 6. ለተጠቃሚው አዲስ የተላከ ብር መኖሩን ቼክ ማድረግ (Topup Check)
 $stmt = $db->prepare("SELECT reference_id, amount FROM transactions 
                          WHERE LOWER(username) = LOWER(:u) AND status = 'PENDING' 
                          ORDER BY id ASC LIMIT 1");
    $stmt->execute([':u' => $username]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tx) {
        echo json_encode([
            'status' => 'success',
            'is_blocked' => false,
            'has_topup' => true,
            'reference_id' => $tx['reference_id'],
            'amount' => (float)$tx['amount'],
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
    echo json_encode([
        'status' => 'error',
        'is_blocked' => false,
        'has_topup' => false,
        'message' => $e->getMessage()
    ]);
}
exit;
