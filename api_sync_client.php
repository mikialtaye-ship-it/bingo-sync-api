<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// የዳታቤዝ ፋይል አቀማመጥ
$dbPath = __DIR__ . "/online_balance.sqlite";

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ተጠቃሚዎችን እና የተላከ ባላንስ የሚይዝ ቴብል መፍጠር (ከሌለ)
    $db->exec("CREATE TABLE IF NOT EXISTS pending_topups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        amount REAL NOT NULL,
        is_claimed INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database Connection Error: " . $e->getMessage()]);
    exit;
}

// 1. ባላንሱ በኮምፒውተሩ መወሰዱን ማረጋገጫ ሲደርስ (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $confirmClaim = $_POST['confirm_claim'] ?? 0;

    if (!empty($username) && $confirmClaim == 1) {
        $stmt = $db->prepare("UPDATE pending_topups SET is_claimed = 1 WHERE username = :u AND is_claimed = 0");
        $stmt->execute([':u' => $username]);

        echo json_encode(["status" => "success", "message" => "Claim confirmed successfully."]);
        exit;
    }
}

// 2. ኮምፒውተሩ አዲስ የተላከ ባላንስ ካለ ሲጠይቅ (GET Request)
$username = $_GET['username'] ?? '';

if (!empty($username)) {
    // ያልተወሰደ አዲስ ባላንስ መፈለግ
    $stmt = $db->prepare("SELECT SUM(amount) AS total_topup FROM pending_topups WHERE username = :u AND is_claimed = 0");
    $stmt->execute([':u' => $username]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $newTopup = $result['total_topup'] ? (float)$result['total_topup'] : 0.00;

    echo json_encode([
        "status" => "success",
        "username" => $username,
        "new_topup" => $newTopup
    ]);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid Request"]);
exit;
