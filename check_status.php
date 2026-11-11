<?php
header('Content-Type: application/json');
$username = $_POST['username'] ?? '';

if (empty($username)) {
    echo json_encode(['status' => 'unknown']);
    exit();
}

$pdo = new PDO("mysql:host=localhost;dbname=የእርስዎ_ዳታቤዝ", "ዩዘርኔም", "ፓስወርድ");
$stmt = $pdo->prepare("SELECT status FROM users WHERE username = ?");
$stmt->execute([$username]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo json_encode(['status' => $row['status']]);
} else {
    echo json_encode(['status' => 'active']);
}
