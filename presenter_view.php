<?php
/**
 * TALA - Presenter Cockpit & Script Screen
 * Dual-Screen Presenter Console with Large Tagalog Teleprompter Script,
 * Live Current Slide Preview, Next Slide Preview, and Real-Time Audience Screen Sync.
 */
session_start();
?>
<!DOCTYPE html>
<html lang="tl" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALA • Presenter Cockpit (Screen para sa Script)</title>
    
    <!-- Tailwind CSS 3 & Fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
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
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>

    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.4); border-radius: 9999px; }
        .dark ::-webkit-scrollbar-thumb { background: rgba(82, 82, 91, 0.5); }

        body {
            font-family: 'Inter', sans-serif;
            height: 100vh;
            overflow: hidden;
            user-select: none;
        }

        .glass-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
        }
        .dark .glass-header {
            background: rgba(18, 18, 20, 0.95);
            border-bottom: 1px solid rgba(39, 39, 42, 0.9);
        }

        .script-box {
            line-height: 1.75;
            transition: font-size 0.2s ease;
        }

        .tap-effect {
            transition: transform 0.15s ease, opacity 0.15s ease;
        }
        .tap-effect:active {
            transform: scale(0.96);
        }
    </style>
</head>

<body class="bg-slate-100 dark:bg-[#090a0b] text-slate-800 dark:text-zinc-100 flex flex-col h-screen">

    <!-- ============================================================== -->
    <!-- TOP BAR: TELEMETRY, AUDIENCE SYNC & TIMER                      -->
    <!-- ============================================================== -->
    <header class="glass-header h-14 px-4 flex items-center justify-between gap-3 flex-shrink-0 z-30">
        <!-- Logo & Cockpit Title -->
        <div class="flex items-center gap-2.5">
            <img src="tala_logo_transparent.png" alt="TALA" class="w-8 h-8 object-contain">
            <div>
                <div class="flex items-center gap-1.5">
                    <h1 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider">
                        TALA Presenter Cockpit
                    </h1>
                    <span class="text-[9px] font-mono font-bold bg-[#15803d]/15 text-[#15803d] dark:text-[#16a34a] px-1.5 py-0.2 rounded">
                        DUAL SCREEN
                    </span>
                </div>
                <p class="text-[10px] text-slate-500 dark:text-zinc-400 truncate max-w-xs sm:max-w-md" id="topSlideTitle">
                    Slide 1: Pambungad sa TALA
                </p>
            </div>
        </div>

        <!-- Center: Stopwatch Presentation Timer -->
        <div class="flex items-center gap-2 bg-white dark:bg-zinc-900 px-3 py-1 rounded-xl border border-slate-200 dark:border-zinc-800 shadow-sm">
            <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
            <i class="ph-bold ph-stopwatch text-slate-400 text-xs"></i>
            <span class="font-mono font-bold text-sm text-slate-900 dark:text-white" id="presentationTimer">00:00</span>
            <div class="flex items-center gap-1 border-l border-slate-200 dark:border-zinc-800 pl-1.5 ml-1">
                <button type="button" onclick="toggleTimer()" id="timerToggleBtn" class="text-slate-500 hover:text-slate-800 dark:hover:text-white text-xs" title="Pause / Play Timer">
                    <i class="ph-fill ph-pause" id="timerIcon"></i>
                </button>
                <button type="button" onclick="resetTimer()" class="text-slate-400 hover:text-rose-500 text-xs" title="Reset Timer">
                    <i class="ph-bold ph-arrow-counter-clockwise"></i>
                </button>
            </div>
        </div>

        <!-- Right: Audience Sync Controls & Projector Launcher -->
        <div class="flex items-center gap-2">
            <!-- Audience Connection Status Pill -->
            <div id="audienceStatusBadge" class="flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-200/80 dark:bg-zinc-800/80 text-[10px] font-bold text-slate-600 dark:text-zinc-400">
                <span class="w-2 h-2 rounded-full bg-amber-400" id="audiencePingDot"></span>
                <span id="audienceStatusText" class="hidden sm:inline">Naghahanap ng Audience Screen...</span>
            </div>

            <!-- Open Projector / Audience Screen Button -->
            <button type="button" onclick="openAudienceScreen()" class="px-3 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] text-white text-xs font-bold transition-all tap-effect flex items-center gap-1.5 shadow-sm shadow-green-700/20" title="Buksan ang screen na ipapakita sa Projector o Panel">
                <i class="ph-bold ph-presentation text-sm"></i>
                <span class="hidden md:inline">Buksan ang Audience Screen</span>
            </button>

            <!-- Theme Toggle -->
            <button type="button" onclick="togglePresentationTheme()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 flex items-center justify-center text-sm transition-all" title="Toggle Light/Dark Theme">
                <i class="ph-bold ph-moon dark:hidden"></i>
                <i class="ph-bold ph-sun hidden dark:inline text-amber-400"></i>
            </button>
        </div>
    </header>

    <!-- ============================================================== -->
    <!-- MAIN COCKPIT: SPLIT SCREEN (PREVIEW VS LARGE TAGALOG SCRIPT)   -->
    <!-- ============================================================== -->
    <main class="flex-1 flex flex-col lg:flex-row overflow-hidden p-3 gap-3">

        <!-- ========================================================== -->
        <!-- LEFT COLUMN: LIVE SLIDE PREVIEW & NAVIGATION (45%)        -->
        <!-- ========================================================== -->
        <div class="w-full lg:w-[48%] flex flex-col gap-3 h-full overflow-hidden">
            
            <!-- Live Active Slide Preview Box -->
            <div class="flex-1 bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm p-3 flex flex-col overflow-hidden relative">
                <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-zinc-800 mb-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                        <span class="text-[11px] font-black tracking-wider uppercase text-rose-600 dark:text-rose-400 font-mono">
                            NAKIKITA NG AUDIENCE SA SCREEN
                        </span>
                    </div>
                    <span class="text-xs font-bold font-mono text-[#15803d] dark:text-[#16a34a]" id="liveSlideBadge">
                        SLIDE 1 / 8
                    </span>
                </div>

                <!-- Embedded Slide Frame (Reflecting Audience Display) -->
                <div class="flex-1 w-full bg-slate-50 dark:bg-[#0c0d0e] rounded-xl border border-slate-200/80 dark:border-zinc-800/80 overflow-hidden relative">
                    <iframe id="audiencePreviewFrame" src="presentation_audience.php" class="w-full h-full border-0 pointer-events-none select-none"></iframe>
                    <div class="absolute inset-0 bg-transparent cursor-pointer" onclick="nextSlide()" title="I-click para sa susunod na slide"></div>
                </div>
            </div>

            <!-- Next Slide Preview & Bridge Cue Card -->
            <div class="h-28 bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm p-3 flex items-center justify-between gap-3 flex-shrink-0">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 text-[10px] font-mono font-bold text-amber-600 dark:text-amber-400 uppercase mb-0.5">
                        <i class="ph-bold ph-arrow-circle-right"></i>
                        <span>Susunod na Slide:</span>
                    </div>
                    <h3 class="text-xs sm:text-sm font-extrabold text-slate-900 dark:text-white truncate" id="nextSlideHeading">
                        Slide 2: Why TALA Was Built
                    </h3>
                    <p class="text-[11px] text-slate-500 dark:text-zinc-400 line-clamp-2 mt-0.5" id="nextSlideCue">
                        Paghahambing sa lumang paper governance at modernong digital web platform ng TALA.
                    </p>
                </div>
                <button type="button" onclick="nextSlide()" class="px-3.5 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-800 dark:text-zinc-100 text-xs font-bold flex items-center gap-1.5 transition-all tap-effect flex-shrink-0" title="Pumunta sa Susunod">
                    <span>Kasunod</span>
                    <i class="ph-bold ph-caret-right text-base"></i>
                </button>
            </div>

            <!-- Big Navigation & Slide Jump Controls -->
            <div class="bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 p-2.5 shadow-sm flex items-center justify-between gap-2 flex-shrink-0">
                <!-- Previous Button -->
                <button type="button" onclick="prevSlide()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-800 dark:text-zinc-200 font-bold text-xs flex items-center gap-1.5 transition-all tap-effect" title="Nakaraang Slide (Left Arrow)">
                    <i class="ph-bold ph-caret-left text-base"></i>
                    <span>Nakaraan</span>
                </button>

                <!-- Quick Slide Jump Pills 1-8 -->
                <div class="flex items-center gap-1 overflow-x-auto py-1 px-1">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <button type="button" onclick="goToSlide(<?= $i ?>)" id="jumpBtn-<?= $i ?>" class="w-7 h-7 rounded-lg text-xs font-mono font-bold transition-all <?= $i === 1 ? 'bg-[#15803d] text-white shadow-sm' : 'bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 hover:bg-slate-200' ?>">
                            <?= $i ?>
                        </button>
                    <?php endfor; ?>
                </div>

                <!-- Next Button -->
                <button type="button" onclick="nextSlide()" class="px-5 py-2 rounded-xl bg-[#15803d] hover:bg-[#166534] text-white font-bold text-xs flex items-center gap-1.5 transition-all tap-effect shadow-md shadow-green-700/20" title="Susunod na Slide (Right Arrow / Space)">
                    <span>Susunod</span>
                    <i class="ph-bold ph-caret-right text-base"></i>
                </button>
            </div>
        </div>


        <!-- ========================================================== -->
        <!-- RIGHT COLUMN: THE BIG TAGALOG SCRIPT SECTION (52%)         -->
        <!-- ========================================================== -->
        <div class="w-full lg:w-[52%] flex flex-col h-full bg-white dark:bg-zinc-900 rounded-2xl border border-slate-200 dark:border-zinc-800 shadow-sm p-4 overflow-hidden">
            
            <!-- Script Header with Section Badge & Font Size Controls -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-zinc-800 mb-3 flex-shrink-0">
                <div class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#16a34a] to-[#15803d] text-white flex items-center justify-center shadow-md shadow-green-700/20">
                        <i class="ph-bold ph-microphone text-lg"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-1.5">
                            <h2 class="text-xs font-mono font-black uppercase text-[#15803d] dark:text-[#16a34a] tracking-wider">
                                BASAHIN NANG MALAKAS SA PANEL
                            </h2>
                            <span class="w-2 h-2 rounded-full bg-[#15803d] animate-ping"></span>
                        </div>
                        <p class="text-xs font-bold text-slate-700 dark:text-zinc-300" id="scriptActiveLabel">
                            Pambungad: Ang TALA Web Platform
                        </p>
                    </div>
                </div>

                <!-- Text Size Adjusters (A- / A+) -->
                <div class="flex items-center gap-1 bg-slate-100 dark:bg-zinc-800 p-1 rounded-xl">
                    <button type="button" onclick="adjustScriptSize(-2)" class="w-7 h-7 rounded-lg bg-white dark:bg-zinc-700 text-slate-700 dark:text-zinc-200 text-xs font-bold hover:bg-slate-50 transition-all flex items-center justify-center" title="Bawasan ang laki ng letra">
                        A-
                    </button>
                    <span class="text-[11px] font-mono font-bold px-1.5 text-slate-600 dark:text-zinc-300" id="fontSizeIndicator">20px</span>
                    <button type="button" onclick="adjustScriptSize(2)" class="w-7 h-7 rounded-lg bg-white dark:bg-zinc-700 text-slate-700 dark:text-zinc-200 text-xs font-bold hover:bg-slate-50 transition-all flex items-center justify-center" title="Palakihin ang letra">
                        A+
                    </button>
                </div>
            </div>

            <!-- Quick Section Selector Chips for the Active Slide -->
            <div class="flex items-center gap-1.5 pb-2 mb-2 overflow-x-auto flex-shrink-0" id="sectionChipsBar">
                <!-- Dynamically populated by JS based on current slide -->
            </div>

            <!-- The Big Teleprompter Box (User can effortlessly read from distance) -->
            <div class="flex-1 overflow-y-auto p-4 rounded-2xl bg-slate-50/80 dark:bg-zinc-800/40 border border-slate-200/80 dark:border-zinc-700/60 custom-scrollbar flex flex-col justify-between">
                <div>
                    <!-- Active Section Cue Card -->
                    <div class="mb-3 px-3 py-1.5 rounded-xl bg-[#15803d]/10 text-[#15803d] dark:text-[#16a34a] text-xs font-bold flex items-center justify-between">
                        <span id="scriptSectionPill">📌 PANGKALAHATANG PAMBUNGAD</span>
                        <span class="text-[10px] font-mono">TAGALOG SCRIPT</span>
                    </div>

                    <!-- Spoken Paragraph (Large, Readable, Clear Tagalog) -->
                    <p id="mainScriptText" class="script-box font-bold text-slate-900 dark:text-zinc-100 text-lg leading-relaxed tracking-normal">
                        Magandang araw po sa ating kagalang-galang na panel at mga panauhin. Ikinagagalak ko pong iprisinta ang Tumauini Active Youth Ledger Analytics o TALA — ang opisyal na digital governance web platform na nilikha upang maghatid ng buong transparency, real-time tracking ng pondo, at mabilis na serbisyo para sa kabataan ng Tumauini.
                    </p>
                </div>

                <!-- Key Bullet Talking Points -->
                <div class="mt-4 pt-3 border-t border-slate-200 dark:border-zinc-700">
                    <span class="text-[10px] font-black uppercase text-slate-400 dark:text-zinc-500 tracking-wider block mb-1.5 font-mono">
                        Mabilisang Gabay sa Pagsagot (Key Points):
                    </span>
                    <ul id="keyPointsList" class="space-y-1 text-xs font-semibold text-slate-600 dark:text-zinc-300">
                        <li class="flex items-center gap-1.5"><i class="ph-bold ph-check text-[#15803d]"></i> Tumauini Youth Governance Platform</li>
                        <li class="flex items-center gap-1.5"><i class="ph-bold ph-check text-[#15803d]"></i> 100% Paperless & Transparent</li>
                    </ul>
                </div>
            </div>

            <!-- Keyboard Navigation Helper at Bottom -->
            <div class="pt-2 mt-2 border-t border-slate-100 dark:border-zinc-800 flex items-center justify-between text-[11px] text-slate-400 font-mono flex-shrink-0">
                <span>Pindutin ang <kbd class="px-1 py-0.5 rounded bg-slate-200 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300">&rarr;</kbd> o <kbd class="px-1.5 py-0.5 rounded bg-slate-200 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300">Space</kbd> para sa susunod na slide</span>
                <span>TALA &bull; Script Cockpit</span>
            </div>
        </div>

    </main>


    <!-- ============================================================== -->
    <!-- SCRIPT DATA & REAL-TIME CONTROLLER                             -->
    <!-- ============================================================== -->
    <script>
        const totalSlides = 8;
        let currentSlide = 1;
        let scriptFontSize = 20; // Default font size in px
        const PRESENTER_SYNC_CHANNEL = 'tala_presentation_sync';

        // Complete presentation script bank in Tagalog
        const presentationDeck = {
            1: {
                title: "Slide 01: Pambungad sa TALA",
                nextTitle: "Slide 02: Legal Mandates & Republic Acts",
                nextCue: "Mga batas ng pamahalaan (RA 10742, RA 11768, RA 7160, RA 6713) na sumusuporta sa fiscal autonomy at full disclosure.",
                sections: {
                    default: {
                        label: "Pambungad sa Platform",
                        script: "Magandang araw po sa ating kagalang-galang na panel at mga panauhin. Ikinagagalak ko pong iprisinta ang Tumauini Active Youth Ledger Analytics o TALA — ang opisyal na digital governance web platform na nilikha upang maghatid ng buong transparency, real-time tracking ng pondo, at mabilis na serbisyo para sa kabataan ng Tumauini.",
                        points: [
                            "Opisyal na digital governance web platform para sa Tumauini.",
                            "Tinitiyak ang 100% transparency at real-time fund tracking.",
                            "Mabilis na pagsumite ng ulat alinsunod sa mga direktiba ng DILG at COA."
                        ]
                    }
                }
            },
            2: {
                title: "Slide 02: Legal Mandates & Republic Acts",
                nextTitle: "Slide 03: Why TALA Was Built",
                nextCue: "Paghahambing sa lumang paper governance at modernong digital web platform ng TALA.",
                sections: {
                    default: {
                        label: "Buod ng mga Batas at Mandato",
                        script: "Ang TALA ay mahigpit na nakasalig sa apat na pangunahing batas ng pamahalaan: ang RA 10742 para sa statutory youth development fund, ang RA 11768 para sa mandatory digital full disclosure at financial autonomy, ang RA 7160 Local Government Code, at ang RA 6713 Code of Conduct para sa bukas na pamamahala.",
                        points: [
                            "RA 10742: 10% statutory fund cap, CBYDP & ABYIP.",
                            "RA 11768: Mandatory digital at physical Full Disclosure.",
                            "RA 7160: DILG Full Disclosure Policy sa 46 barangay.",
                            "RA 6713: Code of Conduct & ethical public audit."
                        ]
                    },
                    ra10742: {
                        label: "1. RA 10742 (SK Reform Act 2015)",
                        script: "Ang Republic Act 10742 o SK Reform Act of 2015 ang nagtatakda ng statutory 10% youth development fund mula sa pangkalahatang pondo ng barangay. Ipinag-uutos din nito ang pagbuo ng Comprehensive Barangay Youth Development Plan (CBYDP) at Annual Barangay Youth Investment Program (ABYIP).",
                        points: [
                            "Mandatory 10% statutory allocation mula sa barangay fund.",
                            "Pormulasyon ng CBYDP at taunang ABYIP.",
                            "Paglikha ng Local Youth Development Office at MYDC."
                        ]
                    },
                    ra11768: {
                        label: "2. RA 11768 (SK Amendments 2022)",
                        script: "Ang Republic Act 11768 o SK Reform Amendments of 2022 ang nagpatibay sa financial autonomy ng kabataan. Mahigpit nitong ipinag-uutos ang digital at physical Full Disclosure ng mga financial statements, voucher, at disbursement upang matiyak ang pananagutan sa bawat sentimo.",
                        points: [
                            "Mandatory digital & physical posting ng quarterly disclosures.",
                            "Direktang financial autonomy at sariling bank accounts ng SK.",
                            "Pamantayan sa honoraria at pampublikong pananagutan."
                        ]
                    },
                    ra7160: {
                        label: "3. RA 7160 (Local Government Code)",
                        script: "Ang Republic Act 7160 o Local Government Code of 1991 ang sandigan ng lokal na pamamahala. Alinsunod sa DILG Full Disclosure Policy at COA accounting rules, kailangang regular na ilathala ang quarterly reports ng lahat ng 46 na barangay ng Tumauini nang walang antala.",
                        points: [
                            "Pundasyon ng lokal na awtonomiya at fiscal decentralization.",
                            "DILG Full Disclosure Policy sa 46 na barangay ng Tumauini.",
                            "Pamantayan ng Commission on Audit (COA) sa liquidation."
                        ]
                    },
                    ra6713: {
                        label: "4. RA 6713 (Code of Conduct & Ethics)",
                        script: "Ang Republic Act 6713 o Code of Conduct and Ethical Standards for Public Officials and Employees ang nagtitiyak na bukas sa pampublikong pagsusuri ang lahat ng opisyal na dokumento at ledgers, may mabilis na pagtugon sa mga hinaing ng mamamayan, at sumusunod sa pinakamataas na pamantayan ng integridad.",
                        points: [
                            "Bukas sa publiko ang mga opisyal na rekord at ledgers.",
                            "Mabilisang pagtugon (SLA) sa mga sulat at helpdesk tickets.",
                            "Pinakamataas na pamantayan ng etika at pananagutan."
                        ]
                    }
                }
            },
            3: {
                title: "Slide 03: Why TALA Was Built (Problema at Solusyon)",
                nextTitle: "Slide 04: The End-to-End Governance Workflow",
                nextCue: "Ang 5-yugtong daloy mula sa youth onboarding hanggang sa executive auditing.",
                sections: {
                    default: {
                        label: "Buod ng Slide 03",
                        script: "Sa slide na ito, ipinapakita natin ang malaking kaibahan ng lumang mano-manong sistema kumpara sa modernong solusyon ng TALA. Dito ninyo makikita kung paano pinalitan ng digital ledgers at automated disclosures ang dating mabagal at magulong papeles.",
                        points: [
                            "Mano-manong papeles = nawawalang resibo at pagkaantala.",
                            "TALA Web Platform = 1-click cloud disclosure at live audit."
                        ]
                    },
                    problem: {
                        label: "1. Tradisyunal na Pamamahala sa Papel",
                        script: "Sa tradisyunal na sistema, nahihirapan ang mga SK official sa tambak na papeles at manu-manong pagpapaskil sa bulletin board. Madalas nawawala o naaantala ang mga ulat, walang malinaw na pagsubaybay sa 46 milyong pisong pondo ng kabataan, at walang paraan ang publiko upang makita ang mga transaksyon.",
                        points: [
                            "21-day reporting lag dahil sa mano-manong pirmahan at pisikal na pagsusumite.",
                            "Walang unified database para sa ₱46M+ kabuuang pondo ng kabataan.",
                            "Zero public visibility para sa mga ordinaryong kabataan."
                        ]
                    },
                    solution: {
                        label: "2. Ang Digital na Solusyon ng TALA",
                        script: "Sa pamamagitan ng TALA, pinalitan natin ito ng mabilis at modernong web platform. Isang click lang, nai-publish agad ang quarterly Full Disclosure reports sa internet, live na namomonitor ang pondo ng 46 na barangay, at may direktang access ang kabataan sa mga opisyal na rekord.",
                        points: [
                            "1-click quarterly Full Disclosure publishing na may PDF preview.",
                            "Real-time monitoring sa pondo at check disbursements.",
                            "Bukas na access para sa pampublikong pagsusuri."
                        ]
                    }
                }
            },
            4: {
                title: "Slide 04: The End-to-End Governance Workflow",
                nextTitle: "Slide 05: Fiscal Transparency & Budget Monitoring",
                nextCue: "Pagsusuri sa Full Disclosure Portal, Budget Registry, at COA Voucher engine.",
                sections: {
                    default: {
                        label: "Buod ng 5 Yugto",
                        script: "Ito ang kumpletong daloy ng pamamahala ng TALA mula sa pagpaparehistro ng kabataan, pagpaplano ng badyet, pag-audit ng voucher, hanggang sa pampublikong paglalathala at pagsusuri ng munisipyo sa lahat ng 46 na barangay.",
                        points: [
                            "5 yugtong saradong loop ng pamamahala.",
                            "Ugnayan ng Kabataan, SK, MYDC, at Mayor's Office."
                        ]
                    },
                    stage1: {
                        label: "Yugto 1: Onboarding & QR ID",
                        script: "Sa Unang Yugto, magpaparehistro online ang kabataan at mabibigyan ng opisyal na QR ID na may tamper-proof security. Tinitiyak nito na lehitimo ang bawat benepisyaryo at walang multo sa attendance o pamamahagi ng tulong.",
                        points: [
                            "Tamper-proof Digital QR Youth ID.",
                            "Tunay na bilang ng kabataang residente bawat barangay."
                        ]
                    },
                    stage2: {
                        label: "Yugto 2: Pagpaplano ng AYIP",
                        script: "Sa Ikalawang Yugto, binubuo ng SK Chairperson at Treasurer ang Annual Barangay Youth Investment Program. Awtomatikong binabantayan ng TALA ang statutory 10% fund cap upang maiwasan ang deficit o labis na paggastos.",
                        points: [
                            "Automated statutory 10% budget guard laban sa deficit.",
                            "Naka-link sa munisipal na alokasyon ng Tumauini."
                        ]
                    },
                    stage3: {
                        label: "Yugto 3: Check Voucher & Liquidation",
                        script: "Sa Ikatlong Yugto, dito inia-upload ang mga check voucher at opisyal na resibo gamit ang standardized COA accounting codes. Mula ilang linggong paghihintay, nagiging minuto na lamang ang proseso ng liquidation.",
                        points: [
                            "Standardized COA accounting expense codes.",
                            "Digital upload ng mga resibo para sa mabilisang liquidation."
                        ]
                    },
                    stage4: {
                        label: "Yugto 4: FDP Disclosure",
                        script: "Sa Ika-apat na Yugto, ang mga naaprubahang quarterly financial reports ay awtomatikong nai-publish sa pampublikong Full Disclosure Portal na may in-browser PDF preview alinsunod sa kautusan ng DILG.",
                        points: [
                            "100% compliant sa Full Disclosure Policy ng DILG.",
                            "Mabilisang pagtingin gamit ang in-browser PDF preview."
                        ]
                    },
                    stage5: {
                        label: "Yugto 5: Pagsusuri ng Pamunuan",
                        script: "Sa Ikalimang Yugto, nirerepaso ng MYDC Federation at Commission on Audit ang compliance ng bawat barangay. Ginagawaran ang mga barangay ng Governance Badges upang mapanatili ang tapat at maayos na pamamahala.",
                        points: [
                            "46-barangay compliance leaderboard.",
                            "Opisyal na pagsusuri ng federasyon at COA auditors."
                        ]
                    }
                }
            },
            5: {
                title: "Slide 05: Fiscal Transparency & Budget Monitoring",
                nextTitle: "Slide 06: Governance Badging, MYDC & Assembly Records",
                nextCue: "Pagsusuri sa Badges, YORP youth council, Minutes of Meeting, at Ticketing.",
                sections: {
                    default: {
                        label: "Buod ng Fiscal Engines",
                        script: "Narito ang tatlong pangunahing haligi ng TALA: ang DILG Full Disclosure Portal, ang Real-Time Budget Registry para sa 46.8 milyong pisong pondo ng kabataan, at ang COA-compliant Check Voucher and Liquidation engine.",
                        points: [
                            "Full Disclosure Board alinsunod sa DILG circulars.",
                            "₱46M+ fund ledger na may live telemetry.",
                            "Check voucher tracking na may digital liquidation attachments."
                        ]
                    },
                    fdp: {
                        label: "1. Full Disclosure Portal",
                        script: "Ito ang ating Full Disclosure Portal. Awtomatiko nitong inilalathala ang quarterly reports ng lahat ng 46 na barangay na may malinaw na PDF preview upang tiyaking 100% compliant ang ating bayan sa mga direktiba ng DILG.",
                        points: [
                            "Quarterly posting (Q1 to Q4) na may timestamped audit certificate.",
                            "Public inspection nang walang kinakailangang login."
                        ]
                    },
                    budget: {
                        label: "2. Real-Time Budget Registry",
                        script: "Ito naman ang Real-Time Budget Registry. Pinagsasama nito ang pondo ng 46 na barangay sa iisang live ledger upang masubaybayan ang approved budget laban sa aktwal na nagastos at maiwasan ang kakulangan.",
                        points: [
                            "Live bar charts at pie breakdown ng alokasyon.",
                            "Awtomatikong babala kapag lumalapit sa 10% statutory cap."
                        ]
                    },
                    voucher: {
                        label: "3. Check Voucher & Liquidation",
                        script: "Sa module na ito pinoproseso ang mga check voucher at liquidation. Direktang ikinakabit ang mga digital na resibo kasama ang COA codes upang maiwasan ang pagkawala ng dokumento at mapabilis ang audit.",
                        points: [
                            "COA classification: Training, Sports, Livelihood, Anti-Drug.",
                            "Zero missing receipts; naka-archive nang permanente sa database."
                        ]
                    }
                }
            },
            6: {
                title: "Slide 06: Governance Badging, MYDC & Assembly Records",
                nextTitle: "Slide 07: System Architecture & Tech Foundation",
                nextCue: "Ang teknikal na pundasyon: PHP 8.2, MySQL 8.4, Tailwind CSS, at Seguridad.",
                sections: {
                    default: {
                        label: "Buod ng Governance Modules",
                        script: "Sa slide na ito, makikita ang ating Barangay Governance Badges na naghihikayat ng maagap na pag-uulat, ang MYDC council portal, ang opisyal na Minutes and Resolutions, at ang ating direktang youth helpdesk.",
                        points: [
                            "Gold, Silver, Bronze seals para sa mabilis mag-ulat.",
                            "Opisyal na rehistro ng mga kinikilalang samahan ng kabataan.",
                            "Digital na repositoryo ng mga batas at resolusyon."
                        ]
                    },
                    badges: {
                        label: "1. Barangay Governance Badges",
                        script: "Ito ang ating Barangay Governance Badges system. Awtomatiko nitong sinusuri ang 46 na barangay at nagbibigay ng Gold, Silver, at Bronze seals ayon sa bilis at husay ng kanilang pagsumite ng ulat.",
                        points: [
                            "Gold Seal: Napapanahong pagsumite ng lahat ng quarterly reports.",
                            "Silver Seal: Kumpleto ngunit may kaunting antala.",
                            "Bronze Seal: May pending na kailangang aksyunan agad."
                        ]
                    },
                    mydc: {
                        label: "2. MYDC & YORP Akreditasyon",
                        script: "Dito pinamamahalaan ang opisyal na rehistro ng mga kinikilalang youth organizations at lider ng kabataan upang masiguro ang lehitimong representasyon sa konseho.",
                        points: [
                            "Tinitiyak na lehitimo ang mga kinatawan sa asembleya.",
                            "YORP accreditation record na may tenure histories."
                        ]
                    },
                    resolutions: {
                        label: "3. Minutes at mga Resolusyon",
                        script: "Dito naka-archive ang mga opisyal na resolusyon, ordinansa, at minutes of meeting ng SK. Naitala rin ang roll call at quorum upang masigurong legal at balido ang bawat panukala.",
                        points: [
                            "Opisyal na legislative archive ng bawat barangay.",
                            "Talaan ng boto, roll call, at quorum certification."
                        ]
                    },
                    tickets: {
                        label: "4. Civic Helpdesk Tickets",
                        script: "Ito ang helpdesk ticketing system kung saan maaaring magpadala ang mga kabataan ng kanilang mga hinaing, tanong, o mungkahi upang maaksyunan agad ng kanilang mga opisyal.",
                        points: [
                            "Direktang linya sa pagitan ng kabataan at pamunuan.",
                            "May tracking number para masubaybayan ang aksyon."
                        ]
                    }
                }
            },
            7: {
                title: "Slide 07: System Architecture & Tech Foundation",
                nextTitle: "Slide 08: Transforming Tumauini Youth Governance",
                nextCue: "Pagtatapos: Mga napatunayang epekto, roadmap, at live system walkthrough.",
                sections: {
                    default: {
                        label: "Buod ng Arkitektura",
                        script: "Ang teknikal na pundasyon ng TALA ay sadyang idinisenyo para sa bilis at proteksyon gamit ang pure PHP 8.2, MySQL 8.4, at Tailwind CSS. Walang mabigat na external frameworks, kaya wala pang 100 milliseconds ang bilis ng pag-load ng bawat pahina.",
                        points: [
                            "Sub-100ms response time sa bawat pahina.",
                            "Zero external framework dependencies.",
                            "Enterprise security defenses laban sa panloloko at data tampering."
                        ]
                    },
                    php: {
                        label: "1. PHP 8.2 & PDO Runtime",
                        script: "Sa backend, gumagana ang TALA gamit ang PHP 8.2 na may PDO prepared statements. Ito ay magaan at walang mabibigat na third-party frameworks kaya napakabilis mag-load ng bawat pahina.",
                        points: [
                            "Mabilis at matipid sa memory ng server.",
                            "100% ligtas laban sa SQL injection dahil sa PDO prepared statements."
                        ]
                    },
                    mysql: {
                        label: "2. MySQL 8 InnoDB Layer",
                        script: "Ang database layer ay tumatakbo sa MySQL 8 gamit ang InnoDB engine na may ACID compliance upang masiguro na laging protektado at buo ang bawat transaksyong pinansyal ng 46 na barangay.",
                        points: [
                            "ACID compliant transactions para sa mga pondo at vouchers.",
                            "Foreign key cascades at indexed tables para sa mabilisang query."
                        ]
                    },
                    tailwind: {
                        label: "3. UI: Tailwind CSS 3",
                        script: "Ang user interface ay binuo gamit ang Tailwind CSS 3 at Phosphor icons. Umaangkop ito sa anumang laki ng screen — mula sa 4K projector hanggang sa smartphone ng opisyal — at may instant dark and light mode.",
                        points: [
                            "Responsive mula sa 4K projectors hanggang sa maliliit na mobile phones.",
                            "Instant persistent Dark Mode at Light Mode toggle."
                        ]
                    },
                    security: {
                        label: "4. Mga Pamantayan sa Seguridad",
                        script: "Para sa seguridad, mahigpit na ipinapatupad ng TALA ang role-based access control, proteksyon laban sa CSRF, file upload quarantine, at buong audit log sa bawat galaw ng admin.",
                        points: [
                            "Role-based access (Citizen, SK Official, Admin, Auditor).",
                            "CSRF token defense at MIME upload verification."
                        ]
                    }
                }
            },
            8: {
                title: "Slide 08: Transforming Tumauini Youth Governance",
                nextTitle: "Pagtatapos: Live Demonstration",
                nextCue: "Handa na para sa mga katanungan ng panel at live system walkthrough.",
                sections: {
                    default: {
                        label: "Buod ng Pagtatapos",
                        script: "Bilang pagtatapos, napatunayan ng TALA ang 100% digital coverage sa buong Tumauini, pinabilis ng 85% ang pagproseso ng mga ulat, at nagbukas ng modernong pamamahala para sa kabataan. Maaari niyo na pong subukan ang ating live system.",
                        points: [
                            "100% sakop ang lahat ng 46 barangay.",
                            "85% bawas sa oras ng pag-aayos ng liquidation.",
                            "₱46M+ pondo ng kabataan na laging bukas sa pagsusuri."
                        ]
                    },
                    impact: {
                        label: "1. Naihatid na Epekto",
                        script: "Bilang buod ng ating tagumpay: Naabot ng TALA ang 100% digital coverage sa lahat ng 46 na barangay ng Tumauini, pinabilis ng 85% ang voucher liquidation, at nagbigay ng 24/7 pampublikong visibility sa higit 46 milyong pisong pondo.",
                        points: [
                            "46/46 barangay ang konektado sa iisang ledger.",
                            "Minuto na lamang sa halip na linggo ang proseso ng vouchers.",
                            "Modelong benchmark para sa ibang bayan at lalawigan."
                        ]
                    },
                    roadmap: {
                        label: "2. Roadmap para sa Hinaharap",
                        script: "Sa hinaharap, kabilang sa ating plano ang offline Progressive Web App para sa malalayong barangay, interactive GIS mapping ng mga proyekto, at automated SMS alerts para sa mga pagpupulong.",
                        points: [
                            "Q1: Progressive Web App (PWA) para sa offline voucher drafting.",
                            "Q2: Interactive GIS Project Map para makita ang lokasyon ng mga proyekto.",
                            "Q3: Automated SMS alert gateway para sa emergency at asembleya."
                        ]
                    },
                    launchpad: {
                        label: "3. Handa sa Live Walkthrough",
                        script: "Bukas at live na gumagana ang ating web platform ngayon. Inaanyayahan po namin ang ating panel na subukan ang live portal habang bukas po kami sa inyong mga katanungan. Maraming salamat po!",
                        points: [
                            "Live Public Portal: public_disclosure.php",
                            "Live Budget Registry: admin_budget.php",
                            "Admin Console Login: login.php"
                        ]
                    }
                }
            }
        };

        // ==========================================
        // DUAL-SCREEN SYNC CONTROLLER
        // ==========================================
        let syncChannel = null;
        let isAudienceConnected = false;
        let audiencePingTimer = null;

        function initSyncChannel() {
            try {
                if ('BroadcastChannel' in window) {
                    syncChannel = new BroadcastChannel(PRESENTER_SYNC_CHANNEL);
                    syncChannel.onmessage = (e) => {
                        const data = e.data;
                        if (!data) return;
                        if (data.type === 'PONG') {
                            setAudienceConnected(true);
                        } else if (data.type === 'GOTO_SLIDE' && typeof data.slide === 'number') {
                            if (data.slide !== currentSlide) {
                                goToSlide(data.slide, false);
                            }
                        }
                    };
                    // Ping audience screen
                    pingAudienceScreen();
                    audiencePingTimer = setInterval(pingAudienceScreen, 3000);
                }
            } catch (err) {
                console.warn('BroadcastChannel sync init error:', err);
            }
        }

        function pingAudienceScreen() {
            if (syncChannel) {
                try { syncChannel.postMessage({ type: 'PING', timestamp: Date.now() }); } catch(e){}
            }
        }

        function setAudienceConnected(connected) {
            isAudienceConnected = connected;
            const badge = document.getElementById('audienceStatusBadge');
            const dot = document.getElementById('audiencePingDot');
            const txt = document.getElementById('audienceStatusText');
            if (connected) {
                badge.className = 'flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-[10px] font-bold text-emerald-700 dark:text-emerald-400';
                dot.className = 'w-2 h-2 rounded-full bg-emerald-500 animate-ping';
                txt.innerText = 'Audience Screen: Nakakonekta';
            } else {
                badge.className = 'flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-200/80 dark:bg-zinc-800/80 text-[10px] font-bold text-slate-600 dark:text-zinc-400';
                dot.className = 'w-2 h-2 rounded-full bg-amber-400';
                txt.innerText = 'Audience Screen: Standby';
            }
        }

        function broadcastSlide(slideNum) {
            if (syncChannel) {
                try { syncChannel.postMessage({ type: 'GOTO_SLIDE', slide: slideNum, timestamp: Date.now() }); } catch(e){}
            }
            try {
                localStorage.setItem('tala_sync_slide', JSON.stringify({ slide: slideNum, timestamp: Date.now() }));
            } catch(e){}
        }

        function broadcastTheme(theme) {
            if (syncChannel) {
                try { syncChannel.postMessage({ type: 'SET_THEME', theme: theme, timestamp: Date.now() }); } catch(e){}
            }
            try {
                localStorage.setItem('tala_sync_theme', theme);
            } catch(e){}
        }

        function openAudienceScreen() {
            window.open('presentation_audience.php', 'TalaAudienceScreen', 'width=1280,height=720,menubar=no,toolbar=no,location=no');
            setTimeout(() => {
                pingAudienceScreen();
                broadcastSlide(currentSlide);
            }, 600);
        }

        // ==========================================
        // SLIDE NAVIGATION & SCRIPT DISPLAY
        // ==========================================
        function updatePresenterCockpit(activeSectionKey = 'default') {
            const slideData = presentationDeck[currentSlide];
            if (!slideData) return;

            // Update top bar titles
            document.getElementById('topSlideTitle').innerText = slideData.title;
            document.getElementById('liveSlideBadge').innerText = `SLIDE ${currentSlide} / ${totalSlides}`;
            document.getElementById('nextSlideHeading').innerText = slideData.nextTitle;
            document.getElementById('nextSlideCue').innerText = slideData.nextCue;

            // Update iframe preview
            const frame = document.getElementById('audiencePreviewFrame');
            if (frame && frame.contentWindow && frame.contentWindow.goToSlide) {
                frame.contentWindow.goToSlide(currentSlide);
            } else if (frame) {
                // Initial load
                frame.src = `presentation_audience.php#slide-${currentSlide}`;
            }

            // Update jump buttons
            for (let i = 1; i <= totalSlides; i++) {
                const btn = document.getElementById('jumpBtn-' + i);
                if (btn) {
                    if (i === currentSlide) {
                        btn.className = 'w-7 h-7 rounded-lg text-xs font-mono font-bold transition-all bg-[#15803d] text-white shadow-sm scale-110';
                    } else {
                        btn.className = 'w-7 h-7 rounded-lg text-xs font-mono font-bold transition-all bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 hover:bg-slate-200';
                    }
                }
            }

            // Populate Section Selector Chips for this slide
            const chipsBar = document.getElementById('sectionChipsBar');
            chipsBar.innerHTML = '';
            const sections = slideData.sections;
            const secKeys = Object.keys(sections);

            secKeys.forEach(key => {
                const sec = sections[key];
                const chip = document.createElement('button');
                chip.type = 'button';
                const isSelected = key === activeSectionKey;
                chip.className = isSelected 
                    ? 'px-3 py-1 rounded-xl bg-[#15803d] text-white text-xs font-bold whitespace-nowrap transition-all shadow-sm'
                    : 'px-3 py-1 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 text-xs font-bold hover:bg-slate-200 whitespace-nowrap transition-all';
                chip.innerText = sec.label;
                chip.onclick = () => selectSection(key);
                chipsBar.appendChild(chip);
            });

            // Display active section script
            selectSection(activeSectionKey, false);
        }

        function selectSection(sectionKey, updateChips = true) {
            const slideData = presentationDeck[currentSlide];
            if (!slideData) return;
            const sec = slideData.sections[sectionKey] || slideData.sections.default;
            if (!sec) return;

            document.getElementById('scriptActiveLabel').innerText = `${slideData.title} • ${sec.label}`;
            document.getElementById('scriptSectionPill').innerText = `📌 ${sec.label.toUpperCase()}`;
            document.getElementById('mainScriptText').innerText = sec.script;

            // Update talking points list
            const pointsList = document.getElementById('keyPointsList');
            pointsList.innerHTML = '';
            if (sec.points && sec.points.length > 0) {
                sec.points.forEach(pt => {
                    const li = document.createElement('li');
                    li.className = 'flex items-start gap-1.5';
                    li.innerHTML = `<i class="ph-bold ph-check text-[#15803d] mt-0.5 flex-shrink-0"></i> <span>${pt}</span>`;
                    pointsList.appendChild(li);
                });
            }

            if (updateChips) {
                const chips = document.querySelectorAll('#sectionChipsBar button');
                const secKeys = Object.keys(slideData.sections);
                chips.forEach((c, idx) => {
                    const key = secKeys[idx];
                    if (key === sectionKey) {
                        c.className = 'px-3 py-1 rounded-xl bg-[#15803d] text-white text-xs font-bold whitespace-nowrap transition-all shadow-sm';
                    } else {
                        c.className = 'px-3 py-1 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 text-xs font-bold hover:bg-slate-200 whitespace-nowrap transition-all';
                    }
                });
            }
        }

        function goToSlide(num, shouldBroadcast = true) {
            if (num >= 1 && num <= totalSlides) {
                currentSlide = num;
                updatePresenterCockpit('default');
                if (shouldBroadcast) {
                    broadcastSlide(currentSlide);
                }
            }
        }

        function nextSlide() {
            if (currentSlide < totalSlides) {
                goToSlide(currentSlide + 1);
            }
        }

        function prevSlide() {
            if (currentSlide > 1) {
                goToSlide(currentSlide - 1);
            }
        }

        // Font Size Adjuster for Easy Reading
        function adjustScriptSize(delta) {
            scriptFontSize = Math.min(32, Math.max(14, scriptFontSize + delta));
            const p = document.getElementById('mainScriptText');
            if (p) p.style.fontSize = scriptFontSize + 'px';
            document.getElementById('fontSizeIndicator').innerText = scriptFontSize + 'px';
            localStorage.setItem('tala_script_font_size', scriptFontSize);
        }

        // Presentation Timer Controller
        let secondsElapsed = 0;
        let isTimerRunning = true;
        let timerInterval = setInterval(() => {
            if (!isTimerRunning) return;
            secondsElapsed++;
            const mins = Math.floor(secondsElapsed / 60);
            const secs = secondsElapsed % 60;
            const str = (mins < 10 ? '0' + mins : mins) + ':' + (secs < 10 ? '0' + secs : secs);
            const el = document.getElementById('presentationTimer');
            if (el) el.innerText = str;
        }, 1000);

        function toggleTimer() {
            isTimerRunning = !isTimerRunning;
            const icon = document.getElementById('timerIcon');
            if (icon) {
                icon.className = isTimerRunning ? 'ph-fill ph-pause' : 'ph-fill ph-play';
            }
        }

        function resetTimer() {
            secondsElapsed = 0;
            const el = document.getElementById('presentationTimer');
            if (el) el.innerText = '00:00';
        }

        // Theme Toggle
        function togglePresentationTheme() {
            const isDark = !document.documentElement.classList.contains('dark');
            document.documentElement.classList.toggle('dark', isDark);
            const theme = isDark ? 'dark' : 'light';
            localStorage.setItem('theme', theme);
            broadcastTheme(theme);
        }

        // Keyboard Controls
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowRight' || e.key === ' ' || e.key === 'PageDown') {
                e.preventDefault();
                nextSlide();
            } else if (e.key === 'ArrowLeft' || e.key === 'PageUp') {
                e.preventDefault();
                prevSlide();
            } else if (e.key >= '1' && e.key <= '8') {
                goToSlide(parseInt(e.key));
            } else if (e.key === 't' || e.key === 'T') {
                togglePresentationTheme();
            } else if (e.key === '+' || e.key === '=') {
                adjustScriptSize(2);
            } else if (e.key === '-' || e.key === '_') {
                adjustScriptSize(-2);
            }
        });

        // Initialize Cockpit
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            if (savedTheme === 'dark') document.documentElement.classList.add('dark');

            const savedFontSize = parseInt(localStorage.getItem('tala_script_font_size'));
            if (savedFontSize) {
                scriptFontSize = savedFontSize;
                adjustScriptSize(0);
            }

            initSyncChannel();
            updatePresenterCockpit('default');
        });
    </script>
</body>
</html>