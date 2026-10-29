<?php
// manage_highlights.php - Portal Highlights & Public Carousel Management (TALA OS Standard)
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// Auto-create highlights table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `highlights` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT NOT NULL,
        `image_path` VARCHAR(255) NOT NULL,
        `created_by` VARCHAR(100) DEFAULT 'Admin',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// Handle Add Highlight (Admins Only)
if ($isAdmin && ($_SERVER["REQUEST_METHOD"] ?? '') === "POST" && (isset($_POST['add_highlight']) || isset($_POST['title']))) {
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/highlights/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $filename = 'hlt_' . time() . '_' . uniqid() . '.' . $ext;
            $image_path = $upload_dir . $filename;
            move_uploaded_file($_FILES['image']['tmp_name'], $image_path);
        }
    }

    if ($image_path) {
        $stmt = $pdo->prepare("INSERT INTO highlights (title, description, image_path, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            trim($_POST['title']), 
            trim($_POST['description']), 
            $image_path, 
            $_SESSION['admin_username'] ?? 'Admin'
        ]);
        header("Location: manage_highlights.php?success=1");
        exit();
    } else {
        header("Location: manage_highlights.php?error=image_required");
        exit();
    }
}

// Handle Delete Highlight (Admins Only)
if ($isAdmin && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT image_path FROM highlights WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && !empty($row['image_path']) && file_exists($row['image_path'])) {
        @unlink($row['image_path']);
    }

    $stmt = $pdo->prepare("DELETE FROM highlights WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: manage_highlights.php?deleted=1");
    exit();
}

