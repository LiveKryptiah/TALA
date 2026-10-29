<?php
// sk2_assembly.php - SK OS 2.0 Katipunan ng Kabataan Assembly Manager
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] !== 'sub-admin' && $_SESSION['role'] !== 'sk_admin')) {
    header("Location: sk2_login.php"); exit();
}

$barangay = $_SESSION['admin_barangay'] ?? 'Municipal Level';
$message = ''; $msgType = '';
$activeTab = $_GET['tab'] ?? 'assemblies';

// =================================================================
// FALLBACK XP FUNCTION (Ensures it works even if not in db_connect.php)
// =================================================================
if (!function_exists('autoAwardXP')) {
    function autoAwardXP($pdo, $admin_user, $barangay, $action_name, $xp_amount) {
        try {
            // Save to DB
            $stmt = $pdo->prepare("INSERT INTO sk2_admin_xp (admin_user, barangay, action_name, xp_earned) VALUES (?, ?, ?, ?)");
            $stmt->execute([$admin_user, $barangay, $action_name, $xp_amount]);

            // Trigger Toast
            $_SESSION['toast_xp'] = $xp_amount;
            $_SESSION['toast_msg'] = $action_name;
        } catch (Exception $e) {}
    }
}

// Auto-create Tables
try {
    // Assemblies Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_assemblies` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT NOT NULL,
        `schedule_date` DATETIME NOT NULL,
        `location` VARCHAR(255) NOT NULL,
        `status` ENUM('Upcoming', 'Ongoing', 'Completed', 'Cancelled') DEFAULT 'Upcoming',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Attendance Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_assembly_attendance` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `assembly_id` INT NOT NULL,
        `youth_id` INT NOT NULL,
        `barangay` VARCHAR(100) NOT NULL,
        `time_in` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`assembly_id`) REFERENCES `sk2_assemblies`(`id`) ON DELETE CASCADE
    )");
} catch (PDOException $e) {}

// HANDLE SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Schedule New Assembly
    if (isset($_POST['add_assembly'])) {
        $stmt = $pdo->prepare("INSERT INTO sk2_assemblies (barangay, title, description, schedule_date, location, status) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$barangay, $_POST['title'], $_POST['description'], $_POST['schedule_date'], $_POST['location'], $_POST['status']])) {
            
            // AUTO XP AWARD
            autoAwardXP($pdo, $_SESSION['admin_username'], $barangay, "Scheduled a Katipunan ng Kabataan Assembly", 40);
            
            header("Location: sk2_assembly.php?tab=assemblies&msg=assembly_added"); 
            exit();
        }
    }
    
    // Log Attendance
    if (isset($_POST['log_attendance'])) {
        $assembly_id = $_POST['assembly_id'];
        $youth_id = $_POST['youth_id'];

        // Check if already logged to prevent duplicates
        $check = $pdo->prepare("SELECT id FROM sk2_assembly_attendance WHERE assembly_id = ? AND youth_id = ?");
        $check->execute([$assembly_id, $youth_id]);
        
        if ($check->rowCount() > 0) {
            header("Location: sk2_assembly.php?tab=attendance&msg=already_logged"); 
            exit();
        } else {
            $stmt = $pdo->prepare("INSERT INTO sk2_assembly_attendance (assembly_id, youth_id, barangay) VALUES (?, ?, ?)");
            if ($stmt->execute([$assembly_id, $youth_id, $barangay])) {
                
                // AUTO XP AWARD
                autoAwardXP($pdo, $_SESSION['admin_username'], $barangay, "Logged Youth Assembly Attendance", 5);

                header("Location: sk2_assembly.php?tab=attendance&msg=attendance_logged"); 
                exit();
            }
        }
    }
}

// HANDLE ACTIONS (Deletions & Updates)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];

    if ($action == 'delete_assembly') {
        $pdo->prepare("DELETE FROM sk2_assemblies WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_assembly.php?tab=assemblies&msg=deleted"); exit();
    } elseif ($action == 'update_status') {
        $status = $_GET['status'];
        $pdo->prepare("UPDATE sk2_assemblies SET status = ? WHERE id = ? AND barangay = ?")->execute([$status, $id, $barangay]);
        
        // AUTO XP AWARD
        autoAwardXP($pdo, $_SESSION['admin_username'], $barangay, "Updated Assembly Status to $status", 10);

        header("Location: sk2_assembly.php?tab=assemblies&msg=status_updated"); exit();
    } elseif ($action == 'delete_attendance') {
        $pdo->prepare("DELETE FROM sk2_assembly_attendance WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_assembly.php?tab=attendance&msg=deleted"); exit();
    }
}

// Set Messages
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m == 'assembly_added') { $message = 'Assembly scheduled successfully.'; $msgType = 'success'; }
    if ($m == 'attendance_logged') { $message = 'Youth attendance logged.'; $msgType = 'success'; }
    if ($m == 'already_logged') { $message = 'This citizen is already logged for this assembly.'; $msgType = 'warning'; }
    if ($m == 'status_updated') { $message = 'Assembly status updated.'; $msgType = 'success'; }
    if ($m == 'deleted') { $message = 'Record purged from database.'; $msgType = 'danger'; }
}

// FETCH DATA
$assemblies = $pdo->prepare("SELECT * FROM sk2_assemblies WHERE barangay = ? ORDER BY schedule_date DESC");
$assemblies->execute([$barangay]); $assemblies = $assemblies->fetchAll(PDO::FETCH_ASSOC);

// Fetch Attendance joined with youth_registry
$attendanceRecords = $pdo->prepare("
    SELECT a.id as attendance_id, a.time_in, y.id as youth_id, y.first_name, y.last_name, y.full_name, asm.title as assembly_title 
    FROM sk2_assembly_attendance a
    JOIN youth_registry y ON a.youth_id = y.id
    JOIN sk2_assemblies asm ON a.assembly_id = asm.id
    WHERE a.barangay = ?
    ORDER BY a.time_in DESC
");
$attendanceRecords->execute([$barangay]); $attendanceRecords = $attendanceRecords->fetchAll(PDO::FETCH_ASSOC);

// Fetch Youth for Dropdown
$youthRegistry = [];
try {
    $stmtY = $pdo->prepare("SELECT id, first_name, last_name, full_name FROM youth_registry WHERE barangay = ? ORDER BY last_name ASC");
    $stmtY->execute([$barangay]); $youthRegistry = $stmtY->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {}
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | Youth Assembly</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
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
    <?php include 'sk2_theme_init.php'; ?>
    
    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        .dark ::-webkit-calendar-picker-indicator { filter: invert(1); }
        
        .tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex h-screen overflow-hidden selection:bg-blue-500 selection:text-white transition-colors duration-300">

<!-- Sidebar Include -->
<?php include 'sk2_sidebar.php'; ?>

<!-- MAIN CONTAINER -->
<main class="ml-64 flex-1 h-screen overflow-y-auto custom-scrollbar relative bg-slate-50 dark:bg-zinc-950 transition-colors duration-300">

        <div class="relative z-10 p-8 lg:p-10 max-w-[1600px] mx-auto">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4 transition-colors">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1 transition-colors">
                        Youth Assembly Center
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium transition-colors">Manage Katipunan ng Kabataan meetings and attendance for <?= htmlspecialchars($barangay) ?>.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <button id="themeToggle" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors shadow-sm">
                        <i id="themeIcon" class="ph-bold ph-moon text-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Notifications -->
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
                <button onclick="switchTab('assemblies')" id="tabBtn-assemblies" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'assemblies' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-calendar-blank"></i> Meeting Scheduler
                </button>
                <button onclick="switchTab('attendance')" id="tabBtn-attendance" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'attendance' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-users-three"></i> Attendance Records
                </button>
            </div>

            <!-- TAB 1: ASSEMBLIES -->
            <div id="tab-assemblies" class="tab-content <?= $activeTab == 'assemblies' ? 'active' : '' ?>">
                <div class="flex justify-between items-center mb-6 transition-colors">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-white transition-colors">Assembly Timetable</h3>
                    <button onclick="document.getElementById('modal-assembly').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                        <i class="ph-bold ph-plus"></i> Schedule Meeting
                    </button>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php if(empty($assemblies)): ?>
                        <div class="col-span-full text-center py-12 bg-white dark:bg-zinc-900 border border-dashed border-slate-300 dark:border-zinc-800 rounded-2xl transition-colors">
                            <p class="text-slate-500 dark:text-zinc-500 font-bold transition-colors">No assemblies scheduled.</p>
                        </div>
                    <?php else: foreach($assemblies as $a): 
                        $statusColor = 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400 border-transparent';
                        if ($a['status'] == 'Upcoming') $statusColor = 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-200 dark:border-blue-500/30';
                        if ($a['status'] == 'Ongoing') $statusColor = 'bg-yellow-50 text-yellow-600 dark:bg-yellow-500/10 dark:text-yellow-400 border border-yellow-200 dark:border-yellow-500/30 animate-pulse';
                        if ($a['status'] == 'Completed') $statusColor = 'bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400 border border-green-200 dark:border-green-500/30';
                        if ($a['status'] == 'Cancelled') $statusColor = 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400 border border-red-200 dark:border-red-500/30';
                    ?>
                    <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 relative group transition-colors shadow-sm flex flex-col h-full hover:border-blue-500 dark:hover:border-blue-500">
                        <div class="flex justify-between items-start mb-4">
                            <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-widest rounded-lg transition-colors <?= $statusColor ?>"><?= $a['status'] ?></span>
                            <div class="flex items-center gap-3">
                                <select onchange="window.location.href='?action=update_status&id=<?= $a['id'] ?>&tab=assemblies&status='+this.value" class="bg-transparent border-none text-[10px] font-bold uppercase tracking-widest outline-none cursor-pointer text-slate-500 hover:text-blue-600 dark:text-zinc-400 dark:hover:text-blue-400 transition-colors appearance-none text-right">
                                    <option value="Upcoming" <?= $a['status']=='Upcoming'?'selected':'' ?> class="dark:bg-zinc-800">Upcoming</option>
                                    <option value="Ongoing" <?= $a['status']=='Ongoing'?'selected':'' ?> class="dark:bg-zinc-800">Ongoing</option>
                                    <option value="Completed" <?= $a['status']=='Completed'?'selected':'' ?> class="dark:bg-zinc-800">Completed</option>
                                    <option value="Cancelled" <?= $a['status']=='Cancelled'?'selected':'' ?> class="dark:bg-zinc-800">Cancel</option>
                                </select>
                                <a href="?action=delete_assembly&id=<?= $a['id'] ?>&tab=assemblies" onclick="return confirm('Permanently delete this assembly?')" class="text-slate-300 hover:text-red-500 dark:text-zinc-600 dark:hover:text-red-400 transition-colors"><i class="ph-bold ph-trash text-lg"></i></a>
                            </div>
                        </div>
                        
                        <h4 class="text-xl font-bold text-slate-900 dark:text-white mb-2 leading-tight transition-colors"><?= htmlspecialchars($a['title']) ?></h4>
                        <p class="text-sm text-slate-600 dark:text-zinc-400 mb-6 flex-1 line-clamp-3 transition-colors"><?= htmlspecialchars($a['description']) ?></p>
                        
                        <div class="space-y-2 bg-slate-50 dark:bg-zinc-950 p-4 rounded-xl border border-slate-100 dark:border-zinc-800 transition-colors">
                            <div class="flex items-center gap-2 text-sm text-slate-700 dark:text-zinc-300 font-medium transition-colors">
                                <i class="ph-fill ph-calendar-blank text-blue-500 dark:text-blue-400"></i> <?= date('F d, Y - h:i A', strtotime($a['schedule_date'])) ?>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-slate-700 dark:text-zinc-300 font-medium transition-colors">
                                <i class="ph-fill ph-map-pin text-red-500 dark:text-red-400"></i> <?= htmlspecialchars($a['location']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- TAB 2: ATTENDANCE RECORDS -->
            <div id="tab-attendance" class="tab-content <?= $activeTab == 'attendance' ? 'active' : '' ?>">
                <div class="flex justify-between items-center mb-6 transition-colors">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-white transition-colors">Assembly Attendance Log</h3>
                    <button onclick="document.getElementById('modal-attendance').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                        <i class="ph-bold ph-clipboard-text"></i> Log Attendance
                    </button>
                </div>

                <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 transition-colors shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800 transition-colors">
                                    <th class="py-4 px-6 w-16">Time In</th>
                                    <th class="py-4 px-6">Youth Citizen</th>
                                    <th class="py-4 px-6">Assembly Attended</th>
                                    <th class="py-4 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50 transition-colors">
                                <?php if(empty($attendanceRecords)): ?>
                                    <tr><td colspan="4" class="py-12 text-center text-slate-500 dark:text-zinc-500 transition-colors">No attendance logged yet.</td></tr>
                                <?php else: foreach($attendanceRecords as $log): 
                                    $name = !empty($log['full_name']) ? $log['full_name'] : $log['first_name'].' '.$log['last_name'];
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors group">
                                    <td class="py-4 px-6 whitespace-nowrap">
                                        <p class="font-bold text-slate-900 dark:text-white transition-colors"><?= date('M d, Y', strtotime($log['time_in'])) ?></p>
                                        <p class="text-[10px] text-slate-500 dark:text-zinc-500 uppercase tracking-widest mt-0.5 transition-colors"><?= date('h:i A', strtotime($log['time_in'])) ?></p>
                                    </td>
                                    <td class="py-4 px-6">
                                        <p class="font-bold text-blue-600 dark:text-blue-400 transition-colors"><?= htmlspecialchars($name) ?></p>
                                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-widest mt-0.5 transition-colors">ID: <?= str_pad($log['youth_id'], 5, '0', STR_PAD_LEFT) ?></p>
                                    </td>
                                    <td class="py-4 px-6 text-slate-700 dark:text-zinc-300 font-semibold transition-colors">
                                        <?= htmlspecialchars($log['assembly_title']) ?>
                                    </td>
                                    <td class="py-4 px-6 text-right whitespace-nowrap">
                                        <a href="?action=delete_attendance&id=<?= $log['attendance_id'] ?>&tab=attendance" onclick="return confirm('Remove this attendance record?')" class="inline-flex w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500 dark:hover:text-white items-center justify-center transition-colors tooltip"><i class="ph-bold ph-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
</main>

<!-- MODAL: SCHEDULE ASSEMBLY -->
<div id="modal-assembly" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm transition-colors" onclick="document.getElementById('modal-assembly').classList.add('hidden')"></div>
    <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
        <div class="flex justify-between items-center mb-8">
            <h3 class="text-xl font-black text-slate-900 dark:text-white transition-colors">Schedule Assembly</h3>
            <button onclick="document.getElementById('modal-assembly').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white transition-colors"><i class="ph-bold ph-x text-xl"></i></button>
        </div>
        <form method="POST" class="space-y-5">
            <input type="hidden" name="add_assembly" value="1">
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Assembly Title *</label>
                <input type="text" name="title" required placeholder="e.g. Q3 KK General Assembly" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 outline-none focus:border-blue-500 dark:focus:border-blue-500 transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Agenda / Description *</label>
                <textarea name="description" rows="3" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none resize-none focus:border-blue-500 dark:focus:border-blue-500 transition-colors"></textarea>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Schedule (Date & Time) *</label>
                <input type="datetime-local" name="schedule_date" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-zinc-300 outline-none focus:border-blue-500 dark:focus:border-blue-500 transition-colors" style="color-scheme: dark light;">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Location *</label>
                <input type="text" name="location" required placeholder="e.g. Barangay Hall Covered Court" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 outline-none focus:border-blue-500 dark:focus:border-blue-500 transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Initial Status</label>
                <select name="status" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none focus:border-blue-500 dark:focus:border-blue-500 cursor-pointer transition-colors">
                    <option value="Upcoming">Upcoming</option>
                    <option value="Ongoing">Ongoing</option>
                </select>
            </div>
            <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 transition-colors">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-black py-4 rounded-xl transition-colors shadow-sm">Publish Schedule</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: LOG ATTENDANCE -->
<div id="modal-attendance" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm transition-colors" onclick="document.getElementById('modal-attendance').classList.add('hidden')"></div>
    <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
        <div class="flex justify-between items-center mb-8">
            <h3 class="text-xl font-black text-slate-900 dark:text-white transition-colors">Log Attendance</h3>
            <button onclick="document.getElementById('modal-attendance').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white transition-colors"><i class="ph-bold ph-x text-xl"></i></button>
        </div>
        <form method="POST" class="space-y-5">
            <input type="hidden" name="log_attendance" value="1">
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Target Assembly *</label>
                <select name="assembly_id" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none focus:border-blue-500 dark:focus:border-blue-500 cursor-pointer transition-colors">
                    <option value="" disabled selected>-- Select Assembly --</option>
                    <?php foreach($assemblies as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['title']) ?> (<?= date('M d', strtotime($a['schedule_date'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Youth Citizen *</label>
                <select name="youth_id" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none focus:border-blue-500 dark:focus:border-blue-500 cursor-pointer transition-colors">
                    <option value="" disabled selected>-- Select from Registry --</option>
                    <?php foreach($youthRegistry as $y): 
                        $name = !empty($y['full_name']) ? $y['full_name'] : $y['first_name'].' '.$y['last_name'];
                    ?>
                        <option value="<?= $y['id'] ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 transition-colors">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-black py-4 rounded-xl transition-colors shadow-sm">Submit Time-in</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================= -->
<!-- CLEAN FLAT XP TOAST NOTIFICATION UI -->
<!-- ========================================================= -->
<?php if (isset($_SESSION['toast_xp'])): ?>
    <style>
        @keyframes shimmerToast {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(200%); }
        }
        .animate-shimmer-toast { animation: shimmerToast 2.5s infinite ease-in-out; }
    </style>
    
    <!-- Centered, Glassmorphism, Pill-shaped (Shadows Removed) -->
    <div id="sk2-xp-toast" class="fixed bottom-12 left-1/2 -translate-x-1/2 z-[9999] transform translate-y-16 scale-90 opacity-0 transition-all duration-700 ease-[cubic-bezier(0.34,1.56,0.64,1)] pointer-events-none">
        
        <div class="bg-white/90 dark:bg-zinc-900/90 backdrop-blur-xl px-2 py-2 pr-6 rounded-full border border-slate-200 dark:border-zinc-700 flex items-center gap-4 relative overflow-hidden">
            
            <!-- Shimmer Sweep -->
            <div class="absolute inset-0 w-[150%] bg-gradient-to-r from-transparent via-white/40 dark:via-white/5 to-transparent animate-shimmer-toast skew-x-12"></div>

            <!-- Clean Star Icon (Restored, no background glow or outer shadow) -->
            <div class="relative w-12 h-12 shrink-0">
                <div class="relative w-full h-full bg-gradient-to-b from-[#4ade80] to-[#15803d] rounded-full flex items-center justify-center z-10">
                    <i class="ph-fill ph-shooting-star text-white text-xl"></i>
                </div>
            </div>
            
            <!-- Typography -->
            <div class="relative z-10 flex flex-col justify-center pb-0.5">
                <p class="text-[15px] font-black text-slate-800 dark:text-white tracking-tight leading-none">
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#1da85b] to-[#15803d] dark:to-[#4ade80]">+<?= $_SESSION['toast_xp'] ?> XP</span> Awarded
                </p>
                <p class="text-[11px] font-bold text-slate-500 dark:text-zinc-400 mt-1 uppercase tracking-widest line-clamp-1">
                    <?= htmlspecialchars($_SESSION['toast_msg']) ?>
                </p>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const toast = document.getElementById('sk2-xp-toast');
            if (toast) {
                // Slide up with bounce
                setTimeout(() => { 
                    toast.classList.remove('translate-y-16', 'scale-90', 'opacity-0'); 
                }, 100);
                
                // Fade out
                setTimeout(() => {
                    toast.classList.add('translate-y-10', 'scale-95', 'opacity-0');
                    setTimeout(() => toast.remove(), 700);
                }, 4000);
            }
        });
    </script>

    <?php 
    unset($_SESSION['toast_xp']); 
    unset($_SESSION['toast_msg']); 
    ?>
<?php endif; ?>

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
        if (themeIcon && document.documentElement.classList.contains('dark')) { 
            themeIcon.classList.replace('ph-moon', 'ph-sun'); 
        }

        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function() {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('theme', 'light');
                    if(themeIcon) themeIcon.classList.replace('ph-sun', 'ph-moon');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('theme', 'dark');
                    if(themeIcon) themeIcon.classList.replace('ph-moon', 'ph-sun');
                }
            });
        }
    });
</script>
</body>
</html>