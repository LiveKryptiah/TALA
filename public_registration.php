<?php
// ==========================================
// 1. AJAX INTELLISENSE ENDPOINT
// ==========================================
if (isset($_GET['ajax_search'])) {
    require 'db_connect.php';
    $search = '%' . trim($_GET['ajax_search']) . '%';
    
    // 1st Query: Get data from new PROFILES
    $stmt1 = $pdo->prepare("
        SELECT full_name, birthdate, barangay, sex, address, civil_status, 'profile' as source 
        FROM youth_profiles 
        WHERE full_name LIKE :search LIMIT 10
    ");
    $stmt1->execute([':search' => $search]);
    $profiles = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // 2nd Query: Get data from old REGISTRY
    $stmt2 = $pdo->prepare("
        SELECT full_name, birthday as birthdate, barangay, sex, NULL as address, civil_status, 'registry' as source 
        FROM youth_registry 
        WHERE full_name LIKE :search LIMIT 10
    ");
    $stmt2->execute([':search' => $search]);
    $registry = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $results = array_slice(array_merge($profiles, $registry), 0, 10);

    foreach ($results as &$row) {
        if (!empty($row['birthdate'])) {
            $parsedDate = strtotime($row['birthdate']);
            if ($parsedDate) {
                $row['birthdate'] = date('Y-m-d', $parsedDate);
            }
        }
    }
    
    ob_clean(); 
    header('Content-Type: application/json');
    echo json_encode($results);
    exit;
}

// ==========================================
// 2. FORM SUBMISSION HANDLER
// ==========================================
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'sub-admin')) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];

