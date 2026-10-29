<?php
// sk_resolution.php - Sangguniang Kabataan Legislative Resolutions Archive
// Standardized to TALA OS Design System (Reference: sk_voucher.php & sk_mom.php)
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

// Only Chair, Sec, Treas (or Municipal Admin) can upload and delete legislative resolutions
$can_upload = in_array($user_position, ['SK Chairperson', 'SK Secretary', 'SK Treasurer']) || ($user_role === 'admin');

// Normalized Barangay Name Variants
$clean_brgy = str_ireplace('Barangay ', '', $user_barangay);
$brgy_variants = array_values(array_unique([$user_barangay, $clean_brgy, 'Barangay ' . $clean_brgy]));
$brgy_placeholders = implode(',', array_fill(0, count($brgy_variants), '?'));

// Filter & Search Parameters
$filter_year = trim($_GET['year'] ?? 'All');
$search = trim($_GET['search'] ?? '');

// Flash Message Handling
$flash_msg = $_SESSION['flash_msg'] ?? '';
$flash_type = $_SESSION['flash_type'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_type']);

// Build Redirect Query Helper
function buildQueryString($year, $search) {
    $params = [];
    if (!empty($year) && $year !== 'All') $params['year'] = $year;
    if (!empty($search)) $params['search'] = $search;
    return !empty($params) ? '?' . http_build_query($params) : '';
}

// Helper Function: Free OCR via OCR.Space API
function extractTextViaFreeOCR($file_path) {
    $api_key = 'helloworld'; 
    $url = 'https://api.ocr.space/parse/image';
    
    if (!function_exists('curl_file_create') || !file_exists($file_path)) {
        return '';
    }
    
    $cfile = curl_file_create($file_path);
    $postParams = [
        'apikey' => $api_key,
        'file' => $cfile,
        'language' => 'eng',
        'isOverlayRequired' => 'false',
        'OCREngine' => '2'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postParams);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data['ParsedResults'][0]['ParsedText'])) {
            return strtolower($data['ParsedResults'][0]['ParsedText']);
        }
    }
    return '';
}

// --- CRUD: DELETE RESOLUTION ---
if (isset($_GET['delete_id']) && $can_upload) {
    $del_id = (int)$_GET['delete_id'];
    try {
        $stmt = $pdo->prepare("SELECT file_path, resolution_number FROM resolutions WHERE id = ? AND barangay IN ($brgy_placeholders)");
        $stmt->execute(array_merge([$del_id], $brgy_variants));
        $del_res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($del_res) {
            // Delete physical file
            if (!empty($del_res['file_path']) && file_exists($del_res['file_path'])) {
                @unlink($del_res['file_path']);
            }
            
            // Delete signatures and resolution record
            $pdo->prepare("DELETE FROM resolution_signatures WHERE resolution_id = ?")->execute([$del_id]);
            $pdo->prepare("DELETE FROM resolutions WHERE id = ?")->execute([$del_id]);
            
            $_SESSION['flash_msg'] = "Resolution #" . htmlspecialchars($del_res['resolution_number']) . " and its verified signatures were securely deleted.";
            $_SESSION['flash_type'] = "success";
        } else {
            $_SESSION['flash_msg'] = "Resolution record not found or access denied.";
            $_SESSION['flash_type'] = "error";
        }
    } catch (Exception $e) {
        $_SESSION['flash_msg'] = "Error deleting resolution: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
    }
    header("Location: sk_resolution.php" . buildQueryString($filter_year, $search));
    exit();
}

