<?php
// tickets.php - E-Sumbong Helpdesk & Citizen Grievance Ledger (TALA OS Standard)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Strict Access Control: Admin or Sub-Admin
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'sub-admin')) {
    header("Location: login.php");
    exit();
}

// Auto-upgrade suggestions table ensuring tracking ID and Barangay exist
try {
    $cols = $pdo->query("SHOW COLUMNS FROM suggestions")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('status', $cols)) {
        $pdo->exec("ALTER TABLE suggestions ADD COLUMN status VARCHAR(50) DEFAULT 'Open'");
        $pdo->exec("ALTER TABLE suggestions ADD COLUMN admin_reply TEXT NULL");
        $pdo->exec("ALTER TABLE suggestions ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    if (!in_array('ticket_id', $cols)) {
        $pdo->exec("ALTER TABLE suggestions ADD COLUMN ticket_id VARCHAR(50) NULL AFTER id");
        $pdo->exec("ALTER TABLE suggestions ADD COLUMN barangay VARCHAR(100) NULL AFTER contact_info");
    }
} catch (Exception $e) {}

// Build preserved filter query helper
function buildUrl($params = []) {
    $current = $_GET;
    foreach ($params as $k => $v) {
        if ($v === null || $v === '' || $v === 'All') {
            unset($current[$k]);
        } else {
            $current[$k] = $v;
        }
    }
    return 'tickets.php' . (!empty($current) ? '?' . http_build_query($current) : '');
}

$status_filter = $_GET['status'] ?? 'All';
$search_query = trim($_GET['search'] ?? '');
$barangay_filter = $_GET['barangay'] ?? 'All';

$filter_params = [];
if ($status_filter !== 'All') $filter_params['status'] = $status_filter;
if ($barangay_filter !== 'All') $filter_params['barangay'] = $barangay_filter;
if (!empty($search_query)) $filter_params['search'] = $search_query;
$filterQuery = !empty($filter_params) ? '&' . http_build_query($filter_params) : '';

// Flash Notification Messages
$flash_msg = $_SESSION['flash_msg'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// Handle Reply & Status (Zero intrusive popups / clean redirect)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['resolve_ticket'])) {
    $id = (int)$_POST['ticket_id'];
    $status = $_POST['status'];
    $reply = trim($_POST['admin_reply'] ?? '');
    
    if (in_array($status, ['Open', 'In Progress', 'Resolved'])) {
        $stmt = $pdo->prepare("UPDATE suggestions SET status = ?, admin_reply = ? WHERE id = ?");
        $stmt->execute([$status, $reply, $id]);
        $_SESSION['flash_msg'] = "Ticket #{$id} status updated to '{$status}'.";
        $_SESSION['flash_type'] = "success";
    }
    
    header("Location: tickets.php" . (!empty($filterQuery) ? '?' . ltrim($filterQuery, '&') : ''));
    exit();
}

// Handle Delete Ticket (Clean redirect)
if (isset($_GET['delete_id'])) {
    $del_id = (int)$_GET['delete_id'];
    $pdo->prepare("DELETE FROM suggestions WHERE id = ?")->execute([$del_id]);
    $_SESSION['flash_msg'] = "Ticket record removed permanently.";
    $_SESSION['flash_type'] = "success";
    header("Location: tickets.php" . (!empty($filterQuery) ? '?' . ltrim($filterQuery, '&') : ''));
    exit();
}

