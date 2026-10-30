<?php
// sk2_civic.php - SK OS 2.0 Civic Tech & Governance Hub
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] !== 'sub-admin' && $_SESSION['role'] !== 'sk_admin')) {
    header("Location: sk2_login.php"); exit();
}

$barangay = $_SESSION['admin_barangay'] ?? 'Municipal Level';
$message = ''; $msgType = '';
$activeTab = $_GET['tab'] ?? 'gamification';

// Auto-create Tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_civic_points` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `youth_id` INT NOT NULL,
        `youth_name` VARCHAR(255) NOT NULL,
        `barangay` VARCHAR(100) NOT NULL,
        `points` INT NOT NULL DEFAULT 0,
        `badge_name` VARCHAR(100),
        `reason` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_map_locations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `type` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_polls` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `question` VARCHAR(255) NOT NULL,
        `status` ENUM('Active', 'Closed') DEFAULT 'Active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_poll_options` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `poll_id` INT NOT NULL,
        `option_text` VARCHAR(255) NOT NULL,
        `votes_count` INT DEFAULT 0,
        FOREIGN KEY (`poll_id`) REFERENCES `sk2_polls`(`id`) ON DELETE CASCADE
    )");
} catch (PDOException $e) {}

// HANDLE SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_points'])) {
        $youth_id = explode('|', $_POST['youth_data'])[0];
        $youth_name = explode('|', $_POST['youth_data'])[1];
        
        $stmt = $pdo->prepare("INSERT INTO sk2_civic_points (youth_id, youth_name, barangay, points, badge_name, reason) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$youth_id, $youth_name, $barangay, $_POST['points'], $_POST['badge_name'], $_POST['reason']])) {
            header("Location: sk2_civic.php?tab=gamification&msg=points_added"); exit();
        }
    }
    
    if (isset($_POST['add_map_pin'])) {
        $stmt = $pdo->prepare("INSERT INTO sk2_map_locations (barangay, name, type, description) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$barangay, $_POST['name'], $_POST['type'], $_POST['description']])) {
            header("Location: sk2_civic.php?tab=mapping&msg=pin_added"); exit();
        }
    }

    if (isset($_POST['add_poll'])) {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO sk2_polls (barangay, question) VALUES (?, ?)");
        $stmt->execute([$barangay, $_POST['question']]);
        $poll_id = $pdo->lastInsertId();

        $stmtOpt = $pdo->prepare("INSERT INTO sk2_poll_options (poll_id, option_text) VALUES (?, ?)");
        if(!empty($_POST['opt1'])) $stmtOpt->execute([$poll_id, $_POST['opt1']]);
        if(!empty($_POST['opt2'])) $stmtOpt->execute([$poll_id, $_POST['opt2']]);
        if(!empty($_POST['opt3'])) $stmtOpt->execute([$poll_id, $_POST['opt3']]);
        $pdo->commit();
        
        header("Location: sk2_civic.php?tab=polling&msg=poll_added"); exit();
    }
}

// HANDLE ACTIONS (Delete/Close)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];
    if ($action == 'delete_pin') {
        $pdo->prepare("DELETE FROM sk2_map_locations WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_civic.php?tab=mapping&msg=deleted"); exit();
    } elseif ($action == 'close_poll') {
        $pdo->prepare("UPDATE sk2_polls SET status = 'Closed' WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_civic.php?tab=polling&msg=closed"); exit();
    }
}

// Set Messages
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m == 'points_added') { $message = 'Civic points and badges successfully awarded.'; $msgType = 'success'; }
    if ($m == 'pin_added') { $message = 'New location mapped successfully.'; $msgType = 'success'; }
    if ($m == 'poll_added') { $message = 'Interactive consultation launched.'; $msgType = 'success'; }
    if ($m == 'closed') { $message = 'Poll consultation closed.'; $msgType = 'warning'; }
    if ($m == 'deleted') { $message = 'Record purged.'; $msgType = 'danger'; }
}

// FETCH DATA
// 1. Leaderboard (Grouped Points)
$leaderboard = $pdo->prepare("SELECT youth_id, youth_name, SUM(points) as total_points, COUNT(badge_name) as badges FROM sk2_civic_points WHERE barangay = ? GROUP BY youth_id, youth_name ORDER BY total_points DESC LIMIT 10");
$leaderboard->execute([$barangay]); $leaderboard = $leaderboard->fetchAll(PDO::FETCH_ASSOC);

// 2. Map Locations
$locations = $pdo->prepare("SELECT * FROM sk2_map_locations WHERE barangay = ? ORDER BY type, name");
$locations->execute([$barangay]); $locations = $locations->fetchAll(PDO::FETCH_ASSOC);

