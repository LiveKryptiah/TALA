<?php
// admin_governance.php - Public Governance Document Manager (TALA OS Standard)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Security Check: Strictly require admin or mayor login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'mayor') {
    header("Location: login.php");
    exit();
}

$base_dir = 'uploads/governance/';
if (!is_dir($base_dir)) {
    mkdir($base_dir, 0777, true);
}

// Helpers
function format_file_size($bytes) {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 0) . ' KB';
    return $bytes . ' B';
}

function get_dir_size($dir) {
    $size = 0;
    foreach (glob(rtrim($dir, '/') . '/*', GLOB_NOSORT) as $each) {
        $size += is_file($each) ? filesize($each) : get_dir_size($each);
    }
    return $size;
}

function buildUrl($params = []) {
    $current = $_GET;
    foreach ($params as $k => $v) {
        if ($v === null || $v === '' || $v === 'All') {
            unset($current[$k]);
        } else {
            $current[$k] = $v;
        }
    }
    return 'admin_governance.php' . (!empty($current) ? '?' . http_build_query($current) : '');
}

$message = '';
$msg_type = 'success';

// Handle Folder Creation
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['create_folder'])) {
    $folder_name = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $_POST['folder_name'] ?? '');
    $folder_name = trim($folder_name);
    if (!empty($folder_name)) {
        $new_dir = $base_dir . $folder_name;
        if (!is_dir($new_dir)) {
            mkdir($new_dir, 0777, true);
            $message = "Governance folder '{$folder_name}' created successfully.";
            $msg_type = 'success';
        } else {
            $message = "Folder '{$folder_name}' already exists.";
            $msg_type = 'warning';
        }
    } else {
        $message = "Please provide a valid folder name.";
        $msg_type = 'warning';
    }
}

// Handle File Upload
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['upload_file'])) {
    $folder = trim($_POST['target_folder'] ?? '');
    $target_dir = $base_dir . $folder . '/';
    
    if (!empty($folder) && is_dir($target_dir)) {
        if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
            $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($_FILES['document']['name']));
            $target_file = $target_dir . time() . '_' . $filename;
            
            if (move_uploaded_file($_FILES['document']['tmp_name'], $target_file)) {
                $clean_original = preg_replace('/^\d+_/', '', $filename);
                $message = "File '{$clean_original}' published to folder '{$folder}'!";
                $msg_type = 'success';
            } else {
                $message = "Failed to upload file. Please check folder write permissions.";
                $msg_type = 'error';
            }
        } else {
            $message = "Please select a valid document to upload.";
            $msg_type = 'warning';
        }
    } else {
        $message = "Target governance folder does not exist.";
        $msg_type = 'error';
    }
}

// Handle Deletion (File or Empty Folder)
if (isset($_GET['delete'])) {
    $target = base64_decode($_GET['delete']);
    $del_msg = '';
    $del_type = 'success';

    // Security check against directory traversal
    if (strpos($target, 'uploads/governance/') === 0 && file_exists($target)) {
        if (is_dir($target)) {
            $files_in_folder = array_filter(glob($target . '/*'), 'is_file');
            if (empty($files_in_folder)) {
                @rmdir($target);
                $del_msg = "Empty directory deleted successfully.";
            } else {
                $del_msg = "Cannot delete folder with existing files inside. Please remove files first.";
                $del_type = 'warning';
            }
        } else {
            @unlink($target);
            $del_msg = "Document removed permanently from governance repository.";
        }
    }
    header("Location: admin_governance.php?msg=" . urlencode($del_msg) . "&type=" . urlencode($del_type));
    exit();
}

if (!empty($_GET['msg'])) {
    $message = $_GET['msg'];
    $msg_type = $_GET['type'] ?? 'success';
}

// Fetch all folders
$folders = array_filter(glob($base_dir . '*'), 'is_dir');
sort($folders);

// Calculate total public files & storage size
$total_files = 0;
foreach ($folders as $f) {
    $total_files += count(array_filter(glob($f . '/*'), 'is_file'));
}
$total_storage = format_file_size(get_dir_size($base_dir));

// Filter & Search Handling
$search = trim($_GET['search'] ?? '');
$filter_folder = trim($_GET['folder'] ?? 'All');

$display_folders = [];
$total_displayed_files = 0;

