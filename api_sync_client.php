<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

$dbPath = DIR . "/online_balance.sqlite";

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // የዝውውር ሰንጠረዥ (Transaction Ledger)
    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reference_id TEXT UNIQUE NOT NULL,
        username TEXT NOT NULL,
        amount REAL NOT NULL,
        status TEXT DEFAULT 'PENDING', -- PENDING ወይም CLAIMED
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        claimed_at DATETIME
    )");
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
}

// 1. ገንዘቡ ወደ ተጠቃሚው መግባቱን ማረጋገጥና መቆለፍ (CLAIM)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ref = $_POST['reference_id'] ?? '';
    $username = $_POST['username'] ?? '';

    if (!empty($ref)) {
        // ገቢ የተደረገውን ትራንዛክሽን ብቻ ፈልጎ ወደ CLAIMED መቀየር
        $stmt = $db->prepare("UPDATE transactions 
                              SET status = 'CLAIMED', claimed_at = CURRENT_TIMESTAMP 
                              WHERE reference_id = :ref AND status = 'PENDING'");
        $stmt->execute([':ref' => $ref]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["status" => "success", "message" => "Transaction locked successfully."]);
        } else {
            echo json_encode(["status" => "already_processed", "message" => "Transaction was already claimed."]);
        }
        exit;
    }
}

// 2. ለተጠቃሚው ያልተወሰደ አዲስ ዝውውር መፈለግ (GET)
$username = $_GET['username'] ?? '';

if (!empty($username)) {
    // ገና ያልተወሰደ የመጀመሪያውን ዝውውር ማውጣት
    $stmt = $db->prepare("SELECT reference_id, amount FROM transactions 
                          WHERE username = :u AND status = 'PENDING' 
                          ORDER BY id ASC LIMIT 1");
    $stmt->execute([':u' => $username]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tx) {
        echo json_encode([
            "status" => "success",
            "has_topup" => true,
            "reference_id" => $tx['reference_id'],
            "amount" => (float)$tx['amount']
        ]);
    } else {
        echo json_encode([
            "status" => "success",
            "has_topup" => false,
            "amount" => 0.00
        ]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid Request"]);
exit;
