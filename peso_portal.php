<?php
session_start();

// 1. Auth Checks
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Ensure only PESO or Master Admins can access
if ($_SESSION['role'] !== 'peso' && $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require 'db_connect.php'; 

// ==========================================
// 2. LOCAL AI ALGORITHM: COSINE SIMILARITY
// Pure PHP Vector Math Engine (Zero API Keys)
// ==========================================
function getTokens($text) {
    $text = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', ' ', $text));
    $tokens = array_filter(explode(' ', $text), 'strlen');
    return array_values($tokens);
}

function calculateCosineSimilarity($textA, $textB) {
    $tokensA = getTokens($textA);
    $tokensB = getTokens($textB);
    
    $vocabulary = array_unique(array_merge($tokensA, $tokensB));
    if (count($vocabulary) == 0) return 0;

    $vectorA = [];
    $vectorB = [];

    foreach ($vocabulary as $word) {
        $vectorA[] = count(array_keys($tokensA, $word));
        $vectorB[] = count(array_keys($tokensB, $word));
    }

    $dotProduct = 0;
    $magnitudeA = 0;
    $magnitudeB = 0;

    for ($i = 0; $i < count($vocabulary); $i++) {
        $dotProduct += $vectorA[$i] * $vectorB[$i];
        $magnitudeA += pow($vectorA[$i], 2);
        $magnitudeB += pow($vectorB[$i], 2);
    }

    if ($magnitudeA == 0 || $magnitudeB == 0) return 0;
    
    $similarity = $dotProduct / (sqrt($magnitudeA) * sqrt($magnitudeB));
    return round($similarity * 100); 
}

// Mock Job Database
$local_jobs = [
    1 => ['title' => 'Hardware Assistant', 'reqs' => 'welding construction manual labor high school nc ii physical hardware'],
    2 => ['title' => 'Data Encoder', 'reqs' => 'computer ms office excel typing college graduate data entry it'],
    3 => ['title' => 'Barangay Health Aide', 'reqs' => 'health care nursing caregiving first aid red cross medical college'],
    4 => ['title' => 'Auto Mechanic', 'reqs' => 'automotive mechanic repair engine nc ii driving cars vehicle'],
];

// ==========================================
// 3. AJAX HANDLERS
// ==========================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // AJAX: Verification Update
    if ($_POST['action'] === 'verify_peso') {
        try {
            $update_type = $_POST['update_type'];
            $column = '';
            if ($update_type === 'general') $column = 'peso_general_status';
            elseif ($update_type === 'skills') $column = 'peso_skills_cert';
            elseif ($update_type === 'gov') $column = 'peso_gov_program';

            if ($column !== '') {
                if ($_POST['source'] === 'profile') {
                    $stmt = $pdo->prepare("UPDATE youth_profiles SET $column = :status, peso_verified_at = CURRENT_TIMESTAMP WHERE id = :id");
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
    
    // AJAX: Smart Match Engine
    if ($_POST['action'] === 'get_matches') {
        $job_id = (int)$_POST['job_id'];
        if (!isset($local_jobs[$job_id])) {
            echo json_encode(['success' => false, 'error' => 'Job not found.']);
            exit;
        }
        
        $job_reqs = $local_jobs[$job_id]['reqs'];
        
        try {
            $stmt = $pdo->query("
                SELECT id, full_name, barangay, skills, tesda_nc, education_level, 'profile' as source 
                FROM youth_profiles WHERE peso_general_status = 'Unemployed'
                UNION ALL 
                SELECT id, full_name, barangay, NULL as skills, NULL as tesda_nc, educational_background as education_level, 'registry' as source 
                FROM youth_registry WHERE peso_general_status = 'Unemployed' AND full_name NOT IN (SELECT full_name FROM youth_profiles)
            ");
            $unemployed_youths = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $matches = [];
            foreach ($unemployed_youths as $y) {
                $youth_vector_text = ($y['skills'] ?? '') . ' ' . ($y['tesda_nc'] ?? '') . ' ' . ($y['education_level'] ?? '');
                $score = calculateCosineSimilarity($job_reqs, $youth_vector_text);
                
                if ($score > 0) {
                    $matches[] = [
                        'name' => $y['full_name'],
                        'barangay' => $y['barangay'],
                        'skills' => $y['skills'] ?: 'N/A',
                        'nc' => $y['tesda_nc'] ?: 'None',
                        'score' => $score
                    ];
                }
            }
            
            usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
            echo json_encode(['success' => true, 'matches' => array_slice($matches, 0, 5)]);
            
        } catch(Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// ==========================================
// AJAX HANDLER: DATATABLES SERVER-SIDE
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fetch_youths') {
    header('Content-Type: application/json');
    
    $draw = $_POST['draw'] ?? 1;
    $start = (int)($_POST['start'] ?? 0);
    $length = (int)($_POST['length'] ?? 10);
    $searchValue = $_POST['search']['value'] ?? '';

    $baseQuery = "
        SELECT 
            id, youth_id, full_name, birthdate, NULL as age_registry, barangay, 
            skills, employment_status, tesda_nc, 
            peso_general_status, peso_skills_cert, peso_gov_program, peso_verified_at, 'profile' as source 
        FROM youth_profiles 
        UNION ALL 
        SELECT 
            id, NULL as youth_id, full_name, NULL as birthdate, age as age_registry, barangay, 
            NULL as skills, NULL as employment_status, NULL as tesda_nc, 
            peso_general_status, peso_skills_cert, peso_gov_program, NULL as peso_verified_at, 'registry' as source 
        FROM youth_registry 
        WHERE full_name NOT IN (SELECT full_name FROM youth_profiles)
    ";

    $totalStmt = $pdo->query("SELECT COUNT(*) FROM ($baseQuery) as t");
    $recordsTotal = $totalStmt->fetchColumn();

    $searchSql = "";
    $params = [];
    if (!empty($searchValue)) {
        $searchSql = " WHERE full_name LIKE :search OR youth_id LIKE :search OR skills LIKE :search ";
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
        // Calculate Age
        $age = 'N/A';
        if ($row['source'] === 'profile' && !empty($row['birthdate'])) {
            $bday = new DateTime($row['birthdate']);
            $today = new DateTime('today');
            $age = $bday->diff($today)->y . " yrs";
        } elseif ($row['source'] === 'registry' && !empty($row['age_registry'])) {
            $age = $row['age_registry'] . " yrs";
        }
        
        // Identity Column
        $icon = $row['source'] === 'profile' 
            ? '<div class="w-10 h-10 rounded-full bg-[#1da85b]/10 text-[#1da85b] flex items-center justify-center font-bold text-sm border border-[#1da85b]/20 shadow-sm"><i class="ph-fill ph-identification-card text-lg"></i></div>'
            : '<div class="w-10 h-10 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center font-bold text-sm border border-slate-200 shadow-sm"><i class="ph-fill ph-archive text-lg"></i></div>';
        $yid = $row['source'] === 'profile' ? htmlspecialchars($row['youth_id']) : 'LEGACY DB';
        $name = htmlspecialchars($row['full_name']);
        $brgy = htmlspecialchars($row['barangay'] ?: 'Unknown');
        
        $identity = '
            <div class="flex items-center gap-3 mb-2">
                '.$icon.'
                <div>
                    <p class="text-sm font-bold text-slate-900">'.$name.'</p>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-0.5">'.$yid.'</p>
                </div>
            </div>
            <div class="pl-13 text-xs text-slate-500 font-medium">'.$age.' • Brgy. '.$brgy.'</div>
        ';

        // Skills Column
        $emp = htmlspecialchars($row['employment_status'] ?: 'Status Not Declared');
        $skills = htmlspecialchars($row['skills'] ?: 'N/A');
        $nc = htmlspecialchars($row['tesda_nc'] ?: 'None');
        
        $skillsProfile = '
            <p class="text-sm font-bold text-slate-700 truncate max-w-[200px] mb-1">'.$emp.'</p>
            <p class="text-[11px] font-semibold text-slate-400 truncate max-w-[200px]">Skills: '.$skills.'<br>NC: '.$nc.'</p>
        ';

        // PESO Controls Column
        $genStatus = $row['peso_general_status'];
        $genClass = $genStatus === 'Employed' ? 'bg-emerald-50 text-emerald-700' : ($genStatus === 'Unemployed' ? 'bg-rose-50 text-rose-700' : 'bg-slate-50');
        
        $skillStatus = $row['peso_skills_cert'];
        $skillClass = !empty($skillStatus) ? 'bg-purple-50 text-purple-700' : 'bg-slate-50';
        
        $govStatus = $row['peso_gov_program'];
        $govClass = !empty($govStatus) ? 'bg-blue-50 text-blue-700' : 'bg-slate-50';

        $controls = '
            <div class="flex flex-col md:flex-row gap-3 items-start w-full">
                <!-- General -->
                <div class="flex flex-col w-full md:w-1/3 relative">
                    <label class="text-[10px] font-bold uppercase text-slate-400 mb-1">General Status</label>
                    <select onchange="updatePeso(this, '.$row['id'].', \''.$row['source'].'\', \'general\')" class="w-full text-xs font-bold border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-[#1da85b] focus:ring-2 focus:ring-[#1da85b]/20 shadow-sm cursor-pointer appearance-none '.$genClass.'">
                        <option value="">-- Select --</option>
                        <option value="Employed" '.($genStatus === 'Employed' ? 'selected' : '').'>Employed</option>
                        <option value="Unemployed" '.($genStatus === 'Unemployed' ? 'selected' : '').'>Unemployed</option>
                    </select>
                    <i class="ph-bold ph-caret-down absolute right-3 bottom-[10px] pointer-events-none opacity-50 text-xs"></i>
                </div>
                <!-- Skills -->
                <div class="flex flex-col w-full md:w-1/3 relative">
                    <label class="text-[10px] font-bold uppercase text-slate-400 mb-1">Skills & Cert</label>
                    <select onchange="updatePeso(this, '.$row['id'].', \''.$row['source'].'\', \'skills\')" class="w-full text-xs font-bold border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-[#1da85b] focus:ring-2 focus:ring-[#1da85b]/20 shadow-sm cursor-pointer appearance-none '.$skillClass.'">
                        <option value="">-- None --</option>
                        <option value="TESDA Training" '.($skillStatus === 'TESDA Training' ? 'selected' : '').'>TESDA Training</option>
                        <option value="NC Certificates" '.($skillStatus === 'NC Certificates' ? 'selected' : '').'>NC Certificates</option>
                    </select>
                    <i class="ph-bold ph-caret-down absolute right-3 bottom-[10px] pointer-events-none opacity-50 text-xs"></i>
                </div>
                <!-- Gov -->
                <div class="flex flex-col w-full md:w-1/3 relative">
                    <label class="text-[10px] font-bold uppercase text-slate-400 mb-1">Gov Program</label>
                    <select onchange="updatePeso(this, '.$row['id'].', \''.$row['source'].'\', \'gov\')" class="w-full text-xs font-bold border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-[#1da85b] focus:ring-2 focus:ring-[#1da85b]/20 shadow-sm cursor-pointer appearance-none '.$govClass.'">
                        <option value="">-- None --</option>
                        <option value="TUPAD" '.($govStatus === 'TUPAD' ? 'selected' : '').'>TUPAD</option>
                        <option value="GIP" '.($govStatus === 'GIP' ? 'selected' : '').'>GIP</option>
                        <option value="SPES" '.($govStatus === 'SPES' ? 'selected' : '').'>SPES</option>
                    </select>
                    <i class="ph-bold ph-caret-down absolute right-3 bottom-[10px] pointer-events-none opacity-50 text-xs"></i>
                </div>
            </div>
        ';

        $data[] = [
            "identity" => $identity,
            "skills" => $skillsProfile,
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
// 4. PAGE ROUTING & DASHBOARD OPTIMIZATION
// ==========================================
$current_page = $_GET['page'] ?? 'dashboard';

// We ONLY run these heavy counts if the user is on the dashboard! 
if ($current_page === 'dashboard') {
    try {
        $general_counts = ['Employed' => 0, 'Unemployed' => 0, 'Unverified' => 0];
        $gov_counts = ['TUPAD' => 0, 'GIP' => 0, 'SPES' => 0];
        $skills_counts = ['TESDA Training' => 0, 'NC Certificates' => 0];
        $total_youth = 0;

        // Base Table Union for Aggregation
        $unionQuery = "
            SELECT peso_general_status, peso_skills_cert, peso_gov_program FROM youth_profiles
            UNION ALL
            SELECT peso_general_status, peso_skills_cert, peso_gov_program FROM youth_registry WHERE full_name NOT IN (SELECT full_name FROM youth_profiles)
        ";

        // Query 1: Total & General Status
        $genStmt = $pdo->query("SELECT peso_general_status as status, COUNT(*) as count FROM ($unionQuery) t GROUP BY peso_general_status");
        foreach($genStmt as $row) {
            $status = $row['status'] ?: 'Unverified';
            if(isset($general_counts[$status])) $general_counts[$status] += $row['count'];
            else $general_counts['Unverified'] += $row['count'];
            $total_youth += $row['count'];
        }

        // Query 2: Gov Programs
        $govStmt = $pdo->query("SELECT peso_gov_program as prog, COUNT(*) as count FROM ($unionQuery) t WHERE peso_gov_program IS NOT NULL AND peso_gov_program != '' GROUP BY peso_gov_program");
        foreach($govStmt as $row) {
            $prog = $row['prog'];
            if(isset($gov_counts[$prog])) $gov_counts[$prog] += $row['count'];
        }

        // Query 3: Skills
        $skillStmt = $pdo->query("SELECT peso_skills_cert as cert, COUNT(*) as count FROM ($unionQuery) t WHERE peso_skills_cert IS NOT NULL AND peso_skills_cert != '' GROUP BY peso_skills_cert");
        foreach($skillStmt as $row) {
            $cert = $row['cert'];
            if(isset($skills_counts[$cert])) $skills_counts[$cert] += $row['count'];
        }
        
    } catch (Exception $e) {
        die("<div style='padding:20px; color:red;'>Database Connection Failed: " . $e->getMessage() . "</div>");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PESO Portal | TALA OS</title>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script> tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, colors: { brand: { 500: '#1da85b' } } } } } </script>
    
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
    </style>
</head>
<body class="text-slate-800 antialiased selection:bg-[#1da85b] selection:text-white flex overflow-x-hidden">

    <!-- ========================================== -->
    <!-- MODERN DEDICATED PESO SIDEBAR              -->
    <!-- ========================================== -->
    <aside class="w-64 bg-white/90 backdrop-blur-xl border-r border-slate-200/60 fixed inset-y-0 left-0 z-50 flex flex-col shadow-[4px_0_24px_rgba(0,0,0,0.02)]">
        
        <div class="h-24 flex items-center justify-between px-5 border-b border-slate-100 mb-4 bg-white">
            <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-10 h-10 bg-slate-100 border border-slate-200 rounded-xl flex items-center justify-center shrink-0 shadow-sm">
                    <img src="peso.png" alt="PESO" class="w-7 h-7 object-contain">
                </div>
                <div class="truncate">
                    <h1 class="text-lg font-black text-slate-900 leading-tight">PESO</h1>
                    <p class="text-[10px] font-bold tracking-widest uppercase text-slate-400">TALA OS Portal</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 px-4 space-y-1.5 overflow-y-auto custom-scrollbar">
            <p class="px-2 text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 mt-2">Core Access</p>
            
            <a href="?page=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page === 'dashboard' ? 'bg-[#f0fdf4] text-[#15803d] border border-[#dcfce7] shadow-sm' : 'text-slate-600 hover:bg-slate-50 border border-transparent' ?>">
                <i class="<?= $current_page === 'dashboard' ? 'ph-fill text-[#15803d]' : 'ph-bold' ?> ph-chart-pie-slice text-lg shrink-0"></i> 
                <span>Employment Overview</span>
            </a>
            
            <a href="?page=verification" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page === 'verification' ? 'bg-[#f0fdf4] text-[#15803d] border border-[#dcfce7] shadow-sm' : 'text-slate-600 hover:bg-slate-50 border border-transparent' ?>">
                <i class="<?= $current_page === 'verification' ? 'ph-fill text-[#15803d]' : 'ph-bold' ?> ph-check-square-offset text-lg shrink-0"></i> 
                <span>Workforce Verification</span>
            </a>

            <p class="px-2 text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-2 mt-6">Algorithmic Match</p>
            
            <a href="?page=match" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page === 'match' ? 'bg-blue-50 text-blue-700 border border-blue-200 shadow-sm' : 'text-slate-600 hover:bg-slate-50 border border-transparent' ?>">
                <i class="<?= $current_page === 'match' ? 'ph-fill text-blue-600' : 'ph-bold' ?> ph-cpu text-lg shrink-0"></i> 
                <span>Smart Match Engine</span>
            </a>
        </nav>
        
        <div class="p-4 border-t border-slate-100">
            <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/60 flex items-center justify-between">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-10 h-10 bg-[#dcfce7] text-[#16a34a] rounded-full flex items-center justify-center font-bold border-2 border-white shadow-sm shrink-0">
                        <i class="ph-bold ph-user-gear text-lg"></i>
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-sm font-extrabold text-slate-800 truncate">PESO Admin</p>
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
    <div class="ml-64 p-8 w-full max-w-[1500px] mx-auto pb-12">
        
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">
                <?php 
                    if ($current_page === 'dashboard') echo 'Employment Analytics';
                    elseif ($current_page === 'verification') echo 'Workforce Control Center';
                    elseif ($current_page === 'match') echo 'AI Job Matching Engine';
                ?>
            </h1>
        </div>

        <?php if ($current_page === 'dashboard'): ?>
        <!-- ========================================== -->
        <!-- VIEW: DASHBOARD OVERVIEW                   -->
        <!-- ========================================== -->
        <div id="view-dashboard">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-6">
                <div class="col-span-2 glass-card p-5 rounded-2xl shadow-sm flex flex-col justify-center hover:border-slate-300 transition-colors bg-white">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Total Labor Force</p>
                    <p class="text-3xl font-extrabold text-slate-900"><?= number_format($total_youth); ?></p>
                </div>
                <div class="col-span-2 glass-card p-5 rounded-2xl shadow-sm flex flex-col justify-center hover:border-slate-300 transition-colors bg-white">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Verified Employed</p>
                    <p class="text-3xl font-extrabold text-slate-900"><?= number_format($general_counts['Employed']); ?></p>
                </div>
                <div class="col-span-2 glass-card p-5 rounded-2xl shadow-sm flex flex-col justify-center hover:border-slate-300 transition-colors bg-white">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Unemployed</p>
                    <p class="text-3xl font-extrabold text-slate-900"><?= number_format($general_counts['Unemployed']); ?></p>
                </div>
                <div class="col-span-2 glass-card p-5 rounded-2xl shadow-sm flex flex-col justify-center hover:border-slate-300 transition-colors bg-white">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Total in Gov Programs</p>
                    <p class="text-3xl font-extrabold text-slate-900"><?= number_format(array_sum($gov_counts)); ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-1 glass-card p-6 rounded-[2rem] shadow-sm bg-white">
                    <h3 class="text-sm font-bold text-slate-800 mb-6 flex items-center gap-2"><i class="ph-fill ph-chart-donut icon-gradient text-lg"></i> Government Programs</h3>
                    <div class="relative h-48 w-full flex items-center justify-center"><canvas id="govChart"></canvas></div>
                </div>
                
                <div class="lg:col-span-2 glass-card p-6 rounded-[2rem] shadow-sm bg-white">
                    <h3 class="text-sm font-bold text-slate-800 mb-4 flex items-center gap-2"><i class="ph-fill ph-book-open icon-gradient text-lg"></i> Active Categorization</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-500 mb-2">Skills & Certifications</p>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-bold text-slate-700">TESDA Training</span>
                                <span class="text-sm font-black text-slate-900"><?= number_format($skills_counts['TESDA Training']) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-bold text-slate-700">NC Certificates</span>
                                <span class="text-sm font-black text-slate-900"><?= number_format($skills_counts['NC Certificates']) ?></span>
                            </div>
                        </div>
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                            <p class="text-xs font-bold text-slate-500 mb-2">Government Support Programs</p>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-bold text-slate-700">TUPAD</span>
                                <span class="text-sm font-black text-slate-900"><?= number_format($gov_counts['TUPAD']) ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-bold text-slate-700">SPES</span>
                                <span class="text-sm font-black text-slate-900"><?= number_format($gov_counts['SPES']) ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm font-bold text-slate-700">GIP</span>
                                <span class="text-sm font-black text-slate-900"><?= number_format($gov_counts['GIP']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($current_page === 'match'): ?>
        <!-- ========================================== -->
        <!-- VIEW: AI SMART MATCH ENGINE                -->
        <!-- ========================================== -->
        <div id="view-match" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <div class="lg:col-span-1 glass-card p-6 rounded-[2rem] shadow-sm flex flex-col">
                <div class="mb-6 border-b border-slate-100 pb-5">
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-3 shadow-sm border border-blue-100">
                        <i class="ph-bold ph-cpu text-2xl"></i>
                    </div>
                    <h2 class="text-lg font-extrabold text-slate-900 leading-tight">Match Algorithm</h2>
                    <p class="text-xs text-slate-500 mt-1">Select an active job opening. The TF-IDF algorithm will process all <span class="font-bold text-slate-700">Unemployed</span> youth and score them based on matching text vectors.</p>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Select Open Vacancy</label>
                        <select id="jobSelector" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold outline-none focus:border-[#1da85b] focus:ring-2 focus:ring-[#1da85b]/20 shadow-sm cursor-pointer">
                            <option value="" disabled selected>-- Choose Job Profile --</option>
                            <?php foreach($local_jobs as $id => $job): ?>
                                <option value="<?= $id ?>" data-reqs="<?= htmlspecialchars($job['reqs']) ?>"><?= htmlspecialchars($job['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 mt-2 hidden" id="reqsBox">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Keywords Vector</p>
                        <p class="text-xs font-semibold text-slate-600" id="reqsText"></p>
                    </div>
                </div>

                <button onclick="runAlgorithm()" class="w-full btn-gradient font-bold py-3.5 rounded-xl shadow-md mt-auto flex items-center justify-center gap-2">
                    Execute Match Logic <i class="ph-bold ph-play"></i>
                </button>
            </div>

            <div class="lg:col-span-2 glass-card p-6 rounded-[2rem] shadow-sm flex flex-col bg-slate-50/50">
                <h3 class="text-base font-extrabold text-slate-900 mb-4 flex items-center gap-2">
                    <i class="ph-fill ph-users-three text-slate-400 text-lg"></i> Top 5 Algorithm Candidates
                </h3>
                
                <div id="matchResults" class="space-y-4">
                    <div class="py-16 text-center text-slate-400 flex flex-col items-center justify-center">
                        <i class="ph-fill ph-robot text-5xl mb-3 opacity-30"></i>
                        <p class="font-bold text-sm text-slate-500">Awaiting execution.</p>
                        <p class="text-xs mt-1 max-w-sm">Select a job and run the algorithm to compute Cosine Similarity scores across the database.</p>
                    </div>
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
                        <input type="text" id="customSearch" placeholder="Search youth name, skills, or ID..." class="w-full pl-10 pr-4 py-2.5 bg-white rounded-xl border border-slate-200 focus:outline-none focus:border-[#1da85b] focus:ring-4 focus:ring-[#1da85b]/10 text-sm font-semibold transition-all shadow-sm">
                    </div>
                </div>

                <div class="overflow-x-auto w-full bg-white">
                    <table id="pesoTable" class="display w-full whitespace-nowrap">
                        <thead>
                            <tr>
                                <th class="rounded-tl-xl w-1/4">Workforce Identity</th>
                                <th class="w-1/4">Declared Skills / Self Status</th>
                                <th class="w-1/2 text-left">PESO Verification Controls (Separated)</th>
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

                new Chart(document.getElementById('govChart'), {
                    type: 'doughnut',
                    data: { 
                        labels: ['TUPAD', 'GIP', 'SPES'], 
                        datasets: [{ 
                            data: [
                                <?= $gov_counts['TUPAD']; ?>, 
                                <?= $gov_counts['GIP']; ?>, 
                                <?= $gov_counts['SPES']; ?>
                            ], 
                            backgroundColor: ['#3b82f6', '#0ea5e9', '#6366f1'], 
                            borderWidth: 0, hoverOffset: 4 
                        }] 
                    },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { tooltip: tTip, legend: { position: 'right', labels: { usePointStyle: true, padding: 15, boxWidth: 8, font: {weight: '600'} } } } }
                });
                
            <?php elseif ($current_page === 'match'): ?>
                $('#jobSelector').change(function() {
                    const reqs = $(this).find(':selected').data('reqs');
                    if (reqs) {
                        $('#reqsText').text(reqs.split(' ').join(', '));
                        $('#reqsBox').removeClass('hidden');
                    }
                });
                
            <?php else: ?>
                // ==========================================
                // DATATABLES AJAX SERVER-SIDE PROCESSING
                // ==========================================
                const table = $('#pesoTable').DataTable({ 
                    "serverSide": true,
                    "processing": true,
                    "ajax": {
                        "url": "?page=verification&ajax=fetch_youths",
                        "type": "POST"
                    },
                    "columns": [
                        { "data": "identity" },
                        { "data": "skills" },
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

        // AJAX: Smart Match Engine Executer
        function runAlgorithm() {
            const jobId = $('#jobSelector').val();
            if (!jobId) {
                alert('Please select a job opening first.');
                return;
            }

            const resultsContainer = $('#matchResults');
            resultsContainer.html(`
                <div class="py-16 text-center text-blue-500 flex flex-col items-center justify-center animate-pulse">
                    <i class="ph-bold ph-spinner-gap text-4xl mb-3 animate-spin"></i>
                    <p class="font-bold text-sm">Computing Text Vectors...</p>
                </div>
            `);

            $.ajax({
                url: 'peso_portal.php',
                type: 'POST',
                data: { action: 'get_matches', job_id: jobId },
                success: function(response) {
                    if(response.success) {
                        if(response.matches.length === 0) {
                            resultsContainer.html('<p class="text-sm font-bold text-slate-500 text-center py-10">No unemployed candidates match this vector.</p>');
                            return;
                        }
                        
                        let html = '';
                        response.matches.forEach(m => {
                            let matchColor = m.score > 70 ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : 'text-amber-600 bg-amber-50 border-amber-200';
                            let barColor = m.score > 70 ? 'bg-emerald-500' : 'bg-amber-500';
                            
                            html += `
                            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 class="font-extrabold text-slate-900 text-base leading-tight">${m.name}</h4>
                                        <p class="text-[10px] font-bold uppercase text-slate-400 tracking-widest mt-0.5">Brgy. ${m.barangay}</p>
                                    </div>
                                    <div class="px-3 py-1 rounded-lg border font-black text-sm ${matchColor} flex items-center gap-1.5 shadow-sm">
                                        <i class="ph-fill ph-target"></i> ${m.score}% Match
                                    </div>
                                </div>
                                <div class="w-full bg-slate-100 rounded-full h-1.5 mb-4">
                                    <div class="${barColor} h-1.5 rounded-full" style="width: ${m.score}%"></div>
                                </div>
                                <p class="text-[11px] font-semibold text-slate-500"><span class="font-bold text-slate-700">Vector Data:</span> ${m.skills} • ${m.nc}</p>
                            </div>
                            `;
                        });
                        resultsContainer.html(html);
                    } else {
                        alert("Error calculating matches: " + response.error);
                    }
                }
            });
        }

       // AJAX: PESO Verification
        function updatePeso(selectElement, recordId, dbSource, updateType) {
            const newStatus = selectElement.value;
            
            // 1. Safely strip out all specific color classes without causing DOM exceptions
            let classes = selectElement.className
                .replace(/\bbg-[a-z]+-50\b/g, '')
                .replace(/\btext-[a-z]+-700\b/g, '')
                .replace(/\bbg-slate-50\b/g, '');
            
            // 2. Apply the exact new colors based on the dropdown choice
            if (updateType === 'general') {
                if (newStatus === 'Employed') classes += ' bg-emerald-50 text-emerald-700';
                else if (newStatus === 'Unemployed') classes += ' bg-rose-50 text-rose-700';
                else classes += ' bg-slate-50';
            } else if (updateType === 'skills') {
                if (newStatus !== '') classes += ' bg-purple-50 text-purple-700';
                else classes += ' bg-slate-50';
            } else if (updateType === 'gov') {
                if (newStatus !== '') classes += ' bg-blue-50 text-blue-700';
                else classes += ' bg-slate-50';
            }
            
            // 3. Clean up spaces and apply the new classes safely
            selectElement.className = classes.replace(/\s+/g, ' ').trim();

            // 4. Execute AJAX call to save to database instantly
            $.ajax({
                url: 'peso_portal.php',
                type: 'POST',
                data: { 
                    action: 'verify_peso', 
                    id: recordId, 
                    status: newStatus, 
                    source: dbSource, 
                    update_type: updateType 
                },
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