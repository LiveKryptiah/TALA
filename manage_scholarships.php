<?php
// manage_scholarships.php - Educational Grants & Scholarship Opportunities (TALA OS Standard - Reference: manage_popup.php)
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// Auto-create scholarships table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `scholarships` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `provider` VARCHAR(255) NOT NULL,
        `description` TEXT NOT NULL,
        `deadline` DATE NOT NULL,
        `application_link` VARCHAR(255) DEFAULT NULL,
        `created_by` VARCHAR(100) DEFAULT 'Admin',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// Handle Add / Edit Scholarship (Admins Only)
if ($isAdmin && ($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && (isset($_POST['save_scholarship']) || isset($_POST['action']))) {
    $id = !empty($_POST['scholarship_id']) ? (int)$_POST['scholarship_id'] : (!empty($_POST['id']) ? (int)$_POST['id'] : null);
    $title = trim($_POST['title'] ?? '');
    $provider = trim($_POST['provider'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $application_link = !empty($_POST['application_link']) ? trim($_POST['application_link']) : null;
    $created_by = $_SESSION['admin_username'] ?? 'Admin';

    if (!empty($title) && !empty($provider) && !empty($deadline)) {
        if (!empty($id)) {
            $stmt = $pdo->prepare("UPDATE scholarships SET title = ?, provider = ?, description = ?, deadline = ?, application_link = ? WHERE id = ?");
            $stmt->execute([$title, $provider, $description, $deadline, $application_link, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO scholarships (title, provider, description, deadline, application_link, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $provider, $description, $deadline, $application_link, $created_by]);
        }
        header("Location: manage_scholarships.php");
        exit();
    }
}

// Handle Delete Scholarship (Admins Only)
if ($isAdmin && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM scholarships WHERE id = ?");
    $stmt->execute([$id]);

    $redirect = 'manage_scholarships.php';
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

$sql = "SELECT * FROM scholarships WHERE 1=1";
$params = [];

if ($status_filter === 'Active') {
    $sql .= " AND deadline >= CURDATE()";
} elseif ($status_filter === 'Expired') {
    $sql .= " AND deadline < CURDATE()";
}

if (!empty($search_query)) {
    $sql .= " AND (title LIKE ? OR provider LIKE ? OR description LIKE ?)";
    $term = "%{$search_query}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY deadline ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$scholarships = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Telemetry counts for header KPI chips
$total_scholarships = (int)$pdo->query("SELECT COUNT(*) FROM scholarships")->fetchColumn();
$active_scholarships = (int)$pdo->query("SELECT COUNT(*) FROM scholarships WHERE deadline >= CURDATE()")->fetchColumn();
$expired_scholarships = (int)$pdo->query("SELECT COUNT(*) FROM scholarships WHERE deadline < CURDATE()")->fetchColumn();
$current_filtered_count = count($scholarships);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage Scholarships | TALA OS</title>
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
                        Manage Scholarships
                    </h1>
                </div>
                
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-graduation-cap"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Grants</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($total_scholarships) ?></span>
                        </div>
                    </div>

                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-clock"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Open</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($active_scholarships) ?></span>
                        </div>
                    </div>

                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-hourglass-high"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Closed</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($expired_scholarships) ?></span>
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
                        ['param' => '', 'val' => '', 'label' => 'All Grants', 'count' => $total_scholarships, 'active' => empty($status_filter) || $status_filter === 'All'],
                        ['param' => 'status', 'val' => 'Active', 'label' => 'Open', 'count' => $active_scholarships, 'active' => ($status_filter === 'Active')],
                        ['param' => 'status', 'val' => 'Expired', 'label' => 'Closed', 'count' => $expired_scholarships, 'active' => ($status_filter === 'Expired')],
                    ];
                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        $url = 'manage_scholarships.php';
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
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search grant, grantor..." class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-800 dark:text-zinc-200 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                    </form>

                    <?php if (!empty($search_query)): ?>
                        <a href="manage_scholarships.php<?= ($status_filter !== 'All') ? '?status=' . urlencode($status_filter) : '' ?>" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Search">
                            <i class="ph-bold ph-x text-xs"></i>
                        </a>
                    <?php endif; ?>

                    <?php if ($isAdmin): ?>
                        <button id="newScholarshipBtn" type="button" onclick="openScholarshipModal()" class="px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group">
                            <i class="ph-bold ph-plus text-xs transition-transform duration-300 group-hover:scale-110"></i>
                            <span>New Grant</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CONSOLIDATED MINIMAL SCHOLARSHIP REGISTRY TABLE (Minimal Table Architecture - Reference: manage_popup.php) -->
            <div class="glass-card rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Minimal Header Strip -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex justify-between items-center transition-colors flex-shrink-0 border-b border-slate-100 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Scholarship Registry</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                            <?= number_format($current_filtered_count) ?>
                        </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400 dark:text-zinc-500">Educational grants & student opportunities</span>
                </div>

                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[720px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider bg-slate-50/90 dark:bg-zinc-900/90 backdrop-blur-sm transition-colors border-b border-slate-100 dark:border-zinc-800">
                                <th class="py-2 px-4">Opportunity</th>
                                <th class="py-2 px-4">Deadline</th>
                                <th class="py-2 px-4">Status</th>
                                <th class="py-2 px-4">Application</th>
                                <th class="py-2 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs font-medium divide-y divide-slate-100/80 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($scholarships)): ?>
                                <?php foreach ($scholarships as $schol): 
                                    $deadlineTime = strtotime($schol['deadline']);
                                    $todayTime = strtotime(date('Y-m-d'));
                                    $isExpired = ($deadlineTime < $todayTime);
                                    $daysDiff = (int)ceil(($deadlineTime - $todayTime) / 86400);
                                    $filterQuery = ($status_filter !== 'All' ? '&status=' . urlencode($status_filter) : '') . (!empty($search_query) ? '&search=' . urlencode($search_query) : '');
                                ?>
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors group">
                                        <!-- Opportunity Column -->
                                        <td class="py-2.5 px-4">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                                    <i class="ph-bold ph-graduation-cap"></i>
                                                </div>
                                                <div class="min-w-0 max-w-xs sm:max-w-sm md:max-w-md">
                                                    <p class="font-bold text-slate-900 dark:text-white text-xs leading-snug truncate group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors">
                                                        <?= htmlspecialchars($schol['title']) ?>
                                                    </p>
                                                    <p class="text-[11px] text-slate-400 dark:text-zinc-500 truncate mt-0.5">
                                                        <span class="inline-flex items-center gap-1"><i class="ph-bold ph-buildings text-[10px]"></i> <?= htmlspecialchars($schol['provider']) ?></span>
                                                        &bull; <?= htmlspecialchars($schol['description']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Deadline Column -->
                                        <td class="py-2.5 px-4 whitespace-nowrap text-xs text-slate-600 dark:text-zinc-400 font-semibold">
                                            <span class="inline-flex items-center gap-1.5">
                                                <i class="ph-bold ph-calendar-blank text-slate-400"></i>
                                                <span><?= date('M j, Y', $deadlineTime) ?></span>
                                            </span>
                                        </td>

                                        <!-- Status Column -->
                                        <td class="py-2.5 px-4 whitespace-nowrap">
                                            <?php if ($isExpired): ?>
                                                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-rose-600 dark:text-rose-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                    <span>Closed</span>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-green-600 dark:text-green-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                                    <span><?= $daysDiff === 0 ? 'Due Today' : ($daysDiff === 1 ? '1 day left' : $daysDiff . ' days left') ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Application Portal Link Column -->
                                        <td class="py-2.5 px-4 whitespace-nowrap">
                                            <?php if (!empty($schol['application_link'])): ?>
                                                <a href="<?= htmlspecialchars($schol['application_link']) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 dark:text-zinc-400 hover:text-green-600 dark:hover:text-green-400 transition-colors" title="<?= htmlspecialchars($schol['application_link']) ?>">
                                                    <span>Apply Link</span>
                                                    <i class="ph-bold ph-arrow-square-out text-[11px] opacity-60"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 dark:text-zinc-500 italic">Direct Inquiry</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Actions Column -->
                                        <td class="py-2.5 px-4 text-right align-middle whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1">
                                                <?php if ($isAdmin): ?>
                                                    <button type="button" onclick="editScholarship(<?= htmlspecialchars(json_encode($schol), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect" title="Edit Opportunity">
                                                        <i class="ph-bold ph-pencil-simple text-xs"></i>
                                                    </button>
                                                    <a href="manage_scholarships.php?delete=<?= $schol['id'] ?><?= $filterQuery ?>" onclick="return confirm('Confirm Deletion: Permanently remove this scholarship opportunity?')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Delete Opportunity">
                                                        <i class="ph-bold ph-trash text-xs"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-10 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <i class="ph-bold ph-graduation-cap text-2xl opacity-40 mb-1 block mx-auto"></i>
                                        <p class="text-xs font-semibold text-slate-600 dark:text-zinc-400">No scholarship records found</p>
                                        <?php if (!empty($search_query) || ($status_filter !== 'All' && !empty($status_filter))): ?>
                                            <a href="manage_scholarships.php" class="mt-2.5 inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
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
                    <span>Showing <strong class="text-slate-700 dark:text-zinc-300"><?= number_format($current_filtered_count) ?></strong> of <?= number_format($total_scholarships) ?></span>
                    <span>TALA OS &bull; Scholarship Registry</span>
                </div>

            </div>

        </div>
    </main>

    <?php if ($isAdmin): ?>
    <!-- SCHOLARSHIP CREATE & EDIT MODAL (Minimal Modern Scale-Fade Pop-up Effect - Reference: manage_popup.php) -->
    <div id="scholarshipModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeScholarshipModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-xl md:max-w-2xl w-full p-5 md:p-7 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800" id="scholarshipModalBox">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-5 border-b border-slate-100 dark:border-zinc-800 pb-3.5">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm">
                        <i class="ph-bold ph-graduation-cap"></i>
                    </div>
                    <div>
                        <h2 id="modalFormTitle" class="text-sm md:text-base font-extrabold text-slate-900 dark:text-white tracking-tight select-none">Post New Scholarship</h2>
                        <p id="modalFormSubtitle" class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium">Post grant guidelines, eligibility, and deadline</p>
                    </div>
                </div>
                <button type="button" onclick="closeScholarshipModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 flex items-center justify-center tap-effect transition-all duration-200 active:scale-[0.95]" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form id="scholarshipModalForm" action="manage_scholarships.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3.5 max-h-[75vh] overflow-y-auto custom-scrollbar pr-1">
                <input type="hidden" name="save_scholarship" value="1">
                <input type="hidden" name="scholarship_id" id="modal_scholarship_id" value="">

                <!-- Opportunity Title -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Opportunity Title *</label>
                    <input type="text" name="title" id="modal_title" required placeholder="e.g. LGU Municipal College Scholarship Grant 2026" class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                </div>

                <!-- Provider / Grantor -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Provider / Grantor *</label>
                    <input type="text" name="provider" id="modal_provider" required placeholder="e.g. LGU Tumauini, CHED, DOST" class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                </div>

                <!-- Application Deadline -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Application Deadline *</label>
                    <input type="date" name="deadline" id="modal_deadline" required class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors cursor-pointer">
                </div>

                <!-- Application Portal Link -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Application Portal Link (Optional)</label>
                    <input type="url" name="application_link" id="modal_application_link" placeholder="https://example.com/apply" class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                </div>

                <!-- Description & Qualifications -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Description & Qualifications *</label>
                    <textarea name="description" id="modal_description" rows="3" required placeholder="Outline eligibility guidelines, document requirements, and benefits..." class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors resize-none custom-scrollbar"></textarea>
                </div>

                <!-- Modal Footer -->
                <div class="md:col-span-2 flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button" onclick="closeScholarshipModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white font-bold text-xs md:text-sm tap-effect transition-all duration-200 active:scale-[0.97]">
                        Cancel
                    </button>
                    <button type="submit" id="modalSubmitBtn" class="bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-200 text-xs md:text-sm tap-effect active:scale-[0.97] flex items-center justify-center gap-2 shadow-none group">
                        <span id="modalSubmitText">Publish Opportunity</span>
                        <i class="ph-bold ph-paper-plane-tilt text-xs group-hover:translate-x-0.5 transition-transform duration-200"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

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
        <a href="manage_scholarships.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-graduation-cap text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Grants</span>
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

        // Open Scholarship Modal (Create or Edit with smooth scale-up modern pop-up effect)
        function openScholarshipModal(data = null) {
            const modal = document.getElementById('scholarshipModalOverlay');
            const box = document.getElementById('scholarshipModalBox');
            const form = document.getElementById('scholarshipModalForm');
            if (!modal || !box) return;

            if (data) {
                // Edit Mode
                document.getElementById('modal_scholarship_id').value = data.id || '';
                document.getElementById('modal_title').value = data.title || '';
                document.getElementById('modal_provider').value = data.provider || '';
                document.getElementById('modal_deadline').value = data.deadline || '';
                document.getElementById('modal_application_link').value = data.application_link || '';
                document.getElementById('modal_description').value = data.description || '';

                document.getElementById('modalFormTitle').textContent = 'Edit Scholarship';
                document.getElementById('modalFormSubtitle').textContent = 'Update opportunity details and guidelines';
                document.getElementById('modalSubmitText').textContent = 'Save Changes';
            } else {
                // Create Mode
                if (form) form.reset();
                document.getElementById('modal_scholarship_id').value = '';

                document.getElementById('modalFormTitle').textContent = 'Post New Scholarship';
                document.getElementById('modalFormSubtitle').textContent = 'Post grant guidelines, eligibility, and deadline';
                document.getElementById('modalSubmitText').textContent = 'Publish Opportunity';
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

        function closeScholarshipModal() {
            const modal = document.getElementById('scholarshipModalOverlay');
            const box = document.getElementById('scholarshipModalBox');
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

        function editScholarship(data) {
            openScholarshipModal(data);
        }

        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('scholarshipModalOverlay');
                if (modal && !modal.classList.contains('hidden')) {
                    closeScholarshipModal();
                }
            }
        });
    </script>

    <!-- Genesis AI Assistant Widget -->
    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include_once __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>