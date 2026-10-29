<?php
// dts.php - Document Tracking System
session_start();
require 'db_connect.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'sub-admin')) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$barangay = $_SESSION['barangay_assigned'] ?? 'Municipal Level';

// Auto-create table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sk_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        barangay VARCHAR(255) NOT NULL,
        doc_type VARCHAR(100) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        status VARCHAR(50) DEFAULT 'Pending',
        admin_feedback TEXT NULL,
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

$message = '';

// Handle Upload (SK only)
if ($role === 'sub-admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc'])) {
    $doc_type = $_POST['doc_type'];
    $upload_dir = 'uploads/dts/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $file_name = time() . '_' . preg_replace('/[^A-Za-z0-9]/', '', $barangay) . '_' . $doc_type . '.pdf';
            $file_path = $upload_dir . $file_name;
            move_uploaded_file($_FILES['document']['tmp_name'], $file_path);
            
            $stmt = $pdo->prepare("INSERT INTO sk_documents (barangay, doc_type, file_path) VALUES (?, ?, ?)");
            $stmt->execute([$barangay, $doc_type, $file_path]);
            $message = "<div class='bg-emerald-50 text-emerald-700 p-4 rounded-xl mb-6 font-bold text-sm'>Document uploaded successfully!</div>";
        } else {
            $message = "<div class='bg-red-50 text-red-700 p-4 rounded-xl mb-6 font-bold text-sm'>Only PDF files are allowed.</div>";
        }
    }
}

// Handle Admin Review (Admin only)
if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_doc'])) {
    $doc_id = $_POST['doc_id'];
    $status = $_POST['status'];
    $feedback = $_POST['feedback'];
    
    $stmt = $pdo->prepare("UPDATE sk_documents SET status = ?, admin_feedback = ? WHERE id = ?");
    $stmt->execute([$status, $feedback, $doc_id]);
    $message = "<div class='bg-emerald-50 text-emerald-700 p-4 rounded-xl mb-6 font-bold text-sm'>Document status updated to $status.</div>";
}

// Fetch Documents
if ($role === 'admin') {
    $docs = $pdo->query("SELECT * FROM sk_documents ORDER BY submitted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT * FROM sk_documents WHERE barangay = ? ORDER BY submitted_at DESC");
    $stmt->execute([$barangay]);
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DTS | TALA OS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style> body { font-family: 'Plus Jakarta Sans', sans-serif; } </style>
</head>
<body class="bg-slate-50 text-slate-800">
    <?php include ($role === 'admin' ? 'sidebar_admin.php' : 'sidebar_sk.php'); ?>
    
    <div class="ml-64 p-8 max-w-7xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-extrabold text-slate-900">Document Tracking System</h1>
            <p class="text-slate-500 font-medium mt-1">Manage ABYIP, CBYIP, and Resolutions seamlessly.</p>
        </div>

        <?= $message ?>

        <?php if ($role === 'sub-admin'): ?>
        <!-- SK Upload Section -->
        <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm mb-8">
            <h2 class="text-xl font-bold mb-4">Submit New Document</h2>
            <form method="POST" enctype="multipart/form-data" class="flex items-end gap-4">
                <input type="hidden" name="upload_doc" value="1">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Document Type</label>
                    <select name="doc_type" required class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-slate-50">
                        <option value="ABYIP">Annual Barangay Youth Investment Program (ABYIP)</option>
                        <option value="CBYIP">Comprehensive Barangay Youth Dev Plan (CBYIP)</option>
                        <option value="Annual Budget">Annual Budget</option>
                        <option value="Resolution">Resolution</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">PDF File</label>
                    <input type="file" name="document" accept=".pdf" required class="w-full border border-slate-200 rounded-xl px-4 py-2.5 bg-slate-50 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-brand-50 file:text-brand-700 cursor-pointer">
                </div>
                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 px-8 rounded-xl transition shadow-md">
                    Upload
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Documents Table -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase">
                    <tr>
                        <th class="p-5">Document</th>
                        <?php if($role === 'admin'): ?><th class="p-5">Barangay</th><?php endif; ?>
                        <th class="p-5">Status</th>
                        <th class="p-5">Feedback</th>
                        <th class="p-5">Date</th>
                        <?php if($role === 'admin'): ?><th class="p-5">Action</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php foreach($docs as $d): ?>
                    <tr class="hover:bg-slate-50 transition">
                        <td class="p-5">
                            <a href="<?= htmlspecialchars($d['file_path']) ?>" target="_blank" class="font-bold text-brand-600 hover:underline flex items-center gap-2">
                                <i class="ph-fill ph-file-pdf text-xl text-red-500"></i> <?= htmlspecialchars($d['doc_type']) ?>
                            </a>
                        </td>
                        <?php if($role === 'admin'): ?>
                            <td class="p-5 font-semibold"><?= htmlspecialchars($d['barangay']) ?></td>
                        <?php endif; ?>
                        <td class="p-5">
                            <?php 
                                $color = $d['status'] === 'Approved' ? 'text-emerald-700 bg-emerald-50 border-emerald-200' : 
                                        ($d['status'] === 'For Revision' ? 'text-red-700 bg-red-50 border-red-200' : 'text-amber-700 bg-amber-50 border-amber-200');
                            ?>
                            <span class="px-2.5 py-1 rounded-md text-xs font-bold border <?= $color ?>"><?= htmlspecialchars($d['status']) ?></span>
                        </td>
                        <td class="p-5 text-slate-500 italic max-w-xs truncate"><?= htmlspecialchars($d['admin_feedback'] ?? 'None') ?></td>
                        <td class="p-5 font-medium text-slate-500"><?= date('M j, Y', strtotime($d['submitted_at'])) ?></td>
                        
                        <?php if($role === 'admin'): ?>
                        <td class="p-5">
                            <form method="POST" class="flex gap-2">
                                <input type="hidden" name="review_doc" value="1">
                                <input type="hidden" name="doc_id" value="<?= $d['id'] ?>">
                                <select name="status" class="border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-bold bg-white">
                                    <option value="Pending" <?= $d['status']=='Pending'?'selected':'' ?>>Pending</option>
                                    <option value="For Revision" <?= $d['status']=='For Revision'?'selected':'' ?>>For Revision</option>
                                    <option value="Approved" <?= $d['status']=='Approved'?'selected':'' ?>>Approved</option>
                                </select>
                                <input type="text" name="feedback" placeholder="Feedback..." value="<?= htmlspecialchars($d['admin_feedback'] ?? '') ?>" class="border border-slate-200 rounded-lg px-2 py-1 text-xs w-32">
                                <button type="submit" class="bg-slate-800 text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-slate-700">Save</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>