<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (($_SESSION['admin_logged_in'] ?? false) !== true || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit();
}
require_once 'db_connect.php';
require_once 'sk_account_service.php';
$account_notice = $_SESSION['sk_account_notice'] ?? '';
unset($_SESSION['sk_account_notice']);
$all_barangays = [
    "Annafunan", "Antagan I", "Antagan II", "Arcon", "Balug", "Banig", "Bantug",
    "Barangay District 1 (Poblacion)", "Barangay District 2 (Poblacion)", "Barangay District 3 (Poblacion)",
    "Barangay District 4 (Poblacion)", "Bayabo East", "Caligayan", "Camasi", "Carpentero",
    "Compania", "Cumabao", "Fermeldy (Hacienda San Francisco)", "Fugu Abajo", "Fugu Norte",
    "Fugu Sur", "Lalauanan", "Lanna", "Lapogan", "Lingaling (Poblacion)", "Liwanag",
    "Malamag East", "Malamag West", "Maligaya (Poblacion)", "Minanga", "Moldero", "Namnama",
    "Paragu", "Pilitan", "San Mateo", "San Pedro", "San Vicente", "Santa", "Santa Catalina",
    "Santa Visitacion (Maggayu)", "Santo Niño", "Sinippil", "Sisim Abajo", "Sisim Alto", "Tunggui", "Ugad"
];
// Municipal administrators can directly provision approved chairperson accounts.
if (empty($_SESSION['sk_account_csrf'])) {
    $_SESSION['sk_account_csrf'] = bin2hex(random_bytes(32));
}
$create_errors = [];
$create_values = ['full_name' => '', 'barangay' => '', 'username' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = createSkChairpersonAccount($pdo, $_POST, $_SESSION, $all_barangays);
    if ($result['created']) {
        $_SESSION['sk_account_notice'] = 'SK Chairperson account created for ' . $result['values']['full_name'] . '. The account can now sign in.';
        $_SESSION['sk_account_csrf'] = bin2hex(random_bytes(32));
        header('Location: admin_sk_accounts.php');
        exit();
    }
    $create_errors = $result['errors'];
    $create_values = $result['values'];
}

