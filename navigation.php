<?php
// Get the current file name (e.g., 'updates.php', 'features.php')
$current_page = basename($_SERVER['PHP_SELF']);
$has_hero = ($current_page === 'index.php');

// 1. Helper function for standard/standalone links (Desktop)
function getNavClass($page, $current_page) {
    if ($page === 'public_registration.php') {
        return ($current_page === $page) 
            ? "text-[13px] font-black text-green-600 dark:text-green-500 border-b-2 border-green-600 dark:border-green-500 py-1 transition whitespace-nowrap" 
            : "text-[13px] font-bold text-slate-700 dark:text-zinc-300 hover:text-green-600 dark:hover:text-green-500 transition whitespace-nowrap";
    }
    return ($current_page === $page) 
        ? "text-[13px] font-bold text-green-600 dark:text-green-500 border-b-2 border-green-600 dark:border-green-500 py-1 transition whitespace-nowrap" 
        : "text-[13px] font-semibold text-slate-500 dark:text-zinc-400 hover:text-green-600 dark:hover:text-green-500 transition whitespace-nowrap";
}

// 2. Helper function for Dropdown Parent Triggers (Desktop)
function getDropdownParentClass($pages_array, $current_page) {
    return in_array($current_page, $pages_array)
        ? "text-[13px] font-bold text-green-600 dark:text-green-500 border-b-2 border-green-600 dark:border-green-500 py-1 transition whitespace-nowrap flex items-center gap-1.5"
        : "text-[13px] font-semibold text-slate-500 dark:text-zinc-400 hover:text-green-600 dark:hover:text-green-500 transition whitespace-nowrap flex items-center gap-1.5";
}

// 3. Helper function for Dropdown Child Items (Desktop)
function getDropdownChildClass($page, $current_page) {
    return ($current_page === $page)
        ? "px-3.5 py-2 text-[13px] font-bold text-green-800 dark:text-green-300 bg-green-50 dark:bg-green-950/40 rounded-xl flex items-center gap-2.5 transition-colors"
        : "px-3.5 py-2 text-[13px] font-semibold text-slate-600 dark:text-zinc-300 hover:text-green-600 dark:hover:text-green-500 hover:bg-slate-50 dark:hover:bg-zinc-800/60 rounded-xl flex items-center gap-2.5 transition-colors";
}

// 4. Helper function for Mobile Links
function getMobileNavClass($page, $current_page) {
    return ($current_page === $page) 
        ? "block text-sm font-bold text-green-800 dark:text-green-300 bg-green-50 dark:bg-green-950/40 px-4 py-2.5 rounded-xl transition-colors" 
        : "block text-sm font-semibold text-slate-600 dark:text-zinc-300 hover:text-green-600 dark:hover:text-green-500 hover:bg-slate-50 dark:hover:bg-zinc-800/60 px-4 py-2.5 rounded-xl transition-colors";
}
?>

