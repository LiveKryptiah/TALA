"""Read-only calendar browser checks; event fixtures stay inside the browser."""
import base64
import json
from pathlib import Path
import unittest
from test_public_design import PublicDesignTests


class CalendarTests(PublicDesignTests):
    def test_calendar_months_keyboard_and_today(self):
        self.open('public_calendar.php')
        initial = self.js("monthYearDisplay.textContent")
        self.assertEqual(self.js("document.querySelectorAll('.calendar-date').length"), self.js('new Date(new Date().getFullYear(),new Date().getMonth()+1,0).getDate()'))
        self.js("document.querySelector('[data-day=\"31\"]')?.click();nextMonth.click();prevMonth.click()")
        self.assertEqual(self.js('monthYearDisplay.textContent'), initial)
        self.js("document.querySelector('[data-day=\"1\"]').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowLeft',bubbles:true}))")
        self.assertNotEqual(self.js('monthYearDisplay.textContent'), initial)
        self.js('todayBtn.click()')
        self.assertEqual(self.js('monthYearDisplay.textContent'), initial)
        self.assertTrue(self.js("document.querySelector('[aria-current=date]').getAttribute('aria-pressed')==='true'"))

    def test_event_details_safe_text_and_escape(self):
        self.open('public_calendar.php')
        # Reinitialize only the calendar using a browser-only record; never write to DB.
        self.js("const now=new Date();const localDay=[now.getFullYear(),String(now.getMonth()+1).padStart(2,'0'),String(now.getDate()).padStart(2,'0')].join('-');calendarEvents.textContent=JSON.stringify([{id:1,title:'<img src=x onerror=alert(1)> Youth Assembly',event_date:localDay+' 14:30:00',location:'Municipal Hall',status:'Upcoming',image_path:''}])")
        source = (Path(__file__).resolve().parents[1]/'public-calendar.js').read_text()
        self.js('eval(' + json.dumps(source) + ')')
        self.js("document.querySelector('[aria-current=date]').click()")
        self.assertEqual(self.js("document.querySelectorAll('#dayEvents img').length"), 0)
        self.js("document.querySelector('.calendar-event-card').click()")
        self.assertTrue(self.js('eventModal.open'))
        self.assertIn('Youth Assembly', self.js('modalTitle.textContent'))
        self.assertIn('2:30', self.js('modalDate.textContent'))
        self.assertEqual(self.js('modalLocation.textContent'), 'Municipal Hall')
        self.cdp('Input.dispatchKeyEvent', {'type':'keyDown','key':'Escape','code':'Escape','windowsVirtualKeyCode':27})
        self.cdp('Input.dispatchKeyEvent', {'type':'keyUp','key':'Escape','code':'Escape','windowsVirtualKeyCode':27})
        self.wait_for('!eventModal.open')

    def test_calendar_mobile_and_both_themes(self):
        output = Path(__file__).resolve().parents[1]/'tmp/screenshots'
        output.mkdir(parents=True, exist_ok=True)
        for width in [390, 1280]:
            self.open('public_calendar.php', width, 1000)
            for theme in ['light', 'dark']:
                self.js(f"talaTheme.set('{theme}',{{animate:false}})")
                self.wait_for("!document.documentElement.classList.contains('tala-theme-changing')")
                self.assertTrue(self.js('document.documentElement.scrollWidth<=innerWidth+1'))
                self.assertEqual(self.js("getComputedStyle(document.querySelector('.calendar-panel')).backgroundColor"), 'rgb(24, 24, 27)' if theme=='dark' else 'rgb(248, 250, 252)')
                self.js('document.querySelector(".calendar-panel").scrollIntoView({block:"center"})')
                shot = self.cdp('Page.captureScreenshot', {'format':'png'})
                (output/f'calendar-{width}-{theme}.png').write_bytes(base64.b64decode(shot['data']))


if __name__ == '__main__':
    # Run calendar-specific checks; shared public-site checks have their own suite.
    suite = unittest.TestSuite(CalendarTests(name) for name in [
        'test_calendar_months_keyboard_and_today', 'test_event_details_safe_text_and_escape', 'test_calendar_mobile_and_both_themes'])
    result = unittest.TextTestRunner(verbosity=2).run(suite)
    raise SystemExit(not result.wasSuccessful())
