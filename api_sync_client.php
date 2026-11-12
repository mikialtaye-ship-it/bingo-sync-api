<?php
header('Content-Type: application/json');
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

// 1. አድሚን ፓነል ባላንስ ሲልክ (ወይም ተቀባይ ሲያረጋግጥ)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'send_topup';

    // ተቀባይ መድረሱን ሲያረጋግጥ
    if ($action === 'confirm') {
        $refId = trim($_POST['reference_id'] ?? '');
        $shopId = strtoupper(trim($_POST['shop_id'] ?? ''));

        if (empty($refId) || empty($shopId)) {
            echo json_encode(['status' => 'error', 'message' => 'Shop ID እና Reference ID ግዴታ ናቸው!']);
            exit;
        }

        $stmt = $db->prepare("UPDATE topups SET status = 'completed' WHERE reference_id = :ref AND UPPER(shop_id) = :sid");
        $stmt->execute([':ref' => $refId, ':sid' => $shopId]);
        echo json_encode(['status' => 'confirmed']);
        exit;
    }

    // አድሚን አዲስ ባላንስ ሲልክ
    if ($action === 'send_topup') {
        $shopId = strtoupper(trim($_POST['shop_id'] ?? ''));
        $amount = (float)($_POST['amount'] ?? 0);

        // 🚨 ግዴታ ማረጋገጫ፡ Shop ID ከሌለ ባላንስ መላክ ክልክል ነው!
        if (empty($shopId) || strpos($shopId, 'SHOP-') !== 0) {
            echo json_encode(['status' => 'error', 'message' => '❌ ግዴታ ትክክለኛ Shop ID (ለምሳሌ SHOP-XXXXXXXX) ማስገባት አለብዎት!']);
            exit;
        }
        if ($amount <= 0) {
            echo json_encode(['status' => 'error', 'message' => '❌ ትክክለኛ የብር መጠን ያስገቡ!']);
            exit;
        }

        $refId = 'REF-' . time() . '-' . rand(100, 999);
        $ins = $db->prepare("INSERT INTO topups (reference_id, shop_id, amount, status) VALUES (:ref, :sid, :amt, 'pending')");
        $ins->execute([':ref' => $refId, ':sid' => $shopId, ':amt' => $amount]);

        echo json_encode(['status' => 'success', 'message' => "✅ ETB $amount ለ $shopId ተልኳል!"]);
        exit;
    }
}

// 2. ተቀባይ (Desktop App) ባላንስ ሲፈትሽ (GET)
$shopId = strtoupper(trim($_GET['shop_id'] ?? ''));

// 🚨 Shop ID ሳይልክ ከጠየቀ ምንም እንዳይሰጠው
if (empty($shopId) || strpos($shopId, 'SHOP-') !== 0) {
    echo json_encode(['has_topup' => false, 'error' => 'Shop ID is strictly required!']);
    exit;
}

// በዚህ Shop ID ብቻ የተላከ pending ባላንስ መፈለግ
$stmt = $db->prepare("SELECT reference_id, shop_id, amount FROM topups WHERE UPPER(shop_id) = :sid AND status = 'pending' ORDER BY id ASC LIMIT 1");
$stmt->execute([':sid' => $shopId]);
$topup = $stmt->fetch(PDO::FETCH_ASSOC);

if ($topup) {
    echo json_encode([
        'has_topup' => true,
        'shop_id' => strtoupper($topup['shop_id']), // ተቀባዩ እንዲያመሳክረው የግድ ይላካል
        'reference_id' => $topup['reference_id'],
        'amount' => (float)$topup['amount']
    ]);
} else {
    echo json_encode([
        'has_topup' => false,
        'shop_id' => $shopId
    ]);
}
