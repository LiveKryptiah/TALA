<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TALA Project Proposal | LGU Tumauini</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        /* Sleek SaaS-style Accordion Animation */
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
        details[open] summary ~ * { animation: fadeInDown 0.3s ease-out; }
        
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Image Crossfade Transition */
        .fade-transition { transition: opacity 0.6s ease-in-out; }

        /* Custom Scrollbar for the right side */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f8fafc; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #10b981; }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen flex flex-col">

    <header class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-center relative">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center text-white font-black text-sm shadow-sm">T</div>
                <span class="font-extrabold text-slate-900 tracking-tight text-xl">TALA Platform</span>
            </div>
            <div class="absolute right-6 hidden md:block">
                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500 bg-slate-100 px-3 py-1.5 rounded-full border border-slate-200">
                    Official Proposal
                </span>
            </div>
        </div>
    </header>

    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12 flex flex-col lg:flex-row gap-10 lg:gap-16">

        <div class="w-full lg:w-5/12 relative">
            <div class="lg:sticky lg:top-24 rounded-2xl overflow-hidden shadow-xl shadow-slate-200/50 border border-slate-200 aspect-video lg:aspect-[4/5] bg-slate-200">
                
                <img id="hero-carousel" src="https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&q=80&w=800" alt="TALA System Visual" class="w-full h-full object-cover fade-transition opacity-100">
                
                <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-slate-900/10 to-transparent flex flex-col justify-end p-6 md:p-8">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/20 backdrop-blur-md border border-emerald-400/30 w-max mb-2">
                        <i class="ph-fill ph-check-circle text-emerald-400 text-sm"></i>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-50">LGU Infrastructure</span>
                    </div>
                    <h2 class="text-white font-bold text-xl md:text-2xl leading-tight text-shadow-sm">Digital Governance<br>for Tumauini Youth</h2>
                </div>
            </div>
        </div>

        <div class="w-full lg:w-7/12 flex flex-col space-y-12 pb-16">
            
            <div>
                <h1 class="text-4xl md:text-5xl font-extrabold text-slate-900 tracking-tight mb-6 leading-[1.15]">
                    Modernizing the Municipal Youth Office.
                </h1>
                <p class="text-lg text-slate-600 leading-relaxed">
                   The TALA Platform is a high-performance Digital Governance Infrastructure designed to modernize the administrative workflow of Tumauini’s youth services. By replacing manual documentation with an automated database engine, the LGU will achieve a 60% reduction in reporting overhead while ensuring full compliance with the Data Privacy Act of 2012.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white border border-slate-200 p-5 rounded-xl shadow-sm flex items-start gap-4">
                    <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-lg"><i class="ph-fill ph-chart-polar text-2xl"></i></div>
                    <div>
                        <div class="text-xl font-bold text-slate-900">Real-Time</div>
                        <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide">OSY Analytics</div>
                    </div>
                </div>
                <div class="bg-white border border-slate-200 p-5 rounded-xl shadow-sm flex items-start gap-4">
                    <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-lg"><i class="ph-fill ph-shield-check text-2xl"></i></div>
                    <div>
                        <div class="text-xl font-bold text-slate-900">RA 10173</div>
                        <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide">Data Compliant</div>
                    </div>
                </div>
                <div class="bg-white border border-slate-200 p-5 rounded-xl shadow-sm flex items-start gap-4">
                    <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-lg"><i class="ph-fill ph-files text-2xl"></i></div>
                    <div>
                        <div class="text-xl font-bold text-slate-900">Zero</div>
                        <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide">Paper Records</div>
                    </div>
                </div>
                <div class="bg-white border border-slate-200 p-5 rounded-xl shadow-sm flex items-start gap-4">
                    <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-lg"><i class="ph-fill ph-map-pin text-2xl"></i></div>
                    <div>
                        <div class="text-xl font-bold text-slate-900">46</div>
                        <div class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide">Barangays Linked</div>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm">
                
                <details class="group border-b border-slate-200 last:border-0" open>
                    <summary class="flex items-center justify-between p-6 cursor-pointer select-none bg-slate-50/50 hover:bg-slate-50 rounded-t-2xl transition-colors">
                        <h3 class="text-base md:text-lg font-semibold text-slate-900 group-hover:text-emerald-600">1. The Operational Solution</h3>
                        <div class="relative w-5 h-5 text-slate-400 group-hover:text-emerald-600">
                            <i class="ph ph-plus absolute inset-0 text-xl transition-opacity group-open:opacity-0"></i>
                            <i class="ph ph-minus absolute inset-0 text-xl opacity-0 transition-opacity group-open:opacity-100"></i>
                        </div>
                    </summary>
                    <div class="px-6 pb-6 text-slate-600 text-sm leading-relaxed pt-2 border-t border-slate-100">
                        <p class="mb-4">Currently, youth data is managed through fragmented physical records. This creates severe delays in identifying beneficiaries for scholarships and complicates regulatory compliance.</p>
                        <p><strong>The TALA Solution:</strong> By moving to a centralized digital database, administrators can filter constituents by education status, voter registration, or barangay with a single click—completely automating DILG reporting processes.</p>
                    </div>
                </details>

                <details class="group border-b border-slate-200 last:border-0">
                    <summary class="flex items-center justify-between p-6 cursor-pointer select-none hover:bg-slate-50 transition-colors">
                        <div class="flex items-center gap-3">
                        
                            <h3 class="text-base md:text-lg font-semibold text-slate-900 group-hover:text-emerald-600">2. OSY & Demographics Tracking Dashboard</h3>
                        </div>
                        <div class="relative w-5 h-5 text-slate-400 group-hover:text-emerald-600">
                            <i class="ph ph-plus absolute inset-0 text-xl transition-opacity group-open:opacity-0"></i>
                            <i class="ph ph-minus absolute inset-0 text-xl opacity-0 transition-opacity group-open:opacity-100"></i>
                        </div>
                    </summary>
                    <div class="px-6 pb-6 text-slate-600 text-sm leading-relaxed pt-2 border-t border-slate-100">
                        <p class="mb-4">Securing national funding from TESDA, DepEd ALS, and DILG grants requires precise demographic data. TALA automates this entirely.</p>
                        <ul class="space-y-3">
                            <li class="flex gap-3"><i class="ph-fill ph-chart-pie-slice text-emerald-500 text-lg mt-0.5"></i> <span><strong>Instant Cohort Filtering:</strong> Automatically sorts registered youth into categories: In-School Youth (ISY), Out-of-School Youth (OSY), Working Youth, and Youth with Special Needs.</span></li>
                            <li class="flex gap-3"><i class="ph-fill ph-target text-emerald-500 text-lg mt-0.5"></i> <span><strong>Targeted Interventions:</strong> Allows the Mayor's office to instantly pull a list of all OSY in a specific barangay to invite them directly to alternative learning or livelihood training programs.</span></li>
                        </ul>
                    </div>
                </details>

                <details class="group border-b border-slate-200 last:border-0">
                    <summary class="flex items-center justify-between p-6 cursor-pointer select-none hover:bg-slate-50 transition-colors">
                        <h3 class="text-base md:text-lg font-semibold text-slate-900 group-hover:text-emerald-600">3. Dashboard & Administrative Control</h3>
                        <div class="relative w-5 h-5 text-slate-400 group-hover:text-emerald-600">
                            <i class="ph ph-plus absolute inset-0 text-xl transition-opacity group-open:opacity-0"></i>
                            <i class="ph ph-minus absolute inset-0 text-xl opacity-0 transition-opacity group-open:opacity-100"></i>
                        </div>
                    </summary>
                    <div class="px-6 pb-6 text-slate-600 text-sm leading-relaxed pt-2 border-t border-slate-100">
                        <ul class="space-y-3">
                            <li class="flex gap-3"><i class="ph-fill ph-check-circle text-emerald-500 text-lg mt-0.5"></i> <span><strong>No-Code Content Updates:</strong> Staff can update announcements and emergency pop-ups without hiring IT personnel.</span></li>
                            <li class="flex gap-3"><i class="ph-fill ph-check-circle text-emerald-500 text-lg mt-0.5"></i> <span><strong>Role-Based Access:</strong> MYDO executives receive global access, while SK officials are sandboxed to their respective barangay.</span></li>
                        </ul>
                    </div>
                </details>

                <details class="group border-b border-slate-200 last:border-0">
                    <summary class="flex items-center justify-between p-6 cursor-pointer select-none hover:bg-slate-50 rounded-b-2xl transition-colors">
                        <h3 class="text-base md:text-lg font-semibold text-slate-900 group-hover:text-emerald-600">4. Cybersecurity Infrastructure</h3>
                        <div class="relative w-5 h-5 text-slate-400 group-hover:text-emerald-600">
                            <i class="ph ph-plus absolute inset-0 text-xl transition-opacity group-open:opacity-0"></i>
                            <i class="ph ph-minus absolute inset-0 text-xl opacity-0 transition-opacity group-open:opacity-100"></i>
                        </div>
                    </summary>
                    <div class="px-6 pb-6 text-slate-600 text-sm leading-relaxed pt-2 border-t border-slate-100">
                        <p>Handling citizen data requires strict adherence to the <strong>Data Privacy Act of 2012 (RA 10173)</strong>. TALA is engineered with government-grade safeguards, including <strong>PDO Prepared Statements</strong> to prevent database hacking (SQL Injections), cryptographic password hashing, and severe input sanitization.</p>
                    </div>
                </details>
            </div>

            <div>
                <h2 class="text-2xl font-bold text-slate-900 mb-5">Financial Proposal</h2>
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="p-6 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h3 class="font-bold text-slate-900">Direct Development Contract</h3>
                            <p class="text-sm text-slate-500">Bypassing commercial agency fees.</p>
                        </div>
                        <div class="sm:text-right">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Investment</span>
                            <span class="text-3xl font-black text-emerald-600 tracking-tight">₱100,000</span>
                        </div>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm min-w-[500px]">
                            <thead>
                                <tr class="bg-white text-slate-400 font-semibold text-[11px] uppercase tracking-wider border-b border-slate-100">
                                    <th class="py-4 px-6 font-medium">Deliverable / Scope of Work</th>
                                    <th class="py-4 px-6 text-right font-medium">Allocated Cost</th>
                                </tr>
                            </thead>
                            <tbody class="text-slate-700 divide-y divide-slate-100">
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="py-4 px-6">
                                        <strong class="text-slate-900 block font-semibold">1. Database Architecture & OSY Analytics</strong>
                                        <span class="text-slate-500 text-xs">Relational MySQL schemas, youth profiling system, and demographic filtering logic.</span>
                                    </td>
                                    <td class="py-4 px-6 text-right font-medium">₱50,000</td>
                                </tr>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="py-4 px-6">
                                        <strong class="text-slate-900 block font-semibold">2. UI/UX Interface Development</strong>
                                        <span class="text-slate-500 text-xs">Mobile-responsive public gateway and administrative control dashboard.</span>
                                    </td>
                                    <td class="py-4 px-6 text-right font-medium">₱25,000</td>
                                </tr>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="py-4 px-6">
                                        <strong class="text-slate-900 block font-semibold">3. Cybersecurity Integration (RA 10173)</strong>
                                        <span class="text-slate-500 text-xs">Data privacy protocols, anti-SQL injection limits, and administrative encryption.</span>
                                    </td>
                                    <td class="py-4 px-6 text-right font-medium">₱15,000</td>
                                </tr>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="py-4 px-6">
                                        <strong class="text-slate-900 block font-semibold">4. LGU Deployment & Staff Turn-over</strong>
                                        <span class="text-slate-500 text-xs">Server configuration, live testing, and system training for MYDO staff.</span>
                                    </td>
                                    <td class="py-4 px-6 text-right font-medium">₱10,000</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="mt-4 flex gap-3 p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-100 text-sm">
                    <i class="ph-bold ph-trend-up text-xl flex-shrink-0 mt-0.5 text-emerald-600"></i>
                    <p><strong>Return on Investment:</strong> This expenditure replaces recurring municipal expenses on physical archiving and thousands of hours in administrative overtime, making the system self-funding within the first year of deployment.</p>
                </div>
            </div>

            <div class="pt-6 border-t border-slate-200 flex flex-col sm:flex-row gap-4 items-center justify-between">
                <a href="index.php" class="text-sm font-semibold text-slate-500 hover:text-slate-900 transition-colors flex items-center gap-2">
                    <i class="ph-bold ph-arrow-left"></i> Back to Portal Prototype
                </a>
                <button onclick="window.print()" class="bg-slate-900 hover:bg-emerald-600 text-white px-8 py-3 rounded-lg font-semibold text-sm transition-colors flex items-center gap-2 shadow-md hover:shadow-lg w-full sm:w-auto justify-center">
                    <i class="ph-bold ph-printer text-lg"></i> Print Official Proposal
                </button>
            </div>

        </div> </main>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const images = [
                'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?auto=format&fit=crop&q=80&w=800',
                'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&q=80&w=800',
                'https://images.unsplash.com/photo-1573164713988-8665fc963095?auto=format&fit=crop&q=80&w=800'
            ];
            
            let currentIndex = 0;
            const carouselImage = document.getElementById('hero-carousel');
            
            setInterval(() => {
                carouselImage.style.opacity = '0'; 
                
                setTimeout(() => {
                    currentIndex = (currentIndex + 1) % images.length;
                    carouselImage.src = images[currentIndex];
                    carouselImage.style.opacity = '1'; 
                }, 600); 
                
            }, 4500); 
        });
    </script>
</body>
</html>