// 46 Tumauini Barangays
$all_barangays = [
    "Annafunan", "Antagan I", "Antagan II", "Arcon", "Balug", "Banig", "Bantug", 
    "Barangay District 1 (Poblacion)", "Barangay District 2 (Poblacion)", "Barangay District 3 (Poblacion)", 
    "Barangay District 4 (Poblacion)", "Bayabo East", "Caligayan", "Camasi", "Carpentero", 
    "Compania", "Cumabao", "Fermeldy (Hacienda San Francisco)", "Fugu Abajo", "Fugu Norte", 
    "Fugu Sur", "Lalauanan", "Lanna", "Lapogan", "Lingaling (Poblacion)", "Liwanag", 
    "Malamag East", "Malamag West", "Maligaya (Poblacion)", "Minanga", "Moldero", 
    "Namnama", "Paragu", "Pilitan", "San Mateo", "San Pedro", "San Vicente", 
    "Santa", "Santa Catalina", "Santa Visitacion (Maggayu)", "Santo Niño", "Sinippil", 
    "Sisim Abajo", "Sisim Alto", "Tunggui", "Ugad"
];

// Telemetry Stats
$open_count = (int)$pdo->query("SELECT COUNT(*) FROM suggestions WHERE status = 'Open'")->fetchColumn();
$in_progress_count = (int)$pdo->query("SELECT COUNT(*) FROM suggestions WHERE status = 'In Progress'")->fetchColumn();
$resolved_count = (int)$pdo->query("SELECT COUNT(*) FROM suggestions WHERE status = 'Resolved'")->fetchColumn();
$total_count = (int)$pdo->query("SELECT COUNT(*) FROM suggestions")->fetchColumn();

// Fetch Tickets with filters
$sql = "SELECT * FROM suggestions WHERE 1=1";
$params = [];

if ($status_filter !== 'All' && in_array($status_filter, ['Open', 'In Progress', 'Resolved'])) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}

if ($barangay_filter !== 'All' && !empty($barangay_filter)) {
    $sql .= " AND barangay = ?";
    $params[] = $barangay_filter;
}

