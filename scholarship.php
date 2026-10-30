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
<html lang="en" class="scroll-smooth">
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
    
    <script>
        tailwind.config = {
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
        .btn-gradient {
            background: linear-gradient(135deg, #15803d 0%, #047857 50%, #ca8a04 100%);
            color: #ffffff;
        }
        .btn-gradient:hover {
            opacity: 0.95;
            box-shadow: 0 10px 25px -3px rgba(21, 128, 61, 0.4);
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
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
    <link rel="stylesheet" href="public-design.css">
</head>
<body data-public-page="scholarship.php" class="tala-public bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white dark:text-zinc-100 dark:bg-zinc-900">

    <!-- Main Navigation -->
    <header class="sticky top-0 z-50">
        <?php include 'navigation.php'; ?>
    </header>

    <!-- CONTENT -->
    <main class="flex-1 w-full">
        <!-- SCHOLARSHIPS & OPPORTUNITIES SECTION -->
        <div id="scholarships" class="py-16 sm:py-20 bg-slate-50 dark:bg-zinc-900">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                
                <div class="flex flex-col sm:flex-row justify-between items-center sm:items-end mb-12 sm:mb-16 scroll-animate text-center sm:text-left gap-4">
                    <div>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight flex items-center justify-center sm:justify-start gap-3 dark:text-white">
                            <i class="ph-fill ph-graduation-cap text-brand-600"></i> Opportunities & Scholarships
                        </h2>
                        <p class="text-slate-600 mt-2 font-medium dark:text-zinc-300">Explore active educational grants and financial assistance programs.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php if (count($scholarships) > 0): ?>
                        <?php foreach ($scholarships as $index => $schol): ?>
                            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200/80 overflow-hidden flex flex-col hover:shadow-lg hover:-translate-y-1 transition-all duration-300 scroll-animate dark:bg-zinc-900 tala-public-card" style="animation-delay: <?= $index * 100 ?>ms;">
                                <div class="p-8 flex flex-col flex-1">
                                    
                                    <div class="w-14 h-14 bg-brand-50 text-brand-700 rounded-[1.25rem] flex items-center justify-center text-3xl mb-6 shadow-sm border border-brand-100">
                                        <i class="ph-fill ph-student"></i>
                                    </div>
                                    
                                    <h3 class="text-xl font-extrabold text-slate-900 leading-snug mb-2 dark:text-white"><?= htmlspecialchars($schol['title']) ?></h3>
                                    <p class="text-[11px] font-extrabold text-brand-600 uppercase tracking-widest mb-4">By: <?= htmlspecialchars($schol['provider']) ?></p>
                                    <p class="text-sm text-slate-600 leading-relaxed mb-8 flex-1 dark:text-zinc-300"><?= htmlspecialchars($schol['description']) ?></p>
                                    
                                    <div class="mt-auto pt-6 border-t border-slate-100 flex items-center justify-between dark:border-zinc-800">
                                        <div class="flex items-center text-[13px] font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-100">
                                            <i class="ph-fill ph-warning-circle mr-1.5 text-lg"></i> Due: <?= date('M j, Y', strtotime($schol['deadline'])) ?>
                                        </div>
                                        
                                        <?php if (!empty($schol['application_link'])): ?>
                                            <a href="<?= htmlspecialchars($schol['application_link']) ?>" target="_blank" class="btn-gradient font-bold py-2.5 px-6 rounded-xl transition-all shadow-sm hover:shadow-md text-sm">Apply &rarr;</a>
                                        <?php else: ?>
                                            <span class="text-[11px] text-slate-400 font-extrabold uppercase tracking-widest dark:text-zinc-500">See MYDO for info</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full bg-white p-16 rounded-[2rem] text-center border border-slate-200 border-dashed shadow-sm scroll-animate dark:bg-zinc-900 dark:border-zinc-700 tala-public-card">
                            <div class="w-20 h-20 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-4 text-4xl dark:bg-zinc-900">
                                <i class="ph-fill ph-graduation-cap"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-1 dark:text-zinc-100">No active scholarships right now</h3>
                            <p class="text-slate-500 text-sm dark:text-zinc-400">Please check back later for new educational grants and opportunities.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'public_footer.php'; ?>

    <!-- Script for Mobile Menu -->
    <script>
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    </script>
</body>
</html>