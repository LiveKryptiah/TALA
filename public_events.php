<?php
require 'db_connect.php';

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $event_id = $_POST['event_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);

    $stmt = $pdo->prepare("SELECT youth_id FROM youth_profiles WHERE full_name = ? AND email = ? AND contact_number = ?");
    $stmt->execute([$full_name, $email, $contact]);
    $youth = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$youth) {
        $message = "Verification failed! Details do not match. Not registered yet? <a href='public_registration.php' class='underline font-bold hover:text-red-900 dark:hover:text-red-300'>Register here</a>.";
        $messageType = "red";
    } else {
        $stmtLimit = $pdo->prepare("SELECT participant_limit, (SELECT COUNT(*) FROM event_registrations WHERE event_id = ?) as current_count FROM events WHERE id = ?");
        $stmtLimit->execute([$event_id, $event_id]);
        $eventData = $stmtLimit->fetch(PDO::FETCH_ASSOC);

        if ($eventData['current_count'] >= $eventData['participant_limit']) {
            $message = "Sorry! This event has reached its maximum capacity.";
            $messageType = "amber";
        } else {
            try {
                $stmtReg = $pdo->prepare("INSERT INTO event_registrations (event_id, youth_id, registered_by) VALUES (?, ?, 'Self')");
                $stmtReg->execute([$event_id, $youth['youth_id']]);
                $message = "Success! You are officially registered for the event. See you there!";
                $messageType = "green";
            } catch (PDOException $e) {
                $message = "You are already registered for this event!";
                $messageType = "green";
            }
        }
    }
}

$events = $pdo->query("SELECT *, (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id) as current_count FROM events e WHERE event_date >= CURDATE() ORDER BY event_date ASC")->fetchAll(PDO::FETCH_ASSOC);