// --- CRUD: LODGE RESOLUTION & RUN OCR ---
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && isset($_POST['upload_resolution']) && $can_upload) {
    $res_number = trim($_POST['resolution_number'] ?? '');
    $res_title = trim($_POST['title'] ?? '');
    $date_passed = trim($_POST['date_passed'] ?? date('Y-m-d'));
    
    if (empty($res_number) || empty($res_title) || empty($date_passed)) {
        $_SESSION['flash_msg'] = "Please provide the resolution number, title, and date passed.";
        $_SESSION['flash_type'] = "error";
    } elseif (!isset($_FILES['res_file']) || $_FILES['res_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_msg'] = "Please upload a valid PDF or scanned image file.";
        $_SESSION['flash_type'] = "error";
    } else {
        $upload_dir = 'uploads/resolutions/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['res_file']['name'], PATHINFO_EXTENSION));
        $clean_no = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $res_number);
        $new_filename = 'RES_' . $clean_no . '_' . time() . '.' . $file_ext;
        $target_path = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['res_file']['tmp_name'], $target_path)) {
            try {
                // 1. Insert Document Record
                $stmt = $pdo->prepare("INSERT INTO resolutions (barangay, resolution_number, title, date_passed, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_barangay, $res_number, $res_title, $date_passed, $target_path, $user_name]);
                $res_id = (int)$pdo->lastInsertId();

                // 2. OCR Automated Signature Extraction
                $extracted_text = extractTextViaFreeOCR($target_path);
                $sig_count = 0;
                
                if (!empty($extracted_text)) {
                    $officials_stmt = $pdo->prepare("SELECT full_name, sk_position FROM admins WHERE barangay_assigned IN ($brgy_placeholders) AND status = 'approved'");
                    $officials_stmt->execute($brgy_variants);
                    $db_officials = $officials_stmt->fetchAll(PDO::FETCH_ASSOC);

                    $att_stmt = $pdo->prepare("INSERT INTO resolution_signatures (resolution_id, official_name, position, has_signed) VALUES (?, ?, ?, 1)");
                    
                    foreach ($db_officials as $off) {
                        $name_parts = explode(' ', strtolower(trim($off['full_name'])));
                        $first_name = $name_parts[0] ?? '';
                        $last_name = end($name_parts) ?? '';
                        
                        if (!empty($first_name) && !empty($last_name) &&
                            strpos($extracted_text, $first_name) !== false && 
                            strpos($extracted_text, $last_name) !== false) {
                            $att_stmt->execute([$res_id, $off['full_name'], $off['sk_position']]);
                            $sig_count++;
                        }
                    }
                    
                    if ($sig_count > 0) {
                        $_SESSION['flash_msg'] = "Resolution lodged! OCR automatically verified $sig_count signature(s).";
                    } else {
                        $_SESSION['flash_msg'] = "Resolution lodged! Document archived, but no matching official signatures were detected via OCR.";
                    }
                } else {
                    $_SESSION['flash_msg'] = "Resolution lodged successfully! Document archived (OCR skipped or unreadable).";
                }
                $_SESSION['flash_type'] = "success";
            } catch (Exception $e) {
                $_SESSION['flash_msg'] = "Error saving resolution: " . $e->getMessage();
                $_SESSION['flash_type'] = "error";
            }
        } else {
            $_SESSION['flash_msg'] = "Failed to store uploaded document file.";
            $_SESSION['flash_type'] = "error";
        }
    }
    header("Location: sk_resolution.php" . buildQueryString($filter_year, $search));
    exit();
}

// --- KPI STATISTICS ---
// 1. Total Resolutions
$cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM resolutions WHERE barangay IN ($brgy_placeholders)");
$cnt_stmt->execute($brgy_variants);
$total_resolutions = (int)$cnt_stmt->fetchColumn();

// 2. Verified Signatures Count
$sig_cnt_stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM resolution_signatures s 
    JOIN resolutions r ON s.resolution_id = r.id 
    WHERE r.barangay IN ($brgy_placeholders) AND s.has_signed = 1
");
$sig_cnt_stmt->execute($brgy_variants);
$total_signatures = (int)$sig_cnt_stmt->fetchColumn();

// 3. Current Year Resolutions Enacted
$curr_year = date('Y');
$yr_stmt = $pdo->prepare("SELECT COUNT(*) FROM resolutions WHERE barangay IN ($brgy_placeholders) AND YEAR(date_passed) = ?");
$yr_stmt->execute(array_merge($brgy_variants, [$curr_year]));
$resolutions_this_year = (int)$yr_stmt->fetchColumn();

