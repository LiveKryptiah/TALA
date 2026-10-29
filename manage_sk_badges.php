<?php
ob_start();
// manage_sk_badges.php - TALA Admin SK Gamification Hub (TALA OS Standard)
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
$msgType = 'success';

// 1. Auto-create & Auto-upgrade the Table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_admin_xp` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `admin_user` VARCHAR(100) NOT NULL,
        `barangay` VARCHAR(100) NOT NULL,
        `action_name` VARCHAR(255) NOT NULL,
        `xp_earned` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Safe upgrade: Add badge columns if they don't exist yet
    $cols = $pdo->query("SHOW COLUMNS FROM sk2_admin_xp")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('badge_name', $cols)) {
        $pdo->exec("ALTER TABLE sk2_admin_xp ADD COLUMN badge_name VARCHAR(100) NULL AFTER action_name");
        $pdo->exec("ALTER TABLE sk2_admin_xp ADD COLUMN badge_icon VARCHAR(100) NULL AFTER badge_name");
        $pdo->exec("ALTER TABLE sk2_admin_xp ADD COLUMN badge_color VARCHAR(100) NULL AFTER badge_icon");
    }
} catch (Exception $e) {}

// Query preservation helper
function buildUrl($params = []) {
    $current = $_GET;
    foreach ($params as $k => $v) {
        if ($v === null || $v === '' || $v === 'All') {
            unset($current[$k]);
        } else {
            $current[$k] = $v;
        }
    }
    return 'manage_sk_badges.php' . (!empty($current) ? '?' . http_build_query($current) : '');
}

// HANDLE MANUAL XP & CUSTOM BADGE AWARDING
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['award_manual_xp'])) {
    $target_barangays = $_POST['barangays'] ?? [];
    $action_name = "Admin Bonus: " . trim($_POST['action_name'] ?? '');
    $xp_amount = (int)($_POST['xp_amount'] ?? 50);
    
    // Custom Badge Handling
    $badge_name = !empty(trim($_POST['custom_badge_name'] ?? '')) ? trim($_POST['custom_badge_name']) : null;
    $badge_icon = null;
    $badge_color = null;

    if ($badge_name) {
        $badge_icon = $_POST['custom_badge_icon'] ?? 'ph-star';
        
        $color_map = [
            'blue'    => 'text-blue-500 bg-blue-50 dark:text-blue-400 dark:bg-blue-500/10',
            'indigo'  => 'text-indigo-500 bg-indigo-50 dark:text-indigo-400 dark:bg-indigo-500/10',
            'amber'   => 'text-amber-500 bg-amber-50 dark:text-amber-400 dark:bg-amber-500/10',
            'purple'  => 'text-purple-500 bg-purple-50 dark:text-purple-400 dark:bg-purple-500/10',
            'rose'    => 'text-rose-500 bg-rose-50 dark:text-rose-400 dark:bg-rose-500/10',
            'teal'    => 'text-teal-500 bg-teal-50 dark:text-teal-400 dark:bg-teal-500/10'
        ];
        $badge_color = $color_map[$_POST['custom_badge_color'] ?? 'amber'] ?? $color_map['amber'];
    }
    
    if (empty($target_barangays)) {
        $message = "Please select at least one barangay to award.";
        $msgType = "danger";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO sk2_admin_xp (admin_user, barangay, action_name, badge_name, badge_icon, badge_color, xp_earned) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $admin_user = $_SESSION['admin_username'] ?? 'MYDO Admin';
            foreach ($target_barangays as $brgy) {
                $stmt->execute([$admin_user, $brgy, $action_name, $badge_name, $badge_icon, $badge_color, $xp_amount]);
            }
            
            $count = count($target_barangays);
            $message = "Successfully awarded $xp_amount XP " . ($badge_name ? "and the '$badge_name' badge " : "") . "to $count barangay(s)!";
            $msgType = "success";
        } catch (Exception $e) {
            $message = "Failed to process bulk award. Database error.";
            $msgType = "danger";
        }
    }
}

// HANDLE XP REVOCATION (Deletion)
if (isset($_GET['action']) && $_GET['action'] == 'revoke' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM sk2_admin_xp WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: manage_sk_badges.php?msg=revoked");
    exit();
}

if (isset($_GET['msg']) && $_GET['msg'] == 'revoked') {
    $message = 'XP Action successfully revoked.';
    $msgType = 'warning';
}

