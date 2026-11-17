<?php
$host = getenv('DB_HOST') ?: 'aws-0-eu-west-1.pooler.supabase.com';
$port = getenv('DB_PORT') ?: '6543';
$dbname = getenv('DB_NAME') ?: 'postgres';
$user = getenv('DB_USER') ?: 'postgres.zriwaxchecfffaiblchi';
$password = getenv('DB_PASS');

try {
    // options='project=...' መጨመሩ Supabase Pooler ትክክለኛውን ፕሮጀክት እንዲያገኝ ያደርገዋል
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require;options='project=zriwaxchecfffaiblchi'";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode([
        'status' => 'error', 
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]));
}
?>
