<?php
// Force PHP to show all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

try {
    require 'db_connect.php';
    echo "Attempting to connect to $host...<br>";
    
    echo "<h3 style='color: green;'>✅ SUCCESS! The database is connected perfectly.</h3>";
    
} catch(PDOException $e) {
    echo "<h3 style='color: red;'>❌ FAILED! Here is the exact reason:</h3>";
    echo "<div style='background: #ffebee; padding: 10px; border: 1px solid red;'>";
    echo "<strong>" . $e->getMessage() . "</strong>";
    echo "</div>";
}
?>