<?php
// manage_disclosure.php - Full Disclosure Policy Management & Transparency Console
// Standardized to TALA OS Design System (Reference: sk_voucher.php & sk_mom.php)
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Strict Access Control: Admin, Sub-Admin, or SK Admin
$allowed_roles = ['admin', 'sub-admin', 'sk_admin'];
if (!isset($_SESSION['admin_logged_in']) || !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'] ?? 'sub-admin';
$isAdmin = ($role === 'admin');
$user_barangay = $_SESSION['barangay_assigned'] ?? $_SESSION['admin_barangay'] ?? '';
if (empty($user_barangay) || $user_barangay === 'All') {
    $user_barangay = 'Balug';
}

$user_position = $_SESSION['sk_position'] ?? $_SESSION['position'] ?? 'Official';
$user_name = $_SESSION['admin_username'] ?? 'SK Official';

// Auto-create tables for Disclosures, Logos, and Categories
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS disclosures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        barangay VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        title VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        year INT NOT NULL,
        quarter VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Auto-upgrade if 'category' column is missing from an older version
    $cols = $pdo->query("SHOW COLUMNS FROM disclosures")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('category', $cols)) {
        $pdo->exec("ALTER TABLE disclosures ADD COLUMN category VARCHAR(100) NOT NULL DEFAULT 'Uncategorized' AFTER barangay");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_settings (
        barangay VARCHAR(255) PRIMARY KEY,
        logo_path VARCHAR(255) NOT NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS document_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(255) NOT NULL UNIQUE
    )");

    $pdo->exec("INSERT IGNORE INTO document_categories (category_name) VALUES 
        ('CBYP'), 
        ('QUARTER REGULAR OF CASH IN BANK'), 
        ('ABYIP'), 
        ('ANNUAL BUDGET'), 
        ('MONTHLY ITEMIZED LIST OF PURCHASE REQUEST'),
        ('PURCHASE REQUEST TEMPLATE')
    ");
} catch (Exception $e) {}

$all_barangays = [
    "Annafunan", "Antagan I", "Antagan II", "Arcon", "Balug", "Banig", "Bantug",
    "Barangay District 1 (Poblacion)", "Barangay District 2 (Poblacion)", "Barangay District 3 (Poblacion)",
    "Barangay District 4 (Poblacion)", "Bayabo East", "Caligayan", "Camasi", "Carpentero",
    "Compania", "Cumabao", "Fermeldy (Hacienda San Francisco)", "Fugu Abajo", "Fugu Norte",
    "Fugu Sur", "Lalauanan", "Lanna", "Lapogan", "Lingaling (Poblacion)", "Liwanag",
    "Malamag East", "Malamag West", "Maligaya (Poblacion)", "Minanga", "Moldero", "Namnama",
    "Paragu", "Pilitan", "San Mateo", "San Pedro", "San Vicente", "Santa", "Santa Catalina",
    "Santa Visitacion (Maggayu)", "Santo Niño", "Sinippil", "Sisim Abajo", "Sisim Alto", "Tunggui", "Ugad"
];
$barangays = $all_barangays;

// Flash Message Handling
$flash_msg = $_SESSION['flash_msg'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// Query parameters preservation
$search = trim($_GET['search'] ?? '');
$filter_barangay = $isAdmin ? trim($_GET['barangay'] ?? '') : $user_barangay;
$filter_category = trim($_GET['category'] ?? '');
$quarter_filter = trim($_GET['quarter'] ?? 'All');

$queryParams = [];
if (!empty($filter_barangay) && $filter_barangay !== 'All') $queryParams['barangay'] = $filter_barangay;
if (!empty($filter_category) && $filter_category !== 'All') $queryParams['category'] = $filter_category;
if (!empty($quarter_filter) && $quarter_filter !== 'All') $queryParams['quarter'] = $quarter_filter;
if (!empty($search)) $queryParams['search'] = $search;
$filterQuery = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';

// --- CRUD: ADD CATEGORY (Admin Only) ---
if ($isAdmin && (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') && isset($_POST['add_category'])) {
    $new_cat = trim($_POST['new_category'] ?? '');
    if (!empty($new_cat)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO document_categories (category_name) VALUES (?)");
            $stmt->execute([$new_cat]);
            $_SESSION['flash_msg'] = "Category '" . htmlspecialchars($new_cat) . "' added successfully.";
            $_SESSION['flash_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['flash_msg'] = "Category already exists or invalid.";
            $_SESSION['flash_type'] = "error";
        }
    }
    header("Location: manage_disclosure.php" . $filterQuery);
    exit();
}

// --- CRUD: DELETE CATEGORY (Admin Only) ---
if ($isAdmin && isset($_GET['delete_cat'])) {
    $cat_id = (int)$_GET['delete_cat'];
    try {
        $stmt = $pdo->prepare("DELETE FROM document_categories WHERE id = ?");
        $stmt->execute([$cat_id]);
        $_SESSION['flash_msg'] = "Category removed successfully.";
        $_SESSION['flash_type'] = "success";
    } catch (Exception $e) {
        $_SESSION['flash_msg'] = "Failed to remove category.";
        $_SESSION['flash_type'] = "error";
    }
    header("Location: manage_disclosure.php" . $filterQuery);
    exit();
}

// --- CRUD: UPDATE LOGO ---
if ((($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') && isset($_POST['update_logo'])) {
    $target_brgy = ($isAdmin && !empty($_POST['logo_barangay'])) ? trim($_POST['logo_barangay']) : $user_barangay;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/logos/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $file_name = md5($target_brgy . time()) . '.' . $ext;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $file_path)) {
                $stmt = $pdo->prepare("INSERT INTO barangay_settings (barangay, logo_path) VALUES (?, ?) ON DUPLICATE KEY UPDATE logo_path = ?");
                $stmt->execute([$target_brgy, $file_path, $file_path]);
                $_SESSION['flash_msg'] = "Council logo updated for " . htmlspecialchars($target_brgy) . ".";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_msg'] = "Failed to upload logo image.";
                $_SESSION['flash_type'] = "error";
            }
        } else {
            $_SESSION['flash_msg'] = "Invalid image file format.";
            $_SESSION['flash_type'] = "error";
        }
    }
    header("Location: manage_disclosure.php" . $filterQuery);
    exit();
}

// --- CRUD: UPLOAD DISCLOSURE DOCUMENT ---
if ((($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') && isset($_POST['upload_disclosure'])) {
    $target_brgy = ($isAdmin && !empty($_POST['target_barangay'])) ? trim($_POST['target_barangay']) : $user_barangay;
    $category = trim($_POST['category'] ?? 'Uncategorized');
    $title = trim($_POST['title'] ?? 'Untitled Document');
    $year = (int)($_POST['year'] ?? date('Y'));
    $quarter = trim($_POST['quarter'] ?? 'Q1');
    
    $upload_dir = 'uploads/disclosures/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $clean_brgy = preg_replace('/[^A-Za-z0-9]/', '', $target_brgy);
            $file_name = time() . '_' . $clean_brgy . '.pdf';
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['document']['tmp_name'], $file_path)) {
                $stmt = $pdo->prepare("INSERT INTO disclosures (barangay, category, title, file_path, year, quarter) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$target_brgy, $category, $title, $file_path, $year, $quarter]);
                $_SESSION['flash_msg'] = "Disclosure document '" . htmlspecialchars($title) . "' published successfully.";
                $_SESSION['flash_type'] = "success";
            } else {
                $_SESSION['flash_msg'] = "Failed to store PDF file.";
                $_SESSION['flash_type'] = "error";
            }
        } else {
            $_SESSION['flash_msg'] = "Only PDF document format is supported.";
            $_SESSION['flash_type'] = "error";
        }
    } else {
        $_SESSION['flash_msg'] = "Please attach a valid PDF document.";
        $_SESSION['flash_type'] = "error";
    }
    header("Location: manage_disclosure.php" . $filterQuery);
    exit();
}

