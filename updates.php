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
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
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

    <link rel="stylesheet" href="public-design.css">
    <link rel="stylesheet" href="public-updates.css">
</head>
<body data-public-page="updates.php" class="tala-public bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white transition-colors duration-300">

    <!-- Include Centralized Navigation -->
    <?php include 'navigation.php'; ?>

    <!-- CONTENT -->
    <main class="flex-1 w-full pt-16 pb-24 max-w-7xl mx-auto px-6 lg:px-8">
        
        <!-- Inline Page Header -->
        <div class="updates-heading">
            <p class="updates-eyebrow">THE LATEST FROM OUR COMMUNITY</p>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight transition-colors">
                Community Updates
            </h1>
            <p class="updates-intro">Stay connected with youth programs, opportunities, and stories from across Tumauini.</p>
        </div>

        <!-- Search & Category Filters -->
        <div class="updates-filters tala-public-card">
            
            <!-- Category Pills -->
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto" id="filter-container" role="group" aria-label="Filter updates by category">
                <button type="button" aria-pressed="true" onclick="filterCategory('All')" class="filter-btn active-filter text-xs sm:text-sm font-bold px-4 py-2.5 rounded-xl transition btn-gradient border border-transparent">
                    All Updates
                </button>
                <button type="button" aria-pressed="false" onclick="filterCategory('Announcements')" class="filter-btn text-xs sm:text-sm font-semibold px-4 py-2.5 rounded-xl transition bg-slate-50 border border-slate-100 text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:bg-zinc-900 dark:border-zinc-800">
                    Announcements
                </button>
                <button type="button" aria-pressed="false" onclick="filterCategory('Scholarships')" class="filter-btn text-xs sm:text-sm font-semibold px-4 py-2.5 rounded-xl transition bg-slate-50 border border-slate-100 text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:bg-zinc-900 dark:border-zinc-800">
                    Scholarships
                </button>
                <button type="button" aria-pressed="false" onclick="filterCategory('Youth Projects')" class="filter-btn text-xs sm:text-sm font-semibold px-4 py-2.5 rounded-xl transition bg-slate-50 border border-slate-100 text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:bg-zinc-900 dark:border-zinc-800">
                    Youth Projects
                </button>
                <button type="button" aria-pressed="false" onclick="filterCategory('Community')" class="filter-btn text-xs sm:text-sm font-semibold px-4 py-2.5 rounded-xl transition bg-slate-50 border border-slate-100 text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:bg-zinc-900 dark:border-zinc-800">
                    Community
                </button>
            </div>

            <!-- Search Bar -->
            <div class="updates-search relative w-full">
                <label for="search-input" class="sr-only">Search community updates</label>
                <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg dark:text-zinc-500"></i>
                <input type="text" id="search-input" oninput="searchUpdates()" placeholder="Search updates..."
                       class="w-full pl-11 pr-4 py-2.5 rounded-xl text-sm font-medium bg-slate-50 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-brand-600 focus:bg-white transition placeholder:font-normal dark:bg-zinc-900 dark:border-zinc-700">
            </div>
        </div>

        <?php if ($featured_announcement): 
            $cat = getCategory($featured_announcement);
            $img = !empty($featured_announcement['image_path']) ? htmlspecialchars($featured_announcement['image_path']) : 'assets/centers/governance.webp';
        ?>
        <!-- Featured Hero Update Card -->
        <div class="updates-featured mb-8 update-card" data-category="<?= htmlspecialchars($cat) ?>" data-title="<?= htmlspecialchars(strtolower($featured_announcement['title'])) ?>">
            <div class="bg-white rounded-[2rem] overflow-hidden transition-all duration-300 grid grid-cols-1 lg:grid-cols-12 dark:bg-zinc-900 tala-public-card">
                <div class="lg:col-span-7 relative min-h-[260px] sm:min-h-[380px] bg-slate-100 dark:bg-zinc-800">
                    <img src="<?= $img ?>" alt="<?= htmlspecialchars($featured_announcement['title']) ?>" decoding="async" class="absolute inset-0 w-full h-full object-cover">
                    <span class="absolute top-5 left-5 bg-brand-800 text-white text-[11px] font-extrabold px-3.5 py-1.5 rounded-lg uppercase tracking-widest">
                        Featured Story
                    </span>
                </div>
                <div class="lg:col-span-5 p-8 sm:p-10 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-3 text-xs font-bold text-slate-500 mb-4 dark:text-zinc-400">
                            <span class="bg-brand-50 text-brand-700 border border-brand-100 px-3 py-1 rounded-lg">
                                <?= htmlspecialchars($cat) ?>
                            </span>
                            <span>•</span>
                            <span class="flex items-center gap-1.5">
                                <i class="ph-bold ph-calendar-blank icon-gradient"></i> <?= date('F j, Y', strtotime($featured_announcement['created_at'])) ?>
                            </span>
                        </div>
                        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 leading-snug mb-4 hover:text-brand-800 transition dark:text-white">
                            <?= htmlspecialchars($featured_announcement['title']) ?>
                        </h2>
                        <p class="text-slate-600 text-sm sm:text-base leading-relaxed mb-6 line-clamp-4 dark:text-zinc-300">
                            <?= htmlspecialchars($featured_announcement['content']) ?>
                        </p>
                    </div>

                    <div class="flex items-center justify-between pt-5 border-t border-slate-100 mt-4 dark:border-zinc-800">
                        <span class="text-xs font-bold text-slate-500 flex items-center gap-2 dark:text-zinc-400">
                            <span class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 dark:text-zinc-500 dark:bg-zinc-800">
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
                    $img = !empty($ann['image_path']) ? htmlspecialchars($ann['image_path']) : 'assets/centers/citizenship.webp';
                ?>
                <article class="update-card bg-white rounded-3xl overflow-hidden hover:-translate-y-1 transition-all duration-300 flex flex-col justify-between dark:bg-zinc-900 tala-public-card"
                         data-category="<?= htmlspecialchars($cat) ?>"
                         data-title="<?= htmlspecialchars(strtolower($ann['title'])) ?>">
                    
                    <div>
                        <!-- Card Image Header -->
                        <div class="relative h-52 bg-slate-100 overflow-hidden dark:bg-zinc-800">
                            <img src="<?= $img ?>" alt="<?= htmlspecialchars($ann['title']) ?>" loading="lazy" decoding="async" class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                            <span class="absolute top-4 left-4 bg-white/95 backdrop-blur-md text-slate-800 text-[10px] font-extrabold px-3 py-1.5 rounded-lg uppercase tracking-widest dark:text-zinc-100">
                                <?= htmlspecialchars($cat) ?>
                            </span>
                        </div>

                        <!-- Card Body -->
                        <div class="p-6 sm:p-8">
                            <div class="flex items-center gap-1.5 text-[11px] font-bold text-slate-400 mb-3 uppercase tracking-widest dark:text-zinc-500">
                                <i class="ph-bold ph-calendar-blank icon-gradient text-sm"></i>
                                <span><?= date('M j, Y', strtotime($ann['created_at'])) ?></span>
                            </div>

                            <h3 class="text-xl font-extrabold text-slate-900 leading-snug mb-3 hover:text-brand-800 transition line-clamp-2 dark:text-white">
                                <?= htmlspecialchars($ann['title']) ?>
                            </h3>

                            <p class="text-slate-600 text-sm leading-relaxed line-clamp-3 mb-4 whitespace-pre-line dark:text-zinc-300">
                                <?= htmlspecialchars($ann['content']) ?>
                            </p>
                        </div>
                    </div>

                    <!-- Card Footer -->
                    <div class="px-6 sm:px-8 pb-6 sm:pb-8 pt-2 flex items-center justify-between border-t border-slate-50">
                        <span class="text-xs font-bold text-slate-500 flex items-center gap-1.5 truncate max-w-[150px] dark:text-zinc-400">
                            <i class="ph-fill ph-user-circle text-lg text-slate-300"></i> <?= htmlspecialchars($ann['created_by']) ?>
                        </span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if(empty($announcements)): ?>
            <!-- Completely Empty Database Fallback -->
            <div class="updates-empty bg-white p-8 sm:p-16 rounded-[2rem] text-center mt-6 dark:bg-zinc-900 tala-public-card">
                <div class="w-20 h-20 flex items-center justify-center mx-auto mb-4 text-5xl"><i class="ph-fill ph-megaphone icon-gradient"></i></div>
                <h3 class="text-xl font-bold text-slate-800 mb-1 dark:text-zinc-100">No Public Announcements</h3>
                <p class="text-slate-500 text-sm dark:text-zinc-400">Check back later for updates from the Municipal Youth Development Office.</p>
            </div>
        <?php endif; ?>

        <!-- No Results Search Fallback -->
        <div id="no-results" role="status" class="hidden text-center py-16 bg-white rounded-[2rem] mt-6 dark:bg-zinc-900 tala-public-card">
            <i class="ph-fill ph-newspaper-clipping text-5xl icon-gradient mb-4 inline-block"></i>
            <h3 class="text-xl font-bold text-slate-800 mb-1 dark:text-zinc-100">No Updates Found</h3>
            <p class="text-sm text-slate-500 dark:text-zinc-400">Try adjusting your search query or category filter.</p>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'public_footer.php'; ?>

    <!-- Interactive Scripts for Filtering & Search -->
    <script>
        // Category Filter Logic
        let currentCategory = 'All';

        function filterCategory(category) {
            currentCategory = category;

            const buttons = document.querySelectorAll('.filter-btn');
            buttons.forEach(btn => {
                const active = btn.textContent.trim() === category || (category === 'All' && btn.textContent.trim() === 'All Updates');
                btn.classList.toggle('active-filter', active);
                btn.classList.toggle('btn-gradient', active);
                btn.setAttribute('aria-pressed', String(active));
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
