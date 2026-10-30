<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sub-admin') {
    header("Location: login.php");
    exit;
}

require 'db_connect.php'; 

$success_msg = "";
$error_msg = "";
$subadmin_unit = $_SESSION['unit_name'] ?? 'Local Unit'; 

// HANDLE FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $appointment_date = $_POST['appointment_date'];
    $ppa_title = $_POST['ppa_title'];
    $remarks = trim($_POST['remarks']);
    $amount = $_POST['amount'];

    try {
        $stmt = $pdo->prepare("INSERT INTO ppa_appointments (sk_unit_name, appointment_date, ppa_title, remarks, amount, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$subadmin_unit, $appointment_date, $ppa_title, $remarks, $amount]);
        $success_msg = "PPA Appointment successfully submitted for Admin approval!";
    } catch (Exception $e) { 
        $error_msg = "Database Error: " . $e->getMessage(); 
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <title>Submit PPA Appointment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8fafc; overflow-x: hidden; }
        .admin-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.02); border: 1px solid #edf1f5; padding: 25px; }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="flex min-h-screen bg-slate-50">

    <?php include 'sidebar_sk.php'; ?>

    <main class="flex-1 p-8 ml-64 w-full">
        <nav class="bg-white border-b border-slate-200 mb-6 p-4 rounded-xl shadow-sm d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">Submit PPA Appointment</h5>
            <span class="badge bg-primary px-3 py-2"><?= htmlspecialchars($subadmin_unit) ?></span>
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

            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="admin-card">
                        <form method="POST" action="">
                            <div class="row g-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-secondary">Appointment Date</label>
                                    <input type="date" name="appointment_date" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold text-secondary">PPA Choice</label>
                                    <select name="ppa_title" class="form-select" required>
                                        <option value="SK HUB">SK HUB</option>
                                        <option value="I PRINT, I CLEAN">I PRINT, I CLEAN</option>
                                        <option value="SK WIFI ZONE">SK WIFI ZONE</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold text-secondary">Estimated Amount (₱)</label>
                                <input type="number" step="0.01" name="amount" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold py-2">Submit for Approval</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>