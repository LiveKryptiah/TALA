<?php
// sk2_safety.php - SK OS 2.0 Safety, Health & Crisis Support
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] !== 'sub-admin' && $_SESSION['role'] !== 'sk_admin')) {
    header("Location: sk2_login.php"); exit();
}

$barangay = $_SESSION['admin_barangay'] ?? 'Municipal Level';
$message = ''; $msgType = '';
$activeTab = $_GET['tab'] ?? 'mental_health';

// Auto-create Tables for Crisis Management
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_safety_hotlines` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `type` ENUM('Emergency', 'Mental Health') NOT NULL,
        `service_name` VARCHAR(255) NOT NULL,
        `contact_number` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_safety_evacuations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `center_name` VARCHAR(255) NOT NULL,
        `capacity` INT NOT NULL,
        `status` ENUM('Standby', 'Active', 'Full') DEFAULT 'Standby',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_safety_resources` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT NOT NULL,
        `link` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// HANDLE SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_hotline'])) {
        $stmt = $pdo->prepare("INSERT INTO sk2_safety_hotlines (barangay, type, service_name, contact_number) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$barangay, $_POST['type'], $_POST['service_name'], $_POST['contact_number']])) {
            $tab = $_POST['type'] == 'Emergency' ? 'disaster' : 'mental_health';
            header("Location: sk2_safety.php?tab=$tab&msg=hotline_added"); exit();
        }
    }
    
    if (isset($_POST['add_evacuation'])) {
        $stmt = $pdo->prepare("INSERT INTO sk2_safety_evacuations (barangay, center_name, capacity, status) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$barangay, $_POST['center_name'], $_POST['capacity'], $_POST['status']])) {
            header("Location: sk2_safety.php?tab=disaster&msg=evac_added"); exit();
        }
    }

    if (isset($_POST['add_resource'])) {
        $stmt = $pdo->prepare("INSERT INTO sk2_safety_resources (barangay, title, description, link) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$barangay, $_POST['title'], $_POST['description'], $_POST['link']])) {
            header("Location: sk2_safety.php?tab=mental_health&msg=resource_added"); exit();
        }
    }
}

// HANDLE ACTIONS (Deletions & Status Updates)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];
    $tab = $_GET['tab'] ?? 'mental_health';

    if ($action == 'delete_hotline') {
        $pdo->prepare("DELETE FROM sk2_safety_hotlines WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_safety.php?tab=$tab&msg=deleted"); exit();
    } elseif ($action == 'delete_evac') {
        $pdo->prepare("DELETE FROM sk2_safety_evacuations WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_safety.php?tab=disaster&msg=deleted"); exit();
    } elseif ($action == 'delete_resource') {
        $pdo->prepare("DELETE FROM sk2_safety_resources WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_safety.php?tab=mental_health&msg=deleted"); exit();
    } elseif ($action == 'update_evac') {
        $status = $_GET['status'];
        $pdo->prepare("UPDATE sk2_safety_evacuations SET status = ? WHERE id = ? AND barangay = ?")->execute([$status, $id, $barangay]);
        header("Location: sk2_safety.php?tab=disaster&msg=updated"); exit();
    }
}

// Set Messages
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if (in_array($m, ['hotline_added', 'evac_added', 'resource_added'])) { $message = 'Database successfully updated.'; $msgType = 'success'; }
    if ($m == 'updated') { $message = 'Status successfully updated.'; $msgType = 'success'; }
    if ($m == 'deleted') { $message = 'Record purged.'; $msgType = 'danger'; }
}

// FETCH DATA
$hotlines_mh = $pdo->prepare("SELECT * FROM sk2_safety_hotlines WHERE barangay = ? AND type = 'Mental Health' ORDER BY created_at DESC");
$hotlines_mh->execute([$barangay]); $hotlines_mh = $hotlines_mh->fetchAll(PDO::FETCH_ASSOC);

$hotlines_em = $pdo->prepare("SELECT * FROM sk2_safety_hotlines WHERE barangay = ? AND type = 'Emergency' ORDER BY created_at DESC");
$hotlines_em->execute([$barangay]); $hotlines_em = $hotlines_em->fetchAll(PDO::FETCH_ASSOC);

