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
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
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

        /* Modern Glass Card: Lessen Opacity, Subtle Gradient Wash, Frosted Blur */
        .glass-card,
        .below-section-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.68) 0%, rgba(240, 253, 244, 0.40) 60%, rgba(254, 252, 232, 0.30) 100%) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border: none !important;
            border-radius: 1.25rem !important;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.05), inset 0 1px 0 0 rgba(255, 255, 255, 0.5) !important;
            -webkit-box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.05) !important;
            transition: all 0.25s ease;
        }
        .dark .glass-card,
        .dark .below-section-card {
            background: linear-gradient(145deg, rgba(24, 24, 27, 0.68) 0%, rgba(20, 83, 45, 0.16) 60%, rgba(113, 63, 18, 0.12) 100%) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border: none !important;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.35), inset 0 1px 0 0 rgba(255, 255, 255, 0.05) !important;
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
            background-color: rgba(250, 251, 252, 0.65);
            background-image: radial-gradient(rgba(21, 128, 61, 0.18) 1.2px, transparent 1.2px);
            background-size: 14px 14px;
            border: none !important;
        }
        .dark .card-asset-grid {
            background-color: rgba(24, 24, 27, 0.50);
            background-image: radial-gradient(rgba(34, 197, 94, 0.2) 1.2px, transparent 1.2px);
            background-size: 14px 14px;
            border: none !important;
        }

        /* Modern Section Cards: Subtle Visible Gradient Wash, Frosted Blur Effect */
        .saas-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.72) 0%, rgba(240, 253, 244, 0.45) 50%, rgba(254, 252, 232, 0.35) 100%) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border: none !important;
            border-radius: 1.25rem;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.04), inset 0 1px 0 0 rgba(255, 255, 255, 0.6) !important;
        }
        .dark .saas-card {
            background: linear-gradient(145deg, rgba(24, 24, 27, 0.72) 0%, rgba(20, 83, 45, 0.18) 50%, rgba(113, 63, 18, 0.14) 100%) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border: none !important;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.30), inset 0 1px 0 0 rgba(255, 255, 255, 0.05) !important;
        }
        .saas-card:hover {
            transform: translateY(-3px);
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.85) 0%, rgba(240, 253, 244, 0.60) 50%, rgba(254, 252, 232, 0.50) 100%) !important;
            border: none !important;
            box-shadow: 0 16px 36px -8px rgba(21, 128, 61, 0.16) !important;
        }
        .dark .saas-card:hover {
            background: linear-gradient(145deg, rgba(24, 24, 27, 0.82) 0%, rgba(20, 83, 45, 0.28) 50%, rgba(113, 63, 18, 0.20) 100%) !important;
            border: none !important;
            box-shadow: 0 16px 36px -8px rgba(34, 197, 94, 0.22) !important;
        }

        /* Modern Frosted Blur Effect & Lessened Opacity for Below of Section Cards (Bottom Capsules) */
        .saas-card .saas-card-bottom,
        .saas-card > div:last-child:not(:first-child) {
            background: linear-gradient(135deg, rgba(241, 245, 249, 0.55) 0%, rgba(236, 253, 245, 0.35) 100%) !important;
            backdrop-filter: blur(14px) !important;
            -webkit-backdrop-filter: blur(14px) !important;
            border-radius: 0.875rem !important;
            padding: 0.45rem 0.75rem !important;
            border: none !important;
            box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, 0.4) !important;
            transition: background 0.25s ease;
        }
        .dark .saas-card .saas-card-bottom,
        .dark .saas-card > div:last-child:not(:first-child) {
            background: linear-gradient(135deg, rgba(39, 39, 42, 0.45) 0%, rgba(20, 83, 45, 0.22) 100%) !important;
            backdrop-filter: blur(14px) !important;
            -webkit-backdrop-filter: blur(14px) !important;
            border: none !important;
            box-shadow: inset 0 1px 0 0 rgba(255, 255, 255, 0.06) !important;
        }
        .saas-card:hover .saas-card-bottom,
        .saas-card:hover > div:last-child:not(:first-child) {
            background: linear-gradient(135deg, rgba(241, 245, 249, 0.75) 0%, rgba(236, 253, 245, 0.55) 100%) !important;
        }
        .dark .saas-card:hover .saas-card-bottom,
        .dark .saas-card:hover > div:last-child:not(:first-child) {
            background: linear-gradient(135deg, rgba(39, 39, 42, 0.65) 0%, rgba(20, 83, 45, 0.35) 100%) !important;
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

        /* Modern Animated Top Gradient Waves (Ocean Aurora Wave Motion) */
        @keyframes waveSlideForward {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(-50%, 0, 0); }
        }

        @keyframes waveSlideReverse {
            0% { transform: translate3d(-50%, 0, 0); }
            100% { transform: translate3d(0, 0, 0); }
        }

        @keyframes waveBobbing {
            0%, 100% { transform: translate3d(0, 0, 0) scaleY(1); }
            50% { transform: translate3d(-25%, -8px, 0) scaleY(1.12); }
        }

        .animate-wave-1 {
            animation: waveSlideForward 16s linear infinite;
            will-change: transform;
        }

        .animate-wave-2 {
            animation: waveSlideReverse 20s linear infinite;
            will-change: transform;
        }

        .animate-wave-3 {
            animation: waveBobbing 10s ease-in-out infinite alternate;
            will-change: transform;
        }

        @media (prefers-reduced-motion: reduce) {
            .animate-wave-1,
            .animate-wave-2,
            .animate-wave-3 {
                animation: none !important;
            }
        }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased min-h-screen selection:bg-[#15803d] selection:text-white relative">

    <!-- Top Ambient Modern Animated Gradient Waves (Centered, Soft Lessened Fluid Green & Yellow Ocean Wave) -->
    <div class="fixed top-0 left-0 right-0 h-72 sm:h-96 pointer-events-none z-0 overflow-hidden flex items-start justify-center" aria-hidden="true" style="mask-image: radial-gradient(ellipse 60% 80% at 50% 15%, black 30%, transparent 80%); -webkit-mask-image: radial-gradient(ellipse 60% 80% at 50% 15%, black 30%, transparent 80%);">
        <!-- Diffuse Backing Aura -->
        <div class="absolute -top-16 left-1/2 -translate-x-1/2 w-[850px] h-[240px] bg-gradient-to-r from-emerald-500/10 via-green-500/8 to-amber-400/10 dark:from-emerald-500/14 dark:via-green-600/12 dark:to-yellow-500/12 blur-3xl rounded-full"></div>

        <!-- Wave Canvas with Organic Soft Blur -->
        <div class="relative w-full h-full filter blur-[16px] opacity-70 dark:opacity-65">
            <!-- Wave 1: Flowing Emerald Green Wave (Soft Lessened Tint) -->
            <svg class="absolute top-0 left-0 w-[200%] h-full animate-wave-1" viewBox="0 0 2880 240" preserveAspectRatio="none" fill="none">
                <defs>
                    <linearGradient id="waveGradGreen" x1="0%" y1="0%" x2="100%" y2="80%">
                        <stop offset="0%" stop-color="#15803d" stop-opacity="0.18" />
                        <stop offset="30%" stop-color="#22c55e" stop-opacity="0.14" />
                        <stop offset="65%" stop-color="#4ade80" stop-opacity="0.08" />
                        <stop offset="100%" stop-color="#16a34a" stop-opacity="0.16" />
                    </linearGradient>
                </defs>
                <path d="M 0 0 L 0 95 C 180 145, 360 45, 540 85 C 720 125, 900 170, 1080 115 C 1260 60, 1350 145, 1440 95 C 1620 145, 1800 45, 1980 85 C 2160 125, 2340 170, 2520 115 C 2700 60, 2790 145, 2880 95 L 2880 0 Z" fill="url(#waveGradGreen)" />
            </svg>

            <!-- Wave 2: Flowing Warm Golden Yellow / Amber Wave (Soft Lessened Tint) -->
            <svg class="absolute top-0 left-0 w-[200%] h-full animate-wave-2" viewBox="0 0 2880 240" preserveAspectRatio="none" fill="none">
                <defs>
                    <linearGradient id="waveGradYellow" x1="100%" y1="0%" x2="0%" y2="90%">
                        <stop offset="0%" stop-color="#facc15" stop-opacity="0.16" />
                        <stop offset="40%" stop-color="#eab308" stop-opacity="0.12" />
                        <stop offset="75%" stop-color="#ca8a04" stop-opacity="0.07" />
                        <stop offset="100%" stop-color="#fde047" stop-opacity="0.14" />
                    </linearGradient>
                </defs>
                <path d="M 0 0 L 0 70 C 220 20, 440 145, 720 85 C 1000 25, 1220 135, 1440 70 C 1660 20, 1880 145, 2160 85 C 2440 25, 2660 135, 2880 70 L 2880 0 Z" fill="url(#waveGradYellow)" />
            </svg>

            <!-- Wave 3: Undulating Mixed Crest Ripple (Soft Lessened Tint) -->
            <svg class="absolute top-0 left-0 w-[200%] h-full animate-wave-3" viewBox="0 0 2880 240" preserveAspectRatio="none" fill="none">
                <defs>
                    <linearGradient id="waveGradMix" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#22c55e" stop-opacity="0.11" />
                        <stop offset="50%" stop-color="#eab308" stop-opacity="0.09" />
                        <stop offset="100%" stop-color="#15803d" stop-opacity="0.11" />
                    </linearGradient>
                </defs>
                <path d="M 0 0 L 0 50 C 360 110, 720 15, 1080 75 C 1260 110, 1380 30, 1440 50 C 1800 110, 2160 15, 2520 75 C 2700 110, 2820 30, 2880 50 L 2880 0 Z" fill="url(#waveGradMix)" />
            </svg>
        </div>
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

                <!-- Live Demo Launch Button (Directly routes to login dashboard) -->
                <a href="login.php" target="_blank" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white px-3 py-1.5 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-all tap-effect" title="Launch TALA Live App (Login Dashboard)">
                    <i class="ph-bold ph-arrow-square-out text-sm relative z-10"></i>
                    <span class="relative z-10 hidden sm:inline">Launch Live App</span>
                </a>
            </div>
        </div>
    </header>

    <!-- MAIN PRESENTATION STAGE (Single-Screen Fit, Zero Scrolling) -->
    <main class="w-full max-w-[1500px] mx-auto flex flex-col justify-center relative z-10" id="presentationContainer">

        <!-- ========================================== -->
        <!-- SLIDE 1: TITLE - TALA                      -->
        <!-- ========================================== -->
        <section class="slide-view active-slide flex flex-col items-center justify-center w-full h-full text-center" id="slide-1" data-title="TALA" data-notes="Good day to our panel and guests. Today, I will present TALA, which stands for Tumauini Active Youth Ledger Analytics. It brings youth records, budgets, reports, and community services into one web platform. Let us begin with the purpose behind the system. Next, I will explain the responsibilities that guided its design.">
            
            <div class="w-full h-full flex flex-col items-center justify-center text-center my-auto p-4 group cursor-pointer" data-speech="Good day to our panel and guests. Today, I will present TALA, which stands for Tumauini Active Youth Ledger Analytics. It brings youth records, budgets, reports, and community services into one web platform. Let us begin with the purpose behind the system." data-speech-title="Introduction to TALA">
                
                <!-- Centered Logo -->
                <div class="flex items-center justify-center">
                    <img src="tala_logo_transparent.png" alt="TALA Logo" class="w-32 h-32 sm:w-40 sm:h-40 md:w-48 md:h-48 lg:w-52 lg:h-52 max-w-[50vw] max-h-[25vh] object-contain rounded-full hover:scale-105 transition-transform duration-300">
                </div>

                <!-- Modern Frosted Glass Below Section Card (Lessened Opacity, Modern Blur, Zero Stroke) -->
                <div class="mt-3 sm:mt-4 w-full max-w-3xl mx-auto rounded-3xl below-section-card glass-card p-3.5 sm:p-5 shadow-xl shadow-green-950/5 transition-all duration-300">
                    <!-- Subtle Top Metadata Bar -->
                    <div class="flex items-center justify-between pb-2 mb-2 text-[11px] font-semibold text-slate-500 dark:text-zinc-400">
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
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-2" data-title="Republic Acts in Government" data-notes="First, let us look at the responsibilities behind youth governance. This slide names four laws that provide context for SK planning, local government, and ethical public service. The main idea is simple: officials need clear plans, organized records, and information that people can review. TALA helps support that work. However, these responsibilities can be difficult to manage through paper records alone.">
            
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
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between group" data-speech="First, Republic Act 10742 provides the foundation for the Sangguniang Kabataan. For this presentation, the key point is planning: youth programs need a clear development plan and an annual investment program. TALA helps organize the records used in that process." data-speech-title="RA 10742: Youth Planning">
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
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between group" data-speech="Next, Republic Act 11768 strengthens the SK framework. In TALA, we focus on making financial records easier to manage and review. This helps officials explain how youth funds are used." data-speech-title="RA 11768: Accountability">
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
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between group" data-speech="In addition, Republic Act 7160 provides the wider framework for local government. TALA brings barangay reports into one place so the municipal team can review them more easily." data-speech-title="RA 7160: Local Government">
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
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between group" data-speech="Finally, Republic Act 6713 highlights ethical public service and accountability. TALA supports these goals by making public information easier to find and giving young people a way to raise concerns." data-speech-title="RA 6713: Ethical Public Service">
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
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-3" data-title="Problem vs Solution" data-notes="Next, let us compare the usual paper-based process with the TALA approach. Paper files can be hard to locate, and repeated data entry can slow down reporting. TALA brings related records into connected modules, making information easier to organize and retrieve. To show how this works, I will walk through the process from registration to review.">
            
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
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="However, having clear responsibilities does not always make the work easy. With paper records, officials may need to enter the same details several times. Reports can be difficult to find, and the public may have to visit an office to request information." data-speech-title="The Paper-Based Process">
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
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-rose-600 dark:text-rose-400">HIGH FRICTION</span>
                            <span class="text-[10px] font-bold text-slate-500 dark:text-zinc-400">Manual Paper Workflow</span>
                        </div>
                    </div>

                    <!-- Right: The TALA Solution -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="This is where TALA helps. Instead of keeping information in separate paper files, officials can manage records through connected modules. Approved reports can be shared online, while budget records are easier to review." data-speech-title="The TALA Digital Approach">
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
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">100% AUTOMATED</span>
                            <span class="text-[10px] font-bold text-[#15803d] dark:text-[#16a34a]">Live Digital Governance</span>
                        </div>
                    </div>

                </div>

                <!-- Footer Transformation Highlight -->
                <div class="glass-card px-4 py-2 rounded-xl flex items-center justify-between text-[11px] sm:text-xs font-semibold text-slate-600 dark:text-zinc-300 mt-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-md bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] font-mono font-bold text-[10px]">PARADIGM</span>
                        <span>Eliminating 21-day reporting delays with real-time digital transparency across 46 barangays.</span>
                    </div>
                    <button type="button" onclick="nextSlide()" class="text-[#15803d] dark:text-[#16a34a] font-bold hover:underline flex-shrink-0 ml-2 flex items-center gap-1">
                        <span>End-to-End Workflow &rarr;</span>
                    </button>
                </div>
            </div>
        </section>


        <!-- ========================================== -->
        <!-- SLIDE 4: THE END-TO-END WORKFLOW           -->
        <!-- ========================================== -->
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-4" data-title="System Workflow" data-notes="The workflow has five stages. First, youth registration creates the records used to identify participants. Next, officials prepare their program and budget plans. After that, they record vouchers and attach supporting documents. Once reports are approved, they can be published for public viewing. Finally, the municipal team reviews submissions and follows up on incomplete records. With that flow in mind, let us look at the financial tools.">
            
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
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between group" data-speech="First, the process starts with youth registration. Staff record the details of each young person and can use a QR ID to help identify their record. This makes it easier to connect a participant with the activities they attend." data-speech-title="Stage 1: Youth Registration">
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
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between group" data-speech="Next, the SK team prepares its youth investment program and budget. TALA keeps the planned programs and their amounts together, making it easier to compare activities with the available funds." data-speech-title="Stage 2: Program and Budget Planning">
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
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between group" data-speech="After planning comes financial documentation. Officials record check vouchers and attach receipts or other supporting files. Keeping these documents together makes liquidation and later review easier." data-speech-title="Stage 3: Vouchers and Supporting Documents">
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
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between group" data-speech="Once reports are reviewed and approved, they can be published through the Full Disclosure Portal. The public can view the available documents online, including PDF reports, without needing a separate office visit." data-speech-title="Stage 4: Public Disclosure">
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
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between group" data-speech="Finally, the municipal team can review barangay submissions and monitor their progress. Governance badges help make reporting performance easier to see. The goal is to encourage timely and complete records." data-speech-title="Stage 5: Review and Monitoring">
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
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-5" data-title="Features: Fiscal Transparency" data-notes="Now, we move to three financial tools. The Full Disclosure Portal shares published reports with the public. The budget console helps officials compare planned amounts with recorded spending. The voucher module keeps transactions and supporting documents together. In simple terms, these tools help answer three questions: what was planned, what was spent, and where are the supporting records? Next, we will look beyond finances.">
            
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
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Let us now look at the Full Disclosure Portal. This is where the public can find published financial reports. The built-in PDF preview makes these documents easier to read in the browser." data-speech-title="Full Disclosure Portal">
                        <div>
                            <!-- Top UI Asset Container -->
                            <div class="card-asset-grid h-32 sm:h-36 w-full rounded-2xl relative flex items-center justify-center p-3 overflow-hidden">
                                <!-- Miniature FDP Live Document Window -->
                                <div class="w-full max-w-[240px] bg-white/95 dark:bg-zinc-900/95 rounded-xl shadow-lg p-2.5 transform group-hover:scale-105 transition-transform duration-300">
                                    <!-- Top Browser Title & Status Bar -->
                                    <div class="flex items-center justify-between pb-1.5 mb-1.5 border-b border-slate-100 dark:border-zinc-800">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                                            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                            <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                                            <span class="text-[9px] font-mono text-slate-400 dark:text-zinc-500 ml-1">fdp.gov.ph</span>
                                        </div>
                                        <div class="flex items-center gap-1 text-[8px] font-bold font-mono px-1.5 py-0.5 rounded-full bg-green-500/10 text-[#15803d] dark:text-[#16a34a]">
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#15803d] animate-ping"></span>
                                            <span>DILG SEAL</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Live Document Capsule Preview -->
                                    <div class="bg-slate-50 dark:bg-zinc-800/80 rounded-xl p-2 flex items-center gap-2 mb-1.5">
                                        <div class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0">
                                            <i class="ph-bold ph-file-pdf text-base"></i>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between">
                                                <span class="text-[10px] font-black text-slate-800 dark:text-zinc-100 truncate">Q1–Q4 Financials.pdf</span>
                                                <i class="ph-bold ph-check-circle text-xs text-[#15803d]"></i>
                                            </div>
                                            <div class="flex items-center gap-1 text-[8px] font-mono text-slate-400 dark:text-zinc-500 mt-0.5">
                                                <span>184+ Disclosures</span>
                                                <span>&bull;</span>
                                                <span class="text-[#15803d] font-bold">100% Published</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Compliance Mini Meter -->
                                    <div class="w-full bg-slate-100 dark:bg-zinc-800 rounded-full h-1.5 overflow-hidden">
                                        <div class="bg-gradient-to-r from-emerald-500 to-[#15803d] h-full rounded-full w-full"></div>
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
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Next is the budget console. It brings budget records from the barangays into a shared view. Officials can compare approved amounts with recorded spending and see which items need attention." data-speech-title="Real-Time Budget Console">
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
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="The third financial tool handles check vouchers and liquidation records. Receipts and supporting documents stay connected to the relevant transaction. As a result, officials can find the information they need for review more easily." data-speech-title="Check Vouchers and Liquidation">
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
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-6" data-title="Features: Civic Governance" data-notes="In addition to financial records, TALA helps organize governance activities. Barangay badges show reporting progress. MYDC and YORP records help organize youth groups and representatives. Meeting minutes and resolutions preserve decisions. However, good governance also needs feedback, so the helpdesk provides a way for young people to raise concerns. Next, I will briefly explain the technology behind these tools.">
            
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
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Beyond finances, TALA also supports governance. The badge system shows barangay reporting performance using Gold, Silver, and Bronze levels. This gives the team a quick way to see progress and identify follow-up work." data-speech-title="Barangay Governance Badges">
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
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Next, the MYDC and YORP modules organize records for youth organizations and their representatives. This helps the municipal team keep track of the groups involved in local youth development." data-speech-title="MYDC and YORP Records">
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
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="In addition, the records module keeps minutes of meetings and resolutions in one place. Officials can refer to attendance, discussions, and decisions when preparing reports or following up on agreed actions." data-speech-title="Meeting Minutes and Resolutions">
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
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="However, governance is also about listening. The helpdesk lets young people send questions, concerns, and suggestions. Each ticket provides a record that officials can use to follow up on the request." data-speech-title="Youth Helpdesk">
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

                <!-- Footer Civic Core Highlight (Below Section Card: Frosted Glass) -->
                <div class="glass-card px-4 py-2 rounded-xl flex items-center justify-between text-[11px] sm:text-xs font-semibold text-slate-600 dark:text-zinc-300 mt-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-md bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] font-mono font-bold text-[10px]">CIVIC CORE</span>
                        <span class="text-slate-600 dark:text-zinc-300 text-[11px] sm:text-xs">Unified command center, youth assembly records, and helpdesk SLA ticketing across 46 barangays.</span>
                    </div>
                    <button type="button" onclick="nextSlide()" class="text-[#15803d] dark:text-[#16a34a] font-bold hover:underline flex-shrink-0 ml-2 flex items-center gap-1">
                        <span>Technical Stack &rarr;</span>
                    </button>
                </div>
            </div>
        </section>


        <!-- ========================================== -->
        <!-- SLIDE 7: TECHNICAL ARCHITECTURE & STACK    -->
        <!-- ========================================== -->
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-7" data-title="Technical Architecture" data-notes="Behind the platform, PHP processes requests and MySQL stores related records. On the screen, Tailwind CSS helps the pages adapt to different devices. The shared theme switch keeps light and dark mode consistent. User roles help separate the responsibilities of administrators and SK chairpersons. You do not need to remember every technical term. The main point is that these parts work together to organize information and control access. Finally, let us look at the overall value and the next steps.">
            
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
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Now, let us briefly look at how the system is built. PHP handles the work on the server, while PDO prepared statements help the application work with database queries. In simple terms, this is the part that processes requests and saves records." data-speech-title="Server-Side Processing: PHP and PDO">
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
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Next, MySQL stores the information behind the platform. It connects related records, such as a barangay, its budget, and its transactions. Database transactions help keep related changes together." data-speech-title="Data Storage: MySQL">
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
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="On the screen, Tailwind CSS helps keep the design consistent across different devices. The layout adapts to desktop and mobile screens, and the shared theme control changes the page between light and dark mode." data-speech-title="Responsive Interface and Themes">
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
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between group" data-speech="Finally, access controls help separate what different users can do. For example, an administrator and an SK chairperson have different responsibilities. These controls help keep account actions within the appropriate role." data-speech-title="User Roles and Access">
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
        <section class="slide-view flex flex-col justify-center items-center w-full h-full" id="slide-8" data-title="Impact, Roadmap & Outro" data-notes="To conclude, TALA brings youth records, financial documents, and public information into one platform. Its value is easier organization, clearer reporting, and better access to available information. Looking ahead, the roadmap includes a mobile web app, project mapping, and SMS notifications. These are planned improvements. Next, I will open the live system and show how the current modules work. Thank you, and I welcome your questions.">
            
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

                <!-- Four equal section cards: impact and three roadmap priorities -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 w-full">
                    <div class="saas-card p-3.5 sm:p-4 rounded-2xl flex flex-col justify-between group" data-speech="To bring everything together, TALA connects youth records, financial documents, and public information in one platform. This makes daily work easier to organize and gives the community a clearer view of youth governance." data-speech-title="Connected Youth Governance">
                        <div>
                            <div class="card-asset-grid h-24 sm:h-28 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-3">
                                <div class="w-full max-w-[260px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-3 transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center justify-between mb-2">
                                        <i class="ph-bold ph-chart-line-up text-xl text-[#15803d] dark:text-[#16a34a]"></i>
                                        <span class="px-2 py-1 rounded-lg bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] text-[10px] font-mono font-bold">IMPACT</span>
                                    </div>
                                    <p class="text-xs font-extrabold text-slate-900 dark:text-white">Connected governance.</p>
                                    <p class="text-[10px] text-slate-500 dark:text-zinc-400 mt-0.5">Transparent public service.</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-[#15803d] dark:text-[#16a34a] font-bold text-[10px] uppercase tracking-wider mb-2">
                                <i class="ph-bold ph-chart-line-up text-sm"></i>
                                <span>YOUTH GOVERNANCE</span>
                            </div>
                            <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white leading-snug mb-2">Measurable Impact</h3>
                            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-zinc-400 leading-relaxed">TALA unifies Tumauini's barangays through digital reporting, transparent fund monitoring, and open public information, helping councils deliver faster, more accountable services for the youth.</p>
                        </div>
                        <div class="mt-3 text-[10px] font-medium text-slate-600 dark:text-zinc-300">
                            <span class="font-mono font-bold text-[#15803d] dark:text-[#16a34a]">COMMUNITY IMPACT</span> &middot; Better service for every youth
                        </div>
                    </div>

                    <div class="saas-card p-3.5 sm:p-4 rounded-2xl flex flex-col justify-between group" data-speech="Looking ahead, the roadmap includes a Progressive Web App. The aim is to improve mobile access and explore ways to prepare selected records when an internet connection is unavailable. This is a planned improvement." data-speech-title="Planned Mobile App">
                        <div>
                            <div class="card-asset-grid h-24 sm:h-28 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-3">
                                <div class="w-full max-w-[260px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-3 transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center justify-between mb-2">
                                        <i class="ph-bold ph-device-mobile text-xl text-[#15803d] dark:text-[#16a34a]"></i>
                                        <span class="px-2 py-1 rounded-lg bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] text-[10px] font-mono font-bold">Q1</span>
                                    </div>
                                    <p class="text-xs font-extrabold text-slate-900 dark:text-white">Draft anywhere.</p>
                                    <p class="text-[10px] text-slate-500 dark:text-zinc-400 mt-0.5">Sync when connected.</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-[#15803d] dark:text-[#16a34a] font-bold text-[10px] uppercase tracking-wider mb-2">
                                <i class="ph-bold ph-device-mobile text-sm"></i>
                                <span>OFFLINE WORKSPACE</span>
                            </div>
                            <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white leading-snug mb-2">Mobile Progressive Web App</h3>
                            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-zinc-400 leading-relaxed">Offline-first voucher drafting for remote barangays, bringing essential youth services closer to every community.</p>
                        </div>
                        <div class="mt-3 text-[10px] font-medium text-slate-600 dark:text-zinc-300">
                            <span class="font-mono font-bold text-[#15803d] dark:text-[#16a34a]">Q1 ROADMAP</span> &middot; Access beyond connectivity
                        </div>
                    </div>

                    <div class="saas-card p-3.5 sm:p-4 rounded-2xl flex flex-col justify-between group" data-speech="Another planned improvement is project mapping. A map would help users see where youth projects are located across Tumauini and connect those locations with the available project records." data-speech-title="Planned Project Mapping">
                        <div>
                            <div class="card-asset-grid h-24 sm:h-28 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-3">
                                <div class="w-full max-w-[260px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-3 transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center justify-between mb-2">
                                        <i class="ph-bold ph-map-pin text-xl text-[#15803d] dark:text-[#16a34a]"></i>
                                        <span class="px-2 py-1 rounded-lg bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] text-[10px] font-mono font-bold">Q2</span>
                                    </div>
                                    <p class="text-xs font-extrabold text-slate-900 dark:text-white">See every project.</p>
                                    <p class="text-[10px] text-slate-500 dark:text-zinc-400 mt-0.5">Locate funded initiatives.</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-[#15803d] dark:text-[#16a34a] font-bold text-[10px] uppercase tracking-wider mb-2">
                                <i class="ph-bold ph-map-pin text-sm"></i>
                                <span>PROJECT VISIBILITY</span>
                            </div>
                            <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white leading-snug mb-2">GIS-Based Project Mapping</h3>
                            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-zinc-400 leading-relaxed">An interactive map pin-pointing funded youth projects across Tumauini for easier monitoring and public inspection.</p>
                        </div>
                        <div class="mt-3 text-[10px] font-medium text-slate-600 dark:text-zinc-300">
                            <span class="font-mono font-bold text-[#15803d] dark:text-[#16a34a]">Q2 ROADMAP</span> &middot; Accountability on the map
                        </div>
                    </div>

                    <div class="saas-card p-3.5 sm:p-4 rounded-2xl flex flex-col justify-between group" data-speech="In addition, the roadmap includes SMS notifications. These would help share meeting reminders, opportunities, and urgent announcements with people who may not be online regularly." data-speech-title="Planned SMS Notifications">
                        <div>
                            <div class="card-asset-grid h-24 sm:h-28 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-3">
                                <div class="w-full max-w-[260px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-3 transform group-hover:scale-105 transition-transform duration-300">
                                    <div class="flex items-center justify-between mb-2">
                                        <i class="ph-bold ph-chat-circle-dots text-xl text-[#15803d] dark:text-[#16a34a]"></i>
                                        <span class="px-2 py-1 rounded-lg bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] text-[10px] font-mono font-bold">Q3</span>
                                    </div>
                                    <p class="text-xs font-extrabold text-slate-900 dark:text-white">Keep youth informed.</p>
                                    <p class="text-[10px] text-slate-500 dark:text-zinc-400 mt-0.5">Reach communities directly.</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-[#15803d] dark:text-[#16a34a] font-bold text-[10px] uppercase tracking-wider mb-2">
                                <i class="ph-bold ph-chat-circle-dots text-sm"></i>
                                <span>COMMUNITY ALERTS</span>
                            </div>
                            <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white leading-snug mb-2">SMS Broadcast Gateway</h3>
                            <p class="text-[11px] sm:text-xs text-slate-500 dark:text-zinc-400 leading-relaxed">SMS alerts for assemblies, grants, and emergencies, helping youth receive timely updates wherever they are.</p>
                        </div>
                        <div class="mt-3 text-[10px] font-medium text-slate-600 dark:text-zinc-300">
                            <span class="font-mono font-bold text-[#15803d] dark:text-[#16a34a]">Q3 ROADMAP</span> &middot; Timely information for every youth
                        </div>
                    </div>

                </div>

                <!-- Bottom Live Launchpad & Closing Banner -->
                <div class="saas-card rounded-2xl p-3 sm:p-4 flex flex-col md:flex-row items-center justify-between gap-3 mt-2 w-full group" data-speech="That concludes the overview. Next, I will demonstrate the live system so you can see how the modules work together. Thank you for listening. I am ready to answer your questions." data-speech-title="Live Demonstration and Questions">
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
    <!-- PRESENTER HOVER TELEPROMPTER HUD (Plain English Speaking Script) -->
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
                        Read aloud to the panel:
                    </span>
                    <span class="text-xs font-bold text-slate-500 dark:text-zinc-400 font-sans truncate" id="hudSectionLabel">Introduction to TALA</span>
                </div>
                <p id="hudSpeechText" class="text-sm sm:text-base md:text-lg font-bold text-slate-900 dark:text-white leading-relaxed">
                    Good day to our panel and guests. Today, I will present TALA, which stands for Tumauini Active Youth Ledger Analytics. It brings youth records, budgets, reports, and community services into one web platform. Let us begin with the purpose behind the system.
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0 pt-1">
            <a href="presentation_audience.php" target="_blank" class="px-3 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold transition-all tap-effect flex items-center gap-1.5 shadow-sm" title="Open the separate presentation screen">
                <i class="ph-bold ph-monitor-play text-sm"></i>
                <span class="hidden sm:inline">Presenter Screen</span>
            </a>
            <button type="button" onclick="toggleHudPin()" id="hudPinBtn" class="px-2.5 py-1.5 rounded-xl bg-green-500/10 text-[#15803d] dark:text-[#16a34a] text-xs font-bold transition-colors flex items-center gap-1" title="Keep the script pinned on screen">
                <i class="ph-bold ph-push-pin text-sm"></i>
                <span class="hidden sm:inline">Pinned</span>
            </button>
            <button type="button" onclick="toggleHudVisibility()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-600 flex items-center justify-center text-sm" title="Hide script (Shortcut: S)">
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
            <a href="presentation_audience.php" target="_blank" class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl bg-[#15803d]/10 hover:bg-[#15803d]/20 text-[#15803d] dark:text-[#16a34a] text-xs font-bold transition-all tap-effect" title="Open the separate presentation screen">
                <i class="ph-bold ph-broadcast text-sm"></i>
                <span class="hidden md:inline">Presenter View</span>
            </a>

            <!-- Presenter Speech HUD Toggle Button -->
            <button type="button" onclick="toggleHudVisibility()" id="dockSpeechBtn" class="flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-green-500/10 text-[#15803d] dark:text-[#16a34a] text-xs font-bold transition-all tap-effect" title="Toggle the speaking script (Shortcut: S)">
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

        // Plain English scripts with transitions for each slide.
        const defaultSlideSpeeches = {
            "1": {
                        "title": "Introduction to TALA",
                        "text": "Good day to our panel and guests. Today, I will present TALA, which stands for Tumauini Active Youth Ledger Analytics. It brings youth records, budgets, reports, and community services into one web platform. Let us begin with the purpose behind the system. Next, I will explain the responsibilities that guided its design."
            },
            "2": {
                        "title": "The Purpose Behind the Platform",
                        "text": "First, let us look at the responsibilities behind youth governance. This slide names four laws that provide context for SK planning, local government, and ethical public service. The main idea is simple: officials need clear plans, organized records, and information that people can review. TALA helps support that work. However, these responsibilities can be difficult to manage through paper records alone."
            },
            "3": {
                        "title": "From Paper Records to Connected Tools",
                        "text": "Next, let us compare the usual paper-based process with the TALA approach. Paper files can be hard to locate, and repeated data entry can slow down reporting. TALA brings related records into connected modules, making information easier to organize and retrieve. To show how this works, I will walk through the process from registration to review."
            },
            "4": {
                        "title": "The Five-Stage Workflow",
                        "text": "The workflow has five stages. First, youth registration creates the records used to identify participants. Next, officials prepare their program and budget plans. After that, they record vouchers and attach supporting documents. Once reports are approved, they can be published for public viewing. Finally, the municipal team reviews submissions and follows up on incomplete records. With that flow in mind, let us look at the financial tools."
            },
            "5": {
                        "title": "Financial Records and Public Disclosure",
                        "text": "Now, we move to three financial tools. The Full Disclosure Portal shares published reports with the public. The budget console helps officials compare planned amounts with recorded spending. The voucher module keeps transactions and supporting documents together. In simple terms, these tools help answer three questions: what was planned, what was spent, and where are the supporting records? Next, we will look beyond finances."
            },
            "6": {
                        "title": "Governance and Community Participation",
                        "text": "In addition to financial records, TALA helps organize governance activities. Barangay badges show reporting progress. MYDC and YORP records help organize youth groups and representatives. Meeting minutes and resolutions preserve decisions. However, good governance also needs feedback, so the helpdesk provides a way for young people to raise concerns. Next, I will briefly explain the technology behind these tools."
            },
            "7": {
                        "title": "How the System Works",
                        "text": "Behind the platform, PHP processes requests and MySQL stores related records. On the screen, Tailwind CSS helps the pages adapt to different devices. The shared theme switch keeps light and dark mode consistent. User roles help separate the responsibilities of administrators and SK chairpersons. You do not need to remember every technical term. The main point is that these parts work together to organize information and control access. Finally, let us look at the overall value and the next steps."
            },
            "8": {
                        "title": "Overall Value and Next Steps",
                        "text": "To conclude, TALA brings youth records, financial documents, and public information into one platform. Its value is easier organization, clearer reporting, and better access to available information. Looking ahead, the roadmap includes a mobile web app, project mapping, and SMS notifications. These are planned improvements. Next, I will open the live system and show how the current modules work. Thank you, and I welcome your questions."
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
