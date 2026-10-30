<?php
session_start();
require 'db_connect.php';

// Ensure user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$user_role = $_SESSION['role']; // 'admin' or 'sub-admin'
$user_barangay = $_SESSION['barangay_assigned'] ?? 'All';
$user_position = $_SESSION['sk_position'] ?? 'Admin';

// RBAC: Determine if user can upload (Only Admin, SK Chair, Sec, Treas)
$can_upload = false;
if ($user_role === 'admin' || in_array($user_position, ['SK Chairperson', 'SK Secretary', 'SK Treasurer'])) {
    $can_upload = true;
}

// Handle Upload Logic
$upload_msg = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_mom']) && $can_upload) {
    $meeting_date = $_POST['meeting_date'];
    $meeting_type = $_POST['meeting_type'];
    $barangay = ($user_role === 'admin') ? $_POST['barangay'] : $user_barangay;
    
    // File Upload Handling
    if (isset($_FILES['mom_file']) && $_FILES['mom_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/mom/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file_ext = strtolower(pathinfo($_FILES['mom_file']['name'], PATHINFO_EXTENSION));
        $new_filename = 'MOM_' . str_replace(' ', '', $barangay) . '_' . time() . '.' . $file_ext;
        $target_path = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['mom_file']['tmp_name'], $target_path)) {
            // Insert MOM Record
            $stmt = $pdo->prepare("INSERT INTO mom_documents (barangay, meeting_date, meeting_type, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$barangay, $meeting_date, $meeting_type, $target_path, $_SESSION['admin_username']]);
            $mom_id = $pdo->lastInsertId();

            // Insert Digital Attendance Signatures
            if (isset($_POST['attendance'])) {
                $att_stmt = $pdo->prepare("INSERT INTO mom_attendance (mom_id, official_name, position, is_present, signature_verified) VALUES (?, ?, ?, 1, 1)");
                foreach ($_POST['attendance'] as $official) {
                    $details = explode('|', $official); // Expected format: Name|Position
                    if(count($details) == 2) {
                        $att_stmt->execute([$mom_id, $details[0], $details[1]]);
                    }
                }
            }
            $upload_msg = "Minutes of the Meeting & Verified Signatures uploaded successfully.";
        }
    }
}

// Fetch MOM Data based on Role
if ($user_role === 'admin') {
    $moms = $pdo->query("SELECT * FROM mom_documents ORDER BY meeting_date DESC")->fetchAll(PDO::FETCH_ASSOC);
    $total_moms = count($moms);
} else {
    $stmt = $pdo->prepare("SELECT * FROM mom_documents WHERE barangay = ? ORDER BY meeting_date DESC");
    $stmt->execute([$user_barangay]);
    $moms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_moms = count($moms);
}

