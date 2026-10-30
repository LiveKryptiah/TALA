<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Security Check: Only Municipal Admin allowed
if (($_SESSION['admin_logged_in'] ?? false) !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';

$message = $_SESSION['flash_msg'] ?? ($_GET['msg'] ?? '');
$msg_type = $_SESSION['flash_type'] ?? ($_GET['type'] ?? 'success');
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// Handle Approve / Reject Actions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];

    $backParams = [];
    if (!empty($_GET['status'])) $backParams['status'] = $_GET['status'];
    if (!empty($_GET['barangay'])) $backParams['barangay'] = $_GET['barangay'];
    if (!empty($_GET['search'])) $backParams['search'] = $_GET['search'];

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE admins SET status = 'approved' WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['flash_msg'] = "Account successfully approved! SK official has been granted portal access.";
            $_SESSION['flash_type'] = "success";
        }
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("SELECT profile_picture, oath_file_path FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        $files = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($files) {
            if (!empty($files['profile_picture']) && file_exists($files['profile_picture'])) @unlink($files['profile_picture']);
            if (!empty($files['oath_file_path']) && file_exists($files['oath_file_path'])) @unlink($files['oath_file_path']);
        }

        // Delete the rejected request completely
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['flash_msg'] = "Account request rejected and removed from registry.";
            $_SESSION['flash_type'] = "warning";
        }
    }
    
    $redirectUrl = 'admin_approval.php' . (!empty($backParams) ? '?' . http_build_query($backParams) : '');
    header("Location: " . $redirectUrl);
    exit();
}

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

// Telemetry Stats
$stmt_pend = $pdo->query("SELECT COUNT(*) FROM admins WHERE status = 'pending' AND role = 'sub-admin'");
$pending_count = (int)$stmt_pend->fetchColumn();

$stmt_app = $pdo->query("SELECT COUNT(*) FROM admins WHERE status = 'approved' AND role = 'sub-admin'");
$approved_count = (int)$stmt_app->fetchColumn();

$stmt_total = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'sub-admin'");
$total_sk_count = (int)$stmt_total->fetchColumn();

// Filter & Search Handling
$search = trim($_GET['search'] ?? '');
$brgy_filter = trim($_GET['barangay'] ?? '');
$status_filter = trim($_GET['status'] ?? 'pending');

$queryParams = [];
if ($status_filter !== 'pending') $queryParams['status'] = $status_filter;
if (!empty($brgy_filter)) $queryParams['barangay'] = $brgy_filter;
if (!empty($search)) $queryParams['search'] = $search;
$filterQuery = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';

$query = "SELECT * FROM admins WHERE role = 'sub-admin'";
$params = [];

if ($status_filter === 'pending') {
    $query .= " AND status = 'pending'";
} elseif ($status_filter === 'approved') {
    $query .= " AND status = 'approved'";
}

