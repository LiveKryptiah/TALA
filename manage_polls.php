<?php
// manage_polls.php - Youth Pulse Polls & Survey Repository (TALA OS Standard - Reference: manage_popup.php)
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Security Check
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$isAdmin = ($role === 'admin');
$barangay = isset($_SESSION['barangay']) ? $_SESSION['barangay'] : (isset($_SESSION['admin_barangay']) ? $_SESSION['admin_barangay'] : 'Municipal Level');
$username = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : (isset($_SESSION['sk_username']) ? $_SESSION['sk_username'] : 'User');

// =================================================================
// FALLBACK XP FUNCTION (Ensures it works even if not in db_connect.php)
// =================================================================
if (!function_exists('autoAwardXP')) {
    function autoAwardXP($pdo, $admin_user, $barangay, $action_name, $xp_amount) {
        try {
            $stmt = $pdo->prepare("INSERT INTO sk2_admin_xp (admin_user, barangay, action_name, xp_earned) VALUES (?, ?, ?, ?)");
            $stmt->execute([$admin_user, $barangay, $action_name, $xp_amount]);

            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $_SESSION['toast_xp'] = $xp_amount;
            $_SESSION['toast_msg'] = $action_name;
        } catch (Exception $e) {}
    }
}

// Auto-create necessary tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `polls` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `question` VARCHAR(255) NOT NULL,
        `barangay` VARCHAR(255) NOT NULL,
        `status` ENUM('active', 'closed') DEFAULT 'active',
        `created_by` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS `poll_options` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `poll_id` INT NOT NULL,
        `option_text` VARCHAR(255) NOT NULL,
        `votes_count` INT DEFAULT 0,
        FOREIGN KEY (`poll_id`) REFERENCES `polls`(`id`) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `poll_votes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `poll_id` INT NOT NULL,
        `youth_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`poll_id`) REFERENCES `polls`(`id`) ON DELETE CASCADE
    )");
} catch (PDOException $e) {}

// Handle Create Poll
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['create_poll'])) {
    $question = trim($_POST['question'] ?? '');
    $options = array_filter(array_map('trim', $_POST['options'] ?? []));
    
    if (!empty($question) && count($options) >= 2) {
        $stmt = $pdo->prepare("INSERT INTO polls (question, barangay, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$question, $barangay, $username]);
        $poll_id = $pdo->lastInsertId();
        
        $stmtOpt = $pdo->prepare("INSERT INTO poll_options (poll_id, option_text) VALUES (?, ?)");
        foreach ($options as $opt) {
            $stmtOpt->execute([$poll_id, $opt]);
        }
        
        // AUTO XP AWARD (Only for SK Officials)
        if (!$isAdmin && function_exists('autoAwardXP')) {
            autoAwardXP($pdo, $username, $barangay, "Launched a Community Poll", 30);
        }

        header("Location: manage_polls.php");
        exit();
    }
}

// Handle Close Poll
if (isset($_GET['close'])) {
    $close_id = (int)$_GET['close'];
    $stmtClose = $isAdmin 
        ? $pdo->prepare("UPDATE polls SET status = 'closed' WHERE id = ?") 
        : $pdo->prepare("UPDATE polls SET status = 'closed' WHERE id = ? AND barangay = ?");
    $isAdmin ? $stmtClose->execute([$close_id]) : $stmtClose->execute([$close_id, $barangay]);
    
    $redirect = 'manage_polls.php';
    $queryParams = [];
    if (!empty($_GET['status']) && $_GET['status'] !== 'All') $queryParams['status'] = $_GET['status'];
    if (!empty($_GET['search'])) $queryParams['search'] = $_GET['search'];
    if (!empty($queryParams)) {
        $redirect .= '?' . http_build_query($queryParams);
    }
    header("Location: " . $redirect);
    exit();
}

// Handle Re-open Poll
if (isset($_GET['reopen'])) {
    $reopen_id = (int)$_GET['reopen'];
    $stmtReopen = $isAdmin 
        ? $pdo->prepare("UPDATE polls SET status = 'active' WHERE id = ?") 
        : $pdo->prepare("UPDATE polls SET status = 'active' WHERE id = ? AND barangay = ?");
    $isAdmin ? $stmtReopen->execute([$reopen_id]) : $stmtReopen->execute([$reopen_id, $barangay]);
    
    $redirect = 'manage_polls.php';
    $queryParams = [];
    if (!empty($_GET['status']) && $_GET['status'] !== 'All') $queryParams['status'] = $_GET['status'];
    if (!empty($_GET['search'])) $queryParams['search'] = $_GET['search'];
    if (!empty($queryParams)) {
        $redirect .= '?' . http_build_query($queryParams);
    }
    header("Location: " . $redirect);
    exit();
}

// Handle Delete Poll
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $stmtDel = $isAdmin 
        ? $pdo->prepare("DELETE FROM polls WHERE id = ?") 
        : $pdo->prepare("DELETE FROM polls WHERE id = ? AND barangay = ?");
    $isAdmin ? $stmtDel->execute([$del_id]) : $stmtDel->execute([$del_id, $barangay]);
    
    $redirect = 'manage_polls.php';
    $queryParams = [];
    if (!empty($_GET['status']) && $_GET['status'] !== 'All') $queryParams['status'] = $_GET['status'];
    if (!empty($_GET['search'])) $queryParams['search'] = $_GET['search'];
    if (!empty($queryParams)) {
        $redirect .= '?' . http_build_query($queryParams);
    }
    header("Location: " . $redirect);
    exit();
}

