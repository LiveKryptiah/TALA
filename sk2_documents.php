<?php
// sk2_documents.php - SK OS 2.0 Mandated Documents & Development Plans
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] !== 'sub-admin' && $_SESSION['role'] !== 'sk_admin')) {
    header("Location: sk2_login.php"); exit();
}

$barangay = $_SESSION['admin_barangay'] ?? 'Municipal Level';
$message = ''; $msgType = '';
$activeTab = $_GET['tab'] ?? 'builder';
$generated_draft = ''; // Variable to hold the AI generated text

// Auto-create Tables
try {
    // Development Plans (CBYDP & ABYIP)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_dev_plans` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `plan_type` ENUM('CBYDP', 'ABYIP') NOT NULL,
        `year_coverage` VARCHAR(50) NOT NULL,
        `center_of_participation` VARCHAR(100) NOT NULL,
        `objective` TEXT NOT NULL,
        `budget_allocation` DECIMAL(15,2) DEFAULT 0.00,
        `status` ENUM('Draft', 'Approved', 'Implemented') DEFAULT 'Draft',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Official Documents Repository
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_official_docs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `doc_type` VARCHAR(100) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `year_tag` INT NOT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// HANDLE SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- NEW: AI DRAFT GENERATOR LOGIC ---
    if (isset($_POST['generate_ai'])) {
        $activeTab = 'aidrafter'; // Keep tab active
        $doc_type = strtoupper($_POST['ai_doc_type']);
        $topic = $_POST['ai_topic'];
        $budget = number_format((float)$_POST['ai_budget'], 2);
        $details = $_POST['ai_details'];

        // Intelligent Template Builder (Can be replaced with cURL to OpenAI/Gemini API later)
        $generated_draft = "REPUBLIC OF THE PHILIPPINES\n";
        $generated_draft .= "PROVINCE OF [PROVINCE]\n";
        $generated_draft .= "MUNICIPALITY/CITY OF [MUNICIPALITY]\n";
        $generated_draft .= "BARANGAY " . strtoupper($barangay) . "\n\n";
        $generated_draft .= "OFFICE OF THE SANGGUNIANG KABATAAN\n\n";
        $generated_draft .= "SK $doc_type NO. 2026-___ \n\n";
        $generated_draft .= "A $doc_type TO APPROVE THE $topic, ALLOCATING FUNDS THEREOF, AND FOR OTHER PURPOSES.\n\n";
        $generated_draft .= "WHEREAS, the Sangguniang Kabataan (SK) is mandated by Republic Act No. 10742, otherwise known as the SK Reform Act of 2015, to initiate programs that enhance the social, political, economic, cultural, and physical development of the youth;\n\n";
        $generated_draft .= "WHEREAS, $details;\n\n";
        $generated_draft .= "WHEREAS, the allocation of ₱$budget is necessary and available under the current SK Fund to ensure the successful implementation of this initiative;\n\n";
        $generated_draft .= "NOW, THEREFORE, BE IT RESOLVED, AS IT IS HEREBY RESOLVED by the Sangguniang Kabataan of Barangay $barangay in a session duly assembled, to approve this $doc_type for the $topic;\n\n";
        $generated_draft .= "RESOLVED FURTHER, that copies of this $doc_type be furnished to the Barangay Council, the Local Youth Development Office (LYDO), and other concerned agencies for their information and guidance.\n\n";
        $generated_draft .= "UNANIMOUSLY APPROVED this " . date('jS') . " day of " . date('F Y') . ".\n\n\n";
        $generated_draft .= "Prepared by:\n\n_______________________\nSK Secretary\n\n\n";
        $generated_draft .= "Approved by:\n\n_______________________\nSK Chairperson";
        
        $message = 'Draft generated successfully. You can now edit and copy it.'; 
        $msgType = 'success';
    }

    // Draft Development Plan
    if (isset($_POST['draft_plan'])) {
        $stmt = $pdo->prepare("INSERT INTO sk2_dev_plans (barangay, plan_type, year_coverage, center_of_participation, objective, budget_allocation) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$barangay, $_POST['plan_type'], $_POST['year_coverage'], $_POST['center_of_participation'], $_POST['objective'], $_POST['budget_allocation']])) {
            header("Location: sk2_documents.php?tab=builder&msg=plan_drafted"); exit();
        }
    }
    
    // Upload Official Document
    if (isset($_POST['upload_doc'])) {
        $file_path = null;
        if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
            $upload_dir = 'uploads/sk_documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $ext = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $filename = 'DOC_' . time() . '_' . rand(1000,9999) . '.pdf';
                $file_path = $upload_dir . $filename;
                move_uploaded_file($_FILES['document_file']['tmp_name'], $file_path);
                
                $stmt = $pdo->prepare("INSERT INTO sk2_official_docs (barangay, doc_type, title, year_tag, file_path) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$barangay, $_POST['doc_type'], $_POST['title'], $_POST['year_tag'], $file_path])) {
                    header("Location: sk2_documents.php?tab=repository&msg=doc_uploaded"); exit();
                }
            } else {
                header("Location: sk2_documents.php?tab=repository&msg=invalid_format"); exit();
            }
        }
    }
}

