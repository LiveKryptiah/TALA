<?php
if (file_exists(__DIR__ . '/config_secrets.php')) {
    require_once __DIR__ . '/config_secrets.php';
}

$is_local = (!isset($_SERVER['SERVER_NAME']) || in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1']) || php_sapi_name() === 'cli');

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
    ];
    
    $activity_text = $activity_map[$current_file] ?? 'Navigating System';
}

$pdo = null;
$db_connection_error = null;

// 1. Check for PostgreSQL / Supabase configuration
$pg_url = getenv('POSTGRES_URL_NON_POOLING') 
       ?: getenv('POSTGRES_URL') 
       ?: getenv('POSTGRES_PRISMA_URL') 
       ?: getenv('DATABASE_URL') 
       ?: ($POSTGRES_URL ?? null);

$pg_host = getenv('POSTGRES_HOST') ?: ($POSTGRES_HOST ?? null);
$pg_port = getenv('POSTGRES_PORT') ?: ($POSTGRES_PORT ?? null);
$pg_db   = getenv('POSTGRES_DATABASE') ?: ($POSTGRES_DATABASE ?? null);
$pg_user = getenv('POSTGRES_USER') ?: ($POSTGRES_USER ?? null);
$pg_pass = getenv('POSTGRES_PASSWORD') ?: ($POSTGRES_PASSWORD ?? null);

// If URL is provided, parse it to extract credentials
if ($pg_url) {
    $dbparts = parse_url($pg_url);
    if (!empty($dbparts['host'])) $pg_host = $dbparts['host'];
    if (!empty($dbparts['port'])) $pg_port = $dbparts['port'];
    if (!empty($dbparts['user'])) $pg_user = urldecode($dbparts['user']);
    if (!empty($dbparts['pass'])) $pg_pass = urldecode($dbparts['pass']);
    if (!empty($dbparts['path'])) $pg_db   = ltrim($dbparts['path'], '/');
}

if ($pg_host || $pg_url) {
    // Detect project reference from SUPABASE_URL or host
    $project_ref = null;
    $sb_url = getenv('SUPABASE_URL') ?: ($SUPABASE_URL ?? null);
    if ($sb_url && preg_match('/https?:\/\/([a-z0-9\-]+)\.supabase\.co/i', $sb_url, $sm)) {
        $project_ref = $sm[1];
    }
    if (!$project_ref && $pg_host && preg_match('/db\.([a-z0-9\-]+)\.supabase\.co/i', $pg_host, $pm)) {
        $project_ref = $pm[1];
    }

    // CRITICAL: Supabase direct hosts (db.<ref>.supabase.co) are IPv6-only.
    // AWS Lambda / Vercel Serverless runtimes cannot route IPv6, throwing:
    // "SQLSTATE[08006] [7] connection to server at ... Cannot assign requested address"
    // We automatically route through Supabase's IPv4 connection pooler.
    if ($pg_host && preg_match('/db\.([a-z0-9\-]+)\.supabase\.co/i', $pg_host, $hm)) {
        $project_ref = $hm[1];
        $pg_host = 'aws-0-us-east-1.pooler.supabase.com';
    }

    // Pooler requires user to be "postgres.<project_ref>"
    if ($pg_host && str_contains($pg_host, 'pooler.supabase.com')) {
        if ($project_ref && ($pg_user === 'postgres' || !str_contains($pg_user ?? '', '.'))) {
            $pg_user = 'postgres.' . $project_ref;
        }
    }

    $pg_port = $pg_port ?: '5432';
    $pg_db   = $pg_db ?: 'postgres';
    $pg_user = $pg_user ?: 'postgres';

    $targets_to_try = [
        ['host' => $pg_host, 'port' => $pg_port, 'user' => $pg_user]
    ];

    // If initial host is not the pooler, add pooler as fallback
    if (!str_contains($pg_host, 'pooler.supabase.com') && $project_ref) {
        $targets_to_try[] = [
            'host' => 'aws-0-us-east-1.pooler.supabase.com',
            'port' => 5432,
            'user' => 'postgres.' . $project_ref
        ];
    }

    foreach ($targets_to_try as $target_conn) {
        try {
            $dsn = "pgsql:host={$target_conn['host']};port={$target_conn['port']};dbname=$pg_db;sslmode=require";
            $pdo = new PDO($dsn, $target_conn['user'], $pg_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 6
            ]);
            
            // Ensure compatibility aliases for MySQL queries
            @$pdo->exec("CREATE OR REPLACE FUNCTION curdate() RETURNS date AS $$ SELECT CURRENT_DATE; $$ LANGUAGE SQL;");
            @$pdo->exec("CREATE OR REPLACE FUNCTION curtime() RETURNS time AS $$ SELECT CURRENT_TIME; $$ LANGUAGE SQL;");
            $db_connection_error = null;
            break;
        } catch (\Throwable $e) {
            $db_connection_error = "Supabase Connection Notice: " . $e->getMessage();
        }
    }
}

