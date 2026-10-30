<?php
// manage_assets.php - SK Equipment & Asset Tracker
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$isAdmin = ($_SESSION['role'] === 'admin');
$barangay = isset($_SESSION['admin_barangay']) ? $_SESSION['admin_barangay'] : 'Municipal Level';
$message = '';

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

// HANDLE ACTIONS (Approve, Reject, Return, Add Asset)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_asset'])) {
    $name = trim($_POST['asset_name']);
    $cat = $_POST['category'];
    $qty = intval($_POST['quantity']);
    $cond = $_POST['condition'];
    $date = $_POST['date_acquired'];

    $stmt = $pdo->prepare("INSERT INTO sk_assets (barangay, asset_name, category, quantity_total, quantity_available, condition_status, date_acquired) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if($stmt->execute([$barangay, $name, $cat, $qty, $qty, $cond, $date])) {
        $message = "<div class='bg-emerald-50 text-emerald-800 border border-emerald-200 p-4 rounded-xl mb-6 font-bold flex items-center gap-2'><i class='ph-fill ph-check-circle text-xl'></i> Asset added to inventory.</div>";
    }
}

if (isset($_GET['action']) && isset($_GET['req_id'])) {
    $req_id = $_GET['req_id'];
    $action = $_GET['action'];

    // Fetch request
    $stmt = $pdo->prepare("SELECT * FROM sk_asset_requests WHERE id = ?");
    $stmt->execute([$req_id]);
    $req = $stmt->fetch();

    if ($req) {
        if ($action == 'approve' && $req['status'] == 'Pending') {
            // Check availability
            $asset = $pdo->query("SELECT quantity_available FROM sk_assets WHERE id = " . $req['asset_id'])->fetch();
            if ($asset['quantity_available'] > 0) {
                $pdo->prepare("UPDATE sk_assets SET quantity_available = quantity_available - 1 WHERE id = ?")->execute([$req['asset_id']]);
                $pdo->prepare("UPDATE sk_asset_requests SET status = 'Approved' WHERE id = ?")->execute([$req_id]);
                $message = "<div class='bg-emerald-50 text-emerald-800 border border-emerald-200 p-4 rounded-xl mb-6 font-bold flex items-center gap-2'><i class='ph-fill ph-check-circle text-xl'></i> Request Approved.</div>";
            } else {
                $message = "<div class='bg-red-50 text-red-800 border border-red-200 p-4 rounded-xl mb-6 font-bold flex items-center gap-2'><i class='ph-fill ph-warning-circle text-xl'></i> Not enough available items!</div>";
            }
        } elseif ($action == 'reject' && $req['status'] == 'Pending') {
            $pdo->prepare("UPDATE sk_asset_requests SET status = 'Rejected' WHERE id = ?")->execute([$req_id]);
            $message = "<div class='bg-amber-50 text-amber-800 border border-amber-200 p-4 rounded-xl mb-6 font-bold flex items-center gap-2'><i class='ph-fill ph-x-circle text-xl'></i> Request Rejected.</div>";
        } elseif ($action == 'return' && $req['status'] == 'Approved') {
            $pdo->prepare("UPDATE sk_assets SET quantity_available = quantity_available + 1 WHERE id = ?")->execute([$req['asset_id']]);
            $pdo->prepare("UPDATE sk_asset_requests SET status = 'Returned' WHERE id = ?")->execute([$req_id]);
            $message = "<div class='bg-blue-50 text-blue-800 border border-blue-200 p-4 rounded-xl mb-6 font-bold flex items-center gap-2'><i class='ph-fill ph-check-square-offset text-xl'></i> Asset Marked as Returned.</div>";
        }
    }
}

// FETCH DATA
$assetsQuery = $isAdmin ? "SELECT * FROM sk_assets ORDER BY created_at DESC" : "SELECT * FROM sk_assets WHERE barangay = ? ORDER BY created_at DESC";
$reqsQuery = $isAdmin ? "SELECT r.*, a.asset_name FROM sk_asset_requests r JOIN sk_assets a ON r.asset_id = a.id ORDER BY r.created_at DESC" : "SELECT r.*, a.asset_name FROM sk_asset_requests r JOIN sk_assets a ON r.asset_id = a.id WHERE r.barangay = ? ORDER BY r.created_at DESC";

$stmtAssets = $pdo->prepare($assetsQuery);
$stmtReqs = $pdo->prepare($reqsQuery);

if($isAdmin) { $stmtAssets->execute(); $stmtReqs->execute(); } 
else { $stmtAssets->execute([$barangay]); $stmtReqs->execute([$barangay]); }

