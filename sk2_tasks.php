<?php
// sk2_tasks.php - SK OS 2.0 Council Kanban Board (Task Tracker)
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || ($_SESSION['role'] !== 'sub-admin' && $_SESSION['role'] !== 'sk_admin')) {
    header("Location: sk2_login.php"); exit();
}

$barangay = $_SESSION['admin_barangay'] ?? 'Balug';
$message = ''; $msgType = '';

// 1. BACKGROUND AJAX HANDLER (For Drag and Drop)
// This intercepts the silent background request when a card is dragged and dropped
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajax_update_task'])) {
    $taskId = $_POST['task_id'];
    $newStatus = $_POST['new_status'];
    
    $stmt = $pdo->prepare("UPDATE sk2_tasks SET status = ? WHERE id = ? AND barangay = ?");
    if($stmt->execute([$newStatus, $taskId, $barangay])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit(); // Stop loading the rest of the page for AJAX requests
}

// 2. BULLETPROOF DATABASE SETUP
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk2_tasks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `barangay` VARCHAR(100) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT NULL,
        `assignee` VARCHAR(100) NOT NULL,
        `priority` ENUM('Low', 'Medium', 'High') DEFAULT 'Medium',
        `status` ENUM('todo', 'in_progress', 'done') DEFAULT 'todo',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// 3. SEED MOCK DATA (If empty, so the board looks great instantly)
try {
    $check = $pdo->prepare("SELECT COUNT(*) FROM sk2_tasks WHERE barangay = ?");
    $check->execute([$barangay]);
    if ($check->fetchColumn() == 0) {
        $tasks = [
            ["Draft resolution for Liga ng Barangay", "Format the resolution allocating 50k for the summer league.", "Kagawad Mark", "High", "todo"],
            ["Secure Mayor's Permit for E-Sports", "Visit munisipyo to get the stamp for the MLBB tournament.", "Kagawad Anna", "Medium", "todo"],
            ["Buy snacks and water for Coastal Clean-up", "Budget is 2k. Get pancit and bottled water.", "Kagawad John", "Low", "in_progress"],
            ["Post ABYIP on Transparency Board", "Print the signed ABYIP and post it on the barangay hall bulletin.", "SK Secretary", "Medium", "in_progress"],
            ["Distribute SPES forms to Purok 1 & 2", "Give forms to the youth leaders in those areas.", "Kagawad Sarah", "High", "done"]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO sk2_tasks (barangay, title, description, assignee, priority, status) VALUES (?, ?, ?, ?, ?, ?)");
        foreach($tasks as $t) {
            $stmt->execute([$barangay, $t[0], $t[1], $t[2], $t[3], $t[4]]);
        }
    }
} catch (Exception $e) {}

// 4. HANDLE MANUAL ADD TASK
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_task'])) {
    $stmt = $pdo->prepare("INSERT INTO sk2_tasks (barangay, title, description, assignee, priority) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$barangay, $_POST['title'], $_POST['description'], $_POST['assignee'], $_POST['priority']])) {
        header("Location: sk2_tasks.php?msg=added"); exit();
    }
}

// 5. HANDLE TASK DELETION
if (isset($_GET['action']) && $_GET['action'] == 'delete') {
    $pdo->prepare("DELETE FROM sk2_tasks WHERE id = ? AND barangay = ?")->execute([$_GET['id'], $barangay]);
    header("Location: sk2_tasks.php?msg=deleted"); exit();
}

// Set Messages
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'added') { $message = 'Task assigned successfully.'; $msgType = 'success'; }
    if ($_GET['msg'] == 'deleted') { $message = 'Task removed from board.'; $msgType = 'danger'; }
}

