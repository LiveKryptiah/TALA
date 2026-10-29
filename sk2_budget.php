<?php
// sk2_budget.php - SK OS 2.0 REAL Financial Analytics (Light/Dark Mode)
session_start();
require 'db_connect.php';

// Security Check: Only SK Admins
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] !== 'sub-admin' && $_SESSION['role'] !== 'sk_admin')) {
    header("Location: sk2_login.php");
    exit();
}

$barangay = $_SESSION['admin_barangay'] ?? 'Municipal';
$message = '';
$msgType = '';

// Auto-create SK 2.0 Transactions Table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_transactions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `description` VARCHAR(255) NOT NULL,
        `category` VARCHAR(100) NOT NULL,
        `type` ENUM('Allocation', 'Expense') DEFAULT 'Expense',
        `amount` DECIMAL(15,2) NOT NULL,
        `transaction_date` DATE NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// HANDLE DELETE
if (isset($_GET['delete'])) {
    $del_id = $_GET['delete'];
    $pdo->prepare("DELETE FROM sk2_transactions WHERE id = ? AND barangay = ?")->execute([$del_id, $barangay]);
    header("Location: sk2_budget.php?msg=deleted");
    exit();
}

// HANDLE FORM SUBMISSION (LOG TRANSACTION)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['log_transaction'])) {
    $desc = trim($_POST['description']);
    $cat = $_POST['category'];
    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $date = $_POST['transaction_date'];

    $stmt = $pdo->prepare("INSERT INTO sk2_transactions (barangay, description, category, type, amount, transaction_date) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$barangay, $desc, $cat, $type, $amount, $date])) {
        header("Location: sk2_budget.php?msg=success");
        exit();
    }
}

// Display messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'success') { $message = 'Transaction successfully recorded in the ledger.'; $msgType = 'success'; }
    if ($_GET['msg'] == 'deleted') { $message = 'Transaction purged from the system.'; $msgType = 'danger'; }
}