$evacuations = $pdo->prepare("SELECT * FROM sk2_safety_evacuations WHERE barangay = ? ORDER BY status ASC, created_at DESC");
$evacuations->execute([$barangay]); $evacuations = $evacuations->fetchAll(PDO::FETCH_ASSOC);

$resources = $pdo->prepare("SELECT * FROM sk2_safety_resources WHERE barangay = ? ORDER BY created_at DESC");
$resources->execute([$barangay]); $resources = $resources->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | Safety & Crisis Support</title>
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
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- ENABLE DARK MODE IN TAILWIND CONFIG -->
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

    <!-- FIXED: Single Main Container -->
    <main class="ml-64 flex-1 h-screen overflow-y-auto custom-scrollbar relative z-10">
        <div class="p-8 lg:p-10 max-w-[1600px] mx-auto">
            
            <!-- Header Area -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4 transition-colors">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1 transition-colors">
                        Safety & Crisis Support
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium transition-colors">Mental health and disaster emergency management for <?= htmlspecialchars($barangay) ?>.</p>
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
                <button onclick="switchTab('mental_health')" id="tabBtn-mental_health" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'mental_health' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-brain"></i> Mental Health & Wellness
                </button>
                <button onclick="switchTab('disaster')" id="tabBtn-disaster" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'disaster' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-warning"></i> Disaster & Emergency
                </button>
            </div>

            <!-- TAB 1: MENTAL HEALTH & WELLNESS -->
            <div id="tab-mental_health" class="tab-content <?= $activeTab == 'mental_health' ? 'active' : '' ?>">
                
                <!-- Counselors & Hotlines -->
                <div class="mb-10">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-slate-800 dark:text-white transition-colors"><i class="ph-fill ph-phone-call text-blue-500 dark:text-blue-400 mr-2"></i> Confidential Hotlines</h3>
                        <button onclick="document.getElementById('modal-hotline').classList.remove('hidden'); document.getElementById('hotline-type').value='Mental Health';" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                            <i class="ph-bold ph-plus"></i> Add Hotline
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <?php if(empty($hotlines_mh)): ?>
                            <p class="text-sm text-slate-500 dark:text-zinc-500 col-span-full transition-colors">No mental health hotlines registered.</p>
                        <?php else: foreach($hotlines_mh as $mh): ?>
                            <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-5 relative group transition-colors shadow-sm flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-bold text-slate-900 dark:text-white transition-colors"><?= htmlspecialchars($mh['service_name']) ?></p>
                                    <p class="text-lg font-black text-blue-600 dark:text-blue-400 mt-1 transition-colors"><?= htmlspecialchars($mh['contact_number']) ?></p>
                                </div>
                                <a href="?action=delete_hotline&id=<?= $mh['id'] ?>&tab=mental_health" onclick="return confirm('Remove hotline?')" class="text-slate-300 hover:text-red-500 dark:text-zinc-600 dark:hover:text-red-400 transition-colors"><i class="ph-bold ph-trash text-xl"></i></a>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- Self-Care Guides & Resources -->
                <div>
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-slate-800 dark:text-white transition-colors"><i class="ph-fill ph-book-open-text text-green-500 dark:text-green-400 mr-2"></i> Guides & Resources</h3>
                        <button onclick="document.getElementById('modal-resource').classList.remove('hidden')" class="bg-slate-800 hover:bg-slate-900 dark:bg-white dark:hover:bg-zinc-200 text-white dark:text-zinc-900 px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                            <i class="ph-bold ph-plus"></i> Add Resource
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php if(empty($resources)): ?>
                            <p class="text-sm text-slate-500 dark:text-zinc-500 col-span-full transition-colors">No wellness resources available.</p>
                        <?php else: foreach($resources as $res): ?>
                            <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 relative group transition-colors shadow-sm">
                                <a href="?action=delete_resource&id=<?= $res['id'] ?>&tab=mental_health" onclick="return confirm('Remove resource?')" class="absolute top-4 right-4 text-slate-300 hover:text-red-500 dark:text-zinc-600 dark:hover:text-red-400 transition-colors"><i class="ph-bold ph-trash text-lg"></i></a>
                                <h4 class="text-lg font-bold text-slate-900 dark:text-white mb-2 transition-colors"><?= htmlspecialchars($res['title']) ?></h4>
                                <p class="text-sm text-slate-600 dark:text-zinc-400 mb-4 transition-colors"><?= htmlspecialchars($res['description']) ?></p>
                                <?php if(!empty($res['link'])): ?>
                                    <a href="<?= htmlspecialchars($res['link']) ?>" target="_blank" class="text-xs font-bold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 flex items-center gap-1 transition-colors"><i class="ph-bold ph-link"></i> External Link</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 2: DISASTER & EMERGENCY -->
            <div id="tab-disaster" class="tab-content <?= $activeTab == 'disaster' ? 'active' : '' ?>">
                
                <!-- Rapid Alert Hotlines -->
                <div class="mb-10">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-slate-800 dark:text-white transition-colors"><i class="ph-fill ph-siren text-red-500 dark:text-red-400 mr-2"></i> Emergency Rapid Contacts</h3>
                        <button onclick="document.getElementById('modal-hotline').classList.remove('hidden'); document.getElementById('hotline-type').value='Emergency';" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                            <i class="ph-bold ph-plus"></i> Add Emergency Number
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php if(empty($hotlines_em)): ?>
                            <p class="text-sm text-slate-500 dark:text-zinc-500 col-span-full transition-colors">No emergency hotlines registered.</p>
                        <?php else: foreach($hotlines_em as $em): ?>
                            <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 rounded-2xl p-5 relative group transition-colors shadow-sm flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-bold text-red-800 dark:text-red-400 transition-colors"><?= htmlspecialchars($em['service_name']) ?></p>
                                    <p class="text-2xl font-black text-red-600 dark:text-red-500 mt-1 transition-colors"><?= htmlspecialchars($em['contact_number']) ?></p>
                                </div>
                                <a href="?action=delete_hotline&id=<?= $em['id'] ?>&tab=disaster" onclick="return confirm('Remove emergency hotline?')" class="text-red-300 hover:text-red-700 dark:text-red-500/50 dark:hover:text-red-400 transition-colors"><i class="ph-bold ph-trash text-xl"></i></a>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- Evacuation Centers -->
                <div>
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold text-slate-800 dark:text-white transition-colors"><i class="ph-fill ph-house-line text-blue-500 dark:text-blue-400 mr-2"></i> Evacuation Centers</h3>
                        <button onclick="document.getElementById('modal-evacuation').classList.remove('hidden')" class="bg-slate-800 hover:bg-slate-900 dark:bg-white dark:hover:bg-zinc-200 text-white dark:text-zinc-900 px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                            <i class="ph-bold ph-plus"></i> Designate Center
                        </button>
                    </div>
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 shadow-sm transition-colors">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800 transition-colors">
                                    <th class="py-4 px-6">Location Name</th>
                                    <th class="py-4 px-6 text-center">Max Capacity</th>
                                    <th class="py-4 px-6 text-center">Current Status</th>
                                    <th class="py-4 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50 transition-colors">
                                <?php if(empty($evacuations)): ?>
                                    <tr><td colspan="4" class="py-10 text-center text-slate-500 dark:text-zinc-500 transition-colors">No evacuation centers established.</td></tr>
                                <?php else: foreach($evacuations as $ev): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors group">
                                    <td class="py-4 px-6 font-bold text-slate-800 dark:text-zinc-200 transition-colors"><?= htmlspecialchars($ev['center_name']) ?></td>
                                    <td class="py-4 px-6 text-center text-slate-600 dark:text-zinc-400 font-bold transition-colors"><?= $ev['capacity'] ?> individuals</td>
                                    <td class="py-4 px-6 text-center">
                                        <?php if($ev['status'] == 'Active'): ?>
                                            <span class="px-2 py-1 text-[10px] font-extrabold uppercase rounded bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400 animate-pulse transition-colors">Active</span>
                                        <?php elseif($ev['status'] == 'Full'): ?>
                                            <span class="px-2 py-1 text-[10px] font-extrabold uppercase rounded bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400 transition-colors">Full</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-[10px] font-extrabold uppercase rounded bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-400 transition-colors">Standby</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 text-right whitespace-nowrap">
                                        <select onchange="window.location.href='?action=update_evac&id=<?= $ev['id'] ?>&status='+this.value" class="bg-slate-100 dark:bg-zinc-800 border-none rounded-lg text-xs font-bold px-2 py-1 mr-2 outline-none cursor-pointer text-slate-600 dark:text-zinc-300 transition-colors">
                                            <option value="Standby" <?= $ev['status']=='Standby'?'selected':'' ?>>Standby</option>
                                            <option value="Active" <?= $ev['status']=='Active'?'selected':'' ?>>Set Active</option>
                                            <option value="Full" <?= $ev['status']=='Full'?'selected':'' ?>>Mark Full</option>
                                        </select>
                                        <a href="?action=delete_evac&id=<?= $ev['id'] ?>" onclick="return confirm('Remove center?')" class="text-red-400 hover:text-red-600 transition-colors"><i class="ph-bold ph-trash text-lg align-middle"></i></a>
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

    <!-- MODAL: ADD HOTLINE -->
    <div id="modal-hotline" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm transition-colors" onclick="document.getElementById('modal-hotline').classList.add('hidden')"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xl font-black text-slate-900 dark:text-white transition-colors">Register Hotline</h3>
                <button onclick="document.getElementById('modal-hotline').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white transition-colors"><i class="ph-bold ph-x text-xl"></i></button>
            </div>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="add_hotline" value="1">
                <input type="hidden" name="type" id="hotline-type" value="Mental Health">
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Service Name *</label>
                    <input type="text" name="service_name" required placeholder="e.g. Brgy Rescue Unit" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Contact Number *</label>
                    <input type="text" name="contact_number" required placeholder="e.g. 0912 345 6789" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none transition-colors">
                </div>
                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 transition-colors">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-black py-4 rounded-xl transition-colors shadow-md">Save Hotline</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ADD EVACUATION CENTER -->
    <div id="modal-evacuation" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm transition-colors" onclick="document.getElementById('modal-evacuation').classList.add('hidden')"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xl font-black text-slate-900 dark:text-white transition-colors">Designate Evacuation</h3>
                <button onclick="document.getElementById('modal-evacuation').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white transition-colors"><i class="ph-bold ph-x text-xl"></i></button>
            </div>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="add_evacuation" value="1">
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Location Name *</label>
                    <input type="text" name="center_name" required placeholder="e.g. Tumauini National High School" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none transition-colors">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Capacity (Persons) *</label>
                        <input type="number" name="capacity" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Status</label>
                        <select name="status" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none transition-colors">
                            <option value="Standby">Standby</option>
                            <option value="Active">Active</option>
                        </select>
                    </div>
                </div>
                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 transition-colors">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-black py-4 rounded-xl transition-colors shadow-md">Establish Center</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ADD WELLNESS RESOURCE -->
    <div id="modal-resource" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm transition-colors" onclick="document.getElementById('modal-resource').classList.add('hidden')"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xl font-black text-slate-900 dark:text-white transition-colors">Publish Resource</h3>
                <button onclick="document.getElementById('modal-resource').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white transition-colors"><i class="ph-bold ph-x text-xl"></i></button>
            </div>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="add_resource" value="1">
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Resource Title *</label>
                    <input type="text" name="title" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Description / Guide Content *</label>
                    <textarea name="description" rows="4" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none resize-none transition-colors"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">External Link (Optional)</label>
                    <input type="url" name="link" placeholder="https://..." class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none transition-colors">
                </div>
                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 transition-colors">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-black py-4 rounded-xl transition-colors shadow-md">Publish Resource</button>
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

        // Local Theme Logic (in case the sidebar toggle isn't available)
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