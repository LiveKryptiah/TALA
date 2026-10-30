<?php
// Force error reporting just in case there is a server configuration issue
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
include 'genesis_widget.php';
require 'db_connect.php';

// Safe Initialization of Variables
$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$myBrgy = isset($_SESSION['barangay_assigned']) ? $_SESSION['barangay_assigned'] : '';

$totalYouth = 0; $totalVoters = 0; $totalOSY = 0; $totalMale = 0; $totalFemale = 0;
$empLabels = []; $empCounts = []; 
$brgyLabels = []; $brgyCounts = [];
$recentProfiles = [];
$db_error_message = null;

try {
    // Determine filters based on role
    $whereBrgy = $isAdmin ? "" : " WHERE barangay = :barangay";
    $andBrgy = $isAdmin ? "" : " AND barangay = :barangay";
    $params = $isAdmin ? [] : [':barangay' => $myBrgy];

    // 1. Total Youth
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM youth_profiles" . $whereBrgy);
    $stmtTotal->execute($params);
    $totalYouth = $stmtTotal->fetchColumn() ?: 0;

    // 2. Registered Voters
    $stmtVoters = $pdo->prepare("SELECT COUNT(*) FROM youth_profiles WHERE is_voter = 1" . $andBrgy);
    $stmtVoters->execute($params);
    $totalVoters = $stmtVoters->fetchColumn() ?: 0;
    
    // 3. Out of School Youth
    $stmtOSY = $pdo->prepare("SELECT COUNT(*) FROM youth_profiles WHERE employment_status = 'Out-of-School Youth (OSY)'" . $andBrgy);
    $stmtOSY->execute($params);
    $totalOSY = $stmtOSY->fetchColumn() ?: 0;

    // 4. Gender Demographics
    $stmtMale = $pdo->prepare("SELECT COUNT(*) FROM youth_profiles WHERE sex = 'Male'" . $andBrgy);
    $stmtMale->execute($params);
    $totalMale = $stmtMale->fetchColumn() ?: 0;
    $totalFemale = $totalYouth - $totalMale;

    // 5. Employment Chart Data
    $stmtEmp = $pdo->prepare("SELECT employment_status, COUNT(*) as count FROM youth_profiles" . $whereBrgy . " GROUP BY employment_status");
    $stmtEmp->execute($params);
    $empData = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
    foreach ($empData as $row) {
        $empLabels[] = !empty($row['employment_status']) ? $row['employment_status'] : 'Unspecified';
        $empCounts[] = $row['count'];
    }

    // 6. Barangay Chart Data
    $stmtBrgy = $pdo->prepare("SELECT barangay, COUNT(*) as count FROM youth_profiles" . $whereBrgy . " GROUP BY barangay ORDER BY count DESC LIMIT 5");
    $stmtBrgy->execute($params);
    $brgyData = $stmtBrgy->fetchAll(PDO::FETCH_ASSOC);
    foreach ($brgyData as $row) {
        $brgyLabels[] = $row['barangay'];
        $brgyCounts[] = $row['count'];
    }

    // 7. Recent Registrations
    $stmtRecent = $pdo->prepare("SELECT youth_id, full_name, barangay, birthdate, employment_status FROM youth_profiles" . $whereBrgy . " ORDER BY created_at DESC LIMIT 5");
    $stmtRecent->execute($params);
    $recentProfiles = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // INSTEAD OF CRASHING WITH A 500 ERROR, WE CATCH IT HERE
    $db_error_message = $e->getMessage();
}

// Helper function for age calculation
function getAge($birthdate) {
    if (empty($birthdate)) return 0;
    $dob = new DateTime($birthdate);
    $now = new DateTime();
    return $now->diff($dob)->y;
}

