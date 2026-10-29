<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $status = isset($_POST['status']) ? trim($_POST['status']) : '';

    if ($id > 0 && !empty($status)) {
        try {
            $stmt = $pdo->prepare("UPDATE youth_registry SET peso_vital_status = ? WHERE id = ?");
            if ($stmt->execute([$status, $id])) {
                echo json_encode(['success' => true, 'message' => 'Vital status updated successfully']);
                exit;
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
    }
}
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>