// Map message types to explicit Tailwind classes for CDN safety AND Dark Mode
$alertClasses = [
    'red' => 'bg-red-50 text-red-800 border-red-200 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/30',
    'amber' => 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30',
    'green' => 'bg-green-50 text-green-800 border-green-200 dark:bg-green-950/40 dark:text-green-300 dark:border-green-800/50',
    'blue' => 'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500/30'
];
$activeAlertClass = isset($alertClasses[$messageType]) ? $alertClasses[$messageType] : 'bg-gray-50 text-gray-800 border-gray-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700';
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upcoming Events | TALA Tumauini 2026</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">

    <!-- Fonts & Tailwind CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <!-- ENABLE DARK MODE IN TAILWIND CONFIG -->
    <script>
        tailwind.config = {
            darkMode: 'class', // <-- FIX
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
    
    <!-- ANTI-FLASH SCRIPT: Must be at the top of the <head>! -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    

    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        .btn-gradient {
            background: linear-gradient(135deg, #15803d 0%, #047857 50%, #ca8a04 100%);
            color: #ffffff;
        }
        .btn-gradient:hover {
            opacity: 0.95;
        }
        .dark .btn-gradient {
            background: linear-gradient(135deg, #166534 0%, #064e3b 50%, #a16207 100%);
        }

        .text-gradient,
        .text-gradient-green-yellow {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 30%, #eab308 100%) !important;
            -webkit-background-clip: text !important;
            background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            color: transparent !important;
            display: inline-block;
        }
        .icon-gradient,
        i.icon-gradient {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 30%, #eab308 100%) !important;
            -webkit-background-clip: text !important;
            background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            color: transparent !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            background-color: transparent !important;
        }
        html.dark .text-gradient,
        html.dark .text-gradient-green-yellow,
        .dark .text-gradient,
        .dark .text-gradient-green-yellow {
            background: linear-gradient(135deg, #22c55e 0%, #4ade80 35%, #facc15 100%) !important;
            -webkit-background-clip: text !important;
            background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            color: transparent !important;
        }
        html.dark .icon-gradient,
        html.dark i.icon-gradient,
        .dark .icon-gradient,
        .dark i.icon-gradient {
            background: linear-gradient(135deg, #22c55e 0%, #4ade80 35%, #facc15 100%) !important;
            -webkit-background-clip: text !important;
            background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            color: transparent !important;
            background-color: transparent !important;
        }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
    <link rel="stylesheet" href="public-design.css">
</head>
<body data-public-page="public_events.php" class="tala-public bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white transition-colors duration-300">

 <?php include 'navigation.php'; ?>

    <!-- CONTENT -->
    <main class="flex-1 max-w-5xl mx-auto px-6 lg:px-8 w-full pt-16 pb-20">
        
        <!-- Inline Page Header -->
        <div class="mb-10 text-left">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight transition-colors">
                <span class="text-gradient">Upcoming Events</span>
            </h1>
        </div>

        <!-- Alert Message Banner -->
        <?php if ($message): ?>
            <div class="<?= $activeAlertClass ?> p-5 mb-8 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between border font-semibold text-sm transition-colors">
                <div class="flex items-center gap-3">
                    <?php if($messageType == 'green'): ?>
                        <i class="ph-fill ph-check-circle icon-gradient text-2xl"></i>
                    <?php elseif($messageType == 'amber'): ?>
                        <i class="ph-fill ph-warning-circle icon-gradient text-2xl"></i>
                    <?php else: ?>
                        <i class="ph-fill ph-info icon-gradient text-2xl"></i>
                    <?php endif; ?>
                    <span><?= $message ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Events List -->
        <div class="space-y-8">
            <?php foreach ($events as $ev): ?>
                <div class="bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden flex flex-col md:flex-row transition-all duration-300 tala-public-card">
                    
                    <!-- Event Info Section -->
                    <div class="p-8 md:p-10 md:w-1/2 bg-slate-50/50 dark:bg-zinc-900/50 border-b md:border-b-0 md:border-r border-slate-100 dark:border-zinc-800 flex flex-col justify-center transition-colors">
                        <div>
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold text-brand-700 dark:text-brand-400 uppercase tracking-widest mb-4 bg-brand-50 dark:bg-brand-500/10 border border-brand-100 dark:border-brand-500/20 px-3.5 py-1.5 rounded-full transition-colors">
                                <i class="ph-fill ph-calendar-blank text-sm icon-gradient"></i> <?= date('l, F j, Y', strtotime($ev['event_date'])) ?>
                            </span>
                            <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white mb-4 leading-snug transition-colors"><?= htmlspecialchars($ev['title']) ?></h2>
                            <p class="text-slate-600 dark:text-zinc-400 mb-8 text-sm sm:text-base leading-relaxed whitespace-pre-line transition-colors"><?= htmlspecialchars($ev['details']) ?></p>
                        </div>
                        
                        <div class="mt-auto inline-flex items-center gap-2 text-sm font-bold text-brand-800 dark:text-brand-300 bg-white dark:bg-zinc-800 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 w-max transition-colors">
                            <i class="ph-fill ph-users-three text-lg icon-gradient"></i> 
                            <?= $ev['current_count'] ?> <span class="text-slate-400 dark:text-zinc-500 font-medium mx-1">/</span> <?= $ev['participant_limit'] ?> Slots Taken
                        </div>
                    </div>

                    <!-- Verification Form Section -->
                    <div class="p-8 md:p-10 md:w-1/2 flex flex-col justify-center bg-white dark:bg-zinc-900 transition-colors">
                        <div class="mb-6 flex items-center gap-3 border-b border-slate-100 dark:border-zinc-800 pb-4 transition-colors">
                            <div class="w-10 h-10 flex items-center justify-center text-2xl transition-colors">
                                <i class="ph-bold ph-identification-badge icon-gradient text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-extrabold text-slate-900 dark:text-white transition-colors">Verify & Register</h3>
                                <p class="text-xs font-semibold text-slate-500 dark:text-zinc-400 transition-colors">Enter exactly as registered in your TALA Profile</p>
                            </div>
                        </div>

                        <?php if ($ev['current_count'] >= $ev['participant_limit']): ?>
                            <div class="bg-slate-50 dark:bg-zinc-950/50 text-slate-500 dark:text-zinc-500 font-bold text-center py-8 rounded-2xl border-2 border-dashed border-slate-200 dark:border-zinc-800 flex flex-col items-center gap-2 transition-colors tala-public-card">
                                <i class="ph-fill ph-prohibit text-4xl text-slate-300 dark:text-zinc-700"></i>
                                <span>EVENT FULL</span>
                            </div>
                        <?php else: ?>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                                
                                <div class="relative">
                                    <i class="ph ph-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 text-lg transition-colors"></i>
                                    <input type="text" name="full_name" placeholder="Full Name" required 
                                           class="w-full pl-12 pr-4 py-3.5 border border-slate-200 dark:border-zinc-700 rounded-xl text-sm font-medium bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-zinc-500 focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 outline-none transition-all">
                                </div>
                                
                                <div class="relative">
                                    <i class="ph ph-envelope-simple absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 text-lg transition-colors"></i>
                                    <input type="email" name="email" placeholder="Registered Email" required 
                                           class="w-full pl-12 pr-4 py-3.5 border border-slate-200 dark:border-zinc-700 rounded-xl text-sm font-medium bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-zinc-500 focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 outline-none transition-all">
                                </div>
                                
                                <div class="relative">
                                    <i class="ph ph-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 text-lg transition-colors"></i>
                                    <input type="tel" name="contact" placeholder="Registered Contact Number" required 
                                           class="w-full pl-12 pr-4 py-3.5 border border-slate-200 dark:border-zinc-700 rounded-xl text-sm font-medium bg-slate-50 dark:bg-zinc-950 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-zinc-500 focus:bg-white dark:focus:bg-zinc-900 focus:ring-2 focus:ring-brand-600 focus:border-brand-600 outline-none transition-all">
                                </div>
                                
                                <button type="submit" class="w-full bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-4 rounded-xl transform hover:-translate-y-0.5 transition-all mt-4 flex items-center justify-center gap-2">
                                    Confirm Registration <i class="ph-bold ph-arrow-right"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Empty State -->
            <?php if(empty($events)): ?>
                <div class="text-center py-20 bg-white dark:bg-zinc-900 rounded-[2rem] border border-slate-200 dark:border-zinc-800 border-dashed transition-colors tala-public-card">
                    <div class="w-20 h-20 flex items-center justify-center mx-auto mb-4 text-5xl transition-colors">
                        <i class="ph-fill ph-calendar-x icon-gradient"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-1 transition-colors">No upcoming events right now</h3>
                    <p class="text-slate-500 dark:text-zinc-400 text-sm transition-colors">Please check back later for new programs and activities.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'public_footer.php'; ?>

    <!-- Scripts -->
    <script>
        // Synchronize Dark Mode on page load to prevent flashing
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // Script for Mobile Menu (if used by navigation.php)
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