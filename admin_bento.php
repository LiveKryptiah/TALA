<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// SECURITY CHECK
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require 'db_connect.php'; 

$success_msg = "";
$error_msg = "";

// HANDLE DELETE REQUEST
if (isset($_GET['delete_id'])) {
    try {
        $del_id = $_GET['delete_id'];
        $stmt = $pdo->prepare("SELECT image_path FROM bento_items WHERE id = ?");
        $stmt->execute([$del_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item && file_exists($item['image_path'])) { unlink($item['image_path']); }
        
        $stmt = $pdo->prepare("DELETE FROM bento_items WHERE id = ?");
        $stmt->execute([$del_id]);
        $success_msg = "Item successfully deleted from the landing page!";
    } catch (Exception $e) { $error_msg = "Error deleting item: " . $e->getMessage(); }
}

// HANDLE UPLOAD REQUEST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['bento_image'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $target_dir = "uploads/";
    
    // Ensure upload directory exists
    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
    
    $file_name = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES["bento_image"]["name"]));
    $target_file = $target_dir . $file_name;
    
    $check = getimagesize($_FILES["bento_image"]["tmp_name"]);
    if($check !== false) {
        if (move_uploaded_file($_FILES["bento_image"]["tmp_name"], $target_file)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO bento_items (title, description, image_path) VALUES (?, ?, ?)");
                $stmt->execute([$title, $description, $target_file]);
                $success_msg = "New item successfully added to the landing page!";
            } catch (Exception $e) { $error_msg = "Database Error: " . $e->getMessage(); }
        } else { $error_msg = "Sorry, error moving file. Check directory permissions."; }
    } else { $error_msg = "File is not a valid image."; }
}

// FETCH ITEMS
$bento_items = [];
try {
    $stmt = $pdo->query("SELECT * FROM bento_items ORDER BY id DESC");
    $bento_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $error_msg = "Fetch Error: " . $e->getMessage(); }

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
    <title>Manage Landing Page | TALA OS</title>
    
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
        <div class="max-w-7xl mx-auto space-y-8">
            
            <!-- HEADER -->
            <div class="flex justify-between items-end pb-4 border-b border-slate-200">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Public Content</h1>
                    <p class="text-sm font-semibold text-slate-500 mt-1 uppercase tracking-widest">Manage Landing Page Grid</p>
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

            <?php if ($error_msg): ?>
                <div class="bg-red-50/80 border border-red-200 text-red-700 px-6 py-4 rounded-2xl flex items-center gap-3 font-bold text-sm shadow-sm backdrop-blur-md transition-all">
                    <i class="ph-fill ph-warning-circle text-2xl"></i> <?= htmlspecialchars($error_msg) ?>
                </div>
            <?php endif; ?>

            <!-- CONTENT SPLIT -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- COLUMN 1: UPLOAD FORM -->
                <div class="lg:col-span-1">
                    <div class="glass-card p-6 md:p-8 rounded-[2rem] shadow-soft border border-slate-200 sticky top-8">
                        <div class="flex items-center gap-3 mb-6 border-b border-slate-100 pb-4">
                            <div class="w-10 h-10 bg-slate-900 text-white rounded-xl flex items-center justify-center text-xl shadow-sm">
                                <i class="ph-bold ph-plus"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-extrabold text-slate-800 leading-tight">Add New Post</h2>
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Deploy to public grid</p>
                            </div>
                        </div>

                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-5">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Project/Post Title <span class="text-red-500">*</span></label>
                                <input type="text" name="title" required placeholder="E.g., Youth Assembly 2026" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Description <span class="text-red-500">*</span></label>
                                <textarea name="description" rows="3" required placeholder="Write a short description..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm resize-none custom-scrollbar"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Upload Picture <span class="text-red-500">*</span></label>
                                <input type="file" name="bento_image" accept="image/*" required class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-[10px] file:font-extrabold file:uppercase file:tracking-widest file:bg-brand-50 file:text-brand-600 hover:file:bg-brand-100 cursor-pointer transition-all border border-slate-200 p-1.5 rounded-xl bg-slate-50 shadow-sm">
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="w-full bg-slate-900 text-white font-bold py-3.5 px-6 rounded-xl shadow-lg transform hover:-translate-y-0.5 hover-gradient-btn transition-all flex items-center justify-center gap-2">
                                    <i class="ph-bold ph-cloud-arrow-up text-lg"></i> Publish to Website
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- COLUMN 2: ACTIVE ITEMS GRID -->
                <div class="lg:col-span-2">
                    <div class="glass-card p-6 md:p-8 rounded-[2rem] shadow-soft border border-slate-200">
                        <div class="flex items-center gap-3 mb-6 border-b border-slate-100 pb-4">
                            <div class="w-10 h-10 bg-brand-50 text-brand-600 border border-brand-100 rounded-xl flex items-center justify-center text-xl shadow-sm">
                                <i class="ph-bold ph-squares-four"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-extrabold text-slate-800 leading-tight">Current Website Grid</h2>
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Active public displays</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/50 text-[10px] font-extrabold text-slate-400 uppercase tracking-widest border-b border-slate-200">
                                        <th class="px-6 py-4 rounded-tl-xl">Display Media</th>
                                        <th class="px-6 py-4">Title & Description</th>
                                        <th class="px-6 py-4 text-right rounded-tr-xl">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-sm font-medium">
                                    <?php if (empty($bento_items)): ?>
                                        <tr>
                                            <td colspan="3" class="px-6 py-16 text-center">
                                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 border border-slate-100 mb-3">
                                                    <i class="ph-fill ph-images text-3xl text-slate-300"></i>
                                                </div>
                                                <p class="text-slate-500 font-bold text-lg">No content deployed</p>
                                                <p class="text-xs text-slate-400 mt-1 font-medium">Use the form to upload your first landing page item.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach($bento_items as $item): ?>
                                        <tr class="hover:bg-slate-50/80 transition-colors group">
                                            
                                            <!-- Media Preview -->
                                            <td class="px-6 py-4 w-32">
                                                <div class="w-20 h-14 rounded-lg overflow-hidden border border-slate-200 shadow-sm bg-slate-100">
                                                    <img src="<?= htmlspecialchars($item['image_path']); ?>" alt="Preview" class="w-full h-full object-cover">
                                                </div>
                                            </td>
                                            
                                            <!-- Content -->
                                            <td class="px-6 py-4">
                                                <p class="font-extrabold text-slate-900 mb-0.5"><?= htmlspecialchars($item['title']); ?></p>
                                                <p class="text-xs font-medium text-slate-500 leading-relaxed line-clamp-2">
                                                    <?= htmlspecialchars($item['description']); ?>
                                                </p>
                                            </td>
                                            
                                            <!-- Action -->
                                            <td class="px-6 py-4 text-right">
                                                <a href="admin_bento.php?delete_id=<?= $item['id']; ?>" onclick="return confirm('WARNING: Are you sure you want to remove this post from the public homepage?');" class="inline-flex items-center justify-center w-9 h-9 bg-white text-slate-400 rounded-xl hover:bg-red-50 hover:text-red-600 border border-slate-200 transition-colors shadow-sm" title="Delete Post">
                                                    <i class="ph-bold ph-trash text-lg"></i>
                                                </a>
                                            </td>
                                            
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- GENESIS WIDGET -->
    <?php include 'genesis_widget.php'; ?>

</body>
</html>
