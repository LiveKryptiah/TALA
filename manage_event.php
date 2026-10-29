<?php
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$isAdmin = ($_SESSION['role'] === 'admin');

// Validate Event ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: events.php");
    exit();
}

$event_id = $_GET['id'];

// Fetch Event Details
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    die("Event not found.");
}

// Fetch Registrations (Adjust column names based on your actual table structure)
// Assuming a table `event_registrations` with `youth_name`, `contact`, `registered_at`
try {
    $roster_stmt = $pdo->prepare("SELECT * FROM event_registrations WHERE event_id = ? ORDER BY registered_at DESC");
    $roster_stmt->execute([$event_id]);
    $roster = $roster_stmt->fetchAll(PDO::FETCH_ASSOC);
    $registered_count = count($roster);
} catch (PDOException $e) {
    // Fallback if table structure is different
    $roster = [];
    $registered_count = 0;
}

$displayName = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : (isset($_SESSION['sk_username']) ? $_SESSION['sk_username'] : 'User');
$displayInitial = strtoupper(substr($displayName, 0, 1));
$isCompleted = ($event['status'] ?? '') === 'Completed';
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($event['title']) ?> | Event Roster</title>
    
    <!-- ANTI-FLASH SCRIPT -->
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
    
    <!-- TAILWIND CONFIG -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { brand: { 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d' } },
                    boxShadow: { 'soft': '0 20px 40px -15px rgba(0,0,0,0.05)' }
                }
            }
        }
    </script>
    
    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; -webkit-tap-highlight-color: transparent; }
        
        /* Ambient Light Mode Background Animations */
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .ambient-bg { position: fixed; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; transition: background-color 0.3s ease; }
        .blob { position: absolute; filter: blur(100px); opacity: 0.6; border-radius: 50%; mix-blend-multiply: true; animation: blob 10s infinite alternate ease-in-out; transition: background-color 0.3s ease; }
        .dark .blob { mix-blend-multiply: normal; mix-blend-mode: screen; opacity: 0.15; }
        .animation-delay-2000 { animation-delay: 2s; }
        
        .glass-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.8); transition: all 0.3s ease; }
        .dark .glass-card { background: rgba(24, 24, 27, 0.85); border: 1px solid rgba(63, 63, 70, 0.5); }
        
        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        
        .btn-gradient {
            background: linear-gradient(135deg, #15803d 0%, #047857 60%, #ca8a04 100%);
            color: #ffffff;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }
        .btn-gradient:hover { opacity: 0.9; }
        .btn-gradient:active { transform: scale(0.95); }
        .dark .btn-gradient { background: linear-gradient(135deg, #166534 0%, #064e3b 60%, #a16207 100%); }

        .tap-effect:active { transform: scale(0.98); opacity: 0.8; transition: transform 0.1s; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex h-screen overflow-hidden selection:bg-[#1da85b] selection:text-white transition-colors duration-300">

    <!-- AMBIENT BACKGROUND -->
    <div class="ambient-bg bg-slate-50 dark:bg-zinc-950">
        <div class="blob bg-brand-200 dark:bg-brand-800 w-[500px] h-[500px] -top-[10%] -left-[10%]"></div>
        <div class="blob bg-blue-200 dark:bg-blue-800 w-[500px] h-[500px] bottom-[10%] -right-[10%] animation-delay-2000"></div>
    </div>

    <!-- SIDEBAR -->
    <?php 
    if ($isAdmin) { include 'sidebar_admin.php'; } 
    else { include 'sidebar_sk.php'; } 
    ?>

    <!-- MAIN CONTENT - Adjusted to exactly match the 1400px architecture -->
    <main class="md:ml-64 pt-20 md:pt-8 px-4 md:px-8 pb-24 md:pb-16 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 overflow-y-auto">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1400px -->
        <div class="max-w-[1400px] mx-auto w-full space-y-6 md:space-y-8">
            
            <!-- HEADER -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center pb-4 border-b border-slate-200 dark:border-zinc-800 gap-4 transition-colors">
                <div class="flex items-center gap-3 md:gap-4">
                    <a href="admin_calendar.php" class="w-8 h-8 md:w-10 md:h-10 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-lg md:rounded-xl flex items-center justify-center text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors shadow-sm tap-effect">
                        <i class="ph-bold ph-arrow-left text-sm md:text-base"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight transition-colors">Event Roster</h1>
                        <p class="text-[10px] md:text-sm font-semibold text-slate-500 dark:text-zinc-400 mt-0.5 md:mt-1 uppercase tracking-widest transition-colors">Participant Ledger & details</p>
                    </div>
                </div>
                
                <div class="flex flex-wrap gap-2 md:gap-3 w-full md:w-auto">
                    <!-- CRUD Controls for Admins -->
                    <?php if ($isAdmin): ?>
                        <button class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-zinc-300 px-3 md:px-4 py-2 md:py-2.5 rounded-lg md:rounded-xl text-[10px] md:text-sm font-bold shadow-sm hover:bg-slate-50 dark:hover:bg-zinc-800 transition-colors flex items-center justify-center gap-1.5 md:gap-2 tap-effect flex-1 md:flex-none">
                            <i class="ph-bold ph-pencil-simple text-sm md:text-base"></i> <span class="hidden sm:inline">Edit</span> Event
                        </button>
                        <button class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 text-red-600 dark:text-red-400 px-3 md:px-4 py-2 md:py-2.5 rounded-lg md:rounded-xl text-[10px] md:text-sm font-bold shadow-sm hover:bg-red-100 dark:hover:bg-red-500/20 transition-colors flex items-center justify-center gap-1.5 md:gap-2 tap-effect">
                            <i class="ph-bold ph-trash text-sm md:text-base"></i>
                        </button>
                    <?php endif; ?>
                    
                    <button onclick="window.print()" class="bg-slate-900 dark:bg-white text-white dark:text-zinc-900 px-3 md:px-4 py-2 md:py-2.5 rounded-lg md:rounded-xl text-[10px] md:text-sm font-bold shadow-md hover:bg-slate-800 dark:hover:bg-zinc-200 transition-colors flex items-center justify-center gap-1.5 md:gap-2 tap-effect flex-1 md:flex-none">
                        <i class="ph-bold ph-printer text-sm md:text-base"></i> Print <span class="hidden sm:inline">Ledger</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">
                
                <!-- LEFT COLUMN: EVENT DETAILS -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="glass-card rounded-2xl md:rounded-[2rem] shadow-soft overflow-hidden border border-slate-200 dark:border-zinc-800 transition-colors flex flex-col">
                        
                        <!-- Event Image -->
                        <div class="h-40 md:h-48 relative overflow-hidden bg-slate-100 dark:bg-zinc-900 border-b border-slate-200 dark:border-zinc-800 transition-colors">
                            <div class="absolute top-4 right-4 z-10">
                                <?php if ($isCompleted): ?>
                                    <span class="bg-white/90 dark:bg-zinc-900/90 backdrop-blur text-blue-600 dark:text-blue-400 text-[9px] md:text-[10px] font-extrabold px-3 py-1.5 rounded-full uppercase tracking-widest shadow-sm flex items-center gap-1 transition-colors"><i class="ph-fill ph-check-circle text-sm"></i> Completed</span>
                                <?php else: ?>
                                    <span class="bg-[#1da85b]/90 dark:bg-[#1da85b]/20 backdrop-blur text-white dark:text-[#4ade80] text-[9px] md:text-[10px] font-extrabold px-3 py-1.5 rounded-full uppercase tracking-widest shadow-sm border dark:border-[#1da85b]/30 flex items-center gap-1 transition-colors"><i class="ph-fill ph-calendar-star text-sm"></i> Upcoming</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($event['image_path'])): ?>
                                <img src="<?= htmlspecialchars($event['image_path']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-slate-800 to-slate-900 dark:from-zinc-800 dark:to-zinc-900 text-white">
                                    <i class="ph-fill ph-image text-4xl md:text-5xl mb-2 text-slate-600 dark:text-zinc-600 transition-colors"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Event Details -->
                        <div class="p-5 md:p-8 flex flex-col flex-1 bg-white/50 dark:bg-zinc-900/50 transition-colors">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-[9px] md:text-[10px] font-extrabold text-[#1da85b] dark:text-[#4ade80] bg-[#1da85b]/10 dark:bg-[#1da85b]/10 px-2.5 py-1 rounded-md uppercase tracking-widest border border-[#1da85b]/20 dark:border-[#1da85b]/20 flex items-center gap-1.5 transition-colors">
                                    <i class="ph-bold ph-clock text-xs md:text-sm"></i> <?= date('F j, Y \a\t g:i A', strtotime($event['event_date'])) ?>
                                </span>
                            </div>
                            
                            <h3 class="text-lg md:text-xl font-extrabold text-slate-900 dark:text-white mb-1.5 md:mb-2 leading-tight transition-colors"><?= htmlspecialchars($event['title']) ?></h3>
                            
                            <p class="text-[11px] md:text-xs font-semibold text-slate-500 dark:text-zinc-400 mb-5 md:mb-6 flex items-center gap-1.5 transition-colors">
                                <i class="ph-fill ph-map-pin text-slate-400 dark:text-zinc-500 text-sm md:text-base"></i> <?= htmlspecialchars($event['location']) ?>
                            </p>

                            <!-- Progress Bar -->
                            <div class="mt-auto pt-4 md:pt-5 border-t border-slate-100 dark:border-zinc-800 transition-colors">
                                <div class="flex justify-between items-end text-[11px] md:text-xs font-bold text-slate-600 dark:text-zinc-300 mb-2 transition-colors">
                                    <span class="uppercase tracking-widest text-[8px] md:text-[9px] text-slate-400 dark:text-zinc-500">Roster Capacity</span>
                                    <?php $limit = $event['participant_limit'] ?? 100; ?>
                                    <span class="<?= $registered_count >= $limit ? 'text-red-500 dark:text-red-400' : 'text-slate-700 dark:text-zinc-200' ?> transition-colors"><?= $registered_count ?> <span class="text-slate-400 dark:text-zinc-500">/ <?= $limit ?></span></span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-zinc-800 rounded-full h-1.5 md:h-2 mb-1.5 md:mb-2 overflow-hidden transition-colors">
                                    <?php 
                                        $pct = min(100, ($registered_count / $limit) * 100); 
                                        $barColor = $isCompleted ? 'bg-blue-500' : ($pct >= 100 ? 'bg-red-500' : 'bg-[#1da85b]');
                                    ?>
                                    <div class="<?= $barColor ?> h-full rounded-full transition-all duration-1000" style="width: <?= $pct ?>%"></div>
                                </div>
                                <?php if ($pct >= 100 && !$isCompleted): ?>
                                    <p class="text-[9px] md:text-[10px] text-red-500 font-bold uppercase tracking-widest"><i class="ph-bold ph-warning"></i> Event is Full</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: ROSTER TABLE -->
                <div class="lg:col-span-2">
                    <div class="glass-card rounded-2xl md:rounded-[2rem] shadow-soft border border-slate-200 dark:border-zinc-800 h-full flex flex-col transition-colors overflow-hidden">
                        
                        <div class="p-5 md:p-8 border-b border-slate-100 dark:border-zinc-800/80 bg-white/50 dark:bg-zinc-900/50 flex justify-between items-center transition-colors">
                            <h2 class="text-lg md:text-xl font-extrabold text-slate-800 dark:text-white transition-colors">Registered Participants</h2>
                            
                            <?php if (!$isCompleted): ?>
                                <button class="btn-gradient text-white px-3 md:px-4 py-2 rounded-lg md:rounded-xl text-[10px] md:text-xs font-bold shadow-md flex items-center gap-1.5 md:gap-2 tap-effect">
                                    <i class="ph-bold ph-user-plus text-sm md:text-base"></i> <span class="hidden sm:inline">Add User</span>
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="flex-1 overflow-x-auto custom-scrollbar bg-white/30 dark:bg-zinc-950/30 transition-colors">
                            <table class="w-full text-left border-collapse min-w-[500px]">
                                <thead>
                                    <tr class="text-[9px] md:text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-100 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900/50 transition-colors">
                                        <th class="py-3 px-4 md:py-4 md:px-6">Participant Name</th>
                                        <th class="py-3 px-4 md:py-4 md:px-6">Registration Date</th>
                                        <th class="py-3 px-4 md:py-4 md:px-6 text-center">Status</th>
                                        <th class="py-3 px-4 md:py-4 md:px-6 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="text-xs md:text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50 transition-colors">
                                    <?php if ($registered_count > 0): ?>
                                        <?php foreach ($roster as $person): ?>
                                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors group">
                                            <td class="py-3 px-4 md:py-4 md:px-6 font-bold text-slate-800 dark:text-zinc-200 transition-colors">
                                                <?= htmlspecialchars($person['youth_name'] ?? 'Unknown User') ?>
                                            </td>
                                            <td class="py-3 px-4 md:py-4 md:px-6 text-[10px] md:text-xs text-slate-500 dark:text-zinc-400 transition-colors">
                                                <?= isset($person['registered_at']) ? date('M j, Y h:i A', strtotime($person['registered_at'])) : 'N/A' ?>
                                            </td>
                                            <td class="py-3 px-4 md:py-4 md:px-6 text-center">
                                                <span class="inline-block px-2 md:px-2.5 py-0.5 md:py-1 text-[8px] md:text-[9px] font-extrabold uppercase tracking-widest rounded bg-[#1da85b]/10 text-[#1da85b] dark:bg-[#1da85b]/20 dark:text-[#4ade80] transition-colors border border-[#1da85b]/20">
                                                    Registered
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 md:py-4 md:px-6 text-right whitespace-nowrap">
                                                <button class="w-7 h-7 md:w-8 md:h-8 inline-flex items-center justify-center rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 hover:bg-red-50 dark:hover:bg-red-500/10 hover:text-red-600 dark:hover:text-red-400 transition-colors tap-effect tooltip" title="Remove Registration">
                                                    <i class="ph-bold ph-trash text-sm md:text-base"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="py-12 md:py-16 text-center text-slate-400 dark:text-zinc-500 transition-colors">
                                                <i class="ph-fill ph-users-three text-4xl md:text-5xl mb-3 md:mb-4 opacity-50 block"></i>
                                                <p class="font-semibold text-base md:text-lg text-slate-500 dark:text-zinc-400 transition-colors">No participants registered yet.</p>
                                                <p class="text-[10px] md:text-xs font-medium mt-1">Once users sign up via the public portal, they will appear here.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                    </div>
                </div>

            </div>
        </div>
    </main>
</body>
</html>