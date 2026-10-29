<?php
// sk2_rights.php - SK OS 2.0 Inclusivity, Rights & Safe Spaces
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] !== 'sub-admin' && $_SESSION['role'] !== 'sk_admin')) {
    header("Location: sk2_login.php"); exit();
}

$barangay = $_SESSION['admin_barangay'] ?? 'Municipal Level';
$message = ''; $msgType = '';

// Auto-create Tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_safe_reports` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `incident_type` VARCHAR(100) NOT NULL,
        `description` TEXT NOT NULL,
        `contact_info` VARCHAR(255) DEFAULT 'Anonymous',
        `status` ENUM('Pending Review', 'Under Investigation', 'Referred to VAWC', 'Resolved', 'Dismissed') DEFAULT 'Pending Review',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// HANDLE MANUAL SUBMISSION (If a youth walks into the barangay hall to report)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['log_report'])) {
    $type = $_POST['incident_type'];
    $desc = $_POST['description'];
    $contact = empty($_POST['contact_info']) ? 'Anonymous (Walk-in)' : $_POST['contact_info'];

    $stmt = $pdo->prepare("INSERT INTO sk2_safe_reports (barangay, incident_type, description, contact_info) VALUES (?, ?, ?, ?)");
    if ($stmt->execute([$barangay, $type, $desc, $contact])) {
        header("Location: sk2_rights.php?msg=report_logged"); exit();
    }
}

// HANDLE STATUS UPDATES & DELETION
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];

    if ($action == 'update_status' && isset($_GET['status'])) {
        $status = $_GET['status'];
        $pdo->prepare("UPDATE sk2_safe_reports SET status = ? WHERE id = ? AND barangay = ?")->execute([$status, $id, $barangay]);
        header("Location: sk2_rights.php?msg=status_updated"); exit();
    } elseif ($action == 'delete') {
        $pdo->prepare("DELETE FROM sk2_safe_reports WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_rights.php?msg=deleted"); exit();
    }
}

// Set Messages
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m == 'report_logged') { $message = 'Secure report logged successfully.'; $msgType = 'success'; }
    if ($m == 'status_updated') { $message = 'Case status updated.'; $msgType = 'success'; }
    if ($m == 'deleted') { $message = 'Report securely purged from database.'; $msgType = 'danger'; }
}

// FETCH DATA
$reports = $pdo->prepare("SELECT * FROM sk2_safe_reports WHERE barangay = ? ORDER BY created_at DESC");
$reports->execute([$barangay]); 
$reports = $reports->fetchAll(PDO::FETCH_ASSOC);

// Count Stats
$pending = 0; $vawc = 0; $resolved = 0;
foreach($reports as $r) {
    if($r['status'] == 'Pending Review') $pending++;
    if($r['status'] == 'Referred to VAWC') $vawc++;
    if($r['status'] == 'Resolved') $resolved++;
}
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | Rights & Safe Spaces</title>
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
    <?php include 'sk2_theme_init.php'; ?>
    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex h-screen overflow-hidden selection:bg-blue-500 selection:text-white transition-colors duration-300">

 <!-- Sidebar Include -->
<?php include 'sk2_sidebar.php'; ?>

