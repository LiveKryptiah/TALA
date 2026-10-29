<?php
// Include your database connection
require 'db_connect.php';

// The new credentials you requested
$username = 'MYDOTumauini';
$password = 'BNTYouth3325';

// Securely hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // First, let's clear out any old attempts from the table so we have a clean slate
    $pdo->query("TRUNCATE TABLE admins");

    // Insert the new securely hashed user
    $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)");
    $stmt->execute([
        ':username' => $username,
        ':password_hash' => $hashed_password
    ]);

    echo "<div style='font-family: sans-serif; padding: 20px; background: #dcfce7; color: #166534; border-radius: 8px; max-width: 500px; margin: 50px auto; text-align: center;'>";
    echo "<h2 style='margin-top: 0;'>✅ Admin Account Created Successfully!</h2>";
    echo "<p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>";
    echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
    echo "<hr style='border-top: 1px solid #bbf7d0; margin: 20px 0;'>";
    echo "<p><strong>IMPORTANT:</strong> For security reasons, please delete this <code>setup_admin.php</code> file from your folder now.</p>";
    echo "<a href='login.php' style='display: inline-block; padding: 10px 20px; background: #166534; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";
    echo "</div>";

} catch(PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>