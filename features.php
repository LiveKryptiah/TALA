<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Features | TALA Tumauini 2026</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">

    <!-- Fonts & Tailwind CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        .btn-gradient {
            background: linear-gradient(135deg, #15803d 0%, #047857 50%, #ca8a04 100%);
            color: #ffffff;
        }
        .btn-gradient:hover {
            opacity: 0.95;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white">

  <?php include 'navigation.php'; ?>
    <!-- CONTENT -->
    <main class="flex-1 w-full pt-16 pb-24 max-w-7xl mx-auto px-6 lg:px-8">
        
        <!-- Inline Page Header -->
        <div class="mb-16 text-center max-w-3xl mx-auto">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mb-3">Platform Features</h1>
            <p class="text-slate-600 text-base sm:text-lg font-medium">A comprehensive suite designed for data-driven Youth Governance.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-10">
            
            <div class="bg-white p-10 md:p-12 rounded-[2rem] hover:-translate-y-1 transition-all duration-300 group">
                <div class="w-16 h-16 bg-brand-50 text-brand-700 rounded-[1.25rem] flex items-center justify-center text-4xl mb-6 border border-brand-100 group-hover:scale-110 transition-transform duration-300">
                    <i class="ph-fill ph-users-three"></i>
                </div>
                <h3 class="text-2xl font-extrabold text-slate-900 mb-4 leading-snug">Youth Directory</h3>
                <p class="text-slate-600 leading-relaxed text-base sm:text-lg">
                    A centralized, highly secure database storing the demographics, contact details, and socioeconomic status of all youth constituents across the 46 barangays of Tumauini.
                </p>
            </div>

            <div class="bg-white p-10 md:p-12 rounded-[2rem] hover:-translate-y-1 transition-all duration-300 group">
                <div class="w-16 h-16 bg-brand-50 text-brand-700 rounded-[1.25rem] flex items-center justify-center text-4xl mb-6 border border-brand-100 group-hover:scale-110 transition-transform duration-300">
                    <i class="ph-fill ph-chart-pie-slice"></i>
                </div>
                <h3 class="text-2xl font-extrabold text-slate-900 mb-4 leading-snug">Live Analytics</h3>
                <p class="text-slate-600 leading-relaxed text-base sm:text-lg">
                    Real-time demographic dashboards showing education levels, employment statuses, and voter registration metrics to help SKs tailor programs that fit actual community needs.
                </p>
            </div>

            <div class="bg-white p-10 md:p-12 rounded-[2rem] hover:-translate-y-1 transition-all duration-300 group">
                <div class="w-16 h-16 bg-brand-50 text-brand-700 rounded-[1.25rem] flex items-center justify-center text-4xl mb-6 border border-brand-100 group-hover:scale-110 transition-transform duration-300">
                    <i class="ph-fill ph-calendar-check"></i>
                </div>
                <h3 class="text-2xl font-extrabold text-slate-900 mb-4 leading-snug">Event Management</h3>
                <p class="text-slate-600 leading-relaxed text-base sm:text-lg">
                    Publish public events, enforce participant limits, and securely verify registrations to ensure that slots are prioritized for registered, verified youth constituents.
                </p>
            </div>

            <div class="bg-white p-10 md:p-12 rounded-[2rem] hover:-translate-y-1 transition-all duration-300 group">
                <div class="w-16 h-16 bg-brand-50 text-brand-700 rounded-[1.25rem] flex items-center justify-center text-4xl mb-6 border border-brand-100 group-hover:scale-110 transition-transform duration-300">
                    <i class="ph-fill ph-clipboard-text"></i>
                </div>
                <h3 class="text-2xl font-extrabold text-slate-900 mb-4 leading-snug">Automated Activity Ledgers</h3>
                <p class="text-slate-600 leading-relaxed text-base sm:text-lg">
                    Automated tracking of every program a youth attends. Upon completion of an event, participants are instantly injected into a permanent ledger attached to their youth ID.
                </p>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-10 px-6 lg:px-8 mt-auto">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <img src="TALA-LOGO.ico" class="w-8 h-8 object-contain" alt="TALA Logo" onerror="this.src='https://via.placeholder.com/32?text=TALA'">
                <p class="text-xs font-semibold text-slate-500">
                    &copy; <?= date('Y') ?> Tumauini Municipal Youth Development Office. All rights reserved.
                </p>
            </div>
            <div class="flex items-center gap-6 text-xs font-semibold text-slate-500">
                <a href="#" class="hover:text-brand-800 transition">Privacy Policy</a>
                <a href="#" class="hover:text-brand-800 transition">Terms of Service</a>
                <a href="#" class="hover:text-brand-800 transition">Contact Support</a>
            </div>
        </div>
    </footer>

    <!-- Script for Mobile Menu -->
    <script>
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');

        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    </script>
</body>
</html>