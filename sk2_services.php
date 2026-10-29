<?php
// sk2_services.php - SK OS 2.0 Services & Opportunities Hub
session_start();
require 'db_connect.php';

// Security Check: Only SK Admins
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] !== 'sub-admin' && $_SESSION['role'] !== 'sk_admin')) {
    header("Location: sk2_login.php");
    exit();
}

$barangay = $_SESSION['admin_barangay'] ?? 'Municipal Level';
$message = '';
$msgType = '';
$activeTab = $_GET['tab'] ?? 'scholarships'; // Keeps track of active tab after reload

// Auto-create Tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_scholarships` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(255) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT NOT NULL,
        `eligibility` TEXT NOT NULL,
        `deadline` DATE NOT NULL,
        `status` ENUM('Active', 'Closed') DEFAULT 'Active',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_jobs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(255) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `type` ENUM('TESDA/Vocational', 'Local Job', 'Internship') NOT NULL,
        `description` TEXT NOT NULL,
        `contact_info` VARCHAR(255) NOT NULL,
        `status` ENUM('Open', 'Filled') DEFAULT 'Open',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_facility_bookings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(255) NOT NULL,
        `facility_name` VARCHAR(255) NOT NULL,
        `youth_name` VARCHAR(255) NOT NULL,
        `purpose` TEXT NOT NULL,
        `booking_date` DATE NOT NULL,
        `time_slot` VARCHAR(100) NOT NULL,
        `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// HANDLE SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_scholarship'])) {
        $stmt = $pdo->prepare("INSERT INTO sk2_scholarships (barangay, title, description, eligibility, deadline) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$barangay, $_POST['title'], $_POST['description'], $_POST['eligibility'], $_POST['deadline']])) {
            header("Location: sk2_services.php?tab=scholarships&msg=added"); exit();
        }
    }
    if (isset($_POST['add_job'])) {
        $stmt = $pdo->prepare("INSERT INTO sk2_jobs (barangay, title, type, description, contact_info) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$barangay, $_POST['title'], $_POST['type'], $_POST['description'], $_POST['contact_info']])) {
            header("Location: sk2_services.php?tab=jobs&msg=added"); exit();
        }
    }
}

// HANDLE ACTIONS (Approve/Reject Bookings, Delete Posts)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];
    $tab = $_GET['tab'] ?? 'scholarships';

    if ($action == 'approve_booking') {
        $pdo->prepare("UPDATE sk2_facility_bookings SET status = 'Approved' WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_services.php?tab=facilities&msg=approved"); exit();
    } elseif ($action == 'reject_booking') {
        $pdo->prepare("UPDATE sk2_facility_bookings SET status = 'Rejected' WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_services.php?tab=facilities&msg=rejected"); exit();
    } elseif ($action == 'delete_scholarship') {
        $pdo->prepare("DELETE FROM sk2_scholarships WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_services.php?tab=scholarships&msg=deleted"); exit();
    } elseif ($action == 'delete_job') {
        $pdo->prepare("DELETE FROM sk2_jobs WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_services.php?tab=jobs&msg=deleted"); exit();
    }
}

// Display messages
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m == 'added') { $message = 'Opportunity successfully posted.'; $msgType = 'success'; }
    if ($m == 'approved') { $message = 'Facility reservation approved.'; $msgType = 'success'; }
    if ($m == 'rejected') { $message = 'Reservation request denied.'; $msgType = 'warning'; }
    if ($m == 'deleted') { $message = 'Record purged from system.'; $msgType = 'danger'; }
}

// FETCH DATA
$scholarships = $pdo->prepare("SELECT * FROM sk2_scholarships WHERE barangay = ? ORDER BY deadline ASC");
$scholarships->execute([$barangay]); $scholarships = $scholarships->fetchAll(PDO::FETCH_ASSOC);

$jobs = $pdo->prepare("SELECT * FROM sk2_jobs WHERE barangay = ? ORDER BY created_at DESC");
$jobs->execute([$barangay]); $jobs = $jobs->fetchAll(PDO::FETCH_ASSOC);

$bookings = $pdo->prepare("SELECT * FROM sk2_facility_bookings WHERE barangay = ? ORDER BY created_at DESC");
$bookings->execute([$barangay]); $bookings = $bookings->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | Services Hub</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- FIX 1: TAILWIND DARK MODE CONFIGURATION -->
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
    <!-- END FIX 1 -->

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- Shared Theme Initialization -->
    <?php include 'sk2_theme_init.php'; ?>

    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        .dark ::-webkit-calendar-picker-indicator { filter: invert(1); }
        
        /* Tab Transitions */
        .tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex h-screen overflow-hidden selection:bg-blue-500 selection:text-white transition-colors duration-300">

<!-- Sidebar Include -->
<?php include 'sk2_sidebar.php'; ?>

<!-- FIX 2: CLEANED UP MAIN CONTAINER WITH DARK MODE BACKGROUND -->
<main class="ml-64 flex-1 h-screen overflow-y-auto custom-scrollbar relative bg-slate-50 dark:bg-zinc-950 transition-colors duration-300">

    <!-- The rest of your specific page content starts right below this! -->
    <!-- MAIN WORKSPACE -->
    <main class="flex-1 overflow-y-auto custom-scrollbar relative">
        <div class="relative z-10 p-8 lg:p-10 max-w-[1600px] mx-auto">
            
            <!-- Header & Theme Toggle -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1">
                        Services & Opportunities Hub
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium">Manage programs and facilities for <?= htmlspecialchars($barangay) ?>.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <button id="themeToggle" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <i id="themeIcon" class="ph-bold ph-moon text-lg"></i>
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <?php 
                    $borderColor = 'border-blue-200 dark:border-blue-500/30'; $bgColor = 'bg-blue-50 dark:bg-blue-500/10'; $textColor = 'text-blue-700 dark:text-blue-400'; $icon = 'ph-info';
                    if($msgType=='danger'){ $borderColor = 'border-red-200 dark:border-red-500/30'; $bgColor = 'bg-red-50 dark:bg-red-500/10'; $textColor = 'text-red-700 dark:text-red-400'; $icon = 'ph-warning-circle'; }
                    if($msgType=='warning'){ $borderColor = 'border-yellow-200 dark:border-yellow-500/30'; $bgColor = 'bg-yellow-50 dark:bg-yellow-500/10'; $textColor = 'text-yellow-700 dark:text-yellow-400'; $icon = 'ph-warning'; }
                    if($msgType=='success'){ $borderColor = 'border-green-200 dark:border-green-500/30'; $bgColor = 'bg-green-50 dark:bg-green-500/10'; $textColor = 'text-green-700 dark:text-green-400'; $icon = 'ph-check-circle'; }
                ?>
                <div class="mb-8 p-4 rounded-xl border <?= $borderColor ?> <?= $bgColor ?> <?= $textColor ?> flex items-center gap-3 font-bold text-sm transition-colors">
                    <i class="ph-fill <?= $icon ?> text-xl"></i> <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- TAB NAVIGATION -->
            <div class="flex gap-2 border-b border-slate-200 dark:border-zinc-800 mb-8 overflow-x-auto custom-scrollbar pb-2">
                <button onclick="switchTab('scholarships')" id="tabBtn-scholarships" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'scholarships' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-graduation-cap"></i> Scholarships & Assistance
                </button>
                <button onclick="switchTab('jobs')" id="tabBtn-jobs" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'jobs' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-briefcase"></i> Jobs & Skill-Training
                </button>
                <button onclick="switchTab('facilities')" id="tabBtn-facilities" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'facilities' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-calendar-check"></i> Facility Reservations
                </button>
            </div>

            <!-- TAB CONTENT: SCHOLARSHIPS -->
            <div id="tab-scholarships" class="tab-content <?= $activeTab == 'scholarships' ? 'active' : '' ?>">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-white">Active Assistance Programs</h3>
                    <button onclick="document.getElementById('modal-scholarship').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                        <i class="ph-bold ph-plus"></i> Post Program
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if(empty($scholarships)): ?>
                        <div class="md:col-span-2 lg:col-span-3 text-center py-10 bg-white dark:bg-zinc-900 border border-dashed border-slate-300 dark:border-zinc-800 rounded-2xl">
                            <p class="text-slate-500 dark:text-zinc-500 font-bold">No active scholarship programs.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($scholarships as $s): ?>
                        <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 relative group transition-colors shadow-sm dark:shadow-none">
                            <a href="?action=delete_scholarship&id=<?= $s['id'] ?>&tab=scholarships" onclick="return confirm('Delete this program?')" class="absolute top-4 right-4 text-slate-400 hover:text-red-500 dark:text-zinc-500 dark:hover:text-red-400 transition-colors"><i class="ph-bold ph-trash text-lg"></i></a>
                            
                            <span class="inline-block px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-widest rounded bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 mb-3">Deadline: <?= date('M d, Y', strtotime($s['deadline'])) ?></span>
                            <h4 class="text-lg font-bold text-slate-900 dark:text-white mb-2 leading-tight pr-6"><?= htmlspecialchars($s['title']) ?></h4>
                            <p class="text-sm text-slate-600 dark:text-zinc-400 mb-4 line-clamp-3"><?= htmlspecialchars($s['description']) ?></p>
                            <div class="bg-slate-50 dark:bg-zinc-800/50 p-3 rounded-xl border border-slate-100 dark:border-zinc-800/50">
                                <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-widest mb-1">Eligibility</p>
                                <p class="text-xs text-slate-700 dark:text-zinc-300 font-medium"><?= htmlspecialchars($s['eligibility']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB CONTENT: JOBS & TRAINING -->
            <div id="tab-jobs" class="tab-content <?= $activeTab == 'jobs' ? 'active' : '' ?>">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-white">Local Opportunities & TESDA</h3>
                    <button onclick="document.getElementById('modal-job').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                        <i class="ph-bold ph-plus"></i> Post Opportunity
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if(empty($jobs)): ?>
                        <div class="md:col-span-2 lg:col-span-3 text-center py-10 bg-white dark:bg-zinc-900 border border-dashed border-slate-300 dark:border-zinc-800 rounded-2xl">
                            <p class="text-slate-500 dark:text-zinc-500 font-bold">No active job or training postings.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($jobs as $j): 
                            $typeColor = $j['type'] == 'TESDA/Vocational' ? 'text-green-600 bg-green-50 dark:text-green-400 dark:bg-green-500/10' : 'text-blue-600 bg-blue-50 dark:text-blue-400 dark:bg-blue-500/10';
                        ?>
                        <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 relative group transition-colors shadow-sm dark:shadow-none">
                            <a href="?action=delete_job&id=<?= $j['id'] ?>&tab=jobs" onclick="return confirm('Delete this posting?')" class="absolute top-4 right-4 text-slate-400 hover:text-red-500 dark:text-zinc-500 dark:hover:text-red-400 transition-colors"><i class="ph-bold ph-trash text-lg"></i></a>
                            
                            <span class="inline-block px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-widest rounded <?= $typeColor ?> mb-3"><?= htmlspecialchars($j['type']) ?></span>
                            <h4 class="text-lg font-bold text-slate-900 dark:text-white mb-2 leading-tight pr-6"><?= htmlspecialchars($j['title']) ?></h4>
                            <p class="text-sm text-slate-600 dark:text-zinc-400 mb-4 line-clamp-3"><?= htmlspecialchars($j['description']) ?></p>
                            <div class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-zinc-400">
                                <i class="ph-fill ph-phone text-slate-400 dark:text-zinc-500"></i> <?= htmlspecialchars($j['contact_info']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB CONTENT: FACILITY RESERVATIONS -->
            <div id="tab-facilities" class="tab-content <?= $activeTab == 'facilities' ? 'active' : '' ?>">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-white">Facility Reservation Queue</h3>
                </div>
                <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 transition-colors shadow-sm dark:shadow-none">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800">
                                    <th class="py-4 px-6">Requester</th>
                                    <th class="py-4 px-6">Facility & Time</th>
                                    <th class="py-4 px-6">Purpose</th>
                                    <th class="py-4 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50">
                                <?php if(empty($bookings)): ?>
                                    <tr><td colspan="4" class="py-12 text-center text-slate-500 dark:text-zinc-500">No facility reservations right now.</td></tr>
                                <?php else: ?>
                                    <?php foreach($bookings as $b): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors">
                                        <td class="py-4 px-6 text-slate-800 dark:text-zinc-200 font-bold"><?= htmlspecialchars($b['youth_name']) ?></td>
                                        <td class="py-4 px-6">
                                            <p class="font-bold text-blue-600 dark:text-blue-400"><?= htmlspecialchars($b['facility_name']) ?></p>
                                            <p class="text-[10px] text-slate-500 dark:text-zinc-400 uppercase tracking-widest mt-1"><?= date('M d, Y', strtotime($b['booking_date'])) ?> | <?= htmlspecialchars($b['time_slot']) ?></p>
                                        </td>
                                        <td class="py-4 px-6 text-slate-600 dark:text-zinc-400 text-xs italic">"<?= htmlspecialchars($b['purpose']) ?>"</td>
                                        <td class="py-4 px-6 text-right whitespace-nowrap">
                                            <?php if($b['status'] == 'Pending'): ?>
                                                <a href="?action=approve_booking&id=<?= $b['id'] ?>&tab=facilities" class="inline-flex w-8 h-8 rounded-lg bg-green-50 text-green-600 hover:bg-green-600 hover:text-white dark:bg-green-500/20 dark:text-green-400 dark:hover:bg-green-500 dark:hover:text-zinc-900 items-center justify-center transition tooltip"><i class="ph-bold ph-check"></i></a>
                                                <a href="?action=reject_booking&id=<?= $b['id'] ?>&tab=facilities" class="inline-flex w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white dark:bg-red-500/20 dark:text-red-400 dark:hover:bg-red-500 dark:hover:text-white items-center justify-center transition ml-1 tooltip"><i class="ph-bold ph-x"></i></a>
                                            <?php else: ?>
                                                <span class="text-[9px] font-extrabold uppercase tracking-widest px-2.5 py-1 rounded border <?= $b['status'] == 'Approved' ? 'text-green-700 bg-green-50 border-green-200 dark:text-green-400 dark:bg-green-500/10 dark:border-green-500/20' : 'text-red-700 bg-red-50 border-red-200 dark:text-red-400 dark:bg-red-500/10 dark:border-red-500/20' ?>"><?= $b['status'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL: ADD SCHOLARSHIP -->
    <div id="modal-scholarship" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm" onclick="document.getElementById('modal-scholarship').classList.add('hidden')"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Post Scholarship</h3>
                <button onclick="document.getElementById('modal-scholarship').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white"><i class="ph-bold ph-x text-xl"></i></button>
            </div>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="add_scholarship" value="1">
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Program Title *</label>
                    <input type="text" name="title" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Description *</label>
                    <textarea name="description" rows="3" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Eligibility Criteria *</label>
                    <textarea name="eligibility" rows="2" required placeholder="e.g. Must be 18-24 yrs old..." class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Application Deadline *</label>
                    <input type="date" name="deadline" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-zinc-300 focus:border-blue-500 outline-none">
                </div>
                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition-colors">Publish Program</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ADD JOB/TRAINING -->
    <div id="modal-job" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm" onclick="document.getElementById('modal-job').classList.add('hidden')"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Post Opportunity</h3>
                <button onclick="document.getElementById('modal-job').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white"><i class="ph-bold ph-x text-xl"></i></button>
            </div>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="add_job" value="1">
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Job/Course Title *</label>
                    <input type="text" name="title" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Type</label>
                    <select name="type" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none appearance-none">
                        <option value="TESDA/Vocational">TESDA/Vocational</option>
                        <option value="Local Job">Local Employment</option>
                        <option value="Internship">Govt Internship</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Description & Requirements *</label>
                    <textarea name="description" rows="3" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none resize-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Contact Info / Link *</label>
                    <input type="text" name="contact_info" required placeholder="Phone number or application link" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none">
                </div>
                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition-colors">Post Opportunity</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts for Tab Switching & Theme -->
    <script>
        // Tab Logic
        function switchTab(tabId) {
            // Hide all contents
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            // Remove active classes from buttons (reset to inactive)
            document.querySelectorAll('[id^="tabBtn-"]').forEach(btn => {
                btn.className = "px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent";
            });

            // Show selected content
            document.getElementById('tab-' + tabId).classList.add('active');
            
            // Set active classes on selected button
            document.getElementById('tabBtn-' + tabId).className = "px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500";
            
            // Update URL without reloading page (for clean sharing/reloads)
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
        }

        // Theme Logic
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        if (document.documentElement.classList.contains('dark')) { themeIcon.classList.replace('ph-moon', 'ph-sun'); }

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