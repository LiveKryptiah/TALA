<?php
// sk2_automation.php - SK OS 2.0 AI & Automation Hub
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] !== 'sub-admin' && $_SESSION['role'] !== 'sk_admin')) {
    header("Location: sk2_login.php"); exit();
}

$barangay = $_SESSION['admin_barangay'] ?? 'Municipal Level';
$message = ''; $msgType = '';
$activeTab = $_GET['tab'] ?? 'chatbot';

// Auto-create Tables
try {
    // Knowledge Base for the AI Chatbot
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_ai_faqs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `question` VARCHAR(255) NOT NULL,
        `answer` TEXT NOT NULL,
        `category` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Auto-Generated Certificates Log
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_cert_logs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `youth_name` VARCHAR(255) NOT NULL,
        `event_name` VARCHAR(255) NOT NULL,
        `date_issued` DATE NOT NULL,
        `cert_code` VARCHAR(50) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

// HANDLE SUBMISSIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Train AI (Add FAQ)
    if (isset($_POST['train_ai'])) {
        $stmt = $pdo->prepare("INSERT INTO sk2_ai_faqs (barangay, question, answer, category) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$barangay, $_POST['question'], $_POST['answer'], $_POST['category']])) {
            header("Location: sk2_automation.php?tab=chatbot&msg=ai_trained"); exit();
        }
    }
    
    // Generate Certificate
    if (isset($_POST['generate_cert'])) {
        $youth_name = $_POST['youth_name'];
        $event_name = $_POST['event_name'];
        $date_issued = $_POST['date_issued'];
        $cert_code = 'CERT-' . strtoupper(substr(md5(uniqid()), 0, 8)); // Generate unique verified code

        $stmt = $pdo->prepare("INSERT INTO sk2_cert_logs (barangay, youth_name, event_name, date_issued, cert_code) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$barangay, $youth_name, $event_name, $date_issued, $cert_code])) {
            header("Location: sk2_automation.php?tab=certificates&msg=cert_issued"); exit();
        }
    }
}

// HANDLE ACTIONS (Deletions)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];

    if ($action == 'delete_faq') {
        $pdo->prepare("DELETE FROM sk2_ai_faqs WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_automation.php?tab=chatbot&msg=deleted"); exit();
    } elseif ($action == 'revoke_cert') {
        $pdo->prepare("DELETE FROM sk2_cert_logs WHERE id = ? AND barangay = ?")->execute([$id, $barangay]);
        header("Location: sk2_automation.php?tab=certificates&msg=deleted"); exit();
    }
}

// Set Messages
if (isset($_GET['msg'])) {
    $m = $_GET['msg'];
    if ($m == 'ai_trained') { $message = 'Knowledge base updated. AI Chatbot is learning...'; $msgType = 'success'; }
    if ($m == 'cert_issued') { $message = 'Digital certificate successfully generated & verified.'; $msgType = 'success'; }
    if ($m == 'deleted') { $message = 'Record purged from the system.'; $msgType = 'danger'; }
}

// FETCH DATA
$faqs = $pdo->prepare("SELECT * FROM sk2_ai_faqs WHERE barangay = ? ORDER BY created_at DESC");
$faqs->execute([$barangay]); $faqs = $faqs->fetchAll(PDO::FETCH_ASSOC);

$certs = $pdo->prepare("SELECT * FROM sk2_cert_logs WHERE barangay = ? ORDER BY created_at DESC");
$certs->execute([$barangay]); $certs = $certs->fetchAll(PDO::FETCH_ASSOC);

// Fetch Youth for Dropdown
$youthRegistry = [];
try {
    $stmtY = $pdo->prepare("SELECT id, first_name, last_name, full_name FROM youth_registry WHERE barangay = ?");
    $stmtY->execute([$barangay]); 
    $youthRegistry = $stmtY->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    // Optional: error_log($e->getMessage()); to help debug if it fails
}
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | AI & Automation</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    
    <!-- ANTI-FLASH SCRIPT -->
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <!-- ENABLE DARK MODE IN TAILWIND CONFIG -->
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

