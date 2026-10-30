<?php
require 'db_connect.php';
try {
    $events = $pdo->query("SELECT id, title, event_date, location, status, image_path FROM events ORDER BY event_date ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $events = [];
}
$events_json = json_encode($events, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Calendar | TALA Tumauini</title>
    <link rel="shortcut icon" href="TALA-LOGO.ico" type="image/x-icon">
    <link rel="stylesheet" href="tala-theme.css">
    <script src="tala-theme.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>tailwind.config = { darkMode: 'class', theme: { extend: { fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] } } } };</script>
    <link rel="stylesheet" href="public-design.css">
    <link rel="stylesheet" href="public-calendar.css">
</head>
<body data-public-page="public_calendar.php" class="tala-public antialiased selection:bg-green-600 selection:text-white flex flex-col min-h-screen">
    <?php include 'navigation.php'; ?>
    <main class="flex-1 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">
        <div class="calendar-heading">
            <p class="calendar-eyebrow">CONNECT. PARTICIPATE. GROW.</p>
            <h1 class="mb-4">Community Calendar</h1>
            <p class="calendar-intro">Make time for your community. Explore youth programs, assemblies, and events across Tumauini.</p>
            <a href="public_events.php" class="calendar-events-link">Explore upcoming events <i class="ph-bold ph-arrow-up-right" aria-hidden="true"></i></a>
        </div>
        <div class="calendar-layout">
            <section class="tala-public-card calendar-panel" aria-label="Monthly community calendar">
                <div class="calendar-toolbar">
                    <div>
                        <p class="calendar-eyebrow">WHAT'S HAPPENING</p>
                        <h2 id="monthYearDisplay" aria-live="polite"></h2>
                        <p id="monthEventCount" class="calendar-muted"></p>
                    </div>
                    <div class="calendar-controls">
                        <button type="button" id="prevMonth" aria-label="Previous month"><i class="ph-bold ph-caret-left" aria-hidden="true"></i></button>
                        <button type="button" id="todayBtn">Today</button>
                        <button type="button" id="nextMonth" aria-label="Next month"><i class="ph-bold ph-caret-right" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="calendar-weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
                <div id="calendarGrid" class="calendar-grid" role="group" aria-label="Choose a date"></div>
                <div class="calendar-legend"><span><i class="calendar-dot"></i> Scheduled event</span><span><i class="calendar-today-dot"></i> Today</span></div>
            </section>
            <aside class="tala-public-card calendar-agenda" aria-label="Events on selected date">
                <div class="calendar-agenda-icon"><i class="ph-duotone ph-calendar-check" aria-hidden="true"></i></div>
                <p class="calendar-eyebrow">YOUR COMMUNITY, YOUR TIME</p>
                <h2 id="selectedDateDisplay"></h2>
                <p id="selectedDayCount" class="calendar-muted" aria-live="polite"></p>
                <div id="dayEvents" class="calendar-day-events" aria-live="polite"></div>
            </aside>
        </div>
    </main>
    <?php include 'public_footer.php'; ?>
    <dialog id="eventModal" class="calendar-dialog" aria-labelledby="modalTitle">
        <div class="calendar-modal-content" id="modalContent">
            <button type="button" id="closeEventModal" class="calendar-modal-close" aria-label="Close event details"><i class="ph-bold ph-x" aria-hidden="true"></i></button>
            <div id="modalImageContainer" hidden><img id="modalImage" alt=""></div>
            <div class="calendar-modal-body">
                <p id="modalStatus" class="calendar-status"></p>
                <h2 id="modalTitle"></h2>
                <p class="calendar-modal-detail"><i class="ph-bold ph-calendar-blank" aria-hidden="true"></i><span id="modalDate"></span></p>
                <p class="calendar-modal-detail"><i class="ph-bold ph-map-pin" aria-hidden="true"></i><span id="modalLocation"></span></p>
            </div>
        </div>
    </dialog>
    <script id="calendarEvents" type="application/json"><?= $events_json ?: '[]' ?></script>
    <script src="public-calendar.js"></script>
</body>
</html>
