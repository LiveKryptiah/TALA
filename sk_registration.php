<?php
require 'db_connect.php';

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $barangay = $_POST['barangay'];
    $sk_position = $_POST['sk_position'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Ensure upload directory exists
    $upload_dir = 'uploads/sk_profiles/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    // Default file paths to NULL if not uploaded
    $profile_pic_path = null;
    $oath_path = null;
    $uploadOk = true;

    // Handle Profile Picture
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $profile_pic_path = $upload_dir . 'PFP_' . time() . '_' . preg_replace('/[^A-Za-z0-9]/', '', $username) . '.' . $ext;
            move_uploaded_file($_FILES['profile_picture']['tmp_name'], $profile_pic_path);
        } else {
            $uploadOk = false;
            $message = "Invalid format for Profile Picture (JPG/PNG only).";
        }
    }

    // Handle Oath of Office
    if ($uploadOk && isset($_FILES['oath_office']) && $_FILES['oath_office']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['oath_office']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'])) {
            $oath_path = $upload_dir . 'OATH_' . time() . '_' . preg_replace('/[^A-Za-z0-9]/', '', $username) . '.' . $ext;
            move_uploaded_file($_FILES['oath_office']['tmp_name'], $oath_path);
        } else {
            $uploadOk = false;
            $message = "Invalid format for Oath of Office (PDF/JPG/PNG only).";
        }
    }

    if ($uploadOk) {
        $stmtCheck = $pdo->prepare("SELECT id FROM admins WHERE username = :username");
        $stmtCheck->execute([':username' => $username]);
        
        if ($stmtCheck->rowCount() > 0) {
            $message = "Username is already taken. Please choose another.";
            $messageType = "red";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            try {
                // Ensure table has the new column for oath of office if it doesn't exist
                $cols = $pdo->query("SHOW COLUMNS FROM admins")->fetchAll(PDO::FETCH_COLUMN);
                if (!in_array('oath_file_path', $cols)) {
                    $pdo->exec("ALTER TABLE admins ADD COLUMN oath_file_path VARCHAR(255) NULL AFTER profile_picture");
                }

                $stmt = $pdo->prepare("INSERT INTO admins (full_name, username, password_hash, role, barangay_assigned, sk_position, status, profile_picture, oath_file_path) VALUES (:full_name, :username, :password_hash, 'sub-admin', :barangay, :sk_position, 'pending', :profile_picture, :oath_file)");
                
                $stmt->execute([
                    ':full_name' => $full_name,
                    ':username' => $username,
                    ':password_hash' => $hashed_password,
                    ':barangay' => $barangay,
                    ':sk_position' => $sk_position,
                    ':profile_picture' => $profile_pic_path,
                    ':oath_file' => $oath_path
                ]);

                $message = "Registration successful! Please wait for the Municipal Admin to approve your account.";
                $messageType = "green";
            } catch (PDOException $e) {
                $message = "Error: " . $e->getMessage();
                $messageType = "red";
            }
        }
    } else {
        $messageType = "red";
    }
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Account Request | TALA OS</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { brand: { 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d' } },
                    boxShadow: { 'soft': '0 20px 40px -15px rgba(0,0,0,0.05)', 'glow': '0 0 20px rgba(22, 163, 74, 0.2)' }
                }
            }
        }
    </script>
    
    <style>
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .animate-blob { animation: blob 8s infinite alternate ease-in-out; }
        .animation-delay-2000 { animation-delay: 2s; }
        .glass-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.8); }
        .text-gradient { background: linear-gradient(135deg, #16a34a, #eab308); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-[#f8fafc] text-slate-800 antialiased selection:bg-brand-500 selection:text-white min-h-screen py-12 px-4 flex flex-col items-center justify-center relative overflow-x-hidden">

    <div class="fixed inset-0 w-full h-full z-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-[10%] -left-[10%] w-[500px] h-[500px] bg-brand-400/20 rounded-full mix-blend-multiply filter blur-[100px] opacity-70 animate-blob"></div>
        <div class="absolute -bottom-[10%] -right-[10%] w-[500px] h-[500px] bg-yellow-400/20 rounded-full mix-blend-multiply filter blur-[100px] opacity-70 animate-blob animation-delay-2000"></div>
    </div>

    <a href="login.php" class="absolute top-8 left-8 sm:left-12 flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-brand-600 transition-colors z-20 bg-white/60 px-5 py-2.5 rounded-full backdrop-blur-md shadow-sm border border-white">
        <i class="ph-bold ph-arrow-left"></i> Return to Login
    </a>

    <div class="w-full max-w-xl px-4 relative z-10">
        <div class="glass-card rounded-[2.5rem] p-8 sm:p-12 shadow-soft w-full">
            
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-white rounded-3xl flex items-center justify-center shadow-sm border border-slate-100 mx-auto mb-5 group">
                    <img src="TALA-LOGO.ico" alt="TALA Logo" class="w-12 h-12 object-contain drop-shadow-sm group-hover:scale-110 transition-transform duration-500">
                </div>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight mb-2">TALA <span class="text-gradient">OS</span></h1>
                <p class="text-sm font-semibold text-slate-500 uppercase tracking-widest">SK Account Provisioning</p>
            </div>

            <?php if (!empty($message)): 
                $bgClass = $messageType === 'green' ? 'bg-green-50/80 border-green-200 text-green-700' : 'bg-red-50/80 border-red-200 text-red-600';
                $iconClass = $messageType === 'green' ? 'ph-check-circle text-green-600' : 'ph-warning-circle text-red-600';
            ?>
                <div class="mb-8 <?= $bgClass ?> backdrop-blur-sm border px-5 py-4 rounded-2xl flex items-start gap-3 text-sm font-medium shadow-sm animate-pulse">
                    <i class="ph-fill <?= $iconClass ?> text-xl mt-0.5 flex-shrink-0"></i>
                    <p class="leading-relaxed"><?= htmlspecialchars($message) ?></p>
                </div>
            <?php endif; ?>

            <form action="sk_registration.php" method="POST" enctype="multipart/form-data" class="space-y-5">
                
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Full Name <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400"><i class="ph-fill ph-user"></i></div>
                        <input type="text" name="full_name" required placeholder="Juan Dela Cruz" class="block w-full pl-11 pr-4 py-3.5 bg-white/80 border border-slate-200 rounded-2xl text-sm font-semibold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Barangay <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400"><i class="ph-fill ph-map-pin"></i></div>
                            <select name="barangay" required class="block w-full pl-11 pr-4 py-3.5 bg-white/80 border border-slate-200 rounded-2xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm appearance-none cursor-pointer">
                                <option value="" disabled selected>Select...</option>
                                <?php foreach ($all_barangays as $brgy): ?>
                                    <option value="<?= $brgy ?>"><?= $brgy ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Position <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400"><i class="ph-fill ph-identification-badge"></i></div>
                            <select name="sk_position" required class="block w-full pl-11 pr-4 py-3.5 bg-white/80 border border-slate-200 rounded-2xl text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm appearance-none cursor-pointer">
                                <option value="" disabled selected>Select...</option>
                                <option value="SK Chairperson">SK Chairperson</option>
                                <option value="SK Kagawad">SK Kagawad</option>
                                <option value="SK Secretary">SK Secretary</option>
                                <option value="SK Treasurer">SK Treasurer</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 pt-2 border-t border-slate-200/60 mt-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Profile Picture <span class="text-red-500">*</span></label>
                        <input type="file" name="profile_picture" accept=".jpg,.jpeg,.png" required class="block w-full text-xs file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer bg-white/80 border border-slate-200 rounded-2xl p-1.5 shadow-sm text-slate-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Oath of Office <span class="text-red-500">*</span></label>
                        <input type="file" name="oath_office" accept=".pdf,.jpg,.jpeg,.png" required class="block w-full text-xs file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer bg-white/80 border border-slate-200 rounded-2xl p-1.5 shadow-sm text-slate-500">
                    </div>
                </div>

                <div class="pt-2 border-t border-slate-200/60 mt-4">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Desired Username <span class="text-red-500">*</span></label>
                    <div class="relative mb-5">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400"><i class="ph-fill ph-at"></i></div>
                        <input type="text" name="username" required placeholder="e.g., sk_juandelacruz" class="block w-full pl-11 pr-4 py-3.5 bg-white/80 border border-slate-200 rounded-2xl text-sm font-semibold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                    </div>
                    
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Security Key <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400"><i class="ph-fill ph-lock-key"></i></div>
                        <input type="password" name="password" required placeholder="••••••••" class="block w-full pl-11 pr-4 py-3.5 bg-white/80 border border-slate-200 rounded-2xl text-sm font-semibold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                    </div>
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-brand-600 to-yellow-500 text-white font-bold text-sm py-4 rounded-2xl shadow-md hover:shadow-glow transform hover:-translate-y-0.5 transition-all duration-300 flex items-center justify-center gap-2 mt-8">
                    Submit Access Request <i class="ph-bold ph-paper-plane-tilt text-lg"></i>
                </button>
                
            </form>

            <div class="mt-8 text-center pt-6 border-t border-slate-200/50">
                <p class="text-xs text-slate-500 font-medium">Already approved? <a href="login.php" class="font-bold text-slate-800 hover:text-brand-600 transition-colors">Sign in here</a></p>
                <p class="text-[10px] text-slate-400 font-bold tracking-widest uppercase mt-6 opacity-70"><i class="ph-fill ph-shield-check mr-1 text-sm align-middle"></i> Pending MYDO Approval</p>
            </div>

        </div>
    </div>
</body>
</html>
