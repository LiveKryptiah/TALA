<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: youth_profiles.php");
    exit();
}

$youth_id = $_GET['id'];
$isAdmin = ($_SESSION['role'] === 'admin');

try {
    // 1. Fetch the Profile
    $stmt = $pdo->prepare("SELECT * FROM youth_profiles WHERE youth_id = :id");
    $stmt->execute([':id' => $youth_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        die("Profile not found.");
    }

    // Security check: SKs can only view their own barangay's youth
    if (!$isAdmin && $profile['barangay'] !== $_SESSION['barangay_assigned']) {
        die("Access Denied: You do not have permission to view youth from other barangays.");
    }

    // 2. Fetch the Activity Ledger History
    $stmtAct = $pdo->prepare("SELECT * FROM youth_activities WHERE youth_id = :id ORDER BY date_attended DESC, created_at DESC");
    $stmtAct->execute([':id' => $youth_id]);
    $activities = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$dob = new DateTime($profile['birthdate']);
$now = new DateTime();
$age = $now->diff($dob)->y;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <title>View Profile | TALA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-gray-100 p-4 md:p-10 font-sans min-h-screen">

    <div class="max-w-7xl mx-auto">
        
        <!-- TOP BAR -->
        <div class="flex justify-between items-center mb-6">
            <a href="youth_profiles.php" class="text-green-700 hover:text-green-900 hover:underline flex items-center font-medium transition">
                <i class="ph ph-arrow-left mr-2"></i> Back to Directory
            </a>
            <?php if (isset($_GET['activity_added'])): ?>
                <span class="bg-green-100 text-green-800 px-4 py-2 rounded-lg font-bold text-sm shadow-sm flex items-center border border-green-200">
                    <i class="ph-fill ph-check-circle mr-2 text-lg"></i> Activity Logged Successfully!
                </span>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
            
            <!-- LEFT COLUMN: Profile Information & Ledger -->
            <div class="xl:col-span-2 space-y-8">
                
                <!-- MAIN PROFILE CARD -->
                <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-200">
                    
                    <!-- Profile Header (Green Area) -->
                    <div class="bg-green-900 p-8 text-white flex flex-col md:flex-row items-center md:items-start gap-6 text-center md:text-left">
                        <?php if($profile['photo_path']): ?>
                            <img src="<?= htmlspecialchars($profile['photo_path']) ?>" class="w-32 h-32 rounded-lg object-cover border-4 border-green-700 shadow-md bg-white flex-shrink-0">
                        <?php else: ?>
                            <div class="w-32 h-32 rounded-lg bg-green-800 border-4 border-green-700 flex items-center justify-center text-4xl flex-shrink-0">
                                <i class="ph ph-user"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="flex-1">
                            <h1 class="text-3xl md:text-4xl font-bold mb-1"><?= htmlspecialchars($profile['full_name']) ?></h1>
                            <p class="text-green-200 font-mono bg-green-800 inline-block px-3 py-1 rounded text-sm mb-3 shadow-inner">ID: <?= htmlspecialchars($profile['youth_id']) ?></p>
                            <div class="flex flex-wrap justify-center md:justify-start gap-4 text-sm text-green-100 mt-2">
                                <span class="flex items-center bg-green-800/50 px-3 py-1.5 rounded-full"><i class="ph ph-map-pin mr-1.5 text-lg"></i> <?= htmlspecialchars($profile['barangay']) ?>, Tumauini</span>
                                <span class="flex items-center bg-green-800/50 px-3 py-1.5 rounded-full"><i class="ph ph-briefcase mr-1.5 text-lg"></i> <?= htmlspecialchars($profile['employment_status']) ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- DETAILED PROFILE DATA (The grid you requested) -->
                    <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
                        
                        <!-- Personal Info -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4"><i class="ph ph-identification-card text-green-600 mr-2"></i>Personal Info</h3>
                            <ul class="space-y-3 text-sm">
                                <li class="flex justify-between"><span class="text-gray-500">Age</span> <span class="font-medium"><?= $age ?> years old</span></li>
                                <li class="flex justify-between"><span class="text-gray-500">Birthdate</span> <span class="font-medium"><?= date('F j, Y', strtotime($profile['birthdate'])) ?></span></li>
                                <li class="flex justify-between"><span class="text-gray-500">Sex</span> <span class="font-medium"><?= htmlspecialchars($profile['sex']) ?></span></li>
                                <li class="flex justify-between"><span class="text-gray-500">Civil Status</span> <span class="font-medium"><?= htmlspecialchars($profile['civil_status']) ?></span></li>
                                <li class="flex justify-between"><span class="text-gray-500">Address</span> <span class="font-medium text-right w-1/2"><?= htmlspecialchars($profile['address']) ?></span></li>
                            </ul>
                        </div>

                        <!-- Contact Details -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4"><i class="ph ph-phone text-green-600 mr-2"></i>Contact Details</h3>
                            <ul class="space-y-3 text-sm">
                                <li class="flex justify-between"><span class="text-gray-500">Mobile</span> <span class="font-medium"><?= htmlspecialchars($profile['contact_number'] ?: 'N/A') ?></span></li>
                                <li class="flex justify-between"><span class="text-gray-500">Email</span> <span class="font-medium"><?= htmlspecialchars($profile['email'] ?: 'N/A') ?></span></li>
                                <li class="flex justify-between"><span class="text-gray-500">Emergency Contact</span> <span class="font-medium"><?= htmlspecialchars($profile['emergency_contact'] ?: 'N/A') ?></span></li>
                                <li class="flex justify-between"><span class="text-gray-500">Parent/Guardian</span> <span class="font-medium"><?= htmlspecialchars($profile['parent_guardian'] ?: 'N/A') ?></span></li>
                            </ul>
                        </div>

                        <!-- Government IDs -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4"><i class="ph ph-bank text-green-600 mr-2"></i>Government IDs</h3>
                            <ul class="space-y-3 text-sm">
                                <li class="flex justify-between"><span class="text-gray-500">PhilSys ID</span> <span class="font-medium"><?= htmlspecialchars($profile['philsys_id'] ?: 'None') ?></span></li>
                                <li class="flex justify-between"><span class="text-gray-500">PhilHealth No.</span> <span class="font-medium"><?= htmlspecialchars($profile['philhealth_no'] ?: 'None') ?></span></li>
                                <li class="flex justify-between items-center"><span class="text-gray-500">Voter Status</span> 
                                    <span class="font-bold text-xs px-2.5 py-1 rounded-full <?= $profile['is_voter'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                        <?= $profile['is_voter'] ? 'REGISTERED VOTER' : 'NOT REGISTERED' ?>
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <!-- Education & Affiliations -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4"><i class="ph ph-graduation-cap text-green-600 mr-2"></i>Education & Affiliations</h3>
                            <ul class="space-y-3 text-sm">
                                <li class="flex flex-col"><span class="text-gray-500 mb-1">Highest Education</span> <span class="font-medium"><?= htmlspecialchars($profile['education_level']) ?></span></li>
                                <li class="flex flex-col"><span class="text-gray-500 mb-1">Skills/Talents</span> <span class="font-medium bg-gray-50 p-2.5 rounded-lg border border-gray-200"><?= nl2br(htmlspecialchars($profile['skills'] ?: 'None listed')) ?></span></li>
                                <li class="flex flex-col"><span class="text-gray-500 mb-1">Organizations</span> <span class="font-medium bg-gray-50 p-2.5 rounded-lg border border-gray-200"><?= nl2br(htmlspecialchars($profile['organizations'] ?: 'None listed')) ?></span></li>
                            </ul>
                        </div>

                        <!-- Programs & Remarks -->
                        <div class="md:col-span-2">
                            <h3 class="text-lg font-bold text-gray-800 border-b pb-2 mb-4"><i class="ph ph-clipboard-text text-green-600 mr-2"></i>Original Registration Remarks</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                                <div>
                                    <span class="block text-gray-500 mb-1">Legacy Programs Availed</span>
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 min-h-[80px] text-gray-700">
                                        <?= nl2br(htmlspecialchars($profile['programs_availed'] ?: 'No legacy programs listed.')) ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="block text-gray-500 mb-1">Admin Remarks</span>
                                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 min-h-[80px] text-gray-700">
                                        <?= nl2br(htmlspecialchars($profile['remarks'] ?: 'No remarks added.')) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ACTIVITY LEDGER CARD -->
                <div class="bg-white shadow-xl rounded-xl overflow-hidden border border-gray-200 p-8">
                    <div class="flex justify-between items-center border-b pb-4 mb-6">
                        <h3 class="text-xl font-bold text-gray-800"><i class="ph ph-list-checks text-green-600 mr-2"></i>Official Activity Ledger</h3>
                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-bold"><?= count($activities) ?> Activities Logged</span>
                    </div>
                    
                    <?php if (count($activities) > 0): ?>
                        <div class="relative border-l-2 border-green-200 ml-3 space-y-6">
                            <?php foreach ($activities as $act): ?>
                                <div class="relative pl-6">
                                    <!-- Timeline Dot -->
                                    <div class="absolute -left-[9px] top-1 w-4 h-4 rounded-full bg-green-500 border-4 border-white shadow"></div>
                                    
                                    <div class="bg-gray-50 hover:bg-green-50 border border-gray-200 hover:border-green-300 p-4 rounded-lg shadow-sm transition">
                                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-2 gap-2">
                                            <div>
                                                <span class="bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded uppercase tracking-wider"><?= htmlspecialchars($act['activity_category']) ?></span>
                                                <h4 class="text-lg font-bold text-gray-800 mt-1.5"><?= htmlspecialchars($act['activity_name']) ?></h4>
                                            </div>
                                            <span class="text-sm font-medium text-gray-600 flex items-center bg-white px-2 py-1 rounded border border-gray-200"><i class="ph ph-calendar-blank mr-1.5 text-green-600"></i> <?= date('M j, Y', strtotime($act['date_attended'])) ?></span>
                                        </div>
                                        <p class="text-xs text-gray-400 mt-3 border-t border-gray-200 pt-2 flex items-center"><i class="ph-fill ph-check-circle mr-1 text-green-500"></i> Verified and logged by: <strong class="ml-1 text-gray-600"><?= htmlspecialchars($act['logged_by']) ?></strong></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12 bg-gray-50 rounded-xl border-2 border-dashed border-gray-300">
                            <div class="w-16 h-16 bg-gray-200 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="ph ph-folder-open text-3xl"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-700">No Activities Yet</h4>
                            <p class="text-gray-500 text-sm mt-1 max-w-sm mx-auto">This youth has not been assigned to any official MYDO programs or activities. Use the form on the right to log their attendance.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- RIGHT COLUMN: Add Activity Form -->
            <div class="xl:col-span-1">
                <div class="bg-white shadow-xl rounded-xl border border-gray-200 p-6 sticky top-6">
                    <h3 class="text-lg font-bold text-green-900 border-b pb-2 mb-2 flex items-center">
                        <i class="ph-fill ph-plus-circle mr-2 text-xl"></i> Log New Activity
                    </h3>
                    
                    <p class="text-xs text-gray-500 mb-6 leading-relaxed">Authorized Admins and SK Officials can officially record attendance for MYDO programs here. This data is permanently attached to the youth's ledger.</p>

                    <form action="add_activity.php" method="POST" class="space-y-5">
                        <input type="hidden" name="youth_id" value="<?= htmlspecialchars($profile['youth_id']) ?>">
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Program Category</label>
                            <select name="activity_category" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none text-sm bg-white shadow-sm" required>
                                <option value="" disabled selected>Select Category...</option>
                                <option value="Youth Camp">🏕️ Youth Camp</option>
                                <option value="Leadership Training">🎯 Leadership Training</option>
                                <option value="Sports">🏀 Sports</option>
                                <option value="Scholarship">🎓 Scholarship</option>
                                <option value="Seminars">🗣️ Seminars</option>
                                <option value="Tree Planting">🌳 Tree Planting</option>
                                <option value="Volunteerism">🤝 Volunteerism</option>
                                <option value="Other">📌 Other Activity</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Specific Program Name</label>
                            <input type="text" name="activity_name" placeholder="e.g. 2026 Inter-Barangay Liga" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none text-sm shadow-sm" required>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Date Attended</label>
                            <input type="date" name="date_attended" value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 outline-none text-sm shadow-sm" required>
                        </div>

                        <button type="submit" class="w-full bg-green-700 hover:bg-green-800 text-white font-bold py-3.5 px-4 rounded-lg shadow-md transition duration-300 mt-4 flex items-center justify-center">
                            <i class="ph ph-check-square-offset mr-2 text-xl"></i> Record Attendance
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</body>
</html>