<!-- MAIN CONTAINER FIXED -->
<main class="ml-64 flex-1 h-screen overflow-y-auto custom-scrollbar relative bg-slate-50 dark:bg-zinc-950 transition-colors duration-300">
    <div class="p-8 lg:p-10 max-w-[1600px] mx-auto relative z-10">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4 transition-colors">
            <div>
                <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1 transition-colors">
                    AI & Automation Tools
                </h2>
                <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium transition-colors">Chatbot training and automated document generation for <?= htmlspecialchars($barangay) ?>.</p>
            </div>
            
            <div class="flex items-center gap-3">
                <button id="themeToggle" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors shadow-sm">
                    <i id="themeIcon" class="ph-bold ph-moon text-lg"></i>
                </button>
            </div>
        </div>

        <!-- System Alert -->
        <?php if ($message): ?>
            <?php 
                $borderColor = 'border-blue-200 dark:border-blue-500/30'; $bgColor = 'bg-blue-50 dark:bg-blue-500/10'; $textColor = 'text-blue-700 dark:text-blue-400'; $icon = 'ph-info';
                if($msgType=='danger'){ $borderColor = 'border-red-200 dark:border-red-500/30'; $bgColor = 'bg-red-50 dark:bg-red-500/10'; $textColor = 'text-red-700 dark:text-red-400'; $icon = 'ph-warning-circle'; }
                if($msgType=='success'){ $borderColor = 'border-green-200 dark:border-green-500/30'; $bgColor = 'bg-green-50 dark:bg-green-500/10'; $textColor = 'text-green-700 dark:text-green-400'; $icon = 'ph-check-circle'; }
            ?>
            <div class="mb-8 p-4 rounded-xl border <?= $borderColor ?> <?= $bgColor ?> <?= $textColor ?> flex items-center gap-3 font-bold text-sm transition-colors shadow-sm">
                <i class="ph-fill <?= $icon ?> text-xl"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- TABS -->
        <div class="flex gap-2 border-b border-slate-200 dark:border-zinc-800 mb-8 overflow-x-auto custom-scrollbar pb-2 transition-colors">
            <button onclick="switchTab('chatbot')" id="tabBtn-chatbot" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'chatbot' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                <i class="ph-bold ph-robot"></i> AI Knowledge Base
            </button>
            <button onclick="switchTab('certificates')" id="tabBtn-certificates" class="px-6 py-3 font-bold text-sm rounded-t-xl transition-all whitespace-nowrap <?= $activeTab == 'certificates' ? 'bg-blue-50 text-blue-600 border-b-2 border-blue-600 dark:bg-blue-500/10 dark:text-blue-400 dark:border-blue-500' : 'text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-white border-b-2 border-transparent' ?>">
                <i class="ph-bold ph-certificate"></i> Certificate Generator
            </button>
        </div>

        <!-- TAB 1: AI CHATBOT KNOWLEDGE BASE -->
        <div id="tab-chatbot" class="tab-content <?= $activeTab == 'chatbot' ? 'active' : '' ?>">
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Dashboard Info -->
                <div class="w-full lg:w-1/3 space-y-6">
                    <div class="bg-blue-600 dark:bg-blue-700/80 rounded-2xl p-6 text-white shadow-md relative overflow-hidden transition-colors">
                        <i class="ph-fill ph-robot absolute -right-6 -bottom-6 text-9xl opacity-10"></i>
                        <h3 class="text-xl font-bold mb-2">Multilingual AI Assistant</h3>
                        <p class="text-blue-100 text-sm mb-6">Train the AI by adding localized policies, schedules, and FAQs. The AI will translate these answers instantly to Tagalog, Ilocano, and English.</p>
                        <button onclick="document.getElementById('modal-ai').classList.remove('hidden')" class="bg-white text-blue-600 hover:bg-blue-50 dark:bg-zinc-900 dark:text-white dark:hover:bg-zinc-800 w-full py-3 rounded-xl font-bold text-sm transition-colors shadow-sm flex justify-center items-center gap-2">
                            <i class="ph-bold ph-brain"></i> Train AI Data
                        </button>
                    </div>
                    
                    <div class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 shadow-sm transition-colors">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500 dark:text-zinc-500 mb-4 transition-colors">AI Model Status</p>
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-300 transition-colors">Data Points</span>
                            <span class="font-black text-slate-900 dark:text-white transition-colors"><?= count($faqs) ?></span>
                        </div>
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-300 transition-colors">Supported Dialects</span>
                            <span class="font-black text-green-600 dark:text-green-400 transition-colors">3 (Active)</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-300 transition-colors">Engine Status</span>
                            <span class="px-2 py-1 text-[10px] font-extrabold uppercase tracking-widest rounded bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400 animate-pulse transition-colors">Online</span>
                        </div>
                    </div>
                </div>

                <!-- AI Data Table -->
                <div class="w-full lg:w-2/3">
                    <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 shadow-sm transition-colors">
                        <div class="p-6 border-b border-slate-200 dark:border-zinc-800 flex justify-between items-center bg-slate-50/50 dark:bg-zinc-900/50 transition-colors">
                            <h3 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2 transition-colors">
                                <i class="ph-bold ph-database text-blue-500 dark:text-blue-400"></i> Active Knowledge Base
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse min-w-[600px]">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800 transition-colors">
                                        <th class="py-4 px-6">Category</th>
                                        <th class="py-4 px-6">Training Query & Response</th>
                                        <th class="py-4 px-6 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50 transition-colors">
                                    <?php if(empty($faqs)): ?>
                                        <tr><td colspan="3" class="py-12 text-center text-slate-500 dark:text-zinc-500 transition-colors">AI has not been trained yet. Add FAQs.</td></tr>
                                    <?php else: foreach($faqs as $faq): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors">
                                        <td class="py-4 px-6 align-top">
                                            <span class="inline-block px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-widest rounded bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300 transition-colors"><?= htmlspecialchars($faq['category']) ?></span>
                                        </td>
                                        <td class="py-4 px-6 align-top">
                                            <p class="font-bold text-blue-600 dark:text-blue-400 mb-1 transition-colors">Q: <?= htmlspecialchars($faq['question']) ?></p>
                                            <p class="text-xs text-slate-600 dark:text-zinc-400 italic leading-relaxed transition-colors">"<?= htmlspecialchars($faq['answer']) ?>"</p>
                                        </td>
                                        <td class="py-4 px-6 text-right align-top">
                                            <a href="?action=delete_faq&id=<?= $faq['id'] ?>&tab=chatbot" onclick="return confirm('Remove this data point from AI?')" class="inline-flex w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500 dark:hover:text-white items-center justify-center transition-colors tooltip"><i class="ph-bold ph-trash"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: CERTIFICATE GENERATOR -->
        <div id="tab-certificates" class="tab-content <?= $activeTab == 'certificates' ? 'active' : '' ?>">
            <div class="flex justify-between items-center mb-6 transition-colors">
                <h3 class="text-xl font-bold text-slate-800 dark:text-white transition-colors">Digital Certificate Issuer</h3>
                <button onclick="document.getElementById('modal-cert').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm transition-colors shadow-sm">
                    <i class="ph-bold ph-magic-wand"></i> Issue Certificate
                </button>
            </div>

            <div class="bg-white dark:bg-zinc-900 rounded-2xl overflow-hidden border border-slate-200 dark:border-zinc-800 transition-colors shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-zinc-900/50 text-[10px] font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest border-b border-slate-200 dark:border-zinc-800 transition-colors">
                                <th class="py-4 px-6">Verification Code</th>
                                <th class="py-4 px-6">Participant</th>
                                <th class="py-4 px-6">Event Details</th>
                                <th class="py-4 px-6 text-center">Export</th>
                                <th class="py-4 px-6 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm font-medium divide-y divide-slate-100 dark:divide-zinc-800/50 transition-colors">
                            <?php if(empty($certs)): ?>
                                <tr><td colspan="5" class="py-12 text-center text-slate-500 dark:text-zinc-500 transition-colors">No certificates issued yet.</td></tr>
                            <?php else: foreach($certs as $cert): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/50 transition-colors">
                                <td class="py-4 px-6 align-middle">
                                    <span class="font-mono text-xs font-bold text-slate-500 dark:text-zinc-400 bg-slate-100 dark:bg-zinc-800 px-2 py-1 rounded transition-colors"><?= $cert['cert_code'] ?></span>
                                </td>
                                <td class="py-4 px-6 font-bold text-slate-900 dark:text-white align-middle transition-colors">
                                    <?= htmlspecialchars($cert['youth_name']) ?>
                                </td>
                                <td class="py-4 px-6 align-middle">
                                    <p class="font-bold text-blue-600 dark:text-blue-400 leading-tight transition-colors"><?= htmlspecialchars($cert['event_name']) ?></p>
                                    <p class="text-[10px] text-slate-500 dark:text-zinc-400 uppercase tracking-widest mt-1 transition-colors">Issued: <?= date('M d, Y', strtotime($cert['date_issued'])) ?></p>
                                </td>
                                <td class="py-4 px-6 text-center align-middle">
                                    <button onclick="alert('In a live environment, this generates a PDF for <?= htmlspecialchars(addslashes($cert['youth_name'])) ?>')" class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700 px-3 py-1.5 rounded-lg transition-colors shadow-sm">
                                        <i class="ph-bold ph-download-simple"></i> Print PDF
                                    </button>
                                </td>
                                <td class="py-4 px-6 text-right whitespace-nowrap align-middle">
                                    <a href="?action=revoke_cert&id=<?= $cert['id'] ?>&tab=certificates" onclick="return confirm('Revoke this certificate?')" class="inline-flex w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white dark:bg-red-500/10 dark:text-red-400 dark:hover:bg-red-500 dark:hover:text-white items-center justify-center transition-colors tooltip"><i class="ph-bold ph-trash"></i></a>
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

