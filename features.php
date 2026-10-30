<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
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
            darkMode: 'class',
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

    <link rel="stylesheet" href="public-design.css">
    <link rel="stylesheet" href="public-features.css">
</head>
<body data-public-page="features.php" class="tala-public bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white dark:text-zinc-100 dark:bg-zinc-900">

  <?php include 'navigation.php'; ?>
    <!-- CONTENT -->
    <main class="flex-1 w-full pt-16 pb-24 max-w-7xl mx-auto px-6 lg:px-8">
        
        <!-- Inline Page Header -->
        <div class="features-heading">
            <p class="features-eyebrow">BUILT FOR TUMAUINI'S YOUTH</p>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mb-3 dark:text-white">Platform Features</h1>
            <p class="features-intro">Better information. Stronger participation. Connected tools that help youth leaders understand their communities and turn plans into action.</p>
        </div>

        <div class="features-grid grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <div class="bg-white p-10 md:p-12 rounded-[2rem] hover:-translate-y-1 transition-all duration-300 group dark:bg-zinc-900 tala-public-card">
                <div class="w-16 h-16 bg-brand-50 text-brand-700 rounded-[1.25rem] flex items-center justify-center text-4xl mb-6 border border-brand-100 group-hover:scale-110 transition-transform duration-300">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M6 20v-2a6 6 0 0 1 12 0v2M5 5a3 3 0 0 0 0 6M19 5a3 3 0 0 1 0 6M2 19v-2a5 5 0 0 1 3-4M22 19v-2a5 5 0 0 0-3-4"/></svg>
                </div>
                <h3 class="text-2xl font-extrabold text-slate-900 mb-4 leading-snug dark:text-white">Youth Directory</h3>
                <p class="text-slate-600 leading-relaxed text-base sm:text-lg dark:text-zinc-300">
                    A centralized, highly secure database storing the demographics, contact details, and socioeconomic status of all youth constituents across the 46 barangays of Tumauini.
                </p>
            </div>

            <div class="bg-white p-10 md:p-12 rounded-[2rem] hover:-translate-y-1 transition-all duration-300 group dark:bg-zinc-900 tala-public-card">
                <div class="w-16 h-16 bg-brand-50 text-brand-700 rounded-[1.25rem] flex items-center justify-center text-4xl mb-6 border border-brand-100 group-hover:scale-110 transition-transform duration-300">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 3a9 9 0 1 0 11 11H10ZM14 2v8h8a9 9 0 0 0-8-8Z"/></svg>
                </div>
                <h3 class="text-2xl font-extrabold text-slate-900 mb-4 leading-snug dark:text-white">Live Analytics</h3>
                <p class="text-slate-600 leading-relaxed text-base sm:text-lg dark:text-zinc-300">
                    Real-time demographic dashboards showing education levels, employment statuses, and voter registration metrics to help SKs tailor programs that fit actual community needs.
                </p>
            </div>

            <div class="bg-white p-10 md:p-12 rounded-[2rem] hover:-translate-y-1 transition-all duration-300 group dark:bg-zinc-900 tala-public-card">
                <div class="w-16 h-16 bg-brand-50 text-brand-700 rounded-[1.25rem] flex items-center justify-center text-4xl mb-6 border border-brand-100 group-hover:scale-110 transition-transform duration-300">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4M17 3v4M3 10h18M8 16l3 3 5-5"/></svg>
                </div>
                <h3 class="text-2xl font-extrabold text-slate-900 mb-4 leading-snug dark:text-white">Event Management</h3>
                <p class="text-slate-600 leading-relaxed text-base sm:text-lg dark:text-zinc-300">
                    Publish public events, enforce participant limits, and securely verify registrations to ensure that slots are prioritized for registered, verified youth constituents.
                </p>
            </div>

            <div class="bg-white p-10 md:p-12 rounded-[2rem] hover:-translate-y-1 transition-all duration-300 group dark:bg-zinc-900 tala-public-card">
                <div class="w-16 h-16 bg-brand-50 text-brand-700 rounded-[1.25rem] flex items-center justify-center text-4xl mb-6 border border-brand-100 group-hover:scale-110 transition-transform duration-300">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5H6a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M8 12h8M8 17h6"/><rect x="8" y="2" width="8" height="6" rx="2"/></svg>
                </div>
                <h3 class="text-2xl font-extrabold text-slate-900 mb-4 leading-snug dark:text-white">Automated Activity Ledgers</h3>
                <p class="text-slate-600 leading-relaxed text-base sm:text-lg dark:text-zinc-300">
                    Automated tracking of every program a youth attends. Upon completion of an event, participants are instantly injected into a permanent ledger attached to their youth ID.
                </p>
            </div>

        </div>
        <section class="features-next tala-public-card" aria-labelledby="features-next-title">
            <div>
                <p class="features-eyebrow">TAKE PART IN YOUR COMMUNITY</p>
                <h2 id="features-next-title">See what's happening next.</h2>
                <p>Find youth activities, programs, and assemblies in Tumauini.</p>
            </div>
            <a href="public_calendar.php" class="features-action">Explore the calendar <i class="ph-bold ph-arrow-up-right" aria-hidden="true"></i></a>
        </section>
    </main>

    <!-- Footer -->
    <?php include 'public_footer.php'; ?>

</body>
</html>