$assets = $stmtAssets->fetchAll(PDO::FETCH_ASSOC);
$requests = $stmtReqs->fetchAll(PDO::FETCH_ASSOC);

// Stats
$totalItems = array_sum(array_column($assets, 'quantity_total'));
$availableItems = array_sum(array_column($assets, 'quantity_available'));
$borrowedItems = $totalItems - $availableItems;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Tracker | Admin Console</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, colors: { brand: { 50: '#f0fdf4', 100: '#dcfce7', 600: '#16a34a', 700: '#15803d', 900: '#14532d' } } } } }
    </script>
    <style>
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(24px); border: 1px solid rgba(226, 232, 240, 0.8); }
        .ambient-bg { position: fixed; inset: 0; z-index: -1; background: #f8fafc; overflow: hidden; pointer-events: none; }
        .blob { position: absolute; filter: blur(90px); opacity: 0.5; border-radius: 50%; mix-blend-multiply: true; }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="text-slate-800 antialiased flex h-screen overflow-hidden selection:bg-brand-900 selection:text-white">

    <div class="ambient-bg">
        <div class="blob bg-brand-200 w-[500px] h-[500px] -top-[10%] -left-[10%]"></div>
        <div class="blob bg-blue-200 w-[500px] h-[500px] bottom-[10%] -right-[10%]"></div>
    </div>

    <?php 
    if ($isAdmin) include 'sidebar_admin.php'; 
    else include 'sidebar_sk.php'; 
    ?>

    <main class="flex-1 overflow-y-auto p-8 ml-64 relative z-10">
        <div class="max-w-7xl mx-auto space-y-8">
            
            <!-- Header -->
            <div class="flex justify-between items-end pb-4 border-b border-slate-200">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Equipment & Asset Tracker</h1>
                    <p class="text-sm font-semibold text-slate-500 mt-1 uppercase tracking-widest">Inventory & Borrowing Management</p>
                </div>
            </div>

            <?= $message ?>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="glass-card p-6 rounded-3xl shadow-sm border-l-4 border-brand-500 flex items-center gap-4">
                    <div class="w-14 h-14 bg-brand-50 text-brand-600 rounded-xl flex items-center justify-center text-3xl"><i class="ph-fill ph-package"></i></div>
                    <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Items</p><p class="text-3xl font-black text-slate-800"><?= $totalItems ?></p></div>
                </div>
                <div class="glass-card p-6 rounded-3xl shadow-sm border-l-4 border-blue-500 flex items-center gap-4">
                    <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-3xl"><i class="ph-fill ph-hand-coins"></i></div>
                    <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Items Borrowed</p><p class="text-3xl font-black text-slate-800"><?= $borrowedItems ?></p></div>
                </div>
                <div class="glass-card p-6 rounded-3xl shadow-sm border-l-4 border-amber-500 flex items-center gap-4">
                    <div class="w-14 h-14 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center text-3xl"><i class="ph-fill ph-clipboard-text"></i></div>
                    <div><p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Pending Requests</p><p class="text-3xl font-black text-slate-800"><?= count(array_filter($requests, fn($r) => $r['status'] === 'Pending')) ?></p></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- INVENTORY TABLE -->
                <div class="lg:col-span-2 space-y-6">
                    <div class="glass-card rounded-[2rem] shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-white/50">
                            <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-2"><i class="ph-bold ph-archive-box text-brand-600"></i> Master Inventory Audit</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-200">
                                        <th class="py-3 px-6">Asset Name & Category</th>
                                        <th class="py-3 px-6 text-center">Availability</th>
                                        <th class="py-3 px-6">Condition</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100">
                                    <?php if(empty($assets)): ?>
                                        <tr><td colspan="3" class="py-10 text-center font-bold text-slate-400">No assets recorded.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($assets as $a): 
                                            $condClass = $a['condition_status'] == 'Good' ? 'text-emerald-600 bg-emerald-50' : ($a['condition_status'] == 'Broken' ? 'text-red-600 bg-red-50' : 'text-amber-600 bg-amber-50');
                                        ?>
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="py-4 px-6">
                                                <p class="font-extrabold text-slate-800"><?= htmlspecialchars($a['asset_name']) ?></p>
                                                <p class="text-[10px] text-slate-500 uppercase tracking-widest"><?= htmlspecialchars($a['category']) ?></p>
                                            </td>
                                            <td class="py-4 px-6 text-center">
                                                <span class="font-black text-slate-800 text-lg"><?= $a['quantity_available'] ?></span>
                                                <span class="text-xs text-slate-400 font-bold">/ <?= $a['quantity_total'] ?></span>
                                            </td>
                                            <td class="py-4 px-6">
                                                <span class="px-2.5 py-1 rounded-md text-[10px] font-extrabold uppercase tracking-widest <?= $condClass ?>"><?= $a['condition_status'] ?></span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- BORROW REQUESTS -->
                    <div class="glass-card rounded-[2rem] shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-white/50">
                            <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-2"><i class="ph-bold ph-hand-pointing text-brand-600"></i> Borrow Requests</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-200">
                                        <th class="py-3 px-6">Borrower & Purpose</th>
                                        <th class="py-3 px-6">Asset & Dates</th>
                                        <th class="py-3 px-6 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm divide-y divide-slate-100">
                                    <?php if(empty($requests)): ?>
                                        <tr><td colspan="3" class="py-10 text-center font-bold text-slate-400">No requests right now.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($requests as $r): ?>
                                        <tr class="hover:bg-slate-50/50">
                                            <td class="py-4 px-6">
                                                <p class="font-extrabold text-slate-800"><?= htmlspecialchars($r['youth_name']) ?></p>
                                                <p class="text-xs text-slate-500 italic max-w-[200px] truncate">"<?= htmlspecialchars($r['purpose']) ?>"</p>
                                            </td>
                                            <td class="py-4 px-6">
                                                <p class="font-bold text-brand-700"><?= htmlspecialchars($r['asset_name']) ?></p>
                                                <p class="text-[10px] text-slate-500 font-bold tracking-widest uppercase"><?= date('M d', strtotime($r['borrow_date'])) ?> to <?= date('M d', strtotime($r['return_date'])) ?></p>
                                            </td>
                                            <td class="py-4 px-6 text-right whitespace-nowrap">
                                                <?php if($r['status'] == 'Pending'): ?>
                                                    <a href="?action=approve&req_id=<?= $r['id'] ?>" class="inline-flex w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 items-center justify-center transition" title="Approve"><i class="ph-bold ph-check"></i></a>
                                                    <a href="?action=reject&req_id=<?= $r['id'] ?>" class="inline-flex w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 items-center justify-center transition" title="Reject"><i class="ph-bold ph-x"></i></a>
                                                <?php elseif($r['status'] == 'Approved'): ?>
                                                    <a href="?action=return&req_id=<?= $r['id'] ?>" class="text-xs font-bold bg-blue-50 text-blue-600 px-3 py-1.5 rounded-lg hover:bg-blue-100 transition border border-blue-200">Mark Returned</a>
                                                <?php else: ?>
                                                    <span class="text-[10px] font-extrabold uppercase tracking-widest px-2 py-1 rounded <?= $r['status'] == 'Returned' ? 'text-slate-500 bg-slate-100' : 'text-red-500 bg-red-50' ?>"><?= $r['status'] ?></span>
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

                <!-- ADD NEW ASSET FORM -->
                <div class="lg:col-span-1">
                    <div class="glass-card p-6 rounded-[2rem] shadow-sm sticky top-8">
                        <div class="flex items-center gap-3 mb-6 border-b border-slate-100 pb-4">
                            <div class="w-10 h-10 bg-brand-50 text-brand-600 rounded-xl flex items-center justify-center text-xl shadow-sm"><i class="ph-bold ph-plus"></i></div>
                            <h2 class="text-lg font-extrabold text-slate-800">Add New Asset</h2>
                        </div>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="add_asset" value="1">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Item Name</label>
                                <input type="text" name="asset_name" required placeholder="e.g. JBL Sound System" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-brand-600 outline-none">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Category</label>
                                <select name="category" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-brand-600 outline-none">
                                    <option value="Electronics/Sound">Electronics & Audio</option>
                                    <option value="Sports Equipment">Sports Equipment</option>
                                    <option value="Furniture/Tents">Furniture & Tents</option>
                                    <option value="Tools">Maintenance Tools</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Total Qty</label>
                                    <input type="number" min="1" name="quantity" required value="1" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-brand-600 outline-none">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Condition</label>
                                    <select name="condition" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-3 text-sm font-medium focus:ring-2 focus:ring-brand-600 outline-none">
                                        <option value="Good">Good</option>
                                        <option value="Needs Repair">Needs Repair</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Date Acquired</label>
                                <input type="date" name="date_acquired" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-brand-600 outline-none">
                            </div>
                            <button type="submit" class="w-full bg-slate-900 hover:bg-brand-700 text-white font-bold py-3.5 rounded-xl transition shadow-md mt-4">Save to Inventory</button>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </main>
</body>
</html>