// HANDLE ACTIONS (Deletions & Status Updates)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];

    if ($action == 'delete_plan') {
        $pdo->prepare("DELETE FROM sk2_dev_plans WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_documents.php?tab=builder&msg=deleted"); exit();
    } elseif ($action == 'update_plan') {
        $status = $_GET['status'];
        $pdo->prepare("UPDATE sk2_dev_plans SET status = ? WHERE id = ? AND barangay = ?")->execute([$status, $id, $barangay]);
        header("Location: sk2_documents.php?tab=builder&msg=updated"); exit();
    } elseif ($action == 'delete_doc') {
        $stmt = $pdo->prepare("SELECT file_path FROM sk2_official_docs WHERE id = ? AND barangay = ?");
        $stmt->execute([$id, $barangay]);
        $doc = $stmt->fetch();
        if ($doc && file_exists($doc['file_path'])) unlink($doc['file_path']); 
        
        $pdo->prepare("DELETE FROM sk2_official_docs WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_documents.php?tab=repository&msg=deleted"); exit();
    }
}

// Set Messages
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m == 'plan_drafted') { $message = 'Development plan successfully drafted.'; $msgType = 'success'; }
    if ($m == 'doc_uploaded') { $message = 'Official document securely uploaded to repository.'; $msgType = 'success'; }
    if ($m == 'invalid_format') { $message = 'Upload failed. Only PDF files are allowed for document previews.'; $msgType = 'danger'; }
    if ($m == 'updated') { $message = 'Plan status updated successfully.'; $msgType = 'success'; }
    if ($m == 'deleted') { $message = 'Record purged from the database.'; $msgType = 'danger'; }
}

// FETCH DATA
$plans = $pdo->prepare("SELECT * FROM sk2_dev_plans WHERE barangay = ? ORDER BY created_at DESC");
$plans->execute([$barangay]); $plans = $plans->fetchAll(PDO::FETCH_ASSOC);

$documents = $pdo->prepare("SELECT * FROM sk2_official_docs WHERE barangay = ? ORDER BY year_tag DESC, created_at DESC");
$documents->execute([$barangay]); $documents = $documents->fetchAll(PDO::FETCH_ASSOC);

// Calculate Totals for Plans
$totalAbyipBudget = 0;
foreach($plans as $p) { if($p['plan_type'] == 'ABYIP') $totalAbyipBudget += $p['budget_allocation']; }
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | Document & Plan Center</title>
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
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex h-screen overflow-hidden selection:bg-blue-500 selection:text-white transition-colors duration-300">

<!-- Sidebar Include -->
<?php include 'sk2_sidebar.php'; ?>

