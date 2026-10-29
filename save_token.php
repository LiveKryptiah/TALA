<?php
// save_token.php
header('Content-Type: application/json');

$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

$name = $data['name'] ?? '';
$token = $data['token'] ?? '';

if (empty($token) || empty($name)) {
    // This will now tell us exactly what was sent!
    echo json_encode([
        'success' => false, 
        'message' => 'Missing name or token',
        'debug_raw_data_received' => $raw_input,
        'debug_name_parsed' => $name,
        'debug_token_parsed' => $token
    ]);
    exit;
}

// 2. Database Connection
$host = 'sql104.infinityfree.com'; 
$dbname = 'if0_42252073_tala'; 
$username = 'if0_42252073'; 
$password = 'talatums3325';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // 3. Save to database (Updates name if token already exists)
    $stmt = $pdo->prepare("
        INSERT INTO user_tokens (name, device_token) 
        VALUES (:name, :token) 
        ON DUPLICATE KEY UPDATE name = :name
    ");
    
    $stmt->execute([
        ':name' => $name,
        ':token' => $token
    ]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>