<?php
session_start();
header('Content-Type: application/json');

// Include your database connection (ensure this file sets up a $conn variable)
require_once 'db_connect.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$sender_id = $_SESSION['user_id'];

// Get the raw POST data sent by JavaScript (Alpine.js / fetch)
$data = json_decode(file_get_contents("php://input"), true);

$receiver_id = $data['receiver_id'] ?? null;
$message_text = trim($data['message_text'] ?? '');

if (empty($message_text) || empty($receiver_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
    exit;
}

// Securely insert the message using prepared statements to prevent SQL injection
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message_text, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
$stmt->bind_param("iis", $sender_id, $receiver_id, $message_text);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

$stmt->close();
$conn->close();
?>