// FETCH RAW LEADERBOARD DATA
$leaderboard = $pdo->query("SELECT barangay, SUM(xp_earned) as total_xp, COUNT(id) as actions_taken FROM sk2_admin_xp GROUP BY barangay ORDER BY total_xp DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Overall Metrics
$total_xp_awarded = (int)$pdo->query("SELECT COALESCE(SUM(xp_earned), 0) FROM sk2_admin_xp")->fetchColumn();
$total_actions_logged = (int)$pdo->query("SELECT COUNT(*) FROM sk2_admin_xp")->fetchColumn();
$top_barangay = !empty($leaderboard) ? $leaderboard[0]['barangay'] : 'None';

// FETCH EARNED BADGES PER BARANGAY
$earned_badges_raw = $pdo->query("SELECT barangay, badge_name, badge_icon, badge_color FROM sk2_admin_xp WHERE badge_name IS NOT NULL ORDER BY created_at ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$barangay_badges = [];
foreach ($earned_badges_raw as $b) {
    $barangay_badges[$b['barangay']][$b['badge_name']] = $b;
}

// FILTER CONTROLS
$search = trim($_GET['search'] ?? '');
$filter_barangay = trim($_GET['barangay'] ?? '');
$view = trim($_GET['view'] ?? 'leaderboard');

// FILTERED LEADERBOARD
$filtered_leaderboard = [];
foreach ($leaderboard as $board) {
    if (!empty($filter_barangay) && $filter_barangay !== 'All' && strcasecmp($board['barangay'], $filter_barangay) !== 0) {
        continue;
    }
    if (!empty($search)) {
        $matches_name = stripos($board['barangay'], $search) !== false;
        $matches_badge = false;
        if (isset($barangay_badges[$board['barangay']])) {
            foreach ($barangay_badges[$board['barangay']] as $badge) {
                if (stripos($badge['badge_name'], $search) !== false) {
                    $matches_badge = true;
                    break;
                }
            }
        }
        if (!$matches_name && !$matches_badge) {
            continue;
        }
    }
    $filtered_leaderboard[] = $board;
}

// FILTERED AUDIT LOG
$log_query = "SELECT * FROM sk2_admin_xp WHERE 1=1";
$log_params = [];

if (!empty($filter_barangay) && $filter_barangay !== 'All') {
    $log_query .= " AND barangay = ?";
    $log_params[] = $filter_barangay;
}

if (!empty($search)) {
    $log_query .= " AND (action_name LIKE ? OR barangay LIKE ? OR badge_name LIKE ? OR admin_user LIKE ?)";
    $term = "%{$search}%";
    $log_params[] = $term;
    $log_params[] = $term;
    $log_params[] = $term;
    $log_params[] = $term;
}

$log_query .= " ORDER BY created_at DESC LIMIT 100";
$stmt = $pdo->prepare($log_query);
$stmt->execute($log_params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// ALL 46 BARANGAYS OF CABAGAN
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

$sk_barangays = $pdo->query("SELECT DISTINCT barangay_assigned FROM admins WHERE role = 'sub-admin' ORDER BY barangay_assigned ASC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
$target_selection_barangays = !empty($sk_barangays) ? $sk_barangays : $all_barangays;

// Badge Tier Helper
if (!function_exists('getAdminBadgeTier')) {
    function getAdminBadgeTier($xp) {
        if ($xp >= 5000) return ['name' => 'Legendary', 'color' => 'bg-fuchsia-500/10 text-fuchsia-700 dark:text-fuchsia-400'];
        if ($xp >= 1000) return ['name' => 'Elite', 'color' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400'];
        if ($xp >= 500)  return ['name' => 'Active', 'color' => 'bg-green-500/10 text-green-700 dark:text-green-400'];
        return ['name' => 'Developing', 'color' => 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SK Leaderboard & Badges | TALA OS</title>
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

        /* Borderless Checkbox Style */
        .brgy-checkbox {
            appearance: none;
            background-color: #f1f5f9;
            margin: 0;
            font: inherit;
            color: currentColor;
            width: 1.15em;
            height: 1.15em;
            border: none;
            border-radius: 0.375rem;
            display: grid;
            place-content: center;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .dark .brgy-checkbox { background-color: #27272a; }
        .brgy-checkbox::before {
            content: "";
            width: 0.65em;
            height: 0.65em;
            transform: scale(0);
            transition: 120ms transform ease-in-out;
            box-shadow: inset 1em 1em #ffffff;
            transform-origin: bottom left;
            clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
        }
        .brgy-checkbox:checked {
            background-color: #15803d;
        }
        .dark .brgy-checkbox:checked {
            background-color: #16a34a;
        }
        .brgy-checkbox:checked::before {
            transform: scale(1);
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

    <!-- Admin Sidebar -->
    <?php include 'sidebar_admin.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA (Responsive & Scrollable - Matching manage_disclosure.php) -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-start">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px and fits viewport smoothly -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-3 md:space-y-4">
            
            <!-- HEADER SECTION (H1 + 3 Unified Green KPI Cards + Header Dark Mode Toggle with xl:pr-14 clearance) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <div>
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                            SK Gamification & Badges
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 font-medium">
                            Council performance leaderboard, earned credentials & recognition points
                        </p>
                    </div>
                </div>
                
                <!-- Quick KPI Badges & Controls -->
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Total XP Awarded -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-trophy text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total XP Awarded</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight truncate"><?= number_format($total_xp_awarded) ?> XP</span>
                        </div>
                    </div>

                    <!-- KPI 2: Ranked Councils -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-buildings text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Ranked Councils</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= count($leaderboard) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Top Leader -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-crown text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Top Leader</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight truncate max-w-[120px] block" title="<?= htmlspecialchars($top_barangay) ?>">
                                <?= htmlspecialchars($top_barangay) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Header Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[36px] h-[36px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
                        <i id="headerThemeIcon" class="ph-bold ph-moon text-base sm:text-lg transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- Action Status Alert -->
            <?php if (!empty($message)): ?>
                <?php 
                    $alertColor = 'bg-green-50/85 dark:bg-green-500/10 text-green-700 dark:text-green-400';
                    $alertIcon = 'ph-check-circle';
                    if ($msgType === 'danger' || $msgType === 'error') {
                        $alertColor = 'bg-red-50/85 dark:bg-red-500/10 text-red-700 dark:text-red-400';
                        $alertIcon = 'ph-warning-circle';
                    } elseif ($msgType === 'warning') {
                        $alertColor = 'bg-amber-50/85 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400';
                        $alertIcon = 'ph-warning';
                    }
                ?>
                <div class="<?= $alertColor ?> px-4 py-3 rounded-2xl flex items-center justify-between gap-3 text-xs md:text-sm font-bold shadow-none backdrop-blur-md transition-colors border-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <i class="ph-bold <?= $alertIcon ?> text-lg flex-shrink-0"></i>
                        <span class="truncate"><?= htmlspecialchars($message) ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="w-6 h-6 rounded-lg flex items-center justify-center hover:bg-black/5 dark:hover:bg-white/10 transition-colors tap-effect" aria-label="Dismiss">
                        <i class="ph-bold ph-x text-xs"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- FILTER, SEARCH & ACTION CONTROL BAR (Matching manage_disclosure.php standard) -->
            <div class="glass-card card-green-glow-bl rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-2.5">
                
                <!-- Console View Switcher Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full xl:w-auto custom-scrollbar py-0.5 flex-shrink-0">
                    <button type="button" onclick="switchConsoleView('leaderboard')" id="btnViewLeaderboard" class="btn-yellow-glow-r inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-[#15803d] text-white dark:bg-[#16a34a]">
                        <i class="ph-bold ph-ranking text-xs relative z-10"></i>
                        <span class="relative z-10">Leaderboard</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-white/20 dark:bg-black/20 text-white relative z-10"><?= number_format(count($filtered_leaderboard)) ?></span>
                    </button>
                    <button type="button" onclick="switchConsoleView('audit')" id="btnViewAudit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white">
                        <i class="ph-bold ph-clock-counter-clockwise text-xs"></i>
                        <span>Audit Trail</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300"><?= number_format(count($logs)) ?></span>
                    </button>
                </div>

                <!-- Dropdowns, Search & Action Button Cluster -->
                <div class="flex flex-wrap items-center gap-2 w-full xl:w-auto">
                    <!-- Barangay Dropdown -->
                    <form method="GET" class="w-full sm:w-auto sm:flex-1 md:w-44 min-w-[140px]">
                        <?php if (!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <select name="barangay" onchange="this.form.submit()" class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="">All 46 Barangays</option>
                            <?php foreach ($all_barangays as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= $filter_barangay === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <!-- Search Input -->
                    <form method="GET" class="relative w-full sm:w-auto sm:flex-1 md:w-56 min-w-[170px]">
                        <?php if (!empty($filter_barangay) && $filter_barangay !== 'All'): ?><input type="hidden" name="barangay" value="<?= htmlspecialchars($filter_barangay) ?>"><?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search council, badge, reason..." class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none transition-colors border-0">
                    </form>

                    <!-- Action Buttons Cluster -->
                    <div class="flex items-center gap-1.5 w-full sm:w-auto ml-auto sm:ml-0 flex-shrink-0">
                        <?php if (!empty($search) || (!empty($filter_barangay) && $filter_barangay !== 'All')): ?>
                            <a href="manage_sk_badges.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 font-bold h-8 w-8 rounded-xl text-xs transition-all tap-effect" title="Clear Filters">
                                <i class="ph-bold ph-x text-xs"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Print Button -->
                        <button type="button" onclick="window.print()" class="no-print hidden sm:flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 font-bold h-8 px-2.5 rounded-xl text-xs transition-all tap-effect" title="Print Leaderboard / Audit Log">
                            <i class="ph-bold ph-printer text-sm mr-1"></i>
                            <span>Print</span>
                        </button>

                        <!-- Grant Award Button -->
                        <button type="button" onclick="openAwardModal()" class="btn-yellow-glow-r px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center justify-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group cursor-pointer">
                            <i class="ph-bold ph-plus text-xs relative z-10 transition-transform duration-300 group-hover:scale-110"></i>
                            <span class="relative z-10">Grant Award & Badge</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- CONSOLIDATED MINIMAL CONSOLE TABLE CARD -->
            <div class="glass-card card-green-glow-bl rounded-2xl md:rounded-3xl overflow-hidden flex flex-col flex-1 min-h-[350px] lg:min-h-0 transition-colors">
                
                <!-- Minimal Header Strip -->
                <div class="p-3 md:p-3.5 bg-white/50 dark:bg-zinc-900/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 border-b border-slate-100 dark:border-zinc-800/80 transition-colors flex-shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-base font-bold flex-shrink-0">
                            <i class="ph-fill ph-trophy" id="cardHeaderIcon"></i>
                        </div>
                        <div>
                            <h2 class="text-xs md:text-sm font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-1.5">
                                <span id="cardHeaderTitle">Council Leaderboard Standings</span>
                            </h2>
                            <p class="text-[11px] md:text-xs text-slate-500 dark:text-zinc-400 font-medium" id="cardHeaderSubtitle">Official municipal rankings, earned visual badges, and XP standings</p>
                        </div>
                    </div>
                    <span class="text-xs font-bold text-slate-700 dark:text-zinc-300 bg-slate-100 dark:bg-zinc-800 px-2.5 py-0.5 rounded-lg" id="viewCountBadge">
                        <?= number_format(count($filtered_leaderboard)) ?> Councils
                    </span>
                </div>

                <!-- VIEW 1: LEADERBOARD TABLE -->
                <div id="viewLeaderboardSection" class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[750px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[11px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider bg-slate-100/90 dark:bg-zinc-800/90 backdrop-blur-sm transition-colors border-b border-slate-100 dark:border-zinc-800/80">
                                <th class="py-2.5 px-4 md:px-5 text-center w-16">Rank</th>
                                <th class="py-2.5 px-4 md:px-5">Council / Barangay</th>
                                <th class="py-2.5 px-4 md:px-5">Tier Classification</th>
                                <th class="py-2.5 px-4 md:px-5">Trophy Cabinet (Badges)</th>
                                <th class="py-2.5 px-4 md:px-5 text-right">Total XP</th>
                                <th class="py-2.5 px-4 md:px-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs md:text-sm font-medium divide-y divide-slate-100/80 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($filtered_leaderboard)): ?>
                                <?php 
                                $pos = 1;
                                foreach ($filtered_leaderboard as $board): 
                                    $bName = $board['barangay'];
                                    $tier = getAdminBadgeTier($board['total_xp']);
                                    
                                    $rankStyle = "bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300";
                                    if ($pos === 1) $rankStyle = "bg-amber-500/10 text-amber-700 dark:text-amber-400 font-black";
                                    elseif ($pos === 2) $rankStyle = "bg-slate-200 text-slate-800 dark:bg-zinc-700 dark:text-zinc-200 font-bold";
                                    elseif ($pos === 3) $rankStyle = "bg-orange-500/10 text-orange-700 dark:text-orange-400 font-bold";
                                ?>
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-800/40 transition-colors group">
                                        <!-- Rank -->
                                        <td class="py-2.5 px-4 md:px-5 text-center whitespace-nowrap">
                                            <span class="w-7 h-7 rounded-xl inline-flex items-center justify-center text-xs font-black <?= $rankStyle ?> transition-colors">
                                                #<?= $pos++ ?>
                                            </span>
                                        </td>

                                        <!-- Barangay -->
                                        <td class="py-2.5 px-4 md:px-5 whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300 px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider transition-colors">
                                                <i class="ph-fill ph-map-pin text-green-600 dark:text-green-500 text-[10px]"></i> <?= htmlspecialchars($bName) ?>
                                            </span>
                                        </td>

                                        <!-- Tier -->
                                        <td class="py-2.5 px-4 md:px-5 whitespace-nowrap">
                                            <span class="inline-block px-2.5 py-0.5 rounded-lg text-[10px] md:text-xs font-extrabold uppercase tracking-wider <?= $tier['color'] ?> transition-colors">
                                                <?= $tier['name'] ?>
                                            </span>
                                        </td>

                                        <!-- Trophy Cabinet -->
                                        <td class="py-2.5 px-4 md:px-5">
                                            <div class="flex flex-wrap gap-1.5 items-center">
                                                <?php if (isset($barangay_badges[$bName]) && !empty($barangay_badges[$bName])): ?>
                                                    <?php foreach ($barangay_badges[$bName] as $earned): ?>
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider rounded-lg <?= $earned['badge_color'] ?>" title="<?= htmlspecialchars($earned['badge_name']) ?>">
                                                            <i class="ph-fill <?= htmlspecialchars($earned['badge_icon']) ?>"></i>
                                                            <span><?= htmlspecialchars($earned['badge_name']) ?></span>
                                                        </span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-xs text-slate-400 dark:text-zinc-500 italic">No badges earned yet</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- Total XP -->
                                        <td class="py-2.5 px-4 md:px-5 text-right whitespace-nowrap">
                                            <span class="font-extrabold text-green-600 dark:text-green-500 text-xs md:text-sm">
                                                <?= number_format($board['total_xp']) ?> XP
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="py-2.5 px-4 md:px-5 text-right align-middle whitespace-nowrap">
                                            <button type="button" onclick="openAwardModalFor('<?= htmlspecialchars($bName, ENT_QUOTES) ?>')" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 hover:bg-[#15803d] hover:text-white dark:hover:bg-[#16a34a] dark:hover:text-white text-xs font-bold transition-colors tap-effect" title="Award XP to <?= htmlspecialchars($bName) ?>">
                                                <i class="ph-bold ph-plus-circle text-xs"></i>
                                                <span>Award</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-14 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <div class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-2 flex items-center justify-center">
                                            <img src="mascot_question.png" alt="No Councils" class="max-h-full max-w-full object-contain pointer-events-none hover:scale-105 transition-transform duration-300">
                                        </div>
                                        <p class="font-extrabold text-sm text-slate-800 dark:text-zinc-200">No Councils Found</p>
                                        <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium mt-0.5">Try clearing filters or click Grant Award above to award points.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- VIEW 2: AUDIT TRAIL LOG TABLE (Hidden by Default) -->
                <div id="viewAuditSection" class="hidden overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[750px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[11px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider bg-slate-100/90 dark:bg-zinc-800/90 backdrop-blur-sm transition-colors border-b border-slate-100 dark:border-zinc-800/80">
                                <th class="py-2.5 px-4 md:px-5">Timestamp</th>
                                <th class="py-2.5 px-4 md:px-5">Barangay & Issuer</th>
                                <th class="py-2.5 px-4 md:px-5">Achievement & Badge</th>
                                <th class="py-2.5 px-4 md:px-5 text-center">XP Points</th>
                                <th class="py-2.5 px-4 md:px-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs md:text-sm font-medium divide-y divide-slate-100/80 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($logs)): ?>
                                <?php foreach ($logs as $log): 
                                    $isManual = strpos($log['action_name'], 'Admin Bonus:') === 0;
                                ?>
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-800/40 transition-colors group">
                                        <!-- Timestamp -->
                                        <td class="py-2.5 px-4 md:px-5 whitespace-nowrap text-xs">
                                            <span class="font-bold text-slate-800 dark:text-zinc-200"><?= date('M j, Y', strtotime($log['created_at'])) ?></span>
                                            <span class="block text-[10px] text-slate-400 dark:text-zinc-500 uppercase tracking-wider mt-0.5"><?= date('h:i A', strtotime($log['created_at'])) ?></span>
                                        </td>

                                        <!-- Barangay & Issuer -->
                                        <td class="py-2.5 px-4 md:px-5 whitespace-nowrap">
                                            <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300 px-2.5 py-1 rounded-lg text-xs font-bold uppercase tracking-wider transition-colors">
                                                <i class="ph-fill ph-map-pin text-green-600 dark:text-green-500 text-[10px]"></i> <?= htmlspecialchars($log['barangay']) ?>
                                            </span>
                                            <span class="block text-[10px] uppercase font-semibold text-slate-400 dark:text-zinc-500 tracking-wider mt-1">
                                                <?= htmlspecialchars($log['admin_user']) ?>
                                            </span>
                                        </td>

                                        <!-- Achievement & Badge -->
                                        <td class="py-2.5 px-4 md:px-5">
                                            <div class="flex flex-col gap-1">
                                                <span class="font-bold text-slate-900 dark:text-white text-xs leading-snug flex items-center gap-1.5">
                                                    <?php if ($isManual): ?>
                                                        <i class="ph-fill ph-star text-amber-500 text-sm"></i>
                                                    <?php else: ?>
                                                        <i class="ph-fill ph-check-circle text-green-600 dark:text-green-500 text-sm"></i>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars(str_replace('Admin Bonus: ', '', $log['action_name'])) ?>
                                                </span>
                                                <?php if(!empty($log['badge_name'])): ?>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider rounded-lg w-max <?= $log['badge_color'] ?>">
                                                        <i class="ph-fill <?= htmlspecialchars($log['badge_icon']) ?>"></i>
                                                        <?= htmlspecialchars($log['badge_name']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- XP Points -->
                                        <td class="py-2.5 px-4 md:px-5 text-center whitespace-nowrap">
                                            <span class="inline-block px-2.5 py-1 text-xs font-extrabold rounded-xl bg-green-500/10 text-green-700 dark:text-green-400">
                                                +<?= number_format($log['xp_earned']) ?> XP
                                            </span>
                                        </td>

                                        <!-- Actions -->
                                        <td class="py-2.5 px-4 md:px-5 text-right align-middle whitespace-nowrap">
                                            <a href="manage_sk_badges.php?action=revoke&id=<?= $log['id'] ?>" onclick="return confirm('Revoke this XP entry? It will reconcile council rankings.')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Revoke Action">
                                                <i class="ph-bold ph-trash text-xs"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-14 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <div class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-2 flex items-center justify-center">
                                            <img src="mascot_question.png" alt="No Records" class="max-h-full max-w-full object-contain pointer-events-none hover:scale-105 transition-transform duration-300">
                                        </div>
                                        <p class="font-extrabold text-sm text-slate-800 dark:text-zinc-200">No Recognition Actions Logged</p>
                                        <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium mt-0.5">Use the Grant Award & Badge button to reward council achievements.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Minimal Table Footer -->
                <div class="px-4 py-2.5 bg-white/40 dark:bg-zinc-900/40 flex justify-between items-center text-xs text-slate-500 dark:text-zinc-400 font-medium flex-shrink-0 border-t border-slate-100 dark:border-zinc-800/80">
                    <span class="text-[11px]">Active Councils &bull; <strong class="text-slate-800 dark:text-zinc-200 font-bold"><?= count($leaderboard) ?></strong> Registered</span>
                    <span class="text-[10px] text-slate-400 dark:text-zinc-500">SK Municipal Gamification Hub &bull; TALA OS</span>
                </div>

            </div>
            
        </div>
    </main>

    <!-- GRANT AWARD & BADGE MODAL (Matching manage_disclosure.php standard) -->
    <div id="awardModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeAwardModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] card-green-glow-bl overflow-hidden max-w-xl w-full p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none max-h-[90vh] overflow-y-auto custom-scrollbar border-0" id="awardModalContent">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-4 pb-2 border-b border-slate-100 dark:border-zinc-800">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 rounded-xl flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-fill ph-seal-check"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight">Grant Award & Badge</h3>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono mt-0.5">Council Recognition & Points</p>
                    </div>
                </div>
                <button type="button" onclick="closeAwardModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>
            
            <!-- Modal Body (Form) -->
            <form method="POST" id="awardForm" class="space-y-3.5">
                <input type="hidden" name="award_manual_xp" value="1">
                
                <!-- 1. Target Barangays -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider">Target Barangays *</label>
                        <button type="button" onclick="toggleAllBarangays()" id="toggleAllBtn" class="text-[11px] font-bold text-green-600 dark:text-green-500 uppercase tracking-wider hover:underline focus:outline-none">Select All</button>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-zinc-800 rounded-xl p-2.5 max-h-40 overflow-y-auto custom-scrollbar grid grid-cols-1 sm:grid-cols-2 gap-1.5 border-0">
                        <?php foreach ($target_selection_barangays as $b): ?>
                            <label class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-white dark:hover:bg-zinc-700/60 cursor-pointer transition-colors group">
                                <input type="checkbox" name="barangays[]" value="<?= htmlspecialchars($b) ?>" class="brgy-cb brgy-checkbox">
                                <span class="text-xs font-semibold text-slate-700 dark:text-zinc-300 group-hover:text-green-600 dark:group-hover:text-green-500 transition-colors truncate"><?= htmlspecialchars($b) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 2. Reason & Points -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Achievement Reason *</label>
                        <input type="text" name="action_name" required placeholder="e.g. Conducted Youth Assembly" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 text-xs md:text-sm font-semibold rounded-xl focus:outline-none transition-colors border-0">
                    </div>
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">XP Points *</label>
                        <input type="number" name="xp_amount" required value="50" min="1" step="5" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white text-xs md:text-sm font-semibold rounded-xl focus:outline-none transition-colors border-0">
                    </div>
                </div>

                <!-- 3. Optional Visual Badge Designer -->
                <div class="bg-slate-50 dark:bg-zinc-950/80 p-3.5 rounded-2xl space-y-2.5 transition-colors border-0">
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="ph-bold ph-paint-brush text-green-600 dark:text-green-500"></i> Visual Badge Designer (Optional)
                    </label>
                    
                    <div>
                        <input type="text" name="custom_badge_name" placeholder="Custom Badge Title (e.g. Eco Pioneer, Champion of Youth)" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 text-xs md:text-sm font-semibold rounded-xl focus:outline-none transition-colors border-0">
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1">Icon</label>
                            <select name="custom_badge_icon" class="w-full px-2.5 py-1.5 bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white text-xs md:text-sm font-semibold rounded-xl focus:outline-none cursor-pointer transition-colors border-0">
                                <option value="ph-star">⭐ Star</option>
                                <option value="ph-trophy">🏆 Trophy</option>
                                <option value="ph-medal">🎖️ Medal</option>
                                <option value="ph-file-text">📄 Document</option>
                                <option value="ph-users-three">👥 Community</option>
                                <option value="ph-robot">🤖 Innovator</option>
                                <option value="ph-leaf">🌿 Environment</option>
                                <option value="ph-heartbeat">❤️ Health</option>
                                <option value="ph-shield-check">🛡️ Shield</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1">Color Theme</label>
                            <select name="custom_badge_color" class="w-full px-2.5 py-1.5 bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white text-xs md:text-sm font-semibold rounded-xl focus:outline-none cursor-pointer transition-colors border-0">
                                <option value="amber">Amber / Gold</option>
                                <option value="blue">Ocean Blue</option>
                                <option value="indigo">Indigo Accent</option>
                                <option value="purple">Royal Purple</option>
                                <option value="rose">Ruby Rose</option>
                                <option value="teal">Teal Breeze</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button" onclick="closeAwardModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm transition-all tap-effect">
                        Cancel
                    </button>
                    <button type="submit" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2 px-5 rounded-xl transition-all text-xs md:text-sm tap-effect flex items-center gap-1.5 shadow-none">
                        <i class="ph-bold ph-paper-plane-tilt relative z-10"></i>
                        <span class="relative z-10">Grant Reward & Badge</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- UI Scripts -->
    <script>
        // View Switcher: Leaderboard vs Audit Trail
        let currentView = 'leaderboard';
        const totalLeaderboardCount = <?= count($filtered_leaderboard) ?>;
        const totalAuditCount = <?= count($logs) ?>;

        function switchConsoleView(view) {
            currentView = view;
            const lbSection = document.getElementById('viewLeaderboardSection');
            const auditSection = document.getElementById('viewAuditSection');
            const btnLb = document.getElementById('btnViewLeaderboard');
            const btnAudit = document.getElementById('btnViewAudit');
            const headerTitle = document.getElementById('cardHeaderTitle');
            const headerSubtitle = document.getElementById('cardHeaderSubtitle');
            const headerIcon = document.getElementById('cardHeaderIcon');
            const countBadge = document.getElementById('viewCountBadge');

            if (view === 'leaderboard') {
                lbSection.classList.remove('hidden');
                auditSection.classList.add('hidden');

                btnLb.className = "btn-yellow-glow-r inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-[#15803d] text-white dark:bg-[#16a34a]";
                btnAudit.className = "inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white";

                headerTitle.innerText = "Council Leaderboard Standings";
                headerSubtitle.innerText = "Official municipal rankings, earned visual badges, and XP standings";
                headerIcon.className = "ph-fill ph-trophy text-green-600 dark:text-green-500";
                countBadge.innerText = totalLeaderboardCount + " Councils";
            } else {
                lbSection.classList.add('hidden');
                auditSection.classList.remove('hidden');

                btnAudit.className = "btn-yellow-glow-r inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-[#15803d] text-white dark:bg-[#16a34a]";
                btnLb.className = "inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white";

                headerTitle.innerText = "Global XP & Recognition Audit Log";
                headerSubtitle.innerText = "Official audit trail of awarded points, visual badges, and gamification logs";
                headerIcon.className = "ph-fill ph-clock-counter-clockwise text-green-600 dark:text-green-500";
                countBadge.innerText = totalAuditCount + " Records";
            }
        }

        // Modal Controls: Grant Award
        const awardModal = document.getElementById('awardModal');
        const awardModalContent = document.getElementById('awardModalContent');
        const awardForm = document.getElementById('awardForm');

        function openAwardModal() {
            if (awardForm) awardForm.reset();
            const btn = document.getElementById('toggleAllBtn');
            if (btn) btn.textContent = "Select All";
            allSelected = false;

            if (awardModal && awardModalContent) {
                awardModal.classList.remove('hidden');
                awardModal.classList.add('flex');
                requestAnimationFrame(() => {
                    awardModal.classList.remove('opacity-0');
                    awardModal.classList.add('opacity-100');
                    awardModalContent.classList.remove('scale-95', 'opacity-0');
                    awardModalContent.classList.add('scale-100', 'opacity-100');
                });
            }
        }

        function openAwardModalFor(brgyName) {
            openAwardModal();
            const checkboxes = document.querySelectorAll('.brgy-cb');
            checkboxes.forEach(cb => {
                cb.checked = (cb.value.toLowerCase() === brgyName.toLowerCase());
            });
        }

        function closeAwardModal() {
            if (awardModal && awardModalContent) {
                awardModal.classList.remove('opacity-100');
                awardModal.classList.add('opacity-0');
                awardModalContent.classList.remove('scale-100', 'opacity-100');
                awardModalContent.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    awardModal.classList.remove('flex');
                    awardModal.classList.add('hidden');
                }, 200);
            }
        }

        // Close on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (awardModal && !awardModal.classList.contains('hidden')) closeAwardModal();
            }
        });

        // Checkbox "Select All" Toggle Logic
        let allSelected = false;
        function toggleAllBarangays() {
            const checkboxes = document.querySelectorAll('.brgy-cb');
            const btn = document.getElementById('toggleAllBtn');
            
            allSelected = !allSelected;
            checkboxes.forEach(cb => cb.checked = allSelected);
            
            if (btn) btn.textContent = allSelected ? "Deselect All" : "Select All";
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
    </script>
    
    <!-- Genesis AI Assistant Widget -->
    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>