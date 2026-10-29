<?php
// sk2_dashboard.php - SK OS 2.0 Main Dashboard
session_start();
require_once 'db_connect.php';

// Security Check - Allow admin, sub-admin, sk, or sk_admin roles
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'sub-admin', 'sk', 'sk_admin'])) {
    header("Location: login.php");
    exit();
}

$barangay = $_SESSION['barangay_assigned'] ?? 'Municipal Level';
$user_name = $_SESSION['full_name'] ?? 'SK Chairperson';

// Initialize default stats
$stats = [
    'active_projects' => 0,
    'total_documents' => 0,
    'total_budget' => 0,
    'active_evacuations' => 0
];
$recent_tasks = [];

// Fetch Live Data (Safely wrapped in try-catch)
try {
    // 1. Get Active Projects
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sk_projects WHERE barangay = ? AND status != 'done'");
    $stmt->execute([$barangay]);
    $stats['active_projects'] = $stmt->fetchColumn();

    // 2. Get Recent Projects for the Widget
    $stmt = $pdo->prepare("SELECT title, status, priority, due_date FROM sk_projects WHERE barangay = ? ORDER BY created_at DESC LIMIT 4");
    $stmt->execute([$barangay]);
    $recent_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get Total Documents
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sk2_official_docs WHERE barangay = ?");
    $stmt->execute([$barangay]);
    $stats['total_documents'] = $stmt->fetchColumn();

    // 4. Get Total Allocated Budget
    $stmt = $pdo->prepare("SELECT SUM(budget_allocation) FROM sk2_dev_plans WHERE barangay = ?");
    $stmt->execute([$barangay]);
    $stats['total_budget'] = $stmt->fetchColumn() ?: 0;

    // 5. Get Active Evacuation Centers
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sk2_safety_evacuations WHERE barangay = ? AND status = 'Active'");
    $stmt->execute([$barangay]);
    $stats['active_evacuations'] = $stmt->fetchColumn();

} catch (PDOException $e) {
    // Silently ignore missing tables for new setups
}