// 6. FETCH DATA
$tasks = ['todo' => [], 'in_progress' => [], 'done' => []];
try {
    $stmt = $pdo->prepare("SELECT * FROM sk2_tasks WHERE barangay = ? ORDER BY priority DESC, created_at DESC");
    $stmt->execute([$barangay]); 
    $allTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group tasks by their current status
    foreach($allTasks as $task) {
        $tasks[$task['status']][] = $task;
    }
} catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK OS 2.0 | Council Kanban Board</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- TAILWIND DARK MODE CONFIGURATION -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] } } }
        }
    </script>

    <!-- Phosphor Icons & SortableJS (For Drag and Drop) -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

    <?php include 'sk2_theme_init.php'; ?>

    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        
        /* Kanban Styling */
        .kanban-ghost { opacity: 0.4; background-color: #f1f5f9; border: 2px dashed #94a3b8; }
        .dark .kanban-ghost { background-color: #27272a; border-color: #52525b; }
        .kanban-drag { cursor: grab; }
        .kanban-drag:active { cursor: grabbing; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 dark:bg-zinc-950 dark:text-zinc-100 flex h-screen overflow-hidden selection:bg-blue-500 selection:text-white transition-colors duration-300">

<!-- Sidebar Include -->
<?php include 'sk2_sidebar.php'; ?>

<!-- MAIN CONTAINER WITH DARK MODE BACKGROUND -->
<main class="ml-64 flex-1 h-screen overflow-y-auto custom-scrollbar relative bg-slate-50 dark:bg-zinc-950 transition-colors duration-300">

        <div class="relative z-10 p-8 lg:p-10 max-w-[1600px] mx-auto min-h-screen flex flex-col">
            
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-8 gap-4 shrink-0">
                <div>
                    <h2 class="text-3xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-3 mb-1">
                        Council Action Board
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400 font-medium">Drag and drop tasks to hold Kagawads accountable and track progress.</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <button id="themeToggle" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 transition-colors">
                        <i id="themeIcon" class="ph-bold ph-moon text-lg"></i>
                    </button>
                    <button onclick="document.getElementById('modal-add').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl font-bold text-sm transition-colors shadow-sm flex items-center gap-2">
                        <i class="ph-bold ph-plus"></i> Assign Task
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <?php 
                    $borderColor = 'border-blue-200 dark:border-blue-500/30'; $bgColor = 'bg-blue-50 dark:bg-blue-500/10'; $textColor = 'text-blue-700 dark:text-blue-400'; $icon = 'ph-info';
                    if($msgType=='danger'){ $borderColor = 'border-red-200 dark:border-red-500/30'; $bgColor = 'bg-red-50 dark:bg-red-500/10'; $textColor = 'text-red-700 dark:text-red-400'; $icon = 'ph-warning-circle'; }
                    if($msgType=='success'){ $borderColor = 'border-green-200 dark:border-green-500/30'; $bgColor = 'bg-green-50 dark:bg-green-500/10'; $textColor = 'text-green-700 dark:text-green-400'; $icon = 'ph-check-circle'; }
                ?>
                <div class="mb-8 p-4 rounded-xl border <?= $borderColor ?> <?= $bgColor ?> <?= $textColor ?> flex items-center gap-3 font-bold text-sm transition-colors shrink-0">
                    <i class="ph-fill <?= $icon ?> text-xl"></i> <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- KANBAN BOARD GRID -->
            <div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-6 pb-10">
                
                <!-- COLUMN: TO DO -->
                <div class="flex flex-col bg-slate-100/50 dark:bg-zinc-900/50 rounded-2xl border border-slate-200 dark:border-zinc-800/50 overflow-hidden h-full max-h-[70vh]">
                    <div class="p-4 border-b border-slate-200 dark:border-zinc-800 bg-slate-100 dark:bg-zinc-900 flex justify-between items-center sticky top-0 z-10">
                        <h3 class="font-black text-slate-800 dark:text-white flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span> To-Do 
                        </h3>
                        <span class="bg-white dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 text-xs font-bold px-2.5 py-1 rounded-lg border border-slate-200 dark:border-zinc-700 shadow-sm" id="count-todo"><?= count($tasks['todo']) ?></span>
                    </div>
                    <!-- The Dropzone Container -->
                    <div id="todo" class="flex-1 p-3 overflow-y-auto custom-scrollbar space-y-3 kanban-container min-h-[150px]">
                        <?php foreach($tasks['todo'] as $t) { renderTaskCard($t); } ?>
                    </div>
                </div>

                <!-- COLUMN: IN PROGRESS -->
                <div class="flex flex-col bg-blue-50/50 dark:bg-blue-950/20 rounded-2xl border border-blue-100 dark:border-blue-900/30 overflow-hidden h-full max-h-[70vh]">
                    <div class="p-4 border-b border-blue-100 dark:border-blue-900/50 bg-blue-50 dark:bg-blue-950/40 flex justify-between items-center sticky top-0 z-10">
                        <h3 class="font-black text-blue-800 dark:text-blue-400 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse"></span> In Progress 
                        </h3>
                        <span class="bg-white dark:bg-blue-900 text-blue-600 dark:text-blue-300 text-xs font-bold px-2.5 py-1 rounded-lg border border-blue-200 dark:border-blue-800 shadow-sm" id="count-in_progress"><?= count($tasks['in_progress']) ?></span>
                    </div>
                    <!-- The Dropzone Container -->
                    <div id="in_progress" class="flex-1 p-3 overflow-y-auto custom-scrollbar space-y-3 kanban-container min-h-[150px]">
                        <?php foreach($tasks['in_progress'] as $t) { renderTaskCard($t); } ?>
                    </div>
                </div>

                <!-- COLUMN: DONE -->
                <div class="flex flex-col bg-emerald-50/50 dark:bg-emerald-950/20 rounded-2xl border border-emerald-100 dark:border-emerald-900/30 overflow-hidden h-full max-h-[70vh]">
                    <div class="p-4 border-b border-emerald-100 dark:border-emerald-900/50 bg-emerald-50 dark:bg-emerald-950/40 flex justify-between items-center sticky top-0 z-10">
                        <h3 class="font-black text-emerald-800 dark:text-emerald-400 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Done 
                        </h3>
                        <span class="bg-white dark:bg-emerald-900 text-emerald-600 dark:text-emerald-300 text-xs font-bold px-2.5 py-1 rounded-lg border border-emerald-200 dark:border-emerald-800 shadow-sm" id="count-done"><?= count($tasks['done']) ?></span>
                    </div>
                    <!-- The Dropzone Container -->
                    <div id="done" class="flex-1 p-3 overflow-y-auto custom-scrollbar space-y-3 kanban-container min-h-[150px]">
                        <?php foreach($tasks['done'] as $t) { renderTaskCard($t); } ?>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- MODAL: ADD TASK -->
    <div id="modal-add" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-slate-900/50 dark:bg-black/80 backdrop-blur-sm" onclick="document.getElementById('modal-add').classList.add('hidden')"></div>
        <div class="absolute inset-y-0 right-0 w-full max-w-md bg-white dark:bg-zinc-900 border-l border-slate-200 dark:border-zinc-800 shadow-2xl p-6 sm:p-8 overflow-y-auto custom-scrollbar transform transition-transform duration-300 translate-x-0">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-black text-slate-900 dark:text-white flex items-center gap-2"><i class="ph-bold ph-kanban text-blue-500"></i> New Assignment</h3>
                <button onclick="document.getElementById('modal-add').classList.add('hidden')" class="text-slate-400 dark:text-zinc-500 hover:text-slate-600 dark:hover:text-white"><i class="ph-bold ph-x text-xl"></i></button>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="add_task" value="1">
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-1.5">Task Title *</label>
                    <input type="text" name="title" required placeholder="e.g. Draft Budget Resolution" class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-1.5">Assign To *</label>
                    <!-- In a real system, you could populate this from a Kagawad database table -->
                    <select name="assignee" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                        <option value="SK Chairperson">SK Chairperson</option>
                        <option value="SK Secretary">SK Secretary</option>
                        <option value="SK Treasurer">SK Treasurer</option>
                        <option value="Kagawad 1">Kagawad 1</option>
                        <option value="Kagawad 2">Kagawad 2</option>
                        <option value="Kagawad 3">Kagawad 3</option>
                        <option value="Kagawad 4">Kagawad 4</option>
                        <option value="Kagawad 5">Kagawad 5</option>
                        <option value="Kagawad 6">Kagawad 6</option>
                        <option value="Kagawad 7">Kagawad 7</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-1.5">Priority Level</label>
                    <select name="priority" required class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none appearance-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20">
                        <option value="Low">🟢 Low Priority</option>
                        <option value="Medium" selected>🟡 Medium Priority</option>
                        <option value="High">🔴 High (Urgent)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 dark:text-zinc-500 uppercase tracking-widest mb-1.5">Instructions / Notes</label>
                    <textarea name="description" rows="3" placeholder="Provide extra details for the assignee..." class="w-full bg-slate-50 dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white outline-none resize-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"></textarea>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-zinc-800">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-4 rounded-xl transition-colors shadow-sm">Assign to Board</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts for UI & Drag-and-Drop Engine -->
    <script>
        // Dark Mode Toggle
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const isDark = document.documentElement.classList.contains('dark');
        if (isDark) { themeIcon.classList.replace('ph-moon', 'ph-sun'); }

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

        // ====================================================
        // SORTABLE JS ENGINE (The Drag-and-Drop Magic)
        // ====================================================
        const columns = ['todo', 'in_progress', 'done'];

        columns.forEach(col => {
            const container = document.getElementById(col);
            if (container) {
                new Sortable(container, {
                    group: 'kanban', // Allows dragging between columns
                    animation: 150,
                    ghostClass: 'kanban-ghost',
                    dragClass: 'kanban-drag',
                    
                    // Fired when an item is dropped
                    onEnd: function (evt) {
                        const itemEl = evt.item;
                        const taskId = itemEl.getAttribute('data-id');
                        const newStatus = evt.to.id; // 'todo', 'in_progress', or 'done'
                        
                        // Prevent doing anything if it wasn't moved to a new column
                        if (evt.from.id === evt.to.id) return;
                        
                        // Immediately update the visual counters
                        document.getElementById('count-' + evt.from.id).innerText = document.getElementById(evt.from.id).children.length;
                        document.getElementById('count-' + evt.to.id).innerText = document.getElementById(evt.to.id).children.length;

                        // SILENT BACKGROUND AJAX CALL TO UPDATE DATABASE
                        const formData = new FormData();
                        formData.append('ajax_update_task', '1');
                        formData.append('task_id', taskId);
                        formData.append('new_status', newStatus);

                        fetch('sk2_tasks.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if(!data.success) {
                                alert("Error updating task. Please refresh the page.");
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                        });
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php
// HELPER FUNCTION: Renders a single Task Card HTML block
function renderTaskCard($task) {
    // Priority badge styling
    $priColor = 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400';
    $priDot = 'bg-slate-400';
    
    if ($task['priority'] == 'High') {
        $priColor = 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400';
        $priDot = 'bg-red-500';
    } elseif ($task['priority'] == 'Low') {
        $priColor = 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400';
        $priDot = 'bg-blue-500';
    } elseif ($task['priority'] == 'Medium') {
        $priColor = 'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/10 dark:text-yellow-400';
        $priDot = 'bg-yellow-500';
    }
?>
    <!-- The Draggable Task Card -->
    <div data-id="<?= $task['id'] ?>" class="bg-white dark:bg-zinc-950 p-4 rounded-xl border border-slate-200 dark:border-zinc-800 shadow-sm cursor-grab hover:border-blue-400 dark:hover:border-blue-500 transition-colors relative group">
        
        <!-- Delete Button (Visible on Hover) -->
        <a href="?action=delete&id=<?= $task['id'] ?>" onclick="return confirm('Delete this task forever?')" class="absolute top-3 right-3 text-slate-300 hover:text-red-500 dark:text-zinc-700 dark:hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity">
            <i class="ph-bold ph-trash text-lg"></i>
        </a>

        <!-- Priority Badge -->
        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-widest mb-3 <?= $priColor ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $priDot ?>"></span> <?= $task['priority'] ?>
        </div>
        
        <!-- Task Details -->
        <h4 class="text-sm font-black text-slate-900 dark:text-white leading-snug mb-1 pr-5"><?= htmlspecialchars($task['title']) ?></h4>
        <?php if(!empty($task['description'])): ?>
            <p class="text-xs text-slate-500 dark:text-zinc-400 line-clamp-2 mb-4 leading-relaxed"><?= htmlspecialchars($task['description']) ?></p>
        <?php else: ?>
            <div class="mb-4"></div>
        <?php endif; ?>
        
        <!-- Assignee Footer -->
        <div class="flex items-center gap-2 pt-3 border-t border-slate-100 dark:border-zinc-800/50">
            <div class="w-6 h-6 rounded-full bg-slate-200 dark:bg-zinc-800 flex items-center justify-center text-slate-500 dark:text-zinc-400 text-xs">
                <i class="ph-fill ph-user"></i>
            </div>
            <span class="text-[10px] font-bold text-slate-600 dark:text-zinc-300"><?= htmlspecialchars($task['assignee']) ?></span>
            
            <div class="ml-auto text-slate-300 dark:text-zinc-700 tooltip" title="Draggable">
                <i class="ph-bold ph-dots-six-vertical text-lg"></i>
            </div>
        </div>
    </div>
<?php
}
?>