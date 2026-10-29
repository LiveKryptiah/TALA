<?php
// admin_mom.php - Youth Registry Verification Gatekeeper
// Standardized to TALA OS Design System (Reference: manage_disclosure.php & admin_resolution.php)
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'db_connect.php';

// Strict Access Control (Admin / Sub-Admin)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'sub-admin'])) {
    header("Location: login.php");
    exit();
}

$admin_barangay = $_SESSION['barangay_assigned'] ?? 'All';
$isAdmin = ($_SESSION['role'] === 'admin');

// Flash Message Handling (Matching manage_disclosure.php)
$flash_msg = $_SESSION['flash_msg'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
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

// Helper: Generate next TMN- ID
function generateNextYouthId($pdo) {
    try {
        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(youth_id, 5) AS UNSIGNED)) FROM (
            SELECT youth_id FROM youth_profiles WHERE youth_id LIKE 'TMN-%'
            UNION ALL
            SELECT youth_id FROM youth_registry WHERE youth_id LIKE 'TMN-%'
        ) as combined");
        $maxId = $stmt->fetchColumn();
        $nextIdNum = $maxId ? $maxId + 1 : 1;
        return 'TMN-' . str_pad($nextIdNum, 6, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        return 'TMN-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }
}

// Ensure youth_id column exists in youth_registry
try { $pdo->exec("ALTER TABLE `youth_registry` ADD COLUMN `youth_id` VARCHAR(50) NULL"); } catch (Exception $e) {}

// Filtering & Pagination Setup
$status_filter = $_GET['status'] ?? 'All';
$barangay_filter = $_GET['barangay'] ?? 'All';
$search_query = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$queryParams = [];
if ($status_filter !== 'All') $queryParams['status'] = $status_filter;
if ($barangay_filter !== 'All') $queryParams['barangay'] = $barangay_filter;
if (!empty($search_query)) $queryParams['search'] = $search_query;
if ($page > 1) $queryParams['page'] = $page;
$filterQuery = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';

// Handle Actions (Approve / Reject / Batch)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    // Single Approve
    if (isset($_POST['approve_id'])) {
        $id = (int)$_POST['approve_id'];
        $new_id = generateNextYouthId($pdo);
        $stmt = $pdo->prepare("UPDATE youth_registry SET document_status = 'Verified', youth_id = ? WHERE id = ?");
        $stmt->execute([$new_id, $id]);
        $_SESSION['flash_msg'] = "Youth applicant approved and assigned official ID: {$new_id}";
        $_SESSION['flash_type'] = "success";
        header("Location: admin_mom.php" . $filterQuery);
        exit();
    }
    
    // Single Reject
    if (isset($_POST['reject_id'])) {
        $id = (int)$_POST['reject_id'];
        $stmt = $pdo->prepare("UPDATE youth_registry SET document_status = 'Rejected' WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['flash_msg'] = "Youth registration marked as Rejected.";
        $_SESSION['flash_type'] = "error";
        header("Location: admin_mom.php" . $filterQuery);
        exit();
    }

    // Batch Approve
    if (isset($_POST['batch_approve']) && !empty($_POST['selected_ids'])) {
        $ids = (array)$_POST['selected_ids'];
        $countApproved = 0;
        foreach ($ids as $id) {
            $new_id = generateNextYouthId($pdo);
            $stmt = $pdo->prepare("UPDATE youth_registry SET document_status = 'Verified', youth_id = ? WHERE id = ?");
            $stmt->execute([$new_id, (int)$id]);
            $countApproved++;
        }
        $_SESSION['flash_msg'] = "Batch verified and assigned official IDs to {$countApproved} youth records.";
        $_SESSION['flash_type'] = "success";
        header("Location: admin_mom.php" . $filterQuery);
        exit();
    }
}

// Telemetry KPI Stats
$pending_count = (int)$pdo->query("SELECT COUNT(*) FROM youth_registry WHERE document_status IN ('Pending Validation', 'Complete (All Files)')")->fetchColumn();
$verified_count = (int)$pdo->query("SELECT COUNT(*) FROM youth_registry WHERE document_status = 'Verified'")->fetchColumn();
$incomplete_count = (int)$pdo->query("SELECT COUNT(*) FROM youth_registry WHERE document_status = 'Incomplete' OR document_status IS NULL OR document_status = ''")->fetchColumn();
$rejected_count = (int)$pdo->query("SELECT COUNT(*) FROM youth_registry WHERE document_status = 'Rejected'")->fetchColumn();
$total_registry = (int)$pdo->query("SELECT COUNT(*) FROM youth_registry")->fetchColumn();

// Query construction
$whereClause = "WHERE 1=1";
$params = [];

if (!$isAdmin && $admin_barangay !== 'All') {
    $whereClause .= " AND barangay = ?";
    $params[] = $admin_barangay;
} elseif ($barangay_filter !== 'All') {
    $whereClause .= " AND barangay = ?";
    $params[] = $barangay_filter;
}

if ($status_filter === 'Review') {
    $whereClause .= " AND document_status IN ('Pending Validation', 'Complete (All Files)')";
} elseif ($status_filter === 'Verified') {
    $whereClause .= " AND document_status = 'Verified'";
} elseif ($status_filter === 'Incomplete') {
    $whereClause .= " AND (document_status = 'Incomplete' OR document_status IS NULL OR document_status = '')";
} elseif ($status_filter === 'Rejected') {
    $whereClause .= " AND document_status = 'Rejected'";
}

if (!empty($search_query)) {
    $whereClause .= " AND (full_name LIKE ? OR youth_id LIKE ? OR email_address LIKE ? OR cellphone_number LIKE ?)";
    $term = "%{$search_query}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

// Count total matching
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM youth_registry $whereClause");
$count_stmt->execute($params);
$filtered_count = (int)$count_stmt->fetchColumn();
$total_pages = max(1, ceil($filtered_count / $limit));

// Fetch paginated records
$list_stmt = $pdo->prepare("SELECT * FROM youth_registry $whereClause ORDER BY (document_status IN ('Pending Validation', 'Complete (All Files)')) DESC, id DESC LIMIT $limit OFFSET $offset");
$list_stmt->execute($params);
$youth_list = $list_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Verification Gatekeeper | TALA OS</title>
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

        /* Custom Checkbox */
        .batch-checkbox { 
            appearance: none; 
            background-color: transparent; 
            margin: 0; 
            width: 1.15em; 
            height: 1.15em; 
            border: 1.5px solid #cbd5e1; 
            border-radius: 6px; 
            display: grid; 
            place-content: center; 
            transition: all 0.2s ease; 
            cursor: pointer; 
        }
        .dark .batch-checkbox { border-color: #3f3f46; }
        .batch-checkbox::before { 
            content: ""; 
            width: 0.65em; 
            height: 0.65em; 
            transform: scale(0); 
            transition: 120ms transform ease-in-out; 
            box-shadow: inset 1em 1em white; 
            transform-origin: bottom left; 
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%); 
        }
        .batch-checkbox:checked { background-color: #15803d; border-color: #15803d; }
        .dark .batch-checkbox:checked { background-color: #16a34a; border-color: #16a34a; }
        .batch-checkbox:checked::before { transform: scale(1); }

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

    <!-- STANDARD TALA OS SIDEBAR (ROLE-BASED) -->
    <?php 
    if ($isAdmin) { include 'sidebar_admin.php'; } 
    else { include 'sidebar_sk.php'; } 
    ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-2.5 md:space-y-3">
            
            <!-- COMMAND CENTER HEADER WITH THREE UNIFIED GREEN KPI BADGES -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <div>
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                            Verification Gatekeeper
                        </h1>
                    </div>
                </div>
                
                <!-- Section Cards: 3 KPI Badges in Unified Solid TALA Green Icon Styling -->
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Pending Review -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-hourglass text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Pending Review</span>
                            <span class="text-base md:text-lg font-black text-amber-600 dark:text-amber-400 leading-tight"><?= number_format($pending_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Total Verified -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-shield-check text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Verified Total</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($verified_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: In Registry -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-users-three text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">In Registry</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_registry) ?></span>
                        </div>
                    </div>

                    <!-- Header Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[36px] h-[36px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
                        <i id="headerThemeIcon" class="ph-bold ph-moon text-base sm:text-lg transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- NOTIFICATIONS BANNER (Matching manage_disclosure.php) -->
            <?php if (!empty($flash_msg)): ?>
                <div class="<?= $flash_type === 'success' ? 'bg-green-50/85 dark:bg-green-500/10 text-green-700 dark:text-green-400' : 'bg-red-50/85 dark:bg-red-500/10 text-red-700 dark:text-red-400' ?> px-4 py-2.5 rounded-2xl flex items-center justify-between gap-3 text-xs md:text-sm font-bold shadow-none backdrop-blur-md transition-colors border-0 flex-shrink-0">
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
            <div class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-2.5 flex-shrink-0">
                <!-- Status Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full xl:w-auto custom-scrollbar py-0.5 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['val' => 'All', 'label' => 'All Registry', 'count' => $total_registry, 'active' => ($status_filter === 'All')],
                        ['val' => 'Review', 'label' => 'Pending Review', 'count' => $pending_count, 'active' => ($status_filter === 'Review')],
                        ['val' => 'Verified', 'label' => 'Verified', 'count' => $verified_count, 'active' => ($status_filter === 'Verified')],
                        ['val' => 'Incomplete', 'label' => 'Incomplete', 'count' => $incomplete_count, 'active' => ($status_filter === 'Incomplete')],
                        ['val' => 'Rejected', 'label' => 'Rejected', 'count' => $rejected_count, 'active' => ($status_filter === 'Rejected')],
                    ];
                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'btn-yellow-glow-r bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        
                        $tabParams = [];
                        if ($tab['val'] !== 'All') $tabParams['status'] = $tab['val'];
                        if ($barangay_filter !== 'All') $tabParams['barangay'] = $barangay_filter;
                        if (!empty($search_query)) $tabParams['search'] = $search_query;
                        $tabUrl = 'admin_mom.php' . (!empty($tabParams) ? '?' . http_build_query($tabParams) : '');
                    ?>
                        <a href="<?= $tabUrl ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span class="relative z-10"><?= $tab['label'] ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?> relative z-10"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Dropdowns, Search & Bulk Actions -->
                <div class="flex flex-wrap items-center gap-2 w-full xl:w-auto">
                    <!-- Barangay Dropdown -->
                    <?php if ($isAdmin): ?>
                    <form method="GET" class="w-full sm:w-auto sm:flex-1 md:w-40 min-w-[140px]">
                        <?php if ($status_filter !== 'All'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>"><?php endif; ?>
                        <?php if (!empty($search_query)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>"><?php endif; ?>
                        <select name="barangay" onchange="this.form.submit()" class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="All">All 46 Barangays</option>
                            <?php foreach ($all_barangays as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= $barangay_filter === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <?php endif; ?>

                    <!-- Search Input -->
                    <form method="GET" class="relative w-full sm:w-auto sm:flex-1 md:w-56">
                        <?php if ($status_filter !== 'All'): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>"><?php endif; ?>
                        <?php if ($barangay_filter !== 'All'): ?><input type="hidden" name="barangay" value="<?= htmlspecialchars($barangay_filter) ?>"><?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search youth, ID, phone..." class="w-full pl-8 pr-3 py-1.5 bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl text-xs font-semibold outline-none transition-colors border-0">
                    </form>

                    <?php if (!empty($search_query) || $barangay_filter !== 'All' || $status_filter !== 'All'): ?>
                        <a href="admin_mom.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Filters">
                            <i class="ph-bold ph-x text-xs"></i>
                        </a>
                    <?php endif; ?>

                    <!-- Bulk Action Button -->
                    <button type="button" onclick="submitBatchApprove()" class="btn-yellow-glow-r px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center justify-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none disabled:opacity-50 cursor-pointer" id="btnBatch" disabled>
                        <i class="ph-bold ph-checks text-xs relative z-10"></i>
                        <span class="relative z-10">Bulk Approve</span>
                    </button>
                </div>
            </div>

            <!-- CONSOLIDATED MINIMAL VERIFICATION REGISTRY TABLE -->
            <form method="POST" id="batchForm" class="flex-1 flex flex-col min-h-0">
                <input type="hidden" name="batch_approve" value="1">

                <div class="glass-card rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                    
                    <!-- Header Strip -->
                    <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex justify-between items-center transition-colors flex-shrink-0 border-b border-slate-100 dark:border-zinc-800/80">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Youth Registry Ledger</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                                <?= number_format($filtered_count) ?> Records
                            </span>
                        </div>
                        <span class="text-[11px] font-medium text-slate-400 dark:text-zinc-500 hidden sm:inline">Review demographics, verify proofs & assign official credentials</span>
                    </div>

                    <!-- Table Scrollable Area -->
                    <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead class="sticky top-0 z-10">
                                <tr class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider bg-slate-50/90 dark:bg-zinc-900/90 backdrop-blur-sm transition-colors border-b border-slate-100 dark:border-zinc-800/80">
                                    <th class="py-2.5 px-3 w-10 text-center">
                                        <input type="checkbox" class="batch-checkbox" id="selectAll" onclick="toggleAll(this)">
                                    </th>
                                    <th class="py-2.5 px-4">Youth Identity</th>
                                    <th class="py-2.5 px-4">Barangay & Demographics</th>
                                    <th class="py-2.5 px-4 text-center">Document Proofs</th>
                                    <th class="py-2.5 px-4 text-center">Status</th>
                                    <th class="py-2.5 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-xs font-medium divide-y divide-slate-100/80 dark:divide-zinc-800/60 transition-colors">
                                <?php if (!empty($youth_list)): ?>
                                    <?php foreach ($youth_list as $y): 
                                        $age = !empty($y['age']) ? (int)$y['age'] : 0;
                                        if ($age === 0 && !empty($y['birthday'])) {
                                            $parsedBirth = strtotime($y['birthday']);
                                            if ($parsedBirth) {
                                                $age = (int)date_diff(date_create($y['birthday']), date_create('today'))->y;
                                            }
                                        }
                                        $submitted = !empty($y['created_at']) ? date('M j, Y', strtotime($y['created_at'])) : (!empty($y['timestamp_recorded']) ? date('M j, Y', strtotime($y['timestamp_recorded'])) : '—');
                                        $status = $y['document_status'] ?? 'Incomplete';
                                        $isVerified = ($status === 'Verified');
                                        $isRejected = ($status === 'Rejected');
                                        $isReview = ($status === 'Pending Validation' || $status === 'Complete (All Files)');

                                        $hasValidId = !empty($y['valid_id_file']);
                                        $hasBirthCert = !empty($y['birth_cert_file']);
                                        $hasResidency = !empty($y['residency_file']);
                                        $docsCount = ($hasValidId ? 1 : 0) + ($hasBirthCert ? 1 : 0) + ($hasResidency ? 1 : 0);
                                    ?>
                                        <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors group">
                                            <!-- Checkbox -->
                                            <td class="py-2.5 px-3 text-center align-middle">
                                                <input type="checkbox" name="selected_ids[]" value="<?= $y['id'] ?>" class="batch-checkbox item-checkbox" onchange="checkSelection()">
                                            </td>

                                            <!-- Youth Identity -->
                                            <td class="py-2.5 px-4 align-middle">
                                                <div class="flex items-center gap-2.5">
                                                    <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                                        <i class="ph-bold ph-user"></i>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="font-bold text-slate-900 dark:text-white text-xs leading-snug truncate group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors">
                                                            <?= htmlspecialchars($y['full_name']) ?>
                                                        </p>
                                                        <div class="flex items-center gap-1.5 text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">
                                                            <?php if (!empty($y['youth_id'])): ?>
                                                                <span class="font-mono font-bold text-green-700 dark:text-green-400"><?= htmlspecialchars($y['youth_id']) ?></span>
                                                                <span>&bull;</span>
                                                            <?php endif; ?>
                                                            <span><?= $submitted ?></span>
                                                            <?php if (!empty($y['cellphone_number'])): ?>
                                                                <span>&bull;</span>
                                                                <span class="truncate max-w-[100px]"><?= htmlspecialchars($y['cellphone_number']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Location & Age -->
                                            <td class="py-2.5 px-4 align-middle whitespace-nowrap">
                                                <p class="text-xs font-semibold text-slate-700 dark:text-zinc-300 flex items-center gap-1">
                                                    <i class="ph-fill ph-map-pin text-green-600 dark:text-green-500 text-[11px]"></i>
                                                    <span>Brgy. <?= htmlspecialchars($y['barangay']) ?></span>
                                                </p>
                                                <p class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium mt-0.5">
                                                    <?= $age > 0 ? $age . ' Yrs' : 'Age —' ?> &bull; <?= htmlspecialchars($y['sex'] ?? '—') ?>
                                                </p>
                                            </td>

                                            <!-- Submitted Document Proofs -->
                                            <td class="py-2.5 px-4 text-center align-middle whitespace-nowrap">
                                                <?php if ($docsCount > 0): ?>
                                                    <button type="button" onclick="openDocModal(<?= htmlspecialchars(json_encode($y), ENT_QUOTES, 'UTF-8') ?>)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-green-500/10 hover:bg-green-500/20 text-green-700 dark:text-green-400 text-[11px] font-bold tap-effect transition-colors">
                                                        <i class="ph-bold ph-identification-card text-xs"></i>
                                                        <span>Inspect (<?= $docsCount ?>)</span>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-slate-400 dark:text-zinc-500 bg-slate-100 dark:bg-zinc-800/60 px-2 py-0.5 rounded-md">
                                                        <i class="ph-bold ph-file-dashed text-xs"></i> None
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Status -->
                                            <td class="py-2.5 px-4 text-center align-middle whitespace-nowrap">
                                                <?php if ($isVerified): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-green-500/10 text-green-700 dark:text-green-400">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Verified
                                                    </span>
                                                <?php elseif ($isRejected): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-500/10 text-rose-700 dark:text-rose-400">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Rejected
                                                    </span>
                                                <?php elseif ($isReview): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-400">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending Review
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400">
                                                        <?= htmlspecialchars($status) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Actions -->
                                            <td class="py-2.5 px-4 text-right align-middle whitespace-nowrap">
                                                <div class="flex items-center justify-end gap-1">
                                                    <?php if (!$isVerified): ?>
                                                        <button type="button" onclick="approveSingle(<?= $y['id'] ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-green-500/10 transition-colors tap-effect" title="Approve & Assign ID">
                                                            <i class="ph-bold ph-check text-xs"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if (!$isRejected): ?>
                                                        <button type="button" onclick="rejectSingle(<?= $y['id'] ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-500/10 transition-colors tap-effect" title="Reject Application">
                                                            <i class="ph-bold ph-x text-xs"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="py-14 text-center text-slate-400 dark:text-zinc-500 select-none">
                                            <img src="mascot_question.png" alt="No matches" class="w-16 h-16 object-contain mx-auto mb-2 opacity-80">
                                            <p class="font-extrabold text-sm text-slate-800 dark:text-zinc-200">No Youth Records Found</p>
                                            <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium mt-0.5">Try clearing active search or filters.</p>
                                            <?php if (!empty($search_query) || $barangay_filter !== 'All' || $status_filter !== 'All'): ?>
                                                <a href="admin_mom.php" class="mt-3 inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold tap-effect transition-colors">
                                                    <span>Clear Filters</span>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Minimal Table Footer with Pagination Controls -->
                    <div class="px-4 py-2.5 bg-white/40 dark:bg-zinc-900/40 flex flex-col sm:flex-row justify-between items-center gap-2 text-xs text-slate-500 dark:text-zinc-400 font-medium flex-shrink-0 border-t border-slate-100 dark:border-zinc-800/80">
                        <span>
                            Showing <strong class="text-slate-800 dark:text-zinc-200"><?= count($youth_list) ?></strong> of <strong class="text-slate-800 dark:text-zinc-200"><?= number_format($filtered_count) ?></strong> entries (Page <?= $page ?> of <?= $total_pages ?>)
                        </span>

                        <!-- Pagination Links -->
                        <?php if ($total_pages > 1): ?>
                            <div class="flex items-center gap-1">
                                <?php if ($page > 1): 
                                    $prevParams = $queryParams; $prevParams['page'] = $page - 1;
                                ?>
                                    <a href="admin_mom.php?<?= http_build_query($prevParams) ?>" class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 font-bold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">Prev</a>
                                <?php endif; ?>
                                
                                <span class="px-2 py-1 text-slate-400 dark:text-zinc-500 font-mono text-[11px]"><?= $page ?> / <?= $total_pages ?></span>

                                <?php if ($page < $total_pages): 
                                    $nextParams = $queryParams; $nextParams['page'] = $page + 1;
                                ?>
                                    <a href="admin_mom.php?<?= http_build_query($nextParams) ?>" class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 font-bold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">Next</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </form>

        </div>
    </main>

    <!-- HIDDEN FORMS FOR SINGLE ACTIONS -->
    <form id="actionForm" method="POST" class="hidden">
        <input type="hidden" name="approve_id" id="approve_id">
        <input type="hidden" name="reject_id" id="reject_id">
    </form>

    <!-- DOCUMENT INSPECTOR MODAL (Matching manage_disclosure.php popup standards) -->
    <div id="idModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeDocModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-2xl w-full p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800" id="idModalBox">
            
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-zinc-800">
                <div class="flex items-center gap-2.5 min-w-0 pr-2">
                    <div class="w-8 h-8 bg-green-500/10 text-green-600 dark:text-green-500 rounded-xl flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-bold ph-identification-badge"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight truncate" id="modalYouthName">Youth Document Dossier</h3>
                        <p class="text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-mono mt-0.5 truncate" id="modalYouthMeta">Demographic Proofs</p>
                    </div>
                </div>
                <button type="button" onclick="closeDocModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect flex-shrink-0">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Document Tabs Selector -->
            <div id="modalDocTabs" class="flex items-center gap-1.5 mb-3 overflow-x-auto custom-scrollbar pb-1">
                <!-- Dynamically populated buttons -->
            </div>

            <!-- Viewer Container -->
            <div class="p-3 bg-slate-50 dark:bg-zinc-950/80 rounded-2xl min-h-[300px] max-h-[55vh] flex flex-col justify-center items-center overflow-y-auto custom-scrollbar border border-slate-100 dark:border-zinc-850">
                <div id="idViewerContainer" class="w-full h-full flex flex-col justify-center items-center">
                    <p class="text-xs text-slate-400 dark:text-zinc-500">Select a document tab above to preview</p>
                </div>
            </div>

            <!-- Modal Footer with Quick Decision Actions -->
            <div class="pt-3 mt-3 flex items-center justify-between gap-2 border-t border-slate-100 dark:border-zinc-800">
                <button type="button" onclick="closeDocModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm transition-all tap-effect">
                    Close
                </button>

                <div class="flex items-center gap-2">
                    <button type="button" id="modalBtnReject" class="px-3.5 py-2 bg-rose-500/10 hover:bg-rose-500/20 text-rose-700 dark:text-rose-400 font-bold rounded-xl text-xs transition-all tap-effect">
                        Reject
                    </button>
                    <button type="button" id="modalBtnApprove" class="btn-yellow-glow-r px-4 py-2 bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold rounded-xl text-xs transition-all tap-effect flex items-center gap-1.5 shadow-none">
                        <span class="relative z-10">Approve & Issue ID</span>
                        <i class="ph-bold ph-check text-xs relative z-10"></i>
                    </button>
                </div>
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
        <a href="tala_directory.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-address-book text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Directory</span>
        </a>
        <a href="admin_mom.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-folders text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Gatekeeper</span>
        </a>
        <a href="admin_approval.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-shield-check text-2xl"></i>
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

        // Theme Toggle Sync
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
            localStorage.theme = isDark ? 'dark' : 'light';
            syncHeaderThemeIcon(isDark);
        }
        syncHeaderThemeIcon(document.documentElement.classList.contains('dark'));

        // Checkbox Logic
        function toggleAll(source) {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
            checkSelection();
        }

        function checkSelection() {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            const btn = document.getElementById('btnBatch');
            let anyChecked = false;
            checkboxes.forEach(cb => { if(cb.checked) anyChecked = true; });
            if (btn) btn.disabled = !anyChecked;
        }

        function submitBatchApprove() {
            if(confirm('Confirm Batch Action: Approve and assign official TALA IDs to all selected youth?')) {
                document.getElementById('batchForm').submit();
            }
        }

        // Single Actions
        function approveSingle(id) {
            if(confirm('Verify and assign official TALA Youth ID?')) {
                document.getElementById('approve_id').value = id;
                document.getElementById('reject_id').value = '';
                document.getElementById('actionForm').submit();
            }
        }

        function rejectSingle(id) {
            if(confirm('Reject this youth registration?')) {
                document.getElementById('reject_id').value = id;
                document.getElementById('approve_id').value = '';
                document.getElementById('actionForm').submit();
            }
        }

        // Document Modal Logic
        const docOverlay = document.getElementById('idModalOverlay');
        const docBox = document.getElementById('idModalBox');
        const viewerContainer = document.getElementById('idViewerContainer');
        const docTabs = document.getElementById('modalDocTabs');
        const btnModalApprove = document.getElementById('modalBtnApprove');
        const btnModalReject = document.getElementById('modalBtnReject');

        function openDocModal(youth) {
            document.getElementById('modalYouthName').innerText = youth.full_name || 'Youth Dossier';
            document.getElementById('modalYouthMeta').innerText = 'Brgy. ' + (youth.barangay || '—') + ' • ' + (youth.youth_id || 'Pending ID');

            if (btnModalApprove) {
                btnModalApprove.onclick = () => { closeDocModal(); approveSingle(youth.id); };
            }
            if (btnModalReject) {
                btnModalReject.onclick = () => { closeDocModal(); rejectSingle(youth.id); };
            }

            // Build tabs for available documents
            const docs = [];
            if (youth.valid_id_file) docs.push({ label: 'Valid ID', path: youth.valid_id_file });
            if (youth.birth_cert_file) docs.push({ label: 'Birth Certificate', path: youth.birth_cert_file });
            if (youth.residency_file) docs.push({ label: 'Residency Certificate', path: youth.residency_file });

            docTabs.innerHTML = '';
            if (docs.length === 0) {
                viewerContainer.innerHTML = '<p class="text-xs text-slate-400 dark:text-zinc-500 font-semibold">No uploaded document files recorded.</p>';
            } else {
                docs.forEach((doc, idx) => {
                    const tabBtn = document.createElement('button');
                    tabBtn.type = 'button';
                    tabBtn.className = `px-3 py-1.5 rounded-xl text-xs font-bold transition-all tap-effect ${idx === 0 ? 'bg-[#15803d] text-white' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400'}`;
                    tabBtn.innerText = doc.label;
                    tabBtn.onclick = () => {
                        Array.from(docTabs.children).forEach(b => {
                            b.className = 'px-3 py-1.5 rounded-xl text-xs font-bold transition-all tap-effect bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400';
                        });
                        tabBtn.className = 'px-3 py-1.5 rounded-xl text-xs font-bold transition-all tap-effect bg-[#15803d] text-white';
                        renderDocPreview(doc.path);
                    };
                    docTabs.appendChild(tabBtn);
                });
                renderDocPreview(docs[0].path);
            }

            if (docOverlay && docBox) {
                docOverlay.classList.remove('hidden');
                docOverlay.classList.add('flex');
                requestAnimationFrame(() => {
                    docOverlay.classList.remove('opacity-0');
                    docOverlay.classList.add('opacity-100');
                    docBox.classList.remove('scale-95', 'opacity-0');
                    docBox.classList.add('scale-100', 'opacity-100');
                });
            }
        }

        function renderDocPreview(filePath) {
            viewerContainer.innerHTML = '';
            if (!filePath) {
                viewerContainer.innerHTML = '<p class="text-xs text-slate-400 dark:text-zinc-500">File link unavailable.</p>';
                return;
            }

            // Check if external URL
            if (filePath.startsWith('http://') || filePath.startsWith('https://')) {
                viewerContainer.innerHTML = `
                    <div class="flex flex-col items-center justify-center p-6 text-center space-y-3">
                        <div class="w-12 h-12 rounded-2xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-2xl">
                            <i class="ph-bold ph-link-simple"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-800 dark:text-zinc-200">External Document Link</p>
                            <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5 truncate max-w-sm">${filePath}</p>
                        </div>
                        <a href="${filePath}" target="_blank" rel="noopener noreferrer" class="px-4 py-2 rounded-xl bg-[#15803d] text-white text-xs font-bold flex items-center gap-1.5 tap-effect hover:bg-[#166534]">
                            <span>Open in External Viewer</span>
                            <i class="ph-bold ph-arrow-square-out text-sm"></i>
                        </a>
                    </div>
                `;
                return;
            }

            const ext = filePath.split('.').pop().toLowerCase();
            if (ext === 'pdf') {
                viewerContainer.innerHTML = `<iframe src="${filePath}" class="w-full h-[50vh] rounded-xl border-none"></iframe>`;
            } else {
                viewerContainer.innerHTML = `<img src="${filePath}" alt="Document Preview" class="max-w-full max-h-[50vh] object-contain rounded-xl">`;
            }
        }

        function closeDocModal() {
            if (docOverlay && docBox) {
                docOverlay.classList.remove('opacity-100');
                docOverlay.classList.add('opacity-0');
                docBox.classList.remove('scale-100', 'opacity-100');
                docBox.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    docOverlay.classList.remove('flex');
                    docOverlay.classList.add('hidden');
                    viewerContainer.innerHTML = '';
                }, 200);
            }
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && docOverlay && !docOverlay.classList.contains('hidden')) {
                closeDocModal();
            }
        });
    </script>

    <!-- Genesis AI Assistant Widget -->
    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>