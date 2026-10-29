<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session completely
session_destroy();

// Redirect back to the login page
header("Location: login.php");
exit();
?>