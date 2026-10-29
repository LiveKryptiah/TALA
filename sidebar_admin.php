<?php
// Ensure session variables are accessible
$current_page = basename($_SERVER['PHP_SELF']);
$admin_name = $_SESSION['admin_username'] ?? 'Administrator';

// Fetch pending tickets count for the badge (Optional, fail gracefully if table doesn't exist)
$pending_tickets = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM suggestions WHERE status = 'Open' OR status = 'In Progress'");
    $pending_tickets = $stmt->fetchColumn();
} catch (Exception $e) {}

// Fetch pending approvals count for the badge (Optional)
$pending_approvals = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins WHERE status = 'pending' AND role = 'sub-admin'");
    $pending_approvals = $stmt->fetchColumn();
} catch (Exception $e) {}
?>

<!-- ANTI-FLASH SCRIPT (Executes instantly when sidebar loads) -->
<script>
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
</script>

<style>
    /* =======================================================
       1. GLOBAL MOBILE LAYOUT FIXER (Applies to all pages)
       ======================================================= */
    @media (max-width: 767px) {
        main {
            margin-left: 0 !important; /* Remove desktop sidebar gap */
            padding-top: 5rem !important; /* Make room for Mobile Top Bar */
            padding-bottom: 6rem !important; /* Make room for Bottom Nav */
            width: 100% !important;
        }
        #globalThemeToggle { display: none !important; }
    }

    /* =======================================================
       2. SIDEBAR STYLES (Desktop & Mobile Drawer)
       ======================================================= */
    #tala-admin-sidebar {
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.3s ease, border-color 0.3s ease !important;
        z-index: 100 !important;
    }
    
    /* Mobile Slide-out Drawer Logic */
    @media (max-width: 767px) {
        #tala-admin-sidebar {
            transform: translateX(-100%); /* Hidden by default on mobile */
            width: 80% !important; /* Take up 80% of screen */
            max-width: 320px !important;
        }
        #tala-admin-sidebar.mobile-open {
            transform: translateX(0); /* Slide in */
        }
    }

    #tala-admin-sidebar .tala-nav-btn {
        all: unset !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        padding: 12px 16px !important;
        margin-bottom: 6px !important;
        border-radius: 12px !important;
        font-size: 14px !important;
        font-weight: 600 !important;
        font-family: 'Plus Jakarta Sans', sans-serif !important;
        box-sizing: border-box !important;
        width: 100% !important;
        text-decoration: none !important;
        line-height: 1.5 !important;
        cursor: pointer !important;
        background-color: transparent !important;
        color: #64748b !important;
        border: 1px solid transparent !important;
        transition: all 0.2s ease !important;
    }

    #tala-admin-sidebar .tala-nav-btn:hover {
        background-color: #f8fafc !important;
        color: #0f172a !important;
        transform: translateX(4px) !important;
    }

    /* Ghost Active / Clicked Link (Translucent Tint, Zero Stroke / Exterior Line) */
    #tala-admin-sidebar .tala-nav-btn.active,
    #tala-admin-sidebar .tala-nav-btn:active {
        background-color: rgba(22, 163, 74, 0.12) !important;
        color: #15803d !important;
        font-weight: 700 !important;
        border: none !important;
        border-width: 0 !important;
        outline: none !important;
        box-shadow: none !important;
        -webkit-box-shadow: none !important;
        position: relative !important;
        overflow: hidden !important;
    }
    #tala-admin-sidebar .tala-nav-btn.active:hover {
        background-color: rgba(22, 163, 74, 0.18) !important;
        color: #15803d !important;
    }
    /* White Gradient on Top Right when Clicked or Active */
    #tala-admin-sidebar .tala-nav-btn.active::before,
    #tala-admin-sidebar .tala-nav-btn:active::before {
        content: '' !important;
        position: absolute !important;
        top: -6px !important;
        right: -6px !important;
        width: 50px !important;
        height: 50px !important;
        background: radial-gradient(circle at 75% 25%, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.4) 45%, transparent 75%) !important;
        pointer-events: none !important;
        border-radius: 9999px !important;
        filter: blur(3px) !important;
        z-index: 1 !important;
        transition: transform 0.25s ease, opacity 0.25s ease !important;
    }
    #tala-admin-sidebar .tala-nav-btn.active:hover::before,
    #tala-admin-sidebar .tala-nav-btn:active:hover::before {
        transform: scale(1.15) !important;
    }
    #tala-admin-sidebar .tala-nav-btn.active i,
    #tala-admin-sidebar .tala-nav-btn:active i {
        position: relative !important;
        z-index: 2 !important;
        color: #15803d !important;
    }
    #tala-admin-sidebar .tala-nav-btn.active > span,
    #tala-admin-sidebar .tala-nav-btn:active > span,
    #tala-admin-sidebar .tala-nav-btn.active div.flex,
    #tala-admin-sidebar .tala-nav-btn:active div.flex,
    #tala-admin-sidebar .tala-nav-btn.active div.flex > span,
    #tala-admin-sidebar .tala-nav-btn:active div.flex > span {
        position: relative !important;
        z-index: 2 !important;
        color: #15803d !important;
        font-weight: 700 !important;
    }
    #tala-admin-sidebar .tala-nav-btn.active span[class*="bg-"] {
        color: #ffffff !important;
    }

    #tala-admin-sidebar .tala-nav-btn i { font-size: 20px !important; margin: 0 !important; }

    #tala-admin-sidebar .sidebar-heading {
        margin: 24px 0 8px 8px !important; 
        font-family: 'Plus Jakarta Sans', sans-serif !important; 
        color: #94a3b8 !important;
        font-size: 10px !important;
        font-weight: 800 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.1em !important;
        transition: color 0.3s ease !important;
    }

    .custom-scrollbar::-webkit-scrollbar { display: none !important; }
    .custom-scrollbar { -ms-overflow-style: none !important; scrollbar-width: none !important; }

    /* Hide scrollbars specifically for the mobile swipeable bottom nav */
    .hide-scrollbar::-webkit-scrollbar { display: none !important; }
    .hide-scrollbar { -ms-overflow-style: none !important; scrollbar-width: none !important; }

    .tala-brand-text { color: #0f172a !important; transition: color 0.3s ease !important; }
    .tala-brand-subtitle { color: #64748b !important; transition: color 0.3s ease !important; }

    .tala-profile-card {
        background: linear-gradient(135deg, #15803d 0%, #16a34a 100%) !important;
        border-radius: 12px !important;
        padding: 8px 10px !important;
        border: none !important;
        box-shadow: none !important;
        display: flex !important;
        align-items: center !important;
        gap: 9px !important;
        transition: transform 0.2s ease, background 0.3s ease !important;
    }

    /* =======================================================
       3. DARK MODE OVERRIDES
       ======================================================= */
    html.dark #tala-admin-sidebar { background-color: rgba(24, 24, 27, 0.98) !important; border-color: rgba(63, 63, 70, 0.5) !important; }
    html.dark #tala-admin-sidebar .tala-nav-btn { color: #a1a1aa !important; }
    html.dark #tala-admin-sidebar .tala-nav-btn:hover { background-color: #27272a !important; color: #f4f4f5 !important; }
    html.dark #tala-admin-sidebar .tala-nav-btn.active,
    html.dark #tala-admin-sidebar .tala-nav-btn:active {
        background-color: rgba(34, 197, 94, 0.16) !important;
        color: #22c55e !important;
        font-weight: 700 !important;
        border: none !important;
        border-width: 0 !important;
        outline: none !important;
        box-shadow: none !important;
        -webkit-box-shadow: none !important;
    }
    html.dark #tala-admin-sidebar .tala-nav-btn.active::before,
    html.dark #tala-admin-sidebar .tala-nav-btn:active::before {
        background: radial-gradient(circle at 75% 25%, rgba(255, 255, 255, 0.45) 0%, rgba(255, 255, 255, 0.15) 45%, transparent 75%) !important;
    }
    html.dark #tala-admin-sidebar .tala-nav-btn.active:hover {
        background-color: rgba(34, 197, 94, 0.22) !important;
        color: #4ade80 !important;
    }
    html.dark #tala-admin-sidebar .tala-nav-btn.active i,
    html.dark #tala-admin-sidebar .tala-nav-btn:active i,
    html.dark #tala-admin-sidebar .tala-nav-btn.active > span,
    html.dark #tala-admin-sidebar .tala-nav-btn:active > span,
    html.dark #tala-admin-sidebar .tala-nav-btn.active div.flex,
    html.dark #tala-admin-sidebar .tala-nav-btn:active div.flex,
    html.dark #tala-admin-sidebar .tala-nav-btn.active div.flex > span,
    html.dark #tala-admin-sidebar .tala-nav-btn:active div.flex > span {
        color: #22c55e !important;
    }
    html.dark #tala-admin-sidebar .tala-nav-btn.active span[class*="bg-"] {
        color: #ffffff !important;
    }
    html.dark #tala-admin-sidebar .sidebar-heading { color: #52525b !important; }
    html.dark .tala-brand-text { color: #f4f4f5 !important; }
    html.dark .tala-brand-subtitle { color: #a1a1aa !important; }
    html.dark .tala-profile-card { background: linear-gradient(135deg, #15803d 0%, #16a34a 100%) !important; border: none !important; }
    html.dark .tala-logout-btn { background-color: rgba(255, 255, 255, 0.18) !important; border-color: rgba(255, 255, 255, 0.28) !important; color: #ffffff !important; }
    html.dark .tala-logout-btn:hover { background-color: rgba(239, 68, 68, 0.88) !important; }

    /* Mobile Overlay Dark Mode */
    .mobile-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); z-index: 45; backdrop-filter: blur(4px); opacity: 0; transition: opacity 0.3s ease; }
    html.dark .mobile-overlay { background: rgba(0, 0, 0, 0.6); }
    .mobile-overlay.active { display: block; opacity: 1; }

    /* Tap Effect for Mobile */
    .tap-effect { -webkit-tap-highlight-color: transparent; }
    .tap-effect:active { transform: scale(0.95); opacity: 0.8; transition: transform 0.1s; }
</style>

<!-- =======================================================
     MOBILE TOP APP BAR (Only visible on phones)
     ======================================================= -->
<header class="md:hidden fixed top-0 left-0 right-0 h-16 bg-white/90 dark:bg-zinc-950/90 backdrop-blur-xl border-b border-slate-200 dark:border-zinc-800 z-40 flex items-center justify-between px-5 transition-colors shadow-sm">
    <div class="flex items-center gap-2">
        <button onclick="toggleMobileMenu()" class="w-10 h-10 flex items-center justify-center rounded-xl text-slate-600 dark:text-zinc-300 tap-effect transition-colors">
            <i class="ph-bold ph-list text-2xl"></i>
        </button>
        <img src="TALA-LOGO.ico" alt="TALA Logo" class="w-8 h-8 rounded-lg object-contain shadow-sm" onerror="this.src='https://cdn-icons-png.flaticon.com/512/1828/1828673.png'">
        <span class="font-extrabold text-xl tracking-tight tala-brand-text ml-1">TALA <span style="background: linear-gradient(135deg, #16a34a 0%, #eab308 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">OS</span></span>
    </div>
    <div class="flex items-center gap-2">
        <button id="mobileThemeToggle" class="w-9 h-9 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 tap-effect transition-colors">
            <i id="mobileThemeIcon" class="ph-bold ph-moon text-lg"></i>
        </button>
        <div class="w-9 h-9 rounded-xl bg-green-100 dark:bg-green-900/30 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 flex items-center justify-center font-black text-sm shadow-sm">
            <?= strtoupper(substr($admin_name, 0, 1)) ?>
        </div>
    </div>
</header>

<!-- =======================================================
     PAGINATED SWIPEABLE MOBILE BOTTOM NAVIGATION
     ======================================================= -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 h-[72px] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-2xl border-t border-slate-200 dark:border-zinc-800 z-40 flex overflow-x-auto hide-scrollbar overscroll-x-contain snap-x snap-mandatory pb-[env(safe-area-inset-bottom)] transition-colors shadow-[0_-4px_24px_rgba(0,0,0,0.04)]">
    
    <!-- PAGE 1: First 4 Icons -->
    <div class="w-full shrink-0 flex items-center justify-around snap-start h-full">
        
        <!-- 1. Dashboard -->
        <a href="tala_dashboard.php" class="flex flex-col items-center justify-center w-1/4 h-full <?= $current_page == 'tala_dashboard.php' ? 'text-[#15803d] dark:text-[#4ade80]' : 'text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300' ?> tap-effect transition-colors">
            <div class="px-4 py-1 <?= $current_page == 'tala_dashboard.php' ? 'bg-emerald-600/15 dark:bg-emerald-500/20 text-[#15803d] dark:text-[#4ade80] rounded-full' : '' ?> mb-1 transition-colors">
                <i class="<?= $current_page == 'tala_dashboard.php' ? 'ph-fill text-[#15803d] dark:text-[#4ade80]' : 'ph-bold' ?> ph-squares-four text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Dashboard</span>
        </a>

        <!-- 2. Calendar -->
        <a href="admin_calendar.php" class="flex flex-col items-center justify-center w-1/4 h-full <?= $current_page == 'admin_calendar.php' ? 'text-[#15803d] dark:text-[#4ade80]' : 'text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300' ?> tap-effect transition-colors">
            <div class="px-4 py-1 <?= $current_page == 'admin_calendar.php' ? 'bg-emerald-600/15 dark:bg-emerald-500/20 text-[#15803d] dark:text-[#4ade80] rounded-full' : '' ?> mb-1 transition-colors">
                <i class="<?= $current_page == 'admin_calendar.php' ? 'ph-fill text-[#15803d] dark:text-[#4ade80]' : 'ph-bold' ?> ph-calendar-blank text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Calendar</span>
        </a>

        <!-- 3. Directory -->
        <a href="tala_directory.php" class="flex flex-col items-center justify-center w-1/4 h-full <?= $current_page == 'tala_directory.php' ? 'text-[#15803d] dark:text-[#4ade80]' : 'text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300' ?> tap-effect transition-colors">
            <div class="px-4 py-1 <?= $current_page == 'tala_directory.php' ? 'bg-emerald-600/15 dark:bg-emerald-500/20 text-[#15803d] dark:text-[#4ade80] rounded-full' : '' ?> mb-1 transition-colors">
                <i class="<?= $current_page == 'tala_directory.php' ? 'ph-fill text-[#15803d] dark:text-[#4ade80]' : 'ph-bold' ?> ph-users-three text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Youth</span>
        </a>

        <!-- 4. Approvals -->
        <a href="admin_approval.php" class="flex flex-col items-center justify-center w-1/4 h-full <?= $current_page == 'admin_approval.php' ? 'text-[#15803d] dark:text-[#4ade80]' : 'text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300' ?> tap-effect transition-colors relative">
            <div class="px-4 py-1 <?= $current_page == 'admin_approval.php' ? 'bg-emerald-600/15 dark:bg-emerald-500/20 text-[#15803d] dark:text-[#4ade80] rounded-full' : '' ?> mb-1 transition-colors relative">
                <i class="<?= $current_page == 'admin_approval.php' ? 'ph-fill text-[#15803d] dark:text-[#4ade80]' : 'ph-bold' ?> ph-shield-check text-2xl"></i>
                <?php if(isset($pending_approvals) && $pending_approvals > 0): ?>
                    <span class="absolute top-0 right-2 w-2.5 h-2.5 bg-red-500 border-2 border-white dark:border-zinc-950 rounded-full"></span>
                <?php endif; ?>
            </div>
            <span class="text-[10px] font-bold">Approvals</span>
        </a>

    </div>

    <!-- PAGE 2: Next 4 Icons -->
    <div class="w-full shrink-0 flex items-center justify-around snap-start h-full">
        
        <!-- 5. Kanban / Projects -->
        <a href="kanban.php" class="flex flex-col items-center justify-center w-1/4 h-full <?= $current_page == 'kanban.php' ? 'text-[#15803d] dark:text-[#4ade80]' : 'text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300' ?> tap-effect transition-colors">
            <div class="px-4 py-1 <?= $current_page == 'kanban.php' ? 'bg-emerald-600/15 dark:bg-emerald-500/20 text-[#15803d] dark:text-[#4ade80] rounded-full' : '' ?> mb-1 transition-colors">
                <i class="<?= $current_page == 'kanban.php' ? 'ph-fill text-[#15803d] dark:text-[#4ade80]' : 'ph-bold' ?> ph-kanban text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Projects</span>
        </a>

        <!-- 6. Governance / Files -->
        <a href="admin_governance.php" class="flex flex-col items-center justify-center w-1/4 h-full <?= $current_page == 'admin_governance.php' ? 'text-[#15803d] dark:text-[#4ade80]' : 'text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300' ?> tap-effect transition-colors">
            <div class="px-4 py-1 <?= $current_page == 'admin_governance.php' ? 'bg-emerald-600/15 dark:bg-emerald-500/20 text-[#15803d] dark:text-[#4ade80] rounded-full' : '' ?> mb-1 transition-colors">
                <i class="<?= $current_page == 'admin_governance.php' ? 'ph-fill text-[#15803d] dark:text-[#4ade80]' : 'ph-bold' ?> ph-file text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Files</span>
        </a>

        <!-- 7. E-Sumbong -->
        <a href="tickets.php" class="flex flex-col items-center justify-center w-1/4 h-full <?= $current_page == 'tickets.php' ? 'text-[#15803d] dark:text-[#4ade80]' : 'text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300' ?> tap-effect transition-colors relative">
            <div class="px-4 py-1 <?= $current_page == 'tickets.php' ? 'bg-emerald-600/15 dark:bg-emerald-500/20 text-[#15803d] dark:text-[#4ade80] rounded-full' : '' ?> mb-1 transition-colors relative">
                <i class="<?= $current_page == 'tickets.php' ? 'ph-fill text-[#15803d] dark:text-[#4ade80]' : 'ph-bold' ?> ph-megaphone text-2xl"></i>
                <?php if(isset($pending_tickets) && $pending_tickets > 0): ?>
                    <span class="absolute top-0 right-2 w-2.5 h-2.5 bg-red-500 border-2 border-white dark:border-zinc-950 rounded-full"></span>
                <?php endif; ?>
            </div>
            <span class="text-[10px] font-bold">Tickets</span>
        </a>

        <!-- 8. Menu/More (Opens Sidebar) -->
        <button onclick="toggleMobileMenu()" class="flex flex-col items-center justify-center w-1/4 h-full text-slate-400 dark:text-zinc-500 tap-effect transition-colors">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-list text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">More</span>
        </button>

    </div>
</nav>

<!-- FLOATING TOP-RIGHT THEME TOGGLE BUTTON (Desktop Only) -->
<button id="globalThemeToggle" class="fixed top-6 right-6 z-[100] w-12 h-12 flex items-center justify-center rounded-2xl bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md border border-slate-200 dark:border-zinc-800 text-slate-500 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800 shadow-sm transition-colors duration-300" title="Toggle Dark Mode">
    <i id="globalThemeIcon" class="ph-bold ph-moon text-xl"></i>
</button>

<!-- Mobile Overlay -->
<div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

<!-- =======================================================
     ADMIN SIDEBAR (Desktop Fixed, Mobile Slide-out)
     ======================================================= -->
<aside id="tala-admin-sidebar" class="bg-white/90 backdrop-blur-2xl border-r border-slate-200/60 fixed inset-y-0 left-0 md:w-64 flex flex-col shadow-[4px_0_24px_rgba(0,0,0,0.03)] z-[50]">
    
    <!-- Mobile Close Button (Inside Sidebar) -->
    <div class="md:hidden flex justify-end p-4 pb-0">
        <button onclick="toggleMobileMenu()" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 tap-effect">
            <i class="ph-bold ph-x text-lg"></i>
        </button>
    </div>

    <!-- Branding with Original TALA Logo -->
    <div class="h-24 flex items-center px-6 border-b border-slate-100/80 dark:border-zinc-800/80 mb-4 transition-colors" style="margin-top: 0 !important;">
        <a href="tala_dashboard.php" class="flex items-center gap-3 group" style="text-decoration: none !important;">
            <img src="TALA-LOGO.ico" alt="TALA Logo" class="w-11 h-11 object-contain shadow-sm rounded-xl group-hover:scale-105 transition-transform" onerror="this.src='https://cdn-icons-png.flaticon.com/512/1828/1828673.png'" />
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight leading-none tala-brand-text" style="margin: 0 !important;">
                    TALA <span style="background: linear-gradient(135deg, #16a34a 0%, #eab308 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block;">OS</span>
                </h1>
                <span class="text-[9px] font-extrabold tracking-widest uppercase tala-brand-subtitle" style="margin: 0 !important;">Admin Console</span>
            </div>
        </a>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 px-4 overflow-y-auto custom-scrollbar pb-6" style="display: block !important;">
        
        <p class="sidebar-heading" style="margin-top: 8px !important;">Core Analytics</p>
        <a href="tala_dashboard.php" class="tala-nav-btn <?= $current_page == 'tala_dashboard.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'tala_dashboard.php' ? 'ph-fill' : 'ph-bold' ?> ph-squares-four"></i> Dashboard
        </a>
        <a href="admin_calendar.php" class="tala-nav-btn <?= $current_page == 'admin_calendar.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'admin_calendar.php' ? 'ph-fill' : 'ph-bold' ?> ph-calendar-blank"></i> Master Calendar
        </a>
        <a href="department_monitor.php" class="tala-nav-btn <?= $current_page == 'department_monitor.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'department_monitor.php' ? 'ph-fill' : 'ph-bold' ?> ph-eye"></i> Inter-Agency Monitor
        </a>

        <p class="sidebar-heading">Administrative</p>
        <a href="admin_mom.php" class="tala-nav-btn <?= $current_page == 'admin_mom.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'admin_mom.php' ? 'ph-fill' : 'ph-bold' ?> ph-folders"></i> MOM Oversight
        </a>
        <a href="admin_resolution.php" class="tala-nav-btn <?= $current_page == 'admin_resolution.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'admin_resolution.php' ? 'ph-fill' : 'ph-bold' ?> ph-scroll"></i> RESO Oversight
        </a>
        <a href="admin_budget.php" class="tala-nav-btn <?= $current_page == 'admin_budget.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'admin_budget.php' ? 'ph-fill' : 'ph-bold' ?> ph-wallet"></i> Budget Monitor
        </a>
        <a href="manage_disclosure.php" class="tala-nav-btn <?= $current_page == 'manage_disclosure.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'manage_disclosure.php' ? 'ph-fill' : 'ph-bold' ?> ph-file-magnifying-glass"></i> Transparency Board
        </a>
        <a href="admin_vouchers.php" class="tala-nav-btn <?= $current_page == 'admin_vouchers.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'admin_vouchers.php' ? 'ph-fill' : 'ph-bold' ?> ph-receipt"></i> Withdrawal History
        </a>

        <p class="sidebar-heading">Database & Users</p>
        <a href="tala_directory.php" class="tala-nav-btn <?= $current_page == 'tala_directory.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'tala_directory.php' ? 'ph-fill' : 'ph-bold' ?> ph-address-book"></i> Master Directory
        </a>
        <a href="manage_sk_badges.php" class="tala-nav-btn <?= $current_page == 'manage_sk_badges.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'manage_sk_badges.php' ? 'ph-fill' : 'ph-bold' ?> ph-medal"></i> SK Leaderboard
        </a>
        <a href="admin_yorp.php" class="tala-nav-btn <?= $current_page == 'admin_yorp.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'admin_yorp.php' ? 'ph-fill' : 'ph-bold' ?> ph-users-three"></i> YORP Tracker
        </a>
        <a href="admin_approval.php" class="tala-nav-btn <?= $current_page == 'admin_approval.php' ? 'active' : '' ?>" style="justify-content: space-between !important;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <i class="<?= $current_page == 'admin_approval.php' ? 'ph-fill' : 'ph-bold' ?> ph-activity"></i> SK Tracker
            </div>
            <?php if(isset($pending_approvals) && $pending_approvals > 0): ?>
                <span class="bg-red-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full shadow-sm"><?= $pending_approvals ?></span>
            <?php endif; ?>
        </a>
        <a href="kanban.php" class="tala-nav-btn <?= $current_page == 'kanban.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'kanban.php' ? 'ph-fill' : 'ph-bold' ?> ph-kanban"></i> Project Board
        </a>
        <a href="admin_governance.php" class="tala-nav-btn <?= $current_page == 'admin_governance.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'admin_governance.php' ? 'ph-fill' : 'ph-bold' ?> ph-file"></i> Public Files
        </a>
        
        <a href="tickets.php" class="tala-nav-btn <?= $current_page == 'tickets.php' ? 'active' : '' ?>" style="justify-content: space-between !important;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <i class="<?= $current_page == 'tickets.php' ? 'ph-fill' : 'ph-bold' ?> ph-megaphone"></i> E-Sumbong
            </div>
            <?php if(isset($pending_tickets) && $pending_tickets > 0): ?>
                <span class="bg-amber-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full shadow-sm"><?= $pending_tickets ?></span>
            <?php endif; ?>
        </a>
        
        <a href="admin_mydc.php" class="tala-nav-btn <?= $current_page == 'admin_mydc.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'admin_mydc.php' ? 'ph-fill' : 'ph-bold' ?> ph-identification-badge"></i> MYDC Registry
        </a>
        <a href="import_youth.php" class="tala-nav-btn <?= $current_page == 'import_youth.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'import_youth.php' ? 'ph-fill' : 'ph-bold' ?> ph-cloud-arrow-up"></i> Bulk Import
        </a>

        <p class="sidebar-heading">Programs & Content</p>
        <a href="manage_announcements.php" class="tala-nav-btn <?= $current_page == 'manage_announcements.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'manage_announcements.php' ? 'ph-fill' : 'ph-bold' ?> ph-speaker-high"></i> Announcements
        </a>
        <a href="events.php" class="tala-nav-btn <?= $current_page == 'events.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'events.php' ? 'ph-fill' : 'ph-bold' ?> ph-calendar-star"></i> All Events
        </a>
        <a href="manage_polls.php" class="tala-nav-btn <?= $current_page == 'manage_polls.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'manage_polls.php' ? 'ph-fill' : 'ph-bold' ?> ph-chart-bar"></i> Youth Polls
        </a>
        <a href="manage_highlights.php" class="tala-nav-btn <?= $current_page == 'manage_highlights.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'manage_highlights.php' ? 'ph-fill' : 'ph-bold' ?> ph-star"></i> Highlights
        </a>
        <a href="manage_popup.php" class="tala-nav-btn <?= $current_page == 'manage_popup.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'manage_popup.php' ? 'ph-fill' : 'ph-bold' ?> ph-bell-ringing"></i> Manage Pop-Up
        </a>
        <a href="manage_scholarships.php" class="tala-nav-btn <?= $current_page == 'manage_scholarships.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'manage_scholarships.php' ? 'ph-fill' : 'ph-bold' ?> ph-graduation-cap"></i> Scholarships
        </a>
        
    </nav>
    
    <!-- User Profile & Logout (Minimal Single-Row Card) -->
    <div class="p-3 border-t border-slate-100 dark:border-zinc-800/80 transition-colors" style="display: block !important;">
        <div class="tala-profile-card" style="margin: 0 !important;">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center font-extrabold flex-shrink-0" style="background-color: rgba(255, 255, 255, 0.2) !important; color: #ffffff !important; font-size: 13px !important;">
                <?= strtoupper(substr($admin_name, 0, 1)) ?>
            </div>
            <div class="flex-1 min-w-0 overflow-hidden">
                <p class="text-xs font-extrabold truncate" style="margin: 0 !important; color: #ffffff !important; line-height: 1.2;">
                    <?= htmlspecialchars($admin_name) ?>
                </p>
                <p class="text-[10px] font-semibold truncate" style="margin: 1px 0 0 0 !important; color: rgba(255, 255, 255, 0.85) !important; line-height: 1.2;">
                    Municipal Admin
                </p>
            </div>
            <a href="logout.php" class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 text-white hover:bg-red-600/90 hover:scale-105 transition-all duration-200" title="Sign Out" style="background-color: rgba(255, 255, 255, 0.15) !important;">
                <i class="ph-bold ph-sign-out text-sm"></i>
            </a>
        </div>
    </div>
</aside>

<!-- GLOBAL SCRIPT (Theme & Native Mobile Swipe Logic) -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        
        // --- 1. THEME TOGGLE LOGIC ---
        const toggleBtn = document.getElementById('globalThemeToggle');
        const icon = document.getElementById('globalThemeIcon');
        const mobileToggleBtn = document.getElementById('mobileThemeToggle');
        const mobileIcon = document.getElementById('mobileThemeIcon');
        
        function syncIcons(isDark) {
            if (icon) icon.className = isDark ? 'ph-bold ph-sun text-xl' : 'ph-bold ph-moon text-xl';
            if (mobileIcon) mobileIcon.className = isDark ? 'ph-bold ph-sun text-lg' : 'ph-bold ph-moon text-lg';
        }

        const isDarkTheme = document.documentElement.classList.contains('dark');
        syncIcons(isDarkTheme);

        function handleThemeToggle() {
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                syncIcons(false);
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                syncIcons(true);
            }
        }

        if (toggleBtn) toggleBtn.addEventListener('click', handleThemeToggle);
        if (mobileToggleBtn) mobileToggleBtn.addEventListener('click', handleThemeToggle);

        // --- 2. FORGIVING NATIVE MOBILE SWIPE LOGIC ---
        let touchStartX = 0;
        let touchStartY = 0;
        let touchEndX = 0;
        let touchEndY = 0;

        document.addEventListener('touchstart', function(e) {
            // Using clientX/Y is much more reliable across mobile browsers
            touchStartX = e.changedTouches[0].clientX;
            touchStartY = e.changedTouches[0].clientY;
        }, {passive: true});

        document.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].clientX;
            touchEndY = e.changedTouches[0].clientY;
            
            if (Math.abs(touchEndY - touchStartY) > 50) return;

            handleSwipe();
        }, {passive: true});

        function handleSwipe() {
            if (window.innerWidth >= 768) return;
            
            const sidebar = document.getElementById('tala-admin-sidebar');
            const overlay = document.getElementById('mobileOverlay');
            const isOpen = sidebar.classList.contains('mobile-open');
            
            const diffX = touchEndX - touchStartX;

            // Open Sidebar: Swiped right AND started near left edge
            if (diffX > 50 && touchStartX < 80) {
                if (!isOpen) {
                    sidebar.classList.add('mobile-open');
                    overlay.classList.add('active');
                }
            } 
            // Close Sidebar: Swiped left from anywhere
            else if (diffX < -50) {
                if (isOpen) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                }
            }
        }
    });

    // Manual Hamburger/Overlay Click Toggle
    function toggleMobileMenu() {
        const sidebar = document.getElementById('tala-admin-sidebar');
        const overlay = document.getElementById('mobileOverlay');
        
        if (sidebar.classList.contains('mobile-open')) {
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
        } else {
            sidebar.classList.add('mobile-open');
            overlay.classList.add('active');
        }
    }
</script>

<!-- Inject Chat & Activity Tracker Widget -->
<?php 
if (file_exists(__DIR__ . '/chat_widget.php')) {
    include __DIR__ . '/chat_widget.php';
} elseif (file_exists(__DIR__ . '/kryptiah_widget.php')) {
    include __DIR__ . '/kryptiah_widget.php';
}
?>