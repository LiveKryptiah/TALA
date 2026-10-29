<?php
session_start();

// 1. Auth Checks
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Adjust roles based on your setup
if ($_SESSION['role'] !== 'deped' && $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require 'db_connect.php'; 

// ==========================================
// 2. AJAX HANDLER: REAL-TIME STATUS UPDATE
// ==========================================
if (isset($_POST['action']) && $_POST['action'] === 'verify_deped') {
    header('Content-Type: application/json');
    try {
        if ($_POST['source'] === 'profile') {
            $stmt = $pdo->prepare("UPDATE youth_profiles SET deped_status = :status, deped_verified_at = CURRENT_TIMESTAMP WHERE id = :id");
        } else {
            $stmt = $pdo->prepare("UPDATE youth_registry SET deped_enrollment_status = :status WHERE id = :id");
        }
        $stmt->execute([':status' => $_POST['status'], ':id' => $_POST['id']]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ==========================================
// 3. AJAX HANDLER: DATATABLES SERVER-SIDE
// ==========================================
// This completely stops website lag by only fetching 10 rows at a time
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fetch_students') {
    header('Content-Type: application/json');
    
    $draw = $_POST['draw'] ?? 1;
    $start = (int)($_POST['start'] ?? 0);
    $length = (int)($_POST['length'] ?? 10);
    $searchValue = $_POST['search']['value'] ?? '';

    $baseQuery = "
        SELECT 
            id, youth_id, full_name, birthdate, NULL as age_registry, barangay, 
            education_level, school_name, deped_status, deped_verified_at, 'profile' as source 
        FROM youth_profiles 
        
        UNION ALL 
        
        SELECT 
            id, NULL as youth_id, full_name, NULL as birthdate, age as age_registry, barangay, 
            educational_background as education_level, name_of_school as school_name, 
            deped_enrollment_status as deped_status, NULL as deped_verified_at, 'registry' as source 
        FROM youth_registry 
        WHERE full_name NOT IN (SELECT full_name FROM youth_profiles)
    ";

    // 1. Get Total Records (Fast)
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM ($baseQuery) as t");
    $recordsTotal = $totalStmt->fetchColumn();

    // 2. Setup Search Filtering
    $searchSql = "";
    $params = [];
    if (!empty($searchValue)) {
        $searchSql = " WHERE full_name LIKE :search OR youth_id LIKE :search OR school_name LIKE :search ";
        $params[':search'] = "%$searchValue%";
    }

    // 3. Get Filtered Total
    $filteredStmt = $pdo->prepare("SELECT COUNT(*) FROM ($baseQuery) as t $searchSql");
    $filteredStmt->execute($params);
    $recordsFiltered = $filteredStmt->fetchColumn();

    // 4. Fetch Just The 10 Requested Rows
    $dataQuery = "SELECT * FROM ($baseQuery) as t $searchSql ORDER BY full_name ASC LIMIT :limit OFFSET :offset";
    $dataStmt = $pdo->prepare($dataQuery);
    foreach($params as $key => $val) {
        $dataStmt->bindValue($key, $val);
    }
    $dataStmt->bindValue(':limit', $length, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $start, PDO::PARAM_INT);
    $dataStmt->execute();
    
    $data = [];
    while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
        // Prepare Age
        $age = 'N/A';
        if ($row['source'] === 'profile' && !empty($row['birthdate'])) {
            $bday = new DateTime($row['birthdate']);
            $today = new DateTime('today');
            $age = $bday->diff($today)->y . " yrs";
        } elseif ($row['source'] === 'registry' && !empty($row['age_registry'])) {
            $age = $row['age_registry'] . " yrs";
        }
        
        // Prepare Status Check
        $currentStatus = htmlspecialchars($row['deped_status'] ?? '');
        if (stripos($currentStatus, 'Not Enrolled') !== false || stripos($currentStatus, 'Dropped') !== false) $currentStatus = 'Drop out';
        if (stripos($currentStatus, 'Pending') !== false || empty($currentStatus)) $currentStatus = '';
        
        // HTML Column 1: Identity
        $icon = $row['source'] === 'profile' 
            ? '<div class="w-10 h-10 rounded-full bg-[#1da85b]/10 text-[#1da85b] flex items-center justify-center font-bold text-sm border border-[#1da85b]/20 shadow-sm" title="New TALA Profile"><i class="ph-fill ph-identification-card text-lg"></i></div>'
            : '<div class="w-10 h-10 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-sm border border-slate-200 shadow-sm" title="Old Database Record"><i class="ph-fill ph-archive text-lg"></i></div>';
        $y_id = $row['source'] === 'profile' ? htmlspecialchars($row['youth_id']) : 'LEGACY DB';
        $fname = htmlspecialchars($row['full_name']);
        
        $identity = '
            <div class="flex items-center gap-3">
                '.$icon.'
                <div>
                    <p class="text-sm font-bold text-slate-900">'.$fname.'</p>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-0.5">'.$y_id.'</p>
                </div>
            </div>';

        // HTML Column 2: Demographics
        $brgy = htmlspecialchars($row['barangay'] ?: 'Unknown');
        $demographics = '
            <span class="text-sm font-bold text-slate-700">'.$age.'</span>
            <span class="text-slate-300 mx-1">•</span>
            <span class="text-xs font-semibold text-slate-500">Brgy. '.$brgy.'</span>';

        // HTML Column 3: Profile
        $sch = htmlspecialchars($row['school_name'] ?: 'Not Specified');
        $edu = htmlspecialchars($row['education_level'] ?: 'No Level Declared');
        $profile = '
            <p class="text-sm font-bold text-slate-700 truncate max-w-[200px]" title="'.$sch.'">'.$sch.'</p>
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mt-0.5">'.$edu.'</p>';

        // HTML Column 4: Status Selector
        $selectClass = '';
        if ($currentStatus === 'Enrolled') $selectClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
        elseif ($currentStatus === 'Drop out') $selectClass = 'bg-rose-50 text-rose-700 border-rose-200';
        elseif ($currentStatus === 'ALS') $selectClass = 'bg-blue-50 text-blue-700 border-blue-200';
        else $selectClass = 'bg-amber-50 text-amber-700 border-amber-200';

        $selUnv = $currentStatus == '' ? 'selected' : '';
        $selEnr = $currentStatus == 'Enrolled' ? 'selected' : '';
        $selDrp = $currentStatus == 'Drop out' ? 'selected' : '';
        $selALS = $currentStatus == 'ALS' ? 'selected' : '';

        $statusHtml = '
            <div class="relative inline-block w-48 text-left">
                <select onchange="updateStatus(this, '.$row['id'].', \''.$row['source'].'\')" class="w-full text-xs font-bold border border-slate-200 rounded-xl px-4 py-2.5 appearance-none focus:outline-none focus:ring-4 focus:ring-[#1da85b]/20 cursor-pointer shadow-sm transition-all '.$selectClass.'">
                    <option value="" '.$selUnv.'>⚠️ Needs Verification</option>
                    <option value="Enrolled" '.$selEnr.'>✅ Enrolled</option>
                    <option value="Drop out" '.$selDrp.'>📉 Drop out</option>
                    <option value="ALS" '.$selALS.'>📚 ALS</option>
                </select>
                <i class="ph-bold ph-caret-down absolute right-3 top-1/2 transform -translate-y-1/2 pointer-events-none opacity-50"></i>
            </div>';

        $data[] = [
            "identity" => $identity,
            "demographics" => $demographics,
            "profile" => $profile,
            "status" => $statusHtml
        ];
    }

    echo json_encode([
        "draw" => intval($draw),
        "recordsTotal" => intval($recordsTotal),
        "recordsFiltered" => intval($recordsFiltered),
        "data" => $data
    ]);
    exit;
}

// ==========================================
// 4. PAGE ROUTING & DASHBOARD OPTIMIZATION
// ==========================================
$current_page = $_GET['page'] ?? 'dashboard';

// We ONLY run these queries if the user is on the dashboard! 
// This saves massive server resources.
if ($current_page === 'dashboard') {
    try {
        // Optimized Dashboard Stats Query (No arrays/loops, let SQL do the math)
        $statsQuery = "
            SELECT 
                CASE 
                    WHEN deped_status LIKE '%Not Enrolled%' OR deped_status LIKE '%Dropped%' THEN 'Drop out'
                    WHEN deped_status LIKE '%Pending%' OR deped_status IS NULL OR deped_status = '' THEN 'Unverified'
                    ELSE deped_status 
                END as clean_status,
                COUNT(*) as count
            FROM (
                SELECT deped_status FROM youth_profiles
                UNION ALL
                SELECT deped_enrollment_status as deped_status FROM youth_registry WHERE full_name NOT IN (SELECT full_name FROM youth_profiles)
            ) as t
            GROUP BY clean_status
        ";
        
        $status_counts = ['Enrolled' => 0, 'Drop out' => 0, 'ALS' => 0, 'Unverified' => 0];
        $total_students = 0;
        
        foreach($pdo->query($statsQuery) as $row) {
            $status = $row['clean_status'];
            if (!isset($status_counts[$status])) $status = 'Unverified'; // Fallback
            $status_counts[$status] += $row['count'];
            $total_students += $row['count'];
        }

        // Optimized Top Schools Query
        $schoolsQuery = "
            SELECT school_name, COUNT(*) as count FROM (
                SELECT school_name FROM youth_profiles
                UNION ALL
                SELECT name_of_school as school_name FROM youth_registry WHERE full_name NOT IN (SELECT full_name FROM youth_profiles)
            ) as t
            WHERE school_name IS NOT NULL AND school_name != '' AND LOWER(school_name) NOT IN ('n/a', 'none')
            GROUP BY school_name
            ORDER BY count DESC
            LIMIT 5
        ";
        $top_schools = [];
        foreach($pdo->query($schoolsQuery) as $row) {
            $top_schools[$row['school_name']] = $row['count'];
        }
        
    } catch (Exception $e) {
        die("<div style='padding:20px; color:red;'>Database Connection Failed. Error: " . $e->getMessage() . "</div>");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DepEd Portal | TALA OS</title>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script> tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, colors: { brand: { 500: '#1da85b' } } } } } </script>
    
    <style>
        body { background-color: #f8fafc; }
        
        /* High-End DataTables SaaS Restyling */
        table.dataTable { width: 100% !important; border-collapse: collapse !important; margin-top: 0 !important; }
        table.dataTable thead th { background-color: #f8fafc; color: #64748b; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; letter-spacing: 0.05em; text-align: left; }
        table.dataTable tbody td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        table.dataTable tbody tr { transition: background-color 0.2s ease; }
        table.dataTable tbody tr:hover { background-color: #f8fafc; }
        
        .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_length { display: none; }
        .dataTables_wrapper .dataTables_info { font-size: 13px; color: #64748b; padding-top: 20px !important; font-weight: 500; }
        .dataTables_wrapper .dataTables_paginate { padding-top: 15px !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button { padding: 6px 12px !important; margin-left: 4px; border-radius: 8px !important; border: 1px solid #e2e8f0 !important; background: white !important; font-size: 13px; font-weight: 600; color: #475569 !important; transition: all 0.2s; cursor: pointer; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #1da85b !important; color: white !important; border-color: #1da85b !important; box-shadow: 0 2px 4px rgba(29, 168, 91, 0.2); }
        table.dataTable.no-footer { border-bottom: none !important; }

        /* Toast Animation */
        #toast { transition: opacity 0.3s, transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); transform: translateY(100px); opacity: 0; pointer-events: none; }
        #toast.show { transform: translateY(0); opacity: 1; }

        .btn-gradient { background: linear-gradient(135deg, #15803d 0%, #047857 60%, #ca8a04 100%); color: #ffffff !important; transition: opacity 0.3s ease, transform 0.3s ease, box-shadow 0.3s ease; border: none !important; }
        .btn-gradient:hover { opacity: 0.95; transform: translateY(-2px); box-shadow: 0 10px 25px -3px rgba(21, 128, 61, 0.4); }
        .icon-gradient { background: linear-gradient(135deg, #15803d 0%, #047857 30%, #ca8a04 80%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block; }
        .glass-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.8); }

        #sidebar { transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        #mainContent { transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        
        aside.collapsed .sidebar-text, aside.collapsed .sidebar-section-title, aside.collapsed .sidebar-user-info { display: none !important; }
        aside.collapsed .sidebar-logo-area { justify-content: center !important; padding: 0 !important; }
        aside.collapsed nav a { justify-content: center !important; padding-left: 0 !important; padding-right: 0 !important; }
        aside.collapsed .sidebar-user-card { padding: 8px !important; justify-content: center !important; }
    </style>
</head>
<body class="text-slate-800 antialiased selection:bg-[#1da85b] selection:text-white flex overflow-x-hidden">

    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden opacity-0 transition-opacity duration-300 lg:hidden"></div>

    <aside id="sidebar" class="w-64 bg-white/90 backdrop-blur-xl border-r border-slate-200/60 fixed inset-y-0 left-0 z-50 flex flex-col shadow-[4px_0_24px_rgba(0,0,0,0.02)] -translate-x-full lg:translate-x-0">
        <div class="h-24 flex items-center justify-between px-5 border-b border-slate-100 mb-4 bg-white sidebar-logo-area">
            <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-10 h-10 bg-slate-100 border border-slate-200 rounded-xl flex items-center justify-center shrink-0 shadow-sm">
                    <img src="deped.png" alt="DepEd" class="w-7 h-7 object-contain">
                </div>
                <div class="sidebar-text truncate">
                    <h1 class="text-lg font-black text-slate-900 leading-tight">DepEd</h1>
                    <p class="text-[10px] font-bold tracking-widest uppercase text-slate-400">TALA OS Portal</p>
                </div>
            </div>
            <button id="desktopToggle" class="hidden lg:flex w-8 h-8 items-center justify-center text-slate-400 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-lg transition-colors shrink-0">
                <i class="ph-bold ph-sidebar text-base"></i>
            </button>
        </div>

        <nav class="flex-1 px-4 space-y-1.5 overflow-y-auto custom-scrollbar">
            <p class="px-2 text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 mt-2 sidebar-section-title">Core Access</p>
            <a href="?page=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page === 'dashboard' ? 'bg-[#f0fdf4] text-[#15803d] border border-[#dcfce7] shadow-sm' : 'text-slate-600 hover:bg-slate-50 border border-transparent' ?>">
                <i class="<?= $current_page === 'dashboard' ? 'ph-fill text-[#15803d]' : 'ph-bold' ?> ph-chart-pie-slice text-lg shrink-0"></i> 
                <span class="sidebar-text truncate">Dashboard Overview</span>
            </a>
            <a href="?page=verification" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page === 'verification' ? 'bg-[#f0fdf4] text-[#15803d] border border-[#dcfce7] shadow-sm' : 'text-slate-600 hover:bg-slate-50 border border-transparent' ?>">
                <i class="<?= $current_page === 'verification' ? 'ph-fill text-[#15803d]' : 'ph-bold' ?> ph-check-square-offset text-lg shrink-0"></i> 
                <span class="sidebar-text truncate">Student Verification</span>
            </a>
        </nav>
        
        <div class="p-4 border-t border-slate-100">
            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/60 sidebar-user-card flex items-center justify-between">
                <div class="flex items-center gap-3 overflow-hidden sidebar-user-info">
                    <div class="w-10 h-10 bg-[#dcfce7] text-[#16a34a] rounded-full flex items-center justify-center font-bold border-2 border-white shadow-sm shrink-0">
                        <i class="ph-bold ph-user text-lg"></i>
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-sm font-extrabold text-slate-800 truncate">District Admin</p>
                        <p class="text-[10px] font-extrabold text-[#16a34a] uppercase tracking-widest truncate">Authorized User</p>
                    </div>
                </div>
                <a href="logout.php" class="flex items-center justify-center w-9 h-9 bg-white border border-red-200 rounded-xl text-red-600 hover:bg-red-50 transition-colors shadow-sm shrink-0" title="Secure Logout">
                    <i class="ph-bold ph-sign-out text-base"></i>
                </a>
            </div>
        </div>
    </aside>

    <div id="mainContent" class="p-8 w-full max-w-[1500px] mx-auto pb-12 lg:ml-64">
        
        <div class="mb-8 flex flex-col md:flex-row md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">
                    <?= $current_page === 'dashboard' ? 'Analytics Dashboard' : 'Verification Control Center' ?>
                </h1>
            </div>
        </div>

        <?php if ($current_page === 'dashboard'): ?>
        <!-- ========================================== -->
        <!-- DASHBOARD OVERVIEW                         -->
        <!-- ========================================== -->
        <div id="view-dashboard">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
                
                <div class="glass-card p-5 rounded-2xl shadow-sm flex justify-between items-center group hover:border-slate-300 transition-colors">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Unified Records</p>
                        <p class="text-3xl font-extrabold text-slate-900"><?= number_format($total_students); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center border border-slate-200 shadow-sm shrink-0">
                        <i class="ph-fill ph-users-three text-2xl icon-gradient"></i>
                    </div>
                </div>
                <div class="glass-card p-5 rounded-2xl shadow-sm flex justify-between items-center group hover:border-slate-300 transition-colors">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Actively Enrolled</p>
                        <p class="text-3xl font-extrabold text-slate-900"><?= number_format($status_counts['Enrolled']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center border border-slate-200 shadow-sm shrink-0">
                        <i class="ph-fill ph-check-circle text-2xl icon-gradient"></i>
                    </div>
                </div>
                <div class="glass-card p-5 rounded-2xl shadow-sm flex justify-between items-center group hover:border-slate-300 transition-colors">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Drop Outs</p>
                        <p class="text-3xl font-extrabold text-slate-900"><?= number_format($status_counts['Drop out']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center border border-slate-200 shadow-sm shrink-0">
                        <i class="ph-fill ph-trend-down text-2xl icon-gradient"></i>
                    </div>
                </div>
                <div class="glass-card p-5 rounded-2xl shadow-sm flex justify-between items-center group hover:border-slate-300 transition-colors">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">ALS Learners</p>
                        <p class="text-3xl font-extrabold text-slate-900"><?= number_format($status_counts['ALS']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center border border-slate-200 shadow-sm shrink-0">
                        <i class="ph-fill ph-book-open text-2xl icon-gradient"></i>
                    </div>
                </div>
                <div class="glass-card p-5 rounded-2xl shadow-sm flex justify-between items-center group hover:border-slate-300 transition-colors">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Unverified</p>
                        <p class="text-3xl font-extrabold text-slate-900"><?= number_format($status_counts['Unverified']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-slate-100 rounded-2xl flex items-center justify-center border border-slate-200 shadow-sm shrink-0">
                        <i class="ph-fill ph-warning-circle text-2xl icon-gradient"></i>
                    </div>
                </div>
                
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1 glass-card p-6 rounded-[2rem] shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 mb-6 flex items-center gap-2"><i class="ph-fill ph-chart-donut icon-gradient text-lg"></i> Overall Status Distribution</h3>
                    <div class="relative h-48 w-full flex items-center justify-center"><canvas id="statusChart"></canvas></div>
                </div>
                
                <div class="lg:col-span-2 glass-card p-6 rounded-[2rem] shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 mb-6 flex items-center gap-2"><i class="ph-fill ph-buildings icon-gradient text-lg"></i> Top 5 Schools Declared</h3>
                    <div class="relative h-48 w-full"><canvas id="schoolChart"></canvas></div>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- ========================================== -->
        <!-- STUDENT VERIFICATION (AJAX OPTIMIZED)      -->
        <!-- ========================================== -->
        <div id="view-verification">
            <div class="glass-card rounded-3xl shadow-sm w-full flex flex-col overflow-hidden">
                
                <div class="p-5 border-b border-slate-100 bg-white/50 flex flex-col md:flex-row justify-between items-center gap-4">
                    <div class="flex items-center gap-3 w-full md:w-auto justify-start">
                        <label for="tableLength" class="text-sm font-bold text-slate-500 whitespace-nowrap">Show</label>
                        <div class="relative inline-block w-24">
                            <select id="tableLength" class="w-full text-sm font-bold border border-slate-200 rounded-xl pl-4 pr-8 py-2.5 appearance-none focus:outline-none focus:border-[#1da85b] focus:ring-4 focus:ring-[#1da85b]/10 cursor-pointer shadow-sm transition-all bg-white text-slate-700">
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="50">50</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-3 top-1/2 transform -translate-y-1/2 pointer-events-none opacity-50 text-slate-500"></i>
                        </div>
                        <span class="text-sm font-bold text-slate-500">entries</span>
                    </div>

                    <div class="relative w-full md:w-96 group">
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 group-focus-within:text-[#1da85b] transition-colors"></i>
                        <input type="text" id="customSearch" placeholder="Search student name, ID, or school..." class="w-full pl-10 pr-4 py-2.5 bg-white rounded-xl border border-slate-200 focus:outline-none focus:border-[#1da85b] focus:ring-4 focus:ring-[#1da85b]/10 text-sm font-semibold transition-all shadow-sm">
                    </div>
                </div>

                <div class="overflow-x-auto w-full bg-white">
                    <table id="depedTable" class="display w-full whitespace-nowrap">
                        <thead>
                            <tr>
                                <th class="rounded-tl-xl">Student Identity</th>
                                <th>Demographics</th>
                                <th>Educational Profile</th>
                                <th class="w-64 text-right pr-6">DepEd Verification Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- The table body starts EMPTY. Zero lag. DataTables fetches rows dynamically via AJAX. -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Floating Success Toast -->
    <div id="toast" class="fixed bottom-8 right-8 bg-slate-900 border border-slate-700 text-white px-5 py-4 rounded-2xl shadow-2xl flex items-center gap-4 z-[9999]">
        <div class="w-10 h-10 bg-emerald-500/20 rounded-full flex items-center justify-center border border-emerald-500/30">
            <i class="ph-fill ph-check-circle text-emerald-400 text-2xl"></i>
        </div>
        <div class="flex flex-col pr-4">
            <span class="text-sm font-bold tracking-wide text-white">Status Verified</span>
            <span class="text-xs text-slate-400 font-medium">Record safely synced to database.</span>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        $(document).ready(function() {
            <?php if ($current_page === 'dashboard'): ?>
                // ==========================================
                // CHARTS INITIALIZATION
                // ==========================================
                Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
                const tTip = { backgroundColor: '#0f172a', titleFont: {size: 13, weight: 'bold'}, bodyFont: {size: 12}, padding: 12, cornerRadius: 8, displayColors: false };

                new Chart(document.getElementById('statusChart'), {
                    type: 'doughnut',
                    data: { 
                        labels: ['Enrolled', 'Drop out', 'ALS', 'Unverified'], 
                        datasets: [{ 
                            data: [
                                <?= $status_counts['Enrolled']; ?>, 
                                <?= $status_counts['Drop out']; ?>, 
                                <?= $status_counts['ALS']; ?>, 
                                <?= $status_counts['Unverified']; ?>
                            ], 
                            backgroundColor: ['#10b981', '#f43f5e', '#3b82f6', '#f59e0b'], 
                            borderWidth: 0, hoverOffset: 4 
                        }] 
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { tooltip: tTip, legend: { position: 'right', labels: { usePointStyle: true, padding: 15, boxWidth: 8, font: {weight: '600'} } } } }
                });

                new Chart(document.getElementById('schoolChart'), {
                    type: 'bar',
                    data: { 
                        labels: <?= json_encode(array_keys($top_schools)); ?>, 
                        datasets: [{ data: <?= json_encode(array_values($top_schools)); ?>, backgroundColor: '#1da85b', borderRadius: 6, barPercentage: 0.5 }] 
                    },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: tTip }, scales: { x: { display: false }, y: { grid: { display: false }, border: {display: false}, ticks: { font: {size: 11, weight: '600'}, color: '#475569' } } } }
                });

            <?php else: ?>
                // ==========================================
                // DATATABLES AJAX SERVER-SIDE PROCESSING
                // ==========================================
                const table = $('#depedTable').DataTable({ 
                    "serverSide": true,
                    "processing": true,
                    "ajax": {
                        "url": "?page=verification&ajax=fetch_students",
                        "type": "POST"
                    },
                    "columns": [
                        { "data": "identity" },
                        { "data": "demographics" },
                        { "data": "profile" },
                        { "data": "status", "orderable": false, "className": "text-right pr-6" }
                    ],
                    "pageLength": 10,
                    "order": [],
                    "dom": '<"px-4 pb-4"t><"px-6 py-4 border-t border-slate-100 bg-slate-50/30 flex flex-col md:flex-row justify-between items-center rounded-b-3xl"ip>',
                    "language": { 
                        "info": "Showing <span class='font-bold text-slate-700'>_START_</span> to <span class='font-bold text-slate-700'>_END_</span> of <span class='font-bold text-slate-700'>_TOTAL_</span> youths",
                        "processing": "<div class='text-center py-4 font-bold text-[#1da85b] animate-pulse flex items-center justify-center gap-2'><i class='ph-bold ph-spinner-gap animate-spin'></i> Loading Database...</div>" 
                    }
                });
                
                $('#customSearch').on('keyup', function() { 
                    table.search(this.value).draw(); 
                });

                $('#tableLength').on('change', function() {
                    table.page.len(this.value).draw();
                });
            <?php endif; ?>
        });

        // ==========================================
        // AJAX STATUS UPDATER
        // ==========================================
        function updateStatus(selectElement, recordId, dbSource) {
            const newStatus = selectElement.value;
            
            selectElement.className = selectElement.className.replace(/bg-\w+-50 text-\w+-700 border-\w+-200/g, '');
            if(newStatus === 'Enrolled') selectElement.classList.add('bg-emerald-50', 'text-emerald-700', 'border-emerald-200');
            else if(newStatus === 'Drop out') selectElement.classList.add('bg-rose-50', 'text-rose-700', 'border-rose-200');
            else if(newStatus === 'ALS') selectElement.classList.add('bg-blue-50', 'text-blue-700', 'border-blue-200');
            else selectElement.classList.add('bg-amber-50', 'text-amber-700', 'border-amber-200');

            $.ajax({
                url: 'deped_portal.php',
                type: 'POST',
                data: { action: 'verify_deped', id: recordId, status: newStatus, source: dbSource },
                success: function(response) {
                    if (response.success) {
                        const toast = document.getElementById('toast');
                        toast.classList.add('show');
                        setTimeout(() => toast.classList.remove('show'), 3000);
                    } else {
                        alert("Error saving: " + response.error);
                    }
                }
            });
        }

        // ==========================================
        // SIDEBAR LOGIC
        // ==========================================
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const desktopToggle = document.getElementById('desktopToggle');
            const overlay = document.getElementById('sidebarOverlay');
            let isCollapsed = false;

            function toggleSidebar() {
                if (window.innerWidth >= 1024) {
                    isCollapsed = !isCollapsed;
                    sidebar.classList.toggle('collapsed', isCollapsed);
                    sidebar.style.width = isCollapsed ? '80px' : '256px';
                    mainContent.style.marginLeft = isCollapsed ? '80px' : '256px';
                } else {
                    sidebar.classList.toggle('-translate-x-full');
                    const isOpen = !sidebar.classList.contains('-translate-x-full');
                    if (isOpen) {
                        overlay.classList.remove('hidden');
                        setTimeout(() => overlay.classList.remove('opacity-0'), 10);
                        document.body.style.overflow = 'hidden';
                    } else {
                        overlay.classList.add('opacity-0');
                        setTimeout(() => overlay.classList.add('hidden'), 300);
                        document.body.style.overflow = '';
                    }
                }
            }
            if(desktopToggle) desktopToggle.addEventListener('click', toggleSidebar);
            if(overlay) overlay.addEventListener('click', toggleSidebar);
        });
    </script>
</body>
</html>