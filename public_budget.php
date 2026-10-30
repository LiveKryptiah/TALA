<?php
// public_budget.php - Public Budget Transparency
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db_connect.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `budget_monitoring` (
        `id` INT AUTO_INCREMENT PRIMARY KEY, 
        `barangay` VARCHAR(255) NOT NULL, 
        `program` VARCHAR(255) NOT NULL, 
        `project_activity` TEXT NOT NULL, 
        `duration` VARCHAR(255) NOT NULL, 
        `amount` DECIMAL(15,2) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

$selected_barangay = isset($_GET['barangay']) ? $_GET['barangay'] : '';

if ($selected_barangay) {
    $stmt = $pdo->prepare("SELECT * FROM budget_monitoring WHERE barangay = ? ORDER BY created_at DESC");
    $stmt->execute([$selected_barangay]);
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_budget = 0;
    foreach ($budgets as $b) {
        $total_budget += $b['amount'];
    }
} else {
    $barangay_summaries = $pdo->query("
        SELECT barangay, SUM(amount) as total_budget, COUNT(id) as total_programs 
        FROM budget_monitoring GROUP BY barangay ORDER BY barangay ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Transparency | TALA Tumauini</title>
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
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
    <link rel="stylesheet" href="public-design.css">
</head>
<body data-public-page="public_budget.php" class="tala-public bg-slate-50 dark:bg-black text-slate-800 dark:text-brand-100 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white transition-colors duration-300">

    <?php include 'navigation.php'; ?>

    <!-- HERO SECTION -->
    <div class="bg-white dark:bg-[#010803] border-b border-slate-200 dark:border-[#021207] relative overflow-hidden transition-colors duration-300">
        <div class="absolute inset-0 hero-pattern text-slate-200 dark:text-[#021207] opacity-50 z-0"></div>
        <div class="absolute inset-0 bg-gradient-to-b from-transparent to-slate-50 dark:to-black z-0"></div>
        <div class="max-w-[1200px] mx-auto px-6 py-16 relative z-10 text-center">
            <p class="text-brand-700 dark:text-brand-400 font-bold tracking-widest text-xs uppercase mb-3"><i class="ph-fill ph-chart-pie-slice"></i> Financial Transparency</p>
            <h1 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white tracking-tight mb-4">Budget Allocation Ledger</h1>
            <p class="text-slate-500 dark:text-brand-300 font-medium max-w-xl mx-auto">Track and monitor Municipal and SK Youth Budgets across the communities of Tumauini.</p>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main class="flex-1 w-full pt-12 pb-24 max-w-[1200px] mx-auto px-6 lg:px-8">

        <?php if (!$selected_barangay): ?>
            <!-- LEVEL 1: GRID -->
            <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 border-b border-slate-200 dark:border-[#021207] pb-4 gap-4">
                <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="ph-fill ph-map-pin text-brand-600 dark:text-brand-400"></i> Select a Council Directory
                </h2>
            </div>

            <?php if(empty($barangay_summaries)): ?>
                <div class="text-center py-24 text-slate-400 dark:text-brand-300 bg-white dark:bg-[#010803] rounded-[2rem] border border-slate-200 dark:border-[#021207] tala-public-card">
                    <i class="ph-fill ph-empty text-6xl mb-4 opacity-50 text-brand-600 dark:text-brand-500"></i>
                    <h2 class="text-xl font-extrabold text-slate-700 dark:text-white mb-2">No Budgets Recorded</h2>
                    <p class="text-sm font-medium">There are currently no budget allocations registered in the system.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach($barangay_summaries as $b): 
                        $is_municipal = ($b['barangay'] === 'Municipal Level');
                        $display_name = $is_municipal ? 'LGU Municipal Admin' : 'Barangay ' . htmlspecialchars($b['barangay']);
                        $icon = $is_municipal ? 'ph-buildings text-green-600 dark:text-green-500' : 'ph-house-line text-brand-600 dark:text-brand-400';
                        $bg_hover = $is_municipal ? 'group-hover:bg-green-100 dark:group-hover:bg-zinc-800' : 'group-hover:bg-brand-100 dark:group-hover:bg-zinc-800';
                        $border_hover = $is_municipal ? 'hover:border-green-600 dark:hover:border-green-500' : 'hover:border-brand-500 dark:hover:border-brand-600';
                    ?>
                        <a href="?barangay=<?= urlencode($b['barangay']) ?>" class="bg-white dark:bg-[#010803] p-6 rounded-[2rem] border border-slate-200 dark:border-[#021207] <?= $border_hover ?> hover-lift flex flex-col items-center text-center group transition-all relative overflow-hidden">
                            <div class="w-16 h-16 bg-slate-50 dark:bg-black rounded-2xl flex items-center justify-center mb-4 <?= $bg_hover ?> transition-colors border border-slate-100 dark:border-[#021207] relative z-10">
                                <i class="ph-fill <?= $icon ?> text-3xl group-hover:scale-110 transition-transform duration-300"></i>
                            </div>
                            <h3 class="text-base font-extrabold text-slate-900 dark:text-white leading-tight group-hover:text-slate-700 dark:group-hover:text-brand-100 transition-colors line-clamp-2 relative z-10 mb-1">
                                <?= $display_name ?>
                            </h3>
                            <p class="text-[10px] font-bold text-slate-400 dark:text-brand-300 uppercase tracking-widest relative z-10 mb-4">
                                <?= $b['total_programs'] ?> Program(s)
                            </p>
                            <div class="w-full mt-auto pt-4 border-t border-slate-100 dark:border-[#021207] relative z-10">
                                <p class="text-[10px] font-bold text-slate-400 dark:text-brand-400 uppercase tracking-widest mb-0.5">Total Allocation</p>
                                <p class="text-lg font-black text-brand-700 dark:text-brand-500">₱<?= number_format($b['total_budget'], 2) ?></p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- LEVEL 2: DETAILED VIEW -->
            <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-8 border-b border-slate-200 dark:border-[#021207] pb-4 gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-4 overflow-x-auto whitespace-nowrap text-xs font-bold text-slate-500 dark:text-brand-300">
                        <a href="public_budget.php" class="hover:text-brand-600 dark:hover:text-brand-400 transition-colors">Council Directories</a>
                        <i class="ph-bold ph-caret-right text-[10px]"></i>
                        <span class="text-slate-800 dark:text-white bg-slate-100 dark:bg-[#010803] px-2 py-1 rounded-md border border-slate-200 dark:border-[#021207]"><?= htmlspecialchars($selected_barangay) ?></span>
                    </div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white flex items-center gap-2">
                        <?php if($selected_barangay === 'Municipal Level'): ?>
                            <i class="ph-fill ph-buildings text-green-600 dark:text-green-500"></i> Municipal Administration
                        <?php else: ?>
                            <i class="ph-fill ph-house-line text-brand-600 dark:text-brand-400"></i> Barangay <?= htmlspecialchars($selected_barangay) ?>
                        <?php endif; ?>
                    </h2>
                </div>
                <a href="public_budget.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 dark:text-brand-200 hover:text-brand-700 dark:hover:text-white bg-white dark:bg-[#010803] px-4 py-2 rounded-xl border border-slate-200 dark:border-[#021207] transition-all">
                    <i class="ph-bold ph-arrow-left text-base"></i> Back to Directories
                </a>
            </div>

            <!-- Total Budget Summary Card -->
            <div class="bg-gradient-to-r from-slate-900 to-slate-800 dark:from-[#010803] dark:to-black p-8 rounded-[2rem] border border-slate-700 dark:border-[#021207] flex items-center justify-between text-white overflow-hidden relative mb-8">
                <i class="ph-fill ph-coins absolute -right-4 -bottom-12 text-[160px] text-white opacity-5"></i>
                <div class="relative z-10">
                    <p class="text-slate-400 dark:text-brand-400 text-xs font-bold uppercase tracking-widest mb-2">Total Budget Allocation</p>
                    <h2 class="text-4xl sm:text-5xl font-black tracking-tight text-green-400">₱<?= number_format($total_budget, 2) ?></h2>
                </div>
                <div class="hidden sm:flex flex-col text-right relative z-10 bg-white/10 dark:bg-white/5 backdrop-blur-sm px-6 py-4 rounded-2xl border border-white/10 tala-public-card">
                    <span class="text-2xl font-black text-white"><?= count($budgets) ?></span>
                    <span class="text-[10px] font-bold text-slate-300 dark:text-brand-300 uppercase tracking-widest">Active Programs</span>
                </div>
            </div>

            <!-- Budget Table -->
            <div class="bg-white dark:bg-[#010803] rounded-3xl border border-slate-200 dark:border-[#021207] overflow-hidden tala-public-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[800px]">
                        <thead>
                            <tr class="text-[10px] font-bold text-slate-400 dark:text-brand-400 uppercase tracking-widest border-b border-slate-100 dark:border-[#021207] bg-slate-50/50 dark:bg-black">
                                <th class="py-5 px-6">Program Focus</th>
                                <th class="py-5 px-6">Specific Activity/Project</th>
                                <th class="py-5 px-6">Duration</th>
                                <th class="py-5 px-6 text-right">Amount (₱)</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-[#021207]">
                            <?php if(empty($budgets)): ?>
                                <tr>
                                    <td colspan="4" class="py-20 text-center">
                                        <i class="ph-fill ph-empty text-5xl text-slate-300 dark:text-brand-600 mb-3 inline-block"></i>
                                        <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-1">No Budgets Found</h3>
                                        <p class="text-sm text-slate-500 dark:text-brand-300">There are currently no allocations recorded for this selection.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($budgets as $row): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-[#021207] transition-colors">
                                        <td class="py-5 px-6 align-top">
                                            <div class="font-extrabold text-slate-900 dark:text-white text-[15px] mb-1"><?= htmlspecialchars($row['program']) ?></div>
                                            <span class="text-[10px] font-bold text-slate-400 dark:text-brand-300 bg-slate-100 dark:bg-black px-2 py-0.5 rounded border border-slate-200 dark:border-[#021207] inline-block">
                                                Added: <?= date('M j, Y', strtotime($row['created_at'])) ?>
                                            </span>
                                        </td>
                                        <td class="py-5 px-6 align-top">
                                            <div class="text-slate-600 dark:text-brand-200 leading-relaxed max-w-md whitespace-pre-wrap text-sm"><?= htmlspecialchars($row['project_activity']) ?></div>
                                        </td>
                                        <td class="py-5 px-6 text-slate-500 dark:text-brand-300 font-bold text-xs whitespace-nowrap align-top">
                                            <div class="flex items-center gap-1.5 bg-slate-50 dark:bg-black border border-slate-100 dark:border-[#021207] px-3 py-1.5 rounded-lg w-fit">
                                                <i class="ph-bold ph-calendar-blank text-slate-400 dark:text-brand-400"></i> <?= htmlspecialchars($row['duration']) ?>
                                            </div>
                                        </td>
                                        <td class="py-5 px-6 text-right align-top">
                                            <span class="text-brand-700 dark:text-brand-400 font-extrabold text-base bg-brand-50 dark:bg-[#010803] px-3 py-1.5 rounded-lg border border-brand-100 dark:border-[#021207] inline-block whitespace-nowrap">
                                                ₱<?= number_format($row['amount'], 2) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'public_footer.php'; ?>
</body>
</html>