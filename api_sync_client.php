<?php
header('Content-Type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

$dbPath = __DIR__ . '/ATDbingo.sqlite';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ተጠቃሚው የላከውን መረጃ መቀበል
    $username = trim($_POST['username'] ?? $_GET['username'] ?? '');
    $clientBalance = isset($_POST['client_balance']) ? (float)$_POST['client_balance'] : null;

    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Username required']);
        exit;
    }

    // 1. ተጠቃሚው ሎካል ያለውን ባላንስ ከላከ፣ ኦንላይን ዳታቤዙን በዛ ባላንስ አዘምን (last_updated ተወግዷል)
    if ($clientBalance !== null) {
        $update = $db->prepare("UPDATE users SET balance = :bal WHERE username = :u");
        $update->execute([':bal' => $clientBalance, ':u' => $username]);

        // በዳሽቦርዱ Recent Activity ላይ እንዲታይ መመዝገብ
        $log = $db->prepare("INSERT INTO generated_licenses (username, alias_name, mode, action_details, topup_amount, created_at) 
                             VALUES (:u, :alias, 'client_sync', 'Live Balance Reported', :bal, CURRENT_TIMESTAMP)");
        $log->execute([
            ':u' => $username,
            ':alias' => $username,
            ':bal' => $clientBalance
        ]);
    }

    // 2. በኦንላይኑ ዳታቤዝ ያለውን ወቅታዊ ባላንስ ለተጠቃሚው መልሶ መላክ
    $stmt = $db->prepare("SELECT balance FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'username' => $username,
        'server_balance' => $userRow ? (float)$userRow['balance'] : 0.00,
        'message' => 'Synced successfully'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
exit;