// Filter and Search parameters
$status_filter = $_GET['status'] ?? 'All';
$search_query = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM polls WHERE 1=1";
$params = [];

if (!$isAdmin) {
    $sql .= " AND barangay = ?";
    $params[] = $barangay;
}

if ($status_filter === 'active' || $status_filter === 'closed') {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($search_query)) {
    $sql .= " AND (question LIKE ? OR barangay LIKE ? OR created_by LIKE ?)";
    $term = "%{$search_query}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$polls = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Telemetry counts for KPI chips
if ($isAdmin) {
    $total_polls = (int)$pdo->query("SELECT COUNT(*) FROM polls")->fetchColumn();
    $active_polls = (int)$pdo->query("SELECT COUNT(*) FROM polls WHERE status = 'active'")->fetchColumn();
    $closed_polls = (int)$pdo->query("SELECT COUNT(*) FROM polls WHERE status = 'closed'")->fetchColumn();
    $total_votes = (int)$pdo->query("SELECT COALESCE(SUM(votes_count), 0) FROM poll_options")->fetchColumn();
} else {
    $stmtTot = $pdo->prepare("SELECT COUNT(*) FROM polls WHERE barangay = ?");
    $stmtTot->execute([$barangay]);
    $total_polls = (int)$stmtTot->fetchColumn();

    $stmtAct = $pdo->prepare("SELECT COUNT(*) FROM polls WHERE barangay = ? AND status = 'active'");
    $stmtAct->execute([$barangay]);
    $active_polls = (int)$stmtAct->fetchColumn();

    $stmtCls = $pdo->prepare("SELECT COUNT(*) FROM polls WHERE barangay = ? AND status = 'closed'");
    $stmtCls->execute([$barangay]);
    $closed_polls = (int)$stmtCls->fetchColumn();

    $stmtVotes = $pdo->prepare("SELECT COALESCE(SUM(po.votes_count), 0) FROM poll_options po JOIN polls p ON po.poll_id = p.id WHERE p.barangay = ?");
    $stmtVotes->execute([$barangay]);
    $total_votes = (int)$stmtVotes->fetchColumn();
}
$current_filtered_count = count($polls);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Youth Pulse Polls | TALA OS</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">

    <!-- ANTI-FLASH SCRIPT -->
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

        /* Modern Spring Hover Effects */
        .modern-card-hover {
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.3s ease !important;
            will-change: transform;
        }
        .modern-card-hover:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- STANDARD TALA OS SIDEBAR (ROLE-BASED) -->
    <?php 
    if ($isAdmin) {
        include 'sidebar_admin.php'; 
    } else {
        include 'sidebar_sk.php'; 
    }
    ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA (Responsive & Gracefully Scrollable - Matching manage_popup.php) -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px and fits viewport smoothly -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-3 md:space-y-4">
            
            <!-- HEADER SECTION (Bold H1 only, 3 Unified Green KPI Badges with Modern Hover) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                        Youth Pulse Polls
                    </h1>
                </div>
                
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-chart-bar"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Active Polls</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($active_polls) ?></span>
                        </div>
                    </div>

                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-lock-key"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Closed</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($closed_polls) ?></span>
                        </div>
                    </div>

                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-users-three"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Votes</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_votes) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILTER, SEARCH & ACTION CONTROL BAR (Matching manage_popup.php) -->
            <div class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col md:flex-row items-center justify-between gap-2.5">
                <!-- Status Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto custom-scrollbar py-1 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['param' => '', 'val' => '', 'label' => 'All Polls', 'count' => $total_polls, 'active' => empty($status_filter) || $status_filter === 'All'],
                        ['param' => 'status', 'val' => 'active', 'label' => 'Active', 'count' => $active_polls, 'active' => ($status_filter === 'active')],
                        ['param' => 'status', 'val' => 'closed', 'label' => 'Closed', 'count' => $closed_polls, 'active' => ($status_filter === 'closed')],
                    ];
                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        $url = 'manage_polls.php';
                        if (!empty($tab['param'])) {
                            $url .= '?' . $tab['param'] . '=' . urlencode($tab['val']);
                            if (!empty($search_query)) $url .= '&search=' . urlencode($search_query);
                        } elseif (!empty($search_query)) {
                            $url .= '?search=' . urlencode($search_query);
                        }
                    ?>
                        <a href="<?= $url ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span><?= $tab['label'] ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 dark:bg-black/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?>"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Search and Create Button -->
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <form method="GET" class="relative flex-1 md:w-56">
                        <?php if (!empty($status_filter) && $status_filter !== 'All'): ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
                        <?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search question, barangay..." class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-800 dark:text-zinc-200 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                    </form>

                    <?php if (!empty($search_query)): ?>
                        <a href="manage_polls.php<?= ($status_filter !== 'All') ? '?status=' . urlencode($status_filter) : '' ?>" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Search">
                            <i class="ph-bold ph-x text-xs"></i>
                        </a>
                    <?php endif; ?>

                    <button id="newPollBtn" type="button" onclick="openPollModal()" class="px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group">
                        <i class="ph-bold ph-plus text-xs transition-transform duration-300 group-hover:scale-110"></i>
                        <span>New Poll</span>
                    </button>
                </div>
            </div>

            <!-- CONSOLIDATED MINIMAL POLL REGISTRY TABLE (Minimal Table Architecture - Reference: manage_popup.php) -->
            <div class="glass-card rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Minimal Header Strip -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex justify-between items-center transition-colors flex-shrink-0 border-b border-slate-100 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Poll Registry</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                            <?= number_format($current_filtered_count) ?>
                        </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400 dark:text-zinc-500">Youth pulse surveys & community ballots</span>
                </div>

                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[720px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider bg-slate-50/90 dark:bg-zinc-900/90 backdrop-blur-sm transition-colors border-b border-slate-100 dark:border-zinc-800">
                                <th class="py-2 px-4">Poll Question</th>
                                <th class="py-2 px-4">Leading Choice</th>
                                <th class="py-2 px-4">Ballots</th>
                                <th class="py-2 px-4">Date</th>
                                <th class="py-2 px-4">Status</th>
                                <th class="py-2 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs font-medium divide-y divide-slate-100/80 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($polls)): ?>
                                <?php foreach ($polls as $poll): 
                                    $options_stmt = $pdo->prepare("SELECT * FROM poll_options WHERE poll_id = ? ORDER BY id ASC");
                                    $options_stmt->execute([$poll['id']]);
                                    $options = $options_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    $poll_votes = array_sum(array_column($options, 'votes_count'));
                                    $isActive = ($poll['status'] === 'active');
                                    $filterQuery = ($status_filter !== 'All' ? '&status=' . urlencode($status_filter) : '') . (!empty($search_query) ? '&search=' . urlencode($search_query) : '');

                                    $topOpt = null;
                                    $maxVotes = -1;
                                    foreach ($options as $opt) {
                                        if ($opt['votes_count'] > $maxVotes) {
                                            $maxVotes = $opt['votes_count'];
                                            $topOpt = $opt;
                                        }
                                    }
                                    $topPct = $poll_votes > 0 ? round(($topOpt['votes_count'] / $poll_votes) * 100) : 0;
                                ?>
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors group">
                                        <!-- Poll Question Column -->
                                        <td class="py-2.5 px-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                                    <i class="ph-bold ph-chart-bar"></i>
                                                </div>
                                                <div class="min-w-0 max-w-xs sm:max-w-sm md:max-w-md">
                                                    <p class="font-bold text-slate-900 dark:text-white text-xs leading-snug truncate group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors">
                                                        <?= htmlspecialchars($poll['question']) ?>
                                                    </p>
                                                    <p class="text-[11px] text-slate-400 dark:text-zinc-500 truncate mt-0.5">
                                                        <span class="inline-flex items-center gap-1"><i class="ph-bold ph-map-pin text-[10px]"></i> Brgy. <?= htmlspecialchars($poll['barangay']) ?></span>
                                                        &bull; <?= count($options) ?> options
                                                        &bull; By <?= htmlspecialchars($poll['created_by']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Leading Choice & Result Column -->
                                        <td class="py-2.5 px-4 whitespace-nowrap">
                                            <?php if ($poll_votes > 0 && $topOpt): ?>
                                                <div class="min-w-0 max-w-[150px] sm:max-w-[180px]">
                                                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-700 dark:text-zinc-300 gap-1.5 mb-1">
                                                        <span class="truncate"><?= htmlspecialchars($topOpt['option_text']) ?></span>
                                                        <span class="text-green-600 dark:text-green-400 font-black flex-shrink-0"><?= $topPct ?>%</span>
                                                    </div>
                                                    <div class="w-24 sm:w-32 bg-slate-100 dark:bg-zinc-800 rounded-full h-1.5 overflow-hidden">
                                                        <div class="bg-[#15803d] dark:bg-[#16a34a] h-full rounded-full transition-all duration-300" style="width: <?= $topPct ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 dark:text-zinc-500 italic">No votes yet</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Ballots Column -->
                                        <td class="py-2.5 px-4 whitespace-nowrap">
                                            <span class="text-xs font-bold text-slate-700 dark:text-zinc-300">
                                                <?= number_format($poll_votes) ?> <span class="text-[11px] text-slate-400 dark:text-zinc-500 font-normal"><?= $poll_votes === 1 ? 'vote' : 'votes' ?></span>
                                            </span>
                                        </td>

                                        <!-- Date Column -->
                                        <td class="py-2.5 px-4 whitespace-nowrap text-xs text-slate-400 dark:text-zinc-500">
                                            <?= date('M j, Y', strtotime($poll['created_at'])) ?>
                                        </td>

                                        <!-- Status Column -->
                                        <td class="py-2.5 px-4 whitespace-nowrap">
                                            <a href="manage_polls.php?<?= $isActive ? 'close' : 'reopen' ?>=<?= $poll['id'] ?><?= $filterQuery ?>" class="inline-flex items-center gap-1.5 text-xs font-bold transition-all tap-effect <?= $isActive ? 'text-green-600 dark:text-green-400' : 'text-slate-400 dark:text-zinc-500' ?>" title="Click to <?= $isActive ? 'Close Poll' : 'Re-open Poll' ?>">
                                                <span class="w-1.5 h-1.5 rounded-full <?= $isActive ? 'bg-green-500 animate-pulse' : 'bg-slate-300 dark:bg-zinc-600' ?>"></span>
                                                <span><?= $isActive ? 'Active' : 'Closed' ?></span>
                                            </a>
                                        </td>

                                        <!-- Actions Column -->
                                        <td class="py-2.5 px-4 text-right align-middle whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1">
                                                <?php if ($isActive): ?>
                                                    <a href="manage_polls.php?close=<?= $poll['id'] ?><?= $filterQuery ?>" onclick="return confirm('Close this poll to further voting?');" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 transition-colors tap-effect" title="Close Poll">
                                                        <i class="ph-bold ph-lock-key text-xs"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="manage_polls.php?reopen=<?= $poll['id'] ?><?= $filterQuery ?>" onclick="return confirm('Re-open this poll for community voting?');" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-green-50 dark:hover:bg-green-500/10 transition-colors tap-effect" title="Re-open Poll">
                                                        <i class="ph-bold ph-lock-key-open text-xs"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="manage_polls.php?delete=<?= $poll['id'] ?><?= $filterQuery ?>" onclick="return confirm('Permanently delete this poll and all its cast votes?');" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Delete Poll">
                                                    <i class="ph-bold ph-trash text-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="py-10 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <i class="ph-bold ph-chart-bar text-2xl opacity-40 mb-1 block mx-auto"></i>
                                        <p class="text-xs font-semibold text-slate-600 dark:text-zinc-400">No community polls found</p>
                                        <?php if (!empty($search_query) || ($status_filter !== 'All' && !empty($status_filter))): ?>
                                            <a href="manage_polls.php" class="mt-2.5 inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
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
                    <span>Showing <strong class="text-slate-700 dark:text-zinc-300"><?= number_format($current_filtered_count) ?></strong> of <?= number_format($total_polls) ?></span>
                    <span>TALA OS &bull; Poll Registry</span>
                </div>

            </div>

        </div>
    </main>

    <!-- POLL CREATE MODAL (Minimal Modern Scale-Fade Pop-up Effect - Reference: manage_popup.php) -->
    <div id="pollModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closePollModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-xl md:max-w-2xl w-full p-5 md:p-7 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800" id="pollModalBox">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-5 border-b border-slate-100 dark:border-zinc-800 pb-3.5">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm">
                        <i class="ph-bold ph-chart-bar-horizontal"></i>
                    </div>
                    <div>
                        <h2 class="text-sm md:text-base font-extrabold text-slate-900 dark:text-white tracking-tight select-none">Create Community Poll</h2>
                        <p class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium">Deploy youth survey questions and voting options</p>
                    </div>
                </div>
                <button type="button" onclick="closePollModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 flex items-center justify-center tap-effect transition-all duration-200 active:scale-[0.95]" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form id="pollModalForm" action="manage_polls.php" method="POST" class="space-y-4 max-h-[75vh] overflow-y-auto custom-scrollbar pr-1">
                <input type="hidden" name="create_poll" value="1">
                
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Question / Topic *</label>
                    <textarea name="question" id="modal_question" rows="2" required placeholder="e.g. Which community initiative should our SK council fund next?" class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors resize-none custom-scrollbar"></textarea>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider select-none">Voting Options *</label>
                        <button type="button" onclick="addOption()" class="text-xs font-bold text-[#15803d] hover:text-[#166534] dark:text-[#4ade80] dark:hover:text-green-300 flex items-center gap-1 transition-colors tap-effect active:scale-[0.97]">
                            <i class="ph-bold ph-plus-circle text-sm"></i>
                            <span>Add option</span>
                        </button>
                    </div>
                    <div id="options-container" class="space-y-2">
                        <div class="flex items-center gap-2 option-row">
                            <input type="text" name="options[]" required placeholder="Option 1" class="option-input flex-1 bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                        </div>
                        <div class="flex items-center gap-2 option-row">
                            <input type="text" name="options[]" required placeholder="Option 2" class="option-input flex-1 bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button" onclick="closePollModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white font-bold text-xs md:text-sm tap-effect transition-all duration-200 active:scale-[0.97]">
                        Cancel
                    </button>
                    <button type="submit" id="modalSubmitBtn" class="bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-200 text-xs md:text-sm tap-effect active:scale-[0.97] flex items-center justify-center gap-2 shadow-none group">
                        <span>Publish Poll</span>
                        <i class="ph-bold ph-paper-plane-tilt text-xs group-hover:translate-x-0.5 transition-transform duration-200"></i>
                    </button>
                </div>
            </form>
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
        <a href="manage_polls.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-chart-bar text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Polls</span>
        </a>
        <a href="admin_approval.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-shield-check text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Approvals</span>
        </a>
    </nav>

    <!-- XP TOAST NOTIFICATION (FOR SK OFFICIALS) -->
    <?php if (isset($_SESSION['toast_xp'])): ?>
        <div id="sk2-xp-toast" class="fixed bottom-8 left-1/2 -translate-x-1/2 z-[9999] transform translate-y-16 scale-90 opacity-0 transition-all duration-700 ease-out pointer-events-none">
            <div class="bg-slate-900/90 dark:bg-zinc-900/90 text-white backdrop-blur-xl px-4 py-2.5 rounded-2xl flex items-center gap-3 border border-white/10">
                <div class="w-8 h-8 rounded-xl bg-amber-400/20 text-amber-400 flex items-center justify-center font-black text-sm flex-shrink-0">
                    <i class="ph-bold ph-star"></i>
                </div>
                <div>
                    <p class="text-xs font-black tracking-tight text-amber-400">+<?= $_SESSION['toast_xp'] ?> XP Awarded</p>
                    <p class="text-[10px] font-bold text-slate-300 dark:text-zinc-400"><?= htmlspecialchars($_SESSION['toast_msg']) ?></p>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", () => {
                const toast = document.getElementById('sk2-xp-toast');
                if (toast) {
                    setTimeout(() => { 
                        toast.classList.remove('translate-y-16', 'scale-90', 'opacity-0'); 
                    }, 100);
                    
                    setTimeout(() => {
                        toast.classList.add('translate-y-10', 'scale-95', 'opacity-0');
                        setTimeout(() => toast.remove(), 700);
                    }, 4000);
                }
            });
        </script>
        <?php 
        unset($_SESSION['toast_xp']); 
        unset($_SESSION['toast_msg']); 
        ?>
    <?php endif; ?>

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

        // Open Poll Modal (Create with smooth scale-up modern pop-up effect)
        function openPollModal() {
            const modal = document.getElementById('pollModalOverlay');
            const box = document.getElementById('pollModalBox');
            const form = document.getElementById('pollModalForm');
            if (!modal || !box) return;

            if (form) form.reset();
            const container = document.getElementById('options-container');
            if (container) {
                container.innerHTML = `
                    <div class="flex items-center gap-2 option-row">
                        <input type="text" name="options[]" required placeholder="Option 1" class="option-input flex-1 bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                    </div>
                    <div class="flex items-center gap-2 option-row">
                        <input type="text" name="options[]" required placeholder="Option 2" class="option-input flex-1 bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                    </div>
                `;
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                box.classList.remove('scale-95', 'opacity-0');
                box.classList.add('scale-100', 'opacity-100');
                const questionInput = document.getElementById('modal_question');
                if (questionInput) questionInput.focus();
            }, 10);
        }

        function closePollModal() {
            const modal = document.getElementById('pollModalOverlay');
            const box = document.getElementById('pollModalBox');
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

        // Add dynamic option to form
        function addOption() {
            const container = document.getElementById('options-container');
            if (!container) return;
            const optionCount = container.querySelectorAll('.option-input').length + 1;
            
            const wrapper = document.createElement('div');
            wrapper.className = 'flex items-center gap-2 option-row';
            
            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'options[]';
            input.required = true;
            input.placeholder = `Option ${optionCount}`;
            input.className = 'option-input flex-1 bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors';
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'w-8 h-8 rounded-xl bg-slate-100 hover:bg-rose-50 dark:bg-zinc-800 dark:hover:bg-rose-500/10 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 flex items-center justify-center tap-effect transition-all active:scale-[0.95] flex-shrink-0';
            removeBtn.innerHTML = '<i class="ph-bold ph-x text-xs"></i>';
            removeBtn.onclick = function() {
                wrapper.remove();
            };
            
            wrapper.appendChild(input);
            wrapper.appendChild(removeBtn);
            container.appendChild(wrapper);
            input.focus();
        }

        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('pollModalOverlay');
                if (modal && !modal.classList.contains('hidden')) {
                    closePollModal();
                }
            }
        });
    </script>

    <!-- Genesis AI Assistant Widget -->
    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include_once __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>