<?php
// public_disclosure.php - Full Disclosure Policy Public Portal
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

// Auto-create tables if they somehow don't exist yet
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS disclosures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        barangay VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL DEFAULT 'Uncategorized',
        title VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        year INT NOT NULL,
        quarter VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Auto-upgrade if 'category' column is missing from an older version
    $cols = $pdo->query("SHOW COLUMNS FROM disclosures")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('category', $cols)) {
        $pdo->exec("ALTER TABLE disclosures ADD COLUMN category VARCHAR(100) NOT NULL DEFAULT 'Uncategorized' AFTER barangay");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS barangay_settings (
        barangay VARCHAR(255) PRIMARY KEY,
        logo_path VARCHAR(255) NOT NULL
    )");
} catch (PDOException $e) {}

$selected_barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';

if ($selected_barangay) {
    // Fetch ALL disclosures for the specific barangay
    $stmt = $pdo->prepare("SELECT * FROM disclosures WHERE barangay = ? ORDER BY category ASC, year DESC, quarter ASC, created_at DESC");
    $stmt->execute([$selected_barangay]);
    $disclosures = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group disclosures dynamically by Category
    $grouped_docs = [];
    foreach ($disclosures as $d) {
        $cat = !empty($d['category']) ? $d['category'] : 'Uncategorized';
        $grouped_docs[$cat][] = $d;
    }

    // Fetch Logo
    $stmt_logo = $pdo->prepare("SELECT logo_path FROM barangay_settings WHERE barangay = ?");
    $stmt_logo->execute([$selected_barangay]);
    $barangay_logo = $stmt_logo->fetchColumn();
} else {
    // LEVEL 1: DIRECTORIES
    $barangay_summaries = $pdo->query("
        SELECT d.barangay, 
               COUNT(d.id) as total_docs, 
               MAX(d.created_at) as latest_update,
               (SELECT logo_path FROM barangay_settings WHERE barangay = d.barangay LIMIT 1) as logo_path
        FROM disclosures d
        GROUP BY d.barangay 
        ORDER BY d.barangay ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Full Disclosure Board | TALA Tumauini</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { brand: { 50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d', 950: '#021207', 975: '#010803', 990: '#000000' } }
                }
            }
        }
    </script>
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <style>
        .hero-pattern { background-image: radial-gradient(currentColor 1px, transparent 1px); background-size: 24px 24px; }
        .hover-lift { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .hover-lift:hover { transform: translateY(-4px); }
    </style>
</head>
<body class="bg-slate-50 dark:bg-black text-slate-800 dark:text-brand-100 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white transition-colors duration-300">

    <?php include 'navigation.php'; ?>

    <!-- HERO SECTION (Only shows on directory level) -->
    <?php if (!$selected_barangay): ?>
    <div class="bg-white dark:bg-[#010803] border-b border-slate-200 dark:border-[#021207] relative overflow-hidden transition-colors duration-300">
        <div class="absolute inset-0 hero-pattern text-slate-200 dark:text-[#021207] opacity-50 z-0"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-transparent to-slate-50 dark:to-black z-0"></div>
        <div class="max-w-7xl mx-auto px-6 py-12 relative z-10 text-center">
            <p class="text-brand-700 dark:text-brand-400 font-bold tracking-widest text-xs uppercase mb-2"><i class="ph-fill ph-file-text"></i> Good Governance</p>
            <h1 class="text-3xl md:text-5xl font-black text-slate-900 dark:text-white tracking-tight mb-3">Full Disclosure Board</h1>
            <p class="text-slate-500 dark:text-brand-300 font-medium max-w-xl mx-auto text-sm md:text-base">Access and review the official documents, action plans, and resolutions published by your Local Youth Councils.</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- MAIN CONTENT -->
    <main class="flex-1 w-full pt-8 pb-20 max-w-7xl mx-auto px-4 lg:px-8">

        <?php if (!$selected_barangay): ?>
            <!-- LEVEL 1: GRID DIRECTORY -->
            <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 border-b border-slate-200 dark:border-[#021207] pb-4 gap-4">
                <h2 class="text-xl font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="ph-fill ph-map-pin text-brand-600 dark:text-brand-400"></i> Select a Council Directory
                </h2>
            </div>

            <?php if(empty($barangay_summaries)): ?>
                <div class="text-center py-20 text-slate-400 dark:text-brand-300 bg-white dark:bg-[#010803] rounded-[2rem] border border-slate-200 dark:border-[#021207]">
                    <i class="ph-fill ph-folder-open text-5xl mb-3 opacity-50 text-brand-600 dark:text-brand-500"></i>
                    <h2 class="text-lg font-extrabold text-slate-700 dark:text-white mb-1">No Documents Found</h2>
                    <p class="text-xs font-medium">No barangays have published their official documents yet.</p>
                </div>
            <?php else: ?>
                <!-- 5 Columns on Large Screens -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-5">
                    <?php foreach($barangay_summaries as $b): 
                        $is_municipal = ($b['barangay'] === 'Municipal Level');
                        $display_name = $is_municipal ? 'Municipal Admin' : 'Brgy. ' . htmlspecialchars($b['barangay']);
                        $bg_hover = $is_municipal ? 'group-hover:bg-green-100 dark:group-hover:bg-zinc-800' : 'group-hover:bg-brand-100 dark:group-hover:bg-zinc-800';
                        $border_hover = $is_municipal ? 'hover:border-green-600 dark:hover:border-green-500' : 'hover:border-brand-500 dark:hover:border-brand-600';
                    ?>
                        <a href="?barangay=<?= urlencode($b['barangay']) ?>" class="bg-white dark:bg-[#010803] p-5 rounded-[1.5rem] border border-slate-200 dark:border-[#021207] <?= $border_hover ?> hover-lift flex flex-col items-center text-center group transition-all relative overflow-hidden">
                            
                            <div class="w-14 h-14 rounded-full flex items-center justify-center mb-3 <?= $bg_hover ?> transition-all duration-300 border-[3px] border-slate-50 dark:border-[#021207] relative z-10 overflow-hidden bg-slate-100 dark:bg-black">
                                <?php if (!empty($b['logo_path'])): ?>
                                    <img src="<?= htmlspecialchars($b['logo_path']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Logo">
                                <?php else: ?>
                                    <i class="ph-fill <?= $is_municipal ? 'ph-buildings text-green-600 dark:text-green-500' : 'ph-house-line text-brand-500 dark:text-brand-400' ?> text-2xl group-hover:scale-110 transition-transform duration-300"></i>
                                <?php endif; ?>
                            </div>

                            <h3 class="text-sm font-extrabold text-slate-900 dark:text-white leading-tight group-hover:text-slate-700 dark:group-hover:text-brand-100 transition-colors line-clamp-2 relative z-10 mb-1">
                                <?= $display_name ?>
                            </h3>
                            
                            <div class="w-full mt-auto pt-3 border-t border-slate-100 dark:border-[#021207] relative z-10 flex justify-between items-center">
                                <p class="text-[9px] font-bold text-slate-400 dark:text-brand-400 uppercase tracking-widest"><i class="ph-bold ph-files"></i> <?= $b['total_docs'] ?> Docs</p>
                                <span class="bg-brand-50 dark:bg-[#021207] text-brand-700 dark:text-brand-400 text-[9px] font-black uppercase px-2 py-0.5 rounded">View &rarr;</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            
            <!-- LEVEL 2: DIRECT PDF GALLERY VIEW -->
            
            <!-- Exact Mockup Header -->
            <div class="bg-white dark:bg-[#010803] border border-slate-200 dark:border-[#021207] rounded-[2rem] p-6 md:p-8 mb-6 flex flex-col md:flex-row items-center justify-between gap-6 mt-2">
                
                <!-- Left Side: Barangay Logo / Default Scenery -->
                <div class="w-24 h-24 md:w-32 md:h-32 rounded-full border-4 border-slate-50 dark:border-[#021207] flex items-center justify-center overflow-hidden shrink-0 bg-slate-100 dark:bg-black relative">
                    <?php if(!empty($barangay_logo)): ?>
                        <img src="<?= htmlspecialchars($barangay_logo) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full bg-gradient-to-b from-sky-200 to-green-400 dark:from-slate-800 dark:to-brand-900 flex flex-col items-center justify-end relative">
                            <i class="ph-fill ph-cloud text-white text-3xl absolute top-4"></i>
                            <div class="w-full h-1/3 bg-green-500 dark:bg-brand-700 rounded-t-full"></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Center: Text Description -->
                <div class="text-center flex-1">
                    <h2 class="text-base md:text-lg font-bold text-slate-900 dark:text-brand-100">Sangguniang Kabataan of <?= htmlspecialchars($selected_barangay) ?></h2>
                    <h1 class="text-2xl md:text-4xl font-black text-[#0f172a] dark:text-white tracking-tight my-1.5">Full Disclosure Board</h1>
                    <p class="text-slate-600 dark:text-brand-400 font-medium max-w-lg mx-auto text-xs md:text-sm">Access and review the official documents, action plans, and resolutions published by your Local Youth Councils.</p>
                </div>

                <!-- Right Side: Official Municipal Seal Logo -->
                <div class="w-24 h-24 md:w-32 md:h-32 rounded-full border-4 border-slate-50 dark:border-[#021207] flex items-center justify-center overflow-hidden shrink-0 bg-white dark:bg-black p-2">
                    <img src="tumauini.png" class="w-full h-full object-contain">
                </div>

            </div>

            <!-- Breadcrumbs Sub-nav -->
            <div class="flex justify-between items-center mb-6 px-2">
                <div class="flex items-center gap-2 text-xs font-bold">
                    <a href="public_disclosure.php" class="text-slate-500 dark:text-brand-500 hover:text-brand-700 dark:hover:text-brand-300 transition-colors">Council Directories</a>
                    <i class="ph-bold ph-caret-right text-slate-300"></i>
                    <span class="text-brand-800 dark:text-brand-400"><?= htmlspecialchars($selected_barangay) ?></span>
                </div>
                <a href="public_disclosure.php" class="text-xs font-bold text-slate-500 dark:text-brand-500 hover:text-brand-700 dark:hover:text-brand-300 transition-colors">Back to Directories</a>
            </div>

            <!-- Multi-Column PDF Preview Gallery (5 COLUMN STRICT GRID) -->
            <div class="w-full pb-8">
                <!-- Using grid layout locked to 5 columns on desktop -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6 px-2 items-start">
                    
                    <?php if(empty($grouped_docs)): ?>
                        <div class="col-span-full w-full text-center py-16 text-slate-500 dark:text-brand-400 font-medium text-sm">
                            No documents have been published yet for this council.
                        </div>
                    <?php else: ?>
                        <?php foreach($grouped_docs as $category => $docs): ?>
                            <!-- SINGLE COLUMN PER CATEGORY (Full width of the grid cell) -->
                            <div class="w-full flex flex-col gap-4 category-carousel" data-docs='<?= htmlspecialchars(json_encode($docs), ENT_QUOTES, 'UTF-8') ?>' data-current="0">
                                <h3 class="text-[15px] font-black text-center text-slate-900 dark:text-white tracking-tight uppercase px-2 line-clamp-2 min-h-[45px] flex items-center justify-center" title="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></h3>
                                
                                <!-- Curved PDF Card -->
                                <div class="group relative flex flex-col bg-white dark:bg-[#010803] rounded-3xl border border-slate-200 dark:border-[#052e16] transition-all hover-lift overflow-hidden">
                                    
                                    <!-- Page Counter (If Multiple Files) -->
                                    <?php if(count($docs) > 1): ?>
                                    <div class="absolute top-3 right-3 bg-slate-900/80 backdrop-blur-sm text-white text-[9px] font-black px-2 py-0.5 rounded-md z-20 doc-counter pointer-events-none">
                                        1 / <?= count($docs) ?>
                                    </div>
                                    <?php endif; ?>

                                    <!-- A4 Aspect Ratio PDF Live Preview Box -->
                                    <div class="relative w-full aspect-[1/1.414] bg-white dark:bg-black border-b border-slate-100 dark:border-[#021207] overflow-hidden">
                                        
                                        <!-- Scaled down iframe -->
                                        <div class="absolute inset-0 origin-top-left scale-[0.6] w-[166.66%] h-[166.66%] pointer-events-none transition-opacity duration-300">
                                            <iframe src="<?= htmlspecialchars($docs[0]['file_path']) ?>#toolbar=0&navpanes=0&scrollbar=0&view=FitH" class="w-full h-full border-none pdf-iframe" loading="lazy"></iframe>
                                        </div>
                                        
                                        <!-- Hover Overlay -->
                                        <div class="absolute inset-0 bg-slate-900/70 dark:bg-black/80 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10 backdrop-blur-[2px]">
                                            <a href="<?= htmlspecialchars($docs[0]['file_path']) ?>" target="_blank" class="bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2.5 px-5 rounded-full transform translate-y-3 group-hover:translate-y-0 transition-all duration-300 flex items-center gap-1.5 text-xs view-btn">
                                                <i class="ph-bold ph-eye text-base"></i> View PDF
                                            </a>
                                        </div>

                                    </div>

                                    <!-- Document Details & Controls -->
                                    <div class="p-4 text-center bg-slate-50 dark:bg-[#021207] flex flex-col flex-1 min-h-[110px]">
                                        <div class="flex-1 flex flex-col justify-center mb-3">
                                            <h4 class="font-extrabold text-slate-800 dark:text-brand-100 text-[11px] leading-snug line-clamp-2 doc-title" title="<?= htmlspecialchars($docs[0]['title']) ?>"><?= htmlspecialchars($docs[0]['title']) ?></h4>
                                            <p class="text-[8px] font-bold text-brand-600 dark:text-brand-500 uppercase tracking-widest mt-1 doc-meta">
                                                <?= htmlspecialchars($docs[0]['quarter']) ?> <?= htmlspecialchars($docs[0]['year']) ?>
                                            </p>
                                        </div>
                                        
                                        <!-- Modern Next Button -->
                                        <div class="mt-auto" style="display: <?= count($docs) > 1 ? 'block' : 'none' ?>;">
                                            <button type="button" class="w-full bg-white dark:bg-black hover:bg-brand-50 dark:hover:bg-[#052e16] text-slate-600 hover:text-brand-600 dark:text-brand-400 border border-slate-200 dark:border-[#052e16] py-1.5 px-3 rounded-xl flex items-center justify-between transition-all next-btn group/btn outline-none">
                                                <span class="text-[9px] font-black uppercase tracking-widest">Next File</span>
                                                <div class="w-5 h-5 rounded-full bg-brand-100 dark:bg-brand-900/30 text-brand-600 dark:text-brand-400 flex items-center justify-center group-hover/btn:bg-brand-600 group-hover/btn:text-white transition-colors">
                                                    <i class="ph-bold ph-caret-right text-xs"></i>
                                                </div>
                                            </button>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

            <!-- JAVASCRIPT FOR CAROUSEL BEHAVIOR -->
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    document.querySelectorAll('.category-carousel').forEach(carousel => {
                        const docs = JSON.parse(carousel.getAttribute('data-docs'));
                        if (docs.length <= 1) return; 
                        
                        const nextBtn = carousel.querySelector('.next-btn');
                        const iframe = carousel.querySelector('.pdf-iframe');
                        const viewBtn = carousel.querySelector('.view-btn');
                        const titleEl = carousel.querySelector('.doc-title');
                        const metaEl = carousel.querySelector('.doc-meta');
                        const counterEl = carousel.querySelector('.doc-counter');
                        
                        nextBtn.addEventListener('click', () => {
                            // Fade out for smooth transition
                            iframe.style.opacity = 0;
                            titleEl.style.opacity = 0;
                            metaEl.style.opacity = 0;
                            
                            setTimeout(() => {
                                let current = parseInt(carousel.getAttribute('data-current'));
                                current = (current + 1) % docs.length;
                                carousel.setAttribute('data-current', current);
                                
                                const doc = docs[current];
                                
                                // Update elements
                                iframe.src = doc.file_path + '#toolbar=0&navpanes=0&scrollbar=0&view=FitH';
                                viewBtn.href = doc.file_path;
                                titleEl.textContent = doc.title;
                                titleEl.title = doc.title;
                                metaEl.textContent = doc.quarter + ' ' + doc.year;
                                
                                if (counterEl) {
                                    counterEl.textContent = (current + 1) + ' / ' + docs.length;
                                }
                                
                                // Fade back in
                                iframe.style.opacity = 1;
                                titleEl.style.opacity = 1;
                                metaEl.style.opacity = 1;
                            }, 150); // Matches transition duration
                        });
                    });
                });
            </script>

        <?php endif; ?>
    </main>

    <footer class="bg-white dark:bg-[#010803] border-t border-slate-200 dark:border-[#021207] py-8 px-6 lg:px-8 mt-auto">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <img src="TALA-LOGO.ico" class="w-6 h-6 object-contain dark:opacity-50" alt="TALA Logo">
                <p class="text-[11px] font-semibold text-slate-500 dark:text-brand-300">&copy; <?= date('Y') ?> Tumauini Municipal Youth Development Office. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>