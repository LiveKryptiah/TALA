<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require 'db_connect.php'; 

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM youth_registry WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("<h3 style='color:red; text-align:center; margin-top:50px;'>Profile parameters not initialized or record missing.</h3>");
    }
} catch (PDOException $e) {
    die("Database Configuration Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <title>Profile View | <?php echo htmlspecialchars($user['full_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] }, colors: { brand: { 50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6', 600: '#2563eb' } } } } }</script>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased">

    <?php include 'sidebar_admin.php'; ?>

    <div class="ml-64 p-8 max-w-5xl">
        <!-- Breadcrumb & Actions -->
        <div class="mb-6 flex justify-between items-center">
            <a href="tala_directory.php" class="text-sm font-semibold text-slate-500 hover:text-brand-600 flex items-center gap-1"><i class="ph-bold ph-arrow-left"></i> Back to Directory</a>
        </div>

        <!-- Main Account Header Card -->
        <div class="bg-white rounded-3xl border border-slate-200/60 p-6 shadow-sm mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4 text-center sm:text-left flex-col sm:flex-row">
                <div class="w-16 h-16 bg-brand-100 text-brand-600 font-extrabold rounded-2xl flex items-center justify-center text-xl border border-brand-200">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-900 leading-tight"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                    <p class="text-sm text-slate-400 font-medium mt-0.5">📍 Barangay <?php echo htmlspecialchars($user['barangay']); ?> • ID: #<?php echo $user['id']; ?></p>
                </div>
            </div>
            <div>
                <?php 
                    $status = htmlspecialchars($user['document_status']);
                    $badge = (strpos(strtolower($status), 'complete') !== false) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200';
                ?>
                <span class="px-4 py-2 border font-bold text-sm rounded-xl <?php echo $badge; ?>"><?php echo $status; ?></span>
            </div>
        </div>

        <!-- 3-Column Profile Bento Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Left Panel: Core Contact & Bio Info -->
            <div class="md:col-span-1 space-y-6">
                <div class="bg-white p-5 rounded-2xl border border-slate-200/60 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Contact Profile</h3>
                    <div class="space-y-4">
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 block uppercase">Mobile Number</span>
                            <span class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($user['cellphone_number'] ?: 'None provided'); ?></span>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 block uppercase">Email Address</span>
                            <span class="text-sm font-semibold text-slate-800 break-all"><?php echo htmlspecialchars($user['email_address'] ?: 'None provided'); ?></span>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 block uppercase">Civil Status</span>
                            <span class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($user['civil_status'] ?: 'Unspecified'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200/60 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Biometrics</h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <span class="text-[11px] font-bold text-slate-400 block uppercase">Age</span>
                                <span class="text-sm font-semibold text-slate-800"><?php echo (int)$user['age'] ?: 'N/A'; ?></span>
                            </div>
                            <div>
                                <span class="text-[11px] font-bold text-slate-400 block uppercase">Sex</span>
                                <span class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($user['sex']); ?></span>
                            </div>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 block uppercase">Date of Birth</span>
                            <span class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($user['birthday'] ?: 'N/A'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Core Metrics & Verification Docs -->
            <div class="md:col-span-2 space-y-6">
                <!-- Academic & Labor Profile -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-1.5"><i class="ph-bold ph-graduation-cap"></i> Academic & Job Profile</h3>
                    <div class="col-span-1 sm:col-span-2 mt-2 pt-4 border-t border-slate-100">
    <span class="text-[11px] font-bold text-slate-400 uppercase flex items-center gap-1"><i class="ph-fill ph-seal-check text-green-500"></i> DepEd Verification Status</span>
    <?php 
        $dStatus = htmlspecialchars($user['deped_enrollment_status'] ?? 'Pending Verification');
        if ($dStatus === 'Enrolled') $dBadge = 'bg-green-100 text-green-700 border-green-200';
        elseif ($dStatus === 'Not Enrolled' || $dStatus === 'Dropped Out') $dBadge = 'bg-red-100 text-red-700 border-red-200';
        elseif ($dStatus === 'Graduated') $dBadge = 'bg-blue-100 text-blue-700 border-blue-200';
        else $dBadge = 'bg-amber-100 text-amber-700 border-amber-200';
    ?>
    <p class="mt-1.5"><span class="px-3 py-1 text-xs font-bold rounded-lg border <?php echo $dBadge; ?>"><?php echo $dStatus; ?></span></p>
</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase">Educational Track</span>
                            <p class="text-sm font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($user['educational_background']); ?></p>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase">Year / Level</span>
                            <p class="text-sm font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($user['year_level'] ?: 'N/A'); ?></p>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-[11px] font-bold text-slate-400 uppercase">Institution / School Name</span>
                            <p class="text-sm font-semibold text-slate-700 mt-0.5"><?php echo htmlspecialchars($user['name_of_school'] ?: 'N/A'); ?></p>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase">Employment Status</span>
                            <p class="text-sm font-bold text-slate-800 mt-0.5"><?php echo htmlspecialchars($user['work_status'] ?: 'N/A'); ?></p>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase">SK Classification</span>
                            <p class="text-sm font-bold text-brand-600 mt-0.5"><?php echo htmlspecialchars($user['youth_classification']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Civic Participation Metrics -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-1.5"><i class="ph-bold ph-gavel"></i> Civic Status & KK Engagement</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-b border-slate-100 pb-4 mb-4">
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase">Registered SK Voter?</span>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5"><?php echo htmlspecialchars($user['registered_sk_voter'] ?: 'No Response'); ?></p>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase">Registered National Voter?</span>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5"><?php echo htmlspecialchars($user['registered_national_voter'] ?: 'No Response'); ?></p>
                        </div>
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase">Voted 2023 SK?</span>
                            <p class="text-sm font-semibold text-slate-800 mt-0.5"><?php echo htmlspecialchars($user['voted_2023'] ?: 'No Response'); ?></p>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <span class="text-[11px] font-bold text-slate-400 uppercase">Katipunan ng Kabataan (KK) Assembly Records</span>
                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 text-sm">
                            <p class="font-medium text-slate-700">Attended KK Assemblies? <span class="font-bold text-slate-900"><?php echo htmlspecialchars($user['attended_kk_assembly'] ?: 'No Response'); ?></span></p>
                            <?php if(!empty($user['sk_assembly_times']) && strtolower($user['sk_assembly_times']) !== 'n/a'): ?>
                                <p class="text-emerald-600 font-semibold mt-1">📊 Attendance frequency details: <?php echo htmlspecialchars($user['sk_assembly_times']); ?></p>
                            <?php endif; ?>
                            <?php if(!empty($user['sk_assembly_why_no'])): ?>
                                <p class="text-amber-700 font-medium mt-1">⚠️ Stated reason for non-attendance: "<?php echo htmlspecialchars($user['sk_assembly_why_no']); ?>"</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Live Documentation Attachment Review links -->
                <div class="bg-white p-6 rounded-2xl border border-slate-200/60 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Cloud Documents (Google Drive)</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <?php 
                        $files = [
                            ['title' => 'Birth Certificate', 'field' => 'birth_cert_file', 'icon' => 'ph-file-text', 'color' => 'bg-blue-50 text-blue-600 border-blue-100'],
                            ['title' => 'Valid ID File', 'field' => 'valid_id_file', 'icon' => 'ph-identification-card', 'color' => 'bg-purple-50 text-purple-600 border-purple-100'],
                            ['title' => 'Residency Clearance', 'field' => 'residency_file', 'icon' => 'ph-house-line', 'color' => 'bg-emerald-50 text-emerald-600 border-emerald-100']
                        ];
                        foreach($files as $f): 
                            $hasFile = !empty($user[$f['field']]);
                        ?>
                            <div class="p-3 border rounded-xl flex flex-col justify-between items-start gap-3 <?php echo $hasFile ? 'bg-white border-slate-200' : 'bg-red-50/50 border-red-100'; ?>">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-lg border <?php echo $hasFile ? $f['color'] : 'bg-red-100 text-red-500 border-red-200'; ?>"><i class="ph-bold <?php echo $f['icon']; ?>"></i></div>
                                    <span class="text-xs font-bold text-slate-700"><?php echo $f['title']; ?></span>
                                </div>
                                <?php if($hasFile): ?>
                                    <a href="<?php echo htmlspecialchars($user[$f['field']]); ?>" target="_blank" class="w-full text-center py-1.5 bg-slate-50 hover:bg-brand-50 hover:text-brand-600 text-[11px] font-bold border rounded-lg transition-colors">Open Drive File</a>
                                <?php else: ?>
                                    <span class="text-[11px] font-bold text-red-600 px-2 py-1 bg-red-100/50 rounded-md w-full text-center">Missing Upload</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>