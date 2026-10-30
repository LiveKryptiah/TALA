<?php
// sk2_youth.php - SK OS 2.0 Youth Registry & Interactive Map
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] !== 'sub-admin' && $_SESSION['role'] !== 'sk_admin')) {
    header("Location: sk2_login.php"); exit();
}

$barangay = $_SESSION['admin_barangay'] ?? 'Balug';
$message = ''; $msgType = '';
$activeTab = $_GET['tab'] ?? 'map';

// 1. BULLETPROOF DATABASE SETUP
try {
    // Create the table safely if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS `youth_registry` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `first_name` VARCHAR(100) NULL,
        `last_name` VARCHAR(100) NULL,
        `full_name` VARCHAR(255) NOT NULL,
        `age` INT DEFAULT 18,
        `vulnerability_status` VARCHAR(50) DEFAULT 'None',
        `latitude` DECIMAL(10, 8) NULL,
        `longitude` DECIMAL(11, 8) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Safely inject new columns if the table already existed but was missing them
    $new_columns = [
        "age" => "INT DEFAULT 18",
        "vulnerability_status" => "VARCHAR(50) DEFAULT 'None'",
        "latitude" => "DECIMAL(10, 8) NULL",
        "longitude" => "DECIMAL(11, 8) NULL",
        "first_name" => "VARCHAR(100) NULL",
        "last_name" => "VARCHAR(100) NULL"
    ];
    
    foreach ($new_columns as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE `youth_registry` ADD COLUMN `$col` $def");
        } catch (Exception $e) {
            // Column already exists, ignore and continue
        }
    }
} catch (Exception $e) {} // Prevent 500 error if DB refuses alterations

// 2. SEED MOCK DATA (Only if empty, centered in Balug, Tumauini)
try {
    $check = $pdo->prepare("SELECT COUNT(*) FROM youth_registry WHERE barangay = ?");
    $check->execute([$barangay]);
    if ($check->fetchColumn() == 0) {
        $baseLat = 17.3015;
        $baseLng = 121.8058;
        $statuses = ['OSY', 'OSY', 'PWD', 'Teenage Parent', 'None', 'None', 'None', 'None']; 
        
        $insert = $pdo->prepare("INSERT INTO youth_registry (barangay, first_name, last_name, full_name, age, vulnerability_status, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        for($i=1; $i<=100; $i++) {
            $lat = $baseLat + (mt_rand(-150, 150) / 15000);
            $lng = $baseLng + (mt_rand(-150, 150) / 15000);
            $stat = $statuses[array_rand($statuses)];
            $age = mt_rand(15, 30);
            $insert->execute([$barangay, "Resident", "Youth $i", "Resident Youth $i", $age, $stat, $lat, $lng]);
        }
    }
} catch (Exception $e) {} // Prevent 500 error on seed failure

// 3. HANDLE MANUAL ADD
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_youth'])) {
    if(empty($_POST['latitude']) || empty($_POST['longitude'])) {
        header("Location: sk2_youth.php?tab=list&msg=no_pin"); exit();
    }
    
    // Attempt to extract first and last name from the dropdown selection
    $fullName = $_POST['full_name'];
    $nameParts = explode(" ", $fullName);
    $firstName = $nameParts[0];
    $lastName = count($nameParts) > 1 ? end($nameParts) : '';

    try {
        $stmt = $pdo->prepare("INSERT INTO youth_registry (barangay, first_name, last_name, full_name, age, vulnerability_status, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$barangay, $firstName, $lastName, $fullName, $_POST['age'], $_POST['vulnerability_status'], $_POST['latitude'], $_POST['longitude']])) {
            header("Location: sk2_youth.php?tab=list&msg=added"); exit();
        }
    } catch (Exception $e) {
        header("Location: sk2_youth.php?tab=list&msg=error"); exit();
    }
}

// 4. HANDLE DELETIONS
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    try {
        $pdo->prepare("DELETE FROM youth_registry WHERE id = ? AND barangay = ?")->execute([$_GET['id'], $barangay]);
        header("Location: sk2_youth.php?tab=list&msg=deleted"); exit();
    } catch (Exception $e) {}
}