// 3. Polls
$polls = $pdo->prepare("SELECT * FROM sk2_polls WHERE barangay = ? ORDER BY created_at DESC");
$polls->execute([$barangay]); $polls = $polls->fetchAll(PDO::FETCH_ASSOC);

// Helper: Fetch Youth for Dropdown
$youthRegistry = [];
try {
    $stmtY = $pdo->prepare("SELECT id, first_name, last_name, full_name FROM youth_registry WHERE barangay = ?");
    $stmtY->execute([$barangay]); $youthRegistry = $stmtY->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | Civic Tech Hub</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    
    <!-- ANTI-FLASH SCRIPT -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- TAILWIND DARK MODE CONFIGURATION -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }
                }
            }
        }
    </script>

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        .tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex h-screen overflow-hidden selection:bg-blue-500 selection:text-white transition-colors duration-300">

<!-- Sidebar Include -->
<?php include 'sk2_sidebar.php'; ?>

<!-- MAIN CONTAINER FIXED -->
<main class="ml-64 flex-1 h-screen overflow-y-auto custom-scrollbar relative z-10">
    <div class="p-8 lg:p-10 max-w-[1600px] mx-auto">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4 transition-colors">
            <div>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1 transition-colors">
                    Civic & Governance Hub
                </h2>
                <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium transition-colors">Gamification, Mapping, and Live Consultations.</p>
            </div>
            
            <div class="flex items-center gap-3">
                <button id="themeToggle" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors shadow-sm">
                    <i id="themeIcon" class="ph-bold ph-moon text-lg"></i>
                </button>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($message): ?>
            <?php 
                $borderColor = 'border-blue-200 dark:border-blue-500/30'; $bgColor = 'bg-blue-50 dark:bg-blue-500/10'; $textColor = 'text-blue-700 dark:text-blue-400'; $icon = 'ph-info';
                if($msgType=='danger'){ $borderColor = 'border-red-200 dark:border-red-500/30'; $bgColor = 'bg-red-50 dark:bg-red-500/10'; $textColor = 'text-red-700 dark:text-red-400'; $icon = 'ph-warning-circle'; }
                if($msgType=='warning'){ $borderColor = 'border-yellow-200 dark:border-yellow-500/30'; $bgColor = 'bg-yellow-50 dark:bg-yellow-500/10'; $textColor = 'text-yellow-700 dark:text-yellow-400'; $icon = 'ph-warning'; }
                if($msgType=='success'){ $borderColor = 'border-green-200 dark:border-green-500/30'; $bgColor = 'bg-green-50 dark:bg-green-500/10'; $textColor = 'text-green-700 dark:text-green-400'; $icon = 'ph-check-circle'; }
            ?>
            <div class="mb-8 p-4 rounded-xl border <?= $borderColor ?> <?= $bgColor ?> <?= $textColor ?> flex items-center gap-3 font-bold text-sm transition-colors shadow-sm">
                <i class="ph-fill <?= $icon ?> text-xl"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- TABS -->
        <div class="flex gap-2 border-b border-slate-200 dark:border-zinc-800 mb-8 overflow-x-auto custom-scrollbar pb-2 transition-colors">
            <button onclick="switchTab('gamification')" id="tabBtn-gamification" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'gamification' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                <i class="ph-bold ph-medal"></i> Gamification & Rewards
            </button>
            <button onclick="switchTab('mapping')" id="tabBtn-mapping" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'mapping' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                <i class="ph-bold ph-map-pin"></i> Barangay Youth Map
            </button>
            <button onclick="switchTab('polling')" id="tabBtn-polling" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'polling' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                <i class="ph-bold ph-chart-bar"></i> Digital Consultations
            </button>
        </div>

        <!-- TAB 1: GAMIFICATION -->
        <div id="tab-gamification" class="tab-content <?= $activeTab == 'gamification' ? 'active' : '' ?>">
            <div class="flex justify-between items-center mb-6 transition-colors">
                <h3 class="text-xl font-bold text-slate-800 dark:text-white">Civic Participation Leaderboard</h3>
                <button onclick="document.getElementById('modal-points').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                    <i class="ph-bold ph-plus"></i> Award Points
                </button>
            </div>
            <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 shadow-sm transition-colors">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800 transition-colors">
                                <th class="py-4 px-6 w-16">Rank</th>
                                <th class="py-4 px-6">Youth Citizen Name</th>
                                <th class="py-4 px-6 text-center">Badges Earned</th>
                                <th class="py-4 px-6 text-right">Total Points</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50 transition-colors">
                            <?php if(empty($leaderboard)): ?>
                                <tr><td colspan="4" class="py-10 text-center text-slate-500 dark:text-zinc-500 transition-colors">No points awarded yet.</td></tr>
                            <?php else: $rank = 1; ?>
                                <?php foreach($leaderboard as $l): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="py-4 px-6 text-slate-500 dark:text-zinc-500 font-black text-lg align-middle">#<?= $rank++ ?></td>
                                    <td class="py-4 px-6 font-bold text-slate-800 dark:text-zinc-200 align-middle"><?= htmlspecialchars($l['youth_name']) ?></td>
                                    <td class="py-4 px-6 text-center text-blue-600 dark:text-blue-400 font-bold align-middle"><?= $l['badges'] ?></td>
                                    <td class="py-4 px-6 text-right text-green-600 dark:text-green-400 font-black text-lg align-middle"><?= $l['total_points'] ?> pts</td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 2: MAPPING -->
        <div id="tab-mapping" class="tab-content <?= $activeTab == 'mapping' ? 'active' : '' ?>">
            <div class="flex justify-between items-center mb-6 transition-colors">
                <h3 class="text-xl font-bold text-slate-800 dark:text-white">Directory of Youth Spaces</h3>
                <button onclick="document.getElementById('modal-map').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                    <i class="ph-bold ph-plus"></i> Add Map Pin
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if(empty($locations)): ?>
                    <div class="col-span-full text-center py-10 bg-white dark:bg-zinc-900 border border-dashed border-slate-300 dark:border-zinc-800 rounded-2xl transition-colors">
                        <p class="text-slate-500 dark:text-zinc-500 font-bold">No locations mapped yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($locations as $loc): 
                        $icon = 'ph-map-pin';
                        if(strpos($loc['type'], 'Court') !== false) $icon = 'ph-basketball';
                        if(strpos($loc['type'], 'Study') !== false) $icon = 'ph-books';
                        if(strpos($loc['type'], 'Safe') !== false) $icon = 'ph-first-aid';
                    ?>
                    <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 relative group transition-colors shadow-sm flex flex-col items-center text-center">
                        <a href="?action=delete_pin&id=<?= $loc['id'] ?>&tab=mapping" onclick="return confirm('Remove location?')" class="absolute top-4 right-4 text-slate-400 hover:text-red-500 dark:text-zinc-500 dark:hover:text-red-400 transition-colors tooltip"><i class="ph-bold ph-trash text-lg"></i></a>
                        <div class="w-16 h-16 bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 rounded-full flex items-center justify-center text-3xl mb-4 transition-colors"><i class="ph-fill <?= $icon ?>"></i></div>
                        <span class="inline-block px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-widest rounded bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300 mb-2 transition-colors"><?= htmlspecialchars($loc['type']) ?></span>
                        <h4 class="text-lg font-bold text-slate-900 dark:text-white mb-2 leading-tight transition-colors"><?= htmlspecialchars($loc['name']) ?></h4>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 transition-colors"><?= htmlspecialchars($loc['description']) ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 3: POLLING -->
        <div id="tab-polling" class="tab-content <?= $activeTab == 'polling' ? 'active' : '' ?>">
            <div class="flex justify-between items-center mb-6 transition-colors">
                <h3 class="text-xl font-bold text-slate-800 dark:text-white">Active Consultations</h3>
                <button onclick="document.getElementById('modal-poll').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                    <i class="ph-bold ph-plus"></i> Launch Poll
                </button>
            </div>
            <div class="space-y-6">
                <?php if(empty($polls)): ?>
                    <div class="text-center py-10 bg-white dark:bg-zinc-900 border border-dashed border-slate-300 dark:border-zinc-800 rounded-2xl transition-colors">
                        <p class="text-slate-500 dark:text-zinc-500 font-bold">No active polls available.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($polls as $p): 
                        $opts = $pdo->query("SELECT * FROM sk2_poll_options WHERE poll_id = {$p['id']}")->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 transition-colors shadow-sm">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <span class="inline-block px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-widest rounded <?= $p['status'] == 'Active' ? 'bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400' : 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-400' ?> mb-2 transition-colors"><?= $p['status'] ?></span>
                                <h4 class="text-lg font-bold text-slate-900 dark:text-white leading-tight transition-colors"><?= htmlspecialchars($p['question']) ?></h4>
                            </div>
                            <?php if($p['status'] == 'Active'): ?>
                                <a href="?action=close_poll&id=<?= $p['id'] ?>&tab=polling" onclick="return confirm('Close this poll?')" class="text-[10px] font-bold text-yellow-600 bg-yellow-50 hover:bg-yellow-100 dark:text-yellow-400 dark:bg-yellow-500/10 dark:hover:bg-yellow-500/20 px-3 py-1.5 rounded-lg uppercase tracking-widest transition-colors">Close Poll</a>
                            <?php endif; ?>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <?php foreach($opts as $opt): ?>
                                <div class="bg-slate-50 dark:bg-zinc-800/50 p-4 rounded-xl border border-slate-100 dark:border-zinc-800/80 flex flex-col justify-between transition-colors">
                                    <p class="text-sm font-semibold text-slate-700 dark:text-zinc-300 mb-2 transition-colors"><?= htmlspecialchars($opt['option_text']) ?></p>
                                    <p class="text-2xl font-black text-blue-600 dark:text-blue-400 transition-colors"><?= $opt['votes_count'] ?> <span class="text-[10px] text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-widest">Votes</span></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</main>

