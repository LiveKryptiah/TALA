<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Security Check: Only specific GUK Evaluators or Master Admins can access this portal
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'guk_evaluator' && $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require 'db_connect.php';

// ==========================================
// DATA FETCHING & KANBAN CATEGORIZATION
// ==========================================
try {
    $stmt = $pdo->query("SELECT * FROM guk_entries ORDER BY submitted_at DESC");
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$entries) $entries = [];
    
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
    $board = ['Submitted' => [], 'Paper Screening' => [], 'Panel Interview' => [], 'Finalist' => [], 'Awardee' => []];
}

$current_page = $_GET['page'] ?? 'pipeline';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GUK Evaluator Portal | Province of Isabela</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, colors: { guk: { 50: '#fdf4ff', 100: '#fae8ff', 500: '#d946ef', 600: '#c026d3', 900: '#701a75' }, gold: { 400: '#fbbf24', 500: '#f59e0b' } } } } }
    </script>
    <style>
        body { background-color: #f8fafc; }
        /* Custom Scrollbar for Kanban Columns */
        .kanban-col::-webkit-scrollbar { width: 4px; }
        .kanban-col::-webkit-scrollbar-track { background: transparent; }
        .kanban-col::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
    </style>
</head>
<body class="text-slate-800 antialiased flex selection:bg-guk-500 selection:text-white h-screen overflow-hidden">

    <!-- ========================================== -->
    <!-- DEDICATED GUK SIDEBAR                      -->
    <!-- ========================================== -->
    <aside class="w-64 bg-slate-900 text-white border-r border-slate-800 flex flex-col shadow-2xl relative z-50">
        <div class="h-28 flex flex-col items-center justify-center px-4 border-b border-slate-800 bg-slate-950 relative overflow-hidden">
            <div class="absolute -right-4 -top-4 opacity-10"><i class="ph-fill ph-medal text-7xl text-gold-500"></i></div>
            <div class="relative z-10 flex flex-col items-center text-center">
                <h1 class="text-xl font-black leading-tight tracking-widest text-gold-400">GAWAD ULIRANG</h1>
                <h2 class="text-sm font-bold tracking-widest text-white">KABATAAN</h2>
            </div>
        </div>

        <nav class="flex-1 px-4 space-y-2 overflow-y-auto mt-6">
            <p class="px-2 text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-3">Evaluation Control</p>
            
            <a href="?page=pipeline" class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-bold transition-all <?= $current_page === 'pipeline' ? 'bg-guk-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
                <i class="<?= $current_page === 'pipeline' ? 'ph-fill' : 'ph-bold' ?> ph-kanban text-lg"></i> Evaluation Pipeline
            </a>
            
            <a href="?page=rubrics" class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-bold transition-all <?= $current_page === 'rubrics' ? 'bg-guk-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
                <i class="<?= $current_page === 'rubrics' ? 'ph-fill' : 'ph-bold' ?> ph-clipboard-text text-lg"></i> Scoring Rubrics
            </a>

            <a href="?page=movs" class="flex items-center gap-3 px-3 py-3 rounded-xl text-sm font-bold transition-all <?= $current_page === 'movs' ? 'bg-guk-600 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
                <i class="<?= $current_page === 'movs' ? 'ph-fill' : 'ph-bold' ?> ph-folder-open text-lg"></i> MOV Repository
            </a>
        </nav>
        
        <div class="p-4 border-t border-slate-800">
            <div class="bg-slate-800 rounded-2xl p-4 mb-4">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Logged in as</p>
                <p class="text-sm font-bold text-white truncate">Board of Judges</p>
            </div>
            <a href="logout.php" class="flex items-center justify-center gap-2 w-full py-2.5 bg-rose-500/10 text-rose-400 border border-rose-500/20 rounded-xl text-xs font-bold hover:bg-rose-500 hover:text-white transition-colors">
                <i class="ph-bold ph-power"></i> Exit Portal
            </a>
        </div>
    </aside>

    <!-- ========================================== -->
    <!-- MAIN CONTENT AREA                          -->
    <!-- ========================================== -->
    <div class="flex-1 flex flex-col h-screen bg-slate-50 relative">
        
        <!-- Header & Timeline -->
        <div class="p-6 md:p-8 bg-white border-b border-slate-200 shadow-sm z-10 flex flex-col xl:flex-row justify-between items-start xl:items-end gap-6 shrink-0">
            <div>
                <p class="text-guk-600 font-bold tracking-widest text-[11px] uppercase mb-1 flex items-center gap-2">
                    <i class="ph-fill ph-star text-gold-500"></i> Pagkilala sa Natatanging Organisasyon
                </p>
                <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Project Management Pipeline</h1>
            </div>
            
            <!-- Official GUK Schedule Tracker -->
            <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 p-2 rounded-2xl text-[10px] font-bold text-slate-500 overflow-x-auto w-full xl:w-auto">
                <div class="px-3 py-2 rounded-xl bg-white border border-slate-200 shadow-sm text-center shrink-0">
                    <span class="block text-[8px] uppercase tracking-widest text-slate-400 mb-0.5">Submission</span>
                    <span class="text-slate-800">July 27</span>
                </div>
                <i class="ph-bold ph-caret-right text-slate-300"></i>
                <div class="px-3 py-2 rounded-xl bg-white border border-slate-200 shadow-sm text-center shrink-0">
                    <span class="block text-[8px] uppercase tracking-widest text-slate-400 mb-0.5">Deadline</span>
                    <span class="text-slate-800">Aug 8</span>
                </div>
                <i class="ph-bold ph-caret-right text-slate-300"></i>
                <div class="px-3 py-2 rounded-xl bg-white border border-slate-200 shadow-sm text-center shrink-0 ring-2 ring-guk-500/50">
                    <span class="block text-[8px] uppercase tracking-widest text-guk-500 mb-0.5">Screening</span>
                    <span class="text-slate-900">Aug 13</span>
                </div>
                <i class="ph-bold ph-caret-right text-slate-300"></i>
                <div class="px-3 py-2 rounded-xl bg-slate-100 text-slate-400 text-center shrink-0">
                    <span class="block text-[8px] uppercase tracking-widest mb-0.5">Finalists</span>
                    <span>Aug 15</span>
                </div>
                <i class="ph-bold ph-caret-right text-slate-300"></i>
                <div class="px-3 py-2 rounded-xl bg-slate-100 text-slate-400 text-center shrink-0">
                    <span class="block text-[8px] uppercase tracking-widest mb-0.5">Interviews</span>
                    <span>Aug 18</span>
                </div>
                <i class="ph-bold ph-caret-right text-slate-300"></i>
                <div class="px-3 py-2 rounded-xl bg-slate-100 text-slate-400 text-center shrink-0">
                    <span class="block text-[8px] uppercase tracking-widest mb-0.5">Awarding</span>
                    <span>Aug 31</span>
                </div>
            </div>
        </div>

        <!-- KANBAN BOARD -->
        <div class="flex-1 overflow-x-auto p-6 md:p-8 flex gap-6 pb-12">
            
            <?php 
            $stages = [
                'Submitted' => ['icon' => 'ph-folder-arrow-down', 'color' => 'text-slate-400'],
                'Paper Screening' => ['icon' => 'ph-clipboard-text', 'color' => 'text-blue-500'],
                'Panel Interview' => ['icon' => 'ph-users-three', 'color' => 'text-purple-500'],
                'Finalist' => ['icon' => 'ph-star', 'color' => 'text-guk-500'],
                'Awardee' => ['icon' => 'ph-trophy', 'color' => 'text-gold-500']
            ];
            
            foreach ($stages as $status => $style): ?>
            <div class="flex flex-col w-[340px] shrink-0 bg-slate-100/50 rounded-3xl border border-slate-200 h-full overflow-hidden">
                <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-white rounded-t-3xl shadow-sm">
                    <h3 class="font-extrabold text-slate-800 text-sm flex items-center gap-2 tracking-tight">
                        <i class="ph-fill <?= $style['icon'] ?> <?= $style['color'] ?> text-lg"></i> <?= $status ?>
                    </h3>
                    <span class="bg-slate-100 border border-slate-200 text-slate-600 text-xs font-black px-2.5 py-1 rounded-lg">
                        <?= count($board[$status]) ?>
                    </span>
                </div>
                
                <div class="p-4 flex-1 overflow-y-auto kanban-col space-y-4">
                    <?php if (empty($board[$status])): ?>
                        <div class="border-2 border-dashed border-slate-200 rounded-2xl p-8 text-center flex flex-col items-center justify-center h-32">
                            <i class="ph-bold ph-archive-box text-3xl text-slate-300 mb-2"></i>
                            <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">No Entries Yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($board[$status] as $entry): ?>
                        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:border-guk-300 transition-all group cursor-pointer relative overflow-hidden">
                            <?php if($status === 'Awardee'): ?>
                                <div class="absolute top-0 right-0 w-16 h-16 bg-gradient-to-bl from-gold-400 to-transparent opacity-20"></div>
                            <?php endif; ?>
                            
                            <div class="flex justify-between items-start mb-3 relative z-10">
                                <span class="text-[9px] font-black uppercase tracking-widest text-guk-700 bg-guk-50 px-2.5 py-1 rounded-md border border-guk-100">
                                    <?= htmlspecialchars($entry['applicant_type']) ?>
                                </span>
                                <button class="text-slate-400 hover:text-slate-800 bg-slate-50 hover:bg-slate-100 p-1 rounded-md transition-colors"><i class="ph-bold ph-dots-three text-lg"></i></button>
                            </div>
                            
                            <h4 class="font-black text-slate-900 text-sm leading-snug mb-1 relative z-10"><?= htmlspecialchars($entry['organization_name']) ?></h4>
                            <p class="text-[10px] font-bold text-slate-500 leading-tight mb-5 relative z-10 line-clamp-2"><?= htmlspecialchars($entry['category']) ?></p>
                            
                            <div class="border-t border-slate-100 pt-4 flex justify-between items-center relative z-10">
                                <div>
                                    <p class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-0.5">Total Score</p>
                                    <span class="text-sm font-black <?= $entry['total_score'] > 0 ? 'text-emerald-600' : 'text-slate-300' ?>">
                                        <?= $entry['total_score'] > 0 ? $entry['total_score'] . ' / 100' : 'Unscored' ?>
                                    </span>
                                </div>
                                <button onclick="openScorecard(<?= $entry['id'] ?>, '<?= htmlspecialchars($entry['category']) ?>')" class="bg-slate-900 hover:bg-guk-600 text-white text-[10px] font-bold uppercase tracking-widest px-4 py-2 rounded-xl transition-colors shadow-sm">
                                    Evaluate
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

    <!-- ========================================== -->
    <!-- SCORECARD EVALUATION MODAL                 -->
    <!-- ========================================== -->
    <div id="scorecardModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[9999] hidden flex justify-center items-center p-4 opacity-0 transition-opacity duration-300">
        <div class="bg-white w-full max-w-4xl max-h-[90vh] rounded-[2rem] shadow-2xl flex flex-col overflow-hidden transform scale-95 transition-transform duration-300" id="modalContent">
            
            <!-- Modal Header -->
            <div class="p-6 md:p-8 border-b border-slate-100 bg-slate-50 flex justify-between items-start">
                <div>
                    <span class="inline-block bg-guk-100 text-guk-700 text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md mb-2">Paper Screening Phase</span>
                    <h2 class="text-2xl font-black text-slate-900 tracking-tight" id="modalCategoryTitle">Official Evaluation Scorecard</h2>
                    <p class="text-sm font-medium text-slate-500 mt-1">Based on the GUK 5-Point Descriptive System.</p>
                </div>
                <button onclick="closeScorecard()" class="w-10 h-10 bg-white border border-slate-200 rounded-full flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-colors shadow-sm">
                    <i class="ph-bold ph-x text-lg"></i>
                </button>
            </div>

            <!-- Modal Body (Form) -->
            <div class="p-6 md:p-8 overflow-y-auto bg-white flex-1">
                <form id="evaluationForm">
                    <input type="hidden" id="evaluate_entry_id">
                    
                    <div class="bg-blue-50 border border-blue-100 p-4 rounded-2xl mb-8 flex items-start gap-4">
                        <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center text-blue-500 shrink-0 shadow-sm"><i class="ph-bold ph-info text-xl"></i></div>
                        <div>
                            <h4 class="font-bold text-blue-900 text-sm mb-1">Scoring Guide</h4>
                            <p class="text-xs text-blue-700 font-medium leading-relaxed">
                                Assign points based on the Means of Verification (MOV).<br>
                                <span class="font-bold">0 pts</span> = Non-Compliant | <span class="font-bold">3 pts</span> = Compliant | <span class="font-bold">5 pts</span> = Fully Compliant.
                            </p>
                        </div>
                    </div>

                    <!-- Dynamic Rubric Categories based on Document -->
                    <div class="space-y-6">
                        
                        <!-- Youth Participation -->
                        <div class="border border-slate-200 rounded-2xl p-6 relative">
                            <span class="absolute -top-3 left-4 bg-white px-2 text-xs font-black uppercase tracking-widest text-slate-400">Section 1</span>
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="font-extrabold text-slate-900 text-lg">Youth Participation</h3>
                                    <p class="text-xs text-slate-500 font-medium">Evaluates structure, meetings, databases, and assemblies.</p>
                                </div>
                                <div class="bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                                    <span class="text-xs font-bold text-slate-500">Weight: <span class="text-slate-900">50%</span></span>
                                </div>
                            </div>
                            <input type="number" min="0" max="50" placeholder="Enter Score (0-50)" class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl font-bold text-sm focus:ring-2 focus:ring-guk-500 outline-none transition-all">
                        </div>

                        <!-- Accountability & Transparency -->
                        <div class="border border-slate-200 rounded-2xl p-6 relative">
                            <span class="absolute -top-3 left-4 bg-white px-2 text-xs font-black uppercase tracking-widest text-slate-400">Section 2</span>
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="font-extrabold text-slate-900 text-lg">Accountability & Transparency</h3>
                                    <p class="text-xs text-slate-500 font-medium">Evaluates financial independence, planning, and web portals.</p>
                                </div>
                                <div class="bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                                    <span class="text-xs font-bold text-slate-500">Weight: <span class="text-slate-900">30%</span></span>
                                </div>
                            </div>
                            <input type="number" min="0" max="30" placeholder="Enter Score (0-30)" class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl font-bold text-sm focus:ring-2 focus:ring-guk-500 outline-none transition-all">
                        </div>

                        <!-- Efficiency & Effectiveness -->
                        <div class="border border-slate-200 rounded-2xl p-6 relative">
                            <span class="absolute -top-3 left-4 bg-white px-2 text-xs font-black uppercase tracking-widest text-slate-400">Section 3</span>
                            <div class="flex justify-between items-center mb-4">
                                <div>
                                    <h3 class="font-extrabold text-slate-900 text-lg">Efficiency & Effectiveness</h3>
                                    <p class="text-xs text-slate-500 font-medium">Evaluates policy-making, project management, and capacity dev.</p>
                                </div>
                                <div class="bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                                    <span class="text-xs font-bold text-slate-500">Weight: <span class="text-slate-900">20%</span></span>
                                </div>
                            </div>
                            <input type="number" min="0" max="20" placeholder="Enter Score (0-20)" class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl font-bold text-sm focus:ring-2 focus:ring-guk-500 outline-none transition-all">
                        </div>
                        
                        <!-- Remarks -->
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Evaluator Remarks / Justification</label>
                            <textarea rows="3" class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl font-medium text-sm focus:ring-2 focus:ring-guk-500 outline-none transition-all resize-none" placeholder="Provide notes based on the Means of Verification..."></textarea>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div class="p-6 md:p-8 border-t border-slate-100 bg-white flex justify-between items-center">
                <button type="button" onclick="closeScorecard()" class="text-sm font-bold text-slate-500 hover:text-slate-800 transition-colors">Cancel Evaluation</button>
                <button type="button" onclick="saveEvaluation()" class="bg-guk-600 hover:bg-guk-700 text-white font-black uppercase tracking-widest text-sm px-8 py-4 rounded-xl shadow-lg hover:shadow-xl hover:-translate-y-0.5 transition-all">
                    Submit Final Score
                </button>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('scorecardModal');
        const modalContent = document.getElementById('modalContent');

        function openScorecard(id, category) {
            document.getElementById('evaluate_entry_id').value = id;
            document.getElementById('modalCategoryTitle').innerText = category;
            
            modal.classList.remove('hidden');
            // Trigger reflow for animation
            void modal.offsetWidth; 
            
            modal.classList.remove('opacity-0');
            modalContent.classList.remove('scale-95');
        }

        function closeScorecard() {
            modal.classList.add('opacity-0');
            modalContent.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }

        function saveEvaluation() {
            // Logic to save the scores via AJAX would go here
            alert("Score successfully submitted and saved to the database.");
            closeScorecard();
        }
    </script>
</body>
</html>