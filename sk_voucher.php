<?php
// sk_voucher.php - Sangguniang Kabataan Disbursement Vouchers Management
// Standardized to TALA OS Design System (Reference: sk_mom.php & manage_announcements.php)
ob_start();
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
require_once 'db_connect.php'; 

// 1. Strict Access Control
$allowed_roles = ['sk_admin', 'sub-admin', 'admin'];
if (!isset($_SESSION['admin_logged_in']) || !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role'] ?? 'sub-admin';
$user_barangay = $_SESSION['barangay_assigned'] ?? $_SESSION['admin_barangay'] ?? '';
if (empty($user_barangay) || $user_barangay === 'All') {
    $user_barangay = 'Balug';
}

$user_position = $_SESSION['sk_position'] ?? $_SESSION['position'] ?? 'Official';
$user_name = $_SESSION['admin_username'] ?? 'SK Official';

// Only Chairperson and Treasurer (or Municipal Admin) can authorize financial disbursements
$can_withdraw = in_array($user_position, ['SK Chairperson', 'SK Treasurer']) || ($user_role === 'admin');

// Normalized Barangay Name Variants
$clean_brgy = str_ireplace('Barangay ', '', $user_barangay);
$brgy_variants = array_values(array_unique([$user_barangay, $clean_brgy, 'Barangay ' . $clean_brgy]));
$brgy_placeholders = implode(',', array_fill(0, count($brgy_variants), '?'));

// Filter & Search Parameters
$filter_program = trim($_GET['program'] ?? 'All');
$search = trim($_GET['search'] ?? '');

// Flash Message Handling
$flash_msg = $_SESSION['flash_msg'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// Build Redirect Query Helper
function buildQueryString($program, $search) {
    $params = [];
    if (!empty($program) && $program !== 'All') $params['program'] = $program;
    if (!empty($search)) $params['search'] = $search;
    return !empty($params) ? '?' . http_build_query($params) : '';
}

// --- CRUD: DELETE / REFUND VOUCHER ---
if (isset($_GET['delete_id']) && $can_withdraw) {
    $del_id = (int)$_GET['delete_id'];
    try {
        $pdo->beginTransaction();

        $fetch_stmt = $pdo->prepare("SELECT v.*, b.program FROM budget_vouchers v LEFT JOIN budget_monitoring b ON v.budget_id = b.id WHERE v.id = ? AND v.barangay IN ($brgy_placeholders) FOR UPDATE");
        $fetch_stmt->execute(array_merge([$del_id], $brgy_variants));
        $voucher = $fetch_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$voucher) {
            throw new Exception("Voucher record not found or access denied.");
        }

        // Refund back to parent budget program
        $refund_stmt = $pdo->prepare("UPDATE budget_monitoring SET amount = amount + ? WHERE id = ?");
        $refund_stmt->execute([$voucher['amount'], $voucher['budget_id']]);

        // Delete voucher
        $del_stmt = $pdo->prepare("DELETE FROM budget_vouchers WHERE id = ?");
        $del_stmt->execute([$del_id]);

        $pdo->commit();

        $_SESSION['flash_msg'] = "Voucher deleted! ₱" . number_format($voucher['amount'], 2) . " has been refunded back to " . htmlspecialchars($voucher['program'] ?? 'parent budget') . ".";
        $_SESSION['flash_type'] = "success";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['flash_msg'] = $e->getMessage();
        $_SESSION['flash_type'] = "error";
    }
    header("Location: sk_voucher.php" . buildQueryString($filter_program, $search));
    exit();
}

// --- CRUD: CREATE VOUCHER ---
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['create_voucher']) && $can_withdraw) {
    $budget_id = (int)($_POST['budget_id'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');
    $withdrawal_date = trim($_POST['withdrawal_date'] ?? date('Y-m-d'));
    $amount = (float)str_replace(',', '', $_POST['amount'] ?? '0');

    if ($budget_id <= 0 || empty($purpose) || $amount <= 0) {
        $_SESSION['flash_msg'] = "Please select a budget program, specify the disbursement purpose, and enter a valid positive amount.";
        $_SESSION['flash_type'] = "error";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Verify program exists and lock row
            $check_stmt = $pdo->prepare("SELECT id, program, amount, barangay FROM budget_monitoring WHERE id = ? AND barangay IN ($brgy_placeholders) FOR UPDATE");
            $check_stmt->execute(array_merge([$budget_id], $brgy_variants));
            $budget_row = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$budget_row) {
                throw new Exception("Invalid budget program selected or not assigned to your barangay.");
            }

            $current_balance = (float)$budget_row['amount'];
            if ($amount > $current_balance) {
                throw new Exception("Insufficient funds! Only ₱" . number_format($current_balance, 2) . " available in " . htmlspecialchars($budget_row['program']) . ".");
            }

            // 2. Insert into budget_vouchers
            $insert_stmt = $pdo->prepare("INSERT INTO budget_vouchers (barangay, budget_id, purpose, amount, withdrawal_date, withdrawn_by) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_stmt->execute([$user_barangay, $budget_id, $purpose, $amount, $withdrawal_date, $user_name]);

            // 3. Deduct from budget_monitoring
            $update_stmt = $pdo->prepare("UPDATE budget_monitoring SET amount = amount - ? WHERE id = ?");
            $update_stmt->execute([$amount, $budget_id]);

            $pdo->commit();

            $_SESSION['flash_msg'] = "Voucher approved! ₱" . number_format($amount, 2) . " disbursed from " . htmlspecialchars($budget_row['program']) . ".";
            $_SESSION['flash_type'] = "success";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_msg'] = $e->getMessage();
            $_SESSION['flash_type'] = "error";
        }
    }
    header("Location: sk_voucher.php" . buildQueryString($filter_program, $search));
    exit();
}


// --- KPI STATISTICS ---
// 1. Total Disbursed
$sum_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM budget_vouchers WHERE barangay IN ($brgy_placeholders)");
$sum_stmt->execute($brgy_variants);
$total_withdrawn = (float)$sum_stmt->fetchColumn();

// 2. Total Vouchers Count
$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM budget_vouchers WHERE barangay IN ($brgy_placeholders)");
$cnt_stmt->execute($brgy_variants);
$total_vouchers = (int)$cnt_stmt->fetchColumn();

// 3. Available Active Budget
$bal_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM budget_monitoring WHERE barangay IN ($brgy_placeholders)");
$bal_stmt->execute($brgy_variants);
$total_remaining_budget = (float)$bal_stmt->fetchColumn();

// Active Programs for Form Dropdown (programs that have remaining funds)
$active_budgets_stmt = $pdo->prepare("SELECT id, program, amount FROM budget_monitoring WHERE barangay IN ($brgy_placeholders) AND amount > 0 ORDER BY program ASC");
$active_budgets_stmt->execute($brgy_variants);
$active_budgets = $active_budgets_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Vouchers for Table
$vouchers_stmt = $pdo->prepare("
    SELECT v.*, b.program 
    FROM budget_vouchers v 
    LEFT JOIN budget_monitoring b ON v.budget_id = b.id 
    WHERE v.barangay IN ($brgy_placeholders) 
    ORDER BY v.withdrawal_date DESC, v.id DESC
");
$vouchers_stmt->execute($brgy_variants);
$all_vouchers = $vouchers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Program tab list & counts
$program_counts = [];
foreach ($all_vouchers as $v) {
    $p_name = $v['program'] ?? 'General Fund';
    if (!isset($program_counts[$p_name])) {
        $program_counts[$p_name] = 0;
    }
    $program_counts[$p_name]++;
}

// Filter for Table View
$filtered_vouchers = [];
foreach ($all_vouchers as $v) {
    $p_name = $v['program'] ?? 'General Fund';
    if ($filter_program !== 'All' && $p_name !== $filter_program) {
        continue;
    }
    
    if (!empty($search)) {
        $search_lower = strtolower($search);
        $date_str = strtolower(date('F d, Y', strtotime($v['withdrawal_date'])));
        $purpose_str = strtolower($v['purpose']);
        $prog_str = strtolower($p_name);
        $user_str = strtolower($v['withdrawn_by']);
        $amount_str = strtolower(number_format($v['amount'], 2));
        
        $match = (strpos($date_str, $search_lower) !== false ||
                  strpos($purpose_str, $search_lower) !== false ||
                  strpos($prog_str, $search_lower) !== false ||
                  strpos($user_str, $search_lower) !== false ||
                  strpos($amount_str, $search_lower) !== false);
                  
        if (!$match) continue;
    }
    
    $filtered_vouchers[] = $v;
}
$current_filtered_count = count($filtered_vouchers);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Disbursement Vouchers | TALA OS</title>
    
    <!-- Anti-Flash Theme Script -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
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
        
        /* Ultra-Clean Modern Glass Cards: ZERO Outside Borders & ZERO Drop Shadows */
        .glass-card { 
            background: #ffffff !important; 
            border: none !important; 
            box-shadow: none !important; 
            -webkit-box-shadow: none !important; 
            transition: all 0.3s ease; 
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

        /* Tap effect for mobile elements */
        .tap-effect:active { transform: scale(0.97); opacity: 0.85; transition: transform 0.1s; }

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
        label:focus,
        label:focus-visible,
        label:active,
        a:focus {
            outline: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }

        label {
            user-select: none;
            -webkit-user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

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

        /* Mobile Drawer Slide-in Animation for SK Sidebar */
        @media (max-width: 767px) {
            #tala-bulletproof-sk-sidebar {
                transform: translateX(-100%) !important;
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.3s ease !important;
                z-index: 100 !important;
                width: 80% !important;
                max-width: 300px !important;
            }
            #tala-bulletproof-sk-sidebar.mobile-open {
                transform: translateX(0) !important;
            }
        }

        @media print {
            body { background: #fff !important; color: #000 !important; }
            #tala-bulletproof-sk-sidebar, nav, #headerThemeToggle, .no-print { display: none !important; }
            main { margin-left: 0 !important; padding: 0 !important; }
            #printableSlip { display: block !important; }
        }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- Role-Appropriate SK Sidebar -->
    <?php include 'sidebar_sk.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA (Reference: sk_mom.php) -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px and fits viewport smoothly -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-3 md:space-y-4">
            
            <!-- HEADER SECTION (Bold H1 only, 3 KPI Badges - Exactly matching sk_mom.php) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                        Disbursement Vouchers
                    </h1>
                    <?php if (!$can_withdraw): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border-0">
                            <i class="ph-bold ph-shield"></i> View-Only (<?= htmlspecialchars($user_position) ?>)
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Total Disbursed -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-hand-coins text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Disbursed</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight">₱<?= number_format($total_withdrawn, 2) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Total Vouchers -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-receipt text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Vouchers</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_vouchers) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Available Active Budget -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-piggy-bank text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Available Funds</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight">₱<?= number_format($total_remaining_budget, 2) ?></span>
                        </div>
                    </div>

                    <!-- Header Dark Mode Toggle Button -->
                    <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-[36px] h-[36px] sm:w-[40px] sm:h-[40px] rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white transition-all duration-200 hover:-translate-y-0.5 tap-effect flex-shrink-0 group cursor-pointer" title="Toggle Dark Mode">
                        <i id="headerThemeIcon" class="ph-bold ph-moon text-base sm:text-lg transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <!-- NOTIFICATIONS -->
            <?php if (!empty($flash_msg)): ?>
                <div class="<?= $flash_type === 'success' ? 'bg-green-50/85 dark:bg-green-500/10 text-green-700 dark:text-green-400' : 'bg-red-50/85 dark:bg-red-500/10 text-red-700 dark:text-red-400' ?> px-4 py-3 rounded-2xl flex items-center justify-between gap-3 text-xs md:text-sm font-bold shadow-none backdrop-blur-md transition-colors border-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <i class="ph-bold <?= $flash_type === 'success' ? 'ph-check-circle' : 'ph-warning-circle' ?> text-lg flex-shrink-0"></i>
                        <span class="truncate"><?= htmlspecialchars($flash_msg) ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="w-6 h-6 rounded-lg flex items-center justify-center hover:bg-black/5 dark:hover:bg-white/10 transition-colors tap-effect" aria-label="Dismiss">
                        <i class="ph-bold ph-x text-xs"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- FILTER, SEARCH & ACTION CONTROL BAR (Matching sk_mom.php) -->
            <div class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col md:flex-row items-center justify-between gap-2.5">
                <!-- Program Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto custom-scrollbar py-1 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['val' => 'All', 'label' => 'All Vouchers', 'count' => $total_vouchers, 'active' => ($filter_program === 'All' || empty($filter_program))]
                    ];
                    foreach ($program_counts as $p_label => $p_cnt) {
                        $filter_tabs[] = [
                            'val' => $p_label,
                            'label' => $p_label,
                            'count' => $p_cnt,
                            'active' => ($filter_program === $p_label)
                        ];
                    }
                    
                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        
                        $tabUrl = 'sk_voucher.php' . buildQueryString($tab['val'], $search);
                    ?>
                        <a href="<?= $tabUrl ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span class="relative z-10"><?= htmlspecialchars($tab['label']) ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 dark:bg-black/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?> relative z-10"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Search and Action Button -->
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <form method="GET" class="relative flex-1 md:w-56">
                        <?php if ($filter_program !== 'All'): ?>
                            <input type="hidden" name="program" value="<?= htmlspecialchars($filter_program) ?>">
                        <?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search vouchers..." class="w-full pl-8 pr-7 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 placeholder-slate-400 dark:placeholder-zinc-500 text-xs font-semibold focus:outline-none transition-colors border-0">
                        <?php if (!empty($search)): ?>
                            <a href="sk_voucher.php<?= $filter_program !== 'All' ? '?program=' . urlencode($filter_program) : '' ?>" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300 transition-colors" title="Clear search">
                                <i class="ph-bold ph-x text-xs"></i>
                            </a>
                        <?php endif; ?>
                    </form>

                    <?php if (!empty($search)): ?>
                        <a href="sk_voucher.php<?= $filter_program !== 'All' ? '?program=' . urlencode($filter_program) : '' ?>" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Search">
                            <i class="ph-bold ph-x text-xs"></i>
                        </a>
                    <?php endif; ?>

                    <?php if ($can_withdraw): ?>
                        <button id="newVoucherBtn" type="button" onclick="openVoucherModal()" class="btn-yellow-glow-r px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group">
                            <i class="ph-bold ph-plus text-xs transition-transform duration-300 group-hover:scale-110 relative z-10"></i>
                            <span class="relative z-10">Create Voucher</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CONSOLIDATED MINIMAL VOUCHER REGISTRY TABLE (Matching sk_mom.php) -->
            <div class="glass-card rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Minimal Header Strip -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex justify-between items-center transition-colors flex-shrink-0 border-b border-slate-100 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Disbursement Registry</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                            <?= number_format($current_filtered_count) ?>
                        </span>
                    </div>
                    <div class="text-[11px] text-slate-400 dark:text-zinc-500 font-semibold">
                        Barangay <?= htmlspecialchars($user_barangay) ?>
                    </div>
                </div>

                <!-- Table Body Container -->
                <div class="overflow-x-auto flex-1 custom-scrollbar">
                    <table class="w-full text-left border-collapse min-w-[700px]">
                        <thead>
                            <tr class="text-[10px] uppercase font-bold tracking-wider text-slate-400 dark:text-zinc-500 border-b border-slate-100 dark:border-zinc-800/80 bg-slate-50/50 dark:bg-zinc-900/40 select-none">
                                <th class="py-2.5 px-4">Date & Ref</th>
                                <th class="py-2.5 px-4">Program Source</th>
                                <th class="py-2.5 px-4">Purpose & Disbursed By</th>
                                <th class="py-2.5 px-4 text-right">Amount Deducted</th>
                                <th class="py-2.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/60 text-xs">
                            <?php if ($current_filtered_count > 0): ?>
                                <?php foreach ($filtered_vouchers as $v): 
                                    $vId = (int)$v['id'];
                                    $vDate = date('M d, Y', strtotime($v['withdrawal_date']));
                                    $vProg = $v['program'] ?? 'General Fund';
                                    $vPurpose = $v['purpose'] ?? 'Official Disbursement';
                                    $vWithdrawn = $v['withdrawn_by'] ?? 'SK Official';
                                    $vAmount = (float)$v['amount'];
                                    
                                    $voucherJson = htmlspecialchars(json_encode([
                                        'id' => $vId,
                                        'date' => $vDate,
                                        'program' => $vProg,
                                        'purpose' => $vPurpose,
                                        'withdrawn_by' => $vWithdrawn,
                                        'amount' => number_format($vAmount, 2),
                                        'barangay' => $user_barangay,
                                        'created_at' => !empty($v['created_at']) ? date('M d, Y h:i A', strtotime($v['created_at'])) : $vDate
                                    ]), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-800/40 transition-colors group">
                                        
                                        <!-- Date & Ref ID -->
                                        <td class="py-3 px-4 align-middle whitespace-nowrap">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 flex items-center justify-center font-bold text-xs flex-shrink-0 group-hover:scale-105 transition-transform">
                                                    <i class="ph-bold ph-receipt text-sm"></i>
                                                </div>
                                                <div>
                                                    <span class="font-extrabold text-slate-800 dark:text-zinc-100 block leading-tight">
                                                        <?= $vDate ?>
                                                    </span>
                                                    <span class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono">
                                                        #VCH-<?= str_pad($vId, 5, '0', STR_PAD_LEFT) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Program Source -->
                                        <td class="py-3 px-4 align-middle">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[11px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300">
                                                <i class="ph-bold ph-folder text-green-600 dark:text-green-500"></i>
                                                <span class="truncate max-w-[220px]"><?= htmlspecialchars($vProg) ?></span>
                                            </span>
                                        </td>

                                        <!-- Purpose & Disbursed By -->
                                        <td class="py-3 px-4 align-middle">
                                            <div class="max-w-md">
                                                <p class="font-bold text-slate-800 dark:text-zinc-100 line-clamp-1 leading-tight mb-0.5">
                                                    <?= htmlspecialchars($vPurpose) ?>
                                                </p>
                                                <span class="text-[10px] text-slate-400 dark:text-zinc-500 block truncate font-medium">
                                                    Authorized by <?= htmlspecialchars($vWithdrawn) ?>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Amount Deducted -->
                                        <td class="py-3 px-4 align-middle text-right whitespace-nowrap">
                                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-black bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 border-0">
                                                <i class="ph-bold ph-arrow-down-right"></i>
                                                <span>₱<?= number_format($vAmount, 2) ?></span>
                                            </div>
                                        </td>

                                        <!-- Minimal Ghost Action Buttons -->
                                        <td class="py-3 px-4 text-right align-middle whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button" onclick="openViewModal(<?= $voucherJson ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect" title="View Voucher Slip">
                                                    <i class="ph-bold ph-eye text-xs"></i>
                                                </button>
                                                <button type="button" onclick="printVoucherSlip(<?= $voucherJson ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect" title="Print Slip">
                                                    <i class="ph-bold ph-printer text-xs"></i>
                                                </button>
                                                <?php if ($can_withdraw): ?>
                                                    <a href="sk_voucher.php?delete_id=<?= $vId ?><?= buildQueryString($filter_program, $search) ?>" onclick="return confirm('Confirm Voucher Cancellation: Permanently delete this voucher and refund ₱<?= number_format($vAmount, 2) ?> back to <?= htmlspecialchars(addslashes($vProg)) ?>?')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Refund & Delete Voucher">
                                                        <i class="ph-bold ph-trash text-xs"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-zinc-800 flex items-center justify-center mx-auto mb-2 text-slate-400 dark:text-zinc-500 text-xl">
                                            <i class="ph-bold ph-receipt"></i>
                                        </div>
                                        <p class="text-xs font-semibold text-slate-600 dark:text-zinc-400">No voucher records found</p>
                                        <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">No disbursements match the selected filter or search keyword.</p>
                                        <?php if (!empty($search) || $filter_program !== 'All'): ?>
                                            <a href="sk_voucher.php" class="mt-3 inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
                                                <span>Clear Filters</span>
                                            </a>
                                        <?php elseif ($can_withdraw): ?>
                                            <button type="button" onclick="openVoucherModal()" class="btn-yellow-glow-r mt-3 inline-flex items-center gap-1 px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white text-xs font-bold transition-all tap-effect">
                                                <i class="ph-bold ph-plus text-xs relative z-10"></i>
                                                <span class="relative z-10">Create First Voucher</span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
            
        </div>
    </main>

    <!-- CREATE VOUCHER MODAL (Reference: sk_mom.php) -->
    <?php if ($can_withdraw): ?>
    <div id="voucherModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeVoucherModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-xl md:max-w-2xl w-full p-5 md:p-7 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800" id="voucherModalBox">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-5 border-b border-slate-100 dark:border-zinc-800 pb-3.5">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="ph-fill ph-receipt"></i>
                    </div>
                    <div>
                        <h2 class="text-base md:text-lg font-black text-slate-900 dark:text-white tracking-tight">Authorize Disbursement Voucher</h2>
                        <p class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium">Barangay <?= htmlspecialchars($user_barangay) ?> • Official Financial Record</p>
                    </div>
                </div>
                <button type="button" onclick="closeVoucherModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 flex items-center justify-center tap-effect transition-all duration-200 active:scale-[0.95]" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Form -->
            <form id="createVoucherForm" action="sk_voucher.php" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-3.5 max-h-[75vh] overflow-y-auto custom-scrollbar pr-1">
                <input type="hidden" name="create_voucher" value="1">

                <!-- Budget Program Selection -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">
                        Select Budget Program Source <span class="text-rose-500">*</span>
                    </label>
                    <select name="budget_id" id="budgetSelect" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 text-xs md:text-sm font-semibold focus:outline-none transition-colors border-0 cursor-pointer" required onchange="updateSelectedBalance(this)">
                        <option value="" disabled selected>Choose active program...</option>
                        <?php foreach ($active_budgets as $b): ?>
                            <option value="<?= $b['id'] ?>" data-balance="<?= (float)$b['amount'] ?>">
                                <?= htmlspecialchars($b['program']) ?> — (₱<?= number_format($b['amount'], 2) ?> remaining)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p id="balanceNotice" class="text-[10px] text-slate-400 dark:text-zinc-500 mt-1 font-medium"></p>
                </div>

                <!-- Purpose -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">
                        Disbursement Purpose / Expense Item <span class="text-rose-500">*</span>
                    </label>
                    <textarea name="purpose" rows="2" placeholder="e.g. Purchase of sports equipment for Inter-Purok League, Honoraria..." class="w-full px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 text-xs md:text-sm font-semibold focus:outline-none transition-colors border-0 resize-none custom-scrollbar" required></textarea>
                </div>

                <!-- Date -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">
                        Withdrawal Date <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" name="withdrawal_date" value="<?= date('Y-m-d') ?>" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 text-xs md:text-sm font-semibold focus:outline-none transition-colors border-0" required>
                </div>

                <!-- Amount -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">
                        Disbursement Amount (₱) <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 font-bold text-xs">₱</span>
                        <input type="number" step="0.01" min="1" name="amount" id="voucherAmountInput" placeholder="0.00" class="w-full pl-7 pr-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 text-xs md:text-sm font-semibold focus:outline-none transition-colors border-0" required>
                    </div>
                </div>

                <!-- Information Notice -->
                <div class="md:col-span-2 p-3 rounded-xl bg-amber-50/80 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 flex items-start gap-2.5 border-0">
                    <i class="ph-bold ph-info text-base flex-shrink-0 mt-0.5"></i>
                    <div class="text-[11px] leading-relaxed">
                        <p class="font-bold">Automated Budget Deduction</p>
                        <p class="text-slate-500 dark:text-zinc-400 mt-0.5">
                            Upon approval, this amount will immediately be subtracted from the selected program's available balance. Both Chairperson and Treasurer signatures will be recorded for COA audit compliance.
                        </p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="md:col-span-2 flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800 mt-1">
                    <button type="button" onclick="closeVoucherModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white font-bold text-xs md:text-sm tap-effect transition-all duration-200 active:scale-[0.97]">
                        Cancel
                    </button>
                    <button type="submit" id="submitVoucherBtn" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-200 text-xs md:text-sm tap-effect active:scale-[0.97] flex items-center justify-center gap-2 shadow-none group">
                        <span class="relative z-10">Authorize & Disburse</span>
                        <i class="ph-bold ph-check-circle text-xs relative z-10 group-hover:scale-110 transition-transform"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- VIEW VOUCHER DETAILS / SLIP MODAL -->
    <div id="viewVoucherModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeViewModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-lg w-full p-5 md:p-7 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800" id="viewVoucherModalBox">
            
            <!-- Slip Header -->
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 pb-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm">
                        <i class="ph-fill ph-receipt"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white leading-tight">Official Voucher Slip</h3>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono" id="modalVoucherRef">#VCH-00000</p>
                    </div>
                </div>
                <button type="button" onclick="closeViewModal()" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 flex items-center justify-center tap-effect transition-all" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Printable Slip Content -->
            <div id="printableSlip" class="space-y-3.5 text-xs">
                
                <div class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-100 dark:border-zinc-800 flex justify-between items-center">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Total Amount Disbursed</span>
                        <span class="text-xl font-black text-rose-600 dark:text-rose-400 tracking-tight" id="modalVoucherAmount">₱0.00</span>
                    </div>
                    <span class="px-2.5 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-400">
                        Disbursed
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-2.5 text-xs">
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-800/40">
                        <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Withdrawal Date</span>
                        <span class="font-bold text-slate-800 dark:text-zinc-200" id="modalVoucherDate">-</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-800/40">
                        <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Barangay Jurisdiction</span>
                        <span class="font-bold text-slate-800 dark:text-zinc-200" id="modalVoucherBrgy">-</span>
                    </div>
                </div>

                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-800/40">
                    <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Budget Program Source</span>
                    <span class="font-bold text-slate-800 dark:text-zinc-200 flex items-center gap-1.5 mt-0.5" id="modalVoucherProgram">
                        <i class="ph-bold ph-folder text-green-600 dark:text-green-500"></i>
                        <span>-</span>
                    </span>
                </div>

                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-800/40">
                    <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Purpose & Narrative</span>
                    <p class="font-semibold text-slate-700 dark:text-zinc-300 mt-0.5 leading-relaxed" id="modalVoucherPurpose">-</p>
                </div>

                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-800/40 flex justify-between items-center">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Authorized Official</span>
                        <span class="font-bold text-slate-800 dark:text-zinc-200" id="modalVoucherWithdrawn">-</span>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Status</span>
                        <span class="text-[10px] font-black text-green-600 dark:text-green-500 flex items-center gap-1">
                            <i class="ph-fill ph-check-circle"></i> Deducted & Reconciled
                        </span>
                    </div>
                </div>

            </div>

            <!-- Footer Actions -->
            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-zinc-800 mt-4 no-print">
                <button type="button" onclick="window.print()" class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold text-xs tap-effect flex items-center gap-1.5 transition-all">
                    <i class="ph-bold ph-printer"></i> Print Slip
                </button>
                <button type="button" onclick="closeViewModal()" class="px-4 py-1.5 rounded-xl bg-slate-900 hover:bg-black dark:bg-white dark:hover:bg-zinc-200 text-white dark:text-zinc-900 font-bold text-xs tap-effect transition-all">
                    Done
                </button>
            </div>

        </div>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Android Style - Reference: sk_mom.php) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 h-[68px] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-2xl z-50 flex justify-around items-center px-2 pb-[env(safe-area-inset-bottom)] transition-colors border-0">
        <a href="sk_dashboard.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-squares-four text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Dashboard</span>
        </a>
        <a href="sk2_budget.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-coins text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Budget</span>
        </a>
        <a href="sk_voucher.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-receipt text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Vouchers</span>
        </a>
        <a href="sk_mom.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-file-text text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Sessions</span>
        </a>
    </nav>

    <!-- UI Scripts -->
    <script>
        // Drawer toggle
        function toggleMobileMenu() {
            const sidebar = document.getElementById('tala-bulletproof-sk-sidebar') || document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            if (!sidebar) return;
            if (sidebar.classList.contains('mobile-open') || sidebar.classList.contains('active')) {
                sidebar.classList.remove('mobile-open', 'active');
                if (overlay) overlay.classList.remove('active');
            } else {
                sidebar.classList.add('mobile-open', 'active');
                if (overlay) overlay.classList.add('active');
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
            localStorage.theme = isDark ? 'dark' : 'light';
            syncHeaderThemeIcon(isDark);
        }
        syncHeaderThemeIcon(document.documentElement.classList.contains('dark'));

        // Balance notice updater in Create Voucher modal
        function updateSelectedBalance(select) {
            const opt = select.options[select.selectedIndex];
            const notice = document.getElementById('balanceNotice');
            const amtInput = document.getElementById('voucherAmountInput');
            if (!opt || !notice) return;
            const bal = parseFloat(opt.getAttribute('data-balance') || 0);
            notice.textContent = 'Available to withdraw: ₱' + bal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (amtInput) {
                amtInput.max = bal;
            }
        }

        // Create Voucher Modal
        function openVoucherModal() {
            const modal = document.getElementById('voucherModalOverlay');
            const box = document.getElementById('voucherModalBox');
            if (!modal || !box) return;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            requestAnimationFrame(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                box.classList.remove('scale-95', 'opacity-0');
                box.classList.add('scale-100', 'opacity-100');
            });
        }

        function closeVoucherModal() {
            const modal = document.getElementById('voucherModalOverlay');
            const box = document.getElementById('voucherModalBox');
            if (!modal || !box) return;

            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            box.classList.remove('scale-100', 'opacity-100');
            box.classList.add('scale-95', 'opacity-0');

            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }

        // View Voucher Modal
        function openViewModal(data) {
            const modal = document.getElementById('viewVoucherModalOverlay');
            const box = document.getElementById('viewVoucherModalBox');
            if (!modal || !box || !data) return;

            document.getElementById('modalVoucherRef').textContent = '#VCH-' + String(data.id).padStart(5, '0');
            document.getElementById('modalVoucherAmount').textContent = '₱' + data.amount;
            document.getElementById('modalVoucherDate').textContent = data.date;
            document.getElementById('modalVoucherBrgy').textContent = 'Barangay ' + data.barangay;
            document.getElementById('modalVoucherProgram').querySelector('span').textContent = data.program;
            document.getElementById('modalVoucherPurpose').textContent = data.purpose;
            document.getElementById('modalVoucherWithdrawn').textContent = data.withdrawn_by;

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            requestAnimationFrame(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                box.classList.remove('scale-95', 'opacity-0');
                box.classList.add('scale-100', 'opacity-100');
            });
        }

        function closeViewModal() {
            const modal = document.getElementById('viewVoucherModalOverlay');
            const box = document.getElementById('viewVoucherModalBox');
            if (!modal || !box) return;

            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            box.classList.remove('scale-100', 'opacity-100');
            box.classList.add('scale-95', 'opacity-0');

            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }

        function printVoucherSlip(data) {
            openViewModal(data);
            setTimeout(() => {
                window.print();
            }, 350);
        }

        // Escape Key Listener
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeVoucherModal();
                closeViewModal();
            }
        });
    </script>
</body>
</html>