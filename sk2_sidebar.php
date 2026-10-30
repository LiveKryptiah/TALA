<?php 
// Get the current file name so we can highlight the active menu item
$current_page = basename($_SERVER['PHP_SELF']); 
?>
<!-- sk2_sidebar.php -->
<aside class="w-64 fixed inset-y-0 left-0 bg-white dark:bg-zinc-900 border-r border-slate-200 dark:border-zinc-800 z-50 flex flex-col h-screen overflow-hidden transition-colors duration-300">
    
    <!-- Logo Area -->
    <div class="h-20 flex items-center px-6 border-b border-slate-200 dark:border-zinc-800 shrink-0 gap-3 bg-white dark:bg-zinc-900 transition-colors duration-300">
        <img src="TALA-LOGO.ico" alt="TALA Logo" class="w-8 h-8 object-contain">
        <div>
            <h2 class="text-lg font-black text-slate-900 dark:text-white leading-none tracking-tight transition-colors">TALA OS</h2>
            <span class="text-[9px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-widest transition-colors">Command Center</span>
        </div>
    </div>

    <!-- Navigation Links -->
    <div class="flex-1 overflow-y-auto py-6 px-4 space-y-1 bg-white dark:bg-zinc-900 transition-colors duration-300" style="scrollbar-width: none;">
        
        <p class="px-2 text-[10px] font-black text-slate-400 dark:text-zinc-500 uppercase tracking-widest mb-3 transition-colors">Main Menu</p>
        
        <a href="sk2_dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors <?= $current_page == 'sk2_dashboard.php' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="ph-squares-four text-xl <?= $current_page == 'sk2_dashboard.php' ? 'ph-fill text-blue-600 dark:text-blue-400' : 'ph-bold text-slate-400 dark:text-zinc-500' ?> transition-colors"></i> Dashboard
        </a>
        
        <!-- NEW: Youth Registry -->
        <a href="sk2_youth.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors <?= $current_page == 'sk2_youth.php' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="ph-users text-xl <?= $current_page == 'sk2_youth.php' ? 'ph-fill text-blue-600 dark:text-blue-400' : 'ph-bold text-slate-400 dark:text-zinc-500' ?> transition-colors"></i> Youth Registry
        </a>

        <a href="sk2_documents.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors <?= $current_page == 'sk2_documents.php' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="ph-folders text-xl <?= $current_page == 'sk2_documents.php' ? 'ph-fill text-blue-600 dark:text-blue-400' : 'ph-bold text-slate-400 dark:text-zinc-500' ?> transition-colors"></i> Documents (DTS)
        </a>
        
        <a href="sk2_services.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors <?= $current_page == 'sk2_services.php' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="ph-warning-circle text-xl <?= $current_page == 'sk2_services.php' ? 'ph-fill text-blue-600 dark:text-blue-400' : 'ph-bold text-slate-400 dark:text-zinc-500' ?> transition-colors"></i> E-Sumbong
        </a>

        <!-- NEW: Asset Tracker -->
        <a href="sk2_assets.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors <?= $current_page == 'sk2_assets.php' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="ph-cube text-xl <?= $current_page == 'sk2_assets.php' ? 'ph-fill text-blue-600 dark:text-blue-400' : 'ph-bold text-slate-400 dark:text-zinc-500' ?> transition-colors"></i> Asset Tracker
        </a>

        <div class="my-5 border-t border-slate-100 dark:border-zinc-800 transition-colors"></div>
        
        <p class="px-2 text-[10px] font-black text-slate-400 dark:text-zinc-500 uppercase tracking-widest mb-3 transition-colors">9 Centers</p>
        
        <a href="sk2_assembly.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors <?= $current_page == 'sk2_assembly.php' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="ph-users-four text-xl <?= $current_page == 'sk2_assembly.php' ? 'ph-fill text-blue-600 dark:text-blue-400' : 'ph-bold text-slate-400 dark:text-zinc-500' ?> transition-colors"></i> KK Assembly
        </a>
        <a href="sk2_civic.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors <?= $current_page == 'sk2_civic.php' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="ph-hand-heart text-xl <?= $current_page == 'sk2_civic.php' ? 'ph-fill text-blue-600 dark:text-blue-400' : 'ph-bold text-slate-400 dark:text-zinc-500' ?> transition-colors"></i> Civic Programs
        </a>
        <a href="sk2_eco.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors <?= $current_page == 'sk2_eco.php' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="ph-briefcase text-xl <?= $current_page == 'sk2_eco.php' ? 'ph-fill text-blue-600 dark:text-blue-400' : 'ph-bold text-slate-400 dark:text-zinc-500' ?> transition-colors"></i> Economic
        </a>
        <a href="sk2_culture.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors <?= $current_page == 'sk2_culture.php' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="ph-palette text-xl <?= $current_page == 'sk2_culture.php' ? 'ph-fill text-blue-600 dark:text-blue-400' : 'ph-bold text-slate-400 dark:text-zinc-500' ?> transition-colors"></i> Culture & Arts
        </a>
        <a href="sk2_rights.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors <?= $current_page == 'sk2_rights.php' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="ph-scales text-xl <?= $current_page == 'sk2_rights.php' ? 'ph-fill text-blue-600 dark:text-blue-400' : 'ph-bold text-slate-400 dark:text-zinc-500' ?> transition-colors"></i> Youth Rights
        </a>
        <a href="sk2_safety.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors <?= $current_page == 'sk2_safety.php' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="ph-shield-plus text-xl <?= $current_page == 'sk2_safety.php' ? 'ph-fill text-blue-600 dark:text-blue-400' : 'ph-bold text-slate-400 dark:text-zinc-500' ?> transition-colors"></i> Safety & Rescue
        </a>
        <a href="sk2_integrations.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors <?= $current_page == 'sk2_integrations.php' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="ph-plugs-connected text-xl <?= $current_page == 'sk2_integrations.php' ? 'ph-fill text-blue-600 dark:text-blue-400' : 'ph-bold text-slate-400 dark:text-zinc-500' ?> transition-colors"></i> Integrations
        </a>
        <a href="sk2_automation.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-bold transition-colors <?= $current_page == 'sk2_automation.php' ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400' : 'text-slate-600 dark:text-zinc-400 hover:bg-slate-50 dark:hover:bg-zinc-800/50 hover:text-slate-900 dark:hover:text-white' ?>">
            <i class="ph-robot text-xl <?= $current_page == 'sk2_automation.php' ? 'ph-fill text-blue-600 dark:text-blue-400' : 'ph-bold text-slate-400 dark:text-zinc-500' ?> transition-colors"></i> Automations
        </a>
        
    </div>

    <!-- Bottom User Area -->
    <div class="p-4 border-t border-slate-200 dark:border-zinc-800 shrink-0 bg-white dark:bg-zinc-900 transition-colors duration-300">
        
    

        <a href="logout.php" class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 text-red-600 dark:text-red-500 font-bold hover:bg-red-50 dark:hover:bg-red-500/10 hover:border-red-200 dark:hover:border-red-500/30 transition-colors shadow-sm">
            <i class="ph-bold ph-sign-out text-lg"></i> Sign Out
        </a>
    </div>
</aside>

<!-- Theme Toggle Script -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const themeToggleBtn = document.getElementById('theme-toggle');
        const themeToggleCircle = themeToggleBtn.querySelector('span:last-child');

        function updateToggleState() {
            if (document.documentElement.classList.contains('dark')) {
                themeToggleBtn.classList.remove('bg-slate-300');
                themeToggleBtn.classList.add('bg-blue-600');
                themeToggleCircle.classList.remove('translate-x-1');
                themeToggleCircle.classList.add('translate-x-5');
            } else {
                themeToggleBtn.classList.remove('bg-blue-600');
                themeToggleBtn.classList.add('bg-slate-300');
                themeToggleCircle.classList.remove('translate-x-5');
                themeToggleCircle.classList.add('translate-x-1');
            }
        }

        // Initialize state on load
        updateToggleState();

        themeToggleBtn.addEventListener('click', function() {
            document.documentElement.classList.toggle('dark');
            
            // Save to localStorage
            if (document.documentElement.classList.contains('dark')) {
                localStorage.theme = 'dark';
            } else {
                localStorage.theme = 'light';
            }
            
            // Update button UI
            updateToggleState();
        });
    });
</script>