<?php
// public_assets.php - Public Youth Asset Borrowing System
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'db_connect.php';

// Auto-create Tables just in case
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk_assets` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `barangay` VARCHAR(255) NOT NULL, `asset_name` VARCHAR(255) NOT NULL, `category` VARCHAR(100) NOT NULL, `quantity_total` INT NOT NULL DEFAULT 1, `quantity_available` INT NOT NULL DEFAULT 1, `condition_status` ENUM('Good', 'Needs Repair', 'Broken') DEFAULT 'Good', `date_acquired` DATE, `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `sk_asset_requests` ( `id` INT AUTO_INCREMENT PRIMARY KEY, `asset_id` INT NOT NULL, `barangay` VARCHAR(255) NOT NULL, `youth_id` INT NOT NULL, `youth_name` VARCHAR(255) NOT NULL, `purpose` TEXT, `borrow_date` DATE NOT NULL, `return_date` DATE NOT NULL, `status` ENUM('Pending', 'Approved', 'Returned', 'Rejected') DEFAULT 'Pending', `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (`asset_id`) REFERENCES `sk_assets`(`id`) ON DELETE CASCADE )");
} catch (PDOException $e) {}

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $asset_id = $_POST['asset_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $purpose = trim($_POST['purpose']);
    $borrow_date = $_POST['borrow_date'];
    $return_date = $_POST['return_date'];

    // Verify Youth Identity
    $stmt = $pdo->prepare("SELECT youth_id, barangay FROM youth_profiles WHERE full_name = ? AND email = ? AND contact_number = ?");
    $stmt->execute([$full_name, $email, $contact]);
    $youth = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$youth) {
        $message = "Verification failed! Details do not match. <a href='public_registration.php' class='underline'>Register here</a>.";
        $messageType = "red";
    } else {
        // Submit Request
        $stmtReq = $pdo->prepare("INSERT INTO sk_asset_requests (asset_id, barangay, youth_id, youth_name, purpose, borrow_date, return_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if($stmtReq->execute([$asset_id, $youth['barangay'], $youth['youth_id'], $full_name, $purpose, $borrow_date, $return_date])) {
            $message = "Request submitted successfully! Please wait for your SK's approval.";
            $messageType = "green";
        }
    }
}

// Fetch Assets that have available quantity
$assets = $pdo->query("SELECT * FROM sk_assets WHERE quantity_available > 0 AND condition_status = 'Good' ORDER BY barangay, asset_name")->fetchAll(PDO::FETCH_ASSOC);

// Group assets by barangay for the dropdown
$groupedAssets = [];
foreach ($assets as $a) {
    $groupedAssets[$a['barangay']][] = $a;
}

$alertClasses = [
    'red' => 'bg-red-50 text-red-800 border-red-200',
    'green' => 'bg-green-50 text-green-800 border-green-200'
];
$activeAlertClass = isset($alertClasses[$messageType]) ? $alertClasses[$messageType] : '';
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SK Equipment Request | TALA Tumauini</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, colors: { brand: { 50: '#f0fdf4', 100: '#dcfce7', 600: '#16a34a', 700: '#15803d', 800: '#166534', 900: '#14532d' } } } } }
    </script>
    <style>
        .btn-gradient { background: #15803d; color: #ffffff; transition: all 0.2s ease; }
        .btn-gradient:hover { background: #166534; transform: translateY(-1px); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-brand-900 selection:text-white">

    <?php include 'navigation.php'; ?>

    <main class="flex-1 w-full pt-16 pb-24 max-w-3xl mx-auto px-6 lg:px-8">
        
        <div class="mb-10 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-brand-50 text-brand-600 mb-4 border border-brand-100">
                <i class="ph-fill ph-package text-3xl"></i>
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mb-3">Borrow SK Equipment</h1>
            <p class="text-slate-600 text-base sm:text-lg font-medium">Request to borrow tents, chairs, sound systems, or sports equipment for your local events.</p>
        </div>

        <?php if ($message): ?>
            <div class="<?= $activeAlertClass ?> p-5 mb-8 rounded-2xl flex flex-col sm:flex-row items-center justify-center gap-3 border font-semibold text-sm">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="bg-white p-8 md:p-10 rounded-[2rem]">
            <?php if(empty($groupedAssets)): ?>
                <div class="text-center py-10">
                    <i class="ph-fill ph-warning-circle text-4xl text-amber-400 mb-3 inline-block"></i>
                    <h3 class="text-lg font-bold text-slate-800">No equipment currently available</h3>
                    <p class="text-sm text-slate-500 mt-1">All registered SK assets are either fully borrowed or undergoing maintenance.</p>
                </div>
            <?php else: ?>
                <form method="POST" class="space-y-6">
                    
                    <div class="pb-6 border-b border-slate-100">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">1. Select Equipment <span class="text-red-500">*</span></label>
                        <select name="asset_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3.5 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-brand-600 outline-none cursor-pointer appearance-none">
                            <option value="" disabled selected>Choose an item from your barangay...</option>
                            <?php foreach($groupedAssets as $brgyName => $items): ?>
                                <optgroup label="📍 <?= htmlspecialchars($brgyName) ?>">
                                    <?php foreach($items as $item): ?>
                                        <option value="<?= $item['id'] ?>">
                                            <?= htmlspecialchars($item['asset_name']) ?> (<?= $item['quantity_available'] ?> available)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b border-slate-100">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">2. Purpose of Borrowing <span class="text-red-500">*</span></label>
                            <input type="text" name="purpose" required placeholder="e.g. Inter-purok basketball league, family gathering" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3.5 text-sm font-medium focus:ring-2 focus:ring-brand-600 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Borrow Date <span class="text-red-500">*</span></label>
                            <input type="date" name="borrow_date" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3.5 text-sm font-medium focus:ring-2 focus:ring-brand-600 outline-none text-slate-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Return Date <span class="text-red-500">*</span></label>
                            <input type="date" name="return_date" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3.5 text-sm font-medium focus:ring-2 focus:ring-brand-600 outline-none text-slate-600">
                        </div>
                    </div>

                    <div class="pt-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">3. Verify Identity <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <input type="text" name="full_name" placeholder="Registered Name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-brand-600 outline-none">
                            <input type="email" name="email" placeholder="Registered Email" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-brand-600 outline-none">
                            <input type="tel" name="contact" placeholder="Contact Number" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-brand-600 outline-none">
                        </div>
                    </div>

                    <button type="submit" class="w-full btn-gradient font-bold py-4 rounded-xl flex items-center justify-center gap-2 mt-8 text-base">
                        <i class="ph-bold ph-paper-plane-tilt text-xl"></i> Submit Request to SK
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-white border-t border-slate-200 py-10 px-6 lg:px-8 mt-auto">
        <div class="max-w-7xl mx-auto text-center text-xs font-semibold text-slate-500">
            &copy; <?= date('Y') ?> Tumauini Municipal Youth Development Office.
        </div>
    </footer>
</body>
</html>