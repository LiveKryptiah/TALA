<?php
session_start();
require 'db_connect.php';

// 1. THE SMART ROUTER (If already logged in, send them to their portal)
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $role = $_SESSION['role'] ?? '';
    
    if ($role === 'deped') { header("Location: deped_portal.php"); exit(); }
    elseif ($role === 'peso') { header("Location: peso_portal.php"); exit(); }
    elseif ($role === 'mho') { header("Location: mho_portal.php"); exit(); }
    elseif ($role === 'mswdo') { header("Location: mswdo_portal.php"); exit(); }
    elseif ($role === 'sub-admin' || $role === 'sk_admin') { header("Location: sk_dashboard.php"); exit(); }
    else { header("Location: tala_dashboard.php"); exit(); } // Main Admin
}

$error = '';

// 2. PROCESS LOGIN ATTEMPT
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, full_name, profile_picture, password_hash, role, status, barangay_assigned, sk_position FROM admins WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password_hash'])) {
                
                if ($admin['status'] === 'pending') {
                    $error = 'Your account is pending approval by the Municipal Admin.';
                } elseif ($admin['status'] === 'rejected') {
                    $error = 'Your account registration was rejected.';
                } else {
                    // LOGIN SUCCESSFUL - Set Sessions
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_full_name'] = $admin['full_name'] ?: $admin['username'];
                    $_SESSION['admin_profile_picture'] = $admin['profile_picture'] ?? '';
                    $_SESSION['role'] = $admin['role']; 
                    $_SESSION['admin_barangay'] = $admin['barangay_assigned']; 
                    $_SESSION['barangay_assigned'] = $admin['barangay_assigned'];
                    $_SESSION['sk_position'] = $admin['sk_position'];

                    // Update last login
                    $pdo->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([$admin['id']]);

                    // ROUTE USER
                    $role = $admin['role'];
                    if ($role === 'deped') { header("Location: deped_portal.php"); exit(); }
                    elseif ($role === 'peso') { header("Location: peso_portal.php"); exit(); }
                    elseif ($role === 'mho') { header("Location: mho_portal.php"); exit(); }
                    elseif ($role === 'mswdo') { header("Location: mswdo_portal.php"); exit(); }
                    elseif ($role === 'sub-admin' || $role === 'sk_admin') { header("Location: sk_dashboard.php"); exit(); }
                    else { header("Location: tala_dashboard.php"); exit(); }
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    } else {
        $error = 'Please fill in all fields.';
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
    <title>Secure Login | TALA Web Platform</title>
    
    <!-- ANTI-FLASH SCRIPT: Must be at the top of the <head>! -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <!-- ENABLE DARK MODE IN TAILWIND CONFIG -->
    <script>
        tailwind.config = {
            darkMode: 'class', // Enables class-based dark mode
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { brand: { 500: '#3b82f6', 600: '#2563eb', 900: '#1e3a8a' } }
                }
            }
        }
    </script>
    
    <style>
        .glass-card { 
            background: #ffffff; 
            border: none; 
            transition: all 0.3s ease; 
        }
        .dark .glass-card { 
            background: #18181b; 
            border: none; 
        }
        
        .btn-gradient {
            background: #15803d;
            color: #ffffff;
            transition: background-color 0.3s ease, opacity 0.3s ease, transform 0.3s ease;
        }
        .btn-gradient:hover {
            background: #166534;
            opacity: 0.95;
            transform: translateY(-2px);
        }
        .dark .btn-gradient {
            background: #16a34a;
        }
        .dark .btn-gradient:hover {
            background: #15803d;
        }

        /* Remove all outline / outside strokelines on focus */
        input:focus,
        input:focus-visible,
        input:active {
            outline: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased min-h-screen flex items-center justify-center relative overflow-hidden selection:bg-brand-500 selection:text-white transition-colors duration-300">

    <!-- FLOATING THEME TOGGLE BUTTON -->
    <button id="themeToggle" class="absolute top-6 right-6 z-50 w-12 h-12 flex items-center justify-center rounded-2xl bg-white dark:bg-zinc-900 text-slate-500 dark:text-zinc-400 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors duration-300" title="Toggle Dark Mode">
        <i id="themeIcon" class="ph-bold ph-moon text-xl"></i>
    </button>

    <!-- LOGIN FORM CONTAINER -->
    <div class="w-full max-w-md px-6 relative z-10">
        <div class="glass-card rounded-[2.5rem] p-8 md:p-10">
            
            <div class="text-center mb-10">
                <div class="w-16 h-16 bg-slate-50 dark:bg-zinc-800 rounded-2xl flex items-center justify-center mx-auto mb-5 transition-colors">
                    <img src="TALA-LOGO.ico" alt="TALA Logo" class="w-10 h-10 object-contain" onerror="this.outerHTML='<i class=\'ph-fill ph-shield-check text-3xl text-green-600\'></i>'">
                </div>
                <h1 class="text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight mb-2 transition-colors">TALA</h1>
                <p class="text-[10px] font-extrabold text-slate-400 dark:text-zinc-500 uppercase tracking-widest transition-colors">Authorized Access Only</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="mb-6 bg-red-50/80 dark:bg-red-500/10 backdrop-blur-md border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-400 px-4 py-3 rounded-xl flex items-center gap-3 text-sm font-bold transition-colors">
                    <i class="ph-fill ph-warning-circle text-xl"></i>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-5">
                <div>
                    <label for="username" class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors cursor-pointer">Username / Identifier</label>
                    <div class="relative">
                        <i class="ph-bold ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 transition-colors"></i>
                        <input type="text" name="username" id="username" required 
                                class="w-full bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl pl-11 pr-4 py-3 text-sm font-semibold outline-none focus:outline-none focus:ring-0 focus:border-slate-200 dark:focus:border-zinc-700 transition-all placeholder-slate-400 dark:placeholder-zinc-500" 
                                placeholder="Enter your username">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-[10px] font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-wider mb-2 transition-colors cursor-pointer">Security Password</label>
                    <div class="relative">
                        <i class="ph-bold ph-lock-key absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 transition-colors"></i>
                        <input type="password" name="password" id="password" required 
                                class="w-full bg-slate-50 dark:bg-zinc-800/60 border border-slate-200 dark:border-zinc-700 text-slate-800 dark:text-white rounded-xl pl-11 pr-4 py-3 text-sm font-semibold outline-none focus:outline-none focus:ring-0 focus:border-slate-200 dark:focus:border-zinc-700 transition-all placeholder-slate-400 dark:placeholder-zinc-500" 
                                placeholder="••••••••">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full btn-gradient font-extrabold py-3.5 rounded-xl text-sm flex items-center justify-center gap-2 transition-all duration-300">
                        Sign in <i class="ph-bold ph-arrow-right"></i>
                    </button>
                </div>
            </form>

        </div>

        <div class="mt-8 text-center flex flex-col gap-3">
            <a href="public_registration.php" class="text-sm font-bold text-green-600 dark:text-green-500 hover:text-green-700 dark:hover:text-green-400 transition-colors">Register as a Youth Citizen</a>
            <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-widest transition-colors">&copy; <?= date('Y') ?> Tumauini Youth Development</p>
        </div>
        
    </div>

    <!-- THEME TOGGLE LOGIC -->
    <script>
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        
        // Ensure icon matches current state on load
        if (document.documentElement.classList.contains('dark')) {
            themeIcon.classList.replace('ph-moon', 'ph-sun');
        }

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