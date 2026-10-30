<?php
// public_officials.php - Public Directory with SK Gamification Showcase
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db_connect.php';

try {
    // 1. Fetch LYDO / Municipal Admins
    $lydo_officials = $pdo->query("SELECT full_name, profile_picture, role FROM admins WHERE role IN ('admin', 'mayor') AND status = 'approved' ORDER BY role ASC, full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Fetch SK Chairpersons
    $sk_raw = $pdo->query("SELECT full_name, barangay_assigned, sk_position, profile_picture FROM admins WHERE role = 'sub-admin' AND status = 'approved' AND sk_position = 'SK Chairperson' ORDER BY barangay_assigned ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Fetch XP Data for the Badges
    // FETCH_KEY_PAIR creates an array like: ['Barangay Name' => TotalXP]
    $xp_data = [];
    try {
        $xp_query = $pdo->query("SELECT barangay, SUM(xp_earned) as total_xp FROM sk2_admin_xp GROUP BY barangay")->fetchAll(PDO::FETCH_KEY_PAIR);
        if ($xp_query) {
            $xp_data = $xp_query;
        }
    } catch (Exception $e) {
        // Table might not exist yet if no actions taken, safe to ignore
    }

} catch (Exception $e) {
    die("Error loading directory.");
}

// Helper Function to Determine Badge Tier based on XP
function getBadgeTier($xp) {
    if ($xp >= 5000) {
        return [
            'name' => 'Legendary Council', 
            'color' => 'bg-gradient-to-r from-fuchsia-600 to-purple-600', 
            'text_color' => 'text-fuchsia-100',
            'border' => 'border-fuchsia-500',
            'icon' => 'ph-crown'
        ];
    }
    if ($xp >= 1000) {
        return [
            'name' => 'Elite Council', 
            'color' => 'bg-gradient-to-r from-amber-400 to-yellow-600', 
            'text_color' => 'text-yellow-100',
            'border' => 'border-amber-400',
            'icon' => 'ph-star'
        ];
    }
    if ($xp >= 500) {
        return [
            'name' => 'Active Council', 
            'color' => 'bg-gradient-to-r from-slate-400 to-slate-600', 
            'text_color' => 'text-white',
            'border' => 'border-slate-400',
            'icon' => 'ph-shield-check'
        ];
    }
    
    // Default / Starter Tier (0 - 499 XP)
    return [
        'name' => 'Developing Council', 
        'color' => 'bg-gradient-to-r from-brand-500 to-brand-700', 
        'text_color' => 'text-white',
        'border' => 'border-brand-200 dark:border-brand-800',
        'icon' => 'ph-seedling'
    ];
}
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Directory | TALA OS</title>
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- Tailwind Configuration -->
    <script>
        tailwind.config = { 
            darkMode: 'class', 
            theme: { 
                extend: { 
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { 
                        brand: { 50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d', 950: '#021207' } 
                    } 
                } 
            } 
        }
    </script>
    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        .glass-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); border: 1px solid rgba(226, 232, 240, 0.8); }
        .dark .glass-card { background: rgba(24, 24, 27, 0.85); border: 1px solid rgba(63, 63, 70, 0.5); }
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
        .text-gradient,
        .text-gradient-green-yellow {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 30%, #eab308 100%) !important;
            -webkit-background-clip: text !important;
            background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            color: transparent !important;
            display: inline-block;
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
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
    <link rel="stylesheet" href="public-design.css">
</head>
<body data-public-page="public_officials.php" class="tala-public bg-slate-50 dark:bg-[#010803] text-slate-800 dark:text-brand-100 antialiased selection:bg-[#1da85b] selection:text-white transition-colors duration-300 flex flex-col min-h-screen">

    <!-- PUBLIC NAVIGATION -->
    <div class="sticky top-0 z-50 bg-white/90 dark:bg-[#010803]/90 backdrop-blur-md border-b border-slate-200 dark:border-[#021207]">
        <?php include 'navigation.php'; ?>
    </div>

    <!-- MAIN CONTENT -->
    <main class="flex-1 py-16 px-6 lg:px-8 max-w-7xl mx-auto w-full z-10">
        
        <!-- Header -->
        <div class="mb-12 text-left">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight transition-colors">
                <span class="text-gradient">Meet Our Youth Leaders</span>
            </h1>
        </div>

        <!-- LYDO & Municipal Level Officials -->
        <div class="mb-24">
            <div class="flex items-center justify-center gap-3 mb-12">
                <div class="h-px bg-slate-200 dark:bg-slate-800 flex-1 max-w-[100px]"></div>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white text-center flex items-center justify-center transition-colors">
                    Municipal Level
                </h2>
                <div class="h-px bg-slate-200 dark:bg-slate-800 flex-1 max-w-[100px]"></div>
            </div>

            <div class="flex flex-wrap justify-center gap-8">
                <?php foreach ($lydo_officials as $lydo): ?>
                    <div class="glass-card p-8 rounded-[2rem] text-center w-full md:w-80 hover:-translate-y-2 transition-transform duration-300 relative overflow-hidden group">
                        <div class="absolute top-0 inset-x-0 h-24 bg-brand-50 dark:bg-[#021207] transition-colors"></div>
                        
                        <div class="relative z-10">
                            <?php if ($lydo['profile_picture']): ?>
                                <img src="<?= htmlspecialchars($lydo['profile_picture']) ?>" alt="<?= htmlspecialchars($lydo['full_name'] ?? 'Municipal official') ?>" class="w-40 h-56 object-cover object-top rounded-[1.5rem] mx-auto mb-6 border-4 border-white dark:border-slate-900 transition-colors">
                            <?php else: ?>
                                <div class="w-32 h-32 bg-slate-100 dark:bg-[#052e16] text-slate-400 dark:text-brand-500 rounded-[1.5rem] flex items-center justify-center text-6xl mx-auto mb-6 border-4 border-white dark:border-slate-900 transition-colors"><i class="ph-fill ph-user"></i></div>
                            <?php endif; ?>
                            
                            <h3 class="text-xl font-black text-slate-900 dark:text-white leading-tight mb-1 transition-colors"><?= htmlspecialchars(trim((string)($lydo['full_name'] ?? '')) ?: ($lydo['role'] === 'mayor' ? 'Municipal Mayor' : 'Municipal Youth Development Officer')) ?></h3>
                            <p class="text-[11px] font-extrabold text-brand-600 dark:text-brand-400 uppercase tracking-widest bg-brand-50 dark:bg-[#021207] inline-block px-3 py-1 rounded-md border border-brand-100 dark:border-brand-900/50 mt-2 transition-colors">
                                <?= $lydo['role'] === 'mayor' ? 'Municipal Mayor' : 'LYDO / MYDC' ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SK Chairpersons & Activity Showcase -->
        <div>
            <div class="flex items-center justify-center gap-3 mb-12">
                <div class="h-px bg-slate-200 dark:bg-slate-800 flex-1 max-w-[100px]"></div>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white text-center flex items-center justify-center transition-colors">
                    SK Chairpersons
                </h2>
                <div class="h-px bg-slate-200 dark:bg-slate-800 flex-1 max-w-[100px]"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8">
                <?php foreach ($sk_raw as $sk): 
                    // Calculate their specific XP and Badge
                    $barangay_name = $sk['barangay_assigned'];
                    $council_xp = isset($xp_data[$barangay_name]) ? (int)$xp_data[$barangay_name] : 0;
                    $badge = getBadgeTier($council_xp);
                ?>
                    <div class="bg-white dark:bg-[#021207] rounded-[2rem] border <?= $badge['border'] ?> text-center hover:-translate-y-2 transition-all duration-300 flex flex-col overflow-hidden relative group">
                        
                        <!-- Top Badge Banner (The Gamification Element) -->
                        <div class="w-full <?= $badge['color'] ?> py-2.5 px-4 flex justify-between items-center z-10 transition-colors">
                            <span class="text-[10px] font-black uppercase tracking-widest <?= $badge['text_color'] ?> flex items-center gap-1.5">
                                <i class="ph-fill <?= $badge['icon'] ?> text-sm"></i> <?= $badge['name'] ?>
                            </span>
                            <span class="text-xs font-black text-white bg-black/20 px-2 py-0.5 rounded-md backdrop-blur-sm">
                                <?= number_format($council_xp) ?> XP
                            </span>
                        </div>

                        <div class="p-6 md:p-8 flex flex-col flex-1 relative z-10">
                            <?php if ($sk['profile_picture']): ?>
                                <img src="<?= htmlspecialchars($sk['profile_picture']) ?>" class="w-24 h-24 object-cover rounded-[1.25rem] border-2 border-slate-100 dark:border-zinc-800 mx-auto mb-5 transition-colors group-hover:scale-105 duration-500">
                            <?php else: ?>
                                <div class="w-24 h-24 bg-slate-50 dark:bg-black text-slate-400 dark:text-slate-600 rounded-[1.25rem] flex items-center justify-center text-4xl border-2 border-slate-100 dark:border-zinc-800 mx-auto mb-5 transition-colors group-hover:scale-105 duration-500"><i class="ph-fill ph-user"></i></div>
                            <?php endif; ?>
                            
                            <h4 class="font-extrabold text-slate-900 dark:text-white text-lg leading-tight mb-1 transition-colors line-clamp-1"><?= htmlspecialchars($sk['full_name']) ?></h4>
                            <p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest mb-4 transition-colors">Chairperson</p>
                            
                            <div class="mt-auto inline-flex items-center text-[11px] font-bold text-slate-600 dark:text-brand-300 bg-slate-50 dark:bg-black px-4 py-2 rounded-full border border-slate-200 dark:border-zinc-800 w-max mx-auto transition-colors">
                                <i class="ph-fill ph-map-pin mr-1.5 text-brand-500 dark:text-brand-400"></i> Brgy. <?= htmlspecialchars($barangay_name) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($sk_raw)): ?>
                <div class="text-center py-16">
                    <i class="ph-fill ph-users-three text-6xl icon-gradient mb-4"></i>
                    <p class="text-slate-500 dark:text-zinc-400 font-bold">No SK Chairpersons registered yet.</p>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- MAIN FOOTER -->
    <?php include 'public_footer.php'; ?>

</body>
</html>