<!-- MODAL: ADD POINTS -->
<div id="modal-points" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm transition-colors" onclick="document.getElementById('modal-points').classList.add('hidden')"></div>
    <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
        <div class="flex justify-between items-center mb-8">
            <h3 class="text-xl font-black text-slate-900 dark:text-white transition-colors">Award Civic Points</h3>
            <button onclick="document.getElementById('modal-points').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white transition-colors"><i class="ph-bold ph-x text-xl"></i></button>
        </div>
        <form method="POST" class="space-y-5">
            <input type="hidden" name="add_points" value="1">
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Select Youth Citizen *</label>
                <select name="youth_data" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none appearance-none cursor-pointer transition-colors">
                    <option value="" disabled selected>-- Select Citizen --</option>
                    <?php foreach($youthRegistry as $y): 
                        $name = !empty($y['full_name']) ? $y['full_name'] : $y['first_name'].' '.$y['last_name'];
                    ?>
                        <option value="<?= $y['id'].'|'.$name ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Points to Award *</label>
                <input type="number" name="points" value="10" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Special Badge (Optional)</label>
                <select name="badge_name" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none appearance-none cursor-pointer transition-colors">
                    <option value="">None</option>
                    <option value="Top Attendee">Top Attendee</option>
                    <option value="Eco-Warrior">Eco-Warrior (Cleanup)</option>
                    <option value="Sports MVP">Sports MVP</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Reason / Event Name *</label>
                <input type="text" name="reason" required placeholder="e.g. Attended KK Assembly" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 focus:border-blue-500 outline-none transition-colors">
            </div>
            <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 transition-colors">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-black py-4 rounded-xl transition-colors shadow-md">Grant Rewards</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADD MAP PIN -->
