<?php
// sk2_projects.php - SK Command Center Kanban Board
session_start();

// Enable Error Reporting so it tells us what's wrong instead of showing a blank page
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'sub-admin')) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$barangay = $_SESSION['barangay_assigned'] ?? 'Municipal Level';
$user_name = $_SESSION['full_name'] ?? 'SK Chairperson';

// Auto-create projects table (Safely allowing NULL for dates)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS sk_projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        barangay VARCHAR(255) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        status VARCHAR(50) NOT NULL DEFAULT 'todo',
        priority VARCHAR(50) NOT NULL DEFAULT 'medium',
        due_date DATE NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // AUTO-UPGRADE: Fix missing columns if the table was created in an older version
    $cols = $pdo->query("SHOW COLUMNS FROM sk_projects")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('priority', $cols)) {
        $pdo->exec("ALTER TABLE sk_projects ADD COLUMN priority VARCHAR(50) NOT NULL DEFAULT 'medium'");
    }
    
    if (!in_array('due_date', $cols)) {
        $pdo->exec("ALTER TABLE sk_projects ADD COLUMN due_date DATE NULL DEFAULT NULL");
    }

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$message = '';

// Handle Add Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'];
    
    // Strict MySQL servers crash if you send an empty string to a DATE column. 
    // This converts a blank date into a safe 'NULL'.
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    if (!empty($title)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO sk_projects (barangay, title, description, status, priority, due_date) VALUES (?, ?, ?, 'todo', ?, ?)");
            $stmt->execute([$barangay, $title, $description, $priority, $due_date]);
            $message = "<div class='bg-emerald-50 text-emerald-700 p-4 rounded-xl mb-6 font-bold text-sm border border-emerald-200'>Project task created successfully!</div>";
        } catch (PDOException $e) {
            $message = "<div class='bg-red-50 text-red-700 p-4 rounded-xl mb-6 font-bold text-sm border border-red-200'>Error saving task: " . $e->getMessage() . "</div>";
        }
    }
}

// Handle Status Change (Move Task)
if (isset($_GET['move']) && isset($_GET['id'])) {
    $new_status = $_GET['move'];
    $task_id = $_GET['id'];
    
    // Ensure they only move their own barangay's tasks
    $stmt = $pdo->prepare("UPDATE sk_projects SET status = ? WHERE id = ? AND barangay = ?");
    $stmt->execute([$new_status, $task_id, $barangay]);
    header("Location: sk2_projects.php");
    exit;
}

// Handle Delete Task
if (isset($_GET['delete'])) {
    $task_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM sk_projects WHERE id = ? AND barangay = ?");
    $stmt->execute([$task_id, $barangay]);
    header("Location: sk2_projects.php");
    exit;
}

// Fetch Tasks
$stmt = $pdo->prepare("SELECT * FROM sk_projects WHERE barangay = ? ORDER BY due_date ASC, created_at DESC");
$stmt->execute([$barangay]);
$all_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group Tasks by Status
$tasks = ['todo' => [], 'doing' => [], 'done' => []];
foreach ($all_tasks as $t) {
    // Safety check just in case a weird status gets into the DB
    $status = in_array($t['status'], ['todo', 'doing', 'done']) ? $t['status'] : 'todo';
    $tasks[$status][] = $t;
}

