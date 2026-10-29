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
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white">

    <!-- Main Navigation -->
    <header class="sticky top-0 z-50">
        <nav class="bg-white/90 backdrop-blur-xl shadow-sm">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                <div class="flex justify-between h-20 items-center">
                    <a href="index.php" class="flex items-center gap-3 group">
                        <img src="TALA-LOGO.ico" class="w-12 h-12 object-contain group-hover:scale-105 transition-transform duration-300" alt="TALA Logo" onerror="this.src='https://via.placeholder.com/48?text=TALA'">
                        <div>
                            <h1 class="text-2xl font-extrabold text-brand-900 tracking-tight leading-none">TALA</h1>
                            <span class="bg-gradient-to-r from-green-600 to-yellow-400 bg-clip-text text-transparent font-bold">Tumauini 2026</span>
                        </div>
                    </a>
                    
                    <div class="hidden md:flex items-center space-x-8">
                        <a href="features.php" class="text-sm font-semibold text-gray-500 hover:text-brand-800 transition">Features</a>
                        <a href="updates.php" class="text-sm font-semibold text-gray-500 hover:text-brand-800 transition">Updates</a>
                        <a href="public_events.php" class="text-sm font-semibold text-gray-500 hover:text-brand-800 transition">Events</a>
                        <a href="scholarships.php" class="text-sm font-bold text-brand-800 border-b-2 border-brand-700 py-1 transition">Scholarships</a>
                        <a href="suggestions.php" class="text-sm font-semibold text-gray-500 hover:text-brand-800 transition">Voice</a>
                        <a href="officials.php" class="text-sm font-semibold text-gray-500 hover:text-brand-800 transition">Leaders</a>
                        <a href="public_registration.php" class="text-sm font-bold text-brand-700 hover:text-brand-900 transition">Register Now</a>
                        <a href="login.php" class="btn-gradient font-bold px-6 py-2 rounded-xl flex items-center justify-center shadow-lg hover:-translate-y-1 transition-all">
                            <i class="ph ph-sign-in mr-2 text-lg"></i> Portal
                        </a>
                    </div>

                    <!-- Mobile Menu Button -->
                    <div class="md:hidden flex items-center">
                        <button id="mobile-menu-btn" class="text-slate-600 hover:text-slate-900 p-2 focus:outline-none">
                            <i class="ph ph-list text-3xl"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Navigation Dropdown -->
            <div id="mobile-menu" class="hidden md:hidden bg-white border-b border-slate-100 px-6 py-4 space-y-3">
                <a href="features.php" class="block text-sm font-semibold text-gray-600 hover:text-brand-800">Features</a>
                <a href="updates.php" class="block text-sm font-semibold text-gray-600 hover:text-brand-800">Updates</a>
                <a href="public_events.php" class="block text-sm font-semibold text-gray-600 hover:text-brand-800">Events</a>
                <a href="scholarships.php" class="block text-sm font-bold text-brand-800">Scholarships</a>
                <a href="suggestions.php" class="block text-sm font-semibold text-gray-600 hover:text-brand-800">Voice</a>
                <a href="officials.php" class="block text-sm font-semibold text-gray-600 hover:text-brand-800">Leaders</a>
                <a href="public_registration.php" class="block text-sm font-bold text-brand-700">Register Now</a>
                <a href="login.php" class="btn-gradient font-bold px-5 py-2.5 rounded-xl flex items-center justify-center w-full shadow-md">
                    <i class="ph ph-sign-in mr-2 text-lg"></i> Portal
                </a>
            </div>
        </nav>
    </header>

    <!-- CONTENT -->
    <main class="flex-1 w-full">
        <!-- SCHOLARSHIPS & OPPORTUNITIES SECTION -->
        <div id="scholarships" class="py-16 sm:py-20 bg-slate-50">
            <div class="max-w-7xl mx-auto px-6 lg:px-8">
                
                <div class="flex flex-col sm:flex-row justify-between items-center sm:items-end mb-12 sm:mb-16 scroll-animate text-center sm:text-left gap-4">
                    <div>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight flex items-center justify-center sm:justify-start gap-3">
                            <i class="ph-fill ph-graduation-cap text-brand-600"></i> Opportunities & Scholarships
                        </h2>
                        <p class="text-slate-600 mt-2 font-medium">Explore active educational grants and financial assistance programs.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php if (count($scholarships) > 0): ?>
                        <?php foreach ($scholarships as $index => $schol): ?>
                            <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200/80 overflow-hidden flex flex-col hover:shadow-lg hover:-translate-y-1 transition-all duration-300 scroll-animate" style="animation-delay: <?= $index * 100 ?>ms;">
                                <div class="p-8 flex flex-col flex-1">
                                    
                                    <div class="w-14 h-14 bg-brand-50 text-brand-700 rounded-[1.25rem] flex items-center justify-center text-3xl mb-6 shadow-sm border border-brand-100">
                                        <i class="ph-fill ph-student"></i>
                                    </div>
                                    
                                    <h3 class="text-xl font-extrabold text-slate-900 leading-snug mb-2"><?= htmlspecialchars($schol['title']) ?></h3>
                                    <p class="text-[11px] font-extrabold text-brand-600 uppercase tracking-widest mb-4">By: <?= htmlspecialchars($schol['provider']) ?></p>
                                    <p class="text-sm text-slate-600 leading-relaxed mb-8 flex-1"><?= htmlspecialchars($schol['description']) ?></p>
                                    
                                    <div class="mt-auto pt-6 border-t border-slate-100 flex items-center justify-between">
                                        <div class="flex items-center text-[13px] font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-100">
                                            <i class="ph-fill ph-warning-circle mr-1.5 text-lg"></i> Due: <?= date('M j, Y', strtotime($schol['deadline'])) ?>
                                        </div>
                                        
                                        <?php if (!empty($schol['application_link'])): ?>
                                            <a href="<?= htmlspecialchars($schol['application_link']) ?>" target="_blank" class="btn-gradient font-bold py-2.5 px-6 rounded-xl transition-all shadow-sm hover:shadow-md text-sm">Apply &rarr;</a>
                                        <?php else: ?>
                                            <span class="text-[11px] text-slate-400 font-extrabold uppercase tracking-widest">See MYDO for info</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full bg-white p-16 rounded-[2rem] text-center border border-slate-200 border-dashed shadow-sm scroll-animate">
                            <div class="w-20 h-20 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-4 text-4xl">
                                <i class="ph-fill ph-graduation-cap"></i>
                            </div>
                            <h3 class="text-xl font-bold text-slate-800 mb-1">No active scholarships right now</h3>
                            <p class="text-slate-500 text-sm">Please check back later for new educational grants and opportunities.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-10 px-6 lg:px-8 mt-auto">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <img src="TALA-LOGO.ico" class="w-8 h-8 object-contain" alt="TALA Logo" onerror="this.src='https://via.placeholder.com/32?text=TALA'">
                <p class="text-xs font-semibold text-slate-500">
                    &copy; <?= date('Y') ?> Tumauini Municipal Youth Development Office. All rights reserved.
                </p>
            </div>
            <div class="flex items-center gap-6 text-xs font-semibold text-slate-500">
                <a href="#" class="hover:text-brand-800 transition">Privacy Policy</a>
                <a href="#" class="hover:text-brand-800 transition">Terms of Service</a>
                <a href="#" class="hover:text-brand-800 transition">Contact Support</a>
            </div>
        </div>
    </footer>

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