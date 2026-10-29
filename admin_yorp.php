<?php
ob_start();
// admin_yorp.php - YORP Management Tracker & Accreditation Console
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Strict Admin Access Control
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$message = '';
$msg_type = 'success';

// Auto-create & upgrade YORP table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `yorp_organizations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `org_name` VARCHAR(255) NOT NULL,
        `org_type` VARCHAR(100) NOT NULL,
        `barangay` VARCHAR(100) NOT NULL,
        `president_name` VARCHAR(255) NOT NULL,
        `contact_number` VARCHAR(50) NOT NULL,
        `email` VARCHAR(100) NOT NULL,
        `registration_file_path` VARCHAR(255) NULL,
        `directory_file_path` VARCHAR(255) NULL,
        `members_file_path` VARCHAR(255) NULL,
        `constitution_file_path` VARCHAR(255) NULL,
        `endorsement_file_path` VARCHAR(255) NULL,
        `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $cols = $pdo->query("SHOW COLUMNS FROM `yorp_organizations`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('registration_file_path', $cols)) {
        $pdo->exec("ALTER TABLE `yorp_organizations` ADD COLUMN `registration_file_path` VARCHAR(255) NULL AFTER `email`");
    }
    if (!in_array('directory_file_path', $cols)) {
        $pdo->exec("ALTER TABLE `yorp_organizations` ADD COLUMN `directory_file_path` VARCHAR(255) NULL AFTER `registration_file_path`");
    }
    if (!in_array('endorsement_file_path', $cols)) {
        $pdo->exec("ALTER TABLE `yorp_organizations` ADD COLUMN `endorsement_file_path` VARCHAR(255) NULL AFTER `constitution_file_path`");
    }
} catch (PDOException $e) {}

// --- CRUD: CREATE & UPDATE ---
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['save_org'])) {
    $org_id = (int)($_POST['org_id'] ?? 0);
    $org_name = trim($_POST['org_name'] ?? '');
    $org_type = trim($_POST['org_type'] ?? 'Community-Based');
    $barangay = trim($_POST['barangay'] ?? '');
    $president_name = trim($_POST['president_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['Pending', 'Approved', 'Rejected']) ? $_POST['status'] : 'Approved';

    $upload_dir = 'uploads/yorp/';
    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
    $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];

    function uploadAdminYorpFile($file, $prefix, $org, $dir, $allowed) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) return null;
        $safe_org = preg_replace('/[^A-Za-z0-9]/', '', $org);
        $path = $dir . $prefix . '_' . time() . '_' . $safe_org . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $path)) return $path;
        return null;
    }

    $members_path = uploadAdminYorpFile($_FILES['members_file'] ?? null, 'MEMBERS', $org_name, $upload_dir, $allowed_exts);
    $cbl_path = uploadAdminYorpFile($_FILES['constitution_file'] ?? null, 'CBL', $org_name, $upload_dir, $allowed_exts);
    $reg_path = uploadAdminYorpFile($_FILES['registration_file'] ?? null, 'REG', $org_name, $upload_dir, $allowed_exts);

    if (!empty($org_id)) {
        // UPDATE
        $sql = "UPDATE yorp_organizations SET org_name=?, org_type=?, barangay=?, president_name=?, contact_number=?, email=?, status=?";
        $update_params = [$org_name, $org_type, $barangay, $president_name, $contact_number, $email, $status];

        if ($members_path) {
            $sql .= ", members_file_path=?";
            $update_params[] = $members_path;
        }
        if ($cbl_path) {
            $sql .= ", constitution_file_path=?";
            $update_params[] = $cbl_path;
        }
        if ($reg_path) {
            $sql .= ", registration_file_path=?";
            $update_params[] = $reg_path;
        }
        $sql .= " WHERE id=?";
        $update_params[] = $org_id;

        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($update_params)) {
            $message = "Organization '{$org_name}' successfully updated.";
            $msg_type = 'success';
        } else {
            $message = "Failed to update organization.";
            $msg_type = 'error';
        }
    } else {
        // CREATE
        $stmt = $pdo->prepare("INSERT INTO yorp_organizations (org_name, org_type, barangay, president_name, contact_number, email, members_file_path, constitution_file_path, registration_file_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$org_name, $org_type, $barangay, $president_name, $contact_number, $email, $members_path, $cbl_path, $reg_path, $status])) {
            $message = "New organization '{$org_name}' successfully accredited and recorded.";
            $msg_type = 'success';
        } else {
            $message = "Failed to record organization.";
            $msg_type = 'error';
        }
    }
}

// Handle Approve / Reject / Delete
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE yorp_organizations SET status = 'Approved' WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Organization successfully approved!";
        $msg_type = 'success';
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE yorp_organizations SET status = 'Rejected' WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Organization application marked as rejected.";
        $msg_type = 'warning';
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare("SELECT members_file_path, constitution_file_path, registration_file_path FROM yorp_organizations WHERE id = ?");
        $stmt->execute([$id]);
        $org = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($org) {
            if (!empty($org['members_file_path']) && file_exists($org['members_file_path'])) {
                @unlink($org['members_file_path']);
            }
            if (!empty($org['constitution_file_path']) && file_exists($org['constitution_file_path'])) {
                @unlink($org['constitution_file_path']);
            }
            if (!empty($org['registration_file_path']) && file_exists($org['registration_file_path'])) {
                @unlink($org['registration_file_path']);
            }
        }
        $pdo->prepare("DELETE FROM yorp_organizations WHERE id = ?")->execute([$id]);
        $message = "Organization record deleted successfully.";
        $msg_type = 'success';
    }
    header("Location: admin_yorp.php?msg=" . urlencode($message) . "&type=" . urlencode($msg_type));
    exit();
}

if (!empty($_GET['msg'])) {
    $message = $_GET['msg'];
    $msg_type = $_GET['type'] ?? 'success';
}

// Filters & Search
$filter_status = trim($_GET['status'] ?? 'All');
$filter_barangay = trim($_GET['barangay'] ?? 'All');
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM yorp_organizations WHERE 1=1";
$params = [];

if ($filter_status !== 'All' && in_array($filter_status, ['Pending', 'Approved', 'Rejected'])) {
    $sql .= " AND status = :status";
    $params[':status'] = $filter_status;
}

if ($filter_barangay !== 'All' && !empty($filter_barangay)) {
    $sql .= " AND barangay = :barangay";
    $params[':barangay'] = $filter_barangay;
}

if (!empty($search)) {
    $sql .= " AND (org_name LIKE :search OR president_name LIKE :search OR barangay LIKE :search OR email LIKE :search OR org_type LIKE :search)";
    $params[':search'] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$organizations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Overall Metrics
$total_orgs = (int)$pdo->query("SELECT COUNT(*) FROM yorp_organizations")->fetchColumn();
$approved_count = (int)$pdo->query("SELECT COUNT(*) FROM yorp_organizations WHERE status = 'Approved'")->fetchColumn();
$pending_count = (int)$pdo->query("SELECT COUNT(*) FROM yorp_organizations WHERE status = 'Pending'")->fetchColumn();
$rejected_count = (int)$pdo->query("SELECT COUNT(*) FROM yorp_organizations WHERE status = 'Rejected'")->fetchColumn();

// All 46 Tumauini Barangays
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

$org_types = [
    "Community-Based",
    "School-Based",
    "Faith-Based",
    "Working Youth",
    "In-School Youth",
    "Out-of-School Youth",
    "Special Sector (PWD, IP, etc.)"
];

if (!function_exists('buildUrl')) {
    function buildUrl($params_to_update) {
        $all_params = $_GET;
        foreach ($params_to_update as $key => $val) { 
            if ($val === null || $val === '' || ($key === 'status' && $val === 'All')) {
                unset($all_params[$key]);
            } else {
                $all_params[$key] = $val;
            }
        }
        return 'admin_yorp.php' . (!empty($all_params) ? '?' . http_build_query($all_params) : '');
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>YORP Registry Console | TALA OS</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    
    <!-- Anti-Flash Theme Script -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { 
                extend: { 
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, 
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
        
        /* Modern Glass Card - Zero Outside Strokes, Zero Shadows */
        .glass-card { 
            background: #ffffff !important; 
            border: none !important; 
            box-shadow: none !important; 
            -webkit-box-shadow: none !important;
            transition: all 0.3s ease; 
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
        
        .tap-effect:active { transform: scale(0.96); opacity: 0.85; transition: transform 0.1s; }

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

    <!-- Admin Sidebar -->
    <?php include 'sidebar_admin.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA (Non-scrollable on desktop, Viewport-fitted architecture) -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px and fits 100% viewport height -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-between min-h-0">
            
            <!-- COMMAND CENTER HEADER & METRICS (Clearance xl:pr-14 for Dark Mode Toggle) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 mb-2 md:mb-3 gap-2 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <div>
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                            YORP Registry Console
                        </h1>
                        <p class="text-xs md:text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                            Youth Organization Registration Program & Accreditation Dossiers
                        </p>
                    </div>
                </div>

                <!-- Header KPI Badges & Controls -->
                <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Approved Orgs -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-bold ph-seal-check text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Approved Orgs</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($approved_count) ?></span>
                        </div>
                    </div>
                    
                    <!-- KPI 2: Pending Review -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-bold ph-hourglass-high text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Pending Review</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($pending_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Total Applied -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-bold ph-buildings text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Applied</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_orgs) ?></span>
                        </div>
                    </div>

                    <!-- Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[36px] h-[36px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
                        <i id="headerThemeIcon" class="ph-bold ph-moon text-base sm:text-lg transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- NOTIFICATIONS -->
            <?php if (!empty($message)): ?>
                <div class="<?= ($msg_type === 'success') ? 'bg-green-50/85 dark:bg-green-500/10 text-green-700 dark:text-green-400' : (($msg_type === 'warning') ? 'bg-amber-50/85 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400' : 'bg-red-50/85 dark:bg-red-500/10 text-red-700 dark:text-red-400') ?> px-4 py-3 rounded-2xl flex items-center justify-between gap-3 text-xs md:text-sm font-bold shadow-none backdrop-blur-md transition-colors border-0 mb-2 md:mb-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <i class="ph-bold <?= ($msg_type === 'success') ? 'ph-check-circle' : (($msg_type === 'warning') ? 'ph-warning' : 'ph-warning-circle') ?> text-lg flex-shrink-0"></i>
                        <span class="truncate"><?= htmlspecialchars($message) ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="w-6 h-6 rounded-lg flex items-center justify-center hover:bg-black/5 dark:hover:bg-white/10 transition-colors tap-effect" aria-label="Dismiss">
                        <i class="ph-bold ph-x text-xs"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- FILTER, SEARCH & ACTION CONTROL BAR (Matching manage_disclosure.php) -->
            <div class="glass-card card-green-glow-bl rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-2.5 mb-2 md:mb-3">
                
                <!-- Quick Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full xl:w-auto custom-scrollbar py-0.5 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['val' => 'All', 'label' => 'All Organizations', 'count' => $total_orgs, 'active' => ($filter_status === 'All' || empty($filter_status))],
                        ['val' => 'Approved', 'label' => 'Approved', 'count' => $approved_count, 'active' => ($filter_status === 'Approved')],
                        ['val' => 'Pending', 'label' => 'Pending Review', 'count' => $pending_count, 'active' => ($filter_status === 'Pending')],
                        ['val' => 'Rejected', 'label' => 'Rejected', 'count' => $rejected_count, 'active' => ($filter_status === 'Rejected')],
                    ];
                    foreach ($filter_tabs as $t):
                        $isActive = $t['active'];
                        $pillClass = $isActive 
                            ? 'bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white btn-yellow-glow-r' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        
                        $pillUrl = buildUrl(['status' => $t['val']]);
                    ?>
                        <a href="<?= $pillUrl ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span><?= $t['label'] ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 dark:bg-black/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?>"><?= $t['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Dropdowns, Search & Action Button Cluster -->
                <div class="flex flex-wrap items-center gap-2 w-full xl:w-auto">
                    <!-- Barangay Dropdown -->
                    <form method="GET" action="admin_yorp.php" class="w-full sm:w-auto sm:flex-1 md:w-44 min-w-[140px]">
                        <?php if ($filter_status !== 'All'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"><?php endif; ?>
                        <?php if (!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <select name="barangay" onchange="this.form.submit()" class="w-full bg-slate-50 dark:bg-zinc-900/60 text-slate-800 dark:text-zinc-200 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="All">All 46 Barangays</option>
                            <?php foreach ($all_barangays as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= $filter_barangay === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <!-- Search Input -->
                    <form method="GET" action="admin_yorp.php" class="relative w-full sm:w-auto sm:flex-1 md:w-56 min-w-[160px]">
                        <?php if ($filter_status !== 'All'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>"><?php endif; ?>
                        <?php if ($filter_barangay !== 'All' && !empty($filter_barangay)): ?><input type="hidden" name="barangay" value="<?= htmlspecialchars($filter_barangay) ?>"><?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search org, president, email..." class="w-full bg-slate-50 dark:bg-zinc-900/60 text-slate-800 dark:text-zinc-200 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none transition-colors border-0">
                    </form>

                    <!-- Action Buttons Cluster -->
                    <div class="flex items-center gap-1.5 w-full sm:w-auto ml-auto sm:ml-0 flex-shrink-0">
                        <?php if (!empty($search) || $filter_status !== 'All' || $filter_barangay !== 'All'): ?>
                            <a href="admin_yorp.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Filters">
                                <i class="ph-bold ph-x text-xs"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Print YORP Registry Button -->
                        <button type="button" onclick="window.print()" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 font-bold h-[30px] px-2.5 rounded-xl text-xs gap-1.5 transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Print YORP Registry">
                            <i class="ph-bold ph-printer text-xs"></i>
                            <span class="hidden sm:inline">Print</span>
                        </button>

                        <!-- Accredit Organization Action Button -->
                        <button type="button" onclick="openOrgModal()" class="flex-1 sm:flex-none px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect btn-yellow-glow-r flex items-center justify-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group cursor-pointer">
                            <i class="ph-bold ph-plus text-xs transition-transform duration-300 group-hover:scale-110"></i>
                            <span>Accredit Organization</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- CONSOLIDATED FULL-WIDTH TABLE CARD (Fills remaining height) -->
            <div class="glass-card card-green-glow-bl rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Table Header Bar -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 transition-colors flex-shrink-0 border-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Official Youth Organizations Registry</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                            <?= number_format(count($organizations)) ?> of <?= number_format($total_orgs) ?> Records
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium hidden sm:inline">Review dossiers, member rosters, bylaws, and accreditation compliance</p>
                </div>

                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[850px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider bg-slate-50/90 dark:bg-zinc-900/90 backdrop-blur-sm transition-colors border-0">
                                <th class="py-2.5 px-4 md:px-5">Organization Profile</th>
                                <th class="py-2.5 px-4 md:px-5">Type & Barangay</th>
                                <th class="py-2.5 px-4 md:px-5">Submitted Dossiers</th>
                                <th class="py-2.5 px-4 md:px-5 text-center">Accreditation Status</th>
                                <th class="py-2.5 px-4 md:px-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs md:text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($organizations)): ?>
                                <?php foreach ($organizations as $org): ?>
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-800/40 transition-colors group">
                                        
                                        <!-- Profile -->
                                        <td class="py-2.5 px-4 md:px-5">
                                            <div class="flex items-start gap-2.5">
                                                <div class="w-9 h-9 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-base font-bold flex-shrink-0 mt-0.5">
                                                    <i class="ph-fill ph-users-three"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-bold text-slate-900 dark:text-white text-xs md:text-sm truncate">
                                                        <?= htmlspecialchars($org['org_name']) ?>
                                                    </p>
                                                    <p class="text-[11px] text-slate-500 dark:text-zinc-400 flex items-center gap-1 mt-0.5">
                                                        <i class="ph-bold ph-user text-slate-400 dark:text-zinc-500"></i>
                                                        <span class="font-semibold"><?= htmlspecialchars($org['president_name']) ?></span>
                                                    </p>
                                                    <p class="text-[10px] text-slate-400 dark:text-zinc-500 mt-0.5">
                                                        <?= htmlspecialchars($org['contact_number']) ?> &bull; <?= htmlspecialchars($org['email']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Type & Location -->
                                        <td class="py-2.5 px-4 md:px-5 whitespace-nowrap">
                                            <span class="inline-block bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider mb-1">
                                                <?= htmlspecialchars($org['org_type']) ?>
                                            </span>
                                            <p class="text-xs font-semibold text-slate-700 dark:text-zinc-300 flex items-center gap-1">
                                                <i class="ph-fill ph-map-pin text-green-600 dark:text-green-500"></i>
                                                <span><?= htmlspecialchars($org['barangay']) ?></span>
                                            </p>
                                        </td>
                                        
                                        <!-- Submitted Documents -->
                                        <td class="py-2.5 px-4 md:px-5">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <?php if (!empty($org['members_file_path'])): ?>
                                                    <a href="<?= htmlspecialchars($org['members_file_path']) ?>" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100 dark:hover:bg-blue-900/50 px-2.5 py-0.5 rounded-lg transition-all tap-effect border-0" title="View Member Roster">
                                                        <i class="ph-bold ph-file-text"></i> Member List
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (!empty($org['constitution_file_path'])): ?>
                                                    <a href="<?= htmlspecialchars($org['constitution_file_path']) ?>" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-950/40 hover:bg-green-100 dark:hover:bg-green-900/50 px-2.5 py-0.5 rounded-lg transition-all tap-effect border-0" title="View Constitution & By-Laws">
                                                        <i class="ph-bold ph-scroll"></i> Constitution
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (!empty($org['registration_file_path'])): ?>
                                                    <a href="<?= htmlspecialchars($org['registration_file_path']) ?>" target="_blank" class="inline-flex items-center gap-1 text-[11px] font-bold text-purple-700 dark:text-purple-300 bg-purple-50 dark:bg-purple-950/40 hover:bg-purple-100 dark:hover:bg-purple-900/50 px-2.5 py-0.5 rounded-lg transition-all tap-effect border-0" title="View Registration Form">
                                                        <i class="ph-bold ph-file"></i> Reg Form
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (empty($org['members_file_path']) && empty($org['constitution_file_path']) && empty($org['registration_file_path'])): ?>
                                                    <span class="text-[11px] text-slate-400 dark:text-zinc-500 italic">No files attached</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        
                                        <!-- Status Badge -->
                                        <td class="py-2.5 px-4 md:px-5 text-center whitespace-nowrap">
                                            <?php 
                                                $statusClass = 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300';
                                                if ($org['status'] == 'Approved') {
                                                    $statusClass = 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400';
                                                } elseif ($org['status'] == 'Pending') {
                                                    $statusClass = 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400';
                                                } elseif ($org['status'] == 'Rejected') {
                                                    $statusClass = 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400';
                                                }
                                            ?>
                                            <span class="inline-block px-2.5 py-1 rounded-lg text-xs font-black uppercase tracking-wider <?= $statusClass ?> border-0">
                                                <?= htmlspecialchars($org['status']) ?>
                                            </span>
                                        </td>
                                        
                                        <!-- Actions -->
                                        <td class="py-2.5 px-4 md:px-5 text-right whitespace-nowrap align-middle">
                                            <div class="inline-flex items-center gap-1.5 justify-end">
                                                <?php if ($org['status'] === 'Pending'): ?>
                                                    <a href="?action=approve&id=<?= $org['id'] ?>" onclick="return confirm('Approve this youth organization accreditation?')" class="w-7 h-7 inline-flex items-center justify-center bg-green-50 dark:bg-green-500/10 text-green-600 dark:text-green-400 rounded-lg hover:bg-green-100 dark:hover:bg-green-500/20 transition-colors tap-effect" title="Approve Organization">
                                                        <i class="ph-bold ph-check text-sm"></i>
                                                    </a>
                                                    <a href="?action=reject&id=<?= $org['id'] ?>" onclick="return confirm('Reject this organization application?')" class="w-7 h-7 inline-flex items-center justify-center bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-lg hover:bg-amber-100 dark:hover:bg-amber-500/20 transition-colors tap-effect" title="Reject Organization">
                                                        <i class="ph-bold ph-x text-sm"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <button type="button" onclick="viewOrg(<?= htmlspecialchars(json_encode($org), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 inline-flex items-center justify-center bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700 rounded-lg transition-colors tap-effect" title="View Organization Details">
                                                    <i class="ph-bold ph-eye text-sm"></i>
                                                </button>
                                                
                                                <button type="button" onclick="editOrg(<?= htmlspecialchars(json_encode($org), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 inline-flex items-center justify-center bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700 rounded-lg transition-colors tap-effect" title="Edit Record">
                                                    <i class="ph-bold ph-pencil-simple text-sm"></i>
                                                </button>

                                                <a href="?action=delete&id=<?= $org['id'] ?>" onclick="return confirm('Permanently delete this organization and its submitted dossiers?')" class="w-7 h-7 inline-flex items-center justify-center bg-slate-100 dark:bg-zinc-800 text-slate-400 hover:bg-red-500 hover:text-white dark:hover:bg-red-500 transition-colors tap-effect" title="Delete Record">
                                                    <i class="ph-bold ph-trash text-sm"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <div class="w-24 h-24 md:w-28 md:h-28 mx-auto mb-2 flex items-center justify-center">
                                            <img src="mascot_question.png" alt="No Organizations" class="max-h-full max-w-full object-contain pointer-events-none hover:scale-105 transition-transform duration-300">
                                        </div>
                                        <p class="font-bold text-xs md:text-sm text-slate-600 dark:text-zinc-300">No youth organizations found matching parameters.</p>
                                        <p class="text-xs text-slate-400 dark:text-zinc-500 mt-0.5">Try clearing your search query or accrediting a new youth organization.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer Info -->
                <div class="px-4 py-2.5 bg-white/40 dark:bg-zinc-900/40 flex justify-between items-center text-xs text-slate-500 dark:text-zinc-400 font-medium flex-shrink-0 border-0">
                    <span>Showing <strong class="text-slate-800 dark:text-zinc-200"><?= count($organizations) ?></strong> of <?= $total_orgs ?> organizations</span>
                    <span>NYC &bull; YORP Registry Console</span>
                </div>
            </div>

        </div>
    </main>

    <!-- ACCREDIT / EDIT MODAL (#orgModal) -->
    <div id="orgModal" class="fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeOrgModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 card-green-glow-bl rounded-[2rem] overflow-hidden max-w-2xl w-full flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none max-h-[90vh] overflow-y-auto custom-scrollbar border-0" id="orgModalContent">
            <!-- Modal Header -->
            <div class="p-4 sm:p-5 flex justify-between items-center transition-colors flex-shrink-0 border-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-lg font-bold">
                        <i class="ph-bold ph-identification-badge"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight" id="modalTitle">Accredit Youth Organization</h3>
                        <p class="text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider" id="modalSubtitle">NYC YORP Registry Entry</p>
                    </div>
                </div>
                <button type="button" onclick="closeOrgModal()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-colors tap-effect">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Form Body -->
            <form action="admin_yorp.php" method="POST" enctype="multipart/form-data" class="flex-1 flex flex-col justify-between overflow-hidden p-4 sm:p-6 pt-0" id="yorpForm">
                <input type="hidden" name="save_org" value="1">
                <input type="hidden" name="org_id" id="modal_org_id" value="">

                <div class="space-y-3 overflow-y-auto custom-scrollbar pr-1 flex-1 min-h-0">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Organization Name *</label>
                        <input type="text" name="org_name" id="modal_org_name" placeholder="e.g. Tumauini Youth Leaders Alliance" class="w-full bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2 text-xs md:text-sm font-semibold border-0 outline-none transition-all" required>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Organization Type *</label>
                            <select name="org_type" id="modal_org_type" class="w-full bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-white rounded-xl px-3.5 py-2 text-xs md:text-sm font-semibold border-0 outline-none transition-all cursor-pointer" required>
                                <?php foreach ($org_types as $type): ?>
                                    <option value="<?= htmlspecialchars($type) ?>"><?= htmlspecialchars($type) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Barangay Jurisdiction *</label>
                            <select name="barangay" id="modal_barangay" class="w-full bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-white rounded-xl px-3.5 py-2 text-xs md:text-sm font-semibold border-0 outline-none transition-all cursor-pointer" required>
                                <option value="" disabled selected>Select Barangay...</option>
                                <?php foreach ($all_barangays as $b): ?>
                                    <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">President / Lead Representative *</label>
                        <input type="text" name="president_name" id="modal_president_name" placeholder="e.g. Juan Dela Cruz" class="w-full bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2 text-xs md:text-sm font-semibold border-0 outline-none transition-all" required>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Contact Number</label>
                            <input type="text" name="contact_number" id="modal_contact_number" placeholder="09123456789" class="w-full bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2 text-xs md:text-sm font-semibold border-0 outline-none transition-all" required>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Official Email Address</label>
                            <input type="email" name="email" id="modal_email" placeholder="org@email.com" class="w-full bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2 text-xs md:text-sm font-semibold border-0 outline-none transition-all" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Accreditation Status</label>
                        <select name="status" id="modal_status" class="w-full bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-white rounded-xl px-3.5 py-2 text-xs md:text-sm font-semibold border-0 outline-none transition-all cursor-pointer">
                            <option value="Approved">Approved (Accredited)</option>
                            <option value="Pending">Pending (Under Review)</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>

                    <!-- File Attachments -->
                    <div class="bg-slate-50 dark:bg-zinc-950 p-3.5 rounded-2xl space-y-2 border-0">
                        <span class="block text-xs font-bold text-slate-700 dark:text-zinc-300 uppercase tracking-wider">Accreditation Dossiers & Files (Optional)</span>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1">Member Roster</label>
                                <input type="file" name="members_file" class="w-full text-xs text-slate-600 dark:text-zinc-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[11px] file:font-semibold file:bg-white dark:file:bg-zinc-900 file:text-slate-700 dark:file:text-zinc-300 cursor-pointer">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1">Constitution & Bylaws</label>
                                <input type="file" name="constitution_file" class="w-full text-xs text-slate-600 dark:text-zinc-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[11px] file:font-semibold file:bg-white dark:file:bg-zinc-900 file:text-slate-700 dark:file:text-zinc-300 cursor-pointer">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1">Registration Form</label>
                                <input type="file" name="registration_file" class="w-full text-xs text-slate-600 dark:text-zinc-400 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[11px] file:font-semibold file:bg-white dark:file:bg-zinc-900 file:text-slate-700 dark:file:text-zinc-300 cursor-pointer">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-2 pt-3 border-0 mt-3 flex-shrink-0">
                    <button type="button" onclick="closeOrgModal()" class="px-4 py-2 bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm hover:bg-slate-200 dark:hover:bg-zinc-700 transition-all tap-effect">
                        Cancel
                    </button>
                    <button type="submit" id="modalSubmitBtn" class="bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2 px-5 rounded-xl text-xs md:text-sm transition-all tap-effect btn-yellow-glow-r flex items-center gap-1.5 shadow-none">
                        <span>Save Organization</span>
                        <i class="ph-bold ph-check text-sm"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- DOSSIER VIEW MODAL (#viewOrgModal) -->
    <div id="viewOrgModal" class="fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeViewOrgModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 card-green-glow-bl rounded-[2rem] overflow-hidden max-w-lg w-full flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none max-h-[90vh] overflow-y-auto custom-scrollbar border-0" id="viewOrgModalContent">
            <!-- Header -->
            <div class="p-4 sm:p-5 flex justify-between items-center transition-colors flex-shrink-0 border-0">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-lg font-bold">
                        <i class="ph-fill ph-users-three"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight" id="view_org_name">Organization Details</h3>
                        <p class="text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider" id="view_org_type">YORP Dossier</p>
                    </div>
                </div>
                <button type="button" onclick="closeViewOrgModal()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-colors tap-effect">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Content -->
            <div class="p-4 sm:p-6 pt-0 space-y-3.5 text-xs md:text-sm overflow-y-auto custom-scrollbar">
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-slate-50 dark:bg-zinc-950 p-3 rounded-2xl border-0">
                        <span class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-0.5">Barangay</span>
                        <span class="font-bold text-slate-800 dark:text-white" id="view_barangay">-</span>
                    </div>
                    <div class="bg-slate-50 dark:bg-zinc-950 p-3 rounded-2xl border-0">
                        <span class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-0.5">Status</span>
                        <span class="font-black" id="view_status">-</span>
                    </div>
                </div>

                <div class="bg-slate-50 dark:bg-zinc-950 p-3.5 rounded-2xl space-y-1.5 border-0">
                    <span class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">Leadership & Contact</span>
                    <p class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                        <i class="ph-bold ph-user text-green-600 dark:text-green-500"></i>
                        <span id="view_president">-</span>
                    </p>
                    <p class="text-slate-600 dark:text-zinc-400 flex items-center gap-1.5">
                        <i class="ph-bold ph-phone text-slate-400 dark:text-zinc-500"></i>
                        <span id="view_contact">-</span>
                    </p>
                    <p class="text-slate-600 dark:text-zinc-400 flex items-center gap-1.5">
                        <i class="ph-bold ph-envelope text-slate-400 dark:text-zinc-500"></i>
                        <span id="view_email">-</span>
                    </p>
                </div>

                <div class="bg-slate-50 dark:bg-zinc-950 p-3.5 rounded-2xl space-y-2 border-0">
                    <span class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">Accreditation Files</span>
                    <div class="flex flex-col gap-1.5" id="view_files_container">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 sm:p-5 pt-0 flex items-center justify-end border-0">
                <button type="button" onclick="closeViewOrgModal()" class="px-4 py-2 bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm hover:bg-slate-200 dark:hover:bg-zinc-700 transition-all tap-effect">
                    Close Dossier
                </button>
            </div>
        </div>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Android Style - Borderless Design) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 h-[68px] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-2xl z-50 flex justify-around items-center px-2 pb-[env(safe-area-inset-bottom)] transition-colors border-0">
        <a href="tala_dashboard.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
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
        <a href="tala_directory.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-address-book text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Directory</span>
        </a>
        <a href="admin_yorp.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-users-three text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">YORP</span>
        </a>
    </nav>

    <!-- JavaScript Handlers -->
    <script>
        const orgModal = document.getElementById('orgModal');
        const orgModalContent = document.getElementById('orgModalContent');
        const viewOrgModal = document.getElementById('viewOrgModal');
        const viewOrgModalContent = document.getElementById('viewOrgModalContent');

        function openOrgModal() {
            document.getElementById('yorpForm').reset();
            document.getElementById('modalTitle').innerText = 'Accredit Youth Organization';
            document.getElementById('modalSubtitle').innerText = 'NYC YORP Registry Entry';
            document.getElementById('modal_org_id').value = '';
            document.getElementById('modalSubmitBtn').innerHTML = '<span>Save Organization</span><i class="ph-bold ph-check text-sm"></i>';

            orgModal.classList.remove('hidden');
            orgModal.classList.add('flex');
            setTimeout(() => {
                orgModal.classList.remove('opacity-0');
                orgModal.classList.add('opacity-100');
                orgModalContent.classList.remove('scale-95', 'opacity-0');
                orgModalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function editOrg(org) {
            document.getElementById('modalTitle').innerText = 'Update Organization';
            document.getElementById('modalSubtitle').innerText = 'Edit YORP Profile & Compliance';
            document.getElementById('modal_org_id').value = org.id;
            document.getElementById('modal_org_name').value = org.org_name;
            document.getElementById('modal_org_type').value = org.org_type;
            document.getElementById('modal_barangay').value = org.barangay;
            document.getElementById('modal_president_name').value = org.president_name;
            document.getElementById('modal_contact_number').value = org.contact_number;
            document.getElementById('modal_email').value = org.email;
            document.getElementById('modal_status').value = org.status;
            document.getElementById('modalSubmitBtn').innerHTML = '<span>Update Organization</span><i class="ph-bold ph-pencil-simple text-sm"></i>';

            orgModal.classList.remove('hidden');
            orgModal.classList.add('flex');
            setTimeout(() => {
                orgModal.classList.remove('opacity-0');
                orgModal.classList.add('opacity-100');
                orgModalContent.classList.remove('scale-95', 'opacity-0');
                orgModalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeOrgModal() {
            orgModal.classList.remove('opacity-100');
            orgModal.classList.add('opacity-0');
            orgModalContent.classList.remove('scale-100', 'opacity-100');
            orgModalContent.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                orgModal.classList.remove('flex');
                orgModal.classList.add('hidden');
            }, 200);
        }

        function viewOrg(org) {
            document.getElementById('view_org_name').innerText = org.org_name;
            document.getElementById('view_org_type').innerText = org.org_type + ' Organization';
            document.getElementById('view_barangay').innerText = org.barangay;
            document.getElementById('view_president').innerText = org.president_name;
            document.getElementById('view_contact').innerText = org.contact_number;
            document.getElementById('view_email').innerText = org.email;

            const statusEl = document.getElementById('view_status');
            statusEl.innerText = org.status;
            if (org.status === 'Approved') {
                statusEl.className = 'font-black text-green-600 dark:text-green-400';
            } else if (org.status === 'Pending') {
                statusEl.className = 'font-black text-amber-600 dark:text-amber-400';
            } else {
                statusEl.className = 'font-black text-red-600 dark:text-red-400';
            }

            const filesContainer = document.getElementById('view_files_container');
            filesContainer.innerHTML = '';
            let fileCount = 0;

            if (org.members_file_path) {
                filesContainer.innerHTML += `
                    <a href="${org.members_file_path}" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 dark:text-blue-400 hover:underline">
                        <i class="ph-bold ph-file-text"></i> Download Member Roster
                    </a>`;
                fileCount++;
            }
            if (org.constitution_file_path) {
                filesContainer.innerHTML += `
                    <a href="${org.constitution_file_path}" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-bold text-green-600 dark:text-green-400 hover:underline">
                        <i class="ph-bold ph-scroll"></i> Download Constitution & Bylaws
                    </a>`;
                fileCount++;
            }
            if (org.registration_file_path) {
                filesContainer.innerHTML += `
                    <a href="${org.registration_file_path}" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-bold text-purple-600 dark:text-purple-400 hover:underline">
                        <i class="ph-bold ph-file"></i> Download Official Registration Form
                    </a>`;
                fileCount++;
            }

            if (fileCount === 0) {
                filesContainer.innerHTML = '<span class="text-xs text-slate-400 dark:text-zinc-500 italic">No files submitted yet.</span>';
            }

            viewOrgModal.classList.remove('hidden');
            viewOrgModal.classList.add('flex');
            setTimeout(() => {
                viewOrgModal.classList.remove('opacity-0');
                viewOrgModal.classList.add('opacity-100');
                viewOrgModalContent.classList.remove('scale-95', 'opacity-0');
                viewOrgModalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeViewOrgModal() {
            viewOrgModal.classList.remove('opacity-100');
            viewOrgModal.classList.add('opacity-0');
            viewOrgModalContent.classList.remove('scale-100', 'opacity-100');
            viewOrgModalContent.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                viewOrgModal.classList.remove('flex');
                viewOrgModal.classList.add('hidden');
            }, 200);
        }

        function toggleMobileMenu() {
            const sidebar = document.getElementById('tala-admin-sidebar') || document.getElementById('tala-bulletproof-sk-sidebar') || document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            if (sidebar && overlay) {
                if (sidebar.classList.contains('mobile-open') || !sidebar.classList.contains('-translate-x-full')) {
                    sidebar.classList.remove('mobile-open');
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                } else {
                    sidebar.classList.add('mobile-open');
                    sidebar.classList.remove('-translate-x-full');
                    overlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            }
        }

        // Header Dark Mode Toggle Logic
        function syncHeaderThemeIcon(isDark) {
            const icon = document.getElementById('headerThemeIcon');
            if (icon) {
                if (isDark) {
                    icon.className = 'ph-bold ph-sun text-base sm:text-lg text-amber-400 group-hover:rotate-45 transition-transform duration-200';
                } else {
                    icon.className = 'ph-bold ph-moon text-base sm:text-lg text-slate-500 dark:text-zinc-400 group-hover:-rotate-12 transition-transform duration-200';
                }
            }
        }

        function toggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            localStorage.theme = isDark ? 'dark' : 'light';
            syncHeaderThemeIcon(isDark);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const isDark = document.documentElement.classList.contains('dark');
            syncHeaderThemeIcon(isDark);
        });

        const themeObserver = new MutationObserver(() => {
            syncHeaderThemeIcon(document.documentElement.classList.contains('dark'));
        });
        themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        // Close modals on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeOrgModal();
                closeViewOrgModal();
            }
        });
    </script>

    <?php include_once 'genesis_widget.php'; ?>
</body>
</html>