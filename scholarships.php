<?php
// scholarships.php - TALA Tumauini 2026 Scholarships & Opportunities
error_reporting(E_ALL);
ini_set('display_errors', 1);

$scholarships = [];
$db_connected = false;

try {
    require 'db_connect.php';
    $db_connected = true;
    // Fetch ALL active scholarships (Assuming you have a 'scholarships' table)
    // If your table is named differently, update 'scholarships' below.
    $scholarships = $pdo->query("SELECT * FROM scholarships ORDER BY deadline ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    $db_connected = false; 
    
    // Fallback data for preview if database fails or table doesn't exist
    $scholarships = [
        [
            'id' => 1,
            'title' => 'LGU Municipal College Scholarship Grant 2026',
            'provider' => 'LGU Tumauini',
            'description' => 'A full-tuition scholarship for outstanding incoming college freshmen residing in Tumauini. Requires a general average of 90% and above.',
            'deadline' => '2026-08-15',
            'application_link' => '#'
        ],
        [
            'id' => 2,
            'title' => 'SK Federation Tech-Voc Assistance Program',
            'provider' => 'SK Federation Tumauini',
            'description' => 'Financial assistance for youth enrolling in TESDA-accredited technical and vocational courses to promote skilled workforce development.',
            'deadline' => '2026-08-30',
            'application_link' => '#'
        ],
        [
            'id' => 3,
            'title' => 'Provincial Board Merit Scholarship',
            'provider' => 'Provincial Government of Isabela',
            'description' => 'Merit-based financial aid for ongoing college students who consistently maintain high academic standing across consecutive semesters.',
            'deadline' => '2026-09-10',
            'application_link' => ''
        ]
    ];
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarships | TALA Tumauini 2026</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">

    <!-- Fonts & Tailwind CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    
    
    <!-- ANTI-FLASH SCRIPT: Must be at the top of the <head>! -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <!-- ENABLE DARK MODE IN TAILWIND CONFIG -->
    <script>
        tailwind.config = {
            darkMode: 'class', // <-- FIX: Enables dark mode based on class
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
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        
        .btn-gradient {
            background: #15803d;
            color: #ffffff;
            transition: all 0.2s ease;
        }
        .btn-gradient:hover {
            background: #166534;
            transform: translateY(-1px);
        }
        
        /* Dark Mode Button Gradient */
        .dark .btn-gradient {
            background: #16a34a;
        }
        .dark .btn-gradient:hover {
            background: #15803d;
        }
        
        /* Entrance blur-in animation for scroll */
        .scroll-animate {
            opacity: 0;
            filter: blur(16px);
            -webkit-filter: blur(16px);
            transform: scale(0.985) translateY(12px);
            animation: blurFadeIn 0.85s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            will-change: opacity, filter, transform;
        }
        
        @keyframes blurFadeIn {
            to {
                opacity: 1;
                filter: blur(0px);
                -webkit-filter: blur(0px);
                transform: scale(1) translateY(0);
            }
        }

        /* TALA OS Green to Yellow Gradient for Text & Icons */
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
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
    <link rel="stylesheet" href="public-design.css">
</head>
<body data-public-page="scholarships.php" class="tala-public bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white transition-colors duration-300">

    <?php include 'navigation.php'; ?>
    
    <!-- CONTENT -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-6 lg:px-8 pt-16 pb-20">
        <!-- SCHOLARSHIPS & OPPORTUNITIES SECTION -->
        <div id="scholarships">
             <!-- Cleaned up duplicated div tags here -->
             <div class="mb-10 text-left">
                <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight transition-colors">
                    <span class="text-gradient">Opportunities & Scholarships</span>
                </h1>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if (count($scholarships) > 0): ?>
                    <?php foreach ($scholarships as $index => $schol): ?>
                        <div class="bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden flex flex-col hover:-translate-y-1 transition-all duration-300 scroll-animate tala-public-card" style="animation-delay: <?= $index * 100 ?>ms;">
                            <div class="p-8 flex flex-col flex-1 transition-colors">
                                
                                <div class="w-14 h-14 flex items-center justify-start text-4xl mb-6">
                                    <i class="ph-fill ph-student icon-gradient"></i>
                                </div>
                                
                                <h3 class="text-xl font-extrabold text-slate-900 dark:text-white leading-snug mb-2 transition-colors"><?= htmlspecialchars($schol['title']) ?></h3>
                                <p class="text-[11px] font-extrabold text-brand-600 dark:text-brand-400 uppercase tracking-widest mb-4 transition-colors">By: <?= htmlspecialchars($schol['provider']) ?></p>
                                <p class="text-sm text-slate-600 dark:text-zinc-400 leading-relaxed mb-8 flex-1 transition-colors"><?= htmlspecialchars($schol['description']) ?></p>
                                
                                <div class="mt-auto pt-6 border-t border-slate-100 dark:border-zinc-800/80 flex items-center justify-between transition-colors">
                                    <div class="flex items-center text-[13px] font-bold text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10 px-3 py-1.5 rounded-lg border border-amber-100 dark:border-amber-500/20 transition-colors">
                                        <i class="ph-fill ph-warning-circle mr-1.5 text-lg"></i> Due: <?= date('M j, Y', strtotime($schol['deadline'])) ?>
                                    </div>
                                    
                                    <?php if (!empty($schol['application_link'])): ?>
                                        <a href="<?= htmlspecialchars($schol['application_link']) ?>" target="_blank" class="btn-gradient font-bold py-2.5 px-6 rounded-xl transition-all text-sm">Apply &rarr;</a>
                                    <?php else: ?>
                                        <span class="text-[11px] text-slate-400 dark:text-zinc-500 font-extrabold uppercase tracking-widest transition-colors">See MYDO for info</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full bg-white dark:bg-zinc-900 p-16 rounded-[2rem] text-center scroll-animate transition-colors tala-public-card">
                        <div class="w-20 h-20 flex items-center justify-center mx-auto mb-4 text-5xl transition-colors">
                            <i class="ph-fill ph-graduation-cap icon-gradient"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-1 transition-colors">No active scholarships right now</h3>
                        <p class="text-slate-500 dark:text-zinc-400 text-sm transition-colors">Please check back later for new educational grants and opportunities.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'public_footer.php'; ?>

    <!-- Script for Mobile Menu and Dark Mode Sync -->
    <script>
        // Synchronize Dark Mode on page load to prevent flashing
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');

        if(mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }
    </script>
</body>
</html>