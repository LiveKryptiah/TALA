<?php
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success_msg = '';
// Get the ID from the URL (default to 1)
$highlight_id = isset($_GET['id']) ? (int)$_GET['id'] : 1;

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $badge_text = $_POST['badge_text'];
    $title_main = $_POST['title_main'];
    $title_highlight = $_POST['title_highlight'];
    $description = $_POST['description'];
    $button_text = $_POST['button_text'];
    $button_link = $_POST['button_link'];
    $stat_label = $_POST['stat_label'];
    $stat_value = $_POST['stat_value'];
    
    $params = [$badge_text, $title_main, $title_highlight, $description, $button_text, $button_link, $stat_label, $stat_value];
    $sql_update = "UPDATE featured_highlight SET badge_text = ?, title_main = ?, title_highlight = ?, description = ?, button_text = ?, button_link = ?, stat_label = ?, stat_value = ?";
    
    // Handle Image Upload
    if (isset($_FILES['highlight_image']) && $_FILES['highlight_image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_extension = pathinfo($_FILES["highlight_image"]["name"], PATHINFO_EXTENSION);
        $new_filename = "highlight_" . $highlight_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES["highlight_image"]["tmp_name"], $target_file)) {
            $sql_update .= ", image_path = ?";
            $params[] = $target_file;
        }
    }
    
    $sql_update .= " WHERE id = ?";
    $params[] = $highlight_id;
    
    $stmt = $pdo->prepare($sql_update);
    if($stmt->execute($params)) {
        $success_msg = "Highlight #$highlight_id updated successfully!";
    }
}

// Fetch current data based on ID
$stmt = $pdo->prepare("SELECT * FROM featured_highlight WHERE id = ?");
$stmt->execute([$highlight_id]);
$highlight = $stmt->fetch(PDO::FETCH_ASSOC);

