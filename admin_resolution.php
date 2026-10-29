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

// 46 Tumauini Barangays
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

// Global Filtering Parameters
$search = trim($_GET['search'] ?? '');
$brgy_filter = trim($_GET['barangay'] ?? '');
$status_filter = trim($_GET['status'] ?? 'All');

$filterParams = [];
if (!empty($search)) $filterParams['search'] = $search;
if (!empty($brgy_filter)) $filterParams['barangay'] = $brgy_filter;
if ($status_filter !== 'All') $filterParams['status'] = $status_filter;
$filterQuery = !empty($filterParams) ? '&' . http_build_query($filterParams) : '';

// Handle Global Delete
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("SELECT file_path, resolution_number FROM resolutions WHERE id = ?");
    $stmt->execute([$del_id]);
    $del_res = $stmt->fetch();
    if ($del_res) {
        if (!empty($del_res['file_path']) && file_exists($del_res['file_path'])) {
            @unlink($del_res['file_path']);
        }
        $pdo->prepare("DELETE FROM resolution_signatures WHERE resolution_id = ?")->execute([$del_id]);
        $pdo->prepare("DELETE FROM resolutions WHERE id = ?")->execute([$del_id]);
        $_SESSION['flash_msg'] = "Resolution " . (!empty($del_res['resolution_number']) ? 'No. ' . htmlspecialchars($del_res['resolution_number']) : '') . " was permanently removed.";
        $_SESSION['flash_type'] = "success";
    }
    $redUrl = 'admin_resolution.php' . (!empty($filterParams) ? '?' . http_build_query($filterParams) : '');
    header("Location: " . $redUrl);
    exit();
}

// Global Filtering Query
$query = "SELECT * FROM resolutions WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR resolution_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($brgy_filter)) {
    $query .= " AND barangay = ?";
    $params[] = $brgy_filter;
}

if ($status_filter === 'Verified') {
    $query .= " AND id IN (SELECT DISTINCT resolution_id FROM resolution_signatures WHERE has_signed = 1)";
} elseif ($status_filter === 'Pending') {
    $query .= " AND id NOT IN (SELECT DISTINCT resolution_id FROM resolution_signatures WHERE has_signed = 1)";
}

$query .= " ORDER BY date_passed DESC, id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$resolutions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Batch fetch signatures to eliminate N+1 queries
$all_signatures = [];
if (!empty($resolutions)) {
    $res_ids = array_column($resolutions, 'id');
    $placeholders = implode(',', array_fill(0, count($res_ids), '?'));
    $sig_stmt = $pdo->prepare("SELECT resolution_id, official_name, position FROM resolution_signatures WHERE resolution_id IN ($placeholders) AND has_signed = 1");
    $sig_stmt->execute($res_ids);
    while ($row = $sig_stmt->fetch(PDO::FETCH_ASSOC)) {
        $all_signatures[$row['resolution_id']][] = [
            'official_name' => $row['official_name'],
            'position' => $row['position']
        ];
    }
}

