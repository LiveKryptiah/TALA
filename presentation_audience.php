<?php
/**
 * TALA - Presentation (Audience / Projector Screen)
 * Clean, high-impact projection display for the panel, audition, and public audience.
 * Real-time two-way synchronization with presenter_view.php via BroadcastChannel & localStorage.
 */
session_start();
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALA • Tumauini Active Youth Ledger Analytics (Audience Display)</title>
    
    <!-- Tailwind CSS 3 & Fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web@2.1.1"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>

    <style>
        /* Hidden scrollbars for clean presentation mode */
        ::-webkit-scrollbar { display: none; }
        * { -ms-overflow-style: none; scrollbar-width: none; }

        html, body {
            height: 100%;
            width: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
            overflow: hidden;
            user-select: none;
            position: relative;
            background-color: #fafbfc;
            background-image: 
                radial-gradient(ellipse 70% 45% at 50% -10%, rgba(34, 197, 94, 0.16) 0%, rgba(250, 204, 21, 0.12) 40%, transparent 75%),
                radial-gradient(circle 500px at 80% -5%, rgba(234, 179, 8, 0.10) 0%, transparent 60%),
                radial-gradient(circle 450px at 20% -5%, rgba(21, 128, 61, 0.12) 0%, transparent 60%);
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        .dark body {
            background-color: #090a0b;
            background-image: 
                radial-gradient(ellipse 70% 45% at 50% -10%, rgba(34, 197, 94, 0.20) 0%, rgba(234, 179, 8, 0.14) 45%, transparent 75%),
                radial-gradient(circle 500px at 80% -5%, rgba(202, 138, 4, 0.14) 0%, transparent 60%),
                radial-gradient(circle 450px at 20% -5%, rgba(21, 128, 61, 0.18) 0%, transparent 60%);
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        /* Ambient Top Modern Gradient (Green & Yellow) with subtle dot grid */
        .stage-bg {
            background-color: #fafbfc !important;
            background-image: 
                radial-gradient(ellipse 75% 50% at 50% -12%, rgba(34, 197, 94, 0.18) 0%, rgba(250, 204, 21, 0.14) 45%, transparent 75%),
                radial-gradient(circle 500px at 80% -5%, rgba(234, 179, 8, 0.12) 0%, transparent 60%),
                radial-gradient(circle 450px at 20% -5%, rgba(21, 128, 61, 0.14) 0%, transparent 60%),
                radial-gradient(rgba(21, 128, 61, 0.08) 1.2px, transparent 1.2px) !important;
            background-size: 100% 100%, 100% 100%, 100% 100%, 24px 24px !important;
            background-repeat: no-repeat, no-repeat, no-repeat, repeat !important;
            background-attachment: fixed !important;
        }
        .dark .stage-bg {
            background-color: #090a0b !important;
            background-image: 
                radial-gradient(ellipse 75% 50% at 50% -12%, rgba(34, 197, 94, 0.22) 0%, rgba(234, 179, 8, 0.16) 45%, transparent 75%),
                radial-gradient(circle 500px at 80% -5%, rgba(202, 138, 4, 0.15) 0%, transparent 60%),
                radial-gradient(circle 450px at 20% -5%, rgba(21, 128, 61, 0.20) 0%, transparent 60%),
                radial-gradient(rgba(34, 197, 94, 0.12) 1.2px, transparent 1.2px) !important;
            background-size: 100% 100%, 100% 100%, 100% 100%, 24px 24px !important;
            background-repeat: no-repeat, no-repeat, no-repeat, repeat !important;
            background-attachment: fixed !important;
        }

        /* Presentation Stage: Perfect vertical centering, zero cut-off, and docking clearance */
        #presentationStage {
            height: 100vh;
            max-height: 100vh;
            width: 100vw;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            z-index: 10;
            box-sizing: border-box;
            padding-top: 48px;
            padding-bottom: 76px;
            padding-left: 1.5rem;
            padding-right: 1.5rem;
            overflow: hidden;
        }

        .slide-view {
            width: 100%;
            max-width: 1440px;
            height: 100%;
            max-height: 100%;
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transform: scale(0.985);
            transition: opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1), transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
            margin: auto;
        }
        .slide-view > :first-child {
            flex-shrink: 0;
        }
        .slide-view::-webkit-scrollbar {
            display: none;
        }

        .slide-view.active-slide {
            display: flex;
            opacity: 1;
            transform: scale(1);
        }

        /* Glass and SaaS Card Standards (Subtle Gradient Wash, Modern Blur & Lessened Opacity) */
        .glass-card,
        .below-section-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.68) 0%, rgba(240, 253, 244, 0.40) 60%, rgba(254, 252, 232, 0.30) 100%) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border: none !important;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.05), inset 0 1px 0 0 rgba(255, 255, 255, 0.5) !important;
            border-radius: 1.25rem !important;
            transition: all 0.25s ease;
        }
        .dark .glass-card,
        .dark .below-section-card {
            background: linear-gradient(145deg, rgba(24, 24, 27, 0.68) 0%, rgba(20, 83, 45, 0.16) 60%, rgba(113, 63, 18, 0.12) 100%) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border: none !important;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.35), inset 0 1px 0 0 rgba(255, 255, 255, 0.05) !important;
        }

        /* Modern Section Cards: Subtle Visible Gradient Wash, Frosted Blur Effect */
        .saas-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.72) 0%, rgba(240, 253, 244, 0.45) 50%, rgba(254, 252, 232, 0.35) 100%) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border: none !important;
            border-radius: 1.25rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.04), inset 0 1px 0 0 rgba(255, 255, 255, 0.6) !important;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .dark .saas-card {
            background: linear-gradient(145deg, rgba(24, 24, 27, 0.72) 0%, rgba(20, 83, 45, 0.18) 50%, rgba(113, 63, 18, 0.14) 100%) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            border: none !important;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.30), inset 0 1px 0 0 rgba(255, 255, 255, 0.05) !important;
        }
        .saas-card:hover {
            transform: translateY(-2px);
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

        /* Action Glow */
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

        /* Auto-hide Floating Controls */
        #audienceDock, #navChevrons, #topWatermark {
            transition: opacity 0.35s ease, transform 0.35s ease;
        }
        body.idle-cursor #audienceDock {
            opacity: 0 !important;
            transform: translate(-50%, 15px) !important;
            pointer-events: none !important;
        }
        body.idle-cursor #navChevrons {
            opacity: 0 !important;
            pointer-events: none !important;
        }
        body.idle-cursor #topWatermark {
            opacity: 0.3 !important;
        }

        /* Subtle Pulse for Active Signals */
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
</head>

