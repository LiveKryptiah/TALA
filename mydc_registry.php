<?php
// mydc_registry.php - TALA Public Youth Website
session_start();
require 'db_connect.php';

// Fetch Active MYDC Members from Database
try {
    $stmt = $pdo->prepare("SELECT * FROM tala_mydc_registry WHERE status = 'Active' ORDER BY full_name ASC");
    $stmt->execute();
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $members = [];
}

$totalMembers = count($members);
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MYDC Registry | TALA Youth Portal</title>
    
    <!-- ANTI-FLASH SCRIPT: Must be at the top of the <head>! -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- ENABLE DARK MODE IN TAILWIND CONFIG -->
    <script>
        tailwind.config = {
            darkMode: 'class', 
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; transition: background-color 0.3s ease, color 0.3s ease; }
    </style>
</head>
<body class="bg-white dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 min-h-screen flex flex-col selection:bg-green-500 selection:text-white transition-colors duration-300">

    <!-- PUBLIC NAVIGATION HEADER (Uncompromised) -->
    <?php include 'navigation.php'; ?>

    <!-- HERO BANNER -->
    <section class="bg-gradient-to-b from-green-50/60 via-yellow-50/20 to-white dark:from-zinc-900 dark:via-zinc-950 dark:to-zinc-950 py-14 px-6 border-b border-slate-100 dark:border-zinc-800 transition-colors duration-300">
        <div class="max-w-7xl mx-auto text-center">
            
            <h1 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white tracking-tight mb-3 transition-colors">
                Municipal Youth Development Council
            </h1>
            
            <p class="text-slate-600 dark:text-zinc-400 max-w-2xl mx-auto font-medium text-base leading-relaxed transition-colors">
                Meet the active representatives and accredited youth leaders serving the Katipunan ng Kabataan across Tumauini.
            </p>

            <!-- REAL-TIME SEARCH BAR -->
            <div class="mt-8 max-w-xl mx-auto relative">
                <i class="ph-bold ph-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 dark:text-zinc-500 text-xl transition-colors"></i>
                <input type="text" id="searchInput" onkeyup="filterRegistry()" placeholder="Search by name, position, organization, or barangay..." 
                       class="w-full pl-12 pr-4 py-4 rounded-2xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 focus:border-green-600 dark:focus:border-green-500 focus:ring-4 focus:ring-green-100 dark:focus:ring-green-900/30 outline-none font-semibold text-slate-800 dark:text-white transition placeholder-slate-400 dark:placeholder-zinc-500">
            </div>
        </div>
    </section>

    <!-- MAIN REGISTRY CONTENT -->
    <main class="max-w-7xl mx-auto px-6 py-12 flex-1 w-full">
        
        <!-- Directory Header -->
        <div class="flex justify-between items-center mb-8 pb-4 border-b border-slate-100 dark:border-zinc-800 transition-colors">
            <h2 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2 transition-colors">
                <i class="ph-fill ph-users-three text-green-600 dark:text-green-500"></i> MYDC Members Directory 
                <span class="bg-green-100 dark:bg-green-500/10 text-green-800 dark:text-green-400 text-xs px-3 py-1 rounded-full font-black transition-colors"><?= $totalMembers ?> Active</span>
            </h2>
        </div>

        <!-- MEMBER CARDS GRID -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6" id="registryGrid">
            <?php if(empty($members)): ?>
                <div class="col-span-full text-center py-20 border-2 border-dashed border-slate-200 dark:border-zinc-800 rounded-3xl bg-slate-50/50 dark:bg-zinc-900/50 transition-colors">
                    <i class="ph-fill ph-users text-5xl text-slate-300 dark:text-zinc-600 mb-3 inline-block transition-colors"></i>
                    <p class="text-slate-500 dark:text-zinc-400 font-bold text-base transition-colors">No active MYDC members registered yet.</p>
                </div>
            <?php else: foreach($members as $m): ?>
                
                <div class="member-card bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 hover:border-green-500 dark:hover:border-green-500 rounded-2xl p-6 transition-all duration-300 flex flex-col justify-between group">
                    <div>
                        <!-- Avatar & Info -->
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-16 h-16 rounded-2xl overflow-hidden bg-green-50 dark:bg-zinc-800 border border-green-100 dark:border-zinc-700 flex items-center justify-center shrink-0 transition-colors">
                                <?php if(!empty($m['photo_path']) && file_exists($m['photo_path'])): ?>
                                    <img src="<?= htmlspecialchars($m['photo_path']) ?>" alt="Member Photo" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span class="text-2xl font-black text-green-700 dark:text-green-500 transition-colors"><?= strtoupper(substr($m['full_name'], 0, 1)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3 class="text-lg font-black text-slate-900 dark:text-white group-hover:text-green-600 dark:group-hover:text-green-400 transition-colors card-name leading-tight">
                                    <?= htmlspecialchars($m['full_name']) ?>
                                </h3>
                                <p class="text-xs font-black text-green-600 dark:text-green-500 uppercase tracking-wider mt-1 card-position transition-colors">
                                    <?= htmlspecialchars($m['position']) ?>
                                </p>
                            </div>
                        </div>

                        <!-- Organization & Location -->
                        <div class="space-y-2 text-xs font-semibold text-slate-600 dark:text-zinc-400 border-t border-slate-100 dark:border-zinc-800/80 pt-4 mt-auto transition-colors">
                            <p class="flex items-start gap-2 card-org">
                                <i class="ph-fill ph-buildings text-yellow-500 mt-0.5"></i> 
                                <span class="text-slate-800 dark:text-zinc-300 font-bold leading-snug transition-colors"><?= htmlspecialchars($m['organization']) ?></span>
                            </p>
                            <p class="flex items-center gap-2 card-brgy">
                                <i class="ph-fill ph-map-pin text-slate-400 dark:text-zinc-500 transition-colors"></i> 
                                <span>Brgy. <?= htmlspecialchars($m['barangay']) ?></span>
                            </p>
                        </div>
                    </div>
                </div>

            <?php endforeach; endif; ?>
        </div>
    </main>

    <!-- FOOTER -->
    <footer class="border-t border-slate-100 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-950 py-10 text-center text-xs font-bold text-slate-400 dark:text-zinc-500 mt-auto transition-colors duration-300">
        <div class="max-w-7xl mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-4">
            <p>© <?= date('Y') ?> TALA Youth Portal — Municipal Youth Development Council</p>
            <div class="flex items-center gap-6 text-slate-500 dark:text-zinc-500">
                <a href="#" class="hover:text-green-600 dark:hover:text-green-400 transition-colors">Privacy Policy</a>
                <a href="#" class="hover:text-green-600 dark:hover:text-green-400 transition-colors">Terms of Service</a>
                <a href="#" class="hover:text-green-600 dark:hover:text-green-400 transition-colors">Help Desk</a>
            </div>
        </div>
    </footer>

    <!-- REAL-TIME FILTER SCRIPT -->
    <script>
        function filterRegistry() {
            let query = document.getElementById('searchInput').value.toLowerCase();
            let cards = document.querySelectorAll('.member-card');

            cards.forEach(card => {
                let name = card.querySelector('.card-name').innerText.toLowerCase();
                let position = card.querySelector('.card-position').innerText.toLowerCase();
                let org = card.querySelector('.card-org').innerText.toLowerCase();
                let brgy = card.querySelector('.card-brgy').innerText.toLowerCase();

                if (name.includes(query) || position.includes(query) || org.includes(query) || brgy.includes(query)) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        }
    </script>
</body>
</html>