// Overall Telemetry Metrics
$total_res = count($resolutions);
$all_resolutions_count = (int)$pdo->query("SELECT COUNT(*) FROM resolutions")->fetchColumn();
$signed_resolutions_count = (int)$pdo->query("SELECT COUNT(DISTINCT resolution_id) FROM resolution_signatures WHERE has_signed = 1")->fetchColumn();
$pending_resolutions_count = max(0, $all_resolutions_count - $signed_resolutions_count);
$active_brgy_count = (int)$pdo->query("SELECT COUNT(DISTINCT barangay) FROM resolutions")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Resolution Master Console | TALA OS</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">

    <!-- ANTI-FLASH SCRIPT -->
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

    <!-- Admin Sidebar -->
    <?php include 'sidebar_admin.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-2.5 md:space-y-3">
            
            <!-- COMMAND CENTER HEADER WITH THREE UNIFIED GREEN KPI BADGES & THEME TOGGLE -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <div>
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                            Resolution Master Console
                        </h1>
                        <p class="text-xs md:text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                            Municipal legislative resolutions, verified signatories, and official records
                        </p>
                    </div>
                </div>
                
                <!-- Quick KPI Badges (3 Unified Solid TALA Green Style) & Dark Mode Toggle -->
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Total Archive -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-files text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Archive</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($all_resolutions_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: OCR Verified -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-signature text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">OCR Verified</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($signed_resolutions_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Active Barangays -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-map-pin text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Active Barangays</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($active_brgy_count) ?></span>
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

            <!-- FILTER, SEARCH & ACTION CONTROL BAR -->
            <div class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-2.5">
                <!-- Status Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full xl:w-auto custom-scrollbar py-0.5 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['val' => 'All', 'label' => 'All Archive', 'count' => $all_resolutions_count, 'active' => ($status_filter === 'All')],
                        ['val' => 'Verified', 'label' => 'OCR Verified', 'count' => $signed_resolutions_count, 'active' => ($status_filter === 'Verified')],
                        ['val' => 'Pending', 'label' => 'Awaiting OCR', 'count' => $pending_resolutions_count, 'active' => ($status_filter === 'Pending')],
                    ];
                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        
                        $tabParams = [];
                        if ($tab['val'] !== 'All') $tabParams['status'] = $tab['val'];
                        if (!empty($brgy_filter)) $tabParams['barangay'] = $brgy_filter;
                        if (!empty($search)) $tabParams['search'] = $search;
                        $tabUrl = 'admin_resolution.php' . (!empty($tabParams) ? '?' . http_build_query($tabParams) : '');
                    ?>
                        <a href="<?= $tabUrl ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span class="relative z-10"><?= $tab['label'] ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 dark:bg-black/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?> relative z-10"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Search and Barangay Dropdown -->
                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full xl:w-auto">
                    <!-- Barangay Select -->
                    <form method="GET" class="w-full sm:w-48">
                        <?php if ($status_filter !== 'All'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>"><?php endif; ?>
                        <?php if (!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <select name="barangay" onchange="this.form.submit()" class="w-full bg-slate-100 dark:bg-zinc-800/80 text-slate-800 dark:text-zinc-200 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="">All 46 Barangays</option>
                            <?php foreach ($all_barangays as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= $brgy_filter === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <!-- Search Input -->
                    <form method="GET" class="relative w-full sm:w-56">
                        <?php if ($status_filter !== 'All'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>"><?php endif; ?>
                        <?php if (!empty($brgy_filter)): ?><input type="hidden" name="barangay" value="<?= htmlspecialchars($brgy_filter) ?>"><?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search titles, numbers..." class="w-full bg-slate-100 dark:bg-zinc-800/80 text-slate-800 dark:text-zinc-200 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none transition-colors border-0">
                    </form>

                    <?php if (!empty($search) || !empty($brgy_filter) || $status_filter !== 'All'): ?>
                        <a href="admin_resolution.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Filters">
                            <i class="ph-bold ph-x text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CONSOLIDATED RESOLUTION REGISTRY TABLE CARD -->
            <div class="glass-card card-green-glow-bl rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Minimal Header Strip -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex justify-between items-center transition-colors flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Resolution Registry</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                            <?= number_format($total_res) ?> Records
                        </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400 dark:text-zinc-500 hidden sm:inline">Legislative archive & OCR signatories</span>
                </div>

                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[750px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider bg-slate-50/90 dark:bg-zinc-900/90 backdrop-blur-sm transition-colors">
                                <th class="py-2.5 px-4">Barangay</th>
                                <th class="py-2.5 px-4">Resolution Details</th>
                                <th class="py-2.5 px-4">Date Passed</th>
                                <th class="py-2.5 px-4 text-center">OCR Signatories</th>
                                <th class="py-2.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs font-medium divide-y divide-slate-100/80 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($resolutions)): ?>
                                <?php foreach ($resolutions as $res): 
                                    $signatures = $all_signatures[$res['id']] ?? [];
                                    $sig_count = count($signatures);
                                    $sig_json = htmlspecialchars(json_encode($signatures), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors group">
                                        <!-- Barangay -->
                                        <td class="py-2.5 px-4 whitespace-nowrap align-middle">
                                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300 px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider transition-colors">
                                                <i class="ph-fill ph-map-pin text-green-600 dark:text-green-500 text-[11px]"></i>
                                                <span><?= htmlspecialchars($res['barangay'] ?? '') ?></span>
                                            </span>
                                        </td>

                                        <!-- Resolution Details -->
                                        <td class="py-2.5 px-4 align-middle">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                                    <i class="ph-bold ph-scroll"></i>
                                                </div>
                                                <div class="min-w-0 max-w-sm sm:max-w-md">
                                                    <p class="font-bold text-slate-900 dark:text-white text-xs leading-snug truncate group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors">
                                                        <?= htmlspecialchars($res['title'] ?? 'Untitled Resolution') ?>
                                                    </p>
                                                    <div class="flex items-center gap-2 text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">
                                                        <span class="font-mono font-bold text-slate-600 dark:text-zinc-400">Res. No. <?= htmlspecialchars($res['resolution_number'] ?? 'N/A') ?></span>
                                                        <?php if (!empty($res['uploaded_by'])): ?>
                                                            <span>&bull;</span>
                                                            <span class="truncate">By <?= htmlspecialchars($res['uploaded_by']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Date Passed -->
                                        <td class="py-2.5 px-4 whitespace-nowrap text-xs text-slate-500 dark:text-zinc-400 align-middle">
                                            <span class="inline-flex items-center gap-1.5 font-medium">
                                                <i class="ph-bold ph-calendar text-slate-400"></i>
                                                <span><?= !empty($res['date_passed']) ? date('M j, Y', strtotime($res['date_passed'])) : '—' ?></span>
                                            </span>
                                        </td>

                                        <!-- Signatories -->
                                        <td class="py-2.5 px-4 text-center align-middle whitespace-nowrap">
                                            <?php if ($sig_count > 0): ?>
                                                <button type="button" onclick="openSignaturesModal(this)" data-sigs="<?= $sig_json ?>" class="inline-flex items-center justify-center gap-1.5 text-green-700 dark:text-green-400 bg-green-50 hover:bg-green-100 dark:bg-green-950/40 dark:hover:bg-green-900/50 px-2.5 py-1 rounded-lg text-xs font-bold transition-all tap-effect">
                                                    <i class="ph-fill ph-signature text-xs"></i>
                                                    <span><?= $sig_count ?> Verified</span>
                                                </button>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 dark:text-zinc-500 bg-slate-100 dark:bg-zinc-800/60 px-2 py-0.5 rounded-md">
                                                    <i class="ph-bold ph-clock text-xs"></i> Awaiting OCR
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Action Buttons -->
                                        <td class="py-2.5 px-4 text-right align-middle whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1">
                                                <?php if (!empty($res['file_path'])): ?>
                                                    <a href="<?= htmlspecialchars($res['file_path']) ?>" target="_blank" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-green-50 dark:hover:bg-green-500/10 transition-colors tap-effect" title="Open PDF Resolution">
                                                        <i class="ph-bold ph-file-pdf text-xs"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="?delete_id=<?= $res['id'] ?><?= $filterQuery ?>" onclick="return confirm('Confirm Deletion: Permanently remove this resolution and its verified records?')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Delete Resolution">
                                                    <i class="ph-bold ph-trash text-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-14 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <div class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-2 flex items-center justify-center">
                                            <img src="mascot_question.png" alt="No Resolutions" class="max-h-full max-w-full object-contain pointer-events-none hover:scale-105 transition-transform duration-300">
                                        </div>
                                        <p class="font-extrabold text-sm text-slate-800 dark:text-zinc-200">No Resolutions Found</p>
                                        <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium mt-0.5">Try clearing filters or search parameters.</p>
                                        <?php if (!empty($search) || !empty($brgy_filter) || $status_filter !== 'All'): ?>
                                            <a href="admin_resolution.php" class="mt-3 inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
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
                    <span>Showing <strong class="text-slate-800 dark:text-zinc-200"><?= count($resolutions) ?></strong> of <?= number_format($all_resolutions_count) ?> records</span>
                    <span>TALA OS &bull; Resolution Master Console</span>
                </div>

            </div>
            
        </div>
    </main>

    <!-- SIGNATORIES POPUP MODAL -->
    <div id="signaturesModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeSignaturesModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-md w-full p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border-0" id="signaturesModalBox">
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-4 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-green-500/10 text-green-600 dark:text-green-500 rounded-xl flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-fill ph-signature"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight">Verified Signatories</h3>
                        <p class="text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-mono mt-0.5">OCR Database Match</p>
                    </div>
                </div>
                <button type="button" onclick="closeSignaturesModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" aria-label="Close modal">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Content List -->
            <div class="max-h-[55vh] overflow-y-auto custom-scrollbar pr-1">
                <ul id="signaturesList" class="space-y-2"></ul>
            </div>

            <!-- Modal Footer -->
            <div class="pt-3 mt-3 flex items-center justify-end">
                <button type="button" onclick="closeSignaturesModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm transition-all tap-effect">
                    Close
                </button>
            </div>
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
        <a href="tala_directory.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-address-book text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Directory</span>
        </a>
        <a href="admin_resolution.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-scroll text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Resolutions</span>
        </a>
        <a href="admin_approval.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-shield-check text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Approvals</span>
        </a>
    </nav>

    <!-- Modal and Helper Scripts -->
    <script>
        const sigModalOverlay = document.getElementById('signaturesModalOverlay');
        const sigModalBox = document.getElementById('signaturesModalBox');
        const list = document.getElementById('signaturesList');

        function openSignaturesModal(btnElement) {
            const rawData = btnElement.getAttribute('data-sigs');
            let sigs = [];
            try { 
                sigs = JSON.parse(rawData); 
            } catch(e) {}
            
            list.innerHTML = '';
            if (sigs.length === 0) {
                list.innerHTML = '<li class="text-center text-xs text-slate-400 italic py-3">No verified signatures recorded.</li>';
            } else {
                sigs.forEach(s => {
                    list.innerHTML += `
                        <li class="flex items-center gap-2.5 p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-950/80 transition-colors">
                            <div class="w-7 h-7 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-xs shrink-0">
                                <i class="ph-bold ph-check"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-slate-900 dark:text-white truncate">${s.official_name || 'Official'}</p>
                                <p class="text-[10px] font-semibold text-slate-400 dark:text-zinc-500 uppercase tracking-wider truncate">${s.position || 'Signatory'}</p>
                            </div>
                        </li>
                    `;
                });
            }
            if (sigModalOverlay && sigModalBox) {
                sigModalOverlay.classList.remove('hidden');
                sigModalOverlay.classList.add('flex');
                setTimeout(() => {
                    sigModalOverlay.classList.remove('opacity-0');
                    sigModalOverlay.classList.add('opacity-100');
                    sigModalBox.classList.remove('scale-95', 'opacity-0');
                    sigModalBox.classList.add('scale-100', 'opacity-100');
                }, 10);
            }
        }

        function closeSignaturesModal() {
            if (sigModalOverlay && sigModalBox) {
                sigModalOverlay.classList.remove('opacity-100');
                sigModalOverlay.classList.add('opacity-0');
                sigModalBox.classList.remove('scale-100', 'opacity-100');
                sigModalBox.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    sigModalOverlay.classList.remove('flex');
                    sigModalOverlay.classList.add('hidden');
                }, 200);
            }
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sigModalOverlay && !sigModalOverlay.classList.contains('hidden')) {
                closeSignaturesModal();
            }
        });

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