<body class="stage-bg text-slate-800 dark:text-zinc-100 flex flex-col justify-center items-center selection:bg-[#15803d] selection:text-white">

    <!-- Top Ambient Modern Animated Gradient Waves (Centered, Soft Lessened Fluid Green & Yellow Ocean Wave) -->
    <div class="fixed top-0 left-0 right-0 h-72 sm:h-96 pointer-events-none z-0 overflow-hidden flex items-start justify-center" aria-hidden="true" style="mask-image: radial-gradient(ellipse 60% 80% at 50% 15%, black 30%, transparent 80%); -webkit-mask-image: radial-gradient(ellipse 60% 80% at 50% 15%, black 30%, transparent 80%);">
        <!-- Diffuse Backing Aura Centered -->
        <div class="absolute -top-16 left-1/2 -translate-x-1/2 w-[850px] h-[240px] bg-gradient-to-r from-emerald-500/10 via-green-500/8 to-amber-400/10 dark:from-emerald-500/14 dark:via-green-600/12 dark:to-yellow-500/12 blur-3xl rounded-full"></div>

        <!-- Wave Canvas with Organic Soft Blur -->
        <div class="relative w-full h-full filter blur-[16px] opacity-70 dark:opacity-65">
            <!-- Wave 1: Flowing Emerald Green Wave (Soft Lessened Tint) -->
            <svg class="absolute top-0 left-0 w-[200%] h-full animate-wave-1" viewBox="0 0 2880 240" preserveAspectRatio="none" fill="none">
                <defs>
                    <linearGradient id="waveGradGreenAud" x1="0%" y1="0%" x2="100%" y2="80%">
                        <stop offset="0%" stop-color="#15803d" stop-opacity="0.18" />
                        <stop offset="30%" stop-color="#22c55e" stop-opacity="0.14" />
                        <stop offset="65%" stop-color="#4ade80" stop-opacity="0.08" />
                        <stop offset="100%" stop-color="#16a34a" stop-opacity="0.16" />
                    </linearGradient>
                </defs>
                <path d="M 0 0 L 0 95 C 180 145, 360 45, 540 85 C 720 125, 900 170, 1080 115 C 1260 60, 1350 145, 1440 95 C 1620 145, 1800 45, 1980 85 C 2160 125, 2340 170, 2520 115 C 2700 60, 2790 145, 2880 95 L 2880 0 Z" fill="url(#waveGradGreenAud)" />
            </svg>

            <!-- Wave 2: Flowing Warm Golden Yellow / Amber Wave (Soft Lessened Tint) -->
            <svg class="absolute top-0 left-0 w-[200%] h-full animate-wave-2" viewBox="0 0 2880 240" preserveAspectRatio="none" fill="none">
                <defs>
                    <linearGradient id="waveGradYellowAud" x1="100%" y1="0%" x2="0%" y2="90%">
                        <stop offset="0%" stop-color="#facc15" stop-opacity="0.16" />
                        <stop offset="40%" stop-color="#eab308" stop-opacity="0.12" />
                        <stop offset="75%" stop-color="#ca8a04" stop-opacity="0.07" />
                        <stop offset="100%" stop-color="#fde047" stop-opacity="0.14" />
                    </linearGradient>
                </defs>
                <path d="M 0 0 L 0 70 C 220 20, 440 145, 720 85 C 1000 25, 1220 135, 1440 70 C 1660 20, 1880 145, 2160 85 C 2440 25, 2660 135, 2880 70 L 2880 0 Z" fill="url(#waveGradYellowAud)" />
            </svg>

            <!-- Wave 3: Undulating Mixed Crest Ripple (Soft Lessened Tint) -->
            <svg class="absolute top-0 left-0 w-[200%] h-full animate-wave-3" viewBox="0 0 2880 240" preserveAspectRatio="none" fill="none">
                <defs>
                    <linearGradient id="waveGradMixAud" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#22c55e" stop-opacity="0.11" />
                        <stop offset="50%" stop-color="#eab308" stop-opacity="0.09" />
                        <stop offset="100%" stop-color="#15803d" stop-opacity="0.11" />
                    </linearGradient>
                </defs>
                <path d="M 0 0 L 0 50 C 360 110, 720 15, 1080 75 C 1260 110, 1380 30, 1440 50 C 1800 110, 2160 15, 2520 75 C 2700 110, 2820 30, 2880 50 L 2880 0 Z" fill="url(#waveGradMixAud)" />
            </svg>
        </div>
    </div>

    <!-- TOP PROGRESS BAR (Keynote Style Progress Telemetry) -->
    <div class="fixed top-0 left-0 right-0 h-1 bg-slate-200/50 dark:bg-zinc-800/60 z-50">
        <div id="deckProgressBar" class="h-full bg-gradient-to-r from-[#16a34a] to-[#15803d] transition-all duration-300" style="width: 12.5%;"></div>
    </div>

    <!-- TOP BRAND WATERMARK (Discreet Conference Header) -->
    <header id="topWatermark" class="fixed top-2.5 left-4 right-4 z-40 flex items-center justify-between pointer-events-none transition-opacity duration-300">
        <!-- Left: Logo & Title (Hidden on Slide 1 for maximum impact) -->
        <div id="topBrandPill" class="flex items-center gap-2.5 px-3 py-1.5 rounded-full bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md border border-slate-200/70 dark:border-zinc-800/80 shadow-sm opacity-0 transition-opacity duration-300">
            <img src="tala_logo_transparent.png" alt="TALA" class="w-5 h-5 object-contain">
            <span class="text-xs font-black tracking-tight text-slate-900 dark:text-white">TALA</span>
            <span class="w-1 h-1 rounded-full bg-slate-300 dark:bg-zinc-700"></span>
            <span class="text-[11px] font-bold text-[#15803d] dark:text-[#16a34a]" id="topSlideTitle">Overview</span>
        </div>

        <!-- Right: Slide Counter Badge -->
        <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md border border-slate-200/70 dark:border-zinc-800/80 shadow-sm text-xs font-mono font-bold text-slate-600 dark:text-zinc-300">
            <span class="w-2 h-2 rounded-full bg-[#15803d] live-pulse"></span>
            <span id="topSlideCounter">SLIDE 01 / 08</span>
        </div>
    </header>

    <!-- FLOATING SIDE CHEVRONS (Hover edge navigation) -->
    <div id="navChevrons" class="fixed inset-y-0 left-0 right-0 pointer-events-none z-30 flex items-center justify-between px-3">
        <button type="button" onclick="prevSlide()" class="pointer-events-auto w-10 h-10 rounded-2xl bg-white/70 dark:bg-zinc-900/70 hover:bg-white dark:hover:bg-zinc-900 border border-slate-200/60 dark:border-zinc-800/80 text-slate-700 dark:text-zinc-300 shadow-lg flex items-center justify-center transition-all hover:scale-110 opacity-40 hover:opacity-100" title="Previous Slide (Arrow Left)">
            <i class="ph-bold ph-caret-left text-lg"></i>
        </button>
        <button type="button" onclick="nextSlide()" class="pointer-events-auto w-10 h-10 rounded-2xl bg-white/70 dark:bg-zinc-900/70 hover:bg-white dark:hover:bg-zinc-900 border border-slate-200/60 dark:border-zinc-800/80 text-slate-700 dark:text-zinc-300 shadow-lg flex items-center justify-center transition-all hover:scale-110 opacity-40 hover:opacity-100" title="Next Slide (Arrow Right / Space)">
            <i class="ph-bold ph-caret-right text-lg"></i>
        </button>
    </div>

    <!-- MAIN PRESENTATION STAGE -->
    <main id="presentationStage" onclick="handleStageClick(event)">

        <!-- ========================================== -->
        <!-- SLIDE 1: TITLE - TALA                      -->
        <!-- ========================================== -->
        <section class="slide-view active-slide flex items-center justify-center w-full h-full text-center" id="slide-1" data-title="TALA Overview">
            <div class="w-full max-w-4xl mx-auto flex flex-col items-center justify-center text-center my-auto p-2 sm:p-4">
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
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 sm:gap-3 text-left">
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
        <!-- SLIDE 2: REPUBLIC ACTS IN GOVERNMENT (NEW) -->
        <!-- ========================================== -->
        <section class="slide-view" id="slide-2" data-title="Republic Acts in Government">
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <!-- Centered Header (No Top Pill Title) -->
                <div class="mb-3 sm:mb-5 text-center max-w-3xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        Legal Mandates & Republic Acts
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1 leading-relaxed">
                        Statutory foundations of Philippine government empowering youth fiscal autonomy, mandatory reporting, and transparency.
                    </p>
                </div>

                <!-- 4 Republic Acts Section Cards (Zero Stroke Lines) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 w-full">
                    
                    <!-- Card 1: RA 10742 -->
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[200px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-2 text-center">
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
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[200px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-2 text-center">
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
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[200px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-2 text-center">
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
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[200px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-2 text-center">
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

                <!-- Footer Legal Mandate Highlight (Zero Stroke) -->
                <div class="glass-card px-4 py-2 rounded-xl flex items-center justify-between text-xs font-semibold text-slate-600 dark:text-zinc-300 mt-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-md bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] font-mono font-bold text-[10px]">STATUTORY</span>
                        <span>TALA serves as the official technological compliance instrument implementing <strong>RA 10742</strong>, <strong>RA 11768</strong>, and <strong>DILG-NYC Circulars</strong>.</span>
                    </div>
                    <span class="text-[#15803d] dark:text-[#16a34a] font-bold">100% Statutory Compliant</span>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- SLIDE 3: THE PROBLEM & THE SOLUTION        -->
        <!-- ========================================== -->
        <section class="slide-view" id="slide-3" data-title="Problem vs Solution">
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <!-- Centered Header (No Top Pill Title) -->
                <div class="mb-3 sm:mb-5 text-center max-w-2xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        Why TALA Was Built
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1 leading-relaxed">
                        Transforming high-friction paper governance into an auditable digital web platform.
                    </p>
                </div>

                <!-- Two-Column Contrast Layout (Zero Stroke Lines) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 w-full">
                    
                    <!-- Left: Traditional Roadblocks -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between">
                        <div>
                            <!-- Paper Bottleneck Mockup -->
                            <div class="card-asset-grid h-28 sm:h-32 w-full rounded-2xl relative flex items-center justify-center p-3 overflow-hidden mb-3">
                                <div class="w-full max-w-[240px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-2">
                                    <div class="flex items-center justify-between pb-1.5 mb-1.5">
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
                                        <strong class="text-slate-900 dark:text-white">Delayed State Disclosures:</strong> Physical bulletin postings prone to paper loss and overdue filing.
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
                            <span class="text-[10px] font-bold text-slate-500 dark:text-zinc-400">Manual Process</span>
                        </div>
                    </div>

                    <!-- Right: The TALA Solution -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between">
                        <div>
                            <!-- TALA Cloud Ledger Mockup -->
                            <div class="card-asset-grid h-28 sm:h-32 w-full rounded-2xl relative flex items-center justify-center p-3 overflow-hidden mb-3">
                                <div class="w-full max-w-[240px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-2">
                                    <div class="flex items-center justify-between pb-1.5 mb-1.5">
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

                <!-- Footer Transformation Highlight (Below Section Card: Frosted Glass) -->
                <div class="glass-card px-4 py-2 rounded-xl flex items-center justify-between text-xs font-semibold text-slate-600 dark:text-zinc-300 mt-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-md bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] font-mono font-bold text-[10px]">PARADIGM</span>
                        <span>Eliminating 21-day reporting delays with real-time digital transparency across 46 barangays.</span>
                    </div>
                    <span class="text-[#15803d] dark:text-[#16a34a] font-bold">Paperless Shift</span>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- SLIDE 4: THE END-TO-END WORKFLOW           -->
        <!-- ========================================== -->
        <section class="slide-view" id="slide-4" data-title="System Workflow">
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <div class="mb-3 sm:mb-5 text-center max-w-2xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        The End-to-End Governance Workflow
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1 leading-relaxed">
                        How data moves seamlessly between Youth Constituents, SK Officials, MYDC, and the LGU.
                    </p>
                </div>

                <!-- 5 Stages Grid (Zero Stroke Lines) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-2.5 sm:gap-3 w-full">
                    
                    <!-- Stage 1 -->
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-24 sm:h-26 w-full rounded-xl relative flex items-center justify-center p-2 overflow-hidden mb-2">
                                <div class="w-full max-w-[170px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-2">
                                    <div class="flex items-center justify-between mb-1 pb-1">
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
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white mb-0.5">Onboarding & ID</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-tight">Digital youth profiling with instant QR ID generation.</p>
                        </div>
                        <div class="pt-2 mt-2 text-[10px] text-slate-600 dark:text-zinc-400 space-y-0.5">
                            <div><strong class="text-slate-800 dark:text-zinc-200">Actors:</strong> Youth & Secretary</div>
                            <div><strong class="text-[#15803d] dark:text-[#16a34a]">Output:</strong> Digital QR ID</div>
                        </div>
                    </div>

                    <!-- Stage 2 -->
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-24 sm:h-26 w-full rounded-xl relative flex flex-col items-center justify-center gap-1 p-2 overflow-hidden mb-2">
                                <div class="w-full max-w-[170px] bg-white dark:bg-zinc-900 rounded-lg px-2.5 py-0.5 shadow-sm flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-slate-800 dark:text-zinc-200 truncate">₱46.8M Budget</span>
                                    <span class="w-2.5 h-2.5 rounded-full border-2 border-[#15803d] border-t-transparent animate-spin flex-shrink-0"></span>
                                </div>
                                <div class="w-full max-w-[170px] bg-[#15803d] text-white rounded-lg px-2.5 py-0.5 shadow-sm flex items-center justify-between text-[10px] font-bold">
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
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white mb-0.5">AYIP Planning</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-tight">Annual ₱46M+ fund allocation and project registry.</p>
                        </div>
                        <div class="pt-2 mt-2 text-[10px] text-slate-600 dark:text-zinc-400 space-y-0.5">
                            <div><strong class="text-slate-800 dark:text-zinc-200">Actors:</strong> Council & LYDO</div>
                            <div><strong class="text-[#15803d] dark:text-[#16a34a]">Output:</strong> Budget Registry</div>
                        </div>
                    </div>

                    <!-- Stage 3 -->
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-24 sm:h-26 w-full rounded-xl relative flex items-center justify-center p-2 overflow-hidden mb-2">
                                <div class="w-full max-w-[170px] flex items-center justify-between px-1 relative z-10">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d]"></span>
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-zinc-600"></span>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-slate-200 dark:bg-zinc-700 mx-1"></div>
                                    <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-[#15803d] to-[#166534] text-white flex items-center justify-center shadow-md shadow-green-700/25 flex-shrink-0">
                                        <i class="ph-bold ph-receipt text-sm"></i>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-slate-200 dark:bg-zinc-700 mx-1"></div>
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d]"></span>
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-zinc-600"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[9px] font-mono font-black uppercase px-1.5 py-0.5 rounded bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a]">Stage 03</span>
                                <span class="text-[10px] text-slate-400 font-mono">03/05</span>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white mb-0.5">Disbursement</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-tight">Check vouchers, invoice uploads & COA coding.</p>
                        </div>
                        <div class="pt-2 mt-2 text-[10px] text-slate-600 dark:text-zinc-400 space-y-0.5">
                            <div><strong class="text-slate-800 dark:text-zinc-200">Actors:</strong> Treasurer & Bank</div>
                            <div><strong class="text-[#15803d] dark:text-[#16a34a]">Output:</strong> Liquidated Check</div>
                        </div>
                    </div>

                    <!-- Stage 4 -->
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-24 sm:h-26 w-full rounded-xl relative flex items-center justify-center p-2 overflow-hidden mb-2">
                                <div class="w-full max-w-[170px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-2">
                                    <div class="flex items-center gap-1 mb-1 px-0.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d]"></span>
                                    </div>
                                    <div class="bg-slate-50 dark:bg-zinc-800/80 rounded-lg px-2 py-0.5 flex items-center justify-between text-[10px]">
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
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white mb-0.5">FDP Disclosure</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-tight">1-click statutory publishing with in-browser PDF.</p>
                        </div>
                        <div class="pt-2 mt-2 text-[10px] text-slate-600 dark:text-zinc-400 space-y-0.5">
                            <div><strong class="text-slate-800 dark:text-zinc-200">Actors:</strong> Public & COA</div>
                            <div><strong class="text-[#15803d] dark:text-[#16a34a]">Output:</strong> Transparency Seal</div>
                        </div>
                    </div>

                    <!-- Stage 5 -->
                    <div class="saas-card p-3 sm:p-3.5 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-24 sm:h-26 w-full rounded-xl relative flex items-center justify-center p-2 overflow-hidden mb-2">
                                <div class="relative z-10 flex flex-col items-center justify-center">
                                    <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-[#16a34a] to-[#15803d] text-white flex items-center justify-center shadow-md shadow-green-700/25 mb-1">
                                        <i class="ph-bold ph-shield-check text-base"></i>
                                    </div>
                                    <div class="bg-white/95 dark:bg-zinc-900/95 rounded-full px-2 py-0.5 shadow-sm text-[9px] font-bold text-slate-700 dark:text-zinc-200 flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d] animate-ping"></span>
                                        <span>46-Brgy Compliance</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[9px] font-mono font-black uppercase px-1.5 py-0.5 rounded bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a]">Stage 05</span>
                                <span class="text-[10px] text-slate-400 font-mono">05/05</span>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white mb-0.5">Executive Review</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-tight">46-barangay ranking badges & municipal feedback loop.</p>
                        </div>
                        <div class="pt-2 mt-2 text-[10px] text-slate-600 dark:text-zinc-400 space-y-0.5">
                            <div><strong class="text-slate-800 dark:text-zinc-200">Actors:</strong> MYDC & COA</div>
                            <div><strong class="text-[#15803d] dark:text-[#16a34a]">Output:</strong> Audit Ledger</div>
                        </div>
                    </div>

                </div>

                <!-- Footer Pipeline Banner -->
                <div class="glass-card px-4 py-2 rounded-xl flex items-center justify-between text-xs font-semibold text-slate-600 dark:text-zinc-300 mt-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-md bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] font-mono font-bold text-[10px]">PIPELINE</span>
                        <span>Continuous loop connecting <strong>46 Barangays</strong>, <strong>MYDC Federation</strong>, and the <strong>Municipal Mayor's Office</strong>.</span>
                    </div>
                    <span class="text-[#15803d] dark:text-[#16a34a] font-bold">100% Digital Workflow</span>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- SLIDE 5: FISCAL PIPELINE ENGINES           -->
        <!-- ========================================== -->
        <section class="slide-view" id="slide-5" data-title="Fiscal Engines">
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <div class="mb-3 sm:mb-5 text-center max-w-2xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        Fiscal Transparency & Budget Monitoring
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1 leading-relaxed">
                        Real-time municipal fund telemetry and complete statutory compliance across all 46 barangays.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4 w-full">
                    
                    <!-- Card 1: FDP Portal -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-32 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-3">
                                <!-- Miniature FDP Live Document Window -->
                                <div class="w-full max-w-[240px] bg-white/95 dark:bg-zinc-900/95 rounded-xl shadow-lg p-2.5">
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
                                    <div class="bg-slate-50 dark:bg-zinc-800/80 rounded-xl p-2 flex items-center gap-2 mb-1">
                                        <div class="w-7 h-7 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0">
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
                            <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">Full Disclosure (FDP) Portal</h3>
                            <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                Centralized quarterly statutory publishing with in-browser PDF previews complying directly with DILG circulars.
                            </p>
                        </div>
                        <div class="pt-3 mt-3 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">DILG COMPLIANT</span>
                            <span class="font-bold text-slate-500 dark:text-zinc-400">Quarterly Q1-Q4</span>
                        </div>
                    </div>

                    <!-- Card 2: Budget Console -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-32 w-full rounded-2xl relative flex flex-col items-center justify-center gap-2 p-3 overflow-hidden mb-3">
                                <div class="w-full max-w-[210px] bg-white dark:bg-zinc-900 rounded-xl px-3 py-1.5 shadow-sm flex items-center justify-between">
                                    <span class="text-xs font-semibold text-slate-800 dark:text-zinc-200 truncate">₱46M+ AYIP Allocation</span>
                                    <span class="w-3 h-3 rounded-full border-2 border-[#15803d] border-t-transparent animate-spin flex-shrink-0"></span>
                                </div>
                                <div class="w-full max-w-[210px] bg-[#15803d] text-white rounded-xl px-3 py-1.5 shadow-md flex items-center justify-between text-[11px] font-bold">
                                    <div class="flex items-center gap-1.5 truncate">
                                        <i class="ph-bold ph-shield-check"></i>
                                        <span class="truncate">Deficit Guard Active</span>
                                    </div>
                                    <span class="text-[10px] opacity-90 font-mono">10% CAP</span>
                                </div>
                            </div>
                            <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">Real-Time Budget Console</h3>
                            <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                Unified fund ledger tracking all 46 barangay youth budgets with automated alerts preventing deficit spending.
                            </p>
                        </div>
                        <div class="pt-3 mt-3 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">TELEMETRY</span>
                            <span class="font-bold text-slate-500 dark:text-zinc-400">Live 46 Brgys</span>
                        </div>
                    </div>

                    <!-- Card 3: Check Voucher & Liquidation -->
                    <div class="saas-card p-4 sm:p-5 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-32 w-full rounded-2xl relative flex items-center justify-center p-3 overflow-hidden mb-3">
                                <div class="w-full flex items-center justify-between px-3 relative z-10">
                                    <div class="flex flex-col items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-zinc-600"></span>
                                        <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                                        <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-zinc-600"></span>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-slate-200 dark:bg-zinc-700 mx-2"></div>
                                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-[#15803d] to-[#166534] text-white flex items-center justify-center shadow-lg shadow-green-700/25 flex-shrink-0">
                                        <i class="ph-bold ph-receipt text-lg"></i>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-slate-200 dark:bg-zinc-700 mx-2"></div>
                                    <div class="flex flex-col items-center gap-1.5">
                                        <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-zinc-600"></span>
                                        <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                                        <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-zinc-600"></span>
                                    </div>
                                </div>
                            </div>
                            <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">Check Voucher & Liquidation</h3>
                            <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed mt-1.5">
                                Standardized COA expense codes with photo attachments to cut liquidation turnaround from weeks to minutes.
                            </p>
                        </div>
                        <div class="pt-3 mt-3 flex items-center justify-between text-xs">
                            <span class="text-[10px] font-mono font-bold text-[#15803d] dark:text-[#16a34a]">COA LIQUIDATION</span>
                            <span class="font-bold text-slate-500 dark:text-zinc-400">85% Speedup</span>
                        </div>
                    </div>

                </div>

                <!-- Footer Live Telemetry Highlight (Below Section Card: Frosted Glass) -->
                <div class="glass-card px-4 py-2 rounded-xl flex items-center justify-between text-xs font-semibold text-slate-600 dark:text-zinc-300 mt-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-md bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] font-mono font-bold text-[10px]">LIVE STAT</span>
                        <span>Over <strong><?= (isset($total_disclosures) && $total_disclosures) ? number_format($total_disclosures) : '184+' ?></strong> public disclosure documents indexed with zero missing quarterly filings.</span>
                    </div>
                    <span class="text-[#15803d] dark:text-[#16a34a] font-bold">100% Audit Readiness</span>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- SLIDE 6: GOVERNANCE BADGES & PARTICIPATION -->
        <!-- ========================================== -->
        <section class="slide-view" id="slide-6" data-title="Governance & MYDC">
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <div class="mb-3 sm:mb-5 text-center max-w-2xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        Governance Badging, MYDC & Assembly Records
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1 leading-relaxed">
                        Dynamic compliance leaderboard, youth organization accreditation, and official assembly records.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 w-full">
                    
                    <!-- Card 1: Badges -->
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[210px] bg-white dark:bg-zinc-900 rounded-xl shadow-md p-2 relative z-10">
                                    <div class="flex items-center justify-between pb-1 mb-1.5">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-zinc-200">
                                            <i class="ph-bold ph-shield-check text-[#15803d] dark:text-[#16a34a] text-sm"></i>
                                            <span>Governance Badges</span>
                                        </div>
                                        <span class="text-[9px] font-mono font-bold text-[#15803d]">46 BRGYS</span>
                                    </div>
                                    <div class="grid grid-cols-3 gap-1 text-center">
                                        <div class="p-1 rounded-lg bg-[#15803d]/10 flex flex-col items-center">
                                            <div class="w-5 h-5 rounded-full bg-gradient-to-br from-[#16a34a] to-[#15803d] text-white flex items-center justify-center shadow-sm text-[10px] mb-0.5">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                            <span class="text-[8px] font-black text-[#15803d]">GOLD</span>
                                            <span class="text-[7px] text-slate-500 font-mono">18 Brgys</span>
                                        </div>
                                        <div class="p-1 rounded-lg bg-emerald-500/10 flex flex-col items-center">
                                            <div class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center shadow-sm text-[10px] mb-0.5">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                            <span class="text-[8px] font-black text-emerald-700 dark:text-emerald-400">SILVER</span>
                                            <span class="text-[7px] text-slate-500 font-mono">22 Brgys</span>
                                        </div>
                                        <div class="p-1 rounded-lg bg-teal-500/10 flex flex-col items-center">
                                            <div class="w-5 h-5 rounded-full bg-teal-700 text-white flex items-center justify-center shadow-sm text-[10px] mb-0.5">
                                                <i class="ph-fill ph-seal-check"></i>
                                            </div>
                                            <span class="text-[8px] font-black text-teal-700 dark:text-teal-400">BRONZE</span>
                                            <span class="text-[7px] text-slate-500 font-mono">6 Brgys</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">Barangay Governance Badges</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed mt-1">
                                Automated 46-barangay scoring matrix awarding Gold, Silver, and Bronze seals for timely compliance.
                            </p>
                        </div>
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-[#15803d]">LEADERBOARD</span>
                            <span class="text-[10px] font-bold text-slate-500">Tiered Seals</span>
                        </div>
                    </div>

                    <!-- Card 2: MYDC -->
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex flex-col items-center justify-center gap-1.5 p-2 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[190px] bg-white dark:bg-zinc-900 rounded-lg px-2.5 py-1 shadow-sm flex items-center justify-between">
                                    <span class="text-[11px] font-semibold text-slate-800 dark:text-zinc-200 truncate">5 Sector Roster</span>
                                    <span class="w-3 h-3 rounded-full border-2 border-[#15803d] border-t-transparent animate-spin flex-shrink-0"></span>
                                </div>
                                <div class="w-full max-w-[190px] bg-[#15803d] text-white rounded-lg px-2.5 py-1 shadow-md flex items-center justify-between text-[10px] font-bold">
                                    <div class="flex items-center gap-1 truncate">
                                        <i class="ph-bold ph-identification-badge"></i>
                                        <span class="truncate">YORP Certified</span>
                                    </div>
                                    <span class="text-[9px] opacity-90 font-mono">ACTIVE</span>
                                </div>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">MYDC & YORP Accreditation</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed mt-1">
                                Central registry for youth organizations and sectoral council leaders with verified tenure histories.
                            </p>
                        </div>
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-[#15803d]">COUNCIL ROSTER</span>
                            <span class="text-[10px] font-bold text-slate-500">Accredited</span>
                        </div>
                    </div>

                    <!-- Card 3: Resolutions -->
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[190px] bg-white dark:bg-zinc-900 rounded-xl shadow-md p-2 relative z-10">
                                    <div class="flex items-center justify-between mb-1 px-0.5 pb-1">
                                        <div class="flex items-center gap-1 text-[11px] font-bold text-slate-800 dark:text-zinc-200">
                                            <i class="ph-bold ph-scroll text-[#15803d]"></i>
                                            <span>Assembly Record</span>
                                        </div>
                                        <span class="text-[8px] font-mono font-bold text-[#15803d] bg-[#15803d]/10 px-1 py-0.5 rounded">PASSED</span>
                                    </div>
                                    <div class="bg-slate-50 dark:bg-zinc-800/80 rounded-lg px-2 py-0.5 flex items-center justify-between text-[9px]">
                                        <div class="flex items-center gap-1 text-slate-700 dark:text-zinc-200 font-semibold truncate">
                                            <i class="ph-bold ph-file-text text-[#15803d]"></i>
                                            <span class="truncate">Res. No. 2026-042</span>
                                        </div>
                                        <span class="text-[8px] text-[#15803d] font-bold">Quorum OK</span>
                                    </div>
                                </div>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">Minutes & Assembly Resolutions</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed mt-1">
                                Official digital repository for SK resolutions, ordinances, and meeting minutes with roll call verification.
                            </p>
                        </div>
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-[#15803d]">RESOLUTIONS</span>
                            <span class="text-[10px] font-bold text-slate-500">Legal Audit</span>
                        </div>
                    </div>

                    <!-- Card 4: Tickets -->
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between">
                        <div>
                            <!-- Top UI Asset: Citizen Helpdesk Ticket Mockup -->
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[215px] bg-white dark:bg-zinc-900 rounded-xl shadow-md p-2.5 relative z-10">
                                    <!-- Ticket Header: ID & Status Pill -->
                                    <div class="flex items-center justify-between pb-1 mb-1.5">
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
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">Civic Feedback & Tickets</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed mt-1">
                                Public youth issue ticketing with tracking numbers, status telemetry, and official council replies.
                            </p>
                        </div>
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-[#15803d]">HELPDESK</span>
                            <span class="text-[10px] font-bold text-slate-500">Public Channel</span>
                        </div>
                    </div>

                </div>

                <!-- Footer Civic Core Highlight (Below Section Card: Frosted Glass) -->
                <div class="glass-card px-4 py-2 rounded-xl flex items-center justify-between text-xs font-semibold text-slate-600 dark:text-zinc-300 mt-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-md bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] font-mono font-bold text-[10px]">CIVIC CORE</span>
                        <span>Unified command center, youth assembly records, and helpdesk SLA ticketing across 46 barangays.</span>
                    </div>
                    <span class="text-[#15803d] dark:text-[#16a34a] font-bold">46 Barangay Network</span>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- SLIDE 7: TECHNICAL ARCHITECTURE & STACK    -->
        <!-- ========================================== -->
        <section class="slide-view" id="slide-7" data-title="Architecture">
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <div class="mb-3 sm:mb-5 text-center max-w-2xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        System Architecture & Tech Foundation
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1 leading-relaxed">
                        Engineered for high performance, zero external framework bloat, and enterprise security.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 w-full">
                    
                    <!-- Stack 1: PHP -->
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[190px] bg-white dark:bg-zinc-900 rounded-xl shadow-md p-2">
                                    <div class="flex items-center gap-1.5 mb-1.5 px-0.5">
                                        <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                        <span class="w-2 h-2 rounded-full bg-[#15803d]"></span>
                                    </div>
                                    <div class="bg-slate-50 dark:bg-zinc-800/80 rounded-lg px-2 py-1 flex items-center justify-between text-[10px]">
                                        <div class="flex items-center gap-1 text-slate-700 dark:text-zinc-200 font-semibold truncate">
                                            <i class="ph-bold ph-cpu text-[#15803d]"></i>
                                            <span class="truncate">PHP 8.2 • PDO</span>
                                        </div>
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d] animate-ping"></span>
                                    </div>
                                </div>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">Backend Runtime</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed mt-1">
                                Lightweight modular PHP 8.2 executing prepared PDO statements with route middleware guards.
                            </p>
                        </div>
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-[#15803d]">PHP &bull; PDO</span>
                            <span class="text-[10px] font-bold text-slate-500">Zero Bloat</span>
                        </div>
                    </div>

                    <!-- Stack 2: MySQL -->
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex flex-col items-center justify-center gap-1.5 p-2 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[190px] bg-white dark:bg-zinc-900 rounded-xl px-2.5 py-1 shadow-sm flex items-center justify-between">
                                    <span class="text-[11px] font-semibold text-slate-800 dark:text-zinc-200 truncate">MySQL 8.0 InnoDB</span>
                                    <span class="w-3 h-3 rounded-full border-2 border-[#15803d] border-t-transparent animate-spin flex-shrink-0"></span>
                                </div>
                                <div class="w-full max-w-[190px] bg-[#15803d] text-white rounded-xl px-2.5 py-1 shadow-md flex items-center justify-between text-[10px] font-bold">
                                    <div class="flex items-center gap-1 truncate">
                                        <i class="ph-bold ph-database"></i>
                                        <span class="truncate">ACID Ledger Safe</span>
                                    </div>
                                    <span class="text-[9px] opacity-90 font-mono">46 BRGYS</span>
                                </div>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">Relational Data Layer</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed mt-1">
                                ACID-compliant schemas with indexed queries and strict cascade rules ensuring financial integrity.
                            </p>
                        </div>
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-[#15803d]">INNODB</span>
                            <span class="text-[10px] font-bold text-slate-500">Strict Cascades</span>
                        </div>
                    </div>

                    <!-- Stack 3: Tailwind -->
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-2.5">
                                <div class="relative z-10 flex flex-col items-center justify-center">
                                    <span class="text-xl sm:text-2xl font-black tracking-tight text-[#15803d] dark:text-[#16a34a] font-sans">
                                        TAILWIND 3
                                    </span>
                                    <div class="mt-1 bg-white/95 dark:bg-zinc-900/95 rounded-full px-2 py-0.5 shadow-sm text-[9px] font-bold text-slate-700 dark:text-zinc-200 flex items-center gap-1">
                                        <i class="ph-bold ph-palette text-[#15803d]"></i>
                                        <span>Glass-Card UI</span>
                                    </div>
                                </div>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">TALA UI Architecture</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed mt-1">
                                Modern flat glass-card styling with Phosphor icons and instant Dark/Light theme persistence.
                            </p>
                        </div>
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-[#15803d]">RESPONSIVE</span>
                            <span class="text-[10px] font-bold text-slate-500">4K to 360px</span>
                        </div>
                    </div>

                    <!-- Stack 4: Security -->
                    <div class="saas-card p-3.5 sm:p-4 flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-28 sm:h-30 w-full rounded-2xl relative flex items-center justify-center p-2.5 overflow-hidden mb-2.5">
                                <div class="w-full flex items-center justify-between px-2 relative z-10">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-zinc-600"></span>
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d]"></span>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-slate-200 dark:bg-zinc-700 mx-1.5"></div>
                                    <div class="w-9 h-9 rounded-2xl bg-gradient-to-br from-[#15803d] to-[#166534] text-white flex items-center justify-center shadow-lg shadow-green-700/25 flex-shrink-0">
                                        <i class="ph-bold ph-shield-check text-base"></i>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-slate-200 dark:bg-zinc-700 mx-1.5"></div>
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-zinc-600"></span>
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d]"></span>
                                    </div>
                                </div>
                            </div>
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">Enterprise Security</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 leading-relaxed mt-1">
                                Strict role-based RBAC, tokenized CSRF defense, upload mime-quarantine, and full admin audit logging.
                            </p>
                        </div>
                        <div class="pt-2 mt-2 flex items-center justify-between text-xs">
                            <span class="text-[9px] font-mono font-bold text-[#15803d]">SECURITY</span>
                            <span class="text-[10px] font-bold text-slate-500">RBAC & CSRF</span>
                        </div>
                    </div>

                </div>

                <!-- Footer Architecture Highlight -->
                <div class="glass-card px-4 py-2 rounded-xl flex items-center justify-between text-xs font-semibold text-slate-600 dark:text-zinc-300 mt-3">
                    <div class="flex items-center gap-1.5">
                        <i class="ph-fill ph-check-circle text-[#15803d] dark:text-[#16a34a] text-sm"></i>
                        <span>TALA is self-hosted on local LGU infrastructure with offline resiliency and cloud-backup readiness.</span>
                    </div>
                    <span class="text-[#15803d] dark:text-[#16a34a] font-bold">Local-First Architecture</span>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- SLIDE 8: IMPACT & CONCLUSION               -->
        <!-- ========================================== -->
        <section class="slide-view" id="slide-8" data-title="Impact & Roadmap">
            <div class="w-full max-w-7xl mx-auto flex flex-col justify-center my-auto">
                <div class="mb-3 sm:mb-4 text-center max-w-2xl mx-auto">
                    <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                        Transforming Tumauini Youth Governance
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-zinc-400 mt-1 leading-relaxed">
                        Measurable achievements, upcoming innovations, and live demonstration launchpad.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 w-full">
                    
                    <!-- Left: Impact Metrics -->
                    <div class="lg:col-span-7 saas-card p-3.5 sm:p-4 rounded-2xl flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-20 sm:h-24 w-full rounded-2xl relative flex items-center justify-center p-2 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[260px] bg-white dark:bg-zinc-900 rounded-xl shadow-sm p-1.5">
                                    <div class="flex items-center justify-between pb-1 mb-1">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-slate-800 dark:text-zinc-200">
                                            <i class="ph-bold ph-chart-line-up text-[#15803d]"></i>
                                            <span>Municipal Impact Telemetry</span>
                                        </div>
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#15803d] animate-ping"></span>
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

                            <div class="grid grid-cols-2 gap-2 mb-1.5">
                                <div class="p-2 rounded-xl bg-[#15803d]/5">
                                    <span class="text-xl font-black text-[#15803d] dark:text-[#16a34a] block">100%</span>
                                    <span class="text-[10px] font-bold text-slate-800 dark:text-zinc-200">Barangay Coverage</span>
                                    <p class="text-[9px] text-slate-500">All 46 barangays unified.</p>
                                </div>
                                <div class="p-2 rounded-xl bg-[#15803d]/5">
                                    <span class="text-xl font-black text-slate-900 dark:text-white block">85%</span>
                                    <span class="text-[10px] font-bold text-slate-800 dark:text-zinc-200">Reporting Speedup</span>
                                    <p class="text-[9px] text-slate-500">Liquidations in minutes.</p>
                                </div>
                                <div class="p-2 rounded-xl bg-[#15803d]/5">
                                    <span class="text-xl font-black text-amber-500 block">₱46M+</span>
                                    <span class="text-[10px] font-bold text-slate-800 dark:text-zinc-200">Youth Fund Monitored</span>
                                    <p class="text-[9px] text-slate-500">Real-time municipal audit.</p>
                                </div>
                                <div class="p-2 rounded-xl bg-[#15803d]/5">
                                    <span class="text-xl font-black text-blue-600 block">24/7</span>
                                    <span class="text-[10px] font-bold text-slate-800 dark:text-zinc-200">Civic Transparency</span>
                                    <p class="text-[9px] text-slate-500">Open public inspection.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Future Roadmap -->
                    <div class="lg:col-span-5 saas-card p-3.5 sm:p-4 rounded-2xl flex flex-col justify-between">
                        <div>
                            <div class="card-asset-grid h-20 sm:h-24 w-full rounded-2xl relative flex items-center justify-center p-2 overflow-hidden mb-2.5">
                                <div class="w-full max-w-[260px] flex items-center justify-between px-2 relative z-10">
                                    <div class="flex flex-col items-center">
                                        <span class="w-6 h-6 rounded-lg bg-[#15803d]/15 text-[#15803d] flex items-center justify-center text-[10px] font-bold font-mono">Q1</span>
                                        <span class="text-[8px] font-bold text-slate-600 dark:text-zinc-400 mt-0.5">PWA</span>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-[#15803d]/30 mx-1.5"></div>
                                    <div class="flex flex-col items-center">
                                        <span class="w-6 h-6 rounded-lg bg-[#15803d]/15 text-[#15803d] flex items-center justify-center text-[10px] font-bold font-mono">Q2</span>
                                        <span class="text-[8px] font-bold text-slate-600 dark:text-zinc-400 mt-0.5">GIS MAP</span>
                                    </div>
                                    <div class="flex-1 h-0.5 bg-[#15803d]/30 mx-1.5"></div>
                                    <div class="flex flex-col items-center">
                                        <span class="w-6 h-6 rounded-lg bg-[#15803d]/15 text-[#15803d] flex items-center justify-center text-[10px] font-bold font-mono">Q3</span>
                                        <span class="text-[8px] font-bold text-slate-600 dark:text-zinc-400 mt-0.5">SMS</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 text-amber-500 font-bold text-xs uppercase tracking-wider mb-2">
                                <i class="ph-bold ph-rocket-launch text-sm"></i>
                                <span>Future Innovation Roadmap</span>
                            </div>

                            <div class="space-y-1.5 text-xs">
                                <div class="p-1.5 rounded-xl bg-slate-50 dark:bg-zinc-900/60 flex items-start gap-2">
                                    <span class="w-4 h-4 rounded bg-amber-500/10 text-amber-500 font-bold text-[8px] flex items-center justify-center mt-0.5">Q1</span>
                                    <div><strong class="text-slate-900 dark:text-white">Mobile PWA:</strong> Offline voucher drafting for remote barangays.</div>
                                </div>
                                <div class="p-1.5 rounded-xl bg-slate-50 dark:bg-zinc-900/60 flex items-start gap-2">
                                    <span class="w-4 h-4 rounded bg-[#15803d]/10 text-[#15803d] font-bold text-[8px] flex items-center justify-center mt-0.5">Q2</span>
                                    <div><strong class="text-slate-900 dark:text-white">GIS Mapping:</strong> Interactive map pin-pointing youth projects.</div>
                                </div>
                                <div class="p-1.5 rounded-xl bg-slate-50 dark:bg-zinc-900/60 flex items-start gap-2">
                                    <span class="w-4 h-4 rounded bg-blue-500/10 text-blue-600 font-bold text-[8px] flex items-center justify-center mt-0.5">Q3</span>
                                    <div><strong class="text-slate-900 dark:text-white">SMS Gateway:</strong> Alerts for assemblies and grants.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Launchpad -->
                <div class="saas-card rounded-2xl p-3 sm:p-3.5 flex flex-col md:flex-row items-center justify-between gap-3 mt-2.5 w-full">
                    <div class="flex items-center gap-3">
                        <img src="mascot.png" alt="TALA Mascot" class="w-9 h-9 object-contain hidden sm:block">
                        <div>
                            <h3 class="font-extrabold text-xs sm:text-sm text-slate-900 dark:text-white">Ready for Live System Evaluation</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400">Floor is open for questions, audit inspection, and live walkthrough.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="public_disclosure.php" target="_blank" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 text-xs font-bold text-slate-700 dark:text-zinc-200 flex items-center gap-1.5">
                            <i class="ph-bold ph-globe text-[#15803d]"></i> Public Portal
                        </a>
                        <a href="admin_budget.php" target="_blank" class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 text-xs font-bold text-slate-700 dark:text-zinc-200 flex items-center gap-1.5">
                            <i class="ph-bold ph-coins text-amber-500"></i> Budget Console
                        </a>
                        <a href="login.php" target="_blank" class="btn-yellow-glow-r px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] text-xs font-bold text-white flex items-center gap-1.5 shadow-sm">
                            <i class="ph-bold ph-lock-key relative z-10"></i> <span class="relative z-10">Login as Admin</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- ============================================================== -->
    <!-- AUTO-HIDING FLOATING BOTTOM DOCK                               -->
    <!-- ============================================================== -->
    <nav id="audienceDock" class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 max-w-xl w-[calc(100%-2rem)] px-3 pointer-events-auto">
        <div class="glass-card rounded-2xl p-2 flex items-center justify-between gap-2 shadow-2xl">
            
            <!-- Prev Slide -->
            <button type="button" onclick="prevSlide()" class="flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 text-xs font-bold transition-all" title="Previous Slide (Arrow Left)">
                <i class="ph-bold ph-caret-left text-sm"></i>
                <span class="hidden sm:inline">Prev</span>
            </button>

            <!-- 8 Slide Dots -->
            <div class="flex items-center gap-1.5 px-2">
                <?php for ($i = 1; $i <= 8; $i++): ?>
                    <button type="button" onclick="goToSlide(<?= $i ?>)" id="audDot-<?= $i ?>" class="w-2.5 h-2.5 rounded-full transition-all duration-300 <?= $i === 1 ? 'bg-[#15803d] dark:bg-[#16a34a] scale-125 ring-2 ring-[#15803d]/30' : 'bg-slate-300 dark:bg-zinc-700 hover:bg-slate-400' ?>" title="Slide <?= $i ?>">
                    </button>
                <?php endfor; ?>
            </div>

            <!-- Next Slide -->
            <button type="button" onclick="nextSlide()" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] text-white flex items-center gap-1 px-3 py-1.5 rounded-xl text-xs font-bold transition-all" title="Next Slide (Arrow Right / Space)">
                <span class="relative z-10 hidden sm:inline">Next</span>
                <i class="ph-bold ph-caret-right text-sm relative z-10"></i>
            </button>

            <!-- Divider -->
            <div class="h-4 w-px bg-slate-200 dark:bg-zinc-800 mx-0.5"></div>

            <!-- Controls: Theme, Presenter, Fullscreen -->
            <div class="flex items-center gap-1">
                <!-- Theme Toggle -->
                <button type="button" onclick="togglePresentationTheme()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 flex items-center justify-center text-xs transition-all" title="Toggle Light/Dark Theme (T)">
                    <i class="ph-bold ph-moon dark:hidden"></i>
                    <i class="ph-bold ph-sun hidden dark:inline text-amber-400"></i>
                </button>

                <!-- Fullscreen Toggle -->
                <button type="button" onclick="toggleFullScreen()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 flex items-center justify-center text-xs transition-all" title="Toggle Fullscreen (F)">
                    <i class="ph-bold ph-corners-out"></i>
                </button>

                <!-- Presenter View Launcher -->
                <a href="presenter_view.php" target="_blank" class="w-8 h-8 rounded-xl bg-[#15803d]/10 hover:bg-[#15803d]/20 text-[#15803d] dark:text-[#16a34a] flex items-center justify-center text-xs transition-all" title="Buksan ang Presenter Screen na may Script">
                    <i class="ph-bold ph-monitor-play"></i>
                </a>
            </div>

        </div>
    </nav>

    <!-- ========================================== -->
    <!-- JAVASCRIPT: AUDIENCE SYNC CONTROLLER       -->
    <!-- ========================================== -->
    <script>
        const totalSlides = 8;
        let currentSlide = 1;
        const PRESENTER_SYNC_CHANNEL = 'tala_presentation_sync';

        const slideTitles = [
            "TALA Overview",
            "Republic Acts in Government",
            "Why TALA Was Built",
            "End-to-End Workflow",
            "Fiscal Transparency",
            "Governance & Badges",
            "Tech Architecture",
            "Impact & Conclusion"
        ];

        function updateSlideUI() {
            document.querySelectorAll('.slide-view').forEach((s, idx) => {
                if (idx + 1 === currentSlide) {
                    s.classList.add('active-slide');
                } else {
                    s.classList.remove('active-slide');
                }
            });

            // Update top progress bar
            const pct = (currentSlide / totalSlides) * 100;
            const bar = document.getElementById('deckProgressBar');
            if (bar) bar.style.width = pct + '%';

            // Update top watermark
            const titleEl = document.getElementById('topSlideTitle');
            const counterEl = document.getElementById('topSlideCounter');
            const brandPill = document.getElementById('topBrandPill');

            if (titleEl) titleEl.innerText = slideTitles[currentSlide - 1] || "Presentation";
            if (counterEl) counterEl.innerText = `SLIDE 0${currentSlide} / 0${totalSlides}`;
            if (brandPill) {
                // Hide brand pill on Slide 1 because it already has the big logo
                brandPill.style.opacity = currentSlide === 1 ? '0' : '1';
            }

            // Update dock dots
            for (let i = 1; i <= totalSlides; i++) {
                const dot = document.getElementById(`audDot-${i}`);
                if (dot) {
                    if (i === currentSlide) {
                        dot.className = 'w-2.5 h-2.5 rounded-full transition-all duration-300 bg-[#15803d] dark:bg-[#16a34a] scale-125 ring-2 ring-[#15803d]/30';
                    } else {
                        dot.className = 'w-2.5 h-2.5 rounded-full transition-all duration-300 bg-slate-300 dark:bg-zinc-700 hover:bg-slate-400';
                    }
                }
            }

            // Scroll container to top in case of small height
            const activeEl = document.querySelector('.slide-view.active-slide');
            if (activeEl) activeEl.scrollTop = 0;
            fitActiveSlide();
        }

        // Auto-fit: scale the active slide's content down so nothing is ever cut off on any screen
        function fitActiveSlide() {
            const slide = document.querySelector('.slide-view.active-slide');
            if (!slide) return;
            const wrap = slide.firstElementChild;
            if (!wrap) return;
            wrap.style.transform = 'none';
            wrap.style.height = '';
            wrap.style.flexShrink = '0';
            const availH = slide.clientHeight;
            const availW = slide.clientWidth;
            const needH = wrap.scrollHeight;
            const needW = wrap.scrollWidth;
            if (!availH || !needH) return;
            const scale = Math.min(1, availH / needH, availW / needW);
            // When content is taller than the stage, flex layout pins it to the top and it overflows
            // downward; shift it up by half the overflow so the scaled result is truly centered.
            const shiftY = needH > availH ? -((needH - availH) / 2) : 0;
            wrap.style.transformOrigin = 'center center';
            wrap.style.transform = (scale < 1 || shiftY) ? `translateY(${shiftY.toFixed(1)}px) scale(${scale.toFixed(4)})` : 'none';
        }
        window.addEventListener('resize', fitActiveSlide);
        window.addEventListener('load', () => { fitActiveSlide(); setTimeout(fitActiveSlide, 400); });
        document.fonts && document.fonts.ready.then(fitActiveSlide);

        function broadcastSlideChange(slideNum) {
            try {
                if (syncChannel) {
                    syncChannel.postMessage({ type: 'GOTO_SLIDE', slide: slideNum, timestamp: Date.now() });
                }
            } catch (err) {}
            localStorage.setItem('tala_sync_slide', JSON.stringify({ slide: slideNum, timestamp: Date.now() }));
        }

        function goToSlide(slideNum, broadcast = true) {
            if (slideNum >= 1 && slideNum <= totalSlides) {
                currentSlide = slideNum;
                updateSlideUI();
                if (broadcast) broadcastSlideChange(slideNum);
            }
        }

        function nextSlide() {
            if (currentSlide < totalSlides) goToSlide(currentSlide + 1);
        }

        function prevSlide() {
            if (currentSlide > 1) goToSlide(currentSlide - 1);
        }

        function applyTheme(theme) {
            const isDark = theme === 'dark';
            document.documentElement.classList.toggle('dark', isDark);
            localStorage.setItem('theme', theme);
            localStorage.theme = theme;
        }

        function togglePresentationTheme() {
            const current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            try {
                if (syncChannel) {
                    syncChannel.postMessage({ type: 'SET_THEME', theme: next, timestamp: Date.now() });
                }
            } catch (e) {}
            localStorage.setItem('tala_sync_theme', next);
        }

        function toggleFullScreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(() => {});
            } else {
                if (document.exitFullscreen) document.exitFullscreen();
            }
        }

        // Click on background advances slide
        function handleStageClick(e) {
            // Ignore if clicking a button, link, or interactive element
            if (e.target.closest('button, a, input, textarea, select, #audienceDock, #navChevrons')) {
                return;
            }
            nextSlide();
        }

        // Right click goes previous slide
        document.addEventListener('contextmenu', (e) => {
            if (!e.target.closest('a, button, input')) {
                e.preventDefault();
                prevSlide();
            }
        });

        // BroadcastChannel Sync Listener
        let syncChannel = null;
        try {
            if ('BroadcastChannel' in window) {
                syncChannel = new BroadcastChannel(PRESENTER_SYNC_CHANNEL);
                syncChannel.onmessage = (e) => {
                    const data = e.data;
                    if (!data) return;
                    if (data.type === 'GOTO_SLIDE' && typeof data.slide === 'number') {
                        goToSlide(data.slide, false); // Don't echo back
                    } else if (data.type === 'SET_THEME' && data.theme) {
                        applyTheme(data.theme);
                    } else if (data.type === 'PING') {
                        syncChannel.postMessage({ type: 'PONG', slide: currentSlide, timestamp: Date.now() });
                    }
                };
                // Broadcast presence on connect
                syncChannel.postMessage({ type: 'PONG', slide: currentSlide, timestamp: Date.now() });
            }
        } catch (err) {
            console.warn('Sync channel error:', err);
        }

        // LocalStorage fallback sync
        window.addEventListener('storage', (e) => {
            if (e.key === 'tala_sync_slide' && e.newValue) {
                try {
                    const data = JSON.parse(e.newValue);
                    if (data && typeof data.slide === 'number' && data.slide !== currentSlide) {
                        goToSlide(data.slide, false);
                    }
                } catch(err){}
            } else if (e.key === 'tala_sync_theme' && e.newValue) {
                applyTheme(e.newValue);
            }
        });

        // Keyboard Controls
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'PageDown' || e.key === 'Enter') {
                e.preventDefault();
                nextSlide();
            } else if (e.key === 'ArrowLeft' || e.key === 'PageUp' || e.key === 'Backspace') {
                e.preventDefault();
                prevSlide();
            } else if (e.key === 'f' || e.key === 'F') {
                toggleFullScreen();
            } else if (e.key === 't' || e.key === 'T') {
                togglePresentationTheme();
            } else if (e.key >= '1' && e.key <= '8') {
                goToSlide(parseInt(e.key));
            }
        });

        // Idle Mouse Auto-hide Controls
        let idleTimer = null;
        function resetIdleTimer() {
            document.body.classList.remove('idle-cursor');
            clearTimeout(idleTimer);
            idleTimer = setTimeout(() => {
                document.body.classList.add('idle-cursor');
            }, 2500);
        }
        document.addEventListener('mousemove', resetIdleTimer);
        document.addEventListener('touchstart', resetIdleTimer);

        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            applyTheme(savedTheme);
            updateSlideUI();
            resetIdleTimer();
        });
    </script>
</body>
</html>
