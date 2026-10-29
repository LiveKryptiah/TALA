<?php
require 'db_connect.php';
$mswdo_hash = password_hash('mswdo123', PASSWORD_DEFAULT);
$pdo->prepare("UPDATE admins SET password_hash = ? WHERE username = 'mswdo_tumauini'")->execute([$mswdo_hash]);
echo "<h3 style='color:green; font-family:sans-serif;'>✅ MSWDO password secured! You can now log in with: mswdo_tumauini / mswdo123</h3>";
?>