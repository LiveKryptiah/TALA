<?php
// public_yorp.php - YORP Public Application
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'db_connect.php';

// =========================================================================================
// SMART DATABASE SETUP & REPAIR
// =========================================================================================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `yorp_organizations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `org_name` VARCHAR(255) NOT NULL,
        `org_type` VARCHAR(100) NOT NULL,
        `barangay` VARCHAR(100) NOT NULL,
        `president_name` VARCHAR(255) NOT NULL,
        `contact_number` VARCHAR(50) NOT NULL,
        `email` VARCHAR(100) NOT NULL,
        `registration_file_path` VARCHAR(255) NOT NULL,
        `directory_file_path` VARCHAR(255) NOT NULL,
        `members_file_path` VARCHAR(255) NOT NULL,
        `constitution_file_path` VARCHAR(255) NOT NULL,
        `endorsement_file_path` VARCHAR(255) NOT NULL,
        `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $cols = $pdo->query("SHOW COLUMNS FROM `yorp_organizations`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('registration_file_path', $cols)) {
        $pdo->exec("ALTER TABLE `yorp_organizations` ADD COLUMN `registration_file_path` VARCHAR(255) NOT NULL AFTER `email`");
    }
    if (!in_array('directory_file_path', $cols)) {
        $pdo->exec("ALTER TABLE `yorp_organizations` ADD COLUMN `directory_file_path` VARCHAR(255) NOT NULL AFTER `registration_file_path`");
    }
    if (!in_array('endorsement_file_path', $cols)) {
        $pdo->exec("ALTER TABLE `yorp_organizations` ADD COLUMN `endorsement_file_path` VARCHAR(255) NOT NULL AFTER `constitution_file_path`");
    }
} catch (PDOException $e) {}

$message = '';
$messageType = '';

// =========================================================================================
// FORM SUBMISSION & FILE UPLOADS
// =========================================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $org_name = trim($_POST['org_name']);
    $org_type = $_POST['org_type'];
    $barangay = $_POST['barangay'];
    $president_name = trim($_POST['president_name']);
    $contact_number = trim($_POST['contact_number']);
    $email = trim($_POST['email']);
    
    $upload_dir = 'uploads/yorp/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

    function uploadYorpFile($file, $prefix, $org, $dir, $allowed) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return false;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) return false;
        
        $safe_org = preg_replace('/[^A-Za-z0-9]/', '', $org);
        $path = $dir . $prefix . '_' . time() . '_' . $safe_org . '.' . $ext;
        
        if (move_uploaded_file($file['tmp_name'], $path)) return $path;
        return false;
    }

    $reg_path = uploadYorpFile($_FILES['registration_file'], 'REG', $org_name, $upload_dir, $allowed_exts);
    $dir_path = uploadYorpFile($_FILES['directory_file'], 'DIR', $org_name, $upload_dir, $allowed_exts);
    $members_path = uploadYorpFile($_FILES['members_file'], 'MEMBERS', $org_name, $upload_dir, $allowed_exts);
    $cbl_path = uploadYorpFile($_FILES['constitution_file'], 'CBL', $org_name, $upload_dir, $allowed_exts);
    $endorse_path = uploadYorpFile($_FILES['endorsement_file'], 'ENDORSE', $org_name, $upload_dir, $allowed_exts);

    if ($reg_path && $dir_path && $members_path && $cbl_path && $endorse_path) {
        $stmt = $pdo->prepare("INSERT INTO yorp_organizations (org_name, org_type, barangay, president_name, contact_number, email, registration_file_path, directory_file_path, members_file_path, constitution_file_path, endorsement_file_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$org_name, $org_type, $barangay, $president_name, $contact_number, $email, $reg_path, $dir_path, $members_path, $cbl_path, $endorse_path]);
        
        $message = "Application submitted successfully! The MYDO will review your documents shortly.";
        $messageType = "green";
    } else {
        $message = "Failed to upload files. Please ensure ALL 5 files are provided and in the correct formats.";
        $messageType = "red";
    }
}

// Data Sets
$barangays = ["Tumauini", "District 1", "Balug", "Camasi", "Moldero", "Ugad", "Lanna"];
$org_types = ["Community-Based", "School-Based", "Faith-Based", "Working Youth", "In-School Youth", "Out-of-School Youth", "Special Sector (PWD, IP, etc.)"];

// Updated mapped classes to support Dark Mode seamlessly
$alertClasses = [
    'red' => 'bg-red-50 text-red-800 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/30',
    'amber' => 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30',
    'green' => 'bg-green-50 text-green-800 border-green-200 dark:bg-green-950/40 dark:text-green-300 dark:border-green-800/50'
];
$activeAlertClass = isset($alertClasses[$messageType]) ? $alertClasses[$messageType] : '';
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YORP Registration | TALA Tumauini</title>
    
    <!-- ANTI-FLASH SCRIPT: Must be at the top of the <head>! -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- ENABLE DARK MODE IN TAILWIND CONFIG -->
    <script>
        tailwind.config = { 
            darkMode: 'class',
            theme: { 
                extend: { 
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, 
                    colors: { brand: { 50: '#f0fdf4', 100: '#dcfce7', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d' } } 
                } 
            } 
        }
    </script>
    
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        
        .btn-gradient {
            background: #15803d;
            color: #ffffff;
            transition: all 0.2s ease;
        }
        .btn-gradient:hover {
            background: #166534;
            transform: translateY(-1px);
        }
        .dark .btn-gradient {
            background: #16a34a;
        }
        .dark .btn-gradient:hover {
            background: #15803d;
        }
        
        /* Animation for step transitions */
        .slide-fade-enter { animation: slideIn 0.3s ease forwards; }
        @keyframes slideIn { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: translateX(0); } }
        
        /* Fade animation for left image text transition */
        .fade-in { animation: fadeIn 0.4s ease forwards; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>

<!-- Locks the page to exactly 100vh so the main window NEVER scrolls -->
<body class="bg-slate-100 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 font-sans antialiased h-screen w-screen overflow-hidden flex flex-col selection:bg-brand-900 selection:text-white transition-colors duration-300">

    <!-- Top Navigation (Assumes standard fixed height) -->
    <div class="shrink-0 z-50 relative">
        <?php include 'navigation.php'; ?>
    </div>

    <!-- Main Container ensuring 4-Sided Margin and Perfect Center -->
    <main class="flex-1 w-full flex items-center justify-center p-4 sm:p-8 lg:p-12 overflow-hidden relative">

        <!-- Centered Split-Card (WIDER MAX-WIDTH AND FIXED HEIGHT) -->
        <div class="w-full max-w-[1150px] bg-white dark:bg-zinc-900 rounded-[2rem] border border-slate-200/80 dark:border-zinc-800 overflow-hidden flex flex-col md:flex-row h-full max-h-[750px] relative transition-colors duration-300">
            
            <!-- LEFT SIDE: DYNAMIC IMAGE & TEXT (Stays inherently dark/brand focused) -->
            <div class="hidden md:flex md:w-5/12 h-full relative shrink-0 bg-brand-900 overflow-hidden">
                <img id="left-img" src="https://images.unsplash.com/photo-1529156069898-49953eb1b5ae?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80" 
                     class="absolute inset-0 w-full h-full object-cover opacity-40 mix-blend-overlay transition-all duration-500" alt="Youth Community">
                <div class="absolute inset-0 bg-gradient-to-t from-brand-900 via-brand-900/60 to-transparent"></div>
                
                <div id="left-content" class="absolute inset-0 p-10 lg:p-12 flex flex-col justify-end text-white fade-in">
                    <div class="w-14 h-14 bg-white/20 backdrop-blur-md rounded-2xl flex items-center justify-center mb-6 border border-white/30">
                        <img src="TALA-LOGO.ico" class="w-8 h-8 object-contain" alt="TALA">
                    </div>
                    <h1 id="left-title" class="text-4xl lg:text-5xl font-black mb-4 leading-tight">YORP<br>Registration.</h1>
                    <p id="left-desc" class="text-brand-50 text-sm lg:text-base font-medium opacity-90 leading-relaxed mb-8">
                        Partner with the Local Youth Development Office. Mandated by DILG and the National Youth Commission.
                    </p>
                    
                    <!-- Progress Indicator -->
                    <div class="flex items-center gap-2">
                        <div id="dot1" class="h-2 w-8 bg-brand-400 rounded-full transition-all duration-300"></div>
                        <div id="dot2" class="h-2 w-2 bg-white/30 rounded-full transition-all duration-300"></div>
                    </div>
                </div>
            </div>

            <!-- RIGHT SIDE: FORM -->
            <div class="w-full md:w-7/12 flex flex-col h-full relative bg-white dark:bg-zinc-900 transition-colors duration-300">
                
                <!-- Sticky Header -->
                <div class="px-8 py-6 border-b border-slate-100 dark:border-zinc-800 flex items-center justify-between shrink-0 bg-white dark:bg-zinc-900 z-10 transition-colors">
                    <div>
                        <h2 id="step-title" class="text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">Organization Details</h2>
                        <p id="step-desc" class="text-[11px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mt-0.5">Step 1 of 2</p>
                    </div>
                    <a href="https://talatumauini.site.je/public_governance.php" target="_blank" class="bg-green-50 dark:bg-green-950/40 text-green-800 dark:text-green-300 hover:bg-green-600 dark:hover:bg-green-600 hover:text-white border border-green-200 dark:border-green-800/50 text-[10px] font-bold px-4 py-2 rounded-xl transition-colors flex items-center gap-1.5" title="Download Official NYC Templates">
                        <i class="ph-bold ph-download-simple text-sm"></i> Get Templates
                    </a>
                </div>

                <!-- Scrollable Form Area -->
                <div class="flex-1 overflow-y-auto custom-scrollbar p-8">
                    
                    <?php if ($message): ?>
                        <div class="<?= $activeAlertClass ?> p-4 mb-6 rounded-xl flex items-center justify-center border font-bold text-sm text-center transition-colors">
                            <?= $message ?>
                        </div>
                    <?php endif; ?>

                    <form id="yorpForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                        
                        <!-- ================= SLIDE 1 ================= -->
                        <div id="slide1" class="slide-fade-enter space-y-6">
                            
                            <div>
                                <h3 class="text-sm font-extrabold text-slate-800 dark:text-white border-b border-slate-100 dark:border-zinc-800/80 pb-2 mb-4 transition-colors">Basic Information</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-1.5 transition-colors">Organization Name <span class="text-red-500">*</span></label>
                                        <input type="text" name="org_name" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3 text-sm font-medium focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 outline-none transition-all">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-1.5 transition-colors">Organization Type <span class="text-red-500">*</span></label>
                                        <select name="org_type" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3 text-sm font-medium focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 outline-none appearance-none cursor-pointer transition-all">
                                            <option value="" disabled selected>Select Type...</option>
                                            <?php foreach($org_types as $type): ?>
                                                <option value="<?= $type ?>"><?= $type ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-1.5 transition-colors">Base Barangay <span class="text-red-500">*</span></label>
                                        <select name="barangay" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3 text-sm font-medium focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 outline-none appearance-none cursor-pointer transition-all">
                                            <option value="" disabled selected>Select Barangay...</option>
                                            <?php foreach($barangays as $brgy): ?>
                                                <option value="<?= $brgy ?>"><?= $brgy ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-sm font-extrabold text-slate-800 dark:text-white border-b border-slate-100 dark:border-zinc-800/80 pb-2 mb-4 mt-2 transition-colors">Contact Person / President</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-1.5 transition-colors">Full Name <span class="text-red-500">*</span></label>
                                        <input type="text" name="president_name" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3 text-sm font-medium focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-1.5 transition-colors">Contact Number <span class="text-red-500">*</span></label>
                                        <input type="tel" name="contact_number" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3 text-sm font-medium focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 outline-none transition-all">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-1.5 transition-colors">Email Address <span class="text-red-500">*</span></label>
                                        <input type="email" name="email" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3 text-sm font-medium focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 outline-none transition-all">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ================= SLIDE 2 ================= -->
                        <div id="slide2" class="hidden slide-fade-enter space-y-4">
                            
                            <div class="bg-green-50 dark:bg-green-950/40 border border-green-200 dark:border-green-800/50 rounded-xl p-4 flex items-start gap-3 mb-2 transition-colors">
                                <i class="ph-fill ph-info text-green-600 dark:text-green-500 text-xl shrink-0"></i>
                                <p class="text-xs font-semibold text-green-800 dark:text-green-300 leading-relaxed">
                                     Please upload the official completed forms. If you don't have them, use the <strong class="text-green-900 dark:text-green-100">Get Templates</strong> button above to download blank DOCX copies.
                                </p>
                            </div>

                            <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl p-3.5 hover:border-brand-300 transition-colors">
                                <label class="block text-[11px] font-extrabold text-slate-800 dark:text-zinc-200 uppercase tracking-widest mb-1.5 transition-colors">1. Registration Form <span class="text-red-500">*</span></label>
                                <input type="file" name="registration_file" accept=".pdf,.doc,.docx" class="w-full text-sm file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-brand-50 dark:file:bg-brand-500/10 file:text-brand-700 dark:file:text-brand-400 hover:file:bg-brand-100 dark:hover:file:bg-brand-500/20 cursor-pointer text-slate-500 dark:text-zinc-400 transition-colors">
                            </div>

                            <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl p-3.5 hover:border-brand-300 transition-colors">
                                <label class="block text-[11px] font-extrabold text-slate-800 dark:text-zinc-200 uppercase tracking-widest mb-1.5 transition-colors">2. Directory of Officers <span class="text-red-500">*</span></label>
                                <input type="file" name="directory_file" accept=".pdf,.doc,.docx" class="w-full text-sm file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-brand-50 dark:file:bg-brand-500/10 file:text-brand-700 dark:file:text-brand-400 hover:file:bg-brand-100 dark:hover:file:bg-brand-500/20 cursor-pointer text-slate-500 dark:text-zinc-400 transition-colors">
                            </div>

                            <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl p-3.5 hover:border-brand-300 transition-colors">
                                <label class="block text-[11px] font-extrabold text-slate-800 dark:text-zinc-200 uppercase tracking-widest mb-1.5 transition-colors">3. List of Members <span class="text-red-500">*</span></label>
                                <input type="file" name="members_file" accept=".pdf,.xls,.xlsx" class="w-full text-sm file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-brand-50 dark:file:bg-brand-500/10 file:text-brand-700 dark:file:text-brand-400 hover:file:bg-brand-100 dark:hover:file:bg-brand-500/20 cursor-pointer text-slate-500 dark:text-zinc-400 transition-colors">
                            </div>

                            <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl p-3.5 hover:border-brand-300 transition-colors">
                                <label class="block text-[11px] font-extrabold text-slate-800 dark:text-zinc-200 uppercase tracking-widest mb-1.5 transition-colors">4. Constitution & By-Laws <span class="text-red-500">*</span></label>
                                <input type="file" name="constitution_file" accept=".pdf,.doc,.docx" class="w-full text-sm file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-brand-50 dark:file:bg-brand-500/10 file:text-brand-700 dark:file:text-brand-400 hover:file:bg-brand-100 dark:hover:file:bg-brand-500/20 cursor-pointer text-slate-500 dark:text-zinc-400 transition-colors">
                            </div>

                            <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl p-3.5 hover:border-brand-300 transition-colors">
                                <label class="block text-[11px] font-extrabold text-slate-800 dark:text-zinc-200 uppercase tracking-widest mb-1.5 transition-colors">5. Endorsement / Certification <span class="text-red-500">*</span></label>
                                <input type="file" name="endorsement_file" accept=".pdf,.doc,.docx,.jpg,.png" class="w-full text-sm file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-brand-50 dark:file:bg-brand-500/10 file:text-brand-700 dark:file:text-brand-400 hover:file:bg-brand-100 dark:hover:file:bg-brand-500/20 cursor-pointer text-slate-500 dark:text-zinc-400 transition-colors">
                            </div>

                        </div>
                    </form>
                </div>

                <!-- Sticky Footer Buttons -->
                <div class="px-8 py-5 border-t border-slate-100 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900/90 shrink-0 z-10 transition-colors">
                    
                    <!-- Buttons for Slide 1 -->
                    <div id="slide1-btns" class="flex justify-between items-center">
                        <span class="text-xs font-semibold text-slate-400 dark:text-zinc-500 transition-colors">&copy; <?= date('Y') ?> MYDO Tumauini</span>
                        <button type="button" onclick="nextSlide()" class="btn-gradient px-8 py-3.5 rounded-xl font-bold text-sm flex items-center gap-2">
                            Next Step <i class="ph-bold ph-arrow-right text-lg"></i>
                        </button>
                    </div>

                    <!-- Buttons for Slide 2 -->
                    <div id="slide2-btns" class="hidden items-center justify-between">
                        <button type="button" onclick="prevSlide()" class="text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white font-bold text-sm flex items-center gap-1.5 px-4 py-3.5 rounded-xl hover:bg-slate-200 dark:hover:bg-zinc-800 transition-colors">
                            <i class="ph-bold ph-arrow-left text-lg"></i> Back
                        </button>
                        <button type="submit" form="yorpForm" class="btn-gradient px-8 py-3.5 rounded-xl font-bold text-sm flex items-center gap-2">
                            Submit Application <i class="ph-bold ph-paper-plane-tilt text-lg"></i>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        // JavaScript Wizard Logic & Dynamic Profile Transition
        function nextSlide() {
            let isValid = true;
            const slide1 = document.getElementById('slide1');
            const inputs = slide1.querySelectorAll('input[required], select[required]');
            
            // HTML5 Validation on Step 1
            inputs.forEach(input => {
                if (!input.checkValidity()) {
                    input.reportValidity();
                    isValid = false;
                    return;
                }
            });

            if (!isValid) return;

            // Transition UI to Slide 2
            document.getElementById('slide1').classList.add('hidden');
            document.getElementById('slide1-btns').classList.add('hidden');
            
            document.getElementById('slide2').classList.remove('hidden');
            document.getElementById('slide2-btns').classList.remove('hidden');
            document.getElementById('slide2-btns').classList.add('flex');

            // Update Right Side Headers
            document.getElementById('step-title').innerText = "Required Documents";
            document.getElementById('step-desc').innerText = "Step 2 of 2";

            // Update Indicators
            document.getElementById('dot1').classList.replace('w-8', 'w-2');
            document.getElementById('dot1').classList.replace('bg-brand-400', 'bg-white/30');
            document.getElementById('dot2').classList.replace('w-2', 'w-8');
            document.getElementById('dot2').classList.replace('bg-white/30', 'bg-brand-400');
            
            // === DYNAMIC LEFT PROFILE CHANGE ===
            const leftContent = document.getElementById('left-content');
            leftContent.classList.remove('fade-in');
            void leftContent.offsetWidth; // trigger reflow
            leftContent.classList.add('fade-in');

            document.getElementById('left-img').src = "https://images.unsplash.com/photo-1606326608606-aa0b62935f2b?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80"; // Office / Document Image
            document.getElementById('left-title').innerHTML = "Submit<br>Documents.";
            document.getElementById('left-desc').innerText = "Upload your official Registration Form, Directory, Members list, CBL, and Endorsement to complete your profile.";

            // Add Required attribute to files now that they are visible
            const fileInputs = document.getElementById('slide2').querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => input.setAttribute('required', 'true'));
        }

        function prevSlide() {
            // Remove required temporarily so HTML5 validation doesn't block going back
            const fileInputs = document.getElementById('slide2').querySelectorAll('input[type="file"]');
            fileInputs.forEach(input => input.removeAttribute('required'));

            // Transition UI back to Slide 1
            document.getElementById('slide2').classList.add('hidden');
            document.getElementById('slide2-btns').classList.add('hidden');
            document.getElementById('slide2-btns').classList.remove('flex');
            
            document.getElementById('slide1').classList.remove('hidden');
            document.getElementById('slide1-btns').classList.remove('hidden');

            // Update Right Side Headers
            document.getElementById('step-title').innerText = "Organization Details";
            document.getElementById('step-desc').innerText = "Step 1 of 2";

            // Update Indicators
            document.getElementById('dot2').classList.replace('w-8', 'w-2');
            document.getElementById('dot2').classList.replace('bg-brand-400', 'bg-white/30');
            document.getElementById('dot1').classList.replace('w-2', 'w-8');
            document.getElementById('dot1').classList.replace('bg-white/30', 'bg-brand-400');

            // === DYNAMIC LEFT PROFILE REVERT ===
            const leftContent = document.getElementById('left-content');
            leftContent.classList.remove('fade-in');
            void leftContent.offsetWidth; // trigger reflow
            leftContent.classList.add('fade-in');

            document.getElementById('left-img').src = "https://images.unsplash.com/photo-1529156069898-49953eb1b5ae?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80"; // Youth Image
            document.getElementById('left-title').innerHTML = "YORP<br>Registration.";
            document.getElementById('left-desc').innerText = "Partner with the Local Youth Development Office. Mandated by DILG and the National Youth Commission.";
        }
    </script>
</body>
</html>