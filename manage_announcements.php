<?php
// manage_announcements.php - Announcement Management Console (TALA OS Standard - Reference: manage_popup.php)
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Strict Admin Access Control
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$isAdmin = true;

// --- CRUD: CREATE & UPDATE ---
if (($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && (isset($_POST['save_announcement']) || isset($_POST['title']))) {
    $id = !empty($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : null;
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $created_by = $_SESSION['admin_username'] ?? 'Admin';
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] === '1';

    $image_path = null;
    $hasNewImage = false;

    // Handle Image Upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/announcements/';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0777, true);
        
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($ext, $allowed)) {
            $filename = 'ann_' . time() . '_' . uniqid() . '.' . $ext;
            $target_file = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = $target_file;
                $hasNewImage = true;
            }
        }
    }

    if (!empty($id)) {
        // UPDATE
        $stmtOld = $pdo->prepare("SELECT image_path FROM announcements WHERE id = ?");
        $stmtOld->execute([$id]);
        $oldImg = $stmtOld->fetchColumn();

        if ($remove_image) {
            if (!empty($oldImg) && file_exists($oldImg) && strpos($oldImg, 'uploads/') === 0) {
                @unlink($oldImg);
            }
            $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ?, image_path = NULL WHERE id = ?");
            $stmt->execute([$title, $content, $id]);
        } elseif ($hasNewImage) {
            if (!empty($oldImg) && file_exists($oldImg) && strpos($oldImg, 'uploads/') === 0) {
                @unlink($oldImg);
            }
            $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ?, image_path = ? WHERE id = ?");
            $stmt->execute([$title, $content, $image_path, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ? WHERE id = ?");
            $stmt->execute([$title, $content, $id]);
        }

        header("Location: manage_announcements.php");
        exit();
    } else {
        // CREATE
        $stmt = $pdo->prepare("INSERT INTO announcements (title, content, image_path, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$title, $content, $image_path, $created_by]);

        header("Location: manage_announcements.php");
        exit();
    }
}

// --- CRUD: DELETE ---
if (isset($_GET['delete']) || isset($_GET['delete_id'])) {
    $id = (int)($_GET['delete'] ?? $_GET['delete_id']);
    $stmt = $pdo->prepare("SELECT image_path FROM announcements WHERE id = ?");
    $stmt->execute([$id]);
    $oldImg = $stmt->fetchColumn();
    if (!empty($oldImg) && file_exists($oldImg) && strpos($oldImg, 'uploads/') === 0) {
        @unlink($oldImg);
    }

    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$id]);

    $redirect = 'manage_announcements.php';
    $queryParams = [];
    if (!empty($_GET['media'])) $queryParams['media'] = $_GET['media'];
    if (!empty($_GET['search'])) $queryParams['search'] = $_GET['search'];
    if (!empty($queryParams)) {
        $redirect .= '?' . http_build_query($queryParams);
    }
    header("Location: " . $redirect);
    exit();
}

// --- CRUD: READ & FILTERS ---
$search = trim($_GET['search'] ?? '');
$filter_media = trim($_GET['media'] ?? '');

$query = "SELECT * FROM announcements WHERE 1=1";
$params = [];

if ($filter_media === 'with_media') {
    $query .= " AND image_path IS NOT NULL AND image_path != ''";
} elseif ($filter_media === 'text_only') {
    $query .= " AND (image_path IS NULL OR image_path = '')";
}

