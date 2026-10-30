<?php
session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// Mock data fetch for Kanban Board
try {
    $stmt = $pdo->query("SELECT * FROM guk_entries ORDER BY submitted_at DESC");
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$entries) $entries = [];
    
    // Categorize for Kanban
    $board = [
        'Submitted' => [],
        'Paper Screening' => [],
        'Panel Interview' => [],
        'Finalist' => [],
        'Awardee' => []
    ];
    
    foreach ($entries as $entry) {
        $board[$entry['status']][] = $entry;
    }
} catch (Exception $e) {
    // Failsafe for missing tables during setup
    $board = ['Submitted' => [], 'Paper Screening' => [], 'Panel Interview' => [], 'Finalist' => [], 'Awardee' => []];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <title>GUK Management | TALA OS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { guk: { 50: '#fdf4ff', 500: '#d946ef', 600: '#c026d3', 900: '#701a75' } } } } }
    </script>
    <style>body { background-color: #f8fafc; }</style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="text-slate-800 antialiased flex selection:bg-guk-500 selection:text-white h-screen overflow-hidden">

    <!-- STANDARD TALA OS ADMIN SIDEBAR -->
    <?php include 'sidebar_admin.php'; ?>

    <!-- MAIN CONTENT AREA -->
    <div class="ml-64 w-full flex flex-col h-screen">
        
        <!-- Header Section -->
        <div class="p-8 border-b border-slate-200 bg-white shadow-sm z-10 flex justify-between items-end shrink-0">
            <div>
                <p class="text-guk-600 font-bold tracking-widest text-[11px] uppercase mb-1 flex items-center gap-1.5">
                    <i class="ph-bold ph-medal"></i> 6th Gawad Ulirang Kabataan
                </p>
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Project Management Pipeline</h1>
                <p class="text-sm font-medium text-slate-500 mt-1">Official search pipeline and portfolio screening.</p>
            </div>
            
            <!-- Milestone Tracker -->
            <div class="hidden xl:flex items-center gap-2 bg-slate-50 border border-slate-200 p-2 rounded-xl text-xs font-bold text-slate-500">
                <div class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 shadow-sm text-center">
                    <span class="block text-[9px] uppercase tracking-widest text-slate-400">Deadline</span>
                    <span class="text-slate-800">Aug 8</span>
                </div>
                <i class="ph-bold ph-caret-right"></i>
                <div class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 shadow-sm text-center">
                    <span class="block text-[9px] uppercase tracking-widest text-slate-400">Screening</span>
                    <span class="text-slate-800">Aug 13</span>
                </div>
                <i class="ph-bold ph-caret-right"></i>
                <div class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 shadow-sm text-center">
                    <span class="block text-[9px] uppercase tracking-widest text-slate-400">Finalists</span>
                    <span class="text-guk-600">Aug 15</span>
                </div>
                <i class="ph-bold ph-caret-right"></i>
                <div class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 shadow-sm text-center">
                    <span class="block text-[9px] uppercase tracking-widest text-slate-400">Interviews</span>
                    <span class="text-slate-800">Aug 18</span>
                </div>
                <i class="ph-bold ph-caret-right"></i>
                <div class="px-3 py-1.5 rounded-lg bg-slate-900 text-white shadow-sm text-center">
                    <span class="block text-[9px] uppercase tracking-widest text-slate-400">Awarding</span>
                    <span>Aug 31</span>
                </div>
            </div>
        </div>

        <!-- KANBAN BOARD -->
        <div class="flex-1 overflow-x-auto overflow-y-hidden p-8 flex gap-6">
            
            <?php 
            $stages = [
                'Submitted' => 'ph-folder-open',
                'Paper Screening' => 'ph-magnifying-glass',
                'Panel Interview' => 'ph-users-three',
                'Finalist' => 'ph-star',
                'Awardee' => 'ph-trophy'
            ];
            
            foreach ($stages as $status => $icon): ?>
            <div class="flex flex-col w-80 shrink-0 bg-slate-100 rounded-3xl border border-slate-200/60 max-h-full overflow-hidden">
                <div class="p-4 border-b border-slate-200/60 flex justify-between items-center bg-slate-50 rounded-t-3xl">
                    <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                        <i class="ph-fill <?= $icon ?> <?= $status === 'Awardee' ? 'text-amber-500' : 'text-slate-400' ?>"></i> <?= $status ?>
                    </h3>
                    <span class="bg-white border border-slate-200 text-slate-600 text-[10px] font-black px-2 py-1 rounded-md shadow-sm">
                        <?= count($board[$status]) ?>
                    </span>
                </div>
                
                <div class="p-4 flex-1 overflow-y-auto space-y-4">
                    <?php if (empty($board[$status])): ?>
                        <div class="border-2 border-dashed border-slate-300 rounded-xl p-6 text-center text-slate-400 text-xs font-bold">
                            Drop entries here
                        </div>
                    <?php else: ?>
                        <?php foreach ($board[$status] as $entry): ?>
                        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm cursor-grab hover:border-guk-400 hover:shadow-md transition-all group">
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-[9px] font-black uppercase tracking-widest text-guk-600 bg-guk-50 px-2 py-0.5 rounded border border-guk-100">
                                    <?= htmlspecialchars($entry['applicant_type']) ?>
                                </span>
                                <button class="text-slate-400 hover:text-slate-800"><i class="ph-bold ph-dots-three text-lg"></i></button>
                            </div>
                            <h4 class="font-bold text-slate-900 text-sm leading-tight mb-1"><?= htmlspecialchars($entry['organization_name']) ?></h4>
                            <p class="text-[10px] font-semibold text-slate-500 leading-tight mb-4"><?= htmlspecialchars($entry['category']) ?></p>
                            
                            <div class="border-t border-slate-100 pt-3 flex justify-between items-center">
                                <span class="text-xs font-bold <?= $entry['total_score'] > 0 ? 'text-emerald-600' : 'text-slate-400' ?>">
                                    <i class="ph-bold ph-check-square-offset"></i> Score: <?= $entry['total_score'] > 0 ? $entry['total_score'] : 'N/A' ?>
                                </span>
                                <button class="text-[10px] font-bold uppercase tracking-widest text-slate-500 hover:text-guk-600 transition-colors">
                                    View MOVs
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
        </div>
    </div>
</body>
</html>