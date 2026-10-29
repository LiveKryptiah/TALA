<?php
// kanban.php - SK Project Management & Execution Board
// Standardized to TALA OS Design System (Reference: sk_voucher.php & sk_mom.php)
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Security Check
$allowed_roles = ['admin', 'sub-admin', 'sk_admin'];
if (!isset($_SESSION['admin_logged_in']) || !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'] ?? 'sub-admin';
$isAdmin = ($user_role === 'admin');
$user_barangay = $_SESSION['barangay_assigned'] ?? $_SESSION['admin_barangay'] ?? '';
if (empty($user_barangay) || $user_barangay === 'All') {
    $user_barangay = 'Balug';
}

$user_position = $_SESSION['sk_position'] ?? $_SESSION['position'] ?? 'Official';
$user_name = $_SESSION['admin_username'] ?? 'SK Official';

// Auto-upgrade budget table for Kanban
try {
    $cols = $pdo->query("SHOW COLUMNS FROM budget_monitoring")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('status', $cols)) {
        $pdo->exec("ALTER TABLE budget_monitoring ADD COLUMN status VARCHAR(50) DEFAULT 'Planning'");
    }
} catch (Exception $e) {}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_status'])) {
    $id = (int)($_POST['project_id'] ?? 0);
    $new_status = trim($_POST['new_status'] ?? '');
    $allowed_statuses = ['Planning', 'Bidding', 'Ongoing', 'Completed'];
    
    if ($id > 0 && in_array($new_status, $allowed_statuses)) {
        if ($isAdmin) {
            $stmt = $pdo->prepare("UPDATE budget_monitoring SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
        } else {
            $clean_brgy = str_ireplace('Barangay ', '', $user_barangay);
            $brgy_variants = array_values(array_unique([$user_barangay, $clean_brgy, 'Barangay ' . $clean_brgy]));
            $brgy_placeholders = implode(',', array_fill(0, count($brgy_variants), '?'));
            
            $stmt = $pdo->prepare("UPDATE budget_monitoring SET status = ? WHERE id = ? AND barangay IN ($brgy_placeholders)");
            $stmt->execute(array_merge([$new_status, $id], $brgy_variants));
        }
    }
    
    $redirect_params = [];
    if (!empty($_GET['brgy'])) $redirect_params['brgy'] = $_GET['brgy'];
    if (!empty($_GET['search'])) $redirect_params['search'] = $_GET['search'];
    $query_str = !empty($redirect_params) ? '?' . http_build_query($redirect_params) : '';
    header("Location: kanban.php" . $query_str);
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

// Filters & Query Execution
$filter = trim($_GET['brgy'] ?? 'All');
$search = trim($_GET['search'] ?? '');

$query = "SELECT * FROM budget_monitoring WHERE 1=1";
$params = [];

if (!$isAdmin) {
    $clean_brgy = str_ireplace('Barangay ', '', $user_barangay);
    $brgy_variants = array_values(array_unique([$user_barangay, $clean_brgy, 'Barangay ' . $clean_brgy]));
    $brgy_placeholders = implode(',', array_fill(0, count($brgy_variants), '?'));
    $query .= " AND barangay IN ($brgy_placeholders)";
    $params = array_merge($params, $brgy_variants);
} elseif ($filter !== 'All' && !empty($filter)) {
    $clean_filter = str_ireplace('Barangay ', '', $filter);
    $filter_variants = array_values(array_unique([$filter, $clean_filter, 'Barangay ' . $clean_filter]));
    $filter_placeholders = implode(',', array_fill(0, count($filter_variants), '?'));
    $query .= " AND barangay IN ($filter_placeholders)";
    $params = array_merge($params, $filter_variants);
}

if (!empty($search)) {
    $query .= " AND (program LIKE ? OR project_activity LIKE ? OR expected_output LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Calculate Telemetry Stats
$total_projects = count($projects);
$total_budget = 0;
foreach ($projects as $p) {
    $total_budget += (float)($p['amount'] ?? 0);
}

// Group Projects by Status
$kanban = ['Planning' => [], 'Bidding' => [], 'Ongoing' => [], 'Completed' => []];
foreach ($projects as $p) {
    $stat = $p['status'] ?? 'Planning';
    if (!isset($kanban[$stat])) $stat = 'Planning';
    $kanban[$stat][] = $p;
}
$active_pipeline_count = count($kanban['Planning']) + count($kanban['Bidding']) + count($kanban['Ongoing']);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Project Kanban Board | TALA OS</title>
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

        /* Fluid Horizontal Kanban Carousel for Touch Screens */
        .kanban-scroll-track {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
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

    <!-- SIDEBAR INCLUSION -->
    <?php include ($isAdmin ? 'sidebar_admin.php' : 'sidebar_sk.php'); ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA (Reference: sk_voucher.php) -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px and fits viewport smoothly -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-2.5 md:space-y-3">
            
            <!-- HEADER SECTION (Clean H1 title only, No corn logo, 3 KPI Badges - Matching sk_voucher.php) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <div>
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                            Project Kanban Board
                        </h1>
                    </div>
                </div>
                
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Total Projects -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-kanban text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Projects</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_projects) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Active Pipeline -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-hourglass-high text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Active Pipeline</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($active_pipeline_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Allocated Budget -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-coins text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Allocated Budget</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight">₱<?= number_format($total_budget, 2) ?></span>
                        </div>
                    </div>

                    <!-- Header Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[36px] h-[36px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
                        <i id="headerThemeIcon" class="ph-bold ph-moon text-base sm:text-lg transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- GLOBAL FILTERS & ACTION CONTROL BAR (Matching sk_voucher.php) -->
            <form method="GET" action="kanban.php" class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col md:flex-row items-center justify-between gap-2.5 flex-shrink-0">
                <div class="flex items-center gap-2 w-full md:flex-1">
                    <div class="relative flex-1">
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search project name, activity, or program..." class="w-full pl-8 pr-7 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 placeholder-slate-400 dark:placeholder-zinc-500 text-xs font-semibold focus:outline-none transition-colors border-0">
                        <?php if (!empty($search)): ?>
                            <a href="kanban.php<?= $filter !== 'All' ? '?brgy=' . urlencode($filter) : '' ?>" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300 transition-colors" title="Clear search">
                                <i class="ph-bold ph-x text-xs"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($isAdmin): ?>
                        <div class="w-48 sm:w-56 flex-shrink-0">
                            <select name="brgy" onchange="this.form.submit()" class="w-full px-3 py-1.5 bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 text-xs font-semibold rounded-xl focus:outline-none transition-colors border-0 cursor-pointer">
                                <option value="All">All 46 Barangays</option>
                                <?php foreach ($all_barangays as $b): ?>
                                    <option value="<?= htmlspecialchars($b) ?>" <?= $filter === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto justify-end">
                    <button type="submit" class="btn-yellow-glow-r px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group">
                        <i class="ph-bold ph-funnel text-xs relative z-10"></i>
                        <span class="relative z-10">Filter</span>
                    </button>

                    <?php if (!empty($search) || ($filter !== 'All' && !empty($filter))): ?>
                        <a href="kanban.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Filters">
                            <i class="ph-bold ph-x text-xs"></i>
                        </a>
                    <?php endif; ?>

                    <a href="<?= $isAdmin ? 'admin_budget.php' : 'sk2_budget.php' ?>" class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold text-xs tap-effect flex items-center gap-1.5 transition-all whitespace-nowrap">
                        <i class="ph-bold ph-coins text-xs"></i>
                        <span>Budget Ledger</span>
                    </a>
                </div>
            </form>

            <!-- MOBILE COLUMN SWITCHER TABS (lg:hidden) -->
            <div class="lg:hidden flex items-center gap-1.5 overflow-x-auto pb-1 custom-scrollbar flex-shrink-0">
                <?php 
                $mobile_tabs = [
                    'Planning'  => ['label' => 'Planning', 'color' => 'text-slate-700 dark:text-slate-300 bg-slate-200/70 dark:bg-zinc-800'],
                    'Bidding'   => ['label' => 'Bidding', 'color' => 'text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-500/10'],
                    'Ongoing'   => ['label' => 'Ongoing', 'color' => 'text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-500/10'],
                    'Completed' => ['label' => 'Completed', 'color' => 'text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-500/10']
                ];
                foreach ($mobile_tabs as $tabKey => $tab):
                ?>
                    <button type="button" onclick="scrollToColumn('col-<?= $tabKey ?>')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap tap-effect transition-all <?= $tab['color'] ?>">
                        <span><?= $tab['label'] ?></span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-white/70 dark:bg-black/30"><?= count($kanban[$tabKey]) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- KANBAN BOARD CONTAINER (Horizontal Swipe on Mobile / 4 Columns on lg Desktop) -->
            <div id="kanbanTrack" class="kanban-scroll-track flex lg:grid lg:grid-cols-4 gap-3 flex-1 min-h-0 overflow-x-auto lg:overflow-x-hidden snap-x snap-mandatory lg:snap-none pb-2 transition-colors">
                <?php 
                $columns = [
                    'Planning' => [
                        'name' => 'Planning',
                        'icon' => 'ph-lightbulb',
                        'badge' => 'bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-slate-300',
                        'accent' => 'bg-slate-400'
                    ],
                    'Bidding' => [
                        'name' => 'Bidding',
                        'icon' => 'ph-gavel',
                        'badge' => 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
                        'accent' => 'bg-blue-500'
                    ],
                    'Ongoing' => [
                        'name' => 'Ongoing',
                        'icon' => 'ph-hourglass-high',
                        'badge' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
                        'accent' => 'bg-amber-500'
                    ],
                    'Completed' => [
                        'name' => 'Completed',
                        'icon' => 'ph-check-circle',
                        'badge' => 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400',
                        'accent' => 'bg-[#15803d]'
                    ],
                ];

                foreach ($columns as $col => $style):
                    $cards = $kanban[$col] ?? [];
                ?>
                
                <!-- COLUMN WRAPPER -->
                <div id="col-<?= $col ?>" class="glass-card rounded-2xl flex flex-col min-h-[460px] lg:min-h-0 h-full overflow-hidden flex-shrink-0 w-[85vw] sm:w-[340px] lg:w-auto snap-center lg:snap-none transition-colors">
                    
                    <!-- Column Header -->
                    <div class="p-3 md:p-3.5 bg-white/50 dark:bg-zinc-900/50 flex justify-between items-center border-b border-slate-100 dark:border-zinc-800/80 transition-colors flex-shrink-0">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full <?= $style['accent'] ?>"></span>
                            <h3 class="font-extrabold text-xs md:text-sm text-slate-900 dark:text-white flex items-center gap-1.5 uppercase tracking-wider">
                                <i class="ph-bold <?= $style['icon'] ?>"></i> <?= $col ?>
                            </h3>
                        </div>
                        <span class="text-xs font-black px-2 py-0.5 rounded-xl <?= $style['badge'] ?>">
                            <?= count($cards) ?>
                        </span>
                    </div>

                    <!-- Cards Scrollable Area -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-2.5 md:p-3 space-y-2.5 min-h-0">
                        <?php if (empty($cards)): ?>
                            <div class="h-full min-h-[160px] flex flex-col items-center justify-center text-center p-4 select-none">
                                <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-zinc-800 flex items-center justify-center mx-auto mb-2 text-slate-400 dark:text-zinc-500 text-lg">
                                    <i class="ph-bold ph-tray"></i>
                                </div>
                                <span class="text-xs font-bold text-slate-500 dark:text-zinc-400">No Projects</span>
                                <span class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">Move items here or update from budget</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($cards as $card): 
                                $card_program = htmlspecialchars($card['program'] ?? 'Untitled Program');
                                $card_activity = htmlspecialchars($card['project_activity'] ?? '');
                                $card_amount = (float)($card['amount'] ?? 0);
                                $card_barangay = htmlspecialchars($card['barangay'] ?? '');
                                $card_duration = htmlspecialchars($card['duration'] ?? '');
                            ?>
                            <div class="bg-slate-50/90 dark:bg-zinc-900/60 hover:bg-slate-100/90 dark:hover:bg-zinc-800/60 rounded-xl p-3 md:p-3.5 transition-all group tap-effect">
                                
                                <div class="flex items-start justify-between gap-2 mb-1.5">
                                    <?php if ($isAdmin): ?>
                                        <span class="text-[9px] md:text-[10px] font-bold uppercase tracking-wider text-slate-600 dark:text-zinc-400 flex items-center gap-1 bg-white dark:bg-zinc-800 px-2 py-0.5 rounded-md">
                                            <i class="ph-fill ph-map-pin text-green-600 dark:text-green-500"></i> <?= $card_barangay ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($card_duration)): ?>
                                        <span class="text-[9px] md:text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider ml-auto bg-slate-100 dark:bg-zinc-800/80 px-2 py-0.5 rounded-md">
                                            <i class="ph-bold ph-calendar text-slate-400"></i> <?= $card_duration ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <h4 onclick="openProjectModal(<?= htmlspecialchars(json_encode($card), ENT_QUOTES, 'UTF-8') ?>)" class="font-extrabold text-xs md:text-sm text-slate-900 dark:text-white leading-snug mb-1 transition-colors hover:text-green-600 dark:hover:text-green-400 cursor-pointer">
                                    <?= $card_program ?>
                                </h4>

                                <?php if (!empty($card_activity)): ?>
                                    <p class="text-[11px] md:text-xs text-slate-500 dark:text-zinc-400 line-clamp-2 leading-relaxed mb-2.5 transition-colors">
                                        <?= $card_activity ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="flex justify-between items-center border-t border-slate-200/60 dark:border-zinc-800/80 pt-2 transition-colors">
                                    <div class="min-w-0 pr-1 flex items-center gap-1.5">
                                        <span class="text-xs md:text-sm font-black text-slate-900 dark:text-white tracking-tight">
                                            ₱<?= number_format($card_amount, 2) ?>
                                        </span>
                                        <button type="button" onclick="openProjectModal(<?= htmlspecialchars(json_encode($card), ENT_QUOTES, 'UTF-8') ?>)" class="text-slate-400 hover:text-green-600 dark:hover:text-green-400 transition-colors" title="Inspect details">
                                            <i class="ph-bold ph-info text-xs"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Move Status Dropdown -->
                                    <form method="POST" class="m-0 flex items-center flex-shrink-0">
                                        <input type="hidden" name="update_status" value="1">
                                        <input type="hidden" name="project_id" value="<?= $card['id'] ?>">
                                        <select name="new_status" onchange="this.form.submit()" class="text-[10px] md:text-xs font-bold bg-white dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 rounded-lg px-2 py-1 cursor-pointer outline-none transition-colors border-0 shadow-none tap-effect">
                                            <?php foreach (array_keys($columns) as $c): ?>
                                                <option value="<?= $c ?>" <?= $col === $c ? 'selected' : '' ?> class="bg-white dark:bg-zinc-900 text-slate-800 dark:text-white">
                                                    Move: <?= $c ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                </div>
                                
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
            
        </div>
    </main>

    <!-- PROJECT DETAIL INSPECTION MODAL (Reference: sk_voucher.php) -->
    <div id="projectModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeProjectModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-lg w-full p-5 md:p-7 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800" id="projectModalBox">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 pb-3 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm">
                        <i class="ph-fill ph-kanban"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white leading-tight" id="modal_proj_program">Project Overview</h3>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono" id="modal_proj_barangay">SK Project Dossier</p>
                    </div>
                </div>
                <button type="button" onclick="closeProjectModal()" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 flex items-center justify-center tap-effect transition-all" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Content -->
            <div class="space-y-3.5 text-xs max-h-[65vh] overflow-y-auto custom-scrollbar pr-1">
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-100 dark:border-zinc-800">
                        <span class="block text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 mb-0.5">Allocated Budget</span>
                        <span class="text-lg font-black text-green-600 dark:text-green-500 tracking-tight" id="modal_proj_amount">₱0.00</span>
                    </div>
                    <div class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-100 dark:border-zinc-800">
                        <span class="block text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 mb-0.5">Pipeline Status</span>
                        <span class="text-base font-black text-slate-900 dark:text-white" id="modal_proj_status">Planning</span>
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-800/40">
                    <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Project Activity</span>
                    <p class="font-semibold text-slate-800 dark:text-zinc-200 mt-0.5 leading-relaxed" id="modal_proj_activity">-</p>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-800/40" id="modal_expected_output_box">
                    <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Expected Output</span>
                    <p class="font-semibold text-slate-800 dark:text-zinc-200 mt-0.5 leading-relaxed" id="modal_proj_output">-</p>
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-800/40">
                        <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Duration</span>
                        <span class="font-bold text-slate-800 dark:text-zinc-200" id="modal_proj_duration">-</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-800/40">
                        <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Category</span>
                        <span class="font-bold text-slate-800 dark:text-zinc-200" id="modal_proj_category">-</span>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-zinc-800 mt-4 no-print">
                <form method="POST" id="modalMoveForm" class="flex items-center gap-2">
                    <input type="hidden" name="update_status" value="1">
                    <input type="hidden" name="project_id" id="modal_hidden_proj_id" value="">
                    <span class="text-xs font-bold text-slate-500 dark:text-zinc-400">Status:</span>
                    <select name="new_status" id="modal_select_status" onchange="this.form.submit()" class="text-xs font-bold bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white rounded-xl px-2.5 py-1.5 outline-none cursor-pointer border-0">
                        <option value="Planning">Planning</option>
                        <option value="Bidding">Bidding</option>
                        <option value="Ongoing">Ongoing</option>
                        <option value="Completed">Completed</option>
                    </select>
                </form>

                <button type="button" onclick="closeProjectModal()" class="px-4 py-1.5 rounded-xl bg-slate-900 hover:bg-black dark:bg-white dark:hover:bg-zinc-200 text-white dark:text-zinc-900 font-bold text-xs tap-effect transition-all">
                    Done
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
            <a href="manage_announcements.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
                <div class="px-4 py-1 mb-1">
                    <i class="ph-bold ph-megaphone text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Notices</span>
            </a>
            <a href="kanban.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
                <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                    <i class="ph-fill ph-kanban text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Kanban</span>
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
            <a href="kanban.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
                <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                    <i class="ph-fill ph-kanban text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Kanban</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        // Drawer toggle
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
            localStorage.theme = isDark ? 'dark' : 'light';
            syncHeaderThemeIcon(isDark);
        }
        syncHeaderThemeIcon(document.documentElement.classList.contains('dark'));

        // Smooth Mobile Column Switcher
        function scrollToColumn(colId) {
            const target = document.getElementById(colId);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', inline: 'start', block: 'nearest' });
            }
        }

        // Project Inspection Modal Logic (Reference: sk_voucher.php)
        function openProjectModal(card) {
            const modal = document.getElementById('projectModalOverlay');
            const box = document.getElementById('projectModalBox');
            if (!modal || !box || !card) return;

            document.getElementById('modal_proj_program').innerText = card.program || 'Untitled Program';
            document.getElementById('modal_proj_barangay').innerText = 'Brgy. ' + (card.barangay || 'Municipal');
            document.getElementById('modal_proj_amount').innerText = '₱' + Number(card.amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modal_proj_status').innerText = card.status || 'Planning';
            document.getElementById('modal_proj_activity').innerText = card.project_activity || 'No detailed activity specified.';
            document.getElementById('modal_proj_output').innerText = card.expected_output || 'No expected output stated.';
            document.getElementById('modal_proj_duration').innerText = card.duration || 'N/A';
            document.getElementById('modal_proj_category').innerText = card.category || 'General Fund';
            document.getElementById('modal_hidden_proj_id').value = card.id;
            document.getElementById('modal_select_status').value = card.status || 'Planning';

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            requestAnimationFrame(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                box.classList.remove('scale-95', 'opacity-0');
                box.classList.add('scale-100', 'opacity-100');
            });
        }

        function closeProjectModal() {
            const modal = document.getElementById('projectModalOverlay');
            const box = document.getElementById('projectModalBox');
            if (!modal || !box) return;

            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            box.classList.remove('scale-100', 'opacity-100');
            box.classList.add('scale-95', 'opacity-0');

            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeProjectModal();
            }
        });
    </script>

    <?php include_once 'genesis_widget.php'; ?>
</body>
</html>