if (!empty($search)) {
    $query .= " AND (title LIKE ? OR content LIKE ? OR created_by LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Telemetry Metrics
$total_announcements = (int)$pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$with_media_count = (int)$pdo->query("SELECT COUNT(*) FROM announcements WHERE image_path IS NOT NULL AND image_path != ''")->fetchColumn();
$text_only_count = max(0, $total_announcements - $with_media_count);
$unique_authors_count = (int)$pdo->query("SELECT COUNT(DISTINCT created_by) FROM announcements")->fetchColumn();
$current_filtered_count = count($announcements);
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Announcements & Broadcasts | TALA OS</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    
    <!-- Anti-Flash Theme Script -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif']
                    },
                    colors: { 
                        brand: { 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d' } 
                    }
                }
            }
        };
    </script>
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            transition: background-color 0.3s ease, color 0.3s ease; 
            -webkit-tap-highlight-color: transparent; 
        }
        
        /* Ultra-Clean Modern Glass Cards: ZERO Outside Borders & ZERO Drop Shadows */
        .glass-card { 
            background: #ffffff !important; 
            border: none !important; 
            box-shadow: none !important; 
            -webkit-box-shadow: none !important;
            transition: all 0.3s ease; 
        }
        .dark .glass-card { 
            background: #18181b !important; 
            border: none !important; 
            box-shadow: none !important; 
            -webkit-box-shadow: none !important;
        }

        /* Mobile Drawer Overlay */
        .mobile-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            z-index: 40;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .mobile-overlay.active {
            display: block;
            opacity: 1;
        }

        .custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }

        /* Tap effect for mobile elements */
        .tap-effect:active { transform: scale(0.97); opacity: 0.85; transition: transform 0.1s; }

        /* Remove all outline / outside strokelines on focus */
        input:focus,
        input:focus-visible,
        input:active,
        select:focus,
        select:focus-visible,
        select:active,
        textarea:focus,
        textarea:focus-visible,
        textarea:active,
        button:focus,
        button:focus-visible,
        label:focus,
        label:focus-visible,
        label:active,
        a:focus {
            outline: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }

        label {
            user-select: none;
            -webkit-user-select: none;
            -webkit-tap-highlight-color: transparent;
        }

        /* Modern Spring Hover Effects (Matching events.php) */
        .modern-card-hover {
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.3s ease !important;
            will-change: transform;
        }
        .modern-card-hover:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- Admin Sidebar -->
    <?php include 'sidebar_admin.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA (Responsive & Gracefully Scrollable - Matching manage_popup.php) -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px and fits viewport smoothly -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-3 md:space-y-4">
            
            <!-- HEADER SECTION (Bold H1 only, 3 Unified Green KPI Badges with Modern Hover) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                        Announcement Management
                    </h1>
                </div>
                
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-megaphone"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Broadcasts</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($total_announcements) ?></span>
                        </div>
                    </div>

                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-image"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">With Media</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($with_media_count) ?></span>
                        </div>
                    </div>

                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-all duration-200 hover:-translate-y-0.5 group cursor-default">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0 group-hover:scale-110 group-hover:bg-green-500/20 transition-all duration-200">
                            <i class="ph-bold ph-user-circle"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Authors</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($unique_authors_count) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FILTER, SEARCH & ACTION CONTROL BAR (Matching manage_popup.php) -->
            <div class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col md:flex-row items-center justify-between gap-2.5">
                <!-- Media Filter Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto custom-scrollbar py-1 flex-shrink-0">
                    <?php 
                    $filter_tabs = [
                        ['param' => '', 'val' => '', 'label' => 'All Broadcasts', 'count' => $total_announcements, 'active' => empty($filter_media)],
                        ['param' => 'media', 'val' => 'with_media', 'label' => 'With Media', 'count' => $with_media_count, 'active' => ($filter_media === 'with_media')],
                        ['param' => 'media', 'val' => 'text_only', 'label' => 'Text Only', 'count' => $text_only_count, 'active' => ($filter_media === 'text_only')],
                    ];
                    foreach ($filter_tabs as $tab):
                        $isActive = $tab['active'];
                        $pillClass = $isActive 
                            ? 'bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white' 
                            : 'bg-slate-100 hover:bg-slate-200/90 text-slate-600 hover:text-slate-900 dark:bg-zinc-800 dark:hover:bg-zinc-700/90 dark:text-zinc-400 dark:hover:text-white';
                        $url = 'manage_announcements.php';
                        if (!empty($tab['param'])) {
                            $url .= '?' . $tab['param'] . '=' . urlencode($tab['val']);
                            if (!empty($search)) $url .= '&search=' . urlencode($search);
                        } elseif (!empty($search)) {
                            $url .= '?search=' . urlencode($search);
                        }
                    ?>
                        <a href="<?= $url ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all duration-200 active:scale-[0.97] tap-effect <?= $pillClass ?>">
                            <span><?= $tab['label'] ?></span>
                            <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black <?= $isActive ? 'bg-white/20 dark:bg-black/20 text-white' : 'bg-slate-200/80 dark:bg-zinc-700/80 text-slate-600 dark:text-zinc-300' ?>"><?= $tab['count'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Search and New Post Button -->
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <form method="GET" class="relative flex-1 md:w-56">
                        <?php if (!empty($filter_media)): ?>
                            <input type="hidden" name="media" value="<?= htmlspecialchars($filter_media) ?>">
                        <?php endif; ?>
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search headline, details, author..." class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-800 dark:text-zinc-200 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                    </form>

                    <?php if (!empty($search)): ?>
                        <a href="manage_announcements.php<?= !empty($filter_media) ? '?media=' . urlencode($filter_media) : '' ?>" class="flex items-center justify-center bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-300 dark:hover:text-white font-bold h-[30px] w-[30px] rounded-xl text-xs transition-all duration-200 active:scale-[0.95] tap-effect flex-shrink-0" title="Clear Search">
                            <i class="ph-bold ph-x text-xs"></i>
                        </a>
                    <?php endif; ?>

                    <button id="newAnnouncementBtn" type="button" onclick="openAnnouncementModal()" class="px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center gap-1.5 flex-shrink-0 transition-all duration-200 active:scale-[0.97] shadow-none group">
                        <i class="ph-bold ph-plus text-xs transition-transform duration-300 group-hover:scale-110"></i>
                        <span>New Post</span>
                    </button>
                </div>
            </div>

            <!-- CONSOLIDATED MINIMAL BROADCAST REGISTRY TABLE (Minimal Table Architecture - Reference: manage_popup.php) -->
            <div class="glass-card rounded-2xl overflow-hidden flex flex-col flex-1 min-h-0 transition-colors">
                
                <!-- Minimal Header Strip -->
                <div class="px-4 py-2.5 bg-white/60 dark:bg-zinc-900/60 flex justify-between items-center transition-colors flex-shrink-0 border-b border-slate-100 dark:border-zinc-800">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-zinc-200">Broadcast Registry</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400">
                            <?= number_format($current_filtered_count) ?>
                        </span>
                    </div>
                    <span class="text-[11px] font-medium text-slate-400 dark:text-zinc-500">Portal bulletins & public notices</span>
                </div>

                <!-- Table Scrollable Area -->
                <div class="overflow-x-auto overflow-y-auto flex-1 custom-scrollbar min-h-0">
                    <table class="w-full text-left border-collapse min-w-[680px]">
                        <thead class="sticky top-0 z-10">
                            <tr class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider bg-slate-50/90 dark:bg-zinc-900/90 backdrop-blur-sm transition-colors border-b border-slate-100 dark:border-zinc-800">
                                <th class="py-2 px-4">Announcement</th>
                                <th class="py-2 px-4">Author</th>
                                <th class="py-2 px-4">Date Posted</th>
                                <th class="py-2 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs font-medium divide-y divide-slate-100/80 dark:divide-zinc-800/60 transition-colors">
                            <?php if (!empty($announcements)): ?>
                                <?php foreach ($announcements as $ann): 
                                    $hasMedia = (!empty($ann['image_path']) && file_exists($ann['image_path']));
                                    $filterQuery = (!empty($filter_media) ? '&media=' . urlencode($filter_media) : '') . (!empty($search) ? '&search=' . urlencode($search) : '');
                                ?>
                                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-800/30 transition-colors group">
                                        <!-- Announcement: Thumbnail + Title + Subtitle Snippet -->
                                        <td class="py-2.5 px-4">
                                            <div class="flex items-center gap-3">
                                                <?php if ($hasMedia): ?>
                                                    <div class="w-8 h-8 rounded-lg overflow-hidden bg-slate-100 dark:bg-zinc-800 flex-shrink-0 relative group/thumb">
                                                        <img src="<?= htmlspecialchars($ann['image_path']) ?>" alt="Thumbnail" class="w-full h-full object-cover group-hover/thumb:scale-110 transition-transform duration-200">
                                                    </div>
                                                <?php else: ?>
                                                    <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                                        <i class="ph-bold ph-megaphone"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="min-w-0 max-w-sm sm:max-w-md">
                                                    <p class="font-bold text-slate-900 dark:text-white text-xs leading-snug truncate group-hover:text-green-700 dark:group-hover:text-green-400 transition-colors">
                                                        <?= htmlspecialchars($ann['title']) ?>
                                                    </p>
                                                    <p class="text-[11px] text-slate-400 dark:text-zinc-500 truncate mt-0.5">
                                                        <?= htmlspecialchars($ann['content']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Author -->
                                        <td class="py-2.5 px-4 whitespace-nowrap text-xs text-slate-500 dark:text-zinc-400 font-semibold">
                                            <span class="inline-flex items-center gap-1.5">
                                                <i class="ph-fill ph-user-circle text-slate-400"></i>
                                                <span><?= htmlspecialchars($ann['created_by']) ?></span>
                                            </span>
                                        </td>

                                        <!-- Date Posted -->
                                        <td class="py-2.5 px-4 whitespace-nowrap text-xs text-slate-400 dark:text-zinc-500">
                                            <?= !empty($ann['created_at']) ? date('M j, Y', strtotime($ann['created_at'])) : '—' ?>
                                        </td>

                                        <!-- Minimal Ghost Action Buttons -->
                                        <td class="py-2.5 px-4 text-right align-middle whitespace-nowrap">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button" onclick="editAnnouncement(<?= htmlspecialchars(json_encode($ann), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800 transition-colors tap-effect" title="Edit Announcement">
                                                    <i class="ph-bold ph-pencil-simple text-xs"></i>
                                                </button>
                                                <a href="manage_announcements.php?delete=<?= $ann['id'] ?><?= $filterQuery ?>" onclick="return confirm('Confirm Deletion: Permanently remove this announcement from the public portal?')" class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors tap-effect" title="Delete Announcement">
                                                    <i class="ph-bold ph-trash text-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="py-10 text-center text-slate-400 dark:text-zinc-500 select-none">
                                        <i class="ph-bold ph-megaphone text-2xl opacity-40 mb-1 block mx-auto"></i>
                                        <p class="text-xs font-semibold text-slate-600 dark:text-zinc-400">No announcement records found</p>
                                        <?php if (!empty($search) || !empty($filter_media)): ?>
                                            <a href="manage_announcements.php" class="mt-2.5 inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 text-xs font-semibold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors tap-effect">
                                                <span>Clear Filters</span>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Minimal Table Footer -->
                <div class="px-4 py-2 border-t border-slate-100 dark:border-zinc-800/80 flex justify-between items-center text-[11px] text-slate-400 dark:text-zinc-500 flex-shrink-0 bg-white/40 dark:bg-zinc-900/40">
                    <span>Showing <strong class="text-slate-700 dark:text-zinc-300"><?= number_format($current_filtered_count) ?></strong> of <?= number_format($total_announcements) ?></span>
                    <span>TALA OS &bull; Broadcast Registry</span>
                </div>

            </div>

        </div>
    </main>

    <!-- ANNOUNCEMENT CREATE & EDIT MODAL (Minimal Modern Scale-Fade Pop-up Effect - Reference: manage_popup.php) -->
    <div id="announcementModalOverlay" class="fixed inset-0 z-50 hidden items-center justify-center p-4 sm:p-6 opacity-0 transition-opacity duration-300">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm cursor-pointer" onclick="closeAnnouncementModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] overflow-hidden max-w-xl md:max-w-2xl w-full p-5 md:p-7 flex flex-col transform scale-95 opacity-0 transition-all duration-300 z-10 shadow-none border border-slate-100 dark:border-zinc-800" id="announcementModalBox">
            
            <!-- Modal Header -->
            <div class="flex items-center justify-between gap-3 mb-5 border-b border-slate-100 dark:border-zinc-800 pb-3.5">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm">
                        <i class="ph-bold ph-megaphone"></i>
                    </div>
                    <div>
                        <h2 id="modalFormTitle" class="text-sm md:text-base font-extrabold text-slate-900 dark:text-white tracking-tight select-none">Post New Announcement</h2>
                        <p id="modalFormSubtitle" class="text-[11px] text-slate-400 dark:text-zinc-500 font-medium">Publish announcement to the community portal feed</p>
                    </div>
                </div>
                <button type="button" onclick="closeAnnouncementModal()" class="w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-400 hover:text-slate-700 dark:hover:text-zinc-200 flex items-center justify-center tap-effect transition-all duration-200 active:scale-[0.95]" title="Close">
                    <i class="ph-bold ph-x text-xs"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form id="announcementModalForm" action="manage_announcements.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-3.5 max-h-[75vh] overflow-y-auto custom-scrollbar pr-1">
                <input type="hidden" name="save_announcement" value="1">
                <input type="hidden" name="announcement_id" id="modal_announcement_id" value="">

                <!-- Headline Title -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Headline / Title *</label>
                    <input type="text" name="title" id="modal_title" required placeholder="e.g. SK Linggo ng Kabataan 2026 Schedule & Guidelines" class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors">
                </div>

                <!-- Photo Attachment -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Banner Graphic (Optional)</label>
                    <input type="file" name="image" id="modal_image" accept="image/*" class="w-full text-xs text-slate-500 dark:text-zinc-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 dark:file:bg-zinc-800 file:text-slate-700 dark:file:text-zinc-300 hover:file:bg-slate-200 dark:hover:file:bg-zinc-700 bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 rounded-xl p-1.5 cursor-pointer transition-colors">
                    
                    <div id="modalCurrentImageContainer" class="hidden mt-2 flex items-center justify-between p-2 rounded-xl bg-slate-50 dark:bg-zinc-900/40 border border-slate-100 dark:border-zinc-800">
                        <div class="flex items-center gap-2">
                            <img id="modalCurrentImageThumb" src="" class="w-10 h-10 object-cover rounded-lg border border-slate-200 dark:border-zinc-700">
                            <div>
                                <p class="text-[11px] font-bold text-green-600 dark:text-green-500">Banner Photo Attached</p>
                                <p class="text-[10px] text-slate-400 dark:text-zinc-500">Upload new file to replace</p>
                            </div>
                        </div>
                        <label class="flex items-center gap-1.5 text-[11px] font-semibold text-slate-500 dark:text-zinc-400 cursor-pointer">
                            <input type="checkbox" name="remove_image" id="modal_remove_image" value="1" class="rounded border-slate-300 dark:border-zinc-700 text-green-600 focus:ring-0">
                            <span>Remove Banner</span>
                        </label>
                    </div>
                </div>

                <!-- Content Details & Guidelines -->
                <div class="md:col-span-2">
                    <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5 select-none">Content Details & Guidelines *</label>
                    <textarea name="content" id="modal_content" rows="4" required placeholder="Write announcement details, guidelines, or program instructions..." class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-slate-300 dark:focus:border-zinc-700 transition-colors resize-none custom-scrollbar"></textarea>
                </div>

                <!-- Modal Footer -->
                <div class="md:col-span-2 flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button" onclick="closeAnnouncementModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-white font-bold text-xs md:text-sm tap-effect transition-all duration-200 active:scale-[0.97]">
                        Cancel
                    </button>
                    <button type="submit" id="modalSubmitBtn" class="bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-200 text-xs md:text-sm tap-effect active:scale-[0.97] flex items-center justify-center gap-2 shadow-none group">
                        <span id="modalSubmitText">Publish Post</span>
                        <i class="ph-bold ph-paper-plane-tilt text-xs group-hover:translate-x-0.5 transition-transform duration-200"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MOBILE BOTTOM NAVIGATION (Android Style) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 h-[68px] bg-white/95 dark:bg-zinc-950/95 backdrop-blur-2xl border-t border-slate-200 dark:border-zinc-800 z-50 flex justify-around items-center px-2 pb-[env(safe-area-inset-bottom)] transition-colors">
        <a href="dashboard.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-squares-four text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Dashboard</span>
        </a>
        <a href="admin_calendar.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-calendar-blank text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Calendar</span>
        </a>
        <a href="manage_announcements.php" class="flex flex-col items-center justify-center w-full h-full text-[#15803d] dark:text-[#4ade80] tap-effect">
            <div class="relative px-4 py-1 bg-[#15803d]/10 dark:bg-[#15803d]/20 rounded-full mb-1 transition-colors">
                <i class="ph-fill ph-megaphone text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Announce</span>
        </a>
        <a href="admin_approval.php" class="flex flex-col items-center justify-center w-full h-full text-slate-400 dark:text-zinc-500 hover:text-slate-700 dark:hover:text-zinc-300 transition-colors tap-effect">
            <div class="px-4 py-1 mb-1">
                <i class="ph-bold ph-shield-check text-2xl"></i>
            </div>
            <span class="text-[10px] font-bold">Approvals</span>
        </a>
    </nav>

    <!-- UI Scripts -->
    <script>
        function toggleMobileMenu() {
            const sidebar = document.getElementById('tala-admin-sidebar') || document.getElementById('tala-bulletproof-sk-sidebar') || document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            if (sidebar && overlay) {
                if (sidebar.classList.contains('mobile-open') || !sidebar.classList.contains('-translate-x-full')) {
                    sidebar.classList.remove('mobile-open');
                    sidebar.classList.add('-translate-x-full');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                } else {
                    sidebar.classList.add('mobile-open');
                    sidebar.classList.remove('-translate-x-full');
                    overlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            }
        }

        // Open Announcement Modal (Create or Edit with smooth scale-up modern pop-up effect)
        function openAnnouncementModal(data = null) {
            const modal = document.getElementById('announcementModalOverlay');
            const box = document.getElementById('announcementModalBox');
            const form = document.getElementById('announcementModalForm');
            if (!modal || !box) return;

            if (data) {
                // Edit Mode
                document.getElementById('modal_announcement_id').value = data.id || '';
                document.getElementById('modal_title').value = data.title || '';
                document.getElementById('modal_content').value = data.content || '';

                document.getElementById('modalFormTitle').textContent = 'Edit Announcement';
                document.getElementById('modalFormSubtitle').textContent = 'Update announcement details and media';
                document.getElementById('modalSubmitText').textContent = 'Save Changes';

                const imgContainer = document.getElementById('modalCurrentImageContainer');
                const imgThumb = document.getElementById('modalCurrentImageThumb');
                const removeImg = document.getElementById('modal_remove_image');
                if (removeImg) removeImg.checked = false;

                if (data.image_path) {
                    if (imgThumb) imgThumb.src = data.image_path;
                    if (imgContainer) imgContainer.classList.remove('hidden');
                } else {
                    if (imgContainer) imgContainer.classList.add('hidden');
                }
            } else {
                // Create Mode
                if (form) form.reset();
                document.getElementById('modal_announcement_id').value = '';

                document.getElementById('modalFormTitle').textContent = 'Post New Announcement';
                document.getElementById('modalFormSubtitle').textContent = 'Publish announcement to the community portal feed';
                document.getElementById('modalSubmitText').textContent = 'Publish Post';

                const imgContainer = document.getElementById('modalCurrentImageContainer');
                if (imgContainer) imgContainer.classList.add('hidden');
            }

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                box.classList.remove('scale-95', 'opacity-0');
                box.classList.add('scale-100', 'opacity-100');
                const titleInput = document.getElementById('modal_title');
                if (titleInput) titleInput.focus();
            }, 10);
        }

        function closeAnnouncementModal() {
            const modal = document.getElementById('announcementModalOverlay');
            const box = document.getElementById('announcementModalBox');
            if (!modal || !box) return;

            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            box.classList.remove('scale-100', 'opacity-100');
            box.classList.add('scale-95', 'opacity-0');

            setTimeout(() => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            }, 200);
        }

        function editAnnouncement(data) {
            openAnnouncementModal(data);
        }

        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('announcementModalOverlay');
                if (modal && !modal.classList.contains('hidden')) {
                    closeAnnouncementModal();
                }
            }
        });
    </script>

    <?php include_once 'genesis_widget.php'; ?>
</body>
</html>