if (!empty($search)) {
    $query .= " AND (full_name LIKE ? OR username LIKE ? OR sk_position LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($brgy_filter)) {
    $query .= " AND barangay_assigned = ?";
    $params[] = $brgy_filter;
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$current_filtered_count = count($accounts);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Account Approvals | TALA OS</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">

    <!-- Anti-Flash Theme Script -->
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

    <!-- STANDARD TALA OS ADMIN SIDEBAR -->
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
                            Account Approvals
                        </h1>
                        <p class="text-xs md:text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                            SK Council onboarding, credentials verification, and oath compliance
                        </p>
                    </div>
                </div>
                
                <!-- Section Cards (3 Unified Solid TALA Green KPI Badges) & Dark Mode Toggle -->
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Pending Requests -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-clock-countdown text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Pending Requests</span>
                            <span class="text-base md:text-lg font-black text-amber-600 dark:text-amber-400 leading-tight"><?= number_format($pending_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Active Officials -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-shield-check text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Active Officials</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($approved_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Total Roster -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-users-three text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Roster</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_sk_count) ?></span>
                        </div>
                    </div>

                    <!-- Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[36px] h-[36px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
                        <i id="headerThemeIcon" class="ph-bold ph-moon text-base sm:text-lg transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- Action Status Alert -->
            <?php if (!empty($message)): ?>
                <?php 
                    $isSuccess = ($msg_type === 'success');
                ?>
                <div class="<?= $isSuccess ? 'bg-green-50/85 dark:bg-green-500/10 text-green-700 dark:text-green-400' : 'bg-amber-50/85 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400' ?> px-4 py-3 rounded-2xl flex items-center justify-between gap-3 text-xs md:text-sm font-bold shadow-none backdrop-blur-md transition-colors border-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <i class="ph-bold <?= $isSuccess ? 'ph-check-circle' : 'ph-warning' ?> text-lg flex-shrink-0"></i>
                        <span class="truncate"><?= htmlspecialchars($message) ?></span>
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
                        ['val' => 'pending', 'label' => 'Pending Review', 'count' => $pending_count, 'active' => ($status_filter === 'pending')],
                        ['val' => 'approved', 'label' => 'Approved Officials', 'count' => $approved_count, 'active' => ($status_filter === 'approved')],
                        ['val' => 'all', 'label' => 'All Accounts', 'count' => $total_sk_count, 'active' => ($status_filter === 'all')],
                    ];
                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        
                        $tabParams = [];
                        if ($tab['val'] !== 'pending') $tabParams['status'] = $tab['val'];
                        if (!empty($brgy_filter)) $tabParams['barangay'] = $brgy_filter;
                        if (!empty($search)) $tabParams['search'] = $search;
                        $tabUrl = 'admin_approval.php' . (!empty($tabParams) ? '?' . http_build_query($tabParams) : '');
                    ?>
                        <a href="<?= $tabUrl ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span class="relative z-10"><?= $tab['label'] ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 dark:bg-black/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?> relative z-10"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Dropdowns and Search -->
                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full xl:w-auto">
                    <!-- Barangay Dropdown -->
                    <form method="GET" class="w-full sm:w-48">
                        <?php if ($status_filter !== 'pending'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>"><?php endif; ?>
                        <?php if (!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <select name="barangay" onchange="this.form.submit()" class="w-full bg-slate-100 dark:bg-zinc-800/80 text-slate-800 dark:text-zinc-200 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="">All 46 Barangays</option>
                            <?php foreach ($all_barangays as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= $brgy_filter === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <!-- Search Input -->
                    <form method="GET" class="relative w-full sm:w-64">
                        <?php if ($status_filter !== 'pending'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>"><?php endif; ?>
                        <?php if (!empty($brgy_filter)): ?><input type="hidden" name="barangay" value="<?= htmlspecialchars($brgy_filter) ?>"><?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search official, username, position..." class="w-full bg-slate-100 dark:bg-zinc-800/80 text-slate-800 dark:text-zinc-200 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none transition-colors border-0">
                    </form>

                    <?php if (!empty($search) || !empty($brgy_filter) || $status_filter !== 'pending'): ?>
                        <a href="admin_approval.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Filters">
                            <i class="ph-bold ph-x text-xs"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CONSOLIDATED REVIEW QUEUE CARD -->
            <div class="glass-card card-green-glow-bl rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Minimal Header Strip -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex justify-between items-center transition-colors flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Official Review Queue</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                            <?= number_format($current_filtered_count) ?> Records
                        </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400 dark:text-zinc-500 hidden sm:inline">Verify credentials, inspect oath of office documentation, and grant portal access</span>
                </div>
                
                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[850px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider bg-slate-50/90 dark:bg-zinc-900/90 backdrop-blur-sm transition-colors">
                                <th class="py-2.5 px-4 md:px-5">Applicant Profile</th>
                                <th class="py-2.5 px-4 md:px-5">Assignment & Position</th>
                                <th class="py-2.5 px-4 md:px-5 text-center">Oath Credentials</th>
                                <th class="py-2.5 px-4 md:px-5">Date Requested</th>
                                <th class="py-2.5 px-4 md:px-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs font-medium divide-y divide-slate-100/80 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($accounts)): ?>
                                <?php foreach ($accounts as $account): 
                                    $oath_path = htmlspecialchars($account['oath_file_path'] ?? '');
                                    $profile_pic = htmlspecialchars($account['profile_picture'] ?? '');
                                    $is_pending = ($account['status'] === 'pending');
                                ?>
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors group">
                                    
                                    <!-- Applicant Info -->
                                    <td class="py-2.5 px-4 md:px-5 align-middle">
                                        <div class="flex items-center gap-3">
                                            <?php if ($profile_pic && file_exists($account['profile_picture'])): ?>
                                                <img src="<?= $profile_pic ?>" alt="Profile" class="w-9 h-9 rounded-xl object-cover bg-slate-100 dark:bg-zinc-800 transition-colors flex-shrink-0">
                                            <?php else: ?>
                                                <div class="w-9 h-9 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-base flex-shrink-0">
                                                    <i class="ph-fill ph-user"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="min-w-0">
                                                <p class="font-bold text-slate-900 dark:text-white text-xs leading-tight transition-colors truncate">
                                                    <?= htmlspecialchars($account['full_name']) ?>
                                                </p>
                                                <p class="text-[11px] font-bold text-slate-400 dark:text-zinc-500 font-mono tracking-wider transition-colors mt-0.5">
                                                    @<?= htmlspecialchars($account['username']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- Assignment -->
                                    <td class="py-2.5 px-4 md:px-5 align-middle whitespace-nowrap">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span class="inline-flex items-center text-xs font-bold text-slate-700 dark:text-zinc-300 bg-slate-100 dark:bg-zinc-800 px-2 py-0.5 rounded-lg uppercase tracking-wider transition-colors">
                                                <i class="ph-fill ph-map-pin mr-1 text-green-600 dark:text-green-500 text-[11px]"></i> Brgy. <?= htmlspecialchars($account['barangay_assigned'] === 'Banig' ? 'Banig/Dy Abra' : $account['barangay_assigned']) ?>
                                            </span>
                                            <span class="inline-flex items-center text-xs font-bold text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-500/10 px-2 py-0.5 rounded-lg uppercase tracking-wider transition-colors">
                                                <i class="ph-fill ph-identification-badge mr-1 text-[11px]"></i> <?= htmlspecialchars($account['sk_position']) ?>
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Documents / Oath -->
                                    <td class="py-2.5 px-4 md:px-5 align-middle text-center whitespace-nowrap">
                                        <?php if (!empty($oath_path)): ?>
                                            <button type="button" onclick="openDocModal('<?= $oath_path ?>', '<?= htmlspecialchars(addslashes($account['full_name'])) ?>')" class="inline-flex items-center justify-center gap-1.5 text-green-700 dark:text-green-400 bg-green-50 hover:bg-green-100 dark:bg-green-950/40 dark:hover:bg-green-900/50 px-2.5 py-1 rounded-lg transition-all tap-effect text-xs font-bold">
                                                <i class="ph-bold ph-file-pdf text-rose-500 text-xs"></i>
                                                <span>Inspect Oath</span>
                                            </button>
                                        <?php else: ?>
                                            <span class="inline-flex items-center justify-center gap-1 text-slate-400 dark:text-zinc-500 bg-slate-100 dark:bg-zinc-800/60 px-2.5 py-1 rounded-lg text-[11px] font-semibold transition-colors">
                                                <i class="ph-fill ph-warning-circle text-xs"></i> Missing Oath
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Date Requested -->
                                    <td class="py-2.5 px-4 md:px-5 align-middle whitespace-nowrap">
                                        <p class="font-bold text-slate-700 dark:text-zinc-300 text-xs transition-colors"><?= date('M j, Y', strtotime($account['created_at'])) ?></p>
                                        <p class="text-[10px] font-semibold text-slate-400 dark:text-zinc-500 uppercase tracking-wider transition-colors"><?= date('h:i A', strtotime($account['created_at'])) ?></p>
                                    </td>
                                    
                                    <!-- Actions -->
                                    <td class="py-2.5 px-4 md:px-5 text-right align-middle whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <?php if ($is_pending): ?>
                                                <a href="?action=approve&id=<?= $account['id'] ?><?= $filterQuery ?>" onclick="return confirm('Approve this SK account? They will gain full access to the SK Portal.')" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white inline-flex items-center justify-center px-3 py-1 font-bold rounded-lg transition-all text-xs tap-effect shadow-none border-0">
                                                    <span class="relative z-10">Approve</span>
                                                    <i class="ph-bold ph-check ml-1 text-xs relative z-10"></i>
                                                </a>
                                                <a href="?action=reject&id=<?= $account['id'] ?><?= $filterQuery ?>" onclick="return confirm('Reject and delete this registration request? This action cannot be undone.')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Reject and Delete">
                                                    <i class="ph-bold ph-trash text-xs"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-500/10 px-2.5 py-1 rounded-lg text-xs font-black uppercase tracking-wider">
                                                    <i class="ph-bold ph-check"></i> Approved
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-14 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <div class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-2 flex items-center justify-center">
                                            <img src="mascot_question.png" alt="No Requests" class="max-h-full max-w-full object-contain pointer-events-none hover:scale-105 transition-transform duration-300">
                                        </div>
                                        <p class="font-extrabold text-sm text-slate-800 dark:text-zinc-200">No Account Requests Found</p>
                                        <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium mt-0.5">Try clearing filters or check back later for new applicant onboarding submissions.</p>
                                        <?php if (!empty($search) || !empty($brgy_filter) || $status_filter !== 'pending'): ?>
                                            <a href="admin_approval.php" class="mt-3 inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
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
                    <span>Showing <strong class="text-slate-800 dark:text-zinc-200"><?= count($accounts) ?></strong> of <?= number_format($total_sk_count) ?> accounts</span>
                    <span>TALA OS &bull; SK Official Onboarding</span>
                </div>
            </div>

        </div>
    </main>

    <!-- DOCUMENT INSPECTION MODAL -->
    <div id="docModal" class="fixed inset-0 z-50 hidden items-center justify-center p-3 sm:p-4 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeDocModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden w-full max-w-2xl max-h-[90vh] flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border-0" id="docModalContent">
            
            <div class="p-4 bg-white/80 dark:bg-zinc-900/80 flex justify-between items-center transition-colors flex-shrink-0">
                <div class="flex items-center gap-2.5 min-w-0 pr-2">
                    <div class="w-8 h-8 bg-green-500/10 text-green-600 dark:text-green-500 rounded-xl flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-fill ph-file-pdf"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-xs md:text-sm leading-tight transition-colors">Oath of Office Inspector</h3>
                        <p class="text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-mono transition-colors truncate mt-0.5" id="modalApplicantName">Applicant</p>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <a id="modalExternalLink" href="#" target="_blank" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" title="Open in New Tab">
                        <i class="ph-bold ph-arrow-square-out text-xs"></i>
                    </a>
                    <button type="button" onclick="closeDocModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" aria-label="Close modal">
                        <i class="ph-bold ph-x text-xs"></i>
                    </button>
                </div>
            </div>

            <div class="p-3 md:p-4 bg-slate-100/60 dark:bg-zinc-950/60 transition-colors flex justify-center items-center min-h-[260px] max-h-[65vh] overflow-y-auto custom-scrollbar">
                <div id="docViewerContainer" class="w-full h-full flex justify-center items-center"></div>
            </div>
            
            <div class="p-3 bg-white/80 dark:bg-zinc-900/80 flex justify-end items-center flex-shrink-0">
                <button type="button" onclick="closeDocModal()" class="px-4 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs transition-all tap-effect">
                    Close Inspector
                </button>
            </div>
        </div>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Android Style) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 h-[68px] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-2xl z-50 flex justify-around items-center px-2 pb-[env(safe-area-inset-bottom)] transition-colors border-0">
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
        <a href="admin_budget.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-coins text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Budget</span>
        </a>
        <a href="admin_approval.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-shield-check text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Approvals</span>
        </a>
    </nav>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        // Mobile Drawer Toggle
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

        // Document Inspection Modal Logic
        const modal = document.getElementById('docModal');
        const modalContent = document.getElementById('docModalContent');
        const viewerContainer = document.getElementById('docViewerContainer');
        const externalLink = document.getElementById('modalExternalLink');

        function openDocModal(filePath, applicantName) {
            document.getElementById('modalApplicantName').innerText = applicantName;
            if (externalLink) externalLink.href = filePath;

            const ext = filePath.split('.').pop().toLowerCase();
            viewerContainer.innerHTML = '';

            if (ext === 'pdf') {
                viewerContainer.innerHTML = `<iframe src="${filePath}" class="w-full h-[50vh] rounded-xl border-none"></iframe>`;
            } else {
                viewerContainer.innerHTML = `<img src="${filePath}" class="max-w-full max-h-[50vh] object-contain rounded-xl">`;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => { 
                modal.classList.remove('opacity-0'); 
                modal.classList.add('opacity-100');
                modalContent.classList.remove('scale-95', 'opacity-0'); 
                modalContent.classList.add('scale-100', 'opacity-100'); 
            }, 10);
        }

        function closeDocModal() {
            modal.classList.remove('opacity-100'); 
            modal.classList.add('opacity-0');
            modalContent.classList.remove('scale-100', 'opacity-100'); 
            modalContent.classList.add('scale-95', 'opacity-0');
            setTimeout(() => { 
                modal.classList.remove('flex'); 
                modal.classList.add('hidden'); 
                viewerContainer.innerHTML = ''; 
            }, 200);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeDocModal();
            }
        });

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
        syncHeaderThemeIcon(document.documentElement.classList.contains('dark'));
    </script>

    <?php include_once 'genesis_widget.php'; ?>
</body>
</html>