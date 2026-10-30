<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Strict Admin Access Control
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Flash Messages
$flash_msg = $_SESSION['flash_msg'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// Auto-upgrade budget table for status column if needed
try {
    $cols = $pdo->query("SHOW COLUMNS FROM budget_monitoring")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('status', $cols)) {
        $pdo->exec("ALTER TABLE budget_monitoring ADD COLUMN status VARCHAR(50) DEFAULT 'Planning'");
    }
} catch (Exception $e) {}

// Query parameters preservation
$filter_barangay = trim($_GET['barangay'] ?? '');
$status_filter = trim($_GET['status'] ?? 'All');
$search = trim($_GET['search'] ?? '');

$queryParams = [];
if (!empty($filter_barangay) && $filter_barangay !== 'All') $queryParams['barangay'] = $filter_barangay;
if (!empty($status_filter) && $status_filter !== 'All') $queryParams['status'] = $status_filter;
if (!empty($search)) $queryParams['search'] = $search;
$filterQuery = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';

// --- CRUD: CREATE & UPDATE ---
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['save_budget'])) {
    $id = $_POST['budget_id'] ?? null;
    $barangay = trim($_POST['barangay'] ?? '');
    $program = trim($_POST['program'] ?? '');
    $project = trim($_POST['project_activity'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $status = trim($_POST['status'] ?? 'Planning');
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? '0');

    if (!empty($id)) {
        // UPDATE
        $stmt = $pdo->prepare("UPDATE budget_monitoring SET barangay=?, program=?, project_activity=?, duration=?, amount=?, status=? WHERE id=?");
        $stmt->execute([$barangay, $program, $project, $duration, $amount, $status, $id]);
        $_SESSION['flash_msg'] = "Budget allocation for " . htmlspecialchars($barangay) . " updated successfully.";
        $_SESSION['flash_type'] = "success";
    } else {
        // CREATE
        $stmt = $pdo->prepare("INSERT INTO budget_monitoring (barangay, program, project_activity, duration, amount, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$barangay, $program, $project, $duration, $amount, $status]);
        $_SESSION['flash_msg'] = "New budget allocation for " . htmlspecialchars($barangay) . " saved successfully.";
        $_SESSION['flash_type'] = "success";
    }

    header("Location: admin_budget.php" . $filterQuery);
    exit();
}

// --- CRUD: DELETE ---
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['delete_budget'])) {
    $id = (int)($_POST['delete_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM budget_monitoring WHERE id=?");
    $stmt->execute([$id]);
    $_SESSION['flash_msg'] = "Budget allocation record permanently removed.";
    $_SESSION['flash_type'] = "success";
    header("Location: admin_budget.php" . $filterQuery);
    exit();
} elseif (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM budget_monitoring WHERE id=?");
    $stmt->execute([$id]);
    $_SESSION['flash_msg'] = "Budget allocation record permanently removed.";
    $_SESSION['flash_type'] = "success";
    header("Location: admin_budget.php" . $filterQuery);
    exit();
}

// --- CRUD: READ & FILTERS ---
$query = "SELECT * FROM budget_monitoring WHERE 1=1";
$params = [];

if (!empty($filter_barangay) && $filter_barangay !== 'All') {
    $query .= " AND barangay = ?";
    $params[] = $filter_barangay;
}

if (!empty($status_filter) && $status_filter !== 'All') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (program LIKE ? OR project_activity LIKE ? OR duration LIKE ? OR barangay LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY created_at DESC, id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$budgets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Telemetry & Metrics for KPI Cards
$total_allocated_all = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM budget_monitoring")->fetchColumn();
$total_records_all = (int)$pdo->query("SELECT COUNT(*) FROM budget_monitoring")->fetchColumn();
$unique_barangays_count = (int)$pdo->query("SELECT COUNT(DISTINCT barangay) FROM budget_monitoring")->fetchColumn();

// Status Counts
$planning_count = (int)$pdo->query("SELECT COUNT(*) FROM budget_monitoring WHERE status = 'Planning'")->fetchColumn();
$ongoing_count = (int)$pdo->query("SELECT COUNT(*) FROM budget_monitoring WHERE status = 'Ongoing'")->fetchColumn();
$completed_count = (int)$pdo->query("SELECT COUNT(*) FROM budget_monitoring WHERE status = 'Completed'")->fetchColumn();

$current_filtered_count = count($budgets);
$filtered_sum = array_sum(array_column($budgets, 'amount'));

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
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Budget Monitoring | TALA OS</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    
    <!-- ANTI-FLASH THEME SCRIPT -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
        
        /* Ultra-Clean Modern Cards: ZERO Outside Borders & ZERO Drop Shadows (Matching manage_disclosure.php) */
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
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- Admin Sidebar -->
    <?php include 'sidebar_admin.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-2.5 md:space-y-3">
            
            <!-- COMMAND CENTER HEADER WITH THREE UNIFIED GREEN KPI BADGES & THEME TOGGLE -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5 min-w-0 w-full sm:w-auto">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <div class="min-w-0">
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors truncate">
                            Budget Monitoring Console
                        </h1>
                        <p class="text-xs md:text-sm text-slate-500 dark:text-zinc-400 mt-0.5 truncate hidden sm:block">
                            Track municipal allocations, barangay youth funds, and program disbursements
                        </p>
                    </div>
                </div>
                
                <!-- Section Cards (3 Unified Solid TALA Green KPI Badges) & Dark Mode Toggle -->
                <div class="flex items-center gap-1.5 sm:gap-2 w-full sm:w-auto overflow-x-auto sm:overflow-visible pb-1 sm:pb-0 custom-scrollbar flex-shrink-0">
                    <!-- KPI 1: Total Allocated -->
                    <div class="glass-card card-green-glow-bl px-2.5 sm:px-3.5 py-1.5 rounded-xl flex items-center gap-2 sm:gap-2.5 flex-1 sm:flex-none min-w-[125px] sm:min-w-0 transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs sm:text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-coins text-sm sm:text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[9px] sm:text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Allocated</span>
                            <span class="text-xs sm:text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight block truncate">₱<?= number_format($total_allocated_all, 2) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Funded Projects -->
                    <div class="glass-card card-green-glow-bl px-2.5 sm:px-3.5 py-1.5 rounded-xl flex items-center gap-2 sm:gap-2.5 flex-1 sm:flex-none min-w-[95px] sm:min-w-0 transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs sm:text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-receipt text-sm sm:text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[9px] sm:text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Projects</span>
                            <span class="text-xs sm:text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight block truncate"><?= number_format($total_records_all) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Active Barangays -->
                    <div class="glass-card card-green-glow-bl px-2.5 sm:px-3.5 py-1.5 rounded-xl flex items-center gap-2 sm:gap-2.5 flex-1 sm:flex-none min-w-[90px] sm:min-w-0 transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-map-pin text-sm sm:text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[9px] sm:text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Barangays</span>
                            <span class="text-xs sm:text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight block truncate"><?= number_format($unique_barangays_count) ?></span>
                        </div>
                    </div>

                    <!-- Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[34px] h-[34px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
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

            <!-- FILTER, SEARCH & ACTION CONTROL BAR -->
            <div class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-2.5">
                <!-- Status Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full xl:w-auto custom-scrollbar py-0.5 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['val' => 'All', 'label' => 'All Budgets', 'count' => $total_records_all, 'active' => ($status_filter === 'All' || empty($status_filter))],
                        ['val' => 'Ongoing', 'label' => 'Ongoing', 'count' => $ongoing_count, 'active' => ($status_filter === 'Ongoing')],
                        ['val' => 'Planning', 'label' => 'Planning', 'count' => $planning_count, 'active' => ($status_filter === 'Planning')],
                        ['val' => 'Completed', 'label' => 'Completed', 'count' => $completed_count, 'active' => ($status_filter === 'Completed')],
                    ];
                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        
                        $tabParams = [];
                        if ($tab['val'] !== 'All') $tabParams['status'] = $tab['val'];
                        if (!empty($filter_barangay) && $filter_barangay !== 'All') $tabParams['barangay'] = $filter_barangay;
                        if (!empty($search)) $tabParams['search'] = $search;
                        $tabUrl = 'admin_budget.php' . (!empty($tabParams) ? '?' . http_build_query($tabParams) : '');
                    ?>
                        <a href="<?= $tabUrl ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span class="relative z-10"><?= $tab['label'] ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 dark:bg-black/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?> relative z-10"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Search, Barangay Dropdown & Action Button -->
                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full xl:w-auto">
                    <!-- Barangay Select -->
                    <form method="GET" class="w-full sm:w-auto sm:flex-1 md:w-44 min-w-[130px]">
                        <?php if ($status_filter !== 'All'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>"><?php endif; ?>
                        <?php if (!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <select name="barangay" onchange="this.form.submit()" class="w-full bg-slate-100 dark:bg-zinc-800/80 text-slate-800 dark:text-zinc-200 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="">All 46 Barangays</option>
                            <?php foreach ($all_barangays as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= $filter_barangay === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <!-- Search Input -->
                    <form method="GET" class="relative w-full sm:w-auto sm:flex-1 md:w-56 min-w-[150px]">
                        <?php if ($status_filter !== 'All'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>"><?php endif; ?>
                        <?php if (!empty($filter_barangay)): ?><input type="hidden" name="barangay" value="<?= htmlspecialchars($filter_barangay) ?>"><?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search program, project..." class="w-full bg-slate-100 dark:bg-zinc-800/80 text-slate-800 dark:text-zinc-200 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none transition-colors border-0">
                    </form>

                    <!-- Action Buttons Cluster (Clear + Allocate) -->
                    <div class="flex items-center gap-1.5 w-full sm:w-auto ml-auto sm:ml-0 flex-shrink-0">
                        <?php if (!empty($search) || (!empty($filter_barangay) && $filter_barangay !== 'All') || $status_filter !== 'All'): ?>
                            <a href="admin_budget.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Filters">
                                <i class="ph-bold ph-x text-xs"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Allocate Budget Button -->
                        <button type="button" onclick="openBudgetModal()" class="flex-1 sm:flex-none px-3.5 py-1.5 rounded-xl btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center justify-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group cursor-pointer border-0">
                            <i class="ph-bold ph-plus text-xs transition-transform duration-300 group-hover:scale-110 relative z-10"></i>
                            <span class="relative z-10">Allocate Budget</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- CONSOLIDATED BUDGET REGISTRY TABLE CARD -->
            <div class="glass-card card-green-glow-bl rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Minimal Header Strip -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex justify-between items-center transition-colors flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Financial Ledger & Allocations</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                            <?= number_format($current_filtered_count) ?> Records
                        </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400 dark:text-zinc-500 hidden sm:inline">Track municipal allocations, barangay youth funds & program disbursements</span>
                </div>

                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[680px] sm:min-w-[750px] md:min-w-full">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider bg-slate-50/90 dark:bg-zinc-900/90 backdrop-blur-sm transition-colors">
                                <th class="py-2.5 px-3 md:px-4">Barangay</th>
                                <th class="py-2.5 px-3 md:px-4">Program & Project Activities</th>
                                <th class="py-2.5 px-3 md:px-4">Duration</th>
                                <th class="py-2.5 px-3 md:px-4 text-center">Status</th>
                                <th class="py-2.5 px-3 md:px-4 text-right">Amount (₱)</th>
                                <th class="py-2.5 px-3 md:px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs font-medium divide-y divide-slate-100/80 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($budgets)): ?>
                                <?php foreach ($budgets as $row): 
                                    $status = $row['status'] ?? 'Planning';
                                ?>
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors group">
                                        <!-- Barangay -->
                                        <td class="py-2.5 px-4 whitespace-nowrap align-middle">
                                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300 px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider transition-colors">
                                                <i class="ph-fill ph-map-pin text-green-600 dark:text-green-500 text-[11px]"></i>
                                                <span><?= htmlspecialchars($row['barangay'] ?? '') ?></span>
                                            </span>
                                        </td>

                                        <!-- Program & Project Activities -->
                                        <td class="py-2.5 px-4 align-middle">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                                    <i class="ph-bold ph-coins"></i>
                                                </div>
                                                <div class="min-w-0 max-w-sm sm:max-w-md">
                                                    <p class="font-bold text-slate-900 dark:text-white text-xs leading-snug truncate group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors">
                                                        <?= htmlspecialchars($row['program'] ?? 'Untitled Program') ?>
                                                    </p>
                                                    <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5 truncate" title="<?= htmlspecialchars($row['project_activity'] ?? '') ?>">
                                                        <?= htmlspecialchars($row['project_activity'] ?? 'No activity description') ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Duration -->
                                        <td class="py-2.5 px-4 whitespace-nowrap text-xs text-slate-500 dark:text-zinc-400 align-middle">
                                            <span class="inline-flex items-center gap-1.5 font-medium">
                                                <i class="ph-bold ph-calendar text-slate-400 text-xs"></i>
                                                <span><?= htmlspecialchars($row['duration'] ?? 'N/A') ?></span>
                                            </span>
                                        </td>

                                        <!-- Status -->
                                        <td class="py-2.5 px-4 text-center align-middle whitespace-nowrap">
                                            <?php if ($status === 'Completed'): ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Completed
                                                </span>
                                            <?php elseif ($status === 'Ongoing'): ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Ongoing
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Planning
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Amount -->
                                        <td class="py-2.5 px-4 text-right align-middle whitespace-nowrap">
                                            <span class="font-black text-green-600 dark:text-green-500 text-xs md:text-sm font-mono">
                                                ₱<?= number_format($row['amount'] ?? 0, 2) ?>
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="py-2.5 px-4 text-right align-middle whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button" onclick="editBudgetModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-green-50 dark:hover:bg-green-500/10 transition-colors tap-effect" title="Edit Allocation">
                                                    <i class="ph-bold ph-pencil-simple text-xs"></i>
                                                </button>
                                                <a href="?delete_id=<?= $row['id'] ?><?= $filterQuery ?>" onclick="return confirm('Confirm Deletion: Permanently remove this budget allocation record?')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Delete Allocation">
                                                    <i class="ph-bold ph-trash text-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-14 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <div class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-2 flex items-center justify-center">
                                            <img src="mascot_question.png" alt="No Budgets" class="max-h-full max-w-full object-contain pointer-events-none hover:scale-105 transition-transform duration-300">
                                        </div>
                                        <p class="font-extrabold text-sm text-slate-800 dark:text-zinc-200">No Budget Records Found</p>
                                        <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium mt-0.5">Try clearing filters or allocate a new budget using the button above.</p>
                                        <?php if (!empty($search) || (!empty($filter_barangay) && $filter_barangay !== 'All') || $status_filter !== 'All'): ?>
                                            <a href="admin_budget.php" class="mt-3 inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
                                                <span>Clear Filters</span>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Minimal Table Footer -->
                <div class="px-4 py-2.5 bg-white/40 dark:bg-zinc-900/40 flex justify-between items-center text-xs text-slate-500 dark:text-zinc-400 font-medium flex-shrink-0">
                    <span>Showing <strong class="text-slate-800 dark:text-zinc-200"><?= count($budgets) ?></strong> of <?= number_format($total_records_all) ?> records</span>
                    <span>Total Filtered: <strong class="text-green-600 dark:text-green-500 font-bold">₱<?= number_format($filtered_sum, 2) ?></strong></span>
                </div>

            </div>
            
        </div>
    </main>

    <!-- BUDGET ALLOCATION POPUP MODAL -->
    <div id="budgetModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeBudgetModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-lg w-full p-4 sm:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border-0 max-h-[90vh] overflow-y-auto custom-scrollbar" id="budgetModalBox">
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-4 pb-2">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-green-500/10 text-green-600 dark:text-green-500 rounded-xl flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-bold ph-coins"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight" id="modalTitle">Allocate Budget</h3>
                        <p class="text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-mono mt-0.5" id="modalSubtitle">Fund Recording & Ledger</p>
                    </div>
                </div>
                <button type="button" onclick="closeBudgetModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" aria-label="Close modal">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>
            
            <!-- Modal Body (Form) -->
            <form action="admin_budget.php<?= !empty($filterQuery) ? $filterQuery : '' ?>" method="POST" id="budgetModalForm" class="space-y-3">
                <input type="hidden" name="save_budget" value="1">
                <input type="hidden" name="budget_id" id="modal_budget_id" value="">
                
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Target Barangay *</label>
                    <select name="barangay" id="modal_barangay" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800/80 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors cursor-pointer border-0" required>
                        <option value="" disabled selected>Select Barangay...</option>
                        <?php foreach ($all_barangays as $b): ?>
                            <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Program Name *</label>
                    <input type="text" name="program" id="modal_program" placeholder="e.g. Youth Sports Development Program" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800/80 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors border-0" required>
                </div>

                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Project / Activities *</label>
                    <textarea name="project_activity" id="modal_project_activity" rows="3" placeholder="Specific activities and objectives funded..." class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800/80 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors resize-none custom-scrollbar border-0" required></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Duration / Period *</label>
                        <input type="text" name="duration" id="modal_duration" placeholder="e.g. Q1 2026 or Jan - Mar" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800/80 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors border-0" required>
                    </div>
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Status *</label>
                        <select name="status" id="modal_status" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800/80 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors cursor-pointer border-0" required>
                            <option value="Planning">Planning</option>
                            <option value="Ongoing">Ongoing</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Allocated Amount (₱) *</label>
                    <input type="number" step="0.01" name="amount" id="modal_amount" placeholder="50000.00" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800/80 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors border-0" required>
                </div>

                <!-- Modal Footer -->
                <div class="pt-3 mt-3 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeBudgetModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm transition-all tap-effect">
                        Cancel
                    </button>
                    <button type="submit" id="modalSubmitBtn" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2 px-5 rounded-xl transition-all text-xs md:text-sm tap-effect flex items-center gap-1.5 shadow-none border-0">
                        <i class="ph-bold ph-floppy-disk relative z-10"></i>
                        <span id="modalSubmitText" class="relative z-10">Save Record</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Android Style) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 h-[68px] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-2xl z-50 flex justify-around items-center px-2 pb-[env(safe-area-inset-bottom)] transition-colors">
        <a href="dashboard.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
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
        <a href="admin_budget.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-coins text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Budget</span>
        </a>
        <a href="admin_approval.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-shield-check text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Approvals</span>
        </a>
    </nav>

    <!-- UI Scripts -->
    <script>
        const budgetModalOverlay = document.getElementById('budgetModalOverlay');
        const budgetModalBox = document.getElementById('budgetModalBox');
        const budgetForm = document.getElementById('budgetModalForm');

        function openBudgetModal() {
            budgetForm.reset();
            document.getElementById('modal_budget_id').value = '';
            document.getElementById('modal_status').value = 'Planning';
            document.getElementById('modalTitle').innerText = 'Allocate Budget';
            document.getElementById('modalSubtitle').innerText = 'Fund Recording & Ledger';
            document.getElementById('modalSubmitText').innerText = 'Save Record';
            
            budgetModalOverlay.classList.remove('hidden');
            budgetModalOverlay.classList.add('flex');
            setTimeout(() => {
                budgetModalOverlay.classList.remove('opacity-0');
                budgetModalOverlay.classList.add('opacity-100');
                budgetModalBox.classList.remove('scale-95', 'opacity-0');
                budgetModalBox.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function editBudgetModal(data) {
            if (!data) return;

            document.getElementById('modal_budget_id').value = data.id || '';
            document.getElementById('modal_barangay').value = data.barangay || '';
            document.getElementById('modal_program').value = data.program || '';
            document.getElementById('modal_project_activity').value = data.project_activity || '';
            document.getElementById('modal_duration').value = data.duration || '';
            document.getElementById('modal_status').value = data.status || 'Planning';
            document.getElementById('modal_amount').value = data.amount || '';

            document.getElementById('modalTitle').innerText = 'Update Allocation';
            document.getElementById('modalSubtitle').innerText = 'Modify existing budget record';
            document.getElementById('modalSubmitText').innerText = 'Update Record';

            budgetModalOverlay.classList.remove('hidden');
            budgetModalOverlay.classList.add('flex');
            setTimeout(() => {
                budgetModalOverlay.classList.remove('opacity-0');
                budgetModalOverlay.classList.add('opacity-100');
                budgetModalBox.classList.remove('scale-95', 'opacity-0');
                budgetModalBox.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeBudgetModal() {
            budgetModalOverlay.classList.remove('opacity-100');
            budgetModalOverlay.classList.add('opacity-0');
            budgetModalBox.classList.remove('scale-100', 'opacity-100');
            budgetModalBox.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                budgetModalOverlay.classList.remove('flex');
                budgetModalOverlay.classList.add('hidden');
            }, 200);
        }

        // Close on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && budgetModalOverlay && !budgetModalOverlay.classList.contains('hidden')) {
                closeBudgetModal();
            }
        });

        // Mobile Drawer Toggle
        function toggleMobileMenu() {
            const sidebar = document.getElementById('tala-admin-sidebar') || document.getElementById('tala-bulletproof-sk-sidebar') || document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            if (!sidebar) return;
            if (sidebar.classList.contains('mobile-open') || sidebar.classList.contains('active') || !sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('mobile-open', 'active');
                sidebar.classList.add('-translate-x-full');
                if (overlay) overlay.classList.remove('active');
                document.body.style.overflow = '';
            } else {
                sidebar.classList.add('mobile-open', 'active');
                sidebar.classList.remove('-translate-x-full');
                if (overlay) overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
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