<style>
    /* Global TALA OS Green to Yellow Gradient Button for Public Website */
    .btn-gradient,
    .btn-yellow-glow-r,
    button.bg-\[\#15803d\],
    button.bg-\[\#16a34a\],
    a.bg-\[\#15803d\],
    a.bg-\[\#16a34a\] {
        background: linear-gradient(135deg, #15803d 0%, #16a34a 35%, #eab308 100%) !important;
        color: #ffffff !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35) !important;
        box-shadow: none !important;
        position: relative !important;
        overflow: hidden !important;
        transition: transform 0.2s ease, opacity 0.2s ease !important;
    }
    .btn-gradient:hover,
    .btn-yellow-glow-r:hover,
    button.bg-\[\#15803d\]:hover,
    button.bg-\[\#16a34a\]:hover,
    a.bg-\[\#15803d\]:hover,
    a.bg-\[\#16a34a\]:hover {
        background: linear-gradient(135deg, #15803d 0%, #16a34a 35%, #eab308 100%) !important;
        box-shadow: none !important;
        transform: translateY(-1px) !important;
        opacity: 0.96 !important;
    }
    html.dark .btn-gradient,
    html.dark .btn-yellow-glow-r,
    html.dark button.bg-\[\#15803d\],
    html.dark button.bg-\[\#16a34a\],
    html.dark a.bg-\[\#15803d\],
    html.dark a.bg-\[\#16a34a\] {
        background: linear-gradient(135deg, #15803d 0%, #16a34a 35%, #eab308 100%) !important;
        color: #ffffff !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35) !important;
        box-shadow: none !important;
    }
    html.dark .btn-gradient:hover,
    html.dark .btn-yellow-glow-r:hover,
    html.dark button.bg-\[\#15803d\]:hover,
    html.dark button.bg-\[\#16a34a\]:hover,
    html.dark a.bg-\[\#15803d\]:hover,
    html.dark a.bg-\[\#16a34a\]:hover {
        background: linear-gradient(135deg, #15803d 0%, #16a34a 35%, #eab308 100%) !important;
        box-shadow: none !important;
        transform: translateY(-1px) !important;
        opacity: 0.96 !important;
    }
    /* Completely remove right-side glow pseudo-element */
    .btn-gradient::before,
    .btn-yellow-glow-r::before,
    button.bg-\[\#15803d\]::before,
    button.bg-\[\#16a34a\]::before,
    a.bg-\[\#15803d\]::before,
    a.bg-\[\#16a34a\]::before {
        display: none !important;
        content: none !important;
    }

    /* TALA OS Green to Yellow Text Gradient for Names & Section Headers */
    .text-gradient,
    .text-gradient-green-yellow {
        background: linear-gradient(135deg, #15803d 0%, #16a34a 30%, #eab308 100%) !important;
        -webkit-background-clip: text !important;
        background-clip: text !important;
        -webkit-text-fill-color: transparent !important;
        color: transparent !important;
        display: inline-block;
    }
    .icon-gradient,
    i.icon-gradient {
        background: linear-gradient(135deg, #15803d 0%, #16a34a 30%, #eab308 100%) !important;
        -webkit-background-clip: text !important;
        background-clip: text !important;
        -webkit-text-fill-color: transparent !important;
        color: transparent !important;
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        background-color: transparent !important;
    }
    html.dark .text-gradient,
    html.dark .text-gradient-green-yellow,
    .dark .text-gradient,
    .dark .text-gradient-green-yellow {
        background: linear-gradient(135deg, #22c55e 0%, #4ade80 35%, #facc15 100%) !important;
        -webkit-background-clip: text !important;
        background-clip: text !important;
        -webkit-text-fill-color: transparent !important;
        color: transparent !important;
    }
    html.dark .icon-gradient,
    html.dark i.icon-gradient,
    .dark .icon-gradient,
    .dark i.icon-gradient {
        background: linear-gradient(135deg, #22c55e 0%, #4ade80 35%, #facc15 100%) !important;
        -webkit-background-clip: text !important;
        background-clip: text !important;
        -webkit-text-fill-color: transparent !important;
        color: transparent !important;
        background-color: transparent !important;
    }

    /* Modern Apple-Style Bottom Blur when Scrolling */
    .apple-bottom-blur-mask {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 100px;
        pointer-events: none;
        z-index: 40;
        backdrop-filter: blur(40px) saturate(190%);
        -webkit-backdrop-filter: blur(40px) saturate(190%);
        mask-image: linear-gradient(to top, rgba(0, 0, 0, 1) 0%, rgba(0, 0, 0, 0.88) 55%, rgba(0, 0, 0, 0) 100%);
        -webkit-mask-image: linear-gradient(to top, rgba(0, 0, 0, 1) 0%, rgba(0, 0, 0, 0.88) 55%, rgba(0, 0, 0, 0) 100%);
        background: linear-gradient(to top, rgba(255, 255, 255, 0.85) 0%, rgba(255, 255, 255, 0.42) 50%, transparent 100%);
        opacity: 0;
        transform: translateY(8px);
        transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1), transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .apple-bottom-blur-mask::before {
        content: '';
        position: absolute;
        inset: 0;
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        mask-image: linear-gradient(to top, rgba(0, 0, 0, 1) 0%, rgba(0, 0, 0, 0.65) 45%, rgba(0, 0, 0, 0) 100%);
        -webkit-mask-image: linear-gradient(to top, rgba(0, 0, 0, 1) 0%, rgba(0, 0, 0, 0.65) 45%, rgba(0, 0, 0, 0) 100%);
        pointer-events: none;
    }
    html.dark .apple-bottom-blur-mask {
        background: linear-gradient(to top, rgba(9, 9, 11, 0.90) 0%, rgba(9, 9, 11, 0.50) 50%, transparent 100%);
    }
    .apple-bottom-blur-mask.active {
        opacity: 1;
        transform: translateY(0);
    }

    /* Modern Apple/Linear Blur-In Scroll Animation (Soft focus transition instead of pop-up) */
    .scroll-animate {
        opacity: 0 !important;
        filter: blur(16px) !important;
        -webkit-filter: blur(16px) !important;
        transform: scale(0.985) translateY(12px) !important;
        transition: opacity 0.85s cubic-bezier(0.16, 1, 0.3, 1), 
                    filter 0.85s cubic-bezier(0.16, 1, 0.3, 1), 
                    -webkit-filter 0.85s cubic-bezier(0.16, 1, 0.3, 1), 
                    transform 0.85s cubic-bezier(0.16, 1, 0.3, 1) !important;
        will-change: opacity, filter, transform;
    }
    .scroll-animate.show {
        opacity: 1 !important;
        filter: blur(0px) !important;
        -webkit-filter: blur(0px) !important;
        transform: scale(1) translateY(0) !important;
    }

    /* =======================================================
       HERO NAVIGATION BLEND (Over Hero Video / Dark Background)
       ======================================================= */
    header#main-public-header,
    header#main-public-header nav,
    #top-contact-bar {
        transition: background-color 0.35s ease, border-color 0.35s ease, box-shadow 0.35s ease, backdrop-filter 0.35s ease, -webkit-backdrop-filter 0.35s ease !important;
    }
    header#main-public-header nav a,
    header#main-public-header nav button {
        transition: color 0.25s ease, background-color 0.25s ease, border-color 0.25s ease !important;
    }

    header.hero-nav-blend,
    header.hero-nav-blend nav {
        background: transparent !important;
        border: none !important;
        border-color: transparent !important;
        border-bottom: none !important;
        border-bottom-color: transparent !important;
        border-top: none !important;
        box-shadow: none !important;
        outline: none !important;
        backdrop-filter: blur(0px) !important;
        -webkit-backdrop-filter: blur(0px) !important;
    }
    header.hero-nav-blend a h1 {
        color: #ffffff !important;
    }
    header.hero-nav-blend a span.text-green-600,
    header.hero-nav-blend a span.dark\:text-green-500 {
        color: #4ade80 !important;
    }
    header.hero-nav-blend nav a,
    header.hero-nav-blend nav button {
        border-bottom: none !important;
        border-color: transparent !important;
    }
    header.hero-nav-blend nav a:not(.btn-gradient):not([href="index.php"]):not([class*="group > div"]),
    header.hero-nav-blend nav button:not(.btn-gradient):not(#nav-theme-toggle):not(#mobile-theme-toggle):not(#mobile-menu-btn) {
        color: rgba(255, 255, 255, 0.90) !important;
    }
    header.hero-nav-blend nav a:not(.btn-gradient):not([href="index.php"]):not([class*="group > div"]):hover,
    header.hero-nav-blend nav button:not(.btn-gradient):not(#nav-theme-toggle):not(#mobile-theme-toggle):not(#mobile-menu-btn):hover {
        color: #ffffff !important;
    }
    header.hero-nav-blend nav button i.ph-caret-down {
        color: rgba(255, 255, 255, 0.75) !important;
        transition: transform 0.2s ease, color 0.2s ease !important;
    }
    header.hero-nav-blend nav button:hover i.ph-caret-down {
        color: #4ade80 !important;
    }
    header.hero-nav-blend #nav-theme-toggle,
    header.hero-nav-blend #mobile-theme-toggle {
        background-color: rgba(255, 255, 255, 0.12) !important;
        color: #ffffff !important;
        border: none !important;
        outline: none !important;
        box-shadow: none !important;
    }
    header.hero-nav-blend #nav-theme-toggle:hover,
    header.hero-nav-blend #mobile-theme-toggle:hover {
        background-color: rgba(255, 255, 255, 0.22) !important;
        border: none !important;
    }
    header.hero-nav-blend #mobile-menu-btn {
        color: #ffffff !important;
        border: none !important;
    }
    header.hero-nav-blend #mobile-menu-btn:hover {
        background-color: rgba(255, 255, 255, 0.15) !important;
    }
    /* Remove all vertical, horizontal and divider lines in blended mode */
    header.hero-nav-blend .border-l,
    header.hero-nav-blend .border-r,
    header.hero-nav-blend .border-t,
    header.hero-nav-blend .border-b,
    header.hero-nav-blend hr {
        border: none !important;
        border-color: transparent !important;
    }
    /* Dropdown Menus in Hero Blend state keep dark frosted background */
    header.hero-nav-blend .group > div[class*="absolute"] {
        background: rgba(15, 23, 42, 0.96) !important;
        backdrop-filter: blur(24px) !important;
        -webkit-backdrop-filter: blur(24px) !important;
        border: none !important;
        border-color: transparent !important;
        box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.70) !important;
    }
    header.hero-nav-blend .group > div[class*="absolute"] a {
        color: rgba(255, 255, 255, 0.88) !important;
    }
    header.hero-nav-blend .group > div[class*="absolute"] a:hover {
        background-color: rgba(255, 255, 255, 0.12) !important;
        color: #ffffff !important;
    }
    header.hero-nav-blend .group > div[class*="absolute"] a i {
        color: #4ade80 !important;
    }
    /* Mobile Menu in Hero Blend state */
    header.hero-nav-blend #mobile-menu {
        background-color: rgba(15, 23, 42, 0.98) !important;
        backdrop-filter: blur(24px) !important;
        -webkit-backdrop-filter: blur(24px) !important;
        border: none !important;
        border-bottom: none !important;
    }
    header.hero-nav-blend #mobile-menu p {
        color: rgba(255, 255, 255, 0.50) !important;
    }
    header.hero-nav-blend #mobile-menu a:not(.btn-gradient) {
        color: rgba(255, 255, 255, 0.88) !important;
    }
    header.hero-nav-blend #mobile-menu a:not(.btn-gradient):hover {
        background-color: rgba(255, 255, 255, 0.12) !important;
        color: #ffffff !important;
    }
    /* Top Contact Bar in Hero Blend - zero border/lines */
    #top-contact-bar.hero-nav-blend {
        background: transparent !important;
        border: none !important;
        border-color: transparent !important;
        border-bottom: none !important;
        border-bottom-color: transparent !important;
        box-shadow: none !important;
        outline: none !important;
        color: rgba(255, 255, 255, 0.75) !important;
    }
    #top-contact-bar.hero-nav-blend * {
        border-color: transparent !important;
    }
    #top-contact-bar.hero-nav-blend a,
    #top-contact-bar.hero-nav-blend button {
        color: rgba(255, 255, 255, 0.75) !important;
        border: none !important;
    }
    #top-contact-bar.hero-nav-blend a:hover,
    #top-contact-bar.hero-nav-blend button:hover {
        color: #ffffff !important;
    }
    #top-contact-bar.hero-nav-blend i {
        color: #4ade80 !important;
    }

    /* =======================================================
       GLOBAL ZERO OUTSIDE LINE STROKE / BORDERLESS DESIGN
       ======================================================= */
    .border,
    .border-2,
    .border-4,
    .border-\[3px\],
    .border-t,
    .border-b,
    .border-l,
    .border-r,
    .border-slate-100,
    .border-slate-200,
    .border-slate-200\/40,
    .border-slate-200\/60,
    .border-slate-200\/70,
    .border-slate-200\/80,
    .border-slate-700,
    .border-slate-800,
    .border-white\/10,
    .border-white\/20,
    .border-white\/40,
    .dark .border-zinc-700,
    .dark .border-zinc-800,
    .dark .border-zinc-800\/80,
    .dark .border-zinc-900,
    .dark .border-zinc-950 {
        border-color: transparent !important;
    }

    /* Remove all outline / outside stroke lines */
    input,
    select,
    textarea,
    button,
    a {
        outline: none !important;
        -webkit-tap-highlight-color: transparent;
    }
    input:focus,
    input:focus-visible,
    select:focus,
    select:focus-visible,
    textarea:focus,
    textarea:focus-visible,
    button:focus,
    button:focus-visible,
    a:focus,
    a:focus-visible {
        outline: none !important;
        border-color: transparent !important;
        box-shadow: none !important;
        -webkit-box-shadow: none !important;
    }
</style>

<header id="main-public-header" class="sticky top-0 z-50 transition-all duration-300 <?= $has_hero ? 'hero-nav-blend' : '' ?>">
    <nav id="main-public-nav" class="bg-white/90 dark:bg-zinc-950/90 backdrop-blur-xl transition-all duration-300">
        <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                
                <!-- Logo -->
                <a href="index.php" class="flex items-center gap-2.5 group mr-4 shrink-0">
                    <img src="TALA-LOGO.ico" class="w-9 h-9 object-contain group-hover:scale-105 transition-transform duration-300" alt="TALA Logo" style="filter: none !important; -webkit-filter: none !important;" onerror="this.src='https://via.placeholder.com/40?text=TALA'">
                    <div class="flex flex-col justify-center hidden sm:flex">
                        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white tracking-tight leading-none mb-0.5">TALA</h1>
                        <span class="text-[10px] font-bold text-green-600 dark:text-green-500 uppercase tracking-widest leading-none">Tumauini 2026</span>
                    </div>
                </a>
                
                <!-- Desktop Navigation Links -->
                <div class="hidden lg:flex items-center space-x-2 xl:space-x-6">
                    
                    <!-- ABOUT DROPDOWN -->
                    <div class="relative group">
                        <button class="<?= getDropdownParentClass(['features.php', 'updates.php', 'public_calendar.php'], $current_page) ?>">
                            About TALA <i class="ph-bold ph-caret-down text-[10px] group-hover:rotate-180 transition-transform duration-200"></i>
                        </button>
                        <div class="absolute top-full left-0 mt-3 w-52 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-xl shadow-xl rounded-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform translate-y-2 group-hover:translate-y-0 z-50">
                            <div class="absolute -top-3 left-0 w-full h-3"></div> <!-- Invisible bridge -->
                            <div class="p-2 flex flex-col gap-1">
                                <a href="public_calendar.php" class="<?= getDropdownChildClass('public_calendar.php', $current_page) ?>">
                                    <i class="ph-bold ph-calendar-blank icon-gradient"></i> Calendar
                                </a>
                                <a href="features.php" class="<?= getDropdownChildClass('features.php', $current_page) ?>">
                                    <i class="ph-bold ph-squares-four icon-gradient"></i> Core Features
                                </a>
                                <a href="updates.php" class="<?= getDropdownChildClass('updates.php', $current_page) ?>">
                                    <i class="ph-bold ph-bell-ringing icon-gradient"></i> System Updates
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- COMMUNITY DROPDOWN -->
                    <div class="relative group">
                        <button class="<?= getDropdownParentClass(['public_events.php', 'scholarships.php', 'public_polls.php', 'suggestions.php'], $current_page) ?>">
                            Community <i class="ph-bold ph-caret-down text-[10px] group-hover:rotate-180 transition-transform duration-200"></i>
                        </button>
                        <div class="absolute top-full left-0 mt-3 w-52 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-xl shadow-xl rounded-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform translate-y-2 group-hover:translate-y-0 z-50">
                            <div class="absolute -top-3 left-0 w-full h-3"></div> <!-- Invisible bridge -->
                            <div class="p-2 flex flex-col gap-1">
                                <a href="public_events.php" class="<?= getDropdownChildClass('public_events.php', $current_page) ?>">
                                    <i class="ph-bold ph-calendar-star icon-gradient"></i> All Events
                                </a>
                                <a href="scholarships.php" class="<?= getDropdownChildClass('scholarships.php', $current_page) ?>">
                                    <i class="ph-bold ph-graduation-cap icon-gradient"></i> Scholarships
                                </a>
                                <a href="public_polls.php" class="<?= getDropdownChildClass('public_polls.php', $current_page) ?>">
                                    <i class="ph-bold ph-chart-bar icon-gradient"></i> Youth Polls
                                </a>
                                <a href="suggestions.php" class="<?= getDropdownChildClass('suggestions.php', $current_page) ?>">
                                    <i class="ph-bold ph-megaphone icon-gradient"></i> Voice / Feedback
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- GOVERNANCE DROPDOWN -->
                    <div class="relative group">
                        <button class="<?= getDropdownParentClass(['public_disclosure.php', 'public_budget.php', 'public_governance.php'], $current_page) ?>">
                            Governance <i class="ph-bold ph-caret-down text-[10px] group-hover:rotate-180 transition-transform duration-200"></i>
                        </button>
                        <div class="absolute top-full left-0 mt-3 w-56 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-xl shadow-xl rounded-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform translate-y-2 group-hover:translate-y-0 z-50">
                            <div class="absolute -top-3 left-0 w-full h-3"></div> <!-- Invisible bridge -->
                            <div class="p-2 flex flex-col gap-1">
                                <a href="public_disclosure.php" class="<?= getDropdownChildClass('public_disclosure.php', $current_page) ?>">
                                    <i class="ph-bold ph-file-text icon-gradient"></i> Full Disclosure
                                </a>
                                <a href="public_budget.php" class="<?= getDropdownChildClass('public_budget.php', $current_page) ?>">
                                    <i class="ph-bold ph-chart-pie-slice icon-gradient"></i> Budget Reports
                                </a>
                                <a href="public_governance.php" class="<?= getDropdownChildClass('public_governance.php', $current_page) ?>">
                                    <i class="ph-bold ph-folder-open icon-gradient"></i> Public Documents
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- DIRECTORIES DROPDOWN -->
                    <div class="relative group">
                        <button class="<?= getDropdownParentClass(['mydc_registry.php', 'public_officials.php', 'public_yorp.php'], $current_page) ?>">
                            Directories <i class="ph-bold ph-caret-down text-[10px] group-hover:rotate-180 transition-transform duration-200"></i>
                        </button>
                        <div class="absolute top-full left-0 mt-3 w-56 bg-white/95 dark:bg-zinc-900/95 backdrop-blur-xl shadow-xl rounded-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 transform translate-y-2 group-hover:translate-y-0 z-50">
                            <div class="absolute -top-3 left-0 w-full h-3"></div> <!-- Invisible bridge -->
                            <div class="p-2 flex flex-col gap-1">
                                <a href="mydc_registry.php" class="<?= getDropdownChildClass('mydc_registry.php', $current_page) ?>">
                                    <i class="ph-bold ph-users-three icon-gradient"></i> MYDC Registry
                                </a>
                                <a href="public_officials.php" class="<?= getDropdownChildClass('public_officials.php', $current_page) ?>">
                                    <i class="ph-bold ph-identification-badge icon-gradient"></i> SK Leaders
                                </a>
                                <a href="public_yorp.php" class="<?= getDropdownChildClass('public_yorp.php', $current_page) ?>">
                                    <i class="ph-bold ph-address-book icon-gradient"></i> YORP Tracker
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions (Portal & Register) -->
                    <div class="flex items-center gap-4 pl-4 ml-2">
                        
                        <!-- Theme Toggle Button in Navigation -->
                        <button onclick="toggleTheme()" id="nav-theme-toggle" class="w-9 h-9 rounded-xl flex items-center justify-center bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 transition-colors focus:outline-none tap-effect" title="Toggle Dark Mode">
                            <i id="nav-theme-icon" class="ph-fill ph-moon text-base"></i>
                        </button>
                        
                        <a href="public_registration.php" class="<?= getNavClass('public_registration.php', $current_page) ?>">Register Now</a>
                        <a href="login.php" class="btn-gradient text-white font-bold px-4 py-2.5 rounded-xl flex items-center justify-center text-xs whitespace-nowrap transition-all shadow-none tap-effect">
                            <i class="ph-bold ph-sign-in mr-1.5 text-base"></i> Portal
                        </a>
                    </div>
                </div>

                <!-- Mobile Menu Toggle Button -->
                <div class="lg:hidden flex items-center gap-2">
                    <button onclick="toggleTheme()" id="mobile-theme-toggle" class="w-9 h-9 rounded-xl flex items-center justify-center bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800 transition-colors focus:outline-none tap-effect">
                        <i id="mobile-theme-icon" class="ph-fill ph-moon text-base"></i>
                    </button>
                    
                    <button id="mobile-menu-btn" class="w-9 h-9 flex items-center justify-center rounded-xl text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-900 focus:outline-none transition-colors tap-effect">
                        <i class="ph-bold ph-list text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Mobile Links Dropdown -->
        <div id="mobile-menu" class="hidden lg:hidden bg-white/95 dark:bg-zinc-950/95 backdrop-blur-xl px-6 py-6 max-h-[80vh] overflow-y-auto space-y-5">
            
            <!-- Category 1 -->
            <div>
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 mb-2 px-3">Community & Programs</p>
                <div class="space-y-1">
                    <a href="public_events.php" class="<?= getMobileNavClass('public_events.php', $current_page) ?>">Events & Activities</a>
                    <a href="public_calendar.php" class="<?= getMobileNavClass('public_calendar.php', $current_page) ?>">Community Calendar</a>
                    <a href="scholarships.php" class="<?= getMobileNavClass('scholarships.php', $current_page) ?>">Scholarships</a>
                    <a href="public_polls.php" class="<?= getMobileNavClass('public_polls.php', $current_page) ?>">Youth Polls</a>
                    <a href="suggestions.php" class="<?= getMobileNavClass('suggestions.php', $current_page) ?>">Voice & Feedback</a>
                </div>
            </div>

            <!-- Category 2 -->
            <div>
                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 mb-2 px-3">Directories & Governance</p>
                <div class="space-y-1">
                    <a href="mydc_registry.php" class="<?= getMobileNavClass('mydc_registry.php', $current_page) ?>">MYDC Registry</a>
                    <a href="public_officials.php" class="<?= getMobileNavClass('public_officials.php', $current_page) ?>">SK Leaders</a>
                    <a href="public_yorp.php" class="<?= getMobileNavClass('public_yorp.php', $current_page) ?>">YORP Tracker</a>
                    <a href="public_disclosure.php" class="<?= getMobileNavClass('public_disclosure.php', $current_page) ?>">Full Disclosure</a>
                    <a href="public_budget.php" class="<?= getMobileNavClass('public_budget.php', $current_page) ?>">Budget Reports</a>
                    <a href="public_governance.php" class="<?= getMobileNavClass('public_governance.php', $current_page) ?>">Public Documents</a>
                </div>
            </div>

            <!-- Actions -->
            <div class="pt-5 space-y-2.5">
                <a href="public_registration.php" class="block text-center text-sm font-bold text-green-600 dark:text-green-500 hover:text-green-700 py-2 transition-colors">Register Profile</a>
                <a href="login.php" class="btn-gradient text-white font-bold px-5 py-3 rounded-xl flex items-center justify-center w-full text-sm transition-all shadow-none tap-effect">
                    <i class="ph-bold ph-sign-in mr-2 text-lg"></i> Access Admin Portal
                </a>
            </div>
        </div>
    </nav>
</header>

<!-- Apple-Style Bottom Blur Mask -->
<div id="apple-bottom-blur-mask" class="apple-bottom-blur-mask"></div>

<!-- Mobile Menu, Global Dark Mode & Apple Scroll Blur Script -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
                
                const icon = mobileMenuBtn.querySelector('i');
                if (icon) {
                    if (mobileMenu.classList.contains('hidden')) {
                        icon.classList.remove('ph-x');
                        icon.classList.add('ph-list');
                    } else {
                        icon.classList.remove('ph-list');
                        icon.classList.add('ph-x');
                    }
                }
            });
        }

        syncThemeIcons();
    });

    function syncThemeIcons() {
        const navIcon = document.getElementById('nav-theme-icon');
        const mobileIcon = document.getElementById('mobile-theme-icon');
        const topIcon = document.getElementById('theme-icon');
        const isDark = document.documentElement.classList.contains('dark');
        
        if (isDark) {
            if (navIcon) { navIcon.classList.remove('ph-moon'); navIcon.classList.add('ph-sun'); }
            if (mobileIcon) { mobileIcon.classList.remove('ph-moon'); mobileIcon.classList.add('ph-sun'); }
            if (topIcon) { topIcon.classList.remove('ph-moon'); topIcon.classList.add('ph-sun'); }
        } else {
            if (navIcon) { navIcon.classList.remove('ph-sun'); navIcon.classList.add('ph-moon'); }
            if (mobileIcon) { mobileIcon.classList.remove('ph-sun'); mobileIcon.classList.add('ph-moon'); }
            if (topIcon) { topIcon.classList.remove('ph-sun'); topIcon.classList.add('ph-moon'); }
        }
    }

    window.toggleTheme = function() {
        const html = document.documentElement;
        if (html.classList.contains('dark')) {
            html.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        } else {
            html.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        }
        syncThemeIcons();
    };

    // Modern Apple/Linear Blur-In Scroll Animation Observer
    (function initScrollBlurAnimation() {
        if (!('IntersectionObserver' in window)) return;
        const observerOptions = {
            root: null,
            rootMargin: '0px 0px -40px 0px',
            threshold: 0.08
        };

        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('show');
                    obs.unobserve(entry.target);
                }
            });
        }, observerOptions);

        function observeElements() {
            const targets = document.querySelectorAll('.scroll-animate:not(.show)');
            targets.forEach(el => observer.observe(el));
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', observeElements);
        } else {
            observeElements();
        }
        window.addEventListener('load', observeElements);
    })();

    // Hero Section Nav Blend Logic (Transparent over hero, default when scrolled)
    (function initHeroNavBlend() {
        function updateNavBlend() {
            const header = document.getElementById('main-public-header');
            if (!header) return;
            const contactBar = document.getElementById('top-contact-bar');
            const hero = document.getElementById('hero-section') || document.querySelector('section.bg-slate-950');

            // If this page does not have a hero section, ensure default navbar styling
            if (!hero) {
                header.classList.remove('hero-nav-blend');
                if (contactBar) contactBar.classList.remove('hero-nav-blend');
                return;
            }

            const scrollY = window.scrollY || document.documentElement.scrollTop || 0;
            // When in hero section (top < 60px)
            if (scrollY < 60) {
                header.classList.add('hero-nav-blend');
                if (contactBar) contactBar.classList.add('hero-nav-blend');
            } else {
                header.classList.remove('hero-nav-blend');
                if (contactBar) contactBar.classList.remove('hero-nav-blend');
            }
        }

        let ticking = false;
        function onScroll() {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    updateNavBlend();
                    ticking = false;
                });
                ticking = true;
            }
        }

        window.addEventListener('scroll', onScroll, { passive: true });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', updateNavBlend);
        } else {
            updateNavBlend();
        }
        window.addEventListener('load', updateNavBlend);
    })();

    // Apple-Style Bottom Blur on Scroll
    (function initAppleScrollBlur() {
        let ticking = false;
        function updateScrollEffects() {
            const mask = document.getElementById('apple-bottom-blur-mask');
            const scrollY = window.scrollY || document.documentElement.scrollTop || 0;

            if (mask) {
                if (scrollY > 40) {
                    mask.classList.add('active');
                } else {
                    mask.classList.remove('active');
                }
            }
            ticking = false;
        }

        window.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(updateScrollEffects);
                ticking = true;
            }
        }, { passive: true });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', updateScrollEffects);
        } else {
            updateScrollEffects();
        }
    })();
</script>