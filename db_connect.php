<?php
if (file_exists(__DIR__ . '/config_secrets.php')) {
    require_once __DIR__ . '/config_secrets.php';
}

$is_local = (!isset($_SERVER['SERVER_NAME']) || in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']) || php_sapi_name() === 'cli');
$host = getenv('DB_HOST') ?: ($DB_HOST ?? ($is_local ? '127.0.0.1' : 'sql104.infinityfree.com'));
$dbname = getenv('DB_NAME') ?: ($DB_NAME ?? 'if0_42252073_tala');
$username = getenv('DB_USER') ?: ($DB_USER ?? ($is_local ? 'root' : 'if0_42252073'));
$password = getenv('DB_PASS') ?: ($DB_PASS ?? ($is_local ? '' : 'talatums3325'));
$port = getenv('DB_PORT') ?: ($DB_PORT ?? '3306');

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
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 4
    ]);
} catch(PDOException $e) {
    $pdo = null;
    $db_connection_error = $e->getMessage();
    
    $script = basename($_SERVER['PHP_SELF'] ?? '');
    if ($script !== 'index.php' && !isset($allow_offline_mode)) {
        if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL'])) {
            die("<div style='font-family:system-ui,-apple-system,sans-serif;max-width:640px;margin:50px auto;padding:32px;border-radius:24px;background:#ffffff;box-shadow:0 20px 40px -15px rgba(0,0,0,0.1);border:1px solid #e2e8f0;text-align:left;'>
                <div style='display:inline-block;padding:6px 12px;border-radius:999px;background:#dcfce7;color:#16a34a;font-weight:700;font-size:12px;margin-bottom:14px;'>Vercel PHP Connected</div>
                <h2 style='margin:0 0 10px;color:#0f172a;font-size:20px;font-weight:800;'>Database Configuration Needed</h2>
                <p style='color:#64748b;font-size:14px;line-height:1.6;margin:0 0 16px;'>To connect your MySQL database on Vercel, set your database credentials in your <strong>Vercel Dashboard &rarr; Project Settings &rarr; Environment Variables</strong>:</p>
                <div style='background:#f8fafc;padding:14px;border-radius:12px;font-family:monospace;font-size:12px;color:#334155;margin-bottom:16px;line-height:1.8;'>
                    DB_HOST = your-database-host<br>
                    DB_NAME = your-database-name<br>
                    DB_USER = your-database-user<br>
                    DB_PASS = your-database-password<br>
                    DB_PORT = 3306
                </div>
                <p style='color:#94a3b8;font-size:12px;margin:0;'>InfinityFree's free database does not accept external connections from Vercel. Connect a cloud database from TiDB Cloud, Supabase, PlanetScale, or Aiven.</p>
            </div>");
        }
        die("Connection failed: " . $e->getMessage());
    }
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