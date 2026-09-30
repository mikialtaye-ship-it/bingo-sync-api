<?php
header('Content-Type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

// በሰርቨሩ ውስጥ የሚገኘው ትክክለኛ የዳታቤዝ መንገድ
$dbPath = '/var/www/html/ATDbingo.sqlite';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // የባንክ ዝውውር መቆጣጠሪያ ሰንጠረዥ (Transaction Ledger)
    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reference_id TEXT UNIQUE NOT NULL,
        username TEXT NOT NULL,
        amount REAL NOT NULL,
        status TEXT DEFAULT 'PENDING',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        claimed_at DATETIME
    )");

    $username = trim($_POST['username'] ?? $_GET['username'] ?? '');

    if (empty($username)) {
        echo json_encode(['status' => 'idle', 'message' => 'Service Active']);
        exit;
    }

    // 1. ቢንጎው ገንዘቡን ወስዶ ማረጋገጫ ሲልክ (Confirm Claim)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reference_id'])) {
        $ref = trim($_POST['reference_id']);
        
        $stmt = $db->prepare("UPDATE transactions 
                              SET status = 'CLAIMED', claimed_at = CURRENT_TIMESTAMP 
                              WHERE reference_id = :ref AND username = :u AND status = 'PENDING'");
        $stmt->execute([':ref' => $ref, ':u' => $username]);

        echo json_encode(['status' => 'success', 'message' => 'Transfer finalized']);
        exit;
    }

    // 2. ቢንጎው አዲስ ዝውውር መኖሩን ሲጠይቅ (Pending Topup Check)
    $stmt = $db->prepare("SELECT reference_id, amount FROM transactions 
                          WHERE username = :u AND status = 'PENDING' 
                          ORDER BY id ASC LIMIT 1");
    $stmt->execute([':u' => $username]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tx) {
        echo json_encode([
            'status' => 'success',
            'has_topup' => true,
            'reference_id' => $tx['reference_id'],
            'amount' => (float)$tx['amount'],
            'new_topup' => (float)$tx['amount']
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'has_topup' => false,
            'amount' => 0.00,
            'new_topup' => 0.00
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Error']);
}
exit;
