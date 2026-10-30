<?php
// sk2_culture.php - SK OS 2.0 Community Culture & Multimedia Hub
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] !== 'sub-admin' && $_SESSION['role'] !== 'sk_admin')) {
    header("Location: sk2_login.php"); exit();
}

$barangay = $_SESSION['admin_barangay'] ?? 'Municipal Level';
$message = ''; $msgType = '';
$activeTab = $_GET['tab'] ?? 'hall_of_fame';

// Auto-create Tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_hall_of_fame` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `youth_name` VARCHAR(255) NOT NULL,
        `achievement` VARCHAR(255) NOT NULL,
        `category` VARCHAR(100) NOT NULL,
        `date_awarded` DATE NOT NULL,
        `photo_path` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_multimedia` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `media_type` ENUM('Podcast', 'Vlog', 'Event Highlight') NOT NULL,
        `url` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// HANDLE SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Add Achiever (Hall of Fame)
    if (isset($_POST['add_achiever'])) {
        $photo_path = null;
        if (isset($_FILES['achiever_photo']) && $_FILES['achiever_photo']['error'] == 0) {
            $upload_dir = 'uploads/achievers/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['achiever_photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = 'HOF_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                $photo_path = $upload_dir . $filename;
                move_uploaded_file($_FILES['achiever_photo']['tmp_name'], $photo_path);
            }
        }

        $stmt = $pdo->prepare("INSERT INTO sk2_hall_of_fame (barangay, youth_name, achievement, category, date_awarded, photo_path) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$barangay, $_POST['youth_name'], $_POST['achievement'], $_POST['category'], $_POST['date_awarded'], $photo_path])) {
            header("Location: sk2_culture.php?tab=hall_of_fame&msg=achiever_added"); exit();
        }
    }
    
    // Add Multimedia Link
    if (isset($_POST['add_media'])) {
        $stmt = $pdo->prepare("INSERT INTO sk2_multimedia (barangay, title, media_type, url, description) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$barangay, $_POST['title'], $_POST['media_type'], $_POST['url'], $_POST['description']])) {
            header("Location: sk2_culture.php?tab=media&msg=media_added"); exit();
        }
    }
}

// HANDLE DELETIONS
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];

    if ($action == 'delete_achiever') {
        // Fetch and delete photo
        $stmt = $pdo->prepare("SELECT photo_path FROM sk2_hall_of_fame WHERE id = ? AND barangay = ?");
        $stmt->execute([$id, $barangay]);
        $achiever = $stmt->fetch();
        if ($achiever && file_exists($achiever['photo_path'])) unlink($achiever['photo_path']);
        
        $pdo->prepare("DELETE FROM sk2_hall_of_fame WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_culture.php?tab=hall_of_fame&msg=deleted"); exit();
    } elseif ($action == 'delete_media') {
        $pdo->prepare("DELETE FROM sk2_multimedia WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_culture.php?tab=media&msg=deleted"); exit();
    }
}

// Set Messages
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m == 'achiever_added') { $message = 'Youth achiever successfully recognized.'; $msgType = 'success'; }
    if ($m == 'media_added') { $message = 'Multimedia content successfully linked.'; $msgType = 'success'; }
    if ($m == 'deleted') { $message = 'Record purged from database.'; $msgType = 'danger'; }
}

// FETCH DATA
$achievers = $pdo->prepare("SELECT * FROM sk2_hall_of_fame WHERE barangay = ? ORDER BY date_awarded DESC");
$achievers->execute([$barangay]); $achievers = $achievers->fetchAll(PDO::FETCH_ASSOC);

$mediaItems = $pdo->prepare("SELECT * FROM sk2_multimedia WHERE barangay = ? ORDER BY created_at DESC");
$mediaItems->execute([$barangay]); $mediaItems = $mediaItems->fetchAll(PDO::FETCH_ASSOC);

// Fetch Youth for Dropdown
$youth_Registry = [];
try {
    $stmtY = $pdo->prepare("SELECT id, first_name, last_name, full_name FROM youth_registry WHERE barangay = ?");
    $stmtY->execute([$barangay]); 
    $youth_Registry = $stmtY->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    // Optional: error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | Culture & Media</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- FIX 1: TAILWIND DARK MODE CONFIGURATION -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }
                }
            }
        }
    </script>
    <!-- END FIX 1 -->

    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <?php include 'sk2_theme_init.php'; ?>
    
    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        .tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex h-screen overflow-hidden selection:bg-blue-500 selection:text-white transition-colors duration-300">

<!-- Sidebar Include -->
<?php include 'sk2_sidebar.php'; ?>

<!-- FIX 2: CLEANED UP MAIN CONTAINER WITH DARK MODE BACKGROUND -->
<main class="ml-64 flex-1 h-screen overflow-y-auto custom-scrollbar relative bg-slate-50 dark:bg-zinc-950 transition-colors duration-300">

    <!-- The rest of your specific page content starts right below this! -->

    <main class="flex-1 overflow-y-auto custom-scrollbar relative">
        <div class="relative z-10 p-8 lg:p-10 max-w-[1600px] mx-auto">
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1">
                        Community Culture & Multimedia
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium">Celebrate achievers and manage local media for <?= htmlspecialchars($barangay) ?>.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <button id="themeToggle" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <i id="themeIcon" class="ph-bold ph-moon text-lg"></i>
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <?php 
                    $borderColor = 'border-blue-200 dark:border-blue-500/30'; $bgColor = 'bg-blue-50 dark:bg-blue-500/10'; $textColor = 'text-blue-700 dark:text-blue-400'; $icon = 'ph-info';
                    if($msgType=='danger'){ $borderColor = 'border-red-200 dark:border-red-500/30'; $bgColor = 'bg-red-50 dark:bg-red-500/10'; $textColor = 'text-red-700 dark:text-red-400'; $icon = 'ph-warning-circle'; }
                    if($msgType=='success'){ $borderColor = 'border-green-200 dark:border-green-500/30'; $bgColor = 'bg-green-50 dark:bg-green-500/10'; $textColor = 'text-green-700 dark:text-green-400'; $icon = 'ph-check-circle'; }
                ?>
                <div class="mb-8 p-4 rounded-xl border <?= $borderColor ?> <?= $bgColor ?> <?= $textColor ?> flex items-center gap-3 font-bold text-sm transition-colors">
                    <i class="ph-fill <?= $icon ?> text-xl"></i> <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- TABS -->
            <div class="flex gap-2 border-b border-slate-200 dark:border-zinc-800 mb-8 overflow-x-auto custom-scrollbar pb-2">
                <button onclick="switchTab('hall_of_fame')" id="tabBtn-hall_of_fame" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'hall_of_fame' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-trophy"></i> SK Hall of Fame
                </button>
                <button onclick="switchTab('media')" id="tabBtn-media" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'media' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-monitor-play"></i> Youth Media Hub
                </button>
            </div>

            <!-- TAB 1: HALL OF FAME (DIGITAL YEARBOOK) -->
            <div id="tab-hall_of_fame" class="tab-content <?= $activeTab == 'hall_of_fame' ? 'active' : '' ?>">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-white">Digital Youth Achiever Yearbook</h3>
                    <button onclick="document.getElementById('modal-achiever').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                        <i class="ph-bold ph-plus"></i> Add Achiever
                    </button>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php if(empty($achievers)): ?>
                        <div class="col-span-full text-center py-12 bg-white dark:bg-zinc-900 border border-dashed border-slate-300 dark:border-zinc-800 rounded-2xl">
                            <i class="ph-fill ph-student text-5xl text-slate-300 dark:text-zinc-700 mb-3 inline-block"></i>
                            <p class="text-slate-500 dark:text-zinc-500 font-bold">No achievers recognized yet.</p>
                        </div>
                    <?php else: foreach($achievers as $ach): 
                        // Style based on category
                        $catColor = 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400';
                        if ($ach['category'] == 'Academic/Board Passer') $catColor = 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400';
                        if ($ach['category'] == 'Sports & Athletics') $catColor = 'bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400';
                        if ($ach['category'] == 'Arts & Culture') $catColor = 'bg-yellow-50 text-yellow-600 dark:bg-yellow-500/10 dark:text-yellow-400';
                    ?>
                        <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl overflow-hidden relative group transition-colors shadow-sm dark:shadow-none flex flex-col">
                            <a href="?action=delete_achiever&id=<?= $ach['id'] ?>" onclick="return confirm('Remove achiever from Hall of Fame?')" class="absolute top-3 right-3 w-8 h-8 rounded-lg bg-red-500/80 hover:bg-red-600 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity z-10"><i class="ph-bold ph-trash"></i></a>
                            
                            <!-- Photo -->
                            <div class="h-48 w-full bg-slate-100 dark:bg-zinc-800 relative overflow-hidden">
                                <?php if($ach['photo_path'] && file_exists($ach['photo_path'])): ?>
                                    <img src="<?= htmlspecialchars($ach['photo_path']) ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-slate-300 dark:text-zinc-700 text-5xl"><i class="ph-fill ph-user-focus"></i></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Details -->
                            <div class="p-5 flex flex-col flex-1">
                                <span class="inline-block px-2.5 py-1 text-[9px] font-extrabold uppercase tracking-widest rounded w-max <?= $catColor ?> mb-3"><?= htmlspecialchars($ach['category']) ?></span>
                                <h4 class="text-lg font-black text-slate-900 dark:text-white leading-tight mb-1"><?= htmlspecialchars($ach['youth_name']) ?></h4>
                                <p class="text-sm font-semibold text-blue-600 dark:text-blue-400 mb-4"><?= htmlspecialchars($ach['achievement']) ?></p>
                                
                                <div class="mt-auto pt-3 border-t border-slate-100 dark:border-zinc-800">
                                    <p class="text-[10px] text-slate-400 dark:text-zinc-500 font-bold uppercase tracking-widest"><i class="ph-bold ph-calendar-blank"></i> Recognized: <?= date('M Y', strtotime($ach['date_awarded'])) ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- TAB 2: MULTIMEDIA HUB -->
            <div id="tab-media" class="tab-content <?= $activeTab == 'media' ? 'active' : '' ?>">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-white">Podcasts, Vlogs & Event Highlights</h3>
                    <button onclick="document.getElementById('modal-media').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                        <i class="ph-bold ph-link"></i> Link Media
                    </button>
                </div>

                <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 transition-colors shadow-sm dark:shadow-none">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800">
                                    <th class="py-4 px-6 w-16">Type</th>
                                    <th class="py-4 px-6">Content Details</th>
                                    <th class="py-4 px-6 text-center">External Link</th>
                                    <th class="py-4 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50">
                                <?php if(empty($mediaItems)): ?>
                                    <tr><td colspan="4" class="py-12 text-center text-slate-500 dark:text-zinc-500">No multimedia links published yet.</td></tr>
                                <?php else: foreach($mediaItems as $media): 
                                    $icon = 'ph-video-camera';
                                    if ($media['media_type'] == 'Podcast') $icon = 'ph-microphone-stage';
                                    if ($media['media_type'] == 'Event Highlight') $icon = 'ph-film-strip';
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="py-4 px-6 text-center">
                                        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 flex items-center justify-center text-xl mx-auto"><i class="ph-fill <?= $icon ?>"></i></div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="text-[9px] font-extrabold uppercase tracking-widest text-slate-400 dark:text-zinc-500"><?= $media['media_type'] ?></span>
                                        <p class="font-bold text-slate-900 dark:text-white text-base mt-0.5 leading-tight"><?= htmlspecialchars($media['title']) ?></p>
                                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1 line-clamp-1"><?= htmlspecialchars($media['description']) ?></p>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <a href="<?= htmlspecialchars($media['url']) ?>" target="_blank" class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700 px-3 py-1.5 rounded-lg transition-colors">
                                            <i class="ph-bold ph-arrow-square-out"></i> Watch / Listen
                                        </a>
                                    </td>
                                    <td class="py-4 px-6 text-right whitespace-nowrap">
                                        <a href="?action=delete_media&id=<?= $media['id'] ?>" onclick="return confirm('Remove media link?')" class="inline-flex w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500 dark:hover:text-white items-center justify-center transition tooltip"><i class="ph-bold ph-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL: ADD ACHIEVER -->
    <div id="modal-achiever" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm" onclick="document.getElementById('modal-achiever').classList.add('hidden')"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Honor Achiever</h3>
                <button onclick="document.getElementById('modal-achiever').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white"><i class="ph-bold ph-x text-xl"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="add_achiever" value="1">
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Youth Full Name *</label>
                    <select name="youth_name" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white focus:border-blue-500 outline-none appearance-none">
                        <option value="" disabled selected>-- Select Youth Achiever --</option>
                        <?php foreach($youth_Registry as $y): 
                            $name = !empty($y['full_name']) ? $y['full_name'] : $y['first_name'].' '.$y['last_name'];
                        ?>
                            <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Achievement Details *</label>
                    <input type="text" name="achievement" required placeholder="e.g. LET Board Exam Topnotcher" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Category *</label>
                        <select name="category" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none">
                            <option value="Academic/Board Passer">Academic / Board</option>
                            <option value="Sports & Athletics">Sports & Athletics</option>
                            <option value="Arts & Culture">Arts & Culture</option>
                            <option value="Community Service">Community Service</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Date Recognized *</label>
                        <input type="date" name="date_awarded" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-zinc-300 outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Achiever Photo *</label>
                    <input type="file" name="achiever_photo" accept="image/*" required class="w-full text-sm text-slate-500 dark:text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 dark:file:bg-zinc-800 file:text-slate-700 dark:file:text-white hover:file:bg-slate-200 cursor-pointer">
                </div>
                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition-colors">Publish to Hall of Fame</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: ADD MEDIA -->
    <div id="modal-media" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm" onclick="document.getElementById('modal-media').classList.add('hidden')"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Link Multimedia Content</h3>
                <button onclick="document.getElementById('modal-media').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white"><i class="ph-bold ph-x text-xl"></i></button>
            </div>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="add_media" value="1">
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Content Title *</label>
                    <input type="text" name="title" required placeholder="e.g. SK Youth Summit Highlights" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Media Type *</label>
                    <select name="media_type" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none">
                        <option value="Podcast">Audio Podcast / Interview</option>
                        <option value="Vlog">Youth Vlog</option>
                        <option value="Event Highlight">Event Video Highlight</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Video/Audio URL *</label>
                    <input type="url" name="url" required placeholder="https://youtube.com/..." class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Brief Description</label>
                    <textarea name="description" rows="3" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none resize-none"></textarea>
                </div>
                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition-colors">Publish Media Link</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // Tab Logic
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('[id^="tabBtn-"]').forEach(btn => {
                btn.className = "px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent";
            });

            document.getElementById('tab-' + tabId).classList.add('active');
            document.getElementById('tabBtn-' + tabId).className = "px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500";
            
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
        }

        // Theme Logic
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        if (document.documentElement.classList.contains('dark')) { themeIcon.classList.replace('ph-moon', 'ph-sun'); }

        themeToggleBtn.addEventListener('click', function() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                themeIcon.classList.replace('ph-sun', 'ph-moon');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                themeIcon.classList.replace('ph-moon', 'ph-sun');
            }
        });
    </script>
</body>
</html>