// Mock Data representing the parsed text from your source document
$officials_list = [
    ['name' => 'Jerwin R. Cunanan', 'position' => 'SK Chairperson'],
    ['name' => 'Anna Janine B. Caliguiran', 'position' => 'SK Kagawad'],
    ['name' => 'Mark Bryan T. Magaru', 'position' => 'SK Kagawad'],
    ['name' => 'Ma. Angela T. Domingo', 'position' => 'SK Kagawad'],
    ['name' => 'Jessabel L. Gamas', 'position' => 'SK Kagawad'],
    ['name' => 'Marimar L. Eugenio', 'position' => 'SK Kagawad'],
    ['name' => 'Jomari L. Calalung', 'position' => 'SK Kagawad'],
    ['name' => 'Kristine F. Guiyab', 'position' => 'SK Kagawad'],
    ['name' => 'Dianne T. Mirandilla', 'position' => 'SK Secretary'],
    ['name' => 'Cristine Apple M. Lumauig', 'position' => 'SK Treasurer']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MOM Management | TALA OS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { brand: { 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d' } },
                    boxShadow: { 'soft': '0 10px 30px -10px rgba(0,0,0,0.05)', 'glow': '0 0 15px rgba(22, 163, 74, 0.2)' }
                }
            }
        }
    </script>
    <style>
        .glass-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); border: 1px solid rgba(226, 232, 240, 0.8); }
        .ambient-bg { position: fixed; inset: 0; z-index: -1; background: #f8fafc; overflow: hidden; pointer-events: none; }
        .blob { position: absolute; filter: blur(90px); opacity: 0.5; border-radius: 50%; }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="text-slate-800 antialiased flex h-screen overflow-hidden">

    <div class="ambient-bg">
        <div class="blob bg-brand-200 w-[500px] h-[500px] -top-[10%] -left-[10%]"></div>
        <div class="blob bg-blue-200 w-[500px] h-[500px] bottom-[10%] -right-[10%]"></div>
    </div>

    <!-- SIDEBAR INCLUDE -->
    <?php include 'sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="flex-1 overflow-y-auto p-8 ml-64">
        
        <div class="max-w-7xl mx-auto space-y-8">
            
            <!-- HEADER -->
            <div class="flex justify-between items-end">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Meeting Records</h1>
                    <p class="text-sm font-semibold text-slate-500 mt-1 uppercase tracking-widest">
                        <?= $user_role === 'admin' ? 'City-Wide MOM Directory' : 'Barangay ' . htmlspecialchars($user_barangay) . ' Directory' ?>
                    </p>
                </div>
                <div class="glass-card px-6 py-3 rounded-2xl shadow-soft flex items-center gap-4">
                    <div class="w-10 h-10 bg-brand-100 text-brand-600 rounded-xl flex items-center justify-center text-xl font-bold">
                        <i class="ph-fill ph-files"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Uploaded</p>
                        <p class="text-2xl font-extrabold text-slate-800 leading-none"><?= $total_moms ?></p>
                    </div>
                </div>
            </div>

            <?php if (!empty($upload_msg)): ?>
                <div class="bg-brand-50 border border-brand-200 text-brand-700 px-5 py-4 rounded-2xl flex items-center gap-3 text-sm font-bold shadow-sm">
                    <i class="ph-fill ph-check-circle text-xl"></i> <?= htmlspecialchars($upload_msg) ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- UPLOAD MODULE (Only visible to allowed roles) -->
                <?php if ($can_upload): ?>
                <div class="lg:col-span-1 space-y-6">
                    <div class="glass-card p-6 rounded-[2rem] shadow-soft">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center text-xl shadow-sm">
                                <i class="ph-bold ph-upload-simple"></i>
                            </div>
                            <h2 class="text-xl font-extrabold text-slate-800">Upload MOM</h2>
                        </div>

                        <form action="mom_management.php" method="POST" enctype="multipart/form-data" class="space-y-5">
                            <input type="hidden" name="upload_mom" value="1">
                            
                            <?php if ($user_role === 'admin'): ?>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Target Barangay</label>
                                <select name="barangay" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-brand-500 outline-none" required>
                                    <option value="Namnama">Namnama</option>
                                    <!-- Add other barangays... -->
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Date</label>
                                    <input type="date" name="meeting_date" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-brand-500 outline-none" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Type</label>
                                    <select name="meeting_type" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-brand-500 outline-none" required>
                                        <option value="Regular Session">Regular Session</option>
                                        <option value="Special Session">Special Session</option>
                                        <option value="Assembly">Assembly</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Scanned Document (PDF/Image)</label>
                                <input type="file" name="mom_file" accept=".pdf,image/*" class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:font-bold file:bg-brand-50 file:text-brand-700 bg-slate-50 border border-slate-200 rounded-xl p-1.5" required>
                            </div>

                            <!-- DIGITAL SIGNATURE ATTENDANCE TRACKER -->
                            <div class="mt-6 pt-6 border-t border-slate-200">
                                <label class="block text-xs font-bold text-slate-800 uppercase tracking-wider mb-1">Verify Physical Signatures</label>
                                <p class="text-[10px] text-slate-500 mb-4 font-medium leading-relaxed">Check the boxes for officials whose signatures are present on the scanned attendance sheet.</p>
                                
                                <div class="space-y-2 max-h-48 overflow-y-auto pr-2 custom-scrollbar">
                                    <?php foreach ($officials_list as $official): ?>
                                    <label class="flex items-center justify-between p-3 bg-white border border-slate-100 rounded-xl hover:bg-slate-50 cursor-pointer transition">
                                        <div class="flex items-center gap-3">
                                            <input type="checkbox" name="attendance[]" value="<?= $official['name'] . '|' . $official['position'] ?>" class="w-4 h-4 text-brand-600 rounded border-slate-300 focus:ring-brand-500">
                                            <div>
                                                <p class="text-sm font-bold text-slate-800 leading-none"><?= htmlspecialchars($official['name']) ?></p>
                                                <p class="text-[10px] font-bold text-brand-600 uppercase tracking-widest mt-1"><?= htmlspecialchars($official['position']) ?></p>
                                            </div>
                                        </div>
                                        <i class="ph-fill ph-signature text-slate-300 text-lg"></i>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <button type="submit" class="w-full bg-slate-900 text-white font-bold py-4 rounded-xl shadow-md hover:bg-brand-600 transition-all flex items-center justify-center gap-2">
                                Save Record <i class="ph-bold ph-floppy-disk"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- DATA TABLE MODULE (Viewable by Admin and SK Officials) -->
                <div class="<?= $can_upload ? 'lg:col-span-2' : 'lg:col-span-3' ?>">
                    <div class="glass-card rounded-[2rem] shadow-soft overflow-hidden flex flex-col h-full">
                        <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-white/50">
                            <h2 class="text-xl font-extrabold text-slate-800">Document Archive</h2>
                            <div class="flex gap-2">
                                <button class="p-2 bg-slate-100 rounded-lg text-slate-500 hover:text-brand-600 transition"><i class="ph-bold ph-funnel"></i></button>
                                <button class="p-2 bg-slate-100 rounded-lg text-slate-500 hover:text-brand-600 transition"><i class="ph-bold ph-sort-ascending"></i></button>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-slate-50/80 text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-slate-200">
                                        <th class="py-4 px-6">Meeting Details</th>
                                        <?php if ($user_role === 'admin'): ?><th class="py-4 px-6">Barangay</th><?php endif; ?>
                                        <th class="py-4 px-6">Verified Attendance</th>
                                        <th class="py-4 px-6 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm font-medium text-slate-600 divide-y divide-slate-100">
                                    <?php if (count($moms) > 0): ?>
                                        <?php foreach ($moms as $mom): 
                                            // Fetch attendance count for this MOM
                                            $att_count = $pdo->query("SELECT COUNT(*) FROM mom_attendance WHERE mom_id = " . $mom['id'])->fetchColumn();
                                        ?>
                                        <tr class="hover:bg-slate-50/50 transition">
                                            <td class="py-4 px-6">
                                                <p class="font-bold text-slate-800"><?= date('F d, Y', strtotime($mom['meeting_date'])) ?></p>
                                                <p class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($mom['meeting_type']) ?></p>
                                            </td>
                                            <?php if ($user_role === 'admin'): ?>
                                            <td class="py-4 px-6">
                                                <span class="bg-slate-100 text-slate-600 px-3 py-1 rounded-full text-xs font-bold"><?= htmlspecialchars($mom['barangay']) ?></span>
                                            </td>
                                            <?php endif; ?>
                                            <td class="py-4 px-6">
                                                <div class="flex items-center gap-2 text-brand-600 bg-brand-50 w-max px-3 py-1.5 rounded-lg border border-brand-100">
                                                    <i class="ph-fill ph-check-circle"></i>
                                                    <span class="font-bold"><?= $att_count ?> Signatures</span>
                                                </div>
                                            </td>
                                            <td class="py-4 px-6 text-right">
                                                <a href="<?= htmlspecialchars($mom['file_path']) ?>" target="_blank" class="inline-flex items-center justify-center w-10 h-10 bg-slate-100 text-slate-600 rounded-xl hover:bg-brand-600 hover:text-white transition shadow-sm">
                                                    <i class="ph-bold ph-eye text-lg"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="py-12 text-center text-slate-400">
                                                <i class="ph-fill ph-folder-open text-4xl mb-3"></i>
                                                <p class="font-semibold">No meeting records found.</p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</body>
</html>