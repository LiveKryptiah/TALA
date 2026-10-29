<?php
require 'db_connect.php';
$real_hash = password_hash('peso123', PASSWORD_DEFAULT);
$pdo->prepare("UPDATE admins SET password_hash = ? WHERE username = 'peso_tumauini'")->execute([$real_hash]);
echo "PESO Password Securely Fixed. You can now login with peso_tumauini / peso123";
?>