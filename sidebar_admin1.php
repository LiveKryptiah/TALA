<?php
// Ensure session variables are accessible
$current_page = basename($_SERVER['PHP_SELF']);
$admin_name = $_SESSION['admin_username'] ?? 'Administrator';
?>
<!-- ADMIN SIDEBAR (City-Wide Oversight) -->
<aside class="w-64 bg-white/80 backdrop-blur-xl border-r border-slate-200/60 fixed inset-y-0 left-0 z-50 flex flex-col shadow-[4px_0_24px_rgba(0,0,0,0.02)]">
    
   <!-- Branding -->
<div class="h-20 flex items-center px-6 border-b border-slate-100 mb-6">
    <a href="dashboard.php" class="flex items-center gap-3 group">
        
        <!-- Logo Image -->
   
        <img src="TALA-LOGO.ico" alt="TALA Logo" class="w-10 h-10 object-contain shadow-md rounded-xl group-hover:scale-105 transition-transform" />
        
        <div>
            <!-- Gradient Text Applied Here -->
            <h1 class="text-xl font-extrabold text-slate-900 tracking-tight leading-none">
                TALA <span class="text-transparent bg-clip-text bg-gradient-to-r from-green-500 to-yellow-400">OS</span>
            </h1>
            <span class="text-[9px] font-bold tracking-widest text-slate-400 uppercase">Admin Console</span>
        </div>
    </a>
</div>

   <!-- Navigation Links -->
<nav class="flex-1 px-4 space-y-1.5 overflow-y-auto custom-scrollbar pb-6">
    
    <p class="px-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 mt-4">Core Analytics</p>
    
    <a href="dashboard.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'dashboard.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
        <i class="<?= $current_page == 'dashboard.php' ? 'ph-fill' : 'ph-bold' ?> ph-squares-four text-lg"></i> Overview
    </a>

 <p class="px-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 mt-4">Administrative Monitoring</p>
    <a href="admin_mom.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'admin_mom.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
        <i class="<?= $current_page == 'admin_mom.php' ? 'ph-fill' : 'ph-bold' ?> ph-folders text-lg"></i> MOM Oversight
    </a>

        <a href="admin_resolution.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'admin_resolution.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
            <i class="<?= $current_page == 'admin_resolution.php' ? 'ph-fill' : 'ph-bold' ?> ph-scroll text-lg"></i> RESO Oversight
        </a>
    <a href="admin_budget.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'admin_budget.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
    <i class="<?= $current_page == 'admin_budget.php' ? 'ph-fill' : 'ph-bold' ?> ph-wallet text-lg"></i> Budget Monitor
</a>
<a href="admin_vouchers.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'admin_vouchers.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
    <i class="<?= $current_page == 'admin_vouchers.php' ? 'ph-fill' : 'ph-bold' ?> ph-receipt text-lg"></i> Withdrawal History
</a>
    <p class="px-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 mt-6">Programs & Events</p>

    <a href="events.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'events.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
        <i class="<?= $current_page == 'events.php' ? 'ph-fill' : 'ph-bold' ?> ph-calendar text-lg"></i> All Events
    </a>

    <a href="manage_scholarships.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'manage_scholarships.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
        <i class="<?= $current_page == 'manage_scholarships.php' ? 'ph-fill' : 'ph-bold' ?> ph-graduation-cap text-lg"></i> Scholarships
    </a>


    <a href="manage_announcements.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'manage_announcements.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
        <i class="<?= $current_page == 'manage_announcements.php' ? 'ph-fill' : 'ph-bold' ?> ph-megaphone text-lg"></i> Announcements
    </a>

    <a href="manage_highlights.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'manage_highlights.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
        <i class="<?= $current_page == 'manage_highlights.php' ? 'ph-fill' : 'ph-bold' ?> ph-sparkle text-lg"></i> Highlights
    </a>

    <a href="manage_popup.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'manage_popup.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
        <i class="<?= $current_page == 'manage_popup.php' ? 'ph-fill' : 'ph-bold' ?> ph-app-window text-lg"></i> Popup Modals
    </a>


 <p class="px-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 mt-6">Database & Users</p>

    <a href="youth_profiles.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'youth_profiles.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
        <i class="<?= $current_page == 'youth_profiles.php' ? 'ph-fill' : 'ph-bold' ?> ph-users text-lg"></i> Youth Profile
    </a>

    <a href="add_profile.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'add_profile.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
        <i class="<?= $current_page == 'add_profile.php' ? 'ph-fill' : 'ph-bold' ?> ph-user-plus text-lg"></i> Add Profile
    </a>
   

    <a href="manage_sk.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'manage_sk.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
        <i class="<?= $current_page == 'manage_sk.php' ? 'ph-fill' : 'ph-bold' ?> ph-buildings text-lg"></i> Manage SK Units
    </a>

  <a href="admin_bento.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'admin_bento.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
        <i class="<?= $current_page == 'admin_bento.php' ? 'ph-fill' : 'ph-bold' ?> ph-chart-polar text-lg"></i> Bento Analytics
    </a>

    <p class="px-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 mt-6">Engagement</p>

    <a href="manage_suggestions.php" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-bold transition-all <?= $current_page == 'manage_suggestions.php' ? 'bg-brand-50 text-brand-600 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800' ?>">
        <i class="<?= $current_page == 'manage_suggestions.php' ? 'ph-fill' : 'ph-bold' ?> ph-chat-circle-text text-lg"></i> Suggestions
    </a>

    <p class="px-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 mt-6">Upcoming Features</p>
<!-- PPA Approval Link for Admin -->
<a href="admin_ppa_approvals.php" class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-slate-100 hover:text-green-600 rounded-lg transition-all">
    <i class="ph-bold ph-check-square text-xl"></i>
    <span class="font-medium">PPA Approvals</span>
</a>

<!-- PPA Approval Link for Admin -->
<a href="send_notification.php" class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:bg-slate-100 hover:text-green-600 rounded-lg transition-all">
    <i class="ph-bold ph-check-square text-xl"></i>
    <span class="font-medium">Push Notification</span>
</a>




</nav>
    <!-- User Profile & Logout -->
    <div class="p-4 border-t border-slate-100">
        <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/60">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-brand-100 text-brand-600 rounded-full flex items-center justify-center font-bold border-2 border-white shadow-sm">
                    <?= strtoupper(substr($admin_name, 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <p class="text-sm font-bold text-slate-800 truncate"><?= htmlspecialchars($admin_name) ?></p>
                    <p class="text-[10px] font-bold text-brand-600 uppercase tracking-widest truncate">Municipal Admin</p>
                </div>
            </div>

            <a href="logout.php" class="flex items-center justify-center gap-2 w-full py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold text-red-600 hover:bg-red-50 hover:border-red-100 transition-colors shadow-sm">
                <i class="ph-bold ph-sign-out"></i> Terminate Session
            </a>
        </div>
    </div>
    
</aside>