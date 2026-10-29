<?php
$is_local = (!isset($_SERVER['SERVER_NAME']) || in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']) || php_sapi_name() === 'cli');
$host = $is_local ? '127.0.0.1' : 'sql104.infinityfree.com';
$dbname = 'if0_42252073_tala';
$username = $is_local ? 'root' : 'if0_42252073';
$password = $is_local ? '' : 'talatums3325';

// Inside db_connect.php or a central config file
if (isset($_SESSION['user_id'])) {
    $current_file = basename($_SERVER['PHP_SELF']);
    $user_id = $_SESSION['user_id'];
    
    // Map file names to readable activities
    $activity_map = [
        'dashboard.php' => 'Viewing Dashboard',
        'youth_profiles.php' => 'Browsing Profiles',
        'add_profile.php' => 'Registering Youth',
        'manage_scholarships.php' => 'Reviewing Scholarships',
        // Add more here...
    ];
    
    $activity_text = $activity_map[$current_file] ?? 'Navigating System';
    
    // Update the database (using PDO example)
    // $stmt = $pdo->prepare("UPDATE users SET current_activity = ?, last_active = NOW() WHERE id = ?");
    // $stmt->execute([$activity_text, $user_id]);
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Connected successfully"; 
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// --- SECURITY HELPER FUNCTIONS ---

function getBarangayFilter() {
    // 1. Check if the session variable even exists yet (prevents the warning on login page)
    if (!isset($_SESSION['role'])) {
        return ""; 
    }

    // 2. If it's a super admin, they see everything (no filter)
    if ($_SESSION['role'] === 'admin') {
        return ""; 
    } 
    // 3. If it's a sub-admin, filter by their assigned barangay
    else {
        // Use null coalescing (??) as an extra safety net
        $brgy = $_SESSION['barangay_assigned'] ?? '';
        return "WHERE barangay = '$brgy'";
    }
}

function getQuerySeparator() {
    // If no role is set, or if they are an admin, start the query with WHERE
    if (!isset($_SESSION['role']) || $_SESSION['role'] === 'admin') {
        return "WHERE";
    } 
    // If they are a sub-admin (meaning a WHERE clause is already active), use AND
    else {
        return "AND";
    }
}
// Add this at the very bottom of db_connect.php
function autoAwardXP($pdo, $admin_user, $barangay, $action_name, $xp_amount) {
    try {
        // Save the XP silently to the database
        $stmt = $pdo->prepare("INSERT INTO sk2_admin_xp (admin_user, barangay, action_name, xp_earned) VALUES (?, ?, ?, ?)");
        $stmt->execute([$admin_user, $barangay, $action_name, $xp_amount]);

        // Set session variables to trigger the pop-up toast
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $_SESSION['toast_xp'] = $xp_amount;
        $_SESSION['toast_msg'] = $action_name;
    } catch (Exception $e) {}
}
?>