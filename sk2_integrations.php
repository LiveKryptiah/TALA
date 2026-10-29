<?php
// sk2_integrations.php - SK OS 2.0 Portals & Unified SK ID
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] !== 'sub-admin' && $_SESSION['role'] !== 'sk_admin')) {
    header("Location: sk2_login.php"); exit();
}

$barangay = $_SESSION['admin_barangay'] ?? 'Municipal Level';
$message = ''; $msgType = '';
$activeTab = $_GET['tab'] ?? 'portals';

// Auto-create Tables for QR Logs
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_qr_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `youth_id` INT NOT NULL,
        `youth_name` VARCHAR(255) NOT NULL,
        `barangay` VARCHAR(100) NOT NULL,
        `event_type` VARCHAR(100) NOT NULL,
        `scanned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// HANDLE QR SCAN SUBMISSION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['log_scan'])) {
    $qr_data = trim($_POST['qr_data']); // Expected format: YOUTH-123
    $event_type = $_POST['event_type'];
    
    // Extract the numeric ID from the QR code (Assuming format "YOUTH-ID")
    $youth_id = intval(str_replace('YOUTH-', '', strtoupper($qr_data)));

    // Verify if the youth exists in this barangay (Updated to youth_registry)
    $stmt = $pdo->prepare("SELECT id, full_name, first_name, last_name FROM youth_registry WHERE id = ? AND barangay = ?");
    $stmt->execute([$youth_id, $barangay]);
    $youth = $stmt->fetch();

    if ($youth) {
        $name = !empty($youth['full_name']) ? $youth['full_name'] : $youth['first_name'] . ' ' . $youth['last_name'];
        $pdo->prepare("INSERT INTO sk2_qr_logs (youth_id, youth_name, barangay, event_type) VALUES (?, ?, ?, ?)")->execute([$youth['id'], $name, $barangay, $event_type]);
        
        header("Location: sk2_integrations.php?tab=scanner&msg=scan_success&name=" . urlencode($name)); exit();
    } else {
        header("Location: sk2_integrations.php?tab=scanner&msg=scan_failed"); exit();
    }
}

// Set Messages
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m == 'scan_success') { 
        $scanned_name = htmlspecialchars($_GET['name'] ?? 'Citizen');
        $message = "Scan successful! Logged attendance for: <strong>{$scanned_name}</strong>"; 
        $msgType = 'success'; 
    }
    if ($m == 'scan_failed') { 
        $message = 'Invalid QR Code. Youth not found in this barangay\'s registry.'; 
        $msgType = 'danger'; 
    }
}

// Fetch Logs
$logs = $pdo->prepare("SELECT * FROM sk2_qr_logs WHERE barangay = ? ORDER BY scanned_at DESC");
$logs->execute([$barangay]); 
$logs = $logs->fetchAll(PDO::FETCH_ASSOC);

// External Portals Directory Array
$portals = [
    ['name' => 'National Youth Commission', 'desc' => 'Official programs, updates, and national youth policies.', 'icon' => 'ph-flag-banner', 'color' => 'blue', 'url' => 'https://nyc.gov.ph/'],
    ['name' => 'TESDA Online Program', 'desc' => 'Free online vocational courses and certifications for youth.', 'icon' => 'ph-certificate', 'color' => 'blue', 'url' => 'https://e-tesda.gov.ph/'],
    ['name' => 'PhilHealth Portal', 'desc' => 'Member registration and health insurance records.', 'icon' => 'ph-heartbeat', 'color' => 'green', 'url' => 'https://memberinquiry.philhealth.gov.ph/'],
    ['name' => 'Pag-IBIG Fund (Virtual)', 'desc' => 'Account creation and savings tracking for young professionals.', 'icon' => 'ph-piggy-bank', 'color' => 'red', 'url' => 'https://www.pagibigfundservices.com/'],
    ['name' => 'DOLE Youth Employment', 'desc' => 'Special Program for Employment of Students (SPES) & JobStart.', 'icon' => 'ph-briefcase', 'color' => 'blue', 'url' => 'https://www.dole.gov.ph/'],
    ['name' => 'Tumauini LGU Services', 'desc' => 'Local municipal portals, business permits, and local announcements.', 'icon' => 'ph-buildings', 'color' => 'yellow', 'url' => '#']
];
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | Portals & SK ID</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- HTML5 QR Code Library for Live Scanning -->
    <script src="https://unpkg.com/html5-qrcode"></script>

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
        
        /* Scanner specific styles */
        #qr-reader { border-radius: 16px; overflow: hidden; border: 2px solid #e2e8f0; }
        .dark #qr-reader { border-color: #27272a; }
        #qr-reader__scan_region { background: #000; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex h-screen overflow-hidden selection:bg-blue-500 selection:text-white transition-colors duration-300">

    
    <!-- Sidebar Include -->
<?php include 'sk2_sidebar.php'; ?>

<!-- Add 'ml-64' to push it past the sidebar, and 'flex-1' to fill the rest of the screen -->
<main class="ml-64 flex-1 h-screen overflow-y-auto flex flex-col relative bg-slate-50">

<!-- Your main content probably looks like this right now -->
<main class="h-screen overflow-y-auto flex flex-col relative bg-slate-50">

    <main class="flex-1 overflow-y-auto custom-scrollbar relative">
        <div class="relative z-10 p-8 lg:p-10 max-w-[1600px] mx-auto">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1">
                        Integration & Unified ID
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium">National portals and live QR pass scanner for <?= htmlspecialchars($barangay) ?>.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <button id="themeToggle" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <i id="themeIcon" class="ph-bold ph-moon text-lg"></i>
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

            <!-- TABS -->
            <div class="flex gap-2 border-b border-slate-200 dark:border-zinc-800 mb-8 overflow-x-auto custom-scrollbar pb-2">
                <button onclick="switchTab('portals')" id="tabBtn-portals" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'portals' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-plugs-connected"></i> External Portals
                </button>
                <button onclick="switchTab('scanner')" id="tabBtn-scanner" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'scanner' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-scan"></i> SK QR Pass Scanner
                </button>
                <button onclick="switchTab('logs')" id="tabBtn-logs" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'logs' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-list-numbers"></i> Scan Logs
                </button>
            </div>

            <!-- TAB 1: EXTERNAL PORTALS -->
            <div id="tab-portals" class="tab-content <?= $activeTab == 'portals' ? 'active' : '' ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($portals as $p): 
                        $colorClass = "text-{$p['color']}-600 bg-{$p['color']}-50 dark:bg-{$p['color']}-500/10 dark:text-{$p['color']}-400";
                    ?>
                    <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 transition-colors shadow-sm dark:shadow-none flex flex-col hover:border-blue-500 dark:hover:border-blue-500 group">
                        <div class="w-12 h-12 rounded-xl <?= $colorClass ?> flex items-center justify-center text-2xl mb-4 group-hover:scale-110 transition-transform">
                            <i class="ph-fill <?= $p['icon'] ?>"></i>
                        </div>
                        <h4 class="text-lg font-bold text-slate-900 dark:text-white mb-2"><?= htmlspecialchars($p['name']) ?></h4>
                        <p class="text-sm text-slate-600 dark:text-zinc-400 mb-6 flex-1"><?= htmlspecialchars($p['desc']) ?></p>
                        
                        <a href="<?= htmlspecialchars($p['url']) ?>" target="_blank" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700 font-bold py-3 rounded-xl transition-colors text-center text-sm flex items-center justify-center gap-2">
                            Launch Portal <i class="ph-bold ph-arrow-up-right"></i>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- TAB 2: SK QR SCANNER -->
            <div id="tab-scanner" class="tab-content <?= $activeTab == 'scanner' ? 'active' : '' ?>">
                <div class="flex flex-col lg:flex-row gap-8">
                    
                    <!-- Scanner Camera Area -->
                    <div class="w-full lg:w-1/2">
                        <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm dark:shadow-none">
                            <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-2 flex items-center gap-2"><i class="ph-bold ph-scan text-blue-500"></i> Live Camera Scan</h3>
                            <p class="text-xs text-slate-500 dark:text-zinc-400 mb-6">Point the Katipunan ng Kabataan QR Pass to the camera to automatically log attendance or benefits.</p>
                            
                            <!-- Container for HTML5 QR Code -->
                            <div id="qr-reader" class="w-full h-[350px] bg-slate-100 dark:bg-zinc-950 flex items-center justify-center relative">
                                <div id="scanner-placeholder" class="text-center absolute z-10 p-6 pointer-events-none">
                                    <i class="ph-bold ph-camera text-4xl text-slate-400 dark:text-zinc-600 mb-2"></i>
                                    <p class="text-sm font-bold text-slate-500 dark:text-zinc-500">Requesting Camera Access...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Entry & Settings -->
                    <div class="w-full lg:w-1/2 flex flex-col gap-6">
                        
                        <!-- Event Settings -->
                        <div class="bg-blue-600 rounded-2xl p-6 text-white shadow-md">
                            <h3 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph-bold ph-sliders"></i> Scanner Configuration</h3>
                            <form id="scanForm" method="POST">
                                <input type="hidden" name="log_scan" value="1">
                                <input type="hidden" name="qr_data" id="qr_data_input">
                                
                                <label class="block text-xs font-bold text-blue-200 uppercase tracking-widest mb-2">Select Active Event / Log Type</label>
                                <select name="event_type" id="event_type" required class="w-full bg-blue-700/50 border border-blue-500 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-white mb-2 appearance-none">
                                    <option value="General Assembly Attendance">General Assembly Attendance</option>
                                    <option value="Relief Goods / Benefits Claim">Relief Goods / Benefits Claim</option>
                                    <option value="Sports Facility Check-in">Sports Facility Check-in</option>
                                    <option value="Training/Seminar Attendance">Training/Seminar Attendance</option>
                                </select>
                            </form>
                        </div>

                        <!-- Manual Entry Fallback -->
                        <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm dark:shadow-none">
                            <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-2 flex items-center gap-2"><i class="ph-bold ph-keyboard"></i> Manual Input Fallback</h3>
                            <p class="text-xs text-slate-500 dark:text-zinc-400 mb-4">If the camera fails or the QR code is damaged, enter the printed Youth ID manually.</p>
                            
                            <div class="flex gap-2">
                                <input type="text" id="manual_qr" placeholder="e.g. YOUTH-123" class="flex-1 bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white uppercase font-bold focus:border-blue-500 outline-none">
                                <button type="button" onclick="submitManualScan()" class="bg-slate-800 hover:bg-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-white px-6 py-3 rounded-xl font-bold text-sm transition-colors">
                                    Log manually
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- TAB 3: SCAN LOGS -->
            <div id="tab-logs" class="tab-content <?= $activeTab == 'logs' ? 'active' : '' ?>">
                <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 transition-colors shadow-sm dark:shadow-none">
                    <div class="p-6 border-b border-slate-200 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-900/50">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <i class="ph-bold ph-database text-blue-500"></i> Attendance & Claims Database
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800">
                                    <th class="py-4 px-6 w-16">Log ID</th>
                                    <th class="py-4 px-6">Timestamp</th>
                                    <th class="py-4 px-6">Youth Citizen</th>
                                    <th class="py-4 px-6">Event / Log Type</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50">
                                <?php if(empty($logs)): ?>
                                    <tr><td colspan="4" class="py-12 text-center text-slate-500 dark:text-zinc-500">No QR scans recorded yet.</td></tr>
                                <?php else: foreach($logs as $log): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="py-4 px-6 text-slate-500 dark:text-zinc-500 text-xs">
                                        <?= str_pad($log['id'], 6, '0', STR_PAD_LEFT) ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <p class="font-bold text-slate-900 dark:text-white"><?= date('M d, Y', strtotime($log['scanned_at'])) ?></p>
                                        <p class="text-[10px] text-slate-500 dark:text-zinc-500 uppercase tracking-widest mt-0.5"><?= date('h:i A', strtotime($log['scanned_at'])) ?></p>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 flex items-center justify-center font-bold text-xs"><i class="ph-bold ph-user"></i></div>
                                            <div>
                                                <p class="font-bold text-slate-800 dark:text-zinc-200"><?= htmlspecialchars($log['youth_name']) ?></p>
                                                <p class="text-[10px] text-slate-400 dark:text-zinc-500 uppercase tracking-widest mt-0.5">YOUTH-<?= $log['youth_id'] ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="inline-block px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-widest rounded bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300"><?= htmlspecialchars($log['event_type']) ?></span>
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

    <!-- Scripts -->
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

            // Initialize Camera only when Scanner tab is opened
            if (tabId === 'scanner') { initScanner(); }
        }

        // --- QR SCANNER LOGIC (html5-qrcode) ---
        let html5QrcodeScanner;
        function initScanner() {
            if(html5QrcodeScanner) return; // Prevent multiple instances
            
            document.getElementById('scanner-placeholder').style.display = 'none';

            html5QrcodeScanner = new Html5QrcodeScanner(
                "qr-reader", { fps: 10, qrbox: {width: 250, height: 250} }
            );
            
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        }

        function onScanSuccess(decodedText, decodedResult) {
            // Stop scanning to prevent multiple rapid submissions
            html5QrcodeScanner.clear();
            
            // Populate hidden form and submit
            document.getElementById('qr_data_input').value = decodedText;
            document.getElementById('scanForm').submit();
        }

        function onScanFailure(error) {
            // handle scan failure silently (runs constantly until a code is found)
        }

        // Manual Submission
        function submitManualScan() {
            let manualVal = document.getElementById('manual_qr').value;
            if(!manualVal) { alert("Please enter a valid ID (e.g., YOUTH-123)"); return; }
            document.getElementById('qr_data_input').value = manualVal;
            document.getElementById('scanForm').submit();
        }

        // Initialize scanner immediately if starting on scanner tab
        if ('<?= $activeTab ?>' === 'scanner') {
            initScanner();
        }

        // Theme Logic
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        if (document.documentElement.classList.contains('dark')) { themeIcon.classList.replace('ph-moon', 'ph-sun'); }

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