if (!empty($search_query)) {
    $sql .= " AND (ticket_id LIKE ? OR sender_name LIKE ? OR message LIKE ? OR category LIKE ?)";
    $term = "%{$search_query}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY FIELD(status, 'Open', 'In Progress', 'Resolved'), created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$current_filtered_count = count($tickets);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>E-Sumbong Helpdesk | TALA OS</title>
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
        .shadow-soft,
        .shadow-inner {
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
            #tala-admin-sidebar, #tala-bulletproof-sk-sidebar, nav, #headerThemeToggle, .no-print, form, .mobile-overlay { display: none !important; }
            main { margin-left: 0 !important; padding: 0 !important; min-height: auto !important; height: auto !important; }
            .glass-card { background: transparent !important; }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">
    
    <!-- STANDARD TALA OS ADMIN SIDEBAR -->
    <?php include 'sidebar_admin.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-start">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-3 md:space-y-4">
            
            <!-- COMMAND CENTER HEADER & KPI BADGES (Clearance xl:pr-14 for Dark Mode Toggle) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <div>
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                            E-Sumbong Helpdesk
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 font-medium">Citizen grievances, inquiries, suggestions & resolution audit trails</p>
                    </div>
                </div>
                
                <!-- Section Cards (KPI badges) & Dark Mode Toggle -->
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Total Inquiries -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-ticket text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Inquiries</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Open Tickets -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-rose-600 dark:text-rose-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-warning-circle text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Open</span>
                            <span class="text-base md:text-lg font-black text-rose-600 dark:text-rose-400 leading-tight"><?= number_format($open_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: In Progress -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-amber-600 dark:text-amber-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-hourglass-high text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">In Progress</span>
                            <span class="text-base md:text-lg font-black text-amber-600 dark:text-amber-400 leading-tight"><?= number_format($in_progress_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 4: Resolved -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-check-circle text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Resolved</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($resolved_count) ?></span>
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

            <!-- FILTER, SEARCH & ACTION CONTROL BAR (Matching manage_disclosure.php standard) -->
            <div class="glass-card card-green-glow-bl rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-2.5">
                
                <!-- Quick Status Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full xl:w-auto custom-scrollbar py-0.5 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['val' => 'All', 'label' => 'All Records', 'count' => $total_count, 'active' => ($status_filter === 'All' || empty($status_filter))],
                        ['val' => 'Open', 'label' => 'Open', 'count' => $open_count, 'active' => ($status_filter === 'Open')],
                        ['val' => 'In Progress', 'label' => 'In Progress', 'count' => $in_progress_count, 'active' => ($status_filter === 'In Progress')],
                        ['val' => 'Resolved', 'label' => 'Resolved', 'count' => $resolved_count, 'active' => ($status_filter === 'Resolved')],
                    ];

                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        
                        $tabUrl = buildUrl(['status' => ($tab['val'] === 'All' ? '' : $tab['val'])]);
                    ?>
                        <a href="<?= $tabUrl ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span class="relative z-10"><?= htmlspecialchars($tab['label']) ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 dark:bg-black/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?> relative z-10"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Dropdown, Search, Print & Filter Actions -->
                <form method="GET" action="tickets.php" class="flex flex-wrap items-center gap-2 w-full xl:w-auto">
                    <?php if ($status_filter !== 'All'): ?>
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                    <?php endif; ?>

                    <!-- Keyword Search -->
                    <div class="relative flex-1 sm:w-60 min-w-[170px]">
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search ticket, name, message..." class="w-full pl-8 pr-3 py-1.5 bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 placeholder-slate-400 dark:placeholder-zinc-500 text-xs font-semibold rounded-xl focus:outline-none transition-colors border-0">
                    </div>

                    <!-- Barangay Dropdown Selector -->
                    <div class="w-full sm:w-44">
                        <select name="barangay" onchange="this.form.submit()" class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="">All 46 Barangays</option>
                            <?php foreach ($all_barangays as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= $barangay_filter === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Submit Button -->
                    <button type="submit" class="bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 font-bold py-1.5 px-3 rounded-xl transition-all text-xs tap-effect flex items-center gap-1">
                        <i class="ph-bold ph-funnel text-xs"></i>
                        <span>Filter</span>
                    </button>

                    <!-- Clear Filter Reset -->
                    <?php if (!empty($search_query) || ($status_filter !== 'All' && !empty($status_filter)) || ($barangay_filter !== 'All' && !empty($barangay_filter))): ?>
                        <a href="tickets.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 font-bold h-8 w-8 rounded-xl text-xs transition-all tap-effect" title="Clear Filters">
                            <i class="ph-bold ph-x"></i>
                        </a>
                    <?php endif; ?>

                    <!-- Print Ledger Button -->
                    <button type="button" onclick="window.print()" class="no-print hidden sm:flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 font-bold h-8 px-2.5 rounded-xl text-xs transition-all tap-effect" title="Print Ledger">
                        <i class="ph-bold ph-printer text-sm mr-1"></i>
                        <span>Print</span>
                    </button>
                </form>

            </div>

            <!-- CONSOLIDATED MINIMAL TABLE CARD (Matching manage_disclosure.php standard) -->
            <div class="glass-card card-green-glow-bl rounded-2xl md:rounded-3xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Table Header Bar -->
                <div class="p-3 md:p-3.5 bg-white/50 dark:bg-zinc-900/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 border-b border-slate-100 dark:border-zinc-800/80 transition-colors flex-shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-base font-bold flex-shrink-0">
                            <i class="ph-fill ph-ticket"></i>
                        </div>
                        <div>
                            <h2 class="text-xs md:text-sm font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-1.5">
                                <span>Citizen Grievance & E-Sumbong Ledger</span>
                            </h2>
                            <p class="text-[11px] md:text-xs text-slate-500 dark:text-zinc-400 font-medium">Community feedback, suggestions, complaint tickets, and resolution audit trails</p>
                        </div>
                    </div>
                    <span class="text-xs font-bold text-slate-700 dark:text-zinc-300 bg-slate-100 dark:bg-zinc-800 px-2.5 py-0.5 rounded-lg">
                        <?= number_format(count($tickets)) ?> of <?= number_format($total_count) ?> Records
                    </span>
                </div>

                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[850px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[11px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider bg-slate-100/90 dark:bg-zinc-800/90 backdrop-blur-sm transition-colors border-b border-slate-100 dark:border-zinc-800/80">
                                <th class="py-2.5 px-4 md:px-5">Ticket & Citizen Profile</th>
                                <th class="py-2.5 px-4 md:px-5">Barangay & Category</th>
                                <th class="py-2.5 px-4 md:px-5">Grievance / Message</th>
                                <th class="py-2.5 px-4 md:px-5 text-center">Ticket Status</th>
                                <th class="py-2.5 px-4 md:px-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs md:text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($tickets)): ?>
                                <?php foreach ($tickets as $t): 
                                    $t_status = $t['status'] ?? 'Open';
                                    $status_styles = [
                                        'Open' => [
                                            'badge' => 'bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-400',
                                            'dot' => 'bg-rose-500',
                                            'label' => 'Open'
                                        ],
                                        'In Progress' => [
                                            'badge' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400',
                                            'dot' => 'bg-amber-500',
                                            'label' => 'In Progress'
                                        ],
                                        'Resolved' => [
                                            'badge' => 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400',
                                            'dot' => 'bg-green-500',
                                            'label' => 'Resolved'
                                        ],
                                    ];
                                    $s_style = $status_styles[$t_status] ?? $status_styles['Open'];
                                    $has_reply = !empty($t['admin_reply']);
                                ?>
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-800/40 transition-colors group">
                                        
                                        <!-- Citizen Profile & Ticket ID -->
                                        <td class="py-2.5 px-4 md:px-5 align-middle">
                                            <div class="flex items-start gap-2.5">
                                                <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-sm font-bold flex-shrink-0 mt-0.5 group-hover:scale-105 transition-transform">
                                                    <i class="ph-fill ph-ticket"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-bold text-slate-900 dark:text-white text-xs md:text-sm leading-tight truncate group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors">
                                                        <?= htmlspecialchars($t['sender_name']) ?>
                                                    </p>
                                                    <div class="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-zinc-400 mt-0.5">
                                                        <?php if (!empty($t['ticket_id'])): ?>
                                                            <span class="font-mono font-bold text-slate-700 dark:text-zinc-300">#<?= htmlspecialchars($t['ticket_id']) ?></span>
                                                            <span>&bull;</span>
                                                        <?php endif; ?>
                                                        <span><?= date('M j, Y • h:i A', strtotime($t['created_at'])) ?></span>
                                                    </div>
                                                    <?php if (!empty($t['contact_info'])): ?>
                                                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 mt-0.5">
                                                            <i class="ph-bold ph-phone text-[9px] mr-0.5"></i> <?= htmlspecialchars($t['contact_info']) ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        
                                        <!-- Location & Category -->
                                        <td class="py-2.5 px-4 md:px-5 whitespace-nowrap align-middle">
                                            <?php if (!empty($t['barangay'])): ?>
                                                <p class="text-xs font-semibold text-slate-700 dark:text-zinc-300 flex items-center gap-1 mb-1">
                                                    <i class="ph-fill ph-map-pin text-green-600 dark:text-green-500"></i>
                                                    <span>Brgy. <?= htmlspecialchars($t['barangay']) ?></span>
                                                </p>
                                            <?php endif; ?>
                                            <span class="inline-block bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider">
                                                <?= htmlspecialchars($t['category'] ?? 'General') ?>
                                            </span>
                                        </td>
                                        
                                        <!-- Message -->
                                        <td class="py-2.5 px-4 md:px-5 align-middle max-w-xs md:max-w-md">
                                            <p class="text-xs text-slate-800 dark:text-zinc-200 line-clamp-2 leading-relaxed">
                                                <?= htmlspecialchars($t['message']) ?>
                                            </p>
                                            <?php if ($has_reply): ?>
                                                <div class="mt-1 flex items-center gap-1 text-[10px] font-bold text-green-700 dark:text-green-400">
                                                    <i class="ph-fill ph-check-circle"></i>
                                                    <span class="truncate">Resolved note logged</span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <!-- Status Badge -->
                                        <td class="py-2.5 px-4 md:px-5 text-center whitespace-nowrap align-middle">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider <?= $s_style['badge'] ?>">
                                                <span class="w-1.5 h-1.5 rounded-full <?= $s_style['dot'] ?>"></span>
                                                <?= $s_style['label'] ?>
                                            </span>
                                        </td>
                                        
                                        <!-- Minimal Ghost Action Buttons -->
                                        <td class="py-2.5 px-4 md:px-5 text-right whitespace-nowrap align-middle">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button" onclick="openViewModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect" title="Inspect Full Ticket">
                                                    <i class="ph-bold ph-eye text-xs"></i>
                                                </button>

                                                <button type="button" onclick="openResolveModal(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-green-50 dark:hover:bg-green-500/10 transition-colors tap-effect" title="Resolve / Update Status">
                                                    <i class="ph-bold ph-pencil-simple text-xs"></i>
                                                </button>

                                                <a href="tickets.php?delete_id=<?= $t['id'] ?><?= $filterQuery ?>" onclick="return confirm('Confirm Deletion: Permanently remove this citizen ticket?')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Delete Ticket">
                                                    <i class="ph-bold ph-trash text-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <div class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-2 flex items-center justify-center">
                                            <img src="mascot_question.png" alt="No Tickets" class="max-h-full max-w-full object-contain pointer-events-none hover:scale-105 transition-transform duration-300">
                                        </div>
                                        <p class="font-bold text-xs md:text-sm text-slate-600 dark:text-zinc-300">No community feedback or grievance tickets found.</p>
                                        <p class="text-xs text-slate-400 dark:text-zinc-500 mt-0.5">Try clearing filters or check back later for citizen submissions.</p>
                                        <?php if (!empty($search_query) || ($status_filter !== 'All' && !empty($status_filter)) || ($barangay_filter !== 'All' && !empty($barangay_filter))): ?>
                                            <a href="tickets.php" class="mt-3 inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
                                                <span>Clear Filters</span>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Table Footer Info -->
                <div class="px-4 py-2.5 bg-white/40 dark:bg-zinc-900/40 flex justify-between items-center text-xs text-slate-500 dark:text-zinc-400 font-medium flex-shrink-0 border-t border-slate-100 dark:border-zinc-800/80">
                    <span class="text-[11px]">Showing <strong class="text-slate-800 dark:text-zinc-200 font-bold"><?= count($tickets) ?></strong> tickets</span>
                    <span class="text-[10px] text-slate-400 dark:text-zinc-500">TALA OS &bull; E-Sumbong Helpdesk</span>
                </div>
            </div>

        </div>
    </main>

    <!-- RESOLVE / REPLY MODAL (Spring Pop-up Effect) -->
    <div id="resolveModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeResolveModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] card-green-glow-bl overflow-hidden max-w-lg md:max-w-xl w-full p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border-0 max-h-[90vh] overflow-y-auto custom-scrollbar" id="resolveModalBox">
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-4 border-b border-slate-100 dark:border-zinc-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-bold ph-ticket"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight">Resolve Citizen Ticket</h3>
                        <p class="text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-mono mt-0.5" id="modal_ticket_tag">Ticket #0</p>
                    </div>
                </div>
                <button type="button" onclick="closeResolveModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Content & Form -->
            <form action="tickets.php<?= !empty($filterQuery) ? '?' . ltrim($filterQuery, '&') : '' ?>" method="POST" class="space-y-3.5">
                <input type="hidden" name="resolve_ticket" value="1">
                <input type="hidden" name="ticket_id" id="modal_form_ticket_id" value="">

                <div class="bg-slate-50 dark:bg-zinc-950/80 p-3.5 rounded-xl space-y-1">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">Citizen Inquiry Message</span>
                    <p class="text-xs text-slate-800 dark:text-zinc-200 leading-relaxed font-medium" id="modal_citizen_message">-</p>
                </div>

                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Ticket Status *</label>
                    <select name="status" id="modal_status_select" class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none transition-colors cursor-pointer border-0">
                        <option value="Open">Open (Pending Evaluation)</option>
                        <option value="In Progress">In Progress (Active Action)</option>
                        <option value="Resolved">Resolved (Completed Action)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Resolution / Action Note</label>
                    <textarea name="admin_reply" id="modal_admin_reply" rows="4" placeholder="Document the municipal or SK actions taken to address this ticket..." class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none transition-colors resize-none custom-scrollbar border-0"></textarea>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button" onclick="closeResolveModal()" class="px-4 py-2 bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm hover:bg-slate-200 dark:hover:bg-zinc-700 transition-all tap-effect">
                        Cancel
                    </button>
                    <button type="submit" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2 px-5 rounded-xl text-xs md:text-sm transition-all tap-effect flex items-center gap-1.5 shadow-none">
                        <span class="relative z-10">Save Status</span>
                        <i class="ph-bold ph-check text-sm relative z-10"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TICKET DETAIL VIEW MODAL (Spring Pop-up Effect) -->
    <div id="viewTicketModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeViewModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] card-green-glow-bl overflow-hidden max-w-lg md:max-w-xl w-full p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border-0 max-h-[90vh] overflow-y-auto custom-scrollbar" id="viewTicketModalBox">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-4 border-b border-slate-100 dark:border-zinc-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-fill ph-ticket"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight" id="view_sender_name">Citizen Inquiry</h3>
                        <p class="text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-mono mt-0.5" id="view_meta">Ticket Dossier</p>
                    </div>
                </div>
                <button type="button" onclick="closeViewModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Content -->
            <div class="space-y-3 text-xs md:text-sm max-h-[70vh] overflow-y-auto custom-scrollbar pr-1">
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="bg-slate-50 dark:bg-zinc-950/80 p-2.5 rounded-xl">
                        <span class="block text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-0.5">Barangay</span>
                        <span class="font-bold text-slate-800 dark:text-white" id="view_barangay">-</span>
                    </div>
                    <div class="bg-slate-50 dark:bg-zinc-950/80 p-2.5 rounded-xl">
                        <span class="block text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-0.5">Status</span>
                        <span class="font-black" id="view_status">-</span>
                    </div>
                </div>

                <div class="bg-slate-50 dark:bg-zinc-950/80 p-3 rounded-xl space-y-1">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">Citizen Contact</span>
                    <p class="font-bold text-slate-800 dark:text-white" id="view_contact">-</p>
                </div>

                <div class="bg-slate-50 dark:bg-zinc-950/80 p-3 rounded-xl space-y-1">
                    <span class="block text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">Submitted Message</span>
                    <p class="text-slate-800 dark:text-zinc-200 leading-relaxed font-medium" id="view_message">-</p>
                </div>

                <div class="bg-slate-50 dark:bg-zinc-950/80 p-3 rounded-xl space-y-1" id="view_reply_box">
                    <span class="block text-[10px] font-bold text-green-700 dark:text-green-400 uppercase tracking-wider">Municipal Action Log</span>
                    <p class="text-slate-800 dark:text-zinc-200 leading-relaxed font-medium" id="view_reply">-</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="pt-3 mt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-end gap-2">
                <button type="button" onclick="closeViewModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm transition-all tap-effect">
                    Close
                </button>
            </div>
        </div>
    </div>

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

        // Resolve Modal Logic (Spring Pop-up Effect)
        const resolveOverlay = document.getElementById('resolveModalOverlay');
        const resolveBox = document.getElementById('resolveModalBox');

        function openResolveModal(ticket) {
            document.getElementById('modal_ticket_tag').innerText = 'Ticket #' + (ticket.ticket_id || ticket.id);
            document.getElementById('modal_form_ticket_id').value = ticket.id;
            document.getElementById('modal_citizen_message').innerText = ticket.message || '';
            document.getElementById('modal_status_select').value = ticket.status || 'Open';
            document.getElementById('modal_admin_reply').value = ticket.admin_reply || '';

            if (resolveOverlay && resolveBox) {
                resolveOverlay.classList.remove('hidden');
                resolveOverlay.classList.add('flex');
                requestAnimationFrame(() => {
                    resolveOverlay.classList.remove('opacity-0');
                    resolveOverlay.classList.add('opacity-100');
                    resolveBox.classList.remove('scale-95', 'opacity-0');
                    resolveBox.classList.add('scale-100', 'opacity-100');
                });
            }
        }

        function closeResolveModal() {
            if (resolveOverlay && resolveBox) {
                resolveOverlay.classList.remove('opacity-100');
                resolveOverlay.classList.add('opacity-0');
                resolveBox.classList.remove('scale-100', 'opacity-100');
                resolveBox.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    resolveOverlay.classList.remove('flex');
                    resolveOverlay.classList.add('hidden');
                }, 200);
            }
        }

        // View Modal Logic (Spring Pop-up Effect)
        const viewOverlay = document.getElementById('viewTicketModalOverlay');
        const viewBox = document.getElementById('viewTicketModalBox');

        function openViewModal(ticket) {
            document.getElementById('view_sender_name').innerText = ticket.sender_name || 'Anonymous Citizen';
            document.getElementById('view_meta').innerText = 'Ticket #' + (ticket.ticket_id || ticket.id) + ' • ' + (ticket.category || 'General');
            document.getElementById('view_barangay').innerText = ticket.barangay ? 'Brgy. ' + ticket.barangay : 'Municipal General';
            
            const statusEl = document.getElementById('view_status');
            statusEl.innerText = ticket.status || 'Open';
            if (ticket.status === 'Resolved') {
                statusEl.className = 'font-black text-green-600 dark:text-green-400';
            } else if (ticket.status === 'In Progress') {
                statusEl.className = 'font-black text-amber-600 dark:text-amber-400';
            } else {
                statusEl.className = 'font-black text-rose-600 dark:text-rose-400';
            }

            document.getElementById('view_contact').innerText = ticket.contact_info || 'No contact provided';
            document.getElementById('view_message').innerText = ticket.message || '';

            const replyBox = document.getElementById('view_reply_box');
            if (ticket.admin_reply && ticket.admin_reply.trim() !== '') {
                document.getElementById('view_reply').innerText = ticket.admin_reply;
                replyBox.classList.remove('hidden');
            } else {
                replyBox.classList.add('hidden');
            }

            if (viewOverlay && viewBox) {
                viewOverlay.classList.remove('hidden');
                viewOverlay.classList.add('flex');
                requestAnimationFrame(() => {
                    viewOverlay.classList.remove('opacity-0');
                    viewOverlay.classList.add('opacity-100');
                    viewBox.classList.remove('scale-95', 'opacity-0');
                    viewBox.classList.add('scale-100', 'opacity-100');
                });
            }
        }

        function closeViewModal() {
            if (viewOverlay && viewBox) {
                viewOverlay.classList.remove('opacity-100');
                viewOverlay.classList.add('opacity-0');
                viewBox.classList.remove('scale-100', 'opacity-100');
                viewBox.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    viewOverlay.classList.remove('flex');
                    viewOverlay.classList.add('hidden');
                }, 200);
            }
        }

        // Close on ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (resolveOverlay && !resolveOverlay.classList.contains('hidden')) closeResolveModal();
                if (viewOverlay && !viewOverlay.classList.contains('hidden')) closeViewModal();
            }
        });
    </script>

    <!-- Genesis AI Assistant Widget -->
    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>