<?php 
// sidebar_sk.php - TALA OS Standardized SK Navigation Sidebar
// Standardized to TALA OS Design System (Reference: sk_voucher.php & sk_mom.php)
$current_page = basename($_SERVER['PHP_SELF']);
$sk_name = $_SESSION['admin_full_name'] ?? $_SESSION['admin_username'] ?? 'SK Official';
$sk_photo = $_SESSION['admin_profile_picture'] ?? '';
// Refresh the current official's portrait, including sessions opened before a roster import.
if (!empty($_SESSION['admin_id']) && isset($pdo)) {
    try {
        $profile_stmt = $pdo->prepare('SELECT full_name, profile_picture FROM admins WHERE id = ?');
        $profile_stmt->execute([(int)$_SESSION['admin_id']]);
        $profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            $sk_name = $profile['full_name'] ?: $sk_name;
            $sk_photo = $profile['profile_picture'] ?? '';
            $_SESSION['admin_full_name'] = $sk_name;
            $_SESSION['admin_profile_picture'] = $sk_photo;
        }
    } catch (PDOException $exception) {}
}
if (!is_string($sk_photo) || strpos($sk_photo, 'uploads/sk_profiles/') !== 0 || strpos($sk_photo, '..') !== false) $sk_photo = '';
$barangay = $_SESSION['barangay_assigned'] ?? 'Barangay';
$barangay_label = $barangay === 'Banig' ? 'Banig/Dy Abra' : $barangay;
$position = $_SESSION['sk_position'] ?? 'Official';
?>

