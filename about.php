<?php
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
$dashboardLink = 'dashboard.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About TALA | Project Roadmap</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- FIX: Updated Phosphor Icons to the official v2 Web Script for guaranteed loading -->
    <script src="https://unpkg.com/@phosphor-icons/web@2.1.1"></script>
    
    <!-- Custom Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    boxShadow: { 'soft': '0 10px 40px -10px rgba(0,0,0,0.05)' }
                }
            }
        }
    </script>
</head>
<body class="bg-[#f8fafc] text-slate-800 antialiased selection:bg-green-200 relative">

    <!-- Decorative Background Gradients (Green to Yellow tint) -->
    <div class="absolute top-0 left-0 w-full h-[500px] bg-gradient-to-b from-green-50 via-yellow-50/20 to-transparent -z-10 pointer-events-none"></div>

    <!-- Glassmorphic Navigation Bar -->
    <nav class="sticky top-0 z-50 bg-white/70 backdrop-blur-md border-b border-white/20 shadow-sm px-6 py-4 flex justify-between items-center transition-all">
        <div class="font-extrabold tracking-widest text-lg text-green-900 flex items-center gap-2">
            <!-- Icon with green-to-yellow gradient text simulation -->
            <i class="ph-fill ph-planet text-green-600 text-2xl"></i> TALA
        </div>
        <?php if ($isLoggedIn): ?>
            <a href="<?= $dashboardLink ?>" class="text-sm font-semibold bg-slate-900 text-white hover:bg-gradient-to-r hover:from-green-600 hover:to-yellow-500 px-5 py-2.5 rounded-full transition-all duration-300 flex items-center gap-2 shadow-md hover:shadow-green-500/25 hover:-translate-y-0.5">
                <i class="ph-bold ph-squares-four"></i> Dashboard
            </a>
        <?php else: ?>
            <a href="login.php" class="text-sm font-semibold bg-gradient-to-r from-green-600 to-green-500 text-white hover:from-green-500 hover:to-yellow-500 px-6 py-2.5 rounded-full transition-all duration-300 shadow-md hover:shadow-green-500/25 hover:-translate-y-0.5">Login</a>
        <?php endif; ?>
    </nav>

    <main class="max-w-6xl mx-auto py-16 px-6 lg:px-8">
        
        <!-- Hero Section -->
        <div class="flex flex-col items-center text-center mb-24">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-gradient-to-r from-green-100 to-yellow-100 border border-green-200 text-green-800 text-xs font-bold uppercase tracking-widest mb-8 shadow-sm">
                <i class="ph-fill ph-sparkle text-yellow-600"></i> Platform Architecture
            </div>
            
            <div class="flex items-center justify-center gap-6 mb-8">
                <div class="relative group">
                    <div class="absolute -inset-1 bg-gradient-to-r from-green-400 to-yellow-400 rounded-full blur opacity-25 group-hover:opacity-60 transition duration-500"></div>
                    <!-- SK Logo -->
                    <img src="TALA-LOGO.png" alt="Sangguniang Kabataan Logo" class="relative w-20 h-20 object-contain rounded-full bg-white p-1">
                </div>
                <div class="h-10 w-[2px] bg-slate-200 rounded-full"></div>
                <div class="relative group">
                    <div class="absolute -inset-1 bg-gradient-to-r from-green-400 to-yellow-400 rounded-full blur opacity-25 group-hover:opacity-60 transition duration-500"></div>
                    <!-- Tumauini LGU Logo -->
                    <img src="https://provinceofisabela.ph/images/LGU-SEAL/tumauini-isabela.png" alt="Tumauini LGU Logo" class="relative w-20 h-20 object-contain rounded-full bg-white p-1">
                </div>
            </div>
            
            <!-- Green to Yellow Gradient Text -->
            <h1 class="text-5xl md:text-6xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-green-800 via-green-600 to-yellow-500 mb-6 tracking-tight">
                About TALA
            </h1>
            <p class="text-lg md:text-xl text-slate-500 max-w-2xl leading-relaxed font-medium">
                A comprehensive digital management platform bridging the gap between the City Youth Development Office and local SK Chairpersons.
            </p>
        </div>

        <!-- 3-Tier Architecture Cards -->
        <div class="mb-24">
            <div class="text-center mb-10">
                <h2 class="text-3xl font-bold text-slate-800 tracking-tight">User Controls & Security</h2>
                <p class="text-slate-500 mt-3">Strict Session Variable routing ensures data integrity across a three-tier architecture.</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-6">
                <!-- Admin Card -->
                <div class="group bg-white p-8 rounded-3xl border border-slate-100 shadow-soft hover:shadow-xl hover:-translate-y-1 transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-green-50 rounded-bl-[100px] -z-10 transition-transform group-hover:scale-110"></div>
                    <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-yellow-400 text-white rounded-2xl flex items-center justify-center text-2xl mb-6 shadow-lg shadow-green-500/30">
                        <i class="ph-fill ph-user-gear"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-3">Admin (LGU)</h3>
                    <p class="text-sm text-slate-500 mb-6 font-medium">Master control with full CRUD capabilities over the entire city-wide platform.</p>
                    <ul class="text-sm text-slate-600 space-y-3 font-medium">
                        <li class="flex items-start gap-2"><i class="ph-bold ph-plus-circle text-green-500 mt-0.5"></i> Broadcast events & scholarships</li>
                        <li class="flex items-start gap-2"><i class="ph-bold ph-eye text-yellow-500 mt-0.5"></i> Track SK compliance & profiles</li>
                        <li class="flex items-start gap-2"><i class="ph-bold ph-arrows-clockwise text-orange-500 mt-0.5"></i> Manage system settings</li>
                    </ul>
                </div>

                <!-- SK Card -->
                <div class="group bg-white p-8 rounded-3xl border border-slate-100 shadow-soft hover:shadow-xl hover:-translate-y-1 transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-blue-50 rounded-bl-[100px] -z-10 transition-transform group-hover:scale-110"></div>
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-cyan-400 text-white rounded-2xl flex items-center justify-center text-2xl mb-6 shadow-lg shadow-blue-500/30">
                        <i class="ph-fill ph-shield-star"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-3">SK Official</h3>
                    <p class="text-sm text-slate-500 mb-6 font-medium">Focused on barangay-level execution, youth coordination, and reporting.</p>
                    <ul class="text-sm text-slate-600 space-y-3 font-medium">
                        <li class="flex items-start gap-2"><i class="ph-bold ph-users text-blue-500 mt-0.5"></i> Manage local youth databases</li>
                        <li class="flex items-start gap-2"><i class="ph-bold ph-camera text-blue-500 mt-0.5"></i> Log digital event attendance</li>
                        <li class="flex items-start gap-2"><i class="ph-bold ph-chats text-blue-500 mt-0.5"></i> Monitor constituent suggestions</li>
                    </ul>
                </div>

                <!-- Public User Card -->
                <div class="group bg-white p-8 rounded-3xl border border-slate-100 shadow-soft hover:shadow-xl hover:-translate-y-1 transition-all duration-300 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-orange-50 rounded-bl-[100px] -z-10 transition-transform group-hover:scale-110"></div>
                    <div class="w-14 h-14 bg-gradient-to-br from-orange-400 to-rose-400 text-white rounded-2xl flex items-center justify-center text-2xl mb-6 shadow-lg shadow-orange-500/30">
                        <i class="ph-fill ph-users"></i>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-3">Public User</h3>
                    <p class="text-sm text-slate-500 mb-6 font-medium">Community engagement, accountability tracking, and accessible information.</p>
                    <ul class="text-sm text-slate-600 space-y-3 font-medium">
                        <li class="flex items-start gap-2"><i class="ph-bold ph-megaphone text-orange-500 mt-0.5"></i> View public announcements</li>
                        <li class="flex items-start gap-2"><i class="ph-bold ph-graduation-cap text-orange-500 mt-0.5"></i> Browse scholarship programs</li>
                        <li class="flex items-start gap-2"><i class="ph-bold ph-paper-plane-tilt text-orange-500 mt-0.5"></i> Submit community feedback</li>
                    </ul>
                </div>
            </div>
        </div>

     
    <!-- Modern Phase Timeline (10-Week) -->