// Helper function for priority colors
function getPriorityBadge($priority) {
    switch(strtolower($priority)) {
        case 'high': return 'bg-red-50 text-red-600 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/30';
        case 'medium': return 'bg-amber-50 text-amber-600 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30';
        case 'low': return 'bg-blue-50 text-blue-600 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/30';
        default: return 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700';
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | Command Dashboard</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <?php include 'sk2_theme_init.php'; ?>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { brand: { 50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d' } }
                }
            }
        }
    </script>
    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        .hover-lift { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .hover-lift:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex h-screen overflow-hidden selection:bg-blue-500 selection:text-white transition-colors duration-300">

    <!-- Sidebar Include -->
    <?php include 'sk2_sidebar.php'; ?>

    <!-- PERFECT LAYOUT FIX: Matches sk2_automation.php exactly -->
    <main class="ml-64 flex-1 h-screen overflow-y-auto custom-scrollbar relative bg-slate-50 dark:bg-zinc-950 transition-colors duration-300">
        
        <div class="relative z-10 p-8 lg:p-10 max-w-[1600px] mx-auto">
            
            <!-- Top Header matches Automation Hub layout -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1">
                        Command Dashboard
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium">Sangguniang Kabataan overview for <?= htmlspecialchars($barangay) ?>.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <button id="themeToggle" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors shadow-sm">
                        <i id="themeIcon" class="ph-bold ph-moon text-lg"></i>
                    </button>
                    <!-- User Profile Badge -->
                    <div class="hidden md:flex items-center gap-3 border-l border-slate-200 dark:border-zinc-800 pl-4 ml-1">
                        <div class="text-right">
                            <p class="text-sm font-bold text-slate-900 dark:text-white leading-tight"><?= htmlspecialchars($user_name) ?></p>
                            <p class="text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest">Chairperson</p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 flex items-center justify-center font-bold border border-blue-200 dark:border-blue-500/30 shadow-sm">
                            <?= strtoupper(substr($user_name, 0, 1)) ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Welcome Banner -->
            <div class="bg-blue-600 rounded-[2rem] p-8 md:p-10 text-white shadow-lg mb-8 relative overflow-hidden">
                <i class="ph-fill ph-circles-four absolute -right-10 -top-10 text-9xl opacity-10"></i>
                <div class="relative z-10">
                    <h2 class="text-3xl font-black tracking-tight mb-2">Welcome back, <?= htmlspecialchars(explode(' ', trim($user_name))[0]) ?>!</h2>
                    <p class="text-blue-100 font-medium max-w-xl">Here is what is happening in Barangay <?= htmlspecialchars($barangay) ?> today. Keep up the great work managing your youth programs and development plans.</p>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Stat 1 -->
                <div class="bg-white dark:bg-zinc-900 rounded-3xl p-6 border border-slate-200 dark:border-zinc-800 shadow-sm dark:shadow-none hover-lift flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 flex items-center justify-center text-2xl border border-blue-100 dark:border-blue-500/20">
                            <i class="ph-fill ph-kanban"></i>
                        </div>
                    </div>
                    <p class="text-[10px] font-black text-slate-400 dark:text-zinc-500 uppercase tracking-widest mb-1">Active Projects</p>
                    <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white"><?= $stats['active_projects'] ?></h3>
                </div>

                <!-- Stat 2 -->
                <div class="bg-white dark:bg-zinc-900 rounded-3xl p-6 border border-slate-200 dark:border-zinc-800 shadow-sm dark:shadow-none hover-lift flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400 flex items-center justify-center text-2xl border border-purple-100 dark:border-purple-500/20">
                            <i class="ph-fill ph-folders"></i>
                        </div>
                    </div>
                    <p class="text-[10px] font-black text-slate-400 dark:text-zinc-500 uppercase tracking-widest mb-1">Official Documents</p>
                    <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white"><?= $stats['total_documents'] ?></h3>
                </div>

                <!-- Stat 3 -->
                <div class="bg-white dark:bg-zinc-900 rounded-3xl p-6 border border-slate-200 dark:border-zinc-800 shadow-sm dark:shadow-none hover-lift flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 flex items-center justify-center text-2xl border border-emerald-100 dark:border-emerald-500/20">
                            <i class="ph-fill ph-wallet"></i>
                        </div>
                    </div>
                    <p class="text-[10px] font-black text-slate-400 dark:text-zinc-500 uppercase tracking-widest mb-1">Mapped Budget</p>
                    <h3 class="text-2xl font-extrabold text-slate-900 dark:text-white truncate">₱<?= number_format($stats['total_budget'], 2) ?></h3>
                </div>

                <!-- Stat 4 -->
                <div class="bg-white dark:bg-zinc-900 rounded-3xl p-6 border border-slate-200 dark:border-zinc-800 shadow-sm dark:shadow-none hover-lift flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400 flex items-center justify-center text-2xl border border-red-100 dark:border-red-500/20">
                            <i class="ph-fill ph-house-line"></i>
                        </div>
                    </div>
                    <p class="text-[10px] font-black text-slate-400 dark:text-zinc-500 uppercase tracking-widest mb-1">Active Evacuations</p>
                    <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white"><?= $stats['active_evacuations'] ?></h3>
                </div>
            </div>

            <!-- Bottom Layout: Recent Tasks & Quick Links -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Recent Projects List -->
                <div class="lg:col-span-2 bg-white dark:bg-zinc-900 rounded-[2rem] border border-slate-200 dark:border-zinc-800 shadow-sm dark:shadow-none p-6 md:p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                            <i class="ph-bold ph-clock-counter-clockwise text-blue-500"></i> Recent Projects
                        </h3>
                        <a href="sk2_projects.php" class="text-sm font-bold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 flex items-center gap-1 transition-colors">
                            View Board <i class="ph-bold ph-arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="flex flex-col gap-3">
                        <?php if (empty($recent_tasks)): ?>
                            <div class="text-center py-10 border-2 border-dashed border-slate-200 dark:border-zinc-800 rounded-2xl">
                                <i class="ph-fill ph-kanban text-4xl text-slate-300 dark:text-zinc-700 mb-2"></i>
                                <p class="text-slate-500 dark:text-zinc-500 font-bold text-sm">No projects added yet.</p>
                            </div>
                        <?php else: foreach ($recent_tasks as $task): ?>
                            <div class="flex items-center justify-between p-4 rounded-2xl border border-slate-100 dark:border-zinc-800/50 hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <div class="flex items-center gap-4">
                                    <div class="w-2 h-2 rounded-full <?= $task['status'] == 'done' ? 'bg-emerald-500' : ($task['status'] == 'doing' ? 'bg-amber-500' : 'bg-slate-300 dark:bg-zinc-600') ?>"></div>
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white leading-none mb-1 <?= $task['status'] == 'done' ? 'line-through text-slate-400 dark:text-zinc-600' : '' ?>"><?= htmlspecialchars($task['title']) ?></p>
                                        <p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest"><?= strtoupper($task['status']) ?></p>
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-md border text-[9px] font-black uppercase tracking-widest <?= getPriorityBadge($task['priority']) ?>">
                                    <?= htmlspecialchars($task['priority']) ?>
                                </span>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- Quick Actions Menu -->
                <div class="bg-white dark:bg-zinc-900 rounded-[2rem] border border-slate-200 dark:border-zinc-800 shadow-sm dark:shadow-none p-6 md:p-8">
                    <h3 class="text-lg font-extrabold text-slate-900 dark:text-white mb-6 flex items-center gap-2">
                        <i class="ph-bold ph-lightning text-amber-500"></i> Quick Actions
                    </h3>
                    
                    <div class="flex flex-col gap-3">
                        <a href="sk2_documents.php?tab=builder" class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-zinc-950 hover:bg-blue-50 dark:hover:bg-blue-500/10 border border-slate-100 dark:border-zinc-800 hover:border-blue-200 dark:hover:border-blue-500/30 transition-colors group">
                            <div class="w-10 h-10 rounded-xl bg-white dark:bg-zinc-900 text-slate-500 dark:text-zinc-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 flex items-center justify-center text-xl shadow-sm dark:shadow-none border border-slate-100 dark:border-zinc-800 transition-colors">
                                <i class="ph-bold ph-strategy"></i>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900 dark:text-white text-sm">Draft Action Plan</p>
                                <p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest">CBYDP / ABYIP</p>
                            </div>
                        </a>
                        
                        <a href="sk2_documents.php?tab=repository" class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-zinc-950 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 border border-slate-100 dark:border-zinc-800 hover:border-emerald-200 dark:hover:border-emerald-500/30 transition-colors group">
                            <div class="w-10 h-10 rounded-xl bg-white dark:bg-zinc-900 text-slate-500 dark:text-zinc-400 group-hover:text-emerald-600 dark:group-hover:text-emerald-400 flex items-center justify-center text-xl shadow-sm dark:shadow-none border border-slate-100 dark:border-zinc-800 transition-colors">
                                <i class="ph-bold ph-upload-simple"></i>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900 dark:text-white text-sm">Upload Document</p>
                                <p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest">Resolutions & MOMs</p>
                            </div>
                        </a>
                        
                        <a href="sk2_safety.php?tab=disaster" class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-zinc-950 hover:bg-red-50 dark:hover:bg-red-500/10 border border-slate-100 dark:border-zinc-800 hover:border-red-200 dark:hover:border-red-500/30 transition-colors group">
                            <div class="w-10 h-10 rounded-xl bg-white dark:bg-zinc-900 text-slate-500 dark:text-zinc-400 group-hover:text-red-600 dark:group-hover:text-red-400 flex items-center justify-center text-xl shadow-sm dark:shadow-none border border-slate-100 dark:border-zinc-800 transition-colors">
                                <i class="ph-bold ph-siren"></i>
                            </div>
                            <div>
                                <p class="font-bold text-slate-900 dark:text-white text-sm">Update Emergencies</p>
                                <p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest">Safety & Crisis</p>
                            </div>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Theme Script -->
    <script>
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        
        if (document.documentElement.classList.contains('dark')) { 
            themeIcon.classList.replace('ph-moon', 'ph-sun'); 
        }

        themeToggleBtn.addEventListener('click', function() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                themeIcon.classList.replace('ph-sun', 'ph-moon');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                themeIcon.classList.replace('ph-moon', 'ph-sun');
            }
        });
    </script>
</body>
</html>