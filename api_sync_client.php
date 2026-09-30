<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// የዳታቤዝ መገኛ መንገድ (DIR በትክክል ሁለት ሁለት አንደርስኮር አለው)
$dbPath = "/var/www/html/ATDbingo.sqlite";

try {
    $db = new PDO("sqlite:" . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // የባንክ ስታይል ዝውውር መቆጣጠሪያ ቴብል ማዘጋጀት
    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reference_id TEXT UNIQUE NOT NULL,
        username TEXT NOT NULL,
        amount REAL NOT NULL,
        status TEXT DEFAULT 'PENDING',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        claimed_at DATETIME
    )");
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
    exit;
}

// 1. ገንዘቡ በኮምፒውተሩ መወሰዱን ማረጋገጫ ሲደርስ (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ref = $_POST['reference_id'] ?? '';
    $username = $_POST['username'] ?? '';

    if (!empty($ref)) {
        $stmt = $db->prepare("UPDATE transactions 
                              SET status = 'CLAIMED', claimed_at = CURRENT_TIMESTAMP 
                              WHERE reference_id = :ref AND status = 'PENDING'");
        $stmt->execute([':ref' => $ref]);

        echo json_encode(["status" => "success", "message" => "Transaction claimed successfully."]);
        exit;
    }
}

// 2. ኮምፒውተሩ አዲስ የተላከ ባላንስ ሲጠይቅ (GET)
$username = $_GET['username'] ?? '';

if (!empty($username)) {
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
