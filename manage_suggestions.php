<?php
session_start();
require 'db_connect.php';
include 'genesis_widget.php';
// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Mark as Reviewed
if (isset($_GET['mark_reviewed'])) {
    $stmt = $pdo->prepare("UPDATE suggestions SET status = 'Reviewed' WHERE id = ?");
    $stmt->execute([$_GET['mark_reviewed']]);
    header("Location: manage_suggestions.php");
    exit();
}

// Delete Suggestion
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM suggestions WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: manage_suggestions.php?deleted=1");
    exit();
}

$suggestions = $pdo->query("SELECT * FROM suggestions ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <title>Community Suggestions | TALA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-gray-50 flex h-screen font-sans overflow-hidden">
  <!-- 1. Include the Sidebar -->
    <?php 
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        include 'sidebar_admin.php';
    } else {
        include 'sidebar_sk.php';
    }
    ?>

    <!-- 2. Add 'ml-64' to your main content div! -->
    <main class="flex-1 p-8 ml-64">
    <main class="flex-1 flex flex-col h-screen overflow-hidden">
        <header class="h-20 bg-white shadow-sm flex items-center px-4 md:px-8 z-10 w-full relative">
            <button onclick="toggleSidebar()" class="text-gray-500 hover:text-green-700 mr-4"><i class="ph ph-list text-3xl"></i></button>
            <h1 class="text-gray-600 font-bold border-l pl-4 border-gray-200">Community Voice Inbox</h1>
        </header>

        <div class="flex-1 overflow-y-auto p-4 md:p-8">
            <div class="mb-6 flex justify-between items-end">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Community Suggestions</h2>
                    <p class="text-gray-500 text-sm">Review concerns, projects, and issues submitted by the youth.</p>
                </div>
            </div>

            <div class="space-y-4 max-w-5xl">
                <?php if (count($suggestions) > 0): ?>
                    <?php foreach ($suggestions as $msg): ?>
                        <div class="bg-white p-6 rounded-2xl shadow-sm border <?= $msg['status'] == 'Unread' ? 'border-brand-500 border-l-4' : 'border-gray-200' ?> flex flex-col md:flex-row gap-4 relative">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="text-xs font-bold px-2 py-1 rounded uppercase tracking-wider <?= $msg['category'] == 'Concern' ? 'bg-orange-100 text-orange-700' : ($msg['category'] == 'Project Suggestion' ? 'bg-blue-100 text-blue-700' : 'bg-red-100 text-red-700') ?>">
                                        <?= htmlspecialchars($msg['category']) ?>
                                    </span>
                                    <span class="text-xs text-gray-400 font-bold"><i class="ph ph-clock"></i> <?= date('M j, Y - g:i A', strtotime($msg['created_at'])) ?></span>
                                </div>
                                <p class="text-gray-800 text-lg leading-relaxed whitespace-pre-line mb-4 font-medium">"<?= htmlspecialchars($msg['message']) ?>"</p>
                                <div class="text-sm text-gray-500">
                                    <span class="font-bold text-gray-700"><i class="ph-fill ph-user text-brand-600"></i> <?= htmlspecialchars($msg['sender_name'] ?: 'Anonymous') ?></span>
                                    <?php if ($msg['contact_info']): ?>
                                        <span class="ml-4 border-l pl-4"><i class="ph-fill ph-phone text-brand-600"></i> <?= htmlspecialchars($msg['contact_info']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex flex-row md:flex-col gap-2 justify-center border-t md:border-t-0 md:border-l border-gray-100 pt-4 md:pt-0 md:pl-4">
                                <?php if ($msg['status'] == 'Unread'): ?>
                                    <a href="manage_suggestions.php?mark_reviewed=<?= $msg['id'] ?>" class="bg-brand-50 hover:bg-brand-100 text-brand-700 font-bold text-xs py-2 px-4 rounded-lg text-center transition flex-1 flex items-center justify-center"><i class="ph-bold ph-check mr-1"></i> Mark Read</a>
                                <?php endif; ?>
                                <a href="manage_suggestions.php?delete=<?= $msg['id'] ?>" onclick="return confirm('Delete this message?')" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold text-xs py-2 px-4 rounded-lg text-center transition flex-1 flex items-center justify-center"><i class="ph-bold ph-trash mr-1"></i> Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="bg-white p-12 rounded-2xl border border-dashed border-gray-300 text-center text-gray-500">
                        <i class="ph ph-envelope-simple-open text-5xl mb-2 text-gray-300"></i>
                        <p class="font-bold">Inbox is empty.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>