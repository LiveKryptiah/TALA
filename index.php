<?php
$announcements = [];
$events_preview = [];
$lydo_officials = [];
$sk_officials = [];
$total_youth = 0;
$sk_barangays_count = 0;
$db_connected = false;

// Define the barangays manually to prevent undefined variable errors
$all_barangays = [
    "Annafunan", "Antagan I", "Antagan II", "Arcon", "Balug", "Banig", "Bantug", 
    "Barangay District 1 (Poblacion)", "Barangay District 2 (Poblacion)", "Barangay District 3 (Poblacion)", 
    "Barangay District 4 (Poblacion)", "Bayabo East", "Caligayan", "Camasi", "Carpentero", 
    "Compania", "Cumabao", "Fermeldy (Hacienda San Francisco)", "Fugu Abajo", "Fugu Norte", 
    "Fugu Sur", "Lalauanan", "Lanna", "Lapogan", "Lingaling (Poblacion)", "Liwanag", 
    "Malamag East", "Malamag West", "Maligaya (Poblacion)", "Minanga", "Moldero", "Namnama", 
    "Paragu", "Pilitan", "San Mateo", "San Pedro", "San Vicente", "Santa", "Santa Catalina", 
    "Santa Visitacion (Maggayu)", "Santo Niño", "Sinippil", "Sisim Abajo", "Sisim Alto", "Tunggui", "Ugad"
];