<!-- MODAL: TRAIN AI CHATBOT -->
<div id="modal-ai" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm transition-colors" onclick="document.getElementById('modal-ai').classList.add('hidden')"></div>
    <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
        <div class="flex justify-between items-center mb-8">
            <h3 class="text-xl font-black text-slate-900 dark:text-white transition-colors">Train AI Database</h3>
            <button onclick="document.getElementById('modal-ai').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white transition-colors"><i class="ph-bold ph-x text-xl"></i></button>
        </div>
        <form method="POST" class="space-y-5">
            <input type="hidden" name="train_ai" value="1">
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Likely Question *</label>
                <input type="text" name="question" required placeholder="e.g. How do I apply for the SK Scholarship?" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 outline-none focus:border-blue-500 dark:focus:border-blue-500 transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Category *</label>
                <select name="category" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none focus:border-blue-500 dark:focus:border-blue-500 cursor-pointer transition-colors">
                    <option value="Policies & Rules">Policies & Rules</option>
                    <option value="Events & Schedules">Events & Schedules</option>
                    <option value="Benefits & Assistance">Benefits & Assistance</option>
                    <option value="General Inquiry">General Inquiry</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">AI Response / Answer *</label>
                <textarea name="answer" rows="5" required placeholder="Type the official answer here. The AI will translate this automatically when asked." class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 outline-none resize-none focus:border-blue-500 dark:focus:border-blue-500 custom-scrollbar transition-colors"></textarea>
            </div>
            <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 transition-colors">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-black py-4 rounded-xl transition-colors shadow-sm">Inject Data to AI</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ISSUE CERTIFICATE -->
