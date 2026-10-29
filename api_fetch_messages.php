<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['partner_id'])) {
    echo json_encode([]);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$partner_id = $_GET['partner_id'];

// Fetch messages where the current user is either the sender or the receiver, and the partner is the other
$query = "SELECT sender_id, message_text, created_at 
          FROM messages 
          WHERE (sender_id = ? AND receiver_id = ?) 
             OR (sender_id = ? AND receiver_id = ?) 
          ORDER BY created_at ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiii", $current_user_id, $partner_id, $partner_id, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    // Format the timestamp to a readable format (e.g., "11:32 AM")
    $time = date("h:i A", strtotime($row['created_at']));
    
    $messages[] = [
        'is_mine' => ($row['sender_id'] == $current_user_id),
        'text' => htmlspecialchars($row['message_text']),
        'time' => $time
    ];
}

echo json_encode($messages);

$stmt->close();
$conn->close();
?>