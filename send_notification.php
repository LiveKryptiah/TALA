<?php
// admin_hub.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- 1. CONFIGURATION ---
$projectId = 'talanotification'; 
$jsonPath = __DIR__ . '/firebase-credentials.json'; 

// --- 2. DATABASE CONNECTION ---
$host = 'sql104.infinityfree.com'; 
$dbname = 'if0_42252073_tala'; 
$username = 'if0_42252073'; 
$password = 'talatums3325';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// --- 3. LOGIC ---
$message = "";
$messageType = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!file_exists($jsonPath)) {
        die("CRITICAL ERROR: 'firebase-credentials.json' not found. Please upload it.");
    }

    $title = $_POST['title'];
    $body = $_POST['body'];
    $token = $_POST['target_token'];
    $imageUrl = $_POST['image_url'] ?? '';
    $iconUrl = $_POST['icon_url'] ?? '';
    $btnText = $_POST['button_text'] ?? '';
    $btnLink = $_POST['button_link'] ?? '';

    $accessToken = getGoogleAccessToken($jsonPath);
    $result = sendFCM($projectId, $accessToken, $token, $title, $body, $imageUrl, $iconUrl, $btnText, $btnLink);
    
    $resultData = json_decode($result, true);
    if (isset($resultData['name'])) {
        $messageType = "success";
        $message = "Notification & Pop-up sent successfully!";
    } else {
        $messageType = "error";
        $message = "Error: " . htmlspecialchars($result);
    }
}

// --- 4. FETCH TOKENS SAFELY ---
$tokens = [];
try {
    $tokens = $pdo->query("SELECT * FROM user_tokens WHERE device_token IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Warning: Could not fetch users. Did you add the 'name' column?";
    $messageType = "error";
}

// --- FUNCTIONS ---
function getGoogleAccessToken($keyFilePath) {
    $keyData = json_decode(file_get_contents($keyFilePath), true);
    $now = time();
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $payload = json_encode([
        'iss' => $keyData['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now
    ]);
    
    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    openssl_sign($base64UrlHeader . "." . $base64UrlPayload, $signature, $keyData['private_key'], OPENSSL_ALGO_SHA256);
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]));
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? die("Auth Error: " . $response);
}

function sendFCM($projectId, $accessToken, $token, $title, $body, $imageUrl, $iconUrl, $btnText, $btnLink) {
    $url = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";
    
    $notification = ['title' => $title, 'body' => $body];
    if (!empty($imageUrl)) $notification['image'] = $imageUrl;

    $webpush = ['headers' => ['Urgency' => 'high']];
    if (!empty($iconUrl)) {
        $webpush['notification'] = ['icon' => $iconUrl, 'requireInteraction' => true];
    }

    $payloadData = [
        'message' => [
            'token' => $token,
            'notification' => $notification,
            'data' => [
                'type' => 'dynamic_popup',
                'title' => $title,
                'description' => $body,
                'image_path' => $imageUrl,
                'button_text' => $btnText,
                'button_link' => $btnLink
            ],
            'webpush' => $webpush,
            'android' => ['notification' => ['sound' => 'default']],
            'apns' => ['payload' => ['aps' => ['sound' => 'default']]]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payloadData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $accessToken]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALA System - Push Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans flex h-screen overflow-hidden relative">
  <!-- 1. Include the Sidebar -->
    <?php 
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        include 'sidebar_admin.php';
    } else {
        include 'sidebar_admin.php';
    }
    ?>


    <main class="flex-1 p-8 ml-64">
    <div class="bg-white w-full max-w-lg rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 overflow-hidden">
        <div class="px-8 pt-8 pb-6 border-b border-gray-100">
            <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Push Notification Hub</h2>
        </div>

        <div class="p-8">
            <?php if($message): ?>
                <div class="mb-6 p-4 rounded-xl text-sm font-medium <?php echo $messageType === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Target Device Dropdown -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Target User</label>
                    <select name="target_token" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl outline-none text-sm">
                        <?php foreach($tokens as $t): ?>
                            <option value="<?php echo htmlspecialchars($t['device_token']); ?>">
                                <?php echo !empty($t['name']) ? "User: " . htmlspecialchars($t['name']) : "Token: " . substr($t['device_token'], 0, 15) . "..."; ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if(empty($tokens)): ?>
                            <option value="" disabled selected>No devices registered yet!</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Basic Message Info -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notification Title</label>
                    <input type="text" name="title" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Message Body</label>
                    <textarea name="body" rows="3" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl outline-none text-sm resize-none"></textarea>
                </div>

                <!-- Imagery -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase">Logo / Icon URL</label>
                        <input type="url" name="icon_url" value="https://talatumauini.site.je/TALA-LOGO.png" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase">Large Picture URL (Pop-up)</label>
                        <input type="url" name="image_url" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl outline-none text-sm">
                    </div>
                </div>

                <!-- Dynamic Pop-up Buttons -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase">Pop-up Button Text</label>
                        <input type="text" name="button_text" placeholder="e.g. Read More" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase">Pop-up Button Link</label>
                        <input type="url" name="button_link" class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl outline-none text-sm">
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full py-3.5 px-4 bg-gray-900 hover:bg-black text-white font-semibold rounded-xl transition-all">
                        Send Notification & Pop-up
                    </button>
                </div>
            </form>
        </div>
    </div>
    </main>
</body>
</html>