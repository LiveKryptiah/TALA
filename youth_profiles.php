<?php
session_start();
require 'db_connect.php';

// Security check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// 1. CAPTURE SEARCH & FILTER INPUTS
$search = $_GET['search'] ?? '';
$filter_voter = $_GET['voter'] ?? ''; 
$filter_osy = $_GET['osy'] ?? '';     
$filter_activity = $_GET['activity'] ?? ''; // NEW: Capture the activity filter

// 2. BUILD THE SQL QUERY DYNAMICALLY
$conditions = [];
$params = [];

// Search Condition
if (!empty($search)) {
    $conditions[] = "(full_name LIKE :search OR youth_id LIKE :search)";
    $params[':search'] = "%$search%";
}

// Security: Barangay Filter Logic
if ($_SESSION['role'] === 'admin') {
    $filter_barangay = $_GET['barangay'] ?? '';
    if (!empty($filter_barangay)) {
        $conditions[] = "barangay = :barangay";
        $params[':barangay'] = $filter_barangay;
    }
} else {
    $filter_barangay = $_SESSION['barangay_assigned'];
    $conditions[] = "barangay = :barangay";
    $params[':barangay'] = $filter_barangay;
}

// Voter Filter 
if ($filter_voter === '1' || $filter_voter === '0') {
    $conditions[] = "is_voter = :voter";
    $params[':voter'] = $filter_voter;
}

// OSY Filter
if ($filter_osy === '1') {
    $conditions[] = "employment_status = 'Out-of-School Youth (OSY)'";
}

// NEW: Official Activity Ledger Filter
if (!empty($filter_activity)) {
    // This subquery checks if the youth has ANY record in the activities table matching the selected category
    $conditions[] = "EXISTS (SELECT 1 FROM youth_activities WHERE youth_activities.youth_id = youth_profiles.youth_id AND activity_category = :activity)";
    $params[':activity'] = $filter_activity;
}

// Assemble the WHERE clause
$whereSql = "";
if (count($conditions) > 0) {
    $whereSql = " WHERE " . implode(" AND ", $conditions);
}

