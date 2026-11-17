<?php
$databaseUrl = getenv('DATABASE_URL');

if ($databaseUrl) {
    $db = parse_url($databaseUrl);
    $host = $db['host'];
    $port = $db['port'] ?? '6543';
    $dbname = ltrim($db['path'], '/');
    $user = $db['user'];
    $password = $db['pass'];
} else {
    $host = getenv('DB_HOST');
    $port = getenv('DB_PORT') ?: '6543';
    $dbname = getenv('DB_NAME') ?: 'postgres';
    $user = getenv('DB_USER');
    $password = getenv('DB_PASS');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    // ትክክለኛውን ስህተት ለማየት
    die(json_encode([
        'status' => 'error', 
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]));
}
?>