<!-- MAIN CONTAINER WITH DARK MODE BACKGROUND -->
<main class="ml-64 flex-1 h-screen overflow-y-auto custom-scrollbar relative bg-slate-50 dark:bg-zinc-950 transition-colors duration-300">

        <div class="relative z-10 p-8 lg:p-10 max-w-[1600px] mx-auto">
            
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1">
                        Document & Development Center
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium">CBYDP/ABYIP Builder, Document Repository, and AI Drafter for <?= htmlspecialchars($barangay) ?>.</p>
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
                <button onclick="switchTab('builder')" id="tabBtn-builder" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'builder' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-strategy"></i> CBYDP & ABYIP Builder
                </button>
                <button onclick="switchTab('repository')" id="tabBtn-repository" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'repository' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-folders"></i> Document Repository
                </button>
                <!-- NEW AI DRAFTER TAB -->
                <button onclick="switchTab('aidrafter')" id="tabBtn-aidrafter" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'aidrafter' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                    <i class="ph-bold ph-robot text-blue-500"></i> AI Drafter
                </button>
            </div>

            <!-- TAB 1: CBYDP & ABYIP BUILDER -->
            <div id="tab-builder" class="tab-content <?= $activeTab == 'builder' ? 'active' : '' ?>">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-xl font-bold text-slate-800 dark:text-white">Development Plan Workflow</h3>
                        <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mt-1">Total Mapped Budget: <span class="text-blue-600 dark:text-blue-400 text-sm">₱<?= number_format($totalAbyipBudget, 2) ?></span></p>
                    </div>
                    <button onclick="document.getElementById('modal-plan').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                        <i class="ph-bold ph-plus"></i> Draft New Plan
                    </button>
                </div>

                <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 shadow-sm dark:shadow-none">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[1000px]">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800">
                                    <th class="py-4 px-6 w-24">Type</th>
                                    <th class="py-4 px-6">Center of Participation</th>
                                    <th class="py-4 px-6">Objective / Program</th>
                                    <th class="py-4 px-6 text-right">Budget</th>
                                    <th class="py-4 px-6 text-center">Status</th>
                                    <th class="py-4 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50">
                                <?php if(empty($plans)): ?>
                                    <tr><td colspan="6" class="py-12 text-center text-slate-500 dark:text-zinc-500">No development plans drafted yet.</td></tr>
                                <?php else: foreach($plans as $p): 
                                    $typeColor = $p['plan_type'] == 'CBYDP' ? 'bg-blue-600 text-white' : 'bg-emerald-600 text-white';
                                ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors">
                                    <td class="py-4 px-6">
                                        <span class="inline-block px-2.5 py-1 text-[10px] font-black rounded <?= $typeColor ?>"><?= $p['plan_type'] ?></span>
                                        <p class="text-[9px] text-slate-400 dark:text-zinc-500 font-bold mt-1 text-center"><?= $p['year_coverage'] ?></p>
                                    </td>
                                    <td class="py-4 px-6 font-bold text-slate-700 dark:text-zinc-300">
                                        <?= htmlspecialchars($p['center_of_participation']) ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <p class="text-slate-900 dark:text-white line-clamp-2 leading-snug"><?= htmlspecialchars($p['objective']) ?></p>
                                    </td>
                                    <td class="py-4 px-6 text-right font-black text-slate-900 dark:text-white">
                                        <?= $p['budget_allocation'] > 0 ? '₱'.number_format($p['budget_allocation'], 2) : '<span class="text-slate-400">N/A</span>' ?>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <?php if($p['status'] == 'Draft'): ?>
                                            <span class="px-2 py-1 text-[10px] font-extrabold uppercase rounded bg-yellow-100 text-yellow-700 dark:bg-yellow-500/20 dark:text-yellow-400">Draft</span>
                                        <?php elseif($p['status'] == 'Approved'): ?>
                                            <span class="px-2 py-1 text-[10px] font-extrabold uppercase rounded bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-400">Approved</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 text-[10px] font-extrabold uppercase rounded bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400">Implemented</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6 text-right whitespace-nowrap">
                                        <select onchange="window.location.href='?action=update_plan&id=<?= $p['id'] ?>&tab=builder&status='+this.value" class="bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700 rounded-lg text-xs font-bold px-2 py-1.5 mr-1 outline-none cursor-pointer text-slate-700 dark:text-zinc-300">
                                            <option value="Draft" <?= $p['status']=='Draft'?'selected':'' ?>>Draft</option>
                                            <option value="Approved" <?= $p['status']=='Approved'?'selected':'' ?>>Approve</option>
                                            <option value="Implemented" <?= $p['status']=='Implemented'?'selected':'' ?>>Implement</option>
                                        </select>
                                        <a href="?action=delete_plan&id=<?= $p['id'] ?>&tab=builder" onclick="return confirm('Delete this plan?')" class="text-red-400 hover:text-red-600 transition-colors ml-2"><i class="ph-bold ph-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: DOCUMENT REPOSITORY -->
            <div id="tab-repository" class="tab-content <?= $activeTab == 'repository' ? 'active' : '' ?>">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-slate-800 dark:text-white">Centralized Repository</h3>
                    <button onclick="document.getElementById('modal-upload').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                        <i class="ph-bold ph-upload-simple"></i> Upload Document
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if(empty($documents)): ?>
                        <div class="col-span-full text-center py-16 bg-white dark:bg-zinc-900 border border-dashed border-slate-300 dark:border-zinc-800 rounded-2xl">
                            <i class="ph-fill ph-folder-open text-5xl text-slate-300 dark:text-zinc-700 mb-3 inline-block"></i>
                            <p class="text-slate-500 dark:text-zinc-500 font-bold">The repository is empty.</p>
                        </div>
                    <?php else: foreach($documents as $doc): 
                        $docIcon = 'ph-file-pdf';
                        if ($doc['doc_type'] == 'Resolution') $docIcon = 'ph-scroll';
                        if ($doc['doc_type'] == 'Minutes (MOM)') $docIcon = 'ph-users-three';
                        if ($doc['doc_type'] == 'Oath of Office') $docIcon = 'ph-hand-palm';
                    ?>
                    <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 relative group transition-colors shadow-sm flex flex-col h-full hover:border-blue-500 dark:hover:border-blue-500">
                        <div class="flex justify-between items-start mb-4">
                            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 flex items-center justify-center text-2xl">
                                <i class="ph-fill <?= $docIcon ?>"></i>
                            </div>
                            <span class="px-2 py-1 bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400 rounded text-[10px] font-black tracking-widest"><?= $doc['year_tag'] ?></span>
                        </div>
                        
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-zinc-500 mb-1"><?= htmlspecialchars($doc['doc_type']) ?></p>
                        <h4 class="text-lg font-bold text-slate-900 dark:text-white mb-6 leading-tight flex-1"><?= htmlspecialchars($doc['title']) ?></h4>
                        
                        <div class="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-zinc-800">
                            <!-- Preview Button opens Modal -->
                            <button onclick="previewDocument('<?= htmlspecialchars($doc['file_path']) ?>', '<?= htmlspecialchars(addslashes($doc['title'])) ?>')" class="text-sm font-bold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 flex items-center gap-1 transition-colors">
                                <i class="ph-bold ph-eye"></i> Preview PDF
                            </button>
                            
                            <div class="flex gap-3">
                                <a href="<?= htmlspecialchars($doc['file_path']) ?>" download class="text-slate-400 hover:text-blue-500 dark:text-zinc-500 dark:hover:text-blue-400 transition-colors tooltip" title="Download"><i class="ph-bold ph-download-simple text-lg"></i></a>
                                <a href="?action=delete_doc&id=<?= $doc['id'] ?>&tab=repository" onclick="return confirm('Permanently delete this document?')" class="text-slate-400 hover:text-red-500 dark:text-zinc-500 dark:hover:text-red-400 transition-colors tooltip" title="Delete"><i class="ph-bold ph-trash text-lg"></i></a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- TAB 3: NEW AI DOCUMENT DRAFTER -->
            <div id="tab-aidrafter" class="tab-content <?= $activeTab == 'aidrafter' ? 'active' : '' ?>">
                <div class="flex flex-col lg:flex-row gap-8 h-full">
                    
                    <!-- Left Side: Prompt Form -->
                    <div class="w-full lg:w-1/3 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm">
                        <div class="mb-6 border-b border-slate-100 dark:border-zinc-800 pb-4">
                            <h3 class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                                <i class="ph-fill ph-magic-wand text-blue-500"></i> Ordinance Drafter
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">Provide the key details, and let the system structure the legal formatting for you.</p>
                        </div>

                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="generate_ai" value="1">
                            
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-1.5">Document Type</label>
                                <select name="ai_doc_type" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-900 dark:text-white outline-none focus:border-blue-500">
                                    <option value="Resolution">SK Resolution</option>
                                    <option value="Ordinance">SK Ordinance</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-1.5">Title / Subject</label>
                                <input type="text" name="ai_topic" required placeholder="e.g. Barangay E-Sports Tournament" value="<?= isset($_POST['ai_topic']) ? htmlspecialchars($_POST['ai_topic']) : '' ?>" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-900 dark:text-white outline-none focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-1.5">Budget Allocation (₱)</label>
                                <input type="number" step="0.01" name="ai_budget" required placeholder="15000.00" value="<?= isset($_POST['ai_budget']) ? htmlspecialchars($_POST['ai_budget']) : '' ?>" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-900 dark:text-white outline-none focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-1.5">Justification / Details</label>
                                <textarea name="ai_details" rows="4" required placeholder="e.g. To foster camaraderie and sportsmanship among the youth through digital platforms..." class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-900 dark:text-white outline-none resize-none focus:border-blue-500"><?= isset($_POST['ai_details']) ? htmlspecialchars($_POST['ai_details']) : '' ?></textarea>
                            </div>

                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-3.5 rounded-xl transition-colors flex justify-center items-center gap-2 mt-2 shadow-sm">
                                <i class="ph-bold ph-robot"></i> Generate Draft
                            </button>
                        </form>
                    </div>

                    <!-- Right Side: Generated Output -->
                    <div class="w-full lg:w-2/3 flex flex-col bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl shadow-sm overflow-hidden h-[600px] lg:h-auto">
                        <div class="px-6 py-4 border-b border-slate-100 dark:border-zinc-800 bg-slate-50/50 dark:bg-zinc-900/50 flex justify-between items-center">
                            <span class="text-sm font-bold text-slate-700 dark:text-zinc-300">Live Editor</span>
                            <button onclick="copyDraft()" class="text-xs font-bold bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-600 dark:text-zinc-300 px-3 py-1.5 rounded-lg hover:text-blue-600 dark:hover:text-blue-400 transition-colors flex items-center gap-1">
                                <i class="ph-bold ph-copy"></i> Copy to Clipboard
                            </button>
                        </div>
                        
                        <!-- The Editable Output Area -->
                        <textarea id="ai-output" class="flex-1 w-full p-6 bg-transparent border-none outline-none resize-none text-sm font-medium text-slate-800 dark:text-zinc-200 custom-scrollbar leading-relaxed" placeholder="Your generated document will appear here..."><?= htmlspecialchars($generated_draft) ?></textarea>
                        
                        <?php if(!empty($generated_draft)): ?>
                        <div class="p-4 bg-blue-50 dark:bg-blue-500/10 border-t border-blue-100 dark:border-blue-500/20 flex justify-between items-center">
                            <span class="text-xs font-bold text-blue-700 dark:text-blue-400 flex items-center gap-1"><i class="ph-fill ph-info"></i> Review before finalizing.</span>
                            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold px-4 py-2 rounded-lg transition-colors shadow-sm">
                                Print to PDF
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL: DRAFT PLAN -->
    <div id="modal-plan" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm" onclick="document.getElementById('modal-plan').classList.add('hidden')"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Draft Plan Builder</h3>
                <button onclick="document.getElementById('modal-plan').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white"><i class="ph-bold ph-x text-xl"></i></button>
            </div>
            <form method="POST" class="space-y-5">
                <input type="hidden" name="draft_plan" value="1">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Plan Type *</label>
                        <select name="plan_type" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none focus:border-blue-500">
                            <option value="CBYDP">CBYDP (3-Year)</option>
                            <option value="ABYIP">ABYIP (Annual)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Year Coverage *</label>
                        <input type="text" name="year_coverage" required placeholder="e.g. 2026-2028 or 2026" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none focus:border-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Center of Participation *</label>
                    <select name="center_of_participation" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none focus:border-blue-500">
                        <option value="Health">Health</option>
                        <option value="Education">Education</option>
                        <option value="Economic Empowerment">Economic Empowerment</option>
                        <option value="Social Inclusion & Equity">Social Inclusion & Equity</option>
                        <option value="Peace-Building & Security">Peace-Building & Security</option>
                        <option value="Governance">Governance</option>
                        <option value="Active Citizenship">Active Citizenship</option>
                        <option value="Environment">Environment</option>
                        <option value="Global Mobility">Global Mobility</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Objective / Program Narrative *</label>
                    <textarea name="objective" rows="4" required placeholder="Describe the program's goal and implementation strategy." class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none resize-none focus:border-blue-500"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Budget Allocation (₱)</label>
                    <input type="number" step="0.01" name="budget_allocation" placeholder="0.00" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none focus:border-blue-500">
                </div>
                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition-colors">Save to Plan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: UPLOAD DOCUMENT -->
    <div id="modal-upload" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm" onclick="document.getElementById('modal-upload').classList.add('hidden')"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            <div class="flex justify-between items-center mb-8">
                <h3 class="text-xl font-black text-slate-900 dark:text-white">Upload Document</h3>
                <button onclick="document.getElementById('modal-upload').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white"><i class="ph-bold ph-x text-xl"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="space-y-5">
                <input type="hidden" name="upload_doc" value="1">
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Document Title / Subject *</label>
                    <input type="text" name="title" required placeholder="e.g. Resolution Approving ABYIP 2026" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none focus:border-blue-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Doc Type *</label>
                        <select name="doc_type" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none focus:border-blue-500">
                            <option value="Resolution">Resolution</option>
                            <option value="Ordinance">Ordinance</option>
                            <option value="Minutes (MOM)">Minutes (MOM)</option>
                            <option value="Oath of Office">Oath of Office</option>
                            <option value="Certificate">Certificate</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">Year Tag *</label>
                        <input type="number" name="year_tag" value="<?= date('Y') ?>" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none focus:border-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-2">PDF File * <span class="text-blue-500 lowercase normal-case">(.pdf only)</span></label>
                    <input type="file" name="document_file" accept="application/pdf" required class="w-full text-sm text-slate-500 dark:text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 dark:file:bg-zinc-800 file:text-slate-700 dark:file:text-white hover:file:bg-slate-200 cursor-pointer">
                </div>
                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition-colors">Upload to Repository</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: PDF AUTOMATED PREVIEWER -->
    <div id="modal-preview" class="fixed inset-0 z-[60] hidden">
        <div class="absolute inset-0 bg-slate-900/90 dark:bg-black/95 backdrop-blur-md" onclick="closePreview()"></div>
        <div class="absolute inset-4 md:inset-10 lg:inset-20 bg-white dark:bg-zinc-900 rounded-2xl shadow-2xl flex flex-col overflow-hidden border border-slate-200 dark:border-zinc-800 transition-colors">
            <div class="p-4 border-b border-slate-200 dark:border-zinc-800 flex justify-between items-center bg-slate-50 dark:bg-zinc-900">
                <h3 id="preview-title" class="text-lg font-black text-slate-900 dark:text-white flex items-center gap-2 truncate">
                    <i class="ph-bold ph-file-pdf text-red-500"></i> Document Preview
                </h3>
                <button onclick="closePreview()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-200 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 hover:bg-red-500 hover:text-white transition-colors shrink-0">
                    <i class="ph-bold ph-x"></i>
                </button>
            </div>
            <div class="flex-1 bg-slate-200 dark:bg-zinc-950 relative">
                <iframe id="preview-iframe" src="" class="w-full h-full border-0"></iframe>
                <div id="preview-loader" class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                    <i class="ph-bold ph-spinner animate-spin text-4xl text-blue-500 mb-2"></i>
                    <p class="text-sm font-bold text-slate-500 dark:text-zinc-400">Loading Document Engine...</p>
                </div>
            </div>
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

        // Copy AI Draft to Clipboard
        function copyDraft() {
            const output = document.getElementById('ai-output');
            output.select();
            document.execCommand('copy');
            alert('Document copied to clipboard!');
        }

        // Automated PDF Preview Logic
        function previewDocument(filePath, title) {
            const iframe = document.getElementById('preview-iframe');
            const loader = document.getElementById('preview-loader');
            
            document.getElementById('preview-title').innerHTML = `<i class="ph-bold ph-file-pdf text-red-500"></i> ${title}`;
            document.getElementById('modal-preview').classList.remove('hidden');
            
            loader.style.display = 'flex';
            iframe.style.opacity = '0';
            iframe.src = filePath + '#toolbar=0'; 
            
            iframe.onload = function() {
                loader.style.display = 'none';
                iframe.style.opacity = '1';
            };
        }

        function closePreview() {
            document.getElementById('modal-preview').classList.add('hidden');
            document.getElementById('preview-iframe').src = ''; 
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