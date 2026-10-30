<?php
// manage_popup.php - Pop-Up Notice Management Console (TALA OS Standard - Reference: manage_announcements.php)
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

$isAdmin = true;

// Auto-upgrade schema if necessary
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `popup_notification` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `is_active` TINYINT(1) DEFAULT 0,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT NOT NULL,
        `button_text` VARCHAR(100) NOT NULL,
        `button_link` VARCHAR(255) NOT NULL,
        `image_path` VARCHAR(255) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    $cols = $pdo->query("SHOW COLUMNS FROM `popup_notification`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('created_at', $cols)) {
        $pdo->exec("ALTER TABLE `popup_notification` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }
    if (!in_array('updated_at', $cols)) {
        $pdo->exec("ALTER TABLE `popup_notification` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
    try {
        $pdo->exec("ALTER TABLE `popup_notification` MODIFY COLUMN `id` INT AUTO_INCREMENT");
    } catch (Exception $ex) {}
} catch (Exception $e) {}



// --- CRUD: CREATE & UPDATE ---
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && (isset($_POST['save_popup']) || isset($_POST['title']))) {
    $id = !empty($_POST['popup_id']) ? (int)$_POST['popup_id'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $button_text = trim($_POST['button_text'] ?? 'Learn More');
    $button_link = trim($_POST['button_link'] ?? 'public_events.php');
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

    $image_path = null;
    $hasNewImage = false;

    // Handle Image Upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);

        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed)) {
            $filename = 'popup_' . time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = $target_file;
                $hasNewImage = true;
            }
        }
    }

    if (!empty($id)) {
        // Fetch current record
        $stmtOld = $pdo->prepare("SELECT image_path FROM popup_notification WHERE id = ?");
        $stmtOld->execute([$id]);
        $oldImg = $stmtOld->fetchColumn();

        if ($remove_image) {
            if (!empty($oldImg) && file_exists($oldImg) && strpos($oldImg, 'uploads/') === 0) {
                @unlink($oldImg);
            }
            $stmt = $pdo->prepare("UPDATE popup_notification SET is_active = ?, title = ?, description = ?, button_text = ?, button_link = ?, image_path = NULL WHERE id = ?");
            $stmt->execute([$is_active, $title, $description, $button_text, $button_link, $id]);
        } elseif ($hasNewImage) {
            if (!empty($oldImg) && file_exists($oldImg) && strpos($oldImg, 'uploads/') === 0) {
                @unlink($oldImg);
            }
            $stmt = $pdo->prepare("UPDATE popup_notification SET is_active = ?, title = ?, description = ?, button_text = ?, button_link = ?, image_path = ? WHERE id = ?");
            $stmt->execute([$is_active, $title, $description, $button_text, $button_link, $image_path, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE popup_notification SET is_active = ?, title = ?, description = ?, button_text = ?, button_link = ? WHERE id = ?");
            $stmt->execute([$is_active, $title, $description, $button_text, $button_link, $id]);
        }

        // If marked active, ensure only this notice is active
        if ($is_active === 1) {
            $stmtSync = $pdo->prepare("UPDATE popup_notification SET is_active = 0 WHERE id != ?");
            $stmtSync->execute([$id]);
        }

        header("Location: manage_popup.php");
        exit();
    } else {
        // CREATE
        $stmt = $pdo->prepare("INSERT INTO popup_notification (is_active, title, description, button_text, button_link, image_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$is_active, $title, $description, $button_text, $button_link, $image_path]);
        $newId = (int)$pdo->lastInsertId();

        // If marked active, ensure only this new notice is active
        if ($is_active === 1 && $newId > 0) {
            $stmtSync = $pdo->prepare("UPDATE popup_notification SET is_active = 0 WHERE id != ?");
            $stmtSync->execute([$newId]);
        }

        header("Location: manage_popup.php");
        exit();
    }
}

// --- CRUD: TOGGLE ACTIVE STATUS ---
if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    $stmt = $pdo->prepare("SELECT is_active FROM popup_notification WHERE id = ?");
    $stmt->execute([$id]);
    $curr = $stmt->fetchColumn();

    if ($curr !== false) {
        if ((int)$curr === 1) {
            // Deactivate
            $pdo->prepare("UPDATE popup_notification SET is_active = 0 WHERE id = ?")->execute([$id]);
        } else {
            // Activate this, deactivate others
            $pdo->exec("UPDATE popup_notification SET is_active = 0");
            $pdo->prepare("UPDATE popup_notification SET is_active = 1 WHERE id = ?")->execute([$id]);
        }
    }
    
    $redirect = 'manage_popup.php';
    $queryParams = [];
    if (!empty($_GET['status'])) $queryParams['status'] = $_GET['status'];
    if (!empty($_GET['media'])) $queryParams['media'] = $_GET['media'];
    if (!empty($_GET['search'])) $queryParams['search'] = $_GET['search'];
    if (!empty($queryParams)) {
        $redirect .= '?' . http_build_query($queryParams);
    }
    header("Location: " . $redirect);
    exit();
}

// --- CRUD: DELETE ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT image_path FROM popup_notification WHERE id = ?");
    $stmt->execute([$id]);
    $oldImg = $stmt->fetchColumn();
    if (!empty($oldImg) && file_exists($oldImg) && strpos($oldImg, 'uploads/') === 0) {
        @unlink($oldImg);
    }
    $pdo->prepare("DELETE FROM popup_notification WHERE id = ?")->execute([$id]);
    
    $redirect = 'manage_popup.php';
    $queryParams = [];
    if (!empty($_GET['status'])) $queryParams['status'] = $_GET['status'];
    if (!empty($_GET['media'])) $queryParams['media'] = $_GET['media'];
    if (!empty($_GET['search'])) $queryParams['search'] = $_GET['search'];
    if (!empty($queryParams)) {
        $redirect .= '?' . http_build_query($queryParams);
    }
    header("Location: " . $redirect);
    exit();
}