?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SK Chairperson Accounts | TALA OS</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">

    <!-- Anti-Flash Theme Script -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
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

        /* Ultra-Clean Modern Cards: ZERO Outside Borders & ZERO Drop Shadows (Matching manage_disclosure.php) */
        .glass-card {
            background: #ffffff !important;
            border: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
            transition: all 0.2s ease;
        }
        .dark .glass-card {
            background: #18181b !important;
            border: none !important;
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

        /* Subtle Below-Left (Bottom-Left) Green Ambient Gradient for Section Cards */
        .card-green-glow-bl {
            position: relative;
            overflow: hidden;
        }
        .card-green-glow-bl::before {
            content: '';
            position: absolute;
            bottom: -10px;
            left: -10px;
            width: 72px;
            height: 72px;
            background: radial-gradient(circle at 25% 75%, rgba(34, 197, 94, 0.22) 0%, rgba(34, 197, 94, 0.08) 45%, transparent 75%);
            pointer-events: none;
            border-radius: 9999px;
            filter: blur(6px);
            z-index: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        .dark .card-green-glow-bl::before {
            background: radial-gradient(circle at 25% 75%, rgba(34, 197, 94, 0.28) 0%, rgba(34, 197, 94, 0.1) 45%, transparent 75%);
        }
        .card-green-glow-bl:hover::before {
            transform: scale(1.15);
            opacity: 1;
        }

        /* Subtle Right-Side Light Yellow Gradient Effect for Green Buttons */
        .btn-yellow-glow-r,
        button.bg-\[\#15803d\],
        button.bg-\[\#16a34a\],
        a.bg-\[\#15803d\] {
            position: relative;
            overflow: hidden;
        }
        .btn-yellow-glow-r::before,
        button.bg-\[\#15803d\]::before,
        button.bg-\[\#16a34a\]::before,
        a.bg-\[\#15803d\]::before {
            content: '';
            position: absolute;
            top: -6px;
            right: -6px;
            width: 38px;
            height: calc(100% + 12px);
            background: radial-gradient(circle at 85% 50%, rgba(254, 240, 138, 0.50) 0%, rgba(250, 204, 21, 0.20) 45%, transparent 75%);
            pointer-events: none;
            border-radius: inherit;
            filter: blur(3px);
            z-index: 1;
            transition: transform 0.25s ease, opacity 0.25s ease;
        }
        .btn-yellow-glow-r:hover::before,
        button.bg-\[\#15803d\]:hover::before,
        button.bg-\[\#16a34a\]:hover::before,
        a.bg-\[\#15803d\]:hover::before {
            transform: scale(1.15);
            opacity: 1;
        }

        /* Remove all outline / outside strokelines on focus */
        input:focus,
        input:focus-visible,
        input:active,
        select:focus,
        select:focus-visible,
        select:active,
        textarea:focus,
        textarea:focus-visible,
        textarea:active,
        button:focus,
        button:focus-visible,
        a:focus {
            outline: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }

        /* Remove all box shadows globally */
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

        /* Suppress conflicting floating theme toggle from sidebar */
        #globalThemeToggle {
            display: none !important;
        }

        @media print {
            body { background: #fff !important; color: #000 !important; }
            #tala-admin-sidebar, #tala-bulletproof-sk-sidebar, nav, #headerThemeToggle, .no-print { display: none !important; }
            main { margin-left: 0 !important; padding: 0 !important; }
        }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen overflow-x-hidden selection:bg-[#15803d] selection:text-white transition-colors duration-300">
    <?php include 'sidebar_admin.php'; ?>
    <main class="md:ml-64 pt-20 md:pt-5 px-3 md:px-6 pb-28 md:pb-6 flex-1 w-full min-w-0">
        <div class="max-w-[1600px] mx-auto w-full space-y-5">
            <header class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <button type="button" onclick="toggleMobileMenu()" class="md:hidden glass-card w-9 h-9 rounded-xl flex items-center justify-center" aria-label="Toggle navigation"><i class="ph-bold ph-list"></i></button>
                    <div>
                        <h1 class="text-xl md:text-3xl font-black tracking-tight text-slate-900 dark:text-white">SK Chairperson Accounts</h1>
                        <p class="text-xs md:text-sm text-slate-500 dark:text-zinc-400 mt-1">Create sign-in credentials and assign barangay access.</p>
                    </div>
                </div>
                <button type="button" onclick="toggleTheme()" id="headerThemeToggle" class="glass-card w-10 h-10 rounded-xl flex items-center justify-center text-slate-500 dark:text-zinc-400 flex-shrink-0" aria-label="Toggle theme"><i class="ph-bold ph-moon dark:hidden"></i><i class="ph-bold ph-sun hidden dark:inline text-amber-400"></i></button>
            </header>
            <?php if ($account_notice): ?>
                <div role="status" class="rounded-2xl px-4 py-3 bg-green-50 dark:bg-green-500/10 text-[#15803d] dark:text-[#4ade80] text-sm font-semibold flex gap-2 items-center"><i class="ph-bold ph-check-circle"></i><?= htmlspecialchars($account_notice, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 items-start">
                <section class="glass-card rounded-3xl overflow-hidden xl:col-span-2" aria-labelledby="createSkAccountTitle">
                    <header class="p-5 sm:p-6 flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-green-500/10 text-[#15803d] dark:text-[#4ade80] flex items-center justify-center flex-shrink-0"><i class="ph-bold ph-user-plus text-xl"></i></div>
                        <div><h2 id="createSkAccountTitle" class="font-extrabold text-base sm:text-lg text-slate-900 dark:text-white">Create Chairperson Account</h2><p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">Enter the official’s details and choose a password.</p></div>
                    </header>
            <form id="createSkAccountForm" method="POST" action="admin_sk_accounts.php" class="px-5 pb-5 sm:px-6 sm:pb-6 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['sk_account_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($create_errors): ?>
                    <div role="alert" class="rounded-2xl px-4 py-3 text-xs font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-800 dark:text-amber-300">
                        <ul class="list-disc pl-4 space-y-1">
                            <?php foreach ($create_errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label for="skAccountFullName" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">Full Name *</label>
                        <input type="text" id="skAccountFullName" name="full_name" maxlength="150" required autocomplete="name" value="<?= htmlspecialchars($create_values['full_name'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Chairperson’s full name" class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-sm font-semibold text-slate-900 dark:text-white border-0">
                    </div>
                    <div>
                        <label for="skAccountBarangay" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">Assigned Barangay *</label>
                        <select id="skAccountBarangay" name="barangay" required class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-sm font-semibold text-slate-900 dark:text-white border-0">
                            <option value="">Choose a barangay</option>
                            <?php foreach ($all_barangays as $barangay): ?>
                                <option value="<?= htmlspecialchars($barangay, ENT_QUOTES, 'UTF-8') ?>" <?= $create_values['barangay'] === $barangay ? 'selected' : '' ?>><?= htmlspecialchars($barangay === 'Banig' ? 'Banig/Dy Abra' : $barangay, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="skAccountPosition" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">Position</label>
                        <input type="text" id="skAccountPosition" value="SK Chairperson" readonly class="w-full px-3 py-2.5 rounded-xl bg-green-500/10 text-sm font-bold text-[#15803d] dark:text-[#4ade80] border-0">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="skAccountUsername" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">Username *</label>
                        <input type="text" id="skAccountUsername" name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9._\-]{3,50}" required autocomplete="off" spellcheck="false" value="<?= htmlspecialchars($create_values['username'], ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g. sk.annafunan" aria-describedby="skUsernameHint" class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-sm font-semibold text-slate-900 dark:text-white border-0">
                        <p id="skUsernameHint" class="text-[10px] text-slate-500 dark:text-zinc-400 mt-1">3–50 letters, numbers, periods, underscores, or hyphens.</p>
                    </div>
                    <div>
                        <label for="skAccountPassword" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">Password *</label>
                        <input type="password" id="skAccountPassword" name="password" minlength="8" maxlength="72" required autocomplete="new-password" placeholder="At least 8 characters" class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-sm font-semibold text-slate-900 dark:text-white border-0">
                    </div>
                    <div>
                        <label for="skAccountConfirmPassword" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-zinc-400 mb-1.5">Confirm Password *</label>
                        <input type="password" id="skAccountConfirmPassword" name="confirm_password" minlength="8" maxlength="72" required autocomplete="new-password" placeholder="Re-enter the password" class="w-full px-3 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-sm font-semibold text-slate-900 dark:text-white border-0">
                    </div>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="inline-flex items-center gap-1.5 text-[10px] font-bold text-[#15803d] dark:text-[#4ade80]"><i class="ph-bold ph-shield-check"></i>Approved for SK portal access</span>
                    <button type="button" id="toggleSkAccountPasswords" aria-pressed="false" class="text-xs font-bold text-slate-500 dark:text-zinc-400 hover:text-[#15803d] dark:hover:text-[#4ade80]">Show passwords</button>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <a href="tala_dashboard.php" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-xs font-bold text-slate-600 dark:text-zinc-300">Cancel</a>
                    <button type="submit" class="btn-yellow-glow-r px-4 py-2.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white text-xs font-bold flex items-center gap-2"><i class="ph-bold ph-user-plus relative z-10"></i><span class="relative z-10">Create Account</span></button>
                </div>
            </form>
                </section>
                <aside class="space-y-4">
                    <section class="glass-card rounded-3xl p-5 sm:p-6">
                        <div class="w-11 h-11 rounded-2xl bg-green-500/10 text-[#15803d] dark:text-[#4ade80] flex items-center justify-center mb-4"><i class="ph-bold ph-shield-check text-xl"></i></div>
                        <h2 class="font-extrabold text-base text-slate-900 dark:text-white">SK portal access</h2>
                        <dl class="mt-4 space-y-4 text-xs">
                            <div><dt class="font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500 text-[10px]">Position</dt><dd class="font-bold mt-1">SK Chairperson</dd></div>
                            <div><dt class="font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500 text-[10px]">Coverage</dt><dd class="mt-1 text-slate-600 dark:text-zinc-300">Assigned barangay’s SK workspace</dd></div>
                            <div><dt class="font-bold uppercase tracking-wider text-slate-400 dark:text-zinc-500 text-[10px]">Account status</dt><dd class="mt-1 text-[#15803d] dark:text-[#4ade80] font-bold">Approved when created</dd></div>
                        </dl>
                        <a href="admin_approval.php?status=approved" class="mt-5 inline-flex items-center gap-2 text-xs font-bold text-[#15803d] dark:text-[#4ade80] hover:underline">View accounts in SK Tracker <i class="ph-bold ph-arrow-right"></i></a>
                    </section>
                </aside>
            </div>
        </div>
    </main>
    <script src="admin-sk-account.js"></script>
    <script>
        function toggleTheme() {
            const dark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', dark ? 'dark' : 'light');
        }
    </script>
</body>
</html>