try {
    // Execute the dynamic query
    $stmt = $pdo->prepare("SELECT * FROM youth_profiles $whereSql ORDER BY created_at DESC");
    $stmt->execute($params);
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

function getAge($birthdate) {
    $dob = new DateTime($birthdate);
    $now = new DateTime();
    return $now->diff($dob)->y;
}

$all_barangays = [
    "Annafunan", "Antagan I", "Antagan II", "Arcon", "Balug", "Banig", "Bantug", 
    "Barangay District 1 (Poblacion)", "Barangay District 2 (Poblacion)", 
    "Barangay District 3 (Poblacion)", "Barangay District 4 (Poblacion)", 
    "Bayabo East", "Caligayan", "Camasi", "Carpentero", "Compania", "Cumabao", 
    "Fermeldy (Hacienda San Francisco)", "Fugu Abajo", "Fugu Norte", "Fugu Sur", 
    "Lalauanan", "Lanna", "Lapogan", "Lingaling (Poblacion)", "Liwanag", 
    "Malamag East", "Malamag West", "Maligaya (Poblacion)", "Minanga", "Moldero", 
    "Namnama", "Paragu", "Pilitan", "San Mateo", "San Pedro", "San Vicente", "Santa", 
    "Santa Catalina", "Santa Visitacion (Maggayu)", "Santo Niño", "Sinippil", 
    "Sisim Abajo", "Sisim Alto", "Tunggui", "Ugad"
];

$displayName = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin';
$displayInitial = strtoupper(substr($displayName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Youth Profiles | TALA OS</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { brand: { 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d' } },
                    boxShadow: { 'soft': '0 20px 40px -15px rgba(0,0,0,0.05)' }
                }
            }
        }
    </script>
    
    <style>
        /* Ambient Light Mode Background Animations */
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .ambient-bg { position: fixed; inset: 0; z-index: -1; background: #f8fafc; overflow: hidden; pointer-events: none; }
        .blob { position: absolute; filter: blur(100px); opacity: 0.6; border-radius: 50%; mix-blend-multiply: true; animation: blob 10s infinite alternate ease-in-out; }
        .animation-delay-2000 { animation-delay: 2s; }
        
        .glass-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.8); }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        
        .hover-gradient-btn { transition: all 0.3s ease; }
        .hover-gradient-btn:hover {
            background: linear-gradient(135deg, #16a34a, #eab308) !important;
            color: white !important;
            border-color: transparent !important;
            box-shadow: 0 10px 25px -5px rgba(22, 163, 74, 0.4) !important;
        }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="text-slate-800 antialiased flex h-screen overflow-hidden selection:bg-brand-500 selection:text-white">

    <div class="ambient-bg">
        <div class="blob bg-brand-200 w-[500px] h-[500px] -top-[10%] -left-[10%]"></div>
        <div class="blob bg-yellow-200 w-[500px] h-[500px] bottom-[10%] -right-[10%] animation-delay-2000"></div>
    </div>

    <?php 
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        include 'sidebar_admin.php';
    } else {
        include 'sidebar_sk.php';
    }
    ?>

    <main class="flex-1 overflow-y-auto p-4 md:p-8 ml-64 relative z-10 custom-scrollbar">
        <div class="max-w-7xl mx-auto space-y-8">
            
            <div class="flex justify-between items-end pb-4 border-b border-slate-200">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Youth Directory</h1>
                    <p class="text-sm font-semibold text-slate-500 mt-1 uppercase tracking-widest">
                        <?= ($_SESSION['role'] === 'admin') ? 'Municipal Registry' : 'Barangay ' . htmlspecialchars($_SESSION['barangay_assigned']) . ' Registry' ?>
                    </p>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-extrabold text-slate-800"><?= htmlspecialchars($displayName) ?></p>
                        <p class="text-[10px] font-bold text-brand-600 uppercase tracking-widest">TALA System Access</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-600 to-yellow-500 text-white flex items-center justify-center font-extrabold text-xl shadow-lg shadow-brand-500/20">
                        <?= $displayInitial ?>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <p class="text-sm font-medium text-slate-600 flex items-center gap-2">
                    <i class="ph-fill ph-database text-slate-400"></i> Total Records Found: <span class="font-extrabold text-slate-900"><?= count($profiles) ?></span>
                </p>
                <div class="flex gap-3 w-full sm:w-auto">
                    <a href="export_csv.php" class="flex-1 sm:flex-none bg-white text-slate-700 hover:text-slate-900 border border-slate-200 px-5 py-2.5 rounded-xl shadow-sm font-bold text-sm flex items-center justify-center gap-2 transition-all hover:bg-slate-50 hover:border-slate-300">
                        <i class="ph-bold ph-download-simple text-lg"></i> Export CSV
                    </a>
                    <a href="add_profile.php" class="flex-1 sm:flex-none bg-gradient-to-r from-brand-600 to-yellow-500 text-white px-5 py-2.5 rounded-xl shadow-lg shadow-brand-500/30 font-bold text-sm flex items-center justify-center gap-2 transform hover:-translate-y-0.5 transition-all">
                        <i class="ph-bold ph-user-plus text-lg"></i> Register New
                    </a>
                </div>
            </div>

            <div class="glass-card p-5 rounded-[1.5rem] shadow-soft border border-slate-200">
                <form method="GET" action="youth_profiles.php" class="flex flex-wrap gap-4 items-center w-full">
                    
                    <div class="flex-1 min-w-[200px] relative">
                        <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name or ID..." class="w-full pl-11 pr-4 py-3 bg-white/50 border border-slate-200 text-slate-800 rounded-xl text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm placeholder-slate-400">
                    </div>

                    <div class="w-full md:w-auto flex-1 min-w-[150px]">
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <select name="barangay" class="w-full py-3 px-4 bg-white/50 border border-slate-200 text-slate-700 rounded-xl text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm appearance-none cursor-pointer">
                                <option value="">All Barangays</option>
                                <?php foreach ($all_barangays as $brgy): ?>
                                    <option value="<?= $brgy ?>" <?= ($filter_barangay === $brgy) ? 'selected' : '' ?>><?= $brgy ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <div class="relative">
                                <i class="ph-bold ph-lock-key absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" readonly value="<?= htmlspecialchars($_SESSION['barangay_assigned']) ?>" class="w-full pl-11 pr-4 py-3 bg-slate-100/50 border border-slate-200 text-slate-500 rounded-xl text-sm font-bold cursor-not-allowed shadow-inner">
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="w-full md:w-auto flex-1 min-w-[150px]">
                        <select name="activity" class="w-full py-3 px-4 bg-white/50 border border-slate-200 text-slate-700 rounded-xl text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm appearance-none cursor-pointer">
                            <option value="">Any Program</option>
                            <option value="Youth Camp" <?= ($filter_activity === 'Youth Camp') ? 'selected' : '' ?>>🏕️ Youth Camp</option>
                            <option value="Leadership Training" <?= ($filter_activity === 'Leadership Training') ? 'selected' : '' ?>>🎯 Leadership Training</option>
                            <option value="Sports" <?= ($filter_activity === 'Sports') ? 'selected' : '' ?>>🏀 Sports</option>
                            <option value="Scholarship" <?= ($filter_activity === 'Scholarship') ? 'selected' : '' ?>>🎓 Scholarship</option>
                            <option value="Seminars" <?= ($filter_activity === 'Seminars') ? 'selected' : '' ?>>🗣️ Seminars</option>
                            <option value="Tree Planting" <?= ($filter_activity === 'Tree Planting') ? 'selected' : '' ?>>🌳 Tree Planting</option>
                            <option value="Volunteerism" <?= ($filter_activity === 'Volunteerism') ? 'selected' : '' ?>>🤝 Volunteerism</option>
                            <option value="Other" <?= ($filter_activity === 'Other') ? 'selected' : '' ?>>📌 Other</option>
                        </select>
                    </div>

                    <div class="w-full md:w-auto flex-1 min-w-[140px]">
                        <select name="voter" class="w-full py-3 px-4 bg-white/50 border border-slate-200 text-slate-700 rounded-xl text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm appearance-none cursor-pointer">
                            <option value="">Voter Status</option>
                            <option value="1" <?= ($filter_voter === '1') ? 'selected' : '' ?>>Registered Voter</option>
                            <option value="0" <?= ($filter_voter === '0') ? 'selected' : '' ?>>Non-Voter</option>
                        </select>
                    </div>

                    <div class="w-full md:w-auto flex-1 min-w-[130px]">
                        <select name="osy" class="w-full py-3 px-4 bg-white/50 border border-slate-200 text-slate-700 rounded-xl text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm appearance-none cursor-pointer">
                            <option value="">OSY Status</option>
                            <option value="1" <?= ($filter_osy === '1') ? 'selected' : '' ?>>OSY Only</option>
                        </select>
                    </div>

                    <div class="flex gap-2 w-full md:w-auto justify-end">
                        <button type="submit" class="bg-slate-900 text-white px-6 py-3 rounded-xl font-bold shadow-sm transition-all hover:-translate-y-0.5 flex items-center justify-center gap-2 hover-gradient-btn">
                            <i class="ph-bold ph-funnel text-lg"></i> Filter
                        </button>
                        <?php if(!empty($search) || (!empty($filter_barangay) && $_SESSION['role'] === 'admin') || $filter_voter !== '' || $filter_osy !== '' || $filter_activity !== ''): ?>
                            <a href="youth_profiles.php" class="bg-white hover:bg-red-50 text-slate-500 hover:text-red-600 border border-slate-200 hover:border-red-200 px-5 py-3 rounded-xl font-bold transition-all shadow-sm flex items-center justify-center" title="Clear Filters">
                                <i class="ph-bold ph-x text-lg"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="glass-card rounded-[1.5rem] shadow-soft border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 text-[10px] font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-200">
                                <th class="px-6 py-5">Youth ID</th>
                                <th class="px-6 py-5">Full Name</th>
                                <th class="px-6 py-5">Barangay & Age</th>
                                <th class="px-6 py-5">Status Indicators</th>
                                <th class="px-6 py-5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm font-medium">
                            <?php if (count($profiles) > 0): ?>
                                <?php foreach ($profiles as $profile): ?>
                                    <tr class="hover:bg-slate-50/80 transition-colors group">
                                        
                                        <td class="px-6 py-4">
                                            <span class="font-mono text-brand-600 font-extrabold text-xs bg-brand-50 px-2 py-1 rounded-md border border-brand-100">
                                                <?= htmlspecialchars($profile['youth_id']) ?>
                                            </span>
                                        </td>
                                        
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-3">
                                                <?php if($profile['photo_path']): ?>
                                                    <img src="<?= htmlspecialchars($profile['photo_path']) ?>" class="w-10 h-10 rounded-xl object-cover border border-slate-200 shadow-sm">
                                                <?php else: ?>
                                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-slate-100 to-slate-200 border border-slate-200 flex items-center justify-center text-slate-400 shadow-sm">
                                                        <i class="ph-fill ph-user text-lg"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="font-extrabold text-slate-800"><?= htmlspecialchars($profile['full_name']) ?></span>
                                            </div>
                                        </td>
                                        
                                        <td class="px-6 py-4">
                                            <p class="font-semibold text-slate-700"><?= htmlspecialchars($profile['barangay']) ?></p>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5 flex items-center gap-1">
                                                <i class="ph-bold ph-calendar-blank"></i> <?= getAge($profile['birthdate']) ?> yrs old
                                            </p>
                                        </td>
                                        
                                        <td class="px-6 py-4">
                                            <div class="flex flex-wrap gap-1.5 items-center">
                                                <?php 
                                                    $emp = htmlspecialchars($profile['employment_status']);
                                                    $bgClass = 'bg-slate-100 text-slate-600 border-slate-200';
                                                    if ($emp == 'Out-of-School Youth (OSY)') $bgClass = 'bg-orange-50 text-orange-600 border-orange-200';
                                                    elseif ($emp == 'Student') $bgClass = 'bg-blue-50 text-blue-600 border-blue-200';
                                                    elseif ($emp == 'Employed') $bgClass = 'bg-brand-50 text-brand-700 border-brand-200';
                                                ?>
                                                <span class="px-2.5 py-1 rounded-md text-[9px] font-extrabold uppercase tracking-widest border shadow-sm <?= $bgClass ?>">
                                                    <?= $emp ?>
                                                </span>
                                                
                                                <?php if($profile['is_voter']): ?>
                                                    <span class="px-2.5 py-1 rounded-md text-[9px] font-extrabold uppercase tracking-widest bg-purple-50 text-purple-600 border border-purple-200 shadow-sm flex items-center gap-1">
                                                        <i class="ph-fill ph-check-circle"></i> Voter
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        
                                        <td class="px-6 py-4 text-right">
                                            <a href="view_profile.php?id=<?= $profile['youth_id'] ?>" class="inline-flex items-center justify-center w-9 h-9 bg-white text-slate-400 rounded-xl hover:bg-brand-50 hover:text-brand-600 border border-slate-200 transition-colors shadow-sm">
                                                <i class="ph-bold ph-arrow-right text-lg"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-16 text-center">
                                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 border border-slate-100 mb-3">
                                            <i class="ph-fill ph-magnifying-glass text-3xl text-slate-300"></i>
                                        </div>
                                        <p class="text-slate-500 font-bold text-lg">No records found</p>
                                        <p class="text-xs text-slate-400 mt-1 font-medium">Try adjusting your search or filter parameters.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <?php include 'genesis_widget.php'; ?>

</body>
</html>
