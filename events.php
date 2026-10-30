<?php
// events.php - Event Management Console (TALA OS Standard - Reference: manage_popup.php)
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

// Handle Add / Edit Event (Admins Only)
if ($isAdmin && ($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && (isset($_POST['save_event']) || isset($_POST['add_event']) || isset($_POST['title']))) {
    $id = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : date('Y-m-d H:i:s');
    $location = trim($_POST['location'] ?? '');
    $limit = !empty($_POST['limit']) ? (int)$_POST['limit'] : 100;
    $status = (!empty($_POST['status']) && in_array($_POST['status'], ['Upcoming', 'Completed'])) ? $_POST['status'] : 'Upcoming';
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

    $image_path = null;
    $hasNewImage = false;

    // Handle Image Upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/events/';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
        
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $filename = 'evt_' . time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = $target_file;
                $hasNewImage = true;
            }
        }
    }

    try {
        if (!empty($id)) {
            // UPDATE
            $stmtOld = $pdo->prepare("SELECT image_path FROM events WHERE id = ?");
            $stmtOld->execute([$id]);
            $oldImg = $stmtOld->fetchColumn();

            if ($remove_image) {
                if (!empty($oldImg) && file_exists($oldImg) && strpos($oldImg, 'uploads/') === 0) {
                    @unlink($oldImg);
                }
                $stmt = $pdo->prepare("UPDATE events SET title = ?, details = ?, event_date = ?, location = ?, participant_limit = ?, status = ?, image_path = NULL WHERE id = ?");
                $stmt->execute([$title, $details, $event_date, $location, $limit, $status, $id]);
            } elseif ($hasNewImage) {
                if (!empty($oldImg) && file_exists($oldImg) && strpos($oldImg, 'uploads/') === 0) {
                    @unlink($oldImg);
                }
                $stmt = $pdo->prepare("UPDATE events SET title = ?, details = ?, event_date = ?, location = ?, participant_limit = ?, status = ?, image_path = ? WHERE id = ?");
                $stmt->execute([$title, $details, $event_date, $location, $limit, $status, $image_path, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE events SET title = ?, details = ?, event_date = ?, location = ?, participant_limit = ?, status = ? WHERE id = ?");
                $stmt->execute([$title, $details, $event_date, $location, $limit, $status, $id]);
            }
            header("Location: events.php");
            exit();
        } else {
            // CREATE
            $stmt = $pdo->prepare("INSERT INTO events (title, details, image_path, event_date, location, participant_limit, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                $details,
                $image_path,
                $event_date,
                $location,
                $limit,
                $status,
                $_SESSION['admin_username'] ?? 'Admin'
            ]);
            header("Location: events.php");
            exit();
        }
    } catch (PDOException $e) {
        die("Error saving event: " . $e->getMessage());
    }
}

// Handle Delete Event (Admins Only)
if ($isAdmin && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT image_path FROM events WHERE id = ?");
    $stmt->execute([$id]);
    $oldImg = $stmt->fetchColumn();
    if (!empty($oldImg) && file_exists($oldImg) && strpos($oldImg, 'uploads/') === 0) {
        @unlink($oldImg);
    }

    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$id]);
    
    $redirect = 'events.php';
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

$sql = "SELECT e.*, 
        (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id) as registered_count 
        FROM events e WHERE 1=1";
$params = [];

if ($status_filter === 'Upcoming') {
    $sql .= " AND (e.status IS NULL OR e.status != 'Completed')";
} elseif ($status_filter === 'Completed') {
    $sql .= " AND e.status = 'Completed'";
}