// --- CRUD: READ & FILTERS ---
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$filter_media = trim($_GET['media'] ?? '');

$query = "SELECT * FROM popup_notification WHERE 1=1";
$params = [];

if ($filter_status === 'active') {
    $query .= " AND is_active = 1";
} elseif ($filter_status === 'paused') {
    $query .= " AND is_active = 0";
}

if ($filter_media === 'with_media') {
    $query .= " AND image_path IS NOT NULL AND image_path != ''";
} elseif ($filter_media === 'text_only') {
    $query .= " AND (image_path IS NULL OR image_path = '')";
}

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR description LIKE ? OR button_text LIKE ? OR button_link LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY is_active DESC, updated_at DESC, id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$popups = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Telemetry Metrics
$total_popups = (int)$pdo->query("SELECT COUNT(*) FROM popup_notification")->fetchColumn();
$active_popups = (int)$pdo->query("SELECT COUNT(*) FROM popup_notification WHERE is_active = 1")->fetchColumn();
$with_media_count = (int)$pdo->query("SELECT COUNT(*) FROM popup_notification WHERE image_path IS NOT NULL AND image_path != ''")->fetchColumn();
$current_filtered_count = count($popups);

// Active popup for simulator default
$active_popup = $pdo->query("SELECT * FROM popup_notification WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$active_popup && !empty($popups)) {
    $active_popup = $popups[0];
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Pop-Up Notice Management | TALA OS</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    
    <!-- Anti-Flash Theme Script -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
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
        
        /* Ultra-Clean Modern Glass Cards: ZERO Outside Borders & ZERO Drop Shadows */
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

        /* Tap effect for mobile elements */
        .tap-effect:active { transform: scale(0.97); opacity: 0.85; transition: transform 0.1s; }

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
        label:focus,
        label:focus-visible,
        label:active,
        a:focus {
            outline: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }

        label {
            user-select: none;
            -webkit-user-select: none;
            -webkit-tap-highlight-color: transparent;
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
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- Admin Sidebar -->
    <?php include 'sidebar_admin.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA (Responsive & Scrollable - Matching manage_announcements.php) -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px and fits viewport smoothly -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-3 md:space-y-4">
            
            <!-- HEADER SECTION (Bold H1 only, 3 Unified Green KPI Badges with Modern Hover) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                        Pop-Up Notice Manager
                    </h1>
                </div>
                
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Broadcast Status -->
                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-broadcast"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Broadcast Status</span>
                            <span class="text-xs md:text-sm font-black leading-tight flex items-center gap-1.5 mt-0.5 <?= $active_popups > 0 ? 'text-green-600 dark:text-green-500' : 'text-slate-500 dark:text-zinc-400' ?>">
                                <?php if ($active_popups > 0): ?>
                                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                                    <span>Active Live</span>
                                <?php else: ?>
                                    <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                    <span>Paused</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <!-- KPI 2: Total Notices -->
                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-megaphone"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Notices</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_popups) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: With Media -->
                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-image"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">With Media</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($with_media_count) ?></span>
                        </div>
                    </div>
                </div>
            </div>


            <!-- FILTER, SEARCH & ACTION CONTROL BAR (Matching manage_announcements.php) -->
            <div class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col md:flex-row items-center justify-between gap-2.5">
                <!-- Status & Media Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto custom-scrollbar py-1 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['param' => '', 'val' => '', 'label' => 'All Notices', 'count' => $total_popups, 'active' => empty($filter_status) && empty($filter_media)],
                        ['param' => 'status', 'val' => 'active', 'label' => 'Active Live', 'count' => $active_popups, 'active' => ($filter_status === 'active')],
                        ['param' => 'media', 'val' => 'with_media', 'label' => 'With Media', 'count' => $with_media_count, 'active' => ($filter_media === 'with_media')],
                    ];
                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        $url = 'manage_popup.php';
                        if (!empty($tab['param'])) {
                            $url .= '?' . $tab['param'] . '=' . urlencode($tab['val']);
                            if (!empty($search)) $url .= '&search=' . urlencode($search);
                        } elseif (!empty($search)) {
                            $url .= '?search=' . urlencode($search);
                        }
                    ?>
                        <a href="<?= $url ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span><?= $tab['label'] ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 dark:bg-black/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?>"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Search, Simulator & New Notice Button -->
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <form method="GET" class="relative flex-1 md:w-56">
                        <?php if (!empty($filter_status)): ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($filter_status) ?>">
                        <?php endif; ?>
                        <?php if (!empty($filter_media)): ?>
                            <input type="hidden" name="media" value="<?= htmlspecialchars($filter_media) ?>">
                        <?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search headline, link..." class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-800 dark:text-zinc-200 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                    </form>

                    <?php if (!empty($search)): ?>
                        <a href="manage_popup.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Search">
                            <i class="ph-bold ph-x text-xs"></i>
                        </a>
                    <?php endif; ?>

                    <button type="button" onclick="openTestModal()" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold text-xs tap-effect flex items-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97]" title="Simulate Pop-Up">
                        <i class="ph-bold ph-presentation text-xs text-green-600 dark:text-green-500"></i>
                        <span class="hidden sm:inline">Simulate</span>
                    </button>

                    <button id="newNoticeBtn" type="button" onclick="openNoticeModal()" class="px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group">
                        <i class="ph-bold ph-plus text-xs transition-transform duration-300 group-hover:scale-110"></i>
                        <span>New Notice</span>
                    </button>
                </div>
            </div>

            <!-- CONSOLIDATED MINIMAL NOTICE REGISTRY TABLE -->
            <div class="glass-card rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Minimal Header Strip -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex justify-between items-center transition-colors flex-shrink-0 border-b border-slate-100 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Notice Registry</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                            <?= number_format($current_filtered_count) ?>
                        </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400 dark:text-zinc-500">Homepage pop-up notices</span>
                </div>

                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[680px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider bg-slate-50/90 dark:bg-zinc-900/90 backdrop-blur-sm transition-colors border-b border-slate-100 dark:border-zinc-800">
                                <th class="py-2 px-4">Notice</th>
                                <th class="py-2 px-4">Destination</th>
                                <th class="py-2 px-4">Status</th>
                                <th class="py-2 px-4">Updated</th>
                                <th class="py-2 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs font-medium divide-y divide-slate-100/80 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($popups)): ?>
                                <?php foreach ($popups as $p): 
                                    $hasMedia = (!empty($p['image_path']) && file_exists($p['image_path']));
                                    $isActive = ((int)($p['is_active'] ?? 0) === 1);
                                    $filterQuery = (!empty($filter_status) ? '&status=' . urlencode($filter_status) : '') . (!empty($filter_media) ? '&media=' . urlencode($filter_media) : '') . (!empty($search) ? '&search=' . urlencode($search) : '');
                                ?>
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors group">
                                        <!-- Notice: Thumbnail + Title + Subtitle Snippet -->
                                        <td class="py-2.5 px-4">
                                            <div class="flex items-center gap-3">
                                                <?php if ($hasMedia): ?>
                                                    <div class="w-8 h-8 rounded-lg overflow-hidden bg-slate-100 dark:bg-zinc-800 flex-shrink-0 cursor-pointer group/thumb" onclick="openTestModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)" title="Preview Banner">
                                                        <img src="<?= htmlspecialchars($p['image_path']) ?>" alt="Thumbnail" class="w-full h-full object-cover group-hover/thumb:scale-110 transition-transform duration-200">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                                        <i class="ph-bold ph-megaphone"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="min-w-0 max-w-sm sm:max-w-md">
                                                    <p class="font-bold text-slate-900 dark:text-white text-xs leading-snug truncate group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors">
                                                        <?= htmlspecialchars($p['title']) ?>
                                                    </p>
                                                    <p class="text-[11px] text-slate-400 dark:text-zinc-500 truncate mt-0.5">
                                                        <?= htmlspecialchars($p['description']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Destination Link -->
                                        <td class="py-2.5 px-4 whitespace-nowrap">
                                            <a href="<?= htmlspecialchars($p['button_link']) ?>" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-zinc-400 hover:text-green-600 dark:hover:text-green-400 transition-colors" title="<?= htmlspecialchars($p['button_link']) ?>">
                                                <span><?= htmlspecialchars($p['button_text']) ?></span>
                                                <i class="ph-bold ph-arrow-square-out text-[11px] opacity-60"></i>
                                            </a>
                                        </td>

                                        <!-- Status Indicator -->
                                        <td class="py-2.5 px-4 whitespace-nowrap">
                                            <a href="manage_popup.php?toggle_status=<?= $p['id'] ?><?= $filterQuery ?>" class="inline-flex items-center gap-1.5 text-xs font-bold transition-all tap-effect <?= $isActive ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-zinc-500' ?>" title="Click to <?= $isActive ? 'Pause Notice' : 'Activate Live' ?>">
                                                <span class="w-1.5 h-1.5 rounded-full <?= $isActive ? 'bg-green-500 animate-pulse' : 'bg-slate-300 dark:bg-zinc-600' ?>"></span>
                                                <span><?= $isActive ? 'Active Live' : 'Paused' ?></span>
                                            </a>
                                        </td>

                                        <!-- Updated Date -->
                                        <td class="py-2.5 px-4 whitespace-nowrap text-xs text-slate-400 dark:text-zinc-500">
                                            <?= !empty($p['updated_at']) ? date('M j, Y', strtotime($p['updated_at'])) : '—' ?>
                                        </td>

                                        <!-- Minimal Ghost Action Buttons -->
                                        <td class="py-2.5 px-4 text-right align-middle whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button" onclick="openTestModal(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect" title="Simulate Pop-Up">
                                                    <i class="ph-bold ph-eye text-xs"></i>
                                                </button>
                                                <button type="button" onclick="editNotice(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect" title="Edit Notice">
                                                    <i class="ph-bold ph-pencil-simple text-xs"></i>
                                                </button>
                                                <a href="manage_popup.php?delete=<?= $p['id'] ?><?= $filterQuery ?>" onclick="return confirm('Confirm Deletion: Permanently remove this pop-up notice?')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Delete Notice">
                                                    <i class="ph-bold ph-trash text-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <i class="ph-bold ph-megaphone text-2xl opacity-40 mb-1 block mx-auto"></i>
                                        <p class="text-xs font-semibold text-slate-600 dark:text-zinc-400">No pop-up notices found</p>
                                        <?php if (!empty($search) || !empty($filter_status) || !empty($filter_media)): ?>
                                            <a href="manage_popup.php" class="mt-2.5 inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
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
                <div class="px-4 py-2 border-t border-slate-100 dark:border-zinc-800/80 flex justify-between items-center text-[11px] text-slate-400 dark:text-zinc-500 flex-shrink-0 bg-white/40 dark:bg-zinc-900/40">
                    <span>Showing <strong class="text-slate-700 dark:text-zinc-300"><?= number_format($current_filtered_count) ?></strong> of <?= number_format($total_popups) ?></span>
                    <span>TALA OS &bull; Pop-Up</span>
                </div>

            </div>

        </div>
    </main>

    <!-- NOTICE CREATE & EDIT MODAL (Minimal Modern Scale-Fade Pop-up Effect) -->
    <div id="noticeModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeNoticeModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-xl md:max-w-2xl w-full p-5 md:p-7 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800" id="noticeModalBox">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-5 border-b border-slate-100 dark:border-zinc-800 pb-3.5">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm">
                        <i class="ph-bold ph-megaphone"></i>
                    </div>
                    <div>
                        <h2 id="modalFormTitle" class="text-sm md:text-base font-extrabold text-slate-900 dark:text-white tracking-tight select-none">Create New Pop-Up Notice</h2>
                        <p id="modalFormSubtitle" class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium">Configure landing page modal notice parameters</p>
                    </div>
                </div>
                <button type="button" onclick="closeNoticeModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 flex items-center justify-center tap-effect transition-all duration-200 active:scale-[0.95]" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form id="noticeModalForm" action="manage_popup.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-3.5 max-h-[75vh] overflow-y-auto custom-scrollbar pr-1">
                <input type="hidden" name="save_popup" value="1">
                <input type="hidden" name="popup_id" id="modal_popup_id" value="">

                <!-- Headline Title -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Headline Title *</label>
                    <input type="text" name="title" id="modal_title" required placeholder="e.g. SK Youth Ambassadress 2026 Screening" class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                </div>

                <!-- Photo Attachment -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Banner Graphic (Optional)</label>
                    <input type="file" name="image" id="modal_image" accept="image/*" class="w-full text-xs text-slate-500 dark:text-zinc-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 dark:file:bg-zinc-800 file:text-slate-700 dark:file:text-zinc-300 hover:file:bg-slate-200 dark:hover:file:bg-zinc-700 bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 rounded-xl p-1.5 cursor-pointer transition-colors">
                    
                    <div id="modalCurrentImageContainer" class="hidden mt-2 flex items-center justify-between p-2 rounded-xl bg-slate-50 dark:bg-zinc-900/40 border border-slate-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2">
                            <img id="modalCurrentImageThumb" src="" class="w-10 h-10 object-cover rounded-lg border border-slate-200 dark:border-zinc-700">
                            <div>
                                <p class="text-[11px] font-bold text-green-600 dark:text-green-500">Banner Graphic Attached</p>
                                <p class="text-[10px] text-slate-400 dark:text-zinc-500">Upload new file to replace</p>
                            </div>
                        </div>
                        <label class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-zinc-400 cursor-pointer">
                            <input type="checkbox" name="remove_image" id="modal_remove_image" value="1" class="rounded border-slate-300 dark:border-zinc-700 text-green-600 focus:ring-0">
                            <span>Remove Banner</span>
                        </label>
                    </div>
                </div>

                <!-- Description -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Content Details & Message *</label>
                    <textarea name="description" id="modal_description" rows="3" required placeholder="Outline event guidelines, dates, venue, or instructions..." class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors resize-none custom-scrollbar"></textarea>
                </div>

                <!-- Button Label -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Button Label *</label>
                    <input type="text" name="button_text" id="modal_button_text" required value="Learn More" placeholder="e.g. Learn More / Register Now" class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                </div>

                <!-- Destination Link -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Destination Link *</label>
                    <input type="text" name="button_link" id="modal_button_link" required value="public_events.php" placeholder="e.g. public_events.php or https://..." class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                </div>

                <!-- Active Broadcast Toggle -->
                <div class="md:col-span-2 p-3 rounded-xl bg-slate-50 dark:bg-zinc-900/60 border border-slate-100 dark:border-zinc-800/60 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm">
                            <i class="ph-bold ph-broadcast"></i>
                        </div>
                        <div>
                            <label for="modal_is_active" class="text-xs md:text-sm font-bold text-slate-900 dark:text-white cursor-pointer select-none">
                                Deploy as Active Live Broadcast
                            </label>
                            <p class="text-[10px] md:text-xs text-slate-400 dark:text-zinc-500">
                                Visitors will immediately see this notice when visiting the homepage.
                            </p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                        <input type="checkbox" name="is_active" id="modal_is_active" value="1" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 dark:bg-zinc-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 dark:after:border-zinc-600 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#15803d] dark:peer-checked:bg-[#16a34a]"></div>
                    </label>
                </div>
                
                <!-- Modal Footer -->
                <div class="md:col-span-2 flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button" onclick="closeNoticeModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white font-bold text-xs md:text-sm tap-effect transition-all duration-200 active:scale-[0.97]">
                        Cancel
                    </button>
                    <button type="submit" id="modalSubmitBtn" class="bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-200 text-xs md:text-sm tap-effect active:scale-[0.97] flex items-center justify-center gap-2 shadow-none group">
                        <span id="modalSubmitText">Publish Notice</span>
                        <i class="ph-bold ph-paper-plane-tilt text-xs group-hover:translate-x-0.5 transition-transform duration-200"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FULLSCREEN SIMULATED MODAL DIALOG (Matches index.php exactly, Zero outside drop shadows) -->
    <div id="testModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeTestModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-[800px] w-full flex flex-col md:flex-row transform scale-95 transition-transform duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800" id="testModalBox">
            
            <button onclick="closeTestModal()" class="absolute top-4 right-4 z-20 w-8 h-8 bg-black/20 hover:bg-black/40 text-white rounded-full flex items-center justify-center backdrop-blur-md transition-colors focus:outline-none">
                <i class="ph-bold ph-x text-lg"></i>
            </button>

            <!-- Banner side -->
            <div id="modal_banner_wrapper" class="<?= empty($active_popup['image_path']) ? 'hidden' : 'md:w-1/2' ?> h-56 md:h-auto relative bg-slate-950">
                <img id="modal_banner_img" src="<?= htmlspecialchars($active_popup['image_path'] ?? '') ?>" class="absolute inset-0 w-full h-full object-cover" alt="Announcement">
            </div>

            <!-- Content side -->
            <div id="modal_content_wrapper" class="<?= empty($active_popup['image_path']) ? 'w-full' : 'md:w-1/2' ?> p-8 sm:p-10 flex flex-col justify-center text-center md:text-left bg-white dark:bg-zinc-900">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-green-500/10 text-green-600 dark:text-green-500 text-[10px] font-bold uppercase tracking-widest mb-4 mx-auto md:mx-0 w-max">
                    <i class="ph-fill ph-bell-ringing"></i> Announcement
                </span>
                
                <h3 id="modal_title_view" class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white leading-tight mb-4 tracking-tight">
                    <?= htmlspecialchars($active_popup['title'] ?? 'Welcome to TALA') ?>
                </h3>
                
                <p id="modal_description_view" class="text-sm text-slate-500 dark:text-zinc-400 font-medium leading-relaxed mb-8 whitespace-pre-line">
                    <?= htmlspecialchars($active_popup['description'] ?? 'Stay tuned for upcoming community events.') ?>
                </p>
                
                <a id="modal_btn" href="<?= htmlspecialchars($active_popup['button_link'] ?? 'public_events.php') ?>" target="_blank" class="w-full bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-3.5 rounded-xl transition-all flex items-center justify-center text-sm shadow-none tap-effect">
                    <span id="modal_btn_text"><?= htmlspecialchars($active_popup['button_text'] ?? 'Explore') ?></span>
                    <i class="ph-bold ph-arrow-right ml-2 text-base"></i>
                </a>
                
                <button onclick="closeTestModal()" class="mt-5 text-xs font-bold text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 transition focus:outline-none">
                    Dismiss for now
                </button>
            </div>
        </div>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Android Style) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 h-[68px] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-2xl border-t border-slate-200 dark:border-zinc-800 z-50 flex justify-around items-center px-2 pb-[env(safe-area-inset-bottom)] transition-colors">
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
        <a href="manage_popup.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-broadcast text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Pop-Up</span>
        </a>
        <a href="admin_approval.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-shield-check text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Approvals</span>
        </a>
    </nav>

    <!-- UI Scripts -->
    <script>
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

        // Open Notice Modal (Create or Edit with smooth scale-up modern pop-up effect)
        function openNoticeModal(data = null) {
            const modal = document.getElementById('noticeModalOverlay');
            const box = document.getElementById('noticeModalBox');
            const form = document.getElementById('noticeModalForm');
            if (!modal || !box) return;

            if (data) {
                // Edit Mode
                document.getElementById('modal_popup_id').value = data.id || '';
                document.getElementById('modal_title').value = data.title || '';
                document.getElementById('modal_description').value = data.description || '';
                document.getElementById('modal_button_text').value = data.button_text || 'Learn More';
                document.getElementById('modal_button_link').value = data.button_link || 'public_events.php';
                document.getElementById('modal_is_active').checked = (parseInt(data.is_active) === 1);

                document.getElementById('modalFormTitle').textContent = 'Edit Pop-Up Notice';
                document.getElementById('modalFormSubtitle').textContent = 'Update notice parameters and destination';
                document.getElementById('modalSubmitText').textContent = 'Save Changes';

                const imgContainer = document.getElementById('modalCurrentImageContainer');
                const imgThumb = document.getElementById('modalCurrentImageThumb');
                const removeImg = document.getElementById('modal_remove_image');
                if (removeImg) removeImg.checked = false;

                if (data.image_path) {
                    if (imgThumb) imgThumb.src = data.image_path;
                    if (imgContainer) imgContainer.classList.remove('hidden');
                } else {
                    if (imgContainer) imgContainer.classList.add('hidden');
                }
            } else {
                // Create Mode
                if (form) form.reset();
                document.getElementById('modal_popup_id').value = '';
                document.getElementById('modal_button_text').value = 'Learn More';
                document.getElementById('modal_button_link').value = 'public_events.php';
                document.getElementById('modal_is_active').checked = false;

                document.getElementById('modalFormTitle').textContent = 'Create New Pop-Up Notice';
                document.getElementById('modalFormSubtitle').textContent = 'Configure landing page modal notice parameters';
                document.getElementById('modalSubmitText').textContent = 'Publish Notice';

                const imgContainer = document.getElementById('modalCurrentImageContainer');
                if (imgContainer) imgContainer.classList.add('hidden');
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                box.classList.remove('scale-95', 'opacity-0');
                box.classList.add('scale-100', 'opacity-100');
                const titleInput = document.getElementById('modal_title');
                if (titleInput) titleInput.focus();
            }, 10);
        }

        function closeNoticeModal() {
            const modal = document.getElementById('noticeModalOverlay');
            const box = document.getElementById('noticeModalBox');
            if (!modal || !box) return;

            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            box.classList.remove('scale-100', 'opacity-100');
            box.classList.add('scale-95', 'opacity-0');

            setTimeout(() => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }, 200);
        }

        function editNotice(data) {
            openNoticeModal(data);
        }

        // Fullscreen Modal Simulator Handlers
        function openTestModal(noticeData) {
            if (noticeData) {
                const title = document.getElementById('modal_title_view');
                const desc = document.getElementById('modal_description_view');
                const btn = document.getElementById('modal_btn');
                const btnText = document.getElementById('modal_btn_text');
                const bannerWrap = document.getElementById('modal_banner_wrapper');
                const bannerImg = document.getElementById('modal_banner_img');
                const contentWrap = document.getElementById('modal_content_wrapper');

                if (title) title.textContent = noticeData.title || '';
                if (desc) desc.textContent = noticeData.description || '';
                if (btn) btn.href = noticeData.button_link || 'public_events.php';
                if (btnText) btnText.textContent = noticeData.button_text || 'Learn More';

                if (noticeData.image_path) {
                    if (bannerImg) bannerImg.src = noticeData.image_path;
                    if (bannerWrap) bannerWrap.className = 'md:w-1/2 h-56 md:h-auto relative bg-slate-950';
                    if (contentWrap) contentWrap.className = 'md:w-1/2 p-8 sm:p-10 flex flex-col justify-center text-center md:text-left bg-white dark:bg-zinc-900';
                } else {
                    if (bannerWrap) bannerWrap.className = 'hidden';
                    if (contentWrap) contentWrap.className = 'w-full p-8 sm:p-10 flex flex-col justify-center text-center bg-white dark:bg-zinc-900';
                }
            }

            const modal = document.getElementById('testModalOverlay');
            const box = document.getElementById('testModalBox');
            if (modal && box) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                setTimeout(() => {
                    modal.classList.remove('opacity-0');
                    modal.classList.add('opacity-100');
                    box.classList.remove('scale-95');
                    box.classList.add('scale-100');
                }, 10);
            }
        }

        function closeTestModal() {
            const modal = document.getElementById('testModalOverlay');
            const box = document.getElementById('testModalBox');
            if (modal && box) {
                modal.classList.remove('opacity-100');
                modal.classList.add('opacity-0');
                box.classList.remove('scale-100');
                box.classList.add('scale-95');
                setTimeout(() => {
                    modal.classList.remove('flex');
                    modal.classList.add('hidden');
                }, 200);
            }
        }

        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const noticeModal = document.getElementById('noticeModalOverlay');
                if (noticeModal && !noticeModal.classList.contains('hidden')) {
                    closeNoticeModal();
                    return;
                }
                const testModal = document.getElementById('testModalOverlay');
                if (testModal && !testModal.classList.contains('hidden')) {
                    closeTestModal();
                    return;
                }
            }
        });
    </script>

    <?php include_once 'genesis_widget.php'; ?>
</body>
</html>