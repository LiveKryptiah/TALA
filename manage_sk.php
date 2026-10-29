<?php
session_start();
require 'db_connect.php';
include 'genesis_widget.php';
// Security: ONLY Super Admins can access this page
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

// Handle Approve / Reject Actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];
    
    if ($action == 'approve') {
        $stmt = $pdo->prepare("UPDATE admins SET status = 'approved' WHERE id = :id");
        $stmt->execute([':id' => $id]);
    } elseif ($action == 'reject') {
        $stmt = $pdo->prepare("UPDATE admins SET status = 'rejected' WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
    
    header("Location: manage_sk.php");
    exit();
}

// Fetch all sub-admins
$stmt = $pdo->query("SELECT * FROM admins WHERE role = 'sub-admin' ORDER BY created_at DESC");
$sk_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage SK Users | TALA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="bg-gray-50 flex h-screen font-sans">

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
    
  
</aside>

    <main class="flex-1 p-8 overflow-y-auto">
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800">Manage SK Access</h2>
            <p class="text-gray-500">Approve or reject account requests from SK Officials.</p>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <table class="w-full text-left">
               <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
    <tr>
        <th class="px-6 py-4">Full Name & Position</th>
        <th class="px-6 py-4">Barangay</th>
        <th class="px-6 py-4">Username</th>
        <th class="px-6 py-4">Status</th>
        <th class="px-6 py-4 text-right">Action</th>
    </tr>
</thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php if (count($sk_users) > 0): ?>
                        <?php foreach ($sk_users as $user): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium"><?= htmlspecialchars($user['full_name'] ?: 'N/A') ?></td>
                                <td class="px-6 py-4 text-green-700 font-bold"><?= htmlspecialchars($user['barangay_assigned']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($user['username']) ?></td>
                                <td class="px-6 py-4">
                                    <?php 
                                        if ($user['status'] == 'pending') echo '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-bold">PENDING</span>';
                                        elseif ($user['status'] == 'approved') echo '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-bold">APPROVED</span>';
                                        else echo '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-bold">REJECTED</span>';
                                    ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if ($user['status'] == 'pending'): ?>
                                        <a href="manage_sk.php?action=approve&id=<?= $user['id'] ?>" onclick="return confirm('Approve this SK Official?')" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded mr-2 text-xs font-medium">Approve</a>
                                        <a href="manage_sk.php?action=reject&id=<?= $user['id'] ?>" onclick="return confirm('Reject this request?')" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded text-xs font-medium">Reject</a>
                                    <?php elseif ($user['status'] == 'approved'): ?>
                                        <a href="manage_sk.php?action=reject&id=<?= $user['id'] ?>" onclick="return confirm('Revoke access for this user?')" class="text-red-500 hover:underline text-xs">Revoke Access</a>
                                    <?php else: ?>
                                        <a href="manage_sk.php?action=approve&id=<?= $user['id'] ?>" class="text-green-600 hover:underline text-xs">Re-Approve</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No SK registrations found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>