// 2. If PostgreSQL not configured or failed, fallback to MySQL (local dev or traditional host)
if (!$pdo) {
    $host = getenv('DB_HOST') ?: ($DB_HOST ?? ($is_local ? '127.0.0.1' : 'sql104.infinityfree.com'));
    $dbname = getenv('DB_NAME') ?: ($DB_NAME ?? 'if0_42252073_tala');
    $username = getenv('DB_USER') ?: ($DB_USER ?? ($is_local ? 'root' : 'if0_42252073'));
    $password = getenv('DB_PASS') ?: ($DB_PASS ?? ($is_local ? '' : 'talatums3325'));
    $port = getenv('DB_PORT') ?: ($DB_PORT ?? '3306');

    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 4
        ]);
    } catch (\Throwable $e) {
        $pdo = null;
        if (!$db_connection_error) {
            $db_connection_error = "MySQL Error: " . $e->getMessage();
        }
    }
}

// 3. Handle fatal connection error for authenticated pages that strictly require a database
if (!$pdo) {
    $script = basename($_SERVER['PHP_SELF'] ?? '');
    if ($script !== 'index.php' && !isset($allow_offline_mode)) {
        if (isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL'])) {
            die("<div style='font-family:system-ui,-apple-system,sans-serif;max-width:640px;margin:50px auto;padding:32px;border-radius:24px;background:#ffffff;box-shadow:0 20px 40px -15px rgba(0,0,0,0.1);border:1px solid #e2e8f0;text-align:left;'>
                <div style='display:inline-block;padding:6px 12px;border-radius:999px;background:#dcfce7;color:#16a34a;font-weight:700;font-size:12px;margin-bottom:14px;'>Supabase Active</div>
                <h2 style='margin:0 0 10px;color:#0f172a;font-size:20px;font-weight:800;'>Database Configuration Notice</h2>
                <p style='color:#64748b;font-size:14px;line-height:1.6;margin:0 0 16px;'>Could not connect to database: " . htmlspecialchars($db_connection_error ?? 'Unknown error') . "</p>
            </div>");
        }
        die("Connection failed: " . ($db_connection_error ?? 'Unknown error'));
    }
}

// --- SECURITY HELPER FUNCTIONS ---

function getBarangayFilter() {
    if (!isset($_SESSION['role'])) {
        return ""; 
    }
    if ($_SESSION['role'] === 'admin') {
        return ""; 
    } 
    if ($_SESSION['role'] === 'sub-admin' && !empty($_SESSION['barangay_assigned'])) {
        global $pdo;
        if (!$pdo) return "";
        return " AND barangay = " . $pdo->quote($_SESSION['barangay_assigned']);
    }
    return " AND 1=0";
}

function getBarangayAdminFilter() {
    if (!isset($_SESSION['role'])) {
        return ""; 
    }
    if ($_SESSION['role'] === 'admin') {
        return ""; 
    } 
    if ($_SESSION['role'] === 'sub-admin' && !empty($_SESSION['barangay_assigned'])) {
        global $pdo;
        if (!$pdo) return "";
        return " AND barangay_assigned = " . $pdo->quote($_SESSION['barangay_assigned']);
    }
    return " AND 1=0";
}

function getQuerySeparator() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] === 'admin') {
        return "WHERE";
    } else {
        return "AND";
    }
}

if (!function_exists('autoAwardXP')) {
    function autoAwardXP($pdo, $admin_user, $barangay, $action_name, $xp_amount) {
        try {
            if (!$pdo) return;
            $stmt = $pdo->prepare("INSERT INTO sk2_admin_xp (admin_user, barangay, action_name, xp_earned) VALUES (?, ?, ?, ?)");
            $stmt->execute([$admin_user, $barangay, $action_name, $xp_amount]);

            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $_SESSION['toast_xp'] = $xp_amount;
            $_SESSION['toast_msg'] = $action_name;
        } catch (\Throwable $e) {}
    }
}
