<?php
session_start();
require 'db_connect.php';

// Security: Only logged-in Admins and SKs can add activities
if (!isset($_SESSION['admin_logged_in'])) {
    die("Access Denied");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $youth_id = $_POST['youth_id'];
    $activity_category = $_POST['activity_category'];
    $activity_name = trim($_POST['activity_name']);
    $date_attended = $_POST['date_attended'];
    $logged_by = $_SESSION['admin_username'] . " (" . ($_SESSION['role'] === 'admin' ? 'Admin' : 'SK') . ")";

    try {
        $stmt = $pdo->prepare("INSERT INTO youth_activities (youth_id, activity_category, activity_name, date_attended, logged_by) VALUES (:youth_id, :category, :name, :date_attended, :logged_by)");
        
        $stmt->execute([
            ':youth_id' => $youth_id,
            ':category' => $activity_category,
            ':name' => $activity_name,
            ':date_attended' => $date_attended,
            ':logged_by' => $logged_by
        ]);

        // Redirect back to the profile with a success flag
        header("Location: view_profile.php?id=" . urlencode($youth_id) . "&activity_added=1");
        exit();

    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}
?>