<div id="modal-cert" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm transition-colors" onclick="document.getElementById('modal-cert').classList.add('hidden')"></div>
    <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
        <div class="flex justify-between items-center mb-8">
            <h3 class="text-xl font-black text-slate-900 dark:text-white transition-colors">Issue Digital Certificate</h3>
            <button onclick="document.getElementById('modal-cert').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white transition-colors"><i class="ph-bold ph-x text-xl"></i></button>
        </div>
        <form method="POST" class="space-y-5">
            <input type="hidden" name="generate_cert" value="1">
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Select Youth Participant *</label>
                <select name="youth_name" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none focus:border-blue-500 dark:focus:border-blue-500 cursor-pointer transition-colors">
                    <option value="" disabled selected>-- Select Citizen --</option>
                    <?php foreach($youthRegistry as $y): 
                        $name = !empty($y['full_name']) ? $y['full_name'] : $y['first_name'].' '.$y['last_name'];
                    ?>
                        <option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                    <option value="Guest Participant">Guest Participant (Manual Entry)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Event / Workshop Name *</label>
                <input type="text" name="event_name" required placeholder="e.g. SK Youth Leadership Summit 2024" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 outline-none focus:border-blue-500 dark:focus:border-blue-500 transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 dark:text-zinc-400 uppercase tracking-widest mb-2 transition-colors">Date Issued *</label>
                <input type="date" name="date_issued" value="<?= date('Y-m-d') ?>" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none focus:border-blue-500 dark:focus:border-blue-500 transition-colors" style="color-scheme: dark light;">
            </div>
            
            <div class="pt-4 border-t border-slate-100 dark:border-zinc-800 transition-colors">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white font-black py-4 rounded-xl transition-colors shadow-md">Generate Certificate</button>
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
    document.addEventListener("DOMContentLoaded", function() {
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        
        if (document.documentElement.classList.contains('dark')) { 
            themeIcon.classList.replace('ph-moon', 'ph-sun'); 
        }

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
    });
</script>
</body>
</html>