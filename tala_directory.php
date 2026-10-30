<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'db_connect.php';

// AJAX: FETCH SINGLE PROFILE
if (isset($_GET['ajax_view_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM youth_profiles WHERE youth_id = :id LIMIT 1");
        $stmt->execute([':id' => $_GET['ajax_view_id']]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($profile ? $profile : ['error' => 'Record not found']);
    } catch (Exception $e) {
        ob_clean();
        header('Content-Type: application/json', true, 500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Strict Authorization Check
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'sub-admin' && $_SESSION['role'] !== 'sk_admin')) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$search = trim($_GET['search'] ?? '');
$brgy_filter = trim($_GET['barangay'] ?? '');
$event_filter = trim($_GET['event'] ?? '');
$tab = trim($_GET['tab'] ?? 'all');

// If user is sub-admin/sk_admin, scope strictly to their assigned barangay
if ($role !== 'admin') {
    $assigned_brgy = $_SESSION['barangay_assigned'] ?? $_SESSION['admin_barangay'] ?? '';
    if (!empty($assigned_brgy)) {
        $brgy_filter = $assigned_brgy;
    }
}

// Pagination Controls
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$valid_limits = [10, 20, 30, 40, 50];
if (!in_array($limit, $valid_limits)) $limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Filter Clauses Construction
$where_clauses = [];
$params = [];

if (!empty($search)) { 
    $where_clauses[] = "(full_name LIKE :search OR youth_id LIKE :search)"; 
    $params[':search'] = "%{$search}%"; 
}

if (!empty($brgy_filter) && $brgy_filter !== 'All') { 
    $where_clauses[] = "barangay = :brgy"; 
    $params[':brgy'] = $brgy_filter; 
}

if ($event_filter === 'None') {
    $where_clauses[] = "(event_purpose IS NULL OR event_purpose = '' OR event_purpose = 'None')";
} elseif (!empty($event_filter)) { 
    $where_clauses[] = "event_purpose = :event"; 
    $params[':event'] = $event_filter; 
}

// Tab Filter
if ($tab === 'verified') {
    $where_clauses[] = "status = 'Verified' AND record_type = 'Profile'";
} elseif ($tab === 'profiles') {
    $where_clauses[] = "record_type = 'Profile'";
} elseif ($tab === 'events') {
    $where_clauses[] = "(event_purpose IS NOT NULL AND event_purpose != '' AND event_purpose != 'None' AND event_purpose != 'General Profiling')";
} elseif ($tab === 'registry') {
    $where_clauses[] = "record_type = 'Registry'";
}

$base_subquery = "
    SELECT youth_id, full_name, barangay, event_purpose, status, created_at, 'Profile' AS record_type FROM youth_profiles 
    UNION ALL 
    SELECT 'Legacy' AS youth_id, full_name, barangay, 'None' AS event_purpose, 'Unprofiled' AS status, created_at, 'Registry' AS record_type FROM youth_registry
";

$where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Count Query
$count_query = "SELECT COUNT(*) FROM (" . $base_subquery . ") AS c" . $where_sql;
$stmt_count = $pdo->prepare($count_query);
foreach ($params as $key => $val) { 
    $stmt_count->bindValue($key, $val); 
}
$stmt_count->execute();
$total_records = (int)$stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Main Query with Pagination
$main_query = "SELECT * FROM (" . $base_subquery . ") AS c" . $where_sql . " ORDER BY (record_type = 'Profile') DESC, created_at DESC, full_name ASC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($main_query);
foreach ($params as $key => $val) { 
    $stmt->bindValue($key, $val); 
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$youths = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Scoped Telemetry Counters for KPI Badges & Quick Pills
$scope_where = "";
$scope_params = [];
if (!empty($brgy_filter) && $brgy_filter !== 'All') {
    $scope_where = " WHERE barangay = ?";
    $scope_params = [$brgy_filter];
}

$stmt_p = $pdo->prepare("SELECT COUNT(*) FROM youth_profiles" . $scope_where);
$stmt_p->execute($scope_params);
$total_profiles_count = (int)$stmt_p->fetchColumn();

$stmt_r = $pdo->prepare("SELECT COUNT(*) FROM youth_registry" . $scope_where);
$stmt_r->execute($scope_params);
$total_registry_count = (int)$stmt_r->fetchColumn();

$total_combined_count = $total_profiles_count + $total_registry_count;

$v_where = !empty($scope_where) ? $scope_where . " AND status = 'Verified'" : " WHERE status = 'Verified'";
$stmt_v = $pdo->prepare("SELECT COUNT(*) FROM youth_profiles" . $v_where);
$stmt_v->execute($scope_params);
$total_verified_count = (int)$stmt_v->fetchColumn();

$e_where = !empty($scope_where) 
    ? $scope_where . " AND (event_purpose IS NOT NULL AND event_purpose != '' AND event_purpose != 'None' AND event_purpose != 'General Profiling')" 
    : " WHERE (event_purpose IS NOT NULL AND event_purpose != '' AND event_purpose != 'None' AND event_purpose != 'General Profiling')";
$stmt_e = $pdo->prepare("SELECT COUNT(*) FROM youth_profiles" . $e_where);
$stmt_e->execute($scope_params);
$total_events_count = (int)$stmt_e->fetchColumn();

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

if (!function_exists('buildUrl')) {
    function buildUrl($params_to_update) {
        $all_params = $_GET;
        foreach ($params_to_update as $key => $val) { 
            if ($val === null || $val === '' || ($key === 'tab' && $val === 'all') || ($key === 'page' && (int)$val === 1)) {
                unset($all_params[$key]);
            } else {
                $all_params[$key] = $val;
            }
        }
        return 'tala_directory.php' . (!empty($all_params) ? '?' . http_build_query($all_params) : '');
    }
}

$flash_msg = $_SESSION['flash_msg'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Youth Directory | TALA OS</title>
    
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
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }
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
        
        /* Modern Clean Glass Card: ZERO Outside Borders & ZERO Shadows */
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
        
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

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
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- Appropriate Role Sidebar -->
    <?php include ($role === 'admin' ? 'sidebar_admin.php' : 'sidebar_sk.php'); ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA (Matching manage_announcements.php) -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px and fits viewport smoothly -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-3 md:space-y-4">
            
            <!-- HEADER SECTION (H1 + 3 Unified Green KPI Cards + Header Dark Mode Toggle) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <div>
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                            Youth Directory
                        </h1>
                        <p class="text-xs md:text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                            Official youth registry, event accreditation & demographic profiles
                        </p>
                    </div>
                </div>

                <!-- Header KPI Badges & Controls -->
                <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Total Profiles -->
                    <div class="glass-card card-green-glow-bl px-2.5 sm:px-3.5 py-1.5 rounded-xl flex items-center gap-2 sm:gap-2.5 flex-1 sm:flex-none min-w-0 transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs sm:text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-bold ph-identification-card text-sm sm:text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[9px] sm:text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Profiles</span>
                            <span class="text-sm sm:text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight truncate"><?= number_format($total_profiles_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Verified Citizens -->
                    <div class="glass-card card-green-glow-bl px-2.5 sm:px-3.5 py-1.5 rounded-xl flex items-center gap-2 sm:gap-2.5 flex-1 sm:flex-none min-w-0 transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs sm:text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-bold ph-seal-check text-sm sm:text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[9px] sm:text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Verified Citizens</span>
                            <span class="text-sm sm:text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight truncate"><?= number_format($total_verified_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Filtered Matches -->
                    <div class="glass-card card-green-glow-bl px-2.5 sm:px-3.5 py-1.5 rounded-xl flex items-center gap-2 sm:gap-2.5 flex-1 sm:flex-none min-w-0 transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs sm:text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-bold ph-funnel text-sm sm:text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[9px] sm:text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Matches</span>
                            <span class="text-sm sm:text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight truncate"><?= number_format($total_records) ?></span>
                        </div>
                    </div>

                    <!-- Header Dark Mode Toggle Button -->
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

            <!-- FILTER, SEARCH & ACTION CONTROL BAR (Matching manage_disclosure.php) -->
            <div class="glass-card card-green-glow-bl rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-2.5">
                
                <!-- Quick Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full xl:w-auto custom-scrollbar py-0.5 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['val' => 'all', 'label' => 'All Records', 'count' => $total_combined_count, 'active' => ($tab === 'all' || empty($tab))],
                        ['val' => 'verified', 'label' => 'Verified Youths', 'count' => $total_verified_count, 'active' => ($tab === 'verified')],
                        ['val' => 'profiles', 'label' => 'Official Profiles', 'count' => $total_profiles_count, 'active' => ($tab === 'profiles')],
                        ['val' => 'events', 'label' => 'Event Passes', 'count' => $total_events_count, 'active' => ($tab === 'events')],
                        ['val' => 'registry', 'label' => 'Legacy Registry', 'count' => $total_registry_count, 'active' => ($tab === 'registry')],
                    ];
                    foreach ($filter_tabs as $t):
                        $isActive = $t['active'];
                        $pillClass = $isActive 
                            ? 'bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white btn-yellow-glow-r' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        
                        $pillUrl = buildUrl(['tab' => $t['val'], 'page' => 1]);
                    ?>
                        <a href="<?= $pillUrl ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span><?= $t['label'] ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 dark:bg-black/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?>"><?= $t['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Dropdowns, Search & Action Button Cluster -->
                <div class="flex flex-wrap items-center gap-2 w-full xl:w-auto">
                    <!-- Barangay Dropdown (Admin only) or Scoped Badge -->
                    <?php if ($role === 'admin'): ?>
                    <form method="GET" class="w-full sm:w-auto sm:flex-1 md:w-44 min-w-[140px]">
                        <?php if ($tab !== 'all'): ?><input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>"><?php endif; ?>
                        <?php if (!empty($event_filter)): ?><input type="hidden" name="event" value="<?= htmlspecialchars($event_filter) ?>"><?php endif; ?>
                        <?php if (!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <?php if ($limit !== 10): ?><input type="hidden" name="limit" value="<?= $limit ?>"><?php endif; ?>
                        <select name="barangay" onchange="this.form.submit()" class="w-full bg-slate-50 dark:bg-zinc-900/60 text-slate-800 dark:text-zinc-200 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="">All 46 Barangays</option>
                            <?php foreach ($all_barangays as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= $brgy_filter === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php else: ?>
                    <div class="px-3 py-1.5 bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 rounded-xl text-xs font-bold flex items-center gap-1.5">
                        <i class="ph-bold ph-map-pin text-green-600 dark:text-green-500"></i>
                        <span><?= htmlspecialchars($brgy_filter) ?></span>
                    </div>
                    <?php endif; ?>

                    <!-- Event Pass Filter Dropdown -->
                    <form method="GET" class="w-full sm:w-auto sm:flex-1 md:w-40 min-w-[130px]">
                        <?php if ($tab !== 'all'): ?><input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>"><?php endif; ?>
                        <?php if ($role === 'admin' && !empty($brgy_filter) && $brgy_filter !== 'All'): ?><input type="hidden" name="barangay" value="<?= htmlspecialchars($brgy_filter) ?>"><?php endif; ?>
                        <?php if (!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <?php if ($limit !== 10): ?><input type="hidden" name="limit" value="<?= $limit ?>"><?php endif; ?>
                        <select name="event" onchange="this.form.submit()" class="w-full bg-slate-50 dark:bg-zinc-900/60 text-slate-800 dark:text-zinc-200 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="">All Event Passes</option>
                            <option value="General Profiling" <?= $event_filter === 'General Profiling' ? 'selected' : '' ?>>General Profiling</option>
                            <option value="Raffle" <?= $event_filter === 'Raffle' ? 'selected' : '' ?>>Raffle</option>
                            <option value="Gabi ng Parangal" <?= $event_filter === 'Gabi ng Parangal' ? 'selected' : '' ?>>Gabi ng Parangal</option>
                            <option value="MOKT" <?= $event_filter === 'MOKT' ? 'selected' : '' ?>>MOKT</option>
                            <option value="Marenu" <?= $event_filter === 'Marenu' ? 'selected' : '' ?>>Marenu</option>
                            <option value="BIDA" <?= $event_filter === 'BIDA' ? 'selected' : '' ?>>BIDA</option>
                            <option value="None" <?= $event_filter === 'None' ? 'selected' : '' ?>>No Event Pass</option>
                        </select>
                    </form>

                    <!-- Search Input -->
                    <form method="GET" class="relative w-full sm:w-auto sm:flex-1 md:w-52 min-w-[160px]">
                        <?php if ($tab !== 'all'): ?><input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>"><?php endif; ?>
                        <?php if ($role === 'admin' && !empty($brgy_filter) && $brgy_filter !== 'All'): ?><input type="hidden" name="barangay" value="<?= htmlspecialchars($brgy_filter) ?>"><?php endif; ?>
                        <?php if (!empty($event_filter)): ?><input type="hidden" name="event" value="<?= htmlspecialchars($event_filter) ?>"><?php endif; ?>
                        <?php if ($limit !== 10): ?><input type="hidden" name="limit" value="<?= $limit ?>"><?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, TALA ID..." class="w-full bg-slate-50 dark:bg-zinc-900/60 text-slate-800 dark:text-zinc-200 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none transition-colors border-0">
                    </form>

                    <!-- Action Buttons Cluster -->
                    <div class="flex items-center gap-1.5 w-full sm:w-auto ml-auto sm:ml-0 flex-shrink-0">
                        <?php if (!empty($search) || ($role === 'admin' && !empty($brgy_filter) && $brgy_filter !== 'All') || !empty($event_filter) || $tab !== 'all'): ?>
                            <a href="tala_directory.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Filters">
                                <i class="ph-bold ph-x text-xs"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Print Directory Button -->
                        <button type="button" onclick="window.print()" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 font-bold h-[30px] px-2.5 rounded-xl text-xs gap-1.5 transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Print Directory">
                            <i class="ph-bold ph-printer text-xs"></i>
                            <span class="hidden sm:inline">Print</span>
                        </button>

                        <!-- Register Youth Action Button -->
                        <a href="public_registration.php" target="_blank" class="flex-1 sm:flex-none px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect btn-yellow-glow-r flex items-center justify-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group cursor-pointer">
                            <i class="ph-bold ph-user-plus text-xs transition-transform duration-300 group-hover:scale-110"></i>
                            <span>Register Youth</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- CONSOLIDATED MINIMAL MASTER DIRECTORY TABLE CARD -->
            <div class="glass-card card-green-glow-bl rounded-2xl overflow-hidden flex flex-col flex-1 min-h-[350px] lg:min-h-0 transition-colors">
                
                <!-- Minimal Header Strip & Rows Selector -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 transition-colors flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Official Youth Registry</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                            <?= number_format($total_records) ?> Records
                        </span>
                    </div>

                    <!-- Limit Selector Pills -->
                    <div class="flex items-center gap-2 self-end sm:self-auto">
                        <span class="text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider hidden sm:inline">Rows:</span>
                        <div class="flex items-center bg-slate-100 dark:bg-zinc-800 p-0.5 rounded-xl text-xs font-bold">
                            <?php foreach($valid_limits as $l): ?>
                                <a href="<?= buildUrl(['limit' => $l, 'page' => 1]) ?>" class="px-2.5 py-0.5 rounded-lg text-[11px] font-bold transition-colors <?= $limit === $l ? 'bg-white dark:bg-zinc-900 text-slate-900 dark:text-white' : 'text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white' ?>">
                                    <?= $l ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[800px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider bg-slate-50/90 dark:bg-zinc-900/90 backdrop-blur-sm transition-colors">
                                <th class="py-2.5 px-4 md:px-5">TALA ID</th>
                                <th class="py-2.5 px-4 md:px-5">Full Name</th>
                                <th class="py-2.5 px-4 md:px-5">Barangay</th>
                                <th class="py-2.5 px-4 md:px-5">Event Pass</th>
                                <th class="py-2.5 px-4 md:px-5">Status</th>
                                <th class="py-2.5 px-4 md:px-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs md:text-sm font-medium divide-y divide-slate-100/80 dark:divide-zinc-800/60 transition-colors">
                            <?php if (empty($youths)): ?>
                                <tr>
                                    <td colspan="6" class="py-14 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <div class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-2 flex items-center justify-center">
                                            <img src="mascot_question.png" alt="No Records" class="max-h-full max-w-full object-contain pointer-events-none hover:scale-105 transition-transform duration-300">
                                        </div>
                                        <p class="font-extrabold text-sm text-slate-800 dark:text-zinc-200">No Youth Records Found</p>
                                        <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium mt-0.5">Try clearing filters or search by another parameter.</p>
                                        <?php if (!empty($search) || ($role === 'admin' && !empty($brgy_filter) && $brgy_filter !== 'All') || !empty($event_filter) || $tab !== 'all'): ?>
                                            <a href="tala_directory.php" class="mt-3 inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
                                                <span>Clear Filters</span>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($youths as $y): ?>
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-800/40 transition-colors group">
                                    <td class="py-2.5 px-4 md:px-5 whitespace-nowrap">
                                        <?php if($y['record_type'] === 'Registry'): ?>
                                            <span class="font-semibold text-slate-500 dark:text-zinc-400 bg-slate-100 dark:bg-zinc-800 px-2 py-0.5 rounded-lg text-[11px] uppercase tracking-wider">Unprofiled</span>
                                        <?php else: ?>
                                            <span class="font-mono font-bold text-slate-900 dark:text-white bg-slate-100 dark:bg-zinc-800 px-2 py-0.5 rounded-lg text-xs"><?= htmlspecialchars($y['youth_id'] ?? '') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2.5 px-4 md:px-5">
                                        <p class="font-bold text-slate-900 dark:text-white text-xs md:text-sm leading-snug transition-colors line-clamp-1">
                                            <?= htmlspecialchars($y['full_name'] ?? '') ?>
                                        </p>
                                    </td>
                                    <td class="py-2.5 px-4 md:px-5 whitespace-nowrap">
                                        <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300 px-2 py-0.5 rounded-lg text-[11px] font-bold uppercase tracking-wider transition-colors">
                                            <i class="ph-fill ph-map-pin text-green-600 dark:text-green-500 text-[10px]"></i> <?= htmlspecialchars($y['barangay'] ?? '') ?>
                                        </span>
                                    </td>
                                    <td class="py-2.5 px-4 md:px-5 whitespace-nowrap">
                                        <?php if(empty($y['event_purpose']) || $y['event_purpose'] === 'None'): ?>
                                            <span class="text-xs font-semibold text-slate-400 dark:text-zinc-500">-</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/30 px-2.5 py-0.5 rounded-lg">
                                                <i class="ph-bold ph-ticket"></i> <?= htmlspecialchars($y['event_purpose'] ?? '') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2.5 px-4 md:px-5 whitespace-nowrap">
                                        <?php if($y['status'] === 'Verified'): ?>
                                            <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-2.5 py-0.5 rounded-lg"><i class="ph-fill ph-check-circle"></i> Verified</span>
                                        <?php elseif($y['record_type'] === 'Registry'): ?>
                                            <span class="inline-flex items-center gap-1 text-xs font-bold text-slate-500 dark:text-zinc-400 bg-slate-100 dark:bg-zinc-800 px-2.5 py-0.5 rounded-lg"><i class="ph-fill ph-warning-circle text-slate-400"></i> Action Req</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/30 px-2.5 py-0.5 rounded-lg"><i class="ph-fill ph-clock"></i> <?= htmlspecialchars($y['status'] ?? '') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2.5 px-4 md:px-5 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1">
                                            <?php if($y['record_type'] === 'Profile'): ?>
                                                <button type="button" onclick="openViewModal('<?= htmlspecialchars($y['youth_id'] ?? '') ?>')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect" title="View Full Profile">
                                                    <i class="ph-bold ph-eye text-xs"></i>
                                                </button>
                                            <?php else: ?>
                                                <a href="public_registration.php?search=<?= urlencode($y['full_name'] ?? '') ?>" target="_blank" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-green-50 dark:hover:bg-green-500/10 transition-colors tap-effect" title="Complete Profile">
                                                    <i class="ph-bold ph-user-plus text-xs"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Bottom Pagination Bar -->
                <?php if($total_pages > 1): ?>
                    <div class="px-4 py-2.5 bg-white/40 dark:bg-zinc-900/40 flex flex-col sm:flex-row justify-between items-center gap-2 transition-colors flex-shrink-0">
                        <div class="text-xs text-slate-500 dark:text-zinc-400 font-medium">
                            Showing <?= $total_records > 0 ? min($offset + 1, $total_records) : 0 ?>–<?= min($offset + $limit, $total_records) ?> of <?= number_format($total_records) ?> records
                        </div>
                        <div class="flex items-center gap-1">
                            <?php if($page > 1): ?>
                                <a href="<?= buildUrl(['page' => $page - 1]) ?>" class="px-2.5 py-1 bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 rounded-xl text-xs font-bold text-slate-700 dark:text-zinc-300 transition-colors tap-effect flex items-center gap-1">
                                    <i class="ph-bold ph-caret-left"></i> Prev
                                </a>
                            <?php endif; ?>

                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            for($i = $start_page; $i <= $end_page; $i++): 
                            ?>
                                <a href="<?= buildUrl(['page' => $i]) ?>" class="w-7 h-7 flex items-center justify-center rounded-xl text-xs font-bold transition-colors tap-effect <?= $i === $page ? 'bg-[#15803d] text-white dark:bg-[#16a34a] btn-yellow-glow-r' : 'bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if($page < $total_pages): ?>
                                <a href="<?= buildUrl(['page' => $page + 1]) ?>" class="px-2.5 py-1 bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 rounded-xl text-xs font-bold text-slate-700 dark:text-zinc-300 transition-colors tap-effect flex items-center gap-1">
                                    Next <i class="ph-bold ph-caret-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="px-4 py-2.5 bg-white/40 dark:bg-zinc-900/40 flex justify-between items-center text-xs text-slate-500 dark:text-zinc-400 font-medium flex-shrink-0">
                        <span>Showing <strong class="text-slate-800 dark:text-zinc-200"><?= number_format($total_records) ?></strong> records</span>
                        <span class="hidden sm:inline">Youth Census Registry &bull; TALA OS</span>
                    </div>
                <?php endif; ?>

            </div>
            
        </div>
    </main>

    <!-- FULL PROFILE VIEW MODAL (Centered Spring Popup Modal - Matching manage_disclosure.php) -->
    <div id="viewModal" class="fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeViewModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 card-green-glow-bl rounded-[2rem] overflow-hidden max-w-4xl w-full flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none max-h-[90vh] overflow-y-auto custom-scrollbar border-0" id="modalContent">
            
            <!-- Modal Header -->
            <div class="p-4 sm:p-6 pb-3 flex items-start justify-between shrink-0 relative transition-colors">
                <div class="flex gap-3 sm:gap-4 items-center">
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-slate-100 dark:bg-zinc-800 overflow-hidden shrink-0 transition-colors">
                        <img id="m_photo" src="" alt="Photo" class="w-full h-full object-cover">
                    </div>
                    <div class="flex flex-col justify-center">
                        <div class="flex flex-wrap items-center gap-1.5 mb-1">
                            <span id="m_id" class="text-xs font-mono font-bold uppercase tracking-wider text-slate-600 dark:text-zinc-300 bg-slate-100 dark:bg-zinc-800 px-2 py-0.5 rounded-lg"></span>
                            <span id="m_status" class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded-lg"></span>
                        </div>
                        <h2 id="m_name" class="text-base sm:text-xl font-black tracking-tight text-slate-900 dark:text-white leading-tight"></h2>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 font-bold flex items-center gap-1 mt-0.5">
                            <i class="ph-fill ph-map-pin text-green-600 dark:text-green-500"></i> <span id="m_barangay"></span>
                        </p>
                    </div>
                </div>
                <button type="button" onclick="closeViewModal()" class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-white flex items-center justify-center transition-colors tap-effect">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Tab Switcher -->
            <div class="flex items-center gap-1.5 overflow-x-auto px-4 sm:px-6 py-2 shrink-0 hide-scrollbar transition-colors bg-slate-50/50 dark:bg-zinc-950/40">
                <button type="button" onclick="switchTab('tab-identity')" id="btn-identity" class="modal-tab-btn active px-3 py-1.5 text-xs font-bold rounded-xl transition-all tap-effect bg-[#15803d] text-white dark:bg-[#16a34a] btn-yellow-glow-r whitespace-nowrap">Identity & Contact</button>
                <button type="button" onclick="switchTab('tab-family')" id="btn-family" class="modal-tab-btn px-3 py-1.5 text-xs font-bold rounded-xl transition-all tap-effect bg-slate-100 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:text-white whitespace-nowrap">Family & Household</button>
                <button type="button" onclick="switchTab('tab-education')" id="btn-education" class="modal-tab-btn px-3 py-1.5 text-xs font-bold rounded-xl transition-all tap-effect bg-slate-100 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:text-white whitespace-nowrap">Education & Work</button>
                <button type="button" onclick="switchTab('tab-capacity')" id="btn-capacity" class="modal-tab-btn px-3 py-1.5 text-xs font-bold rounded-xl transition-all tap-effect bg-slate-100 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:text-white whitespace-nowrap">Skills & Programs</button>
            </div>

            <!-- Modal Content Body -->
            <div class="p-4 sm:p-6 overflow-y-auto flex-1 custom-scrollbar transition-colors">
                
                <!-- Tab: Identity -->
                <div id="tab-identity" class="modal-tab-content grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div class="bg-slate-50 dark:bg-zinc-950/70 rounded-2xl p-4 md:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-3 transition-colors border-0">
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Date of Birth</p><p id="m_dob" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Sex</p><p id="m_sex" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Gender Identity</p><p id="m_gender" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Civil Status</p><p id="m_civil" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Contact No.</p><p id="m_contact" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div class="col-span-2 sm:col-span-1"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Email</p><p id="m_email" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5 break-all"></p></div>
                        <div class="col-span-2 sm:col-span-3 pt-2"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Full Address</p><p id="m_address" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                    </div>
                    <div class="bg-slate-50 dark:bg-zinc-950/70 rounded-2xl p-4 space-y-3 transition-colors border-0">
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">PhilSys ID</p><p id="m_philsys" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 font-mono mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">PhilHealth No.</p><p id="m_philhealth" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 font-mono mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">PSA Birth Cert</p><p id="m_psa" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 font-mono mt-0.5"></p></div>
                        <div class="pt-1"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Registered Voter</p><p id="m_voter" class="text-xs md:text-sm font-bold mt-0.5"></p></div>
                    </div>
                </div>

                <!-- Tab: Family -->
                <div id="tab-family" class="modal-tab-content hidden grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="bg-slate-50 dark:bg-zinc-950/70 rounded-2xl p-4 space-y-3 transition-colors border-0">
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Father's Name</p><p id="m_father" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Mother's Name</p><p id="m_mother" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Guardian's Name</p><p id="m_guardian" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div class="pt-1"><p class="text-[10px] md:text-xs text-rose-500 font-bold uppercase tracking-wider">Emergency Contact</p><p id="m_emergency" class="text-xs md:text-sm font-bold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                    </div>
                    <div class="bg-slate-50 dark:bg-zinc-950/70 rounded-2xl p-4 grid grid-cols-2 gap-3 h-max transition-colors border-0">
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Household Size</p><p id="m_hhsize" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Income Bracket</p><p id="m_income" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div class="pt-1"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">4Ps Beneficiary</p><p id="m_4ps" class="text-xs md:text-sm font-bold mt-0.5"></p></div>
                        <div class="pt-1"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Solo Parent / Child</p><p id="m_solo" class="text-xs md:text-sm font-bold mt-0.5"></p></div>
                        <div class="col-span-2 pt-1"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">PWD in Household</p><p id="m_pwd" class="text-xs md:text-sm font-bold mt-0.5"></p></div>
                    </div>
                </div>

                <!-- Tab: Education & Work -->
                <div id="tab-education" class="modal-tab-content hidden grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="bg-slate-50 dark:bg-zinc-950/70 rounded-2xl p-4 grid grid-cols-2 gap-3 transition-colors border-0">
                        <div class="col-span-2"><p class="text-[10px] md:text-xs text-green-700 dark:text-green-400 font-black uppercase tracking-wider bg-green-500/10 w-max px-2.5 py-1 rounded-lg">Education Profile</p></div>
                        <div class="col-span-2"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Highest Attainment</p><p id="m_educ_lvl" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div class="col-span-2"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">School Name</p><p id="m_school" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Course/Track</p><p id="m_course" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Grade/Year</p><p id="m_grade" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">School ID</p><p id="m_schoolid" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Graduated?</p><p id="m_graduated" class="text-xs md:text-sm font-bold mt-0.5"></p></div>
                        <div class="pt-1"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">ALS Enrolled?</p><p id="m_als" class="text-xs md:text-sm font-bold mt-0.5"></p></div>
                        <div class="pt-1"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">OSY / Dropout?</p><p id="m_dropout" class="text-xs md:text-sm font-bold mt-0.5"></p></div>
                        <div class="col-span-2 pt-1"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Expected Graduation</p><p id="m_exp_grad" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                    </div>
                    <div class="bg-slate-50 dark:bg-zinc-950/70 rounded-2xl p-4 grid grid-cols-2 gap-3 h-max transition-colors border-0">
                        <div class="col-span-2"><p class="text-[10px] md:text-xs text-green-700 dark:text-green-400 font-black uppercase tracking-wider bg-green-500/10 w-max px-2.5 py-1 rounded-lg">Employment Profile</p></div>
                        <div class="col-span-2"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Status</p><p id="m_emp_status" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div class="col-span-2"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Occupation</p><p id="m_occupation" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div class="col-span-2"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Employer / Company</p><p id="m_employer" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div class="pt-1"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Monthly Income</p><p id="m_emp_income" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div class="pt-1"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">TESDA NC</p><p id="m_tesda" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                    </div>
                </div>

                <!-- Tab: Skills & Programs -->
                <div id="tab-capacity" class="modal-tab-content hidden grid grid-cols-1 gap-3">
                    <div class="bg-slate-50 dark:bg-zinc-950/70 rounded-2xl p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 transition-colors border-0">
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Declared Skills</p><p id="m_skills" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 leading-relaxed mt-1"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Organizations</p><p id="m_orgs" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 leading-relaxed mt-1"></p></div>
                        <div class="col-span-1 sm:col-span-2 pt-2"></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Gov Services Utilized</p><p id="m_gov" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Programs Availed</p><p id="m_availed" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div class="col-span-1 sm:col-span-2"><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Program Interests</p><p id="m_interests" class="text-xs md:text-sm font-semibold text-slate-800 dark:text-zinc-200 mt-0.5"></p></div>
                        <div class="col-span-1 sm:col-span-2 pt-2"></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Requested Event Pass</p><span id="m_event" class="inline-flex mt-1 items-center gap-1.5 text-xs font-bold text-green-700 dark:text-green-400 bg-green-500/10 px-2.5 py-1 rounded-lg"></span></div>
                        <div><p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">Internal Remarks</p><p id="m_remarks" class="text-xs md:text-sm font-semibold text-amber-600 dark:text-amber-400 mt-0.5"></p></div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Android Style - Borderless Design) -->
    <?php if ($role !== 'admin'): ?>
    <nav class="md:hidden fixed bottom-0 left-0 right-0 h-[68px] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-2xl z-50 flex justify-around items-center px-2 pb-[env(safe-area-inset-bottom)] transition-colors border-0">
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
        <a href="tala_directory.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-address-book text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Directory</span>
        </a>
        <a href="sk2_documents.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-files text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Docs</span>
        </a>
    </nav>
    <?php else: ?>
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
        <a href="tala_directory.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-address-book text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Directory</span>
        </a>
        <a href="manage_announcements.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-megaphone text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Announcements</span>
        </a>
    </nav>
    <?php endif; ?>

    <!-- UI Scripts -->
    <script>
        const viewModal = document.getElementById('viewModal');
        const modalContent = document.getElementById('modalContent');

        function switchTab(tabId) {
            document.querySelectorAll('.modal-tab-content').forEach(t => t.classList.add('hidden'));
            document.querySelectorAll('.modal-tab-btn').forEach(b => {
                b.classList.remove('bg-[#15803d]', 'text-white', 'dark:bg-[#16a34a]', 'btn-yellow-glow-r');
                b.classList.add('bg-slate-100', 'text-slate-600', 'dark:bg-zinc-800', 'dark:text-zinc-400');
            });
            document.getElementById(tabId).classList.remove('hidden');
            const btn = document.getElementById('btn-' + tabId.replace('tab-', ''));
            if (btn) {
                btn.classList.remove('bg-slate-100', 'text-slate-600', 'dark:bg-zinc-800', 'dark:text-zinc-400');
                btn.classList.add('bg-[#15803d]', 'text-white', 'dark:bg-[#16a34a]', 'btn-yellow-glow-r');
            }
        }

        async function openViewModal(youthId) {
            switchTab('tab-identity');
            viewModal.classList.remove('hidden');
            viewModal.classList.add('flex');
            setTimeout(() => {
                viewModal.classList.remove('opacity-0');
                viewModal.classList.add('opacity-100');
                modalContent.classList.remove('scale-95', 'opacity-0');
                modalContent.classList.add('scale-100', 'opacity-100');
            }, 10);

            try {
                const response = await fetch('?ajax_view_id=' + encodeURIComponent(youthId));
                const rawText = await response.text();
                let data;
                try { data = JSON.parse(rawText); } catch(e) { alert("System error reading data."); return; }

                if(data && !data.error) {
                    const safe = (val) => (val !== null && val !== undefined && val !== '') ? val : '<span class="text-slate-400 dark:text-zinc-600 italic font-normal">N/A</span>';
                    const safeFlag = (val, trueWord='Yes') => (val === trueWord || val === 1 || val === '1') ? `<span class="text-emerald-600 dark:text-emerald-400 font-bold">${val === 1 || val === '1' ? 'Yes' : val}</span>` : safe(val === 0 || val === '0' ? 'No' : val);
                    const dangerFlag = (val, dangerWord='Yes') => (val === dangerWord || val === 1 || val === '1') ? `<span class="text-rose-600 dark:text-rose-400 font-bold">${val === 1 || val === '1' ? 'Yes' : val}</span>` : safe(val === 0 || val === '0' ? 'No' : val);

                    document.getElementById('m_photo').src = data.photo_path || 'https://cdn-icons-png.flaticon.com/512/847/847969.png';
                    document.getElementById('m_id').innerText = data.youth_id || '';
                    document.getElementById('m_name').innerText = data.full_name || '';
                    document.getElementById('m_barangay').innerHTML = safe(data.barangay);
                    document.getElementById('m_status').innerHTML = `<i class="ph-fill ph-check-circle"></i> ${data.status || 'Verified'}`;
                    
                    document.getElementById('m_dob').innerHTML = safe(data.birthdate);
                    document.getElementById('m_sex').innerHTML = safe(data.sex);
                    document.getElementById('m_gender').innerHTML = safe(data.gender_identity);
                    document.getElementById('m_civil').innerHTML = safe(data.civil_status);
                    document.getElementById('m_contact').innerHTML = safe(data.contact_number);
                    document.getElementById('m_email').innerHTML = safe(data.email);
                    document.getElementById('m_address').innerHTML = safe(data.address);
                    document.getElementById('m_philsys').innerHTML = safe(data.philsys_id);
                    document.getElementById('m_philhealth').innerHTML = safe(data.philhealth_no);
                    document.getElementById('m_psa').innerHTML = safe(data.psa_birth_cert);
                    document.getElementById('m_voter').innerHTML = safeFlag(data.is_voter);

                    document.getElementById('m_father').innerHTML = safe(data.father_name);
                    document.getElementById('m_mother').innerHTML = safe(data.mother_name);
                    document.getElementById('m_guardian').innerHTML = safe(data.guardian_name || data.parent_guardian);
                    document.getElementById('m_emergency').innerHTML = safe(data.emergency_contact);
                    document.getElementById('m_hhsize').innerHTML = safe(data.household_size);
                    document.getElementById('m_income').innerHTML = safe(data.income_bracket);
                    document.getElementById('m_4ps').innerHTML = safeFlag(data.is_4ps);
                    document.getElementById('m_solo').innerHTML = safeFlag(data.is_solo_parent);
                    document.getElementById('m_pwd').innerHTML = safeFlag(data.is_pwd_household);

                    document.getElementById('m_educ_lvl').innerHTML = safe(data.education_level);
                    document.getElementById('m_school').innerHTML = safe(data.school_name);
                    document.getElementById('m_course').innerHTML = safe(data.course);
                    document.getElementById('m_grade').innerHTML = safe(data.grade_level);
                    document.getElementById('m_schoolid').innerHTML = safe(data.school_id);
                    document.getElementById('m_graduated').innerHTML = safeFlag(data.graduated);
                    document.getElementById('m_als').innerHTML = safeFlag(data.is_als);
                    document.getElementById('m_dropout').innerHTML = dangerFlag(data.is_dropout);
                    document.getElementById('m_exp_grad').innerHTML = safe(data.expected_graduation);

                    document.getElementById('m_emp_status').innerHTML = safe(data.employment_status);
                    document.getElementById('m_occupation').innerHTML = safe(data.occupation);
                    document.getElementById('m_employer').innerHTML = safe(data.employer);
                    document.getElementById('m_emp_income').innerHTML = safe(data.monthly_income);
                    document.getElementById('m_tesda').innerHTML = safe(data.tesda_nc);

                    document.getElementById('m_skills').innerHTML = safe(data.skills);
                    document.getElementById('m_orgs').innerHTML = safe(data.organizations);
                    document.getElementById('m_gov').innerHTML = safe(data.gov_services);
                    document.getElementById('m_availed').innerHTML = safe(data.programs_availed);
                    document.getElementById('m_interests').innerHTML = safe(data.program_interests);
                    document.getElementById('m_event').innerHTML = `<i class="ph-bold ph-ticket"></i> ` + (data.event_purpose ? safe(data.event_purpose) : 'None');
                    document.getElementById('m_remarks').innerHTML = safe(data.remarks);
                } else {
                    alert(data.error || 'Failed to fetch details.');
                    closeViewModal();
                }
            } catch (err) {
                alert('An error occurred while fetching the record.');
            }
        }

        function closeViewModal() {
            viewModal.classList.remove('opacity-100');
            viewModal.classList.add('opacity-0');
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');
            setTimeout(() => { 
                viewModal.classList.remove('flex');
                viewModal.classList.add('hidden'); 
            }, 200);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !viewModal.classList.contains('hidden')) {
                closeViewModal();
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

        // Sync if class changes from another theme button
        const themeObserver = new MutationObserver(() => {
            syncHeaderThemeIcon(document.documentElement.classList.contains('dark'));
        });
        themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    </script>
    
    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>