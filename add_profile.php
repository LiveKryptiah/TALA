<?php
session_start();
require 'db_connect.php';

// Security Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$myBarangay = $_SESSION['barangay_assigned'] ?? '';

// Array of all 46 Tumauini Barangays
$all_barangays = [
    "Annafunan", "Antagan I", "Antagan II", "Arcon", "Balug", "Banig", "Bantug", 
    "Barangay District 1 (Poblacion)", "Barangay District 2 (Poblacion)", 
    "Barangay District 3 (Poblacion)", "Barangay District 4 (Poblacion)", 
    "Bayabo East", "Caligayan", "Camasi", "Carpentero", "Compania", "Cumabao", 
    "Fermeldy (Hacienda San Francisco)", "Fugu Abajo", "Fugu Norte", "Fugu Sur", 
    "Lalauanan", "Lanna", "Lapogan", "Lingaling (Poblacion)", "Liwanag", 
    "Malamag East", "Malamag West", "Maligaya (Poblacion)", "Minanga", "Moldero", 
    "Namnama", "Paragu", "Pilitan", "San Mateo", "San Pedro", "San Vicente", "Santa", 
    "Santa Catalina", "Santa Visitacion (Maggayu)", "Santo Niño", "Sinippil", 
    "Sisim Abajo", "Sisim Alto", "Tunggui", "Ugad"
];

