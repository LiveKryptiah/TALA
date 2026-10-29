<?php
// Read the uploads directory
$base_dir = 'uploads/governance/';
$folders = [];

if (is_dir($base_dir)) {
    $folders = array_filter(glob($base_dir . '*'), 'is_dir');
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Governance Document Portal | TALA OS</title>
    
    <!-- Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind Config to match your brand colors AND ENABLE DARK MODE -->
    <script>
        tailwind.config = { 
            darkMode: 'class', // <-- FIX: Enabled class-based dark mode
            theme: { 
                extend: { 
                    colors: { 
                        brand: { 
                            50: '#f0fdf4', 
                            100: '#dcfce7', 
                            500: '#22c55e', 
                            700: '#15803d', 
                            800: '#166534', 
                            900: '#14532d' 
                        } 
                    }, 
                    fontFamily: { 
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'] 
                    } 
                } 
            } 
        }
    </script>
    
    <style>
        /* Light Mode Custom Styles */
        body { background-color: #f8fafc; transition: background-color 0.3s ease, color 0.3s ease; }
        .hero-pattern { background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 24px 24px; }
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.8); }
        .icon-gradient { background: linear-gradient(135deg, #15803d 0%, #047857 30%, #ca8a04 80%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        
        /* Dark Mode Custom Styles */
        .dark body { background-color: #09090b; }
        .dark .hero-pattern { background-image: radial-gradient(#27272a 1px, transparent 1px); }
        .dark .glass-card { background: rgba(24, 24, 27, 0.7); border: 1px solid rgba(63, 63, 70, 0.5); }

        /* Custom Scrollbar for file lists */
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
    </style>
</head>
<body class="text-slate-800 dark:text-zinc-100 bg-slate-50 dark:bg-zinc-950 antialiased selection:bg-brand-500 selection:text-white flex flex-col min-h-screen transition-colors duration-300">

    <!-- DYNAMIC NAVIGATION BAR INCLUDED HERE -->
    <?php include 'navigation.php'; ?>

    <!-- HERO SECTION -->
    <div class="bg-white dark:bg-zinc-950 border-b border-slate-200 dark:border-zinc-800 relative overflow-hidden hero-pattern transition-colors duration-300">
        <!-- Adjusted gradients to switch between light and dark naturally -->
        <div class="absolute inset-0 bg-gradient-to-b from-white/60 to-[#f8fafc] dark:from-zinc-950/80 dark:to-zinc-950 transition-colors duration-300"></div>
        <div class="max-w-[1200px] mx-auto px-6 py-20 relative z-10 text-center">
            <h1 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white tracking-tight mb-4 transition-colors">Official Public Documents</h1>
            <p class="text-slate-500 dark:text-zinc-400 font-medium max-w-xl mx-auto transition-colors">Access and download municipal ordinances, executive orders, public notices, and resolutions directly from the local government unit.</p>
        </div>
    </div>

    <!-- DOCUMENT EXPLORER -->
    <main class="flex-1 max-w-[1200px] w-full mx-auto px-6 py-12">
        <?php if(empty($folders)): ?>
            <div class="text-center py-24 text-slate-400 dark:text-zinc-500 glass-card rounded-[2rem] transition-colors">
                <i class="ph-fill ph-folder-open text-6xl mb-4 opacity-50 icon-gradient"></i>
                <h2 class="text-xl font-extrabold text-slate-700 dark:text-zinc-300 mb-2 transition-colors">No Documents Available</h2>
                <p class="text-sm font-medium">The municipal administration has not uploaded any public files yet.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach($folders as $folder): 
                    $folder_name = basename($folder);
                    $files = array_filter(glob($folder . '/*'), 'is_file');
                ?>
                    <div class="glass-card rounded-[2rem] overflow-hidden flex flex-col transition-all duration-300 group">
                        
                        <!-- Folder Header -->
                        <div class="bg-slate-50 dark:bg-zinc-900/50 p-5 border-b border-slate-100 dark:border-zinc-800/50 flex items-center gap-4 transition-colors">
                            <div class="w-12 h-12 bg-white dark:bg-zinc-800 rounded-xl border border-slate-200 dark:border-zinc-700 flex items-center justify-center shrink-0 transition-colors group-hover:border-amber-400/50 dark:group-hover:border-amber-500/30">
                                <i class="ph-fill ph-folder text-amber-400 text-2xl group-hover:scale-110 transition-transform duration-300"></i>
                            </div>
                            <div class="overflow-hidden">
                                <h3 class="font-extrabold text-slate-900 dark:text-white text-lg truncate transition-colors" title="<?= htmlspecialchars($folder_name) ?>">
                                    <?= htmlspecialchars($folder_name) ?>
                                </h3>
                                <p class="text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest transition-colors"><?= count($files) ?> Documents</p>
                            </div>
                        </div>
                        
                        <!-- File List -->
                        <div class="p-2 flex-1 bg-white dark:bg-zinc-900/80 transition-colors">
                            <?php if(empty($files)): ?>
                                <div class="py-8 text-center text-slate-400 dark:text-zinc-500 text-xs font-bold transition-colors">Empty Folder</div>
                            <?php else: ?>
                                <ul class="divide-y divide-slate-50 dark:divide-zinc-800/50 max-h-64 overflow-y-auto custom-scrollbar transition-colors">
                                    <?php foreach($files as $file): 
                                        // Clean up the filename for display
                                        $display_name = basename($file);
                                        $display_name = preg_replace('/^[0-9]+_/', '', $display_name); 
                                    ?>
                                        <li>
                                            <a href="<?= htmlspecialchars($file) ?>" target="_blank" class="flex items-center gap-3 p-3 hover:bg-slate-50 dark:hover:bg-zinc-800/50 rounded-xl transition-colors file-link-group">
                                                <i class="ph-fill ph-file-pdf text-rose-500 text-xl shrink-0 file-link-group-hover:scale-110 transition-transform"></i>
                                                <span class="text-sm font-semibold text-slate-700 dark:text-zinc-300 file-link-group-hover:text-brand-600 dark:file-link-group-hover:text-brand-400 truncate flex-1 transition-colors" title="<?= htmlspecialchars($display_name) ?>">
                                                    <?= htmlspecialchars($display_name) ?>
                                                </span>
                                                <i class="ph-bold ph-download-simple text-slate-300 dark:text-zinc-600 file-link-group-hover:text-brand-600 dark:file-link-group-hover:text-brand-400 shrink-0 transition-colors"></i>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- FOOTER -->
    <footer class="bg-white dark:bg-zinc-950 border-t border-slate-200 dark:border-zinc-800 py-8 mt-12 transition-colors duration-300">
        <div class="max-w-[1200px] mx-auto px-6 text-center">
            <p class="text-sm font-bold text-slate-400 dark:text-zinc-500 transition-colors">© <?= date('Y') ?> TALA OS. All rights reserved.</p>
        </div>
    </footer>

    <!-- Dark Mode Sync Script -->
    <script>
        // Check localStorage immediately to prevent flash of white background
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // Keep pseudo hover states working smoothly
        document.querySelectorAll('.file-link-group').forEach(link => {
            link.addEventListener('mouseenter', () => link.classList.add('file-link-group-hover'));
            link.addEventListener('mouseleave', () => link.classList.remove('file-link-group-hover'));
        });
    </script>
</body>
</html>