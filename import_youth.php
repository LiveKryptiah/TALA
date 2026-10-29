<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'db_connect.php'; 

// Strict Access Control: Admin or Sub-Admin
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'sub-admin')) {
    header("Location: login.php");
    exit();
}

$status_info = null;

// Get current youth registry statistics
$current_record_count = 0;
$latest_sync_time = 'No data recorded';
try {
    $current_record_count = (int)$pdo->query("SELECT COUNT(*) FROM youth_registry")->fetchColumn();
    $latest_row = $pdo->query("SELECT timestamp_recorded, created_at FROM youth_registry ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($latest_row) {
        $latest_sync_time = !empty($latest_row['timestamp_recorded']) ? $latest_row['timestamp_recorded'] : date('M d, Y', strtotime($latest_row['created_at']));
    }
} catch (Exception $e) {}

function getColVal($column, $map, $key, $capitalize = false) {
    if (isset($map[$key]) && isset($column[$map[$key]])) {
        $val = trim($column[$map[$key]]);
        return $capitalize ? ucwords(strtolower($val)) : $val;
    }
    return '';
}

if (isset($_POST['import'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $fileTmpName = $_FILES['csv_file']['tmp_name'];
        $fileExtension = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        
        if ($fileExtension === 'csv') {
            $file = fopen($fileTmpName, "r");
            $headers = fgetcsv($file);
            
            // Check if file is empty
            if ($headers !== FALSE) {
                $map = [];
                
                foreach ($headers as $index => $header) {
                    $header = trim($header);
                    if (stripos($header, 'Timestamp') !== false) $map['timestamp'] = $index;
                    elseif (stripos($header, 'LAST NAME') !== false) $map['last_name'] = $index;
                    elseif (stripos($header, 'MIDDLE NAME') !== false) $map['middle_name'] = $index;
                    elseif (stripos($header, 'FIRST NAME') !== false) $map['first_name'] = $index;
                    elseif (stripos($header, 'Column 5') !== false || stripos($header, 'Barangay') !== false) $map['barangay'] = $index;
                    elseif (stripos($header, 'SEX') !== false) $map['sex'] = $index;
                    elseif (stripos($header, 'AGE') !== false && stripos($header, 'Group') === false) $map['age'] = $index;
                    elseif (stripos($header, 'BIRTHDAY') !== false) $map['birthday'] = $index;
                    elseif (stripos($header, 'EMAIL') !== false) $map['email'] = $index;
                    elseif (stripos($header, 'CELLPHONE') !== false || stripos($header, 'NUMBER') !== false) $map['phone'] = $index;
                    elseif (stripos($header, 'CIVIL STATUS') !== false) $map['civil_status'] = $index;
                    elseif (stripos($header, 'Year Level') !== false) $map['year_level'] = $index;
                    elseif (stripos($header, 'SK Voter?') !== false) $map['sk_voter'] = $index;
                    elseif (stripos($header, 'National Voter?') !== false) $map['nat_voter'] = $index;
                    elseif (stripos($header, 'KK Assembly?') !== false) $map['kk_assembly'] = $index;
                    elseif (stripos($header, 'Age Group') !== false) $map['age_group'] = $index;
                    elseif (stripos($header, 'Classification') !== false) $map['classification'] = $index;
                    elseif (stripos($header, 'WORK STATUS') !== false) $map['work_status'] = $index;
                    elseif (stripos($header, 'Educational') !== false) $map['educ'] = $index;
                    elseif (stripos($header, 'School') !== false) $map['school'] = $index;
                    elseif (stripos($header, 'if Yes') !== false) $map['sk_yes'] = $index;
                    elseif (stripos($header, 'if No') !== false) $map['sk_no'] = $index;
                    elseif (stripos($header, '2023') !== false) $map['voted'] = $index;
                    elseif (stripos($header, 'Birth Certificate') !== false) $map['birth_cert'] = $index;
                    elseif (stripos($header, 'valid ID') !== false) $map['valid_id'] = $index;
                    elseif (stripos($header, 'Residency') !== false) $map['residency'] = $index;
                }
                
                $sql = "INSERT INTO youth_registry (
                    timestamp_recorded, last_name, middle_name, first_name, full_name, 
                    barangay, sex, age, birthday, email_address, cellphone_number, civil_status,
                    year_level, registered_sk_voter, registered_national_voter, attended_kk_assembly,
                    youth_age_group, youth_classification, educational_background, name_of_school, work_status,
                    sk_assembly_times, sk_assembly_why_no, voted_2023, birth_cert_file, valid_id_file, residency_file, document_status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                try {
                    // Start Transaction
                    $pdo->beginTransaction();
                    
                    // Clear old data securely
                    $pdo->exec("DELETE FROM youth_registry");
                    
                    $stmt = $pdo->prepare($sql);
                    $importCount = 0;
                    
                    // Process CSV
                    while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
                        $lastName  = getColVal($column, $map, 'last_name', true);
                        $midName   = getColVal($column, $map, 'middle_name', true);
                        $firstName = getColVal($column, $map, 'first_name', true);
                        $fullName  = trim("$firstName $midName $lastName");
                        
                        if (empty($fullName)) continue;

                        $raw_age = getColVal($column, $map, 'age');
                        preg_match('/(\d+)/', $raw_age, $matches);
                        $age = isset($matches[1]) ? (int)$matches[1] : 0;
                        
                        $birthCert = getColVal($column, $map, 'birth_cert');
                        $validId   = getColVal($column, $map, 'valid_id');
                        $residency = getColVal($column, $map, 'residency');
                        
                        $docsCount = 0;
                        if (!empty($birthCert)) $docsCount++;
                        if (!empty($validId)) $docsCount++;
                        if (!empty($residency)) $docsCount++;

                        if ($docsCount === 3) $status = 'Complete (All Files)';
                        elseif ($docsCount === 0) $status = 'Missing Documents';
                        else $status = 'Incomplete';
                        
                        $rawTs = getColVal($column, $map, 'timestamp');
                        $createdAt = null;
                        if (!empty($rawTs)) {
                            $parsedTime = strtotime($rawTs);
                            if ($parsedTime !== false && $parsedTime > 0) {
                                $createdAt = date('Y-m-d H:i:s', $parsedTime);
                            }
                        }
                        if (!$createdAt) {
                            $createdAt = date('Y-m-d H:i:s');
                        }
                        
                        $stmt->execute([
                            $rawTs, $lastName, $midName, $firstName, $fullName,
                            getColVal($column, $map, 'barangay', true), getColVal($column, $map, 'sex', true), $age,
                            getColVal($column, $map, 'birthday'), getColVal($column, $map, 'email'), getColVal($column, $map, 'phone'), getColVal($column, $map, 'civil_status'),
                            getColVal($column, $map, 'year_level'), getColVal($column, $map, 'sk_voter'), getColVal($column, $map, 'nat_voter'), getColVal($column, $map, 'kk_assembly'),
                            getColVal($column, $map, 'age_group'), getColVal($column, $map, 'classification'), getColVal($column, $map, 'educ'), getColVal($column, $map, 'school'), getColVal($column, $map, 'work_status'),
                            getColVal($column, $map, 'sk_yes'), getColVal($column, $map, 'sk_no'), getColVal($column, $map, 'voted'),
                            $birthCert, $validId, $residency, $status, $createdAt
                        ]);
                        $importCount++;
                    }
                    
                    // Commit transaction if everything succeeded
                    $pdo->commit();
                    $current_record_count = $importCount;
                    $status_info = [
                        'type' => 'success',
                        'title' => 'Sync Successful',
                        'desc' => number_format($importCount) . ' youth profiles imported to master registry.'
                    ];
                    
                } catch (Exception $e) {
                    // Rollback if any error occurs
                    $pdo->rollBack();
                    $status_info = [
                        'type' => 'error',
                        'title' => 'Import Failed',
                        'desc' => 'Database transaction rolled back. ' . htmlspecialchars($e->getMessage())
                    ];
                }
            } else {
                $status_info = [
                    'type' => 'warning',
                    'title' => 'Empty File',
                    'desc' => 'The uploaded CSV file does not contain any readable data rows.'
                ];
            }
            fclose($file);
        } else {
            $status_info = [
                'type' => 'error',
                'title' => 'Invalid Format',
                'desc' => 'Please upload a valid comma-separated (.csv) file.'
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Master CSV Import Engine | TALA OS</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    
    <!-- ANTI-FLASH SCRIPT: Must be at the top of the <head>! -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script>
        tailwind.config = { 
            darkMode: 'class',
            theme: { 
                extend: { 
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
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
        
        /* Ultra-Clean Modern Cards: ZERO Outside Borders & ZERO Drop Shadows */
        .glass-card { 
            background: #ffffff !important; 
            border: 1px solid rgba(226, 232, 240, 0.8) !important; 
            box-shadow: none !important; 
            -webkit-box-shadow: none !important;
            transition: all 0.2s ease; 
        }
        .dark .glass-card { 
            background: #18181b !important; 
            border: 1px solid rgba(39, 39, 42, 0.8) !important; 
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

        /* Remove all outline / outside strokelines on focus */
        input:focus,
        input:focus-visible,
        input:active,
        button:focus,
        button:focus-visible,
        a:focus {
            outline: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }

        /* Enforce zero drop shadows */
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
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen overflow-x-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">
    
    <!-- Admin Sidebar -->
    <?php include 'sidebar_admin.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>
    
    <!-- MAIN CONTENT AREA -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content smoothly -->
        <div class="max-w-[1400px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-4 md:space-y-6">
            
            <!-- HEADER (with xl:pr-14 for desktop dark mode button clearance) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                        Master CSV Import Engine
                    </h1>
                    <p class="text-xs md:text-sm text-slate-500 dark:text-zinc-400 mt-0.5">
                        Synchronize municipal youth demographic and survey profiles in bulk with transactional validation
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="downloadSampleTemplate()" class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-zinc-300 bg-white dark:bg-zinc-900 border border-slate-200/80 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors flex items-center gap-2 tap-effect shadow-none">
                        <i class="ph-bold ph-download-simple text-sm text-green-600 dark:text-green-500"></i> Template
                    </button>
                    <a href="tala_directory.php" class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-zinc-300 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-900 dark:hover:bg-zinc-800 transition-colors flex items-center gap-2 tap-effect">
                        <i class="ph-bold ph-arrow-left text-sm"></i> Back to Directory
                    </a>
                </div>
            </div>

            <!-- TOP STATS KPI CARDS -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 md:gap-3">
                <div class="glass-card rounded-xl md:rounded-2xl p-3 md:p-4 flex items-center gap-3">
                    <div class="w-9 h-9 md:w-10 md:h-10 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-lg md:text-xl flex-shrink-0">
                        <i class="ph-bold ph-users-three"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] md:text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider truncate">Master Profiles</p>
                        <p class="text-base md:text-xl font-black text-green-600 dark:text-green-500 truncate mt-0.5">
                            <?= number_format($current_record_count) ?>
                        </p>
                    </div>
                </div>

                <div class="glass-card rounded-xl md:rounded-2xl p-3 md:p-4 flex items-center gap-3">
                    <div class="w-9 h-9 md:w-10 md:h-10 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-lg md:text-xl flex-shrink-0">
                        <i class="ph-bold ph-database"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] md:text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider truncate">Target Table</p>
                        <p class="text-xs md:text-sm font-black text-slate-800 dark:text-zinc-200 truncate mt-0.5 font-mono">
                            youth_registry
                        </p>
                    </div>
                </div>

                <div class="glass-card rounded-xl md:rounded-2xl p-3 md:p-4 flex items-center gap-3">
                    <div class="w-9 h-9 md:w-10 md:h-10 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-lg md:text-xl flex-shrink-0">
                        <i class="ph-bold ph-file-csv"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] md:text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider truncate">Supported Format</p>
                        <p class="text-xs md:text-sm font-black text-slate-800 dark:text-zinc-200 truncate mt-0.5">
                            .CSV (UTF-8)
                        </p>
                    </div>
                </div>

                <div class="glass-card rounded-xl md:rounded-2xl p-3 md:p-4 flex items-center gap-3">
                    <div class="w-9 h-9 md:w-10 md:h-10 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-lg md:text-xl flex-shrink-0">
                        <i class="ph-bold ph-shield-check"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] md:text-[11px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider truncate">Integrity Protocol</p>
                        <p class="text-xs md:text-sm font-black text-slate-800 dark:text-zinc-200 truncate mt-0.5">
                            Atomic ACID Safe
                        </p>
                    </div>
                </div>
            </div>

            <!-- MAIN INGESTION & SCHEMA SECTION -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 md:gap-6 items-start">
                
                <!-- LEFT COLUMN: FILE UPLOAD ZONE (7 Cols) -->
                <div class="lg:col-span-7 space-y-4">
                    <div class="glass-card rounded-2xl md:rounded-3xl p-5 sm:p-7">
                        
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-9 h-9 md:w-10 md:h-10 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-lg md:text-xl font-bold flex-shrink-0">
                                <i class="ph-bold ph-cloud-arrow-up"></i>
                            </div>
                            <div>
                                <h2 class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white">
                                    Upload Survey Dataset
                                </h2>
                                <p class="text-xs text-slate-500 dark:text-zinc-400">
                                    Select or drop your master municipal CSV file to begin ingestion
                                </p>
                            </div>
                        </div>

                        <!-- Minimal Inline Status Banner -->
                        <?php if ($status_info): ?>
                            <div class="mb-4 px-3.5 py-2.5 rounded-xl text-xs font-medium border flex items-center gap-2.5 transition-colors <?= $status_info['type'] === 'success' ? 'bg-green-50/80 dark:bg-green-950/30 text-green-800 dark:text-green-300 border-green-200/60 dark:border-green-800/50' : ($status_info['type'] === 'warning' ? 'bg-amber-50/80 dark:bg-amber-950/30 text-amber-800 dark:text-amber-300 border-amber-200/60 dark:border-amber-800/50' : 'bg-rose-50/80 dark:bg-rose-950/30 text-rose-800 dark:text-rose-300 border-rose-200/60 dark:border-rose-800/50') ?>">
                                <i class="ph-fill <?= $status_info['type'] === 'success' ? 'ph-check-circle text-green-600 dark:text-green-400' : ($status_info['type'] === 'warning' ? 'ph-warning-circle text-amber-600 dark:text-amber-400' : 'ph-x-circle text-rose-600 dark:text-rose-400') ?> text-lg flex-shrink-0"></i>
                                <div class="min-w-0">
                                    <span class="font-bold mr-1.5"><?= htmlspecialchars($status_info['title']) ?>:</span>
                                    <span><?= htmlspecialchars($status_info['desc']) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="importForm" class="space-y-4">
                            
                            <!-- DROP ZONE -->
                            <div id="dropZone" class="border-2 border-dashed border-slate-200 dark:border-zinc-800 hover:border-green-600/50 dark:hover:border-green-500/50 bg-slate-50/40 dark:bg-zinc-900/40 hover:bg-green-50/20 dark:hover:bg-green-950/20 transition-all rounded-2xl p-7 sm:p-9 text-center cursor-pointer relative group">
                                <input type="file" name="csv_file" id="csv_file" accept=".csv" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20">
                                
                                <div id="emptyZone" class="flex flex-col items-center justify-center pointer-events-none">
                                    <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-white dark:bg-zinc-800 text-slate-400 dark:text-zinc-400 group-hover:text-green-600 dark:group-hover:text-green-400 flex items-center justify-center text-2xl md:text-3xl mb-2.5 transition-colors">
                                        <i class="ph-bold ph-file-csv"></i>
                                    </div>
                                    <p class="text-xs sm:text-sm font-extrabold text-slate-800 dark:text-zinc-200 group-hover:text-green-600 dark:group-hover:text-green-400 transition-colors">
                                        Click to choose file or drag and drop
                                    </p>
                                    <p class="text-[11px] text-slate-400 dark:text-zinc-500 mt-1">
                                        Standard comma-separated format (.csv) up to 50MB
                                    </p>
                                </div>

                                <!-- SELECTED FILE DETAILS (Hidden until file selected) -->
                                <div id="fileDetails" class="hidden flex-col items-center justify-center pointer-events-none">
                                    <div class="w-12 h-12 md:w-14 md:h-14 rounded-2xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-2xl md:text-3xl mb-2.5">
                                        <i class="ph-bold ph-check-circle"></i>
                                    </div>
                                    <p id="fileNameDisplay" class="text-xs sm:text-sm font-black text-slate-900 dark:text-white truncate max-w-xs"></p>
                                    <p id="fileSizeDisplay" class="text-[11px] text-slate-500 dark:text-zinc-400 mt-0.5"></p>
                                    <span class="mt-2 text-[10px] font-bold text-green-600 dark:text-green-500">Click or drop another file to replace</span>
                                </div>
                            </div>

                            <!-- SYNC NOTICE -->
                            <div class="p-3 rounded-xl bg-amber-500/10 text-amber-800 dark:text-amber-300 text-xs flex items-start gap-2.5">
                                <i class="ph-bold ph-warning-circle text-base flex-shrink-0 mt-0.5 text-amber-600 dark:text-amber-400"></i>
                                <div class="leading-relaxed text-[11px]">
                                    <strong class="font-bold">Transactional Sync:</strong> Running this import executes an atomic replacement of the youth registry dataset. If any syntax error occurs, all changes are automatically rolled back.
                                </div>
                            </div>

                            <!-- ACTION BUTTONS -->
                            <div class="pt-2 flex flex-col sm:flex-row items-center gap-2.5">
                                <button type="submit" name="import" id="submitBtn" class="w-full sm:flex-1 py-2.5 px-5 bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white text-xs md:text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2 tap-effect shadow-none">
                                    <i class="ph-bold ph-upload-simple text-base"></i>
                                    <span id="btnText">Sync Master Profiles</span>
                                </button>
                                
                                <button type="button" onclick="downloadSampleTemplate()" class="w-full sm:w-auto py-2.5 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 text-xs font-bold rounded-xl transition-colors flex items-center justify-center gap-2 tap-effect shadow-none">
                                    <i class="ph-bold ph-download-simple text-sm"></i>
                                    <span>Download Template</span>
                                </button>
                            </div>

                        </form>
                    </div>
                </div>

                <!-- RIGHT COLUMN: SCHEMA & COLUMN MAPPING REFERENCE (5 Cols) -->
                <div class="lg:col-span-5 space-y-4">
                    <div class="glass-card rounded-2xl md:rounded-3xl p-5 sm:p-7 space-y-4">
                        <div class="flex items-center justify-between pb-1">
                            <div class="flex items-center gap-2">
                                <i class="ph-bold ph-list-checks text-lg text-green-600 dark:text-green-500"></i>
                                <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">
                                    Expected Schema Headers
                                </h3>
                            </div>
                            <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 font-mono">
                                26 Parameters
                            </span>
                        </div>

                        <p class="text-xs text-slate-500 dark:text-zinc-400 leading-relaxed">
                            The import engine inspects the CSV header row and automatically maps recognized column headers regardless of column ordering:
                        </p>

                        <!-- CATEGORY 1: IDENTITY -->
                        <div class="space-y-1.5 pt-1">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-zinc-500 block">Personal Demographics</span>
                            <div class="flex flex-wrap gap-1">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">LAST NAME</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">FIRST NAME</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">MIDDLE NAME</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">SEX</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">AGE</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">BIRTHDAY</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">CIVIL STATUS</span>
                            </div>
                        </div>

                        <!-- CATEGORY 2: CONTACT & LOCATION -->
                        <div class="space-y-1.5 pt-1.5">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-zinc-500 block">Location & Contact</span>
                            <div class="flex flex-wrap gap-1">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">Barangay (or Column 5)</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">EMAIL</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">CELLPHONE / NUMBER</span>
                            </div>
                        </div>

                        <!-- CATEGORY 3: CIVIC ENGAGEMENT -->
                        <div class="space-y-1.5 pt-1.5">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-zinc-500 block">Civic & Voter Engagement</span>
                            <div class="flex flex-wrap gap-1">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">SK Voter?</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">National Voter?</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">KK Assembly?</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">2023 Voted</span>
                            </div>
                        </div>

                        <!-- CATEGORY 4: EDUCATION & WORK -->
                        <div class="space-y-1.5 pt-1.5">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-zinc-500 block">Education, Work & Classification</span>
                            <div class="flex flex-wrap gap-1">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">Year Level</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">Educational</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">School</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">WORK STATUS</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">Classification</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">Age Group</span>
                            </div>
                        </div>

                        <!-- CATEGORY 5: DOCUMENTS -->
                        <div class="space-y-1.5 pt-1.5">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-zinc-500 block">Verification Documents</span>
                            <div class="flex flex-wrap gap-1">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">Birth Certificate</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">Valid ID</span>
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-zinc-800/80 text-[11px] font-semibold text-slate-700 dark:text-zinc-300">Residency Proof</span>
                            </div>
                        </div>

                    </div>
                </div>

            </div>

        </div>

    </main>

    <!-- MOBILE BOTTOM NAVIGATION (Android Style) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 h-[68px] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-2xl border-t border-slate-200 dark:border-zinc-800 z-50 flex justify-around items-center px-2 pb-[env(safe-area-inset-bottom)] transition-colors">
        <a href="dashboard.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-squares-four text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Dashboard</span>
        </a>
        <a href="tala_directory.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-address-book text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Directory</span>
        </a>
        <a href="import_youth.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-file-csv text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Import</span>
        </a>
        <a href="admin_approval.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-shield-check text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Approvals</span>
        </a>
    </nav>

    <!-- Unified Mobile Menu Toggle & Interactive File Handlers -->
    <script>
        function toggleMobileMenu() {
            const sidebar = document.getElementById('tala-admin-sidebar') || document.getElementById('tala-bulletproof-sk-sidebar') || document.getElementById('sidebar');
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

        // File selection handler
        const fileInput = document.getElementById('csv_file');
        const emptyZone = document.getElementById('emptyZone');
        const fileDetails = document.getElementById('fileDetails');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const fileSizeDisplay = document.getElementById('fileSizeDisplay');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const importForm = document.getElementById('importForm');
        const dropZone = document.getElementById('dropZone');

        function formatBytes(bytes, decimals = 2) {
            if (!bytes || bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        function updateFilePreview(file) {
            if (file) {
                fileNameDisplay.textContent = file.name;
                fileSizeDisplay.textContent = formatBytes(file.size);
                emptyZone.classList.add('hidden');
                fileDetails.classList.remove('hidden');
                fileDetails.classList.add('flex');
            }
        }

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    updateFilePreview(this.files[0]);
                }
            });
        }

        // Drag & Drop visual feedback
        if (dropZone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.add('border-green-500', 'bg-green-50/30', 'dark:bg-green-950/30');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropZone.classList.remove('border-green-500', 'bg-green-50/30', 'dark:bg-green-950/30');
                }, false);
            });

            dropZone.addEventListener('drop', (e) => {
                if (e.dataTransfer && e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    updateFilePreview(e.dataTransfer.files[0]);
                }
            });
        }

        if (importForm) {
            importForm.addEventListener('submit', function() {
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                    submitBtn.innerHTML = '<i class="ph-bold ph-spinner animate-spin text-base"></i><span>Ingesting & Validating Dataset...</span>';
                }
            });
        }

        // Sample CSV Template Downloader
        function downloadSampleTemplate() {
            const headers = [
                "Timestamp", "LAST NAME", "MIDDLE NAME", "FIRST NAME", "Barangay", 
                "SEX", "AGE", "BIRTHDAY", "EMAIL", "CELLPHONE NUMBER", "CIVIL STATUS", 
                "Year Level", "SK Voter?", "National Voter?", "KK Assembly?", "Age Group", 
                "Classification", "WORK STATUS", "Educational Background", "School", 
                "if Yes", "if No", "2023", "Birth Certificate", "valid ID", "Residency"
            ];
            
            const sampleRow = [
                "\"2026-10-01 10:00:00\"", "\"Dela Cruz\"", "\"Santos\"", "\"Juan\"", "\"Poblacion\"",
                "\"Male\"", "21", "\"2005-05-15\"", "\"juan.delacruz@example.com\"", "\"09171234567\"", "\"Single\"",
                "\"College 3rd Year\"", "\"Yes\"", "\"Yes\"", "\"Yes\"", "\"Core Youth (18-24)\"",
                "\"In School Youth\"", "\"Student\"", "\"College\"", "\"State University\"",
                "\"3 Times\"", "\"\"", "\"Yes\"", "\"birth_cert_01.pdf\"", "\"valid_id_01.png\"", "\"cert_residency_01.pdf\""
            ];

            const csvContent = "data:text/csv;charset=utf-8," 
                + [headers.join(","), sampleRow.join(",")].join("\n");
                
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "tala_youth_registry_template.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>

    <!-- Genesis AI Assistant Widget -->
    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>