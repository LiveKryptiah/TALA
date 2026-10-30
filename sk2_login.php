<?php
// login.php - TALA OS Main Login Gateway
session_start();
require_once 'db_connect.php';

// If already logged in, redirect them immediately based on role
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: manage_disclosure.php"); // Update this to your main admin page if different
        exit();
    } else {
        header("Location: sk2_dashboard.php");
        exit();
    }
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        try {
            // Adjust table name 'users' and columns to match your actual database
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify user exists and password matches 
            // Note: If you are using plain text passwords for testing instead of hashes, change this to: if ($password === $user['password'])
            if ($user && password_verify($password, $user['password'])) {
                
                // Set Session Variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role']; 
                $_SESSION['barangay_assigned'] = $user['barangay_assigned'] ?? 'Municipal Level';
                $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];

                // Route based on role
                if ($user['role'] === 'admin') {
                    header("Location: manage_disclosure.php"); 
                } else {
                    header("Location: sk2_dashboard.php");
                }
                exit();
                
            } else {
                $error_message = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error_message = "System error: Please check your database connection.";
        }
    } else {
        $error_message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TALA OS Command Center</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { brand: { 50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d' } }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .hero-pattern { background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 24px 24px; }
        .btn-gradient {
            background: #15803d;
            color: #ffffff;
            transition: background-color 0.3s ease, opacity 0.3s ease, transform 0.3s ease;
        }
        .btn-gradient:hover { background: #166534; opacity: 0.95; transform: translateY(-2px); }

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
<body class="min-h-screen flex items-center justify-center relative overflow-hidden text-slate-800 bg-slate-50">

    <div class="w-full max-w-md bg-white p-8 md:p-10 rounded-[2.5rem] relative z-10 m-4">
        
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-slate-50 rounded-[1.5rem] flex items-center justify-center mx-auto mb-4 overflow-hidden p-2">
                <img src="TALA-LOGO.ico" alt="TALA Logo" class="w-full h-full object-contain">
            </div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">TALA OS</h1>
            <p class="text-xs font-bold text-brand-600 uppercase tracking-widest mt-1">Command Center Gateway</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="bg-red-50 text-red-600 px-4 py-3 rounded-xl text-sm font-bold border border-red-100 flex items-center gap-2 mb-6">
                <i class="ph-fill ph-warning-circle text-lg"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label for="username" class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5 ml-1 cursor-pointer">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                        <i class="ph-bold ph-user"></i>
                    </div>
                    <input type="text" id="username" name="username" required placeholder="Enter your username" class="w-full border border-slate-200 rounded-xl pl-11 pr-4 py-3 bg-slate-50 text-sm outline-none focus:outline-none focus:ring-0 focus:border-slate-200 transition-colors">
                </div>
            </div>

            <div>
                <label for="password" class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5 ml-1 cursor-pointer">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                        <i class="ph-bold ph-lock-key"></i>
                    </div>
                    <input type="password" id="password" name="password" required placeholder="Enter your password" class="w-full border border-slate-200 rounded-xl pl-11 pr-4 py-3 bg-slate-50 text-sm outline-none focus:outline-none focus:ring-0 focus:border-slate-200 transition-colors">
                </div>
            </div>

            <button type="submit" class="w-full btn-gradient font-bold py-3.5 rounded-xl mt-2 flex items-center justify-center gap-2">
                Sign In <i class="ph-bold ph-arrow-right"></i>
            </button>
        </form>

        <div class="mt-8 text-center border-t border-slate-100 pt-6">
            <p class="text-[11px] font-semibold text-slate-400">Restricted Access &bull; Authorized Personnel Only</p>
        </div>
    </div>

</body>
</html>