<style>
    /* 
     ======================================================================
     BULLETPROOF SK SIDEBAR CSS ISOLATION
     ======================================================================
    */

    /* 1. Global Reset (EXCLUDING ICONS so Phosphor Fonts don't break) */
    #tala-bulletproof-sk-sidebar,
    #tala-bulletproof-sk-sidebar *:not(i) {
        font-family: 'Plus Jakarta Sans', sans-serif !important;
    }

    #tala-bulletproof-sk-sidebar,
    #tala-bulletproof-sk-sidebar * {
        box-sizing: border-box !important;
        letter-spacing: normal !important;
        text-transform: none !important;
        text-decoration: none !important;
        line-height: 1.5 !important;
    }

    /* Main Sidebar Container - Zero Drop Shadow */
    #tala-bulletproof-sk-sidebar {
        width: 256px !important;
        background-color: rgba(255, 255, 255, 0.95) !important;
        backdrop-filter: blur(24px) !important;
        border-right: 1px solid rgba(226, 232, 240, 0.7) !important;
        position: fixed !important;
        top: 0 !important;
        bottom: 0 !important;
        left: 0 !important;
        z-index: 50 !important;
        display: flex !important;
        flex-direction: column !important;
        box-shadow: none !important;
        -webkit-box-shadow: none !important;
        margin: 0 !important;
        padding: 0 !important;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), background-color 0.3s ease, border-color 0.3s ease !important;
    }

    /* Custom Logo Animations */
    @keyframes fadeSwap1 {
        0%, 45%, 100% { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }
        50%, 95% { opacity: 0; transform: translateY(-10px) scale(0.95); pointer-events: none; }
    }
    @keyframes fadeSwap2 {
        0%, 45%, 100% { opacity: 0; transform: translateY(10px) scale(0.95); pointer-events: none; }
        50%, 95% { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }
    }
    @keyframes gradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }
    
    .tbp-brand-state-1 { animation: fadeSwap1 8s cubic-bezier(0.4, 0, 0.2, 1) infinite !important; }
    .tbp-brand-state-2 { animation: fadeSwap2 8s cubic-bezier(0.4, 0, 0.2, 1) infinite !important; }
    
    .tbp-animate-gradient {
        background-size: 200% auto !important;
        animation: gradientShift 2s linear infinite !important;
    }

    /* Branding Area */
    .tbp-brand-wrapper {
        height: 76px !important;
        display: flex !important;
        align-items: center !important;
        padding: 0 20px !important;
        border-bottom: 1px solid rgba(241, 245, 249, 0.9) !important;
        margin-bottom: 16px !important;
        position: relative !important;
        overflow: hidden !important;
        transition: border-color 0.3s ease !important;
    }
    .tbp-brand-inner {
        position: relative !important;
        width: 100% !important;
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
    }
    .tbp-brand-link {
        position: absolute !important;
        inset: 0 !important;
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
        width: 100% !important;
        height: 100% !important;
        cursor: pointer !important;
    }
    .tbp-logo {
        width: 38px !important;
        height: 38px !important;
        object-fit: contain !important;
        transition: transform 0.3s ease !important;
    }
    .tbp-brand-link:hover .tbp-logo {
        transform: scale(1.05) !important;
    }

    /* Navigation Area */
    .tbp-nav {
        flex: 1 !important;
        padding: 0 14px !important;
        overflow-y: auto !important;
        display: block !important;
    }
    .tbp-nav::-webkit-scrollbar { width: 4px !important; }
    .tbp-nav::-webkit-scrollbar-track { background: transparent !important; }
    .tbp-nav::-webkit-scrollbar-thumb { background: #cbd5e1 !important; border-radius: 4px !important; }

    .tbp-heading {
        display: block !important;
        padding: 0 8px !important;
        margin: 18px 0 6px 0 !important;
        font-size: 10px !important;
        font-weight: 800 !important;
        color: #94a3b8 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.1em !important;
    }
    .tbp-heading:first-child {
        margin-top: 6px !important;
    }

    /* Navigation Links */
    .tbp-link {
        display: flex !important;
        align-items: center !important;
        gap: 11px !important;
        padding: 9px 12px !important;
        margin-bottom: 4px !important;
        border-radius: 12px !important;
        font-size: 13px !important;
        font-weight: 700 !important;
        color: #64748b !important;
        background-color: transparent !important;
        transition: all 0.2s ease !important;
        cursor: pointer !important;
        position: relative !important;
    }
    .tbp-link i {
        font-size: 18px !important;
        margin: 0 !important;
        font-style: normal !important;
        flex-shrink: 0 !important;
    }
    .tbp-link span {
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    
    .tbp-link:hover {
        background-color: #f8fafc !important; 
        color: #0f172a !important; 
    }

    /* Ghost Active / Clicked Link (Translucent Tint, Zero Stroke / Exterior Line) */
    .tbp-link.active,
    .tbp-link:active {
        background-color: rgba(22, 163, 74, 0.12) !important; 
        color: #15803d !important; 
        font-weight: 700 !important;
        border: none !important;
        border-width: 0 !important;
        outline: none !important;
        box-shadow: none !important;
        -webkit-box-shadow: none !important;
        position: relative !important;
        overflow: hidden !important;
    }
    .tbp-link.active:hover {
        background-color: rgba(22, 163, 74, 0.18) !important;
        color: #15803d !important;
    }
    /* White Gradient on Top Right when Clicked or Active */
    .tbp-link.active::before,
    .tbp-link:active::before {
        content: '' !important;
        position: absolute !important;
        top: -6px !important;
        right: -6px !important;
        width: 50px !important;
        height: 50px !important;
        background: radial-gradient(circle at 75% 25%, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.4) 45%, transparent 75%) !important;
        pointer-events: none !important;
        border-radius: 9999px !important;
        filter: blur(3px) !important;
        z-index: 1 !important;
        transition: transform 0.25s ease, opacity 0.25s ease !important;
    }
    .tbp-link.active:hover::before,
    .tbp-link:active:hover::before {
        transform: scale(1.15) !important;
    }
    .tbp-link.active i,
    .tbp-link:active i,
    .tbp-link.active span,
    .tbp-link:active span {
        position: relative !important;
        z-index: 2 !important;
        color: #15803d !important;
        font-weight: 700 !important;
    }

    /* Footer / Minimal Profile Area */
    .tbp-footer {
        padding: 10px 12px !important;
        border-top: 1px solid rgba(241, 245, 249, 0.9) !important;
        display: block !important;
        transition: border-color 0.3s ease !important;
    }
    .tbp-profile-card {
        background: linear-gradient(135deg, #15803d 0%, #166534 100%) !important;
        border-radius: 12px !important;
        padding: 8px 10px !important;
        border: none !important;
        box-shadow: none !important;
        -webkit-box-shadow: none !important;
        display: flex !important;
        align-items: center !important;
        gap: 9px !important;
        transition: transform 0.2s ease, background 0.3s ease !important;
    }
    .tbp-avatar {
        width: 32px !important;
        height: 32px !important;
        border-radius: 8px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background-color: rgba(255, 255, 255, 0.2) !important;
        color: #ffffff !important;
        font-size: 13px !important;
        font-weight: 800 !important;
        border: none !important;
        box-shadow: none !important;
        flex-shrink: 0 !important;
    }
    .tbp-profile-info {
        flex: 1 !important;
        min-width: 0 !important;
        overflow: hidden !important;
    }
    .tbp-profile-name {
        font-size: 12px !important;
        font-weight: 800 !important;
        color: #ffffff !important;
        margin: 0 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        line-height: 1.2 !important;
    }
    .tbp-profile-role {
        font-size: 10px !important;
        font-weight: 600 !important;
        color: rgba(255, 255, 255, 0.85) !important;
        margin: 1px 0 0 0 !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        line-height: 1.2 !important;
    }
    .tbp-logout-btn {
        width: 28px !important;
        height: 28px !important;
        border-radius: 8px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background-color: rgba(255, 255, 255, 0.15) !important;
        color: #ffffff !important;
        font-size: 14px !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
        flex-shrink: 0 !important;
        border: none !important;
        text-decoration: none !important;
    }
    .tbp-logout-btn:hover {
        background-color: rgba(239, 68, 68, 0.9) !important;
        color: #ffffff !important;
        transform: scale(1.05) !important;
    }

    /* Mobile Drawer Slide-in Animation for SK Sidebar */
    @media (max-width: 767px) {
        #tala-bulletproof-sk-sidebar {
            transform: translateX(-100%) !important;
            z-index: 100 !important;
            width: 80% !important;
            max-width: 300px !important;
        }
        #tala-bulletproof-sk-sidebar.mobile-open,
        #tala-bulletproof-sk-sidebar.active {
            transform: translateX(0) !important;
        }
    }

    /* ======================================================================
       DARK MODE OVERRIDES (Strictly Isolated)
       ====================================================================== */
    html.dark #tala-bulletproof-sk-sidebar {
        background-color: rgba(24, 24, 27, 0.98) !important; /* zinc-900 */
        border-right-color: rgba(39, 39, 42, 0.8) !important; /* zinc-800 */
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-brand-wrapper {
        border-bottom-color: rgba(39, 39, 42, 0.8) !important;
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-brand-link h1 {
        color: #ffffff !important;
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-nav::-webkit-scrollbar-thumb {
        background: #3f3f46 !important; /* zinc-700 */
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-link {
        color: #a1a1aa !important; /* zinc-400 */
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-link:hover {
        background-color: rgba(39, 39, 42, 0.6) !important; /* zinc-800 */
        color: #ffffff !important;
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-link.active,
    html.dark #tala-bulletproof-sk-sidebar .tbp-link:active {
        background-color: rgba(34, 197, 94, 0.16) !important;
        color: #22c55e !important;
        font-weight: 700 !important;
        border: none !important;
        border-width: 0 !important;
        outline: none !important;
        box-shadow: none !important;
        -webkit-box-shadow: none !important;
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-link.active::before,
    html.dark #tala-bulletproof-sk-sidebar .tbp-link:active::before {
        background: radial-gradient(circle at 75% 25%, rgba(255, 255, 255, 0.45) 0%, rgba(255, 255, 255, 0.15) 45%, transparent 75%) !important;
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-link.active:hover {
        background-color: rgba(34, 197, 94, 0.22) !important;
        color: #22c55e !important;
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-link.active i,
    html.dark #tala-bulletproof-sk-sidebar .tbp-link:active i,
    html.dark #tala-bulletproof-sk-sidebar .tbp-link.active span,
    html.dark #tala-bulletproof-sk-sidebar .tbp-link:active span {
        color: #22c55e !important;
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-footer {
        border-top-color: rgba(39, 39, 42, 0.8) !important;
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-profile-card {
        background: linear-gradient(135deg, #166534 0%, #15803d 100%) !important;
        border: none !important;
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-avatar {
        background-color: rgba(255, 255, 255, 0.2) !important;
        color: #ffffff !important;
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-profile-name {
        color: #ffffff !important;
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-profile-role {
        color: rgba(255, 255, 255, 0.85) !important;
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-logout-btn {
        background-color: rgba(255, 255, 255, 0.15) !important;
        color: #ffffff !important;
    }
    html.dark #tala-bulletproof-sk-sidebar .tbp-logout-btn:hover {
        background-color: rgba(239, 68, 68, 0.9) !important;
        color: #ffffff !important;
    }
</style>

<!-- SK SIDEBAR (Barangay-Level Operations) -->
<aside id="tala-bulletproof-sk-sidebar">
    
    <!-- Branding -->
    <div class="tbp-brand-wrapper">
        <div class="tbp-brand-inner">
            
            <!-- State 1: TALA SK -->
            <a href="sk_dashboard.php" class="tbp-brand-link tbp-brand-state-1">
                <img src="TALA-LOGO.png" alt="Tala Logo" class="tbp-logo">
                <div style="display: flex !important; flex-direction: column !important; justify-content: center !important;">
                    <h1 style="font-size: 19px !important; font-weight: 800 !important; color: #0f172a !important; line-height: 1 !important; margin: 0 !important;">
                        TALA <span style="background: linear-gradient(to right, #22c55e, #facc15) !important; -webkit-background-clip: text !important; color: transparent !important;">SK</span>
                    </h1>
                    <span style="font-size: 9px !important; font-weight: 800 !important; letter-spacing: 0.1em !important; color: #94a3b8 !important; text-transform: uppercase !important; margin-top: 4px !important; line-height: 1 !important;">Local Council</span>
                </div>
            </a>

            <!-- State 2: SKTALA 2.0 Beta -->
            <a href="https://talatumauini.site.je/login.php" target="_blank" class="tbp-brand-link tbp-brand-state-2">
                <img src="sk.png" alt="SK Logo" class="tbp-logo">
                <div style="display: flex !important; flex-direction: column !important; justify-content: center !important;">
                    <h1 style="font-size: 17px !important; font-weight: 800 !important; color: #0f172a !important; line-height: 1 !important; margin: 0 !important; display: flex !important; align-items: flex-start !important; gap: 4px !important;">
                        TALA 2.0 
                        <span class="tbp-animate-gradient" style="font-size: 8px !important; font-weight: 900 !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; background: linear-gradient(to right, #22c55e, #facc15, #22c55e) !important; -webkit-background-clip: text !important; color: transparent !important; margin-top: 2px !important;">BETA</span>
                    </h1>
                    <span style="font-size: 8px !important; font-weight: 800 !important; letter-spacing: 0.1em !important; color: #94a3b8 !important; text-transform: uppercase !important; margin-top: 4px !important; line-height: 1 !important;">Barangay Level</span>
                </div>
            </a>

        </div>
    </div>

    <!-- Navigation Links -->
    <nav class="tbp-nav">
        
        <p class="tbp-heading">Dashboard</p>
        
        <a href="sk_dashboard.php" class="tbp-link <?= $current_page == 'sk_dashboard.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'sk_dashboard.php' ? 'ph-fill' : 'ph-bold' ?> ph-squares-four"></i>
            <span>Overview</span>
        </a>
        
        <p class="tbp-heading">Records & Compliance</p>

        <!-- SK Budget Vouchers -->
        <a href="sk_voucher.php" class="tbp-link <?= $current_page == 'sk_voucher.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'sk_voucher.php' ? 'ph-fill' : 'ph-bold' ?> ph-receipt"></i>
            <span>Budget Vouchers</span>
        </a>

        <!-- Meeting Records -->
        <a href="sk_mom.php" class="tbp-link <?= $current_page == 'sk_mom.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'sk_mom.php' ? 'ph-fill' : 'ph-bold' ?> ph-folders"></i>
            <span>Meeting Records</span>
        </a>
        
        <!-- Resolutions -->
        <a href="sk_resolution.php" class="tbp-link <?= $current_page == 'sk_resolution.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'sk_resolution.php' ? 'ph-fill' : 'ph-bold' ?> ph-scroll"></i>
            <span>Resolutions</span>
        </a>

        <!-- Master Calendar Link -->
        <a href="admin_calendar.php" class="tbp-link <?= $current_page == 'admin_calendar.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'admin_calendar.php' ? 'ph-fill' : 'ph-bold' ?> ph-calendar-blank"></i>
            <span>Master Calendar</span>
        </a>
        
        <!-- SK Project Kanban -->
        <a href="kanban.php" class="tbp-link <?= $current_page == 'kanban.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'kanban.php' ? 'ph-fill' : 'ph-bold' ?> ph-kanban"></i>
            <span>Project Board</span>
        </a>
        
        <!-- Full Disclosure Board -->
        <a href="manage_disclosure.php" class="tbp-link <?= $current_page == 'manage_disclosure.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'manage_disclosure.php' ? 'ph-fill' : 'ph-bold' ?> ph-file-magnifying-glass"></i>
            <span>Transparency Board</span>
        </a>

        <p class="tbp-heading">Engagement</p>

        <a href="public_registration.php" class="tbp-link <?= $current_page == 'public_registration.php' ? 'active' : '' ?>">
            <i class="<?= $current_page == 'public_registration.php' ? 'ph-fill' : 'ph-bold' ?> ph-user-plus"></i>
            <span>Add Profile</span>
        </a>
        
    </nav>

    <!-- User Profile & Logout (Minimal Single-Row Card) -->
    <div class="tbp-footer">
        <div class="tbp-profile-card">
            <div class="tbp-avatar" style="overflow: hidden;">
                <?php if ($sk_photo): ?>
                    <img src="<?= htmlspecialchars($sk_photo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($sk_name, ENT_QUOTES, 'UTF-8') ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: inherit;">
                <?php else: ?>
                    <?= htmlspecialchars(strtoupper(substr($sk_name, 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </div>
            <div class="tbp-profile-info">
                <p class="tbp-profile-name" title="<?= htmlspecialchars($sk_name) ?>"><?= htmlspecialchars($sk_name) ?></p>
                <p class="tbp-profile-role" title="<?= htmlspecialchars($position) ?> &bull; Brgy. <?= htmlspecialchars($barangay_label) ?>"><?= htmlspecialchars($position) ?></p>
            </div>
            <a href="logout.php" class="tbp-logout-btn" title="Sign Out">
                <i class="ph-bold ph-sign-out"></i>
            </a>
        </div>
    </div>
</aside>