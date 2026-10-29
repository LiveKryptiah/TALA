<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db_connect.php'; 

try {
    $total_youth = $pdo->query("SELECT COUNT(*) FROM youth_registry")->fetchColumn();
    $total_complete = $pdo->query("SELECT COUNT(*) FROM youth_registry WHERE document_status = 'Complete (All Files)'")->fetchColumn();
    $total_pending = $pdo->query("SELECT COUNT(*) FROM youth_registry WHERE document_status LIKE '%Incomplete%' OR document_status LIKE '%Missing%'")->fetchColumn();

    $barangay_query = $pdo->query("SELECT barangay, COUNT(*) as count FROM youth_registry GROUP BY barangay");
    $b_labels = []; $b_data = [];
    while($row = $barangay_query->fetch(PDO::FETCH_ASSOC)) {
        $b_labels[] = $row['barangay']; $b_data[] = $row['count'];
    }

    $class_query = $pdo->query("SELECT youth_classification, COUNT(*) as count FROM youth_registry GROUP BY youth_classification");
    $c_labels = []; $c_data = [];
    while($row = $class_query->fetch(PDO::FETCH_ASSOC)) {
        $c_labels[] = $row['youth_classification']; $c_data[] = $row['count'];
    }
} catch (PDOException $e) {
    die("Dashboard Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TALA OS | Analytics Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { brand: { 50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6', 600: '#2563eb', } } } } }
    </script>
    
    <style>
        body { background-color: #f8fafc; margin: 0; }
        .main-content { margin-left: 260px; padding: 40px; max-width: 1200px; }
        
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 30px; }
        .kpi-card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .kpi-card h3 { margin: 0 0 10px 0; font-size: 14px; color: #64748b; font-weight: 500; text-transform: uppercase; }
        .kpi-card p { margin: 0; font-size: 32px; font-weight: 700; color: #0f172a; }

        .charts-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 24px; }
        .chart-card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .chart-container { position: relative; height: 300px; width: 100%; }
        
        @media (max-width: 900px) { .charts-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <?php include 'sidebar_admin.php'; ?>

    <div class="main-content">
        <div class="flex justify-between items-end mb-8">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">Analytics Overview</h1>
                <p class="text-slate-500 mt-1">Live demographic tracking</p>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <h3>Total Registered</h3>
                <p><?php echo $total_youth; ?></p>
            </div>
            <div class="kpi-card">
                <h3>Fully Verified</h3>
                <p style="color:#10b981;"><?php echo $total_complete; ?></p>
            </div>
            <div class="kpi-card">
                <h3>Pending Documents</h3>
                <p style="color:#f59e0b;"><?php echo $total_pending; ?></p>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3 class="font-bold text-slate-800 mb-4">Barangay Distribution</h3>
                <div class="chart-container"><canvas id="barangayChart"></canvas></div>
            </div>
            <div class="chart-card">
                <h3 class="font-bold text-slate-800 mb-4">Youth Classifications</h3>
                <div class="chart-container"><canvas id="classificationChart"></canvas></div>
            </div>
        </div>
    </div>

    <script>
        new Chart(document.getElementById('barangayChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($b_labels); ?>,
                datasets: [{ data: <?php echo json_encode($b_data); ?>, backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b'] }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'bottom' } } }
        });

        new Chart(document.getElementById('classificationChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($c_labels); ?>,
                datasets: [{ data: <?php echo json_encode($c_data); ?>, backgroundColor: '#3b82f6', borderRadius: 6 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
    </script>
</body>
</html>