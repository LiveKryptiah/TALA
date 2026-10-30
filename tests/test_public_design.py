"""Read-only browser checks for public page consistency and the shared theme switch."""
from pathlib import Path
import base64
import time
import unittest
import test_form_intellisense as browser_tests


class PublicDesignTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        browser_tests.FormHistoryTests.setUpClass()

    @classmethod
    def tearDownClass(cls):
        browser_tests.FormHistoryTests.tearDownClass()

    def js(self, expression):
        return browser_tests.FormHistoryTests.js(expression)

    def cdp(self, method, params=None):
        return browser_tests.FormHistoryTests.cdp(method, params)

    def wait_for(self, expression, limit=200):
        for _ in range(limit):
            if self.js(expression):
                return
            time.sleep(.05)
        self.fail('Timed out: ' + expression)

    def open(self, page, width=1280, height=900):
        self.cdp('Emulation.setDeviceMetricsOverride', {'width':width,'height':height,'deviceScaleFactor':1,'mobile':False})
        self.cdp('Page.navigate', {'url':'http://localhost:8080/' + page})
        self.wait_for("location.pathname === '/" + page + "' && document.readyState !== 'loading' && !!window.talaTheme && !!document.querySelector('[data-tala-theme-control]')")
        self.assertNotIn('Fatal error', self.js('document.body.innerText'))
        self.js("talaTheme.set('light',{animate:false})")
        self.wait_for("!document.documentElement.classList.contains('tala-theme-changing')")

    def test_public_routes_share_surfaces_and_working_switches(self):
        pages=['index.php','features.php','mydc_registry.php','officials.php','public_assets.php','public_budget.php',
               'public_calendar.php','public_disclosure.php','public_events.php','public_governance.php',
               'public_officials.php','public_polls.php','public_yorp.php','scholarships.php','suggestions.php',
               'updates.php','about.php','scholarship.php']
        for page in pages:
            with self.subTest(page=page):
                self.open(page)
                self.assertTrue(self.js("document.body.classList.contains('tala-public')"))
                self.assertEqual(self.js('getComputedStyle(document.body).backgroundColor'), 'rgb(255, 255, 255)')
                self.assertTrue(self.js("!!document.getElementById('main-public-header')"))
                self.assertTrue(self.js("!!document.querySelector('footer a[href=\"public_events.php\"]')"))
                self.js("window.themeCount=0;window.addEventListener('tala:themechange',()=>themeCount++);Array.from(document.querySelectorAll('[data-tala-theme-control]')).find(b=>b.getClientRects().length).click()")
                self.wait_for("document.documentElement.classList.contains('dark') && !document.documentElement.classList.contains('tala-theme-changing')")
                self.assertEqual(self.js('getComputedStyle(document.body).backgroundColor'), 'rgb(9, 9, 11)')
                self.assertEqual(self.js('themeCount'), 1)
                self.assertTrue(self.js("Array.from(document.querySelectorAll('[data-tala-theme-control]')).every(b=>b.getAttribute('aria-checked')==='true')"))

    def test_atomic_fallback_and_rapid_switching(self):
        self.open('features.php')
        self.js("talaTheme.toggle();talaTheme.toggle();talaTheme.toggle();talaTheme.toggle();")
        time.sleep(.35)
        self.assertFalse(self.js("document.documentElement.classList.contains('dark')"))
        self.js("document.startViewTransition=undefined;talaTheme.set('dark')")
        self.assertEqual(self.js('getComputedStyle(document.body).backgroundColor'), 'rgb(9, 9, 11)')
        self.assertEqual(self.js("getComputedStyle(document.querySelector('main h1')).transitionDuration"), '0s')
        self.wait_for("!document.documentElement.classList.contains('tala-theme-changing')")
        self.js("dispatchEvent(new StorageEvent('storage',{key:'theme',newValue:'light'}))")
        self.assertFalse(self.js("document.documentElement.classList.contains('dark')"))

    def test_mobile_layout_and_mydo_portrait(self):
        output=Path(__file__).resolve().parents[1]/'tmp/screenshots'
        output.mkdir(parents=True,exist_ok=True)
        for page in ['features.php','public_assets.php','public_yorp.php','public_officials.php']:
            with self.subTest(page=page):
                self.open(page,390,844)
                self.assertTrue(self.js('document.documentElement.scrollWidth<=innerWidth+1'))
                self.js("talaTheme.set('dark',{animate:false})")
                self.wait_for("!document.documentElement.classList.contains('tala-theme-changing')")
                shot=self.cdp('Page.captureScreenshot',{'format':'png'})
                (output/('public-'+page.replace('.php','')+'-mobile.png')).write_bytes(base64.b64decode(shot['data']))
        self.open('public_officials.php')
        self.assertNotIn('Deprecated:', self.js('document.body.innerText'))
        self.assertTrue(self.js("(()=>{const img=document.querySelector('img[src=\"uploads/admin_profiles/mydo-profile.png\"]');return !!img && getComputedStyle(img).objectPosition==='50% 0%' && img.getBoundingClientRect().height>=200;})()"))

    def test_nine_generated_center_images(self):
        self.open('index.php')
        names=['health','education','economy','inclusion','peace','governance','citizenship','environment','mobility']
        self.js("document.getElementById('advocacies').scrollIntoView({block:'center'})")
        for index,name in enumerate(names):
            self.js(f"document.querySelectorAll('#center-dots button')[{index}].click()")
            self.wait_for(f"(()=>{{const img=document.getElementById('center-img');return img.currentSrc.includes('/assets/centers/{name}.webp') && img.complete && img.naturalWidth>0;}})()")
            self.assertEqual(self.js("document.getElementById('center-counter').textContent.trim()"),f'{index+1} of 9')
        self.js("document.querySelector('#center-dots button').click()")
        self.wait_for("document.getElementById('center-img').currentSrc.includes('/health.webp')")
        self.js("document.getElementById('tala-popup')?.querySelector('button')?.click()")
        time.sleep(1)
        shot=self.cdp('Page.captureScreenshot',{'format':'png'})
        output=Path(__file__).resolve().parents[1]/'tmp/screenshots/centers-homepage.png'
        output.write_bytes(base64.b64decode(shot['data']))

    def test_login_switch_stays_in_corner_and_does_not_submit(self):
        self.open('login.php',390,844)
        self.assertEqual(self.js("getComputedStyle(document.getElementById('themeToggle')).position"),'absolute')
        self.js("window.loginSubmits=0;document.querySelector('form').addEventListener('submit',e=>{e.preventDefault();loginSubmits++;});document.getElementById('themeToggle').click()")
        self.wait_for("document.documentElement.classList.contains('dark')")
        self.assertEqual(self.js('loginSubmits'),0)
        self.assertEqual(self.js("document.querySelector('input[name=username]').value"),'')


if __name__=='__main__':
    unittest.main(verbosity=2)