<div class="mb-24 max-w-4xl mx-auto">
    <div class="text-center mb-16">
        <span class="inline-block bg-gradient-to-r from-green-100 to-yellow-100 text-green-800 text-xs font-extrabold px-4 py-1.5 rounded-full uppercase tracking-widest border border-green-200/50 shadow-sm mb-3">
            Phase 1: Completed
        </span>

        <h2 class="text-3xl font-bold text-slate-800 tracking-tight">
            10-Week Implementation Roadmap
        </h2>
    </div>

    <?php
    $timeline_completed = [
        [
            'week' => '1', 'icon' => 'ph-shield-check', 'title' => 'Authentication & User Control',
            'desc' => 'Established the system security perimeter by developing secure registration, encrypted password hashing, session management, and a Role-Based Access Control (RBAC) architecture.'
        ],
        [
            'week' => '2', 'icon' => 'ph-desktop', 'title' => 'Dynamic UI/UX & Dashboards',
            'desc' => 'Implemented a modular Tailwind CSS interface with role-based dashboards and dynamic navigation. Separate administrator and SK official interfaces provide a streamlined experience.'
        ],
        [
            'week' => '3', 'icon' => 'ph-identification-card', 'title' => 'Youth Profiling System',
            'desc' => 'Developed the youth profiling module and database structure, enabling SK officials to manage demographic records while administrators consolidate data for planning.'
        ],
        [
            'week' => '4', 'icon' => 'ph-calendar-check', 'title' => 'Event & Activity Management',
            'desc' => 'Implemented an event management system allowing administrators and SK officials to organize and manage activities, programs, and community initiatives.'
        ],
        [
            'week' => '5', 'icon' => 'ph-list-checks', 'title' => 'Event Attendance & Tracking',
            'desc' => 'Built a digital attendance management system that records participant engagement and stores attendance data securely for monitoring and COA reporting.'
        ],
        [
            'week' => '6', 'icon' => 'ph-hands-clapping', 'title' => 'Public User Engagement & Suggestions',
            'desc' => 'Developed public-facing portals where citizens can submit suggestions, concerns, and feedback directly to their respective SK officials.'
        ],
        [
            'week' => '7', 'icon' => 'ph-graduation-cap', 'title' => 'Scholarship & Program Portal',
            'desc' => 'Created a scholarship and youth program module that allows administrators to publish opportunities and enables users to browse available assistance programs.'
        ],
        [
            'week' => '8', 'icon' => 'ph-broadcast', 'title' => 'Announcements & Communications',
            'desc' => 'Activated the announcements board and notification system to efficiently distribute important updates, directives, and public advisories city-wide.'
        ],
        [
            'week' => '9', 'icon' => 'ph-crown', 'title' => 'Featured Highlights & Advanced Admin Tools',
            'desc' => 'Implemented featured highlights for youth achievements and completed advanced administrative tools for user management and platform oversight.'
        ],
              ['week' => '10', 'icon' => 'ph-calendar-plus', 'title' => 'Activity & Engagement', 'desc' => 'Integrating a centralized municipality-wide Event Calendar.'],
        
         ['week' => '11', 'icon' => 'ph-browser', 'title' => 'Core Portal & Identity', 'desc' => 'Establishing the SK website portal. Implementing secure, role-based User Controls and Profile Management for administrators, officials, and the public.'],
        [
            'week' => '12', 'icon' => 'ph-rocket-launch', 'title' => 'Optimization & Final Deployment',
            'desc' => 'Performed extensive stress testing, optimized database performance, strengthened security measures, fixed system bugs, and deployed the completed platform.'
        ]
        
    ];
    ?>

    <div class="relative border-l-2 border-dashed border-slate-300 ml-4 md:ml-6 pl-8 md:pl-12 space-y-12">
        <?php foreach ($timeline_completed as $item): ?>
            <div class="relative group">
                <!-- Timeline Icon with Green-to-Yellow Hover -->
                <div class="absolute -left-[57px] md:-left-[73px] top-0 w-12 h-12 bg-white border-4 border-green-500 rounded-full shadow-[0_0_15px_rgba(34,197,94,0.4)] flex items-center justify-center text-green-600 text-xl group-hover:scale-110 group-hover:border-yellow-400 group-hover:text-yellow-600 transition-all duration-300 z-10">
                    <i class="ph-bold <?= htmlspecialchars($item['icon']) ?>"></i>
                </div>

                <!-- Card -->
                <div class="bg-white p-6 md:p-8 rounded-2xl border border-slate-100 shadow-sm hover:shadow-lg transition-all duration-300">
                    <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 mb-4">
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-green-600 to-yellow-600 font-black text-lg">
                            Week <?= htmlspecialchars($item['week']) ?>
                        </span>
                        <div class="hidden md:block w-1.5 h-1.5 bg-slate-300 rounded-full"></div>
                        <h3 class="text-xl font-bold text-slate-800">
                            <?= htmlspecialchars($item['title']) ?>
                        </h3>
                        <span class="md:ml-auto inline-block bg-green-50 text-green-600 border border-green-100 text-[10px] font-bold px-3 py-1 rounded-md uppercase tracking-wider">
                            Completed
                        </span>
                    </div>
                    <p class="text-slate-600 leading-relaxed">
                        <?= htmlspecialchars($item['desc']) ?>
                    </p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

        <!-- Modern Phase 1 Timeline (5-Week In Progress) -->
        <div class="mb-24 max-w-4xl mx-auto">
            <div class="text-center mb-16">
                <!-- Altered to yellow-orange to show "in progress" instead of blue -->
                <span class="inline-block bg-yellow-100/50 text-yellow-800 text-xs font-extrabold px-4 py-1.5 rounded-full uppercase tracking-widest border border-yellow-300/50 shadow-sm mb-3">
                    Phase 1: In Progress
                </span>
                <h2 class="text-3xl font-bold text-slate-800 tracking-tight">5-Week Active Implementation</h2>
            </div>
            
            <?php
            $timeline_active = [
          
          
                ['week' => '1', 'icon' => 'ph-coins', 'title' => 'Financial Transparency', 'desc' => 'Designing the Digital Liquidation System to streamline SK expense reports, paired with a Budget Transparency Portal for public fund allocation insights.'],
                ['week' => '2', 'icon' => 'ph-file-text', 'title' => 'Legislative Automation', 'desc' => 'Building the Resolution Generator for legislative documents and setting up Youth Assembly Archives to store past meeting minutes.'],
                ['week' => '3', 'icon' => 'ph-magnifying-glass', 'title' => 'Public Accountability', 'desc' => 'Finalizing Project TALA (an interactive live tracker for initiatives) and a Public Audit Trail to ensure absolute SK operational transparency.']
            ];
            ?>

            <div class="relative border-l-2 border-dashed border-slate-300 ml-4 md:ml-6 pl-8 md:pl-12 space-y-12">
                <?php foreach ($timeline_active as $item): ?>
                <div class="relative group">
                    <!-- Timeline Icon -->
                    <div class="absolute -left-[57px] md:-left-[73px] top-0 w-12 h-12 bg-white border-4 border-yellow-500 rounded-full shadow-[0_0_15px_rgba(234,179,8,0.4)] flex items-center justify-center text-yellow-600 text-xl group-hover:scale-110 group-hover:border-orange-400 group-hover:text-orange-500 transition-all duration-300 z-10">
                        <i class="ph-bold <?= htmlspecialchars($item['icon']) ?>"></i>
                    </div>
                    
                    <div class="bg-white p-6 md:p-8 rounded-2xl border border-slate-100 shadow-sm group-hover:shadow-lg transition-all duration-300">
                        <div class="flex flex-col md:flex-row md:items-center gap-2 md:gap-4 mb-3">
                            <span class="text-yellow-600 font-black text-lg">Week <?= $item['week'] ?></span>
                            <div class="hidden md:block w-1.5 h-1.5 bg-slate-300 rounded-full"></div>
                            <h3 class="text-xl font-bold text-slate-800"><?= $item['title'] ?></h3>
                            <span class="md:ml-auto inline-block bg-yellow-50 text-yellow-700 border border-yellow-100 text-[10px] font-bold px-2.5 py-1 rounded-md uppercase tracking-wider">In Progress</span>
                        </div>
                        <p class="text-slate-600 leading-relaxed font-medium"><?= $item['desc'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
   <!-- Investment Breakdown & ROI Section -->
        <div class="mb-24 max-w-5xl mx-auto">
            <div class="text-center mb-12">
                <span class="inline-block bg-gradient-to-r from-green-100 to-yellow-100 text-green-800 text-xs font-extrabold px-4 py-1.5 rounded-full uppercase tracking-widest border border-green-200/50 shadow-sm mb-3">
                    <i class="ph-bold ph-chart-line-up text-yellow-600"></i> Strategic Investment
                </span>
                <h2 class="text-3xl font-bold text-slate-800 tracking-tight">Project Budget & Value Proposition</h2>
                <p class="text-slate-500 mt-3 max-w-2xl mx-auto font-medium">A transparent breakdown of the ₱100,000 development budget and the massive Return on Investment (ROI) it delivers to the local government.</p>
            </div>

            <!-- Sleek Financial Table -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-soft overflow-hidden mb-12">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="py-4 px-6 font-bold text-slate-700">Development Phase</th>
                                <th class="py-4 px-6 font-bold text-slate-700">Strategic Inclusions</th>
                                <th class="py-4 px-6 font-bold text-slate-700 text-right">Allocation</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm font-medium text-slate-600 divide-y divide-slate-50">
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4 px-6 text-slate-800 font-bold">I. Architecture & Security</td>
                                <td class="py-4 px-6">Triple-tier RBAC, Encrypted Identity Management, Anti-Injection Security routing.</td>
                                <td class="py-4 px-6 text-right font-bold text-slate-700">₱ 20,000</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4 px-6 text-slate-800 font-bold">II. Modern UI/UX Design</td>
                                <td class="py-4 px-6">Enterprise-grade aesthetics, fully mobile-responsive dynamic dashboards.</td>
                                <td class="py-4 px-6 text-right font-bold text-slate-700">₱ 15,000</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4 px-6 text-slate-800 font-bold">III. SK Operations</td>
                                <td class="py-4 px-6">Youth Profiling, Event Management, and Proof of Presence digital attendance.</td>
                                <td class="py-4 px-6 text-right font-bold text-slate-700">₱ 25,000</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4 px-6 text-slate-800 font-bold">IV. Financial & Legislative</td>
                                <td class="py-4 px-6">Digital Liquidation System, Resolution Generator, and Youth Assembly Archives.</td>
                                <td class="py-4 px-6 text-right font-bold text-slate-700">₱ 20,000</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4 px-6 text-slate-800 font-bold">V. Public Transparency</td>
                                <td class="py-4 px-6">Project TALA Live Tracker, Suggestions Inbox, and Public Audit Trail.</td>
                                <td class="py-4 px-6 text-right font-bold text-slate-700">₱ 15,000</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="py-4 px-6 text-slate-800 font-bold">VI. Deployment & Turnover</td>
                                <td class="py-4 px-6">Cloud server configuration, stress testing, and LGU/SK operational training.</td>
                                <td class="py-4 px-6 text-right font-bold text-slate-700">₱ 5,000</td>
                            </tr>
<thead>
         
             
                <!-- Added Free Feature Expansion Row -->
                <tr class="bg-yellow-50/30 hover:bg-yellow-50/50 transition-colors">
                    <td class="py-4 px-6 text-yellow-900 font-bold">VIII. Term-End Expansion</td>
                    <td class="py-4 px-6 text-yellow-700">Free minimal feature requests &  least platform upgrades until the end of the current term.</td>
                    <td class="py-4 px-6 text-right font-bold text-yellow-800">FREE (Included)</td>
                </tr>
                        </tbody>
                        <tfoot>
                            <!-- Gradient applied to footer -->
                            <tr class="bg-gradient-to-r from-green-600 to-yellow-500 text-white">
                                <td colspan="2" class="py-5 px-6 font-extrabold text-lg uppercase tracking-wider">Total System Investment</td>
                                <td class="py-5 px-6 text-right font-extrabold text-2xl">₱100,000</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- The Pitch: 3 Key Selling Points -->
            <div class="grid md:grid-cols-3 gap-6 mb-12">
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-soft transition-all">
                    <div class="w-10 h-10 bg-green-100 text-green-600 rounded-xl flex items-center justify-center text-xl mb-4">
                        <i class="ph-bold ph-shield-check"></i>
                    </div>
                    <h4 class="font-bold text-slate-800 mb-2">Eradicates Audit Red Flags</h4>
                    <p class="text-xs text-slate-500 leading-relaxed font-medium">Replaces easily forged paper sheets with real-time selfie uploads. Guarantees that youth funds are justified with digital evidence, protecting officials from COA audit issues.</p>
                </div>
                
                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-soft transition-all">
                    <div class="w-10 h-10 bg-yellow-100 text-yellow-600 rounded-xl flex items-center justify-center text-xl mb-4">
                        <i class="ph-bold ph-lightning"></i>
                    </div>
                    <h4 class="font-bold text-slate-800 mb-2">Slashes Admin Overhead</h4>
                    <p class="text-xs text-slate-500 leading-relaxed font-medium">The Resolution Generator and Liquidation System digitize manual workflows. This ₱100k investment saves hundreds of thousands in man-hours, paper, and operational delays.</p>
                </div>

                <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm hover:shadow-soft transition-all">
                    <div class="w-10 h-10 bg-orange-100 text-orange-600 rounded-xl flex items-center justify-center text-xl mb-4">
                        <i class="ph-bold ph-eye"></i>
                    </div>
                    <h4 class="font-bold text-slate-800 mb-2">Unprecedented Public Trust</h4>
                    <p class="text-xs text-slate-500 leading-relaxed font-medium">Elevates the SK's public image through the Transparency Portal. Constituents can track budgets and clean-up drives in real-time, achieving perfect DILG compliance.</p>
                </div>
            </div>

      


        <!-- Dark Mode Phase 2 Section (Expanded) -->
        <div class="bg-slate-900 rounded-[2.5rem] p-8 md:p-16 relative overflow-hidden shadow-2xl border border-slate-800">
            <!-- Decorative Dark Mode Blobs (Green and Yellow) -->
            <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-yellow-500/10 rounded-full blur-[100px] pointer-events-none -translate-y-1/2 translate-x-1/3"></div>
            <div class="absolute bottom-0 left-0 w-[400px] h-[400px] bg-green-500/10 rounded-full blur-[100px] pointer-events-none translate-y-1/3 -translate-x-1/4"></div>

            <div class="relative z-10 text-center mb-16">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-green-500/20 border border-green-400/30 text-green-300 text-xs font-bold uppercase tracking-widest mb-6 shadow-lg shadow-green-500/10">
                    <i class="ph-fill ph-rocket-launch text-yellow-400"></i> Beyond Current Scope
                </div>
                <h2 class="text-3xl md:text-5xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-green-400 to-yellow-400 tracking-tight mb-4">Phase 2: Future Innovations</h2>
                <p class="text-slate-400 max-w-2xl mx-auto font-medium text-lg">Enterprise-level upgrades subject to future funding, advanced machine learning scale, and mobile API integrations.</p>
            </div>

            <!-- 8-Grid for Future Projects -->
            <div class="relative z-10 grid md:grid-cols-2 gap-6 max-w-5xl mx-auto">
                
                <!-- Existing 4 -->
                <div class="group bg-white/5 border border-white/10 p-6 md:p-8 rounded-3xl hover:bg-white/10 hover:border-green-500/50 transition-all duration-300 backdrop-blur-sm">
                    <div class="w-12 h-12 bg-green-500/20 text-green-400 rounded-xl flex items-center justify-center text-2xl mb-5 border border-green-500/30 group-hover:scale-110 group-hover:shadow-[0_0_20px_rgba(74,222,128,0.4)] transition-all">
                        <i class="ph-fill ph-face-id"></i>
                    </div>
                    <h3 class="font-bold text-white mb-2 text-lg">Biometric AI Recognition</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Upgrading the selfie module to an automated AI facial recognition system for instant, tamper-proof attendance verification.</p>
                </div>

                <div class="group bg-white/5 border border-white/10 p-6 md:p-8 rounded-3xl hover:bg-white/10 hover:border-yellow-500/50 transition-all duration-300 backdrop-blur-sm">
                    <div class="w-12 h-12 bg-yellow-500/20 text-yellow-400 rounded-xl flex items-center justify-center text-2xl mb-5 border border-yellow-500/30 group-hover:scale-110 group-hover:shadow-[0_0_20px_rgba(250,204,21,0.4)] transition-all">
                        <i class="ph-fill ph-device-mobile"></i>
                    </div>
                    <h3 class="font-bold text-white mb-2 text-lg">Native Mobile Application</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Dedicated iOS and Android apps utilizing Flutter/React Native for offline data caching and native hardware push notifications.</p>
                </div>

                <div class="group bg-white/5 border border-white/10 p-6 md:p-8 rounded-3xl hover:bg-white/10 hover:border-green-500/50 transition-all duration-300 backdrop-blur-sm">
                    <div class="w-12 h-12 bg-green-500/20 text-green-400 rounded-xl flex items-center justify-center text-2xl mb-5 border border-green-500/30 group-hover:scale-110 group-hover:shadow-[0_0_20px_rgba(74,222,128,0.4)] transition-all">
                        <i class="ph-fill ph-chat-teardrop-text"></i>
                    </div>
                    <h3 class="font-bold text-white mb-2 text-lg">SMS Gateway (Text Blast)</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Direct text-blasting API to send urgent disaster alerts and assembly invites to constituents via SMS, bypassing internet requirements.</p>
                </div>

                <div class="group bg-white/5 border border-white/10 p-6 md:p-8 rounded-3xl hover:bg-white/10 hover:border-yellow-500/50 transition-all duration-300 backdrop-blur-sm">
                    <div class="w-12 h-12 bg-yellow-500/20 text-yellow-400 rounded-xl flex items-center justify-center text-2xl mb-5 border border-yellow-500/30 group-hover:scale-110 group-hover:shadow-[0_0_20px_rgba(250,204,21,0.4)] transition-all">
                        <i class="ph-fill ph-robot"></i>
                    </div>
                    <h3 class="font-bold text-white mb-2 text-lg">AI Constituent Bot</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Integration of an intelligent 24/7 chatbot into the public portal capable of answering common youth queries regarding scholarships and schedules.</p>
                </div>

                <!-- 4 New Additions -->
                <div class="group bg-white/5 border border-white/10 p-6 md:p-8 rounded-3xl hover:bg-white/10 hover:border-green-500/50 transition-all duration-300 backdrop-blur-sm">
                    <div class="w-12 h-12 bg-green-500/20 text-green-400 rounded-xl flex items-center justify-center text-2xl mb-5 border border-green-500/30 group-hover:scale-110 group-hover:shadow-[0_0_20px_rgba(74,222,128,0.4)] transition-all">
                        <i class="ph-fill ph-chart-polar"></i>
                    </div>
                    <h3 class="font-bold text-white mb-2 text-lg">Predictive Analytics Dashboard</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Machine learning integration to analyze youth engagement data, helping officials optimize budget allocation and forecast program turnouts.</p>
                </div>

                <div class="group bg-white/5 border border-white/10 p-6 md:p-8 rounded-3xl hover:bg-white/10 hover:border-yellow-500/50 transition-all duration-300 backdrop-blur-sm">
                    <div class="w-12 h-12 bg-yellow-500/20 text-yellow-400 rounded-xl flex items-center justify-center text-2xl mb-5 border border-yellow-500/30 group-hover:scale-110 group-hover:shadow-[0_0_20px_rgba(250,204,21,0.4)] transition-all">
                        <i class="ph-fill ph-identification-badge"></i>
                    </div>
                    <h3 class="font-bold text-white mb-2 text-lg">Digital SK ID & Perks System</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">A verifiable virtual ID system granting registered youth access to exclusive local discounts, priority LGU services, and educational partner perks.</p>
                </div>

                <div class="group bg-white/5 border border-white/10 p-6 md:p-8 rounded-3xl hover:bg-white/10 hover:border-green-500/50 transition-all duration-300 backdrop-blur-sm">
                    <div class="w-12 h-12 bg-green-500/20 text-green-400 rounded-xl flex items-center justify-center text-2xl mb-5 border border-green-500/30 group-hover:scale-110 group-hover:shadow-[0_0_20px_rgba(74,222,128,0.4)] transition-all">
                        <i class="ph-fill ph-trophy"></i>
                    </div>
                    <h3 class="font-bold text-white mb-2 text-lg">Gamified Volunteering</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">A civic engagement points system where youths earn trackable rewards for consistently attending barangay clean-up drives and public seminars.</p>
                </div>

                <div class="group bg-white/5 border border-white/10 p-6 md:p-8 rounded-3xl hover:bg-white/10 hover:border-yellow-500/50 transition-all duration-300 backdrop-blur-sm">
                    <div class="w-12 h-12 bg-yellow-500/20 text-yellow-400 rounded-xl flex items-center justify-center text-2xl mb-5 border border-yellow-500/30 group-hover:scale-110 group-hover:shadow-[0_0_20px_rgba(250,204,21,0.4)] transition-all">
                        <i class="ph-fill ph-check-square-offset"></i>
                    </div>
                    <h3 class="font-bold text-white mb-2 text-lg">E-Voting & Secure Polling</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">Encrypted digital polling mechanisms for Local Youth Development Council elections and large-scale policy consensus during Katipunan ng Kabataan assemblies.</p>
                </div>

            </div>
        </div>

    </main>

    <footer class="text-center py-8 text-slate-500 text-sm font-medium">
        &copy; <?= date('Y') ?> TALA Management System. All rights reserved.
    </footer>

</body>
</html>