<!-- FIX 2: CLEANED UP MAIN CONTAINER WITH DARK MODE BACKGROUND -->
<main class="ml-64 flex-1 h-screen overflow-y-auto custom-scrollbar relative bg-slate-50 dark:bg-zinc-950 transition-colors duration-300">
        
        <!-- Highly Secure Watermark / Pattern -->
        <div class="absolute inset-0 opacity-[0.03] dark:opacity-[0.02] pointer-events-none z-0" style="background-image: repeating-linear-gradient(45deg, #000 0, #000 1px, transparent 0, transparent 50%); background-size: 10px 10px;"></div>

        <div class="relative z-10 p-8 lg:p-10 max-w-[1600px] mx-auto">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1">
                        Rights & Safe Spaces Desk
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium flex items-center gap-2">
                        <i class="ph-fill ph-lock-key text-blue-500"></i> End-to-End Encrypted Case Management for <?= htmlspecialchars($barangay) ?>.
                    </p>
                </div>
                
                <div class="flex items-center gap-3">
                    <button id="themeToggle" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <i id="themeIcon" class="ph-bold ph-moon text-lg"></i>
                    </button>
                    <button onclick="document.getElementById('modal-report').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 transition-colors shadow-sm">
                        <i class="ph-bold ph-plus"></i> Log Manual Intake
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <?php 
                    $borderColor = 'border-blue-200 dark:border-blue-500/30'; $bgColor = 'bg-blue-50 dark:bg-blue-500/10'; $textColor = 'text-blue-700 dark:text-blue-400'; $icon = 'ph-info';
                    if($msgType=='danger'){ $borderColor = 'border-red-200 dark:border-red-500/30'; $bgColor = 'bg-red-50 dark:bg-red-500/10'; $textColor = 'text-red-700 dark:text-red-400'; $icon = 'ph-warning-circle'; }
                    if($msgType=='success'){ $borderColor = 'border-green-200 dark:border-green-500/30'; $bgColor = 'bg-green-50 dark:bg-green-500/10'; $textColor = 'text-green-700 dark:text-green-400'; $icon = 'ph-check-circle'; }
                ?>
                <div class="mb-8 p-4 rounded-xl border <?= $borderColor ?> <?= $bgColor ?> <?= $textColor ?> flex items-center gap-3 font-bold text-sm transition-colors">
                    <i class="ph-fill <?= $icon ?> text-xl"></i> <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- Case Dashboard HUD -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 rounded-2xl transition-colors flex items-center gap-5 border-l-4 border-l-red-500">
                    <div class="w-14 h-14 bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400 border border-red-100 dark:border-red-500/20 rounded-xl flex items-center justify-center text-3xl"><i class="ph-fill ph-warning-octagon"></i></div>
                    <div><p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500 mb-1">Action Required</p><h3 class="text-3xl font-black text-slate-900 dark:text-white"><?= $pending ?></h3></div>
                </div>
                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 rounded-2xl transition-colors flex items-center gap-5 border-l-4 border-l-yellow-500">
                    <div class="w-14 h-14 bg-yellow-50 text-yellow-600 dark:bg-yellow-500/10 dark:text-yellow-400 border border-yellow-100 dark:border-yellow-500/20 rounded-xl flex items-center justify-center text-3xl"><i class="ph-fill ph-police-car"></i></div>
                    <div><p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500 mb-1">Active VAWC Cases</p><h3 class="text-3xl font-black text-slate-900 dark:text-white"><?= $vawc ?></h3></div>
                </div>
                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 rounded-2xl transition-colors flex items-center gap-5 border-l-4 border-l-green-500">
                    <div class="w-14 h-14 bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400 border border-green-100 dark:border-green-500/20 rounded-xl flex items-center justify-center text-3xl"><i class="ph-fill ph-shield-check"></i></div>
                    <div><p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500 mb-1">Resolved Safely</p><h3 class="text-3xl font-black text-slate-900 dark:text-white"><?= $resolved ?></h3></div>
                </div>
            </div>

            <!-- Highly Secure Reports Inbox -->
            <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 shadow-sm dark:shadow-none">
                <div class="p-6 border-b border-slate-200 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-900/50">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="ph-bold ph-envelope-open text-blue-500"></i> Confidential Reports Inbox
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800">
                                <th class="py-4 px-6 w-24">Case ID</th>
                                <th class="py-4 px-6">Incident Details</th>
                                <th class="py-4 px-6">Contact Given</th>
                                <th class="py-4 px-6 text-center">Status</th>
                                <th class="py-4 px-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50">
                            <?php if(empty($reports)): ?>
                                <tr>
                                    <td colspan="5" class="py-16 text-center">
                                        <i class="ph-fill ph-check-circle text-5xl text-slate-300 dark:text-zinc-700 mb-3 inline-block"></i>
                                        <p class="text-slate-500 dark:text-zinc-400 font-bold">Inbox clear. No active reports.</p>
                                    </td>
                                </tr>
                            <?php else: foreach($reports as $r): 
                                // Color logic for status
                                $statColor = 'text-slate-600 bg-slate-100 dark:bg-zinc-800 dark:text-zinc-300';
                                if($r['status'] == 'Pending Review') $statColor = 'text-red-700 bg-red-100 dark:bg-red-500/20 dark:text-red-400 animate-pulse';
                                if($r['status'] == 'Under Investigation') $statColor = 'text-blue-700 bg-blue-100 dark:bg-blue-500/20 dark:text-blue-400';
                                if($r['status'] == 'Referred to VAWC') $statColor = 'text-yellow-700 bg-yellow-100 dark:bg-yellow-500/20 dark:text-yellow-400';
                                if($r['status'] == 'Resolved') $statColor = 'text-green-700 bg-green-100 dark:bg-green-500/20 dark:text-green-400';
                            ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <td class="py-4 px-6 text-slate-500 dark:text-zinc-500 font-mono text-xs font-bold">
                                    CAS-<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400"><?= htmlspecialchars($r['incident_type']) ?></span>
                                    <p class="text-slate-900 dark:text-white mt-1 line-clamp-2 leading-snug w-80"><?= htmlspecialchars($r['description']) ?></p>
                                    <p class="text-[9px] text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-widest mt-2"><i class="ph-fill ph-clock"></i> <?= date('M d, Y h:i A', strtotime($r['created_at'])) ?></p>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700">
                                        <i class="ph-fill <?= $r['contact_info']=='Anonymous' ? 'ph-mask-happy text-slate-400' : 'ph-user text-blue-500' ?>"></i>
                                        <span class="text-xs font-bold text-slate-700 dark:text-zinc-300"><?= htmlspecialchars($r['contact_info']) ?></span>
                                    </div>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-widest rounded-lg <?= $statColor ?>"><?= $r['status'] ?></span>
                                </td>
                                <td class="py-4 px-6 text-right whitespace-nowrap">
                                    <select onchange="window.location.href='?action=update_status&id=<?= $r['id'] ?>&status='+this.value" class="bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 rounded-lg text-xs font-bold px-3 py-2 mr-2 outline-none cursor-pointer text-slate-700 dark:text-zinc-300 hover:border-blue-500 transition-colors focus:ring-2 focus:ring-blue-500">
                                        <option value="Pending Review" <?= $r['status']=='Pending Review'?'selected':'' ?>>Set Pending</option>
                                        <option value="Under Investigation" <?= $r['status']=='Under Investigation'?'selected':'' ?>>Investigate</option>
                                        <option value="Referred to VAWC" <?= $r['status']=='Referred to VAWC'?'selected':'' ?>>Refer to VAWC</option>
                                        <option value="Resolved" <?= $r['status']=='Resolved'?'selected':'' ?>>Resolve Case</option>
                                        <option value="Dismissed" <?= $r['status']=='Dismissed'?'selected':'' ?>>Dismiss</option>
                                    </select>
                                    <a href="?action=delete&id=<?= $r['id'] ?>" onclick="return confirm('WARNING: Are you sure you want to permanently delete this report?')" class="inline-flex w-9 h-9 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500 dark:hover:text-white items-center justify-center transition tooltip" title="Purge Record"><i class="ph-bold ph-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL: MANUAL REPORT INTAKE -->
    <div id="modal-report" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm" onclick="document.getElementById('modal-report').classList.add('hidden')"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-white">Manual Case Intake</h3>
                    <p class="text-[10px] text-red-500 font-bold uppercase tracking-widest mt-1"><i class="ph-fill ph-lock-key"></i> Strictly Confidential</p>
                </div>
                <button onclick="document.getElementById('modal-report').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white"><i class="ph-bold ph-x text-xl"></i></button>
            </div>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="log_report" value="1">
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Type of Violation *</label>
                    <select name="incident_type" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                        <option value="Cyberbullying">Cyberbullying</option>
                        <option value="Physical Bullying / Harassment">Physical Bullying / Harassment</option>
                        <option value="Safe Spaces Act Violation (Catcalling)">Safe Spaces Act Violation (Catcalling, etc)</option>
                        <option value="Domestic / Child Abuse">Domestic / Child Abuse</option>
                        <option value="Discrimination">Discrimination (Gender/Race/Status)</option>
                        <option value="Other">Other Violation</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Detailed Narrative *</label>
                    <textarea name="description" rows="5" required placeholder="Describe what happened. Include dates, locations, and involved parties if known." class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none resize-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Complainant Contact (Leave blank for Anonymous)</label>
                    <input type="text" name="contact_info" placeholder="Email, Phone, or Name" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                </div>
                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition-colors flex items-center justify-center gap-2">
                        <i class="ph-bold ph-lock-key"></i> Log Secure Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script for Theme Toggle -->
    <script>
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