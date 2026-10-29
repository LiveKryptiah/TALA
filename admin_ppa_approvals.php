<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// SECURITY CHECK (Admin only)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require 'db_connect.php'; 

$success_msg = "";
$error_msg = "";

// HANDLE APPROVE / REJECT ACTION
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action']; // Will be 'Approved' or 'Rejected'
    
    if (in_array($action, ['Approved', 'Rejected'])) {
        try {
            // First, get the SK unit name and PPA title for the notification
            $stmt = $pdo->prepare("SELECT sk_unit_name, ppa_title FROM ppa_appointments WHERE id = ?");
            $stmt->execute([$id]);
            $requestData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($requestData) {
                // Update the status in the database
                $updateStmt = $pdo->prepare("UPDATE ppa_appointments SET status = ? WHERE id = ?");
                $updateStmt->execute([$action, $id]);
                
                $success_msg = "Request has been successfully " . strtolower($action) . "!";

                // ==========================================
                // FIREBASE PUSH NOTIFICATION TRIGGER (TO SK)
                // ==========================================
                /*
                // Remove spaces from SK unit name to create a clean topic (e.g., "San Pedro" -> "SanPedro")
                $clean_unit_topic = str_replace(' ', '', $requestData['sk_unit_name']); 
                $topic = "sk_unit_" . $clean_unit_topic; 
                
                $title = "PPA Request " . $action;
                $body = "Your request for " . $requestData['ppa_title'] . " has been " . $action . " by the Admin.";
                
                // Call your custom Firebase cURL function here
                // sendFirebaseNotification($topic, $title, $body);
                */
            }
        } catch (Exception $e) {
            $error_msg = "Error updating request: " . $e->getMessage();
        }
    }
}

// FETCH PENDING ITEMS
$pending_requests = [];
try {
    $stmt = $pdo->query("SELECT * FROM ppa_appointments WHERE status = 'Pending' ORDER BY appointment_date ASC");
    $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { 
    $error_msg = "Fetch Error: " . $e->getMessage(); 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PPA Approval Board</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8fafc; overflow-x: hidden; }
        .admin-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); border: 1px solid #edf1f5; padding: 25px; }
        table thead th { background-color: #f8fafc !important; color: #475569 !important; border-bottom: 2px solid #e2e8f0 !important; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
    </style>
</head>
<body class="flex min-h-screen bg-slate-50">

    <!-- Include Admin Sidebar -->
    <?php include 'sidebar_admin.php'; ?>

    <main class="flex-1 p-8 ml-64 w-full">
        <nav class="bg-white border-b border-slate-200 mb-6 p-4 rounded-xl shadow-sm d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">PPA Approval Board</h5>
        </nav>

        <div class="container-fluid px-0 pb-5">
            <?php if($success_msg): ?> 
                <div class="alert alert-success alert-dismissible fade show fw-bold">
                    <i class="bi bi-check-circle me-2"></i> <?= $success_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div> 
            <?php endif; ?>
            <?php if($error_msg): ?> 
                <div class="alert alert-danger alert-dismissible fade show fw-bold">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?= $error_msg; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div> 
            <?php endif; ?>

            <div class="admin-card">
                <h5 class="fw-bold text-dark border-bottom pb-2 mb-3"><i class="bi bi-inboxes me-2"></i>Pending SK Appointments</h5>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>SK Unit</th>
                                <th>Date</th>
                                <th>PPAs (Title)</th>
                                <th>Remarks</th>
                                <th>Amount</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_requests)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="bi bi-emoji-smile fs-4 d-block mb-2"></i> No pending requests. You're all caught up!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($pending_requests as $req): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($req['sk_unit_name']); ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= date('M d, Y', strtotime($req['appointment_date'])); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($req['ppa_title']); ?></td>
                                    <td>
                                        <small class="text-muted d-block" style="max-width: 250px; white-space: normal;">
                                            <?= htmlspecialchars($req['remarks']); ?>
                                        </small>
                                    </td>
                                    <td class="text-success fw-bold">₱<?= number_format($req['amount'], 2); ?></td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            <a href="?action=Approved&id=<?= $req['id']; ?>" class="btn btn-success btn-sm rounded-pill px-3 fw-bold shadow-sm" onclick="return confirm('Approve this request?');">
                                                <i class="bi bi-check-lg"></i> Approve
                                            </a>
                                            <a href="?action=Rejected&id=<?= $req['id']; ?>" class="btn btn-outline-danger btn-sm rounded-pill px-3 fw-bold" onclick="return confirm('Reject this request?');">
                                                <i class="bi bi-x-lg"></i> Reject
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>