if (!empty($search_query)) {
    $sql .= " AND (e.title LIKE ? OR e.location LIKE ? OR e.details LIKE ?)";
    $term = "%{$search_query}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY e.event_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Telemetry counts for header KPI chips
$total_events = (int)$pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$completed_events = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE status = 'Completed'")->fetchColumn();
$upcoming_events = max(0, $total_events - $completed_events);
$total_registered = (int)$pdo->query("SELECT COUNT(*) FROM event_registrations")->fetchColumn();
$current_filtered_count = count($events);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Event Management | TALA OS</title>
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
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- STANDARD TALA OS SIDEBAR (ROLE-BASED) -->
    <?php 
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
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
                        Event Management
                    </h1>
                </div>
                
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-calendar"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Upcoming</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($upcoming_events) ?></span>
                        </div>
                    </div>

                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-check-circle"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Completed</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($completed_events) ?></span>
                        </div>
                    </div>

                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-users-three"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Attendees</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_registered) ?></span>
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
                        ['param' => '', 'val' => '', 'label' => 'All Events', 'count' => $total_events, 'active' => empty($status_filter) || $status_filter === 'All'],
                        ['param' => 'status', 'val' => 'Upcoming', 'label' => 'Upcoming', 'count' => $upcoming_events, 'active' => ($status_filter === 'Upcoming')],
                        ['param' => 'status', 'val' => 'Completed', 'label' => 'Completed', 'count' => $completed_events, 'active' => ($status_filter === 'Completed')],
                    ];
                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        $url = 'events.php';
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
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search program, venue..." class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-800 dark:text-zinc-200 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                    </form>

                    <?php if (!empty($search_query)): ?>
                        <a href="events.php<?= ($status_filter !== 'All') ? '?status=' . urlencode($status_filter) : '' ?>" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Search">
                            <i class="ph-bold ph-x text-xs"></i>
                        </a>
                    <?php endif; ?>

                    <?php if ($isAdmin): ?>
                        <button id="newEventBtn" type="button" onclick="openEventModal()" class="px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group">
                            <i class="ph-bold ph-plus text-xs transition-transform duration-300 group-hover:scale-110"></i>
                            <span>New Event</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CONSOLIDATED MINIMAL EVENT REGISTRY TABLE (Minimal Table Architecture - Reference: manage_popup.php) -->
            <div class="glass-card rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Minimal Header Strip -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex justify-between items-center transition-colors flex-shrink-0 border-b border-slate-100 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Event Registry</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                            <?= number_format($current_filtered_count) ?>
                        </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400 dark:text-zinc-500">Community events & youth assemblies</span>
                </div>

                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[720px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider bg-slate-50/90 dark:bg-zinc-900/90 backdrop-blur-sm transition-colors border-b border-slate-100 dark:border-zinc-800">
                                <th class="py-2 px-4">Event</th>
                                <th class="py-2 px-4">Schedule</th>
                                <th class="py-2 px-4">Roster</th>
                                <th class="py-2 px-4">Status</th>
                                <th class="py-2 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs font-medium divide-y divide-slate-100/80 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($events)): ?>
                                <?php foreach ($events as $ev): 
                                    $hasMedia = (!empty($ev['image_path']) && file_exists($ev['image_path']));
                                    $registered = (int)($ev['registered_count'] ?? 0);
                                    $limit = max(1, (int)($ev['participant_limit'] ?? 1));
                                    $pct = min(100, round(($registered / $limit) * 100));
                                    $isCompleted = (($ev['status'] ?? '') === 'Completed');
                                    $isFull = ($registered >= $limit);
                                    $filterQuery = ($status_filter !== 'All' ? '&status=' . urlencode($status_filter) : '') . (!empty($search_query) ? '&search=' . urlencode($search_query) : '');
                                ?>
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors group">
                                        <!-- Event Column -->
                                        <td class="py-2.5 px-4">
                                            <div class="flex items-center gap-3">
                                                <?php if ($hasMedia): ?>
                                                    <div class="w-8 h-8 rounded-lg overflow-hidden bg-slate-100 dark:bg-zinc-800 flex-shrink-0 relative group/thumb">
                                                        <img src="<?= htmlspecialchars($ev['image_path']) ?>" alt="Thumbnail" class="w-full h-full object-cover group-hover/thumb:scale-110 transition-transform duration-200">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                                        <i class="ph-bold ph-calendar"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="min-w-0 max-w-xs sm:max-w-sm md:max-w-md">
                                                    <p class="font-bold text-slate-900 dark:text-white text-xs leading-snug truncate group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors">
                                                        <?= htmlspecialchars($ev['title']) ?>
                                                    </p>
                                                    <p class="text-[11px] text-slate-400 dark:text-zinc-500 truncate mt-0.5">
                                                        <span class="inline-flex items-center gap-1"><i class="ph-bold ph-map-pin text-[10px]"></i> <?= htmlspecialchars($ev['location']) ?></span>
                                                        <?php if (!empty($ev['details'])): ?>
                                                            &bull; <?= htmlspecialchars($ev['details']) ?>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Schedule Column -->
                                        <td class="py-2.5 px-4 whitespace-nowrap text-xs text-slate-600 dark:text-zinc-400 font-semibold">
                                            <span class="inline-flex items-center gap-1.5">
                                                <i class="ph-bold ph-clock text-slate-400"></i>
                                                <span><?= date('M j, Y • g:i A', strtotime($ev['event_date'])) ?></span>
                                            </span>
                                        </td>

                                        <!-- Roster Column -->
                                        <td class="py-2.5 px-4 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold <?= $isFull ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-zinc-300' ?>">
                                                    <?= $registered ?><span class="text-slate-400 dark:text-zinc-500 font-normal">/<?= $limit ?></span>
                                                </span>
                                                <div class="w-14 bg-slate-100 dark:bg-zinc-800 rounded-full h-1.5 overflow-hidden">
                                                    <?php 
                                                        $barColor = $isCompleted ? 'bg-slate-400 dark:bg-zinc-600' : ($isFull ? 'bg-rose-500' : 'bg-[#15803d] dark:bg-[#16a34a]');
                                                    ?>
                                                    <div class="<?= $barColor ?> h-full rounded-full transition-all duration-300" style="width: <?= $pct ?>%"></div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Status Column -->
                                        <td class="py-2.5 px-4 whitespace-nowrap">
                                            <?php if ($isCompleted): ?>
                                                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 dark:text-zinc-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400 dark:bg-zinc-500"></span>
                                                    <span>Completed</span>
                                                </span>
                                            <?php elseif ($isFull): ?>
                                                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-rose-600 dark:text-rose-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                    <span>Full Roster</span>
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1.5 text-xs font-bold text-green-600 dark:text-green-400">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                                    <span>Upcoming</span>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Actions Column -->
                                        <td class="py-2.5 px-4 text-right align-middle whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1">
                                                <a href="manage_event.php?id=<?= $ev['id'] ?>" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect" title="Manage Roster & Ledger">
                                                    <i class="ph-bold ph-users-three text-xs"></i>
                                                </a>
                                                <?php if ($isAdmin): ?>
                                                    <button type="button" onclick="editEvent(<?= htmlspecialchars(json_encode($ev), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect" title="Edit Event">
                                                        <i class="ph-bold ph-pencil-simple text-xs"></i>
                                                    </button>
                                                    <a href="events.php?delete=<?= $ev['id'] ?><?= $filterQuery ?>" onclick="return confirm('Confirm Deletion: Permanently delete this event and its registration logs?')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Delete Event">
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
                                        <i class="ph-bold ph-calendar text-2xl opacity-40 mb-1 block mx-auto"></i>
                                        <p class="text-xs font-semibold text-slate-600 dark:text-zinc-400">No event records found</p>
                                        <?php if (!empty($search_query) || ($status_filter !== 'All' && !empty($status_filter))): ?>
                                            <a href="events.php" class="mt-2.5 inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
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
                    <span>Showing <strong class="text-slate-700 dark:text-zinc-300"><?= number_format($current_filtered_count) ?></strong> of <?= number_format($total_events) ?></span>
                    <span>TALA OS &bull; Event Registry</span>
                </div>

            </div>

        </div>
    </main>

    <?php if ($isAdmin): ?>
    <!-- EVENT CREATE & EDIT MODAL (Minimal Modern Scale-Fade Pop-up Effect - Reference: manage_popup.php) -->
    <div id="eventModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeEventModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-xl md:max-w-2xl w-full p-5 md:p-7 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800" id="eventModalBox">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-5 border-b border-slate-100 dark:border-zinc-800 pb-3.5">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm">
                        <i class="ph-bold ph-calendar-plus"></i>
                    </div>
                    <div>
                        <h2 id="modalFormTitle" class="text-sm md:text-base font-extrabold text-slate-900 dark:text-white tracking-tight select-none">Create New Event</h2>
                        <p id="modalFormSubtitle" class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium">Configure schedule, venue, and roster capacity</p>
                    </div>
                </div>
                <button type="button" onclick="closeEventModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 flex items-center justify-center tap-effect transition-all duration-200 active:scale-[0.95]" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form id="eventModalForm" action="events.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-3.5 max-h-[75vh] overflow-y-auto custom-scrollbar pr-1">
                <input type="hidden" name="save_event" value="1">
                <input type="hidden" name="event_id" id="modal_event_id" value="">

                <!-- Event Title -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Event Title *</label>
                    <input type="text" name="title" id="modal_title" required placeholder="e.g. Linggo ng Kabataan 2026 Opening" class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                </div>

                <!-- Date & Time -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Date & Time *</label>
                    <input type="datetime-local" name="event_date" id="modal_event_date" required class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors cursor-pointer">
                </div>

                <!-- Slot Limit -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Slot Limit *</label>
                    <input type="number" name="limit" id="modal_limit" required min="1" placeholder="e.g. 150" class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                </div>

                <!-- Location / Venue -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Location / Venue *</label>
                    <input type="text" name="location" id="modal_location" required placeholder="e.g. Municipal Gymnasium" class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Event Status *</label>
                    <select name="status" id="modal_status" class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors cursor-pointer">
                        <option value="Upcoming">Upcoming</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>

                <!-- Photo Attachment -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Event Banner Graphic (Optional)</label>
                    <input type="file" name="image" id="modal_image" accept="image/*" class="w-full text-xs text-slate-500 dark:text-zinc-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 dark:file:bg-zinc-800 file:text-slate-700 dark:file:text-zinc-300 hover:file:bg-slate-200 dark:hover:file:bg-zinc-700 bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 rounded-xl p-1.5 cursor-pointer transition-colors">
                    
                    <div id="modalCurrentImageContainer" class="hidden mt-2 flex items-center justify-between p-2 rounded-xl bg-slate-50 dark:bg-zinc-900/40 border border-slate-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2">
                            <img id="modalCurrentImageThumb" src="" class="w-10 h-10 object-cover rounded-lg border border-slate-200 dark:border-zinc-700">
                            <div>
                                <p class="text-[11px] font-bold text-green-600 dark:text-green-500">Banner Photo Attached</p>
                                <p class="text-[10px] text-slate-400 dark:text-zinc-500">Upload new file to replace</p>
                            </div>
                        </div>
                        <label class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-zinc-400 cursor-pointer">
                            <input type="checkbox" name="remove_image" id="modal_remove_image" value="1" class="rounded border-slate-300 dark:border-zinc-700 text-green-600 focus:ring-0">
                            <span>Remove Banner</span>
                        </label>
                    </div>
                </div>

                <!-- Program Details & Guidelines -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Program Details & Guidelines *</label>
                    <textarea name="details" id="modal_details" rows="3" required placeholder="Provide program agenda, requirements, and instructions..." class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors resize-none custom-scrollbar"></textarea>
                </div>

                <!-- Modal Footer -->
                <div class="md:col-span-2 flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button" onclick="closeEventModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white font-bold text-xs md:text-sm tap-effect transition-all duration-200 active:scale-[0.97]">
                        Cancel
                    </button>
                    <button type="submit" id="modalSubmitBtn" class="bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-200 text-xs md:text-sm tap-effect active:scale-[0.97] flex items-center justify-center gap-2 shadow-none group">
                        <span id="modalSubmitText">Publish Event</span>
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
        <a href="events.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-calendar text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Events</span>
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

        // Open Event Modal (Create or Edit with smooth scale-up modern pop-up effect)
        function openEventModal(data = null) {
            const modal = document.getElementById('eventModalOverlay');
            const box = document.getElementById('eventModalBox');
            const form = document.getElementById('eventModalForm');
            if (!modal || !box) return;

            if (data) {
                // Edit Mode
                document.getElementById('modal_event_id').value = data.id || '';
                document.getElementById('modal_title').value = data.title || '';
                document.getElementById('modal_location').value = data.location || '';
                document.getElementById('modal_limit').value = data.participant_limit || 100;
                document.getElementById('modal_status').value = data.status || 'Upcoming';
                document.getElementById('modal_details').value = data.details || '';

                if (data.event_date) {
                    const dt = data.event_date.replace(' ', 'T').substring(0, 16);
                    document.getElementById('modal_event_date').value = dt;
                }

                document.getElementById('modalFormTitle').textContent = 'Edit Event';
                document.getElementById('modalFormSubtitle').textContent = 'Update event schedule, venue, and parameters';
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
                document.getElementById('modal_event_id').value = '';
                document.getElementById('modal_status').value = 'Upcoming';

                document.getElementById('modalFormTitle').textContent = 'Create New Event';
                document.getElementById('modalFormSubtitle').textContent = 'Configure schedule, venue, and roster capacity';
                document.getElementById('modalSubmitText').textContent = 'Publish Event';

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

        function closeEventModal() {
            const modal = document.getElementById('eventModalOverlay');
            const box = document.getElementById('eventModalBox');
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

        function editEvent(data) {
            openEventModal(data);
        }

        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('eventModalOverlay');
                if (modal && !modal.classList.contains('hidden')) {
                    closeEventModal();
                }
            }
        });
    </script>

    <!-- Genesis AI Assistant Widget -->
    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>