// Fetch All Resolutions for Table
$res_stmt = $pdo->prepare("
    SELECT r.*, 
           (SELECT COUNT(*) FROM resolution_signatures s WHERE s.resolution_id = r.id AND s.has_signed = 1) AS sig_count 
    FROM resolutions r 
    WHERE r.barangay IN ($brgy_placeholders) 
    ORDER BY r.date_passed DESC, r.id DESC
");
$res_stmt->execute($brgy_variants);
$all_resolutions = $res_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Signatures for each resolution
$signatures_by_res = [];
if (!empty($all_resolutions)) {
    $res_ids = array_column($all_resolutions, 'id');
    $placeholders = implode(',', array_fill(0, count($res_ids), '?'));
    $sig_fetch = $pdo->prepare("SELECT resolution_id, official_name, position FROM resolution_signatures WHERE resolution_id IN ($placeholders) AND has_signed = 1");
    $sig_fetch->execute($res_ids);
    while ($row = $sig_fetch->fetch(PDO::FETCH_ASSOC)) {
        $signatures_by_res[$row['resolution_id']][] = [
            'official_name' => $row['official_name'],
            'position' => $row['position']
        ];
    }
}

// Year tab list & counts
$year_counts = [];
foreach ($all_resolutions as $r) {
    $y = !empty($r['date_passed']) ? date('Y', strtotime($r['date_passed'])) : date('Y');
    if (!isset($year_counts[$y])) {
        $year_counts[$y] = 0;
    }
    $year_counts[$y]++;
}
krsort($year_counts);

// Filter for Table View
$filtered_resolutions = [];
foreach ($all_resolutions as $r) {
    $r_year = !empty($r['date_passed']) ? date('Y', strtotime($r['date_passed'])) : date('Y');
    if ($filter_year !== 'All' && $r_year !== $filter_year) {
        continue;
    }
    
    if (!empty($search)) {
        $search_lower = strtolower($search);
        $res_no_str = strtolower($r['resolution_number'] ?? '');
        $title_str = strtolower($r['title'] ?? '');
        $date_str = strtolower(date('F d, Y', strtotime($r['date_passed'])));
        $uploader_str = strtolower($r['uploaded_by'] ?? '');
        
        $match = (strpos($res_no_str, $search_lower) !== false ||
                  strpos($title_str, $search_lower) !== false ||
                  strpos($date_str, $search_lower) !== false ||
                  strpos($uploader_str, $search_lower) !== false);
                  
        if (!$match) continue;
    }
    
    $filtered_resolutions[] = $r;
}
$current_filtered_count = count($filtered_resolutions);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Legislative Resolutions | TALA OS</title>
    
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
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- Role-Appropriate SK Sidebar -->
    <?php include 'sidebar_sk.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA (Reference: sk_voucher.php & sk_mom.php) -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px and fits viewport smoothly -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-3 md:space-y-4">
            
            <!-- HEADER SECTION (Clean H1 title only, No corn logo, 3 KPI Badges - Matching sk_voucher.php) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div class="flex items-center gap-2.5">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden w-9 h-9 rounded-xl glass-card flex items-center justify-center text-slate-600 dark:text-zinc-300 hover:text-slate-900 dark:hover:text-white transition-colors tap-effect flex-shrink-0" aria-label="Toggle navigation drawer">
                        <i class="ph-bold ph-list text-lg"></i>
                    </button>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                        Resolutions
                    </h1>
                    <?php if (!$can_upload): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border-0">
                            <i class="ph-bold ph-shield"></i> View-Only (<?= htmlspecialchars($user_position) ?>)
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <!-- KPI 1: Total Resolutions -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-scroll text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Resolutions</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_resolutions) ?></span>
                        </div>
                    </div>

                    <!-- KPI 2: Verified Signatures -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-signature text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Verified Signatories</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_signatures) ?></span>
                        </div>
                    </div>

                    <!-- KPI 3: Enacted This Year -->
                    <div class="glass-card card-green-glow-bl px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-slate-200 dark:group-hover:bg-zinc-700 transition-all duration-200 relative z-10">
                            <i class="ph-fill ph-calendar-check text-base"></i>
                        </div>
                        <div class="min-w-0 relative z-10">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Enacted (<?= $curr_year ?>)</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($resolutions_this_year) ?></span>
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

            <!-- FILTER, SEARCH & ACTION CONTROL BAR (Matching sk_voucher.php) -->
            <div class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col md:flex-row items-center justify-between gap-2.5">
                <!-- Year Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto custom-scrollbar py-1 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['val' => 'All', 'label' => 'All Resolutions', 'count' => $total_resolutions, 'active' => ($filter_year === 'All' || empty($filter_year))]
                    ];
                    foreach ($year_counts as $y_label => $y_cnt) {
                        $filter_tabs[] = [
                            'val' => (string)$y_label,
                            'label' => 'Year ' . $y_label,
                            'count' => $y_cnt,
                            'active' => ($filter_year === (string)$y_label)
                        ];
                    }
                    
                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        
                        $tabUrl = 'sk_resolution.php' . buildQueryString($tab['val'], $search);
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
                        <?php if ($filter_year !== 'All'): ?>
                            <input type="hidden" name="year" value="<?= htmlspecialchars($filter_year) ?>">
                        <?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search resolutions..." class="w-full pl-8 pr-7 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 placeholder-slate-400 dark:placeholder-zinc-500 text-xs font-semibold focus:outline-none transition-colors border-0">
                        <?php if (!empty($search)): ?>
                            <a href="sk_resolution.php<?= $filter_year !== 'All' ? '?year=' . urlencode($filter_year) : '' ?>" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300 transition-colors" title="Clear search">
                                <i class="ph-bold ph-x text-xs"></i>
                            </a>
                        <?php endif; ?>
                    </form>

                    <?php if (!empty($search)): ?>
                        <a href="sk_resolution.php<?= $filter_year !== 'All' ? '?year=' . urlencode($filter_year) : '' ?>" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Search">
                            <i class="ph-bold ph-x text-xs"></i>
                        </a>
                    <?php endif; ?>

                    <?php if ($can_upload): ?>
                        <button id="newLodgeBtn" type="button" onclick="openResolutionModal()" class="btn-yellow-glow-r px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group">
                            <i class="ph-bold ph-plus text-xs transition-transform duration-300 group-hover:scale-110 relative z-10"></i>
                            <span class="relative z-10">Lodge Resolution</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CONSOLIDATED MINIMAL LEGISLATIVE ARCHIVE TABLE (Matching sk_voucher.php) -->
            <div class="glass-card rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Minimal Header Strip -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex justify-between items-center transition-colors flex-shrink-0 border-b border-slate-100 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Legislative Archive</span>
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
                                <th class="py-2.5 px-4">Date & Resolution No.</th>
                                <th class="py-2.5 px-4">Title & Legislative Intent</th>
                                <th class="py-2.5 px-4 text-center">OCR Signatories</th>
                                <th class="py-2.5 px-4 text-center">Document</th>
                                <th class="py-2.5 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-zinc-800/60 text-xs">
                            <?php if ($current_filtered_count > 0): ?>
                                <?php foreach ($filtered_resolutions as $r): 
                                    $rId = (int)$r['id'];
                                    $rDate = date('M d, Y', strtotime($r['date_passed']));
                                    $rNo = $r['resolution_number'] ?? 'N/A';
                                    $rTitle = $r['title'] ?? 'Legislative Resolution';
                                    $rUploader = $r['uploaded_by'] ?? 'SK Official';
                                    $rFile = $r['file_path'] ?? '';
                                    $sigCount = (int)($r['sig_count'] ?? 0);
                                    
                                    $sigs = $signatures_by_res[$rId] ?? [];
                                    $sigsJson = htmlspecialchars(json_encode([
                                        'id' => $rId,
                                        'number' => $rNo,
                                        'title' => $rTitle,
                                        'date' => $rDate,
                                        'signatories' => $sigs
                                    ]), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-800/40 transition-colors group">
                                        
                                        <!-- Date & Resolution Number -->
                                        <td class="py-3 px-4 align-middle whitespace-nowrap">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 flex items-center justify-center font-bold text-xs flex-shrink-0 group-hover:scale-105 transition-transform">
                                                    <i class="ph-bold ph-scroll text-sm"></i>
                                                </div>
                                                <div>
                                                    <span class="font-extrabold text-slate-800 dark:text-zinc-100 block leading-tight">
                                                        <?= $rDate ?>
                                                    </span>
                                                    <span class="text-[10px] text-green-600 dark:text-green-500 font-bold font-mono">
                                                        Res. No. <?= htmlspecialchars($rNo) ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Title & Legislative Intent -->
                                        <td class="py-3 px-4 align-middle">
                                            <div class="max-w-lg">
                                                <p class="font-bold text-slate-800 dark:text-zinc-100 line-clamp-2 leading-tight mb-0.5">
                                                    <?= htmlspecialchars($rTitle) ?>
                                                </p>
                                                <span class="text-[10px] text-slate-400 dark:text-zinc-500 block truncate font-medium">
                                                    Lodged by <?= htmlspecialchars($rUploader) ?>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- OCR Signatories -->
                                        <td class="py-3 px-4 align-middle text-center whitespace-nowrap">
                                            <?php if ($sigCount > 0): ?>
                                                <button type="button" onclick="openSignaturesModal(<?= $sigsJson ?>)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400 hover:bg-green-100 dark:hover:bg-green-500/20 transition-colors tap-effect" title="View Verified Signatories">
                                                    <i class="ph-fill ph-signature text-xs"></i>
                                                    <span><?= $sigCount ?> Verified</span>
                                                </button>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-400 dark:text-zinc-500">
                                                    <i class="ph-bold ph-clock text-[10px]"></i>
                                                    <span>No OCR matches</span>
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Document File -->
                                        <td class="py-3 px-4 align-middle text-center whitespace-nowrap">
                                            <?php if (!empty($rFile) && file_exists($rFile)): ?>
                                                <a href="<?= htmlspecialchars($rFile) ?>" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 hover:bg-[#15803d] hover:text-white dark:hover:bg-[#16a34a] dark:hover:text-white transition-colors tap-effect" title="View Document File">
                                                    <i class="ph-bold ph-file-pdf text-xs"></i>
                                                    <span>View PDF</span>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-[10px] text-slate-400 dark:text-zinc-500 italic">No file attached</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Minimal Ghost Action Buttons (Matching sk_voucher.php) -->
                                        <td class="py-3 px-4 text-right align-middle whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1">
                                                <?php if ($sigCount > 0): ?>
                                                    <button type="button" onclick="openSignaturesModal(<?= $sigsJson ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect" title="View Signatories">
                                                        <i class="ph-bold ph-signature text-xs"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!empty($rFile) && file_exists($rFile)): ?>
                                                    <a href="<?= htmlspecialchars($rFile) ?>" target="_blank" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-green-600 dark:hover:text-green-400 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect" title="Open Full Document">
                                                        <i class="ph-bold ph-arrow-square-out text-xs"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($can_upload): ?>
                                                    <a href="sk_resolution.php?delete_id=<?= $rId ?><?= buildQueryString($filter_year, $search) ?>" onclick="return confirm('Confirm Deletion: Permanently delete Resolution No. <?= htmlspecialchars(addslashes($rNo)) ?> and its verified signatures?')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Delete Resolution">
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
                                            <i class="ph-bold ph-scroll"></i>
                                        </div>
                                        <p class="text-xs font-semibold text-slate-600 dark:text-zinc-400">No resolution records found</p>
                                        <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-0.5">No legislative documents match the selected filter or search keyword.</p>
                                        <?php if (!empty($search) || $filter_year !== 'All'): ?>
                                            <a href="sk_resolution.php" class="mt-3 inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
                                                <span>Clear Filters</span>
                                            </a>
                                        <?php elseif ($can_upload): ?>
                                            <button type="button" onclick="openResolutionModal()" class="btn-yellow-glow-r mt-3 inline-flex items-center gap-1 px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white text-xs font-bold transition-all tap-effect">
                                                <i class="ph-bold ph-plus text-xs relative z-10"></i>
                                                <span class="relative z-10">Lodge First Resolution</span>
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

    <!-- LODGE RESOLUTION MODAL (Reference: sk_voucher.php) -->
    <?php if ($can_upload): ?>
    <div id="resolutionModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeResolutionModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-xl md:max-w-2xl w-full p-5 md:p-7 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800" id="resolutionModalBox">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-5 border-b border-slate-100 dark:border-zinc-800 pb-3.5">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center text-lg flex-shrink-0">
                        <i class="ph-fill ph-scroll"></i>
                    </div>
                    <div>
                        <h2 class="text-base md:text-lg font-black text-slate-900 dark:text-white tracking-tight">Lodge New Resolution</h2>
                        <p class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium">Barangay <?= htmlspecialchars($user_barangay) ?> • Official Legislative Archive</p>
                    </div>
                </div>
                <button type="button" onclick="closeResolutionModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 flex items-center justify-center tap-effect transition-all duration-200 active:scale-[0.95]" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Form -->
            <form id="createResolutionForm" action="sk_resolution.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-3.5 max-h-[75vh] overflow-y-auto custom-scrollbar pr-1">
                <input type="hidden" name="upload_resolution" value="1">

                <!-- Resolution Number -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">
                        Resolution Number <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="resolution_number" placeholder="e.g. 2026-001 or 04-2026" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 text-xs md:text-sm font-semibold focus:outline-none transition-colors border-0" required>
                </div>

                <!-- Date Passed -->
                <div>
                    <label class="block text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">
                        Date Enacted / Passed <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" name="date_passed" value="<?= date('Y-m-d') ?>" class="w-full px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 text-xs md:text-sm font-semibold focus:outline-none transition-colors border-0" required>
                </div>

                <!-- Resolution Title -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">
                        Resolution Title / Legislative Purpose <span class="text-rose-500">*</span>
                    </label>
                    <textarea name="title" rows="2" placeholder="e.g. A Resolution approving the Annual Barangay Youth Investment Program (ABYIP)..." class="w-full px-3.5 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-800 dark:text-zinc-100 text-xs md:text-sm font-semibold focus:outline-none transition-colors border-0 resize-none custom-scrollbar" required></textarea>
                </div>

                <!-- PDF File Upload with OCR Box -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">
                        Upload Document File (.pdf / image) <span class="text-rose-500">*</span>
                    </label>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-100 dark:border-zinc-800">
                        <input type="file" name="res_file" accept=".pdf,image/*" class="w-full text-xs text-slate-600 dark:text-zinc-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:font-bold file:text-xs file:bg-[#15803d] file:text-white hover:file:bg-[#166534] dark:file:bg-[#16a34a] dark:hover:file:bg-[#15803d] transition-colors cursor-pointer" required>
                    </div>
                </div>

                <!-- Information Notice regarding Kryptiah AI OCR -->
                <div class="md:col-span-2 p-3 rounded-xl bg-green-50/80 dark:bg-green-500/10 text-green-800 dark:text-green-300 flex items-start gap-2.5 border-0">
                    <i class="ph-bold ph-magic-wand text-base flex-shrink-0 mt-0.5 text-green-600 dark:text-green-400"></i>
                    <div class="text-[11px] leading-relaxed">
                        <p class="font-bold text-green-900 dark:text-green-200">Kryptiah AI • Automated Signature Verification</p>
                        <p class="text-slate-600 dark:text-zinc-400 mt-0.5">
                            Uploaded documents are automatically scanned for signatories matching your approved SK Roster. Verified signatures will be linked directly to this resolution record.
                        </p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="md:col-span-2 flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800 mt-1">
                    <button type="button" onclick="closeResolutionModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white font-bold text-xs md:text-sm tap-effect transition-all duration-200 active:scale-[0.97]">
                        Cancel
                    </button>
                    <button type="submit" id="submitResolutionBtn" class="btn-yellow-glow-r bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-200 text-xs md:text-sm tap-effect active:scale-[0.97] flex items-center justify-center gap-2 shadow-none group">
                        <span class="relative z-10">Lodge & Scan</span>
                        <i class="ph-bold ph-check-circle text-xs relative z-10 group-hover:scale-110 transition-transform"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- VIEW SIGNATORIES MODAL (Reference: sk_voucher.php) -->
    <div id="signaturesModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeSignaturesModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-lg w-full p-5 md:p-7 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800" id="signaturesModalBox">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 pb-3 mb-4">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-slate-100 dark:bg-zinc-800 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm">
                        <i class="ph-fill ph-signature"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white leading-tight">Verified Signatories</h3>
                        <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-mono" id="modalResNumber">Res. No. -</p>
                    </div>
                </div>
                <button type="button" onclick="closeSignaturesModal()" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 flex items-center justify-center tap-effect transition-all" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Resolution Title Context -->
            <div class="p-3 rounded-2xl bg-slate-50 dark:bg-zinc-800/60 border border-slate-100 dark:border-zinc-800 mb-3.5">
                <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-zinc-500 block">Resolution Title</span>
                <p class="text-xs font-bold text-slate-800 dark:text-zinc-200 mt-0.5 line-clamp-2" id="modalResTitle">-</p>
            </div>

            <!-- Signatories List -->
            <div class="space-y-2 max-h-[50vh] overflow-y-auto custom-scrollbar pr-1" id="modalSignatoriesList">
                <!-- Dynamically populated via JavaScript -->
            </div>

            <!-- Footer Actions -->
            <div class="flex justify-between items-center pt-3 border-t border-slate-100 dark:border-zinc-800 mt-4">
                <span class="text-[11px] font-semibold text-slate-400 dark:text-zinc-500 flex items-center gap-1">
                    <i class="ph-fill ph-check-circle text-green-600 dark:text-green-500"></i> OCR Verified
                </span>
                <button type="button" onclick="closeSignaturesModal()" class="px-4 py-1.5 rounded-xl bg-slate-900 hover:bg-black dark:bg-white dark:hover:bg-zinc-200 text-white dark:text-zinc-900 font-bold text-xs tap-effect transition-all">
                    Done
                </button>
            </div>

        </div>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Android Style - Reference: sk_voucher.php) -->
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
        <a href="sk_voucher.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-receipt text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Vouchers</span>
        </a>
        <a href="sk_resolution.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-scroll text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Resolutions</span>
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

        // Lodge Resolution Modal
        function openResolutionModal() {
            const modal = document.getElementById('resolutionModalOverlay');
            const box = document.getElementById('resolutionModalBox');
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

        function closeResolutionModal() {
            const modal = document.getElementById('resolutionModalOverlay');
            const box = document.getElementById('resolutionModalBox');
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

        // View Signatories Modal
        function openSignaturesModal(data) {
            const modal = document.getElementById('signaturesModalOverlay');
            const box = document.getElementById('signaturesModalBox');
            const list = document.getElementById('modalSignatoriesList');
            if (!modal || !box || !data) return;

            document.getElementById('modalResNumber').textContent = 'Res. No. ' + data.number;
            document.getElementById('modalResTitle').textContent = data.title;

            // Populate Signatories
            list.innerHTML = '';
            const sigs = data.signatories || [];
            if (sigs.length === 0) {
                list.innerHTML = `
                    <div class="py-6 text-center text-slate-400 dark:text-zinc-500">
                        <i class="ph-bold ph-signature text-2xl mb-1 opacity-50 block"></i>
                        <p class="text-xs font-semibold">No verified signatories recorded</p>
                        <p class="text-[10px] mt-0.5">The OCR scan did not detect registered roster names on this file.</p>
                    </div>
                `;
            } else {
                sigs.forEach(s => {
                    const li = document.createElement('div');
                    li.className = 'flex items-center gap-3 p-2.5 rounded-xl bg-slate-50 dark:bg-zinc-800/50 border border-slate-100 dark:border-zinc-800/80 transition-colors';
                    li.innerHTML = `
                        <div class="w-8 h-8 rounded-lg bg-green-100 dark:bg-green-500/10 text-green-600 dark:text-green-400 flex items-center justify-center text-sm shrink-0">
                            <i class="ph-bold ph-check"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-slate-800 dark:text-zinc-100 truncate">${escapeHtml(s.official_name)}</p>
                            <p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider truncate">${escapeHtml(s.position)}</p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400">
                            Verified
                        </span>
                    `;
                    list.appendChild(li);
                });
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            requestAnimationFrame(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                box.classList.remove('scale-95', 'opacity-0');
                box.classList.add('scale-100', 'opacity-100');
            });
        }

        function closeSignaturesModal() {
            const modal = document.getElementById('signaturesModalOverlay');
            const box = document.getElementById('signaturesModalBox');
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

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Escape Key Listener
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeResolutionModal();
                closeSignaturesModal();
            }
        });
    </script>
</body>
</html>