<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db_connect.php';

// ==========================================
// 1. AJAX HANDLER: UPDATE DEPED STATUS
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'verify_deped') {
    header('Content-Type: application/json');
    try {
        $youth_id = $_POST['youth_id'];
        $new_status = $_POST['deped_status'];

        $stmt = $pdo->prepare("UPDATE youth_profiles SET deped_status = :status WHERE youth_id = :id");
        $stmt->execute([':status' => $new_status, ':id' => $youth_id]);
        
        echo json_encode(['success' => true, 'message' => 'Verified successfully!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ==========================================
// 2. FETCH RECORDS FOR DEPED TO REVIEW
// ==========================================
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status_filter'] ?? '';

$query = "SELECT youth_id, full_name, barangay, school_name, education_level, deped_status FROM youth_profiles WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (full_name LIKE :search OR youth_id LIKE :search OR school_name LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($filter_status) {
    // If they want to see only unverified, or specific statuses
    if ($filter_status === 'Unverified') {
        $query .= " AND (deped_status IS NULL OR deped_status = '')";
    } else {
        $query .= " AND deped_status = :status";
        $params[':status'] = $filter_status;
    }
}

$query .= " ORDER BY id DESC LIMIT 200";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$youths = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <title>DepEd Verification Portal | TALA OS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background: #f8fafc; }</style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="text-slate-800 antialiased selection:bg-purple-500 selection:text-white">

    <!-- Navbar for DepEd -->
    <div class="bg-white border-b border-slate-200 px-8 py-4 flex justify-between items-center sticky top-0 z-50">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center"><i class="ph-bold ph-books text-2xl"></i></div>
            <div>
                <h1 class="text-lg font-extrabold text-slate-900 leading-tight">DepEd Control Center</h1>
                <p class="text-[10px] font-bold tracking-widest uppercase text-slate-400">TALA OS Verification</p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-sm font-bold text-slate-600"><i class="ph-fill ph-user-circle"></i> DepEd Admin</span>
            <a href="logout.php" class="text-sm font-bold text-red-500 hover:text-red-700">Logout</a>
        </div>
    </div>

    <div class="p-8 max-w-[1400px] mx-auto">
        
        <!-- Filters & Search -->
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <h2 class="text-2xl font-black text-slate-800">Student Verification</h2>
            
            <form method="GET" action="deped_verification.php" class="flex gap-2 bg-white p-2 rounded-2xl border border-slate-200 shadow-sm">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search student or school..." class="px-4 py-2 text-sm font-semibold focus:outline-none w-64 bg-transparent border-r border-slate-100">
                <select name="status_filter" class="px-4 py-2 text-sm font-semibold focus:outline-none bg-transparent cursor-pointer">
                    <option value="">All Statuses</option>
                    <option value="Unverified" <?= $filter_status === 'Unverified' ? 'selected' : '' ?>>Needs Verification</option>
                    <option value="Enrolled" <?= $filter_status === 'Enrolled' ? 'selected' : '' ?>>Enrolled</option>
                    <option value="Drop out" <?= $filter_status === 'Drop out' ? 'selected' : '' ?>>Drop out</option>
                    <option value="ALS" <?= $filter_status === 'ALS' ? 'selected' : '' ?>>ALS</option>
                </select>
                <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded-xl transition-colors text-sm">Filter</button>
            </form>
        </div>

        <!-- Verification Table -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200">
                        <th class="p-5 text-[10px] font-black uppercase tracking-widest text-slate-400">Student Info</th>
                        <th class="p-5 text-[10px] font-black uppercase tracking-widest text-slate-400">Declared School/Level</th>
                        <th class="p-5 text-[10px] font-black uppercase tracking-widest text-slate-400">DepEd Verification Control</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($youths)): ?>
                        <tr><td colspan="3" class="p-10 text-center text-slate-500 font-medium">No students found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($youths as $y): ?>
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="p-5">
                                <span class="font-mono font-bold text-slate-400 text-[10px] block mb-0.5"><?= htmlspecialchars($y['youth_id']) ?></span>
                                <span class="font-bold text-slate-900 block"><?= htmlspecialchars($y['full_name']) ?></span>
                                <span class="text-xs font-semibold text-slate-500"><i class="ph-fill ph-map-pin"></i> <?= htmlspecialchars($y['barangay']) ?></span>
                            </td>
                            <td class="p-5">
                                <span class="font-bold text-slate-700 block text-sm"><?= htmlspecialchars($y['school_name'] ?: 'Not Specified') ?></span>
                                <span class="text-xs font-semibold text-slate-500"><?= htmlspecialchars($y['education_level'] ?: 'No Level Declared') ?></span>
                            </td>
                            <td class="p-5">
                                <div class="flex items-center gap-2">
                                    <select id="status_<?= $y['youth_id'] ?>" class="bg-slate-100 border border-slate-200 text-slate-700 font-bold text-sm rounded-lg px-4 py-2 focus:ring-2 focus:ring-purple-500 outline-none cursor-pointer">
                                        <option value="" <?= empty($y['deped_status']) ? 'selected' : '' ?>>Unverified</option>
                                        <option value="Enrolled" <?= $y['deped_status'] === 'Enrolled' ? 'selected' : '' ?>>Enrolled</option>
                                        <option value="Drop out" <?= $y['deped_status'] === 'Drop out' ? 'selected' : '' ?>>Drop out</option>
                                        <option value="ALS" <?= $y['deped_status'] === 'ALS' ? 'selected' : '' ?>>ALS</option>
                                    </select>
                                    
                                    <button onclick="updateStatus('<?= $y['youth_id'] ?>')" id="btn_<?= $y['youth_id'] ?>" class="bg-slate-900 text-white font-bold px-4 py-2 rounded-lg text-sm hover:bg-purple-600 transition-colors flex items-center gap-1 shadow-sm">
                                        <i class="ph-bold ph-floppy-disk"></i> Save
                                    </button>
                                </div>
                                <div id="msg_<?= $y['youth_id'] ?>" class="text-[10px] font-bold mt-1 h-3 transition-all"></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- AJAX LOGIC -->
    <script>
        async function updateStatus(youthId) {
            const statusSelect = document.getElementById('status_' + youthId);
            const btn = document.getElementById('btn_' + youthId);
            const msgBox = document.getElementById('msg_' + youthId);
            const newStatus = statusSelect.value;

            // UI Loading state
            const originalBtnHtml = btn.innerHTML;
            btn.innerHTML = '<i class="ph-bold ph-spinner animate-spin"></i> Saving...';
            btn.disabled = true;
            btn.classList.add('opacity-50');

            // Prepare Data
            const formData = new FormData();
            formData.append('action', 'verify_deped');
            formData.append('youth_id', youthId);
            formData.append('deped_status', newStatus);

            try {
                const response = await fetch('deped_verification.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if(result.success) {
                    msgBox.innerHTML = '<span class="text-emerald-500"><i class="ph-fill ph-check-circle"></i> Verified successfully</span>';
                    btn.classList.replace('bg-slate-900', 'bg-emerald-500');
                    btn.classList.replace('hover:bg-purple-600', 'hover:bg-emerald-600');
                    setTimeout(() => {
                        btn.classList.replace('bg-emerald-500', 'bg-slate-900');
                        btn.classList.replace('hover:bg-emerald-600', 'hover:bg-purple-600');
                        msgBox.innerHTML = '';
                    }, 2000);
                } else {
                    msgBox.innerHTML = '<span class="text-red-500"><i class="ph-fill ph-warning-circle"></i> Error updating</span>';
                }
            } catch (error) {
                msgBox.innerHTML = '<span class="text-red-500"><i class="ph-fill ph-warning-circle"></i> Connection error</span>';
            } finally {
                // Restore Button
                btn.innerHTML = originalBtnHtml;
                btn.disabled = false;
                btn.classList.remove('opacity-50');
            }
        }
    </script>
</body>
</html>