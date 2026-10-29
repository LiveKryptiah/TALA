<?php
// updates.php - TALA Tumauini 2026 Community Updates & Announcements
error_reporting(E_ALL);
ini_set('display_errors', 1);

$announcements = [];
$db_connected = false;

try {
    require 'db_connect.php';
    $db_connected = true;
    // Fetch ALL announcements
    $announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    $db_connected = false; 
}

// Data Processing: Auto-categorize and determine the featured post
$featured_announcement = null;
if (count($announcements) > 0) {
    $featured_announcement = $announcements[0]; // First item is featured
}

// Helper function to auto-assign a category based on content/title (if your DB doesn't have a category column)
function getCategory($ann) {
    if (isset($ann['category']) && !empty($ann['category'])) return $ann['category'];
    $text = strtolower($ann['title'] . ' ' . $ann['content']);
    if (strpos($text, 'scholar') !== false) return 'Scholarships';
    if (strpos($text, 'project') !== false || strpos($text, 'sk') !== false) return 'Youth Projects';
    if (strpos($text, 'event') !== false || strpos($text, 'sports') !== false) return 'Community';
    return 'Announcements';
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Updates & News - TALA Tumauini 2026</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">

    <!-- Fonts & Tailwind CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

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
            background: #15803d;
            color: #ffffff;
            transition: all 0.2s ease;
        }
        .btn-gradient:hover {
            background: #166534;
            transform: translateY(-1px);
        }
        .dark .btn-gradient {
            background: #16a34a;
        }
        .dark .btn-gradient:hover {
            background: #15803d;
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
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white transition-colors duration-300">

    <!-- Include Centralized Navigation -->
    <?php include 'navigation.php'; ?>

    <!-- CONTENT -->
    <main class="flex-1 w-full pt-16 pb-24 max-w-7xl mx-auto px-6 lg:px-8">
        
        <!-- Inline Page Header -->
        <div class="mb-10 text-left">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight transition-colors">
                <span class="text-gradient">Community Updates</span>
            </h1>
        </div>

        <!-- Search & Category Filters -->
        <div class="flex flex-col md:flex-row gap-4 items-center justify-between bg-white p-4 rounded-2xl mb-12">
            
            <!-- Category Pills -->
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto" id="filter-container">
                <button onclick="filterCategory('All')" class="filter-btn active-filter text-xs sm:text-sm font-bold px-4 py-2.5 rounded-xl transition btn-gradient border border-transparent">
                    All Updates
                </button>
                <button onclick="filterCategory('Announcements')" class="filter-btn text-xs sm:text-sm font-semibold px-4 py-2.5 rounded-xl transition bg-slate-50 border border-slate-100 text-slate-600 hover:bg-slate-100">
                    Announcements
                </button>
                <button onclick="filterCategory('Scholarships')" class="filter-btn text-xs sm:text-sm font-semibold px-4 py-2.5 rounded-xl transition bg-slate-50 border border-slate-100 text-slate-600 hover:bg-slate-100">
                    Scholarships
                </button>
                <button onclick="filterCategory('Youth Projects')" class="filter-btn text-xs sm:text-sm font-semibold px-4 py-2.5 rounded-xl transition bg-slate-50 border border-slate-100 text-slate-600 hover:bg-slate-100">
                    Youth Projects
                </button>
                <button onclick="filterCategory('Community')" class="filter-btn text-xs sm:text-sm font-semibold px-4 py-2.5 rounded-xl transition bg-slate-50 border border-slate-100 text-slate-600 hover:bg-slate-100">
                    Community
                </button>
            </div>

            <!-- Search Bar -->
            <div class="relative w-full md:w-80">
                <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                <input type="text" id="search-input" onkeyup="searchUpdates()" placeholder="Search updates..." 
                       class="w-full pl-11 pr-4 py-2.5 rounded-xl text-sm font-medium bg-slate-50 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-600 focus:bg-white transition placeholder:font-normal">
            </div>
        </div>

        <?php if ($featured_announcement): 
            $cat = getCategory($featured_announcement);
            $img = !empty($featured_announcement['image_path']) ? htmlspecialchars($featured_announcement['image_path']) : 'https://images.unsplash.com/photo-1531482615713-2afd69097998?auto=format&fit=crop&w=1200&q=80';
        ?>
        <!-- Featured Hero Update Card -->
        <div class="mb-12 update-card" data-category="<?= htmlspecialchars($cat) ?>" data-title="<?= htmlspecialchars(strtolower($featured_announcement['title'])) ?>">
            <div class="bg-white rounded-[2rem] overflow-hidden transition-all duration-300 grid grid-cols-1 lg:grid-cols-12">
                <div class="lg:col-span-7 relative min-h-[260px] sm:min-h-[380px] bg-slate-100">
                    <img src="<?= $img ?>" alt="Featured Update" class="absolute inset-0 w-full h-full object-cover">
                    <span class="absolute top-5 left-5 bg-brand-800 text-white text-[11px] font-extrabold px-3.5 py-1.5 rounded-lg uppercase tracking-widest">
                        Featured Story
                    </span>
                </div>
                <div class="lg:col-span-5 p-8 sm:p-10 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-3 text-xs font-bold text-slate-500 mb-4">
                            <span class="bg-brand-50 text-brand-700 border border-brand-100 px-3 py-1 rounded-lg">
                                <?= htmlspecialchars($cat) ?>
                            </span>
                            <span>•</span>
                            <span class="flex items-center gap-1.5">
                                <i class="ph-bold ph-calendar-blank icon-gradient"></i> <?= date('F j, Y', strtotime($featured_announcement['created_at'])) ?>
                            </span>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 leading-snug mb-4 hover:text-brand-800 transition">
                            <?= htmlspecialchars($featured_announcement['title']) ?>
                        </h2>
                        <p class="text-slate-600 text-sm sm:text-base leading-relaxed mb-6 line-clamp-4">
                            <?= htmlspecialchars($featured_announcement['content']) ?>
                        </p>
                    </div>

                    <div class="flex items-center justify-between pt-5 border-t border-slate-100 mt-4">
                        <span class="text-xs font-bold text-slate-500 flex items-center gap-2">
                            <span class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                                <i class="ph-fill ph-user text-lg"></i>
                            </span>
                            By <?= htmlspecialchars($featured_announcement['created_by']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Grid of All Updates -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="updates-grid">
            <?php foreach ($announcements as $index => $ann): ?>
                <?php 
                    if ($index === 0) continue; // Skip featured
                    $cat = getCategory($ann);
                    $img = !empty($ann['image_path']) ? htmlspecialchars($ann['image_path']) : 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=800&q=80';
                ?>
                <article class="update-card bg-white rounded-3xl overflow-hidden hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between"
                         data-category="<?= htmlspecialchars($cat) ?>"
                         data-title="<?= htmlspecialchars(strtolower($ann['title'])) ?>">
                    
                    <div>
                        <!-- Card Image Header -->
                        <div class="relative h-52 bg-slate-100 overflow-hidden">
                            <img src="<?= $img ?>" alt="Update thumbnail" class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                            <span class="absolute top-4 left-4 bg-white/95 backdrop-blur-md text-slate-800 text-[10px] font-extrabold px-3 py-1.5 rounded-lg uppercase tracking-widest">
                                <?= htmlspecialchars($cat) ?>
                            </span>
                        </div>

                        <!-- Card Body -->
                        <div class="p-6 sm:p-8">
                            <div class="flex items-center gap-1.5 text-[11px] font-bold text-slate-400 mb-3 uppercase tracking-widest">
                                <i class="ph-bold ph-calendar-blank icon-gradient text-sm"></i>
                                <span><?= date('M j, Y', strtotime($ann['created_at'])) ?></span>
                            </div>

                            <h3 class="text-xl font-extrabold text-slate-900 leading-snug mb-3 hover:text-brand-800 transition line-clamp-2">
                                <?= htmlspecialchars($ann['title']) ?>
                            </h3>

                            <p class="text-slate-600 text-sm leading-relaxed line-clamp-3 mb-4 whitespace-pre-line">
                                <?= htmlspecialchars($ann['content']) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="px-6 sm:px-8 pb-6 sm:pb-8 pt-2 flex items-center justify-between border-t border-slate-50">
                        <span class="text-xs font-bold text-slate-500 flex items-center gap-1.5 truncate max-w-[150px]">
                            <i class="ph-fill ph-user-circle text-lg text-slate-300"></i> <?= htmlspecialchars($ann['created_by']) ?>
                        </span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if(empty($announcements)): ?>
            <!-- Completely Empty Database Fallback -->
            <div class="bg-white p-16 rounded-[2rem] text-center mt-6">
                <div class="w-20 h-20 flex items-center justify-center mx-auto mb-4 text-5xl"><i class="ph-fill ph-megaphone icon-gradient"></i></div>
                <h3 class="text-xl font-bold text-slate-800 mb-1">No Public Announcements</h3>
                <p class="text-slate-500 text-sm">Check back later for updates from the Municipal Youth Development Office.</p>
            </div>
        <?php endif; ?>

        <!-- No Results Search Fallback -->
        <div id="no-results" class="hidden text-center py-16 bg-white rounded-[2rem] mt-6">
            <i class="ph-fill ph-newspaper-clipping text-5xl icon-gradient mb-4 inline-block"></i>
            <h3 class="text-xl font-bold text-slate-800 mb-1">No Updates Found</h3>
            <p class="text-sm text-slate-500">Try adjusting your search query or category filter.</p>
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

    <!-- Interactive Scripts for Filtering & Search -->
    <script>
        // Category Filter Logic
        let currentCategory = 'All';

        function filterCategory(category) {
            currentCategory = category;

            // Update Button Styles
            const buttons = document.querySelectorAll('.filter-btn');
            buttons.forEach(btn => {
                if (btn.innerText.trim().toLowerCase() === category.toLowerCase() || (category === 'All' && btn.innerText.trim() === 'All Updates')) {
                    // Applied btn-gradient here!
                    btn.className = 'filter-btn active-filter text-xs sm:text-sm font-bold px-4 py-2.5 rounded-xl transition btn-gradient border border-transparent';
                } else {
                    btn.className = 'filter-btn text-xs sm:text-sm font-semibold px-4 py-2.5 rounded-xl transition bg-slate-50 border border-slate-100 text-slate-600 hover:bg-slate-100';
                }
            });

            applyFilters();
        }

        // Search Logic
        function searchUpdates() {
            applyFilters();
        }

        // Apply Search & Filter Combination
        function applyFilters() {
            const query = document.getElementById('search-input').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.update-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const cardCategory = card.getAttribute('data-category');
                const cardTitle = card.getAttribute('data-title');

                const matchesCategory = (currentCategory === 'All' || cardCategory === currentCategory);
                const matchesSearch = (cardTitle.includes(query));

                if (matchesCategory && matchesSearch) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });

            // Handle Empty State
            const noResults = document.getElementById('no-results');
            if (visibleCount === 0 && cards.length > 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }
    </script>
</body>
</html>