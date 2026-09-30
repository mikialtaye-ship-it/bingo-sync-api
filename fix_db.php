<?php
$dbPath = __DIR__ . '/ATDbingo.sqlite';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // last_updated ኮለምንን ወደ users ቴብል መጨመር
    $db->exec("ALTER TABLE users ADD COLUMN last_updated DATETIME DEFAULT CURRENT_TIMESTAMP");

    echo "<h2 style='color: green;'>✅ 'last_updated' column added successfully!</h2>";
    echo "<p><a href='dashboard.php'>ወደ ዳሽቦርድ ተመለስ</a></p>";
} catch (PDOException $e) {
    echo "<h2 style='color: orange;'>ማስታወሻ: " . $e->getMessage() . "</h2>";
    echo "<p><a href='dashboard.php'>ወደ ዳሽቦርድ ተመለስ</a></p>";
}
