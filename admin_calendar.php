<?php
// admin_calendar.php - Master Calendar & Scheduling Console
// Standardized to TALA OS Design System (Reference: manage_disclosure.php & admin_resolution.php)
ob_start();
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
require_once 'db_connect.php';

// Strict Access Control: Admin or SK Official
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$user_role = $_SESSION['role'] ?? 'sub-admin';
$user_barangay = $_SESSION['barangay_assigned'] ?? $_SESSION['admin_barangay'] ?? '';
$displayName = $_SESSION['admin_username'] ?? 'Official';

// Flash Message Handling (Matching manage_disclosure.php)
$flash_msg = $_SESSION['flash_msg'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// Auto-upgrade and ensure events table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        details TEXT NULL,
        image_path VARCHAR(255) NULL,
        event_date DATETIME NOT NULL,
        location VARCHAR(255) NULL,
        participant_limit INT DEFAULT 100,
        status VARCHAR(50) DEFAULT 'Upcoming',
        created_by VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $cols = $pdo->query("SHOW COLUMNS FROM events")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('status', $cols)) {
        $pdo->exec("ALTER TABLE events ADD COLUMN status VARCHAR(50) DEFAULT 'Upcoming'");
    }
    if (!in_array('participant_limit', $cols)) {
        $pdo->exec("ALTER TABLE events ADD COLUMN participant_limit INT DEFAULT 100");
    }
} catch (Exception $e) {}

// CRUD: Handle Add or Edit Event (Admins / Officials)
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['save_event'])) {
    $id = !empty($_POST['event_id']) ? (int)$_POST['event_id'] : null;
    $title = trim($_POST['title'] ?? 'Untitled Event');
    $details = trim($_POST['details'] ?? '');
    $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : date('Y-m-d H:i:s');
    $location = trim($_POST['location'] ?? 'Municipal Venue');
    $limit = !empty($_POST['participant_limit']) ? (int)$_POST['participant_limit'] : 100;
    $status = (!empty($_POST['status']) && in_array($_POST['status'], ['Upcoming', 'Completed', 'Cancelled'])) ? $_POST['status'] : 'Upcoming';

    try {
        if (!empty($id)) {
            $stmt = $pdo->prepare("UPDATE events SET title = ?, details = ?, event_date = ?, location = ?, participant_limit = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $details, $event_date, $location, $limit, $status, $id]);
            $_SESSION['flash_msg'] = "Event '{$title}' updated successfully.";
            $_SESSION['flash_type'] = "success";
        } else {
            $stmt = $pdo->prepare("INSERT INTO events (title, details, event_date, location, participant_limit, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $details, $event_date, $location, $limit, $status, $displayName]);
            $_SESSION['flash_msg'] = "Event '{$title}' scheduled successfully.";
            $_SESSION['flash_type'] = "success";
        }
    } catch (PDOException $e) {
        $_SESSION['flash_msg'] = "Database error: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
    }

    header("Location: admin_calendar.php");
    exit();
}

// CRUD: Handle Delete Event
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$del_id]);
        $_SESSION['flash_msg'] = "Event removed from master schedule.";
        $_SESSION['flash_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['flash_msg'] = "Failed to remove event.";
        $_SESSION['flash_type'] = "error";
    }
    header("Location: admin_calendar.php");
    exit();
}

