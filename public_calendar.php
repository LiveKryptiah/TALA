<?php
// public_calendar.php - Public Facing Event Calendar
require 'db_connect.php';

try {
    // Fetch only active/upcoming/completed events (exclude drafts if applicable)
    $query = "SELECT id, title, event_date, location, status, image_path FROM events ORDER BY event_date ASC";
    $stmt = $pdo->query($query);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $events_json = json_encode($events);
} catch (Exception $e) {
    $events_json = '[]';
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Calendar | TALA OS</title>

    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { transition: background-color 0.3s ease, color 0.3s ease; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #3f3f46; }
        
        .glass-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(24px); border: 1px solid rgba(255, 255, 255, 0.8); transition: all 0.3s ease; }
        .dark .glass-card { background: rgba(24, 24, 27, 0.85); border: 1px solid rgba(63, 63, 70, 0.5); }
        
        .cal-cell { min-height: 120px; transition: all 0.2s ease; }
        .cal-cell:hover { background-color: rgba(226, 232, 240, 0.5); }
        .dark .cal-cell:hover { background-color: rgba(63, 63, 70, 0.4); }
    </style>
</head>
<body class="bg-slate-50 dark:bg-zinc-950 text-slate-800 dark:text-zinc-100 antialiased selection:bg-[#1da85b] selection:text-white transition-colors duration-300 flex flex-col min-h-screen">

    <!-- INJECT PUBLIC NAVIGATION -->
    <?php include 'navigation.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 py-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full z-10">
        
        <div class="text-center mb-12">
            <h1 class="text-4xl md:text-5xl font-black text-slate-900 dark:text-white tracking-tight mb-4 transition-colors">Community Calendar</h1>
            <p class="text-lg text-slate-500 dark:text-zinc-400 max-w-2xl mx-auto transition-colors">Stay up to date with the latest youth programs, assemblies, and events happening in our municipality.</p>
        </div>

        <div class="glass-card rounded-[2rem] p-6 md:p-8 w-full">
            
            <!-- Calendar Controls -->
            <div class="flex flex-col sm:flex-row items-center justify-between mb-8 gap-4">
                <h2 id="monthYearDisplay" class="text-2xl font-black text-slate-900 dark:text-white transition-colors">Month Year</h2>
                <div class="flex gap-2">
                    <button id="prevMonth" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors"><i class="ph-bold ph-caret-left"></i></button>
                    <button id="todayBtn" class="px-4 h-10 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 font-bold text-sm hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors">Today</button>
                    <button id="nextMonth" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300 hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors"><i class="ph-bold ph-caret-right"></i></button>
                </div>
            </div>

            <!-- Days of Week Header -->
            <div class="grid grid-cols-7 gap-px mb-2 text-center">
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Sun</div>
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Mon</div>
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Tue</div>
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Wed</div>
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Thu</div>
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Fri</div>
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-zinc-500 py-2">Sat</div>
            </div>

            <!-- The Grid -->
            <div id="calendarGrid" class="grid grid-cols-7 gap-px bg-slate-200 dark:bg-zinc-800 border border-slate-200 dark:border-zinc-800 rounded-2xl overflow-hidden transition-colors">
                <!-- Populated by JavaScript -->
            </div>
            
        </div>
    </main>

    <!-- EVENT DETAILS MODAL (Public) -->
    <div id="eventModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm transition-colors" onclick="closeEventModal()"></div>
        <div class="relative bg-white dark:bg-zinc-900 rounded-[2rem] w-full max-w-lg overflow-hidden transform scale-95 opacity-0 transition-all duration-300 border border-slate-100 dark:border-zinc-800" id="modalContent">
            
            <div id="modalImageContainer" class="w-full h-48 bg-slate-100 dark:bg-zinc-800 hidden">
                <img id="modalImage" src="" class="w-full h-full object-cover">
            </div>
            
            <div class="p-8">
                <div class="flex justify-between items-start mb-4">
                    <span id="modalDate" class="inline-block px-3 py-1 bg-brand-50 dark:bg-brand-500/10 text-brand-700 dark:text-brand-400 text-[10px] font-extrabold uppercase tracking-widest rounded-lg border border-brand-100 dark:border-brand-500/20">Date</span>
                    <button onclick="closeEventModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 hover:bg-slate-200 dark:hover:bg-zinc-700 transition-colors"><i class="ph-bold ph-x"></i></button>
                </div>
                
                <h3 id="modalTitle" class="text-2xl font-black text-slate-900 dark:text-white mb-2">Event Title</h3>
                
                <div class="flex items-center gap-2 text-sm font-bold text-slate-500 dark:text-zinc-400 mb-6">
                    <i class="ph-fill ph-map-pin text-[#1da85b] dark:text-[#4ade80]"></i>
                    <span id="modalLocation">Location</span>
                </div>
                
                <div class="pt-6 border-t border-slate-100 dark:border-zinc-800">
                    <span class="text-[10px] font-bold text-slate-400 dark:text-zinc-500 uppercase tracking-widest">Status</span>
                    <p id="modalStatus" class="text-sm font-bold text-slate-800 dark:text-zinc-200 mt-1">Status</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const rawEvents = <?= $events_json ?>;
        const events = rawEvents.map(e => ({ ...e, dateObj: new Date(e.event_date) }));

        let currentDate = new Date();
        const today = new Date();

        const calendarGrid = document.getElementById('calendarGrid');
        const monthYearDisplay = document.getElementById('monthYearDisplay');

        function renderCalendar() {
            calendarGrid.innerHTML = '';
            const year = currentDate.getFullYear();
            const month = currentDate.getMonth();

            const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            monthYearDisplay.textContent = `${monthNames[month]} ${year}`;

            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();

            for (let i = 0; i < firstDay; i++) {
                const cell = document.createElement('div');
                cell.className = 'bg-slate-50 dark:bg-zinc-900/50 cal-cell p-2 transition-colors';
                calendarGrid.appendChild(cell);
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const cell = document.createElement('div');
                const isToday = day === today.getDate() && month === today.getMonth() && year === today.getFullYear();
                
                cell.className = `bg-white dark:bg-zinc-950 cal-cell p-2 flex flex-col gap-1 transition-colors`;
                let dayNumberHTML = `<span class="text-sm font-bold ${isToday ? 'bg-[#1da85b] text-white w-7 h-7 flex items-center justify-center rounded-full' : 'text-slate-700 dark:text-zinc-300'}">${day}</span>`;
                
                cell.innerHTML = `<div class="flex justify-between items-start mb-1">${dayNumberHTML}</div><div class="event-container flex-1 overflow-y-auto custom-scrollbar space-y-1"></div>`;

                const dayEvents = events.filter(e => e.dateObj.getDate() === day && e.dateObj.getMonth() === month && e.dateObj.getFullYear() === year);
                const eventContainer = cell.querySelector('.event-container');

                dayEvents.forEach(ev => {
                    const isCompleted = ev.status === 'Completed';
                    const evColors = isCompleted 
                        ? 'bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 border-slate-200 dark:border-zinc-700' 
                        : 'bg-brand-50 dark:bg-brand-500/10 text-brand-700 dark:text-brand-400 border-brand-100 dark:border-brand-500/20 cursor-pointer hover:scale-[1.02]';

                    const evJson = JSON.stringify(ev).replace(/"/g, '&quot;');

                    eventContainer.innerHTML += `
                        <div onclick="openEventModal(${evJson})" class="px-2 py-1.5 text-[11px] font-bold rounded border truncate transition-all ${evColors}" title="${ev.title}">
                            ${ev.title}
                        </div>
                    `;
                });

                calendarGrid.appendChild(cell);
            }

            const totalCells = firstDay + daysInMonth;
            const remainingCells = (Math.ceil(totalCells / 7) * 7) - totalCells;
            for (let i = 0; i < remainingCells; i++) {
                const cell = document.createElement('div');
                cell.className = 'bg-slate-50 dark:bg-zinc-900/50 cal-cell p-2 transition-colors';
                calendarGrid.appendChild(cell);
            }
        }

        function openEventModal(eventData) {
            document.getElementById('modalTitle').textContent = eventData.title;
            document.getElementById('modalLocation').textContent = eventData.location || 'Location TBA';
            document.getElementById('modalStatus').textContent = eventData.status;
            
            const d = new Date(eventData.event_date);
            document.getElementById('modalDate').textContent = d.toLocaleDateString('en-US', { weekday: 'short', month: 'long', day: 'numeric', hour: '2-digit', minute:'2-digit' });

            const imgContainer = document.getElementById('modalImageContainer');
            if (eventData.image_path) {
                document.getElementById('modalImage').src = eventData.image_path;
                imgContainer.classList.remove('hidden');
            } else {
                imgContainer.classList.add('hidden');
            }

            const modal = document.getElementById('eventModal');
            const content = document.getElementById('modalContent');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => content.classList.remove('scale-95', 'opacity-0'), 10);
        }

        function closeEventModal() {
            const modal = document.getElementById('eventModal');
            const content = document.getElementById('modalContent');
            content.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }

        document.getElementById('prevMonth').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); });
        document.getElementById('nextMonth').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); });
        document.getElementById('todayBtn').addEventListener('click', () => { currentDate = new Date(); renderCalendar(); });

        renderCalendar();
    </script>
</body>
</html>