<?php
// canva_callback.php - Loop Breaker & Debug Edition
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require 'db_connect.php'; // Uses your working DB connection

// 🔴 YOUR CANVA CREDENTIALS
$client_id = 'OC-AZ-n4iBZM6HS'; 
if (file_exists(__DIR__ . '/config_secrets.php')) {
    require_once __DIR__ . '/config_secrets.php';
    $client_secret = $CANVA_CLIENT_SECRET ?? 'YOUR_CANVA_CLIENT_SECRET_HERE';
} else {
    $client_secret = getenv('CANVA_CLIENT_SECRET') ?: 'YOUR_CANVA_CLIENT_SECRET_HERE';
}
$redirect_uri = 'https://talatumauini.site.je/canva_callback.php';

// DEBUG CHECK: Let's see what parameters are actually arriving
// If this prints on your screen, the redirect loop is officially broken!
if (isset($_GET['error'])) {
    die("<h2 style='color:red;'>Canva returned an error:</h2> " . htmlspecialchars($_GET['error']));
}

if (!isset($_GET['code'])) {
    // ----------------------------------------------------
    // STAGE 1: We do NOT have a code yet. Send user to Canva.
    // ----------------------------------------------------
    $code_verifier = bin2hex(random_bytes(32));
    $_SESSION['code_verifier'] = $code_verifier;
    $code_challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');

    $auth_url = "https://www.canva.com/api/oauth/authorize?" . http_build_query([
        'response_type' => 'code',
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
   'scope' => 'design:content:read design:content:write design:meta:read comment:read',
        'code_challenge' => $code_challenge,
        'code_challenge_method' => 's256'
    ]);
    
    header("Location: $auth_url");
    exit;
} else {
    // ----------------------------------------------------
    // STAGE 2: We GOT the code from Canva! Let's exchange it.
    // ----------------------------------------------------
    $code = $_GET['code'];
    $verifier = $_SESSION['code_verifier'] ?? '';

    if (empty($verifier)) {
        die("<h2 style='color:red;'>Session Error:</h2> The code_verifier session was lost. Please clear your cookies, go back to your workspace, and try clicking 'Authenticate Canva' again.");
    }

 $ch = curl_init("https://api.canva.com/rest/v1/designs/" . $design_id . "/comments");
    $credentials = base64_encode("$client_id:$client_secret");
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Basic $credentials",
        "Content-Type: application/x-www-form-urlencoded"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'authorization_code',
        'code_verifier' => $verifier,
        'code' => $code,
        'redirect_uri' => $redirect_uri
    ]));
    
    $response_raw = curl_exec($ch);
    $response = json_decode($response_raw, true);
    curl_close($ch);
    
    // Save Tokens to Database
    if (isset($response['access_token'])) {
        $access_token = $response['access_token'];
        $refresh_token = $response['refresh_token'] ?? '';
        $expires_at = time() + ($response['expires_in'] ?? 14400);

        try {
            $stmt = $pdo->prepare("UPDATE canva_integration SET access_token = :at, refresh_token = :rt, expires_at = :ea WHERE id = 1");
            $stmt->execute([':at' => $access_token, ':rt' => $refresh_token, ':ea' => $expires_at]);
            
            echo "<div style='font-family:sans-serif; text-align:center; margin-top:50px;'>";
            echo "<h2 style='color: #0d9488;'>Authentication 100% Successful!</h2>";
            echo "<p>Your Canva tokens are saved and ready for the GUK Bid Book.</p>";
            echo "<a href='guk_workspace.php' style='display:inline-block; padding:12px 24px; background:#0d9488; color:white; text-decoration:none; border-radius:8px; font-weight:bold;'>Return to Workspace</a>";
            echo "</div>";
            
            unset($_SESSION['code_verifier']); 

        } catch (PDOException $e) {
            echo "<h2 style='color:red;'>Database Save Error</h2><p>" . $e->getMessage() . "</p>";
        }

    } else {
        echo "<h2 style='color:red;'>Token Exchange Failed</h2>";
        echo "<pre>" . htmlspecialchars($response_raw) . "</pre>";
    }
}
?>