// Set Messages
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m == 'added') { $message = 'Youth citizen pinned and registered successfully.'; $msgType = 'success'; }
    if ($m == 'deleted') { $message = 'Citizen record purged.'; $msgType = 'danger'; }
    if ($m == 'no_pin') { $message = 'Error: You must drop a pin on the map to save the location.'; $msgType = 'danger'; }
    if ($m == 'error') { $message = 'Database Error: Could not save record.'; $msgType = 'danger'; }
}

// 5. FETCH DATA SAFELY
$youthRecords = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM youth_registry WHERE barangay = ? ORDER BY id DESC");
    $stmt->execute([$barangay]); 
    $youthRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Calculate Stats Safely
$total = count($youthRecords);
$osy = 0; $pwd = 0; $teen = 0;

foreach($youthRecords as $y) {
    if(isset($y['vulnerability_status'])) {
        if($y['vulnerability_status'] == 'OSY') $osy++;
        if($y['vulnerability_status'] == 'PWD') $pwd++;
        if($y['vulnerability_status'] == 'Teenage Parent') $teen++;
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | Youth Registry & Interactive Map</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- TAILWIND DARK MODE CONFIGURATION -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] } } }
        }
    </script>

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- Leaflet JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <?php include 'sk2_theme_init.php'; ?>

    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        .tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        
        /* Map UI Fixes */
        .leaflet-popup-content-wrapper { border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); padding: 0; overflow: hidden; }
        .dark .leaflet-popup-content-wrapper { background-color: #18181b; color: #fff; border: 1px solid #27272a; }
        .dark .leaflet-popup-tip { background-color: #18181b; border: 1px solid #27272a; }
        .leaflet-popup-content { margin: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* Custom Tooltip on Hover */
        .custom-tooltip { font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 700; border-radius: 8px; padding: 6px 10px; border: none; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .dark .custom-tooltip { background-color: #27272a; color: white; border: 1px solid #3f3f46; }
        
        /* Interactive Pins Styling */
        .custom-pin div { transition: transform 0.2s; }
        .custom-pin:hover div { transform: scale(1.15) translateY(-2px); }
        
        .leaflet-container { z-index: 1 !important; } /* Fixes map overlapping Tailwind modals */
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex h-screen overflow-hidden selection:bg-blue-500 selection:text-white transition-colors duration-300">

<!-- Sidebar Include -->
<?php include 'sk2_sidebar.php'; ?>

<!-- MAIN CONTAINER WITH DARK MODE BACKGROUND -->
<main class="ml-64 flex-1 h-screen overflow-y-auto custom-scrollbar relative bg-slate-50 dark:bg-zinc-950 transition-colors duration-300">

        <div class="relative z-10 p-8 lg:p-10 max-w-[1600px] mx-auto">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1">
                        Youth Registry & Interactive Map
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium">Pinpoint and identify OSY, PWDs, and targeted sectors in Barangay Balug, Tumauini.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <button id="themeToggle" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <i id="themeIcon" class="ph-bold ph-moon text-lg"></i>
                    </button>
                    <!-- Trigger the JS function to open the modal properly so map renders -->
                    <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl font-bold text-sm transition-colors shadow-sm flex items-center gap-2">
                        <i class="ph-bold ph-plus"></i> Add Record
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <?php 
                    $borderColor = 'border-blue-200 dark:border-blue-500/30'; $bgColor = 'bg-blue-50 dark:bg-blue-500/10'; $textColor = 'text-blue-700 dark:text-blue-400'; $icon = 'ph-info';
                    if($msgType=='danger'){ $borderColor = 'border-red-200 dark:border-red-500/30'; $bgColor = 'bg-red-50 dark:bg-red-500/10'; $textColor = 'text-red-700 dark:text-red-400'; $icon = 'ph-warning-circle'; }
                    if($msgType=='success'){ $borderColor = 'border-green-200 dark:border-green-500/30'; $bgColor = 'bg-green-50 dark:bg-green-500/10'; $textColor = 'text-green-700 dark:text-green-400'; $icon = 'ph-check-circle'; }
                ?>
                <div class="mb-8 p-4 rounded-xl border <?= $borderColor ?> <?= $bgColor ?> <?= $textColor ?> flex items-center gap-3 font-bold text-sm transition-colors">
                    <i class="ph-fill <?= $icon ?> text-xl"></i> <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- STAT HUD -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-5 rounded-2xl flex items-center gap-4">
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 rounded-xl flex items-center justify-center text-2xl"><i class="ph-fill ph-users-three"></i></div>
                    <div><p class="text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500">Total Registered</p><h3 class="text-2xl font-black text-slate-900 dark:text-white"><?= $total ?></h3></div>
                </div>
                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-5 rounded-2xl flex items-center gap-4 border-l-4 border-l-red-500">
                    <div class="w-12 h-12 bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400 rounded-xl flex items-center justify-center text-2xl"><i class="ph-fill ph-student"></i></div>
                    <div><p class="text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500">Out-of-School (OSY)</p><h3 class="text-2xl font-black text-slate-900 dark:text-white"><?= $osy ?></h3></div>
                </div>
                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-5 rounded-2xl flex items-center gap-4 border-l-4 border-l-yellow-500">
                    <div class="w-12 h-12 bg-yellow-50 text-yellow-600 dark:bg-yellow-500/10 dark:text-yellow-400 rounded-xl flex items-center justify-center text-2xl"><i class="ph-fill ph-wheelchair"></i></div>
                    <div><p class="text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500">PWD Youth</p><h3 class="text-2xl font-black text-slate-900 dark:text-white"><?= $pwd ?></h3></div>
                </div>
                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-5 rounded-2xl flex items-center gap-4 border-l-4 border-l-orange-500">
                    <div class="w-12 h-12 bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400 rounded-xl flex items-center justify-center text-2xl"><i class="ph-fill ph-baby"></i></div>
                    <div><p class="text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500">Teen Parents</p><h3 class="text-2xl font-black text-slate-900 dark:text-white"><?= $teen ?></h3></div>
                </div>
            </div>

            <!-- TABS -->
            <div class="flex gap-2 border-b border-slate-200 dark:border-zinc-800 mb-6 overflow-x-auto custom-scrollbar pb-2">
                <button onclick="switchTab('map')" id="tabBtn-map" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'map' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-map-pin"></i> Interactive Map Pins
                </button>
                <button onclick="switchTab('list')" id="tabBtn-list" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'list' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-list-dashes"></i> Data Roster
                </button>
            </div>

            <!-- TAB 1: INTERACTIVE MAP -->
            <div id="tab-map" class="tab-content <?= $activeTab == 'map' ? 'active' : '' ?>">
                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-2 rounded-2xl shadow-sm mb-6 flex flex-wrap gap-2">
                    <button onclick="updateMapFilter('All')" id="btnFilter-All" class="map-filter-btn px-4 py-2 rounded-xl text-sm font-bold bg-slate-900 text-white dark:bg-white dark:text-zinc-900 transition-colors">Show All Citizens</button>
                    <button onclick="updateMapFilter('OSY')" id="btnFilter-OSY" class="map-filter-btn px-4 py-2 rounded-xl text-sm font-bold bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/20 dark:hover:text-red-400 transition-colors"><i class="ph-fill ph-student text-red-500"></i> OSY Pins</button>
                    <button onclick="updateMapFilter('PWD')" id="btnFilter-PWD" class="map-filter-btn px-4 py-2 rounded-xl text-sm font-bold bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400 hover:bg-yellow-50 hover:text-yellow-600 dark:hover:bg-yellow-500/20 dark:hover:text-yellow-400 transition-colors"><i class="ph-fill ph-wheelchair text-yellow-500"></i> PWD Pins</button>
                    <button onclick="updateMapFilter('Teenage Parent')" id="btnFilter-Teenage Parent" class="map-filter-btn px-4 py-2 rounded-xl text-sm font-bold bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400 hover:bg-orange-50 hover:text-orange-600 dark:hover:bg-orange-500/20 dark:hover:text-orange-400 transition-colors"><i class="ph-fill ph-baby text-orange-500"></i> Teen Parent Pins</button>
                </div>
                
                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-2 shadow-sm">
                    <!-- The Main Map Container -->
                    <div id="youthMap" class="w-full h-[650px] rounded-xl z-0 relative"></div>
                </div>
            </div>

            <!-- TAB 2: ROSTER -->
            <div id="tab-list" class="tab-content <?= $activeTab == 'list' ? 'active' : '' ?>">
                <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800">
                                    <th class="py-4 px-6 w-20">ID</th>
                                    <th class="py-4 px-6">Citizen Name</th>
                                    <th class="py-4 px-6">Classification</th>
                                    <th class="py-4 px-6">Coordinates</th>
                                    <th class="py-4 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50">
                                <?php if(empty($youthRecords)): ?>
                                    <tr><td colspan="5" class="py-12 text-center text-slate-500 dark:text-zinc-500">Registry is empty.</td></tr>
                                <?php else: foreach($youthRecords as $y): 
                                    $badge = '<span class="px-2 py-1 text-[10px] font-extrabold uppercase rounded bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400">Standard</span>';
                                    if(isset($y['vulnerability_status'])) {
                                        if($y['vulnerability_status'] == 'OSY') $badge = '<span class="px-2 py-1 text-[10px] font-extrabold uppercase rounded bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-400"><i class="ph-bold ph-student"></i> OSY</span>';
                                        if($y['vulnerability_status'] == 'PWD') $badge = '<span class="px-2 py-1 text-[10px] font-extrabold uppercase rounded bg-yellow-100 text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-400"><i class="ph-bold ph-wheelchair"></i> PWD</span>';
                                        if($y['vulnerability_status'] == 'Teenage Parent') $badge = '<span class="px-2 py-1 text-[10px] font-extrabold uppercase rounded bg-orange-100 text-orange-700 dark:bg-orange-500/20 dark:text-orange-400"><i class="ph-bold ph-baby"></i> Teen Parent</span>';
                                    }
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="py-4 px-6 text-xs font-bold text-slate-500 dark:text-zinc-500">YT-<?= str_pad($y['id'], 5, '0', STR_PAD_LEFT) ?></td>
                                    <td class="py-4 px-6 font-bold text-slate-900 dark:text-white">
                                        <?= htmlspecialchars($y['full_name'] ?? 'Unknown') ?> 
                                        <?php if(isset($y['age'])): ?>
                                            <span class="text-[10px] text-slate-400 font-normal ml-2"><?= $y['age'] ?> yrs old</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6"><?= $badge ?></td>
                                    <td class="py-4 px-6 text-xs text-slate-500 font-mono">
                                        <?= isset($y['latitude']) && $y['latitude'] ? number_format($y['latitude'], 4).', '.number_format($y['longitude'], 4) : 'Unmapped' ?>
                                    </td>
                                    <td class="py-4 px-6 text-right">
                                        <a href="?action=delete&id=<?= $y['id'] ?>&tab=list" onclick="return confirm('Delete this record?')" class="text-red-400 hover:text-red-600 transition-colors"><i class="ph-bold ph-trash text-lg"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL: ADD RECORD WITH INTERACTIVE MAP PIN -->
    <div id="modal-add" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm" onclick="closeAddModal()"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Add Youth Record</h3>
                <button onclick="closeAddModal()" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white"><i class="ph-bold ph-x text-xl"></i></button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_youth" value="1">
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-1.5">Select Resident *</label>
                    <select name="full_name" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-2.5 text-sm text-slate-900 dark:text-white outline-none focus:border-blue-500">
                        <option value="" disabled selected>-- Choose from Master List --</option>
                        <option value="Juan Dela Cruz">Juan Dela Cruz</option>
                        <option value="Maria Santos">Maria Santos</option>
                        <option value="Mark Reyes">Mark Reyes</option>
                        <option value="Anna Bautista">Anna Bautista</option>
                        <option value="John Paul Garcia">John Paul Garcia</option>
                        <option value="Luzviminda Torres">Luzviminda Torres</option>
                        <option value="Miguel Mendoza">Miguel Mendoza</option>
                        <option value="Sarah Cruz">Sarah Cruz</option>
                        <option value="Christian Villanueva">Christian Villanueva</option>
                        <option value="Jessa Ramos">Jessa Ramos</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-1.5">Age</label>
                        <input type="number" name="age" required value="18" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-2.5 text-sm text-slate-900 dark:text-white outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-1.5">Classification</label>
                        <select name="vulnerability_status" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-2.5 text-sm text-slate-900 dark:text-white outline-none appearance-none focus:border-blue-500">
                            <option value="None">Standard/Regular</option>
                            <option value="OSY">OSY (Out of School)</option>
                            <option value="PWD">PWD (Disability)</option>
                            <option value="Teenage Parent">Teenage Parent</option>
                        </select>
                    </div>
                </div>

                <!-- INTERACTIVE PIN MAP UI -->
                <div class="p-4 bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl mt-2">
                    <p class="text-xs font-bold text-slate-700 dark:text-zinc-300 mb-3 flex items-center gap-1"><i class="ph-fill ph-map-pin text-red-500"></i> Pin Location</p>
                    
                    <!-- Dropdown mapped to Balug, Tumauini -->
                    <select id="purokDropdown" class="w-full bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white outline-none mb-3">
                        <option value="" disabled selected>-- Jump to Zone/Purok --</option>
                        <option value="17.3030,121.8060">Purok 1 (North Balug)</option>
                        <option value="17.3015,121.8040">Purok 2 (Balug Center)</option>
                        <option value="17.3000,121.8070">Purok 3 (South Balug)</option>
                    </select>

                    <!-- The Mini Form Map -->
                    <div id="formMap" class="w-full h-48 rounded-lg border border-slate-200 dark:border-zinc-700 z-0 bg-slate-200"></div>
                    
                    <!-- Hidden inputs where JS stores the coordinates for the database -->
                    <input type="hidden" name="latitude" id="formLat" required>
                    <input type="hidden" name="longitude" id="formLng" required>
                    
                    <p id="pinStatusText" class="text-[10px] font-bold text-red-500 mt-2">📍 Click on the map to pin residence.</p>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition-colors shadow-sm">Save to Registry</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts for UI & Map Engine -->
    <script>
        // Tab Logic
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('[id^="tabBtn-"]').forEach(btn => {
                btn.className = "px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent";
            });

            document.getElementById('tab-' + tabId).classList.add('active');
            document.getElementById('tabBtn-' + tabId).className = "px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500";
            
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);

            // Re-render main map if map tab is clicked
            if(tabId === 'map') { setTimeout(() => { map.invalidateSize(); }, 200); }
        }

        // ==========================================
        // MINI-MAP: ADD RECORD MODAL (PIN DROPPER)
        // ==========================================
        var formMap = null;
        var formMarker = null;

        function openAddModal() {
            document.getElementById('modal-add').classList.remove('hidden');
            
            // Initialize mini map only once
            if(!formMap) {
                // Set to Balug, Tumauini coordinates
                formMap = L.map('formMap').setView([17.3015, 121.8058], 15);
                
                // Using clean standard CARTO light tiles for the form
                L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { 
                    maxZoom: 19, attribution: '© CARTO' 
                }).addTo(formMap);
                
                // Listen for clicks on the mini map
                formMap.on('click', function(e) {
                    let lat = e.latlng.lat;
                    let lng = e.latlng.lng;
                    
                    if(formMarker) { formMap.removeLayer(formMarker); }
                    
                    formMarker = L.marker([lat, lng]).addTo(formMap);
                    
                    document.getElementById('formLat').value = lat;
                    document.getElementById('formLng').value = lng;
                    
                    document.getElementById('pinStatusText').innerHTML = `<span class="text-green-600"><i class="ph-bold ph-check-circle"></i> Location Pinned Successfully!</span>`;
                });
            }
            setTimeout(() => { formMap.invalidateSize(); }, 300);
        }

        function closeAddModal() {
            document.getElementById('modal-add').classList.add('hidden');
        }

        // Jump to Purok logic
        document.getElementById('purokDropdown').addEventListener('change', function(e) {
            if(e.target.value) {
                let coords = e.target.value.split(',');
                formMap.flyTo([parseFloat(coords[0]), parseFloat(coords[1])], 17);
            }
        });


        // ==========================================
        // MAIN INTERACTIVE PINS ENGINE
        // ==========================================
        const rawYouthData = <?php echo json_encode($youthRecords); ?>;
        // Center main map on Balug, Tumauini
        var map = L.map('youthMap').setView([17.3015, 121.8058], 15);
        
        var lightTiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { maxZoom: 19, attribution: '© CARTO' });
        var darkTiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 19, attribution: '© CARTO' });
        
        if (document.documentElement.classList.contains('dark')) { darkTiles.addTo(map); } else { lightTiles.addTo(map); }

        function updateMapTheme(theme) {
            if(theme === 'dark') { map.removeLayer(lightTiles); darkTiles.addTo(map); } 
            else { map.removeLayer(darkTiles); lightTiles.addTo(map); }
        }

        // Layer group to hold all active markers so we can clear them easily
        var markerLayerGroup = L.layerGroup().addTo(map);

        function updateMapFilter(filterType) {
            // UI Button styling
            document.querySelectorAll('.map-filter-btn').forEach(btn => {
                btn.classList.remove('bg-slate-900', 'text-white', 'dark:bg-white', 'dark:text-zinc-900');
                btn.classList.add('bg-slate-100', 'text-slate-600', 'dark:bg-zinc-800', 'dark:text-zinc-400');
            });
            let activeBtn = document.getElementById('btnFilter-' + filterType);
            activeBtn.classList.remove('bg-slate-100', 'text-slate-600', 'dark:bg-zinc-800', 'dark:text-zinc-400');
            activeBtn.classList.add('bg-slate-900', 'text-white', 'dark:bg-white', 'dark:text-zinc-900');

            // Clear old pins
            markerLayerGroup.clearLayers();

            rawYouthData.forEach(y => {
                if(y.latitude && y.longitude) {
                    
                    let show = false;
                    if (filterType === 'All') { show = true; } 
                    else if (y.vulnerability_status === filterType) { show = true; }

                    if (show) {
                        // Default to Blue (Standard)
                        let colorClass = 'bg-blue-500';
                        let iconClass = 'ph-user';
                        let badgeColor = 'bg-blue-100 text-blue-700';
                        let statusLabel = 'Standard Youth';

                        if (y.vulnerability_status === 'OSY') { 
                            colorClass = 'bg-red-500'; iconClass = 'ph-student'; 
                            badgeColor = 'bg-red-100 text-red-700'; statusLabel = 'Out of School';
                        }
                        if (y.vulnerability_status === 'PWD') { 
                            colorClass = 'bg-yellow-500'; iconClass = 'ph-wheelchair'; 
                            badgeColor = 'bg-yellow-100 text-yellow-700'; statusLabel = 'PWD Citizen';
                        }
                        if (y.vulnerability_status === 'Teenage Parent') { 
                            colorClass = 'bg-orange-500'; iconClass = 'ph-baby'; 
                            badgeColor = 'bg-orange-100 text-orange-700'; statusLabel = 'Teenage Parent';
                        }

                        // Create Custom HTML Pin
                        let customIcon = L.divIcon({
                            className: 'custom-pin',
                            html: `<div class="w-8 h-8 ${colorClass} text-white rounded-full flex items-center justify-center shadow-lg border-2 border-white dark:border-zinc-800"><i class="ph-bold ${iconClass} text-lg"></i></div>`,
                            iconSize: [32, 32],
                            iconAnchor: [16, 16],
                            popupAnchor: [0, -16]
                        });

                        // Create the popup card for when the pin is clicked
                        let popupHTML = `
                            <div class="p-3 bg-white dark:bg-zinc-900 w-48">
                                <h4 class="font-black text-slate-900 dark:text-white text-base mb-0.5 truncate">${y.full_name || 'Unknown Citizen'}</h4>
                                <p class="text-xs font-bold text-slate-500 dark:text-zinc-400 mb-3">${y.age} years old</p>
                                <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-widest rounded ${badgeColor}"><i class="ph-bold ${iconClass}"></i> ${statusLabel}</span>
                            </div>
                        `;

                        let marker = L.marker([y.latitude, y.longitude], {icon: customIcon});
                        
                        // Show name instantly on hover
                        marker.bindTooltip(y.full_name, {direction: 'top', offset: [0, -12], className: 'custom-tooltip'});
                        
                        // Show full card on click
                        marker.bindPopup(popupHTML);
                        
                        markerLayerGroup.addLayer(marker);
                    }
                }
            });
        }

        // Initialize with 'All' filter
        updateMapFilter('All');
        setTimeout(() => { map.invalidateSize(); }, 500);

        // Dark Mode Toggle
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const isDark = document.documentElement.classList.contains('dark');
        if (isDark) { themeIcon.classList.replace('ph-moon', 'ph-sun'); }

        themeToggleBtn.addEventListener('click', function() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                themeIcon.classList.replace('ph-sun', 'ph-moon');
                updateMapTheme('light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                themeIcon.classList.replace('ph-moon', 'ph-sun');
                updateMapTheme('dark');
            }
        });
    </script>
</body>
</html>