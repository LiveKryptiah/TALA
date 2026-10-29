<?php
// Test 1: Can PHP run at all?
echo "1. PHP is working.<br>";

// Test 2: Is cURL enabled?
if (function_exists('curl_init')) {
    echo "2. cURL extension is enabled.<br>";
} else {
    echo "<span style='color:red;'>2. CRITICAL ERROR: cURL extension is NOT enabled on this server!</span><br>";
}

// Test 3: Can we connect to the Database?
$host = 'sql104.infinityfree.com';
$db   = 'if0_42252073_tala'; // Put your live DB name here
$user = 'if0_42252073'; // Put your live DB user here
$pass = 'talatums3325'; // Put your live DB password here

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    echo "3. Database connection successful!<br>";
} catch (PDOException $e) {
    echo "<span style='color:red;'>3. DATABASE ERROR: " . $e->getMessage() . "</span><br>";
}
?>