// Wrapping this in function_exists prevents a fatal server crash if the page loads twice
if (!function_exists('getPriorityColor')) {
    function getPriorityColor($priority) {
        switch ($priority) {
            case 'high': return 'bg-red-50 text-red-600 border-red-200';
            case 'medium': return 'bg-amber-50 text-amber-600 border-amber-200';
            case 'low': return 'bg-blue-50 text-blue-600 border-blue-200';
            default: return 'bg-slate-50 text-slate-600 border-slate-200';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects Board | TALA OS</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { brand: { 50: '#f0fdf4', 100: '#dcfce7', 200: '#bbf7d0', 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d' } }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f8fafc; }
        .hover-lift { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .hover-lift:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* Modal Animation */
        #taskModal { transition: opacity 0.3s ease; }
        #taskModal .modal-content { transition: transform 0.3s ease; transform: scale(0.95); }
        #taskModal.active { opacity: 1; pointer-events: auto; }
        #taskModal.active .modal-content { transform: scale(1); }
    </style>
</head>
<body class="text-slate-800 antialiased overflow-hidden">

<!-- Sidebar Include -->
<?php include 'sk2_sidebar.php'; ?>

<!-- Add 'ml-64' to push it past the sidebar, and 'flex-1' to fill the rest of the screen -->
<main class="ml-64 flex-1 h-screen overflow-y-auto flex flex-col relative bg-slate-50">

<!-- Your main content probably looks like this right now -->
<main class="h-screen overflow-y-auto flex flex-col relative bg-slate-50">

    <!-- Main Content Area -->
    <main class="ml-64 h-screen overflow-y-auto hide-scrollbar flex flex-col relative">
        
        <!-- Top Header -->
        <header class="bg-white/80 backdrop-blur-md border-b border-slate-200 sticky top-0 z-30 px-8 py-4 flex justify-between items-center shadow-sm">
            <div>
                <p class="text-[10px] font-black text-brand-600 uppercase tracking-widest mb-0.5">Command Center</p>
                <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight leading-none flex items-center gap-2">
                    <i class="ph-fill ph-kanban text-brand-600"></i> Projects Board
                </h1>
            </div>
            <div class="flex items-center gap-4">
                <button onclick="openModal()" class="bg-brand-600 hover:bg-brand-700 text-white font-bold py-2 px-5 rounded-xl transition-colors shadow-sm flex items-center gap-2 text-sm outline-none">
                    <i class="ph-bold ph-plus"></i> New Task
                </button>
            </div>
        </header>

        <div class="p-8 pb-24 flex-1">
            <?= $message ?>

            <!-- Kanban Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 h-full items-start">
                
                <!-- COLUMN 1: TO DO -->
                <div class="bg-slate-100/50 rounded-[2rem] border border-slate-200 p-5 flex flex-col gap-4 min-h-[500px]">
                    <div class="flex justify-between items-center px-2">
                        <h2 class="font-extrabold text-slate-900 flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-slate-300"></span> To Do
                        </h2>
                        <span class="bg-slate-200 text-slate-600 text-xs font-bold px-2 py-1 rounded-lg"><?= count($tasks['todo']) ?></span>
                    </div>

                    <?php if(empty($tasks['todo'])): ?>
                        <div class="text-center py-10 text-slate-400 text-sm font-medium border-2 border-dashed border-slate-200 rounded-2xl">No tasks here</div>
                    <?php endif; ?>

                    <?php foreach($tasks['todo'] as $task): ?>
                        <div class="bg-white p-5 rounded-[1.5rem] border border-slate-200 shadow-sm hover-lift flex flex-col gap-3">
                            <div class="flex justify-between items-start">
                                <span class="text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md border <?= getPriorityColor($task['priority']) ?>">
                                    <?= htmlspecialchars($task['priority']) ?>
                                </span>
                                <a href="?delete=<?= $task['id'] ?>" onclick="return confirm('Delete task?')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="ph-bold ph-trash"></i></a>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-slate-900 leading-tight mb-1"><?= htmlspecialchars($task['title']) ?></h3>
                                <p class="text-xs text-slate-500 line-clamp-2"><?= htmlspecialchars($task['description']) ?></p>
                            </div>
                            <div class="flex justify-between items-center mt-2 pt-3 border-t border-slate-100">
                                <div class="text-[10px] font-bold text-slate-400 flex items-center gap-1">
                                    <i class="ph-bold ph-calendar-blank"></i> 
                                    <?= !empty($task['due_date']) ? date('M j, Y', strtotime($task['due_date'])) : 'No Date' ?>
                                </div>
                                <a href="?move=doing&id=<?= $task['id'] ?>" class="text-[10px] font-bold bg-brand-50 hover:bg-brand-100 text-brand-700 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1">
                                    Start <i class="ph-bold ph-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- COLUMN 2: IN PROGRESS -->
                <div class="bg-brand-50/30 rounded-[2rem] border border-brand-100 p-5 flex flex-col gap-4 min-h-[500px]">
                    <div class="flex justify-between items-center px-2">
                        <h2 class="font-extrabold text-brand-900 flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-brand-400 animate-pulse"></span> In Progress
                        </h2>
                        <span class="bg-brand-100 text-brand-700 text-xs font-bold px-2 py-1 rounded-lg"><?= count($tasks['doing']) ?></span>
                    </div>

                    <?php if(empty($tasks['doing'])): ?>
                        <div class="text-center py-10 text-brand-300/50 text-sm font-medium border-2 border-dashed border-brand-100 rounded-2xl">No active tasks</div>
                    <?php endif; ?>

                    <?php foreach($tasks['doing'] as $task): ?>
                        <div class="bg-white p-5 rounded-[1.5rem] border border-brand-200 shadow-sm hover-lift flex flex-col gap-3 relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-1 h-full bg-brand-400"></div>
                            <div class="flex justify-between items-start">
                                <span class="text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md border <?= getPriorityColor($task['priority']) ?>">
                                    <?= htmlspecialchars($task['priority']) ?>
                                </span>
                                <a href="?delete=<?= $task['id'] ?>" onclick="return confirm('Delete task?')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="ph-bold ph-trash"></i></a>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-slate-900 leading-tight mb-1"><?= htmlspecialchars($task['title']) ?></h3>
                                <p class="text-xs text-slate-500 line-clamp-2"><?= htmlspecialchars($task['description']) ?></p>
                            </div>
                            <div class="flex justify-between items-center mt-2 pt-3 border-t border-slate-100">
                                <a href="?move=todo&id=<?= $task['id'] ?>" class="text-[10px] font-bold text-slate-400 hover:text-slate-700 transition-colors flex items-center gap-1">
                                    <i class="ph-bold ph-arrow-left"></i> Back
                                </a>
                                <a href="?move=done&id=<?= $task['id'] ?>" class="text-[10px] font-bold bg-blue-50 hover:bg-blue-100 text-blue-700 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1">
                                    Complete <i class="ph-bold ph-check"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- COLUMN 3: COMPLETED -->
                <div class="bg-slate-100/50 rounded-[2rem] border border-slate-200 p-5 flex flex-col gap-4 min-h-[500px] opacity-75 hover:opacity-100 transition-opacity">
                    <div class="flex justify-between items-center px-2">
                        <h2 class="font-extrabold text-slate-700 flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-emerald-400"></span> Completed
                        </h2>
                        <span class="bg-slate-200 text-slate-600 text-xs font-bold px-2 py-1 rounded-lg"><?= count($tasks['done']) ?></span>
                    </div>

                    <?php if(empty($tasks['done'])): ?>
                        <div class="text-center py-10 text-slate-400 text-sm font-medium border-2 border-dashed border-slate-200 rounded-2xl">No completed tasks</div>
                    <?php endif; ?>

                    <?php foreach($tasks['done'] as $task): ?>
                        <div class="bg-slate-50 p-5 rounded-[1.5rem] border border-slate-200 shadow-sm flex flex-col gap-3">
                            <div class="flex justify-between items-start">
                                <span class="text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    DONE
                                </span>
                                <a href="?delete=<?= $task['id'] ?>" onclick="return confirm('Delete task?')" class="text-slate-400 hover:text-red-500 transition-colors"><i class="ph-bold ph-trash"></i></a>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-600 leading-tight mb-1 line-through decoration-slate-300"><?= htmlspecialchars($task['title']) ?></h3>
                            </div>
                            <div class="flex justify-start items-center mt-2 pt-3 border-t border-slate-200">
                                <a href="?move=doing&id=<?= $task['id'] ?>" class="text-[10px] font-bold text-slate-500 hover:text-brand-600 transition-colors flex items-center gap-1">
                                    <i class="ph-bold ph-arrow-counter-clockwise"></i> Reopen
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>
        </div>
    </main>

    <!-- NEW TASK MODAL -->
    <div id="taskModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100] opacity-0 pointer-events-none flex items-center justify-center p-4">
        <div class="modal-content bg-white rounded-3xl shadow-xl w-full max-w-md overflow-hidden flex flex-col max-h-[90vh]">
            
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <h3 class="font-extrabold text-slate-900 flex items-center gap-2">
                    <i class="ph-fill ph-plus-circle text-brand-600 text-xl"></i> Create New Task
                </h3>
                <button onclick="closeModal()" class="text-slate-400 hover:text-red-500 transition-colors outline-none"><i class="ph-bold ph-x text-xl"></i></button>
            </div>
            
            <form method="POST" class="p-6 flex flex-col gap-4 overflow-y-auto">
                <input type="hidden" name="add_task" value="1">
                
                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Task Title *</label>
                    <input type="text" name="title" required placeholder="e.g., Draft CBYIP Document" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 bg-slate-50 text-sm outline-none focus:border-brand-500 focus:bg-white transition-colors">
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Description</label>
                    <textarea name="description" rows="3" placeholder="Add details..." class="w-full border border-slate-200 rounded-xl px-4 py-2.5 bg-slate-50 text-sm outline-none focus:border-brand-500 focus:bg-white transition-colors resize-none"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Priority</label>
                        <select name="priority" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 bg-slate-50 text-sm outline-none focus:border-brand-500 focus:bg-white transition-colors cursor-pointer">
                            <option value="low">Low Priority</option>
                            <option value="medium" selected>Medium Priority</option>
                            <option value="high">High Priority</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Due Date (Optional)</label>
                        <input type="date" name="due_date" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 bg-slate-50 text-sm outline-none focus:border-brand-500 focus:bg-white transition-colors">
                    </div>
                </div>

                <div class="mt-4 flex gap-3">
                    <button type="button" onclick="closeModal()" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-3 rounded-xl transition-colors text-sm">Cancel</button>
                    <button type="submit" class="flex-1 bg-brand-600 hover:bg-brand-700 text-white font-bold py-3 rounded-xl transition-colors shadow-sm text-sm">Save Task</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('taskModal').classList.add('active');
        }
        function closeModal() {
            document.getElementById('taskModal').classList.remove('active');
        }
        // Close modal on outside click
        document.getElementById('taskModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>