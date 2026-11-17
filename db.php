<?php
$host = 'aws-0-eu-west-1.pooler.supabase.com';
$port = '5432';
$dbname = 'postgres';
$user = 'postgres.zriwaxchecfffaiblchi';
$password = 'AtdBingo2026#';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
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