try {
    require 'db_connect.php';
    $db_connected = true;

    $latest_highlight = $pdo->query("SELECT * FROM highlights ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    // Fetch Dynamic Featured Highlights
    $highlight1 = $pdo->query("SELECT * FROM featured_highlight WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    $highlight2 = $pdo->query("SELECT * FROM featured_highlight WHERE id = 2")->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT * FROM bento_items ORDER BY id DESC");
    $bento_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Active Pop-up Notification
    $popup = $pdo->query("SELECT * FROM popup_notification WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
   
    // 2. Fetch Updates & Events
    $announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    $events_preview = $pdo->query("SELECT *, (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id) as current_count FROM events e WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    
    // 1. Fetch Stats: Real-time youth registry telemetry
    $total_youth = (int)$pdo->query("SELECT COUNT(*) FROM youth_registry")->fetchColumn();
    if ($total_youth === 0) {
        $total_youth = (int)$pdo->query("SELECT COUNT(*) FROM youth_profiles")->fetchColumn();
    }
    $sk_barangays_count = $pdo->query("SELECT COUNT(DISTINCT barangay_assigned) FROM admins WHERE role = 'sub-admin' AND status = 'approved'")->fetchColumn();

    // 3. Fetch Leaders (LYDO & SK Chairpersons Only)
    $lydo_officials = $pdo->query("SELECT full_name, profile_picture FROM admins WHERE role = 'admin' AND status = 'approved' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    $sk_raw = $pdo->query("SELECT full_name, barangay_assigned, sk_position, profile_picture FROM admins WHERE role = 'sub-admin' AND status = 'approved' AND sk_position = 'SK Chairperson' ORDER BY barangay_assigned ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sk_raw as $sk) {
        $sk_officials[$sk['barangay_assigned']][] = $sk;
    }

    // Fetch Active Scholarships
    $scholarships = $pdo->query("SELECT * FROM scholarships WHERE deadline >= CURDATE() ORDER BY deadline ASC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

    // --- COMMUNITY VOICE SUBMISSION LOGIC ---
    $suggestion_success = false;
    $generated_ticket_id = '';
    
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_suggestion'])) {
        $name = !empty(trim($_POST['name'])) ? trim($_POST['name']) : 'Anonymous';
        $contact = !empty(trim($_POST['contact'])) ? trim($_POST['contact']) : 'None provided';
        $category = $_POST['category'];
        $barangay = $_POST['barangay'] ?? 'Unspecified';
        $message = trim($_POST['message']);
        
        // Generate a unique tracking ID
        $ticket_id = 'TALA-' . date('ym') . '-' . strtoupper(substr(md5(uniqid()), 0, 5));

        // Auto-add columns if they don't exist yet
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM suggestions")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('ticket_id', $cols)) {
                $pdo->exec("ALTER TABLE suggestions ADD COLUMN ticket_id VARCHAR(50) NULL AFTER id");
                $pdo->exec("ALTER TABLE suggestions ADD COLUMN barangay VARCHAR(100) NULL AFTER contact_info");
            }
        } catch (Exception $e) {}

        $stmt = $pdo->prepare("INSERT INTO suggestions (ticket_id, sender_name, contact_info, barangay, category, message) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$ticket_id, $name, $contact, $barangay, $category, $message]);
        
        $suggestion_success = true;
        $generated_ticket_id = $ticket_id;
    }

} catch (Exception $e) { $db_connected = false; }

// --- CALENDAR DATA FETCH ---
try {
    $cal_query = "SELECT id, title, event_date, location, status, image_path FROM events ORDER BY event_date ASC";
    $cal_stmt = $pdo->query($cal_query);
    $tala_calendar_events = $cal_stmt->fetchAll(PDO::FETCH_ASSOC);
    $tala_events_json = json_encode($tala_calendar_events);
} catch (Exception $e) {
    $tala_events_json = '[]';
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALA | Tumauini Active Youth Ledger & Analytics</title>
    <link rel="icon" type="image/ico" href="TALA-LOGO.ico">
    
    <!-- ANTI-FLASH SCRIPT -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <!-- Fonts & Tailwind CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- Tailwind Configuration -->
    <script>
        tailwind.config = { 
            darkMode: 'class',
            theme: { 
                extend: { 
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: { 
                        brand: { 
                            50: '#f0fdf4', 
                            100: '#dcfce7', 
                            200: '#bbf7d0', 
                            400: '#4ade80',
                            500: '#22c55e', 
                            600: '#16a34a', 
                            700: '#15803d', 
                            800: '#166534', 
                            900: '#14532d',
                        } 
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
        .dark .btn-gradient,
        .dark .btn-yellow-glow-r,
        .dark button.bg-\[\#15803d\],
        .dark button.bg-\[\#16a34a\],
        .dark a.bg-\[\#15803d\],
        .dark a.bg-\[\#16a34a\] {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 35%, #eab308 100%) !important;
            color: #ffffff !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35) !important;
            box-shadow: none !important;
        }
        .dark .btn-gradient:hover,
        .dark .btn-yellow-glow-r:hover,
        .dark button.bg-\[\#15803d\]:hover,
        .dark button.bg-\[\#16a34a\]:hover,
        .dark a.bg-\[\#15803d\]:hover,
        .dark a.bg-\[\#16a34a\]:hover {
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


        /* Calendar Specific Styles */
        .cal-cell { min-height: 120px; transition: all 0.2s ease; }
        .cal-cell:hover { background-color: rgba(241, 245, 249, 0.8); }
        .dark .cal-cell:hover { background-color: rgba(39, 39, 42, 0.4); }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        
        /* CONTINUOUS SCROLLING GALLERY (INFINITE MARQUEE) */
        @keyframes scrollMarquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); } 
        }
        
        .marquee-track {
            display: flex;
            width: max-content;
            animation: scrollMarquee 40s linear infinite; 
        }
        .marquee-track:hover {
            animation-play-state: paused;
        }
        .fade-edges {
            -webkit-mask-image: linear-gradient(to right, transparent, black 10%, black 90%, transparent);
            mask-image: linear-gradient(to right, transparent, black 10%, black 90%, transparent);
        }

        /* MODERN BLUR-IN SCROLL ANIMATION (Apple/Linear Soft Focus Transition) */
        .scroll-animate {
            opacity: 0;
            filter: blur(16px);
            -webkit-filter: blur(16px);
            transform: scale(0.985) translateY(12px);
            transition: opacity 0.85s cubic-bezier(0.16, 1, 0.3, 1), 
                        filter 0.85s cubic-bezier(0.16, 1, 0.3, 1), 
                        -webkit-filter 0.85s cubic-bezier(0.16, 1, 0.3, 1), 
                        transform 0.85s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: opacity, filter, transform;
        }
        .scroll-animate.show {
            opacity: 1;
            filter: blur(0px);
            -webkit-filter: blur(0px);
            transform: scale(1) translateY(0);
        }
        .delay-100 { transition-delay: 100ms; }
        .delay-200 { transition-delay: 200ms; }
        .delay-300 { transition-delay: 300ms; }
        
        .hide-scrollbar::-webkit-scrollbar { display: none; }

        /* CUSTOM LOGO BREATHING ANIMATION */
        @keyframes breath {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        .animate-breath {
            animation: breath 2s infinite ease-in-out;
        }
        #changingText {
            transition: all 0.3s ease;
            display: inline-block;
            min-width: 220px;
        }

        /* Native app tap effect */
        .tap-effect:active { transform: scale(0.97); opacity: 0.85; transition: transform 0.1s; }

        /* Zero Image Filters */
        img {
            filter: none !important;
            -webkit-filter: none !important;
        }
    </style>
</head>
<body class="bg-white dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 font-sans antialiased selection:bg-slate-900 selection:text-white dark:selection:bg-white dark:selection:text-zinc-900 transition-colors duration-300">

    <!-- TALA LOADING SCREEN -->
    <div id="tala-loader" class="fixed inset-0 z-[9999] bg-white dark:bg-zinc-950 flex flex-col items-center justify-center transition-opacity duration-700 ease-in-out">
        <div class="relative flex flex-col items-center">
            <img src="TALA-LOGO.ico" alt="TALA Logo" class="w-32 h-32 object-contain animate-breath relative z-10">
        </div>
    </div>

    <!-- TOP CONTACT BAR -->
    <div id="top-contact-bar" class="bg-white dark:bg-zinc-950 text-slate-700 dark:text-zinc-300 py-1.5 px-6 lg:px-8 text-[11px] font-bold tracking-wider uppercase flex justify-between sm:justify-end items-center gap-6 relative z-[60] transition-all duration-300 hero-nav-blend">
        <!-- Dark Mode Toggle Button -->
        <button onclick="toggleTheme()" id="theme-toggle" class="hover:text-green-600 dark:hover:text-green-500 transition-colors flex items-center gap-1.5 focus:outline-none tap-effect" title="Toggle Dark Mode">
            <i id="theme-icon" class="ph-fill ph-moon text-sm"></i> <span class="hidden sm:inline">Theme</span>
        </button>
        
        <div class="flex items-center gap-6">
            <a href="tel:+639982731960" class="flex items-center gap-1.5 hover:text-green-600 dark:hover:text-green-500 transition-colors">
                <i class="ph-fill ph-phone text-sm icon-gradient"></i> <span class="hidden sm:inline">+63 998 273 1960</span>
            </a>
            <a href="mailto:lydotumauini@gmail.com" class="flex items-center gap-1.5 hover:text-green-600 dark:hover:text-green-500 transition-colors">
                <i class="ph-fill ph-envelope-simple text-sm icon-gradient"></i> <span class="hidden sm:inline">lydotumauini@gmail.com</span>
            </a>
        </div>
    </div>

    <!-- UNIFIED NAVIGATION (Loaded below contact bar) -->
    <?php include 'navigation.php'; ?>
    
    <!-- MODERN HERO SECTION (VIDEO BACKGROUND) -->
    <section id="hero-section" class="relative min-h-[90vh] lg:min-h-screen flex items-center overflow-hidden bg-slate-950 -mt-[112px] pt-[112px]">
        <!-- Fullscreen Background Video -->
        <video autoplay loop muted playsinline class="absolute inset-0 w-full h-full object-cover z-0 opacity-100 filter brightness-110 contrast-[1.02]">
            <source src="herobg.mp4" type="video/mp4">
        </video>
        <div class="absolute inset-0 bg-gradient-to-b from-slate-950/35 via-slate-950/20 to-slate-950/55 z-[1]"></div>

        <div class="relative z-10 max-w-5xl mx-auto px-6 text-center py-20">
            <!-- Status Badge -->
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 backdrop-blur-md text-xs font-bold uppercase tracking-widest mb-6 text-white">
                <span class="flex h-2.5 w-2.5 relative">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-500 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-green-500"></span>
                </span>
                Municipal Youth Development Office
            </div>

            <!-- Headline -->
            <h1 class="text-4xl md:text-6xl xl:text-7xl font-black text-white leading-tight tracking-tight mb-6">
                Empowering the Youth of <br>
                <span id="changingText" class="text-gradient font-black">
                    Tumauini
                </span>
            </h1>

            <!-- Centered Description -->
            <p class="text-base sm:text-lg lg:text-xl text-slate-200 leading-relaxed max-w-2xl mx-auto mb-10 font-medium">
                TALA is the official centralized database, analytics dashboard, and event management platform designed to shape the future of our community.
            </p>

            <!-- Centered Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <a href="public_registration.php" class="btn-gradient font-bold px-8 py-4 rounded-xl flex items-center justify-center transition-all shadow-none tap-effect">
                    Register Profile
                    <i class="ph-bold ph-arrow-right ml-2 text-lg"></i>
                </a>

                <a href="public_events.php" class="bg-white/10 backdrop-blur-md hover:bg-white/20 text-white font-bold px-8 py-4 rounded-xl transition-all flex items-center justify-center tap-effect">
                    <i class="ph-fill ph-calendar-star mr-2 text-xl icon-gradient"></i>
                    View Events
                </a>
            </div>

            <!-- Social Proof with Real-Time Telemetry Count -->
            <div class="mt-12 flex items-center justify-center gap-4">
                <div class="flex -space-x-3">
                    <img class="w-10 h-10 rounded-full object-cover" src="about.jpg" alt="Youth">
                    <img class="w-10 h-10 rounded-full object-cover" src="hero2.jpg" alt="Youth">
                    <img class="w-10 h-10 rounded-full object-cover" src="hero3.jpg" alt="Youth">
                </div>
                <p class="text-sm text-slate-300 font-medium">
                    Join <span class="text-white font-black text-base"><?= number_format($total_youth) ?>+</span> registered youth today.
                </p>
            </div>
        </div>
    </section>

    <!-- UNIFIED SPOTLIGHT SECTION -->
    <?php if ($highlight1 || $highlight2): ?>
    <section id="featured-highlights" class="relative overflow-hidden bg-white dark:bg-zinc-950 py-20 transition-colors duration-300">
        
        <div class="relative z-10 max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex flex-col gap-20 lg:gap-28">
                
                <!-- HIGHLIGHT 1 -->
                <?php if ($highlight1): ?>
                <div class="grid lg:grid-cols-2 items-center gap-10 lg:gap-16 scroll-animate">
                    <div class="flex justify-center items-center order-1">
                        <img src="<?= htmlspecialchars($highlight1['image_path']) ?>" alt="Featured Highlight 1" class="max-h-[350px] lg:max-h-[450px] w-auto object-contain hover:scale-105 transition duration-500 rounded-2xl">
                    </div>

                    <div class="text-center lg:text-left order-2">
                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 dark:text-white leading-tight mb-4 tracking-tight">
                            <?= htmlspecialchars($highlight1['title_main']) ?>
                            <span class="block text-gradient mt-1"><?= htmlspecialchars($highlight1['title_highlight']) ?></span>
                        </h2>

                        <p class="text-slate-600 dark:text-zinc-300 text-lg leading-relaxed max-w-2xl mx-auto lg:mx-0 mb-8 font-medium">
                            <?= nl2br(htmlspecialchars($highlight1['description'])) ?>
                        </p>

                        <a href="<?= htmlspecialchars($highlight1['button_link']) ?>" class="btn-gradient inline-flex items-center px-8 py-3.5 rounded-xl font-bold transition-all shadow-none tap-effect">
                            <?= htmlspecialchars($highlight1['button_text']) ?> <i class="ph-bold ph-arrow-right ml-2"></i>
                        </a>

                        <?php if (!empty($highlight1['stat_label']) && !empty($highlight1['stat_value'])): ?>
                        <div class="mt-10 flex justify-center lg:justify-start">
                            <div class="inline-flex items-center gap-4 px-6 py-4 rounded-2xl bg-slate-50 dark:bg-zinc-900 shadow-sm">
                                <div class="w-12 h-12 flex items-center justify-center text-3xl">
                                    <i class="ph-fill ph-trophy icon-gradient"></i>
                                </div>
                                <div class="text-left">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-zinc-500"><?= htmlspecialchars($highlight1['stat_label']) ?></p>
                                    <p class="text-xl font-black text-slate-900 dark:text-white"><?= htmlspecialchars($highlight1['stat_value']) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- HIGHLIGHT 2 -->
                <?php if ($highlight2): ?>
                <div class="grid lg:grid-cols-2 items-center gap-10 lg:gap-16 scroll-animate">
                    <div class="text-center lg:text-left order-2 lg:order-1">
                        <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 dark:text-white leading-tight mb-4 tracking-tight">
                            <?= htmlspecialchars($highlight2['title_main']) ?>
                            <span class="block text-gradient mt-1"><?= htmlspecialchars($highlight2['title_highlight']) ?></span>
                        </h2>

                        <p class="text-slate-600 dark:text-zinc-300 text-lg leading-relaxed max-w-2xl mx-auto lg:mx-0 mb-8 font-medium">
                            <?= nl2br(htmlspecialchars($highlight2['description'])) ?>
                        </p>

                        <a href="<?= htmlspecialchars($highlight2['button_link']) ?>" class="btn-gradient inline-flex items-center px-8 py-3.5 rounded-xl font-bold transition-all shadow-none tap-effect">
                            <?= htmlspecialchars($highlight2['button_text']) ?> <i class="ph-bold ph-arrow-right ml-2"></i>
                        </a>

                        <?php if (!empty($highlight2['stat_label']) && !empty($highlight2['stat_value'])): ?>
                        <div class="mt-10 flex justify-center lg:justify-start">
                            <div class="inline-flex items-center gap-4 px-6 py-4 rounded-2xl bg-slate-50 dark:bg-zinc-900 shadow-sm">
                                <div class="w-12 h-12 flex items-center justify-center text-3xl">
                                    <i class="ph-fill ph-chart-line-up icon-gradient"></i>
                                </div>
                                <div class="text-left">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-zinc-500"><?= htmlspecialchars($highlight2['stat_label']) ?></p>
                                    <p class="text-xl font-black text-slate-900 dark:text-white"><?= htmlspecialchars($highlight2['stat_value']) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="flex justify-center items-center order-1 lg:order-2">
                        <img src="<?= htmlspecialchars($highlight2['image_path']) ?>" alt="Featured Highlight 2" class="max-h-[350px] lg:max-h-[450px] w-auto object-contain hover:scale-105 transition duration-500 rounded-2xl">
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- LATEST UPDATES SECTION -->
    <div id="updates" class="py-24 bg-white dark:bg-zinc-950 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-12 scroll-animate gap-4">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    <span class="text-gradient">Latest Updates</span>
                </h2>
                <a href="updates.php" class="text-green-600 dark:text-green-500 font-bold hover:underline text-sm flex items-center gap-1 transition-colors">View All Updates <i class="ph-bold ph-arrow-right"></i></a>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if (count($announcements) > 0): ?>
                    <?php foreach ($announcements as $index => $ann): ?>
                        <div class="relative h-[400px] rounded-[2rem] overflow-hidden group transition-all duration-300 scroll-animate" style="transition-delay: <?= $index * 100 ?>ms;">
                            <?php if (!empty($ann['image_path'])): ?>
                                <img src="<?= htmlspecialchars($ann['image_path']) ?>" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                            <?php else: ?>
                                <div class="absolute inset-0 w-full h-full bg-slate-800 flex items-center justify-center text-slate-600 text-6xl">
                                    <i class="ph-fill ph-image"></i>
                                </div>
                            <?php endif; ?>

                            <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-transparent opacity-90 group-hover:opacity-100 transition-opacity duration-300"></div>

                            <div class="absolute inset-0 flex flex-col justify-end p-8">
                                <div class="transform transition-transform duration-500 group-hover:-translate-y-2">
                                    <div class="flex items-center gap-3 text-[11px] font-bold uppercase tracking-widest mb-3">
                                        <span class="text-green-400 flex items-center"><i class="ph-bold ph-calendar-blank mr-1.5"></i> <?= date('M j, Y', strtotime($ann['created_at'])) ?></span>
                                        <span class="text-slate-400 truncate">• <?= htmlspecialchars($ann['created_by']) ?></span>
                                    </div>
                                    <h3 class="text-2xl font-extrabold text-white mb-3 leading-snug drop-shadow-md line-clamp-2">
                                        <?= htmlspecialchars($ann['title']) ?>
                                    </h3>
                                    <p class="text-slate-300 text-sm leading-relaxed line-clamp-3">
                                        <?= htmlspecialchars($ann['content']) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full bg-slate-50 dark:bg-zinc-900 p-12 rounded-[2rem] text-center text-slate-500 dark:text-zinc-400 scroll-animate shadow-sm">
                        No public announcements at this time.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- UPCOMING EVENTS SECTION -->
    <div id="events" class="py-24 bg-white dark:bg-zinc-950 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-12 scroll-animate gap-4">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    <span class="text-gradient">Upcoming Events</span>
                </h2>
                <a href="public_events.php" class="text-green-600 dark:text-green-500 font-bold hover:underline text-sm flex items-center gap-1 transition-colors">View All Events <i class="ph-bold ph-arrow-right"></i></a>
            </div>

            <div class="space-y-8">
                <?php if (count($events_preview) > 0): ?>
                    <?php foreach ($events_preview as $index => $ev): ?>
                        <div class="bg-slate-50 dark:bg-zinc-900 rounded-[2rem] overflow-hidden flex flex-col md:flex-row transition scroll-animate shadow-sm" style="transition-delay: <?= $index * 100 ?>ms;">
                            <div class="md:w-2/5 h-64 md:h-auto bg-slate-100 dark:bg-zinc-800 relative">
                                <?php if (!empty($ev['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($ev['image_path']) ?>" class="w-full h-full object-cover absolute inset-0">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-slate-900 text-white text-center flex-col absolute inset-0">
                                        <span class="text-sm font-bold text-slate-400 uppercase tracking-widest"><?= date('F', strtotime($ev['event_date'])) ?></span>
                                        <span class="text-6xl font-extrabold"><?= date('d', strtotime($ev['event_date'])) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-8 md:p-10 md:w-3/5 flex flex-col justify-center bg-slate-50 dark:bg-zinc-900">
                                <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white leading-tight mb-4 tracking-tight"><?= htmlspecialchars($ev['title']) ?></h3>
                                <p class="text-sm sm:text-base text-slate-600 dark:text-zinc-300 mb-6 leading-relaxed font-medium"><?= htmlspecialchars($ev['details']) ?></p>
                                
                                <div class="flex flex-wrap items-center gap-4 text-sm font-bold text-slate-600 dark:text-zinc-300 mb-8">
                                    <span class="flex items-center bg-white dark:bg-zinc-950 px-3 py-1.5 rounded-lg shadow-sm"><i class="ph-bold ph-clock text-green-600 dark:text-green-500 mr-2 text-lg"></i> <?= date('g:i A', strtotime($ev['event_date'])) ?></span>
                                    <span class="flex items-center bg-white dark:bg-zinc-950 px-3 py-1.5 rounded-lg shadow-sm"><i class="ph-bold ph-map-pin text-green-600 dark:text-green-500 mr-2 text-lg"></i> <?= htmlspecialchars($ev['location']) ?></span>
                                </div>
                                
                                <div class="mt-auto flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 pt-6">
                                    <div class="w-full sm:w-1/2">
                                        <div class="flex justify-between text-xs font-bold text-slate-500 dark:text-zinc-400 mb-2 uppercase tracking-widest">
                                            <span>Slots Claimed</span>
                                            <span><?= $ev['current_count'] ?> / <?= $ev['participant_limit'] ?></span>
                                        </div>
                                        <div class="w-full bg-slate-200 dark:bg-zinc-800 rounded-full h-2.5">
                                            <?php $pct = min(100, ($ev['current_count'] / $ev['participant_limit']) * 100); ?>
                                            <div class="bg-[#15803d] dark:bg-[#16a34a] h-2.5 rounded-full" style="width: <?= $pct ?>%"></div>
                                        </div>
                                    </div>
                                    <a href="public_events.php" class="btn-gradient font-bold py-3 px-8 rounded-xl transition-all shadow-none tap-effect whitespace-nowrap">Register Now &rarr;</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-slate-50 dark:bg-zinc-900 p-16 rounded-[2rem] text-center text-slate-500 dark:text-zinc-400 scroll-animate shadow-sm">
                        <div class="w-20 h-20 flex items-center justify-center mx-auto mb-4 text-5xl"><i class="ph-fill ph-calendar-x icon-gradient"></i></div>
                        <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-1">No upcoming events right now</h3>
                        <p class="text-slate-500 dark:text-zinc-400 text-sm">Please check back later for new programs and activities.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SCHOLARSHIPS SECTION -->
    <div id="scholarships" class="py-24 bg-white dark:bg-zinc-950 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-12 scroll-animate gap-4">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    <span class="text-gradient">Opportunities & Scholarships</span>
                </h2>
                <a href="scholarships.php" class="text-green-600 dark:text-green-500 font-bold hover:underline text-sm flex items-center gap-1 transition-colors">View All <i class="ph-bold ph-arrow-right"></i></a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if (count($scholarships) > 0): ?>
                    <?php foreach ($scholarships as $index => $schol): ?>
                        <div class="bg-slate-50 dark:bg-zinc-900 rounded-[2rem] overflow-hidden flex flex-col hover:-translate-y-1 transition-all duration-300 scroll-animate shadow-sm" style="animation-delay: <?= $index * 100 ?>ms;">
                            <div class="p-8 flex flex-col flex-1">
                                <div class="w-14 h-14 flex items-center justify-start text-4xl mb-6">
                                    <i class="ph-fill ph-student icon-gradient"></i>
                                </div>
                                <h3 class="text-xl font-extrabold text-slate-900 dark:text-white leading-snug mb-2"><?= htmlspecialchars($schol['title']) ?></h3>
                                <p class="text-[11px] font-extrabold text-green-600 dark:text-green-500 uppercase tracking-widest mb-4">By: <?= htmlspecialchars($schol['provider']) ?></p>
                                <p class="text-sm text-slate-600 dark:text-zinc-300 leading-relaxed mb-8 flex-1"><?= htmlspecialchars($schol['description']) ?></p>
                                
                                <div class="mt-auto pt-6 flex items-center justify-between">
                                    <div class="flex items-center text-[13px] font-bold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-3 py-1.5 rounded-lg">
                                        <i class="ph-fill ph-warning-circle mr-1.5 text-lg"></i> Due: <?= date('M j, Y', strtotime($schol['deadline'])) ?>
                                    </div>
                                    <?php if (!empty($schol['application_link'])): ?>
                                        <a href="<?= htmlspecialchars($schol['application_link']) ?>" target="_blank" class="btn-gradient text-white font-bold py-2.5 px-6 rounded-xl transition-all text-sm shadow-none tap-effect">Apply &rarr;</a>
                                    <?php else: ?>
                                        <span class="text-[11px] text-slate-400 dark:text-zinc-500 font-extrabold uppercase tracking-widest">See MYDO for info</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full bg-slate-50 dark:bg-zinc-900 p-16 rounded-[2rem] text-center text-slate-500 dark:text-zinc-400 scroll-animate shadow-sm">
                        <div class="w-20 h-20 flex items-center justify-center mx-auto mb-4 text-5xl"><i class="ph-fill ph-graduation-cap icon-gradient"></i></div>
                        <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-1">No active scholarships right now</h3>
                        <p class="text-slate-500 dark:text-zinc-400 text-sm">Please check back later for new educational grants and opportunities.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- COMMUNITY CALENDAR SECTION -->
    <div class="py-24 bg-white dark:bg-zinc-950 transition-colors duration-300">
        <section id="calendar-section" class="max-w-7xl mx-auto px-6 lg:px-8 w-full relative z-10">
            <div class="mb-10 scroll-animate">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    <span class="text-gradient">Community Calendar</span>
                </h2>
            </div>

            <div class="bg-slate-50 dark:bg-zinc-900 rounded-[2rem] p-6 md:p-8 w-full transition-colors duration-300 scroll-animate delay-200 shadow-sm">
                <!-- Calendar Controls -->
                <div class="flex flex-col sm:flex-row items-center justify-between mb-8 gap-4">
                    <h3 id="calMonthYearDisplay" class="text-2xl font-black text-slate-900 dark:text-white transition-colors">Month Year</h3>
                    <div class="flex gap-2">
                        <button id="calPrevMonth" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 shadow-sm hover:bg-slate-100 dark:hover:bg-zinc-700 transition-colors tap-effect"><i class="ph-bold ph-caret-left"></i></button>
                        <button id="calTodayBtn" class="px-4 h-10 flex items-center justify-center rounded-xl bg-white dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 font-bold text-sm shadow-sm hover:bg-slate-100 dark:hover:bg-zinc-700 transition-colors tap-effect">Today</button>
                        <button id="calNextMonth" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 shadow-sm hover:bg-slate-100 dark:hover:bg-zinc-700 transition-colors tap-effect"><i class="ph-bold ph-caret-right"></i></button>
                    </div>
                </div>

                <!-- Days of Week Header -->
                <div class="grid grid-cols-7 gap-px mb-2 text-center">
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Sun</div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Mon</div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Tue</div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Wed</div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Thu</div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Fri</div>
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Sat</div>
                </div>

                <!-- The Grid -->
                <div id="talaCalendarGrid" class="grid grid-cols-7 gap-px bg-slate-200 dark:bg-zinc-800 rounded-2xl overflow-hidden transition-colors">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
        </section>
    </div>

    <!-- YOUTH REGISTRATION FEATURE SECTION -->
    <div id="register-feature" class="py-24 bg-white dark:bg-zinc-950 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row items-center gap-16 lg:gap-24">
                
                <!-- Left Side: Image Carousel -->
                <div class="lg:w-1/2 w-full scroll-animate flex items-center justify-center">
                    <div class="relative w-full h-[400px] sm:h-[500px] group">
                        <div id="registrySlider" class="absolute inset-0 w-full h-full">
                            <div class="registry-slide absolute inset-0 opacity-100 transition-opacity duration-1000 flex items-center justify-center">
                                <img src="register1.png" alt="Youth Programs" class="max-w-full max-h-full object-contain group-hover:scale-105 transition duration-700">
                            </div>
                            <div class="registry-slide absolute inset-0 opacity-0 transition-opacity duration-1000 flex items-center justify-center">
                                <img src="register2.png" alt="Youth Community" class="max-w-full max-h-full object-contain group-hover:scale-105 transition duration-700">
                            </div>
                            <div class="registry-slide absolute inset-0 opacity-0 transition-opacity duration-1000 flex items-center justify-center">
                                <img src="register3.png" alt="Youth Events" class="max-w-full max-h-full object-contain group-hover:scale-105 transition duration-700">
                            </div>
                        </div>
                        <div class="absolute bottom-4 right-4 sm:bottom-10 sm:right-10 bg-slate-50/95 dark:bg-zinc-900/95 backdrop-blur-md p-5 rounded-2xl flex items-center gap-4 z-10 hover:-translate-y-1 transition-transform shadow-md">
                            <div class="w-12 h-12 flex items-center justify-center text-3xl">
                                <i class="ph-fill ph-users icon-gradient"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest">Join the Ledger</p>
                                <p class="text-base font-extrabold text-slate-900 dark:text-white">Be Counted Today</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Details -->
                <div class="lg:w-1/2 w-full scroll-animate delay-200">
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 dark:text-white mb-6 leading-tight tracking-tight">Be Part of the Movement. <span class="text-gradient">Register Your Profile.</span></h2>
                    <p class="text-slate-600 dark:text-zinc-300 text-lg leading-relaxed mb-8 font-medium">
                        TALA is the centralized database for the youth of Tumauini. By creating your official profile, you unlock direct access to municipal programs, leadership training, and educational opportunities.
                    </p>
                    <ul class="space-y-5 mb-10">
                        <li class="flex items-start">
                            <div class="flex-shrink-0 mt-0.5">
                                <i class="ph-fill ph-check-circle icon-gradient text-2xl"></i>
                            </div>
                            <span class="text-slate-700 dark:text-zinc-200 font-semibold ml-3 text-base">Priority slots for municipal events, sports leagues, and seminars.</span>
                        </li>
                        <li class="flex items-start">
                            <div class="flex-shrink-0 mt-0.5">
                                <i class="ph-fill ph-check-circle icon-gradient text-2xl"></i>
                            </div>
                            <span class="text-slate-700 dark:text-zinc-200 font-semibold ml-3 text-base">Direct access to MYDO scholarships, job fairs, and livelihood grants.</span>
                        </li>
                        <li class="flex items-start">
                            <div class="flex-shrink-0 mt-0.5">
                                <i class="ph-fill ph-check-circle icon-gradient text-2xl"></i>
                            </div>
                            <span class="text-slate-700 dark:text-zinc-200 font-semibold ml-3 text-base">Participate in SK planning, community feedback, and local governance.</span>
                        </li>
                    </ul>
                    <div class="flex flex-wrap items-center gap-5 pt-4">
                        <a href="public_registration.php" class="btn-gradient font-bold py-4 px-8 rounded-xl flex items-center transition-all shadow-none tap-effect">
                            Create Free Profile <i class="ph-bold ph-arrow-right ml-2 text-lg"></i>
                        </a>
                        <p class="text-sm text-slate-500 dark:text-zinc-400 font-semibold flex items-center">
                            <i class="ph-fill ph-clock mr-1.5 text-slate-400 text-lg"></i> Takes less than 3 minutes.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ABOUT US SECTION -->
    <div id="about" class="py-24 bg-white dark:bg-zinc-950 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row items-center gap-16 lg:gap-24">
                
                <div class="lg:w-1/2 w-full scroll-animate">
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 dark:text-white mb-6 tracking-tight"><span class="text-gradient">Youth at the Center</span></h2>
                    <p class="text-slate-600 dark:text-zinc-300 text-lg leading-relaxed mb-10 font-medium">
                     The Municipal Youth Development Office (MYDO) and SK Federation of Tumauini are committed to empowering young people through leadership, participation, and community-driven programs that promote growth, service, and positive change.
                    </p>

                    <div class="space-y-6">
                        <div class="flex items-start bg-slate-50 dark:bg-zinc-900 p-6 rounded-2xl shadow-sm">
                            <div class="flex-shrink-0 w-14 h-14 flex items-center justify-center text-4xl mr-5">
                                <i class="ph-fill ph-target icon-gradient"></i>
                            </div>
                            <div>
                                <h4 class="text-xl font-extrabold text-slate-900 dark:text-white mb-1">Our Mission</h4>
                                <p class="text-sm text-slate-500 dark:text-zinc-400 leading-relaxed font-medium">To provide opportunities that develop the skills, leadership, and active involvement of the youth in building a stronger and more progressive community.</p>
                            </div>
                        </div>

                        <div class="flex items-start bg-slate-50 dark:bg-zinc-900 p-6 rounded-2xl shadow-sm">
                            <div class="flex-shrink-0 w-14 h-14 flex items-center justify-center text-4xl mr-5">
                                <i class="ph-fill ph-eye icon-gradient"></i>
                            </div>
                            <div>
                                <h4 class="text-xl font-extrabold text-slate-900 dark:text-white mb-1">Our Vision</h4>
                                <p class="text-sm text-slate-500 dark:text-zinc-400 leading-relaxed font-medium">A community of empowered, responsible, and future-ready youth who actively contribute to the development and success of Tumauini.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:w-1/2 w-full scroll-animate delay-200">
                    <div class="relative rounded-[2rem] bg-slate-100 dark:bg-zinc-900 overflow-hidden group shadow-sm">
                        <img src="about.jpg" alt="About TALA" class="w-full h-full object-cover group-hover:scale-105 transition duration-700 min-h-[500px]">
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- DIGITAL GOVERNANCE SECTION -->
    <div id="about-digital" class="py-24 bg-white dark:bg-zinc-950 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row items-center gap-16 lg:gap-24">
                
                <div class="lg:w-1/2 w-full scroll-animate order-2 lg:order-1">
                    <div class="relative rounded-[2rem] bg-slate-100 dark:bg-zinc-800 overflow-hidden group shadow-sm">
                        <img src="digital.jpg" alt="Digital Governance" class="w-full h-full object-cover group-hover:scale-105 transition duration-700 min-h-[500px]">
                    </div>
                </div>

                <div class="lg:w-1/2 w-full scroll-animate delay-200 order-1 lg:order-2">
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 dark:text-white mb-6 tracking-tight"><span class="text-gradient">Empowering the Youth Sector</span></h2>
                    <p class="text-slate-600 dark:text-zinc-300 text-lg leading-relaxed mb-10 font-medium">
                        The Tumauini Active Youth Ledger & Analytics (TALA) is a pioneering digital platform established by the Municipal Youth Development Office (MYDO). Our purpose is to bridge the gap between local government initiatives and the youth constituents of Tumauini.
                    </p>
                    
                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="ph-fill ph-check-circle text-green-600 dark:text-green-500 text-2xl"></i>
                            </div>
                            <span class="text-slate-700 dark:text-zinc-200 font-semibold ml-4 text-base">Centralized data ecosystem tracking all youth demographics.</span>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="ph-fill ph-check-circle text-green-600 dark:text-green-500 text-2xl"></i>
                            </div>
                            <span class="text-slate-700 dark:text-zinc-200 font-semibold ml-4 text-base">Transparent program allocations and performance metrics.</span>
                        </div>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <i class="ph-fill ph-check-circle text-green-600 dark:text-green-500 text-2xl"></i>
                            </div>
                            <span class="text-slate-700 dark:text-zinc-200 font-semibold ml-4 text-base">Data-driven insights to tailor programs that fit actual community needs.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 9 CENTERS OF PARTICIPATION (PYDP) -->
    <div id="advocacies" class="py-24 bg-white dark:bg-zinc-950 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="mb-10 scroll-animate">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    <span class="text-gradient">9 Centers of Participation</span>
                </h2>
            </div>

            <!-- Carousel Container -->
            <div class="bg-slate-50 dark:bg-zinc-900 rounded-[2rem] overflow-hidden flex flex-col lg:flex-row min-h-[500px] scroll-animate delay-100 relative shadow-sm">
                
                <div class="lg:w-1/2 p-10 md:p-16 flex flex-col justify-center relative z-10">
                    <span class="inline-block px-3 py-1 bg-white dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 font-bold text-[10px] uppercase tracking-widest rounded-md w-max mb-6 shadow-sm" id="center-counter">
                        1 of 9
                    </span>

                    <div id="center-text-container" class="transition-opacity duration-500 opacity-100 min-h-[160px]">
                        <h3 id="center-title" class="text-3xl lg:text-4xl font-extrabold text-slate-900 dark:text-white mb-4 flex items-center tracking-tight">
                            <i id="center-icon" class="ph-fill ph-heartbeat icon-gradient mr-4 text-4xl"></i>
                            <span id="center-title-text" class="text-gradient">Health</span>
                        </h3>
                        <p id="center-desc" class="text-slate-600 dark:text-zinc-300 text-lg leading-relaxed font-medium">
                            Ensuring the physical, mental, and social well-being of the youth through accessible healthcare, nutrition programs, and mental health awareness.
                        </p>
                    </div>

                    <div class="mt-12 flex items-center gap-4">
                        <button onclick="prevCenter()" class="w-12 h-12 rounded-full bg-white dark:bg-zinc-800 flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-700 transition tap-effect focus:outline-none shadow-sm">
                            <i class="ph-bold ph-caret-left text-xl"></i>
                        </button>
                        <div class="flex gap-2" id="center-dots"></div>
                        <button onclick="nextCenter()" class="w-12 h-12 rounded-full bg-white dark:bg-zinc-800 flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-700 transition tap-effect focus:outline-none shadow-sm">
                            <i class="ph-bold ph-caret-right text-xl"></i>
                        </button>
                    </div>
                </div>

                <div class="lg:w-1/2 relative h-72 lg:h-auto bg-slate-900">
                    <div class="absolute inset-0 bg-gradient-to-br from-slate-900 to-green-950 opacity-90 z-0"></div>
                    <i id="center-bg-icon" class="ph-fill ph-heartbeat absolute inset-0 m-auto text-[12rem] text-white opacity-10 z-0 flex items-center justify-center"></i>
                    <img id="center-img" src="center-health.jpg" alt="Center of Participation" class="absolute inset-0 w-full h-full object-cover transition-opacity duration-500 opacity-100 z-10">
                </div>
            </div>
        </div>
    </div>

    <!-- CONTINUOUS SCROLLING GALLERY -->
    <div class="py-20 bg-white dark:bg-zinc-950 overflow-hidden scroll-animate transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-6 mb-10 text-left">
            <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                <span class="text-gradient">Youth in Action</span>
            </h2>
        </div>
        
        <div class="relative w-full fade-edges py-4 cursor-pointer">
            <div class="marquee-track gap-6 px-3">
                <img src="slide1.jpg" alt="Event Moment" class="h-56 w-80 md:h-64 md:w-96 object-cover rounded-[2rem] hover:scale-105 transition-all duration-500">
                <img src="slide2.jpg" alt="Event Moment" class="h-56 w-80 md:h-64 md:w-96 object-cover rounded-[2rem] hover:scale-105 transition-all duration-500">
                <img src="slide3.jpg" alt="Event Moment" class="h-56 w-80 md:h-64 md:w-96 object-cover rounded-[2rem] hover:scale-105 transition-all duration-500">
                <img src="slide4.jpg" alt="Event Moment" class="h-56 w-80 md:h-64 md:w-96 object-cover rounded-[2rem] hover:scale-105 transition-all duration-500">
                <img src="slide5.jpg" alt="Event Moment" class="h-56 w-80 md:h-64 md:w-96 object-cover rounded-[2rem] hover:scale-105 transition-all duration-500">
                
                <img src="slide1.jpg" alt="Event Moment" class="h-56 w-80 md:h-64 md:w-96 object-cover rounded-[2rem] hover:scale-105 transition-all duration-500">
                <img src="slide2.jpg" alt="Event Moment" class="h-56 w-80 md:h-64 md:w-96 object-cover rounded-[2rem] hover:scale-105 transition-all duration-500">
                <img src="slide3.jpg" alt="Event Moment" class="h-56 w-80 md:h-64 md:w-96 object-cover rounded-[2rem] hover:scale-105 transition-all duration-500">
                <img src="slide4.jpg" alt="Event Moment" class="h-56 w-80 md:h-64 md:w-96 object-cover rounded-[2rem] hover:scale-105 transition-all duration-500">
                <img src="slide5.jpg" alt="Event Moment" class="h-56 w-80 md:h-64 md:w-96 object-cover rounded-[2rem] hover:scale-105 transition-all duration-500">
            </div>
        </div>
    </div>

    <!-- OFFICIALS / LEADERSHIP SECTION -->
    <div id="officials" class="py-24 bg-white dark:bg-zinc-950 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-12 scroll-animate gap-4">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    <span class="text-gradient">Meet Our Youth Leaders</span>
                </h2>
                <a href="public_officials.php" class="text-green-600 dark:text-green-500 font-bold hover:underline text-sm flex items-center gap-1 transition-colors">View Full Directory <i class="ph-bold ph-arrow-right"></i></a>
            </div>

            <?php if ($db_connected): ?>
                
                <!-- LYDO Section -->
                <div class="mb-20">
                    <div class="flex items-center justify-center gap-3 mb-12 scroll-animate delay-100">
                        <div class="h-px bg-slate-200 dark:bg-zinc-800 flex-1 max-w-[100px]"></div>
                        <h3 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white text-center flex items-center justify-center">
                            Municipal Youth Development Office
                        </h3>
                        <div class="h-px bg-slate-200 dark:bg-zinc-800 flex-1 max-w-[100px]"></div>
                    </div>
                    
                    <div class="flex flex-wrap justify-center gap-8">
                        <?php if (count($lydo_officials) > 0): ?>
                            <?php foreach ($lydo_officials as $lydo): ?>
                                <div class="bg-slate-50 dark:bg-zinc-900 p-8 rounded-[2rem] text-center w-full md:w-72 scroll-animate delay-200 hover:-translate-y-1 transition-transform shadow-sm">
                                    <?php if (!empty($lydo['profile_picture'])): ?>
                                        <img src="<?= htmlspecialchars($lydo['profile_picture']) ?>" class="w-28 h-28 object-cover rounded-full mx-auto mb-5 shadow-sm">
                                    <?php else: ?>
                                        <div class="w-28 h-28 bg-slate-200 dark:bg-zinc-800 text-slate-400 dark:text-zinc-500 rounded-full flex items-center justify-center text-5xl mx-auto mb-5 shadow-sm"><i class="ph-fill ph-user"></i></div>
                                    <?php endif; ?>
                                    <h4 class="text-lg font-extrabold text-slate-900 dark:text-white"><?= htmlspecialchars($lydo['full_name'] ?? 'Municipal Youth Development Officer') ?></h4>
                                    <p class="text-[11px] font-extrabold text-green-600 dark:text-green-500 uppercase tracking-widest mt-1">LYDO / MYDC</p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- SK Chairpersons Section -->
                <div>
                    <div class="flex items-center justify-center gap-3 mb-12 scroll-animate delay-100">
                        <div class="h-px bg-slate-200 dark:bg-zinc-800 flex-1 max-w-[100px]"></div>
                        <h3 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white text-center flex items-center justify-center">
                            Sangguniang Kabataan Chairpersons
                        </h3>
                        <div class="h-px bg-slate-200 dark:bg-zinc-800 flex-1 max-w-[100px]"></div>
                    </div>

                    <?php if (count($sk_officials) > 0): ?>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-x-6 gap-y-12 mt-8 scroll-animate delay-200">
                            <?php foreach ($sk_officials as $barangay => $officials): ?>
                                <?php foreach ($officials as $off): ?>
                                    <div class="bg-slate-50 dark:bg-zinc-900 rounded-[2rem] text-center hover:-translate-y-1 transition-all duration-300 relative mt-6 pt-14 pb-6 px-4 shadow-sm">
                                        <div class="absolute -top-10 left-1/2 transform -translate-x-1/2">
                                            <?php if (!empty($off['profile_picture'])): ?>
                                                <img src="<?= htmlspecialchars($off['profile_picture']) ?>" class="w-20 h-20 object-cover rounded-[1.25rem] shadow-md bg-slate-100 dark:bg-zinc-800">
                                            <?php else: ?>
                                                <div class="w-20 h-20 bg-slate-200 dark:bg-zinc-800 text-slate-400 dark:text-zinc-500 rounded-[1.25rem] flex items-center justify-center text-3xl shadow-md"><i class="ph-fill ph-user"></i></div>
                                            <?php endif; ?>
                                        </div>
                                        <h4 class="font-extrabold text-slate-900 dark:text-white text-[15px] leading-tight mb-1 line-clamp-1" title="<?= htmlspecialchars($off['full_name'] ?? '') ?>"><?= htmlspecialchars($off['full_name'] ?? '') ?></h4>
                                        <p class="text-[10px] font-bold text-green-600 dark:text-green-500 uppercase tracking-widest mb-3">Chairperson</p>
                                        <span class="inline-flex items-center text-[11px] font-bold text-slate-600 dark:text-zinc-300 bg-white dark:bg-zinc-800 px-3 py-1.5 rounded-full line-clamp-1 w-max max-w-full justify-center shadow-sm">
                                            <i class="ph-fill ph-map-pin mr-1.5 text-slate-400"></i> <?= htmlspecialchars($barangay) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- COMMUNITY VOICE SECTION -->
    <div id="voice" class="py-24 bg-white dark:bg-zinc-950 transition-colors duration-300 relative overflow-hidden">
        <div class="max-w-4xl mx-auto px-6 relative z-10">
            <div class="mb-10 text-left scroll-animate">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    <span class="text-gradient">We want to hear from you</span>
                </h2>
            </div>

            <?php if (isset($suggestion_success) && $suggestion_success): ?>
                <div class="bg-slate-50 dark:bg-zinc-900 p-10 md:p-12 rounded-[2rem] text-center scroll-animate shadow-sm">
                    <div class="w-20 h-20 flex items-center justify-center text-5xl mx-auto mb-6"><i class="ph-fill ph-check-circle icon-gradient"></i></div>
                    <h3 class="text-2xl font-extrabold text-slate-900 dark:text-white mb-2">Issue Officially Recorded!</h3>
                    <p class="font-medium text-slate-600 dark:text-zinc-300 mb-6">Thank you for your feedback. Our youth officials will review it shortly.</p>
                    
                    <div class="bg-white dark:bg-zinc-950 rounded-xl p-4 mb-8 inline-block shadow-sm">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Your Tracking ID</p>
                        <p class="text-xl font-mono font-black text-slate-900 dark:text-white"><?= htmlspecialchars($generated_ticket_id) ?></p>
                    </div><br>

                    <a href="index.php#voice" class="inline-block text-sm font-bold btn-gradient px-8 py-3.5 rounded-xl transition tap-effect">Submit Another</a>
                </div>
            <?php else: ?>
                <div class="bg-slate-50 dark:bg-zinc-900 p-8 md:p-10 rounded-[2rem] scroll-animate delay-200 shadow-sm">
                    <form action="index.php#voice" method="POST" class="space-y-6">
                        <input type="hidden" name="submit_suggestion" value="1">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2">Full Name <span class="text-slate-400 font-medium normal-case tracking-normal">(Optional)</span></label>
                                <input type="text" name="name" placeholder="Leave blank to remain anonymous" class="w-full rounded-xl px-4 py-3.5 text-sm font-medium text-slate-900 dark:text-white focus:bg-white dark:focus:bg-zinc-950 outline-none bg-white dark:bg-zinc-950 transition-all placeholder:text-slate-400 dark:placeholder:text-zinc-500 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2">Contact / Email <span class="text-slate-400 font-medium normal-case tracking-normal">(Optional)</span></label>
                                <input type="text" name="contact" placeholder="So we can reply to you" class="w-full rounded-xl px-4 py-3.5 text-sm font-medium text-slate-900 dark:text-white focus:bg-white dark:focus:bg-zinc-950 outline-none bg-white dark:bg-zinc-950 transition-all placeholder:text-slate-400 dark:placeholder:text-zinc-500 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2">Barangay <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <select name="barangay" required class="w-full rounded-xl pl-4 pr-10 py-3.5 text-sm font-medium text-slate-900 dark:text-white focus:bg-white dark:focus:bg-zinc-950 outline-none bg-white dark:bg-zinc-950 transition-all cursor-pointer appearance-none shadow-sm">
                                        <option value="" disabled selected>Where is this happening?</option>
                                        <option value="Municipal Wide">Municipal Wide (General)</option>
                                        <?php foreach($all_barangays as $b): ?>
                                            <option value="<?= htmlspecialchars($b) ?>"><?= htmlspecialchars($b) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2">Category <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <select name="category" required class="w-full rounded-xl pl-4 pr-10 py-3.5 text-sm font-medium text-slate-900 dark:text-white focus:bg-white dark:focus:bg-zinc-950 outline-none bg-white dark:bg-zinc-950 transition-all cursor-pointer appearance-none shadow-sm">
                                        <option value="" disabled selected>Select feedback type...</option>
                                        <option value="Concern">General Concern / Inquiry</option>
                                        <option value="Project Suggestion">Project / Event Suggestion</option>
                                        <option value="Community Issue">Report a Community Issue</option>
                                    </select>
                                    <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2">Your Message <span class="text-red-500">*</span></label>
                            <textarea name="message" rows="4" required placeholder="Please provide specific details..." class="w-full rounded-xl px-4 py-3.5 text-sm font-medium text-slate-900 dark:text-white focus:bg-white dark:focus:bg-zinc-950 outline-none bg-white dark:bg-zinc-950 transition-all placeholder:text-slate-400 dark:placeholder:text-zinc-500 resize-none shadow-sm"></textarea>
                        </div>

                        <button type="submit" class="w-full btn-gradient font-bold py-4 rounded-xl flex items-center justify-center mt-4 transition-all shadow-none tap-effect">
                            <i class="ph-bold ph-paper-plane-tilt mr-2 text-lg"></i> Submit Ticket
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ACCESS PORTALS -->
    <div class="py-24 bg-white dark:bg-zinc-950 overflow-hidden transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="bg-slate-950 rounded-[2rem] flex flex-col lg:flex-row overflow-hidden relative shadow-xl">
                
                <div class="lg:w-1/2 p-10 md:p-16 flex flex-col justify-center scroll-animate relative z-10">
                    <div class="w-14 h-14 flex items-center justify-start text-4xl mb-6"><i class="ph-fill ph-shield-check icon-gradient"></i></div>
                    <h3 class="text-3xl font-extrabold text-white mb-4 tracking-tight"><span class="text-gradient">Official Access</span></h3>
                    <p class="text-slate-300 mb-8 leading-relaxed font-medium">Secure login portal for the Municipal Youth Development Officer and Barangay SK Officials.</p>
                    <div class="flex flex-wrap gap-4">
                        <a href="login.php" class="bg-white hover:bg-slate-100 text-slate-950 font-bold py-3.5 px-8 rounded-xl transition-all shadow-none tap-effect">Admin Login</a>
                        <a href="sk_registration.php" class="bg-slate-800 hover:bg-slate-700 text-white font-bold py-3.5 px-8 rounded-xl transition-all tap-effect">Request SK Account</a>
                    </div>
                </div>
                
                <div class="lg:w-1/2 p-10 md:p-16 flex flex-col justify-center scroll-animate delay-200 relative z-10 bg-slate-900/60">
                    <div class="w-14 h-14 flex items-center justify-start text-4xl mb-6"><i class="ph-fill ph-student icon-gradient"></i></div>
                    <h3 class="text-3xl font-extrabold text-white mb-4 tracking-tight"><span class="text-gradient">Youth Portal</span></h3>
                    <p class="text-slate-300 mb-8 leading-relaxed font-medium">Register yourself in the database to get access to municipal programs, scholarships, and events.</p>
                    <div class="flex flex-wrap gap-4">
                        <a href="public_registration.php" class="btn-gradient font-bold py-3.5 px-8 rounded-xl transition-all shadow-none tap-effect">Register Profile</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN FOOTER -->
    <footer class="bg-white dark:bg-zinc-950 py-12 transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                <div class="flex items-center gap-3">
                    <img src="TALA-LOGO.ico" alt="TALA Logo" class="w-10 h-10 object-contain grayscale opacity-60 dark:opacity-40">
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-800 dark:text-white tracking-tight">TALA</h2>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">MDMIS System</p>
                    </div>
                </div>
                <div class="flex flex-wrap justify-center gap-6 text-sm font-bold text-slate-500 dark:text-zinc-400">
                    <a href="login.php" class="hover:text-slate-900 dark:hover:text-white transition-colors">Admin Portal</a>
                    <a href="public_registration.php" class="hover:text-slate-900 dark:hover:text-white transition-colors">Youth Registration</a>
                    <a href="public_events.php" class="hover:text-slate-900 dark:hover:text-white transition-colors">Events</a>
                </div>
            </div>
            <div class="mt-10 pt-8 text-center text-sm font-medium text-slate-400 dark:text-zinc-500">
                <p>&copy; <?= date('Y') ?> Tumauini Municipal Youth Development Office. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- DYNAMIC POP-UP NOTIFICATION -->
    <?php if ($popup): ?>
    <div id="tala-popup" class="fixed inset-0 z-[9999] hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-500">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm popup-close-trigger cursor-pointer"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-[800px] w-full flex flex-col md:flex-row transform scale-95 transition-transform duration-500 z-10 shadow-2xl" id="popup-content">
            
            <button class="absolute top-4 right-4 z-20 w-8 h-8 bg-black/20 hover:bg-black/40 text-white rounded-full flex items-center justify-center backdrop-blur-md transition-colors popup-close-trigger focus:outline-none">
                <i class="ph-bold ph-x text-lg"></i>
            </button>

            <?php if (!empty($popup['image_path'])): ?>
            <div class="md:w-1/2 h-56 md:h-auto relative bg-slate-950">
                <img src="<?= htmlspecialchars($popup['image_path']) ?>" class="absolute inset-0 w-full h-full object-cover" alt="Announcement">
            </div>
            <?php endif; ?>

            <div class="<?= empty($popup['image_path']) ? 'w-full' : 'md:w-1/2' ?> p-8 sm:p-10 flex flex-col justify-center text-center md:text-left bg-white dark:bg-zinc-900">
                
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-green-500/10 text-green-600 dark:text-green-500 text-[10px] font-bold uppercase tracking-widest mb-4 mx-auto md:mx-0 w-max">
                    <i class="ph-fill ph-bell-ringing"></i> Announcement
                </span>
                
                <h3 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white leading-tight mb-4 tracking-tight">
                    <?= htmlspecialchars($popup['title']) ?>
                </h3>
                
                <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium leading-relaxed mb-8">
                    <?= nl2br(htmlspecialchars($popup['description'])) ?>
                </p>
                
                <?php if (!empty($popup['button_link']) && !empty($popup['button_text'])): ?>
                <a href="<?= htmlspecialchars($popup['button_link']) ?>" class="btn-gradient w-full font-bold py-3.5 rounded-xl transition-all flex items-center justify-center text-sm shadow-none tap-effect">
                    <?= htmlspecialchars($popup['button_text']) ?> <i class="ph-bold ph-arrow-right ml-2 text-lg"></i>
                </a>
                <?php endif; ?>
                
                <button class="mt-5 text-xs font-bold text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 transition popup-close-trigger focus:outline-none">
                    Dismiss for now
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- EVENT DETAILS MODAL (Public Calendar) -->
    <div id="pubEventModal" class="fixed inset-0 z-[9999] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm transition-colors" onclick="closePubEventModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] w-full max-w-lg overflow-hidden transform scale-95 opacity-0 transition-all duration-300 shadow-2xl" id="pubModalContent">
            
            <div id="pubModalImageContainer" class="w-full h-48 bg-slate-100 dark:bg-zinc-800 hidden">
                <img id="pubModalImage" src="" class="w-full h-full object-cover">
            </div>
            
            <div class="p-8">
                <div class="flex justify-between items-start mb-4">
                    <span id="pubModalDate" class="inline-block px-3 py-1 bg-green-500/10 text-green-600 dark:text-green-500 text-[10px] font-extrabold uppercase tracking-widest rounded-lg">Date</span>
                    <button onclick="closePubEventModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors"><i class="ph-bold ph-x"></i></button>
                </div>
                
                <h3 id="pubModalTitle" class="text-2xl font-black text-slate-900 dark:text-white mb-2">Event Title</h3>
                
                <div class="flex items-center gap-2 text-sm font-bold text-slate-500 dark:text-zinc-400 mb-6">
                    <i class="ph-fill ph-map-pin text-green-600 dark:text-green-500"></i>
                    <span id="pubModalLocation">Location</span>
                </div>
                
                <div class="pt-6">
                    <span class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest">Status</span>
                    <p id="pubModalStatus" class="text-sm font-bold text-slate-800 dark:text-zinc-200 mt-1">Status</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme Toggle Sync Logic
        const themeIcon = document.getElementById('theme-icon');

        function syncThemeIcons() {
            const isDark = document.documentElement.classList.contains('dark');
            if (themeIcon) {
                if (isDark) {
                    themeIcon.classList.remove('ph-moon');
                    themeIcon.classList.add('ph-sun');
                } else {
                    themeIcon.classList.remove('ph-sun');
                    themeIcon.classList.add('ph-moon');
                }
            }
        }
        syncThemeIcons();

        function toggleTheme() {
            const html = document.documentElement;
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
            syncThemeIcons();
            if (window.syncThemeIconsNav) { window.syncThemeIconsNav(); }
        }

        document.addEventListener('DOMContentLoaded', function () {
            
            // 2. SCROLL ANIMATION LOGIC (Intersection Observer)
            const observerOptions = {
                root: null,
                rootMargin: '0px 0px -40px 0px',
                threshold: 0.08
            };

            const observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('show');
                        observer.unobserve(entry.target);
                    }
                });
            }, observerOptions);

            const scrollElements = document.querySelectorAll('.scroll-animate');
            scrollElements.forEach(el => observer.observe(el));

            // 3. 9 CENTERS OF PARTICIPATION LOGIC
            const centers = [
                { title: "Health", icon: "ph-heartbeat", img: "center-health.jpg", desc: "Ensuring the physical, mental, and social well-being of the youth through accessible healthcare, nutrition programs, and mental health awareness." },
                { title: "Education", icon: "ph-books", img: "center-education.jpg", desc: "Providing equitable access to quality education, skills training, and lifelong learning opportunities to prepare the youth for the future." },
                { title: "Economic Empowerment", icon: "ph-briefcase", img: "center-economy.jpg", desc: "Promoting decent work, entrepreneurship, and financial literacy to help youth achieve financial independence and contribute to the local economy." },
                { title: "Social Inclusion & Equity", icon: "ph-users", img: "center-inclusion.jpg", desc: "Fostering a culture of equality and ensuring that marginalized and vulnerable youth sectors have a voice and access to opportunities." },
                { title: "Peace-Building & Security", icon: "ph-shield-check", img: "center-peace.jpg", desc: "Empowering the youth as agents of peace, justice, and conflict resolution to build safe and secure communities." },
                { title: "Governance", icon: "ph-bank", img: "center-governance.jpg", desc: "Strengthening youth participation in local governance and policy-making to ensure their needs are represented in public administration." },
                { title: "Active Citizenship", icon: "ph-hand-heart", img: "center-citizenship.jpg", desc: "Encouraging volunteerism, civic engagement, and active community participation to foster a deep sense of patriotism and social responsibility." },
                { title: "Environment", icon: "ph-leaf", img: "center-environment.jpg", desc: "Mobilizing the youth for climate action, disaster risk reduction, and environmental protection to ensure a sustainable future." },
                { title: "Global Mobility", icon: "ph-globe-hemisphere-east", img: "center-mobility.jpg", desc: "Enhancing youth competitiveness through cross-border exchanges, international cooperation, and global awareness programs." }
            ];

            let centerIndex = 0;
            const centerTextContainer = document.getElementById('center-text-container');
            const centerImg = document.getElementById('center-img');
            const centerTitleText = document.getElementById('center-title-text');
            const centerDesc = document.getElementById('center-desc');
            const centerIcon = document.getElementById('center-icon');
            const centerBgIcon = document.getElementById('center-bg-icon');
            const centerCounter = document.getElementById('center-counter');
            const dotsContainer = document.getElementById('center-dots');
            
            let centerInterval;

            // Generate Dots
            centers.forEach((_, i) => {
                const dot = document.createElement('button');
                dot.className = `w-2.5 h-2.5 rounded-full transition-all duration-300 ${i === 0 ? 'bg-slate-900 dark:bg-white w-6' : 'bg-slate-300 hover:bg-slate-400 dark:bg-zinc-700 dark:hover:bg-zinc-600'}`;
                dot.onclick = () => updateCenter(i);
                dotsContainer.appendChild(dot);
            });

            function updateCenter(index) {
                centerTextContainer.classList.replace('opacity-100', 'opacity-0');
                centerImg.classList.replace('opacity-100', 'opacity-0');
                
                setTimeout(() => {
                    centerIndex = index;
                    
                    centerTitleText.textContent = centers[index].title;
                    centerDesc.textContent = centers[index].desc;
                    centerIcon.className = `ph-fill ${centers[index].icon} icon-gradient mr-4 text-4xl`;
                    centerBgIcon.className = `ph-fill ${centers[index].icon} absolute inset-0 m-auto text-[12rem] text-white opacity-10 z-0 flex items-center justify-center`;
                    centerImg.src = centers[index].img;
                    centerCounter.textContent = `${index + 1} of 9`;
                    
                    Array.from(dotsContainer.children).forEach((dot, i) => {
                        dot.className = `h-2.5 rounded-full transition-all duration-300 ${i === index ? 'bg-slate-900 dark:bg-white w-6' : 'w-2.5 bg-slate-300 hover:bg-slate-400 dark:bg-zinc-700 dark:hover:bg-zinc-600'}`;
                    });

                    centerTextContainer.classList.replace('opacity-0', 'opacity-100');
                    centerImg.classList.replace('opacity-0', 'opacity-100');
                }, 500);

                resetCenterInterval();
            }

            window.nextCenter = () => { updateCenter((centerIndex + 1) % centers.length); };
            window.prevCenter = () => { updateCenter((centerIndex - 1 + centers.length) % centers.length); };

            function resetCenterInterval() {
                clearInterval(centerInterval);
                centerInterval = setInterval(window.nextCenter, 6000); 
            }
            resetCenterInterval();

            // 4. LOADING SCREEN LOGIC
            window.addEventListener('load', function() {
                const loader = document.getElementById('tala-loader');
                if (loader) {
                    setTimeout(() => {
                        loader.classList.add('opacity-0');
                        setTimeout(() => { loader.style.display = 'none'; }, 700);
                    }, 500);
                }
            });

            // 5. HERO TEXT ROTATION
            const places = ["Tumauini", "District 1", "Balug", "Camasi", "Moldero", "Ugad", "Lanna"];
            let current = 0;
            const textElement = document.getElementById("changingText");

            if (textElement) {
                setInterval(() => {
                    textElement.style.opacity = 0;
                    setTimeout(() => {
                        current = (current + 1) % places.length;
                        textElement.textContent = places[current];
                        textElement.style.opacity = 1;
                    }, 300);
                }, 2500);
            }

            // 6. YOUTH REGISTRATION SLIDER LOGIC
            const registrySlides = document.querySelectorAll('.registry-slide');
            let currentRegistry = 0;

            function nextRegistrySlide() {
                if (registrySlides.length === 0) return;
                registrySlides[currentRegistry].style.opacity = '0';
                currentRegistry = (currentRegistry + 1) % registrySlides.length;
                registrySlides[currentRegistry].style.opacity = '1';
            }

            if (registrySlides.length > 0) {
                setInterval(nextRegistrySlide, 4000);
            }

            // 7. POP-UP LOGIC
            const popup = document.getElementById('tala-popup');
            const popupContent = document.getElementById('popup-content');
            const closeTriggers = document.querySelectorAll('.popup-close-trigger');
            
            if (popup && !sessionStorage.getItem('tala_popup_shown')) {
                setTimeout(() => {
                    popup.classList.remove('hidden');
                    popup.classList.add('flex');
                    setTimeout(() => {
                        popup.classList.remove('opacity-0');
                        popupContent.classList.remove('scale-95');
                    }, 50);
                    sessionStorage.setItem('tala_popup_shown', 'true');
                }, 1500);
            }

            closeTriggers.forEach(trigger => {
                trigger.addEventListener('click', () => {
                    popup.classList.add('opacity-0');
                    popupContent.classList.add('scale-95');
                    setTimeout(() => {
                        popup.classList.add('hidden');
                        popup.classList.remove('flex');
                    }, 500); 
                });
            });

            // 8. COMMUNITY CALENDAR LOGIC
            const rawEvents = <?= $tala_events_json ?>;
            const events = rawEvents.map(e => ({ ...e, dateObj: new Date(e.event_date) }));

            let currentDate = new Date();
            const today = new Date();

            const calendarGrid = document.getElementById('talaCalendarGrid');
            const monthYearDisplay = document.getElementById('calMonthYearDisplay');

            function renderCalendar() {
                if(!calendarGrid) return;
                calendarGrid.innerHTML = '';
                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();

                const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                monthYearDisplay.textContent = `${monthNames[month]} ${year}`;

                const firstDay = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();

                // Prev month blanks
                for (let i = 0; i < firstDay; i++) {
                    const cell = document.createElement('div');
                    cell.className = 'bg-slate-50 dark:bg-zinc-950/60 cal-cell p-2 transition-colors';
                    calendarGrid.appendChild(cell);
                }

                // Current month
                for (let day = 1; day <= daysInMonth; day++) {
                    const cell = document.createElement('div');
                    const isToday = day === today.getDate() && month === today.getMonth() && year === today.getFullYear();
                    
                    cell.className = `bg-white dark:bg-zinc-900 cal-cell p-2 flex flex-col gap-1 transition-colors`;
                    let dayNumberHTML = `<span class="text-sm font-bold ${isToday ? 'bg-[#15803d] dark:bg-[#16a34a] text-white w-7 h-7 flex items-center justify-center rounded-full' : 'text-slate-700 dark:text-zinc-300'}">${day}</span>`;
                    
                    cell.innerHTML = `<div class="flex justify-between items-start mb-1">${dayNumberHTML}</div><div class="event-container flex-1 overflow-y-auto custom-scrollbar space-y-1"></div>`;

                    const dayEvents = events.filter(e => e.dateObj.getDate() === day && e.dateObj.getMonth() === month && e.dateObj.getFullYear() === year);
                    const eventContainer = cell.querySelector('.event-container');

                    dayEvents.forEach(ev => {
                        const isCompleted = ev.status === 'Completed';
                        const evColors = isCompleted 
                            ? 'bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400' 
                            : 'bg-green-50 dark:bg-green-950/40 text-green-800 dark:text-green-300 cursor-pointer hover:scale-[1.02]';

                        const evJson = JSON.stringify(ev).replace(/"/g, '&quot;');

                        eventContainer.innerHTML += `
                            <div onclick="openPubEventModal(${evJson})" class="px-2 py-1.5 text-[11px] font-bold rounded truncate transition-all ${evColors}" title="${ev.title}">
                                ${ev.title}
                            </div>
                        `;
                    });

                    calendarGrid.appendChild(cell);
                }

                // Next month blanks
                const totalCells = firstDay + daysInMonth;
                const remainingCells = (Math.ceil(totalCells / 7) * 7) - totalCells;
                for (let i = 0; i < remainingCells; i++) {
                    const cell = document.createElement('div');
                    cell.className = 'bg-slate-50 dark:bg-zinc-950/60 cal-cell p-2 transition-colors';
                    calendarGrid.appendChild(cell);
                }
            }

            // Attach listeners if elements exist
            if(document.getElementById('calPrevMonth')) {
                document.getElementById('calPrevMonth').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); });
                document.getElementById('calNextMonth').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); });
                document.getElementById('calTodayBtn').addEventListener('click', () => { currentDate = new Date(); renderCalendar(); });
            }

            renderCalendar();
        });

        // Global Scope functions for Modal
        window.openPubEventModal = function(eventData) {
            document.getElementById('pubModalTitle').textContent = eventData.title;
            document.getElementById('pubModalLocation').textContent = eventData.location || 'Location TBA';
            document.getElementById('pubModalStatus').textContent = eventData.status;
            
            const d = new Date(eventData.event_date);
            document.getElementById('pubModalDate').textContent = d.toLocaleDateString('en-US', { weekday: 'short', month: 'long', day: 'numeric', hour: '2-digit', minute:'2-digit' });

            const imgContainer = document.getElementById('pubModalImageContainer');
            if (eventData.image_path) {
                document.getElementById('pubModalImage').src = eventData.image_path;
                imgContainer.classList.remove('hidden');
            } else {
                imgContainer.classList.add('hidden');
            }

            const modal = document.getElementById('pubEventModal');
            const content = document.getElementById('pubModalContent');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => content.classList.remove('scale-95', 'opacity-0'), 10);
        }

        window.closePubEventModal = function() {
            const modal = document.getElementById('pubEventModal');
            const content = document.getElementById('pubModalContent');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }
    </script>
</body>
</html>