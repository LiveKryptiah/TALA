<?php
// department_monitor.php - Inter-Agency Department Telemetry Console
// Standardized to TALA OS Design System (Reference: manage_disclosure.php & admin_resolution.php)
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Master Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'mayor') {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';

// Database Auto-Patcher ensuring columns exist
$tables = ['youth_profiles', 'youth_registry'];
foreach ($tables as $table) {
    try { $pdo->exec("ALTER TABLE `$table` ADD COLUMN `mho_pregnancy_status` VARCHAR(255) DEFAULT 'No Record'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE `$table` ADD COLUMN `sex` VARCHAR(50) DEFAULT 'Unspecified'"); } catch (Exception $e) {}
}

try {
    // 2. THE GRAND UNIFIED QUERY (Fetches tracking data for all departments)
    $query = "
        SELECT 
            id, sex,
            deped_status, 
            peso_general_status, peso_gov_program, peso_skills_cert, 
            mcr_civil_status, mcr_vital_status,
            mswdo_4ps, mswdo_pwd, mswdo_solo_parent, mswdo_indigent,
            mayor_scholarship, 
            bplo_business_owner,
            mho_pregnancy_status
        FROM youth_profiles 
        
        UNION ALL 
        
        SELECT 
            id, sex,
            deped_enrollment_status as deped_status, 
            peso_general_status, peso_gov_program, peso_skills_cert, 
            mcr_civil_status, mcr_vital_status,
            mswdo_4ps, mswdo_pwd, mswdo_solo_parent, mswdo_indigent,
            mayor_scholarship, 
            bplo_business_owner,
            mho_pregnancy_status
        FROM youth_registry 
        WHERE full_name NOT IN (SELECT full_name FROM youth_profiles)
    ";
    
    $stmt = $pdo->query($query);
    $all_youths = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. MASTER ANALYTICS COUNTERS
    $total_population = count($all_youths);
    
    // Initialize Department Counters
    $deped = ['Enrolled' => 0, 'Drop out' => 0, 'ALS' => 0, 'Unverified' => 0];
    $peso = ['Employed' => 0, 'Unemployed' => 0, 'In Gov Program' => 0, 'Has Skills/Cert' => 0];
    $mcr = ['Married' => 0, 'Deceased' => 0];
    $mswdo = ['4Ps' => 0, 'PWD' => 0, 'Solo Parent' => 0, 'Indigent' => 0];
    $mayor = ['Scholars' => 0, 'Pending' => 0, 'Graduated' => 0];
    $bplo = ['Business Owners' => 0];
    $mho = ['Pregnant / Post-Partum' => 0, 'At Risk' => 0];

    // Process all records
    foreach ($all_youths as $y) {
        // --- DEPED ---
        $edu = $y['deped_status'] ?? '';
        if (stripos($edu, 'Enrolled') !== false) $deped['Enrolled']++;
        elseif (stripos($edu, 'Drop') !== false || stripos($edu, 'Not Enrolled') !== false) $deped['Drop out']++;
        elseif (stripos($edu, 'ALS') !== false) $deped['ALS']++;
        else $deped['Unverified']++;

        // --- PESO ---
        if ($y['peso_general_status'] === 'Employed') $peso['Employed']++;
        if ($y['peso_general_status'] === 'Unemployed') $peso['Unemployed']++;
        if (!empty($y['peso_gov_program'])) $peso['In Gov Program']++;
        if (!empty($y['peso_skills_cert'])) $peso['Has Skills/Cert']++;

        // --- MCR ---
        if ($y['mcr_civil_status'] === 'Married') $mcr['Married']++;
        if ($y['mcr_vital_status'] === 'Deceased') $mcr['Deceased']++;

        // --- MSWDO ---
        if ($y['mswdo_4ps'] === 'Verified') $mswdo['4Ps']++;
        if ($y['mswdo_pwd'] === 'Verified') $mswdo['PWD']++;
        if ($y['mswdo_solo_parent'] === 'Verified') $mswdo['Solo Parent']++;
        if ($y['mswdo_indigent'] === 'Verified') $mswdo['Indigent']++;

        // --- MAYOR (Scholarship) ---
        if ($y['mayor_scholarship'] === 'Approved Scholar') $mayor['Scholars']++;
        if ($y['mayor_scholarship'] === 'Pending Review') $mayor['Pending']++;
        if ($y['mayor_scholarship'] === 'Graduated') $mayor['Graduated']++;

        // --- BPLO ---
        if ($y['bplo_business_owner'] === 'Yes') $bplo['Business Owners']++;
        
        // --- MHO (Health) ---
        if (strtolower($y['sex'] ?? '') === 'female') {
            if ($y['mho_pregnancy_status'] === 'Pregnant' || $y['mho_pregnancy_status'] === 'Post-Partum / Young Mother') {
                $mho['Pregnant / Post-Partum']++;
            } elseif ($y['mho_pregnancy_status'] === 'At Risk / Monitoring') {
                $mho['At Risk']++;
            }
        }
    }

    $specialized_assistance = $mswdo['4Ps'] + $mswdo['PWD'] + $mayor['Scholars'] + $bplo['Business Owners'];

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Department Monitor | TALA OS</title>
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
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { 
                        brand: { 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d' } 
                    }
                } 
            } 
        }
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
        
        .tap-effect:active { transform: scale(0.96); opacity: 0.85; transition: transform 0.1s; }

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
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300 flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden">

    <!-- STANDARD TALA OS ADMIN SIDEBAR -->
    <?php include 'sidebar_admin.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA (Fully Responsive & Gracefully Scrollable) -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px and fits viewport smoothly -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-between min-h-0 space-y-2.5 md:space-y-3">
            
            <!-- COMMAND CENTER HEADER WITH THREE UNIFIED GREEN KPI BADGES -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                        Department Monitor
                    </h1>
                </div>
                
                <!-- Section Cards: 3 KPI Badges in Unified Solid TALA Green Icon Styling -->
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Total Monitored Population -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-users-three text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Monitored Youth</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_population) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Active Inter-Agencies -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-buildings text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Agencies</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight">6 Portals</span>
                        </div>
                    </div>

                    <!-- KPI 3: Specialized Assistance -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-seal-check text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Specialized Aid</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($specialized_assistance) ?></span>
                        </div>
                    </div>

                    <!-- Header Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[36px] h-[36px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
                        <i id="headerThemeIcon" class="ph-bold ph-moon text-base sm:text-lg transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- FILTER, SEARCH & ACTION CONTROL BAR (Matching manage_disclosure.php) -->
            <div class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-2.5 flex-shrink-0">
                <!-- Sector Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full xl:w-auto custom-scrollbar py-0.5 flex-shrink-0" id="filterPillsContainer">
                    <button type="button" onclick="setAgencyFilter('all', this)" class="filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect btn-yellow-glow-r bg-[#15803d] text-white">
                        <span class="relative z-10">All Sectors</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-white/20 text-white relative z-10">6</span>
                    </button>
                    <button type="button" onclick="setAgencyFilter('education', this)" class="filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white">
                        <span class="relative z-10">Education & Grants</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300 relative z-10">2</span>
                    </button>
                    <button type="button" onclick="setAgencyFilter('employment', this)" class="filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white">
                        <span class="relative z-10">Livelihood & Business</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300 relative z-10">2</span>
                    </button>
                    <button type="button" onclick="setAgencyFilter('welfare', this)" class="filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white">
                        <span class="relative z-10">Social Welfare</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300 relative z-10">1</span>
                    </button>
                    <button type="button" onclick="setAgencyFilter('health-registry', this)" class="filter-pill inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white">
                        <span class="relative z-10">Health & Registry</span>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300 relative z-10">2</span>
                    </button>
                </div>

                <!-- Real-time Search Input -->
                <div class="flex items-center gap-2 w-full xl:w-auto">
                    <div class="relative w-full sm:w-auto sm:flex-1 md:w-64 lg:w-72">
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" id="agencySearchInput" placeholder="Search agency, metric, or status..." oninput="handleAgencySearch(this.value)" class="w-full pl-8 pr-3 py-1.5 bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 rounded-xl text-xs font-semibold focus:outline-none transition-colors border-0">
                    </div>

                    <a href="dashboard.php" class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold text-xs tap-effect flex items-center gap-1.5 transition-all flex-shrink-0">
                        <i class="ph-bold ph-squares-four text-xs"></i>
                        <span>Admin Hub</span>
                    </a>
                </div>
            </div>

            <!-- 6-DEPARTMENT CONSOLIDATED GRID -->
            <div id="agencyCardsContainer" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2.5 md:gap-3 flex-1 min-h-0 xl:[grid-auto-rows:1fr]">

                <!-- 1. DEPED CARD -->
                <div class="agency-card glass-card card-green-glow-bl p-3.5 md:p-4 rounded-2xl flex flex-col h-full min-h-[200px] md:min-h-[220px] xl:min-h-0 transition-all duration-200 hover:-translate-y-0.5 group" data-sector="education" data-keywords="deped education school enrolled drop outs als basic learners">
                    <div class="flex justify-between items-center mb-2 md:mb-2.5 flex-shrink-0">
                        <div class="flex items-center gap-2.5 min-w-0 pr-2">
                            <div class="w-8 h-8 md:w-9 md:h-9 bg-slate-100 dark:bg-zinc-800 rounded-xl flex items-center justify-center transition-colors flex-shrink-0">
                                <img src="deped.png" alt="DepEd Logo" class="w-5 h-5 md:w-6 md:h-6 object-contain" style="filter: none !important; -webkit-filter: none !important;">
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm md:text-base font-extrabold text-slate-900 dark:text-white tracking-tight transition-colors leading-tight truncate">DepEd Portal</h2>
                                <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium truncate">Basic Education Tracking</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <button type="button" onclick="inspectAgency('deped')" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 text-xs font-bold transition-all tap-effect" title="Inspect Telemetry">
                                <i class="ph-bold ph-chart-bar"></i>
                            </button>
                            <a href="deped_portal.php" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold transition-all text-xs uppercase tracking-wider px-3 py-1.5 rounded-lg flex items-center justify-center tap-effect whitespace-nowrap shadow-none">Access</a>
                        </div>
                    </div>
                    <div class="flex-1 grid grid-cols-2 gap-2 min-h-0">
                        <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 transition-colors truncate">Enrolled</p>
                            <p class="text-lg md:text-2xl font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($deped['Enrolled']) ?></p>
                        </div>
                        <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 transition-colors truncate">Drop Outs</p>
                            <p class="text-lg md:text-2xl font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($deped['Drop out']) ?></p>
                        </div>
                        <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 transition-colors truncate">ALS Learners</p>
                            <p class="text-lg md:text-2xl font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($deped['ALS']) ?></p>
                        </div>
                        <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 transition-colors truncate">Unverified</p>
                            <p class="text-lg md:text-2xl font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($deped['Unverified']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- 2. PESO CARD -->
                <div class="agency-card glass-card card-green-glow-bl p-3.5 md:p-4 rounded-2xl flex flex-col h-full min-h-[200px] md:min-h-[220px] xl:min-h-0 transition-all duration-200 hover:-translate-y-0.5 group" data-sector="employment" data-keywords="peso employment job livelihood employed unemployed gov program skills certification tesda">
                    <div class="flex justify-between items-center mb-2 md:mb-2.5 flex-shrink-0">
                        <div class="flex items-center gap-2.5 min-w-0 pr-2">
                            <div class="w-8 h-8 md:w-9 md:h-9 bg-slate-100 dark:bg-zinc-800 rounded-xl flex items-center justify-center transition-colors flex-shrink-0">
                                <img src="peso.png" alt="PESO Logo" class="w-5 h-5 md:w-6 md:h-6 object-contain" style="filter: none !important; -webkit-filter: none !important;">
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm md:text-base font-extrabold text-slate-900 dark:text-white tracking-tight transition-colors leading-tight truncate">PESO Portal</h2>
                                <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium truncate">Employment & Livelihood</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <button type="button" onclick="inspectAgency('peso')" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 text-xs font-bold transition-all tap-effect" title="Inspect Telemetry">
                                <i class="ph-bold ph-chart-bar"></i>
                            </button>
                            <a href="peso_portal.php" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold transition-all text-xs uppercase tracking-wider px-3 py-1.5 rounded-lg flex items-center justify-center tap-effect whitespace-nowrap shadow-none">Access</a>
                        </div>
                    </div>
                    <div class="flex-1 grid grid-cols-2 gap-2 min-h-0">
                        <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 transition-colors truncate">Employed</p>
                            <p class="text-lg md:text-2xl font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($peso['Employed']) ?></p>
                        </div>
                        <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 transition-colors truncate">Unemployed</p>
                            <p class="text-lg md:text-2xl font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($peso['Unemployed']) ?></p>
                        </div>
                        <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 transition-colors truncate">Gov Programs</p>
                            <p class="text-lg md:text-2xl font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($peso['In Gov Program']) ?></p>
                        </div>
                        <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 transition-colors truncate">Skills / NC</p>
                            <p class="text-lg md:text-2xl font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($peso['Has Skills/Cert']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- 3. MSWDO CARD -->
                <div class="agency-card glass-card card-green-glow-bl p-3.5 md:p-4 rounded-2xl flex flex-col h-full min-h-[200px] md:min-h-[220px] xl:min-h-0 transition-all duration-200 hover:-translate-y-0.5 group" data-sector="welfare" data-keywords="mswdo social welfare 4ps pwd solo parent indigent assistance aid">
                    <div class="flex justify-between items-center mb-2 md:mb-2.5 flex-shrink-0">
                        <div class="flex items-center gap-2.5 min-w-0 pr-2">
                            <div class="w-8 h-8 md:w-9 md:h-9 bg-slate-100 dark:bg-zinc-800 rounded-xl flex items-center justify-center transition-colors flex-shrink-0">
                                <img src="mswdo.png" alt="MSWDO Logo" class="w-5 h-5 md:w-6 md:h-6 object-contain" style="filter: none !important; -webkit-filter: none !important;">
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm md:text-base font-extrabold text-slate-900 dark:text-white tracking-tight transition-colors leading-tight truncate">MSWDO Portal</h2>
                                <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium truncate">Social Welfare Assistance</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <button type="button" onclick="inspectAgency('mswdo')" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 text-xs font-bold transition-all tap-effect" title="Inspect Telemetry">
                                <i class="ph-bold ph-chart-bar"></i>
                            </button>
                            <a href="mswdo_portal.php" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold transition-all text-xs uppercase tracking-wider px-3 py-1.5 rounded-lg flex items-center justify-center tap-effect whitespace-nowrap shadow-none">Access</a>
                        </div>
                    </div>
                    <div class="flex-1 grid grid-cols-2 gap-2 min-h-0">
                        <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 transition-colors truncate">Verified 4Ps</p>
                            <p class="text-lg md:text-2xl font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($mswdo['4Ps']) ?></p>
                        </div>
                        <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 transition-colors truncate">Verified PWD</p>
                            <p class="text-lg md:text-2xl font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($mswdo['PWD']) ?></p>
                        </div>
                        <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 transition-colors truncate">Solo Parents</p>
                            <p class="text-lg md:text-2xl font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($mswdo['Solo Parent']) ?></p>
                        </div>
                        <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 transition-colors truncate">Indigents</p>
                            <p class="text-lg md:text-2xl font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($mswdo['Indigent']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- 4. MAYOR'S OFFICE CARD -->
                <div class="agency-card glass-card card-green-glow-bl p-3.5 md:p-4 rounded-2xl flex flex-col h-full min-h-[200px] md:min-h-[220px] xl:min-h-0 transition-all duration-200 hover:-translate-y-0.5 group" data-sector="education" data-keywords="mayor scholarship tertiary scholar college approved pending graduated vtb">
                    <div class="flex justify-between items-center mb-2 md:mb-2.5 flex-shrink-0">
                        <div class="flex items-center gap-2.5 min-w-0 pr-2">
                            <div class="w-8 h-8 md:w-9 md:h-9 bg-slate-100 dark:bg-zinc-800 rounded-xl flex items-center justify-center transition-colors flex-shrink-0">
                                <img src="vtb.png" alt="Mayor's Office Logo" class="w-5 h-5 md:w-6 md:h-6 object-contain" style="filter: none !important; -webkit-filter: none !important;">
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm md:text-base font-extrabold text-slate-900 dark:text-white tracking-tight transition-colors leading-tight truncate">Mayor's Office</h2>
                                <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium truncate">Tertiary Scholarship</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <button type="button" onclick="inspectAgency('mayor')" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 text-xs font-bold transition-all tap-effect" title="Inspect Telemetry">
                                <i class="ph-bold ph-chart-bar"></i>
                            </button>
                            <a href="mayor_portal.php" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold transition-all text-xs uppercase tracking-wider px-3 py-1.5 rounded-lg flex items-center justify-center tap-effect whitespace-nowrap shadow-none">Access</a>
                        </div>
                    </div>
                    <div class="flex-1 flex flex-col gap-2 min-h-0">
                        <div class="flex-1 flex justify-between items-center bg-slate-50/80 dark:bg-zinc-900/60 px-3 py-2 rounded-xl transition-colors min-h-0">
                            <div class="min-w-0 pr-2">
                                <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider transition-colors truncate">Active Scholars</p>
                                <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium truncate">Approved beneficiaries</p>
                            </div>
                            <p class="text-xl md:text-2xl font-black text-green-600 dark:text-green-500 transition-colors leading-none flex-shrink-0"><?= number_format($mayor['Scholars']) ?></p>
                        </div>
                        <div class="flex-1 grid grid-cols-2 gap-2 min-h-0">
                            <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                                <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider transition-colors truncate">Pending Apps</p>
                                <p class="text-lg md:text-xl font-black text-slate-900 dark:text-white transition-colors leading-none mt-0.5 truncate"><?= number_format($mayor['Pending']) ?></p>
                            </div>
                            <div class="bg-slate-50/80 dark:bg-zinc-900/60 p-2.5 rounded-xl transition-colors flex flex-col justify-center min-w-0">
                                <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider transition-colors truncate">Graduated</p>
                                <p class="text-lg md:text-xl font-black text-slate-900 dark:text-white transition-colors leading-none mt-0.5 truncate"><?= number_format($mayor['Graduated']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. MCR CARD -->
                <div class="agency-card glass-card card-green-glow-bl p-3.5 md:p-4 rounded-2xl flex flex-col h-full min-h-[200px] md:min-h-[220px] xl:min-h-0 transition-all duration-200 hover:-translate-y-0.5 group" data-sector="health-registry" data-keywords="mcr civil registry married wedding deceased vital status certificates">
                    <div class="flex justify-between items-center mb-2 md:mb-2.5 flex-shrink-0">
                        <div class="flex items-center gap-2.5 min-w-0 pr-2">
                            <div class="w-8 h-8 md:w-9 md:h-9 bg-slate-100 dark:bg-zinc-800 rounded-xl flex items-center justify-center transition-colors flex-shrink-0">
                                <i class="ph-bold ph-scroll text-xl text-green-600 dark:text-green-500"></i>
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-sm md:text-base font-extrabold text-slate-900 dark:text-white tracking-tight transition-colors leading-tight truncate">MCR Registry</h2>
                                <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium truncate">Civil Registry & Vital Stats</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <button type="button" onclick="inspectAgency('mcr')" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 text-xs font-bold transition-all tap-effect" title="Inspect Telemetry">
                                <i class="ph-bold ph-chart-bar"></i>
                            </button>
                            <a href="mcr_portal.php" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold transition-all text-xs uppercase tracking-wider px-3 py-1.5 rounded-lg flex items-center justify-center tap-effect whitespace-nowrap shadow-none">Access</a>
                        </div>
                    </div>
                    <div class="flex-1 flex flex-col gap-2 min-h-0">
                        <div class="flex-1 bg-slate-50/80 dark:bg-zinc-900/60 rounded-xl p-2.5 transition-colors flex flex-col justify-center min-w-0">
                            <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 transition-colors truncate">Legally Married</p>
                            <p class="text-2xl md:text-3xl font-black text-slate-900 dark:text-white tracking-tight transition-colors leading-none truncate"><?= number_format($mcr['Married']) ?></p>
                        </div>
                        <div class="flex-1 flex justify-between items-center bg-slate-50/80 dark:bg-zinc-900/60 px-3 py-2 rounded-xl transition-colors min-h-0">
                            <div class="min-w-0 pr-2">
                                <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider transition-colors truncate">Flagged Deceased</p>
                                <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium truncate">Vital status verification</p>
                            </div>
                            <p class="text-lg md:text-xl font-black text-slate-900 dark:text-white transition-colors leading-none flex-shrink-0"><?= number_format($mcr['Deceased']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- 6. MHO & BPLO COMBINED COLUMN -->
                <div class="agency-card flex flex-col gap-2.5 md:gap-3 h-full min-h-[200px] md:min-h-[220px] xl:min-h-0" data-sector="health-registry employment" data-keywords="mho health adolescent maternal pregnant at risk bplo business owner enterprise youth">
                    
                    <!-- MHO CARD -->
                    <div class="glass-card card-green-glow-bl p-3 md:p-3.5 rounded-2xl flex flex-col flex-1 min-h-[95px] md:min-h-[105px] xl:min-h-0 transition-all duration-200 hover:-translate-y-0.5">
                        <div class="flex justify-between items-center mb-1.5 flex-shrink-0">
                            <div class="flex items-center gap-2 min-w-0 pr-2">
                                <div class="w-7 h-7 bg-slate-100 dark:bg-zinc-800 rounded-lg flex items-center justify-center transition-colors flex-shrink-0">
                                    <img src="mho.png" alt="MHO Logo" class="w-4 h-4 md:w-5 md:h-5 object-contain" style="filter: none !important; -webkit-filter: none !important;">
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-xs md:text-sm font-extrabold text-slate-900 dark:text-white tracking-tight leading-tight transition-colors truncate">MHO Health</h2>
                                    <p class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium truncate">Adolescent Care</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <button type="button" onclick="inspectAgency('mho')" class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 text-[11px] font-bold transition-all tap-effect" title="Inspect">
                                    <i class="ph-bold ph-chart-bar"></i>
                                </button>
                                <a href="mho_portal.php" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold transition-all text-[10px] md:text-xs uppercase tracking-wider px-2.5 py-1 rounded-lg tap-effect flex-shrink-0 whitespace-nowrap shadow-none">Access</a>
                            </div>
                        </div>
                        <div class="flex-1 grid grid-cols-2 gap-2 min-h-0">
                            <div class="bg-slate-50/80 dark:bg-zinc-900/60 rounded-xl p-2 transition-colors flex flex-col justify-center min-w-0">
                                <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 truncate">Maternal</p>
                                <p class="text-base md:text-lg font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($mho['Pregnant / Post-Partum']) ?></p>
                            </div>
                            <div class="bg-slate-50/80 dark:bg-zinc-900/60 rounded-xl p-2 transition-colors flex flex-col justify-center min-w-0">
                                <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-0.5 truncate">At Risk</p>
                                <p class="text-base md:text-lg font-black text-slate-900 dark:text-white transition-colors leading-none truncate"><?= number_format($mho['At Risk']) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- BPLO CARD -->
                    <div class="glass-card card-green-glow-bl p-3 md:p-3.5 rounded-2xl flex flex-col flex-1 min-h-[95px] md:min-h-[105px] xl:min-h-0 transition-all duration-200 hover:-translate-y-0.5">
                        <div class="flex justify-between items-center mb-1.5 flex-shrink-0">
                            <div class="flex items-center gap-2 min-w-0 pr-2">
                                <div class="w-7 h-7 bg-slate-100 dark:bg-zinc-800 rounded-lg flex items-center justify-center transition-colors flex-shrink-0">
                                    <i class="ph-bold ph-storefront text-base text-green-600 dark:text-green-500"></i>
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-xs md:text-sm font-extrabold text-slate-900 dark:text-white tracking-tight leading-tight transition-colors truncate">BPLO Enterprise</h2>
                                    <p class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium truncate">Youth Business</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 flex-shrink-0">
                                <button type="button" onclick="inspectAgency('bplo')" class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 text-[11px] font-bold transition-all tap-effect" title="Inspect">
                                    <i class="ph-bold ph-chart-bar"></i>
                                </button>
                                <a href="bplo_portal.php" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold transition-all text-[10px] md:text-xs uppercase tracking-wider px-2.5 py-1 rounded-lg tap-effect flex-shrink-0 whitespace-nowrap shadow-none">Access</a>
                            </div>
                        </div>
                        <div class="flex-1 flex justify-between items-center bg-slate-50/80 dark:bg-zinc-900/60 px-3 py-2 rounded-xl transition-colors min-h-0">
                            <div class="min-w-0 pr-2">
                                <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider leading-tight truncate">Business Owners</p>
                                <p class="text-[11px] font-medium text-slate-400 dark:text-zinc-500 leading-tight truncate">Registered Youth</p>
                            </div>
                            <p class="text-lg md:text-2xl font-black text-green-600 dark:text-green-500 tracking-tight leading-none flex-shrink-0"><?= number_format($bplo['Business Owners']) ?></p>
                        </div>
                    </div>

                </div>

            </div>

            <!-- EMPTY SEARCH STATE (Matching manage_disclosure.php) -->
            <div id="agencyEmptyState" class="hidden flex-1 flex flex-col items-center justify-center p-6 text-center select-none glass-card rounded-2xl min-h-[300px]">
                <img src="mascot_question.png" alt="No matches" class="w-20 h-20 object-contain mb-3 opacity-80">
                <h3 class="text-sm md:text-base font-extrabold text-slate-900 dark:text-white mb-1">No Matching Department Records</h3>
                <p class="text-xs text-slate-400 dark:text-zinc-500 max-w-sm mb-4">No agencies or metrics match your search query. Try clearing your search term or selecting another sector.</p>
                <button type="button" onclick="resetAgencyFilters()" class="btn-yellow-glow-r px-4 py-2 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect">
                    Reset Filter & Search
                </button>
            </div>

        </div>
    </main>

    <!-- INTERACTIVE AGENCY INSPECTOR MODAL (Matching manage_disclosure.php #settingsModal) -->
    <div id="agencyModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeAgencyModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-lg w-full p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800 max-h-[85vh]" id="agencyModalBox">
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100 dark:border-zinc-800 flex-shrink-0">
                <div class="flex items-center gap-2.5">
                    <div id="modalAgencyIconContainer" class="w-8 h-8 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-bold ph-chart-line-up"></i>
                    </div>
                    <div>
                        <h3 id="modalAgencyTitle" class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight">Agency Dossier</h3>
                        <p id="modalAgencySubtitle" class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono mt-0.5">Telemetry Metrics Breakdown</p>
                    </div>
                </div>
                <button type="button" onclick="closeAgencyModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Content (Scrollable) -->
            <div class="space-y-3.5 overflow-y-auto custom-scrollbar flex-1 pr-1 text-xs">
                
                <!-- Summary Banner -->
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-zinc-800/60">
                    <p class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 mb-0.5">Overview & Mandate</p>
                    <p id="modalAgencyDesc" class="font-semibold text-slate-700 dark:text-zinc-300 leading-relaxed">-</p>
                </div>

                <!-- Metrics Breakdown Grid -->
                <div class="space-y-1.5" id="modalAgencyMetricsList">
                    <!-- Populated dynamically -->
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="pt-3 mt-3 flex items-center justify-between flex-shrink-0 border-t border-slate-100 dark:border-zinc-800">
                <a href="#" id="modalAgencyPortalLink" class="btn-yellow-glow-r px-4 py-2 bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold rounded-xl text-xs md:text-sm transition-all tap-effect flex items-center gap-1.5">
                    <i class="ph-bold ph-arrow-square-out"></i>
                    <span>Launch Agency Portal</span>
                </a>
                <button type="button" onclick="closeAgencyModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm transition-all tap-effect">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
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

        // Header Theme Toggle
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

        // Agency Dossier Data Store
        const agencyData = {
            deped: {
                title: "Department of Education (DepEd)",
                subtitle: "Basic Education Division",
                desc: "Monitors formal schooling enrollment status, Alternative Learning System (ALS) participation, and identifies youth vulnerable to dropping out.",
                portal: "deped_portal.php",
                metrics: [
                    { label: "Enrolled in Basic Education", val: "<?= number_format($deped['Enrolled']) ?>", note: "Active learners currently in school" },
                    { label: "Early School Leavers / Drop Outs", val: "<?= number_format($deped['Drop out']) ?>", note: "Out-of-school youth targeted for interventions" },
                    { label: "ALS Beneficiaries", val: "<?= number_format($deped['ALS']) ?>", note: "Enrolled in Alternative Learning System" },
                    { label: "Unverified Schooling Status", val: "<?= number_format($deped['Unverified']) ?>", note: "Pending educational records confirmation" }
                ]
            },
            peso: {
                title: "Public Employment Service Office (PESO)",
                subtitle: "Labor & Livelihood Bureau",
                desc: "Facilitates youth employment matching, government job training programs (GIP, SPES), and technical-vocational skills certifications.",
                portal: "peso_portal.php",
                metrics: [
                    { label: "Employed Youth", val: "<?= number_format($peso['Employed']) ?>", note: "Wage-employed or self-employed individuals" },
                    { label: "Unemployed Youth", val: "<?= number_format($peso['Unemployed']) ?>", note: "Active jobseekers and underemployed youth" },
                    { label: "Government Programs (SPES / GIP)", val: "<?= number_format($peso['In Gov Program']) ?>", note: "Participating in municipal training & apprenticeships" },
                    { label: "TESDA / NC Skills Certified", val: "<?= number_format($peso['Has Skills/Cert']) ?>", note: "Holding verified national skills qualifications" }
                ]
            },
            mswdo: {
                title: "Municipal Social Welfare and Development (MSWDO)",
                subtitle: "Social Protection & Welfare",
                desc: "Administers targeted assistance for vulnerable sectors including Pantawid Pamilya (4Ps), Persons with Disabilities (PWD), solo parents, and indigent families.",
                portal: "mswdo_portal.php",
                metrics: [
                    { label: "Verified 4Ps Beneficiaries", val: "<?= number_format($mswdo['4Ps']) ?>", note: "Covered under Pantawid Pamilyang Pilipino Program" },
                    { label: "Verified Youth with Disabilities (PWD)", val: "<?= number_format($mswdo['PWD']) ?>", note: "Registered with municipal disability council" },
                    { label: "Solo Parent Households", val: "<?= number_format($mswdo['Solo Parent']) ?>", note: "Single-headed youth households" },
                    { label: "Classified Indigent Youth", val: "<?= number_format($mswdo['Indigent']) ?>", note: "Eligible for local and national hardship relief" }
                ]
            },
            mayor: {
                title: "Office of the Municipal Mayor",
                subtitle: "Tertiary Education Assistance & Grants",
                desc: "Oversees local government financial aid, college scholarship grants, and academic incentive programs for deserving Tumauini students.",
                portal: "mayor_portal.php",
                metrics: [
                    { label: "Active Approved Scholars", val: "<?= number_format($mayor['Scholars']) ?>", note: "Currently receiving tuition & stipend grants" },
                    { label: "Pending Scholarship Applications", val: "<?= number_format($mayor['Pending']) ?>", note: "Under committee review and evaluation" },
                    { label: "Graduated Grant Recipients", val: "<?= number_format($mayor['Graduated']) ?>", note: "Successfully completed tertiary degrees" }
                ]
            },
            mcr: {
                title: "Municipal Civil Registrar (MCR)",
                subtitle: "Vital Statistics & Registry Records",
                desc: "Validates civil status declarations, registers legal marriages involving youth, and updates vital records to prevent registry anomalies.",
                portal: "mcr_portal.php",
                metrics: [
                    { label: "Legally Married Youth", val: "<?= number_format($mcr['Married']) ?>", note: "Registered civil or religious marriages" },
                    { label: "Deceased / Vital Status Flagged", val: "<?= number_format($mcr['Deceased']) ?>", note: "Registry records updated with death certificates" }
                ]
            },
            mho: {
                title: "Municipal Health Office (MHO)",
                subtitle: "Adolescent Care & Maternal Health",
                desc: "Provides teenage pregnancy monitoring, reproductive health education, and prenatal care for adolescent mothers in Tumauini.",
                portal: "mho_portal.php",
                metrics: [
                    { label: "Maternal & Young Mothers", val: "<?= number_format($mho['Pregnant / Post-Partum']) ?>", note: "Currently receiving prenatal or post-partum care" },
                    { label: "Health Monitoring / At-Risk", val: "<?= number_format($mho['At Risk']) ?>", note: "Under clinical guidance and follow-up" }
                ]
            },
            bplo: {
                title: "Business Permits & Licensing Office (BPLO)",
                subtitle: "Youth Commercial Enterprise",
                desc: "Registers youth-owned business enterprises, micro-franchises, and commercial licenses operating within the municipality.",
                portal: "bplo_portal.php",
                metrics: [
                    { label: "Registered Youth Business Owners", val: "<?= number_format($bplo['Business Owners']) ?>", note: "Possessing active municipal mayor's permits" }
                ]
            }
        };

        // Filter and Search State
        let currentSector = 'all';
        let currentSearch = '';

        function setAgencyFilter(sector, btn) {
            currentSector = sector;
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
            filterCards();
        }

        function handleAgencySearch(query) {
            currentSearch = query.toLowerCase().trim();
            filterCards();
        }

        function resetAgencyFilters() {
            document.getElementById('agencySearchInput').value = '';
            currentSearch = '';
            const allBtn = document.querySelector('#filterPillsContainer .filter-pill');
            setAgencyFilter('all', allBtn);
        }

        function filterCards() {
            const cards = document.querySelectorAll('#agencyCardsContainer .agency-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const sector = card.getAttribute('data-sector') || '';
                const keywords = (card.getAttribute('data-keywords') || '').toLowerCase();
                const cardText = card.textContent.toLowerCase();

                const matchSector = (currentSector === 'all') || sector.includes(currentSector);
                const matchSearch = (!currentSearch) || keywords.includes(currentSearch) || cardText.includes(currentSearch);

                if (matchSector && matchSearch) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            const emptyState = document.getElementById('agencyEmptyState');
            if (visibleCount === 0) {
                emptyState.classList.remove('hidden');
                emptyState.classList.add('flex');
            } else {
                emptyState.classList.add('hidden');
                emptyState.classList.remove('flex');
            }
        }

        // Inspector Modal Handlers
        function inspectAgency(agencyKey) {
            const data = agencyData[agencyKey];
            if (!data) return;

            document.getElementById('modalAgencyTitle').textContent = data.title;
            document.getElementById('modalAgencySubtitle').textContent = data.subtitle;
            document.getElementById('modalAgencyDesc').textContent = data.desc;
            document.getElementById('modalAgencyPortalLink').href = data.portal;

            const list = document.getElementById('modalAgencyMetricsList');
            list.innerHTML = '';
            data.metrics.forEach(m => {
                const item = document.createElement('div');
                item.className = 'p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-800/40 flex items-center justify-between gap-3';
                item.innerHTML = `
                    <div class="min-w-0">
                        <p class="font-bold text-slate-800 dark:text-zinc-200 text-xs truncate">${m.label}</p>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-medium truncate">${m.note}</p>
                    </div>
                    <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-none flex-shrink-0">${m.val}</span>
                `;
                list.appendChild(item);
            });

            const modal = document.getElementById('agencyModalOverlay');
            const box = document.getElementById('agencyModalBox');
            if (modal && box) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                requestAnimationFrame(() => {
                    modal.classList.remove('opacity-0');
                    modal.classList.add('opacity-100');
                    box.classList.remove('scale-95', 'opacity-0');
                    box.classList.add('scale-100', 'opacity-100');
                });
            }
        }

        function closeAgencyModal() {
            const modal = document.getElementById('agencyModalOverlay');
            const box = document.getElementById('agencyModalBox');
            if (modal && box) {
                modal.classList.remove('opacity-100');
                modal.classList.add('opacity-0');
                box.classList.remove('scale-100', 'opacity-100');
                box.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }, 250);
            }
        }

        // Close on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAgencyModal();
            }
        });
    </script>

    <!-- Genesis AI Assistant Widget -->
    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>