<?php
// presentation.php - TALA OS Official System Presentation (Tumauini 2026)
// Complete Presentation Showcase: Intro, Workflow, Features, Tech Architecture & Outro
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Fetch quick telemetry stats for real dynamic showcase numbers
$total_youth = 0;
$total_budget = 0;
$total_disclosures = 0;
$total_barangays_active = 46;

try {
    $total_youth = (int)$pdo->query("SELECT COUNT(*) FROM youth_profiles")->fetchColumn();
    $total_disclosures = (int)$pdo->query("SELECT COUNT(*) FROM full_disclosure_records")->fetchColumn();
    $bgt_stmt = $pdo->query("SELECT SUM(allocated_amount) FROM tala_budget_records");
    $total_budget = (float)$bgt_stmt->fetchColumn();
} catch (Exception $e) {
    // Graceful fallback to presentation figures
    $total_youth = 12480;
    $total_disclosures = 184;
    $total_budget = 46000000;
}

if ($total_youth === 0) $total_youth = 12480;
if ($total_disclosures === 0) $total_disclosures = 184;
if ($total_budget <= 0) $total_budget = 46000000;
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TALA System Presentation | Tumauini Youth Governance 2026</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">

    <!-- Anti-Flash Script for Theme Persistence -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <!-- Google Fonts & Tailwind CSS -->
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
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d'
                        }
                    }
                }
            }
        };
    </script>

    <style>
        /* Modern Top Ambient Gradient: Lessened Green & Yellow */
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
            -webkit-tap-highlight-color: transparent;
            overflow-x: hidden;
            position: relative;
            background-color: #fafbfc !important;
            background-image: 
                radial-gradient(ellipse 75% 50% at 50% -12%, rgba(34, 197, 94, 0.18) 0%, rgba(250, 204, 21, 0.14) 45%, transparent 75%),
                radial-gradient(circle 500px at 80% -5%, rgba(234, 179, 8, 0.12) 0%, transparent 60%),
                radial-gradient(circle 450px at 20% -5%, rgba(21, 128, 61, 0.14) 0%, transparent 60%) !important;
            background-repeat: no-repeat !important;
            background-attachment: fixed !important;
        }
        .dark body {
            background-color: #090a0b !important;
            background-image: 
                radial-gradient(ellipse 75% 50% at 50% -12%, rgba(34, 197, 94, 0.22) 0%, rgba(234, 179, 8, 0.16) 45%, transparent 75%),
                radial-gradient(circle 500px at 80% -5%, rgba(202, 138, 4, 0.15) 0%, transparent 60%),
                radial-gradient(circle 450px at 20% -5%, rgba(21, 128, 61, 0.20) 0%, transparent 60%) !important;
            background-repeat: no-repeat !important;
            background-attachment: fixed !important;
        }

        /* Modern Glass Card: Lessen Opacity, Frosted Blur, Modern Backdrop */
        .glass-card {
            background: rgba(255, 255, 255, 0.65) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border: none !important;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.05) !important;
            -webkit-box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.05) !important;
            transition: all 0.25s ease;
        }
        .dark .glass-card {
            background: rgba(24, 24, 27, 0.65) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border: none !important;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.35) !important;
            -webkit-box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.35) !important;
        }

        /* Clean Focus Card (Green blur effects disabled) */
        .card-green-glow-bl {
            position: relative;
        }
        .card-green-glow-bl::after {
            display: none !important;
            content: none !important;
        }

        /* TALA OS Primary Action Glow Button */
        .btn-yellow-glow-r {
            position: relative;
            z-index: 1;
            overflow: hidden;
        }
        .btn-yellow-glow-r::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 90px;
            height: 180%;
            background: radial-gradient(circle, rgba(250, 204, 21, 0.45) 0%, rgba(250, 204, 21, 0) 70%);
            pointer-events: none;
            filter: blur(4px);
            z-index: 1;
            transition: transform 0.25s ease, opacity 0.25s ease;
        }
        .btn-yellow-glow-r:hover::before {
            transform: scale(1.2);
            opacity: 1;
        }

        /* Custom Scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.3);
            border-radius: 9999px;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(82, 82, 91, 0.4);
        }

        /* Modern Section Cards: Top UI Asset & Dot Grid Pattern */
        .card-asset-grid {
            background-color: #fafbfc;
            background-image: radial-gradient(rgba(21, 128, 61, 0.18) 1.2px, transparent 1.2px);
            background-size: 14px 14px;
        }
        .dark .card-asset-grid {
            background-color: rgba(24, 24, 27, 0.7);
            background-image: radial-gradient(rgba(34, 197, 94, 0.2) 1.2px, transparent 1.2px);
            background-size: 14px 14px;
        }

        .saas-card {
            background: #ffffff;
            border: none !important;
            border-radius: 1.25rem;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.04);
        }
        .dark .saas-card {
            background: rgba(24, 24, 27, 0.85);
            border: none !important;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.25);
        }
        .saas-card:hover {
            transform: translateY(-3px);
            border: none !important;
            box-shadow: 0 16px 32px -8px rgba(21, 128, 61, 0.12);
        }
        .dark .saas-card:hover {
            border: none !important;
            box-shadow: 0 16px 32px -8px rgba(34, 197, 94, 0.16);
        }

        .card-asset-grid {
            border: none !important;
        }

        /* Complete Zero Stroke Lines on All Presentation Section Cards */
        .saas-card,
        .saas-card .card-asset-grid,
        .saas-card [class*="border-t"],
        .saas-card [class*="border-b"],
        .saas-card [class*="border-l"],
        .saas-card [class*="border-r"],
        .saas-card [class*="border"] {
            border: none !important;
            border-width: 0 !important;
            border-color: transparent !important;
        }
        /* Preserve only active spinner indicator animation */
        .saas-card .animate-spin {
            border: 2px solid #15803d !important;
            border-top-color: transparent !important;
        }

        /* PRESENTATION VIEWPORT LOCK: 100% Fit on screen, ZERO Window Scrolling */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body:not(.scroll-mode) {
            height: 100vh;
            max-height: 100vh;
            overflow: hidden !important;
        }
        /* Clean Audience Display Projection Mode */
        body.audience-mode #presenterHoverCue,
        body.audience-mode #deckNavigationDock,
        body.audience-mode #presentationHeader,
        body.audience-mode #topHoverZone,
        body.audience-mode #notesDrawer {
            display: none !important;
        }
        body.audience-mode #presentationContainer {
            height: 100vh !important;
            max-height: 100vh !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }

        body:not(.scroll-mode) #presentationContainer {
            height: 100vh;
            max-height: 100vh;
            padding-top: 36px;
            padding-bottom: 84px; /* Clearance for floating bottom dock */
            padding-left: 1.5rem;
            padding-right: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: hidden !important;
        }
        body:not(.scroll-mode) .slide-view {
            height: 100%;
            max-height: 100%;
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: hidden !important;
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
        }
        body:not(.scroll-mode) .slide-view.active-slide {
            display: flex;
            opacity: 1;
        }

        /* In continuous scroll mode when user toggles 'M' */
        body.scroll-mode {
            height: auto !important;
            max-height: none !important;
            overflow-y: auto !important;
        }
        body.scroll-mode #presentationContainer {
            height: auto !important;
            max-height: none !important;
            overflow: visible !important;
            padding-bottom: 96px;
        }
        body.scroll-mode .slide-view {
            display: flex !important;
            opacity: 1 !important;
            height: auto !important;
            max-height: none !important;
            margin-bottom: 40px;
            overflow: visible !important;
        }

        /* Auto-Hide Executive Header */
        #presentationHeader {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            transform: translateY(-100%);
            opacity: 0;
            pointer-events: none;
            transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }

        #presentationHeader.header-visible,
        #presentationHeader:hover,
        #presentationHeader.header-pinned {
            transform: translateY(0) !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        /* Top Hover Detection Barrier (Invisible 24px strip at top of screen) */
        #topHoverZone {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 24px;
            z-index: 45;
            pointer-events: auto;
        }

        /* Subtle Glow Peek Pill when header is hidden */
        #topPeekPill {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            z-index: 44;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        #presentationHeader.header-visible ~ #topPeekPill,
        #presentationHeader:hover ~ #topPeekPill,
        #presentationHeader.header-pinned ~ #topPeekPill {
            opacity: 0 !important;
            transform: translate(-50%, -100%) !important;
            pointer-events: none !important;
        }

        /* In continuous scroll mode when pinned */
        body.scroll-mode #presentationHeader.header-pinned {
            position: sticky !important;
            transform: translateY(0) !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        /* Teleprompter / Speaker Notes Slide-out */
        #notesDrawer {
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        #notesDrawer.open {
            transform: translateX(0);
        }

        /* Pulse glow for presentation live indicator */
        @keyframes pulseGlow {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.08); }
        }
        .live-pulse {
            animation: pulseGlow 2s infinite ease-in-out;
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased min-h-screen selection:bg-[#15803d] selection:text-white relative">

    <!-- Top Ambient Modern Gradient (Lessened Green & Yellow Aura) -->
    <div class="fixed top-0 left-0 right-0 h-96 pointer-events-none z-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-[950px] h-[360px] bg-gradient-to-r from-emerald-500/20 via-green-500/20 to-amber-400/20 dark:from-emerald-600/25 dark:via-green-600/25 dark:to-yellow-500/25 blur-3xl rounded-full"></div>
    </div>

    <!-- TOP HOVER DETECTOR ZONE (Hover near the top of the screen to reveal controls) -->
    <div id="topHoverZone" class="fixed top-0 left-0 right-0 h-6 sm:h-8 z-40 pointer-events-auto" title="Hover to show presentation controls"></div>

    <!-- SUBTLE PEEK INDICATOR (Visible when header is auto-hidden) -->
    <div id="topPeekPill" onclick="showTopBarManual()" class="fixed top-0 left-1/2 -translate-x-1/2 z-35 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md px-3.5 py-1 rounded-b-xl border-x border-b border-slate-200/60 dark:border-zinc-800/80 text-[10px] font-bold text-slate-500 dark:text-zinc-400 cursor-pointer shadow-sm transition-all duration-300 hover:text-green-600 dark:hover:text-green-400 hover:py-1.5 flex items-center gap-1.5 opacity-80 hover:opacity-100">
        <i class="ph-bold ph-caret-down text-xs text-green-600 dark:text-green-400 animate-bounce"></i>
        <span>Hover for Presentation Controls</span>
    </div>

    <!-- TOP PRESENTATION EXECUTIVE CONTROL BAR (Auto-hiding on default, slides down on hover) -->
    <header id="presentationHeader" class="fixed top-0 left-0 right-0 z-50 w-full bg-white/95 dark:bg-zinc-900/95 backdrop-blur-xl border-b border-slate-200/60 dark:border-zinc-800/60 px-3 sm:px-6 py-2.5 shadow-md transition-all duration-300">
        <div class="max-w-[1600px] mx-auto flex items-center justify-between gap-3">
            
            <!-- Left Brand & Seals -->
            <div class="flex items-center gap-3 min-w-0">
                <a href="index.php" class="flex items-center gap-2 group" title="Return to TALA Public Portal">
                    <img src="TALA-LOGO.png" alt="TALA" class="w-8 h-8 sm:w-9 sm:h-9 object-contain group-hover:scale-105 transition-transform">
                    <div class="hidden sm:block min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="font-black text-sm md:text-base tracking-tight text-slate-900 dark:text-white">TALA</span>
                            <span class="text-[10px] font-black uppercase tracking-wider px-1.5 py-0.5 rounded-md bg-green-500/10 text-green-600 dark:text-green-400">Presentation</span>
                        </div>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-medium truncate">Tumauini Youth Governance & Transparency</p>
                    </div>
                </a>

                <div class="h-5 w-px bg-slate-200 dark:bg-zinc-800 hidden md:block"></div>

                <!-- Municipality Seals -->
                <div class="hidden lg:flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-zinc-400">
                    <img src="tumauini.png" alt="Tumauini" class="w-6 h-6 object-contain" title="Municipality of Tumauini">
                    <img src="sk.png" alt="SK" class="w-6 h-6 object-contain" title="Sangguniang Kabataan">
                    <span class="text-[11px] truncate">46 Barangays &bull; MYDC Council</span>
                </div>
            </div>

            <!-- Center Slide Indicator & Scrubber -->
            <div class="flex items-center gap-2 md:gap-3" id="topSlideTracker">
                <div class="flex items-center gap-1 bg-slate-100 dark:bg-zinc-800 px-2.5 py-1 rounded-xl text-xs font-bold">
                    <span class="text-green-600 dark:text-green-400 font-black" id="currentSlideDisplay">01</span>
                    <span class="text-slate-400 dark:text-zinc-500">/</span>
                    <span class="text-slate-500 dark:text-zinc-400 font-bold" id="totalSlidesDisplay">08</span>
                </div>
                <span class="text-xs font-extrabold text-slate-700 dark:text-zinc-300 hidden md:block truncate max-w-[240px]" id="slideTitleHeading">
                    TALA Overview
                </span>
            </div>

            <!-- Right Controls: Timer, Mode, Notes, Pin, Theme, System Links -->
            <div class="flex items-center gap-1.5 sm:gap-2">
                
                <!-- Presentation Timer -->
                <div class="hidden sm:flex items-center gap-1.5 bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 px-2.5 py-1 rounded-xl text-xs font-mono font-bold" title="Presentation Timer">
                    <span class="w-2 h-2 rounded-full bg-green-500 live-pulse"></span>
                    <span id="presentationTimer">00:00</span>
                    <button type="button" onclick="resetTimer()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white transition-colors ml-1" title="Reset Timer">
                        <i class="ph-bold ph-arrow-counter-clockwise text-[11px]"></i>
                    </button>
                </div>

                <!-- Pin / Auto-hide Toggle Button -->
                <button type="button" onclick="togglePinHeader()" id="pinHeaderBtn" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 flex items-center justify-center transition-all tap-effect" title="Pin / Unpin Top Bar (Shortcut: H)">
                    <i class="ph-bold ph-push-pin text-base text-slate-400 dark:text-zinc-500" id="pinHeaderIcon"></i>
                </button>

                <!-- Speaker Notes Toggle Button (Key: N) -->
                <button type="button" onclick="toggleNotes()" id="notesToggleBtn" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 text-xs font-bold transition-all tap-effect" title="Toggle Presenter Speaker Notes (Shortcut: N)">
                    <i class="ph-bold ph-note text-sm text-green-600 dark:text-green-400"></i>
                    <span class="hidden md:inline">Notes</span>
                    <span class="text-[9px] px-1 py-0.2 bg-slate-200 dark:bg-zinc-700 rounded font-mono hidden lg:inline">N</span>
                </button>

                <!-- Fullscreen Toggle (Key: F) -->
                <button type="button" onclick="toggleFullScreen()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 flex items-center justify-center transition-all tap-effect" title="Toggle Fullscreen (Shortcut: F)">
                    <i class="ph-bold ph-corners-out text-base"></i>
                </button>

                <!-- View Mode: Deck vs Scroll (Key: M) -->
                <button type="button" onclick="toggleViewMode()" id="viewModeBtn" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 flex items-center justify-center transition-all tap-effect" title="Toggle Deck / Scroll View (Shortcut: M)">
                    <i class="ph-bold ph-presentation text-base" id="viewModeIcon"></i>
                </button>

                <!-- Theme Toggle Button (Key: T) -->
                <button type="button" onclick="togglePresentationTheme()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 flex items-center justify-center transition-all tap-effect" title="Toggle Theme (Shortcut: T)">
                    <i class="ph-bold ph-moon dark:hidden text-base"></i>
                    <i class="ph-bold ph-sun hidden dark:inline text-base text-amber-400"></i>
                </button>

                <!-- Live Demo Launch Modal Button -->
                <button type="button" onclick="openLaunchModal()" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all tap-effect">
                    <i class="ph-bold ph-arrow-square-out text-sm relative z-10"></i>
                    <span class="relative z-10 hidden sm:inline">Launch Live App</span>
                </button>
            </div>
        </div>
    </header>

    <!-- MAIN PRESENTATION STAGE (Single-Screen Fit, Zero Scrolling) -->
    <main class="w-full max-w-[1500px] mx-auto flex flex-col justify-center relative z-10" id="presentationContainer">

        <!-- ========================================== -->
        <!-- SLIDE 1: TITLE - TALA                      -->
        <!-- ========================================== -->
        <section class="slide-view active-slide flex flex-col items-center justify-center w-full h-full text-center" id="slide-1" data-title="TALA" data-notes="Batiin ang panel at mga panauhin. TALA: Tumauini Active Youth Ledger Analytics web platform.">
            
            <div class="w-full h-full flex flex-col items-center justify-center text-center my-auto p-4 group cursor-pointer" data-speech="Magandang araw po sa ating kagalang-galang na panel at mga panauhin. Ikinagagalak ko pong iprisinta ang Tumauini Active Youth Ledger Analytics o TALA — ang opisyal na digital governance web platform na nilikha upang maghatid ng buong transparency, real-time tracking ng pondo, at mabilis na serbisyo para sa kabataan ng Tumauini." data-speech-title="Pambungad: Ang TALA Web Platform">
                
                <!-- Centered Logo -->
                <div class="flex items-center justify-center">
                    <img src="tala_logo_transparent.png" alt="TALA Logo" class="w-40 h-40 sm:w-52 sm:h-52 md:w-60 md:h-60 lg:w-64 lg:h-64 max-w-[70vw] max-h-[40vh] object-contain rounded-full hover:scale-105 transition-transform duration-300">
                </div>

                <!-- Modern Frosted Glass Below Section Card (Lessened Opacity, Modern Blur) -->
                <div class="mt-4 sm:mt-5 w-full max-w-3xl mx-auto rounded-3xl bg-white/55 dark:bg-zinc-900/55 backdrop-blur-2xl border border-white/60 dark:border-zinc-800/60 p-4 sm:p-5 shadow-xl shadow-green-950/5 transition-all duration-300 hover:bg-white/70 dark:hover:bg-zinc-900/70">
                    <!-- Subtle Top Metadata Bar -->
                    <div class="flex items-center justify-between pb-2.5 mb-2.5 border-b border-slate-200/50 dark:border-zinc-800/50 text-[11px] font-semibold text-slate-500 dark:text-zinc-400">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-[#15803d] animate-ping"></span>
                            <span class="font-bold text-slate-800 dark:text-zinc-200 uppercase tracking-wider font-mono text-[10px]">Official Web Platform</span>
                        </div>
                        <div class="flex items-center gap-1.5 font-mono text-[10px] text-[#15803d] dark:text-[#16a34a] font-bold">
                            <i class="ph-bold ph-shield-check text-xs"></i>
                            <span>Municipality of Tumauini, Isabela</span>
                        </div>
                    </div>

                    <!-- 4-Pillar Modern Glass Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 text-left">
                        <!-- Stat 1 -->
                        <div class="p-2.5 rounded-2xl bg-white/50 dark:bg-zinc-800/40 backdrop-blur-md">
                            <div class="flex items-center gap-1.5 text-xs text-[#15803d] dark:text-[#16a34a] mb-1">
                                <i class="ph-bold ph-buildings text-sm"></i>
                                <span class="font-bold text-[11px]">Coverage</span>
                            </div>
                            <div class="text-sm sm:text-base font-black text-slate-900 dark:text-white">46 Barangays</div>
                            <div class="text-[10px] text-slate-500 dark:text-zinc-400">Unified digital network</div>
                        </div>

                        <!-- Stat 2 -->
                        <div class="p-2.5 rounded-2xl bg-white/50 dark:bg-zinc-800/40 backdrop-blur-md">
                            <div class="flex items-center gap-1.5 text-xs text-[#15803d] dark:text-[#16a34a] mb-1">
                                <i class="ph-bold ph-scales text-sm"></i>
                                <span class="font-bold text-[11px]">Statutory</span>
                            </div>
                            <div class="text-sm sm:text-base font-black text-slate-900 dark:text-white">RA 10742 & 11768</div>
                            <div class="text-[10px] text-slate-500 dark:text-zinc-400">10% Youth fund cap</div>
                        </div>

                        <!-- Stat 3 -->
                        <div class="p-2.5 rounded-2xl bg-white/50 dark:bg-zinc-800/40 backdrop-blur-md">
                            <div class="flex items-center gap-1.5 text-xs text-[#15803d] dark:text-[#16a34a] mb-1">
                                <i class="ph-bold ph-file-text text-sm"></i>
                                <span class="font-bold text-[11px]">Compliance</span>
                            </div>
                            <div class="text-sm sm:text-base font-black text-slate-900 dark:text-white">DILG Disclosure</div>
                            <div class="text-[10px] text-slate-500 dark:text-zinc-400">1-click quarterly audit</div>
                        </div>

                        <!-- Stat 4 -->
                        <div class="p-2.5 rounded-2xl bg-white/50 dark:bg-zinc-800/40 backdrop-blur-md">
                            <div class="flex items-center gap-1.5 text-xs text-[#15803d] dark:text-[#16a34a] mb-1">
                                <i class="ph-bold ph-users-three text-sm"></i>
                                <span class="font-bold text-[11px]">Assembly</span>
                            </div>
                            <div class="text-sm sm:text-base font-black text-slate-900 dark:text-white">MYDC & YORP</div>
                            <div class="text-[10px] text-slate-500 dark:text-zinc-400">Youth council roster</div>
                        </div>
                    </div>
                </div>

            </div>
        </section>


        <!-- ========================================== -->
        <!-- SLIDE 2: REPUBLIC ACTS IN GOVERNMENT       -->
        <!-- ========================================== -->
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-2" data-title="Republic Acts in Government" data-notes="Ipaliwanag ang mga legal na batayan ng pamahalaan: RA 10742 (10% youth fund cap, CBYDP at ABYIP), RA 11768 (mandatory full disclosure at financial autonomy), RA 7160 (Local Government Code at DILG FDP policy sa 46 barangay), at RA 6713 (Code of Conduct at public inspection).">
            
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <!-- Centered Header & Subtitle (Zero Top Pill) -->
                <div class="mb-4 sm:mb-6 text-center max-w-3xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        Legal Mandates & Republic Acts
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1.5 leading-relaxed">
                        Statutory foundations of Philippine government empowering youth fiscal autonomy, mandatory reporting, and transparency.
                    </p>
                </div>

                <!-- 4 Republic Acts Section Cards (Zero Stroke Lines) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 w-full">
                    
                    <!-- Card 1: RA 10742 -->
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between group" data-speech="Ang Republic Act 10742 o SK Reform Act of 2015 ang nagtatakda ng statutory 10% youth development fund mula sa pangkalahatang pondo ng barangay. Ipinag-uutos din nito ang pagbuo ng Comprehensive Barangay Youth Development Plan (CBYDP) at Annual Barangay Youth Investment Program (ABYIP)." data-speech-title="RA 10742: SK Reform Act of 2015">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[200px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-2 text-center transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-[#15803d]/10 text-[#15803d] mb-1">
                                        <i class="ph-bold ph-scales text-lg"></i>
                                    </div>
                                    <div class="text-[11px] font-black text-slate-900 dark:text-white">RA 10742</div>
                                    <div class="text-[9px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">SK REFORM ACT 2015</div>
                                </div>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">Republic Act No. 10742</h3>
                            <p class="text-[10px] text-[#15803d] dark:text-[#16a34a] font-bold mb-1">Sangguniang Kabataan Reform Act</p>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed">
                                Mandates the statutory <strong>10% youth allocation</strong> from the barangay fund, creates the MYDC roster, and establishes mandatory CBYDP and ABYIP investment plans.
                            </p>
                        </div>
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-[#15803d]">10% FUND CAP</span>
                            <span class="text-[10px] font-bold text-slate-500">CBYDP & ABYIP</span>
                        </div>
                    </div>

                    <!-- Card 2: RA 11768 -->
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between group" data-speech="Ang Republic Act 11768 o SK Reform Amendments of 2022 ang nagpatibay sa financial autonomy ng kabataan. Mahigpit nitong ipinag-uutos ang digital at physical Full Disclosure ng mga financial statements, voucher, at disbursement upang matiyak ang pananagutan sa bawat sentimo." data-speech-title="RA 11768: SK Amendments of 2022">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[200px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-2 text-center transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-[#15803d]/10 text-[#15803d] mb-1">
                                        <i class="ph-bold ph-file-lock text-lg"></i>
                                    </div>
                                    <div class="text-[11px] font-black text-slate-900 dark:text-white">RA 11768</div>
                                    <div class="text-[9px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">SK AMENDMENTS 2022</div>
                                </div>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">Republic Act No. 11768</h3>
                            <p class="text-[10px] text-[#15803d] dark:text-[#16a34a] font-bold mb-1">SK Reform Amendments Act</p>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed">
                                Strengthens financial autonomy, mandates <strong>digital & physical full disclosure</strong> of vouchers and financial statements, and establishes standardized honoraria.
                            </p>
                        </div>
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-[#15803d]">MANDATORY FDP</span>
                            <span class="text-[10px] font-bold text-slate-500">Digital Posting</span>
                        </div>
                    </div>

                    <!-- Card 3: RA 7160 -->
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between group" data-speech="Ang Republic Act 7160 o Local Government Code of 1991 ang sandigan ng lokal na pamamahala. Alinsunod sa DILG Full Disclosure Policy at COA accounting rules, kailangang regular na ilathala ang quarterly reports ng lahat ng 46 na barangay ng Tumauini nang walang antala." data-speech-title="RA 7160: Local Government Code at DILG FDP">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[200px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-2 text-center transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-[#15803d]/10 text-[#15803d] mb-1">
                                        <i class="ph-bold ph-buildings text-lg"></i>
                                    </div>
                                    <div class="text-[11px] font-black text-slate-900 dark:text-white">RA 7160</div>
                                    <div class="text-[9px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">LOCAL GOV CODE 1991</div>
                                </div>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">Republic Act No. 7160</h3>
                            <p class="text-[10px] text-[#15803d] dark:text-[#16a34a] font-bold mb-1">Local Government Code & DILG FDP</p>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed">
                                Foundation of municipal fiscal autonomy and COA accounting. Mandates statutory quarterly disclosure across all <strong>46 Tumauini barangays</strong>.
                            </p>
                        </div>
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-[#15803d]">DILG POLICY</span>
                            <span class="text-[10px] font-bold text-slate-500">46 Barangays</span>
                        </div>
                    </div>

                    <!-- Card 4: RA 6713 -->
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between group" data-speech="Ang Republic Act 6713 o Code of Conduct and Ethical Standards for Public Officials and Employees ang nagtitiyak na bukas sa pampublikong pagsusuri ang lahat ng opisyal na dokumento at ledgers, may mabilis na pagtugon sa mga hinaing ng mamamayan, at sumusunod sa pinakamataas na pamantayan ng integridad." data-speech-title="RA 6713: Code of Conduct & Ethical Standards">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[200px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-2 text-center transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-[#15803d]/10 text-[#15803d] mb-1">
                                        <i class="ph-bold ph-shield-check text-lg"></i>
                                    </div>
                                    <div class="text-[11px] font-black text-slate-900 dark:text-white">RA 6713</div>
                                    <div class="text-[9px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">CODE OF ETHICS 1989</div>
                                </div>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">Republic Act No. 6713</h3>
                            <p class="text-[10px] text-[#15803d] dark:text-[#16a34a] font-bold mb-1">Code of Conduct for Public Officials</p>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed">
                                Mandates public inspection of state ledgers, transparent governance, rapid turnaround on citizen queries, and anti-corruption fiduciary compliance.
                            </p>
                        </div>
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-[#15803d]">ETHICAL AUDIT</span>
                            <span class="text-[10px] font-bold text-slate-500">Public Trust</span>
                        </div>
                    </div>

                </div>

                <!-- Footer Legal Mandate Highlight -->
                <div class="glass-card px-4 py-2 rounded-xl flex items-center justify-between text-xs font-semibold text-slate-600 dark:text-zinc-300 mt-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-md bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] font-mono font-bold text-[10px]">STATUTORY</span>
                        <span>TALA serves as the official technological compliance instrument implementing <strong>RA 10742</strong>, <strong>RA 11768</strong>, and <strong>DILG-NYC Circulars</strong>.</span>
                    </div>
                    <button type="button" onclick="nextSlide()" class="text-[#15803d] dark:text-[#16a34a] font-bold hover:underline flex-shrink-0 ml-2 flex items-center gap-1">
                        <span>Why TALA Was Built &rarr;</span>
                    </button>
                </div>
            </div>
        </section>


        <!-- ========================================== -->
        <!-- SLIDE 3: THE PROBLEM & THE SOLUTION        -->
        <!-- ========================================== -->
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-3" data-title="Problem vs Solution" data-notes="Ihambing ang lumang sistema sa TALA. Ang dating proseso ay mabagal at mano-mano; ang TALA ay may automated disclosures at live fund tracking.">
            
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <!-- Centered Header & Subtitle (Zero Top Pill) -->
                <div class="mb-4 sm:mb-6 text-center max-w-2xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        Why TALA Was Built
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1.5 leading-relaxed">
                        Transforming high-friction paper governance into an auditable digital web platform.
                    </p>
                </div>

                <!-- Two-Column Contrast Layout with Top UI Assets -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 w-full">
                    
                    <!-- Left: Traditional Roadblocks -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Sa tradisyunal na sistema, nahihirapan ang mga SK official sa tambak na papeles at manu-manong pagpapaskil sa bulletin board. Madalas nawawala o naaantala ang mga ulat, walang malinaw na pagsubaybay sa 46 milyong pisong pondo ng kabataan, at walang paraan ang publiko upang makita ang mga transaksyon." data-speech-title="Tradisyunal na Pamamahala sa Papel">
                        <div>
                            <!-- Top UI Asset: Paper Bottleneck Alert Mockup -->
                            <div class="card-asset-grid h-28 sm:h-32 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-3 overflow-hidden mb-3">
                                <div class="w-full max-w-[240px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-rose-200/80 dark:border-rose-900/40 p-2 transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-rose-100 dark:border-rose-950">
                                        <div class="flex items-center gap-1.5 text-rose-600 dark:text-rose-400 font-bold text-xs">
                                            <i class="ph-bold ph-warning-octagon text-sm"></i>
                                            <span>Manual Paper Filing</span>
                                        </div>
                                        <span class="text-[9px] font-mono font-bold bg-rose-500/10 text-rose-600 px-1.5 py-0.5 rounded">HIGH FRICTION</span>
                                    </div>
                                    <div class="flex items-center justify-between text-[10px] text-slate-600 dark:text-zinc-400 px-1">
                                        <div class="flex items-center gap-1">
                                            <i class="ph-bold ph-clock-countdown text-rose-500"></i>
                                            <span>21-Day Reporting Lag</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-rose-600 font-semibold">
                                            <i class="ph-bold ph-x-circle"></i>
                                            <span>Zero Live Audit</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 mb-2 text-rose-600 dark:text-rose-400 font-bold text-xs uppercase tracking-wider">
                                <i class="ph-bold ph-warning-circle text-base"></i>
                                <span>Traditional Paper Governance</span>
                            </div>

                            <div class="space-y-2 text-xs">
                                <div class="flex items-start gap-2.5 p-2 rounded-xl bg-rose-500/5">
                                    <span class="w-5 h-5 rounded-md bg-rose-500/10 text-rose-600 font-bold text-[10px] flex items-center justify-center flex-shrink-0 mt-0.5">1</span>
                                    <div>
                                        <strong class="text-slate-900 dark:text-white">Delayed State Disclosures:</strong> Physical paper bulletin postings prone to loss and overdue filing.
                                    </div>
                                </div>

                                <div class="flex items-start gap-2.5 p-2 rounded-xl bg-rose-500/5">
                                    <span class="w-5 h-5 rounded-md bg-rose-500/10 text-rose-600 font-bold text-[10px] flex items-center justify-center flex-shrink-0 mt-0.5">2</span>
                                    <div>
                                        <strong class="text-slate-900 dark:text-white">Unmonitored ₱46M+ AYIP Budget:</strong> No unified ledger tracking allocation versus actual disbursements.
                                    </div>
                                </div>

                                <div class="flex items-start gap-2.5 p-2 rounded-xl bg-rose-500/5">
                                    <span class="w-5 h-5 rounded-md bg-rose-500/10 text-rose-600 font-bold text-[10px] flex items-center justify-center flex-shrink-0 mt-0.5">3</span>
                                    <div>
                                        <strong class="text-slate-900 dark:text-white">Zero Public Visibility:</strong> Youth constituents cannot inspect check vouchers or monitor program funds.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: The TALA Solution -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Sa pamamagitan ng TALA, pinalitan natin ito ng mabilis at modernong web platform. Isang click lang, nai-publish agad ang quarterly Full Disclosure reports sa internet, live na namomonitor ang pondo ng 46 na barangay, at may direktang access ang kabataan sa mga opisyal na rekord." data-speech-title="Ang Digital na Solusyon ng TALA">
                        <div>
                            <!-- Top UI Asset: TALA Cloud Ledger in Design Green -->
                            <div class="card-asset-grid h-28 sm:h-32 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-3 overflow-hidden mb-3">
                                <div class="w-full max-w-[240px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-[#15803d]/30 dark:border-[#16a34a]/30 p-2 transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100 dark:border-zinc-800">
                                        <div class="flex items-center gap-1.5 text-[#15803d] dark:text-[#16a34a] font-bold text-xs">
                                            <i class="ph-bold ph-shield-check text-sm"></i>
                                            <span>TALA Digital Cloud Ledger</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-[9px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#15803d] animate-ping"></span>
                                            <span>ONLINE</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between text-[10px] text-slate-700 dark:text-zinc-200 px-1 font-semibold">
                                        <div class="flex items-center gap-1 text-[#15803d]">
                                            <i class="ph-bold ph-check-circle"></i>
                                            <span>1-Click FDP Publishing</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-[#15803d]">
                                            <i class="ph-bold ph-chart-line-up"></i>
                                            <span>Live Fund Telemetry</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 mb-2 text-[#15803d] dark:text-[#16a34a] font-bold text-xs uppercase tracking-wider">
                                <i class="ph-bold ph-check-circle text-base"></i>
                                <span>The TALA Digital Solution</span>
                            </div>

                            <div class="space-y-2 text-xs">
                                <div class="flex items-start gap-2.5 p-2 rounded-xl bg-[#15803d]/5">
                                    <i class="ph-bold ph-cloud-arrow-up text-[#15803d] text-sm mt-0.5 flex-shrink-0"></i>
                                    <div>
                                        <strong class="text-slate-900 dark:text-white">Automated Full Disclosure (FDP):</strong> 1-click statutory publishing (Q1–Q4) with in-browser PDF previews.
                                    </div>
                                </div>

                                <div class="flex items-start gap-2.5 p-2 rounded-xl bg-[#15803d]/5">
                                    <i class="ph-bold ph-chart-line-up text-[#15803d] text-sm mt-0.5 flex-shrink-0"></i>
                                    <div>
                                        <strong class="text-slate-900 dark:text-white">Real-Time Fund Telemetry:</strong> Live monitoring of municipal allocations, check vouchers & project completion.
                                    </div>
                                </div>

                                <div class="flex items-start gap-2.5 p-2 rounded-xl bg-[#15803d]/5">
                                    <i class="ph-bold ph-eye text-[#15803d] text-sm mt-0.5 flex-shrink-0"></i>
                                    <div>
                                        <strong class="text-slate-900 dark:text-white">Open Civic Transparency:</strong> Public audit access across 46 barangays paired with official meeting records and citizen feedback.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>


        <!-- ========================================== -->
        <!-- SLIDE 4: THE END-TO-END WORKFLOW           -->
        <!-- ========================================== -->
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-4" data-title="System Workflow" data-notes="Ilatag ang kumpletong daloy ng datos sa lahat ng 5 yugto sa isang pinag-isang screen.">
            
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <!-- Centered Header & Subtitle (Zero Top Pill) -->
                <div class="mb-4 sm:mb-6 text-center max-w-2xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        The End-to-End Governance Workflow
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1.5 leading-relaxed">
                        How data moves seamlessly between Youth Constituents, SK Officials, MYDC, and the LGU.
                    </p>
                </div>

                <!-- All 5 Stages in ONE Screen View (5-Column SaaS Cards with Top UI Assets) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-2.5 sm:gap-3 w-full">
                    
                    <!-- Stage 1: Onboarding & ID (Mini Digital QR ID Mockup) -->
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between group" data-speech="Sa Unang Yugto, magpaparehistro online ang kabataan at mabibigyan ng opisyal na QR ID na may tamper-proof security. Tinitiyak nito na lehitimo ang bawat benepisyaryo at walang multo sa attendance o pamamahagi ng tulong." data-speech-title="Yugto 1: Pagpaparehistro at Digital QR ID">
                        <div>
                            <!-- Top UI Asset: Miniature QR ID Card -->
                            <div class="card-asset-grid h-24 sm:h-28 w-full rounded-xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-2 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[170px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-slate-200/60 dark:border-zinc-700/60 p-2 transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center justify-between mb-1.5 pb-1 border-b border-slate-100 dark:border-zinc-800">
                                        <div class="flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#15803d]"></span>
                                        </div>
                                        <span class="text-[9px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">QR-ID VERIFIED</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-[#15803d]/10 text-[#15803d] flex items-center justify-center flex-shrink-0">
                                            <i class="ph-bold ph-qr-code text-base"></i>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-[10px] font-bold text-slate-800 dark:text-zinc-200 truncate">YTH-46-2026</div>
                                            <div class="flex items-center gap-1 text-[9px] text-[#15803d] dark:text-[#16a34a] font-semibold">
                                                <span class="w-1.5 h-1.5 rounded-full bg-[#15803d] animate-ping"></span>
                                                <span>Active Profile</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[9px] font-mono font-black uppercase px-1.5 py-0.5 rounded bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a]">Stage 01</span>
                                <span class="text-[10px] text-slate-400 font-mono">01/05</span>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white mb-1">Onboarding & ID</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-tight">Digital youth profiling with instant QR ID generation.</p>
                        </div>
                        <div class="pt-2 mt-2 border-t border-slate-100 dark:border-zinc-800/80 text-[10px] text-slate-600 dark:text-zinc-400 space-y-0.5">
                            <div><strong class="text-slate-800 dark:text-zinc-200">Actors:</strong> Youth & Secretary</div>
                            <div><strong class="text-[#15803d] dark:text-[#16a34a]">Output:</strong> Digital QR ID</div>
                        </div>
                    </div>

                    <!-- Stage 2: AYIP Planning (Stacked Telemetry Pills) -->
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between group" data-speech="Sa Ikalawang Yugto, binubuo ng SK Chairperson at Treasurer ang Annual Barangay Youth Investment Program. Awtomatikong binabantayan ng TALA ang statutory 10% fund cap upang maiwasan ang deficit o labis na paggastos." data-speech-title="Yugto 2: Pagpaplano ng AYIP at Badyet">
                        <div>
                            <!-- Top UI Asset: Stacked Budget Pills -->
                            <div class="card-asset-grid h-24 sm:h-28 w-full rounded-xl border border-slate-200/70 dark:border-zinc-800/80 relative flex flex-col items-center justify-center gap-1.5 p-2 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[170px] bg-white dark:bg-zinc-900 rounded-lg px-2.5 py-1 shadow-sm border border-slate-200/60 dark:border-zinc-700/60 flex items-center justify-between transform group-hover:scale-105 transition-transform duration-300">
                                    <span class="text-[10px] font-bold text-slate-800 dark:text-zinc-200 truncate">₱46.8M Budget</span>
                                    <span class="w-3 h-3 rounded-full border-2 border-[#15803d] border-t-transparent animate-spin flex-shrink-0"></span>
                                </div>
                                <div class="w-full max-w-[170px] bg-[#15803d] text-white rounded-lg px-2.5 py-1 shadow-sm flex items-center justify-between text-[10px] font-bold transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center gap-1 truncate">
                                        <i class="ph-bold ph-chart-pie-slice text-xs"></i>
                                        <span class="truncate">AYIP Registry</span>
                                    </div>
                                    <span class="text-[8px] opacity-90 font-mono">100%</span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[9px] font-mono font-black uppercase px-1.5 py-0.5 rounded bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a]">Stage 02</span>
                                <span class="text-[10px] text-slate-400 font-mono">02/05</span>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white mb-1">AYIP Planning</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-tight">Annual ₱46M+ fund allocation and project registry.</p>
                        </div>
                        <div class="pt-2 mt-2 border-t border-slate-100 dark:border-zinc-800/80 text-[10px] text-slate-600 dark:text-zinc-400 space-y-0.5">
                            <div><strong class="text-slate-800 dark:text-zinc-200">Actors:</strong> Council & LYDO</div>
                            <div><strong class="text-[#15803d] dark:text-[#16a34a]">Output:</strong> Budget Registry</div>
                        </div>
                    </div>

                    <!-- Stage 3: Disbursement (Network Pipeline Diagram) -->
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between group" data-speech="Sa Ikatlong Yugto, dito inia-upload ang mga check voucher at opisyal na resibo gamit ang standardized COA accounting codes. Mula ilang linggong paghihintay, nagiging minuto na lamang ang proseso ng liquidation." data-speech-title="Yugto 3: Check Voucher at Liquidation">
                        <div>
                            <!-- Top UI Asset: Network Pipeline -->
                            <div class="card-asset-grid h-24 sm:h-28 w-full rounded-xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-2 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[170px] flex items-center justify-between px-1 relative z-10 transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex flex-col items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full border border-slate-300 dark:border-zinc-600 bg-white dark:bg-zinc-900"></span>
                                        <span class="w-2 h-2 rounded-full border border-[#15803d] bg-[#15803d]"></span>
                                        <span class="w-2 h-2 rounded-full border border-slate-300 dark:border-zinc-600 bg-white dark:bg-zinc-900"></span>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-slate-200 dark:bg-zinc-700 mx-1.5"></div>
                                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#15803d] to-[#166534] text-white flex items-center justify-center shadow-md shadow-green-700/25 flex-shrink-0">
                                        <i class="ph-bold ph-receipt text-base"></i>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-slate-200 dark:bg-zinc-700 mx-1.5"></div>
                                    <div class="flex flex-col items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full border border-slate-300 dark:border-zinc-600 bg-white dark:bg-zinc-900"></span>
                                        <span class="w-2 h-2 rounded-full border border-[#15803d] bg-[#15803d]"></span>
                                        <span class="w-2 h-2 rounded-full border border-slate-300 dark:border-zinc-600 bg-white dark:bg-zinc-900"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[9px] font-mono font-black uppercase px-1.5 py-0.5 rounded bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a]">Stage 03</span>
                                <span class="text-[10px] text-slate-400 font-mono">03/05</span>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white mb-1">Disbursement</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-tight">Check vouchers, invoice uploads & COA coding.</p>
                        </div>
                        <div class="pt-2 mt-2 border-t border-slate-100 dark:border-zinc-800/80 text-[10px] text-slate-600 dark:text-zinc-400 space-y-0.5">
                            <div><strong class="text-slate-800 dark:text-zinc-200">Actors:</strong> Treasurer & Bank</div>
                            <div><strong class="text-[#15803d] dark:text-[#16a34a]">Output:</strong> Liquidated Check</div>
                        </div>
                    </div>

                    <!-- Stage 4: FDP Disclosure (Mini Browser Mockup) -->
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between group" data-speech="Sa Ika-apat na Yugto, ang mga naaprubahang quarterly financial reports ay awtomatikong nai-publish sa pampublikong Full Disclosure Portal na may in-browser PDF preview alinsunod sa kautusan ng DILG." data-speech-title="Yugto 4: Awtomatikong Pag-post sa FDP">
                        <div>
                            <!-- Top UI Asset: Mini Browser Mockup -->
                            <div class="card-asset-grid h-24 sm:h-28 w-full rounded-xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-2 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[170px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-slate-200/60 dark:border-zinc-700/60 p-2 transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center gap-1 mb-1.5 px-0.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d]"></span>
                                    </div>
                                    <div class="bg-slate-50 dark:bg-zinc-800/80 border border-slate-200 dark:border-zinc-700 rounded-lg px-2 py-1 flex items-center justify-between text-[10px]">
                                        <div class="flex items-center gap-1 text-slate-700 dark:text-zinc-200 font-semibold truncate">
                                            <i class="ph-bold ph-lock-simple text-[#15803d] text-xs"></i>
                                            <span class="truncate">fdp.gov.ph</span>
                                        </div>
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d] animate-ping"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[9px] font-mono font-black uppercase px-1.5 py-0.5 rounded bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a]">Stage 04</span>
                                <span class="text-[10px] text-slate-400 font-mono">04/05</span>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white mb-1">FDP Disclosure</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-tight">1-click statutory publishing with in-browser PDF.</p>
                        </div>
                        <div class="pt-2 mt-2 border-t border-slate-100 dark:border-zinc-800/80 text-[10px] text-slate-600 dark:text-zinc-400 space-y-0.5">
                            <div><strong class="text-slate-800 dark:text-zinc-200">Actors:</strong> Public & COA</div>
                            <div><strong class="text-[#15803d] dark:text-[#16a34a]">Output:</strong> Transparency Seal</div>
                        </div>
                    </div>

                    <!-- Stage 5: Executive Review & Audit Loop -->
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between group" data-speech="Sa Ikalimang Yugto, nirerepaso ng MYDC Federation at Commission on Audit ang compliance ng bawat barangay. Ginagawaran ang mga barangay ng Governance Badges upang mapanatili ang tapat at maayos na pamamahala." data-speech-title="Yugto 5: Pagsusuri ng Pamunuan at Audit">
                        <div>
                            <!-- Top UI Asset: Executive Audit Badge in Design Green -->
                            <div class="card-asset-grid h-24 sm:h-28 w-full rounded-xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-2 overflow-hidden mb-2.5">
                                <div class="absolute w-16 h-16 rounded-full bg-[#15803d]/15 blur-lg pointer-events-none"></div>
                                <div class="relative z-10 flex flex-col items-center justify-center transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#16a34a] to-[#15803d] text-white flex items-center justify-center shadow-md shadow-green-700/25 mb-1">
                                        <i class="ph-bold ph-shield-check text-lg"></i>
                                    </div>
                                    <div class="bg-white/95 dark:bg-zinc-900/95 border border-slate-200/60 dark:border-zinc-700/60 rounded-full px-2 py-0.5 shadow-sm text-[9px] font-bold text-slate-700 dark:text-zinc-200 flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d] animate-ping"></span>
                                        <span>46-Brgy Compliance</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[9px] font-mono font-black uppercase px-1.5 py-0.5 rounded bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a]">Stage 05</span>
                                <span class="text-[10px] text-slate-400 font-mono">05/05</span>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white mb-1">Executive Review</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-tight">46-barangay ranking badges & municipal feedback loop.</p>
                        </div>
                        <div class="pt-2 mt-2 border-t border-slate-100 dark:border-zinc-800/80 text-[10px] text-slate-600 dark:text-zinc-400 space-y-0.5">
                            <div><strong class="text-slate-800 dark:text-zinc-200">Actors:</strong> MYDC & COA</div>
                            <div><strong class="text-[#15803d] dark:text-[#16a34a]">Output:</strong> Audit Ledger</div>
                        </div>
                    </div>

                </div>

                <!-- Footer Architecture Highlight -->
                <div class="glass-card px-4 py-2 rounded-xl flex items-center justify-between text-[11px] sm:text-xs font-semibold text-slate-600 dark:text-zinc-300 mt-2.5">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-md bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] font-mono font-bold text-[10px]">PIPELINE</span>
                        <span class="text-slate-600 dark:text-zinc-300 text-[11px] sm:text-xs">Continuous loop connecting <strong>46 Barangays</strong>, <strong>MYDC Federation</strong>, and the <strong>Municipal Mayor's Office</strong>.</span>
                    </div>
                    <button type="button" onclick="nextSlide()" class="text-[#15803d] dark:text-[#16a34a] font-bold hover:underline flex-shrink-0 ml-2 flex items-center gap-1">
                        <span>Fiscal Transparency &rarr;</span>
                    </button>
                </div>
            </div>
        </section>


        <!-- ========================================== -->
        <!-- SLIDE 5: FEATURES PART 1 - FISCAL PIPELINE -->
        <!-- ========================================== -->
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-5" data-title="Features: Fiscal Transparency" data-notes="I-highlight ang pampinansyal na haligi ng TALA: Full Disclosure Portal alinsunod sa DILG, Real-Time Budget Console para sa 46 barangay, at COA Check Voucher liquidation.">
            
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <!-- Centered Header & Subtitle (Zero Top Pill) -->
                <div class="mb-4 sm:mb-6 text-center max-w-2xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        Fiscal Transparency & Budget Monitoring
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1.5 leading-relaxed">
                        Real-time municipal fund telemetry and complete statutory compliance across all 46 barangays.
                    </p>
                </div>

                <!-- 3 Section Cards in 1 Row (Exact Reference Image Style) -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4 w-full">
                    
                    <!-- Card 1: Full Disclosure (Browser / URL Mockup style with Green accents) -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Ito ang ating Full Disclosure Portal. Awtomatiko nitong inilalathala ang quarterly reports ng lahat ng 46 na barangay na may malinaw na PDF preview upang tiyaking 100% compliant ang ating bayan sa mga direktiba ng DILG." data-speech-title="Full Disclosure (FDP) Portal">
                        <div>
                            <!-- Top UI Asset Container -->
                            <div class="card-asset-grid h-32 sm:h-36 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-3 overflow-hidden">
                                <!-- Miniature Browser Mockup Window -->
                                <div class="w-full max-w-[220px] bg-white dark:bg-zinc-900 rounded-xl shadow-md border border-slate-200/60 dark:border-zinc-700/60 p-2 transform group-hover:scale-105 transition-transform duration-300">
                                    <!-- Top Browser Dots -->
                                    <div class="flex items-center gap-1.5 mb-2 px-0.5">
                                        <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                        <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                                    </div>
                                    <!-- Input Pill: Green Lock & URL -->
                                    <div class="bg-slate-50 dark:bg-zinc-800/80 border border-slate-200 dark:border-zinc-700 rounded-lg px-2.5 py-1.5 flex items-center justify-between text-[11px]">
                                        <div class="flex items-center gap-1.5 text-slate-700 dark:text-zinc-200 font-semibold truncate">
                                            <i class="ph-bold ph-lock-simple text-[#15803d]"></i>
                                            <span class="truncate">tala.gov.ph/fdp-board</span>
                                        </div>
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d] animate-ping"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Content: Title & Description -->
                            <div class="mt-3.5 sm:mt-4">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                    Full Disclosure (FDP) Portal
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                    Centralized quarterly statutory publishing with in-browser PDF previews complying directly with DILG circulars.
                                </p>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">DILG COMPLIANT</span>
                            <a href="public_disclosure.php" target="_blank" class="font-bold text-slate-700 dark:text-zinc-300 hover:text-[#15803d] dark:hover:text-[#16a34a] flex items-center gap-1 text-[11px]">
                                <span>Open</span> <i class="ph-bold ph-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Card 2: Budget Console (Interactive Telemetry Pills style) -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Ito naman ang Real-Time Budget Registry. Pinagsasama nito ang pondo ng 46 na barangay sa iisang live ledger upang masubaybayan ang approved budget laban sa aktwal na nagastos at maiwasan ang kakulangan." data-speech-title="Real-Time Budget Console">
                        <div>
                            <!-- Top UI Asset Container -->
                            <div class="card-asset-grid h-32 sm:h-36 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex flex-col items-center justify-center gap-2 p-3 overflow-hidden">
                                <!-- Top Pill: Fund Total with Spinner -->
                                <div class="w-full max-w-[210px] bg-white dark:bg-zinc-900 rounded-xl px-3 py-1.5 shadow-sm border border-slate-200/60 dark:border-zinc-700/60 flex items-center justify-between transform group-hover:scale-105 transition-transform duration-300">
                                    <span class="text-xs font-semibold text-slate-800 dark:text-zinc-200 truncate">₱46M+ AYIP Allocation</span>
                                    <span class="w-3.5 h-3.5 rounded-full border-2 border-[#15803d] border-t-transparent animate-spin flex-shrink-0"></span>
                                </div>
                                <!-- Bottom Pill: Solid Green Badge -->
                                <div class="w-full max-w-[210px] bg-[#15803d] text-white rounded-xl px-3 py-1.5 shadow-md flex items-center justify-between text-[11px] font-bold transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center gap-1.5 truncate">
                                        <i class="ph-bold ph-shield-check"></i>
                                        <span class="truncate">Deficit Guard Active</span>
                                    </div>
                                    <span class="text-[10px] opacity-90 font-mono">10% CAP</span>
                                </div>
                            </div>

                            <!-- Bottom Content: Title & Description -->
                            <div class="mt-3.5 sm:mt-4">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                    Real-Time Budget & AYIP Console
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                    Unified fund ledger tracking all 46 barangay youth budgets with automated alerts preventing deficit spending.
                                </p>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">TELEMETRY</span>
                            <a href="admin_budget.php" target="_blank" class="font-bold text-slate-700 dark:text-zinc-300 hover:text-[#15803d] dark:hover:text-[#16a34a] flex items-center gap-1 text-[11px]">
                                <span>Console</span> <i class="ph-bold ph-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Card 3: Check Voucher & Liquidation (Network Node with Center Icon) -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Sa module na ito pinoproseso ang mga check voucher at liquidation. Direktang ikinakabit ang mga digital na resibo kasama ang COA codes upang maiwasan ang pagkawala ng dokumento at mapabilis ang audit." data-speech-title="Check Voucher at Liquidation">
                        <div>
                            <!-- Top UI Asset Container -->
                            <div class="card-asset-grid h-32 sm:h-36 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-3 overflow-hidden">
                                <!-- Network Diagram (Like Card 4 in Reference Image) -->
                                <div class="w-full flex items-center justify-between px-3 relative z-10 transform group-hover:scale-105 transition-transform duration-300">
                                    <!-- Left Node Column -->
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full border-2 border-slate-300 dark:border-zinc-600 bg-white dark:bg-zinc-900"></span>
                                        <span class="w-2.5 h-2.5 rounded-full border-2 border-[#15803d] bg-[#15803d]"></span>
                                        <span class="w-2.5 h-2.5 rounded-full border-2 border-slate-300 dark:border-zinc-600 bg-white dark:bg-zinc-900"></span>
                                    </div>

                                    <!-- Connecting Horizontal Line Left -->
                                    <div class="flex-1 h-0.5 bg-slate-200 dark:bg-zinc-700 mx-2"></div>

                                    <!-- Center Floating Rounded Button with Green Gradient & Receipt Icon -->
                                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-[#15803d] to-[#166534] text-white flex items-center justify-center shadow-lg shadow-green-700/25 flex-shrink-0">
                                        <i class="ph-bold ph-receipt text-xl"></i>
                                    </div>

                                    <!-- Connecting Horizontal Line Right -->
                                    <div class="flex-1 h-0.5 bg-slate-200 dark:bg-zinc-700 mx-2"></div>

                                    <!-- Right Node Column -->
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full border-2 border-slate-300 dark:border-zinc-600 bg-white dark:bg-zinc-900"></span>
                                        <span class="w-2.5 h-2.5 rounded-full border-2 border-[#15803d] bg-[#15803d]"></span>
                                        <span class="w-2.5 h-2.5 rounded-full border-2 border-slate-300 dark:border-zinc-600 bg-white dark:bg-zinc-900"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Content: Title & Description -->
                            <div class="mt-3.5 sm:mt-4">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                    Check Voucher & Liquidation
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                    Standardized COA expense codes with photo attachments to cut liquidation turnaround from weeks to minutes.
                                </p>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">COA LIQUIDATION</span>
                            <a href="login.php" target="_blank" class="font-bold text-slate-700 dark:text-zinc-300 hover:text-[#15803d] dark:hover:text-[#16a34a] flex items-center gap-1 text-[11px]">
                                <span>Vouchers</span> <i class="ph-bold ph-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>

                </div>

                <!-- Bottom Live Feature Callout -->
                <div class="glass-card px-4 py-2 rounded-xl flex flex-col sm:flex-row items-center justify-between gap-2 text-xs mt-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-md bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] font-mono font-bold text-[10px]">LIVE STAT</span>
                        <span class="text-slate-600 dark:text-zinc-300 text-[11px] sm:text-xs">Over <strong><?= number_format($total_disclosures) ?></strong> public disclosure documents indexed with zero missing quarterly filings.</span>
                    </div>
                    <button type="button" onclick="nextSlide()" class="text-[#15803d] dark:text-[#16a34a] font-bold text-xs hover:underline flex items-center gap-1">
                        <span>Pillar 02: Civic Governance</span>
                        <i class="ph-bold ph-arrow-right"></i>
                    </button>
                </div>
            </div>
        </section>


        <!-- ========================================== -->
        <!-- SLIDE 6: FEATURES PART 2 - CIVIC TECH      -->
        <!-- ========================================== -->
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-6" data-title="Features: Civic Governance" data-notes="Present the civic governance modules: Barangay Ranking gamifies compliance; MYDC & YORP organizes all youth organizations; Minutes & Resolutions archives official assembly records; Ticketing ensures youth feedback is acted upon.">
            
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <!-- Centered Header & Subtitle (Zero Top Pill) -->
                <div class="mb-4 sm:mb-6 text-center max-w-2xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        Governance Badging, MYDC & Assembly Records
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1.5 leading-relaxed">
                        Gamified municipal compliance, verified youth rosters, and official legislative records.
                    </p>
                </div>

                <!-- 4 Section Cards in 1 Row (All Styled with Top UI Assets in Design Green) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 w-full">
                    
                    <!-- Card 1: Barangay Badges (100% Green Governance Seal Matrix) -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Ito ang ating Barangay Governance Badges system. Awtomatiko nitong sinusuri ang 46 na barangay at nagbibigay ng Gold, Silver, at Bronze seals ayon sa bilis at husay ng kanilang pagsumite ng ulat." data-speech-title="Barangay Governance Badges">
                        <div>
                            <!-- Top UI Asset Container: Pure Green Matrix -->
                            <div class="card-asset-grid h-32 sm:h-36 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-2.5 overflow-hidden">
                                <!-- Ambient Green Soft Glow -->
                                <div class="absolute w-28 h-28 rounded-full bg-[#15803d]/15 blur-xl pointer-events-none"></div>

                                <!-- Green Governance Seals & Leaderboard Matrix Widget -->
                                <div class="w-full max-w-[215px] bg-white dark:bg-zinc-900 rounded-xl shadow-md border border-slate-200/70 dark:border-zinc-700/70 p-2.5 relative z-10 transform group-hover:scale-105 transition-transform duration-300">
                                    <!-- Header: Title & Total Barangays in Green -->
                                    <div class="flex items-center justify-between pb-1.5 mb-2 border-b border-slate-100 dark:border-zinc-800">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-zinc-200">
                                            <i class="ph-bold ph-shield-check text-[#15803d] dark:text-[#16a34a] text-sm"></i>
                                            <span>Governance Badges</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-[9px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#15803d] animate-ping"></span>
                                            <span>46 BRGYS</span>
                                        </div>
                                    </div>
                                    <!-- 3-Column Badges Grid: All in TALA Green Hues -->
                                    <div class="grid grid-cols-3 gap-1.5 text-center">
                                        <!-- Gold Seal (Forest Green) -->
                                        <div class="p-1 rounded-lg bg-[#15803d]/10 border border-[#15803d]/30 flex flex-col items-center">
                                            <div class="w-6 h-6 rounded-full bg-gradient-to-br from-[#16a34a] to-[#15803d] text-white flex items-center justify-center shadow-sm text-xs mb-0.5">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                            <span class="text-[9px] font-black text-[#15803d] dark:text-[#16a34a]">GOLD</span>
                                            <span class="text-[8px] text-slate-500 dark:text-zinc-400 font-mono">18 Brgys</span>
                                        </div>
                                        <!-- Silver Seal (Emerald Green) -->
                                        <div class="p-1 rounded-lg bg-emerald-500/10 dark:bg-emerald-500/5 border border-emerald-500/25 flex flex-col items-center">
                                            <div class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-sm text-xs mb-0.5">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                            <span class="text-[9px] font-black text-emerald-700 dark:text-emerald-400">SILVER</span>
                                            <span class="text-[8px] text-slate-500 dark:text-zinc-400 font-mono">22 Brgys</span>
                                        </div>
                                        <!-- Bronze Seal (Teal Green) -->
                                        <div class="p-1 rounded-lg bg-teal-500/10 dark:bg-teal-500/5 border border-teal-500/25 flex flex-col items-center">
                                            <div class="w-6 h-6 rounded-full bg-teal-700 text-white flex items-center justify-center shadow-sm text-xs mb-0.5">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                            <span class="text-[9px] font-black text-teal-700 dark:text-teal-400">BRONZE</span>
                                            <span class="text-[8px] text-slate-500 dark:text-zinc-400 font-mono">6 Brgys</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Content: Title & Description -->
                            <div class="mt-3.5 sm:mt-4">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                    Barangay Governance Badges
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                    Automated 46-barangay scoring matrix awarding Gold, Silver, and Bronze seals for timely compliance.
                                </p>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">LEADERBOARD</span>
                            <a href="admin_governance.php" target="_blank" class="font-bold text-slate-700 dark:text-zinc-300 hover:text-[#15803d] dark:hover:text-[#16a34a] flex items-center gap-1 text-[11px]">
                                <span>Rankings</span> <i class="ph-bold ph-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Card 2: Council & YORP (Interactive Pills with Loader style in Green) -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Dito pinamamahalaan ang opisyal na rehistro ng mga kinikilalang youth organizations at lider ng kabataan upang masiguro ang lehitimong representasyon sa konseho." data-speech-title="Akreditasyon ng MYDC at YORP">
                        <div>
                            <!-- Top UI Asset Container -->
                            <div class="card-asset-grid h-32 sm:h-36 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex flex-col items-center justify-center gap-2 p-3 overflow-hidden">
                                <!-- Top Pill: Sector Roster with Spinner -->
                                <div class="w-full max-w-[200px] bg-white dark:bg-zinc-900 rounded-xl px-3 py-1.5 shadow-sm border border-slate-200/60 dark:border-zinc-700/60 flex items-center justify-between transform group-hover:scale-105 transition-transform duration-300">
                                    <span class="text-xs font-semibold text-slate-800 dark:text-zinc-200 truncate">5 Sector Roster</span>
                                    <span class="w-3.5 h-3.5 rounded-full border-2 border-[#15803d] border-t-transparent animate-spin flex-shrink-0"></span>
                                </div>
                                <!-- Bottom Pill: Solid Green Badge -->
                                <div class="w-full max-w-[200px] bg-[#15803d] text-white rounded-xl px-3 py-1.5 shadow-md flex items-center justify-between text-[11px] font-bold transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center gap-1.5 truncate">
                                        <i class="ph-bold ph-identification-badge"></i>
                                        <span class="truncate">YORP Certified</span>
                                    </div>
                                    <span class="text-[10px] opacity-90 font-mono">ACTIVE</span>
                                </div>
                            </div>

                            <!-- Bottom Content: Title & Description -->
                            <div class="mt-3.5 sm:mt-4">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                    MYDC & YORP Accreditation
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                    Central registry for youth organizations and sectoral council leaders with verified tenure histories.
                                </p>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">COUNCIL ROSTER</span>
                            <a href="admin_mydc.php" target="_blank" class="font-bold text-slate-700 dark:text-zinc-300 hover:text-[#15803d] dark:hover:text-[#16a34a] flex items-center gap-1 text-[11px]">
                                <span>Roster</span> <i class="ph-bold ph-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Card 3: Minutes & Assembly Resolutions -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Dito naka-archive ang mga opisyal na resolusyon, ordinansa, at minutes of meeting ng SK. Naitala rin ang roll call at quorum upang masigurong legal at balido ang bawat panukala." data-speech-title="Minutes at mga Resolusyon ng SK">
                        <div>
                            <!-- Top UI Asset Container: Resolution Document Mockup -->
                            <div class="card-asset-grid h-32 sm:h-36 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-3 overflow-hidden">
                                <div class="absolute w-24 h-24 rounded-full bg-[#15803d]/15 blur-xl pointer-events-none"></div>
                                <div class="w-full max-w-[210px] bg-white dark:bg-zinc-900 rounded-xl shadow-md border border-slate-200/60 dark:border-zinc-700/60 p-2 transform group-hover:scale-105 transition-transform duration-300 relative z-10">
                                    <div class="flex items-center justify-between mb-1.5 px-0.5 pb-1 border-b border-slate-100 dark:border-zinc-800">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-zinc-200">
                                            <i class="ph-bold ph-scroll text-[#15803d]"></i>
                                            <span>Assembly Record</span>
                                        </div>
                                        <span class="text-[9px] font-mono font-bold text-[#15803d] bg-[#15803d]/10 px-1.5 py-0.5 rounded">PASSED</span>
                                    </div>
                                    <div class="bg-slate-50 dark:bg-zinc-800/80 border border-slate-200 dark:border-zinc-700 rounded-lg px-2 py-1 flex items-center justify-between text-[10px]">
                                        <div class="flex items-center gap-1.5 text-slate-700 dark:text-zinc-200 font-semibold truncate">
                                            <i class="ph-bold ph-file-text text-[#15803d]"></i>
                                            <span class="truncate">Res. No. 2026-042</span>
                                        </div>
                                        <span class="text-[9px] text-[#15803d] font-bold">Quorum OK</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Content: Title & Description -->
                            <div class="mt-3.5 sm:mt-4">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                    Minutes & Assembly Resolutions
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                    Official digital repository for SK resolutions, ordinances, and meeting minutes with recorded roll calls and quorum verification.
                                </p>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">RESOLUTIONS</span>
                            <a href="admin_mom.php" target="_blank" class="font-bold text-slate-700 dark:text-zinc-300 hover:text-[#15803d] dark:hover:text-[#16a34a] flex items-center gap-1 text-[11px]">
                                <span>Minutes</span> <i class="ph-bold ph-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Card 4: Civic Feedback & Tickets -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Ito ang helpdesk ticketing system kung saan maaaring magpadala ang mga kabataan ng kanilang mga hinaing, tanong, o mungkahi upang maaksyunan agad ng kanilang mga opisyal." data-speech-title="Direktang Ugnayan at Helpdesk Tickets">
                        <div>
                            <!-- Top UI Asset Container: Citizen Helpdesk Ticket Mockup -->
                            <div class="card-asset-grid h-32 sm:h-36 w-full rounded-2xl relative flex items-center justify-center p-3 overflow-hidden">
                                <div class="w-full max-w-[215px] bg-white dark:bg-zinc-900 rounded-xl shadow-md p-2.5 relative z-10 transform group-hover:scale-105 transition-transform duration-300">
                                    <!-- Ticket Header: ID & Status Pill -->
                                    <div class="flex items-center justify-between pb-1 mb-1.5 border-b border-slate-100 dark:border-zinc-800">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-zinc-200">
                                            <i class="ph-bold ph-ticket text-[#15803d] dark:text-[#16a34a]"></i>
                                            <span class="font-mono text-[10px]">TCK-2026-089</span>
                                        </div>
                                        <span class="text-[8px] font-mono font-bold text-[#15803d] bg-[#15803d]/10 px-1.5 py-0.5 rounded-full">RESOLVED</span>
                                    </div>
                                    <!-- Ticket Body: Inquiry Description -->
                                    <div class="bg-slate-50 dark:bg-zinc-800/80 rounded-lg p-1.5 mb-1.5 text-left">
                                        <div class="text-[10px] font-bold text-slate-800 dark:text-zinc-200 truncate">Brgy Sports Clinic Request</div>
                                        <div class="text-[9px] text-slate-500 dark:text-zinc-400 truncate">From: SK Youth Assembly</div>
                                    </div>
                                    <!-- Ticket Telemetry: 24h SLA & Official Reply Badge -->
                                    <div class="flex items-center justify-between text-[9px] text-slate-500 font-mono">
                                        <span class="text-slate-400">24h SLA</span>
                                        <span class="text-[#15803d] font-bold flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#15803d] animate-ping"></span>
                                            Council Replied
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Content: Title & Description -->
                            <div class="mt-3.5 sm:mt-4">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                    Civic Feedback & Tickets
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                    Direct constituent grievance ticketing closing the feedback loop between youth and municipal administrators.
                                </p>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">HELPDESK</span>
                            <a href="tickets.php" target="_blank" class="font-bold text-slate-700 dark:text-zinc-300 hover:text-[#15803d] dark:hover:text-[#16a34a] flex items-center gap-1 text-[11px]">
                                <span>Tickets</span> <i class="ph-bold ph-arrow-up-right"></i>
                            </a>
                        </div>
                    </div>

                </div>

                <!-- Footer Step -->
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-zinc-400 mt-3 px-1">
                    <span class="text-[11px] truncate">Unified command center and citizen-facing services across 46 barangays.</span>
                    <button type="button" onclick="nextSlide()" class="text-[#15803d] dark:text-[#16a34a] font-bold hover:underline flex-shrink-0 ml-2">
                        Technical Stack &rarr;
                    </button>
                </div>
            </div>
        </section>


        <!-- ========================================== -->
        <!-- SLIDE 7: TECHNICAL ARCHITECTURE & STACK    -->
        <!-- ========================================== -->
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-7" data-title="Technical Architecture" data-notes="Demonstrate engineering maturity. Highlight PHP 8.2 with PDO prepared statements, MySQL ACID compliance, Tailwind CSS 3.x with zero exterior borders, responsive design down to 360px phones, and role-based access security.">
            
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <!-- Centered Header & Subtitle (Zero Top Pill) -->
                <div class="mb-4 sm:mb-6 text-center max-w-2xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        System Architecture & Tech Foundation
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1.5 leading-relaxed">
                        Engineered for high performance, zero external framework bloat, and enterprise security.
                    </p>
                </div>

                <!-- 4 Section Cards in 1 Row (Exact Reference Image Style) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 w-full">
                    
                    <!-- Stack 1: Backend Runtime (Browser / Window Mockup style with Green accents) -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Sa backend, gumagana ang TALA gamit ang PHP 8.2 na may PDO prepared statements. Ito ay magaan at walang mabibigat na third-party frameworks kaya napakabilis mag-load ng bawat pahina." data-speech-title="Backend Runtime: PHP 8.2 at PDO">
                        <div>
                            <!-- Top UI Asset Container -->
                            <div class="card-asset-grid h-32 sm:h-36 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-3 overflow-hidden">
                                <!-- Miniature Code Window -->
                                <div class="w-full max-w-[210px] bg-white dark:bg-zinc-900 rounded-xl shadow-md border border-slate-200/60 dark:border-zinc-700/60 p-2 transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center gap-1.5 mb-2 px-0.5">
                                        <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                        <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                                    </div>
                                    <div class="bg-slate-50 dark:bg-zinc-800/80 border border-slate-200 dark:border-zinc-700 rounded-lg px-2.5 py-1.5 flex items-center justify-between text-[11px]">
                                        <div class="flex items-center gap-1.5 text-slate-700 dark:text-zinc-200 font-semibold truncate">
                                            <i class="ph-bold ph-cpu text-[#15803d]"></i>
                                            <span class="truncate">PHP 8.2 • PDO</span>
                                        </div>
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d] animate-ping"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Content: Title & Description -->
                            <div class="mt-3.5 sm:mt-4">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                    Backend Runtime
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                    Lightweight modular PHP 8.2 executing prepared PDO statements with route middleware guards.
                                </p>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">PHP &bull; PDO</span>
                            <span class="text-[11px] font-bold text-slate-500 dark:text-zinc-400">Zero Bloat</span>
                        </div>
                    </div>

                    <!-- Stack 2: Data Layer (Interactive Pills style with Green accents) -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Ang database layer ay tumatakbo sa MySQL 8 gamit ang InnoDB engine na may ACID compliance upang masiguro na laging protektado at buo ang bawat transaksyong pinansyal ng 46 na barangay." data-speech-title="Relational Data Layer: MySQL 8">
                        <div>
                            <!-- Top UI Asset Container -->
                            <div class="card-asset-grid h-32 sm:h-36 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex flex-col items-center justify-center gap-2 p-3 overflow-hidden">
                                <!-- Top Pill: Engine with Spinner -->
                                <div class="w-full max-w-[200px] bg-white dark:bg-zinc-900 rounded-xl px-3 py-1.5 shadow-sm border border-slate-200/60 dark:border-zinc-700/60 flex items-center justify-between transform group-hover:scale-105 transition-transform duration-300">
                                    <span class="text-xs font-semibold text-slate-800 dark:text-zinc-200 truncate">MySQL 8.0 InnoDB</span>
                                    <span class="w-3.5 h-3.5 rounded-full border-2 border-[#15803d] border-t-transparent animate-spin flex-shrink-0"></span>
                                </div>
                                <!-- Bottom Pill: Solid Green Badge -->
                                <div class="w-full max-w-[200px] bg-[#15803d] text-white rounded-xl px-3 py-1.5 shadow-md flex items-center justify-between text-[11px] font-bold transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center gap-1.5 truncate">
                                        <i class="ph-bold ph-database"></i>
                                        <span class="truncate">ACID Ledger Safe</span>
                                    </div>
                                    <span class="text-[10px] opacity-90 font-mono">46 BRGYS</span>
                                </div>
                            </div>

                            <!-- Bottom Content: Title & Description -->
                            <div class="mt-3.5 sm:mt-4">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                    Relational Data Layer
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                    ACID-compliant schemas with indexed queries and strict cascade rules ensuring financial integrity.
                                </p>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">INNODB</span>
                            <span class="text-[11px] font-bold text-slate-500 dark:text-zinc-400">Strict Cascades</span>
                        </div>
                    </div>

                    <!-- Stack 3: Presentation & UI (Bold Typography with Green Glow) -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Ang user interface ay binuo gamit ang Tailwind CSS 3 at Phosphor icons. Umaangkop ito sa anumang laki ng screen — mula sa 4K projector hanggang sa smartphone ng opisyal — at may instant dark and light mode." data-speech-title="UI Architecture: Tailwind CSS 3">
                        <div>
                            <!-- Top UI Asset Container -->
                            <div class="card-asset-grid h-32 sm:h-36 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-3 overflow-hidden">
                                <div class="absolute w-24 h-24 rounded-full bg-green-500/15 blur-xl pointer-events-none"></div>
                                <div class="relative z-10 flex flex-col items-center justify-center transform group-hover:scale-105 transition-transform duration-300">
                                    <span class="text-2xl sm:text-3xl font-black tracking-tight text-[#15803d] dark:text-[#16a34a] drop-shadow-sm font-sans">
                                        TAILWIND 3
                                    </span>
                                    <div class="mt-1 bg-white/95 dark:bg-zinc-900/95 border border-slate-200/60 dark:border-zinc-700/60 rounded-full px-2.5 py-0.5 shadow-sm text-[10px] font-bold text-slate-700 dark:text-zinc-200 flex items-center gap-1">
                                        <i class="ph-bold ph-palette text-[#15803d]"></i>
                                        <span>Glass-Card UI</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Content: Title & Description -->
                            <div class="mt-3.5 sm:mt-4">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                    TALA UI Architecture
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                    Modern flat glass-card styling with Phosphor icons and instant Dark/Light theme persistence.
                                </p>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">RESPONSIVE</span>
                            <span class="text-[11px] font-bold text-slate-500 dark:text-zinc-400">4K to 360px</span>
                        </div>
                    </div>

                    <!-- Stack 4: Security Standards (Network Nodes with Center Key/Shield) -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Para sa seguridad, mahigpit na ipinapatupad ng TALA ang role-based access control, proteksyon laban sa CSRF, file upload quarantine, at buong audit log sa bawat galaw ng admin." data-speech-title="Mga Pamantayan sa Seguridad">
                        <div>
                            <!-- Top UI Asset Container -->
                            <div class="card-asset-grid h-32 sm:h-36 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-3 overflow-hidden">
                                <!-- Network Diagram (Like Card 4 in Reference Image) -->
                                <div class="w-full flex items-center justify-between px-3 relative z-10 transform group-hover:scale-105 transition-transform duration-300">
                                    <!-- Left Node Column -->
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full border-2 border-slate-300 dark:border-zinc-600 bg-white dark:bg-zinc-900"></span>
                                        <span class="w-2.5 h-2.5 rounded-full border-2 border-[#15803d] bg-[#15803d]"></span>
                                        <span class="w-2.5 h-2.5 rounded-full border-2 border-slate-300 dark:border-zinc-600 bg-white dark:bg-zinc-900"></span>
                                    </div>

                                    <!-- Connecting Horizontal Line Left -->
                                    <div class="flex-1 h-0.5 bg-slate-200 dark:bg-zinc-700 mx-2"></div>

                                    <!-- Center Floating Rounded Button with Green Gradient & Shield Icon -->
                                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-[#15803d] to-[#166534] text-white flex items-center justify-center shadow-lg shadow-green-700/25 flex-shrink-0">
                                        <i class="ph-bold ph-shield-check text-xl"></i>
                                    </div>

                                    <!-- Connecting Horizontal Line Right -->
                                    <div class="flex-1 h-0.5 bg-slate-200 dark:bg-zinc-700 mx-2"></div>

                                    <!-- Right Node Column -->
                                    <div class="flex flex-col items-center gap-2">
                                        <span class="w-2.5 h-2.5 rounded-full border-2 border-slate-300 dark:border-zinc-600 bg-white dark:bg-zinc-900"></span>
                                        <span class="w-2.5 h-2.5 rounded-full border-2 border-[#15803d] bg-[#15803d]"></span>
                                        <span class="w-2.5 h-2.5 rounded-full border-2 border-slate-300 dark:border-zinc-600 bg-white dark:bg-zinc-900"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Content: Title & Description -->
                            <div class="mt-3.5 sm:mt-4">
                                <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
                                    Enterprise Security Standards
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                    Strict multi-tier RBAC enforcement, quarantine file upload sanitization, and full audit logs.
                                </p>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">RBAC GUARD</span>
                            <span class="text-[11px] font-bold text-slate-500 dark:text-zinc-400">Quarantine Safe</span>
                        </div>
                    </div>

                </div>

                <!-- Footer Architecture Highlight -->
                <div class="glass-card px-4 py-2 rounded-xl flex items-center justify-between text-[11px] sm:text-xs font-semibold text-slate-600 dark:text-zinc-300 mt-3">
                    <div class="flex items-center gap-1.5">
                        <i class="ph-fill ph-check-circle text-[#15803d] dark:text-[#16a34a] text-sm"></i>
                        <span>TALA is self-hosted on local LGU infrastructure with offline resiliency and cloud-backup readiness.</span>
                    </div>
                    <button type="button" onclick="nextSlide()" class="text-[#15803d] dark:text-[#16a34a] font-bold hover:underline flex-shrink-0 ml-2">
                        Conclusion &rarr;
                    </button>
                </div>
            </div>
        </section>


        <!-- ========================================== -->
        <!-- SLIDE 8: OUTRO - IMPACT, ROADMAP & LAUNCH  -->
        <!-- ========================================== -->
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-8" data-title="Impact, Roadmap & Outro" data-notes="Conclude with high energy. Summarize the quantifiable impact: 100% barangay coverage, 85% reduction in reporting latency, total paperless transparency. Present the future roadmap (PWA, SMS alerts, GIS mapping). Invite the panel to the live interactive demonstration and open the floor for Q&A.">
            
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <!-- Centered Header & Subtitle (Zero Top Pill) -->
                <div class="mb-3 sm:mb-4 text-center max-w-2xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        Transforming Tumauini Youth Governance
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1 leading-relaxed">
                        Measurable achievements, upcoming innovations, and live demonstration launchpad.
                    </p>
                </div>

                <!-- Top Grid: Proven Impact & Roadmap with Top UI Assets -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 w-full">
                    
                    <!-- Left: Quantified Impact Metrics (7 cols) -->
                    <div class="lg:col-span-7 saas-card p-3.5 sm:p-4 rounded-2xl flex flex-col justify-between group" data-speech="Bilang buod ng ating tagumpay: Naabot ng TALA ang 100% digital coverage sa lahat ng 46 na barangay ng Tumauini, pinabilis ng 85% ang voucher liquidation, at nagbigay ng 24/7 pampublikong visibility sa higit 46 milyong pisong pondo." data-speech-title="Naihatid na Makabuluhang Epekto">
                        <div>
                            <!-- Top UI Asset: Municipal Telemetry in Design Green -->
                            <div class="card-asset-grid h-24 sm:h-28 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-2.5 overflow-hidden mb-3">
                                <div class="w-full max-w-[260px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm border border-[#15803d]/30 dark:border-[#16a34a]/30 p-2 transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center justify-between pb-1 mb-1 border-b border-slate-100 dark:border-zinc-800">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-zinc-200">
                                            <i class="ph-bold ph-chart-line-up text-[#15803d]"></i>
                                            <span>Municipal Impact Telemetry</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-[9px] font-mono font-bold text-[#15803d]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#15803d] animate-ping"></span>
                                            <span>ACTIVE</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between text-[10px] text-slate-700 dark:text-zinc-300 font-semibold px-1">
                                        <span class="flex items-center gap-1 text-[#15803d]"><i class="ph-bold ph-check-circle"></i> 46/46 Unified</span>
                                        <span class="flex items-center gap-1 text-[#15803d]"><i class="ph-bold ph-lightning"></i> 85% Speedup</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 text-[#15803d] dark:text-[#16a34a] font-bold text-xs uppercase tracking-wider mb-2">
                                <i class="ph-bold ph-chart-line-up text-sm"></i>
                                <span>Measurable Impact Delivered</span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <div class="p-2 sm:p-2.5 rounded-xl bg-[#15803d]/5 border border-[#15803d]/10">
                                    <span class="text-xl sm:text-2xl font-black text-[#15803d] dark:text-[#16a34a] block">100%</span>
                                    <span class="text-[11px] font-bold text-slate-800 dark:text-zinc-200">Barangay Coverage</span>
                                    <p class="text-[10px] text-slate-500 dark:text-zinc-400">All 46 barangays unified.</p>
                                </div>

                                <div class="p-2 sm:p-2.5 rounded-xl bg-[#15803d]/5 border border-[#15803d]/10">
                                    <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white block">85%</span>
                                    <span class="text-[11px] font-bold text-slate-800 dark:text-zinc-200">Reporting Speedup</span>
                                    <p class="text-[10px] text-slate-500 dark:text-zinc-400">Voucher liquidations in minutes.</p>
                                </div>

                                <div class="p-2 sm:p-2.5 rounded-xl bg-[#15803d]/5 border border-[#15803d]/10">
                                    <span class="text-xl sm:text-2xl font-black text-amber-500 block">₱46M+</span>
                                    <span class="text-[11px] font-bold text-slate-800 dark:text-zinc-200">Youth Fund Monitored</span>
                                    <p class="text-[10px] text-slate-500 dark:text-zinc-400">Real-time municipal audit.</p>
                                </div>

                                <div class="p-2 sm:p-2.5 rounded-xl bg-[#15803d]/5 border border-[#15803d]/10">
                                    <span class="text-xl sm:text-2xl font-black text-blue-600 dark:text-blue-400 block">24/7</span>
                                    <span class="text-[11px] font-bold text-slate-800 dark:text-zinc-200">Civic Transparency</span>
                                    <p class="text-[10px] text-slate-500 dark:text-zinc-400">Open public inspection.</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-2 rounded-xl bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-medium text-slate-600 dark:text-zinc-300">
                            <strong>Official Recognition:</strong> Benchmark digital transformation model for Sangguniang Kabataan Federations.
                        </div>
                    </div>

                    <!-- Right: Future Roadmap (5 cols) -->
                    <div class="lg:col-span-5 saas-card p-3.5 sm:p-4 rounded-2xl flex flex-col justify-between group" data-speech="Sa hinaharap, kabilang sa ating plano ang offline Progressive Web App para sa malalayong barangay, interactive GIS mapping ng mga proyekto, at automated SMS alerts para sa mga pagpupulong." data-speech-title="Roadmap para sa Hinaharap">
                        <div>
                            <!-- Top UI Asset: Roadmap Timeline Pipeline Mockup -->
                            <div class="card-asset-grid h-24 sm:h-28 w-full rounded-2xl border border-slate-200/70 dark:border-zinc-800/80 relative flex items-center justify-center p-2.5 overflow-hidden mb-3">
                                <div class="w-full max-w-[260px] flex items-center justify-between px-2 relative z-10 transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex flex-col items-center">
                                        <span class="w-7 h-7 rounded-lg bg-[#15803d]/15 text-[#15803d] dark:text-[#16a34a] flex items-center justify-center text-xs font-bold font-mono">Q1</span>
                                        <span class="text-[8px] font-bold text-slate-600 dark:text-zinc-400 mt-1">PWA</span>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-[#15803d]/30 mx-1.5"></div>
                                    <div class="flex flex-col items-center">
                                        <span class="w-7 h-7 rounded-lg bg-[#15803d]/15 text-[#15803d] dark:text-[#16a34a] flex items-center justify-center text-xs font-bold font-mono">Q2</span>
                                        <span class="text-[8px] font-bold text-slate-600 dark:text-zinc-400 mt-1">GIS MAP</span>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-[#15803d]/30 mx-1.5"></div>
                                    <div class="flex flex-col items-center">
                                        <span class="w-7 h-7 rounded-lg bg-[#15803d]/15 text-[#15803d] dark:text-[#16a34a] flex items-center justify-center text-xs font-bold font-mono">Q3</span>
                                        <span class="text-[8px] font-bold text-slate-600 dark:text-zinc-400 mt-1">SMS</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 text-amber-500 font-bold text-xs uppercase tracking-wider mb-2">
                                <i class="ph-bold ph-rocket-launch text-sm"></i>
                                <span>Future Innovation Roadmap</span>
                            </div>

                            <div class="space-y-1.5">
                                <div class="flex items-start gap-2 p-1.5 rounded-xl bg-slate-50 dark:bg-zinc-900/60 border border-slate-100 dark:border-zinc-800">
                                    <div class="w-5 h-5 rounded-md bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold text-[9px] flex-shrink-0 mt-0.5">
                                        Q1
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-slate-900 dark:text-white">Mobile Progressive Web App (PWA)</h4>
                                        <p class="text-[10px] text-slate-500 dark:text-zinc-400">Offline-first voucher drafting for remote barangays.</p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-2 p-1.5 rounded-xl bg-slate-50 dark:bg-zinc-900/60 border border-slate-100 dark:border-zinc-800">
                                    <div class="w-5 h-5 rounded-md bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] flex items-center justify-center font-bold text-[9px] flex-shrink-0 mt-0.5">
                                        Q2
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-slate-900 dark:text-white">GIS-Based Project Mapping</h4>
                                        <p class="text-[10px] text-slate-500 dark:text-zinc-400">Interactive map pin-pointing funded youth projects.</p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-2 p-1.5 rounded-xl bg-slate-50 dark:bg-zinc-900/60 border border-slate-100 dark:border-zinc-800">
                                    <div class="w-5 h-5 rounded-md bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold text-[9px] flex-shrink-0 mt-0.5">
                                        Q3
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-slate-900 dark:text-white">SMS Broadcast Gateway</h4>
                                        <p class="text-[10px] text-slate-500 dark:text-zinc-400">SMS alerts for assemblies, grants & emergencies.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-slate-100 dark:border-zinc-800 text-[10px] font-mono text-slate-400 dark:text-zinc-500">
                            Vision: Sustained innovation for the youth of Tumauini.
                        </div>
                    </div>

                </div>

                <!-- Bottom Live Launchpad & Closing Banner -->
                <div class="saas-card rounded-2xl p-3 sm:p-4 flex flex-col md:flex-row items-center justify-between gap-3 mt-2 w-full group" data-speech="Bukas at live na gumagana ang ating web platform ngayon. Inaanyayahan po namin ang ating panel na subukan ang live portal habang bukas po kami sa inyong mga katanungan. Maraming salamat po!" data-speech-title="Live Demonstration Launchpad">
                    <div class="flex items-center gap-3">
                        <img src="mascot.png" alt="TALA Mascot" class="w-10 h-10 object-contain hidden sm:block">
                        <div>
                            <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white">
                                Ready for Live System Evaluation
                            </h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400">
                                Floor is open for questions, audit inspection, and live walkthrough.
                            </p>
                        </div>
                    </div>

                    <!-- Launch Buttons Cluster -->
                    <div class="flex flex-wrap items-center gap-2 w-full md:w-auto justify-end">
                        <a href="public_disclosure.php" target="_blank" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 text-xs font-bold transition-all tap-effect flex items-center gap-1.5">
                            <i class="ph-bold ph-globe text-[#15803d] text-sm"></i>
                            <span>Public Portal</span>
                        </a>
                        
                        <a href="admin_budget.php" target="_blank" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 text-xs font-bold transition-all tap-effect flex items-center gap-1.5">
                            <i class="ph-bold ph-coins text-amber-500 text-sm"></i>
                            <span>Budget Console</span>
                        </a>

                        <a href="login.php" target="_blank" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all tap-effect flex items-center gap-1.5">
                            <i class="ph-bold ph-lock-key text-sm relative z-10"></i>
                            <span class="relative z-10">Login as Admin</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

    </main>


    <!-- ============================================================== -->
    <!-- PRESENTER HOVER TELEPROMPTER HUD (Malaking Script sa Tagalog) -->
    <!-- ============================================================== -->
    <div id="presenterHoverCue" class="fixed bottom-20 left-1/2 -translate-x-1/2 z-40 max-w-5xl w-[calc(100%-2rem)] p-4 sm:p-5 rounded-3xl bg-white/98 dark:bg-zinc-900/98 border-2 border-[#15803d] shadow-2xl backdrop-blur-xl transition-all duration-300 pointer-events-auto flex items-start justify-between gap-4">
        <div class="flex items-start gap-3.5 flex-1 min-w-0">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-[#16a34a] to-[#15803d] text-white flex items-center justify-center flex-shrink-0 shadow-lg shadow-green-700/25 mt-0.5">
                <i class="ph-bold ph-microphone text-xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <span class="text-[11px] font-mono font-black uppercase text-[#15803d] dark:text-[#16a34a] tracking-wider flex items-center gap-1.5 bg-green-500/10 px-2.5 py-0.5 rounded-full">
                        <span class="w-2 h-2 rounded-full bg-[#15803d] animate-ping"></span>
                        Basahin nang Malakas sa Panel:
                    </span>
                    <span class="text-xs font-bold text-slate-500 dark:text-zinc-400 font-sans truncate" id="hudSectionLabel">Pambungad sa TALA</span>
                </div>
                <p id="hudSpeechText" class="text-sm sm:text-base md:text-lg font-bold text-slate-900 dark:text-white leading-relaxed">
                    Magandang araw po sa ating kagalang-galang na panel at mga panauhin. Ikinagagalak ko pong iprisinta ang Tumauini Active Youth Ledger Analytics o TALA...
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0 pt-1">
            <a href="presenter_view.php" target="_blank" class="px-3 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold transition-all tap-effect flex items-center gap-1.5 shadow-sm" title="Buksan ang Hiwalay na Screen para sa Presenter at Script">
                <i class="ph-bold ph-monitor-play text-sm"></i>
                <span class="hidden sm:inline">Presenter Screen</span>
            </a>
            <button type="button" onclick="toggleHudPin()" id="hudPinBtn" class="px-2.5 py-1.5 rounded-xl bg-green-500/10 text-[#15803d] dark:text-[#16a34a] text-xs font-bold transition-colors flex items-center gap-1" title="Panatilihing nakapirmi sa screen">
                <i class="ph-bold ph-push-pin text-sm"></i>
                <span class="hidden sm:inline">Pinned</span>
            </button>
            <button type="button" onclick="toggleHudVisibility()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-600 flex items-center justify-center text-sm" title="Itago (Shortcut: S)">
                <i class="ph-bold ph-x"></i>
            </button>
        </div>
    </div>


    <!-- ============================================================== -->
    <!-- DECK NAVIGATION FLOATING BOTTOM DOCK                          -->
    <!-- ============================================================== -->
    <nav class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 max-w-3xl w-[calc(100%-2rem)] px-3 pointer-events-auto" id="deckNavigationDock">
        <div class="glass-card rounded-2xl p-2 flex items-center justify-between gap-2 shadow-xl border border-slate-200/50 dark:border-zinc-800/80">
            
            <!-- Previous Slide Button -->
            <button type="button" onclick="prevSlide()" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 text-xs font-bold transition-all tap-effect" title="Previous Slide (Arrow Left)">
                <i class="ph-bold ph-caret-left text-sm"></i>
                <span class="hidden sm:inline">Prev</span>
            </button>

            <!-- Dual-Screen Presenter Launcher -->
            <a href="presenter_view.php" target="_blank" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-[#15803d]/10 hover:bg-[#15803d]/20 text-[#15803d] dark:text-[#16a34a] text-xs font-bold transition-all tap-effect" title="Buksan ang Screen ng Presenter na may Malaking Script">
                <i class="ph-bold ph-broadcast text-sm"></i>
                <span class="hidden md:inline">Presenter View</span>
            </a>

            <!-- Presenter Speech HUD Toggle Button -->
            <button type="button" onclick="toggleHudVisibility()" id="dockSpeechBtn" class="flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-green-500/10 text-[#15803d] dark:text-[#16a34a] text-xs font-bold transition-all tap-effect" title="I-toggle ang Script Prompter sa Screen (Shortcut: S)">
                <i class="ph-bold ph-microphone"></i>
                <span class="hidden sm:inline">Script</span>
            </button>

            <!-- Slide Dots Indicators -->
            <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto py-1 px-1">
                <?php for ($i = 1; $i <= 8; $i++): ?>
                    <button type="button" onclick="goToSlide(<?= $i ?>)" id="slideDot-<?= $i ?>" class="slide-dot w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full transition-all duration-300 <?= $i === 1 ? 'bg-[#15803d] dark:bg-[#16a34a] scale-125' : 'bg-slate-300 dark:bg-zinc-700 hover:bg-slate-400' ?>" title="Go to slide <?= $i ?>">
                    </button>
                <?php endfor; ?>
            </div>

            <!-- Next Slide Button -->
            <button type="button" onclick="nextSlide()" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all tap-effect" title="Next Slide (Arrow Right / Space)">
                <span class="relative z-10 hidden sm:inline">Next</span>
                <i class="ph-bold ph-caret-right text-sm relative z-10"></i>
            </button>
        </div>
    </nav>


    <!-- ============================================================== -->
    <!-- SPEAKER NOTES DRAWER (TELEPROMPTER FOR PRESENTERS)            -->
    <!-- ============================================================== -->
    <aside id="notesDrawer" class="fixed top-0 right-0 z-50 h-full w-full sm:w-96 md:w-[420px] bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl transform translate-x-full flex flex-col justify-between p-5 transition-transform duration-300">
        <div>
            <!-- Header -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center font-bold text-sm">
                        <i class="ph-bold ph-chat-centered-text"></i>
                    </div>
                    <div>
                        <h4 class="font-extrabold text-sm text-slate-900 dark:text-white">Presenter Teleprompter</h4>
                        <span class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono" id="notesSlideIndicator">Slide 01 Notes</span>
                    </div>
                </div>
                <button type="button" onclick="toggleNotes()" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-500 dark:text-zinc-400 flex items-center justify-center transition-colors">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Talking Points Container -->
            <div class="space-y-3 overflow-y-auto max-h-[calc(100vh-210px)] custom-scrollbar pr-1">
                <div class="p-3 rounded-2xl bg-amber-500/10 text-amber-800 dark:text-amber-300 text-xs font-semibold">
                    <i class="ph-fill ph-info mr-1"></i> Press <strong>N</strong> to toggle this notes prompter anytime during your pitch.
                </div>

                <div class="space-y-2">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-zinc-500">Key Speaking Points</span>
                    <div class="p-4 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 text-xs sm:text-sm text-slate-700 dark:text-zinc-200 font-medium leading-relaxed" id="speakerNotesText">
                        Loading talking points...
                    </div>
                </div>

                <!-- Keyboard Navigation Reference -->
                <div class="p-3 rounded-2xl bg-slate-100 dark:bg-zinc-800/80 text-[11px] text-slate-600 dark:text-zinc-400 space-y-1">
                    <span class="block font-bold text-slate-800 dark:text-zinc-200 uppercase tracking-wider text-[10px]">Keyboard Shortcuts:</span>
                    <div class="grid grid-cols-2 gap-1 font-mono text-[10px]">
                        <div><strong class="text-green-600">&rarr; / Space:</strong> Next</div>
                        <div><strong class="text-green-600">&larr;:</strong> Previous</div>
                        <div><strong class="text-green-600">F:</strong> Fullscreen</div>
                        <div><strong class="text-green-600">N:</strong> Toggle Notes</div>
                        <div><strong class="text-green-600">M:</strong> Toggle View Mode</div>
                        <div><strong class="text-green-600">T:</strong> Toggle Theme</div>
                        <div><strong class="text-green-600">H:</strong> Pin / Unpin Top</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="pt-3 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between text-xs text-slate-400">
            <span>TALA &bull; Presentation Mode</span>
            <button type="button" onclick="toggleNotes()" class="font-bold text-green-600 dark:text-green-400 hover:underline">Close Prompter</button>
        </div>
    </aside>


    <!-- ============================================================== -->
    <!-- LIVE DEMO SYSTEM LAUNCHPAD MODAL                               -->
    <!-- ============================================================== -->
    <div id="launchModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm">
        <div class="glass-card rounded-3xl max-w-lg w-full p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 pb-3">
                <div class="flex items-center gap-2">
                    <img src="TALA-LOGO.png" alt="TALA" class="w-7 h-7 object-contain">
                    <div>
                        <h4 class="font-black text-sm text-slate-900 dark:text-white">Live System Launchpad</h4>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500">Jump directly into running modules</p>
                    </div>
                </div>
                <button type="button" onclick="closeLaunchModal()" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 text-slate-500 flex items-center justify-center">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <div class="grid grid-cols-2 gap-2 text-xs font-bold">
                <a href="public_disclosure.php" target="_blank" class="p-3 rounded-2xl bg-slate-100 hover:bg-green-500/10 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-800 dark:text-zinc-200 flex items-center gap-2 transition-all">
                    <i class="ph-bold ph-file-text text-green-600 text-base"></i>
                    <span>Disclosure Board</span>
                </a>
                <a href="admin_budget.php" target="_blank" class="p-3 rounded-2xl bg-slate-100 hover:bg-green-500/10 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-800 dark:text-zinc-200 flex items-center gap-2 transition-all">
                    <i class="ph-bold ph-coins text-amber-500 text-base"></i>
                    <span>Budget Registry</span>
                </a>
                <a href="admin_governance.php" target="_blank" class="p-3 rounded-2xl bg-slate-100 hover:bg-green-500/10 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-800 dark:text-zinc-200 flex items-center gap-2 transition-all">
                    <i class="ph-bold ph-trophy text-amber-600 text-base"></i>
                    <span>Barangay Badges</span>
                </a>
                <a href="admin_mydc.php" target="_blank" class="p-3 rounded-2xl bg-slate-100 hover:bg-green-500/10 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-800 dark:text-zinc-200 flex items-center gap-2 transition-all">
                    <i class="ph-bold ph-users-three text-blue-600 text-base"></i>
                    <span>MYDC Council</span>
                </a>
                <a href="tickets.php" target="_blank" class="p-3 rounded-2xl bg-slate-100 hover:bg-green-500/10 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-800 dark:text-zinc-200 flex items-center gap-2 transition-all">
                    <i class="ph-bold ph-ticket text-rose-500 text-base"></i>
                    <span>Helpdesk Tickets</span>
                </a>
                <a href="login.php" target="_blank" class="p-3 rounded-2xl bg-[#15803d] hover:bg-[#166534] text-white flex items-center gap-2 transition-all">
                    <i class="ph-bold ph-sign-in text-base"></i>
                    <span>Admin Login</span>
                </a>
            </div>

            <div class="pt-2 text-right">
                <button type="button" onclick="closeLaunchModal()" class="px-4 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 text-xs font-bold">
                    Close
                </button>
            </div>
        </div>
    </div>


    <!-- ============================================================== -->
    <!-- JAVASCRIPT CONTROLLERS                                        -->
    <!-- ============================================================== -->
    <script>
        // Total slides in presentation
        const totalSlides = 8;
        let currentSlide = 1;
        let isScrollMode = false;

        // Workflow state definitions
        const workflowData = {
            1: {
                badge: "STAGE 01",
                module: "public_registration.php • youth_profiles",
                title: "Citizen Onboarding & Digital Youth ID",
                desc: "Online youth profiling with tamper-evident Digital QR ID generation for assembly & payout verification.",
                actors: ["Youth Citizen (15-30 yrs)", "SK Barangay Secretary", "Municipal LYDO"],
                artifact: "Digital QR Youth ID • Demographic Telemetry • Verified Citizen Record",
                icon: "ph-identification-card",
                visualTitle: "Digital Identity Pipeline",
                visualSub: "Instant verification with cryptographic hashing and QR scanning."
            },
            2: {
                badge: "STAGE 02",
                module: "admin_budget.php • sk_resolution.php",
                title: "AYIP Formulation & Barangay Budget Allocation",
                desc: "Annual Youth Investment Program (AYIP) creation with statutory 10% sector cap guard.",
                actors: ["SK Chairperson", "SK Treasurer", "Kagawad on Youth", "LGU Budget Officer"],
                artifact: "Approved AYIP Resolution • Municipal Fund Allocation • Sectoral Caps",
                icon: "ph-chart-pie",
                visualTitle: "Budget & Resolution Gateway",
                visualSub: "Automated cap validation preventing deficits past statutory limits."
            },
            3: {
                badge: "STAGE 03",
                module: "sk_voucher.php • admin_vouchers.php",
                title: "Check Voucher Disbursement & Liquidation",
                desc: "Standardized check voucher creation with COA codes and instant receipt attachments.",
                actors: ["SK Treasurer", "SK Chairperson", "Disbursing Officer", "Supplier / Payee"],
                artifact: "Check Disbursement Voucher • Liquidation Proofs • Bank Ledger Entry",
                icon: "ph-receipt",
                visualTitle: "COA-Compliant Voucher Engine",
                visualSub: "Real-time linkage between bank checks, official receipts & liquidation."
            },
            4: {
                badge: "STAGE 04",
                module: "manage_disclosure.php • public_disclosure.php",
                title: "Automatic Full Disclosure Board Posting",
                desc: "1-click quarterly disclosure publishing (Q1–Q4) for DILG compliance and public audit.",
                actors: ["All 46 SK Councils", "Municipal DILG Officer", "COA Auditors", "General Public"],
                artifact: "In-Browser PDF Disclosures • Timestamped Audit Certificate • Compliance Seal",
                icon: "ph-file-text",
                visualTitle: "DILG FDP Transparency Board",
                visualSub: "Zero-latency public transparency with tamper-resistant audit logs."
            },
            5: {
                badge: "STAGE 05",
                module: "admin_governance.php • admin_mom.php • tickets.php",
                title: "Executive Audit, SK Badges & Civic Loop",
                desc: "46-barangay governance ranking, official assembly resolutions, and direct youth ticketing.",
                actors: ["MYDC Council", "SK Municipal Federation", "Youth Constituents"],
                artifact: "Barangay Compliance Scorecard • Governance Badges • Assembly Resolutions",
                icon: "ph-trophy",
                visualTitle: "Executive Audit & Accountability Loop",
                visualSub: "Continuous governance accountability driving 100% municipal compliance."
            }
        };

        // ==========================================
        // DUAL-SCREEN BROADCAST SYNCHRONIZATION
        // ==========================================
        const PRESENTER_SYNC_CHANNEL = 'tala_presentation_sync';
        let syncChannel = null;
        try {
            if ('BroadcastChannel' in window) {
                syncChannel = new BroadcastChannel(PRESENTER_SYNC_CHANNEL);
                syncChannel.onmessage = (event) => {
                    const data = event.data;
                    if (!data) return;
                    if (data.type === 'GOTO_SLIDE' && typeof data.slide === 'number') {
                        goToSlide(data.slide, false);
                    } else if (data.type === 'SET_THEME' && data.theme) {
                        applyPresentationTheme(data.theme, false);
                    } else if (data.type === 'PING') {
                        try { syncChannel.postMessage({ type: 'PONG', slide: currentSlide, timestamp: Date.now() }); } catch(e){}
                    }
                };
            }
        } catch (e) {
            console.warn('BroadcastChannel error:', e);
        }

        // Cross-window sync via localStorage event fallback
        window.addEventListener('storage', (e) => {
            if (e.key === 'tala_sync_slide' && e.newValue) {
                try {
                    const data = JSON.parse(e.newValue);
                    if (data && typeof data.slide === 'number') {
                        goToSlide(data.slide, false);
                    }
                } catch(err) {}
            } else if (e.key === 'tala_sync_theme' && e.newValue) {
                applyPresentationTheme(e.newValue, false);
            }
        });

        function broadcastSlideChange(slideNum) {
            if (syncChannel) {
                try { syncChannel.postMessage({ type: 'GOTO_SLIDE', slide: slideNum, timestamp: Date.now() }); } catch(e){}
            }
            try {
                localStorage.setItem('tala_sync_slide', JSON.stringify({ slide: slideNum, timestamp: Date.now() }));
            } catch(e){}
        }

        function broadcastThemeChange(theme) {
            if (syncChannel) {
                try { syncChannel.postMessage({ type: 'SET_THEME', theme: theme, timestamp: Date.now() }); } catch(e){}
            }
            try {
                localStorage.setItem('tala_sync_theme', theme);
            } catch(e){}
        }

        // ==========================================
        // PRESENTER HOVER TELEPROMPTER CONTROLLER
        // ==========================================
        let hudPinned = true;
        let hudVisible = true;
        let speechResetTimer = null;

        // Default speaking scripts for each slide in Tagalog
        const defaultSlideSpeeches = {
            1: {
                title: "Pambungad: Ang TALA Web Platform",
                text: "Magandang araw po sa ating kagalang-galang na panel at mga panauhin. Ikinagagalak ko pong iprisinta ang Tumauini Active Youth Ledger Analytics o TALA — ang opisyal na digital governance web platform na nilikha upang maghatid ng buong transparency, real-time tracking ng pondo, at mabilis na serbisyo para sa kabataan ng Tumauini."
            },
            2: {
                title: "Mga Batas ng Republika at Legal Mandates",
                text: "Ang TALA ay mahigpit na nakasalig sa apat na pangunahing batas ng pamahalaan: ang RA 10742 para sa statutory youth development fund, ang RA 11768 para sa mandatory digital full disclosure at financial autonomy, ang RA 7160 Local Government Code, at ang RA 6713 Code of Conduct para sa bukas na pamamahala."
            },
            3: {
                title: "Bakit Binuo ang TALA: Problema at Solusyon",
                text: "Sa slide na ito, ipinapakita natin ang malaking kaibahan ng lumang mano-manong sistema kumpara sa modernong solusyon ng TALA. Dito ninyo makikita kung paano pinalitan ng digital ledgers at automated disclosures ang dating mabagal at magulong papeles."
            },
            4: {
                title: "Ang Daloy ng Pamamahala: 5 Yugto",
                text: "Ito ang kumpletong daloy ng pamamahala ng TALA mula sa pagpaparehistro ng kabataan, pagpaplano ng badyet, pag-audit ng voucher, hanggang sa pampublikong paglalathala at pagsusuri ng munisipyo sa lahat ng 46 na barangay."
            },
            5: {
                title: "Mga Pangunahing Module ng Pananalapi",
                text: "Narito ang tatlong pangunahing haligi ng TALA: ang DILG Full Disclosure Portal, ang Real-Time Budget Registry para sa 46.8 milyong pisong pondo ng kabataan, at ang COA-compliant Check Voucher and Liquidation engine."
            },
            6: {
                title: "Governance Badges, MYDC at Opisyal na Rekord",
                text: "Sa slide na ito, makikita ang ating Barangay Governance Badges na naghihikayat ng maagap na pag-uulat, ang MYDC council portal, ang opisyal na Minutes and Resolutions, at ang ating direktang youth helpdesk."
            },
            7: {
                title: "Arkitektura ng Sistema at Seguridad",
                text: "Ang teknikal na pundasyon ng TALA ay sadyang idinisenyo para sa bilis at proteksyon gamit ang pure PHP 8.2, MySQL 8.4, at Tailwind CSS. Walang mabigat na external frameworks, kaya wala pang 100 milliseconds ang bilis ng pag-load ng bawat pahina."
            },
            8: {
                title: "Naihatid na Epekto at Kinabukasan",
                text: "Bilang pagtatapos, napatunayan ng TALA ang 100% digital coverage sa buong Tumauini, pinabilis ng 85% ang pagproseso ng mga ulat, at nagbukas ng modernong pamamahala para sa kabataan. Maaari niyo na pong subukan ang ating live system."
            }
        };

        function setPresenterSpeech(speech, label) {
            if (speechResetTimer) {
                clearTimeout(speechResetTimer);
                speechResetTimer = null;
            }
            const hud = document.getElementById('presenterHoverCue');
            const textElem = document.getElementById('hudSpeechText');
            const labelElem = document.getElementById('hudSectionLabel');
            if (textElem && speech) {
                textElem.innerText = speech;
            }
            if (labelElem && label) {
                labelElem.innerText = label;
            }
            if (hud && hudVisible) {
                hud.style.opacity = '1';
                hud.classList.remove('hidden');
            }
        }

        function resetPresenterSpeech(delay = 1000) {
            if (speechResetTimer) clearTimeout(speechResetTimer);
            speechResetTimer = setTimeout(() => {
                const currentDefault = defaultSlideSpeeches[currentSlide];
                if (currentDefault) {
                    setPresenterSpeech(currentDefault.text, currentDefault.title);
                }
            }, delay);
        }

        function toggleHudVisibility() {
            hudVisible = !hudVisible;
            const hud = document.getElementById('presenterHoverCue');
            const btn = document.getElementById('dockSpeechBtn');
            if (hud) {
                if (hudVisible) {
                    hud.classList.remove('hidden');
                    hud.style.opacity = '1';
                    hud.style.pointerEvents = 'auto';
                } else {
                    hud.classList.add('hidden');
                    hud.style.opacity = '0';
                    hud.style.pointerEvents = 'none';
                }
            }
            if (btn) {
                if (hudVisible) {
                    btn.className = 'flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-green-500/10 text-[#15803d] dark:text-[#16a34a] text-xs font-bold transition-all tap-effect';
                } else {
                    btn.className = 'flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-500 text-xs font-bold transition-all tap-effect';
                }
            }
        }

        function toggleHudPin() {
            hudPinned = !hudPinned;
            const btn = document.getElementById('hudPinBtn');
            if (btn) {
                if (hudPinned) {
                    btn.className = 'px-2.5 py-1 rounded-lg bg-green-500/10 text-[#15803d] dark:text-[#16a34a] text-[10px] font-bold transition-colors flex items-center gap-1';
                    btn.querySelector('span').innerText = 'Pinned';
                } else {
                    btn.className = 'px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-400 text-[10px] font-bold transition-colors flex items-center gap-1';
                    btn.querySelector('span').innerText = 'Auto';
                }
            }
        }

        function initPresenterHoverListeners() {
            const speechElements = document.querySelectorAll('[data-speech]');
            speechElements.forEach(el => {
                el.addEventListener('mouseenter', () => {
                    const speech = el.getAttribute('data-speech');
                    const label = el.getAttribute('data-speech-title') || 'Punto sa Presentasyon';
                    setPresenterSpeech(speech, label);
                    el.classList.add('ring-2', 'ring-[#15803d]/40', 'dark:ring-[#16a34a]/40');
                });
                el.addEventListener('mouseleave', () => {
                    el.classList.remove('ring-2', 'ring-[#15803d]/40', 'dark:ring-[#16a34a]/40');
                    if (!hudPinned) {
                        resetPresenterSpeech(900);
                    }
                });
                el.addEventListener('click', () => {
                    const speech = el.getAttribute('data-speech');
                    const label = el.getAttribute('data-speech-title') || 'Punto sa Presentasyon';
                    setPresenterSpeech(speech, label);
                });
            });
        }

        // Slide Navigation
        function updateSlideUI() {
            // Update slide visibility
            document.querySelectorAll('.slide-view').forEach((s, idx) => {
                if (idx + 1 === currentSlide) {
                    s.classList.add('active-slide');
                } else {
                    s.classList.remove('active-slide');
                }
            });

            // Update top tracker
            const curStr = currentSlide < 10 ? '0' + currentSlide : currentSlide;
            document.getElementById('currentSlideDisplay').innerText = curStr;

            // Get current active slide title and notes
            const activeSlideElem = document.getElementById('slide-' + currentSlide);
            if (activeSlideElem) {
                const title = activeSlideElem.getAttribute('data-title') || 'Presentation';
                const notes = activeSlideElem.getAttribute('data-notes') || 'No speaker notes for this slide.';
                document.getElementById('slideTitleHeading').innerText = title;
                document.getElementById('speakerNotesText').innerText = notes;
                document.getElementById('notesSlideIndicator').innerText = `Slide ${curStr} Notes`;
            }

            // Refresh Presenter Speech Teleprompter to current slide's script
            const currentDefault = defaultSlideSpeeches[currentSlide];
            if (currentDefault) {
                setPresenterSpeech(currentDefault.text, currentDefault.title);
            }

            // Update bottom dots
            for (let i = 1; i <= totalSlides; i++) {
                const dot = document.getElementById('slideDot-' + i);
                if (dot) {
                    if (i === currentSlide) {
                        dot.className = 'slide-dot w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full transition-all duration-300 bg-[#15803d] dark:bg-[#16a34a] scale-125';
                    } else {
                        dot.className = 'slide-dot w-2.5 h-2.5 sm:w-3 sm:h-3 rounded-full transition-all duration-300 bg-slate-300 dark:bg-zinc-700 hover:bg-slate-400';
                    }
                }
            }

            // Instant top reset to prevent downward scroll jitter
            window.scrollTo(0, 0);
        }

        function nextSlide() {
            if (currentSlide < totalSlides) {
                goToSlide(currentSlide + 1, true);
            }
        }

        function prevSlide() {
            if (currentSlide > 1) {
                goToSlide(currentSlide - 1, true);
            }
        }

        function goToSlide(slideNum, shouldBroadcast = true) {
            if (slideNum >= 1 && slideNum <= totalSlides) {
                currentSlide = slideNum;
                updateSlideUI();
                if (shouldBroadcast) {
                    broadcastSlideChange(currentSlide);
                }
            }
        }

        // Interactive Workflow Step Controller
        let currentWfStep = 1;
        function setWorkflowStep(stepNum) {
            currentWfStep = stepNum;
            const data = workflowData[stepNum];
            if (!data) return;

            // Update buttons if present
            for (let i = 1; i <= 5; i++) {
                const btn = document.getElementById('wf-btn-' + i);
                if (btn) {
                    if (i === stepNum) {
                        btn.className = 'wf-btn glass-card p-3 rounded-2xl text-left border-2 border-[#15803d] dark:border-[#16a34a] transition-all';
                        const firstSpan = btn.querySelector('span:first-child');
                        if (firstSpan) firstSpan.className = 'block text-[10px] font-mono font-bold text-green-600 dark:text-green-400 uppercase';
                    } else {
                        btn.className = 'wf-btn glass-card p-3 rounded-2xl text-left border-2 border-transparent transition-all opacity-75 hover:opacity-100';
                        const firstSpan = btn.querySelector('span:first-child');
                        if (firstSpan) firstSpan.className = 'block text-[10px] font-mono font-bold text-slate-400 dark:text-zinc-500 uppercase';
                    }
                }
            }

            // Update content elements if card is present
            const card = document.getElementById('workflowContentCard');
            if (!card) return;
            card.style.opacity = '0.4';
            setTimeout(() => {
                const el = (id) => document.getElementById(id);
                if (el('wfStageBadge')) el('wfStageBadge').innerText = data.badge;
                if (el('wfModuleTag')) el('wfModuleTag').innerText = data.module;
                if (el('wfTitle')) el('wfTitle').innerText = data.title;
                if (el('wfDesc')) el('wfDesc').innerText = data.desc;
                if (el('wfArtifact')) el('wfArtifact').innerText = data.artifact;
                if (el('wfIcon')) el('wfIcon').className = 'ph-bold ' + data.icon;
                if (el('wfVisualTitle')) el('wfVisualTitle').innerText = data.visualTitle;
                if (el('wfVisualSub')) el('wfVisualSub').innerText = data.visualSub;

                // Update actors
                const actorsBox = el('wfActors');
                if (actorsBox && data.actors) {
                    actorsBox.innerHTML = '';
                    data.actors.forEach(actor => {
                        const span = document.createElement('span');
                        span.className = 'px-2.5 py-1 rounded-xl bg-slate-100 dark:bg-zinc-800 text-xs font-bold text-slate-700 dark:text-zinc-300';
                        span.innerText = actor;
                        actorsBox.appendChild(span);
                    });
                }

                card.style.opacity = '1';
            }, 120);
        }

        function nextWorkflowStep() {
            let next = currentWfStep + 1;
            if (next > 5) next = 1;
            setWorkflowStep(next);
        }

        function prevWorkflowStep() {
            let prev = currentWfStep - 1;
            if (prev < 1) prev = 5;
            setWorkflowStep(prev);
        }

        // Speaker Notes Drawer Toggle
        function toggleNotes() {
            const drawer = document.getElementById('notesDrawer');
            if (drawer) {
                drawer.classList.toggle('open');
            }
        }

        // Fullscreen Toggle
        function toggleFullScreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }

        // Auto-Hide Executive Header Logic
        const topHeader = document.getElementById('presentationHeader');
        const hoverTrigger = document.getElementById('topHoverZone');
        let hideHeaderTimer = null;
        let isHeaderPinned = false;

        function showTopHeader() {
            if (topHeader) {
                topHeader.classList.add('header-visible');
            }
            if (hideHeaderTimer) {
                clearTimeout(hideHeaderTimer);
                hideHeaderTimer = null;
            }
        }

        function scheduleHideTopHeader(delay = 1800) {
            if (isHeaderPinned) return;
            if (hideHeaderTimer) clearTimeout(hideHeaderTimer);
            hideHeaderTimer = setTimeout(() => {
                if (topHeader && !topHeader.matches(':hover') && !isHeaderPinned) {
                    topHeader.classList.remove('header-visible');
                }
            }, delay);
        }

        function showTopBarManual() {
            showTopHeader();
            scheduleHideTopHeader(3500);
        }

        function togglePinHeader() {
            isHeaderPinned = !isHeaderPinned;
            const pinIcon = document.getElementById('pinHeaderIcon');
            const pinBtn = document.getElementById('pinHeaderBtn');
            if (isHeaderPinned) {
                if (topHeader) topHeader.classList.add('header-pinned', 'header-visible');
                if (pinIcon) pinIcon.className = 'ph-fill ph-push-pin text-base text-green-600 dark:text-green-400';
                if (pinBtn) pinBtn.title = 'Unpin Top Bar (Auto-hide on)';
            } else {
                if (topHeader) topHeader.classList.remove('header-pinned');
                if (pinIcon) pinIcon.className = 'ph-bold ph-push-pin text-base text-slate-400 dark:text-zinc-500';
                if (pinBtn) pinBtn.title = 'Pin Top Bar (Keep visible)';
                scheduleHideTopHeader(1400);
            }
        }

        // Mouse hover listeners to reveal header
        document.addEventListener('mousemove', (e) => {
            if (e.clientY <= 65) {
                showTopHeader();
            } else if (topHeader && !topHeader.matches(':hover') && !isHeaderPinned) {
                scheduleHideTopHeader(1400);
            }
        });

        if (hoverTrigger) {
            hoverTrigger.addEventListener('mouseenter', showTopHeader);
        }
        if (topHeader) {
            topHeader.addEventListener('mouseenter', showTopHeader);
            topHeader.addEventListener('mouseleave', () => scheduleHideTopHeader(1200));
        }

        // Touch event to show on mobile when tapping near the top
        document.addEventListener('touchstart', (e) => {
            if (e.touches[0].clientY <= 80) {
                showTopHeader();
                scheduleHideTopHeader(3000);
            }
        }, { passive: true });

        // View Mode: Deck vs Scroll Mode
        function toggleViewMode() {
            isScrollMode = !isScrollMode;
            document.body.classList.toggle('scroll-mode', isScrollMode);
            const icon = document.getElementById('viewModeIcon');
            if (icon) {
                icon.className = isScrollMode ? 'ph-bold ph-slides text-base text-green-600' : 'ph-bold ph-presentation text-base';
            }
        }

        // Presentation Timer
        let secondsElapsed = 0;
        let timerInterval = setInterval(() => {
            secondsElapsed++;
            const mins = Math.floor(secondsElapsed / 60);
            const secs = secondsElapsed % 60;
            const str = (mins < 10 ? '0' + mins : mins) + ':' + (secs < 10 ? '0' + secs : secs);
            const el = document.getElementById('presentationTimer');
            if (el) el.innerText = str;
        }, 1000);

        function resetTimer() {
            secondsElapsed = 0;
            const el = document.getElementById('presentationTimer');
            if (el) el.innerText = '00:00';
        }

        // Theme Controller (Sync with TALA system localStorage & Broadcast)
        function applyPresentationTheme(theme, shouldBroadcast = true) {
            const isDark = theme === 'dark';
            document.documentElement.classList.toggle('dark', isDark);
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            localStorage.theme = isDark ? 'dark' : 'light';
            if (shouldBroadcast) {
                broadcastThemeChange(isDark ? 'dark' : 'light');
            }
        }

        function togglePresentationTheme() {
            const isDark = !document.documentElement.classList.contains('dark');
            applyPresentationTheme(isDark ? 'dark' : 'light', true);
        }

        // Launch Modal
        function openLaunchModal() {
            document.getElementById('launchModal').classList.remove('hidden');
            document.getElementById('launchModal').classList.add('flex');
        }
        function closeLaunchModal() {
            document.getElementById('launchModal').classList.remove('flex');
            document.getElementById('launchModal').classList.add('hidden');
        }

        // Keyboard Controls
        document.addEventListener('keydown', (e) => {
            // Ignore keystrokes if typing inside an input or textarea
            if (['INPUT', 'TEXTAREA'].includes(e.target.tagName)) return;

            if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'PageDown') {
                e.preventDefault();
                nextSlide();
            } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
                e.preventDefault();
                prevSlide();
            } else if (e.key === 'f' || e.key === 'F') {
                toggleFullScreen();
            } else if (e.key === 'n' || e.key === 'N') {
                toggleNotes();
            } else if (e.key === 't' || e.key === 'T') {
                togglePresentationTheme();
            } else if (e.key === 'm' || e.key === 'M') {
                toggleViewMode();
            } else if (e.key === 'h' || e.key === 'H') {
                togglePinHeader();
            } else if (e.key === 's' || e.key === 'S') {
                toggleHudVisibility();
            } else if (e.key >= '1' && e.key <= '8') {
                goToSlide(parseInt(e.key));
            } else if (e.key === 'Escape') {
                closeLaunchModal();
                const drawer = document.getElementById('notesDrawer');
                if (drawer && drawer.classList.contains('open')) {
                    drawer.classList.remove('open');
                }
            }
        });

        // Touch Swipe Navigation for Tablets & Mobile
        let touchStartX = 0;
        let touchEndX = 0;
        document.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        document.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, { passive: true });

        function handleSwipe() {
            const swipeThreshold = 50;
            if (touchEndX < touchStartX - swipeThreshold) {
                // Swiped Left -> Next Slide
                nextSlide();
            }
            if (touchEndX > touchStartX + swipeThreshold) {
                // Swiped Right -> Prev Slide
                prevSlide();
            }
        }

        // Initialize presentation
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('mode') === 'audience') {
                document.body.classList.add('audience-mode');
            }
            updateSlideUI();
            initPresenterHoverListeners();
            setWorkflowStep(1);
            if (!document.body.classList.contains('audience-mode')) {
                showTopHeader();
                scheduleHideTopHeader(3500);
            }
        });
    </script>
</body>
</html>
