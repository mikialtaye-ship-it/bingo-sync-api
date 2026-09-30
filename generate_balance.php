<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$dbHost = 'sqlXXX.infinityfree.com';
$dbName = 'if0_xxxxxxx_atd_bingo_db';
$dbUser = 'if0_xxxxxxx';
$dbPass = 'your_db_password_here';

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3
    ]);
} catch (Throwable $e) {
    // MySQL ባይገናኝም ገጹ መከፈቱን እንዲቀጥል ይፈቅዳል
}

function encryptRaw(string $plainText, string $key): string
{
    $iv = random_bytes(16);
    $cipherText = openssl_encrypt(
        $plainText,
        'AES-256-CBC',
        substr($key, 0, 32),
        OPENSSL_RAW_DATA,
        $iv
    );

    if ($cipherText === false) {
        throw new RuntimeException('Encryption failed.');
    }

    return $iv . $cipherText;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $mode = $_POST['mode'] ?? 'topup';
        $currentUser = $_SESSION['username'] ?? 'root';

        if ($mode === 'topup') {
            $username = trim($_POST['topup_username'] ?? '');
            $amount = trim($_POST['amount'] ?? '');

            if ($username === '' || !is_numeric($amount) || (float)$amount <= 0) {
                throw new InvalidArgumentException('Please enter a valid username and amount.');
            }

            $cleanUsername = preg_replace('/\s+/', ' ', trim($username));
            $cleanUsername = preg_replace('/^top\s+up\s+/i', '', $cleanUsername);

            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, balance, last_updated) 
                                           VALUES (?, ?, NOW()) 
                                           ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance), last_updated = NOW()");
                    $stmt->execute([$cleanUsername, (float)$amount]);

                    $logStmt = $pdo->prepare("INSERT INTO generated_licenses (username, alias_name, mode, action_details, topup_amount) 
                                              VALUES (?, ?, 'topup', ?, ?)");
                    $logStmt->execute([$currentUser, $cleanUsername, "Top-Up Online", (float)$amount]);
                } catch (Throwable $dbEx) {}
            }

            $displayUsername = 'top up ' . $cleanUsername;
            $key = 'This_secrate_key_for_encription_2026';
            $raw = encryptRaw((string)$amount, $key);

            $filename = 'topup_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $displayUsername) . '.enc';
            
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($raw));
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $raw;
            exit;
        }

        if ($mode === 'user') {
            $username = trim($_POST['user_username'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $balance = trim($_POST['balance'] ?? '0');
            $mac = strtoupper(trim($_POST['mac_address'] ?? ''));

            if ($username === '' || $password === '' || $name === '' || $mac === '') {
                throw new InvalidArgumentException('Please fill in all user fields correctly.');
            }

            if ($pdo) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, name, balance, mac_address) 
                                           VALUES (?, ?, ?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE name = VALUES(name), balance = VALUES(balance)");
                    $stmt->execute([$username, $password, $name, (float)$balance, $mac]);
                } catch (Throwable $dbEx) {}
            }

            $payload = json_encode([
                'username' => $username,
                'password' => $password,
                'name' => $name,
                'balance' => (float)$balance,
                'created_at' => date('Y-m-d H:i:s'),
                'balance_imported' => 0,
                'mac_address' => $mac
            ], JSON_UNESCAPED_SLASHES);

            $key = 'This_secrate_key_for_encription_2026_for_user_generation';
            $raw = encryptRaw((string)$payload, $key);
            $base64 = base64_encode($raw);

            $fileName = 'user_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $username) . '.enc';
            
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . strlen($base64));
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $base64;
            exit;
        }

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATD Bingo | Generate Balance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --sidebar-bg: #0f172a;
            --sidebar-text: #94a3b8;
            --sidebar-hover: #1e293b;
            --bg-main: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #0f172a;
            --text-light: #64748b;
            --border-color: #e2e8f0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { display: flex; min-height: 100vh; background-color: var(--bg-main); color: var(--text-dark); }
        .sidebar { width: 260px; background-color: var(--sidebar-bg); color: var(--sidebar-text); display: flex; flex-direction: column; padding: 24px 16px; flex-shrink: 0; }
        .brand { display: flex; align-items: center; gap: 12px; padding: 0 12px 24px; border-bottom: 1px solid #1e293b; color: #ffffff; font-size: 18px; font-weight: 700; }
        .brand i { color: var(--primary); font-size: 22px; }
        .nav-links { display: flex; flex-direction: column; gap: 6px; margin-top: 24px; flex: 1; }
        .nav-link { display: flex; align-items: center; gap: 14px; padding: 12px 14px; border-radius: 8px; text-decoration: none; color: var(--sidebar-text); font-size: 14px; font-weight: 500; }
        .nav-link:hover, .nav-link.active { background-color: var(--primary); color: #ffffff; }
        .nav-link.logout { color: #f87171; margin-top: auto; }
        .main-wrapper { flex: 1; display: flex; flex-direction: column; min-width: 0; overflow-y: auto; }
        .topbar { background: #ffffff; border-bottom: 1px solid var(--border-color); padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; }
        .content { padding: 32px; display: flex; justify-content: center; }
        .card { width: 100%; max-width: 500px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .card h2 { font-size: 20px; font-weight: 700; margin-bottom: 16px; text-align: center; }
        .tab { display: flex; gap: 8px; margin-bottom: 16px; }
        .tab button { flex: 1; padding: 10px; border: none; border-radius: 8px; background: #e2e8f0; color: #1e293b; font-weight: 600; cursor: pointer; }
        .tab button.active { background: var(--primary); color: white; }
        input, button.btn-submit { width: 100%; box-sizing: border-box; margin-top: 12px; padding: 12px 14px; border-radius: 8px; font-size: 14px; }
        input { border: 1px solid var(--border-color); outline: none; }
        input:focus { border-color: var(--primary); }
        button.btn-submit { border: none; background: var(--primary); color: white; font-weight: 600; cursor: pointer; }
        .error { margin-top: 12px; padding: 10px; border-radius: 8px; background: #fee2e2; color: #b91c1c; font-size: 13px; }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="brand">
            <i class="fa-solid fa-gamepad"></i>
            <span>ATD Bingo Control</span>
        </div>
        <nav class="nav-links">
            <a href="dashboard.php" class="nav-link">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Dashboard</span>
            </a>
            <a href="generate_balance.php" class="nav-link active">
                <i class="fa-solid fa-bolt"></i>
                <span>Generate Balance</span>
            </a>
            <a href="logout.php" class="nav-link logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <div class="main-wrapper">
        <header class="topbar">
            <div><strong>Balance Generator</strong></div>
            <div><span><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span></div>
        </header>

        <main class="content">
            <div class="card">
                <h2>ATD Balance & License Control</h2>

                <div class="tab">
                    <button type="button" class="active" onclick="switchMode('topup')">Top-up</button>
                    <button type="button" onclick="switchMode('user')">User</button>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="generate_balance.php">
                    <input type="hidden" name="mode" id="mode" value="topup">

                    <div id="topupFields">
                        <input type="text" name="topup_username" value="Mafu" placeholder="Username" required>
                        <input type="number" name="amount" value="20000" step="0.01" min="0.01" placeholder="Amount" required>
                    </div>

                    <div id="userFields" style="display:none;">
                        <input type="text" name="user_username" placeholder="Username">
                        <input type="text" name="password" placeholder="Password" value="123456">
                        <input type="text" name="name" placeholder="Full Name">
                        <input type="number" name="balance" value="0" placeholder="Starting Balance">
                        <input type="text" name="mac_address" placeholder="Device MAC (e.g. AA:BB:CC:DD:EE:FF)">
                    </div>

                    <button type="submit" class="btn-submit">Generate .enc File</button>
                </form>
            </div>
        </main>
    </div>

    <script>
        function switchMode(mode) {
            document.getElementById('mode').value = mode;
            const buttons = document.querySelectorAll('.tab button');
            if (mode === 'topup') {
                buttons[0].classList.add('active');
                buttons[1].classList.remove('active');
                document.getElementById('topupFields').style.display = 'block';
                document.getElementById('userFields').style.display = 'none';
            } else {
                buttons[1].classList.add('active');
                buttons[0].classList.remove('active');
                document.getElementById('topupFields').style.display = 'none';
                document.getElementById('userFields').style.display = 'block';
            }
        }
    </script>
</body>
</html>
