<?php
// public_officials.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db_connect.php';

// Fetch approved SK Officials
$stmt = $pdo->query("SELECT * FROM admins WHERE role = 'sub-admin' AND status = 'approved' ORDER BY barangay_assigned ASC");
$officials_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group officials by barangay
$sk_officials = [];
foreach ($officials_data as $off) {
    $sk_officials[$off['barangay_assigned']][] = $off;
}

?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Youth Leaders | TALA Tumauini 2026</title>
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
            box-shadow: 0 10px 25px -3px rgba(21, 128, 61, 0.4);
        }
    </style>
    <script defer src="form-intellisense.php"></script>
    <script>if (window.tailwind) tailwind.config = { ...tailwind.config, darkMode: 'class' };</script>
    <link rel="stylesheet" href="public-design.css">
</head>
<body data-public-page="officials.php" class="tala-public bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white dark:text-zinc-100 dark:bg-zinc-900">

  <?php include 'navigation.php'; ?>

    <!-- CONTENT -->
    <main class="flex-1 w-full pt-16 pb-24">
        
        <!-- Inline Page Header -->
        <div class="mb-16 text-center max-w-3xl mx-auto px-6 lg:px-8">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mb-3 dark:text-white">Youth Leaders</h1>
            <p class="text-slate-600 text-base sm:text-lg dark:text-zinc-300">The dedicated officials representing the youth of Tumauini.</p>
        </div>

        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <!-- SK Chairpersons Directory -->
            <div class="flex items-center justify-center gap-3 mb-12">
                <div class="h-px bg-slate-200 flex-1 max-w-[100px]"></div>
                <h3 class="text-xl sm:text-2xl font-extrabold text-slate-900 text-center dark:text-white">Sangguniang Kabataan Officials</h3>
                <div class="h-px bg-slate-200 flex-1 max-w-[100px]"></div>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-x-6 gap-y-12 mt-8">
                <?php if(!empty($sk_officials)): ?>
                    <?php foreach ($sk_officials as $barangay => $officials): ?>
                        <?php foreach ($officials as $off): ?>
                            <div class="bg-white rounded-3xl shadow-sm border border-slate-200/80 text-center hover:shadow-md hover:-translate-y-1 transition-all duration-300 relative mt-6 dark:bg-zinc-900 tala-public-card">
                                <!-- Floating Avatar -->
                                <div class="absolute -top-10 left-1/2 transform -translate-x-1/2">
                                    <?php if (!empty($off['profile_picture'])): ?>
                                        <img src="<?= htmlspecialchars($off['profile_picture']) ?>" class="w-20 h-20 object-cover rounded-[1.25rem] shadow-sm border-[3px] border-white bg-slate-50 dark:bg-zinc-900">
                                    <?php else: ?>
                                        <div class="w-20 h-20 bg-slate-50 text-slate-400 rounded-[1.25rem] flex items-center justify-center text-3xl shadow-sm border-[3px] border-white dark:text-zinc-500 dark:bg-zinc-900"><i class="ph-fill ph-user"></i></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="pt-14 pb-6 px-4">
                                    <h4 class="font-extrabold text-slate-900 text-[15px] leading-tight mb-1 line-clamp-1 dark:text-white" title="<?= htmlspecialchars($off['full_name']) ?>"><?= htmlspecialchars($off['full_name']) ?></h4>
                                    <p class="text-[10px] font-bold text-brand-600 uppercase tracking-widest mb-3"><?= htmlspecialchars($off['sk_position'] ?? 'SK Official') ?></p>
                                    <span class="inline-flex items-center text-[11px] font-bold text-slate-500 bg-slate-50 px-3 py-1.5 rounded-full border border-slate-200 line-clamp-1 w-max max-w-full justify-center dark:text-zinc-400 dark:bg-zinc-900 dark:border-zinc-700">
                                        <i class="ph-fill ph-map-pin mr-1.5 text-slate-400 dark:text-zinc-500"></i> <?= htmlspecialchars($barangay) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full text-center py-16 bg-white rounded-[2rem] border border-slate-200 border-dashed shadow-sm dark:bg-zinc-900 dark:border-zinc-700 tala-public-card">
                        <div class="w-16 h-16 bg-slate-50 text-slate-300 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl dark:bg-zinc-900">
                            <i class="ph-fill ph-users"></i>
                        </div>
                        <h3 class="text-lg font-bold text-slate-800 mb-1 dark:text-zinc-100">No Youth Leaders Found</h3>
                        <p class="text-sm text-slate-500 dark:text-zinc-400">The directory is currently being updated.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'public_footer.php'; ?>

    <!-- Script for Mobile Menu -->
    <script>
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');

        if(mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                mobileMenu.classList.toggle('hidden');
            });
        }
    </script>
</body>
</html>