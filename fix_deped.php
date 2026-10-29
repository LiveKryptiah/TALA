<?php
require 'db_connect.php';

try {
    // 1. Generate a real, secure encrypted hash for 'deped123'
    $real_hash = password_hash('deped123', PASSWORD_DEFAULT);
    
    // 2. Update the dummy hash in the database with the real one
    $stmt = $pdo->prepare("UPDATE admins SET password_hash = :hash WHERE username = 'deped_tumauini'");
    $stmt->execute([':hash' => $real_hash]);
    
    echo "<h2 style='color: green; font-family: sans-serif; text-align: center; margin-top: 50px;'>
            ✅ Success! The DepEd password has been securely encrypted.<br>
            You can now go to login.php and log in with:<br><br>
            Username: <b>deped_tumauini</b><br>
            Password: <b>deped123</b>
          </h2>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>