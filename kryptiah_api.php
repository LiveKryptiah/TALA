<?php
session_start();
header('Content-Type: application/json');

require 'db_connect.php';
require 'config_ai.php';

// 1. Verify Configuration
if (!isset($GEMINI_API_KEY) || empty(trim($GEMINI_API_KEY))) {
    echo json_encode(['status' => 'error', 'message' => 'Config error: API Key is missing.']);
    exit();
}

// 2. Validate User
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized.']);
    exit();
}

$user_message = trim($_POST['message'] ?? '');
if (empty($user_message)) {
    echo json_encode(['status' => 'error', 'message' => 'No input.']);
    exit();
}

// 3. Prepare Context
try {
    $total_youth = $pdo->query("SELECT COUNT(*) FROM youth_profiles")->fetchColumn();
    $context = "You are Kryptiah, AI for TALA OS. Current system state: $total_youth registered youth. Keep answers brief and professional.";

    // 4. API Request
    // FIXED: Changing this BACK to gemini-3.5-flash because you were right the first time!
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:generateContent?key=" . $GEMINI_API_KEY;
    
    $payload = [
        "contents" => [
            ["role" => "user", "parts" => [["text" => $context . "\nUser: " . $user_message]]]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass local SSL issues
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) throw new Exception("cURL Error: " . $error);

    $data = json_decode($response, true);
    
    // 5. Better Error Handling
    // This will now catch the TRUE original error (like an invalid API key)
    if (isset($data['error'])) {
        throw new Exception("API Error: " . $data['error']['message']);
    }

    $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? 'System processing error.';

    echo json_encode(['status' => 'success', 'reply' => $reply]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>