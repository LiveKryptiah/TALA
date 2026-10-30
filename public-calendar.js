/* Read SQL dates locally so calendar days are consistent across browsers. */
(() => {
    'use strict';
    const parseDate = value => {
        const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        if (!match) return null;
        const [, y, m, d, h = 0, min = 0, s = 0] = match;
        const date = new Date(+y, +m - 1, +d, +h, +min, +s);
        return Number.isNaN(date.getTime()) ? null : date;
    };
    const events = JSON.parse(document.getElementById('calendarEvents').textContent)
        .map(event => ({ ...event, date: parseDate(event.event_date) })).filter(event => event.date);
    const today = new Date();
    let selectedDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
    let currentDate = new Date(today.getFullYear(), today.getMonth(), 1);
    const grid = document.getElementById('calendarGrid');
    const dialog = document.getElementById('eventModal');
    const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    const time = date => date.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
    function element(tag, className, text) {
        const node = document.createElement(tag);
        node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }
    function renderAgenda() {
        document.getElementById('selectedDateDisplay').textContent = selectedDate.toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric' });
        const dayEvents = events.filter(event => sameDay(event.date, selectedDate));
        document.getElementById('selectedDayCount').textContent = dayEvents.length ? `${dayEvents.length} event${dayEvents.length === 1 ? '' : 's'} on this day` : 'A little room for what comes next.';
        const list = document.getElementById('dayEvents');
        list.replaceChildren();
        if (!dayEvents.length) {
            const empty = element('div', 'calendar-empty');
            empty.append(element('h3', '', 'No events scheduled'), element('p', '', 'Choose another date to explore community activities. New programs will appear here when scheduled.'));
            list.append(empty);
        }
        dayEvents.forEach(event => {
            const button = element('button', 'calendar-event-card');
            button.type = 'button';
            button.setAttribute('aria-haspopup', 'dialog');
            button.append(element('span', 'calendar-status' + (event.status === 'Completed' ? ' is-completed' : ''), event.status || 'Scheduled'), element('h3', '', event.title), element('p', '', time(event.date)), element('p', '', event.location || 'Location to be announced'));
            button.addEventListener('click', () => openEventModal(event));
            list.append(button);
        });
    }
    function renderCalendar(focusDay) {
        grid.replaceChildren();
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        document.getElementById('monthYearDisplay').textContent = currentDate.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
        const monthEvents = events.filter(event => event.date.getFullYear() === year && event.date.getMonth() === month);
        document.getElementById('monthEventCount').textContent = `${monthEvents.length} community event${monthEvents.length === 1 ? '' : 's'} this month`;
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const total = Math.ceil((firstDay + daysInMonth) / 7) * 7;
        for (let i = 0; i < total; i++) {
            const day = i - firstDay + 1;
            if (day < 1 || day > daysInMonth) {
                const blank = element('div', 'calendar-blank');
                blank.setAttribute('aria-hidden', 'true');
                grid.append(blank);
                continue;
            }
            const date = new Date(year, month, day);
            const dayEvents = monthEvents.filter(event => sameDay(event.date, date));
            const button = element('button', 'calendar-date' + (sameDay(date, today) ? ' is-today' : ''));
            button.type = 'button';
            button.dataset.day = day;
            button.setAttribute('aria-pressed', String(sameDay(date, selectedDate)));
            button.setAttribute('aria-label', date.toLocaleDateString('en-PH', { dateStyle: 'full' }) + `, ${dayEvents.length} event${dayEvents.length === 1 ? '' : 's'}`);
            if (sameDay(date, today)) button.setAttribute('aria-current', 'date');
            button.append(element('span', 'calendar-day-number', day));
            if (dayEvents.length) {
                button.append(element('span', 'calendar-date-event', dayEvents[0].title));
                const dots = element('span', 'calendar-date-dots');
                dots.setAttribute('aria-hidden', 'true');
                for (let j = 0; j < Math.min(dayEvents.length, 3); j++) dots.append(element('i', 'calendar-dot'));
                button.append(dots);
            }
            button.addEventListener('click', () => { selectedDate = date; renderCalendar(day); });
            button.addEventListener('keydown', event => {
                const offsets = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
                if (!(event.key in offsets)) return;
                event.preventDefault();
                selectedDate = new Date(year, month, day + offsets[event.key]);
                currentDate = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1);
                renderCalendar(selectedDate.getDate());
            });
            grid.append(button);
        }
        renderAgenda();
        if (focusDay) grid.querySelector(`[data-day="${focusDay}"]`)?.focus({ preventScroll: true });
    }
    function openEventModal(event) {
        document.getElementById('modalTitle').textContent = event.title;
        document.getElementById('modalLocation').textContent = event.location || 'Location to be announced';
        const badge = document.getElementById('modalStatus');
        badge.textContent = event.status || 'Scheduled';
        badge.classList.toggle('is-completed', event.status === 'Completed');
        document.getElementById('modalDate').textContent = event.date.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) + ' · ' + time(event.date);
        const image = document.getElementById('modalImage');
        const imageContainer = document.getElementById('modalImageContainer');
        imageContainer.hidden = true;
        image.removeAttribute('src');
        image.alt = event.title;
        if (event.image_path) {
            const url = new URL(event.image_path, location.href);
            if (['http:', 'https:'].includes(url.protocol)) {
                image.onload = () => { imageContainer.hidden = false; };
                image.onerror = () => { imageContainer.hidden = true; };
                image.src = url.href;
            }
        }
        dialog.showModal();
    }
    document.getElementById('closeEventModal').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    function moveMonth(offset) {
        // Day one prevents the 29th–31st from skipping shorter months.
        currentDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + offset, 1);
        selectedDate = new Date(currentDate);
        renderCalendar();
    }
    document.getElementById('prevMonth').addEventListener('click', () => moveMonth(-1));
    document.getElementById('nextMonth').addEventListener('click', () => moveMonth(1));
    document.getElementById('todayBtn').addEventListener('click', () => {
        selectedDate = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        currentDate = new Date(today.getFullYear(), today.getMonth(), 1);
        renderCalendar();
    });
    renderCalendar();
})();