// Fetch all events with rich details for Calendar rendering
try {
    $query = "SELECT id, title, details, event_date, location, participant_limit, status FROM events ORDER BY event_date ASC";
    $stmt = $pdo->query($query);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $events_json = json_encode($events, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
} catch (Exception $e) {
    $events = [];
    $events_json = '[]';
}

// KPI telemetry calculations
$total_events = count($events);
$upcoming_events = 0;
$completed_events = 0;
$this_month_events = 0;
$todayStr = date('Y-m-d');
$currentMonth = date('Y-m');

foreach ($events as $ev) {
    $evDateStr = substr($ev['event_date'], 0, 10);
    $evMonthStr = substr($ev['event_date'], 0, 7);
    
    if (($ev['status'] ?? '') === 'Completed') {
        $completed_events++;
    } elseif ($evDateStr >= $todayStr) {
        $upcoming_events++;
    }
    
    if ($evMonthStr === $currentMonth) {
        $this_month_events++;
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
    <title>Master Calendar | TALA OS</title>
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
        
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        
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
        
        /* Calendar cell styling */
        .cal-cell { min-height: 58px; height: 100%; transition: all 0.2s ease; }
        @media (min-width: 1024px) { .cal-cell { min-height: 0; } }
        
        .cal-cell:hover { background-color: rgba(241, 245, 249, 0.8); }
        .dark .cal-cell:hover { background-color: rgba(39, 39, 42, 0.7); }

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

        /* Tap effect for mobile elements */
        .tap-effect:active { transform: scale(0.97); opacity: 0.85; transition: transform 0.1s; }

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
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- STANDARD TALA OS SIDEBAR (ROLE-BASED) -->
    <?php 
    if ($isAdmin) { include 'sidebar_admin.php'; } 
    else { include 'sidebar_sk.php'; } 
    ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-2.5 md:space-y-3">
            
            <!-- COMMAND CENTER HEADER WITH THREE UNIFIED GREEN KPI BADGES -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                        Master Calendar
                    </h1>
                </div>
                
                <!-- Section Cards: 3 KPI Badges in Unified Solid TALA Green Icon Styling -->
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Total Scheduled Events -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-calendar text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Scheduled</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_events) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Upcoming Events -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-clock text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Upcoming</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($upcoming_events) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Completed Events -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-check-circle text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Completed</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($completed_events) ?></span>
                        </div>
                    </div>

                    <!-- Header Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[36px] h-[36px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
                        <i id="headerThemeIcon" class="ph-bold ph-moon text-base sm:text-lg transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- NOTIFICATIONS BANNER (Matching manage_disclosure.php) -->
            <?php if (!empty($flash_msg)): ?>
                <div class="<?= $flash_type === 'success' ? 'bg-green-50/85 dark:bg-green-500/10 text-green-700 dark:text-green-400' : 'bg-red-50/85 dark:bg-red-500/10 text-red-700 dark:text-red-400' ?> px-4 py-2.5 rounded-2xl flex items-center justify-between gap-3 text-xs md:text-sm font-bold shadow-none backdrop-blur-md transition-colors border-0 flex-shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <i class="ph-bold <?= $flash_type === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-lg flex-shrink-0"></i>
                        <span class="truncate"><?= htmlspecialchars($flash_msg) ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="w-6 h-6 rounded-lg flex items-center justify-center hover:bg-black/5 dark:hover:bg-white/10 transition-colors tap-effect" aria-label="Dismiss">
                        <i class="ph-bold ph-x text-xs"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- FILTER, SEARCH & ACTION CONTROL BAR (Matching manage_disclosure.php) -->
            <div class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-2.5 flex-shrink-0">
                <!-- Status Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full xl:w-auto custom-scrollbar py-0.5 flex-shrink-0" id="filterPillsContainer">
                    <button type="button" onclick="setCalendarFilter('All', this)" class="filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect btn-yellow-glow-r bg-[#15803d] text-white">
                        <span class="relative z-10">All Events</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-white/20 text-white relative z-10"><?= $total_events ?></span>
                    </button>
                    <button type="button" onclick="setCalendarFilter('Upcoming', this)" class="filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white">
                        <span class="relative z-10">Upcoming</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300 relative z-10"><?= $upcoming_events ?></span>
                    </button>
                    <button type="button" onclick="setCalendarFilter('ThisMonth', this)" class="filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white">
                        <span class="relative z-10">This Month</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300 relative z-10"><?= $this_month_events ?></span>
                    </button>
                    <button type="button" onclick="setCalendarFilter('Completed', this)" class="filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white">
                        <span class="relative z-10">Completed</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300 relative z-10"><?= $completed_events ?></span>
                    </button>
                </div>

                <!-- Live Search & Action Buttons -->
                <div class="flex flex-wrap items-center gap-2 w-full xl:w-auto">
                    <!-- Real-Time Search Input -->
                    <div class="relative w-full sm:w-auto sm:flex-1 md:w-56 lg:w-64">
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" id="calendarSearchInput" placeholder="Search event, venue, or details..." oninput="handleCalendarSearch(this.value)" class="w-full pl-8 pr-3 py-1.5 bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 rounded-xl text-xs font-semibold focus:outline-none transition-colors border-0">
                    </div>

                    <!-- List View Switcher -->
                    <a href="events.php" class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold text-xs tap-effect flex items-center gap-1.5 transition-all">
                        <i class="ph-bold ph-list-bullets text-xs"></i>
                        <span>List View</span>
                    </a>

                    <!-- New Event Trigger (Opens #eventModal) -->
                    <button type="button" onclick="openCreateEventModal()" class="btn-yellow-glow-r px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group">
                        <i class="ph-bold ph-plus text-xs transition-transform duration-300 group-hover:scale-110 relative z-10"></i>
                        <span class="relative z-10">Schedule Event</span>
                    </button>
                </div>
            </div>

            <!-- DUAL-ZONE CALENDAR VIEWPORT (3 COLS CALENDAR + 1 COL UPCOMING SCHEDULE) -->
            <div class="grid grid-cols-1 xl:grid-cols-4 gap-3 md:gap-4 flex-1 min-h-0">
                
                <!-- ZONE 1: MAIN CALENDAR GRID CARD (3 COLS) -->
                <div class="xl:col-span-3 glass-card rounded-2xl p-3 md:p-4 flex flex-col overflow-hidden h-full min-h-0">
                    
                    <!-- Calendar Controls Bar -->
                    <div class="flex items-center justify-between mb-2 md:mb-3 gap-2 flex-shrink-0">
                        <h2 id="monthYearDisplay" class="text-base md:text-xl font-black text-slate-900 dark:text-white transition-colors tracking-tight">Month Year</h2>
                        <div class="flex items-center gap-1">
                            <button id="prevMonth" class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect" title="Previous Month">
                                <i class="ph-bold ph-caret-left text-sm"></i>
                            </button>
                            <button id="todayBtn" class="px-3 h-8 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 font-bold text-xs md:text-sm hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
                                Today
                            </button>
                            <button id="nextMonth" class="w-8 h-8 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect" title="Next Month">
                                <i class="ph-bold ph-caret-right text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Swipeable / Responsive Grid Wrapper -->
                    <div class="overflow-x-auto custom-scrollbar flex-1 flex flex-col min-h-0">
                        <div class="min-w-[550px] lg:min-w-0 flex-1 flex flex-col min-h-0">
                            
                            <!-- Day Column Headers -->
                            <div class="grid grid-cols-7 gap-1 md:gap-1.5 mb-1.5 text-center flex-shrink-0">
                                <div class="text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500 py-1">Sun</div>
                                <div class="text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500 py-1">Mon</div>
                                <div class="text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500 py-1">Tue</div>
                                <div class="text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500 py-1">Wed</div>
                                <div class="text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500 py-1">Thu</div>
                                <div class="text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500 py-1">Fri</div>
                                <div class="text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500 py-1">Sat</div>
                            </div>

                            <!-- Calendar Body (Populated Dynamically by JavaScript) -->
                            <div id="calendarGrid" class="grid grid-cols-7 gap-1 md:gap-1.5 flex-1 min-h-0 [grid-auto-rows:1fr]"></div>
                        </div>
                    </div>
                </div>

                <!-- ZONE 2: UPCOMING EVENTS SCHEDULE CARD (1 COL) -->
                <div class="xl:col-span-1 glass-card p-3 md:p-4 rounded-2xl flex flex-col h-full min-h-0 overflow-hidden">
                    <div class="flex items-center justify-between mb-2 md:mb-3 flex-shrink-0 pb-2 border-b border-slate-100 dark:border-zinc-800">
                        <h3 class="text-xs md:text-sm font-extrabold text-slate-900 dark:text-white flex items-center gap-1.5 transition-colors tracking-tight">
                            <i class="ph-bold ph-calendar-star text-green-600 dark:text-green-500"></i>
                            <span>Upcoming Schedule</span>
                        </h3>
                        <span id="upcomingBadgeCount" class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider">Top Stream</span>
                    </div>
                    
                    <div id="upcomingEventsList" class="flex-1 overflow-y-auto space-y-2 md:space-y-2.5 custom-scrollbar pr-1 min-h-0">
                        <!-- Populated by JavaScript -->
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- SCHEDULE / EDIT EVENT POPUP MODAL (Matching manage_disclosure.php #publishModal) -->
    <div id="eventModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeEventModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-lg w-full p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800 max-h-[90vh] overflow-y-auto custom-scrollbar" id="eventModalBox">
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-4 border-b border-slate-100 dark:border-zinc-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-green-500/10 text-green-600 dark:text-green-500 rounded-xl flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-fill ph-calendar-plus"></i>
                    </div>
                    <div>
                        <h3 id="eventModalTitle" class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight">Schedule New Event</h3>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono mt-0.5">Youth Development Master Schedule</p>
                    </div>
                </div>
                <button type="button" onclick="closeEventModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>
            
            <!-- Modal Form -->
            <form action="admin_calendar.php" method="POST" id="eventScheduleForm" class="space-y-3">
                <input type="hidden" name="save_event" value="1">
                <input type="hidden" name="event_id" id="form_event_id" value="">
                
                <!-- Event Title -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Event Title *</label>
                    <input type="text" name="title" id="form_title" placeholder="e.g. Linggo ng Kabataan Sports Clinic 2026" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors border-0" required>
                </div>

                <!-- Date & Time + Status -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Date & Time *</label>
                        <input type="datetime-local" name="event_date" id="form_date" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors border-0" required>
                    </div>
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Standing Status *</label>
                        <select name="status" id="form_status" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors cursor-pointer border-0">
                            <option value="Upcoming">Upcoming</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <!-- Location & Participant Limit -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Venue / Location</label>
                        <input type="text" name="location" id="form_location" placeholder="e.g. Balug Multi-Purpose Hall" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors border-0">
                    </div>
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Participant Limit</label>
                        <input type="number" name="participant_limit" id="form_limit" value="100" min="1" max="5000" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors border-0">
                    </div>
                </div>

                <!-- Details / Description -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Description & Narrative</label>
                    <textarea name="details" id="form_details" rows="3" placeholder="Outline activities, target demographic, or requirements..." class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors border-0 resize-none custom-scrollbar"></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100 dark:border-zinc-800 mt-2">
                    <button type="button" onclick="closeEventModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm transition-all tap-effect">
                        Cancel
                    </button>
                    <button type="submit" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2 px-5 rounded-xl transition-all text-xs md:text-sm tap-effect flex items-center gap-1.5 shadow-none group">
                        <i class="ph-bold ph-floppy-disk relative z-10"></i>
                        <span id="formSubmitBtnText" class="relative z-10">Save Schedule</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- EVENT DOSSIER INSPECTOR MODAL -->
    <div id="detailModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeDetailModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-lg w-full p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800" id="detailModalBox">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 pb-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm">
                        <i class="ph-fill ph-calendar-star"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white leading-tight">Event Dossier</h3>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono" id="modalDetailStatus">Upcoming</p>
                    </div>
                </div>
                <button type="button" onclick="closeDetailModal()" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 flex items-center justify-center tap-effect transition-all" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Content -->
            <div class="space-y-3 text-xs">
                <!-- Title Card -->
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-800/60">
                    <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block mb-1">Event Title</span>
                    <h4 class="text-base font-extrabold text-slate-900 dark:text-white leading-snug" id="modalDetailTitle">-</h4>
                </div>

                <div class="grid grid-cols-2 gap-2.5 text-xs">
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-800/40">
                        <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Date & Time</span>
                        <span class="font-bold text-slate-800 dark:text-zinc-200 mt-0.5 block" id="modalDetailDate">-</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-800/40">
                        <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Location</span>
                        <span class="font-bold text-slate-800 dark:text-zinc-200 truncate mt-0.5 block" id="modalDetailLocation">-</span>
                    </div>
                </div>

                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-800/40">
                    <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Target Capacity Limit</span>
                    <span class="font-bold text-slate-800 dark:text-zinc-200 mt-0.5 block" id="modalDetailLimit">Open Admission</span>
                </div>

                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-800/40" id="modalDetailContainer">
                    <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Description & Narrative</span>
                    <p class="font-semibold text-slate-700 dark:text-zinc-300 mt-0.5 leading-relaxed whitespace-pre-line" id="modalDetailDesc">-</p>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="flex justify-between items-center pt-3 border-t border-slate-100 dark:border-zinc-800 mt-4 no-print">
                <div class="flex items-center gap-2">
                    <button type="button" id="modalDetailEditBtn" class="btn-yellow-glow-r px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center gap-1.5 transition-all">
                        <i class="ph-bold ph-pencil-simple relative z-10"></i>
                        <span class="relative z-10">Edit Event</span>
                    </button>
                    <a href="#" id="modalDetailDeleteBtn" onclick="return confirm('Permanently remove this event from the master calendar?')" class="px-3.5 py-1.5 rounded-xl bg-red-50 hover:bg-red-100 dark:bg-red-500/10 dark:hover:bg-red-500/20 text-red-600 dark:text-red-400 font-bold text-xs tap-effect flex items-center gap-1.5 transition-all">
                        <i class="ph-bold ph-trash"></i>
                        <span>Delete</span>
                    </a>
                </div>
                <button type="button" onclick="closeDetailModal()" class="px-4 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold text-xs tap-effect transition-all ml-auto">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Android Style - Reference: manage_disclosure.php) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 h-[68px] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-2xl z-50 flex justify-around items-center px-2 pb-[env(safe-area-inset-bottom)] transition-colors border-0">
        <?php if ($isAdmin): ?>
            <a href="admin_dashboard.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
                <div class="px-4 py-1 mb-1">
                    <i class="ph-bold ph-squares-four text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Dashboard</span>
            </a>
            <a href="manage_disclosure.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
                <div class="px-4 py-1 mb-1">
                    <i class="ph-bold ph-files text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Disclosure</span>
            </a>
            <a href="admin_calendar.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
                <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                    <i class="ph-fill ph-calendar text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Calendar</span>
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
            <a href="admin_calendar.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
                <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                    <i class="ph-fill ph-calendar text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Calendar</span>
            </a>
            <a href="manage_disclosure.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
                <div class="px-4 py-1 mb-1">
                    <i class="ph-bold ph-files text-2xl"></i>
                </div>
                <span class="text-[10px] font-bold">Disclosure</span>
            </a>
        <?php endif; ?>
    </nav>

    <!-- CALENDAR CORE JAVASCRIPT & INTERACTIONS -->
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

        // Theme Toggle Sync
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

        // Data & State Store
        const rawEvents = <?= $events_json ?>;
        const allEvents = rawEvents.map(e => ({ ...e, dateObj: new Date(e.event_date) }));
        let activeFilter = 'All';
        let searchQuery = '';

        let currentDate = new Date(); 
        const today = new Date();

        const calendarGrid = document.getElementById('calendarGrid');
        const monthYearDisplay = document.getElementById('monthYearDisplay');
        const upcomingList = document.getElementById('upcomingEventsList');

        // Filter Pills Handler
        function setCalendarFilter(filter, btn) {
            activeFilter = filter;
            document.querySelectorAll('#filterPillsContainer .filter-pill').forEach(el => {
                el.className = 'filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                const pillCount = el.querySelector('span:last-child');
                if (pillCount) pillCount.className = 'px-1.5 py-0.5 rounded-full text-[10px] font-black bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300 relative z-10';
            });
            if (btn) {
                btn.className = 'filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect btn-yellow-glow-r bg-[#15803d] text-white';
                const pillCount = btn.querySelector('span:last-child');
                if (pillCount) pillCount.className = 'px-1.5 py-0.5 rounded-full text-[10px] font-black bg-white/20 text-white relative z-10';
            }
            renderCalendar();
            renderUpcoming();
        }

        // Live Search Handler
        function handleCalendarSearch(query) {
            searchQuery = query.toLowerCase().trim();
            renderCalendar();
            renderUpcoming();
        }

        // Filter Evaluation
        function getFilteredEvents() {
            const todayZero = new Date();
            todayZero.setHours(0,0,0,0);
            const currentMonthStr = `${currentDate.getFullYear()}-${String(currentDate.getMonth()+1).padStart(2, '0')}`;

            return allEvents.filter(ev => {
                // Match search query
                if (searchQuery) {
                    const titleMatch = (ev.title || '').toLowerCase().includes(searchQuery);
                    const locMatch = (ev.location || '').toLowerCase().includes(searchQuery);
                    const detMatch = (ev.details || '').toLowerCase().includes(searchQuery);
                    if (!titleMatch && !locMatch && !detMatch) return false;
                }

                // Match active tab
                if (activeFilter === 'Upcoming') {
                    const d = new Date(ev.event_date);
                    return (ev.status !== 'Completed') && (d.getTime() >= todayZero.getTime());
                } else if (activeFilter === 'ThisMonth') {
                    const d = new Date(ev.event_date);
                    const mStr = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2, '0')}`;
                    return mStr === currentMonthStr;
                } else if (activeFilter === 'Completed') {
                    return ev.status === 'Completed';
                }

                return true;
            });
        }

        // Render Calendar Grid
        function renderCalendar() {
            calendarGrid.innerHTML = '';
            
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();

            const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            monthYearDisplay.textContent = `${monthNames[month]} ${year}`;

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const filteredEvents = getFilteredEvents();

            // Padding days before start of month
            for (let i = 0; i < firstDay; i++) {
                const cell = document.createElement('div');
                cell.className = 'bg-slate-50/50 dark:bg-zinc-900/30 cal-cell p-1 rounded-xl transition-colors opacity-30 select-none';
                calendarGrid.appendChild(cell);
            }

            // Actual days
            for (let day = 1; day <= daysInMonth; day++) {
                const cell = document.createElement('div');
                const isToday = day === today.getDate() && month === today.getMonth() && year === today.getFullYear();
                
                cell.className = `bg-slate-50/80 dark:bg-zinc-900/60 hover:bg-slate-100 dark:hover:bg-zinc-800 cal-cell p-1 md:p-1.5 flex flex-col gap-0.5 rounded-xl transition-colors relative group cursor-pointer min-h-0 overflow-hidden`;
                
                let dayNumberHTML = `<span class="text-xs md:text-sm font-bold leading-none ${isToday ? 'bg-[#15803d] dark:bg-[#16a34a] text-white w-5 h-5 flex items-center justify-center rounded-full font-black' : 'text-slate-700 dark:text-zinc-300'}">${day}</span>`;
                
                cell.innerHTML = `
                    <div class="flex justify-between items-center mb-0.5 flex-shrink-0">
                        ${dayNumberHTML}
                        <button type="button" class="w-4 h-4 rounded-full bg-slate-200/80 dark:bg-zinc-700/80 hover:bg-green-600 dark:hover:bg-green-500 hover:text-white text-slate-500 dark:text-zinc-400 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-all text-[10px]" title="Quick Schedule" onclick="event.stopPropagation(); quickAddEvent(${year}, ${month}, ${day});">
                            <i class="ph-bold ph-plus"></i>
                        </button>
                    </div>
                    <div class="event-container flex-1 overflow-y-auto custom-scrollbar space-y-0.5 min-h-0"></div>
                `;

                // Allow cell click to schedule or view
                cell.onclick = (e) => {
                    if (e.target.tagName !== 'BUTTON') {
                        quickAddEvent(year, month, day);
                    }
                };

                const dayEvents = filteredEvents.filter(e => e.dateObj.getDate() === day && e.dateObj.getMonth() === month && e.dateObj.getFullYear() === year);
                const eventContainer = cell.querySelector('.event-container');

                dayEvents.forEach(ev => {
                    const isCompleted = ev.status === 'Completed';
                    const evColors = isCompleted 
                        ? 'bg-slate-200/80 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 line-through' 
                        : 'bg-green-50 dark:bg-green-950/40 text-green-700 dark:text-green-300 font-semibold hover:bg-green-100 dark:hover:bg-green-900/50';

                    const a = document.createElement('button');
                    a.type = 'button';
                    a.className = `w-full text-left block px-1.5 py-0.5 text-[11px] md:text-xs rounded-lg leading-tight truncate transition-all hover:scale-[1.02] tap-effect ${evColors}`;
                    a.title = `${ev.title} (${ev.location || 'Venue'})`;
                    a.textContent = ev.title;
                    a.onclick = (event) => {
                        event.stopPropagation();
                        openDetailModal(ev);
                    };

                    eventContainer.appendChild(a);
                });

                calendarGrid.appendChild(cell);
            }

            // Padding days after end of month
            const totalCells = firstDay + daysInMonth;
            const remainingCells = (Math.ceil(totalCells / 7) * 7) - totalCells;
            for (let i = 0; i < remainingCells; i++) {
                const cell = document.createElement('div');
                cell.className = 'bg-slate-50/50 dark:bg-zinc-900/30 cal-cell p-1 rounded-xl transition-colors opacity-30 select-none';
                calendarGrid.appendChild(cell);
            }
        }

        // Render Upcoming Schedule Sidebar
        function renderUpcoming() {
            upcomingList.innerHTML = '';
            const todayZero = new Date();
            todayZero.setHours(0,0,0,0);

            const filtered = getFilteredEvents();
            const futureEvents = filtered.filter(e => {
                const d = new Date(e.event_date);
                d.setHours(0,0,0,0);
                return d.getTime() >= todayZero.getTime() && e.status !== 'Completed';
            }).slice(0, 7);

            const badgeCount = document.getElementById('upcomingBadgeCount');
            if (badgeCount) badgeCount.textContent = `${futureEvents.length} Active`;

            if (futureEvents.length === 0) {
                upcomingList.innerHTML = `
                    <div class="h-full flex flex-col items-center justify-center p-4 text-center select-none py-8">
                        <img src="mascot_question.png" alt="No events" class="w-16 h-16 object-contain mb-2 opacity-75">
                        <p class="text-xs font-bold text-slate-700 dark:text-zinc-300 leading-snug">No upcoming schedules</p>
                        <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5 max-w-[200px]">There are currently no events matching your active filter criteria.</p>
                        <button type="button" onclick="openCreateEventModal()" class="mt-3 px-3 py-1.5 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 font-bold text-xs hover:bg-green-500/20 transition-all tap-effect">
                            + Schedule Event
                        </button>
                    </div>
                `;
                return;
            }

            futureEvents.forEach(ev => {
                const dateStr = ev.dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                const timeStr = ev.dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                const item = document.createElement('div');
                item.className = 'block p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-900/60 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect group cursor-pointer';
                item.onclick = () => openDetailModal(ev);
                item.innerHTML = `
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-bold text-green-600 dark:text-green-500 uppercase tracking-wider">${dateStr} &bull; ${timeStr}</span>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md bg-green-500/10 text-green-600 dark:text-green-500 uppercase">${escapeHtml(ev.status || 'Upcoming')}</span>
                    </div>
                    <h4 class="font-bold text-xs md:text-sm text-slate-800 dark:text-white leading-tight mb-1 group-hover:text-green-600 dark:group-hover:text-green-500 transition-colors truncate">${escapeHtml(ev.title)}</h4>
                    <p class="text-xs text-slate-500 dark:text-zinc-400 flex items-center gap-1 truncate"><i class="ph-bold ph-map-pin text-slate-400 dark:text-zinc-500"></i> ${escapeHtml(ev.location || 'Municipal Venue')}</p>
                `;
                upcomingList.appendChild(item);
            });
        }

        // Quick add from day cell
        function quickAddEvent(year, month, day) {
            const d = new Date(year, month, day, 9, 0);
            const iso = new Date(d.getTime() - (d.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
            openCreateEventModal(iso);
        }

        // Modal Functionality: Create Event
        function openCreateEventModal(dateIso = '') {
            document.getElementById('form_event_id').value = '';
            document.getElementById('form_title').value = '';
            document.getElementById('form_location').value = '';
            document.getElementById('form_limit').value = '100';
            document.getElementById('form_status').value = 'Upcoming';
            document.getElementById('form_details').value = '';
            
            if (dateIso) {
                document.getElementById('form_date').value = dateIso;
            } else {
                const now = new Date();
                const defaultDate = new Date(now.getTime() - (now.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
                document.getElementById('form_date').value = defaultDate;
            }

            document.getElementById('eventModalTitle').textContent = 'Schedule New Event';
            document.getElementById('formSubmitBtnText').textContent = 'Publish Schedule';

            showModal('eventModalOverlay', 'eventModalBox');
            requestAnimationFrame(() => document.getElementById('form_title').focus());
        }

        // Modal Functionality: Edit Event
        function openEditEventModal(ev) {
            closeDetailModal();
            document.getElementById('form_event_id').value = ev.id;
            document.getElementById('form_title').value = ev.title;
            document.getElementById('form_location').value = ev.location || '';
            document.getElementById('form_limit').value = ev.participant_limit || 100;
            document.getElementById('form_status').value = ev.status || 'Upcoming';
            document.getElementById('form_details').value = ev.details || '';
            
            const d = new Date(ev.event_date);
            const iso = new Date(d.getTime() - (d.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
            document.getElementById('form_date').value = iso;

            document.getElementById('eventModalTitle').textContent = 'Edit Event Schedule';
            document.getElementById('formSubmitBtnText').textContent = 'Update Schedule';

            showModal('eventModalOverlay', 'eventModalBox');
        }

        function closeEventModal() {
            hideModal('eventModalOverlay', 'eventModalBox');
        }

        // Modal Functionality: View Detail Dossier
        function openDetailModal(ev) {
            document.getElementById('modalDetailTitle').textContent = ev.title;
            document.getElementById('modalDetailStatus').textContent = ev.status || 'Upcoming';
            
            const d = new Date(ev.event_date);
            document.getElementById('modalDetailDate').textContent = d.toLocaleDateString('en-US', { 
                weekday: 'short', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric', 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            document.getElementById('modalDetailLocation').textContent = ev.location || 'Municipal Venue';
            document.getElementById('modalDetailLimit').textContent = ev.participant_limit ? (ev.participant_limit + ' Attendees Limit') : 'Open Admission';
            
            const descEl = document.getElementById('modalDetailDesc');
            if (ev.details && ev.details.trim().length > 0) {
                descEl.textContent = ev.details;
                document.getElementById('modalDetailContainer').style.display = '';
            } else {
                document.getElementById('modalDetailContainer').style.display = 'none';
            }

            document.getElementById('modalDetailEditBtn').onclick = () => openEditEventModal(ev);
            document.getElementById('modalDetailDeleteBtn').href = `admin_calendar.php?delete=${ev.id}`;

            showModal('detailModalOverlay', 'detailModalBox');
        }

        function closeDetailModal() {
            hideModal('detailModalOverlay', 'detailModalBox');
        }

        // Generic Modal Helpers
        function showModal(overlayId, boxId) {
            const modal = document.getElementById(overlayId);
            const box = document.getElementById(boxId);
            if (!modal || !box) return;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            requestAnimationFrame(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                box.classList.remove('scale-95', 'opacity-0');
                box.classList.add('scale-100', 'opacity-100');
            });
        }

        function hideModal(overlayId, boxId) {
            const modal = document.getElementById(overlayId);
            const box = document.getElementById(boxId);
            if (!modal || !box) return;

            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            box.classList.remove('scale-100', 'opacity-100');
            box.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 250);
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Escape Key Listener
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEventModal();
                closeDetailModal();
            }
        });

        // Navigation controls
        document.getElementById('prevMonth').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); });
        document.getElementById('nextMonth').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); });
        document.getElementById('todayBtn').addEventListener('click', () => { currentDate = new Date(); renderCalendar(); });

        // Initial Render
        renderCalendar();
        renderUpcoming();
    </script>

    <?php include_once 'genesis_widget.php'; ?>
</body>
</html>