<div id="modal-map" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm transition-colors" onclick="document.getElementById('modal-map').classList.add('hidden')"></div>
    <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
        <div class="flex justify-between items-center mb-8">
            <h3 class="text-xl font-black text-slate-900 dark:text-white transition-colors">Pin New Location</h3>
            <button onclick="document.getElementById('modal-map').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white transition-colors"><i class="ph-bold ph-x text-xl"></i></button>
        </div>
        <form method="POST" class="space-y-5">
            <input type="hidden" name="add_map_pin" value="1">
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Location Name *</label>
                <input type="text" name="name" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Space Type *</label>
                <select name="type" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none appearance-none cursor-pointer transition-colors">
                    <option value="Basketball Court">Basketball Court</option>
                    <option value="Study Nook">Study Nook</option>
                    <option value="Youth Center">Youth Center</option>
                    <option value="Recycling Hub">Recycling Hub</option>
                    <option value="Disaster Safe-Zone">Disaster Safe-Zone</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Description</label>
                <textarea name="description" rows="3" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none resize-none transition-colors"></textarea>
            </div>
            <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 transition-colors">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-black py-4 rounded-xl transition-colors shadow-md">Save Location</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADD POLL -->
<div id="modal-poll" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm transition-colors" onclick="document.getElementById('modal-poll').classList.add('hidden')"></div>
    <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
        <div class="flex justify-between items-center mb-8">
            <h3 class="text-xl font-black text-slate-900 dark:text-white transition-colors">Launch Consultation</h3>
            <button onclick="document.getElementById('modal-poll').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white transition-colors"><i class="ph-bold ph-x text-xl"></i></button>
        </div>
        <form method="POST" class="space-y-5">
            <input type="hidden" name="add_poll" value="1">
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Poll Question / Topic *</label>
                <input type="text" name="question" required placeholder="e.g. Next sports league game format?" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 focus:border-blue-500 outline-none transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Option 1 *</label>
                <input type="text" name="opt1" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Option 2 *</label>
                <input type="text" name="opt2" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Option 3 (Optional)</label>
                <input type="text" name="opt3" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none transition-colors">
            </div>
            <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 transition-colors">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-black py-4 rounded-xl transition-colors shadow-md">Publish Poll</button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script>
    // Tab Logic
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('[id^="tabBtn-"]').forEach(btn => {
            btn.className = "px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent";
        });

        document.getElementById('tab-' + tabId).classList.add('active');
        document.getElementById('tabBtn-' + tabId).className = "px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500";
        
        const url = new URL(window.location);
        url.searchParams.set('tab', tabId);
        window.history.pushState({}, '', url);
    }

    // Theme Logic
    document.addEventListener("DOMContentLoaded", function() {
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
    });
</script>
</body>
</html>