$displayName = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'User';
$displayInitial = strtoupper(substr($displayName, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Youth Profile | TALA OS</title>
    
    <!-- Modern Enterprise SaaS Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
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
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        
        .hover-gradient-btn { transition: all 0.3s ease; }
        .hover-gradient-btn:hover {
            background: linear-gradient(135deg, #16a34a, #eab308) !important;
            color: white !important;
            border-color: transparent !important;
            box-shadow: 0 10px 25px -5px rgba(22, 163, 74, 0.4) !important;
        }
    </style>
</head>
<body class="text-slate-800 antialiased flex h-screen overflow-hidden selection:bg-brand-500 selection:text-white">

    <!-- GLOBAL AMBIENT MESH GRADIENT BACKGROUND -->
    <div class="ambient-bg">
        <div class="blob bg-brand-200 w-[500px] h-[500px] -top-[10%] -left-[10%]"></div>
        <div class="blob bg-yellow-200 w-[500px] h-[500px] bottom-[10%] -right-[10%] animation-delay-2000"></div>
    </div>

    <!-- SIDEBAR INCLUDE -->
    <?php 
    if ($isAdmin) {
        include 'sidebar_admin.php';
    } else {
        include 'sidebar_sk.php';
    }
    ?>

    <!-- MAIN CONTENT -->
    <main class="flex-1 overflow-y-auto p-4 md:p-8 ml-64 relative z-10 custom-scrollbar">
        <div class="max-w-6xl mx-auto space-y-8 pb-10">
            
            <!-- HEADER -->
            <div class="flex justify-between items-end pb-4 border-b border-slate-200">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Manual Registration</h1>
                    <p class="text-sm font-semibold text-slate-500 mt-1 uppercase tracking-widest">Profile Registration Module</p>
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

            <div class="flex justify-between items-center mb-6">
                <a href="youth_profiles.php" class="inline-flex items-center gap-2 text-xs font-extrabold text-slate-500 hover:text-brand-600 transition-colors bg-white/80 px-5 py-2.5 rounded-full backdrop-blur-md shadow-sm border border-white uppercase tracking-widest">
                    <i class="ph-bold ph-arrow-left text-lg"></i> Back to Directory
                </a>
            </div>

            <!-- FORM CONTAINER -->
            <div class="glass-card rounded-[2.5rem] shadow-soft border border-slate-200 overflow-hidden">
                
                <!-- Form Header -->
                <div class="bg-gradient-to-r from-slate-900 to-slate-800 p-8 md:px-10 text-white flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center p-1 shadow-lg">
                            <img src="TALA-LOGO.ico" alt="TALA Logo" class="w-10 h-10 object-contain drop-shadow-sm" onerror="this.outerHTML='<i class=\'ph-fill ph-users-three text-3xl text-brand-600\'></i>'">
                        </div>
                        <div>
                            <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight">TALA Registration</h2>
                            <p class="text-[10px] font-bold text-brand-400 uppercase tracking-widest mt-1">Tumauini Active Youth Ledger</p>
                        </div>
                    </div>
                    <div class="bg-white/10 backdrop-blur-md px-5 py-3 rounded-xl border border-white/10">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-300">System Mode</p>
                        <p class="text-sm font-extrabold text-white flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-brand-500 animate-pulse"></span> Authorized Intake
                        </p>
                    </div>
                </div>

                <form action="process_profile.php" method="POST" enctype="multipart/form-data" class="p-6 md:p-10 space-y-8 bg-white/50">
                    
                    <!-- SECTION 1: Identification -->
                    <div class="bg-white border border-slate-100 p-6 md:p-8 rounded-[2rem] shadow-sm">
                        <div class="flex items-center gap-3 mb-6 border-b border-slate-200 pb-4">
                            <div class="w-8 h-8 bg-brand-50 text-brand-600 rounded-xl flex items-center justify-center font-extrabold text-sm border border-brand-100">1</div>
                            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Identification</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Youth ID</label>
                                <input type="text" name="youth_id" value="Auto-generated upon save" readonly class="w-full bg-slate-50 border border-slate-200 text-slate-500 rounded-xl px-4 py-3 text-sm font-bold cursor-not-allowed shadow-inner italic">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Upload ID Photo <span class="text-red-500">*</span></label>
                                <input type="file" name="id_photo" accept="image/*" required class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-[10px] file:font-extrabold file:uppercase file:tracking-widest file:bg-brand-50 file:text-brand-600 hover:file:bg-brand-100 cursor-pointer transition-all border border-slate-200 p-1.5 rounded-xl bg-white shadow-sm">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2: Personal Information -->
                    <div class="bg-white border border-slate-100 p-6 md:p-8 rounded-[2rem] shadow-sm">
                        <div class="flex items-center gap-3 mb-6 border-b border-slate-200 pb-4">
                            <div class="w-8 h-8 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center font-extrabold text-sm border border-blue-100">2</div>
                            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Personal Information</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="md:col-span-3">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Full Name (Last, First, M.I.) <span class="text-red-500">*</span></label>
                                <input type="text" name="full_name" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm" required placeholder="Dela Cruz, Juan M.">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Birthday <span class="text-red-500">*</span></label>
                                <input type="date" name="birthdate" id="birthdate" onchange="calculateAge()" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm" required>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Age</label>
                                <input type="number" name="age" id="age" readonly placeholder="Auto-calculated" class="w-full bg-slate-100 border border-slate-200 text-slate-600 rounded-xl px-4 py-3 text-sm font-bold cursor-not-allowed shadow-inner">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Sex <span class="text-red-500">*</span></label>
                                <select name="sex" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm cursor-pointer appearance-none" required>
                                    <option value="" disabled selected>Select...</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Civil Status <span class="text-red-500">*</span></label>
                                <select name="civil_status" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm cursor-pointer appearance-none" required>
                                    <option value="" disabled selected>Select...</option>
                                    <option value="Single">Single</option>
                                    <option value="Married">Married</option>
                                    <option value="Widowed">Widowed</option>
                                    <option value="Separated">Separated</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 3: Contact & Location -->
                    <div class="bg-white border border-slate-100 p-6 md:p-8 rounded-[2rem] shadow-sm">
                        <div class="flex items-center gap-3 mb-6 border-b border-slate-200 pb-4">
                            <div class="w-8 h-8 bg-yellow-50 text-yellow-600 rounded-xl flex items-center justify-center font-extrabold text-sm border border-yellow-100">3</div>
                            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Contact & Location</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Address (Street/Purok) <span class="text-red-500">*</span></label>
                                <input type="text" name="address" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm" required placeholder="House No., Street Name">
                            </div>
                            
                            <!-- SMART BARANGAY FIELD -->
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Barangay <span class="text-red-500">*</span></label>
                                <?php if ($isAdmin): ?>
                                    <select name="barangay" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm cursor-pointer appearance-none" required>
                                        <option value="" disabled selected>Select Barangay...</option>
                                        <?php foreach ($all_barangays as $brgy): ?>
                                            <option value="<?= $brgy ?>"><?= $brgy ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <div class="relative">
                                        <i class="ph-bold ph-lock-key absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                        <input type="text" name="barangay" value="<?= htmlspecialchars($myBarangay) ?>" readonly class="w-full bg-slate-100 border border-slate-200 text-slate-600 rounded-xl px-4 py-3 text-sm font-bold cursor-not-allowed shadow-inner">
                                    </div>
                                    <p class="text-[9px] font-bold uppercase tracking-widest text-brand-600 mt-2 flex items-center gap-1"><i class="ph-fill ph-info"></i> Locked to your assigned jurisdiction</p>
                                <?php endif; ?>
                            </div>

                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Contact Number</label>
                                <input type="tel" name="contact_number" placeholder="09XXXXXXXXX" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Email Address</label>
                                <input type="email" name="email" placeholder="you@example.com" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Parent/Guardian Name</label>
                                <input type="text" name="parent_guardian" placeholder="Full Name" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Emergency Contact Number</label>
                                <input type="tel" name="emergency_contact" placeholder="09XXXXXXXXX" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 4: Government IDs & Voting -->
                    <div class="bg-white border border-slate-100 p-6 md:p-8 rounded-[2rem] shadow-sm">
                        <div class="flex items-center gap-3 mb-6 border-b border-slate-200 pb-4">
                            <div class="w-8 h-8 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center font-extrabold text-sm border border-purple-100">4</div>
                            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Government Info</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">PhilSys ID Number</label>
                                <input type="text" name="philsys_id" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">PhilHealth Number</label>
                                <input type="text" name="philhealth_no" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Registered Voter?</label>
                                <select name="is_voter" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm cursor-pointer appearance-none">
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 5: Socio-Economic Profile -->
                    <div class="bg-white border border-slate-100 p-6 md:p-8 rounded-[2rem] shadow-sm">
                        <div class="flex items-center gap-3 mb-6 border-b border-slate-200 pb-4">
                            <div class="w-8 h-8 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center font-extrabold text-sm border border-orange-100">5</div>
                            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Socio-Economic Profile</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Highest Educational Attainment <span class="text-red-500">*</span></label>
                                <select name="education_level" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm cursor-pointer appearance-none" required>
                                    <option value="" disabled selected>Select Level...</option>
                                    <option value="Elementary Level">Elementary Level</option>
                                    <option value="Elementary Graduate">Elementary Graduate</option>
                                    <option value="High School Level">High School Level</option>
                                    <option value="High School Graduate">High School Graduate</option>
                                    <option value="College Level">College Level</option>
                                    <option value="College Graduate">College Graduate</option>
                                    <option value="Vocational/Tech">Vocational/Tech</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Employment Status <span class="text-red-500">*</span></label>
                                <select name="employment_status" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm cursor-pointer appearance-none" required>
                                    <option value="" disabled selected>Select Status...</option>
                                    <option value="Student">Student</option>
                                    <option value="Employed">Employed</option>
                                    <option value="Unemployed">Unemployed</option>
                                    <option value="Self-Employed">Self-Employed</option>
                                    <option value="Out-of-School Youth (OSY)">Out-of-School Youth (OSY)</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Skills / Talents</label>
                                <textarea name="skills" rows="2" placeholder="e.g., Graphic Design, Public Speaking, Carpentry..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm resize-none custom-scrollbar"></textarea>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Organizations Affiliated With</label>
                                <textarea name="organizations" rows="2" placeholder="e.g., Sangguniang Kabataan, Boy Scouts, Local Dance Troupe..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm resize-none custom-scrollbar"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 6: Analytics & Remarks -->
                    <div class="bg-white border border-slate-100 p-6 md:p-8 rounded-[2rem] shadow-sm">
                        <div class="flex items-center gap-3 mb-6 border-b border-slate-200 pb-4">
                            <div class="w-8 h-8 bg-slate-100 text-slate-600 rounded-xl flex items-center justify-center font-extrabold text-sm border border-slate-200">6</div>
                            <h3 class="text-xl font-extrabold text-slate-800 tracking-tight">Analytics & Remarks</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Programs Availed</label>
                                <textarea name="programs_availed" rows="2" placeholder="List any municipal/barangay youth programs this person has participated in..." class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm resize-none custom-scrollbar"></textarea>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Remarks / Additional Notes</label>
                                <textarea name="remarks" rows="2" class="w-full bg-slate-50 border border-slate-200 text-slate-800 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-brand-500/30 focus:border-brand-500 transition-all shadow-sm resize-none custom-scrollbar"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="pt-6 flex flex-col-reverse md:flex-row justify-end gap-4 border-t border-slate-100 mt-8">
                        <button type="reset" class="px-8 py-3.5 bg-white border border-slate-200 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-50 font-bold transition-all text-center shadow-sm">
                            Clear Form
                        </button>
                        <button type="submit" class="px-8 py-3.5 bg-slate-900 text-white font-bold rounded-xl shadow-lg transform hover:-translate-y-0.5 hover-gradient-btn transition-all flex items-center justify-center gap-2">
                            <i class="ph-bold ph-floppy-disk text-lg"></i> Save Youth Profile
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </main>

    <!-- GENESIS WIDGET -->
    <?php include 'genesis_widget.php'; ?>

    <!-- SCRIPTS -->
    <script>
        // Auto-calculate Age Logic
        function calculateAge() {
            const birthdate = document.getElementById('birthdate').value;
            if (birthdate) {
                const today = new Date();
                const birthDate = new Date(birthdate);
                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                
                // Subtract 1 if the birthday hasn't happened yet this year
                if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                document.getElementById('age').value = age;
            }
        }
    </script>
</body>
</html>