// Fetch Real Transactions (Filtered by logged-in SK's Barangay)
$stmt = $pdo->prepare("SELECT * FROM sk2_transactions WHERE barangay = ? ORDER BY transaction_date DESC, id DESC");
$stmt->execute([$barangay]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Financial Readouts dynamically based on this barangay's data
$total_allocation = 0;
$total_expense = 0;
foreach($transactions as $tx) {
    if ($tx['type'] == 'Allocation') $total_allocation += $tx['amount'];
    if ($tx['type'] == 'Expense') $total_expense += $tx['amount'];
}
$remaining_balance = $total_allocation - $total_expense;
$burn_rate = $total_allocation > 0 ? round(($total_expense / $total_allocation) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | Financial Analytics</title>
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
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
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

    <!-- The rest of your specific page content starts right below this! -->
    <!-- MAIN WORKSPACE -->
    <main class="flex-1 overflow-y-auto custom-scrollbar relative">
        <div class="relative z-10 p-8 lg:p-10 max-w-[1600px] mx-auto">
            
            <!-- Header & Theme Toggle -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1">
                        Financial Analytics
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium">Tracking the 10% Sangguniang Kabataan Fund for <?= htmlspecialchars($barangay) ?>.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <!-- Theme Toggle Button -->
                    <button id="themeToggle" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <i id="themeIcon" class="ph-bold ph-moon text-lg"></i>
                    </button>

                    <button onclick="document.getElementById('newTransactionModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-bold text-sm flex items-center gap-2 transition-colors">
                        <i class="ph-bold ph-receipt"></i> Log Transaction
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="mb-8 p-4 rounded-xl border <?= $msgType == 'success' ? 'bg-green-50 border-green-200 text-green-700 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50 border-red-200 text-red-700 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400' ?> flex items-center gap-3 font-bold text-sm transition-colors">
                    <i class="ph-fill <?= $msgType == 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-xl"></i> <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- Heads Up Display (Financial Stats) -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 rounded-2xl md:col-span-2 relative overflow-hidden transition-colors">
                    <div class="absolute top-0 right-0 p-6 opacity-10 text-blue-500 dark:text-blue-400">
                        <i class="ph-fill ph-vault text-6xl"></i>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500 mb-2">Liquid Balance Available</p>
                    <h3 class="text-4xl sm:text-5xl font-black text-slate-900 dark:text-white mb-4">₱<?= number_format($remaining_balance, 2) ?></h3>
                    
                    <!-- Burn Rate Meter -->
                    <div class="w-full">
                        <div class="flex justify-between text-[10px] font-bold text-slate-500 dark:text-zinc-400 mb-1.5 uppercase tracking-widest">
                            <span>Budget Burn Rate</span>
                            <span class="<?= $burn_rate > 80 ? 'text-red-500 dark:text-red-400' : 'text-blue-600 dark:text-blue-400' ?>"><?= $burn_rate ?>% Used</span>
                        </div>
                        <div class="w-full h-2 bg-slate-100 dark:bg-zinc-800 rounded-full overflow-hidden">
                            <div class="h-full <?= $burn_rate > 80 ? 'bg-red-500' : 'bg-blue-500' ?> transition-all duration-1000" style="width: <?= $burn_rate ?>%"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 rounded-2xl flex flex-col justify-center transition-colors">
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500 mb-2">Total SK Allocation</p>
                    <h3 class="text-2xl font-black text-slate-700 dark:text-zinc-300">₱<?= number_format($total_allocation, 2) ?></h3>
                    <p class="text-[10px] text-green-600 dark:text-green-500 font-bold mt-2"><i class="ph-bold ph-trend-up"></i> Lifetime Total</p>
                </div>

                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-6 rounded-2xl flex flex-col justify-center transition-colors">
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500 mb-2">Total Disbursed</p>
                    <h3 class="text-2xl font-black text-red-600 dark:text-red-400">₱<?= number_format($total_expense, 2) ?></h3>
                    <p class="text-[10px] text-red-500 dark:text-red-500 font-bold mt-2"><i class="ph-bold ph-trend-down"></i> Active Expenses</p>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 relative transition-colors shadow-sm dark:shadow-none">
                <div class="p-6 border-b border-slate-200 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-900/50">
                    <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                        <i class="ph-bold ph-list-dashes text-blue-500"></i> Transaction Master Ledger
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[800px]">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800">
                                <th class="py-4 px-6">Date</th>
                                <th class="py-4 px-6">Description</th>
                                <th class="py-4 px-6">Category</th>
                                <th class="py-4 px-6 text-right">Amount</th>
                                <th class="py-4 px-6 text-center w-16">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50">
                            <?php if(empty($transactions)): ?>
                                <tr>
                                    <td colspan="5" class="py-16 text-center">
                                        <i class="ph-fill ph-wallet text-5xl text-slate-300 dark:text-zinc-700 mb-3 inline-block"></i>
                                        <p class="text-slate-500 dark:text-zinc-400 font-bold">No transactions recorded yet.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($transactions as $tx): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors group">
                                    <td class="py-4 px-6 text-slate-500 dark:text-zinc-400 whitespace-nowrap">
                                        <?= date('M d, Y', strtotime($tx['transaction_date'])) ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <p class="text-slate-800 dark:text-zinc-200 font-bold"><?= htmlspecialchars($tx['description']) ?></p>
                                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 uppercase tracking-widest mt-1">ID: TXN-<?= str_pad($tx['id'], 5, '0', STR_PAD_LEFT) ?></p>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="px-2.5 py-1 rounded-md text-[10px] font-extrabold uppercase tracking-widest border border-slate-200 bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:border-zinc-700 dark:text-zinc-300">
                                            <?= htmlspecialchars($tx['category']) ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-right whitespace-nowrap">
                                        <?php if($tx['type'] == 'Allocation'): ?>
                                            <span class="text-green-600 dark:text-green-400 font-black">+ ₱<?= number_format($tx['amount'], 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-slate-900 dark:text-white font-black">- ₱<?= number_format($tx['amount'], 2) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <a href="?delete=<?= $tx['id'] ?>" onclick="return confirm('Permanently delete this transaction? This will affect your budget readouts.')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-500 hover:text-white dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500 dark:hover:text-white transition-all opacity-0 group-hover:opacity-100">
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

        </div>
    </main>

    <!-- NEW TRANSACTION MODAL -->
    <div id="newTransactionModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm" onclick="document.getElementById('newTransactionModal').classList.add('hidden')"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Log Transaction</h3>
                <button onclick="document.getElementById('newTransactionModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 dark:text-zinc-500 dark:hover:text-white"><i class="ph-bold ph-x text-xl"></i></button>
            </div>

            <form method="POST" class="space-y-5">
                <input type="hidden" name="log_transaction" value="1">
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Description / Purpose *</label>
                    <input type="text" name="description" required placeholder="e.g. Purchase of basketballs" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none transition-colors">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Type</label>
                        <select name="type" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none appearance-none">
                            <option value="Expense">Expense (Deduction)</option>
                            <option value="Allocation">Allocation (Addition)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Date *</label>
                        <input type="date" name="transaction_date" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-zinc-300 focus:border-blue-500 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Amount (₱) *</label>
                    <input type="number" step="0.01" min="1" name="amount" required placeholder="0.00" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-2xl font-black text-blue-600 dark:text-blue-500 focus:border-blue-500 outline-none transition-colors">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Budget Category</label>
                    <select name="category" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none appearance-none">
                        <option value="Internal Revenue Allotment">Internal Revenue Allotment (IRA)</option>
                        <option value="Education & Leadership">Education & Leadership</option>
                        <option value="Sports & Recreation">Sports & Recreation</option>
                        <option value="Health & Nutrition">Health & Nutrition</option>
                        <option value="Environmental Protection">Environmental Protection</option>
                        <option value="Anti-Drug Abuse">Anti-Drug Abuse</option>
                        <option value="Gender & Development">Gender & Development</option>
                        <option value="Other Operations">Other Operations</option>
                    </select>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition-colors">
                        Commit to Ledger
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script to handle Light/Dark Toggle -->
    <script>
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');

        // Setup initial icon
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