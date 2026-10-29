<?php
session_start();

// 1. Auth Checks
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'mswdo' && $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require 'db_connect.php'; 

// ==========================================
// 2. YOUTH RISK INDEX (YRI) AI ALGORITHM
// Zero-API Weighted Decision Tree
// ==========================================
function calculateRiskScore($youth) {
    $score = 0;
    $flags = [];

    // 1. Education Factor (DepEd)
    $edu = $youth['deped_status'] ?? '';
    if (stripos($edu, 'Drop') !== false || stripos($edu, 'Not Enrolled') !== false) {
        $score += 30;
        $flags[] = "School Dropout";
    }

    // 2. Economic Factor (PESO)
    $emp = $youth['peso_general_status'] ?? '';
    if ($emp === 'Unemployed') {
        $score += 20;
        $flags[] = "Unemployed";
    }

    // 3. Health & Welfare Factor (MHO)
    $mho = $youth['mho_pregnancy_status'] ?? '';
    if ($mho === 'Pregnant' || $mho === 'Post-Partum / Young Mother') {
        $score += 25;
        $flags[] = "Early Pregnancy/Motherhood";
    } elseif ($mho === 'At Risk / Monitoring') {
        $score += 15;
        $flags[] = "MHO Health Risk";
    } elseif ($mho === 'No Record' || empty($mho)) {
        $score += 10;
        $flags[] = "Unmonitored Health";
    }

    // 4. Social Status Factor (MSWDO / MCR)
    if (($youth['mswdo_solo_parent'] ?? '') === 'Verified') {
        $score += 15;
        $flags[] = "Solo Parent";
    }
    if (($youth['mswdo_indigent'] ?? '') === 'Verified') {
        $score += 10;
        $flags[] = "Registered Indigent";
    }

    // Categorization
    $level = 'Low';
    $color = 'bg-emerald-50 text-emerald-700 border-emerald-200';
    
    if ($score >= 60) {
        $level = 'Critical';
        $color = 'bg-rose-50 text-rose-700 border-rose-200';
    } elseif ($score >= 40) {
        $level = 'High';
        $color = 'bg-orange-50 text-orange-700 border-orange-200';
    } elseif ($score >= 20) {
        $level = 'Moderate';
        $color = 'bg-amber-50 text-amber-700 border-amber-200';
    }

    return [
        'score' => $score,
        'level' => $level,
        'color' => $color,
        'flags' => $flags
    ];
}

// ==========================================
// 3. AJAX HANDLERS
// ==========================================

