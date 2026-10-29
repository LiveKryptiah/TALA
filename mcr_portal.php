<?php
session_start();

// 1. Auth Checks
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Ensure only MCR or Master Admins can access
if ($_SESSION['role'] !== 'mcr' && $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require 'db_connect.php'; 

// ==========================================
// 2. AJAX HANDLERS
// ==========================================

// A. SEPARATED STATUS UPDATES
if (isset($_POST['action']) && $_POST['action'] === 'verify_mcr') {
    header('Content-Type: application/json');
    try {
        $update_type = $_POST['update_type']; // 'birth', 'civil', or 'vital'
        $column = '';
        
        // Route to the correct database column
        if ($update_type === 'birth') $column = 'mcr_birth_status';
        elseif ($update_type === 'civil') $column = 'mcr_civil_status';
        elseif ($update_type === 'vital') $column = 'mcr_vital_status';

        if ($column !== '') {
            if ($_POST['source'] === 'profile') {
                $stmt = $pdo->prepare("UPDATE youth_profiles SET $column = :status, mcr_verified_at = CURRENT_TIMESTAMP WHERE id = :id");
            } else {
                $stmt = $pdo->prepare("UPDATE youth_registry SET $column = :status WHERE id = :id");
            }
            $stmt->execute([':status' => $_POST['status'], ':id' => $_POST['id']]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid update type']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// B. DATATABLES SERVER-SIDE PROCESSING
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fetch_mcr') {
    header('Content-Type: application/json');
    
    $draw = $_POST['draw'] ?? 1;
    $start = (int)($_POST['start'] ?? 0);
    $length = (int)($_POST['length'] ?? 10);
    $searchValue = $_POST['search']['value'] ?? '';

    $baseQuery = "
        SELECT 
            id, youth_id, full_name, birthdate, NULL as age_registry, barangay, civil_status as declared_civil,
            mcr_birth_status, mcr_civil_status, mcr_vital_status, mcr_verified_at, 'profile' as source 
        FROM youth_profiles 
        UNION ALL 
        SELECT 
            id, NULL as youth_id, full_name, NULL as birthdate, age as age_registry, barangay, NULL as declared_civil,
            mcr_birth_status, mcr_civil_status, mcr_vital_status, NULL as mcr_verified_at, 'registry' as source 
        FROM youth_registry 
        WHERE full_name NOT IN (SELECT full_name FROM youth_profiles)
    ";

    $totalStmt = $pdo->query("SELECT COUNT(*) FROM ($baseQuery) as t");
    $recordsTotal = $totalStmt->fetchColumn();

    $searchSql = "";
    $params = [];
    if (!empty($searchValue)) {
        $searchSql = " WHERE full_name LIKE :search OR youth_id LIKE :search OR barangay LIKE :search ";
        $params[':search'] = "%$searchValue%";
    }

    $filteredStmt = $pdo->prepare("SELECT COUNT(*) FROM ($baseQuery) as t $searchSql");
    $filteredStmt->execute($params);
    $recordsFiltered = $filteredStmt->fetchColumn();

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
        // Date parsing
        $bday = 'No B-Day Record';
        if ($row['source'] === 'profile' && !empty($row['birthdate'])) {
            $bday = date('M j, Y', strtotime($row['birthdate']));
        } elseif ($row['source'] === 'registry' && !empty($row['age_registry'])) {
            $bday = $row['age_registry'] . " years old (Legacy)";
        }

        // Deceased Row Dimming
        $isDeceased = ($row['mcr_vital_status'] === 'Deceased');
        $nameClass = $isDeceased ? 'text-slate-500 line-through' : 'text-slate-900';

        // Column 1: Identity
        $icon = $row['source'] === 'profile' 
            ? '<div class="w-10 h-10 rounded-full bg-[#1da85b]/10 text-[#1da85b] flex items-center justify-center font-bold text-sm border border-[#1da85b]/20 shadow-sm"><i class="ph-fill ph-identification-card text-lg"></i></div>'
            : '<div class="w-10 h-10 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-sm border border-slate-200 shadow-sm"><i class="ph-fill ph-archive text-lg"></i></div>';
        $yid = $row['source'] === 'profile' ? htmlspecialchars($row['youth_id']) : 'LEGACY DB';
        $name = htmlspecialchars($row['full_name']);

        $identity = '
            <div class="flex items-center gap-3 mb-2">
                '.$icon.'
                <div>
                    <p class="text-sm font-bold '.$nameClass.'">'.$name.'</p>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-0.5">'.$yid.'</p>
                </div>
            </div>';

        // Column 2: Self-Declared Information
        $brgy = htmlspecialchars($row['barangay'] ?: 'Unknown');
        $civil = htmlspecialchars($row['declared_civil'] ?: 'Not Declared');
        $info = '
            <p class="text-sm font-bold text-slate-700 truncate max-w-[200px] mb-1">'.htmlspecialchars($bday).'</p>
            <p class="text-[11px] font-semibold text-slate-400 truncate max-w-[200px]">
                Brgy: '.$brgy.'<br>Civil: '.$civil.'
            </p>';

        // Column 3: Controls
        $f_birth = $row['mcr_birth_status'];
        $c_birth = $f_birth === 'Verified' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-50';

        $f_civil = $row['mcr_civil_status'];
        $c_civil = $f_civil === 'Married' ? 'bg-blue-50 text-blue-700' : 'bg-slate-50';

        $f_vital = $row['mcr_vital_status'];
        $c_vital = $f_vital === 'Deceased' ? 'bg-rose-50 text-rose-700' : 'bg-slate-50';

        $controls = '
            <div class="flex flex-col md:flex-row gap-3 items-start w-full">
                <!-- Birth -->
                <div class="flex flex-col w-full lg:w-1/3 relative">
                    <label class="text-[10px] font-bold uppercase text-slate-400 mb-1">Birth Record</label>
                    <select onchange="updateMCR(this, '.$row['id'].', \''.$row['source'].'\', \'birth\')" class="w-full text-[11px] font-bold border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-[#1da85b] focus:ring-2 focus:ring-[#1da85b]/20 shadow-sm cursor-pointer appearance-none '.$c_birth.'">
                        <option value="">Unverified</option>
                        <option value="Verified" '.($f_birth === 'Verified' ? 'selected' : '').'>Verified</option>
                    </select>
                    <i class="ph-bold ph-caret-down absolute right-3 bottom-[10px] pointer-events-none opacity-50 text-xs"></i>
                </div>
                <!-- Civil Status -->
                <div class="flex flex-col w-full lg:w-1/3 relative">
                    <label class="text-[10px] font-bold uppercase text-slate-400 mb-1">Marriage/Civil</label>
                    <select onchange="updateMCR(this, '.$row['id'].', \''.$row['source'].'\', \'civil\')" class="w-full text-[11px] font-bold border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-[#1da85b] focus:ring-2 focus:ring-[#1da85b]/20 shadow-sm cursor-pointer appearance-none '.$c_civil.'">
                        <option value="">-- Select --</option>
                        <option value="Single" '.($f_civil === 'Single' ? 'selected' : '').'>Single</option>
                        <option value="Married" '.($f_civil === 'Married' ? 'selected' : '').'>Married</option>
                        <option value="Widowed" '.($f_civil === 'Widowed' ? 'selected' : '').'>Widowed</option>
                        <option value="Annulled" '.($f_civil === 'Annulled' ? 'selected' : '').'>Annulled</option>
                    </select>
                    <i class="ph-bold ph-caret-down absolute right-3 bottom-[10px] pointer-events-none opacity-50 text-xs"></i>
                </div>
                <!-- Vital Status -->
                <div class="flex flex-col w-full lg:w-1/3 relative">
                    <label class="text-[10px] font-bold uppercase text-slate-400 mb-1">Vital Status</label>
                    <select onchange="updateMCR(this, '.$row['id'].', \''.$row['source'].'\', \'vital\')" class="w-full text-[11px] font-bold border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-[#1da85b] focus:ring-2 focus:ring-[#1da85b]/20 shadow-sm cursor-pointer appearance-none '.$c_vital.'">
                        <option value="Alive" '.($f_vital === 'Alive' || empty($f_vital) ? 'selected' : '').'>Alive</option>
                        <option value="Deceased" '.($f_vital === 'Deceased' ? 'selected' : '').'>Deceased</option>
                    </select>
                    <i class="ph-bold ph-caret-down absolute right-3 bottom-[10px] pointer-events-none opacity-50 text-xs"></i>
                </div>
            </div>';

        $data[] = [
            "DT_RowClass" => $isDeceased ? 'group opacity-60 bg-slate-50' : 'group',
            "identity" => $identity,
            "info" => $info,
            "controls" => $controls
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
// 3. DASHBOARD OPTIMIZATION
// ==========================================
$current_page = $_GET['page'] ?? 'dashboard';

if ($current_page === 'dashboard') {
    try {
        // Ultra-Fast Aggregation Query
        $statsQuery = "
            SELECT 
                COUNT(*) as total_youth,
                SUM(CASE WHEN mcr_birth_status = 'Verified' THEN 1 ELSE 0 END) as birth_verified,
                SUM(CASE WHEN mcr_civil_status = 'Married' THEN 1 ELSE 0 END) as married,
                SUM(CASE WHEN mcr_vital_status = 'Deceased' THEN 1 ELSE 0 END) as deceased,
                SUM(CASE WHEN mcr_vital_status IS NULL OR mcr_vital_status != 'Deceased' THEN 1 ELSE 0 END) as alive
            FROM (
                SELECT mcr_birth_status, mcr_civil_status, mcr_vital_status FROM youth_profiles 
                UNION ALL 
                SELECT mcr_birth_status, mcr_civil_status, mcr_vital_status FROM youth_registry WHERE full_name NOT IN (SELECT full_name FROM youth_profiles)
            ) as t
        ";
        
        $stmt = $pdo->query($statsQuery);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $total_youth = $row['total_youth'] ?? 0;
        $mcr_counts = [
            'Birth Verified' => $row['birth_verified'] ?? 0,
            'Married' => $row['married'] ?? 0,
            'Deceased' => $row['deceased'] ?? 0,
            'Alive' => $row['alive'] ?? 0
        ];
        
    } catch (Exception $e) {
        die("<div style='padding:20px; color:red; font-family:sans-serif;'>Database Connection Failed. Ensure ALTER TABLE was run. Error: " . $e->getMessage() . "</div>");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MCR Portal | TALA OS</title>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, colors: { brand: { 500: '#1da85b' } } } } }
    </script>
    
    <style>
        body { background-color: #f8fafc; }
        
        table.dataTable { width: 100% !important; border-collapse: collapse !important; margin-top: 0 !important; }
        table.dataTable thead th { background-color: #f8fafc; color: #64748b; font-size: 11px; font-weight: 800; text-transform: uppercase; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; letter-spacing: 0.05em; text-align: left; }
        table.dataTable tbody td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        table.dataTable tbody tr { transition: background-color 0.2s ease; }
        table.dataTable tbody tr:hover { background-color: #f8fafc; }
        
        .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_length { display: none; }
        .dataTables_wrapper .dataTables_info { font-size: 13px; color: #64748b; padding-top: 20px !important; font-weight: 500; }
        .dataTables_wrapper .dataTables_paginate { padding-top: 15px !important; }
        .dataTables_wrapper .dataTables_paginate .paginate_button { padding: 6px 12px !important; margin-left: 4px; border-radius: 8px !important; border: 1px solid #e2e8f0 !important; background: white !important; font-size: 13px; font-weight: 600; color: #475569 !important; transition: all 0.2s; cursor: pointer; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #1da85b !important; color: white !important; border-color: #1da85b !important; box-shadow: 0 2px 4px rgba(29, 168, 91, 0.2); }
        table.dataTable.no-footer { border-bottom: none !important; }
        
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

    <!-- ========================================== -->
    <!-- MODERN DEDICATED MCR SIDEBAR               -->
    <!-- ========================================== -->
    <aside id="sidebar" class="w-64 bg-white/90 backdrop-blur-xl border-r border-slate-200/60 fixed inset-y-0 left-0 z-50 flex flex-col shadow-[4px_0_24px_rgba(0,0,0,0.02)] -translate-x-full lg:translate-x-0">
        
        <div class="h-24 flex items-center justify-between px-5 border-b border-slate-100 mb-4 bg-white sidebar-logo-area">
            <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-10 h-10 bg-slate-100 border border-slate-200 rounded-xl flex items-center justify-center shrink-0 shadow-sm">
                    <img src="mcr.png" alt="MCR" class="w-7 h-7 object-contain">
                </div>
                <div class="sidebar-text truncate">
                    <h1 class="text-lg font-black text-slate-900 leading-tight">MCR</h1>
                    <p class="text-[10px] font-bold tracking-widest uppercase text-slate-400">TALA OS Portal</p>
                </div>
            </div>
            
            <button id="desktopToggle" class="hidden lg:flex w-8 h-8 items-center justify-center text-slate-400 hover:text-slate-700 bg-slate-50 hover:bg-slate-100 rounded-lg transition-colors shrink-0">
                <i class="ph-bold ph-sidebar text-base"></i>
            </button>
        </div>

        <nav class="flex-1 px-4 space-y-1.5 overflow-y-auto custom-scrollbar">
            <p class="px-2 text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 mt-2 sidebar-section-title">Core Access</p>
            
            <a href="?page=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page === 'dashboard' ? 'bg-[#f0fdf4] text-[#15803d] border border-[#dcfce7] shadow-sm' : 'text-slate-600 hover:bg-slate-50 border border-transparent' ?>" title="Registry Overview">
                <i class="<?= $current_page === 'dashboard' ? 'ph-fill text-[#15803d]' : 'ph-bold' ?> ph-chart-pie-slice text-lg shrink-0"></i> 
                <span class="sidebar-text truncate">Registry Overview</span>
            </a>
            
            <a href="?page=verification" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page === 'verification' ? 'bg-[#f0fdf4] text-[#15803d] border border-[#dcfce7] shadow-sm' : 'text-slate-600 hover:bg-slate-50 border border-transparent' ?>" title="Civil Verification">
                <i class="<?= $current_page === 'verification' ? 'ph-fill text-[#15803d]' : 'ph-bold' ?> ph-check-square-offset text-lg shrink-0"></i> 
                <span class="sidebar-text truncate">Civil Verification</span>
            </a>
        </nav>
        
        <div class="p-4 border-t border-slate-100">
            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/60 sidebar-user-card flex items-center justify-between">
                <div class="flex items-center gap-3 overflow-hidden sidebar-user-info">
                    <div class="w-10 h-10 bg-[#dcfce7] text-[#16a34a] rounded-full flex items-center justify-center font-bold border-2 border-white shadow-sm shrink-0">
                        <i class="ph-bold ph-shield-check text-lg"></i>
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-sm font-extrabold text-slate-800 truncate">MCR Officer</p>
                        <p class="text-[10px] font-extrabold text-[#16a34a] uppercase tracking-widest truncate">Authorized User</p>
                    </div>
                </div>
                <a href="logout.php" class="flex items-center justify-center w-9 h-9 bg-white border border-red-200 rounded-xl text-red-600 hover:bg-red-50 transition-colors shadow-sm shrink-0" title="Secure Logout">
                    <i class="ph-bold ph-sign-out text-base"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- ========================================== -->
    <!-- MAIN CONTENT AREA                          -->
    <!-- ========================================== -->
    <div id="mainContent" class="p-8 w-full max-w-[1500px] mx-auto pb-12 lg:ml-64">
        
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">
                <?= $current_page === 'dashboard' ? 'Civil Analytics' : 'Registry Control Center' ?>
            </h1>
        </div>

        <?php if ($current_page === 'dashboard'): ?>
        <!-- ========================================== -->
        <!-- VIEW: DASHBOARD OVERVIEW                   -->
        <!-- ========================================== -->
        <div id="view-dashboard">
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="glass-card p-5 rounded-2xl shadow-sm flex flex-col justify-center hover:border-slate-300 transition-colors">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Total Registry Base</p>
                    <p class="text-3xl font-extrabold text-slate-900"><?= number_format($total_youth); ?></p>
                </div>
                <div class="glass-card p-5 rounded-2xl border-emerald-100 shadow-sm flex flex-col justify-center hover:border-emerald-300 transition-colors bg-emerald-50/50">
                    <p class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider mb-1">Verified Birthdays</p>
                    <p class="text-3xl font-extrabold text-emerald-700"><?= number_format($mcr_counts['Birth Verified']); ?></p>
                </div>
                <div class="glass-card p-5 rounded-2xl border-blue-100 shadow-sm flex flex-col justify-center hover:border-blue-300 transition-colors bg-blue-50/50">
                    <p class="text-[10px] font-bold text-blue-600 uppercase tracking-wider mb-1">Married Youths</p>
                    <p class="text-3xl font-extrabold text-blue-700"><?= number_format($mcr_counts['Married']); ?></p>
                </div>
                <div class="glass-card p-5 rounded-2xl border-rose-100 shadow-sm flex flex-col justify-center hover:border-rose-300 transition-colors bg-rose-50/50">
                    <p class="text-[10px] font-bold text-rose-600 uppercase tracking-wider mb-1">Flagged Deceased</p>
                    <p class="text-3xl font-extrabold text-rose-700"><?= number_format($mcr_counts['Deceased']); ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="glass-card p-6 rounded-2xl shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 mb-6 flex items-center gap-2"><i class="ph-fill ph-heart icon-gradient text-lg"></i> Vital Status Ratio</h3>
                    <div class="relative h-56 w-full flex items-center justify-center"><canvas id="vitalChart"></canvas></div>
                </div>
                
                <div class="glass-card p-6 rounded-2xl shadow-sm flex flex-col justify-center text-center">
                    <div class="w-20 h-20 bg-slate-100 text-slate-600 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-200 shadow-sm">
                        <i class="ph-bold ph-files icon-gradient text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-800 mb-2">MCR Verification Authority</h3>
                    <p class="text-sm text-slate-500 max-w-md mx-auto">This portal allows the Municipal Civil Registrar to officially verify self-declared data. Marking a youth as "Deceased" will automatically exclude them from active analytics in other portals.</p>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- ========================================== -->
        <!-- VIEW: WORKFORCE VERIFICATION (AJAX)        -->
        <!-- ========================================== -->
        <div id="view-verification">
            <div class="glass-card rounded-3xl shadow-sm w-full flex flex-col overflow-hidden">
                
                <!-- Table Toolbar -->
                <div class="p-5 border-b border-slate-100 bg-white/50 flex flex-col md:flex-row justify-between items-center gap-4">
                    <!-- Custom Show Entries Dropdown -->
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

                    <!-- Custom Search Input -->
                    <div class="relative w-full md:w-96 group">
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 transform -translate-y-1/2 text-slate-400 group-focus-within:text-[#1da85b] transition-colors"></i>
                        <input type="text" id="customSearch" placeholder="Search youth name or ID..." class="w-full pl-10 pr-4 py-2.5 bg-white rounded-xl border border-slate-200 focus:outline-none focus:border-[#1da85b] focus:ring-4 focus:ring-[#1da85b]/10 text-sm font-semibold transition-all shadow-sm">
                    </div>
                </div>

                <!-- DataTable -->
                <div class="overflow-x-auto w-full bg-white">
                    <table id="mcrTable" class="display w-full whitespace-nowrap">
                        <thead>
                            <tr>
                                <th class="rounded-tl-xl w-1/4">Citizen Identity</th>
                                <th class="w-1/4">Self-Declared Information</th>
                                <th class="w-1/2 text-left">Official MCR Verification Controls</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Data fetched dynamically via Server-Side AJAX -->
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
            <span class="text-sm font-bold tracking-wide text-white">Registry Updated</span>
            <span class="text-xs text-slate-400 font-medium">MCR record synced to database.</span>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        $(document).ready(function() {
            <?php if ($current_page === 'dashboard'): ?>
                // CHARTS INITIALIZATION
                Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
                const tTip = { backgroundColor: '#0f172a', titleFont: {size: 13, weight: 'bold'}, bodyFont: {size: 12}, padding: 12, cornerRadius: 8, displayColors: false };

                new Chart(document.getElementById('vitalChart'), {
                    type: 'doughnut',
                    data: { 
                        labels: ['Alive & Active', 'Deceased'], 
                        datasets: [{ 
                            data: [
                                <?= $mcr_counts['Alive']; ?>, 
                                <?= $mcr_counts['Deceased']; ?>
                            ], 
                            backgroundColor: ['#14b8a6', '#f43f5e'], 
                            borderWidth: 0, hoverOffset: 4 
                        }] 
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '75%', plugins: { tooltip: tTip, legend: { position: 'bottom', labels: { usePointStyle: true, padding: 15, boxWidth: 8, font: {weight: '600'} } } } }
                });
            <?php else: ?>
                // DATATABLES AJAX SERVER-SIDE PROCESSING
                const table = $('#mcrTable').DataTable({ 
                    "serverSide": true,
                    "processing": true,
                    "ajax": {
                        "url": "?page=verification&ajax=fetch_mcr",
                        "type": "POST"
                    },
                    "columns": [
                        { "data": "identity" },
                        { "data": "info" },
                        { "data": "controls", "orderable": false }
                    ],
                    "pageLength": 10,
                    "order": [],
                    "dom": '<"px-4 pb-4"t><"px-6 py-4 border-t border-slate-100 bg-slate-50/30 flex flex-col md:flex-row justify-between items-center rounded-b-3xl"ip>',
                    "language": { 
                        "info": "Showing <span class='font-bold text-slate-700'>_START_</span> to <span class='font-bold text-slate-700'>_END_</span> of <span class='font-bold text-slate-700'>_TOTAL_</span> citizens",
                        "processing": "<div class='text-center py-4 font-bold text-[#1da85b] animate-pulse flex items-center justify-center gap-2'><i class='ph-bold ph-spinner-gap animate-spin'></i> Loading Database...</div>"
                    }
                });
                
                $('#customSearch').on('keyup', function() { table.search(this.value).draw(); });
                $('#tableLength').on('change', function() { table.page.len(this.value).draw(); });
            <?php endif; ?>
        });

        // AJAX STATUS UPDATER (Fixed for Safe JS Class Injection)
        function updateMCR(selectElement, recordId, dbSource, updateType) {
            const newStatus = selectElement.value;
            
            // 1. Safely strip out specific color classes using regex
            let classes = selectElement.className
                .replace(/\bbg-[a-z]+-50\b/g, '')
                .replace(/\btext-[a-z]+-700\b/g, '')
                .replace(/\bbg-slate-50\b/g, '');
            
            // 2. Adjust local UI Colors based on the type of select
            if (updateType === 'birth' && newStatus === 'Verified') {
                classes += ' bg-emerald-50 text-emerald-700';
            } else if (updateType === 'civil' && newStatus === 'Married') {
                classes += ' bg-blue-50 text-blue-700';
            } else if (updateType === 'vital' && newStatus === 'Deceased') {
                classes += ' bg-rose-50 text-rose-700';
            } else {
                classes += ' bg-slate-50';
            }

            selectElement.className = classes.replace(/\s+/g, ' ').trim();

            // 3. Send to database
            $.ajax({
                url: 'mcr_portal.php',
                type: 'POST',
                data: { action: 'verify_mcr', id: recordId, status: newStatus, source: dbSource, update_type: updateType },
                success: function(response) {
                    if (response.success) {
                        const toast = document.getElementById('toast');
                        toast.classList.add('show');
                        setTimeout(() => toast.classList.remove('show'), 3000);
                        
                        // Dynamically Add/Remove Dimming & Strikethrough if Deceased
                        const tr = selectElement.closest('tr');
                        if (updateType === 'vital') {
                            const nameEl = tr.querySelector('td:first-child p.font-bold');
                            if (newStatus === 'Deceased') {
                                tr.classList.add('opacity-60', 'bg-slate-50');
                                if(nameEl) {
                                    nameEl.classList.add('text-slate-500', 'line-through');
                                    nameEl.classList.remove('text-slate-900');
                                }
                            } else {
                                tr.classList.remove('opacity-60', 'bg-slate-50');
                                if(nameEl) {
                                    nameEl.classList.remove('text-slate-500', 'line-through');
                                    nameEl.classList.add('text-slate-900');
                                }
                            }
                        }
                    } else {
                        alert("Error saving: " + response.error);
                    }
                },
                error: function(xhr, status, error) {
                    alert("Failed to connect to the server. Please try again.");
                    console.error("AJAX Error:", error);
                }
            });
        }

        // ==========================================
        // MODERN EXPAND / COLLAPSE SIDEBAR LOGIC
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