<?php
// admin_mydc.php - TALA Admin: Manage MYDC Members (TALA OS Standard)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Security check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php"); 
    exit();
}

$message = ''; 
$msgType = 'success';

// Auto-create & upgrade MYDC table for TALA
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tala_mydc_registry` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `full_name` VARCHAR(255) NOT NULL,
        `position` VARCHAR(150) NOT NULL,
        `organization` VARCHAR(255) NOT NULL,
        `barangay` VARCHAR(100) NOT NULL,
        `photo_path` VARCHAR(255),
        `status` ENUM('Active', 'Inactive') DEFAULT 'Active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $cols = $pdo->query("SHOW COLUMNS FROM `tala_mydc_registry`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('status', $cols)) {
        $pdo->exec("ALTER TABLE `tala_mydc_registry` ADD COLUMN `status` ENUM('Active', 'Inactive') DEFAULT 'Active' AFTER `photo_path`");
    }
} catch (PDOException $e) {}

// Query URL builder helper
function buildUrl($params = []) {
    $current = $_GET;
    foreach ($params as $k => $v) {
        if ($v === null || $v === '' || $v === 'All') {
            unset($current[$k]);
        } else {
            $current[$k] = $v;
        }
    }
    return 'admin_mydc.php' . (!empty($current) ? '?' . http_build_query($current) : '');
}

// CREATE OR UPDATE MEMBER
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_member'])) {
    $member_id = (int)($_POST['member_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/mydc/';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $filename = 'MYDC_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target)) {
                $photo_path = $target;
            }
        }
    }

    if (!empty($member_id)) {
        // UPDATE
        $sql = "UPDATE tala_mydc_registry SET full_name = ?, position = ?, organization = ?, barangay = ?, status = ?";
        $params = [$full_name, $position, $organization, $barangay, $status];
        if ($photo_path) {
            $sql .= ", photo_path = ?";
            $params[] = $photo_path;
        }
        $sql .= " WHERE id = ?";
        $params[] = $member_id;
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
            $message = "Council member '{$full_name}' updated successfully!";
            $msgType = "success";
        }
    } else {
        // INSERT
        $stmt = $pdo->prepare("INSERT INTO tala_mydc_registry (full_name, position, organization, barangay, photo_path, status) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$full_name, $position, $organization, $barangay, $photo_path, $status])) {
            $message = "Council member '{$full_name}' registered successfully!";
            $msgType = "success";
        }
    }
}

// DELETE MEMBER
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT photo_path FROM tala_mydc_registry WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['photo_path']) && file_exists($row['photo_path'])) {
        @unlink($row['photo_path']);
    }
    
    $pdo->prepare("DELETE FROM tala_mydc_registry WHERE id = ?")->execute([$id]);
    $msg = "Council member removed from registry.";
    header("Location: admin_mydc.php?msg=" . urlencode($msg) . "&type=success"); 
    exit();
}

if (!empty($_GET['msg'])) {
    $message = $_GET['msg'];
    $msgType = $_GET['type'] ?? 'success';
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
$total_members = (int)$pdo->query("SELECT COUNT(*) FROM tala_mydc_registry")->fetchColumn();
$unique_barangays = (int)$pdo->query("SELECT COUNT(DISTINCT barangay) FROM tala_mydc_registry WHERE barangay IS NOT NULL AND barangay != ''")->fetchColumn();
$active_members_count = (int)$pdo->query("SELECT COUNT(*) FROM tala_mydc_registry WHERE status = 'Active'")->fetchColumn();
$inactive_members_count = $total_members - $active_members_count;

// Search and Filter Query
$search_query = trim($_GET['search'] ?? '');
$brgy_filter = trim($_GET['barangay'] ?? '');
$status_filter = trim($_GET['status'] ?? 'All');

$sql = "SELECT * FROM tala_mydc_registry WHERE 1=1";
$params = [];

if ($status_filter !== 'All' && in_array($status_filter, ['Active', 'Inactive'])) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($brgy_filter)) {
    $sql .= " AND barangay = ?";
    $params[] = $brgy_filter;
}

if (!empty($search_query)) {
    $sql .= " AND (full_name LIKE ? OR position LIKE ? OR organization LIKE ? OR barangay LIKE ?)";
    $term = "%{$search_query}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
$current_filtered_count = count($members);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MYDC Council Registry | TALA OS</title>
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
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
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
                <div class="flex items-center gap-2.5 min-w-0 w-full sm:w-auto">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <div class="min-w-0">
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors truncate">
                            MYDC Council Registry
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 font-medium truncate hidden sm:block">Municipal Youth Development Council roster, sector representatives & barangay affiliations</p>
                    </div>
                </div>
                
                <!-- Section Cards (3 KPI badges) & Dark Mode Toggle -->
                <div class="flex items-center gap-1.5 sm:gap-2 w-full sm:w-auto overflow-x-auto sm:overflow-visible pb-1 sm:pb-0 custom-scrollbar flex-shrink-0">
                    <!-- KPI 1: Council Members -->
                    <div class="glass-card card-green-glow-bl px-2.5 sm:px-3.5 py-1.5 rounded-xl flex items-center gap-2 sm:gap-2.5 flex-1 sm:flex-none min-w-[105px] sm:min-w-0 transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs sm:text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-users-three text-sm sm:text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[9px] sm:text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Council Members</span>
                            <span class="text-xs sm:text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight block truncate"><?= number_format($total_members) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Represented Barangays -->
                    <div class="glass-card card-green-glow-bl px-2.5 sm:px-3.5 py-1.5 rounded-xl flex items-center gap-2 sm:gap-2.5 flex-1 sm:flex-none min-w-[105px] sm:min-w-0 transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs sm:text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-map-pin text-sm sm:text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[9px] sm:text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Brgy Affiliations</span>
                            <span class="text-xs sm:text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight block truncate"><?= number_format($unique_barangays) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Active Standing -->
                    <div class="glass-card card-green-glow-bl px-2.5 sm:px-3.5 py-1.5 rounded-xl flex items-center gap-2 sm:gap-2.5 flex-1 sm:flex-none min-w-[100px] sm:min-w-0 transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs sm:text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-seal-check text-sm sm:text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[9px] sm:text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Active Standing</span>
                            <span class="text-xs sm:text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight block truncate"><?= number_format($active_members_count) ?></span>
                        </div>
                    </div>

                    <!-- Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[34px] h-[34px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
                        <i id="headerThemeIcon" class="ph-bold ph-moon text-base sm:text-lg transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- ACTION NOTIFICATIONS BANNER -->
            <?php if (!empty($message)): ?>
                <?php 
                    $alertColor = 'bg-green-50/85 dark:bg-green-500/10 text-green-700 dark:text-green-400';
                    $alertIcon = 'ph-check-circle';
                    if ($msgType === 'error' || $msgType === 'danger') {
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
                
                <!-- Quick Status Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full xl:w-auto custom-scrollbar py-0.5 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['val' => 'All', 'label' => 'All Members', 'count' => $total_members, 'active' => ($status_filter === 'All' || empty($status_filter))],
                        ['val' => 'Active', 'label' => 'Active', 'count' => $active_members_count, 'active' => ($status_filter === 'Active')],
                        ['val' => 'Inactive', 'label' => 'Inactive', 'count' => $inactive_members_count, 'active' => ($status_filter === 'Inactive')],
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

                <!-- Search, Barangay Dropdown, Print & Register Actions -->
                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full xl:w-auto">
                    <!-- Barangay Dropdown Selector -->
                    <form method="GET" action="admin_mydc.php" class="w-full sm:w-auto sm:flex-1 md:w-44 min-w-[130px]">
                        <?php if ($status_filter !== 'All' && !empty($status_filter)): ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                        <?php endif; ?>
                        <?php if (!empty($search_query)): ?>
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                        <?php endif; ?>
                        <select name="barangay" onchange="this.form.submit()" class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="">All 46 Barangays</option>
                            <?php foreach ($all_barangays as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= $brgy_filter === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <!-- Keyword Search -->
                    <form method="GET" action="admin_mydc.php" class="relative w-full sm:w-auto sm:flex-1 md:w-56 min-w-[150px]">
                        <?php if ($status_filter !== 'All' && !empty($status_filter)): ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                        <?php endif; ?>
                        <?php if (!empty($brgy_filter)): ?>
                            <input type="hidden" name="barangay" value="<?= htmlspecialchars($brgy_filter) ?>">
                        <?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search member, position..." class="w-full pl-8 pr-3 py-1.5 bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 placeholder-slate-400 dark:placeholder-zinc-500 text-xs font-semibold rounded-xl focus:outline-none transition-colors border-0">
                    </form>

                    <!-- Action Buttons Cluster (Clear + Print + Register Member) -->
                    <div class="flex items-center gap-1.5 w-full sm:w-auto ml-auto sm:ml-0 flex-shrink-0">
                        <?php if (!empty($search_query) || ($status_filter !== 'All' && !empty($status_filter)) || !empty($brgy_filter)): ?>
                            <a href="admin_mydc.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Filters">
                                <i class="ph-bold ph-x text-xs"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Print Roster Button -->
                        <button type="button" onclick="window.print()" class="no-print hidden sm:flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 font-bold h-[30px] px-2.5 rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Print Member Roster">
                            <i class="ph-bold ph-printer text-sm mr-1"></i>
                            <span>Print</span>
                        </button>

                        <!-- Register Member Primary Action -->
                        <button type="button" onclick="openMemberModal()" class="btn-yellow-glow-r flex-1 sm:flex-none px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center justify-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group cursor-pointer border-0 whitespace-nowrap">
                            <i class="ph-bold ph-user-plus text-xs transition-transform duration-300 group-hover:scale-110 relative z-10"></i>
                            <span class="relative z-10">Register Member</span>
                        </button>
                    </div>
                </div>

            </div>

            <!-- CONSOLIDATED FULL-WIDTH TABLE CARD (Fills remaining height) -->
            <div class="glass-card card-green-glow-bl rounded-2xl md:rounded-3xl overflow-hidden flex flex-col flex-1 min-h-[350px] lg:min-h-0 transition-colors">
                
                <!-- Table Header Bar -->
                <div class="p-3 md:p-3.5 bg-white/50 dark:bg-zinc-900/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 border-b border-slate-100 dark:border-zinc-800/80 transition-colors flex-shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-base font-bold flex-shrink-0">
                            <i class="ph-fill ph-identification-card"></i>
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-xs md:text-sm font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-1.5 truncate">
                                <span>Municipal Youth Development Council (MYDC) Roster</span>
                            </h2>
                            <p class="text-[11px] md:text-xs text-slate-500 dark:text-zinc-400 font-medium truncate hidden sm:block">Official registry of sector representatives, council leadership, and affiliated barangays</p>
                        </div>
                    </div>
                    <span class="text-xs font-bold text-slate-700 dark:text-zinc-300 bg-slate-100 dark:bg-zinc-800 px-2.5 py-0.5 rounded-lg flex-shrink-0">
                        <?= number_format(count($members)) ?> of <?= number_format($total_members) ?> Records
                    </span>
                </div>

                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[850px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[11px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider bg-slate-100/90 dark:bg-zinc-800/90 backdrop-blur-sm transition-colors border-b border-slate-100 dark:border-zinc-800/80">
                                <th class="py-2.5 px-3 sm:px-4 md:px-5">Member Profile</th>
                                <th class="py-2.5 px-3 sm:px-4 md:px-5">Council Position & Role</th>
                                <th class="py-2.5 px-3 sm:px-4 md:px-5">Affiliation & Jurisdiction</th>
                                <th class="py-2.5 px-3 sm:px-4 md:px-5 text-center">Status</th>
                                <th class="py-2.5 px-3 sm:px-4 md:px-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs md:text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($members)): ?>
                                <?php foreach ($members as $m): 
                                     $is_active = ($m['status'] ?? 'Active') === 'Active';
                                ?>
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-800/40 transition-colors group">
                                        
                                        <!-- Profile -->
                                        <td class="py-2.5 px-3 sm:px-4 md:px-5 align-middle">
                                            <div class="flex items-center gap-3">
                                                <?php if (!empty($m['photo_path']) && file_exists($m['photo_path'])): ?>
                                                    <img src="<?= htmlspecialchars($m['photo_path']) ?>" alt="" class="w-9 h-9 rounded-xl object-cover bg-slate-100 dark:bg-zinc-800 flex-shrink-0 group-hover:scale-105 transition-transform">
                                                <?php else: ?>
                                                    <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 font-extrabold text-sm flex items-center justify-center flex-shrink-0 group-hover:scale-105 transition-transform">
                                                        <?= strtoupper(substr($m['full_name'], 0, 1)) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="min-w-0">
                                                    <p class="font-bold text-slate-900 dark:text-white text-xs md:text-sm leading-tight truncate group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors">
                                                        <?= htmlspecialchars($m['full_name']) ?>
                                                    </p>
                                                    <p class="text-[10px] md:text-xs font-medium text-slate-400 dark:text-zinc-500 mt-0.5">
                                                        Registered <?= date('M Y', strtotime($m['created_at'])) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Position -->
                                        <td class="py-2.5 px-3 sm:px-4 md:px-5 align-middle whitespace-nowrap">
                                            <span class="inline-flex items-center text-[10px] md:text-xs font-bold text-blue-700 dark:text-blue-400 bg-blue-50 dark:bg-blue-500/10 px-2.5 py-1 rounded-lg uppercase tracking-wider">
                                                <?= htmlspecialchars($m['position']) ?>
                                            </span>
                                        </td>

                                        <!-- Organization & Barangay -->
                                        <td class="py-2.5 px-3 sm:px-4 md:px-5 align-middle whitespace-nowrap">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <span class="inline-flex items-center text-[10px] md:text-xs font-bold text-slate-700 dark:text-zinc-300 bg-slate-100 dark:bg-zinc-800 px-2.5 py-0.5 rounded-lg">
                                                    <i class="ph-fill ph-buildings mr-1 text-slate-400"></i> <?= htmlspecialchars($m['organization']) ?>
                                                </span>
                                                <span class="inline-flex items-center text-[10px] md:text-xs font-bold text-slate-700 dark:text-zinc-300 bg-slate-100 dark:bg-zinc-800 px-2.5 py-0.5 rounded-lg">
                                                    <i class="ph-fill ph-map-pin mr-1 text-green-600 dark:text-green-500"></i> Brgy. <?= htmlspecialchars($m['barangay']) ?>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Status -->
                                        <td class="py-2.5 px-3 sm:px-4 md:px-5 text-center whitespace-nowrap align-middle">
                                            <?php if ($is_active): ?>
                                                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-500/10 px-2.5 py-1 rounded-lg">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 bg-slate-100 dark:bg-zinc-800 px-2.5 py-1 rounded-lg">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Actions -->
                                        <td class="py-2.5 px-3 sm:px-4 md:px-5 text-right whitespace-nowrap align-middle">
                                            <div class="inline-flex items-center gap-1 justify-end">
                                                <button type="button" onclick="editMember(<?= htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 inline-flex items-center justify-center text-slate-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-slate-100 dark:hover:bg-zinc-800 rounded-lg transition-colors tap-effect" title="Edit Member">
                                                    <i class="ph-bold ph-pencil-simple text-xs"></i>
                                                </button>

                                                <a href="?delete=<?= $m['id'] ?>" onclick="return confirm('Permanently remove this member from the MYDC registry?')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-all tap-effect" title="Delete Member">
                                                    <i class="ph-bold ph-trash text-xs"></i>
                                                </a>
                                            </div>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <div class="w-24 h-24 md:w-28 md:h-28 mx-auto mb-2 flex items-center justify-center">
                                            <img src="mascot_question.png" alt="No Members" class="max-h-full max-w-full object-contain pointer-events-none hover:scale-105 transition-transform duration-300">
                                        </div>
                                        <p class="font-bold text-xs md:text-sm text-slate-600 dark:text-zinc-300">No council members found matching parameters.</p>
                                        <p class="text-xs text-slate-400 dark:text-zinc-500 mt-0.5">Try clearing filters or click "Register Member" to add council representatives.</p>
                                        <?php if (!empty($search_query) || !empty($brgy_filter) || ($status_filter !== 'All' && !empty($status_filter))): ?>
                                            <a href="admin_mydc.php" class="mt-3 inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
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
                    <span class="text-[11px]">Showing <strong class="text-slate-800 dark:text-zinc-200 font-bold"><?= count($members) ?></strong> of <?= $total_members ?> council members</span>
                    <span class="text-[10px] text-slate-400 dark:text-zinc-500">TALA OS &bull; Municipal Youth Development Council</span>
                </div>
            </div>

        </div>
    </main>

    <!-- REGISTER / EDIT MEMBER MODAL (#memberModal) -->
    <div id="memberModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-3 sm:p-4 md:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeMemberModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] card-green-glow-bl overflow-hidden max-w-lg w-full p-4 sm:p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border-0 max-h-[90vh] overflow-y-auto custom-scrollbar" id="memberModalContent">
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-4 border-b border-slate-100 dark:border-zinc-800 pb-3">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-bold ph-user-plus"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight truncate" id="modalTitle">Register Council Member</h3>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono mt-0.5 truncate" id="modalSubtitle">MYDC Official Roster</p>
                    </div>
                </div>
                <button type="button" onclick="closeMemberModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect flex-shrink-0" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Form Body -->
            <form action="admin_mydc.php" method="POST" enctype="multipart/form-data" class="space-y-3.5" id="memberForm">
                <input type="hidden" name="save_member" value="1">
                <input type="hidden" name="member_id" id="modal_member_id" value="">

                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Full Name *</label>
                    <input type="text" name="full_name" id="modal_full_name" required placeholder="e.g. Juan Dela Cruz" class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold focus:outline-none transition-all border-0">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Council Position *</label>
                        <input type="text" name="position" id="modal_position" required placeholder="e.g. Youth Representative" class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold focus:outline-none transition-all border-0">
                    </div>

                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Barangay Jurisdiction *</label>
                        <select name="barangay" id="modal_barangay" required class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold focus:outline-none transition-all cursor-pointer border-0">
                            <option value="" disabled selected>Select Barangay...</option>
                            <?php foreach ($all_barangays as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Organization / Sector *</label>
                        <input type="text" name="organization" id="modal_organization" required placeholder="e.g. Academic Youth Org" class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold focus:outline-none transition-all border-0">
                    </div>

                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Membership Status</label>
                        <select name="status" id="modal_status" class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold focus:outline-none transition-all cursor-pointer border-0">
                            <option value="Active">Active Standing</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Member Photo (Optional)</label>
                    <input type="file" name="photo" accept="image/*" class="w-full text-xs text-slate-600 dark:text-zinc-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#15803d] file:text-white hover:file:bg-[#166534] bg-slate-100 dark:bg-zinc-800 p-2.5 rounded-xl cursor-pointer border-0">
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800 mt-2 flex-shrink-0">
                    <button type="button" onclick="closeMemberModal()" class="px-4 py-2 bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm hover:bg-slate-200 dark:hover:bg-zinc-700 transition-all tap-effect">
                        Cancel
                    </button>
                    <button type="submit" id="modalSubmitBtn" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2 px-5 rounded-xl text-xs md:text-sm transition-all tap-effect flex items-center gap-1.5 shadow-none">
                        <span class="relative z-10">Save Member</span>
                        <i class="ph-bold ph-check text-sm relative z-10"></i>
                    </button>
                </div>
            </form>
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

        // Member Modal Logic (Spring Pop-up Effect)
        const memberModal = document.getElementById('memberModal');
        const memberModalContent = document.getElementById('memberModalContent');

        function openMemberModal() {
            document.getElementById('memberForm').reset();
            document.getElementById('modalTitle').innerText = 'Register Council Member';
            document.getElementById('modalSubtitle').innerText = 'MYDC Official Roster';
            document.getElementById('modal_member_id').value = '';
            document.getElementById('modalSubmitBtn').innerHTML = '<span class="relative z-10">Save Member</span><i class="ph-bold ph-check text-sm relative z-10"></i>';

            if (memberModal && memberModalContent) {
                memberModal.classList.remove('hidden');
                memberModal.classList.add('flex');
                requestAnimationFrame(() => {
                    memberModal.classList.remove('opacity-0');
                    memberModal.classList.add('opacity-100');
                    memberModalContent.classList.remove('scale-95', 'opacity-0');
                    memberModalContent.classList.add('scale-100', 'opacity-100');
                });
            }
        }

        function editMember(member) {
            document.getElementById('modalTitle').innerText = 'Update Council Member';
            document.getElementById('modalSubtitle').innerText = 'Edit Profile & Affiliation';
            document.getElementById('modal_member_id').value = member.id;
            document.getElementById('modal_full_name').value = member.full_name;
            document.getElementById('modal_position').value = member.position;
            document.getElementById('modal_organization').value = member.organization;
            document.getElementById('modal_barangay').value = member.barangay;
            document.getElementById('modal_status').value = member.status || 'Active';
            document.getElementById('modalSubmitBtn').innerHTML = '<span class="relative z-10">Update Member</span><i class="ph-bold ph-pencil-simple text-sm relative z-10"></i>';

            if (memberModal && memberModalContent) {
                memberModal.classList.remove('hidden');
                memberModal.classList.add('flex');
                requestAnimationFrame(() => {
                    memberModal.classList.remove('opacity-0');
                    memberModal.classList.add('opacity-100');
                    memberModalContent.classList.remove('scale-95', 'opacity-0');
                    memberModalContent.classList.add('scale-100', 'opacity-100');
                });
            }
        }

        function closeMemberModal() {
            if (memberModal && memberModalContent) {
                memberModal.classList.remove('opacity-100');
                memberModal.classList.add('opacity-0');
                memberModalContent.classList.remove('scale-100', 'opacity-100');
                memberModalContent.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    memberModal.classList.remove('flex');
                    memberModal.classList.add('hidden');
                }, 200);
            }
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && memberModal && !memberModal.classList.contains('hidden')) {
                closeMemberModal();
            }
        });
    </script>

    <!-- Genesis AI Assistant Widget -->
    <?php include_once 'genesis_widget.php'; ?>
</body>
</html>