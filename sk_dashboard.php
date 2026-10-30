<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
require_once 'db_connect.php'; 

// Role Authorization Check
$allowed_roles = ['sk_admin', 'sub-admin', 'admin'];
if (!isset($_SESSION['admin_logged_in']) || !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['admin_username'] ?? 'SK Official';
$admin_barangay = $_SESSION['barangay_assigned'] ?? $_SESSION['admin_barangay'] ?? 'Barangay Balug';

// Date Filter Parameters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$base_where = "barangay = :brgy";
$params = [':brgy' => $admin_barangay];

if (!empty($start_date) && !empty($end_date)) {
    $base_where .= " AND DATE(created_at) BETWEEN :start AND :end";
    $params[':start'] = $start_date;
    $params[':end'] = $end_date;
    $date_label = date('M d, Y', strtotime($start_date)) . " - " . date('M d, Y', strtotime($end_date));
} else {
    $date_label = "All Time Record";
}

function getCount($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

try {
    // 1. Core Population Metrics
    $total_youth = getCount($pdo, "SELECT COUNT(*) FROM youth_registry WHERE $base_where", $params);

    // 2. Verified vs Need Validation vs Incomplete
    $verified_count = getCount($pdo, "SELECT COUNT(*) FROM youth_registry WHERE $base_where AND document_status = 'Verified'", $params);
    $verified_percentage = $total_youth > 0 ? round(($verified_count / $total_youth) * 100) : 0;

    $need_validation = getCount($pdo, "SELECT COUNT(*) FROM youth_registry WHERE $base_where AND (document_status LIKE '%Pending%' OR document_status = '' OR document_status IS NULL)", $params);

    $incomplete_profiles = getCount($pdo, "SELECT COUNT(*) FROM youth_registry WHERE $base_where AND (cellphone_number = '' OR cellphone_number IS NULL OR birthday IS NULL)", $params);

    // 3. Age Demographics
    $new_15_yo = getCount($pdo, "SELECT COUNT(*) FROM youth_registry WHERE $base_where AND YEAR(CURDATE()) - YEAR(birthday) = 15", $params);
    $turning_31 = getCount($pdo, "SELECT COUNT(*) FROM youth_registry WHERE $base_where AND YEAR(CURDATE()) - YEAR(birthday) = 31", $params);

    // 4. Pending Approvals
    $pending_approvals = getCount($pdo, "SELECT COUNT(*) FROM youth_profiles WHERE barangay = :brgy AND remarks = 'Self-Registered via Public Portal'", [':brgy' => $admin_barangay]);

    // 5. Transferred Out
    $transferred = 3; // Baseline indicator

    // 6. Skills Extraction & Ranking
    $stmt_skills = $pdo->prepare("SELECT skills FROM youth_registry WHERE barangay = :brgy AND skills IS NOT NULL AND skills != ''");
    $stmt_skills->execute([':brgy' => $admin_barangay]);
    $all_skills_data = $stmt_skills->fetchAll(PDO::FETCH_COLUMN) ?: [];
    
    $skill_counts = [];
    foreach ($all_skills_data as $skill_string) {
        $skills_array = explode(',', $skill_string);
        foreach ($skills_array as $skill) {
            $cleaned_skill = trim($skill);
            if (!empty($cleaned_skill)) {
                if (!isset($skill_counts[$cleaned_skill])) $skill_counts[$cleaned_skill] = 0;
                $skill_counts[$cleaned_skill]++;
            }
        }
    }
    arsort($skill_counts);
    $top_skills = array_slice($skill_counts, 0, 4, true);

    // 7. Timeline Registration Activity Data
    if (!empty($start_date) && !empty($end_date)) {
        $chart_stmt = $pdo->prepare("SELECT DATE(created_at) as reg_date, COUNT(*) as count FROM youth_registry WHERE $base_where GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC");
        $chart_stmt->execute($params);
        $chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $chart_stmt = $pdo->prepare("SELECT DATE(created_at) as reg_date, COUNT(*) as count FROM youth_registry WHERE barangay = :brgy GROUP BY DATE(created_at) ORDER BY DATE(created_at) DESC LIMIT 30");
        $chart_stmt->execute([':brgy' => $admin_barangay]);
        $chart_data = array_reverse($chart_stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    $chart_labels = []; 
    $chart_values = [];
    foreach ($chart_data as $row) {
        $chart_labels[] = date('M d', strtotime($row['reg_date']));
        $chart_values[] = (int)$row['count'];
    }

} catch (PDOException $e) {
    die("SK Dashboard Engine Error: " . $e->getMessage());
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
    <title>SK Barangay Dashboard | TALA OS</title>
    
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
        };
    </script>
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease; 
            -webkit-tap-highlight-color: transparent; 
        }

        /* Modern Clean Glass Card: ZERO Outside Borders & ZERO Drop Shadows */
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

        /* Subtle Top-Right Green Ambient Gradient for Section Cards */
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

        /* Subtle Top-Right Orange Ambient Gradient for Section Cards */
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

        /* Top Declared Skills Card: Gradient from Green at Top to White Below */
        .card-skills-gradient {
            background: linear-gradient(180deg, #15803d 0%, #16a34a 20%, #f0fdf4 65%, #ffffff 100%) !important;
            border: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
            transition: all 0.2s ease;
        }
        .dark .card-skills-gradient {
            background: linear-gradient(180deg, #15803d 0%, #16a34a 20%, #142e1d 65%, #18181b 100%) !important;
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

        /* Remove all outline / outside strokelines on focus */
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
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- Role Appropriate Sidebar -->
    <?php include 'sidebar_sk.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA (Matching manage_announcements.php & tala_dashboard.php) -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers content smoothly to 1600px -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-3 md:space-y-4">
            
            <!-- HEADER SECTION (H1 + Date Filter + Inline Header Theme Toggle) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl glass-card flex items-center justify-center flex-shrink-0">
                        <img src="<?= htmlspecialchars($mascot_file) ?>" alt="Mascot" class="w-7 h-7 object-contain">
                    </div>
                    <div>
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                            SK Executive Dashboard
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 font-medium">
                            Welcome, <?= htmlspecialchars($admin_name) ?> &bull; <strong class="text-green-600 dark:text-green-500"><?= htmlspecialchars($admin_barangay) ?></strong> &bull; <?= date('l, F j, Y') ?>
                        </p>
                    </div>
                </div>
                
                <!-- Date Filter Bar & Controls Cluster -->
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <form method="GET" action="sk_dashboard.php" class="glass-card p-1.5 rounded-xl flex items-center gap-1.5 transition-colors w-full sm:w-auto border-0">
                        <div class="flex items-center bg-slate-50 dark:bg-zinc-900/60 px-2.5 py-1 rounded-lg">
                            <span class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mr-1.5">From</span>
                            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="text-xs font-semibold text-slate-700 dark:text-white bg-transparent outline-none cursor-pointer border-0" style="color-scheme: dark light;">
                        </div>
                        <div class="flex items-center bg-slate-50 dark:bg-zinc-900/60 px-2.5 py-1 rounded-lg">
                            <span class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mr-1.5">To</span>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="text-xs font-semibold text-slate-700 dark:text-white bg-transparent outline-none cursor-pointer border-0" style="color-scheme: dark light;">
                        </div>
                        <button type="submit" class="bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-1 px-3.5 rounded-lg text-xs transition-all tap-effect shadow-none border-0">
                            Filter
                        </button>
                        <?php if(!empty($start_date)): ?>
                            <a href="sk_dashboard.php" class="p-1 rounded-lg text-slate-400 hover:text-green-600 dark:hover:text-green-400 transition-colors" title="Clear filter">
                                <i class="ph-bold ph-x text-xs"></i>
                            </a>
                        <?php endif; ?>
                    </form>

                    <!-- Header Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[36px] h-[36px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
                        <i id="headerThemeIcon" class="ph-bold ph-moon text-base sm:text-lg transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- CORE KPI METRICS: 4 Clean Minimal Glass Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 md:gap-3.5">
                
                <!-- 1. Registered Youth -->
                <div class="glass-card card-green-glow-tr p-3.5 sm:p-4.5 rounded-2xl flex flex-col justify-between transition-colors">
                    <div class="flex items-center justify-between relative z-10">
                        <span class="text-[10px] md:text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Registered Youth</span>
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-sm sm:text-base">
                            <i class="ph-fill ph-users-three"></i>
                        </span>
                    </div>
                    <div class="mt-2.5 relative z-10">
                        <h2 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                            <?= number_format($total_youth); ?>
                        </h2>
                        <p class="text-[10px] md:text-[11px] text-slate-400 dark:text-zinc-500 font-medium mt-0.5">
                            Katipunan ng Kabataan members
                        </p>
                    </div>
                </div>

                <!-- 2. Target Coverage / Verification Ratio -->
                <div class="glass-card card-green-glow-tr p-3.5 sm:p-4.5 rounded-2xl flex flex-col justify-between transition-colors">
                    <div class="flex items-center justify-between relative z-10">
                        <span class="text-[10px] md:text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Verification Ratio</span>
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-sm sm:text-base">
                            <i class="ph-fill ph-shield-check"></i>
                        </span>
                    </div>
                    <div class="mt-2.5 relative z-10">
                        <div class="flex items-baseline gap-1.5">
                            <h2 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                                <?= $verified_percentage; ?>%
                            </h2>
                            <span class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500 font-medium">of total records</span>
                        </div>
                        <div class="w-full bg-slate-100 dark:bg-zinc-800 rounded-full h-1 mt-2 overflow-hidden">
                            <div class="bg-[#15803d] dark:bg-[#16a34a] h-full rounded-full transition-all duration-500" style="width: <?= min(100, $verified_percentage); ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- 3. New Cohort (15-Year-Olds) -->
                <div class="glass-card card-green-glow-tr p-3.5 sm:p-4.5 rounded-2xl flex flex-col justify-between transition-colors">
                    <div class="flex items-center justify-between relative z-10">
                        <span class="text-[10px] md:text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">New Influx (15 y/o)</span>
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-sm sm:text-base">
                            <i class="ph-fill ph-trend-up"></i>
                        </span>
                    </div>
                    <div class="mt-2.5 relative z-10">
                        <h2 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                            <?= number_format($new_15_yo); ?>
                        </h2>
                        <p class="text-[10px] md:text-[11px] text-slate-400 dark:text-zinc-500 font-medium mt-0.5 truncate">
                            Entering youth cohort this year
                        </p>
                    </div>
                </div>

                <!-- 4. Records Due Validation -->
                <div class="glass-card card-orange-glow-tr p-3.5 sm:p-4.5 rounded-2xl flex flex-col justify-between transition-colors">
                    <div class="flex items-center justify-between relative z-10">
                        <span class="text-[10px] md:text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500">Needs Validation</span>
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm sm:text-base">
                            <i class="ph-fill ph-file-magnifying-glass"></i>
                        </span>
                    </div>
                    <div class="mt-2.5 relative z-10">
                        <h2 class="text-xl sm:text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                            <?= number_format($need_validation); ?>
                        </h2>
                        <p class="text-[10px] md:text-[11px] text-slate-400 dark:text-zinc-500 font-medium mt-0.5">
                            Pending review & documentation
                        </p>
                    </div>
                </div>

            </div>

            <!-- MAIN ANALYTICS SECTION: 2 COLUMNS (8 cols / 4 cols) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 md:gap-4 flex-1 min-h-0">
                
                <!-- LEFT COLUMN: ACTIVITY TIMELINE CHART & TELEMETRY (8 cols) -->
                <div class="lg:col-span-8 flex flex-col space-y-3 md:space-y-4">
                    
                    <!-- Registration Activity Timeline Area Chart -->
                    <div class="glass-card p-4 sm:p-5 rounded-2xl flex flex-col flex-1 transition-colors">
                        <div class="flex items-center justify-between mb-3 flex-shrink-0">
                            <div>
                                <h3 class="text-xs sm:text-sm font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                                    <i class="ph-bold ph-chart-line-up text-green-600 dark:text-green-500"></i> Registration Timeline Activity
                                </h3>
                                <p class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium">Historical profile intake volume for <?= htmlspecialchars($admin_barangay) ?></p>
                            </div>
                            <span class="text-[10px] font-bold text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-950/40 px-2.5 py-1 rounded-xl">
                                <?= !empty($start_date) ? 'Filtered Range' : 'Recent 30 Entries' ?>
                            </span>
                        </div>
                        <div class="relative w-full h-[220px] sm:h-[260px] flex-1">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>

                    <!-- Demographic Status Grid -->
                    <div class="glass-card p-3.5 sm:p-4 rounded-2xl transition-colors">
                        <div class="flex items-center justify-between mb-2.5">
                            <h3 class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                <i class="ph-bold ph-identification-card text-green-600 dark:text-green-500"></i> Demographic Cohort Health
                            </h3>
                            <a href="tala_directory.php" class="text-[10px] font-bold text-green-600 dark:text-green-500 hover:underline flex items-center gap-1 transition-colors">
                                View Directory <i class="ph-bold ph-arrow-right"></i>
                            </a>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <!-- Turning 31 -->
                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-900/60 flex items-center justify-between">
                                <div>
                                    <p class="text-[9px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">Age-Out (31 y/o)</p>
                                    <p class="text-sm sm:text-base font-black text-slate-900 dark:text-white"><?= number_format($turning_31) ?></p>
                                </div>
                                <span class="w-6 h-6 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xs"><i class="ph-bold ph-hourglass-high"></i></span>
                            </div>
                            <!-- Incomplete Profiles -->
                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-900/60 flex items-center justify-between">
                                <div>
                                    <p class="text-[9px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">Incomplete</p>
                                    <p class="text-sm sm:text-base font-black text-slate-900 dark:text-white"><?= number_format($incomplete_profiles) ?></p>
                                </div>
                                <span class="w-6 h-6 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xs"><i class="ph-bold ph-warning"></i></span>
                            </div>
                            <!-- Transferred Out -->
                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-900/60 flex items-center justify-between">
                                <div>
                                    <p class="text-[9px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">Transferred</p>
                                    <p class="text-sm sm:text-base font-black text-slate-900 dark:text-white"><?= number_format($transferred) ?></p>
                                </div>
                                <span class="w-6 h-6 rounded-lg bg-slate-200 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 flex items-center justify-center text-xs"><i class="ph-bold ph-sign-out"></i></span>
                            </div>
                            <!-- Action Required / Approvals -->
                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-900/60 flex items-center justify-between">
                                <div>
                                    <p class="text-[9px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">Pending Portal</p>
                                    <p class="text-sm sm:text-base font-black text-slate-900 dark:text-white"><?= number_format($pending_approvals) ?></p>
                                </div>
                                <span class="w-6 h-6 rounded-lg bg-green-500/10 text-green-600 dark:text-green-400 flex items-center justify-center text-xs"><i class="ph-bold ph-bell-ringing"></i></span>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN: SKILLS RANKING & QUICK ACCESS (4 cols) -->
                <div class="lg:col-span-4 flex flex-col space-y-3 md:space-y-4">
                    
                    <!-- Top Youth Skills Ranking -->
                    <div class="card-skills-gradient rounded-2xl p-4 sm:p-5 flex flex-col flex-1 transition-colors">
                        <div class="flex items-center justify-between mb-3 pb-1.5 border-0">
                            <div>
                                <h3 class="text-xs sm:text-sm font-bold text-white flex items-center gap-1.5">
                                    <i class="ph-fill ph-lightning text-white text-sm"></i> Top Declared Skills
                                </h3>
                                <p class="text-[11px] text-white/90 font-medium">Demographic talents in <?= htmlspecialchars($admin_barangay) ?></p>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-white/20 text-white backdrop-blur-sm border border-white/20">
                                <?= count($top_skills) ?> Disciplines
                            </span>
                        </div>
                        
                        <div class="space-y-2.5 flex-1 overflow-y-auto custom-scrollbar">
                            <?php if (empty($top_skills)): ?>
                                <p class="text-xs text-slate-400 dark:text-zinc-500 italic py-6 text-center">No declared skills recorded yet.</p>
                            <?php else: ?>
                                <?php 
                                $max_skill_cnt = max($top_skills) ?: 1;
                                $s_rank = 1;
                                foreach ($top_skills as $skill_name => $s_count): 
                                    $s_pct = round(($s_count / $max_skill_cnt) * 100);
                                ?>
                                <div>
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="font-bold text-slate-800 dark:text-zinc-100 truncate">
                                            <span class="text-slate-500 dark:text-zinc-400 font-semibold mr-1">#<?= $s_rank++ ?></span>
                                            <?= htmlspecialchars($skill_name) ?>
                                        </span>
                                        <span class="font-black text-slate-900 dark:text-white"><?= number_format($s_count) ?></span>
                                    </div>
                                    <div class="w-full bg-slate-200/70 dark:bg-zinc-800 rounded-full h-1.5 overflow-hidden">
                                        <div class="bg-[#15803d] dark:bg-[#16a34a] h-full rounded-full transition-all duration-500 shadow-sm" style="width: <?= $s_pct ?>%"></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Council Access Hub -->
                    <div class="glass-card rounded-2xl p-4 transition-colors flex-shrink-0">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white mb-2.5">
                            Council Modules
                        </h3>
                        <div class="grid grid-cols-2 gap-2">
                            <a href="tala_directory.php" class="p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 dark:bg-zinc-900/60 dark:hover:bg-zinc-900 hover:text-green-700 dark:hover:text-green-400 text-xs font-bold text-slate-700 dark:text-zinc-300 flex items-center gap-2 transition-colors tap-effect group">
                                <i class="ph-bold ph-address-book text-slate-400 dark:text-zinc-500 group-hover:text-green-600 dark:group-hover:text-green-500 transition-colors"></i> Directory
                            </a>
                            <a href="sk2_documents.php" class="p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 dark:bg-zinc-900/60 dark:hover:bg-zinc-900 hover:text-green-700 dark:hover:text-green-400 text-xs font-bold text-slate-700 dark:text-zinc-300 flex items-center gap-2 transition-colors tap-effect group">
                                <i class="ph-bold ph-files text-slate-400 dark:text-zinc-500 group-hover:text-green-600 dark:group-hover:text-green-500 transition-colors"></i> Documents
                            </a>
                            <a href="kanban.php" class="p-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 dark:bg-zinc-900/60 dark:hover:bg-zinc-900 hover:text-green-700 dark:hover:text-green-400 text-xs font-bold text-slate-700 dark:text-zinc-300 flex items-center gap-2 transition-colors tap-effect group">
                                <i class="ph-bold ph-kanban text-slate-400 dark:text-zinc-500 group-hover:text-green-600 dark:group-hover:text-green-500 transition-colors"></i> Projects
                            </a>
                        </div>
                    </div>

                </div>

            </div>
            
        </div>
    </main>

    <!-- MOBILE BOTTOM NAVIGATION (Android Style - Borderless Design) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 h-[68px] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-2xl z-50 flex justify-around items-center px-2 pb-[env(safe-area-inset-bottom)] transition-colors border-0">
        <a href="sk_dashboard.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d] dark:bg-[#16a34a] text-white rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-squares-four text-2xl text-white"></i>
            </div>
            <span class="text-[10px] font-bold">Dashboard</span>
        </a>
        <a href="kanban.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-kanban text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Projects</span>
        </a>
        <a href="tala_directory.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-address-book text-2xl"></i>
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

    <!-- UI Scripts -->
    <script>
        // Mobile Drawer Toggle
        function toggleMobileMenu() {
            const sidebar = document.getElementById('tala-bulletproof-sk-sidebar') || document.getElementById('sidebar');
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

    <!-- Chart.js Timeline Initialization -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const canvas = document.getElementById('activityChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            
            const chartLabels = <?= json_encode($chart_labels) ?>;
            const chartValues = <?= json_encode($chart_values) ?>;
            const isDark = document.documentElement.classList.contains('dark') || localStorage.theme === 'dark';

            // Area gradient
            const gradient = ctx.createLinearGradient(0, 0, 0, 240);
            if (isDark) {
                gradient.addColorStop(0, 'rgba(22, 163, 74, 0.22)');
                gradient.addColorStop(1, 'rgba(22, 163, 74, 0.0)');
            } else {
                gradient.addColorStop(0, 'rgba(21, 128, 61, 0.18)');
                gradient.addColorStop(1, 'rgba(21, 128, 61, 0.0)');
            }

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartLabels.length > 0 ? chartLabels : ['No records'],
                    datasets: [{
                        label: 'Intake Activity',
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
    
    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>