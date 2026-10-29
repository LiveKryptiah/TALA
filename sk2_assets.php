<?php
// sk2_assets.php - SK OS 2.0 Equipment & Asset Tracker (Light/Dark Mode)
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

// Auto-create Tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk_assets` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(255) NOT NULL,
        `asset_name` VARCHAR(255) NOT NULL,
        `category` VARCHAR(100) NOT NULL,
        `quantity_total` INT NOT NULL DEFAULT 1,
        `quantity_available` INT NOT NULL DEFAULT 1,
        `condition_status` ENUM('Good', 'Needs Repair', 'Broken') DEFAULT 'Good',
        `date_acquired` DATE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk_asset_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `asset_id` INT NOT NULL,
        `barangay` VARCHAR(255) NOT NULL,
        `youth_id` INT NOT NULL,
        `youth_name` VARCHAR(255) NOT NULL,
        `purpose` TEXT,
        `borrow_date` DATE NOT NULL,
        `return_date` DATE NOT NULL,
        `status` ENUM('Pending', 'Approved', 'Returned', 'Rejected') DEFAULT 'Pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`asset_id`) REFERENCES `sk_assets`(`id`) ON DELETE CASCADE
    )");
} catch (PDOException $e) {}

// HANDLE ACTIONS (Add Asset, Approve, Reject, Return, Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_asset'])) {
    $name = trim($_POST['asset_name']);
    $cat = $_POST['category'];
    $qty = intval($_POST['quantity']);
    $cond = $_POST['condition'];
    $date = $_POST['date_acquired'];

    $stmt = $pdo->prepare("INSERT INTO sk_assets (barangay, asset_name, category, quantity_total, quantity_available, condition_status, date_acquired) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if($stmt->execute([$barangay, $name, $cat, $qty, $qty, $cond, $date])) {
        header("Location: sk2_assets.php?msg=added");
        exit();
    }
}

if (isset($_GET['action']) && isset($_GET['req_id'])) {
    $req_id = $_GET['req_id'];
    $action = $_GET['action'];

    $stmt = $pdo->prepare("SELECT * FROM sk_asset_requests WHERE id = ? AND barangay = ?");
    $stmt->execute([$req_id, $barangay]);
    $req = $stmt->fetch();

    if ($req) {
        if ($action == 'approve' && $req['status'] == 'Pending') {
            $asset = $pdo->query("SELECT quantity_available FROM sk_assets WHERE id = " . $req['asset_id'])->fetch();
            if ($asset['quantity_available'] > 0) {
                $pdo->prepare("UPDATE sk_assets SET quantity_available = quantity_available - 1 WHERE id = ?")->execute([$req['asset_id']]);
                $pdo->prepare("UPDATE sk_asset_requests SET status = 'Approved' WHERE id = ?")->execute([$req_id]);
                header("Location: sk2_assets.php?msg=approved"); exit();
            } else {
                header("Location: sk2_assets.php?msg=no_stock"); exit();
            }
        } elseif ($action == 'reject' && $req['status'] == 'Pending') {
            $pdo->prepare("UPDATE sk_asset_requests SET status = 'Rejected' WHERE id = ?")->execute([$req_id]);
            header("Location: sk2_assets.php?msg=rejected"); exit();
        } elseif ($action == 'return' && $req['status'] == 'Approved') {
            $pdo->prepare("UPDATE sk_assets SET quantity_available = quantity_available + 1 WHERE id = ?")->execute([$req['asset_id']]);
            $pdo->prepare("UPDATE sk_asset_requests SET status = 'Returned' WHERE id = ?")->execute([$req_id]);
            header("Location: sk2_assets.php?msg=returned"); exit();
        }
    }
}

if (isset($_GET['delete_asset'])) {
    $del_id = $_GET['delete_asset'];
    $pdo->prepare("DELETE FROM sk_assets WHERE id = ? AND barangay = ?")->execute([$del_id, $barangay]);
    header("Location: sk2_assets.php?msg=deleted"); exit();
}

// Display messages
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m == 'added') { $message = 'Asset added to inventory ledger.'; $msgType = 'success'; }
    if ($m == 'approved') { $message = 'Borrow request authorized.'; $msgType = 'success'; }
    if ($m == 'returned') { $message = 'Asset logged back into inventory.'; $msgType = 'info'; }
    if ($m == 'rejected') { $message = 'Request denied.'; $msgType = 'warning'; }
    if ($m == 'deleted') { $message = 'Asset purged from system.'; $msgType = 'danger'; }
    if ($m == 'no_stock') { $message = 'Insufficient inventory to approve request.'; $msgType = 'danger'; }
}

// FETCH DATA
$stmtAssets = $pdo->prepare("SELECT * FROM sk_assets WHERE barangay = ? ORDER BY created_at DESC");
$stmtAssets->execute([$barangay]);
$assets = $stmtAssets->fetchAll(PDO::FETCH_ASSOC);