$displayName = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'User';
$displayInitial = strtoupper(substr($displayName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard & Analytics | TALA OS</title>
    
    <!-- Modern Enterprise SaaS Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: { brand: { 500: '#22c55e', 600: '#16a34a', 700: '#15803d', 900: '#14532d' } },
                    boxShadow: { 'soft': '0 20px 40px -15px rgba(0,0,0,0.05)' }
                }
            }
        }
    </script>
    
    <style>
        /* Ambient Light Mode Background Animations */
        @keyframes blob {
            0% { transform: translate(0px, 0px) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0px, 0px) scale(1); }
        }
        .ambient-bg { position: fixed; inset: 0; z-index: -1; background: #f8fafc; overflow: hidden; pointer-events: none; }
        .blob { position: absolute; filter: blur(100px); opacity: 0.6; border-radius: 50%; mix-blend-multiply: true; animation: blob 10s infinite alternate ease-in-out; }
        .animation-delay-2000 { animation-delay: 2s; }
        
        .glass-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.8); }
        
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        
        .text-gradient { background: linear-gradient(135deg, #16a34a, #eab308); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="text-slate-800 antialiased flex h-screen overflow-hidden selection:bg-brand-500 selection:text-white">

    <!-- GLOBAL AMBIENT MESH GRADIENT BACKGROUND -->
    <div class="ambient-bg">
        <div class="blob bg-brand-200 w-[500px] h-[500px] -top-[10%] -left-[10%]"></div>
        <div class="blob bg-yellow-200 w-[500px] h-[500px] bottom-[10%] -right-[10%] animation-delay-2000"></div>
    </div>

    <!-- SIDEBAR INCLUDE -->
    <?php 
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        include 'sidebar_admin.php';
    } else {
        include 'sidebar_sk.php';
    }
    ?>

    <!-- MAIN CONTENT -->
    <main class="flex-1 overflow-y-auto p-8 ml-64 relative z-10 custom-scrollbar">
        <div class="max-w-7xl mx-auto space-y-8">
            
            <!-- HEADER -->
            <div class="flex justify-between items-end pb-4 border-b border-slate-200">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Analytics Overview</h1>
                    <p class="text-sm font-semibold text-slate-500 mt-1 uppercase tracking-widest">
                        <?= $isAdmin ? "City-Wide Demographic Intelligence" : "Barangay " . htmlspecialchars($myBrgy) . " Intelligence" ?>
                    </p>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-extrabold text-slate-800"><?= htmlspecialchars($displayName) ?></p>
                        <p class="text-[10px] font-bold text-brand-600 uppercase tracking-widest">TALA System Access</p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-brand-600 to-yellow-500 text-white flex items-center justify-center font-extrabold text-xl shadow-lg shadow-brand-500/20">
                        <?= $displayInitial ?>
                    </div>
                </div>
            </div>

            <!-- DATABASE ERROR CATCHER UI -->
            <?php if ($db_error_message): ?>
                <div class="bg-red-50/80 border border-red-200 text-red-700 px-6 py-5 rounded-2xl shadow-sm backdrop-blur-md">
                    <h3 class="font-bold text-lg flex items-center"><i class="ph-fill ph-warning-circle mr-2 text-2xl"></i> Database Verification Needed</h3>
                    <p class="mt-1 text-sm font-medium">The dashboard loaded securely, but charts cannot display data because a required table or column is missing.</p>
                    <p class="mt-3 bg-white p-3 rounded-xl border border-red-100 font-mono text-xs text-red-600 font-semibold"><?= htmlspecialchars($db_error_message) ?></p>
                </div>
            <?php endif; ?>

            <!-- SUMMARY CARDS -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Card 1 -->
                <div class="glass-card p-6 rounded-[2rem] shadow-soft flex items-center justify-between group hover:-translate-y-1 transition-transform duration-300">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Registered</p>
                        <h3 class="text-4xl font-extrabold text-slate-800 mt-1"><?= number_format($totalYouth) ?></h3>
                    </div>
                    <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-3xl shadow-sm group-hover:bg-blue-600 group-hover:text-white transition-colors duration-300">
                        <i class="ph-fill ph-users-three"></i>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="glass-card p-6 rounded-[2rem] shadow-soft flex items-center justify-between group hover:-translate-y-1 transition-transform duration-300">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Registered Voters</p>
                        <h3 class="text-4xl font-extrabold text-brand-600 mt-1"><?= number_format($totalVoters) ?></h3>
                    </div>
                    <div class="w-14 h-14 bg-brand-50 text-brand-600 rounded-2xl flex items-center justify-center text-3xl shadow-sm group-hover:bg-brand-600 group-hover:text-white transition-colors duration-300">
                        <i class="ph-fill ph-check-circle"></i>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="glass-card p-6 rounded-[2rem] shadow-soft flex items-center justify-between group hover:-translate-y-1 transition-transform duration-300">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Out-of-School (OSY)</p>
                        <h3 class="text-4xl font-extrabold text-orange-500 mt-1"><?= number_format($totalOSY) ?></h3>
                    </div>
                    <div class="w-14 h-14 bg-orange-50 text-orange-500 rounded-2xl flex items-center justify-center text-3xl shadow-sm group-hover:bg-orange-500 group-hover:text-white transition-colors duration-300">
                        <i class="ph-fill ph-warning-circle"></i>
                    </div>
                </div>

                <!-- Card 4 -->
                <div class="glass-card p-6 rounded-[2rem] shadow-soft flex items-center justify-between group hover:-translate-y-1 transition-transform duration-300">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Gender Demo</p>
                        <div class="flex gap-4 items-center">
                            <span class="text-lg font-extrabold text-blue-500 flex items-center gap-1" title="Male">
                                <i class="ph-bold ph-gender-male"></i> <?= number_format($totalMale) ?>
                            </span>
                            <span class="text-lg font-extrabold text-pink-500 flex items-center gap-1" title="Female">
                                <i class="ph-bold ph-gender-female"></i> <?= number_format($totalFemale) ?>
                            </span>
                        </div>
                    </div>
                    <div class="w-14 h-14 bg-purple-50 text-purple-500 rounded-2xl flex items-center justify-center text-3xl shadow-sm group-hover:bg-purple-500 group-hover:text-white transition-colors duration-300">
                        <i class="ph-fill ph-gender-intersex"></i>
                    </div>
                </div>
            </div>

            <!-- CHARTS SECTION -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                
                <div class="glass-card p-6 md:p-8 rounded-[2rem] shadow-soft border border-slate-200">
                    <h3 class="text-lg font-extrabold text-slate-800 mb-6 border-b border-slate-100 pb-4">Employment Status Breakdown</h3>
                    <div class="relative h-[300px] w-full flex items-center justify-center">
                        <canvas id="employmentChart"></canvas>
                    </div>
                </div>

                <div class="glass-card p-6 md:p-8 rounded-[2rem] shadow-soft border border-slate-200">
                    <h3 class="text-lg font-extrabold text-slate-800 mb-6 border-b border-slate-100 pb-4">
                        <?= $isAdmin ? "Top 5 Barangays (Youth Population)" : "Barangay Youth Population" ?>
                    </h3>
                    <div class="relative h-[300px] w-full">
                        <canvas id="barangayChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- RECENT REGISTRATIONS TABLE -->
            <div class="glass-card rounded-[2rem] shadow-soft overflow-hidden border border-slate-200">
                <div class="px-6 py-6 border-b border-slate-100 flex justify-between items-center bg-white/50">
                    <h3 class="text-xl font-extrabold text-slate-800">Recent Registrations</h3>
                    <a href="youth_profiles.php" class="text-xs font-bold text-brand-600 bg-brand-50 px-4 py-2 rounded-xl hover:bg-brand-600 hover:text-white transition-colors">View Database</a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100">
                                <th class="px-6 py-4">Youth ID & Name</th>
                                <th class="px-6 py-4">Barangay & Age</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm font-medium">
                            <?php if (count($recentProfiles) > 0): ?>
                                <?php foreach ($recentProfiles as $profile): ?>
                                    <tr class="hover:bg-slate-50 transition-colors group">
                                        <td class="px-6 py-4">
                                            <p class="font-bold text-slate-800 text-sm"><?= htmlspecialchars($profile['full_name']) ?></p>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5"><?= htmlspecialchars($profile['youth_id']) ?></p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <p class="font-semibold text-slate-700"><?= htmlspecialchars($profile['barangay']) ?></p>
                                            <p class="text-xs text-slate-500 mt-0.5 flex items-center gap-1"><i class="ph-bold ph-calendar-blank"></i> <?= getAge($profile['birthdate']) ?> yrs old</p>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php 
                                                $status = htmlspecialchars($profile['employment_status']);
                                                $bgClass = 'bg-slate-100 text-slate-600 border-slate-200';
                                                if ($status == 'Student') $bgClass = 'bg-blue-50 text-blue-700 border-blue-200';
                                                if ($status == 'Employed') $bgClass = 'bg-brand-50 text-brand-700 border-brand-200';
                                                if ($status == 'Out-of-School Youth (OSY)') $bgClass = 'bg-orange-50 text-orange-700 border-orange-200';
                                            ?>
                                            <span class="px-3 py-1.5 rounded-lg text-[10px] uppercase font-extrabold tracking-widest border shadow-sm <?= $bgClass ?>"><?= $status ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="view_profile.php?id=<?= $profile['youth_id'] ?>" class="inline-flex items-center justify-center w-8 h-8 bg-white text-slate-400 rounded-lg hover:bg-brand-50 hover:text-brand-600 border border-slate-200 transition-colors shadow-sm">
                                                <i class="ph-bold ph-arrow-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="px-6 py-16 text-center text-slate-400 font-semibold">No registrations available yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- SCRIPTS FOR CHARTS -->
    <script>
        // Set global font for Chart.js
        Chart.defaults.font.family = '"Plus Jakarta Sans", sans-serif';
        Chart.defaults.color = '#64748b';

        // Only draw charts if data arrays exist (prevents JS crashing if DB fails)
        const empLabels = <?= json_encode($empLabels) ?: '[]' ?>;
        const empCounts = <?= json_encode($empCounts) ?: '[]' ?>;
        const brgyLabels = <?= json_encode($brgyLabels) ?: '[]' ?>;
        const brgyCounts = <?= json_encode($brgyCounts) ?: '[]' ?>;

        if (empLabels.length > 0) {
            const ctxEmp = document.getElementById('employmentChart').getContext('2d');
            new Chart(ctxEmp, {
                type: 'doughnut',
                data: {
                    labels: empLabels,
                    datasets: [{
                        data: empCounts,
                        backgroundColor: ['#16a34a', '#eab308', '#f97316', '#3b82f6', '#8b5cf6', '#cbd5e1'],
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    cutout: '75%',
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 20, usePointStyle: true, pointStyle: 'circle' } }
                    }
                }
            });
        }

        if (brgyLabels.length > 0) {
            const ctxBrgy = document.getElementById('barangayChart').getContext('2d');
            
            // Create Gradient for Bar Chart
            let gradient = ctxBrgy.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, '#16a34a'); // Brand 600
            gradient.addColorStop(1, '#22c55e'); // Brand 500

            new Chart(ctxBrgy, {
                type: 'bar',
                data: {
                    labels: brgyLabels,
                    datasets: [{
                        label: 'Registered Youth',
                        data: brgyCounts,
                        backgroundColor: gradient,
                        borderRadius: 8,
                        barThickness: 40
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { 
                        y: { 
                            beginAtZero: true, 
                            grid: { color: '#f1f5f9', drawBorder: false },
                            ticks: { stepSize: 1, padding: 10 } 
                        },
                        x: {
                            grid: { display: false, drawBorder: false },
                            ticks: { padding: 10 }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>