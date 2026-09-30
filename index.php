<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$dbPath = __DIR__ . '/ATDbingo.sqlite';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        name TEXT,
        balance REAL NOT NULL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    try {
        $db->exec("ALTER TABLE users ADD COLUMN last_updated DATETIME DEFAULT CURRENT_TIMESTAMP");
    } catch (PDOException $ignored) {
    }

    $rootUser = $db->prepare("SELECT id, password FROM users WHERE username = 'root' LIMIT 1");
    $rootUser->execute();
    $root = $rootUser->fetch(PDO::FETCH_ASSOC);

    if (!$root) {
        $insert = $db->prepare("INSERT INTO users (username, password, name, balance) VALUES ('root', '123456', 'Administrator', 0.00)");
        $insert->execute();
    } elseif (empty($root['password']) || (string)$root['password'] !== '123456') {
        $update = $db->prepare("UPDATE users SET password = :password, name = COALESCE(NULLIF(TRIM(name), ''), 'Administrator') WHERE username = 'root'");
        $update->execute([':password' => '123456']);
    }
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'እባክዎ የተጠቃሚ ስም እና የይለፍ ቃል ያስገቡ!';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && (string)$password === (string)($user['password'] ?? '')) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'የተሳሳተ የተጠቃሚ ስም ወይም የይለፍ ቃል!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATD Bingo | Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --bg-color: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --border-color: rgba(255, 255, 255, 0.1);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 40px 32px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .brand-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primary), #3b82f6);
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 16px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        }

        .brand-icon i {
            font-size: 26px;
            color: #ffffff;
        }

        h2 {
            color: var(--text-main);
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        p.subtitle {
            color: var(--text-muted);
            font-size: 13px;
            margin-bottom: 28px;
        }

        .error-msg {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-align: left;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group label {
            display: block;
            color: var(--text-muted);
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            font-size: 15px;
        }

        .input-wrapper input {
            width: 100%;
            padding: 12px 14px 12px 42px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            outline: none;
            color: var(--text-main);
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .input-wrapper input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
            background: rgba(15, 23, 42, 0.9);
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            border: none;
            border-radius: 8px;
            color: #ffffff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            transition: background 0.2s ease;
        }

        .btn-login:hover {
            background: var(--primary-hover);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="brand-icon">
            <i class="fa-solid fa-gamepad"></i>
        </div>
        <h2>ATD Control</h2>
        <p class="subtitle">Sign in to manage your system</p>

        <?php if (!empty($error)): ?>
            <div class="error-msg">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="input-group">
                <label>Username</label>
                <div class="input-wrapper">
                    <i class="fa-regular fa-user"></i>
                    <input type="text" name="username" placeholder="Enter username" required autocomplete="off">
                </div>
            </div>

            <div class="input-group">
                <label>Password</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <span>Sign In</span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>
    </div>

</body>
</html>