$stmtReqs = $pdo->prepare("SELECT r.*, a.asset_name FROM sk_asset_requests r JOIN sk_assets a ON r.asset_id = a.id WHERE r.barangay = ? ORDER BY r.created_at DESC");
$stmtReqs->execute([$barangay]);
$requests = $stmtReqs->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats
$totalItems = 0; $availableItems = 0;
foreach($assets as $a) { $totalItems += $a['quantity_total']; $availableItems += $a['quantity_available']; }
$borrowedItems = $totalItems - $availableItems;
$pendingReqs = count(array_filter($requests, fn($r) => $r['status'] === 'Pending'));
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | Asset Tracker</title>
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
    </style>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex h-screen overflow-hidden selection:bg-blue-500 selection:text-white transition-colors duration-300">

<!-- Sidebar Include -->
<?php include 'sk2_sidebar.php'; ?>

<!-- FIX 2: CLEANED UP MAIN CONTAINER WITH DARK MODE BACKGROUND -->
<main class="ml-64 flex-1 h-screen overflow-y-auto custom-scrollbar relative bg-slate-50 dark:bg-zinc-950 transition-colors duration-300">

    <!-- The rest of your specific page content (like your <div class="relative z-10 p-8...">) starts right below this! -->
    <!-- MAIN WORKSPACE -->
    <main class="flex-1 overflow-y-auto custom-scrollbar relative">
        <div class="relative z-10 p-8 lg:p-10 max-w-[1600px] mx-auto">
            
            <!-- Header & Theme Toggle -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1">
                        Equipment & Asset Tracker
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium">Inventory and borrowing management for <?= htmlspecialchars($barangay) ?>.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <!-- Theme Toggle Button -->
                    <button id="themeToggle" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <i id="themeIcon" class="ph-bold ph-moon text-lg"></i>
                    </button>

                    <button onclick="document.getElementById('newAssetModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 transition-colors">
                        <i class="ph-bold ph-plus"></i> Initialize Asset
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

            <!-- Heads Up Display (Stats) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 rounded-2xl transition-colors flex items-center gap-5">
                    <div class="w-14 h-14 bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 border border-blue-100 dark:border-blue-500/20 rounded-xl flex items-center justify-center text-3xl"><i class="ph-fill ph-package"></i></div>
                    <div><p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500 mb-1">Total Inventory</p><h3 class="text-3xl font-black text-slate-900 dark:text-white"><?= $totalItems ?></h3></div>
                </div>
                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 rounded-2xl transition-colors flex items-center gap-5">
                    <div class="w-14 h-14 bg-yellow-50 text-yellow-600 dark:bg-yellow-500/10 dark:text-yellow-400 border border-yellow-100 dark:border-yellow-500/20 rounded-xl flex items-center justify-center text-3xl"><i class="ph-fill ph-hand-coins"></i></div>
                    <div><p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500 mb-1">Currently Borrowed</p><h3 class="text-3xl font-black text-slate-900 dark:text-white"><?= $borrowedItems ?></h3></div>
                </div>
                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 rounded-2xl transition-colors flex items-center gap-5">
                    <div class="w-14 h-14 bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400 border border-red-100 dark:border-red-500/20 rounded-xl flex items-center justify-center text-3xl"><i class="ph-fill ph-clipboard-text"></i></div>
                    <div><p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500 mb-1">Pending Requests</p><h3 class="text-3xl font-black text-slate-900 dark:text-white"><?= $pendingReqs ?></h3></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- INVENTORY TABLE -->
                <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 relative transition-colors shadow-sm dark:shadow-none">
                    <div class="p-6 border-b border-slate-200 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-900/50">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <i class="ph-bold ph-archive-box text-blue-500"></i> Master Database
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[500px]">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800">
                                    <th class="py-4 px-6">Asset Specification</th>
                                    <th class="py-4 px-6 text-center">Availability</th>
                                    <th class="py-4 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50">
                                <?php if(empty($assets)): ?>
                                    <tr><td colspan="3" class="py-12 text-center text-slate-500 dark:text-zinc-500">No assets recorded.</td></tr>
                                <?php else: ?>
                                    <?php foreach($assets as $a): 
                                        $condColor = $a['condition_status'] == 'Good' ? 'text-green-700 bg-green-50 border-green-200 dark:text-green-400 dark:bg-green-500/10 dark:border-green-500/20' : ($a['condition_status'] == 'Broken' ? 'text-red-700 bg-red-50 border-red-200 dark:text-red-400 dark:bg-red-500/10 dark:border-red-500/20' : 'text-yellow-700 bg-yellow-50 border-yellow-200 dark:text-yellow-400 dark:bg-yellow-500/10 dark:border-yellow-500/20');
                                    ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors group">
                                        <td class="py-4 px-6">
                                            <p class="text-slate-800 dark:text-zinc-200 font-bold"><?= htmlspecialchars($a['asset_name']) ?></p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <p class="text-[10px] text-slate-400 dark:text-zinc-500 uppercase tracking-widest"><?= htmlspecialchars($a['category']) ?></p>
                                                <span class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase tracking-wider border <?= $condColor ?>"><?= $a['condition_status'] ?></span>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            <span class="font-black text-slate-900 dark:text-white text-lg"><?= $a['quantity_available'] ?></span>
                                            <span class="text-xs text-slate-500 dark:text-zinc-500 font-bold">/ <?= $a['quantity_total'] ?></span>
                                        </td>
                                        <td class="py-4 px-6 text-right">
                                            <a href="?delete_asset=<?= $a['id'] ?>" onclick="return confirm('Purge asset from database?')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500 dark:hover:text-white transition-all opacity-0 group-hover:opacity-100">
                                                <i class="ph-bold ph-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- BORROW REQUESTS TABLE -->
                <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 relative transition-colors shadow-sm dark:shadow-none">
                    <div class="p-6 border-b border-slate-200 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-900/50">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <i class="ph-bold ph-hand-pointing text-blue-500"></i> Active Requests
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[500px]">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800">
                                    <th class="py-4 px-6">Requester</th>
                                    <th class="py-4 px-6">Target Asset</th>
                                    <th class="py-4 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50">
                                <?php if(empty($requests)): ?>
                                    <tr><td colspan="3" class="py-12 text-center text-slate-500 dark:text-zinc-500">No requests in queue.</td></tr>
                                <?php else: ?>
                                    <?php foreach($requests as $r): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors">
                                        <td class="py-4 px-6">
                                            <p class="text-slate-800 dark:text-zinc-200 font-bold"><?= htmlspecialchars($r['youth_name']) ?></p>
                                            <p class="text-[10px] text-slate-500 dark:text-zinc-500 italic max-w-[150px] truncate mt-1">"<?= htmlspecialchars($r['purpose']) ?>"</p>
                                        </td>
                                        <td class="py-4 px-6">
                                            <p class="font-bold text-blue-600 dark:text-blue-400"><?= htmlspecialchars($r['asset_name']) ?></p>
                                            <p class="text-[10px] text-slate-500 dark:text-zinc-500 font-bold tracking-widest uppercase mt-1">Due: <?= date('M d', strtotime($r['return_date'])) ?></p>
                                        </td>
                                        <td class="py-4 px-6 text-right whitespace-nowrap">
                                            <?php if($r['status'] == 'Pending'): ?>
                                                <a href="?action=approve&req_id=<?= $r['id'] ?>" class="inline-flex w-8 h-8 rounded-lg bg-green-50 text-green-600 hover:bg-green-600 hover:text-white dark:bg-green-500/20 dark:text-green-400 dark:hover:bg-green-500 dark:hover:text-zinc-900 items-center justify-center transition" title="Authorize"><i class="ph-bold ph-check"></i></a>
                                                <a href="?action=reject&req_id=<?= $r['id'] ?>" class="inline-flex w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white dark:bg-red-500/20 dark:text-red-400 dark:hover:bg-red-500 dark:hover:text-white items-center justify-center transition ml-1" title="Deny"><i class="ph-bold ph-x"></i></a>
                                            <?php elseif($r['status'] == 'Approved'): ?>
                                                <a href="?action=return&req_id=<?= $r['id'] ?>" class="text-[10px] font-bold uppercase tracking-widest bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white dark:bg-blue-500/20 dark:text-blue-400 dark:hover:bg-blue-500 dark:hover:text-white px-3 py-2 rounded-lg transition border border-blue-200 dark:border-blue-500/30">Verify Return</a>
                                            <?php else: ?>
                                                <span class="text-[9px] font-extrabold uppercase tracking-widest px-2.5 py-1 rounded border <?= $r['status'] == 'Returned' ? 'text-slate-500 bg-slate-100 border-slate-200 dark:text-zinc-400 dark:bg-zinc-800 dark:border-zinc-700' : 'text-red-600 bg-red-50 border-red-200 dark:text-red-400 dark:bg-red-500/10 dark:border-red-500/20' ?>"><?= $r['status'] ?></span>
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

    <!-- NEW ASSET MODAL -->
    <div id="newAssetModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm" onclick="document.getElementById('newAssetModal').classList.add('hidden')"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Initialize Asset</h3>
                <button onclick="document.getElementById('newAssetModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 dark:text-zinc-500 dark:hover:text-white"><i class="ph-bold ph-x text-xl"></i></button>
            </div>

            <form method="POST" class="space-y-5">
                <input type="hidden" name="add_asset" value="1">
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Item Name *</label>
                    <input type="text" name="asset_name" required placeholder="e.g. JBL Sound System" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none transition-colors">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Category</label>
                    <select name="category" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none appearance-none">
                        <option value="Electronics/Sound">Electronics & Audio</option>
                        <option value="Sports Equipment">Sports Equipment</option>
                        <option value="Furniture/Tents">Furniture & Tents</option>
                        <option value="Tools">Maintenance Tools</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Total Quantity *</label>
                        <input type="number" min="1" name="quantity" required value="1" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Condition</label>
                        <select name="condition" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none appearance-none">
                            <option value="Good">Good</option>
                            <option value="Needs Repair">Needs Repair</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Date Acquired *</label>
                    <input type="date" name="date_acquired" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-zinc-300 focus:border-blue-500 outline-none">
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition-colors">
                        Commit to Database
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script to handle Light/Dark Toggle -->
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