<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'db_connect.php'; 

// Security Check
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'sub-admin')) {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['admin_username'] ?? 'Administrator';

// ==========================================
// DATE FILTER LOGIC
// ==========================================
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$base_query = "FROM youth_registry WHERE 1=1";
$admin_query = "FROM admins WHERE 1=1";
$params = [];

if (!empty($start_date) && !empty($end_date)) {
    $base_query .= " AND DATE(created_at) BETWEEN :start AND :end";
    $admin_query .= " AND DATE(created_at) BETWEEN :start AND :end";
    $params[':start'] = $start_date;
    $params[':end'] = $end_date;
    $date_label = date('M d, Y', strtotime($start_date)) . " - " . date('M d, Y', strtotime($end_date));
} else {
    $date_label = "All Time Record";
}

// Helper function to fetch counts cleanly
function getCount($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

try {
    // 1. Core MYDO Metrics (Filtered)
    $total_registered = getCount($pdo, "SELECT COUNT(*) $base_query", $params);
    
    // Coverage Percentage
    $target_population = 24000;
    $coverage_percentage = $total_registered > 0 ? round(($total_registered / $target_population) * 100) : 0;

    // Approvals & Validations (Filtered)
    $pending_approvals = getCount($pdo, "SELECT COUNT(*) $admin_query AND status = 'pending'", $params);
    $records_due_validation = getCount($pdo, "SELECT COUNT(*) $base_query AND (document_status LIKE '%Incomplete%' OR document_status LIKE '%Pending%' OR document_status = '' OR document_status IS NULL)", $params);
    
    // New Registrations
    if (!empty($start_date)) {
        $new_registrations = $total_registered;
        $new_reg_label = "In Selected Period";
    } else {
        $new_registrations = getCount($pdo, "SELECT COUNT(*) FROM youth_registry WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        $new_reg_label = "Registered this month";
    }

    // 2. Inter-Agency Sync Digits (Filtered)
    $deped_count = getCount($pdo, "SELECT COUNT(*) $base_query AND deped_enrollment_status NOT IN ('Pending Verification', '', 'No Record') AND deped_enrollment_status IS NOT NULL", $params);
    $peso_count = getCount($pdo, "SELECT COUNT(*) $base_query AND peso_vital_status NOT IN ('Alive / Active', '', 'No Record') AND peso_vital_status IS NOT NULL", $params);
    $mswdo_count = getCount($pdo, "SELECT COUNT(*) $base_query AND mswdo_case_status NOT IN ('No Record / Cleared', '', 'No Record') AND mswdo_case_status IS NOT NULL", $params);
    $mho_count = getCount($pdo, "SELECT COUNT(*) $base_query AND mho_pregnancy_status NOT IN ('No Record', '') AND mho_pregnancy_status IS NOT NULL", $params);

    // 3. Barangay Top 10 & Bottom 10 (Filtered)
    $stmt = $pdo->prepare("SELECT barangay, COUNT(*) as count $base_query AND barangay != '' GROUP BY barangay ORDER BY count DESC");
    $stmt->execute($params);
    $all_brgys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $top_10 = array_slice($all_brgys, 0, 10);
    $bottom_10 = array_reverse(array_slice($all_brgys, -10, 10));
    $highest_count = $top_10[0]['count'] ?? 1;

    // 4. Chart Data Generation (Registrations over time - Recent 30 active days up to today)
    if (!empty($start_date) && !empty($end_date)) {
        $chart_stmt = $pdo->prepare("SELECT DATE(created_at) as reg_date, COUNT(*) as count $base_query GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC");
        $chart_stmt->execute($params);
        $chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $chart_stmt = $pdo->prepare("SELECT DATE(created_at) as reg_date, COUNT(*) as count $base_query GROUP BY DATE(created_at) ORDER BY DATE(created_at) DESC LIMIT 30");
        $chart_stmt->execute($params);
        $chart_data = array_reverse($chart_stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    $chart_labels = []; $chart_values = [];
    foreach($chart_data as $row) {
        $chart_labels[] = date('M d', strtotime($row['reg_date']));
        $chart_values[] = (int)$row['count'];
    }

} catch (PDOException $e) {
    die("Dashboard Engine Error: " . $e->getMessage());
}

$mascot_file = 'mascot.png';
foreach (['mascot.png', 'mascot.webp', 'mascot.jpg', 'mascot.svg'] as $m) {
    if (file_exists(__DIR__ . '/' . $m)) {
        $mascot_file = $m;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TALA OS | Executive Dashboard</title>
    
    <!-- ANTI-FLASH SCRIPT -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = { 
            darkMode: 'class', 
            theme: { 
                extend: { 
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }
                } 
            } 
        }
    </script>
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease; 
            -webkit-tap-highlight-color: transparent; 
        }

        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; } 
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; } 
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        
        /* Modern Glass Card - Zero Outside Strokes, Zero Shadows */
        .glass-card { 
            background: #ffffff !important; 
            border: none !important; 
            box-shadow: none !important;
            transition: all 0.3s ease;
        }
        .dark .glass-card { 
            background: #18181b !important; 
            border: none !important; 
            box-shadow: none !important;
        }

        /* Subtle Top-Right Green Ambient Gradient for Section Cards (from sk_dashboard) */
        .card-green-glow-tr {
            position: relative;
            overflow: hidden;
        }
        .card-green-glow-tr::before {
            content: '';
            position: absolute;
            top: -10px;
            right: -10px;
            width: 72px;
            height: 72px;
            background: radial-gradient(circle at 75% 25%, rgba(34, 197, 94, 0.22) 0%, rgba(34, 197, 94, 0.08) 45%, transparent 75%);
            pointer-events: none;
            border-radius: 9999px;
            filter: blur(6px);
            z-index: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        .dark .card-green-glow-tr::before {
            background: radial-gradient(circle at 75% 25%, rgba(34, 197, 94, 0.28) 0%, rgba(34, 197, 94, 0.1) 45%, transparent 75%);
        }
        .card-green-glow-tr:hover::before {
            transform: scale(1.15);
            opacity: 1;
        }

        /* Subtle Top-Right Orange Ambient Gradient for Section Cards (from sk_dashboard) */
        .card-orange-glow-tr {
            position: relative;
            overflow: hidden;
        }
        .card-orange-glow-tr::before {
            content: '';
            position: absolute;
            top: -10px;
            right: -10px;
            width: 72px;
            height: 72px;
            background: radial-gradient(circle at 75% 25%, rgba(245, 158, 11, 0.28) 0%, rgba(217, 119, 6, 0.09) 45%, transparent 75%);
            pointer-events: none;
            border-radius: 9999px;
            filter: blur(6px);
            z-index: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        .dark .card-orange-glow-tr::before {
            background: radial-gradient(circle at 75% 25%, rgba(245, 158, 11, 0.34) 0%, rgba(217, 119, 6, 0.12) 45%, transparent 75%);
        }
        .card-orange-glow-tr:hover::before {
            transform: scale(1.15);
            opacity: 1;
        }

        /* Barangay Ranking Card: Softened gradient with rich green header and gentle emerald-tinted body (lessened white) */
        .card-ranking-gradient {
            background: linear-gradient(180deg, #15803d 0%, #16a34a 28%, #d1fae5 72%, #dcfce7 100%) !important;
            border: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
            transition: all 0.2s ease;
        }
        .dark .card-ranking-gradient {
            background: linear-gradient(180deg, #15803d 0%, #16a34a 24%, #142e1d 65%, #18181b 100%) !important;
            border: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }

        /* Mobile Overlay */
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

        /* Remove outlines on focus */
        input:focus,
        input:focus-visible,
        input:active,
        button:focus,
        button:focus-visible,
        a:focus {
            outline: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }

        /* Enforce zero drop shadows globally */
        .shadow-none-all,
        .shadow-sm,
        .shadow-md,
        .shadow-lg,
        .shadow-xl,
        .shadow-2xl {
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }

        /* Image filter reset */
        img {
            filter: none !important;
            -webkit-filter: none !important;
        }

        /* Native app tap effect */
        .tap-effect:active { transform: scale(0.97); opacity: 0.85; transition: transform 0.1s; }

        /* Login Green Button Style */
        .btn-green {
            background-color: #15803d;
            color: #ffffff;
            transition: all 0.2s ease;
        }
        .btn-green:hover {
            background-color: #166534;
        }
        .dark .btn-green {
            background-color: #16a34a;
        }
        .dark .btn-green:hover {
            background-color: #15803d;
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen overflow-x-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- STANDARD TALA OS ADMIN SIDEBAR -->
    <?php include 'sidebar_admin.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Responsive and smoothly centered -->
        <div class="max-w-[1500px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-4 md:space-y-5">
            
            <!-- MINIMAL HEADER SECTION (with xl:pr-14 theme toggle clearance) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-3 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200/60 dark:border-zinc-800 flex items-center justify-center flex-shrink-0">
                        <img src="<?= htmlspecialchars($mascot_file) ?>" alt="Mascot" class="w-7 h-7 object-contain">
                    </div>
                    <div>
                        <h1 class="text-xl md:text-2xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                            Dashboard
                        </h1>
                        <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium">
                            Welcome, <?= htmlspecialchars($admin_name) ?> &bull; <?= date('l, F j, Y') ?>
                        </p>
                    </div>
                </div>
                
                <!-- Minimal Date Filter Bar -->
                <form method="GET" action="tala_dashboard.php" class="glass-card p-1.5 rounded-xl flex items-center gap-1.5 transition-colors w-full sm:w-auto">
                    <div class="flex items-center bg-slate-50 dark:bg-zinc-900/60 px-2.5 py-1 rounded-lg">
                        <span class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mr-1.5">From</span>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="text-xs font-semibold text-slate-700 dark:text-white bg-transparent outline-none cursor-pointer" style="color-scheme: dark light;">
                    </div>
                    <div class="flex items-center bg-slate-50 dark:bg-zinc-900/60 px-2.5 py-1 rounded-lg">
                        <span class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mr-1.5">To</span>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="text-xs font-semibold text-slate-700 dark:text-white bg-transparent outline-none cursor-pointer" style="color-scheme: dark light;">
                    </div>
                    <button type="submit" class="bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-1 px-3.5 rounded-lg text-xs transition-all tap-effect shadow-none">
                        Filter
                    </button>
                    <?php if(!empty($start_date)): ?>
                        <a href="tala_dashboard.php" class="p-1 rounded-lg text-slate-400 hover:text-green-600 dark:hover:text-green-400 transition-colors" title="Clear filter">
                            <i class="ph-bold ph-x text-xs"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- CORE KPI METRICS: 4 Clean Minimal Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
                
                <!-- 1. Registered Youth -->
                <div class="glass-card card-green-glow-tr p-4 sm:p-5 rounded-2xl flex flex-col justify-between transition-colors">
                    <div class="flex items-center justify-between relative z-10">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Registered Youth</span>
                        <span class="w-8 h-8 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-base">
                            <i class="ph-bold ph-users-three"></i>
                        </span>
                    </div>
                    <div class="mt-3 relative z-10">
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                            <?= number_format($total_registered); ?>
                        </h2>
                        <p class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium mt-0.5">
                            Verified youth profiles
                        </p>
                    </div>
                </div>

                <!-- 2. Target Coverage -->
                <div class="glass-card card-green-glow-tr p-4 sm:p-5 rounded-2xl flex flex-col justify-between transition-colors">
                    <div class="flex items-center justify-between relative z-10">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Target Coverage</span>
                        <span class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 flex items-center justify-center text-base">
                            <i class="ph-bold ph-target"></i>
                        </span>
                    </div>
                    <div class="mt-3 relative z-10">
                        <div class="flex items-baseline gap-1.5">
                            <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                                <?= $coverage_percentage; ?>%
                            </h2>
                            <span class="text-xs text-slate-400 dark:text-zinc-500 font-medium">of 24,000 target</span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-zinc-800 rounded-full h-1 mt-2 overflow-hidden">
                            <div class="bg-[#15803d] dark:bg-[#16a34a] h-full rounded-full transition-all duration-500" style="width: <?= min(100, $coverage_percentage); ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- 3. New Registrations -->
                <div class="glass-card card-green-glow-tr p-4 sm:p-5 rounded-2xl flex flex-col justify-between transition-colors">
                    <div class="flex items-center justify-between relative z-10">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">New Profiles</span>
                        <span class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 flex items-center justify-center text-base">
                            <i class="ph-bold ph-trend-up"></i>
                        </span>
                    </div>
                    <div class="mt-3 relative z-10">
                        <h2 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                            <?= number_format($new_registrations); ?>
                        </h2>
                        <p class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium mt-0.5 truncate">
                            <?= htmlspecialchars($new_reg_label) ?>
                        </p>
                    </div>
                </div>

                <!-- 4. Records Due Validation -->
                <div class="glass-card card-orange-glow-tr p-4 sm:p-5 rounded-2xl flex flex-col justify-between transition-colors">
                    <div class="flex items-center justify-between relative z-10">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Needs Validation</span>
                        <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-base">
                            <i class="ph-bold ph-file-magnifying-glass"></i>
                        </span>
                    </div>
                    <div class="mt-3 relative z-10">
                        <h2 class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400 tracking-tight">
                            <?= number_format($records_due_validation); ?>
                        </h2>
                        <p class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium mt-0.5">
                            Documents awaiting review
                        </p>
                    </div>
                </div>

            </div>

            <!-- MAIN ANALYTICS SECTION: 2 COLUMNS (8 cols / 4 cols) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 md:gap-5">
                
                <!-- LEFT COLUMN: ACTIVITY CHART & TELEMETRY (8 cols) -->
                <div class="lg:col-span-8 space-y-4 md:space-y-5">
                    
                    <!-- Registration Activity Timeline Area Chart -->
                    <div class="glass-card card-green-glow-tr p-4 sm:p-6 rounded-2xl flex flex-col transition-colors">
                        <div class="flex items-center justify-between mb-4 flex-shrink-0">
                            <div>
                                <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                                    <i class="ph-bold ph-chart-line-up text-green-600 dark:text-green-500"></i> Registration Activity
                                </h3>
                                <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium">Historical profile volume over timeline</p>
                            </div>
                            <span class="text-[11px] font-bold text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-950/40 border border-green-200/50 dark:border-green-800/30 px-2.5 py-1 rounded-lg">
                                <?= !empty($start_date) ? 'Filtered Range' : 'Recent 30 Entries' ?>
                            </span>
                        </div>
                        <div class="relative w-full h-[240px] sm:h-[270px]">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>

                    <!-- Inter-Agency Live Sync Bar -->
                    <div class="glass-card p-4 sm:p-5 rounded-2xl transition-colors">
                        <div class="flex items-center justify-between mb-3.5">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <i class="ph-bold ph-arrows-clockwise text-green-600 dark:text-green-500"></i> Inter-Agency Telemetry
                            </h3>
                            <a href="department_monitor.php" class="text-[11px] font-bold text-green-600 dark:text-green-500 hover:text-green-700 dark:hover:text-green-400 hover:underline flex items-center gap-1 transition-colors">
                                Monitor Console <i class="ph-bold ph-arrow-right"></i>
                            </a>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                            <!-- DepEd -->
                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-900/60 flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">DepEd</p>
                                    <p class="text-base font-black text-slate-900 dark:text-white"><?= number_format($deped_count) ?></p>
                                </div>
                                <span class="w-2 h-2 rounded-full <?= $deped_count > 0 ? 'bg-green-500' : 'bg-slate-300 dark:bg-zinc-600' ?>"></span>
                            </div>
                            <!-- PESO -->
                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-900/60 flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">PESO</p>
                                    <p class="text-base font-black text-slate-900 dark:text-white"><?= number_format($peso_count) ?></p>
                                </div>
                                <span class="w-2 h-2 rounded-full <?= $peso_count > 0 ? 'bg-green-500' : 'bg-slate-300 dark:bg-zinc-600' ?>"></span>
                            </div>
                            <!-- MSWDO -->
                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-900/60 flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">MSWDO</p>
                                    <p class="text-base font-black text-slate-900 dark:text-white"><?= number_format($mswdo_count) ?></p>
                                </div>
                                <span class="w-2 h-2 rounded-full <?= $mswdo_count > 0 ? 'bg-green-500' : 'bg-slate-300 dark:bg-zinc-600' ?>"></span>
                            </div>
                            <!-- MHO -->
                            <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-900/60 flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">MHO</p>
                                    <p class="text-base font-black text-slate-900 dark:text-white"><?= number_format($mho_count) ?></p>
                                </div>
                                <span class="w-2 h-2 rounded-full <?= $mho_count > 0 ? 'bg-green-500' : 'bg-slate-300 dark:bg-zinc-600' ?>"></span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN: BARANGAY DEMOGRAPHICS & QUICK ACCESS (4 cols) -->
                <div class="lg:col-span-4 space-y-4 md:space-y-5">
                    
                    <!-- Barangay Ranking -->
                    <div class="card-ranking-gradient rounded-2xl p-4 sm:p-5 flex flex-col transition-colors">
                        <div class="flex items-center justify-between mb-3 pb-2 border-0">
                            <div>
                                <h3 class="text-xs sm:text-sm font-bold text-white flex items-center gap-1.5">
                                    <i class="ph-bold ph-chart-bar text-yellow-300"></i> Barangay Ranking
                                </h3>
                                <p class="text-[11px] text-green-100 font-medium">Registry performance</p>
                            </div>
                            <div class="flex items-center bg-black/20 dark:bg-black/35 backdrop-blur-sm p-0.5 rounded-lg text-xs font-bold border border-white/20">
                                <button type="button" onclick="switchBrgyTab('top')" id="tabBtnTop" class="px-2.5 py-0.5 rounded-md bg-white text-green-800 text-[11px] font-bold transition-all shadow-none">Top 10</button>
                                <button type="button" onclick="switchBrgyTab('bottom')" id="tabBtnBottom" class="px-2.5 py-0.5 rounded-md text-green-100 hover:text-white text-[11px] font-bold transition-all">Bottom 10</button>
                            </div>
                        </div>
                        
                        <!-- Top 10 Panel -->
                        <div id="brgyTopPanel" class="space-y-2.5 max-h-[290px] overflow-y-auto custom-scrollbar pr-1">
                            <?php 
                            $rank = 1;
                            if (empty($top_10)) echo "<p class='text-xs text-slate-400 dark:text-zinc-500 italic py-4 text-center'>No records found in this range.</p>";
                            foreach ($top_10 as $b): 
                                $pct = ($b['count'] / $highest_count) * 100;
                            ?>
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="font-bold text-slate-700 dark:text-zinc-300 truncate">
                                        <span class="text-slate-400 dark:text-zinc-500 font-semibold mr-1"><?= $rank++ ?>.</span>
                                        <?= htmlspecialchars($b['barangay']) ?>
                                    </span>
                                    <span class="font-bold text-slate-900 dark:text-white"><?= number_format($b['count']) ?></span>
                                </div>
                                <div class="w-full bg-green-950/10 dark:bg-zinc-800/80 rounded-full h-1">
                                    <div class="bg-[#15803d] dark:bg-[#16a34a] h-1 rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Bottom 10 Panel -->
                        <div id="brgyBottomPanel" class="space-y-2.5 max-h-[290px] overflow-y-auto custom-scrollbar pr-1 hidden">
                            <?php 
                            $total_brgys = count($all_brgys);
                            $rank = $total_brgys > 0 ? $total_brgys : 46; 
                            if (empty($bottom_10)) echo "<p class='text-xs text-slate-400 dark:text-zinc-500 italic py-4 text-center'>No records found.</p>";
                            foreach ($bottom_10 as $b): 
                                $pct = ($b['count'] / $highest_count) * 100;
                            ?>
                            <div>
                                <div class="flex justify-between text-xs mb-1">
                                    <span class="font-bold text-slate-700 dark:text-zinc-300 truncate">
                                        <span class="text-slate-400 dark:text-zinc-500 font-semibold mr-1"><?= $rank-- ?>.</span>
                                        <?= htmlspecialchars($b['barangay']) ?>
                                    </span>
                                    <span class="font-bold text-slate-500 dark:text-zinc-400"><?= number_format($b['count']) ?></span>
                                </div>
                                <div class="w-full bg-green-950/10 dark:bg-zinc-800/80 rounded-full h-1">
                                    <div class="bg-slate-400 dark:bg-zinc-600 h-1 rounded-full transition-all" style="width: <?= $pct ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Quick Navigation Minimal Hub -->
                    <div class="glass-card rounded-2xl p-4 sm:p-5 transition-colors">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white mb-2.5">
                            Quick Access
                        </h3>
                        <div class="grid grid-cols-2 gap-2">
                            <a href="tala_directory.php" class="p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 dark:bg-zinc-900/60 dark:hover:bg-zinc-900 hover:text-green-700 dark:hover:text-green-400 text-xs font-bold text-slate-700 dark:text-zinc-300 flex items-center gap-2 transition-colors tap-effect group">
                                <i class="ph-bold ph-address-book text-slate-400 dark:text-zinc-500 group-hover:text-green-600 dark:group-hover:text-green-500 transition-colors"></i> Directory
                            </a>
                            <a href="department_monitor.php" class="p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 dark:bg-zinc-900/60 dark:hover:bg-zinc-900 hover:text-green-700 dark:hover:text-green-400 text-xs font-bold text-slate-700 dark:text-zinc-300 flex items-center gap-2 transition-colors tap-effect group">
                                <i class="ph-bold ph-eye text-slate-400 dark:text-zinc-500 group-hover:text-green-600 dark:group-hover:text-green-500 transition-colors"></i> Monitor
                            </a>
                            <a href="admin_calendar.php" class="p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 dark:bg-zinc-900/60 dark:hover:bg-zinc-900 hover:text-green-700 dark:hover:text-green-400 text-xs font-bold text-slate-700 dark:text-zinc-300 flex items-center gap-2 transition-colors tap-effect group">
                                <i class="ph-bold ph-calendar-blank text-slate-400 dark:text-zinc-500 group-hover:text-green-600 dark:group-hover:text-green-500 transition-colors"></i> Calendar
                            </a>
                            <a href="import_youth.php" class="p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 dark:bg-zinc-900/60 dark:hover:bg-zinc-900 hover:text-green-700 dark:hover:text-green-400 text-xs font-bold text-slate-700 dark:text-zinc-300 flex items-center gap-2 transition-colors tap-effect group">
                                <i class="ph-bold ph-cloud-arrow-up text-slate-400 dark:text-zinc-500 group-hover:text-green-600 dark:group-hover:text-green-500 transition-colors"></i> Bulk Import
                            </a>
                        </div>
                    </div>

                </div>

            </div>
            
        </div>
    </main>

    <!-- Script to toggle mobile sidebar -->
    <script>
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

        function switchBrgyTab(tab) {
            const topPanel = document.getElementById('brgyTopPanel');
            const bottomPanel = document.getElementById('brgyBottomPanel');
            const btnTop = document.getElementById('tabBtnTop');
            const btnBottom = document.getElementById('tabBtnBottom');
            if (tab === 'top') {
                topPanel.classList.remove('hidden');
                bottomPanel.classList.add('hidden');
                btnTop.className = 'px-2.5 py-0.5 rounded-md bg-white text-green-800 text-[11px] font-bold transition-all shadow-none';
                btnBottom.className = 'px-2.5 py-0.5 rounded-md text-green-100 hover:text-white text-[11px] font-bold transition-all';
            } else {
                topPanel.classList.add('hidden');
                bottomPanel.classList.remove('hidden');
                btnBottom.className = 'px-2.5 py-0.5 rounded-md bg-white text-green-800 text-[11px] font-bold transition-all shadow-none';
                btnTop.className = 'px-2.5 py-0.5 rounded-md text-green-100 hover:text-white text-[11px] font-bold transition-all';
            }
        }
    </script>

    <!-- Minimal Chart.js Area Timeline Chart -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const canvas = document.getElementById('activityChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            
            const chartLabels = <?= json_encode($chart_labels) ?>;
            const chartValues = <?= json_encode($chart_values) ?>;
            const isDark = document.documentElement.classList.contains('dark') || localStorage.theme === 'dark';

            // Subtle area gradient
            const gradient = ctx.createLinearGradient(0, 0, 0, 240);
            if (isDark) {
                gradient.addColorStop(0, 'rgba(22, 163, 74, 0.22)');
                gradient.addColorStop(1, 'rgba(22, 163, 74, 0.0)');
            } else {
                gradient.addColorStop(0, 'rgba(21, 128, 61, 0.18)');
                gradient.addColorStop(1, 'rgba(21, 128, 61, 0.0)');
            }

            const activityChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartLabels.length > 0 ? chartLabels : ['No records'],
                    datasets: [{
                        label: 'Registrations',
                        data: chartValues.length > 0 ? chartValues : [0],
                        borderColor: isDark ? '#16a34a' : '#15803d',
                        borderWidth: 2,
                        pointRadius: chartValues.length > 15 ? 0 : 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: isDark ? '#16a34a' : '#15803d',
                        fill: true,
                        backgroundColor: gradient,
                        tension: 0.35
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#27272a' : '#0f172a',
                            titleFont: { family: "'Plus Jakarta Sans'", size: 12, weight: 'bold' },
                            bodyFont: { family: "'Plus Jakarta Sans'", size: 11 },
                            padding: 10,
                            cornerRadius: 8,
                            displayColors: false
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: isDark ? '#71717a' : '#94a3b8',
                                font: { family: "'Plus Jakarta Sans'", size: 10 },
                                maxTicksLimit: 8
                            }
                        },
                        y: {
                            border: { display: false },
                            grid: {
                                color: isDark ? 'rgba(39, 39, 42, 0.6)' : 'rgba(241, 245, 249, 0.9)'
                            },
                            ticks: {
                                color: isDark ? '#71717a' : '#94a3b8',
                                font: { family: "'Plus Jakarta Sans'", size: 10 },
                                precision: 0
                            }
                        }
                    }
                }
            });
        });
    </script>
    
    <!-- Genesis AI Assistant Widget -->
    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>