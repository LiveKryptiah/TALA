<?php
// Secure variables just in case they aren't set in the parent file yet
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$myBrgy = isset($_SESSION['barangay_assigned']) ? $_SESSION['barangay_assigned'] : '';

// Get the current file name (e.g., 'dashboard.php') to auto-highlight the active menu button
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!-- COLLAPSIBLE MINI-SIDEBAR COMPONENT -->
<aside id="tala-sidebar" class="w-64 bg-green-900 text-white flex-shrink-0 flex flex-col shadow-xl z-40 h-screen absolute md:relative transition-all duration-300 ease-in-out -translate-x-full md:translate-x-0 md:ml-0 overflow-hidden">
    
    <!-- Branding -->
    <div class="h-20 flex flex-col justify-center px-5 border-b border-green-800">
        <div class="flex items-center">
            <img src="TALA-LOGO.ico" alt="TALA Logo" class="w-10 h-10 flex-shrink-0 object-contain">
            <div class="sidebar-text ml-4 transition-all duration-300 whitespace-nowrap">
                <h1 class="text-2xl font-bold tracking-wider">TALA</h1>
                <p class="text-[10px] text-green-300 uppercase tracking-widest mt-[-4px]">
                    <?= $isAdmin ? 'Admin Panel' : 'SK - ' . substr(htmlspecialchars($myBrgy), 0, 10) ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Navigation Links -->
    <nav class="flex-1 px-3 py-6 space-y-2 overflow-y-auto overflow-x-hidden [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">

        
        <p class="sidebar-text px-2 text-xs font-semibold text-green-400 uppercase tracking-wider mb-2 transition-all duration-300">Main Menu</p>
<!-- Add Alpine.js to your <head> or at the top of sidebar.php if not already included -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- Wrapper for the Sidebar and the Slide-Over Panel -->
<div x-data="{ notifyOpen: false }">

    <!-- ========================================== -->
    <!-- YOUR SIDEBAR MENU ITEM                     -->
   


        
        <a href="dashboard.php" title="Dashboard" class="flex items-center px-3 py-3 rounded-lg transition <?= $currentPage == 'dashboard.php' ? 'bg-green-800 text-white' : 'text-green-200 hover:bg-green-800 hover:text-white' ?>">
            <i class="ph ph-squares-four text-2xl min-w-[2rem] text-center"></i>
            <span class="sidebar-text ml-3 font-medium whitespace-nowrap transition-all duration-300">Dashboard</span>
        </a>
    
    
    
    <a href="admin_budget.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'admin_budget.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
    <i class="<?= $current_page == 'admin_budget.php' ? 'ph-fill' : 'ph-bold' ?> ph-wallet text-lg"></i> Budget Monitor
</a>
    
    
        
        <a href="youth_profiles.php" title="Youth Profiles" class="flex items-center px-3 py-3 rounded-lg transition <?= $currentPage == 'youth_profiles.php' ? 'bg-green-800 text-white' : 'text-green-200 hover:bg-green-800 hover:text-white' ?>">
            <i class="ph ph-users text-2xl min-w-[2rem] text-center"></i>
            <span class="sidebar-text ml-3 font-medium whitespace-nowrap transition-all duration-300">Youth Profiles</span>
        </a>
    
    <a href="department_monitor.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold transition-all text-slate-400 hover:text-white hover:bg-white/5">
    <i class="ph-bold ph-git-network text-lg"></i> Inter-Agency Monitor
</a>
    
        
        <a href="add_profile.php" title="Add Profile" class="flex items-center px-3 py-3 rounded-lg transition <?= $currentPage == 'add_profile.php' ? 'bg-green-800 text-white' : 'text-green-200 hover:bg-green-800 hover:text-white' ?>">
            <i class="ph ph-user-plus text-2xl min-w-[2rem] text-center"></i>
            <span class="sidebar-text ml-3 font-medium whitespace-nowrap transition-all duration-300">Add Profile</span>
        </a>
        
        <!-- Smart Event Menu: Checks for Admin/SK Role -->
        <a href="events.php" title="Events" class="flex items-center px-3 py-3 rounded-lg transition <?= ($currentPage == 'events.php' || $currentPage == 'manage_event.php') ? 'bg-green-800 text-white' : 'text-green-200 hover:bg-green-800 hover:text-white' ?>">
            <i class="ph ph-calendar-star text-2xl min-w-[2rem] text-center"></i>
            <span class="sidebar-text ml-3 font-medium whitespace-nowrap transition-all duration-300"><?= $isAdmin ? 'Manage Events' : 'View Events' ?></span>
        </a>

