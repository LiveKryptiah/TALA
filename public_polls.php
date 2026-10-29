<?php
// public_polls.php - Public Pulse of the Youth
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'db_connect.php';

// Auto-create necessary tables just in case public visits before admin does
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `polls` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `question` VARCHAR(255) NOT NULL,
        `barangay` VARCHAR(255) NOT NULL,
        `status` ENUM('active', 'closed') DEFAULT 'active',
        `created_by` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS `poll_options` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `poll_id` INT NOT NULL,
        `option_text` VARCHAR(255) NOT NULL,
        `votes_count` INT DEFAULT 0,
        `FOREIGN KEY` (`poll_id`) REFERENCES `polls`(`id`) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `poll_votes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `poll_id` INT NOT NULL,
        `youth_id` INT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `FOREIGN KEY` (`poll_id`) REFERENCES `polls`(`id`) ON DELETE CASCADE
    )");
} catch (PDOException $e) {}

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $poll_id = $_POST['poll_id'];
    $option_id = $_POST['option_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);

    // Verify Youth Identity
    $stmt = $pdo->prepare("SELECT youth_id FROM youth_profiles WHERE full_name = ? AND email = ? AND contact_number = ?");
    $stmt->execute([$full_name, $email, $contact]);
    $youth = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$youth) {
        $message = "Verification failed! Details do not match. <a href='public_registration.php' class='underline hover:text-red-900 dark:hover:text-red-300'>Register here</a>.";
        $messageType = "red";
    } else {
        // Check if already voted
        $stmtVote = $pdo->prepare("SELECT id FROM poll_votes WHERE poll_id = ? AND youth_id = ?");
        $stmtVote->execute([$poll_id, $youth['youth_id']]);
        
        if ($stmtVote->fetch()) {
            $message = "You have already cast your vote for this poll!";
            $messageType = "amber";
        } else {
            // Record Vote
            $pdo->prepare("INSERT INTO poll_votes (poll_id, youth_id) VALUES (?, ?)")->execute([$poll_id, $youth['youth_id']]);
            $pdo->prepare("UPDATE poll_options SET votes_count = votes_count + 1 WHERE id = ?")->execute([$option_id]);
            
            $message = "Vote recorded! Thank you for making your voice heard.";
            $messageType = "green";
        }
    }
}

// Fetch Active Polls with their options
$polls = $pdo->query("SELECT * FROM polls WHERE status = 'active' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Map message types for light and dark modes
$alertClasses = [
    'red' => 'bg-red-50 text-red-800 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/30',
    'amber' => 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30',
    'green' => 'bg-green-50 text-green-800 border-green-200 dark:bg-green-950/40 dark:text-green-300 dark:border-green-800/50'
];
$activeAlertClass = isset($alertClasses[$messageType]) ? $alertClasses[$messageType] : '';
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pulse of the Youth | TALA Tumauini</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    
    <!-- ANTI-FLASH SCRIPT: Must be at the top of the <head>! -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    
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
        
        /* Custom Radio Button Styling */
        input[type="radio"]:checked + label { border-color: #16a34a; background-color: #f0fdf4; color: #14532d; }
        
        /* Dark Mode Custom Radio Button Styling */
        .dark input[type="radio"]:checked + label { border-color: #16a34a; background-color: rgba(22, 163, 74, 0.1); color: #4ade80; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white transition-colors duration-300">

    <?php include 'navigation.php'; ?>

    <main class="flex-1 w-full pt-16 pb-24 max-w-4xl mx-auto px-6 lg:px-8">
        
        <div class="mb-10 text-center">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight mb-3 transition-colors">Pulse of the Youth</h1>
            <p class="text-slate-600 dark:text-zinc-400 text-base sm:text-lg font-medium transition-colors">Have your say in the local planning process. Verify your youth profile to vote.</p>
        </div>

        <?php if ($message): ?>
            <div class="<?= $activeAlertClass ?> p-5 mb-8 rounded-2xl flex flex-col sm:flex-row items-center justify-center gap-3 border font-semibold text-sm transition-colors">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="space-y-8">
            <?php if(empty($polls)): ?>
                <div class="text-center py-20 bg-white dark:bg-zinc-900 rounded-[2rem] border border-slate-200 dark:border-zinc-800 border-dashed transition-colors">
                    <i class="ph-fill ph-check-square-offset text-5xl text-slate-300 dark:text-zinc-600 mb-4 inline-block transition-colors"></i>
                    <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-1 transition-colors">No active polls right now</h3>
                    <p class="text-slate-500 dark:text-zinc-400 text-sm transition-colors">Check back later when the SK requires community feedback.</p>
                </div>
            <?php else: ?>
                <?php foreach($polls as $poll): 
                    $options = $pdo->query("SELECT * FROM poll_options WHERE poll_id = {$poll['id']}")->fetchAll(PDO::FETCH_ASSOC);
                ?>
                    <div class="bg-white dark:bg-zinc-900 p-8 md:p-10 rounded-[2rem] transition-colors">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-[10px] font-extrabold uppercase tracking-widest mb-4 transition-colors">
                            <i class="ph-fill ph-map-pin"></i> <?= htmlspecialchars($poll['barangay']) ?>
                        </span>
                        
                        <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white mb-6 leading-snug transition-colors"><?= htmlspecialchars($poll['question']) ?></h2>
                        
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">
                            
                            <!-- Options -->
                            <div class="space-y-3">
                                <?php foreach($options as $opt): ?>
                                    <div class="relative">
                                        <input type="radio" name="option_id" id="opt_<?= $opt['id'] ?>" value="<?= $opt['id'] ?>" required class="peer sr-only">
                                        <label for="opt_<?= $opt['id'] ?>" class="block w-full p-4 border-2 border-slate-200 dark:border-zinc-700 rounded-xl cursor-pointer peer-hover:border-brand-300 dark:peer-hover:border-brand-600 transition-all font-bold text-slate-600 dark:text-zinc-300">
                                            <?= htmlspecialchars($opt['option_text']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Verification -->
                            <div class="pt-6 border-t border-slate-100 dark:border-zinc-800/80 transition-colors">
                                <p class="text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest mb-4 transition-colors">Verify Identity to Vote</p>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <input type="text" name="full_name" placeholder="Registered Name" required 
                                           class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 outline-none text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-zinc-500 transition-all">
                                    <input type="email" name="email" placeholder="Registered Email" required 
                                           class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 outline-none text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-zinc-500 transition-all">
                                    <input type="tel" name="contact" placeholder="Contact Number" required 
                                           class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 rounded-xl px-4 py-3 text-sm font-medium focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 outline-none text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-zinc-500 transition-all">
                                </div>
                            </div>

                            <button type="submit" class="w-full btn-gradient font-bold py-4 rounded-xl flex items-center justify-center gap-2 mt-4">
                                <i class="ph-bold ph-check-square-offset text-xl"></i> Cast My Vote
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white dark:bg-zinc-950 border-t border-slate-200 dark:border-zinc-800 py-10 px-6 lg:px-8 mt-auto transition-colors duration-300">
        <div class="max-w-7xl mx-auto text-center text-xs font-semibold text-slate-500 dark:text-zinc-500 transition-colors">
            &copy; <?= date('Y') ?> Tumauini Municipal Youth Development Office.
        </div>
    </footer>

    <!-- Dark Mode Sync Script -->
    <script>
        // Synchronize Dark Mode on page load to prevent flashing
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</body>
</html>