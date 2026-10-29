<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Strict Admin Access Control
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$msg = '';
$msg_type = 'success';

// --- CRUD: CREATE & UPDATE ---
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['save_voucher'])) {
    $id = $_POST['voucher_id'] ?? null;
    $budget_id = (int)($_POST['budget_id'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $withdrawal_date = trim($_POST['withdrawal_date'] ?? date('Y-m-d'));
    $new_amount = (float)str_replace(',', '', $_POST['amount'] ?? '0');

    try {
        $pdo->beginTransaction();

        if (!empty($id)) {
            // UPDATE EXISTING VOUCHER
            $old_stmt = $pdo->prepare("SELECT amount, budget_id FROM budget_vouchers WHERE id = ?");
            $old_stmt->execute([$id]);
            $old_voucher = $old_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old_voucher) {
                throw new Exception("Voucher record not found.");
            }

            $old_amount = (float)$old_voucher['amount'];
            $actual_budget_id = (int)$old_voucher['budget_id'];
            $difference = $new_amount - $old_amount;

            $budget_stmt = $pdo->prepare("SELECT amount FROM budget_monitoring WHERE id = ? FOR UPDATE");
            $budget_stmt->execute([$actual_budget_id]);
            $current_budget = $budget_stmt->fetchColumn();

            if ($current_budget !== false && $difference > 0 && $difference > $current_budget) {
                throw new Exception("Insufficient funds. Available balance: ₱" . number_format($current_budget, 2));
            }

            if ($current_budget !== false) {
                $update_budget = $pdo->prepare("UPDATE budget_monitoring SET amount = amount - ? WHERE id = ?");
                $update_budget->execute([$difference, $actual_budget_id]);
            }

            $update_voucher = $pdo->prepare("UPDATE budget_vouchers SET purpose = ?, amount = ?, withdrawal_date = ? WHERE id = ?");
            $update_voucher->execute([$purpose, $new_amount, $withdrawal_date, $id]);

            $msg = "Voucher activity successfully updated and parent budget reconciled.";
            $msg_type = "success";

        } else {
            // CREATE NEW VOUCHER
            $budget_stmt = $pdo->prepare("SELECT barangay, amount FROM budget_monitoring WHERE id = ? FOR UPDATE");
            $budget_stmt->execute([$budget_id]);
            $budget_data = $budget_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$budget_data) {
                throw new Exception("Selected budget source does not exist.");
            }

            if ($new_amount > (float)$budget_data['amount']) {
                throw new Exception("Insufficient funds. Only ₱" . number_format($budget_data['amount'], 2) . " available in this budget.");
            }

            $update_budget = $pdo->prepare("UPDATE budget_monitoring SET amount = amount - ? WHERE id = ?");
            $update_budget->execute([$new_amount, $budget_id]);

            $admin_name = ($_SESSION['admin_username'] ?? 'Admin') . ' (Admin)';
            $insert_voucher = $pdo->prepare("INSERT INTO budget_vouchers (barangay, budget_id, purpose, amount, withdrawal_date, withdrawn_by) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_voucher->execute([$budget_data['barangay'], $budget_id, $purpose, $new_amount, $withdrawal_date, $admin_name]);

            $msg = "New voucher successfully recorded and deducted from budget.";
            $msg_type = "success";
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg = $e->getMessage();
        $msg_type = "error";
    }
}

// --- CRUD: DELETE ---
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['delete_voucher'])) {
    $id = (int)($_POST['delete_id'] ?? 0);
    
    try {
        $pdo->beginTransaction();
        
        $fetch_stmt = $pdo->prepare("SELECT budget_id, amount FROM budget_vouchers WHERE id = ?");
        $fetch_stmt->execute([$id]);
        $voucher = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

        if ($voucher) {
            $refund_stmt = $pdo->prepare("UPDATE budget_monitoring SET amount = amount + ? WHERE id = ?");
            $refund_stmt->execute([$voucher['amount'], $voucher['budget_id']]);

            $del_stmt = $pdo->prepare("DELETE FROM budget_vouchers WHERE id = ?");
            $del_stmt->execute([$id]);

            $msg = "Activity record deleted. ₱" . number_format($voucher['amount'], 2) . " refunded to parent budget.";
            $msg_type = "success";
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $msg = "Error deleting voucher record.";
        $msg_type = "error";
    }
}

// --- SORTS AND FILTERS ---
$search = trim($_GET['search'] ?? '');
$filter_barangay = trim($_GET['barangay'] ?? '');
$sort_date = (isset($_GET['sort_date']) && strtoupper($_GET['sort_date']) === 'ASC') ? 'ASC' : 'DESC';
$period_filter = trim($_GET['period'] ?? 'All');

$query = "SELECT v.*, COALESCE(b.program, 'Direct Disbursement') as program 
          FROM budget_vouchers v 
          LEFT JOIN budget_monitoring b ON v.budget_id = b.id WHERE 1=1 ";
$params = [];

if ($filter_barangay !== 'All' && !empty($filter_barangay)) {
    $query .= "AND v.barangay = ? ";
    $params[] = $filter_barangay;
}

if ($period_filter === 'month') {
    $query .= "AND MONTH(v.withdrawal_date) = MONTH(CURRENT_DATE) AND YEAR(v.withdrawal_date) = YEAR(CURRENT_DATE) ";
} elseif ($period_filter === 'year') {
    $query .= "AND YEAR(v.withdrawal_date) = YEAR(CURRENT_DATE) ";
}

if (!empty($search)) {
    $query .= "AND (v.purpose LIKE ? OR v.barangay LIKE ? OR b.program LIKE ? OR v.withdrawn_by LIKE ?) ";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= "ORDER BY v.withdrawal_date $sort_date, v.barangay ASC, v.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// --- LEDGER GROUPING LOGIC ---
$grouped_vouchers = [];
foreach ($vouchers as $v) {
    $group_key = $v['withdrawal_date'] . '|' . $v['barangay'];
    
    if (!isset($grouped_vouchers[$group_key])) {
        $grouped_vouchers[$group_key] = [
            'date' => $v['withdrawal_date'],
            'barangay' => $v['barangay'],
            'total_amount' => 0,
            'items' => []
        ];
    }
    
    $grouped_vouchers[$group_key]['total_amount'] += (float)$v['amount'];
    $grouped_vouchers[$group_key]['items'][] = $v;
}

// Metrics & Counts
$total_withdrawn_all = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM budget_vouchers")->fetchColumn();
$total_vouchers_count = (int)$pdo->query("SELECT COUNT(*) FROM budget_vouchers")->fetchColumn();
$this_month_count = (int)$pdo->query("SELECT COUNT(*) FROM budget_vouchers WHERE MONTH(withdrawal_date) = MONTH(CURRENT_DATE) AND YEAR(withdrawal_date) = YEAR(CURRENT_DATE)")->fetchColumn();
$this_year_count = (int)$pdo->query("SELECT COUNT(*) FROM budget_vouchers WHERE YEAR(withdrawal_date) = YEAR(CURRENT_DATE)")->fetchColumn();
$unique_barangays_count = (int)$pdo->query("SELECT COUNT(DISTINCT barangay) FROM budget_vouchers")->fetchColumn();
$current_filtered_count = count($vouchers);

$all_barangays = [
    "Annafunan", "Antagan I", "Antagan II", "Arcon", "Balug", "Banig", "Bantug",
    "Barangay District 1 (Poblacion)", "Barangay District 2 (Poblacion)", "Barangay District 3 (Poblacion)",
    "Barangay District 4 (Poblacion)", "Bayabo East", "Caligayan", "Camasi", "Carpentero",
    "Compania", "Cumabao", "Fermeldy (Hacienda San Francisco)", "Fugu Abajo", "Fugu Norte",
    "Fugu Sur", "Lalauanan", "Lanna", "Lapogan", "Lingaling (Poblacion)", "Liwanag",
    "Malamag East", "Malamag West", "Maligaya (Poblacion)", "Minanga", "Moldero", "Namnama",
    "Paragu", "Pilitan", "San Mateo", "San Pedro", "San Vicente", "Santa", "Santa Catalina",
    "Santa Visitacion (Maggayu)", "Santo Niño", "Sinippil", "Sisim Abajo", "Sisim Alto", "Tunggui", "Ugad"
];
$barangays = $all_barangays;

$all_budgets = $pdo->query("SELECT id, barangay, program, amount FROM budget_monitoring ORDER BY barangay ASC, program ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vouchers & Disbursements | TALA OS</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    
    <!-- Anti-Flash Theme Script -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif']
                    },
                    colors: {
                        brand: { 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d' }
                    }
                }
            }
        };
    </script>
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            transition: background-color 0.3s ease, color 0.3s ease; 
            -webkit-tap-highlight-color: transparent; 
        }
        
        /* Ultra-Clean Modern Glass Cards: ZERO Outside Borders & ZERO Drop Shadows (Matching manage_disclosure.php) */
        .glass-card { 
            background: #ffffff !important; 
            border: none !important; 
            box-shadow: none !important; 
            -webkit-box-shadow: none !important; 
            transition: all 0.2s ease; 
        }
        .dark .glass-card { 
            background: #18181b !important; 
            border: none !important; 
            box-shadow: none !important; 
            -webkit-box-shadow: none !important; 
        }

        /* Mobile Drawer Overlay */
        .mobile-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            z-index: 40;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .mobile-overlay.active {
            display: block;
            opacity: 1;
        }

        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }

        .tap-effect:active { transform: scale(0.97); opacity: 0.85; transition: transform 0.1s; }

        /* Subtle Below-Left (Bottom-Left) Green Ambient Gradient for Section Cards */
        .card-green-glow-bl {
            position: relative;
            overflow: hidden;
        }
        .card-green-glow-bl::before {
            content: '';
            position: absolute;
            bottom: -10px;
            left: -10px;
            width: 72px;
            height: 72px;
            background: radial-gradient(circle at 25% 75%, rgba(34, 197, 94, 0.22) 0%, rgba(34, 197, 94, 0.08) 45%, transparent 75%);
            pointer-events: none;
            border-radius: 9999px;
            filter: blur(6px);
            z-index: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        .dark .card-green-glow-bl::before {
            background: radial-gradient(circle at 25% 75%, rgba(34, 197, 94, 0.28) 0%, rgba(34, 197, 94, 0.1) 45%, transparent 75%);
        }
        .card-green-glow-bl:hover::before {
            transform: scale(1.15);
            opacity: 1;
        }

        /* Subtle Right-Side Light Yellow Gradient Effect for Green Buttons */
        .btn-yellow-glow-r,
        button.bg-\[\#15803d\],
        button.bg-\[\#16a34a\],
        a.bg-\[\#15803d\] {
            position: relative;
            overflow: hidden;
        }
        .btn-yellow-glow-r::before,
        button.bg-\[\#15803d\]::before,
        button.bg-\[\#16a34a\]::before,
        a.bg-\[\#15803d\]::before {
            content: '';
            position: absolute;
            top: -6px;
            right: -6px;
            width: 38px;
            height: calc(100% + 12px);
            background: radial-gradient(circle at 85% 50%, rgba(254, 240, 138, 0.50) 0%, rgba(250, 204, 21, 0.20) 45%, transparent 75%);
            pointer-events: none;
            border-radius: inherit;
            filter: blur(3px);
            z-index: 1;
            transition: transform 0.25s ease, opacity 0.25s ease;
        }
        .btn-yellow-glow-r:hover::before,
        button.bg-\[\#15803d\]:hover::before,
        button.bg-\[\#16a34a\]:hover::before,
        a.bg-\[\#15803d\]:hover::before {
            transform: scale(1.15);
            opacity: 1;
        }

        /* Remove all outline / outside strokelines on focus */
        input:focus,
        input:focus-visible,
        input:active,
        select:focus,
        select:focus-visible,
        select:active,
        textarea:focus,
        textarea:focus-visible,
        textarea:active,
        button:focus,
        button:focus-visible,
        a:focus {
            outline: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }

        /* Remove all box shadows globally */
        .shadow-none-all,
        .shadow-sm,
        .shadow-md,
        .shadow-lg,
        .shadow-xl,
        .shadow-2xl,
        .shadow-soft {
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }

        img {
            filter: none !important;
            -webkit-filter: none !important;
        }

        /* Suppress conflicting floating theme toggle from sidebar */
        #globalThemeToggle { 
            display: none !important; 
        }

        @media print {
            body { background: #fff !important; color: #000 !important; }
            #tala-admin-sidebar, #tala-bulletproof-sk-sidebar, nav, #headerThemeToggle, .no-print { display: none !important; }
            main { margin-left: 0 !important; padding: 0 !important; }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- Admin Sidebar -->
    <?php include 'sidebar_admin.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-2.5 md:space-y-3">
            
            <!-- COMMAND CENTER HEADER WITH THREE UNIFIED GREEN KPI BADGES & THEME TOGGLE -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <div>
                        <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                            Voucher Management
                        </h1>
                        <p class="text-xs md:text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                            Itemized disbursements, transactions, and program deductions
                        </p>
                    </div>
                </div>
                
                <!-- Section Cards (3 Unified Solid TALA Green KPI Badges) & Dark Mode Toggle -->
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Total Disbursed -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-currency-circle-dollar text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Disbursed</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight">₱<?= number_format($total_withdrawn_all, 2) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Logged Activities -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-receipt text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Activities</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_vouchers_count) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Scope -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-map-pin text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Scope</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight truncate">
                                <?= !empty($filter_barangay) && $filter_barangay !== 'All' ? htmlspecialchars($filter_barangay) : number_format($unique_barangays_count) . ' Brgys' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[36px] h-[36px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
                        <i id="headerThemeIcon" class="ph-bold ph-moon text-base sm:text-lg transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- Action Status Alert -->
            <?php if (!empty($msg)): ?>
                <div class="<?= $msg_type === 'success' ? 'bg-green-50/85 dark:bg-green-500/10 text-green-700 dark:text-green-400' : 'bg-red-50/85 dark:bg-red-500/10 text-red-700 dark:text-red-400' ?> px-4 py-3 rounded-2xl flex items-center justify-between gap-3 text-xs md:text-sm font-bold shadow-none backdrop-blur-md transition-colors border-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <i class="ph-bold <?= $msg_type === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-lg flex-shrink-0"></i>
                        <span class="truncate"><?= htmlspecialchars($msg) ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="w-6 h-6 rounded-lg flex items-center justify-center hover:bg-black/5 dark:hover:bg-white/10 transition-colors tap-effect" aria-label="Dismiss">
                        <i class="ph-bold ph-x text-xs"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- FILTER, SEARCH & ACTION CONTROL BAR -->
            <div class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col xl:flex-row items-stretch xl:items-center justify-between gap-2.5">
                <!-- Period Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full xl:w-auto custom-scrollbar py-0.5 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['val' => 'All', 'label' => 'All Disbursed', 'count' => $total_vouchers_count, 'active' => ($period_filter === 'All' || empty($period_filter))],
                        ['val' => 'month', 'label' => 'This Month', 'count' => $this_month_count, 'active' => ($period_filter === 'month')],
                        ['val' => 'year', 'label' => 'This Year', 'count' => $this_year_count, 'active' => ($period_filter === 'year')],
                    ];
                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        
                        $tabParams = [];
                        if ($tab['val'] !== 'All') $tabParams['period'] = $tab['val'];
                        if (!empty($filter_barangay) && $filter_barangay !== 'All') $tabParams['barangay'] = $filter_barangay;
                        if ($sort_date !== 'DESC') $tabParams['sort_date'] = $sort_date;
                        if (!empty($search)) $tabParams['search'] = $search;
                        $tabUrl = 'admin_vouchers.php' . (!empty($tabParams) ? '?' . http_build_query($tabParams) : '');
                    ?>
                        <a href="<?= $tabUrl ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span class="relative z-10"><?= $tab['label'] ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 dark:bg-black/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?> relative z-10"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Dropdowns, Search & Action Button Cluster -->
                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full xl:w-auto">
                    <!-- Barangay Dropdown -->
                    <form method="GET" class="w-full sm:w-auto sm:flex-1 md:w-44 min-w-[140px]">
                        <?php if ($period_filter !== 'All'): ?><input type="hidden" name="period" value="<?= htmlspecialchars($period_filter) ?>"><?php endif; ?>
                        <?php if ($sort_date !== 'DESC'): ?><input type="hidden" name="sort_date" value="<?= htmlspecialchars($sort_date) ?>"><?php endif; ?>
                        <?php if (!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <select name="barangay" onchange="this.form.submit()" class="w-full bg-slate-100 dark:bg-zinc-800/80 text-slate-800 dark:text-zinc-200 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="">All 46 Barangays</option>
                            <?php foreach ($all_barangays as $b): ?>
                                <option value="<?= htmlspecialchars($b) ?>" <?= $filter_barangay === $b ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <!-- Sort Date Dropdown -->
                    <form method="GET" class="w-full sm:w-auto sm:flex-1 md:w-36 min-w-[120px]">
                        <?php if ($period_filter !== 'All'): ?><input type="hidden" name="period" value="<?= htmlspecialchars($period_filter) ?>"><?php endif; ?>
                        <?php if (!empty($filter_barangay) && $filter_barangay !== 'All'): ?><input type="hidden" name="barangay" value="<?= htmlspecialchars($filter_barangay) ?>"><?php endif; ?>
                        <?php if (!empty($search)): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>
                        <select name="sort_date" onchange="this.form.submit()" class="w-full bg-slate-100 dark:bg-zinc-800/80 text-slate-800 dark:text-zinc-200 rounded-xl px-3 py-1.5 text-xs font-semibold outline-none cursor-pointer transition-colors border-0">
                            <option value="DESC" <?= $sort_date === 'DESC' ? 'selected' : '' ?>>Newest First</option>
                            <option value="ASC" <?= $sort_date === 'ASC' ? 'selected' : '' ?>>Oldest First</option>
                        </select>
                    </form>

                    <!-- Search Input -->
                    <form method="GET" class="relative w-full sm:w-auto sm:flex-1 md:w-52 min-w-[160px]">
                        <?php if ($period_filter !== 'All'): ?><input type="hidden" name="period" value="<?= htmlspecialchars($period_filter) ?>"><?php endif; ?>
                        <?php if (!empty($filter_barangay) && $filter_barangay !== 'All'): ?><input type="hidden" name="barangay" value="<?= htmlspecialchars($filter_barangay) ?>"><?php endif; ?>
                        <?php if ($sort_date !== 'DESC'): ?><input type="hidden" name="sort_date" value="<?= htmlspecialchars($sort_date) ?>"><?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search purpose, program..." class="w-full bg-slate-100 dark:bg-zinc-800/80 text-slate-800 dark:text-zinc-200 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none transition-colors border-0">
                    </form>

                    <!-- Action Buttons Cluster -->
                    <div class="flex items-center gap-1.5 w-full sm:w-auto ml-auto sm:ml-0 flex-shrink-0">
                        <?php if (!empty($search) || (!empty($filter_barangay) && $filter_barangay !== 'All') || $period_filter !== 'All' || $sort_date !== 'DESC'): ?>
                            <a href="admin_vouchers.php" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Filters">
                                <i class="ph-bold ph-x text-xs"></i>
                            </a>
                        <?php endif; ?>

                        <!-- Disburse Voucher Button -->
                        <button type="button" onclick="openVoucherModal()" class="flex-1 sm:flex-none px-3.5 py-1.5 rounded-xl btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center justify-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group cursor-pointer border-0">
                            <i class="ph-bold ph-plus text-xs transition-transform duration-300 group-hover:scale-110 relative z-10"></i>
                            <span class="relative z-10">Disburse Voucher</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- CONSOLIDATED MASTER VOUCHER LEDGER TABLE CARD -->
            <div class="glass-card card-green-glow-bl rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Minimal Header Strip -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex justify-between items-center transition-colors flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Master Voucher Ledger</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                            <?= number_format($current_filtered_count) ?> Records
                        </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400 dark:text-zinc-500 hidden sm:inline">Itemized disbursements, transactions & budget reconciliations</span>
                </div>

                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[750px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider bg-slate-50/90 dark:bg-zinc-900/90 backdrop-blur-sm transition-colors">
                                <th class="py-2.5 px-4 md:px-5">Transaction Date & Barangay</th>
                                <th class="py-2.5 px-4 md:px-5 text-center">Activities Lodged</th>
                                <th class="py-2.5 px-4 md:px-5">Total Withdrawn</th>
                                <th class="py-2.5 px-4 md:px-5 text-right">Details</th>
                            </tr>
                        </thead>
                        
                        <?php if (count($grouped_vouchers) > 0): ?>
                            <?php $group_idx = 0; ?>
                            <?php foreach ($grouped_vouchers as $group_key => $group): ?>
                                <?php $group_id = "group_" . $group_idx; ?>
                                <tbody class="divide-y divide-slate-100/80 dark:divide-zinc-800/60 transition-colors ledger-group-block">
                                    
                                    <!-- Parent Accordion Row -->
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 cursor-pointer transition-colors group" onclick="toggleLedger('<?= $group_id ?>')">
                                        <td class="py-2.5 px-4 md:px-5">
                                            <div class="flex items-center gap-2.5">
                                                <i class="ph-bold ph-caret-down text-slate-400 dark:text-zinc-500 transition-transform duration-300 text-sm transform" id="icon_<?= $group_id ?>"></i>
                                                <div>
                                                    <p class="font-extrabold text-slate-900 dark:text-white text-xs md:text-sm leading-tight transition-colors">
                                                        <?= date('F d, Y', strtotime($group['date'])) ?>
                                                    </p>
                                                    <span class="inline-flex items-center gap-1 bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300 px-2 py-0.5 rounded-lg text-[11px] font-bold uppercase tracking-wider mt-0.5 transition-colors">
                                                        <i class="ph-fill ph-map-pin text-green-600 dark:text-green-500 text-[10px]"></i> <?= htmlspecialchars($group['barangay']) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-4 md:px-5 text-center whitespace-nowrap">
                                            <span class="bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 text-xs font-bold px-2.5 py-1 rounded-xl transition-colors">
                                                <?= count($group['items']) ?> <?= count($group['items']) === 1 ? 'Activity' : 'Activities' ?>
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-4 md:px-5 whitespace-nowrap">
                                            <span class="font-extrabold text-green-600 dark:text-green-500 text-xs md:text-sm">
                                                ₱<?= number_format($group['total_amount'], 2) ?>
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-4 md:px-5 text-right whitespace-nowrap">
                                            <span class="text-xs text-slate-500 dark:text-zinc-400 font-bold uppercase tracking-wider group-hover:text-green-600 dark:group-hover:text-green-400 transition-colors inline-flex items-center gap-1">
                                                <span>View Items</span>
                                                <i class="ph-bold ph-caret-right text-xs"></i>
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                                
                                <!-- Expanded Child Items -->
                                <tbody id="<?= $group_id ?>" class="hidden transition-all duration-300 bg-slate-50/30 dark:bg-zinc-950/30 divide-y divide-slate-100/80 dark:divide-zinc-800/40">
                                    <tr class="bg-slate-100/70 dark:bg-zinc-900/70 text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider transition-colors">
                                        <th class="py-2 px-4 md:px-5 pl-10">Specific Activity & Program</th>
                                        <th class="py-2 px-4 md:px-5 text-center">Withdrawn By</th>
                                        <th class="py-2 px-4 md:px-5">Amount (₱)</th>
                                        <th class="py-2 px-4 md:px-5 text-right">Actions</th>
                                    </tr>
                                    <?php foreach ($group['items'] as $v): ?>
                                    <tr class="hover:bg-slate-50/80 dark:hover:bg-zinc-800/40 transition-colors group/child voucher-item-row">
                                        <td class="py-2.5 px-4 md:px-5 pl-10">
                                            <p class="font-bold text-slate-900 dark:text-white text-xs leading-snug transition-colors line-clamp-1 voucher-purpose">
                                                <?= htmlspecialchars($v['purpose']) ?>
                                            </p>
                                            <p class="text-[11px] text-green-700 dark:text-green-400 font-bold mt-0.5 truncate max-w-sm voucher-program">
                                                <?= htmlspecialchars($v['program']) ?>
                                            </p>
                                        </td>
                                        <td class="py-2.5 px-4 md:px-5 text-center whitespace-nowrap">
                                            <span class="text-xs text-slate-500 dark:text-zinc-400 font-medium">
                                                <?= htmlspecialchars($v['withdrawn_by']) ?>
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-4 md:px-5 whitespace-nowrap">
                                            <span class="font-extrabold text-xs md:text-sm text-green-600 dark:text-green-500">
                                                ₱<?= number_format($v['amount'], 2) ?>
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-4 md:px-5 text-right whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1">
                                                <!-- Edit Button -->
                                                <button type="button" onclick="editVoucherModal(<?= htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-green-50 dark:hover:bg-green-500/10 transition-colors tap-effect" title="Edit Activity">
                                                    <i class="ph-bold ph-pencil-simple text-xs"></i>
                                                </button>
                                                <!-- Delete Form -->
                                                <form action="admin_vouchers.php" method="POST" onsubmit="return confirm('Warning: Deleting this activity will refund ₱<?= number_format($v['amount'], 2) ?> back to the allocated program budget. Proceed?');" class="inline">
                                                    <input type="hidden" name="delete_voucher" value="1">
                                                    <input type="hidden" name="delete_id" value="<?= $v['id'] ?>">
                                                    <button type="submit" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Delete & Refund Activity">
                                                        <i class="ph-bold ph-trash text-xs"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <?php $group_idx++; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tbody>
                                <tr>
                                    <td colspan="4" class="py-14 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <div class="w-20 h-20 md:w-24 md:h-24 mx-auto mb-2 flex items-center justify-center">
                                            <img src="mascot_question.png" alt="No Records" class="max-h-full max-w-full object-contain pointer-events-none hover:scale-105 transition-transform duration-300">
                                        </div>
                                        <p class="font-extrabold text-sm text-slate-800 dark:text-zinc-200">No Vouchers Found</p>
                                        <p class="text-xs text-slate-400 dark:text-zinc-500 font-medium mt-0.5">Try clearing filters or click Disburse Voucher above to record expenditures.</p>
                                        <?php if (!empty($search) || (!empty($filter_barangay) && $filter_barangay !== 'All') || $period_filter !== 'All' || $sort_date !== 'DESC'): ?>
                                            <a href="admin_vouchers.php" class="mt-3 inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
                                                <span>Clear Filters</span>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        <?php endif; ?>
                    </table>
                </div>

                <!-- Minimal Table Footer -->
                <div class="px-4 py-2.5 bg-white/40 dark:bg-zinc-900/40 flex justify-between items-center text-xs text-slate-500 dark:text-zinc-400 font-medium flex-shrink-0">
                    <span>Showing <strong class="text-slate-800 dark:text-zinc-200"><?= number_format($current_filtered_count) ?></strong> of <?= number_format($total_vouchers_count) ?> activities</span>
                    <span class="hidden sm:inline">Voucher Reconciliation &bull; TALA OS</span>
                </div>

            </div>
            
        </div>
    </main>

    <!-- VOUCHER DISBURSEMENT MODAL -->
    <div id="voucherModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeVoucherModal()"></div>
        
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-lg w-full p-5 md:p-6 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border-0 max-h-[90vh] overflow-y-auto custom-scrollbar" id="voucherModalContent">
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-4 pb-2">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-green-500/10 text-green-600 dark:text-green-500 rounded-xl flex items-center justify-center text-base font-bold flex-shrink-0">
                        <i class="ph-bold ph-receipt"></i>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-slate-900 dark:text-white text-sm md:text-base leading-tight" id="modalTitle">Disburse Voucher</h3>
                        <p class="text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider font-mono mt-0.5">Fund Deduction & Reconciliation</p>
                    </div>
                </div>
                <button type="button" onclick="closeVoucherModal()" class="w-7 h-7 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 rounded-lg flex items-center justify-center transition-colors tap-effect" aria-label="Close modal">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>
            
            <!-- Modal Body (Form) -->
            <form action="admin_vouchers.php" method="POST" id="voucherModalForm" class="space-y-3">
                <input type="hidden" name="save_voucher" value="1">
                <input type="hidden" name="voucher_id" id="modal_voucher_id" value="">
                
                <!-- Target Budget Source -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Target Budget Source *</label>
                    <select name="budget_id" id="modal_budget_id" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800/80 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors cursor-pointer border-0" required>
                        <option value="" disabled selected>Select Parent Budget...</option>
                        <?php if (count($all_budgets) > 0): ?>
                            <?php foreach ($all_budgets as $b): ?>
                                <option value="<?= $b['id'] ?>">
                                    <?= htmlspecialchars($b['barangay']) ?> — <?= htmlspecialchars($b['program']) ?> (₱<?= number_format($b['amount'], 2) ?> available)
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No active budget allocations found</option>
                        <?php endif; ?>
                    </select>
                    <p id="editWarning" class="text-xs text-amber-600 dark:text-amber-400 font-bold mt-1.5 hidden transition-colors flex items-center gap-1">
                        <i class="ph-bold ph-lock-key"></i> Budget source locked during edit to maintain accounting integrity.
                    </p>
                </div>

                <!-- Purpose / Activities -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Specific Activity / Purpose *</label>
                    <textarea name="purpose" id="modal_purpose" rows="3" placeholder="e.g. Purchase of sports equipment for Summer League" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800/80 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors resize-none custom-scrollbar border-0" required></textarea>
                </div>

                <!-- Date & Amount -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Disbursement Date *</label>
                        <input type="date" name="withdrawal_date" id="modal_withdrawal_date" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800/80 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors border-0" required>
                    </div>
                    <div>
                        <label class="block text-[10px] md:text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-1">Amount (₱) *</label>
                        <input type="number" step="0.01" name="amount" id="modal_amount" placeholder="0.00" class="w-full px-3 py-2 bg-slate-100 dark:bg-zinc-800/80 text-xs md:text-sm font-semibold rounded-xl focus:outline-none dark:text-white transition-colors border-0" required>
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeVoucherModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold rounded-xl text-xs md:text-sm transition-all tap-effect">
                        Cancel
                    </button>
                    <button type="submit" id="modalSubmitBtn" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2 px-5 rounded-xl transition-all text-xs md:text-sm tap-effect flex items-center gap-1.5 shadow-none border-0">
                        <i class="ph-bold ph-floppy-disk relative z-10"></i>
                        <span id="modalSubmitText" class="relative z-10">Save Activity</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Android Style) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 h-[68px] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-2xl z-50 flex justify-around items-center px-2 pb-[env(safe-area-inset-bottom)] transition-colors border-0">
        <a href="dashboard.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-squares-four text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Dashboard</span>
        </a>
        <a href="admin_calendar.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-calendar-blank text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Calendar</span>
        </a>
        <a href="admin_budget.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-coins text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Budget</span>
        </a>
        <a href="admin_vouchers.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-receipt text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Vouchers</span>
        </a>
        <a href="admin_approval.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-shield-check text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Approvals</span>
        </a>
    </nav>

    <!-- UI Scripts -->
    <script>
        // Toggle Accordion Group Rows
        function toggleLedger(groupId) {
            const groupBody = document.getElementById(groupId);
            const toggleIcon = document.getElementById('icon_' + groupId);
            
            if (groupBody && toggleIcon) {
                if (groupBody.classList.contains('hidden')) {
                    groupBody.classList.remove('hidden');
                    toggleIcon.classList.add('rotate-180');
                } else {
                    groupBody.classList.add('hidden');
                    toggleIcon.classList.remove('rotate-180');
                }
            }
        }

        // Modal Controls
        const voucherModal = document.getElementById('voucherModal');
        const voucherModalContent = document.getElementById('voucherModalContent');
        const voucherForm = document.getElementById('voucherModalForm');
        const budgetSelect = document.getElementById('modal_budget_id');
        const editWarning = document.getElementById('editWarning');

        function openVoucherModal() {
            voucherForm.reset();
            document.getElementById('modal_voucher_id').value = '';
            
            budgetSelect.style.pointerEvents = 'auto';
            budgetSelect.style.opacity = '1';
            budgetSelect.setAttribute('name', 'budget_id');
            
            const hiddenBudget = document.getElementById('hidden_budget_id');
            if (hiddenBudget) hiddenBudget.remove();
            
            editWarning.classList.add('hidden');
            document.getElementById('modalTitle').innerText = 'Disburse Voucher';
            document.getElementById('modalSubmitText').innerText = 'Save Activity';
            
            voucherModal.classList.remove('hidden');
            voucherModal.classList.add('flex');
            setTimeout(() => {
                voucherModal.classList.remove('opacity-0');
                voucherModal.classList.add('opacity-100');
                voucherModalContent.classList.remove('scale-95', 'opacity-0');
                voucherModalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function editVoucherModal(data) {
            if (!data) return;

            document.getElementById('modal_voucher_id').value = data.id || '';
            budgetSelect.value = data.budget_id || '';
            budgetSelect.style.pointerEvents = 'none';
            budgetSelect.style.opacity = '0.6';
            budgetSelect.removeAttribute('name');

            let hiddenBudget = document.getElementById('hidden_budget_id');
            if (!hiddenBudget) {
                hiddenBudget = document.createElement('input');
                hiddenBudget.type = 'hidden';
                hiddenBudget.name = 'budget_id';
                hiddenBudget.id = 'hidden_budget_id';
                voucherForm.appendChild(hiddenBudget);
            }
            hiddenBudget.value = data.budget_id || '';

            editWarning.classList.remove('hidden');
            document.getElementById('modal_purpose').value = data.purpose || '';
            document.getElementById('modal_withdrawal_date').value = data.withdrawal_date || '';
            document.getElementById('modal_amount').value = data.amount || '';

            document.getElementById('modalTitle').innerText = 'Update Activity';
            document.getElementById('modalSubmitText').innerText = 'Apply Changes';

            voucherModal.classList.remove('hidden');
            voucherModal.classList.add('flex');
            setTimeout(() => {
                voucherModal.classList.remove('opacity-0');
                voucherModal.classList.add('opacity-100');
                voucherModalContent.classList.remove('scale-95', 'opacity-0');
                voucherModalContent.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeVoucherModal() {
            voucherModal.classList.remove('opacity-100');
            voucherModal.classList.add('opacity-0');
            voucherModalContent.classList.remove('scale-100', 'opacity-100');
            voucherModalContent.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                voucherModal.classList.remove('flex');
                voucherModal.classList.add('hidden');
            }, 200);
        }

        // Close on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !voucherModal.classList.contains('hidden')) {
                closeVoucherModal();
            }
        });

        // Mobile Drawer Toggle
        function toggleMobileMenu() {
            const sidebar = document.getElementById('tala-admin-sidebar') || document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            if (sidebar && overlay) {
                if (sidebar.classList.contains('mobile-open') || !sidebar.classList.contains('-translate-x-full')) {
                    sidebar.classList.remove('mobile-open');
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                } else {
                    sidebar.classList.add('mobile-open');
                    sidebar.classList.remove('-translate-x-full');
                    overlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            }
        }

        // Header Dark Mode Toggle Logic
        function syncHeaderThemeIcon(isDark) {
            const icon = document.getElementById('headerThemeIcon');
            if (icon) {
                if (isDark) {
                    icon.classList.remove('ph-moon');
                    icon.classList.add('ph-sun');
                } else {
                    icon.classList.remove('ph-sun');
                    icon.classList.add('ph-moon');
                }
            }
        }

        function toggleTheme() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            localStorage.theme = isDark ? 'dark' : 'light';
            syncHeaderThemeIcon(isDark);
        }
        syncHeaderThemeIcon(document.documentElement.classList.contains('dark'));
    </script>

    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>