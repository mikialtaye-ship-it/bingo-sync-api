<?php
ob_start();
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$dbPath = '/var/www/html/ATDbingo.sqlite';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS bingo_centers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        center_name TEXT NOT NULL,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        mac_address TEXT UNIQUE NOT NULL, -- አንድ ማክ አድሬስ አንድ ጊዜ ብቻ
        owner_name TEXT,
        phone TEXT,
        additional_contact TEXT,
        region TEXT,
        address TEXT,
        notes TEXT,
        initial_balance REAL DEFAULT 0,
        status TEXT DEFAULT 'ACTIVE',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS user_licenses (
        username TEXT PRIMARY KEY,
        mac_address TEXT,
        status TEXT DEFAULT 'ACTIVE',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // አምድ ካልነበረው መጨመር
    try {
        $db->exec("ALTER TABLE user_licenses ADD COLUMN mac_address TEXT");
    } catch (Exception $e) {}

} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

function encryptRaw(string $plainText, string $key): string {
    $iv = random_bytes(16);
    $cipherText = openssl_encrypt($plainText, 'AES-256-CBC', substr($key, 0, 32), OPENSSL_RAW_DATA, $iv);
    return $iv . $cipherText;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_center') {
    $center_name        = trim($_POST['center_name'] ?? '');
    $username           = trim($_POST['username'] ?? '');
    $password           = trim($_POST['password'] ?? '');
    $mac_address        = strtoupper(trim($_POST['mac_address'] ?? ''));
    $owner_name         = trim($_POST['owner_name'] ?? '');
    $phone              = trim($_POST['phone'] ?? '');
    $additional_contact = trim($_POST['additional_contact'] ?? '');
    $region             = trim($_POST['region'] ?? '');
    $address            = trim($_POST['address'] ?? '');
    $notes              = trim($_POST['notes'] ?? '');
    $initial_balance    = isset($_POST['initial_balance']) ? (float)$_POST['initial_balance'] : 0.0;

    if ($center_name === '' || $username === '' || $password === '' || $mac_address === '') {
        $message = "እባክዎ ዋና ዋና መስኮችን በሙሉ ይሙሉ!";
    } else {
        // 1. MAC Address ቀድሞ የተመዘገበ መሆኑን ቼክ ማድረግ
        $macCheck = $db->prepare("SELECT username FROM bingo_centers WHERE UPPER(mac_address) = UPPER(:mac) LIMIT 1");
        $macCheck->execute([':mac' => $mac_address]);
        $existingUserWithMac = $macCheck->fetchColumn();

        if ($existingUserWithMac) {
            $message = "ስህተት፡ ይህ MAC Address ({$mac_address}) ቀድሞ ለተጠቃሚ '{$existingUserWithMac}' ተመዝግቧል! በአንድ MAC አንድ ማሽን ብቻ ነው መመዝገብ የሚቻለው።";
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO bingo_centers 
                    (center_name, username, password, mac_address, owner_name, phone, additional_contact, region, address, notes, initial_balance, created_at) 
                    VALUES (:cn, :u, :p, :mac, :on, :ph, :add, :reg, :addr, :not, :bal, CURRENT_TIMESTAMP)");
                
                $stmt->execute([
                    ':cn'   => $center_name,
                    ':u'    => $username,
                    ':p'    => $password,
                    ':mac'  => $mac_address,
                    ':on'   => $owner_name,
                    ':ph'   => $phone,
                    ':add'  => $additional_contact,
                    ':reg'  => $region,
                    ':addr' => $address,
                    ':not'  => $notes,
                    ':bal'  => $initial_balance
                ]);

                // ካንሰል ማድረጊያው (Checking Status) ላይ ወዲያውኑ ACTIVE ሆኖ እንዲቀመጥ ማድረግ
                $licStmt = $db->prepare("INSERT OR REPLACE INTO user_licenses (username, mac_address, status, updated_at) VALUES (:u, :mac, 'ACTIVE', CURRENT_TIMESTAMP)");
                $licStmt->execute([':u' => $username, ':mac' => $mac_address]);

                // .enc ፋይል ማመንጨት
                $payload = json_encode([
                    'username'         => $username,
                    'password'         => $password,
                    'name'             => $center_name,
                    'balance'          => $initial_balance,
                    'created_at'       => date('Y-m-d H:i:s'),
                    'balance_imported' => 0,
                    'mac_address'      => $mac_address
                ], JSON_UNESCAPED_SLASHES);

                $key = 'This_secrate_key_for_encription_2026_for_user_generation';
                $raw = encryptRaw((string)$payload, $key);
                $base64 = base64_encode($raw);

                $fileName = 'user_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $username) . '.enc';
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $fileName . '"');
                header('Content-Length: ' . strlen($base64));
                echo $base64;
                exit;

            } catch (PDOException $e) {
                $message = "ስህተት፡ ይህ የተጠቃሚ ስም ቀድሞ ተመዝግቧል!";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATD Control | New Bingo Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg-color: #0b111e; --card-bg: #121a2d; --input-bg: #1a253c; --border-color: #243452; --primary: #2563eb; --primary-hover: #1d4ed8; --text-main: #ffffff; --text-sub: #8ea1c1; --step-inactive: #1c2b48; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { background-color: var(--bg-color); color: var(--text-main); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .modal-card { width: 100%; max-width: 440px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 20px; padding: 28px 24px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4); }
        .stepper { display: flex; align-items: center; justify-content: center; margin-bottom: 24px; gap: 12px; }
        .step-circle { width: 32px; height: 32px; border-radius: 50%; background: var(--step-inactive); color: var(--text-sub); display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; }
        .step-circle.active { background: var(--primary); color: #fff; }
        .step-circle.completed { background: #10b981; color: #fff; }
        .step-line { width: 25px; height: 2px; background: var(--step-inactive); }
        .step-line.completed { background: #10b981; }
        .icon-header { width: 52px; height: 52px; background: rgba(37, 99, 235, 0.15); border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; color: #38bdf8; font-size: 22px; }
        .header-title { font-size: 20px; font-weight: 700; text-align: center; margin-bottom: 6px; }
        .header-desc { font-size: 13px; color: var(--text-sub); text-align: center; margin-bottom: 22px; }
        .form-group { margin-bottom: 14px; }
        .form-row { display: flex; gap: 10px; }
        .form-row .form-group { flex: 1; }
        label { display: block; font-size: 11px; font-weight: 700; color: var(--text-sub); margin-bottom: 6px; text-transform: uppercase; }
        input, textarea { width: 100%; background: var(--input-bg); border: 1px solid var(--border-color); border-radius: 10px; padding: 12px 14px; color: #fff; font-size: 14px; outline: none; }
        input:focus, textarea:focus { border-color: var(--primary); }
        .btn-row { display: flex; gap: 12px; margin-top: 24px; align-items: center; }
        .btn-continue, .btn-confirm { flex: 2; background: var(--primary); color: white; border: none; padding: 13px; border-radius: 10px; font-weight: 600; font-size: 14px; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 8px; }
        .btn-back, .btn-cancel { flex: 1; background: transparent; color: var(--text-sub); border: none; padding: 13px; cursor: pointer; font-size: 14px; text-align: center; text-decoration: none; }
        .review-box { background: rgba(26, 37, 60, 0.6); border: 1px solid var(--border-color); border-radius: 14px; padding: 18px; margin-bottom: 20px; }
        .review-item { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 13px; }
        .review-label { color: var(--text-sub); font-size: 11px; text-transform: uppercase; font-weight: 600; }
        .review-value { font-weight: 700; color: #fff; font-family: monospace; }
        .review-divider { height: 1px; background: var(--border-color); margin: 12px 0; }
        .alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #f87171; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 18px; }
    </style>
</head>
<body>

<div class="modal-card">
    <div class="stepper">
        <div class="step-circle active" id="dot-1">1</div>
        <div class="step-line" id="line-1"></div>
        <div class="step-circle" id="dot-2">2</div>
        <div class="step-line" id="line-2"></div>
        <div class="step-circle" id="dot-3">3</div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="create_center">

        <!-- STEP 1 -->
        <div id="step-1" class="step-content">
            <div class="icon-header"><i class="fa-solid fa-store"></i></div>
            <div class="header-title">New Bingo Center</div>
            <div class="header-desc">Register a new terminal. Balance starts at zero.</div>

            <div class="form-group">
                <label>Full Name / Branch</label>
                <input type="text" name="center_name" id="center_name" placeholder="e.g. Merkato Branch" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" id="username" placeholder="e.g. merkato01" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="text" name="password" id="password" placeholder="min 6 chars" required>
                </div>
            </div>

            <div class="form-group">
                <label>MAC Address (1 MAC = 1 Center)</label>
                <input type="text" name="mac_address" id="mac_address" placeholder="00:23:18:6D:06:8C" required>
            </div>

            <div class="btn-row">
                <button type="button" class="btn-continue" onclick="validateStep1()">Continue &rarr;</button>
                <a href="dashboard.php" class="btn-cancel">Cancel</a>
            </div>
        </div>

        <!-- STEP 2 -->
        <div id="step-2" class="step-content" style="display: none;">
            <div class="icon-header"><i class="fa-regular fa-address-card"></i></div>
            <div class="header-title">Registration Info</div>
            <div class="header-desc">Owner and contact details</div>

            <div class="form-group">
                <label>Owner Name</label>
                <input type="text" name="owner_name" id="owner_name" placeholder="e.g. Ahmed Hassan">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" id="phone" placeholder="+251 9XX XXX XXX">
                </div>
                <div class="form-group">
                    <label>Additional Contact / Telegram</label>
                    <input type="text" name="additional_contact" id="additional_contact" placeholder="Alt phone, telegram">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Region / City</label>
                    <input type="text" name="region" id="region" placeholder="e.g. Addis Ababa, Oromiya">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" name="address" id="address" placeholder="Street, area...">
                </div>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="notes" rows="2" placeholder="Optional notes..."></textarea>
            </div>

            <div class="btn-row">
                <button type="button" class="btn-back" onclick="goToStep(1)">Back</button>
                <button type="button" class="btn-continue" onclick="populateReview(); goToStep(3)">Continue &rarr;</button>
            </div>
        </div>

        <!-- STEP 3 -->
        <div id="step-3" class="step-content" style="display: none;">
            <div class="icon-header"><i class="fa-solid fa-list-check"></i></div>
            <div class="header-title">Review & Confirm</div>
            <div class="header-desc">Confirm center details before creating</div>

            <div class="review-box">
                <div class="review-item"><span class="review-label">Center Name</span><span class="review-value" id="rv-center">-</span></div>
                <div class="review-item"><span class="review-label">Username</span><span class="review-value" id="rv-username">-</span></div>
                <div class="review-item"><span class="review-label">Password</span><span class="review-value" id="rv-password">-</span></div>
                <div class="review-item"><span class="review-label">MAC Address</span><span class="review-value" id="rv-mac">-</span></div>
                <div class="review-item"><span class="review-label">Owner</span><span class="review-value" id="rv-owner">-</span></div>
                <div class="review-item"><span class="review-label">Phone</span><span class="review-value" id="rv-phone">-</span></div>
                <div class="review-item"><span class="review-label">Telegram / Alt</span><span class="review-value" id="rv-alt">-</span></div>
                <div class="review-item"><span class="review-label">Region</span><span class="review-value" id="rv-region">-</span></div>
                <div class="review-item"><span class="review-label">Address</span><span class="review-value" id="rv-address">-</span></div>
                <div class="review-divider"></div>
                <div class="review-item">
                    <span class="review-label">Initial Balance</span>
                    <span class="review-value" style="color: #38bdf8;">0.00 ETB</span>
                    <input type="hidden" name="initial_balance" value="0.00">
                </div>
            </div>

            <div class="btn-row">
                <button type="button" class="btn-back" onclick="goToStep(2)">Back</button>
                <button type="submit" class="btn-confirm"><i class="fa-regular fa-circle-check"></i> Create Center</button>
            </div>
        </div>
    </form>
</div>

<script>
    function validateStep1() {
        const cn = document.getElementById('center_name').value.trim();
        const u = document.getElementById('username').value.trim();
        const p = document.getElementById('password').value.trim();
        const m = document.getElementById('mac_address').value.trim();

        if (!cn || !u || !p || !m) {
            alert('እባክዎ የመጀመሪያውን ደረጃ መረጃዎች (Center Name, Username, Password, MAC) በሙሉ ይሙሉ!');
            return;
        }
        goToStep(2);
    }

    function goToStep(step) {
        document.querySelectorAll('.step-content').forEach(el => el.style.display = 'none');
        document.getElementById('step-' + step).style.display = 'block';

        for (let i = 1; i <= 3; i++) {
            let dot = document.getElementById('dot-' + i);
            dot.className = 'step-circle';
            if (i < step) {
                dot.classList.add('completed');
                dot.innerHTML = '&#10003;';
            } else if (i === step) {
                dot.classList.add('active');
                dot.innerHTML = i;
            } else {
                dot.innerHTML = i;
            }
        }
        document.getElementById('line-1').className = step > 1 ? 'step-line completed' : 'step-line';
        document.getElementById('line-2').className = step > 2 ? 'step-line completed' : 'step-line';
    }

    function populateReview() {
        document.getElementById('rv-center').innerText   = document.getElementById('center_name').value.trim() || '-';
        document.getElementById('rv-username').innerText = document.getElementById('username').value.trim() || '-';
        document.getElementById('rv-password').innerText = document.getElementById('password').value.trim() || '-';
        document.getElementById('rv-mac').innerText      = document.getElementById('mac_address').value.trim() || '-';
        document.getElementById('rv-owner').innerText    = document.getElementById('owner_name').value.trim() || '-';
        document.getElementById('rv-phone').innerText    = document.getElementById('phone').value.trim() || '-';
        document.getElementById('rv-alt').innerText      = document.getElementById('additional_contact').value.trim() || '-';
        document.getElementById('rv-region').innerText   = document.getElementById('region').value.trim() || '-';
        document.getElementById('rv-address').innerText  = document.getElementById('address').value.trim() || '-';
    }
</script>
</body>
</html>