// A. STATUS UPDATES
if (isset($_POST['action']) && $_POST['action'] === 'verify_mswdo') {
    header('Content-Type: application/json');
    try {
        $update_type = $_POST['update_type'];
        $column = '';
        if ($update_type === '4ps') $column = 'mswdo_4ps';
        elseif ($update_type === 'pwd') $column = 'mswdo_pwd';
        elseif ($update_type === 'solo_parent') $column = 'mswdo_solo_parent';
        elseif ($update_type === 'indigent') $column = 'mswdo_indigent';

        if ($column !== '') {
            if ($_POST['source'] === 'profile') {
                $stmt = $pdo->prepare("UPDATE youth_profiles SET $column = :status, mswdo_verified_at = CURRENT_TIMESTAMP WHERE id = :id");
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
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fetch_youths') {
    header('Content-Type: application/json');
    
    $draw = $_POST['draw'] ?? 1;
    $start = (int)($_POST['start'] ?? 0);
    $length = (int)($_POST['length'] ?? 10);
    $searchValue = $_POST['search']['value'] ?? '';

    $baseQuery = "
        SELECT 
            id, youth_id, full_name, birthdate, NULL as age_registry, barangay, 
            mswdo_4ps, mswdo_pwd, mswdo_solo_parent, mswdo_indigent, mswdo_verified_at, 'profile' as source 
        FROM youth_profiles 
        UNION ALL 
        SELECT 
            id, NULL as youth_id, full_name, NULL as birthdate, age as age_registry, barangay, 
            mswdo_4ps, mswdo_pwd, mswdo_solo_parent, mswdo_indigent, NULL as mswdo_verified_at, 'registry' as source 
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
        // Identity HTML
        $icon = $row['source'] === 'profile' 
            ? '<div class="w-10 h-10 rounded-full bg-[#1da85b]/10 text-[#1da85b] flex items-center justify-center font-bold text-sm border border-[#1da85b]/20 shadow-sm"><i class="ph-fill ph-identification-card text-lg"></i></div>'
            : '<div class="w-10 h-10 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-sm border border-slate-200 shadow-sm"><i class="ph-fill ph-archive text-lg"></i></div>';
        $yid = $row['source'] === 'profile' ? htmlspecialchars($row['youth_id']) : 'LEGACY DB';
        $name = htmlspecialchars($row['full_name']);
        
        $identity = '
            <div class="flex items-center gap-3">
                '.$icon.'
                <div>
                    <p class="text-sm font-bold text-slate-900">'.$name.'</p>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-0.5">'.$yid.'</p>
                </div>
            </div>';

        // Barangay HTML
        $brgy = htmlspecialchars($row['barangay'] ?: 'Unknown');
        $barangayHtml = '<span class="text-sm font-bold text-slate-700">Brgy. '.$brgy.'</span>';

        // Controls HTML
        $f_4ps = $row['mswdo_4ps'];
        $c_4ps = $f_4ps === 'Verified' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-50';
        
        $f_pwd = $row['mswdo_pwd'];
        $c_pwd = $f_pwd === 'Verified' ? 'bg-amber-50 text-amber-700' : 'bg-slate-50';
        
        $f_solo = $row['mswdo_solo_parent'];
        $c_solo = $f_solo === 'Verified' ? 'bg-blue-50 text-blue-700' : 'bg-slate-50';
        
        $f_ind = $row['mswdo_indigent'];
        $c_ind = $f_ind === 'Verified' ? 'bg-rose-50 text-rose-700' : 'bg-slate-50';

        $controls = '
            <div class="flex flex-col lg:flex-row gap-3 items-start w-full">
                <div class="flex flex-col w-full lg:w-1/4 relative">
                    <label class="text-[10px] font-bold uppercase text-slate-400 mb-1">4Ps Beneficiary</label>
                    <select onchange="updateMswdo(this, '.$row['id'].', \''.$row['source'].'\', \'4ps\')" class="w-full text-[11px] font-bold border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-[#1da85b] focus:ring-2 focus:ring-[#1da85b]/20 shadow-sm cursor-pointer appearance-none '.$c_4ps.'">
                        <option value="">-- Select --</option>
                        <option value="Verified" '.($f_4ps === 'Verified' ? 'selected' : '').'>Verified</option>
                        <option value="No Record" '.($f_4ps === 'No Record' ? 'selected' : '').'>No Record</option>
                    </select>
                    <i class="ph-bold ph-caret-down absolute right-3 bottom-[10px] pointer-events-none opacity-50 text-xs"></i>
                </div>
                <div class="flex flex-col w-full lg:w-1/4 relative">
                    <label class="text-[10px] font-bold uppercase text-slate-400 mb-1">PWD</label>
                    <select onchange="updateMswdo(this, '.$row['id'].', \''.$row['source'].'\', \'pwd\')" class="w-full text-[11px] font-bold border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-[#1da85b] focus:ring-2 focus:ring-[#1da85b]/20 shadow-sm cursor-pointer appearance-none '.$c_pwd.'">
                        <option value="">-- Select --</option>
                        <option value="Verified" '.($f_pwd === 'Verified' ? 'selected' : '').'>Verified</option>
                        <option value="No Record" '.($f_pwd === 'No Record' ? 'selected' : '').'>No Record</option>
                    </select>
                    <i class="ph-bold ph-caret-down absolute right-3 bottom-[10px] pointer-events-none opacity-50 text-xs"></i>
                </div>
                <div class="flex flex-col w-full lg:w-1/4 relative">
                    <label class="text-[10px] font-bold uppercase text-slate-400 mb-1">Solo Parent</label>
                    <select onchange="updateMswdo(this, '.$row['id'].', \''.$row['source'].'\', \'solo_parent\')" class="w-full text-[11px] font-bold border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-[#1da85b] focus:ring-2 focus:ring-[#1da85b]/20 shadow-sm cursor-pointer appearance-none '.$c_solo.'">
                        <option value="">-- Select --</option>
                        <option value="Verified" '.($f_solo === 'Verified' ? 'selected' : '').'>Verified</option>
                        <option value="No Record" '.($f_solo === 'No Record' ? 'selected' : '').'>No Record</option>
                    </select>
                    <i class="ph-bold ph-caret-down absolute right-3 bottom-[10px] pointer-events-none opacity-50 text-xs"></i>
                </div>
                <div class="flex flex-col w-full lg:w-1/4 relative">
                    <label class="text-[10px] font-bold uppercase text-slate-400 mb-1">Indigent</label>
                    <select onchange="updateMswdo(this, '.$row['id'].', \''.$row['source'].'\', \'indigent\')" class="w-full text-[11px] font-bold border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-[#1da85b] focus:ring-2 focus:ring-[#1da85b]/20 shadow-sm cursor-pointer appearance-none '.$c_ind.'">
                        <option value="">-- Select --</option>
                        <option value="Verified" '.($f_ind === 'Verified' ? 'selected' : '').'>Verified</option>
                        <option value="No Record" '.($f_ind === 'No Record' ? 'selected' : '').'>No Record</option>
                    </select>
                    <i class="ph-bold ph-caret-down absolute right-3 bottom-[10px] pointer-events-none opacity-50 text-xs"></i>
                </div>
            </div>
        ';

        $data[] = [
            "identity" => $identity,
            "barangay" => $barangayHtml,
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
// 4. GLOBAL DATA FETCH (For Radar & Dash Stats)
// ==========================================
$current_page = $_GET['page'] ?? 'dashboard';

try {
    // Grand Unified Query fetching cross-department telemetry for the AI & Dashboard
    $query = "
        SELECT 
            id, youth_id, full_name, birthdate, NULL as age_registry, barangay, 
            mswdo_4ps, mswdo_pwd, mswdo_solo_parent, mswdo_indigent, mswdo_verified_at, 'profile' as source,
            deped_status, peso_general_status, mho_pregnancy_status
        FROM youth_profiles 
        UNION ALL 
        SELECT 
            id, NULL as youth_id, full_name, NULL as birthdate, age as age_registry, barangay, 
            mswdo_4ps, mswdo_pwd, mswdo_solo_parent, mswdo_indigent, NULL as mswdo_verified_at, 'registry' as source,
            deped_enrollment_status as deped_status, peso_general_status, mho_pregnancy_status
        FROM youth_registry 
        WHERE full_name NOT IN (SELECT full_name FROM youth_profiles)
    ";
    
    $stmt = $pdo->query($query);
    $youths = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$youths) $youths = [];

    // Analytics Processing (Runs efficiently in PHP Memory)
    $total_youth = count($youths);
    $mswdo_counts = ['4Ps' => 0, 'PWD' => 0, 'Solo Parent' => 0, 'Indigent' => 0];
    
    // AI Radar Collection
    $critical_youths = [];

    foreach ($youths as $key => $y) {
        if ($y['mswdo_4ps'] === 'Verified') $mswdo_counts['4Ps']++;
        if ($y['mswdo_pwd'] === 'Verified') $mswdo_counts['PWD']++;
        if ($y['mswdo_solo_parent'] === 'Verified') $mswdo_counts['Solo Parent']++;
        if ($y['mswdo_indigent'] === 'Verified') $mswdo_counts['Indigent']++;

        // Calculate YRI Score via Algorithm
        $risk_data = calculateRiskScore($y);
        
        // Only push youth with a score of 60 or higher to the Critical Radar
        if ($risk_data['score'] >= 60) {
            $youths[$key]['risk_data'] = $risk_data; 
            $critical_youths[] = $youths[$key];
        }
    }
    
    // Sort critical youth by highest score first for the Radar Page
    usort($critical_youths, function($a, $b) {
        return $b['risk_data']['score'] <=> $a['risk_data']['score'];
    });
    
} catch (Exception $e) {
    die("<div style='padding:20px; color:red; font-family:sans-serif;'>Database Connection Failed: " . $e->getMessage() . "</div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MSWDO Portal | TALA OS</title>
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

        .risk-bar { transition: width 1s cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
</head>
<body class="text-slate-800 antialiased selection:bg-[#1da85b] selection:text-white flex overflow-x-hidden">

    <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/50 z-40 hidden opacity-0 transition-opacity duration-300 lg:hidden"></div>

    <!-- ========================================== -->
    <!-- MODERN DEDICATED MSWDO SIDEBAR             -->
    <!-- ========================================== -->
    <aside id="sidebar" class="w-64 bg-white/90 backdrop-blur-xl border-r border-slate-200/60 fixed inset-y-0 left-0 z-50 flex flex-col shadow-[4px_0_24px_rgba(0,0,0,0.02)] -translate-x-full lg:translate-x-0">
        
        <div class="h-24 flex items-center justify-between px-5 border-b border-slate-100 mb-4 bg-white sidebar-logo-area">
            <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-10 h-10 bg-slate-100 border border-slate-200 rounded-xl flex items-center justify-center shrink-0 shadow-sm">
                    <img src="mswdo.png" alt="MSWDO" class="w-7 h-7 object-contain">
                </div>
                <div class="sidebar-text truncate">
                    <h1 class="text-lg font-black text-slate-900 leading-tight">MSWDO</h1>
                    <p class="text-[10px] font-bold tracking-widest uppercase text-slate-400">TALA OS Portal</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 px-4 space-y-1.5 overflow-y-auto custom-scrollbar">
            <p class="px-2 text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 mt-2 sidebar-section-title">Core Access</p>
            
            <a href="?page=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page === 'dashboard' ? 'bg-[#f0fdf4] text-[#15803d] border border-[#dcfce7] shadow-sm' : 'text-slate-600 hover:bg-slate-50 border border-transparent' ?>">
                <i class="<?= $current_page === 'dashboard' ? 'ph-fill text-[#15803d]' : 'ph-bold' ?> ph-chart-pie-slice text-lg shrink-0"></i> 
                <span class="sidebar-text truncate">Welfare Overview</span>
            </a>
            
            <a href="?page=verification" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page === 'verification' ? 'bg-[#f0fdf4] text-[#15803d] border border-[#dcfce7] shadow-sm' : 'text-slate-600 hover:bg-slate-50 border border-transparent' ?>">
                <i class="<?= $current_page === 'verification' ? 'ph-fill text-[#15803d]' : 'ph-bold' ?> ph-check-square-offset text-lg shrink-0"></i> 
                <span class="sidebar-text truncate">Beneficiary Validation</span>
            </a>

            <p class="px-2 text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 mt-6 sidebar-section-title">Algorithmic Tracking</p>
            
            <a href="?page=radar" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page === 'radar' ? 'bg-rose-50 text-rose-700 border border-rose-200 shadow-sm' : 'text-slate-600 hover:bg-slate-50 border border-transparent' ?>">
                <i class="<?= $current_page === 'radar' ? 'ph-fill text-rose-600' : 'ph-bold' ?> ph-radar text-lg shrink-0"></i> 
                <span class="sidebar-text truncate">AI Risk Radar</span>
                <?php if (count($critical_youths) > 0): ?>
                    <span class="ml-auto bg-rose-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full shadow-sm"><?= count($critical_youths) ?></span>
                <?php endif; ?>
            </a>
        </nav>
        
        <div class="p-4 border-t border-slate-100">
            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/60 flex items-center justify-between">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-10 h-10 bg-[#dcfce7] text-[#16a34a] rounded-full flex items-center justify-center font-bold border-2 border-white shadow-sm shrink-0">
                        <i class="ph-bold ph-hand-heart text-lg"></i>
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-sm font-extrabold text-slate-800 truncate">MSWDO Admin</p>
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
                <?php 
                    if ($current_page === 'dashboard') echo 'Social Welfare Analytics';
                    elseif ($current_page === 'verification') echo 'Beneficiary Control Center';
                    elseif ($current_page === 'radar') echo 'Early Warning Radar';
                ?>
            </h1>
        </div>

        <?php if ($current_page === 'dashboard'): ?>
        <!-- ========================================== -->
        <!-- VIEW: DASHBOARD OVERVIEW                   -->
        <!-- ========================================== -->
        <div id="view-dashboard">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-6">
                <div class="col-span-2 glass-card p-5 rounded-2xl shadow-sm flex flex-col justify-center hover:border-slate-300 transition-colors">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Total Monitored Youth</p>
                    <p class="text-3xl font-extrabold text-slate-900"><?= number_format($total_youth); ?></p>
                </div>
                <div class="col-span-2 glass-card p-5 rounded-2xl shadow-sm flex flex-col justify-center transition-colors bg-white">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Verified 4Ps</p>
                    <p class="text-3xl font-extrabold text-slate-900"><?= number_format($mswdo_counts['4Ps']); ?></p>
                </div>
                <div class="col-span-2 glass-card p-5 rounded-2xl shadow-sm flex flex-col justify-center transition-colors bg-white">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Registered PWD</p>
                    <p class="text-3xl font-extrabold text-slate-900"><?= number_format($mswdo_counts['PWD']); ?></p>
                </div>
                <div class="col-span-2 glass-card p-5 rounded-2xl shadow-sm flex flex-col justify-center transition-colors bg-white">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Solo Parents</p>
                    <p class="text-3xl font-extrabold text-slate-900"><?= number_format($mswdo_counts['Solo Parent']); ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1 glass-card p-6 rounded-[2rem] shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 mb-6 flex items-center gap-2"><i class="ph-fill ph-chart-donut icon-gradient text-lg"></i> Beneficiary Distribution</h3>
                    <div class="relative h-48 w-full flex items-center justify-center"><canvas id="mswdoChart"></canvas></div>
                </div>
                
                <div class="lg:col-span-2 glass-card p-6 rounded-[2rem] shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="ph-fill ph-hand-heart icon-gradient text-lg"></i> Program Overview</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex flex-col justify-center">
                            <p class="text-xs font-bold text-slate-500 mb-2">Financial Assistance</p>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-bold text-slate-700">4Ps Beneficiaries</span>
                                <span class="text-sm font-bold text-slate-900"><?= number_format($mswdo_counts['4Ps']) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-bold text-slate-700">Registered Indigents</span>
                                <span class="text-sm font-bold text-slate-900"><?= number_format($mswdo_counts['Indigent']) ?></span>
                            </div>
                        </div>
                        
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex flex-col justify-center">
                            <p class="text-xs font-bold text-slate-500 mb-2">Special Sectors</p>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-sm font-bold text-slate-700">PWD (Persons with Disability)</span>
                                <span class="text-sm font-bold text-slate-900"><?= number_format($mswdo_counts['PWD']) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-bold text-slate-700">Solo Parents</span>
                                <span class="text-sm font-bold text-slate-900"><?= number_format($mswdo_counts['Solo Parent']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php elseif ($current_page === 'verification'): ?>
        <!-- ========================================== -->
        <!-- VIEW: BENEFICIARY VERIFICATION (AJAX)      -->
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

                <div class="overflow-x-auto w-full bg-white">
                    <table id="mswdoTable" class="display w-full whitespace-nowrap">
                        <thead>
                            <tr>
                                <th class="rounded-tl-xl w-1/4">Youth Identity</th>
                                <th class="w-1/4">Barangay</th>
                                <th class="w-1/2 text-left">MSWDO Verification Controls (Separated)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- The table body starts EMPTY. Zero lag. DataTables fetches rows dynamically via AJAX. -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php elseif ($current_page === 'radar'): ?>
        <!-- ========================================== -->
        <!-- VIEW: AI RISK RADAR (EARLY WARNING SYSTEM) -->
        <!-- ========================================== -->
        <div id="view-radar">
            
            <div class="bg-rose-50 border border-rose-200 p-6 rounded-[2rem] shadow-sm mb-6 flex flex-col md:flex-row justify-between items-center gap-6">
                <div>
                    <h2 class="text-xl font-black text-rose-700 flex items-center gap-2 mb-1"><i class="ph-fill ph-warning-circle"></i> High-Risk Alerts</h2>
                    <p class="text-sm font-medium text-rose-600">The TALA Algorithmic Expert System has automatically cross-referenced DepEd, PESO, MHO, and MCR databases. The youths listed below have a Youth Risk Index (YRI) of 60 or higher, requiring immediate social intervention.</p>
                </div>
                <div class="bg-white rounded-2xl px-6 py-4 shadow-sm border border-rose-100 flex flex-col items-center shrink-0">
                    <span class="text-[10px] font-black uppercase tracking-widest text-rose-400">Critical Cases</span>
                    <span class="text-4xl font-black text-rose-700 leading-none"><?= count($critical_youths) ?></span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if (count($critical_youths) === 0): ?>
                    <div class="col-span-full py-16 text-center text-slate-400 glass-card rounded-[2rem]">
                        <i class="ph-fill ph-shield-check text-5xl mb-3 text-emerald-400"></i>
                        <h3 class="text-lg font-bold text-slate-600">No Critical Cases Detected</h3>
                        <p class="text-sm">The algorithm did not find any youth exceeding the risk threshold.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($critical_youths as $youth): 
                        $score = $youth['risk_data']['score'];
                        $level = $youth['risk_data']['level'];
                        $color = $youth['risk_data']['color'];
                        $flags = $youth['risk_data']['flags'];
                    ?>
                    <div class="bg-white rounded-[2rem] p-6 border border-slate-200 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden flex flex-col">
                        
                        <!-- Top Header -->
                        <div class="flex justify-between items-start mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-full <?= $color ?> flex items-center justify-center font-bold text-lg border">
                                    <i class="ph-fill ph-warning"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-900 leading-tight"><?= htmlspecialchars($youth['full_name']) ?></h3>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Brgy. <?= htmlspecialchars($youth['barangay']) ?></p>
                                </div>
                            </div>
                            <div class="flex flex-col items-end">
                                <span class="text-2xl font-black text-slate-900"><?= $score ?></span>
                                <span class="text-[9px] font-black uppercase tracking-widest text-rose-500">YRI Score</span>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="w-full bg-slate-100 rounded-full h-1.5 mb-5">
                            <div class="bg-rose-500 h-1.5 rounded-full risk-bar" style="width: <?= min($score, 100) ?>%"></div>
                        </div>

                        <!-- Detected Flags -->
                        <div class="mb-6 flex-1">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Detected Risk Factors:</p>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach($flags as $flag): ?>
                                    <span class="bg-slate-50 text-slate-600 border border-slate-200 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider">
                                        <?= htmlspecialchars($flag) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <button class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs py-3 rounded-xl transition-colors shadow-sm mt-auto">
                            Initiate Intervention Protocol
                        </button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
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
            <span class="text-sm font-bold tracking-wide text-white">Record Saved</span>
            <span class="text-xs text-slate-400 font-medium">Verification safely synced.</span>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        $(document).ready(function() {
            <?php if ($current_page === 'dashboard'): ?>
                Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
                const tTip = { backgroundColor: '#0f172a', titleFont: {size: 13, weight: 'bold'}, bodyFont: {size: 12}, padding: 12, cornerRadius: 8, displayColors: false };

                new Chart(document.getElementById('mswdoChart'), {
                    type: 'doughnut',
                    data: { 
                        labels: ['4Ps', 'PWD', 'Solo Parent', 'Indigent'], 
                        datasets: [{ 
                            data: [
                                <?= $mswdo_counts['4Ps']; ?>, 
                                <?= $mswdo_counts['PWD']; ?>, 
                                <?= $mswdo_counts['Solo Parent']; ?>,
                                <?= $mswdo_counts['Indigent']; ?>
                            ], 
                            backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#f43f5e'], 
                            borderWidth: 0, hoverOffset: 4 
                        }] 
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { tooltip: tTip, legend: { position: 'right', labels: { usePointStyle: true, padding: 15, boxWidth: 8, font: {weight: '600'} } } } }
                });
                
            <?php elseif ($current_page === 'verification'): ?>
                // ==========================================
                // DATATABLES AJAX SERVER-SIDE PROCESSING
                // ==========================================
                const table = $('#mswdoTable').DataTable({ 
                    "serverSide": true,
                    "processing": true,
                    "ajax": {
                        "url": "?page=verification&ajax=fetch_youths",
                        "type": "POST"
                    },
                    "columns": [
                        { "data": "identity" },
                        { "data": "barangay" },
                        { "data": "controls", "orderable": false }
                    ],
                    "pageLength": 10,
                    "order": [],
                    "dom": '<"px-4 pb-4"t><"px-6 py-4 border-t border-slate-100 bg-slate-50/30 flex flex-col md:flex-row justify-between items-center rounded-b-3xl"ip>',
                    "language": { 
                        "info": "Showing <span class='font-bold text-slate-700'>_START_</span> to <span class='font-bold text-slate-700'>_END_</span> of <span class='font-bold text-slate-700'>_TOTAL_</span> youths",
                        "processing": "<div class='text-center py-4 font-bold text-[#1da85b] animate-pulse flex items-center justify-center gap-2'><i class='ph-bold ph-spinner-gap animate-spin'></i> Loading Database...</div>"
                    }
                });
                
                $('#customSearch').on('keyup', function() { table.search(this.value).draw(); });
                $('#tableLength').on('change', function() { table.page.len(this.value).draw(); });
            <?php endif; ?>
        });

        // AJAX: MSWDO Verification (Fixed to prevent DOMException crash)
        function updateMswdo(selectElement, recordId, dbSource, updateType) {
            const newStatus = selectElement.value;
            
            // 1. Safely strip out specific color classes using regex
            let classes = selectElement.className
                .replace(/\bbg-[a-z]+-50\b/g, '')
                .replace(/\btext-[a-z]+-700\b/g, '')
                .replace(/\bbg-slate-50\b/g, '');
            
            // 2. Re-apply the new colors based on logic
            if (updateType === '4ps' && newStatus === 'Verified') classes += ' bg-emerald-50 text-emerald-700';
            else if (updateType === 'pwd' && newStatus === 'Verified') classes += ' bg-amber-50 text-amber-700';
            else if (updateType === 'solo_parent' && newStatus === 'Verified') classes += ' bg-blue-50 text-blue-700';
            else if (updateType === 'indigent' && newStatus === 'Verified') classes += ' bg-rose-50 text-rose-700';
            else classes += ' bg-slate-50';

            // 3. Clean up spaces and assign back securely
            selectElement.className = classes.replace(/\s+/g, ' ').trim();

            // 4. Send Database Update
            $.ajax({
                url: 'mswdo_portal.php',
                type: 'POST',
                data: { action: 'verify_mswdo', id: recordId, status: newStatus, source: dbSource, update_type: updateType },
                success: function(response) {
                    if (response.success) {
                        const toast = document.getElementById('toast');
                        toast.classList.add('show');
                        setTimeout(() => toast.classList.remove('show'), 3000);
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
    </script>
</body>
</html>