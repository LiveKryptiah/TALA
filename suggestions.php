<?php
require 'db_connect.php';
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = !empty(trim($_POST['name'])) ? trim($_POST['name']) : 'Anonymous';
    $contact = !empty(trim($_POST['contact'])) ? trim($_POST['contact']) : null;
    $category = $_POST['category'];
    $message = trim($_POST['message']);

    $stmt = $pdo->prepare("INSERT INTO suggestions (sender_name, contact_info, category, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $contact, $category, $message]);
    $success = true;
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Voice | TALA Tumauini 2026</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    
    <!-- ANTI-FLASH SCRIPT: Must be at the top of the <head>! -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    

    <!-- Fonts & Tailwind CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <!-- ENABLE DARK MODE IN TAILWIND CONFIG -->
    <script>
        tailwind.config = {
            darkMode: 'class', // <-- FIX: Enables class-based dark mode
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; }
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
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
    <link rel="stylesheet" href="public-design.css">
</head>
<body data-public-page="suggestions.php" class="tala-public bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white transition-colors duration-300">

 <?php include 'navigation.php'; ?>

    <!-- CONTENT -->
    <main class="flex-1 w-full pt-16 pb-24 max-w-3xl mx-auto px-6 lg:px-8">
        
        <!-- Inline Page Header -->
        <div class="mb-10 text-center sm:text-left">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight mb-3 transition-colors">Community Voice</h1>
            <p class="text-slate-600 dark:text-zinc-400 text-base sm:text-lg transition-colors">Have an idea? See an issue? Submit your concerns directly to the MYDO and SK Officials.</p>
        </div>
        
        <?php if ($success): ?>
            <!-- Dark Mode supported Success Screen -->
            <div class="bg-green-50 dark:bg-green-950/40 border border-green-200 dark:border-green-800/50 text-green-800 dark:text-green-300 p-10 md:p-12 rounded-[2rem] text-center transition-colors">
                <div class="w-20 h-20 bg-green-500/10 text-green-600 dark:text-green-500 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 transition-colors"><i class="ph-fill ph-check-circle"></i></div>
                <h3 class="text-2xl font-extrabold mb-2 text-slate-900 dark:text-white transition-colors">Message Sent Successfully!</h3>
                <p class="font-medium text-slate-600 dark:text-zinc-300 mb-8 text-lg transition-colors">Thank you for your valuable feedback. Our youth officials will review it shortly.</p>
                <!-- Using window.location.pathname allows it to work whether the file is suggestions.php, contact.php, etc. -->
                <a href="<?= basename($_SERVER['PHP_SELF']) ?>" class="inline-flex items-center gap-2 text-sm font-bold btn-gradient px-8 py-3.5 rounded-xl transition">
                    <i class="ph-bold ph-arrow-u-up-left"></i> Submit Another
                </a>
            </div>
        <?php else: ?>
            <div class="bg-white dark:bg-zinc-900 p-8 md:p-10 rounded-[2rem] transition-colors tala-public-card">
                <form method="POST" class="space-y-6">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Full Name <span class="text-slate-400 dark:text-zinc-500 font-medium normal-case tracking-normal">(Optional)</span></label>
                            <input type="text" name="name" placeholder="Leave blank to remain anonymous" class="w-full border border-slate-200 dark:border-zinc-700 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-800 dark:text-white focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 outline-none bg-slate-50 dark:bg-zinc-950 transition-all placeholder:font-normal placeholder:text-slate-400 dark:placeholder:text-zinc-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Contact / Email <span class="text-slate-400 dark:text-zinc-500 font-medium normal-case tracking-normal">(Optional)</span></label>
                            <input type="text" name="contact" placeholder="So we can reply to you" class="w-full border border-slate-200 dark:border-zinc-700 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-800 dark:text-white focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 outline-none bg-slate-50 dark:bg-zinc-950 transition-all placeholder:font-normal placeholder:text-slate-400 dark:placeholder:text-zinc-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Category <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <select name="category" required class="w-full border border-slate-200 dark:border-zinc-700 rounded-xl pl-4 pr-10 py-3.5 text-sm font-medium text-slate-800 dark:text-white focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 outline-none bg-slate-50 dark:bg-zinc-950 transition-all cursor-pointer appearance-none">
                                <option value="" disabled selected>Select the type of feedback...</option>
                                <option value="Concern">General Concern / Inquiry</option>
                                <option value="Project Suggestion">Project / Event Suggestion</option>
                                <option value="Community Issue">Report a Community Issue</option>
                            </select>
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 pointer-events-none transition-colors"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Your Message <span class="text-red-500">*</span></label>
                        <textarea name="message" rows="5" required placeholder="Please provide specific details..." class="w-full border border-slate-200 dark:border-zinc-700 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-800 dark:text-white focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 outline-none bg-slate-50 dark:bg-zinc-950 transition-all placeholder:font-normal placeholder:text-slate-400 dark:placeholder:text-zinc-500 resize-none"></textarea>
                    </div>

                    <button type="submit" class="w-full btn-gradient font-bold py-4 rounded-xl flex items-center justify-center gap-2 mt-4">
                        <i class="ph-bold ph-paper-plane-tilt text-lg"></i> Submit to MYDO
                    </button>
                </form>
            </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <?php include 'public_footer.php'; ?>

    <!-- Script for Mobile Menu & Dark Mode Sync -->
    <script>
        // Synchronize Dark Mode on page load to prevent flashing
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');

        if(mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }
    </script>
</body>
</html>