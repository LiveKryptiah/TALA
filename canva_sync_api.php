<?php
// canva_sync_api.php (DEBUG MODE ENHANCED)
header('Content-Type: application/json');
require 'db_connect.php';

try {
    // 1. Fetch credentials
    $stmt = $pdo->query("SELECT design_id, access_token FROM canva_integration LIMIT 1");
    $canva_config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$canva_config || empty($canva_config['access_token'])) {
        echo json_encode(['error' => 'Database is empty. Please run the Authenticate Canva setup.']);
        exit;
    }

    $design_id = trim($canva_config['design_id']);
    $token = trim($canva_config['access_token']);

    $logs = [];

    // ========================================================
    // A. FETCH DESIGN METADATA
    // ========================================================
    $ch = curl_init("https://api.canva.com/rest/v1/designs/$design_id");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $design_raw = curl_exec($ch);
    $design_response = json_decode($design_raw, true);
    curl_close($ch);

    // DEBUG CHECK 1: Did Canva return an error for the design?
    if (isset($design_response['code'])) {
        echo json_encode(['error' => 'Canva API Error: ' . $design_response['message'] . ' (Code: ' . $design_response['code'] . ')']);
        exit;
    }

    if (isset($design_response['design'])) {
        $design = $design_response['design'];
        $logs[] = [
            'icon' => 'ph-paint-brush',
            'color' => 'text-canva-500',
            'user' => 'Layout Team',
            'action' => 'saved document updates',
            'time' => date('h:i A', strtotime($design['updated_at']))
        ];
    }

    // ========================================================
    // B. FETCH REAL COMMENTS
    // ========================================================
    $ch2 = curl_init("https://api.canva.com/rest/v1/designs/$design_id/comments");
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    $comments_raw = curl_exec($ch2);
    $comments_response = json_decode($comments_raw, true);
    curl_close($ch2);

    

    if (isset($comments_response['comments']) && count($comments_response['comments']) > 0) {
        $recent_comments = array_slice($comments_response['comments'], 0, 4);
        
        foreach ($recent_comments as $comment) {
            $msg = strlen($comment['message']) > 35 ? substr($comment['message'], 0, 35) . '...' : $comment['message'];
            $author = $comment['author']['display_name'] ?? 'TWG Member';
            
            $logs[] = [
                'icon' => 'ph-chat-circle',
                'color' => 'text-amber-400',
                'user' => $author,
                'action' => 'commented: "' . htmlspecialchars($msg) . '"',
                'time' => date('h:i A', strtotime($comment['created_at']))
            ];
        }
    }

    // Return successful data
    echo json_encode([
        'success' => true,
        'logs' => $logs
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Server Error: ' . $e->getMessage()]);
}
?>