foreach ($folders as $folder_path) {
    $fname = basename($folder_path);
    if (!empty($filter_folder) && $filter_folder !== 'All' && $fname !== $filter_folder) {
        continue;
    }
    
    $files = array_filter(glob($folder_path . '/*'), 'is_file');
    // Sort files newest first
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    if (!empty($search)) {
        $matching_files = [];
        $folder_matches = (stripos($fname, $search) !== false);
        foreach ($files as $f) {
            $clean_f = preg_replace('/^\d+_/', '', basename($f));
            if ($folder_matches || stripos($clean_f, $search) !== false) {
                $matching_files[] = $f;
            }
        }
        if ($folder_matches || !empty($matching_files)) {
            $display_folders[$folder_path] = $matching_files;
            $total_displayed_files += count($matching_files);
        }
    } else {
        $display_folders[$folder_path] = $files;
        $total_displayed_files += count($files);
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Public Governance Repository | TALA OS</title>
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
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <div>
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                            Governance Repository
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 font-medium">Public document repository, executive orders, ordinances & files</p>
                    </div>
                </div>
                
                <!-- Section Cards (3 KPI badges) & Dark Mode Toggle -->
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Total Folders -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-folder text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Folders</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format(count($folders)) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Public Files -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-files text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Public Files</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($total_files) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Storage Used -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-hard-drive text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Storage Used</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= $total_storage ?></span>
                        </div>
                    </div>

                    <!-- Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[36px] h-[36px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
                        <i id="headerThemeIcon" class="ph-bold ph-moon text-base sm:text-lg transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- NOTIFICATIONS BANNER -->
            <?php if (!empty($message)): ?>
                <?php 
                    $alertColor = 'bg-green-50/85 dark:bg-green-500/10 text-green-700 dark:text-green-400';
                    $alertIcon = 'ph-check-circle';
                    if ($msg_type === 'error' || $msg_type === 'danger') {
                        $alertColor = 'bg-red-50/85 dark:bg-red-500/10 text-red-700 dark:text-red-400';
                        $alertIcon = 'ph-warning-circle';
                    } elseif ($msg_type === 'warning') {
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
                
                <!-- Quick Folder Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full xl:w-auto custom-scrollbar py-0.5 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        [
                            'val' => 'All',
                            'label' => 'All Folders',
                            'count' => $total_files,
                            'active' => ($filter_folder === 'All' || empty($filter_folder))
                        ]
                    ];
                    foreach ($folders as $f) {
                        $fn = basename($f);
                        $f_count = count(array_filter(glob($f . '/*'), 'is_file'));
                        $filter_tabs[] = [
                            'val' => $fn,
                            'label' => $fn,
                            'count' => $f_count,
                            'active' => ($filter_folder === $fn)
                        ];
                    }

                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        
                        $tabUrl = buildUrl(['folder' => ($tab['val'] === 'All' ? '' : $tab['val'])]);
                    ?>
                        <a href="<?= $tabUrl ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span class="relative z-10"><?= htmlspecialchars($tab['label']) ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 dark:bg-black/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?> relative z-10"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Dropdown, Search, Print & Action Buttons -->
                <form method="GET" action="admin_governance.php" class="flex flex-wrap items-center gap-2 w-full xl:w-auto">
                    <?php if (!empty($filter_folder) && $filter_folder !== 'All'): ?>
                        <input type="hidden" name="folder" value="<?= htmlspecialchars($filter_folder) ?>">
                    <?php endif; ?>

                    <!-- Keyword Search -->
                    <div class="relative flex-1 sm:w-60 min-w-[170px]">
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search documents..." class="w-full pl-8 pr-3 py-1.5 bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 placeholder-slate-400 dark:placeholder-zinc-500 text-xs font-semibold rounded-xl focus:outline-none transition-colors border-0">
                    </div>

                    <!-- Direct Folder Dropdown Selector -->
                    <div class="w-full sm:w-44">
                        <select name="folder" onchange="this.form.submit()" class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="">All Folders (<?= count($folders) ?>)</option>
                            <?php foreach($folders as $f): 
                                $fn = basename($f);
                            ?>
                                <option value="<?= htmlspecialchars($fn) ?>" <?= $filter_folder === $fn ? 'selected' : '' ?>><?= htmlspecialchars($fn) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Submit Button -->
                    <button type="submit" class="bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 font-bold py-1.5 px-3 rounded-xl transition-all text-xs tap-effect flex items-center gap-1">
                        <i class="ph-bold ph-funnel text-xs"></i>
                        <span>Filter</span>
                    </button>

                    <!-- Clear Filter Reset -->
                    <?php if (!empty($search) || (!empty($filter_folder) && $filter_folder !== 'All')): ?>
                        <a href="admin_governance.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 font-bold h-8 w-8 rounded-xl text-xs transition-all tap-effect" title="Clear Filters">
                            <i class="ph-bold ph-x"></i>
                        </a>
                    <?php endif; ?>

                    <!-- Print Inventory Button -->
                    <button type="button" onclick="window.print()" class="no-print hidden sm:flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 font-bold h-8 px-2.5 rounded-xl text-xs transition-all tap-effect" title="Print Document Inventory">
                        <i class="ph-bold ph-printer text-sm mr-1"></i>
                        <span>Print</span>
                    </button>

                    <!-- Create New Folder Modal Trigger -->
                    <button type="button" onclick="openFolderModal()" class="bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 font-bold py-1.5 px-3 rounded-xl transition-all text-xs tap-effect flex items-center gap-1.5 whitespace-nowrap">
                        <i class="ph-bold ph-folder-plus text-sm text-green-600 dark:text-green-500"></i>
                        <span>New Folder</span>
                    </button>

                    <!-- Upload Document Primary Button -->
                    <button type="button" onclick="openUploadModal()" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-1.5 px-3.5 rounded-xl transition-all text-xs tap-effect flex items-center gap-1.5 whitespace-nowrap">
                        <i class="ph-bold ph-upload-simple text-sm relative z-10"></i>
                        <span class="relative z-10">Upload Document</span>
                    </button>
                </form>

            </div>

            <!-- CONSOLIDATED FULL-WIDTH REPOSITORY CARD (Fills remaining height) -->
            <div class="glass-card card-green-glow-bl rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Card Header -->
                <div class="p-3 md:p-3.5 bg-white/50 dark:bg-zinc-900/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 border-b border-slate-100 dark:border-zinc-800/80 transition-colors flex-shrink-0">
                    <div>
                        <h2 class="text-xs md:text-sm font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-1.5">
                            <i class="ph-bold ph-folder-notch-open text-green-600 dark:text-green-500"></i>
                            <span>Public Governance Repository</span>
                        </h2>
                        <p class="text-[11px] md:text-xs text-slate-500 dark:text-zinc-400 font-medium">Executive orders, municipal memoranda, ordinances, and public transparency files</p>
                    </div>
                    <span class="text-xs font-bold text-slate-700 dark:text-zinc-300 bg-slate-100 dark:bg-zinc-800 px-2.5 py-0.5 rounded-lg">
                        <?= count($display_folders) ?> Folders &bull; <?= $total_displayed_files ?> Files
                    </span>
                </div>

                <!-- Explorer Scroll Area -->
                <div class="flex-1 overflow-y-auto custom-scrollbar p-3 md:p-4 space-y-3.5 min-h-0">
                    <?php if (empty($display_folders)): ?>
                        <div class="py-14 px-4 text-center text-slate-400 dark:text-zinc-500 select-none">
                            <div class="w-24 h-24 md:w-28 md:h-28 mx-auto mb-2 flex items-center justify-center">
                                <img src="mascot_question.png" alt="No Documents" class="max-h-full max-w-full object-contain pointer-events-none hover:scale-105 transition-transform duration-300">
                            </div>
                            <p class="font-bold text-xs md:text-sm text-slate-600 dark:text-zinc-300">No governance folders or documents found.</p>
                            <p class="text-xs text-slate-400 dark:text-zinc-500 mt-0.5">Use "New Folder" to create categories or "Upload Document" to publish files.</p>
                            <div class="mt-4 flex items-center justify-center gap-2">
                                <button type="button" onclick="openFolderModal()" class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 text-xs font-bold transition-all tap-effect">
                                    Create First Folder
                                </button>
                                <button type="button" onclick="openUploadModal()" class="btn-yellow-glow-r px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white text-xs font-bold transition-all tap-effect">
                                    <span class="relative z-10">Upload Document</span>
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($display_folders as $folder_path => $files): 
                                $folder_name = basename($folder_path);
                            ?>
                                <div class="bg-slate-50/70 dark:bg-zinc-900/50 rounded-2xl p-3.5 md:p-4 space-y-3 transition-colors">
                                    
                                    <!-- Folder Header Row -->
                                    <div class="flex items-center justify-between gap-2 border-b border-slate-200/60 dark:border-zinc-800/80 pb-2.5">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-lg font-bold flex-shrink-0">
                                                <i class="ph-fill ph-folder"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <span class="font-black text-xs md:text-sm text-slate-900 dark:text-white truncate block leading-tight">
                                                    <?= htmlspecialchars($folder_name) ?>
                                                </span>
                                                <span class="text-[10px] text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-wider">
                                                    <?= count($files) ?> <?= count($files) === 1 ? 'Document' : 'Documents' ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center gap-1.5 flex-shrink-0">
                                            <button type="button" onclick="openUploadModalFor('<?= htmlspecialchars(addslashes($folder_name)) ?>')" class="text-xs font-bold text-slate-700 dark:text-zinc-200 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 px-3 py-1.5 rounded-xl transition-all tap-effect flex items-center gap-1">
                                                <i class="ph-bold ph-plus text-xs text-green-600 dark:text-green-500"></i>
                                                <span>Upload Here</span>
                                            </button>

                                            <?php if (empty($files)): ?>
                                                <a href="?delete=<?= base64_encode($folder_path) ?>" onclick="return confirm('Permanently remove this empty folder?')" class="text-xs font-bold text-rose-600 dark:text-rose-400 bg-rose-50 hover:bg-rose-100 dark:bg-rose-500/10 dark:hover:bg-rose-500/20 px-3 py-1.5 rounded-xl transition-all tap-effect whitespace-nowrap">
                                                    Delete Folder
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Files inside Folder -->
                                    <?php if (empty($files)): ?>
                                        <p class="text-xs text-slate-400 dark:text-zinc-500 italic py-2 pl-1">This folder has no files yet. Click "Upload Here" to publish public documents.</p>
                                    <?php else: ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2.5">
                                            <?php foreach ($files as $file): 
                                                $filename = basename($file);
                                                $clean_name = preg_replace('/^\d+_/', '', $filename);
                                                $filesize = file_exists($file) ? format_file_size(filesize($file)) : '';
                                                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                                
                                                $iconClass = 'ph-file text-slate-400';
                                                $iconBg = 'bg-slate-100 dark:bg-zinc-700/60 text-slate-500';
                                                if ($ext === 'pdf') {
                                                    $iconClass = 'ph-file-pdf text-rose-500';
                                                    $iconBg = 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400';
                                                } elseif (in_array($ext, ['doc', 'docx', 'txt', 'rtf'])) {
                                                    $iconClass = 'ph-file-doc text-blue-500';
                                                    $iconBg = 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400';
                                                } elseif (in_array($ext, ['xls', 'xlsx', 'csv'])) {
                                                    $iconClass = 'ph-file-xls text-emerald-500';
                                                    $iconBg = 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400';
                                                } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'])) {
                                                    $iconClass = 'ph-file-image text-purple-500';
                                                    $iconBg = 'bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400';
                                                } elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
                                                    $iconClass = 'ph-file-zip text-amber-500';
                                                    $iconBg = 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400';
                                                }
                                            ?>
                                                <div class="flex items-center justify-between gap-3 p-3 rounded-xl bg-white dark:bg-zinc-800/80 hover:bg-slate-100/80 dark:hover:bg-zinc-800 transition-all group">
                                                    <div class="flex items-center gap-3 min-w-0">
                                                        <div class="w-9 h-9 rounded-xl <?= $iconBg ?> flex items-center justify-center font-bold flex-shrink-0 group-hover:scale-105 transition-transform">
                                                            <i class="ph-fill <?= $iconClass ?> text-lg"></i>
                                                        </div>
                                                        <div class="min-w-0">
                                                            <a href="<?= htmlspecialchars($file) ?>" target="_blank" class="text-xs md:text-sm font-bold text-slate-900 dark:text-white hover:text-green-600 dark:hover:text-green-400 truncate block transition-colors tap-effect" title="<?= htmlspecialchars($clean_name) ?>">
                                                                <?= htmlspecialchars($clean_name) ?>
                                                            </a>
                                                            <div class="flex items-center gap-2 text-[10px] text-slate-400 dark:text-zinc-500 font-semibold uppercase tracking-wider mt-0.5">
                                                                <span><?= strtoupper($ext) ?></span>
                                                                <?php if ($filesize): ?>
                                                                    <span>&bull;</span>
                                                                    <span><?= $filesize ?></span>
                                                                <?php endif; ?>
                                                                <span>&bull;</span>
                                                                <span><?= date('M j, Y', filemtime($file)) ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="flex items-center gap-1 flex-shrink-0">
                                                        <a href="<?= htmlspecialchars($file) ?>" target="_blank" class="w-8 h-8 inline-flex items-center justify-center rounded-xl bg-slate-100 dark:bg-zinc-700/60 text-slate-600 dark:text-zinc-300 hover:bg-green-100 hover:text-green-700 dark:hover:bg-green-900/40 dark:hover:text-green-400 transition-all tap-effect" title="Open File in New Tab">
                                                            <i class="ph-bold ph-arrow-square-out text-sm"></i>
                                                        </a>
                                                        <a href="<?= htmlspecialchars($file) ?>" download class="w-8 h-8 inline-flex items-center justify-center rounded-xl bg-slate-100 dark:bg-zinc-700/60 text-slate-600 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700 transition-all tap-effect" title="Download File">
                                                            <i class="ph-bold ph-download-simple text-sm"></i>
                                                        </a>
                                                        <a href="?delete=<?= base64_encode($file) ?>" onclick="return confirm('Permanently remove this public document? This action cannot be undone.')" class="w-8 h-8 inline-flex items-center justify-center rounded-xl bg-slate-100 dark:bg-zinc-700/60 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400 transition-all tap-effect" title="Delete Document">
                                                            <i class="ph-bold ph-trash text-sm"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Table Footer Info -->
                <div class="px-4 py-2.5 bg-white/40 dark:bg-zinc-900/40 flex justify-between items-center text-xs text-slate-500 dark:text-zinc-400 font-medium flex-shrink-0 border-t border-slate-100 dark:border-zinc-800/80">
                    <span class="text-[11px]">Showing <strong class="text-slate-800 dark:text-zinc-200 font-bold"><?= count($display_folders) ?></strong> folders &bull; <strong class="text-slate-800 dark:text-zinc-200 font-bold"><?= $total_displayed_files ?></strong> files</span>
                    <span class="text-[10px] text-slate-400 dark:text-zinc-500">Public Transparency & Governance &bull; TALA OS</span>
                </div>
            </div>

        </div>
    </main>

    <!-- CREATE FOLDER MODAL (#folderModal) -->
    <div id="folderModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeFolderModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] card-green-glow-bl overflow-hidden max-w-md w-full p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border-0 max-h-[90vh] overflow-y-auto custom-scrollbar" id="folderModalContent">
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-4 border-b border-slate-100 dark:border-zinc-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-bold ph-folder-plus"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight">Create Governance Folder</h3>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono mt-0.5">Repository Directory</p>
                    </div>
                </div>
                <button type="button" onclick="closeFolderModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Form Body -->
            <form action="admin_governance.php" method="POST" class="space-y-4">
                <input type="hidden" name="create_folder" value="1">

                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1.5">Folder Name *</label>
                    <input type="text" name="folder_name" placeholder="e.g. Executive Orders 2026" required class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold focus:outline-none transition-all border-0">
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button" onclick="closeFolderModal()" class="px-4 py-2 bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm hover:bg-slate-200 dark:hover:bg-zinc-700 transition-all tap-effect">
                        Cancel
                    </button>
                    <button type="submit" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2 px-5 rounded-xl text-xs md:text-sm transition-all tap-effect flex items-center gap-1.5 shadow-none">
                        <span class="relative z-10">Create Folder</span>
                        <i class="ph-bold ph-check text-sm relative z-10"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- UPLOAD DOCUMENT MODAL (#uploadModal) -->
    <div id="uploadModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeUploadModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] card-green-glow-bl overflow-hidden max-w-lg w-full p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border-0 max-h-[90vh] overflow-y-auto custom-scrollbar" id="uploadModalContent">
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-4 border-b border-slate-100 dark:border-zinc-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-bold ph-upload-simple"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight">Publish Public Document</h3>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono mt-0.5">Transparency File Upload</p>
                    </div>
                </div>
                <button type="button" onclick="closeUploadModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Form Body -->
            <form action="admin_governance.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="upload_file" value="1">

                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1.5">Target Governance Folder *</label>
                    <select name="target_folder" id="modal_target_folder" required class="w-full bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-white rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold focus:outline-none transition-all cursor-pointer border-0">
                        <option value="" disabled selected>Select target directory...</option>
                        <?php foreach($folders as $folder): 
                            $f_name = basename($folder);
                        ?>
                            <option value="<?= htmlspecialchars($f_name) ?>"><?= htmlspecialchars($f_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1.5">Select Document File *</label>
                    <input type="file" name="document" required class="w-full text-xs text-slate-600 dark:text-zinc-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-[#15803d] file:text-white hover:file:bg-[#166534] bg-slate-100 dark:bg-zinc-800 p-2.5 rounded-xl cursor-pointer border-0">
                    <p class="text-[10px] text-slate-400 dark:text-zinc-500 mt-1">Supported formats: PDF, Word (DOCX/DOC), Excel (XLSX/CSV), Text, and Images.</p>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button" onclick="closeUploadModal()" class="px-4 py-2 bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm hover:bg-slate-200 dark:hover:bg-zinc-700 transition-all tap-effect">
                        Cancel
                    </button>
                    <button type="submit" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2 px-5 rounded-xl text-xs md:text-sm transition-all tap-effect flex items-center gap-1.5 shadow-none">
                        <span class="relative z-10">Publish Document</span>
                        <i class="ph-bold ph-paper-plane-tilt text-sm relative z-10"></i>
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

        // Folder Modal Logic
        const folderModal = document.getElementById('folderModal');
        const folderModalContent = document.getElementById('folderModalContent');

        function openFolderModal() {
            if (folderModal && folderModalContent) {
                folderModal.classList.remove('hidden');
                folderModal.classList.add('flex');
                requestAnimationFrame(() => {
                    folderModal.classList.remove('opacity-0');
                    folderModal.classList.add('opacity-100');
                    folderModalContent.classList.remove('scale-95', 'opacity-0');
                    folderModalContent.classList.add('scale-100', 'opacity-100');
                });
            }
        }

        function closeFolderModal() {
            if (folderModal && folderModalContent) {
                folderModal.classList.remove('opacity-100');
                folderModal.classList.add('opacity-0');
                folderModalContent.classList.remove('scale-100', 'opacity-100');
                folderModalContent.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    folderModal.classList.remove('flex');
                    folderModal.classList.add('hidden');
                }, 200);
            }
        }

        // Upload Modal Logic
        const uploadModal = document.getElementById('uploadModal');
        const uploadModalContent = document.getElementById('uploadModalContent');

        function openUploadModal() {
            if (uploadModal && uploadModalContent) {
                uploadModal.classList.remove('hidden');
                uploadModal.classList.add('flex');
                requestAnimationFrame(() => {
                    uploadModal.classList.remove('opacity-0');
                    uploadModal.classList.add('opacity-100');
                    uploadModalContent.classList.remove('scale-95', 'opacity-0');
                    uploadModalContent.classList.add('scale-100', 'opacity-100');
                });
            }
        }

        function openUploadModalFor(folderName) {
            const selectEl = document.getElementById('modal_target_folder');
            if (selectEl) {
                selectEl.value = folderName;
            }
            openUploadModal();
        }

        function closeUploadModal() {
            if (uploadModal && uploadModalContent) {
                uploadModal.classList.remove('opacity-100');
                uploadModal.classList.add('opacity-0');
                uploadModalContent.classList.remove('scale-100', 'opacity-100');
                uploadModalContent.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    uploadModal.classList.remove('flex');
                    uploadModal.classList.add('hidden');
                }, 200);
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

        // Close on ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (folderModal && !folderModal.classList.contains('hidden')) closeFolderModal();
                if (uploadModal && !uploadModal.classList.contains('hidden')) closeUploadModal();
            }
        });
    </script>

    <!-- Genesis AI Assistant Widget -->
    <?php include_once 'genesis_widget.php'; ?>
</body>
</html>