// Search & Query Highlights
$search_query = trim($_GET['search'] ?? '');
if (!empty($search_query)) {
    $stmt = $pdo->prepare("SELECT * FROM highlights WHERE title LIKE ? OR description LIKE ? OR created_by LIKE ? ORDER BY created_at DESC");
    $term = "%{$search_query}%";
    $stmt->execute([$term, $term, $term]);
    $highlights = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $highlights = $pdo->query("SELECT * FROM highlights ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

// Telemetry counts for header KPI chips
$total_highlights = (int)$pdo->query("SELECT COUNT(*) FROM highlights")->fetchColumn();
$with_images = (int)$pdo->query("SELECT COUNT(*) FROM highlights WHERE image_path IS NOT NULL AND image_path != ''")->fetchColumn();
$total_creators = (int)$pdo->query("SELECT COUNT(DISTINCT created_by) FROM highlights")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Portal Highlights | TALA OS</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    
    <!-- ANTI-FLASH SCRIPT -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
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
        a:focus {
            outline: none !important;
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }

        /* Remove all box shadows globally */
        .shadow-none-all,
        .shadow-sm,
        .shadow-md,
        .shadow-lg,
        .shadow-xl,
        .shadow-2xl,
        .shadow-soft {
            box-shadow: none !important;
            -webkit-box-shadow: none !important;
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased flex min-h-screen lg:h-screen overflow-x-hidden overflow-y-auto lg:overflow-hidden selection:bg-[#15803d] selection:text-white dark:selection:bg-[#16a34a] dark:selection:text-white transition-colors duration-300">

    <!-- STANDARD TALA OS SIDEBAR (ROLE-BASED) -->
    <?php 
    if ($isAdmin) {
        include 'sidebar_admin.php';
    } else {
        include 'sidebar_sk.php';
    }
    ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMobileMenu()"></div>

    <!-- MAIN CONTENT AREA (Responsive & Gracefully Scrollable) -->
    <main class="md:ml-64 pt-20 md:pt-4 px-3 md:px-6 pb-28 md:pb-6 custom-scrollbar flex-1 w-full transition-all duration-300 relative z-10 min-h-screen lg:h-screen overflow-y-auto flex flex-col justify-between">
        
        <!-- INNER WRAPPER: Centers the content to exactly 1600px and fits viewport smoothly -->
        <div class="max-w-[1600px] mx-auto w-full flex-1 flex flex-col justify-start min-h-0 space-y-3 md:space-y-4">
            
            <!-- HEADER SECTION (Bold H1 only, No redundant descriptions or eyebrows, xl:pr-14 clearance) -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center py-1 gap-2.5 transition-colors flex-shrink-0 xl:pr-14">
                <div>
                    <h1 class="text-xl md:text-2xl lg:text-3xl font-black tracking-tight text-slate-900 dark:text-white transition-colors">
                        Portal Highlights
                    </h1>
                </div>
                
                <!-- Quick KPI Badges - ALL 3 UNIFIED GREEN STYLE (Matching events.php) -->
                <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0">
                            <i class="ph-bold ph-cards"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Total Banners</span>
                            <span class="text-base md:text-lg font-black text-green-600 dark:text-green-500 leading-tight"><?= number_format($total_highlights) ?></span>
                        </div>
                    </div>

                    <div class="glass-card px-3.5 py-1.5 rounded-xl flex items-center gap-2.5 flex-1 sm:flex-none transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0">
                            <i class="ph-bold ph-check-circle"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Active On Portal</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($with_images) ?></span>
                        </div>
                    </div>

                    <div class="hidden sm:flex glass-card px-3.5 py-1.5 rounded-xl items-center gap-2.5 flex-1 sm:flex-none transition-colors">
                        <div class="w-8 h-8 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center font-bold text-sm flex-shrink-0">
                            <i class="ph-bold ph-user-circle"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="block text-[10px] md:text-xs uppercase tracking-wider text-slate-400 dark:text-zinc-500 font-bold leading-none truncate">Contributors</span>
                            <span class="text-base md:text-lg font-black text-slate-900 dark:text-white leading-tight"><?= number_format($total_creators) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Flash Alert Banners (Matching events.php) -->
            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-50 dark:bg-green-950/40 text-green-800 dark:text-green-300 border border-green-200/50 dark:border-green-800/40 px-3.5 py-2.5 rounded-xl flex items-center justify-between gap-2.5 text-xs md:text-sm font-bold flex-shrink-0 transition-colors">
                    <div class="flex items-center gap-2">
                        <i class="ph-fill ph-check-circle text-base"></i>
                        <span>Highlight banner published and active on the portal carousel!</span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-200 tap-effect">
                        <i class="ph-bold ph-x text-xs"></i>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 border border-red-200/50 dark:border-red-800/40 px-3.5 py-2.5 rounded-xl flex items-center justify-between gap-2.5 text-xs md:text-sm font-bold flex-shrink-0 transition-colors">
                    <div class="flex items-center gap-2">
                        <i class="ph-fill ph-trash text-base"></i>
                        <span>Highlight banner permanently deleted from the system.</span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200 tap-effect">
                        <i class="ph-bold ph-x text-xs"></i>
                    </button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 border border-amber-200/50 dark:border-amber-800/40 px-3.5 py-2.5 rounded-xl flex items-center justify-between gap-2.5 text-xs md:text-sm font-bold flex-shrink-0 transition-colors">
                    <div class="flex items-center gap-2">
                        <i class="ph-fill ph-warning text-base"></i>
                        <span>Please select a valid cover banner image (JPG, PNG, WEBP).</span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-200 tap-effect">
                        <i class="ph-bold ph-x text-xs"></i>
                    </button>
                </div>
            <?php endif; ?>

            <!-- FILTER, SEARCH & ACTION CONTROL BAR (Matching events.php) -->
            <div class="glass-card rounded-2xl p-2.5 md:p-3 transition-colors flex flex-col md:flex-row items-center justify-between gap-2.5">
                <!-- Status Pills -->
                <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto custom-scrollbar pb-1 md:pb-0 flex-shrink-0">
                    <a href="manage_highlights.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold whitespace-nowrap transition-all tap-effect bg-[#15803d] text-white dark:bg-[#16a34a] dark:text-white">
                        <i class="ph-bold ph-cards text-xs"></i>
                        <span>All Banners</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-black bg-white/20 dark:bg-black/20"><?= $total_highlights ?></span>
                    </a>
                </div>

                <!-- Search and Create Button -->
                <div class="flex items-center gap-2 w-full md:w-auto">
                    <form method="GET" class="relative flex-1 md:w-60">
                        <i class="ph-bold ph-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search headline, description..." class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-800 dark:text-zinc-200 placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl pl-8 pr-3 py-1.5 text-xs font-semibold outline-none focus:border-green-600 dark:focus:border-green-500 transition-colors">
                    </form>

                    <?php if (!empty($search_query)): ?>
                        <a href="manage_highlights.php" class="flex items-center justify-center bg-slate-200 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 font-bold h-[34px] w-[34px] rounded-xl text-xs transition-all tap-effect" title="Clear Search">
                            <i class="ph-bold ph-x"></i>
                        </a>
                    <?php endif; ?>

                    <?php if ($isAdmin): ?>
                        <button type="button" onclick="toggleCreateHighlightForm()" class="px-3.5 py-1.5 rounded-xl bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold text-xs tap-effect flex items-center gap-1.5 flex-shrink-0 transition-all shadow-none">
                            <i class="ph-bold ph-plus text-xs"></i>
                            <span>New Highlight</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CREATE NEW HIGHLIGHT COLLAPSIBLE CARD (Admins Only - Matching events.php) -->
            <?php if ($isAdmin): ?>
            <div id="createHighlightCard" class="hidden glass-card rounded-2xl p-4 md:p-6 transition-all">
                <div class="flex items-center justify-between gap-3 mb-4 border-b border-slate-100 dark:border-zinc-800 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-green-500/10 text-green-600 dark:text-green-500 flex items-center justify-center text-sm font-bold">
                            <i class="ph-bold ph-image-square"></i>
                        </div>
                        <h2 class="text-xs md:text-sm font-extrabold text-slate-900 dark:text-white tracking-tight">Post New Highlight Banner</h2>
                    </div>
                    <button type="button" onclick="toggleCreateHighlightForm()" class="text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 tap-effect">
                        <i class="ph-bold ph-x text-sm"></i>
                    </button>
                </div>

                <form action="manage_highlights.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
                    <input type="hidden" name="add_highlight" value="1">
                    
                    <div class="md:col-span-2">
                        <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5">Banner Headline *</label>
                        <input type="text" name="title" required placeholder="e.g. Linggo ng Kabataan Sportsfest & Culminating Gala" class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-green-600 dark:focus:border-green-500 transition-colors">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5">Cover Banner Image *</label>
                        <input type="file" name="image" accept="image/*" required class="w-full text-xs text-slate-500 dark:text-zinc-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 dark:file:bg-zinc-800 file:text-slate-700 dark:file:text-zinc-300 hover:file:bg-slate-200 dark:hover:file:bg-zinc-700 bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 rounded-xl p-1.5 cursor-pointer transition-colors">
                    </div>
                    
                    <div class="col-span-full">
                        <label class="block text-[10px] md:text-xs font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-wider mb-1.5">Program Overview / Short Description *</label>
                        <textarea name="description" rows="3" required placeholder="Write a brief overview of the featured youth initiative, program highlights, and celebration details..." class="w-full bg-slate-50 dark:bg-zinc-900/60 border border-slate-200/80 dark:border-zinc-800 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 rounded-xl px-3.5 py-2.5 text-xs md:text-sm font-semibold outline-none focus:border-green-600 dark:focus:border-green-500 transition-colors resize-none custom-scrollbar"></textarea>
                    </div>
                    
                    <div class="col-span-full flex justify-end gap-2 pt-1">
                        <button type="button" onclick="toggleCreateHighlightForm()" class="px-4 py-2.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 hover:bg-slate-200 dark:hover:bg-zinc-700 font-bold text-xs md:text-sm tap-effect transition-all">
                            Cancel
                        </button>
                        <button type="submit" class="bg-[#15803d] hover:bg-[#166534] dark:bg-[#16a34a] dark:hover:bg-[#15803d] text-white font-bold py-2.5 px-6 rounded-xl transition-all text-xs md:text-sm tap-effect flex items-center justify-center gap-2 shadow-none">
                            <span>Publish Highlight</span>
                            <i class="ph-bold ph-paper-plane-tilt text-xs"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- HIGHLIGHTS CARDS GRID (Modeled after Event Cards Grid in events.php) -->
            <div class="flex-1 min-h-0 overflow-y-auto custom-scrollbar">
                <?php if (count($highlights) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 md:gap-4 pb-6">
                        <?php foreach ($highlights as $hlt): ?>
                            <div class="glass-card rounded-2xl overflow-hidden flex flex-col group transition-all">
                                
                                <!-- Banner Header -->
                                <div class="h-44 sm:h-48 relative overflow-hidden bg-slate-100 dark:bg-zinc-900 flex-shrink-0">
                                    <!-- Status Chips Top-Right -->
                                    <div class="absolute top-3 right-3 z-10 flex gap-1.5">
                                        <span class="bg-white/95 dark:bg-zinc-900/95 backdrop-blur text-green-700 dark:text-green-400 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider flex items-center gap-1">
                                            <i class="ph-fill ph-check-circle"></i> Live Banner
                                        </span>
                                    </div>

                                    <!-- Date Badge Top-Left -->
                                    <div class="absolute top-3 left-3 z-10">
                                        <span class="bg-white/95 dark:bg-zinc-900/95 backdrop-blur text-slate-800 dark:text-zinc-200 text-[10px] font-black px-2.5 py-1 rounded-lg flex items-center gap-1">
                                            <i class="ph-bold ph-calendar text-slate-400"></i>
                                            <?= date('M j, Y', strtotime($hlt['created_at'])) ?>
                                        </span>
                                    </div>

                                    <!-- Cover Image (Zero image drop shadows) -->
                                    <?php if (!empty($hlt['image_path']) && file_exists($hlt['image_path'])): ?>
                                        <img src="<?= htmlspecialchars($hlt['image_path']) ?>" alt="<?= htmlspecialchars($hlt['title']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    <?php else: ?>
                                        <div class="w-full h-full flex flex-col items-center justify-center bg-slate-200/70 dark:bg-zinc-800/80 text-slate-400 dark:text-zinc-500">
                                            <i class="ph-fill ph-image text-4xl mb-1 opacity-50"></i>
                                            <span class="font-bold text-[10px] uppercase tracking-wider">No Cover Image</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Card Content -->
                                <div class="p-4 md:p-5 flex flex-col flex-1 bg-white dark:bg-zinc-900 transition-colors">
                                    
                                    <h3 class="text-sm md:text-base font-extrabold text-slate-900 dark:text-white mb-1.5 leading-snug line-clamp-2">
                                        <?= htmlspecialchars($hlt['title']) ?>
                                    </h3>
                                    
                                    <p class="text-xs font-semibold text-slate-400 dark:text-zinc-500 mb-2 flex items-center gap-1 truncate">
                                        <i class="ph-fill ph-user-circle text-slate-400"></i>
                                        Published by <?= htmlspecialchars($hlt['created_by'] ?? 'Admin') ?>
                                    </p>
                                    
                                    <p class="text-xs text-slate-600 dark:text-zinc-300 line-clamp-3 mb-4 leading-relaxed font-normal">
                                        <?= htmlspecialchars($hlt['description']) ?>
                                    </p>
                                    
                                    <!-- Actions Row at bottom of card -->
                                    <div class="mt-auto pt-3 border-t border-slate-100 dark:border-zinc-800/80 transition-colors flex items-center gap-2">
                                        <?php if (!empty($hlt['image_path']) && file_exists($hlt['image_path'])): ?>
                                            <a href="<?= htmlspecialchars($hlt['image_path']) ?>" target="_blank" class="flex-1 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-800 dark:text-zinc-200 font-bold py-2 px-3 rounded-xl text-xs tap-effect transition-all flex items-center justify-center gap-1.5 shadow-none">
                                                <i class="ph-bold ph-arrow-square-out"></i>
                                                <span>View Image</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="flex-1 text-center py-2 text-xs font-bold text-slate-400 dark:text-zinc-500">No Image</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($isAdmin): ?>
                                            <a href="manage_highlights.php?delete=<?= $hlt['id'] ?>" onclick="return confirm('Permanently remove this highlight banner? This cannot be undone.');" class="w-8 h-8 inline-flex items-center justify-center rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 hover:text-rose-600 dark:hover:text-rose-400 transition-all tap-effect flex-shrink-0" title="Delete Banner">
                                                <i class="ph-bold ph-trash text-xs"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="glass-card p-10 md:p-14 text-center rounded-2xl flex flex-col items-center justify-center transition-colors">
                        <div class="w-24 h-24 md:w-28 md:h-28 mx-auto mb-2 flex items-center justify-center">
                            <img src="mascot_question.png" alt="No Highlights" class="max-h-full max-w-full object-contain pointer-events-none hover:scale-105 transition-transform duration-300">
                        </div>
                        <h3 class="text-base md:text-lg font-extrabold text-slate-800 dark:text-white transition-colors">No Highlights Found</h3>
                        <p class="text-xs md:text-sm text-slate-400 dark:text-zinc-500 transition-colors mt-1 max-w-sm">
                            <?= (!empty($search_query)) ? 'No banners matched your search query. Try clearing search.' : 'There are no active highlight banners published on the portal.' ?>
                        </p>
                        <?php if (!empty($search_query)): ?>
                            <a href="manage_highlights.php" class="mt-4 px-3.5 py-1.5 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-700 dark:text-zinc-300 text-xs font-bold hover:bg-slate-200 dark:hover:bg-zinc-700 transition-all tap-effect">
                                Clear Search
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
    
    <!-- JAVASCRIPT LOGIC (Matching events.php) -->
    <script>
        function toggleMobileMenu() {
            const sidebar = document.getElementById('tala-admin-sidebar') || document.getElementById('tala-bulletproof-sk-sidebar');
            const overlay = document.getElementById('mobileOverlay');
            if (sidebar && overlay) {
                if (sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('active');
                } else {
                    sidebar.classList.add('mobile-open');
                    overlay.classList.add('active');
                }
            }
        }

        function toggleCreateHighlightForm() {
            const card = document.getElementById('createHighlightCard');
            if (card) {
                card.classList.toggle('hidden');
                if (!card.classList.contains('hidden')) {
                    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        }
    </script>

    <!-- Genesis AI Assistant Widget -->
    <?php if (file_exists(__DIR__ . '/genesis_widget.php')) { include __DIR__ . '/genesis_widget.php'; } ?>
</body>
</html>