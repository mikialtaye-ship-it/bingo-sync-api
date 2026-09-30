<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized! Please login first.']);
    exit;
}

$dbPath = __DIR__ . '/ATDbingo.sqlite';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // POST Request መሆኑን ማረጋገጥ
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $currentUser = $_SESSION['username'] ?? 'root';

        if (empty($username) || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'እባክዎ ትክክለኛ ተጠቃሚ እና የገንዘብ መጠን ያስገቡ!']);
            exit;
        }

        // ተጠቃሚው መኖሩን ማረጋገጥ፤ ከሌለ አዲስ መፍጠር
        $checkUser = $db->prepare("SELECT id, balance FROM users WHERE username = :u");
        $checkUser->execute([':u' => $username]);
        $user = $checkUser->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // last_updated ተወግዷል
            $update = $db->prepare("UPDATE users SET balance = balance + :amt WHERE username = :u");
            $update->execute([':amt' => $amount, ':u' => $username]);
        } else {
            $insert = $db->prepare("INSERT INTO users (username, password, name, balance) VALUES (:u, '123456', :name, :amt)");
            $insert->execute([':u' => $username, ':name' => $username, ':amt' => $amount]);
        }

        // የተጠቃሚውን አዲሱን ባላንስ ማውጣት
        $stmtBal = $db->prepare("SELECT balance FROM users WHERE username = :u");
        $stmtBal->execute([':u' => $username]);
        $newBalance = (float)$stmtBal->fetchColumn();

        // በ generated_licenses ውስጥ Top-Up Activity መመዝገብ
        $logStmt = $db->prepare("INSERT INTO generated_licenses (username, alias_name, mode, action_details, topup_amount, created_at) 
                                 VALUES (:admin, :target, 'topup', 'Online Synced Balance', :amt, CURRENT_TIMESTAMP)");
        $logStmt->execute([
            ':admin' => $currentUser,
            ':target' => $username,
            ':amt' => $amount
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'ETB ' . number_format($amount, 2) . ' ለተጠቃሚ ' . $username . ' በተሳካ ሁኔታ ተሞልቷል!',
            'balance' => $newBalance
        ]);
        exit;
    }

    // መደበኛ ቼክ (GET ከሆነ)
    $stmt = $db->query("SELECT SUM(balance) FROM users");
    $total = (float)$stmt->fetchColumn();
    echo json_encode(['success' => true, 'balance' => $total, 'message' => 'System connected']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
exit;