<!-- Admin Only Controls -->
            
 <!-- NEW: FEATURED HIGHLIGHT -->
            <a href="manage_highlight.php" title="Featured Highlight" class="flex items-center px-3 py-3 rounded-lg transition <?= $currentPage == 'manage_highlight.php' ? 'bg-green-800 text-white' : 'text-green-200 hover:bg-green-800 hover:text-white' ?>">
                <i class="ph ph-star text-2xl min-w-[2rem] text-center"></i>
                <span class="sidebar-text ml-3 font-medium whitespace-nowrap transition-all duration-300">Highlight Spotlight</span>
            </a>

        <?php if ($isAdmin): ?>
            <div class="pt-6 pb-2">
                <p class="sidebar-text px-2 text-xs font-semibold text-green-400 uppercase tracking-wider transition-all duration-300">Admin Controls</p>
            </div>
            
            <!-- NEW: Manage Announcements -->
            <a href="manage_announcements.php" title="Announcements" class="flex items-center px-3 py-3 rounded-lg transition <?= $currentPage == 'manage_announcements.php' ? 'bg-green-800 text-white' : 'text-green-200 hover:bg-green-800 hover:text-white' ?>">
                <i class="ph ph-megaphone text-2xl min-w-[2rem] text-center"></i>
                <span class="sidebar-text ml-3 font-medium whitespace-nowrap transition-all duration-300">Announcements</span>
            </a>

<!-- NEW: SCHOLARSHIPS -->
            <a href="manage_scholarships.php" title="Scholarships" class="flex items-center px-3 py-3 rounded-lg transition <?= $currentPage == 'manage_scholarships.php' ? 'bg-green-800 text-white' : 'text-green-200 hover:bg-green-800 hover:text-white' ?>">
                <i class="ph ph-graduation-cap text-2xl min-w-[2rem] text-center"></i>
                <span class="sidebar-text ml-3 font-medium whitespace-nowrap transition-all duration-300">Scholarships</span>
            </a>

            
            <a href="manage_sk.php" title="Manage SK Users" class="flex items-center px-3 py-3 rounded-lg transition <?= $currentPage == 'manage_sk.php' ? 'bg-green-800 text-white' : 'text-green-200 hover:bg-green-800 hover:text-white' ?>">
                <i class="ph ph-shield-check text-2xl min-w-[2rem] text-center"></i>
                <span class="sidebar-text ml-3 font-medium whitespace-nowrap transition-all duration-300">Manage SK Users</span>
            </a>
        <?php endif; ?>

<a href="manage_popup.php" title="Pop-up Alert" class="flex items-center px-3 py-3 rounded-lg transition <?= $currentPage == 'manage_popup.php' ? 'bg-green-800 text-white' : 'text-green-200 hover:bg-green-800 hover:text-white' ?>">
    <i class="ph ph-bell-ringing text-2xl min-w-[2rem] text-center"></i>
    <span class="sidebar-text ml-3 font-medium whitespace-nowrap transition-all duration-300">Pop-up Alert</span>
</a>
        
<!-- NEW: SUGGESTIONS INBOX -->
            <a href="manage_suggestions.php" title="Community Suggestions" class="flex items-center px-3 py-3 rounded-lg transition <?= $currentPage == 'manage_suggestions.php' ? 'bg-green-800 text-white' : 'text-green-200 hover:bg-green-800 hover:text-white' ?>">
                <i class="ph ph-envelope-open text-2xl min-w-[2rem] text-center"></i>
                <span class="sidebar-text ml-3 font-medium whitespace-nowrap transition-all duration-300">Suggestions</span>
            </a>

    </nav>



    <!-- User Profile & Logout -->
    <div class="p-4 border-t border-green-800 overflow-hidden">
        <a href="logout.php" title="Logout" class="flex items-center px-2 py-2 text-green-200 hover:text-white transition hover:bg-red-600 rounded-lg">
            <i class="ph ph-sign-out text-2xl min-w-[2rem] text-center"></i>
            <span class="sidebar-text ml-3 font-medium whitespace-nowrap transition-all duration-300">Logout</span>
        </a>
    </div>
</aside>