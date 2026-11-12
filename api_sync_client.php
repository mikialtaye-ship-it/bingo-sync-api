<?php
header('Content-Type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

// dashboard.php እና generate_balance.php የሚጠቀሙት ትክክለኛው ዳታቤዝ
$dbPath = "/var/www/html/ATDbingo.sqlite";
if (!file_exists($dbPath)) {
    $dbPath = __DIR__ . '/ATDbingo.sqlite';
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. ተቀባዩ (Desktop App) ባላንሱን መቀበሉን ሲያረጋግጥ (POST Request)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reference_id'])) {
        $refId = trim($_POST['reference_id']);
        $rawShopId = strtoupper(trim($_POST['shop_id'] ?? ''));
        $cleanShopId = str_replace('SHOP-', '', $rawShopId);

        if (empty($cleanShopId)) {
            echo json_encode(['success' => false, 'message' => 'Shop ID ግዴታ ነው!']);
            exit;
        }

        // ትራንስፈሩን CLAIMED ማድረግ (ዳሽቦርዱ ላይ CLAIMED እንዲሆን)
        $stmt = $db->prepare("UPDATE transactions 
                              SET status = 'CLAIMED', claimed_at = CURRENT_TIMESTAMP 
                              WHERE reference_id = :ref 
                              AND REPLACE(UPPER(TRIM(shop_id)), 'SHOP-', '') = :sid");
        $stmt->execute([':ref' => $refId, ':sid' => $cleanShopId]);

        echo json_encode(['success' => true, 'status' => 'confirmed']);
        exit;
    }

    // 2. ተቀባዩ (Desktop App) አዲስ ባላንስ ወይም መታገዱን ሲፈትሽ (GET Request)
    $rawShopId = strtoupper(trim($_GET['shop_id'] ?? ''));
    $cleanShopId = str_replace('SHOP-', '', $rawShopId);

    // 🚨 ግዴታ ማረጋገጫ፡ Shop ID ከሌለ ጨርሶ እንዳይሰራ!
    if (empty($cleanShopId)) {
        echo json_encode([
            'has_topup' => false,
            'error' => '⛔ Shop ID ግዴታ ነው! ያለ Shop ID መስራት አይቻልም።'
        ]);
        exit;
    }

    // ሱቁ ታግዷል ወይ ብሎ ማረጋገጥ
    $blockStmt = $db->prepare("SELECT status FROM shops WHERE REPLACE(UPPER(TRIM(shop_id)), 'SHOP-', '') = :sid LIMIT 1");
    $blockStmt->execute([':sid' => $cleanShopId]);
    $shopRow = $blockStmt->fetch(PDO::FETCH_ASSOC);

    if ($shopRow && strtoupper($shopRow['status']) === 'BLOCKED') {
        echo json_encode([
            'is_blocked' => true,
            'shop_id' => 'SHOP-' . $cleanShopId
        ]);
        exit;
    }

    // በዚህ Shop ID ብቻ የተላከ PENDING ባላንስ ከ transactions ሰንጠረዥ መፈለግ
    $stmt = $db->prepare("SELECT reference_id, amount, shop_id 
                          FROM transactions 
                          WHERE REPLACE(UPPER(TRIM(shop_id)), 'SHOP-', '') = :sid 
                          AND status = 'PENDING' 
                          ORDER BY id ASC LIMIT 1");
    $stmt->execute([':sid' => $cleanShopId]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tx && (float)$tx['amount'] > 0) {
        $returnedShop = 'SHOP-' . str_replace('SHOP-', '', strtoupper(trim($tx['shop_id'])));
        echo json_encode([
            'is_blocked' => false,
            'has_topup' => true,
            'shop_id' => $returnedShop,
            'reference_id' => $tx['reference_id'],
            'amount' => (float)$tx['amount']
        ]);
    } else {
        echo json_encode([
            'is_blocked' => false,
            'has_topup' => false,
            'shop_id' => 'SHOP-' . $cleanShopId
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['has_topup' => false, 'error' => $e->getMessage()]);
}
exit;