$success = false;
$error = '';
$new_youth_id = '';
$selected_event = '';
$uploaded_photo = 'default.jpg'; 
$user_age = 'N/A';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['full_name'])) {
    try {
        // Generate ID
        $stmt = $pdo->query("SELECT youth_id FROM youth_profiles ORDER BY id DESC LIMIT 1");
        $lastRecord = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lastRecord && !empty($lastRecord['youth_id'])) {
            $lastIdNumber = (int) str_replace('TMN-', '', $lastRecord['youth_id']);
            $newIdNumber = $lastIdNumber + 1;
            $new_youth_id = 'TMN-' . str_pad($newIdNumber, 6, '0', STR_PAD_LEFT);
        } else {
            $new_youth_id = 'TMN-000001';
        }

        // Upload Photo
        $photo_path = null;
        if (isset($_FILES['id_photo']) && $_FILES['id_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_extension = strtolower(pathinfo($_FILES['id_photo']['name'], PATHINFO_EXTENSION));
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($file_extension, $allowed_types)) {
                $new_filename = $new_youth_id . '_' . time() . '.' . $file_extension;
                $photo_path = $upload_dir . $new_filename;
                move_uploaded_file($_FILES['id_photo']['tmp_name'], $photo_path);
                $uploaded_photo = $photo_path; 
            } else {
                throw new Exception("Invalid photo format. Please upload a JPG or PNG.");
            }
        }

        // Format Arrays
        $skills_str = isset($_POST['skills']) ? implode(', ', $_POST['skills']) : null;
        $orgs_str = isset($_POST['organizations']) ? implode(', ', $_POST['organizations']) : null;
        $gov_services_str = isset($_POST['gov_services']) ? implode(', ', $_POST['gov_services']) : null;
        $programs_str = isset($_POST['programs']) ? implode(', ', $_POST['programs']) : null;
        
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $selected_event = $_POST['event_purpose'] ?? 'General Profiling';
        
        if (!empty($_POST['birthdate'])) {
            $birthDate = new DateTime($_POST['birthdate']);
            $today = new DateTime('today');
            $user_age = $birthDate->diff($today)->y;
        }

        // Save to DB
        $sql = "INSERT INTO youth_profiles (
            youth_id, photo_path, full_name, birthdate, sex, address, barangay, civil_status,
            contact_number, email, emergency_contact, parent_guardian, philsys_id, psa_birth_cert,
            education_level, employment_status, skills, organizations, program_interests, remarks, status,
            gender_identity, father_name, mother_name, guardian_name, household_size,
            income_bracket, is_4ps, is_solo_parent, is_pwd_household, school_name, school_id, course,
            grade_level, graduated, is_dropout, is_als, expected_graduation, occupation, employer,
            monthly_income, tesda_nc, gov_services, e_signature, ip_address, event_purpose
        ) VALUES (
            :youth_id, :photo_path, :full_name, :birthdate, :sex, :address, :barangay, :civil_status,
            :contact_number, :email, :emergency_contact, :parent_guardian, :philsys_id, :psa_birth_cert,
            :education_level, :employment_status, :skills, :organizations, :program_interests, :remarks, :status,
            :gender_identity, :father_name, :mother_name, :guardian_name, :household_size,
            :income_bracket, :is_4ps, :is_solo_parent, :is_pwd_household, :school_name, :school_id, :course,
            :grade_level, :graduated, :is_dropout, :is_als, :expected_graduation, :occupation, :employer,
            :monthly_income, :tesda_nc, :gov_services, :e_signature, :ip_address, :event_purpose
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':youth_id' => $new_youth_id, 
            ':photo_path' => $photo_path, 
            ':full_name' => $_POST['full_name'],
            ':birthdate' => $_POST['birthdate'], 
            ':sex' => $_POST['sex'], 
            ':address' => $_POST['address'],
            ':barangay' => $_POST['barangay'], 
            ':civil_status' => $_POST['civil_status'] ?? null,
            ':contact_number' => $_POST['contact_number'] ?? null, 
            ':email' => $_POST['email'] ?? null,
            ':emergency_contact' => $_POST['emergency_contact'] ?? null, 
            ':parent_guardian' => $_POST['guardian_name'] ?? null,
            ':philsys_id' => $_POST['philsys_id'] ?? null, 
            ':psa_birth_cert' => $_POST['psa_birth_cert'] ?? null,
            ':education_level' => $_POST['education_level'] ?? null,
            ':employment_status' => $_POST['employment_status'] ?? null,
            ':skills' => $skills_str, 
            ':organizations' => $orgs_str, 
            ':program_interests' => $programs_str,
            ':remarks' => 'Registered via Admin Profiling Module',
            ':status' => 'Verified',
            ':gender_identity' => $_POST['gender_identity'] ?? null,
            ':father_name' => $_POST['father_name'] ?? null, 
            ':mother_name' => $_POST['mother_name'] ?? null,
            ':guardian_name' => $_POST['guardian_name'] ?? null, 
            ':household_size' => $_POST['household_size'] ?? null,
            ':income_bracket' => $_POST['income_bracket'] ?? null, 
            ':is_4ps' => $_POST['is_4ps'] ?? 'No',
            ':is_solo_parent' => $_POST['is_solo_parent'] ?? 'No', 
            ':is_pwd_household' => $_POST['is_pwd_household'] ?? 'No',
            ':school_name' => $_POST['school_name'] ?? null, 
            ':school_id' => $_POST['school_id'] ?? null,
            ':course' => $_POST['course'] ?? null, 
            ':grade_level' => $_POST['grade_level'] ?? null,
            ':graduated' => (isset($_POST['education_level']) && strpos($_POST['education_level'], 'Graduate') !== false) ? 'Yes' : 'No', 
            ':is_dropout' => $_POST['is_dropout'] ?? 'No',
            ':is_als' => $_POST['is_als'] ?? 'No', 
            ':expected_graduation' => $_POST['expected_graduation'] ?? null,
            ':occupation' => $_POST['occupation'] ?? null, 
            ':employer' => $_POST['employer'] ?? null,
            ':monthly_income' => $_POST['monthly_income'] ?? null, 
            ':tesda_nc' => $_POST['tesda_nc'] ?? null,
            ':gov_services' => $gov_services_str, 
            ':e_signature' => $_POST['e_signature'] ?? null, 
            ':ip_address' => $ip_address, 
            ':event_purpose' => $selected_event
        ]);
        $success = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$all_barangays = ["Annafunan", "Antagan I", "Antagan II", "Arcon", "Balug", "Banig", "Bantug", "Barangay District 1 (Poblacion)", "Barangay District 2 (Poblacion)", "Barangay District 3 (Poblacion)", "Barangay District 4 (Poblacion)", "Bayabo East", "Caligayan", "Camasi", "Carpentero", "Compania", "Cumabao", "Fermeldy (Hacienda San Francisco)", "Fugu Abajo", "Fugu Norte", "Fugu Sur", "Lalauanan", "Lanna", "Lapogan", "Lingaling (Poblacion)", "Liwanag", "Malamag East", "Malamag West", "Maligaya (Poblacion)", "Minanga", "Moldero", "Namnama", "Paragu", "Pilitan", "San Mateo", "San Pedro", "San Vicente", "Santa", "Santa Catalina", "Santa Visitacion (Maggayu)", "Santo Niño", "Sinippil", "Sisim Abajo", "Sisim Alto", "Tunggui", "Ugad"];
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Official Profiling | TALA OS</title>
    
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { 
                extend: { 
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, 
                    colors: { brand: { 500: '#22c55e', 600: '#16a34a', 700: '#15803d' } } 
                } 
            }
        }
    </script>
    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; -webkit-tap-highlight-color: transparent; }
        
        .ambient-bg { position: fixed; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; }
        .blob { position: absolute; filter: blur(100px); opacity: 0.5; border-radius: 50%; mix-blend-multiply: true; transition: background-color 0.3s ease; }
        .dark .blob { mix-blend-multiply: normal; mix-blend-mode: screen; opacity: 0.15; }

        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(24px); border: 1px solid rgba(226, 232, 240, 0.8); transition: all 0.3s ease; }
        .dark .glass-card { background: rgba(24, 24, 27, 0.95); border: 1px solid rgba(63, 63, 70, 0.5); }
        
        .step-form { display: none; animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
        .step-form.active { display: block; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .cert-border { border: 4px double #334155; }
        .dark .cert-border { border-color: #475569; }

        .event-radio:checked + div { border-color: #16a34a; background-color: #f0fdf4; box-shadow: 0 4px 15px -3px rgba(22, 163, 74, 0.2); transform: translateY(-2px); }
        .dark .event-radio:checked + div { background-color: rgba(22, 163, 74, 0.1); border-color: #16a34a; }
        .event-radio:checked + div .check-icon { opacity: 1; scale: 1; }
        
        .id-gradient { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
        /* Global TALA OS Green to Yellow Gradient Button for Public Website */
        .btn-gradient,
        .btn-yellow-glow-r,
        button.bg-\[\#15803d\],
        button.bg-\[\#16a34a\],
        a.bg-\[\#15803d\],
        a.bg-\[\#16a34a\] {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 35%, #eab308 100%) !important;
            color: #ffffff !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35) !important;
            box-shadow: none !important;
            position: relative !important;
            overflow: hidden !important;
            transition: transform 0.2s ease, opacity 0.2s ease !important;
        }
        .btn-gradient:hover,
        .btn-yellow-glow-r:hover,
        button.bg-\[\#15803d\]:hover,
        button.bg-\[\#16a34a\]:hover,
        a.bg-\[\#15803d\]:hover,
        a.bg-\[\#16a34a\]:hover {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 35%, #eab308 100%) !important;
            box-shadow: none !important;
            transform: translateY(-1px) !important;
            opacity: 0.96 !important;
        }
        .btn-gradient:active { transform: scale(0.97) !important; }
        .dark .btn-gradient,
        .dark .btn-yellow-glow-r,
        .dark button.bg-\[\#15803d\],
        .dark button.bg-\[\#16a34a\],
        .dark a.bg-\[\#15803d\],
        .dark a.bg-\[\#16a34a\] {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 35%, #eab308 100%) !important;
            color: #ffffff !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.35) !important;
            box-shadow: none !important;
        }
        .dark .btn-gradient:hover,
        .dark .btn-yellow-glow-r:hover,
        .dark button.bg-\[\#15803d\]:hover,
        .dark button.bg-\[\#16a34a\]:hover,
        .dark a.bg-\[\#15803d\]:hover,
        .dark a.bg-\[\#16a34a\]:hover {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 35%, #eab308 100%) !important;
            box-shadow: none !important;
            transform: translateY(-1px) !important;
            opacity: 0.96 !important;
        }
        /* Completely remove right-side glow pseudo-element */
        .btn-gradient::before,
        .btn-yellow-glow-r::before,
        button.bg-\[\#15803d\]::before,
        button.bg-\[\#16a34a\]::before,
        a.bg-\[\#15803d\]::before,
        a.bg-\[\#16a34a\]::before {
            display: none !important;
            content: none !important;
        }
        
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }

        .tap-effect:active { transform: scale(0.98); opacity: 0.8; transition: transform 0.1s; }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex h-screen overflow-hidden selection:bg-brand-500 selection:text-white transition-colors duration-300">

    <div class="ambient-bg">
        <div class="blob bg-brand-200 dark:bg-brand-800 w-[500px] h-[500px] top-[-10%] left-[-10%]"></div>
        <div class="blob bg-green-200 dark:bg-green-900 w-[400px] h-[400px] bottom-[-10%] right-[10%]"></div>
    </div>

    <?php include ($role === 'admin' ? 'sidebar_admin.php' : 'sidebar_sk.php'); ?>

    <main class="md:ml-64 pt-20 md:pt-8 px-4 md:px-8 pb-24 md:pb-16 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 overflow-y-auto">
        
        <div class="max-w-[1400px] mx-auto w-full flex flex-col items-center">
            
            <div class="w-full max-w-4xl space-y-6 md:space-y-8">
                
                <div class="flex flex-col md:flex-row justify-between items-start md:items-end pb-4 border-b border-slate-200 dark:border-zinc-800 transition-colors">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight transition-colors">Official Profiling</h1>
                        <p class="text-xs md:text-sm font-semibold text-slate-500 dark:text-zinc-400 mt-1 uppercase tracking-widest transition-colors">Create / Update Youth Records</p>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="glass-card rounded-[2.5rem] shadow-2xl overflow-hidden text-center md:text-left">
                        <div class="bg-brand-600 dark:bg-brand-700 p-8 md:p-12 text-white relative overflow-hidden transition-colors">
                            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(#fff 1px, transparent 1px); background-size: 20px 20px;"></div>
                            <div class="relative z-10 flex flex-col md:flex-row items-center gap-6">
                                <div class="w-20 h-20 bg-white text-brand-600 rounded-full flex items-center justify-center shadow-xl shrink-0 border-4 border-brand-400"><i class="ph-bold ph-confetti text-4xl"></i></div>
                                <div>
                                    <h2 class="text-sm font-bold uppercase tracking-[0.2em] text-brand-200 mb-1">Auto-Verified</h2>
                                    <h1 class="text-3xl md:text-4xl font-black tracking-tight mb-2">Registration Complete!</h1>
                                    <p class="text-brand-100 font-medium text-sm">The official Digital Youth ID and Achievement Portfolio are now active.</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 md:p-10 bg-slate-50 dark:bg-zinc-950 flex flex-col md:flex-row gap-10 transition-colors">
                            <div class="md:w-1/2 flex flex-col items-center">
                                <h3 class="text-sm font-black text-slate-400 dark:text-zinc-500 uppercase tracking-widest mb-4 transition-colors">Digital ID Preview</h3>
                                <div class="w-full max-w-sm id-gradient text-white rounded-[2rem] p-6 shadow-2xl relative overflow-hidden border border-slate-700">
                                    <div class="absolute -right-10 -bottom-10 opacity-10"><i class="ph-fill ph-fingerprint text-[200px]"></i></div>
                                    <div class="flex justify-between items-start mb-6 relative z-10">
                                        <div>
                                            <h4 class="text-[9px] uppercase tracking-[0.2em] text-brand-400 font-black mb-1">TALA Registration No.</h4>
                                            <p class="text-xl font-black font-mono tracking-wider drop-shadow-md"><?= htmlspecialchars($new_youth_id) ?></p>
                                        </div>
                                        <div class="bg-green-500/20 border border-green-500/50 text-green-400 text-[9px] font-black uppercase px-2.5 py-1 rounded-md tracking-widest backdrop-blur-sm flex items-center gap-1"><i class="ph-bold ph-check-circle"></i> Verified</div>
                                    </div>
                                    <div class="flex gap-4 relative z-10 mb-6">
                                        <div class="w-20 h-20 rounded-2xl bg-slate-800 overflow-hidden border-2 border-slate-600 shrink-0 shadow-inner">
                                            <img src="<?= htmlspecialchars($uploaded_photo) ?>" class="w-full h-full object-cover">
                                        </div>
                                        <div class="flex flex-col justify-center overflow-hidden">
                                            <h2 class="text-lg font-black leading-tight mb-1 truncate"><?= htmlspecialchars($_POST['full_name']) ?></h2>
                                            <p class="text-xs text-slate-300 font-medium mb-1"><i class="ph-fill ph-map-pin text-brand-400"></i> <?= htmlspecialchars($_POST['barangay']) ?></p>
                                            <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Age: <span class="text-white"><?= htmlspecialchars($user_age) ?></span></p>
                                        </div>
                                    </div>
                                    <div class="flex justify-between items-end relative z-10 border-t border-slate-700/50 pt-4 mt-2">
                                        <div>
                                            <p class="text-[8px] uppercase tracking-widest text-slate-400 font-bold mb-1">Valid For</p>
                                            <p class="text-xs font-bold text-white"><i class="ph-bold ph-ticket text-brand-400"></i> <?= htmlspecialchars($selected_event) ?></p>
                                        </div>
                                        <div class="bg-white p-1.5 rounded-lg shadow-sm"><i class="ph-bold ph-qr-code text-4xl text-slate-900"></i></div>
                                    </div>
                                </div>
                            </div>

                            <div class="md:w-1/2 flex flex-col justify-center">
                                <div class="mb-8 text-center sm:text-left">
                                    <h3 class="text-lg font-black text-slate-800 dark:text-white mb-1 transition-colors">Achievement Portfolio</h3>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 font-medium mb-5 transition-colors">Badges unlocked by this youth.</p>
                                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-4 justify-items-center sm:justify-items-start">
                                        <div class="flex flex-col items-center gap-2 group">
                                            <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-yellow-400 to-amber-600 flex items-center justify-center text-white shadow-[0_10px_20px_-10px_rgba(245,158,11,0.6)] transform group-hover:scale-110 transition-all border-2 border-white dark:border-zinc-800"><i class="ph-fill ph-medal text-2xl"></i></div>
                                            <span class="text-[9px] font-black text-slate-800 dark:text-white text-center leading-tight uppercase tracking-wider transition-colors">Registered 2026</span>
                                        </div>
                                        <?php foreach (array_slice([['icon' => 'ph-plant', 'title' => 'Volunteer'],['icon' => 'ph-graduation-cap', 'title' => 'Seminar'],['icon' => 'ph-drop', 'title' => 'Blood Donor']], 0, 3) as $badge): ?>
                                        <div class="flex flex-col items-center gap-2 opacity-40 grayscale hover:grayscale-0 hover:opacity-100 transition-all cursor-not-allowed">
                                            <div class="w-14 h-14 rounded-full bg-slate-200 dark:bg-zinc-800 flex items-center justify-center text-slate-500 dark:text-zinc-400 border border-slate-300 dark:border-zinc-700 shadow-inner transition-colors"><i class="ph-fill <?= $badge['icon'] ?> text-2xl"></i></div>
                                            <span class="text-[9px] font-bold text-slate-500 dark:text-zinc-500 text-center leading-tight uppercase tracking-wider transition-colors"><?= $badge['title'] ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 p-5 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-4 shadow-sm transition-colors">
                                    <div class="flex items-center gap-4 text-left">
                                        <div class="w-12 h-12 bg-brand-50 dark:bg-brand-500/10 rounded-xl flex items-center justify-center text-brand-600 dark:text-brand-400 shrink-0 border border-brand-100 dark:border-brand-500/20 transition-colors"><i class="ph-fill ph-certificate text-2xl"></i></div>
                                        <div>
                                            <h4 class="font-bold text-slate-800 dark:text-white text-sm mb-0.5 transition-colors">Certificate of Registration</h4>
                                            <p class="text-[9px] text-slate-500 dark:text-zinc-400 font-bold uppercase tracking-widest transition-colors">Digitally Signed</p>
                                        </div>
                                    </div>
                                    <button onclick="window.print()" class="w-full sm:w-auto bg-slate-900 dark:bg-white text-white dark:text-zinc-900 px-5 py-2.5 rounded-xl text-xs font-bold shadow-md transition-all flex items-center justify-center gap-2 shrink-0 tap-effect"><i class="ph-bold ph-printer"></i> Print</button>
                                </div>
                                
                                <div class="mt-6 text-center sm:text-left">
                                    <a href="official_profiling.php" class="text-sm font-bold text-brand-600 dark:text-brand-400 hover:text-brand-700 transition-colors flex items-center justify-center sm:justify-start gap-2 tap-effect"><i class="ph-bold ph-plus-circle"></i> Profile Another Youth</a>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>

                    <?php if (!empty($error)): ?>
                        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-400 px-4 py-3 rounded-xl flex items-center gap-3 text-sm font-bold shadow-sm transition-colors"><i class="ph-fill ph-warning-circle text-xl"></i> <?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form action="" method="POST" enctype="multipart/form-data" id="registrationForm" class="glass-card rounded-[2rem] shadow-sm p-6 md:p-10 transition-colors relative overflow-hidden">
                        
                        <div class="step-form active" id="step-1">
                            <div class="flex items-center gap-3 mb-8 pb-4 border-b border-slate-100 dark:border-zinc-800 transition-colors">
                                <div class="w-10 h-10 bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 rounded-xl flex items-center justify-center transition-colors"><i class="ph-bold ph-identification-card text-xl"></i></div>
                                <div><h3 class="text-xl font-extrabold text-slate-800 dark:text-white transition-colors tracking-tight">Identity Profiling</h3><p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest transition-colors">Section 1</p></div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                <div class="md:col-span-3 relative">
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Full Legal Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="full_name" id="full_name" onkeyup="searchName(this.value)" autocomplete="off" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors" placeholder="Last Name, First Name M.I.">
                                    <div id="name_suggestions" class="absolute z-50 w-full bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl shadow-lg mt-1 hidden max-h-48 overflow-y-auto divide-y divide-slate-100 dark:divide-zinc-800 transition-colors custom-scrollbar"></div>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Date of Birth <span class="text-red-500">*</span></label>
                                    <input type="date" name="birthdate" id="birthdate" onchange="calculateAge()" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors" style="color-scheme: dark light;">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Age</label>
                                    <input type="number" name="age" id="age" readonly placeholder="Auto" class="w-full bg-slate-100 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-xl px-4 py-3.5 text-sm font-semibold cursor-not-allowed text-slate-500 dark:text-zinc-500 transition-colors">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Sex assigned at birth <span class="text-red-500">*</span></label>
                                    <select name="sex" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors appearance-none cursor-pointer">
                                        <option value="">Select...</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Gender Identity <span class="text-slate-400 dark:text-zinc-500 font-normal tracking-normal text-[9px]">(Optional)</span></label>
                                    <input type="text" name="gender_identity" placeholder="e.g., Man, Woman, Non-binary" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Civil Status <span class="text-red-500">*</span></label>
                                    <select name="civil_status" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors appearance-none cursor-pointer">
                                        <option value="">Select...</option><option value="Single">Single</option><option value="Married">Married</option><option value="Widowed">Widowed</option><option value="Separated">Separated</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Barangay <span class="text-red-500">*</span></label>
                                    <select name="barangay" id="barangay" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors appearance-none cursor-pointer">
                                        <option value="">Select...</option>
                                        <?php foreach ($all_barangays as $brgy): ?><option value="<?= $brgy ?>"><?= $brgy ?></option><?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="md:col-span-3 border-t border-slate-100 dark:border-zinc-800/80 pt-5 mt-2 transition-colors"></div>

                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Contact Number</label>
                                    <input type="tel" name="contact_number" placeholder="09XX-XXX-XXXX" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">House No. & Street <span class="text-red-500">*</span></label>
                                    <input type="text" name="address" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                </div>

                                <div class="md:col-span-3 border-t border-slate-100 dark:border-zinc-800/80 pt-5 mt-2 transition-colors"></div>

                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">PhilSys ID Number <span class="text-slate-400 dark:text-zinc-500 font-normal tracking-normal text-[9px]">(Optional)</span></label>
                                    <input type="text" name="philsys_id" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">PSA Birth Cert No. <span class="text-slate-400 dark:text-zinc-500 font-normal tracking-normal text-[9px]">(Optional)</span></label>
                                    <input type="text" name="psa_birth_cert" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">ID / Profile Photo (For Digital ID) <span class="text-red-500">*</span></label>
                                    <input type="file" name="id_photo" accept="image/*" required class="w-full text-xs text-slate-500 dark:text-zinc-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-brand-50 dark:file:bg-brand-500/10 file:text-brand-600 dark:file:text-brand-400 file:font-bold cursor-pointer border border-slate-200 dark:border-zinc-700 p-1.5 rounded-xl bg-slate-50 dark:bg-zinc-900 hover:bg-white dark:hover:bg-zinc-800 transition-colors shadow-sm">
                                </div>
                            </div>
                            <div class="mt-8 flex justify-end">
                                <button type="button" onclick="nextStep(1)" class="btn-gradient text-white px-8 py-3.5 rounded-xl font-bold flex items-center gap-2 tap-effect text-sm">Proceed to Family <i class="ph-bold ph-arrow-right"></i></button>
                            </div>
                        </div>

                        <div class="step-form" id="step-2">
                            <div class="flex items-center gap-3 mb-8 pb-4 border-b border-slate-100 dark:border-zinc-800 transition-colors">
                                <div class="w-10 h-10 bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-xl flex items-center justify-center transition-colors"><i class="ph-bold ph-users text-xl"></i></div>
                                <div><h3 class="text-xl font-extrabold text-slate-800 dark:text-white transition-colors tracking-tight">Family & Demographics</h3><p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest transition-colors">Section 2</p></div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Father's Name</label>
                                    <input type="text" name="father_name" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Mother's Maiden Name</label>
                                    <input type="text" name="mother_name" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Guardian's Name</label>
                                    <input type="text" name="guardian_name" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Emergency Contact No. <span class="text-red-500">*</span></label>
                                    <input type="text" name="emergency_contact" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                </div>

                                <div class="md:col-span-2 border-t border-slate-100 dark:border-zinc-800/80 pt-5 mt-2 transition-colors"></div>

                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Household Size</label>
                                    <input type="number" name="household_size" placeholder="Number of people in house" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Income Bracket</label>
                                    <select name="income_bracket" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors appearance-none cursor-pointer"><option value="">Select...</option><option value="Below 10,000">Below ₱10,000</option><option value="10,000 - 20,000">₱10,000 - ₱20,000</option><option value="Above 20,000">Above ₱20,000</option></select>
                                </div>
                                
                                <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
                                    <label class="flex items-center justify-between p-4 border border-slate-200 dark:border-zinc-700 rounded-xl bg-slate-50 dark:bg-zinc-900 cursor-pointer transition-colors">
                                        <span class="text-xs font-bold text-slate-700 dark:text-zinc-300">4Ps Beneficiary?</span>
                                        <select name="is_4ps" class="bg-white dark:bg-zinc-950 text-slate-800 dark:text-white border border-slate-200 dark:border-zinc-700 rounded shadow-sm text-xs py-1 px-2 outline-none cursor-pointer"><option value="No">No</option><option value="Yes">Yes</option></select>
                                    </label>
                                    <label class="flex items-center justify-between p-4 border border-slate-200 dark:border-zinc-700 rounded-xl bg-slate-50 dark:bg-zinc-900 cursor-pointer transition-colors">
                                        <span class="text-xs font-bold text-slate-700 dark:text-zinc-300">Solo Parent / Child?</span>
                                        <select name="is_solo_parent" class="bg-white dark:bg-zinc-950 text-slate-800 dark:text-white border border-slate-200 dark:border-zinc-700 rounded shadow-sm text-xs py-1 px-2 outline-none cursor-pointer"><option value="No">No</option><option value="Yes">Yes</option></select>
                                    </label>
                                    <label class="flex items-center justify-between p-4 border border-slate-200 dark:border-zinc-700 rounded-xl bg-slate-50 dark:bg-zinc-900 cursor-pointer transition-colors">
                                        <span class="text-xs font-bold text-slate-700 dark:text-zinc-300">PWD in Household?</span>
                                        <select name="is_pwd_household" class="bg-white dark:bg-zinc-950 text-slate-800 dark:text-white border border-slate-200 dark:border-zinc-700 rounded shadow-sm text-xs py-1 px-2 outline-none cursor-pointer"><option value="No">No</option><option value="Yes">Yes</option></select>
                                    </label>
                                </div>
                            </div>
                            <div class="mt-8 flex justify-between items-center">
                                <button type="button" onclick="prevStep(2)" class="text-slate-400 dark:text-zinc-500 font-bold px-4 py-3 hover:text-slate-800 dark:hover:text-white transition-colors flex items-center gap-2 text-sm tap-effect"><i class="ph-bold ph-arrow-left"></i> Back</button>
                                <button type="button" onclick="nextStep(2)" class="btn-gradient text-white px-8 py-3.5 rounded-xl font-bold flex items-center gap-2 tap-effect text-sm">Proceed to Education <i class="ph-bold ph-arrow-right"></i></button>
                            </div>
                        </div>

                        <div class="step-form" id="step-3">
                            <div class="flex items-center gap-3 mb-8 pb-4 border-b border-slate-100 dark:border-zinc-800 transition-colors">
                                <div class="w-10 h-10 bg-green-50 dark:bg-green-950/40 text-green-800 dark:text-green-300 rounded-xl flex items-center justify-center transition-colors"><i class="ph-bold ph-graduation-cap text-xl"></i></div>
                                <div><h3 class="text-xl font-extrabold text-slate-800 dark:text-white transition-colors tracking-tight">Education & Employment</h3><p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest transition-colors">Sections 3 & 4</p></div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                
                                <div class="md:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Highest Education Attained <span class="text-red-500">*</span></label>
                                    <select name="education_level" id="education_level" onchange="toggleEducationFields(this.value)" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors appearance-none cursor-pointer">
                                        <option value="">Select...</option>
                                        <option value="Elementary Level">Elementary Level</option>
                                        <option value="Elementary Graduate">Elementary Graduate</option>
                                        <option value="High School Level">High School Level</option>
                                        <option value="High School Graduate">High School Graduate</option>
                                        <option value="Vocational">Vocational / Short Course</option>
                                        <option value="College Level">College Level</option>
                                        <option value="College Graduate">College Graduate</option>
                                        <option value="Post-Graduate">Post-Graduate</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Name of School</label>
                                    <input type="text" name="school_name" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Course / Track (If applicable)</label>
                                    <input type="text" name="course" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                </div>

                                <div id="current_student_fields" class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-5 hidden mt-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">School ID No.</label>
                                        <input type="text" name="school_id" class="w-full bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Grade / Year Level</label>
                                        <input type="text" name="grade_level" class="w-full bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                    </div>

                                    <div class="md:col-span-2 grid grid-cols-2 sm:grid-cols-3 gap-4 mt-2">
                                        <label class="flex flex-col p-3 border border-slate-200 dark:border-zinc-700 rounded-xl bg-slate-50 dark:bg-zinc-900 cursor-pointer transition-colors">
                                            <span class="text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase mb-1 transition-colors">OSY / Dropout?</span>
                                            <select name="is_dropout" class="bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded shadow-sm text-xs py-1.5 px-2 outline-none cursor-pointer"><option value="No">No</option><option value="Yes">Yes</option></select>
                                        </label>
                                        <label class="flex flex-col p-3 border border-slate-200 dark:border-zinc-700 rounded-xl bg-slate-50 dark:bg-zinc-900 cursor-pointer transition-colors">
                                            <span class="text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase mb-1 transition-colors">ALS Enrolled?</span>
                                            <select name="is_als" class="bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded shadow-sm text-xs py-1.5 px-2 outline-none cursor-pointer"><option value="No">No</option><option value="Yes">Yes</option></select>
                                        </label>
                                        <label class="flex flex-col p-3 border border-slate-200 dark:border-zinc-700 rounded-xl bg-slate-50 dark:bg-zinc-900 col-span-2 sm:col-span-1 transition-colors">
                                            <span class="text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase mb-1 transition-colors">Scholarship Grant</span>
                                            <input type="text" name="scholarship" placeholder="If any" class="bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded shadow-sm text-xs py-1.5 px-2 outline-none">
                                        </label>
                                    </div>
                                </div>

                                <div class="md:col-span-2 border-t border-slate-100 dark:border-zinc-800/80 pt-5 mt-2 transition-colors"></div>

                                <div class="md:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Current Employment Status <span class="text-red-500">*</span></label>
                                    <select name="employment_status" id="employment_status" onchange="toggleEmploymentFields(this.value)" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors appearance-none cursor-pointer">
                                        <option value="">Select...</option>
                                        <option value="Student">Student (Not Working)</option>
                                        <option value="Unemployed">Unemployed / Looking for work</option>
                                        <option value="Working Student">Working Student</option>
                                        <option value="Employed">Employed (Full/Part-time)</option>
                                        <option value="Freelancer">Freelancer</option>
                                        <option value="Self-employed">Self-employed / Business</option>
                                        <option value="OFW">OFW</option>
                                    </select>
                                </div>
                                
                                <div id="working_fields" class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-5 hidden mt-2">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Occupation / Job Title</label>
                                        <input type="text" name="occupation" class="w-full bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Employer / Business</label>
                                        <input type="text" name="employer" class="w-full bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">Monthly Income Range</label>
                                        <select name="monthly_income" class="w-full bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors appearance-none cursor-pointer"><option value="">Select...</option><option value="Below 10k">Below ₱10,000</option><option value="10k - 20k">₱10,000 - ₱20,000</option><option value="20k - 30k">₱20,000 - ₱30,000</option><option value="Above 30k">Above ₱30,000</option></select>
                                    </div>
                                </div>

                                <div class="md:col-span-2 mt-2">
                                    <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors">TESDA NC Qualification (If any)</label>
                                    <input type="text" name="tesda_nc" placeholder="e.g., NC II Computer Systems" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl px-4 py-3.5 text-sm font-semibold focus:bg-white dark:focus:bg-zinc-950 focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-colors">
                                </div>
                            </div>

                            <div class="mt-8 flex justify-between items-center">
                                <button type="button" onclick="prevStep(3)" class="text-slate-400 dark:text-zinc-500 font-bold px-4 py-3 hover:text-slate-800 dark:hover:text-white transition-colors flex items-center gap-2 text-sm tap-effect"><i class="ph-bold ph-arrow-left"></i> Back</button>
                                <button type="button" onclick="nextStep(3)" class="btn-gradient text-white px-8 py-3.5 rounded-xl font-bold flex items-center gap-2 tap-effect text-sm">Proceed to Skills <i class="ph-bold ph-arrow-right"></i></button>
                            </div>
                        </div>

                        <div class="step-form" id="step-4">
                            <div class="flex items-center gap-3 mb-8 pb-4 border-b border-slate-100 dark:border-zinc-800 transition-colors">
                                <div class="w-10 h-10 bg-teal-50 dark:bg-teal-500/10 text-teal-600 dark:text-teal-400 rounded-xl flex items-center justify-center transition-colors"><i class="ph-bold ph-star text-xl"></i></div>
                                <div><h3 class="text-xl font-extrabold text-slate-800 dark:text-white transition-colors tracking-tight">Capacities & Interests</h3><p class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest transition-colors">Sections 5, 6, 7 & 8</p></div>
                            </div>

                            <div class="space-y-8">
                                <div>
                                    <label class="block text-[11px] font-black text-slate-800 dark:text-white uppercase tracking-wider mb-3 transition-colors"><i class="ph-bold ph-wrench text-brand-500"></i> Top Skills & Talents</label>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                        <?php foreach(['Programming', 'Cooking', 'Driving', 'Graphic Design', 'Photography', 'Music', 'Agriculture', 'Livestock', 'Carpentry', 'Electrical', 'Leadership', 'Public Speaking', 'Disaster Response', 'First Aid', 'Sports', 'Dance', 'Singing', 'Business', 'Marketing'] as $skill): ?>
                                            <label class="flex items-center gap-2 p-2 border border-slate-200 dark:border-zinc-700 rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer transition-colors"><input type="checkbox" name="skills[]" value="<?= $skill ?>" class="w-3.5 h-3.5 accent-brand-500"><span class="text-xs font-semibold text-slate-700 dark:text-zinc-300 transition-colors"><?= $skill ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-black text-slate-800 dark:text-white uppercase tracking-wider mb-3 transition-colors"><i class="ph-bold ph-users-three text-brand-500"></i> Affiliated Organizations</label>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                        <?php foreach(['SK', 'Church', 'Youth Organization', 'Red Cross', 'Scouts', 'Sports Club', 'Cultural Group', 'NGO', 'Volunteer'] as $org): ?>
                                            <label class="flex items-center gap-2 p-2 border border-slate-200 dark:border-zinc-700 rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer transition-colors"><input type="checkbox" name="organizations[]" value="<?= $org ?>" class="w-3.5 h-3.5 accent-brand-500"><span class="text-xs font-semibold text-slate-700 dark:text-zinc-300 transition-colors"><?= $org ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-black text-slate-800 dark:text-white uppercase tracking-wider mb-3 transition-colors"><i class="ph-bold ph-bank text-brand-500"></i> Government Records / Services</label>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                        <?php foreach(['Registered Voter', 'PhilHealth', 'PhilSys', 'TIN', 'National ID', 'Driver\'s License', 'Passport', 'Scholarship', 'TESDA', 'PESO'] as $gov): ?>
                                            <label class="flex items-center gap-2 p-2 border border-slate-200 dark:border-zinc-700 rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer transition-colors"><input type="checkbox" name="gov_services[]" value="<?= $gov ?>" class="w-3.5 h-3.5 accent-brand-500"><span class="text-xs font-semibold text-slate-700 dark:text-zinc-300 transition-colors"><?= $gov ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-black text-slate-800 dark:text-white uppercase tracking-wider mb-3 transition-colors"><i class="ph-bold ph-lightbulb text-brand-500"></i> Centers of Participation</label>
                                    <p class="text-[10px] text-slate-500 dark:text-zinc-500 mb-3 transition-colors">What programs are you interested in joining?</p>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                        <?php foreach(['Agriculture', 'Sports', 'Arts', 'Leadership', 'Entrepreneurship', 'Technology', 'Scholarships', 'Environment', 'Mental Health', 'Volunteerism', 'Culture', 'Disaster Response'] as $interest): ?>
                                            <label class="flex items-center gap-2 p-2 border border-slate-200 dark:border-zinc-700 rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-800 cursor-pointer transition-colors"><input type="checkbox" name="programs[]" value="<?= $interest ?>" class="w-3.5 h-3.5 accent-brand-500"><span class="text-xs font-semibold text-slate-700 dark:text-zinc-300 transition-colors"><?= $interest ?></span></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-10 flex justify-between items-center">
                                <button type="button" onclick="prevStep(4)" class="text-slate-400 dark:text-zinc-500 font-bold px-4 py-3 hover:text-slate-800 dark:hover:text-white transition-colors flex items-center gap-2 text-sm tap-effect"><i class="ph-bold ph-arrow-left"></i> Back</button>
                                <button type="button" onclick="nextStep(4)" class="btn-gradient text-white px-8 py-3.5 rounded-xl font-bold flex items-center gap-2 tap-effect text-sm">Proceed to Event <i class="ph-bold ph-arrow-right"></i></button>
                            </div>
                        </div>

                        <div class="step-form" id="step-5">
                            <div class="text-center mb-8">
                                <div class="w-16 h-16 bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400 rounded-2xl flex items-center justify-center mx-auto mb-4 border border-brand-100 dark:border-brand-500/20 transition-colors"><i class="ph-bold ph-signature text-3xl"></i></div>
                                <h3 class="text-2xl font-black text-slate-900 dark:text-white mb-1 transition-colors">Event Pass & Certification</h3>
                                <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium transition-colors">Select the event and sign the registration. <span class="text-red-500">*</span></p>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                                <?php 
                                $events = [['title' => 'Raffle', 'icon' => 'ph-ticket', 'desc' => 'General Entry'],['title' => 'Gabi ng Parangal', 'icon' => 'ph-medal', 'desc' => 'Awards Night'],['title' => 'MOKT', 'icon' => 'ph-basketball', 'desc' => 'Olympics'],['title' => 'Marenu', 'icon' => 'ph-hands-clapping', 'desc' => 'Cultural Fest'],['title' => 'BIDA', 'icon' => 'ph-shield-check', 'desc' => 'Anti-Drug']];
                                foreach($events as $e): ?>
                                    <label class="relative cursor-pointer group">
                                        <input type="radio" name="event_purpose" value="<?= $e['title'] ?>" required class="peer sr-only event-radio">
                                        <div class="border-2 border-slate-200 dark:border-zinc-700 rounded-2xl p-4 hover:border-brand-300 dark:hover:border-brand-500 transition-all flex items-center gap-4 bg-slate-50 dark:bg-zinc-900 relative overflow-hidden group-hover:bg-white dark:group-hover:bg-zinc-800 shadow-sm">
                                            <div class="w-12 h-12 rounded-xl bg-white dark:bg-zinc-950 border border-slate-100 dark:border-zinc-800 flex items-center justify-center text-slate-400 dark:text-zinc-500 group-hover:text-brand-500 dark:group-hover:text-brand-400 shadow-sm transition-colors"><i class="ph-bold <?= $e['icon'] ?> text-2xl"></i></div>
                                            <div><h4 class="font-black text-slate-800 dark:text-white text-base transition-colors"><?= $e['title'] ?></h4><p class="text-[10px] uppercase tracking-widest text-slate-400 dark:text-zinc-500 font-bold transition-colors"><?= $e['desc'] ?></p></div>
                                            <i class="ph-fill ph-check-circle text-brand-500 text-2xl absolute right-4 opacity-0 scale-50 transition-all duration-300 check-icon drop-shadow-sm"></i>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                                <label class="relative cursor-pointer group sm:col-span-2 mt-2">
                                    <input type="radio" name="event_purpose" value="General Profiling" required class="peer sr-only event-radio">
                                    <div class="border-2 border-dashed border-slate-300 dark:border-zinc-700 rounded-2xl p-4 hover:border-brand-400 dark:hover:border-brand-500 transition-all flex items-center justify-center gap-3 bg-white dark:bg-zinc-900 relative overflow-hidden text-center">
                                        <i class="ph-bold ph-identification-card text-xl text-slate-400 dark:text-zinc-500 transition-colors"></i>
                                        <div><h4 class="font-bold text-slate-600 dark:text-zinc-300 transition-colors">No Event - General Profiling Only</h4></div>
                                        <i class="ph-fill ph-check-circle text-brand-500 text-xl absolute right-4 opacity-0 scale-50 transition-all duration-300 check-icon"></i>
                                    </div>
                                </label>
                            </div>

                            <div class="bg-white dark:bg-zinc-900 cert-border p-6 md:p-8 rounded-xl shadow-inner relative overflow-hidden transition-colors">
                                <div class="absolute top-0 right-0 p-4 opacity-10 dark:opacity-5"><i class="ph-fill ph-seal-check text-6xl"></i></div>
                                <h4 class="text-xs font-black uppercase tracking-[0.2em] text-slate-400 dark:text-zinc-500 text-center mb-6 transition-colors">Data Privacy Consent</h4>
                                
                                <div class="text-[11px] md:text-xs text-slate-600 dark:text-zinc-400 leading-relaxed text-justify mb-8 font-medium transition-colors">
                                    By affixing the electronic signature below, it is hereby declared that all information provided in this TALA OS Profile is true and correct. Consent is given to the Local Youth Development Office to collect, index, and process this data strictly for profiling, program targeting, and event registration in accordance with the Data Privacy Act of 2012 (RA 10173). Timestamp and IP address are recorded for security purposes.
                                </div>
                                
                                <div class="border-t border-dashed border-slate-300 dark:border-zinc-700 pt-6 transition-colors">
                                    <label class="block text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest mb-2 text-center transition-colors">Electronic Signature (Administrator) <span class="text-red-500">*</span></label>
                                    <input type="text" name="e_signature" required class="w-full text-center bg-transparent border-b-2 border-slate-800 dark:border-white px-4 py-2 text-xl md:text-2xl font-['Brush_Script_MT',cursive] italic focus:outline-none focus:border-brand-500 dark:focus:border-brand-400 text-slate-900 dark:text-white transition-colors" placeholder="Type authorized name here">
                                </div>
                            </div>

                            <div class="mt-8 md:mt-10 flex flex-col md:flex-row justify-between items-center gap-4">
                                <button type="button" onclick="prevStep(5)" class="text-slate-400 dark:text-zinc-500 font-bold px-4 py-3 hover:text-slate-800 dark:hover:text-white transition-colors flex items-center gap-2 text-sm tap-effect w-full md:w-auto justify-center"><i class="ph-bold ph-arrow-left"></i> Review Details</button>
                                <button type="submit" class="w-full md:w-auto btn-gradient text-white font-black uppercase tracking-widest py-3.5 md:py-4 px-8 md:px-10 rounded-2xl shadow-xl hover:shadow-2xl transition-all text-sm flex items-center justify-center gap-3 tap-effect">
                                    Generate ID & Badges <i class="ph-bold ph-identification-card text-xl"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // -----------------------------------------
        // 1. INTELLISENSE (AJAX LIVE SEARCH)
        // -----------------------------------------
        async function searchName(query) {
            const suggestionsBox = document.getElementById('name_suggestions');
            if (query.length < 2) {
                suggestionsBox.classList.add('hidden');
                return;
            }
            
            try {
                const response = await fetch('?ajax_search=' + encodeURIComponent(query));
                if (!response.ok) throw new Error('Network response was not ok');
                const data = await response.json();
                
                if (data.length > 0) {
                    suggestionsBox.innerHTML = '';
                    data.forEach(user => {
                        const div = document.createElement('div');
                        div.className = 'p-3 hover:bg-brand-50 dark:hover:bg-zinc-800 cursor-pointer transition-colors';
                        
                        const displayBarangay = user.barangay || 'N/A';
                        const displayDate = user.birthdate || 'N/A';
                        
                        div.innerHTML = `<div class="font-bold text-slate-800 dark:text-white text-sm transition-colors">${user.full_name}</div><div class="text-[10px] text-slate-500 dark:text-zinc-500 font-bold uppercase tracking-widest mt-0.5 transition-colors"><i class="ph-fill ph-map-pin text-brand-400"></i> ${displayBarangay} &nbsp;&bull;&nbsp; <i class="ph-fill ph-calendar text-brand-400"></i> ${displayDate}</div>`;
                        
                        div.onclick = () => {
                            document.getElementById('full_name').value = user.full_name || '';
                            if (user.birthdate) document.getElementById('birthdate').value = user.birthdate;
                            
                            const setDropdown = (dropdownName, dbValue) => {
                                if (!dbValue) return;
                                const selectElement = document.querySelector(`select[name="${dropdownName}"]`);
                                if (selectElement) {
                                    for (let i = 0; i < selectElement.options.length; i++) {
                                        if (selectElement.options[i].value.toLowerCase() === dbValue.toLowerCase()) {
                                            selectElement.selectedIndex = i;
                                            break;
                                        }
                                    }
                                }
                            };

                            setDropdown('barangay', user.barangay);
                            setDropdown('sex', user.sex);
                            setDropdown('civil_status', user.civil_status);
                            if (user.address) {
                                const addressInput = document.querySelector(`input[name="address"]`);
                                if (addressInput) addressInput.value = user.address;
                            }

                            calculateAge();
                            suggestionsBox.classList.add('hidden');
                            
                            const toast = document.createElement('div');
                            toast.className = 'fixed top-4 right-4 bg-green-50 dark:bg-green-950/40 text-green-800 dark:text-green-300 px-4 py-3 rounded-xl shadow-lg border border-green-200 dark:border-green-800/50 text-sm font-bold flex items-center gap-2 z-50 transition-colors';
                            toast.innerHTML = user.source === 'profile' 
                                ? '<i class="ph-fill ph-user-circle-check text-xl"></i> Existing Profile Found! Auto-filling data.'
                                : '<i class="ph-fill ph-file-text text-xl"></i> Registry Record Found! Fill out remaining fields.';
                            
                            document.body.appendChild(toast);
                            setTimeout(() => toast.remove(), 4000);
                        };
                        suggestionsBox.appendChild(div);
                    });
                    suggestionsBox.classList.remove('hidden');
                } else {
                    suggestionsBox.classList.add('hidden');
                }
            } catch (e) {
                console.error("AJAX Error: ", e);
            }
        }

        document.addEventListener('click', function(e) {
            const box = document.getElementById('name_suggestions');
            const input = document.getElementById('full_name');
            if (box && e.target !== input && !box.contains(e.target)) {
                box.classList.add('hidden');
            }
        });

        // -----------------------------------------
        // 2. CONDITIONAL SMART FIELDS LOGIC
        // -----------------------------------------
        function toggleEducationFields(level) {
            const studentFields = document.getElementById('current_student_fields');
            if (studentFields) {
                if (level.includes('Graduate') || level === 'Vocational' || level === 'Post-Graduate' || level === '') {
                    studentFields.classList.add('hidden');
                    studentFields.querySelectorAll('input').forEach(input => input.value = '');
                    studentFields.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
                } else {
                    studentFields.classList.remove('hidden');
                }
            }
        }

        function toggleEmploymentFields(status) {
            const workingFields = document.getElementById('working_fields');
            if (workingFields) {
                if (status === 'Student' || status === 'Unemployed' || status === '') {
                    workingFields.classList.add('hidden');
                    workingFields.querySelectorAll('input').forEach(input => input.value = '');
                    workingFields.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
                } else {
                    workingFields.classList.remove('hidden');
                }
            }
        }

        // -----------------------------------------
        // 3. WIZARD NAVIGATION & VALIDATION
        // -----------------------------------------
        const totalSteps = 5;

        function calculateAge() {
            const birthdate = document.getElementById('birthdate').value;
            if (birthdate) {
                const today = new Date();
                const birthDate = new Date(birthdate);
                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) { age--; }
                document.getElementById('age').value = age;
            }
        }

        function nextStep(current) {
            const stepDiv = document.getElementById('step-' + current);
            const inputs = stepDiv.querySelectorAll('input[required], select[required]');
            let isValid = true;
            
            for (let input of inputs) {
                if (input.closest('.hidden')) continue;

                if (!input.checkValidity()) {
                    input.reportValidity();
                    isValid = false;
                    break;
                }
            }
            
            if (current === 5) {
                const radios = stepDiv.querySelectorAll('input[type="radio"][required]');
                let isRadioValid = false;
                radios.forEach(r => { if(r.checked) isRadioValid = true; });
                if(!isRadioValid && radios.length > 0) {
                    radios[0].reportValidity();
                    isValid = false;
                }
            }

            if (!isValid) return;

            stepDiv.classList.remove('active');
            document.getElementById('step-' + (current + 1)).classList.add('active');
            document.getElementById('registrationForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function prevStep(current) {
            document.getElementById('step-' + current).classList.remove('active');
            document.getElementById('step-' + (current - 1)).classList.add('active');
            document.getElementById('registrationForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    </script>
</body>
</html>