// --- CRUD: DELETE DISCLOSURE DOCUMENT ---
if (((($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') && isset($_POST['delete_disclosure'])) || isset($_GET['delete'])) {
    $del_id = (int)($_POST['delete_id'] ?? $_GET['delete'] ?? 0);
    if ($del_id > 0) {
        if ($isAdmin) {
            $stmt = $pdo->prepare("SELECT file_path, title FROM disclosures WHERE id = ?");
            $stmt->execute([$del_id]);
        } else {
            $clean_brgy = str_ireplace('Barangay ', '', $user_barangay);
            $brgy_variants = array_values(array_unique([$user_barangay, $clean_brgy, 'Barangay ' . $clean_brgy]));
            $brgy_placeholders = implode(',', array_fill(0, count($brgy_variants), '?'));
            
            $stmt = $pdo->prepare("SELECT file_path, title FROM disclosures WHERE id = ? AND barangay IN ($brgy_placeholders)");
            $stmt->execute(array_merge([$del_id], $brgy_variants));
        }
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($doc) {
            if (!empty($doc['file_path']) && file_exists($doc['file_path'])) {
                @unlink($doc['file_path']);
            }
            $pdo->prepare("DELETE FROM disclosures WHERE id = ?")->execute([$del_id]);
            $_SESSION['flash_msg'] = "Document '" . htmlspecialchars($doc['title']) . "' was permanently removed.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_msg'] = "Document record not found or access denied.";
            $_SESSION['flash_type'] = "error";
        }
    }
    header("Location: manage_disclosure.php" . $filterQuery);
    exit();
}

// --- READ / FILTER LOGIC ---
$where_clauses = [];
$query_params = [];

if (!$isAdmin) {
    $clean_brgy = str_ireplace('Barangay ', '', $user_barangay);
    $brgy_variants = array_values(array_unique([$user_barangay, $clean_brgy, 'Barangay ' . $clean_brgy]));
    $brgy_placeholders = implode(',', array_fill(0, count($brgy_variants), '?'));
    $where_clauses[] = "barangay IN ($brgy_placeholders)";
    $query_params = array_merge($query_params, $brgy_variants);
} elseif ($filter_barangay !== 'All' && !empty($filter_barangay)) {
    $clean_filter = str_ireplace('Barangay ', '', $filter_barangay);
    $filter_variants = array_values(array_unique([$filter_barangay, $clean_filter, 'Barangay ' . $clean_filter]));
    $filter_placeholders = implode(',', array_fill(0, count($filter_variants), '?'));
    $where_clauses[] = "barangay IN ($filter_placeholders)";
    $query_params = array_merge($query_params, $filter_variants);
}

if ($filter_category !== 'All' && !empty($filter_category)) {
    $where_clauses[] = "category = ?";
    $query_params[] = $filter_category;
}
if ($quarter_filter !== 'All' && !empty($quarter_filter)) {
    $where_clauses[] = "quarter = ?";
    $query_params[] = $quarter_filter;
}
if (!empty($search)) {
    $where_clauses[] = "(title LIKE ? OR category LIKE ? OR barangay LIKE ? OR year LIKE ? OR quarter LIKE ?)";
    $term = "%{$search}%";
    $query_params[] = $term;
    $query_params[] = $term;
    $query_params[] = $term;
    $query_params[] = $term;
    $query_params[] = $term;
}

$sql = "SELECT * FROM disclosures";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY year DESC, quarter DESC, created_at DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($query_params);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Telemetry & Metrics for KPI Cards
if ($isAdmin) {
    $total_docs_count = (int)$pdo->query("SELECT COUNT(*) FROM disclosures")->fetchColumn();
    $total_barangays_active = (int)$pdo->query("SELECT COUNT(DISTINCT barangay) FROM disclosures")->fetchColumn();
} else {
    $clean_brgy = str_ireplace('Barangay ', '', $user_barangay);
    $brgy_variants = array_values(array_unique([$user_barangay, $clean_brgy, 'Barangay ' . $clean_brgy]));
    $brgy_placeholders = implode(',', array_fill(0, count($brgy_variants), '?'));
    
    $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM disclosures WHERE barangay IN ($brgy_placeholders)");
    $cnt_stmt->execute($brgy_variants);
    $total_docs_count = (int)$cnt_stmt->fetchColumn();
    $total_barangays_active = 1;
}

$total_categories_count = (int)$pdo->query("SELECT COUNT(*) FROM document_categories")->fetchColumn();

// Quarter Counts
$q_base = "SELECT COUNT(*) FROM disclosures WHERE ";
$q_filter = $isAdmin ? "1=1" : "barangay IN (" . implode(',', array_fill(0, count($brgy_variants), '?')) . ")";
$q_params = $isAdmin ? [] : $brgy_variants;

function getQuarterCount($pdo, $q, $isAdmin, $q_params, $brgy_placeholders = '') {
    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM disclosures WHERE quarter = ?");
        $stmt->execute([$q]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM disclosures WHERE quarter = ? AND barangay IN ($brgy_placeholders)");
        $stmt->execute(array_merge([$q], $q_params));
    }
    return (int)$stmt->fetchColumn();
}

$brgy_ph = !$isAdmin ? $brgy_placeholders : '';
$q1_count = getQuarterCount($pdo, 'Q1', $isAdmin, $q_params, $brgy_ph);
$q2_count = getQuarterCount($pdo, 'Q2', $isAdmin, $q_params, $brgy_ph);
$q3_count = getQuarterCount($pdo, 'Q3', $isAdmin, $q_params, $brgy_ph);
$q4_count = getQuarterCount($pdo, 'Q4', $isAdmin, $q_params, $brgy_ph);
$annual_count = getQuarterCount($pdo, 'Annual', $isAdmin, $q_params, $brgy_ph);

$current_filtered_count = count($docs);

// Fetch Current Logo for the active context
$logo_target_brgy = (!$isAdmin) ? $user_barangay : (($filter_barangay !== 'All' && !empty($filter_barangay)) ? $filter_barangay : 'Balug');
$stmt = $pdo->prepare("SELECT logo_path FROM barangay_settings WHERE barangay = ?");
$stmt->execute([$logo_target_brgy]);
$current_logo = $stmt->fetchColumn();

// Fetch Categories for dropdown
$categories = $pdo->query("SELECT * FROM document_categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Full Disclosure Policy | TALA OS</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    
    <!-- Anti-Flash Theme Script -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { 
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'] 
                    },
                    colors: {
                        brand: { 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d' }
                    }
                }
            }
        };
    </script>
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            transition: background-color 0.3s ease, color 0.3s ease; 
            -webkit-tap-highlight-color: transparent; 
        }
        
        /* Ultra-Clean Modern Cards: ZERO Outside Borders & ZERO Drop Shadows */
        .glass-card { 
            background: #ffffff !important; 
            border: none !important; 
            box-shadow: none !important; 
            -webkit-box-shadow: none !important;
            transition: all 0.2s ease; 
        }
        .dark .glass-card { 
            background: #18181b !important; 
            border: none !important; 
            box-shadow: none !important; 
            -webkit-box-shadow: none !important;
        }

        /* Mobile Drawer Overlay */
        .mobile-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            z-index: 40;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .mobile-overlay.active {
            display: block;
            opacity: 1;
        }
        
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; } 
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } 
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; } 
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        
        .tap-effect:active { transform: scale(0.97); opacity: 0.85; transition: transform 0.1s; }

        /* Subtle Below-Left (Bottom-Left) Green Ambient Gradient for Section Cards */
        .card-green-glow-bl {
            position: relative;
            overflow: hidden;
        }
        .card-green-glow-bl::before {
            content: '';
            position: absolute;
            bottom: -10px;
            left: -10px;
            width: 72px;
            height: 72px;
            background: radial-gradient(circle at 25% 75%, rgba(34, 197, 94, 0.22) 0%, rgba(34, 197, 94, 0.08) 45%, transparent 75%);
            pointer-events: none;
            border-radius: 9999px;
            filter: blur(6px);
            z-index: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        .dark .card-green-glow-bl::before {
            background: radial-gradient(circle at 25% 75%, rgba(34, 197, 94, 0.28) 0%, rgba(34, 197, 94, 0.1) 45%, transparent 75%);
        }
        .card-green-glow-bl:hover::before {
            transform: scale(1.15);
            opacity: 1;
        }

        /* Subtle Right-Side Light Yellow Gradient Effect for Green Buttons */
        .btn-yellow-glow-r,
        button.bg-\[\#15803d\],
        button.bg-\[\#16a34a\],
        a.bg-\[\#15803d\] {
            position: relative;
            overflow: hidden;
        }
        .btn-yellow-glow-r::before,
        button.bg-\[\#15803d\]::before,
        button.bg-\[\#16a34a\]::before,
        a.bg-\[\#15803d\]::before {
            content: '';
            position: absolute;
            top: -6px;
            right: -6px;
            width: 38px;
            height: calc(100% + 12px);
            background: radial-gradient(circle at 85% 50%, rgba(254, 240, 138, 0.50) 0%, rgba(250, 204, 21, 0.20) 45%, transparent 75%);
            pointer-events: none;
            border-radius: inherit;
            filter: blur(3px);
            z-index: 1;
            transition: transform 0.25s ease, opacity 0.25s ease;
        }
        .btn-yellow-glow-r:hover::before,
        button.bg-\[\#15803d\]:hover::before,
        button.bg-\[\#16a34a\]:hover::before,
        a.bg-\[\#15803d\]:hover::before {
            transform: scale(1.15);
            opacity: 1;
        }

        /* Remove all outline / outside strokelines on focus */
        input:focus,
        input:focus-visible,
        input:active,
        select:focus,
        select:focus-visible,
        select:active,
        textarea:focus,
        textarea:focus-visible,
        textarea:active,
        button:focus,
        button:focus-visible,
        a:focus {
            outline: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }

        /* Remove all box shadows globally */
        .shadow-none-all,
        .shadow-sm,
        .shadow-md,
        .shadow-lg,
        .shadow-xl,
        .shadow-2xl,
        .shadow-soft {
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }

        img {
            filter: none !important;
            -webkit-filter: none !important;
        }

        /* Suppress conflicting floating theme toggle from sidebar */
        #globalThemeToggle { 
            display: none !important; 
        }

        @media print {
            body { background: #fff !important; color: #000 !important; }
            #tala-admin-sidebar, #tala-bulletproof-sk-sidebar, nav, #headerThemeToggle, .no-print { display: none !important; }
            main { margin-left: 0 !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- Role-Based Sidebar -->
    <?php include ($isAdmin ? 'sidebar_admin.php' : 'sidebar_sk.php'); ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA (Reference: sk_voucher.php) -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-3 md:space-y-4">
            
            <!-- HEADER SECTION (Bold H1 only, No corn logo, 3 KPI Badges - Matching sk_voucher.php) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                        Full Disclosure Console
                    </h1>
                </div>
                
                <!-- Section Cards (3 KPI badges) & Dark Mode Toggle -->
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Total Documents -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-files text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Documents</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_docs_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Categories -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-folders text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Categories</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_categories_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Active Councils -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-buildings text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Active Councils</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($total_barangays_active) ?></span>
                        </div>
                    </div>

                    <!-- Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[36px] h-[36px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
                        <i id="headerThemeIcon" class="ph-bold ph-moon text-base sm:text-lg transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- NOTIFICATIONS -->
            <?php if (!empty($flash_msg)): ?>
                <div class="<?= $flash_type === 'success' ? 'bg-green-50/85 dark:bg-green-500/10 text-green-700 dark:text-green-400' : 'bg-red-50/85 dark:bg-red-500/10 text-red-700 dark:text-red-400' ?> px-4 py-3 rounded-2xl flex items-center justify-between gap-3 text-xs md:text-sm font-bold shadow-none backdrop-blur-md transition-colors border-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <i class="ph-bold <?= $flash_type === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-lg flex-shrink-0"></i>
                        <span class="truncate"><?= htmlspecialchars($flash_msg) ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="w-6 h-6 rounded-lg flex items-center justify-center hover:bg-black/5 dark:hover:bg-white/10 transition-colors tap-effect" aria-label="Dismiss">
                        <i class="ph-bold ph-x text-xs"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- FILTER, SEARCH & ACTION CONTROL BAR (Matching sk_voucher.php) -->
            <div class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-2.5">
                <!-- Quarter Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full xl:w-auto custom-scrollbar py-0.5 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['val' => 'All', 'label' => 'All Records', 'count' => $total_docs_count, 'active' => ($quarter_filter === 'All' || empty($quarter_filter))],
                        ['val' => 'Q1', 'label' => '1st Quarter', 'count' => $q1_count, 'active' => ($quarter_filter === 'Q1')],
                        ['val' => 'Q2', 'label' => '2nd Quarter', 'count' => $q2_count, 'active' => ($quarter_filter === 'Q2')],
                        ['val' => 'Q3', 'label' => '3rd Quarter', 'count' => $q3_count, 'active' => ($quarter_filter === 'Q3')],
                        ['val' => 'Q4', 'label' => '4th Quarter', 'count' => $q4_count, 'active' => ($quarter_filter === 'Q4')],
                        ['val' => 'Annual', 'label' => 'Annual', 'count' => $annual_count, 'active' => ($quarter_filter === 'Annual')],
                    ];
                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        
                        $tabParams = [];
                        if ($tab['val'] !== 'All') $tabParams['quarter'] = $tab['val'];
                        if (!empty($filter_barangay) && $filter_barangay !== 'All') $tabParams['barangay'] = $filter_barangay;
                        if (!empty($filter_category) && $filter_category !== 'All') $tabParams['category'] = $filter_category;
                        if (!empty($search)) $tabParams['search'] = $search;
                        $tabUrl = 'manage_disclosure.php' . (!empty($tabParams) ? '?' . http_build_query($tabParams) : '');
                    ?>
                        <a href="<?= $tabUrl ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span class="relative z-10"><?= $tab['label'] ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 dark:bg-black/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?> relative z-10"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Dropdowns, Search & Action Buttons -->
                <div class="flex flex-wrap items-center gap-2 w-full xl:w-auto">
                    <!-- Barangay Dropdown (Admin Only) -->
                    <?php if ($isAdmin): ?>
                    <form method="GET" class="w-full sm:w-auto sm:flex-1 md:w-36 lg:w-40 min-w-[130px]">
                        <?php if ($quarter_filter !== 'All'): ?><input type="hidden" name="quarter" value="<?= htmlspecialchars($quarter_filter) ?>"><?php endif; ?>
                        <?php if (!empty($filter_category) && $filter_category !== 'All'): ?><input type="hidden" name="category" value="<?= htmlspecialchars($filter_category) ?>"><?php endif; ?>
                        <?php if (!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <select name="barangay" onchange="this.form.submit()" class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="">All 46 Barangays</option>
                            <option value="Municipal Level" <?= $filter_barangay === 'Municipal Level' ? 'selected' : '' ?>>Municipal Level</option>
                            <?php foreach ($all_barangays as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= $filter_barangay === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php endif; ?>

                    <!-- Category Dropdown -->
                    <form method="GET" class="w-full sm:w-auto sm:flex-1 md:w-40 lg:w-44 min-w-[140px]">
                        <?php if ($quarter_filter !== 'All'): ?><input type="hidden" name="quarter" value="<?= htmlspecialchars($quarter_filter) ?>"><?php endif; ?>
                        <?php if ($isAdmin && !empty($filter_barangay) && $filter_barangay !== 'All'): ?><input type="hidden" name="barangay" value="<?= htmlspecialchars($filter_barangay) ?>"><?php endif; ?>
                        <?php if (!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <select name="category" onchange="this.form.submit()" class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['category_name']) ?>" <?= $filter_category === $cat['category_name'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <!-- Search Input -->
                    <form method="GET" class="relative w-full sm:w-auto sm:flex-1 md:w-48 lg:w-52 min-w-[150px]">
                        <?php if ($quarter_filter !== 'All'): ?><input type="hidden" name="quarter" value="<?= htmlspecialchars($quarter_filter) ?>"><?php endif; ?>
                        <?php if ($isAdmin && !empty($filter_barangay) && $filter_barangay !== 'All'): ?><input type="hidden" name="barangay" value="<?= htmlspecialchars($filter_barangay) ?>"><?php endif; ?>
                        <?php if (!empty($filter_category) && $filter_category !== 'All'): ?><input type="hidden" name="category" value="<?= htmlspecialchars($filter_category) ?>"><?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search document..." class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none transition-colors border-0">
                    </form>

                    <!-- Action Buttons Cluster -->
                    <div class="flex items-center gap-1.5 w-full sm:w-auto ml-auto sm:ml-0 flex-shrink-0">
                        <?php if (!empty($search) || ($isAdmin && !empty($filter_barangay) && $filter_barangay !== 'All') || (!empty($filter_category) && $filter_category !== 'All') || $quarter_filter !== 'All'): ?>
                            <a href="manage_disclosure.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Filters">
                                <i class="ph-bold ph-x text-xs"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Publish Document Button (with .btn-yellow-glow-r) -->
                        <button type="button" onclick="openPublishModal()" class="btn-yellow-glow-r flex-1 sm:flex-none px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center justify-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group cursor-pointer">
                            <i class="ph-bold ph-cloud-arrow-up text-xs transition-transform duration-300 group-hover:scale-110 relative z-10"></i>
                            <span class="relative z-10">Publish</span>
                        </button>

                        <!-- Settings Button -->
                        <button type="button" onclick="openSettingsModal()" class="w-[30px] h-[30px] rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold tap-effect flex items-center justify-center flex-shrink-0 transition-all duration-200 active:scale-[0.95] shadow-none cursor-pointer" title="Council Logo & Category Settings">
                            <i class="ph-bold ph-gear text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- CONSOLIDATED MINIMAL FULL DISCLOSURE REGISTRY TABLE -->
            <div class="glass-card rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Minimal Header Strip -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex justify-between items-center transition-colors flex-shrink-0 border-b border-slate-100 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Full Disclosure Ledger</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                            <?= number_format($current_filtered_count) ?> Records
                        </span>
                    </div>
                    <span class="text-[11px] font-semibold text-slate-400 dark:text-zinc-500 hidden sm:inline">Official compliance reports & attachments</span>
                </div>

                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[800px]">
                        <thead>
                            <tr class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider bg-slate-50/50 dark:bg-zinc-900/40 select-none border-b border-slate-100 dark:border-zinc-800/80">
                                <th class="py-2.5 px-4">Barangay</th>
                                <th class="py-2.5 px-4">Document Title & Category</th>
                                <th class="py-2.5 px-4">Fiscal Period</th>
                                <th class="py-2.5 px-4">Published Date</th>
                                <th class="py-2.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs divide-y divide-slate-100 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($docs)): ?>
                                <?php foreach ($docs as $row): ?>
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-800/40 transition-colors group">
                                        <!-- Barangay -->
                                        <td class="py-3 px-4 whitespace-nowrap align-middle">
                                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300 px-2.5 py-1 rounded-xl text-xs font-bold uppercase tracking-wider transition-colors">
                                                <i class="ph-fill ph-map-pin text-green-600 dark:text-green-500 text-[11px]"></i>
                                                <span><?= htmlspecialchars($row['barangay'] ?? '') ?></span>
                                            </span>
                                        </td>

                                        <!-- Document Details -->
                                        <td class="py-3 px-4 align-middle">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold text-xs flex-shrink-0 group-hover:scale-105 transition-transform">
                                                    <i class="ph-fill ph-file-pdf text-sm"></i>
                                                </div>
                                                <div class="min-w-0 max-w-sm sm:max-w-md">
                                                    <p class="font-bold text-slate-900 dark:text-white text-xs leading-snug truncate group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors">
                                                        <?= htmlspecialchars($row['title'] ?? 'Untitled Document') ?>
                                                    </p>
                                                    <div class="flex items-center gap-2 text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">
                                                        <span class="font-bold text-green-700 dark:text-green-400 uppercase tracking-wider text-[10px]">
                                                            <?= htmlspecialchars($row['category'] ?? 'Uncategorized') ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Fiscal Period -->
                                        <td class="py-3 px-4 whitespace-nowrap align-middle">
                                            <span class="inline-flex items-center gap-1.5 text-slate-600 dark:text-zinc-400 text-xs font-semibold">
                                                <i class="ph-bold ph-calendar text-slate-400 text-xs"></i>
                                                <span class="bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 px-2 py-0.5 rounded-lg text-xs font-bold">
                                                    <?= htmlspecialchars($row['quarter']) ?> <?= htmlspecialchars($row['year']) ?>
                                                </span>
                                            </span>
                                        </td>

                                        <!-- Published Date -->
                                        <td class="py-3 px-4 whitespace-nowrap text-xs text-slate-500 dark:text-zinc-400 align-middle">
                                            <?= date('M j, Y', strtotime($row['created_at'])) ?>
                                        </td>

                                        <!-- Actions -->
                                        <td class="py-3 px-4 text-right align-middle whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1">
                                                <a href="<?= htmlspecialchars($row['file_path']) ?>" target="_blank" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect" title="View PDF Document">
                                                    <i class="ph-bold ph-arrow-square-out text-xs"></i>
                                                </a>
                                                <a href="?delete=<?= $row['id'] ?><?= !empty($filterQuery) ? '&' . ltrim($filterQuery, '?') : '' ?>" onclick="return confirm('Confirm Deletion: Permanently remove this disclosure document from the transparency board?')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Delete Document">
                                                    <i class="ph-bold ph-trash text-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-14 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-zinc-800 flex items-center justify-center mx-auto mb-2 text-slate-400 dark:text-zinc-500 text-xl">
                                            <i class="ph-bold ph-files"></i>
                                        </div>
                                        <p class="font-extrabold text-xs text-slate-800 dark:text-zinc-200">No Documents Found</p>
                                        <p class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium mt-0.5">No compliance records match the selected filter or search keyword.</p>
                                        <?php if (!empty($search) || ($isAdmin && !empty($filter_barangay) && $filter_barangay !== 'All') || (!empty($filter_category) && $filter_category !== 'All') || $quarter_filter !== 'All'): ?>
                                            <a href="manage_disclosure.php" class="mt-3 inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
                                                <span>Clear Filters</span>
                                            </a>
                                        <?php else: ?>
                                            <button type="button" onclick="openPublishModal()" class="btn-yellow-glow-r mt-3 inline-flex items-center gap-1 px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white text-xs font-bold transition-all tap-effect">
                                                <i class="ph-bold ph-plus text-xs relative z-10"></i>
                                                <span class="relative z-10">Publish First Document</span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Minimal Table Footer -->
                <div class="px-4 py-2 bg-white/40 dark:bg-zinc-900/40 flex justify-between items-center text-xs text-slate-500 dark:text-zinc-400 font-medium flex-shrink-0 border-t border-slate-100 dark:border-zinc-800/80">
                    <span class="text-[11px]">Showing <strong class="text-slate-800 dark:text-zinc-200 font-bold"><?= count($docs) ?></strong> records</span>
                    <span class="text-[10px] text-slate-400 dark:text-zinc-500">Full Disclosure Policy &bull; TALA OS</span>
                </div>

            </div>
            
        </div>
    </main>

    <!-- PUBLISH DOCUMENT POPUP MODAL (Reference: sk_voucher.php) -->
    <div id="publishModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closePublishModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-lg w-full p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800 max-h-[90vh] overflow-y-auto custom-scrollbar" id="publishModalBox">
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-4 border-b border-slate-100 dark:border-zinc-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 rounded-xl flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-fill ph-cloud-arrow-up"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight">Publish Document</h3>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono mt-0.5">Public Transparency Board</p>
                    </div>
                </div>
                <button type="button" onclick="closePublishModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>
            
            <!-- Modal Body (Form) -->
            <form action="manage_disclosure.php<?= !empty($filterQuery) ? $filterQuery : '' ?>" method="POST" enctype="multipart/form-data" id="publishForm" class="space-y-3">
                <input type="hidden" name="upload_disclosure" value="1">
                
                <!-- Target Barangay -->
                <?php if ($isAdmin): ?>
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Target Barangay *</label>
                    <select name="target_barangay" id="modal_target_barangay" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors cursor-pointer border-0" required>
                        <option value="Municipal Level" <?= $filter_barangay === 'Municipal Level' ? 'selected' : '' ?>>Municipal Level (General)</option>
                        <?php foreach ($barangays as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>" <?= $filter_barangay === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else: ?>
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Assigned Council</label>
                    <div class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 rounded-xl px-3 py-2 text-xs md:text-sm font-bold flex items-center justify-between">
                        <span><?= htmlspecialchars($user_barangay) ?></span>
                        <i class="ph-bold ph-lock-simple text-slate-400 text-xs"></i>
                    </div>
                    <input type="hidden" name="target_barangay" value="<?= htmlspecialchars($user_barangay) ?>">
                </div>
                <?php endif; ?>

                <!-- Document Category -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Document Category *</label>
                    <select name="category" id="modal_category" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors cursor-pointer border-0" required>
                        <option value="" disabled selected>Select Official Category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['category_name']) ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Document Title -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Document Title *</label>
                    <input type="text" name="title" id="modal_title" placeholder="e.g. Annual Budget for Youth Development 2026" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors border-0" required>
                </div>

                <!-- Fiscal Year & Quarter -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Fiscal Year *</label>
                        <input type="number" name="year" id="modal_year" value="<?= date('Y') ?>" min="2020" max="2035" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors border-0" required>
                    </div>
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Quarter *</label>
                        <select name="quarter" id="modal_quarter" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors cursor-pointer border-0" required>
                            <option value="Q1">1st Quarter (Q1)</option>
                            <option value="Q2">2nd Quarter (Q2)</option>
                            <option value="Q3">3rd Quarter (Q3)</option>
                            <option value="Q4">4th Quarter (Q4)</option>
                            <option value="Annual">Annual (Full Year)</option>
                        </select>
                    </div>
                </div>

                <!-- PDF File Upload Box -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">PDF Attachment *</label>
                    <div class="bg-slate-50 dark:bg-zinc-800/60 rounded-xl p-3 transition-colors border border-slate-100 dark:border-zinc-800">
                        <input type="file" name="document" id="modal_document" accept=".pdf" required class="block w-full text-xs text-slate-500 dark:text-zinc-400 file:mr-2.5 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#15803d] file:text-white hover:file:bg-[#166534] dark:file:bg-[#16a34a] dark:hover:file:bg-[#15803d] cursor-pointer">
                        <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-1.5">Official PDF documents only (max 25MB).</p>
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-zinc-800 mt-2">
                    <button type="button" onclick="closePublishModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm transition-all tap-effect">
                        Cancel
                    </button>
                    <button type="submit" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2 px-5 rounded-xl transition-all text-xs md:text-sm tap-effect flex items-center gap-1.5 shadow-none group">
                        <i class="ph-bold ph-cloud-arrow-up relative z-10"></i>
                        <span class="relative z-10">Publish Document</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- COUNCIL SETTINGS & CATEGORIES POPUP MODAL (Reference: sk_voucher.php) -->
    <div id="settingsModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeSettingsModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-lg w-full p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800 max-h-[85vh]" id="settingsModalBox">
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-zinc-800 flex-shrink-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 rounded-xl flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-fill ph-gear"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight">Council Settings</h3>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono mt-0.5">Logo & Classification</p>
                    </div>
                </div>
                <button type="button" onclick="closeSettingsModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>
            
            <!-- Modal Body (Scrollable) -->
            <div class="space-y-4 overflow-y-auto custom-scrollbar flex-1 pr-1">
                
                <!-- Section 1: Council Profile Logo -->
                <div class="p-3.5 bg-slate-50 dark:bg-zinc-800/60 rounded-2xl transition-colors border border-slate-100 dark:border-zinc-800">
                    <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-2.5 flex items-center gap-1.5">
                        <i class="ph-bold ph-image text-green-600 dark:text-green-500"></i> Council Profile Logo
                    </h4>
                    
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-14 h-14 rounded-2xl bg-white dark:bg-zinc-900 flex items-center justify-center overflow-hidden shrink-0 border border-slate-100 dark:border-zinc-800">
                            <?php if (!empty($current_logo)): ?>
                                <img src="<?= htmlspecialchars($current_logo) ?>" alt="Council Logo" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="ph-bold ph-buildings text-2xl text-slate-300 dark:text-zinc-600"></i>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-zinc-400 leading-tight">
                            <p class="font-bold text-slate-800 dark:text-zinc-200 text-sm"><?= htmlspecialchars($logo_target_brgy) ?></p>
                            <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">Displayed on public transparency headers.</p>
                        </div>
                    </div>

                    <form action="manage_disclosure.php<?= !empty($filterQuery) ? $filterQuery : '' ?>" method="POST" enctype="multipart/form-data" class="space-y-2.5">
                        <input type="hidden" name="update_logo" value="1">
                        <?php if ($isAdmin): ?>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Target Barangay</label>
                            <select name="logo_barangay" class="w-full bg-white dark:bg-zinc-900 text-slate-800 dark:text-white rounded-xl px-3 py-1.5 text-xs font-semibold focus:outline-none transition-colors cursor-pointer border-0">
                                <?php foreach ($barangays as $b): ?>
                                    <option value="<?= htmlspecialchars($b) ?>" <?= $logo_target_brgy === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div>
                            <input type="file" name="logo" accept="image/*" required class="block w-full text-xs text-slate-500 dark:text-zinc-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#15803d] file:text-white hover:file:bg-[#166534] dark:file:bg-[#16a34a] dark:hover:file:bg-[#15803d] cursor-pointer">
                        </div>
                        <button type="submit" class="btn-yellow-glow-r w-full bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2 rounded-xl text-xs md:text-sm transition-all tap-effect flex items-center justify-center gap-1.5 shadow-none group">
                            <i class="ph-bold ph-floppy-disk relative z-10"></i>
                            <span class="relative z-10">Update Council Logo</span>
                        </button>
                    </form>
                </div>

                <!-- Section 2: Category Management (Admin Only) -->
                <?php if ($isAdmin): ?>
                <div class="p-3.5 bg-slate-50 dark:bg-zinc-800/60 rounded-2xl transition-colors border border-slate-100 dark:border-zinc-800">
                    <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-700 dark:text-zinc-300 mb-2 flex items-center gap-1.5">
                        <i class="ph-bold ph-list-plus text-green-600 dark:text-green-500"></i> Manage Official Categories
                    </h4>
                    
                    <form action="manage_disclosure.php<?= !empty($filterQuery) ? $filterQuery : '' ?>" method="POST" class="flex gap-1.5 mb-2.5">
                        <input type="hidden" name="add_category" value="1">
                        <input type="text" name="new_category" placeholder="e.g. Sangguniang Resolution" required class="flex-1 bg-white dark:bg-zinc-900 text-slate-800 dark:text-white placeholder-slate-400 text-xs md:text-sm px-3 py-1.5 rounded-xl font-semibold focus:outline-none border-0">
                        <button type="submit" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold px-3 py-1.5 rounded-xl text-xs md:text-sm tap-effect shadow-none group">
                            <i class="ph-bold ph-plus relative z-10"></i>
                        </button>
                    </form>

                    <!-- Categories List Chips -->
                    <div class="flex flex-wrap gap-1.5 max-h-36 overflow-y-auto custom-scrollbar pr-1">
                        <?php foreach ($categories as $cat): ?>
                            <span class="inline-flex items-center gap-1 bg-white dark:bg-zinc-900 text-slate-700 dark:text-zinc-300 px-2.5 py-1 rounded-xl text-xs font-bold border border-slate-100 dark:border-zinc-800">
                                <?= htmlspecialchars($cat['category_name']) ?>
                                <a href="?delete_cat=<?= $cat['id'] ?><?= !empty($filterQuery) ? '&' . ltrim($filterQuery, '?') : '' ?>" onclick="return confirm('Remove category \'<?= htmlspecialchars(addslashes($cat['category_name'])) ?>\'?')" class="text-slate-400 hover:text-red-500 transition-colors ml-0.5" title="Remove Category">
                                    <i class="ph-bold ph-x text-xs"></i>
                                </a>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <!-- Modal Footer -->
            <div class="pt-3 mt-3 flex items-center justify-end flex-shrink-0 border-t border-slate-100 dark:border-zinc-800">
                <button type="button" onclick="closeSettingsModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm transition-all tap-effect">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Android Style - Reference: sk_voucher.php) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 h-[68px] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-2xl z-50 flex justify-around items-center px-2 pb-[env(safe-area-inset-bottom)] transition-colors border-0">
        <?php if ($isAdmin): ?>
            <a href="admin_dashboard.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
                <div class="px-4 py-1 mb-1">
                    <i class="ph-bold ph-squares-four text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Dashboard</span>
            </a>
            <a href="admin_calendar.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
                <div class="px-4 py-1 mb-1">
                    <i class="ph-bold ph-calendar-blank text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Calendar</span>
            </a>
            <a href="manage_disclosure.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
                <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                    <i class="ph-fill ph-files text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Disclosure</span>
            </a>
            <a href="admin_users.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
                <div class="px-4 py-1 mb-1">
                    <i class="ph-bold ph-users text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Users</span>
            </a>
        <?php else: ?>
            <a href="sk_dashboard.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
                <div class="px-4 py-1 mb-1">
                    <i class="ph-bold ph-squares-four text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Dashboard</span>
            </a>
            <a href="sk2_budget.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
                <div class="px-4 py-1 mb-1">
                    <i class="ph-bold ph-coins text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Budget</span>
            </a>
            <a href="sk_voucher.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
                <div class="px-4 py-1 mb-1">
                    <i class="ph-bold ph-receipt text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Vouchers</span>
            </a>
            <a href="manage_disclosure.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
                <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                    <i class="ph-fill ph-files text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Disclosure</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- UI Scripts -->
    <script>
        // Modal Controls: Publish Document
        const publishModalOverlay = document.getElementById('publishModalOverlay');
        const publishModalBox = document.getElementById('publishModalBox');
        const publishForm = document.getElementById('publishForm');

        function openPublishModal() {
            if (publishForm) publishForm.reset();
            if (publishModalOverlay && publishModalBox) {
                publishModalOverlay.classList.remove('hidden');
                publishModalOverlay.classList.add('flex');
                requestAnimationFrame(() => {
                    publishModalOverlay.classList.remove('opacity-0');
                    publishModalOverlay.classList.add('opacity-100');
                    publishModalBox.classList.remove('scale-95', 'opacity-0');
                    publishModalBox.classList.add('scale-100', 'opacity-100');
                });
            }
        }

        function closePublishModal() {
            if (publishModalOverlay && publishModalBox) {
                publishModalOverlay.classList.remove('opacity-100');
                publishModalOverlay.classList.add('opacity-0');
                publishModalBox.classList.remove('scale-100', 'opacity-100');
                publishModalBox.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    publishModalOverlay.classList.remove('flex');
                    publishModalOverlay.classList.add('hidden');
                }, 300);
            }
        }

        // Modal Controls: Settings
        const settingsModalOverlay = document.getElementById('settingsModalOverlay');
        const settingsModalBox = document.getElementById('settingsModalBox');

        function openSettingsModal() {
            if (settingsModalOverlay && settingsModalBox) {
                settingsModalOverlay.classList.remove('hidden');
                settingsModalOverlay.classList.add('flex');
                requestAnimationFrame(() => {
                    settingsModalOverlay.classList.remove('opacity-0');
                    settingsModalOverlay.classList.add('opacity-100');
                    settingsModalBox.classList.remove('scale-95', 'opacity-0');
                    settingsModalBox.classList.add('scale-100', 'opacity-100');
                });
            }
        }

        function closeSettingsModal() {
            if (settingsModalOverlay && settingsModalBox) {
                settingsModalOverlay.classList.remove('opacity-100');
                settingsModalOverlay.classList.add('opacity-0');
                settingsModalBox.classList.remove('scale-100', 'opacity-100');
                settingsModalBox.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    settingsModalOverlay.classList.remove('flex');
                    settingsModalOverlay.classList.add('hidden');
                }, 300);
            }
        }

        // Close on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (publishModalOverlay && !publishModalOverlay.classList.contains('hidden')) closePublishModal();
                if (settingsModalOverlay && !settingsModalOverlay.classList.contains('hidden')) closeSettingsModal();
            }
        });

        // Mobile Drawer Toggle
        function toggleMobileMenu() {
            const sidebar = document.getElementById('tala-admin-sidebar') || document.getElementById('tala-bulletproof-sk-sidebar') || document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            if (!sidebar) return;
            if (sidebar.classList.contains('mobile-open') || sidebar.classList.contains('active')) {
                sidebar.classList.remove('mobile-open', 'active');
                if (overlay) overlay.classList.remove('active');
            } else {
                sidebar.classList.add('mobile-open', 'active');
                if (overlay) overlay.classList.add('active');
            }
        }

        // Header Dark Mode Toggle Logic
        function syncHeaderThemeIcon(isDark) {
            const icon = document.getElementById('headerThemeIcon');
            if (icon) {
                if (isDark) {
                    icon.classList.remove('ph-moon');
                    icon.classList.add('ph-sun');
                } else {
                    icon.classList.remove('ph-sun');
                    icon.classList.add('ph-moon');
                }
            }
        }

        function toggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            localStorage.theme = isDark ? 'dark' : 'light';
            syncHeaderThemeIcon(isDark);
        }
        syncHeaderThemeIcon(document.documentElement.classList.contains('dark'));
    </script>

    <!-- Genesis AI Assistant Widget -->
    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>