$displayName = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin';
$displayInitial = strtoupper(substr($displayName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Highlights | TALA OS</title>
    
    <!-- Modern Enterprise SaaS Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script>
        tailwind.config = {
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
        /* Ambient Light Mode Background Animations */
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .ambient-bg { position: fixed; inset: 0; z-index: -1; background: #f8fafc; overflow: hidden; pointer-events: none; }
        .blob { position: absolute; filter: blur(100px); opacity: 0.6; border-radius: 50%; mix-blend-multiply: true; animation: blob 10s infinite alternate ease-in-out; }
        .animation-delay-2000 { animation-delay: 2s; }
        
        .glass-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.8); }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        
        .hover-gradient-btn { transition: all 0.3s ease; }
        .hover-gradient-btn:hover {
            background: linear-gradient(135deg, #16a34a, #eab308) !important;
            color: white !important;
            border-color: transparent !important;
            box-shadow: 0 10px 25px -5px rgba(22, 163, 74, 0.4) !important;
        }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="text-slate-800 antialiased flex h-screen overflow-hidden selection:bg-brand-500 selection:text-white">

    <!-- GLOBAL AMBIENT MESH GRADIENT BACKGROUND -->
    <div class="ambient-bg">
        <div class="blob bg-brand-200 w-[500px] h-[500px] -top-[10%] -left-[10%]"></div>
        <div class="blob bg-yellow-200 w-[500px] h-[500px] bottom-[10%] -right-[10%] animation-delay-2000"></div>
    </div>

    <!-- SIDEBAR INCLUDE -->
    <?php 
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        include 'sidebar_admin.php';
    } else {
        include 'sidebar_sk.php';
    }
    ?>

    <!-- MAIN CONTENT -->
    <main class="flex-1 overflow-y-auto p-4 md:p-8 ml-64 relative z-10 custom-scrollbar">
        <div class="max-w-4xl mx-auto space-y-8 pb-10">
            
            <!-- HEADER -->
            <div class="flex justify-between items-end pb-4 border-b border-slate-200">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Public Content</h1>
                    <p class="text-sm font-semibold text-slate-500 mt-1 uppercase tracking-widest">Manage Spotlight Highlights</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-extrabold text-slate-800"><?= htmlspecialchars($displayName) ?></p>
                        <p class="text-[10px] font-bold text-brand-600 uppercase tracking-widest">TALA System Access</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-600 to-yellow-500 text-white flex items-center justify-center font-extrabold text-xl shadow-lg shadow-brand-500/20">
                        <?= $displayInitial ?>
                    </div>
                </div>
            </div>

            <!-- SYSTEM ALERTS -->
            <?php if ($success_msg): ?>
                <div class="bg-green-50/80 border border-green-200 text-green-700 px-6 py-4 rounded-2xl flex items-center gap-3 font-bold text-sm shadow-sm backdrop-blur-md transition-all">
                    <i class="ph-fill ph-check-circle text-2xl"></i> <?= htmlspecialchars($success_msg) ?>
                </div>
            <?php endif; ?>

            <!-- SELECTOR & FORM MODULE -->
            <div class="glass-card rounded-[2rem] shadow-soft border border-slate-200 overflow-hidden">
                
                <!-- Selector Header -->
                <div class="bg-slate-50 border-b border-slate-100 p-6 md:px-10 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-white rounded-xl shadow-sm border border-slate-200 flex items-center justify-center text-brand-600 text-xl shrink-0">
                            <i class="ph-bold ph-swap"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Currently Editing</p>
                            <h2 class="text-lg font-extrabold text-slate-800 leading-tight">Select Section to Edit</h2>
                        </div>
                    </div>
                    
                    <div class="w-full sm:w-2/3">
                        <select onchange="window.location.href='?id='+this.value" class="w-full bg-white border-2 border-brand-500 text-brand-700 rounded-xl px-4 py-3 text-sm font-extrabold focus:outline-none focus:ring-4 focus:ring-brand-500/20 transition-all shadow-sm cursor-pointer appearance-none">
                            <option value="1" <?= $highlight_id == 1 ? 'selected' : '' ?>>Highlight 1 (Primary - Image Left)</option>
                            <option value="2" <?= $highlight_id == 2 ? 'selected' : '' ?>>Highlight 2 (Secondary - Image Right)</option>
                        </select>
                    </div>
                </div>

                <!-- Form Area -->
                <form method="POST" enctype="multipart/form-data" class="p-6 md:p-10 space-y-6">
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Badge Text (Top Label)</label>
                        <input type="text" name="badge_text" value="<?= htmlspecialchars($highlight['badge_text']) ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Main Title (White Text)</label>
                            <input type="text" name="title_main" value="<?= htmlspecialchars($highlight['title_main']) ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Highlight Title (Yellow Text)</label>
                            <input type="text" name="title_highlight" value="<?= htmlspecialchars($highlight['title_highlight']) ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Description Paragraph</label>
                        <textarea name="description" rows="3" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm resize-none custom-scrollbar"><?= htmlspecialchars($highlight['description']) ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Button Call-to-action Text</label>
                            <input type="text" name="button_text" value="<?= htmlspecialchars($highlight['button_text']) ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Button Link (e.g. public_events.php)</label>
                            <input type="text" name="button_link" value="<?= htmlspecialchars($highlight['button_link']) ?>" required class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 bg-brand-50/50 p-5 rounded-2xl border border-brand-100">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Floating Stat Label (e.g. Slots Left)</label>
                            <input type="text" name="stat_label" value="<?= htmlspecialchars($highlight['stat_label']) ?>" placeholder="Optional" class="w-full bg-white border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Floating Stat Value (e.g. Only 45)</label>
                            <input type="text" name="stat_value" value="<?= htmlspecialchars($highlight['stat_value']) ?>" placeholder="Optional" class="w-full bg-white border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-6">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-3">Update Image Thumbnail</label>
                        <div class="flex flex-col sm:flex-row items-center gap-6">
                            <?php if(!empty($highlight['image_path'])): ?>
                                <div class="relative group shrink-0">
                                    <img src="<?= htmlspecialchars($highlight['image_path']) ?>" class="w-24 h-24 object-cover rounded-2xl border border-slate-200 shadow-sm">
                                    <div class="absolute inset-0 bg-slate-900/60 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-[10px] font-extrabold uppercase tracking-widest backdrop-blur-sm">Current</div>
                                </div>
                            <?php else: ?>
                                <div class="w-24 h-24 bg-slate-50 border border-slate-200 border-dashed rounded-2xl flex flex-col items-center justify-center text-slate-400 shrink-0">
                                    <i class="ph-bold ph-image text-2xl mb-1"></i>
                                    <span class="text-[9px] font-bold uppercase tracking-widest">No Image</span>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="highlight_image" accept="image/*" class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-xs file:font-extrabold file:uppercase file:tracking-widest file:bg-brand-50 file:text-brand-600 hover:file:bg-brand-100 cursor-pointer transition-all border border-slate-200 p-2 rounded-2xl bg-white shadow-sm">
                        </div>
                    </div>

                    <div class="flex justify-end pt-6 mt-4 border-t border-slate-100">
                        <button type="submit" class="bg-slate-900 text-white font-bold py-3.5 px-8 rounded-xl shadow-lg transform hover:-translate-y-0.5 hover-gradient-btn transition-all flex items-center justify-center gap-2">
                            <i class="ph-bold ph-floppy-disk text-lg"></i> Save Changes to Section #<?= $highlight_id ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- GENESIS WIDGET -->
    <?php include 'genesis_widget.php'; ?>

</body>
</html>
