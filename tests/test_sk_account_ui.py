"""Exercise the actual account dialog without an authenticated session or real accounts."""
import base64
from pathlib import Path
import subprocess
import time
import unittest
import uuid
import json

import test_form_intellisense as browser_tests


class SkAccountUiTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        browser_tests.FormHistoryTests.setUpClass()
        cls.html = subprocess.check_output([
            'C:/Users/azare/php82/php.exe', str(Path(__file__).with_name('render_sk_account_fixture.php')),
        ]).decode('utf-8')

    @classmethod
    def tearDownClass(cls):
        browser_tests.FormHistoryTests.tearDownClass()

    def js(self, expression):
        return browser_tests.FormHistoryTests.js(expression)

    def cdp(self, method, params=None):
        return browser_tests.FormHistoryTests.cdp(method, params)

    def setUp(self):
        self.cdp('Emulation.setFocusEmulationEnabled', {'enabled': True})
        self.cdp('Emulation.setDeviceMetricsOverride', {'width': 1280, 'height': 900, 'deviceScaleFactor': 1, 'mobile': False})
        self.cdp('Page.navigate', {'url': 'http://localhost:8080/form-intellisense.js?ui=' + uuid.uuid4().hex})
        for _ in range(100):
            if self.js("document.readyState === 'complete' && location.pathname === '/form-intellisense.js'"):
                break
            time.sleep(0.05)
        self.js("history.replaceState(null,'','/admin_sk_accounts.php')")
        self.js('document.open();document.write(' + json.dumps(self.html) + ');document.close();')
        for _ in range(200):
            if self.js("document.readyState === 'complete' && !!document.getElementById('createSkAccountForm')"):
                break
            time.sleep(0.05)
        self.assertTrue(self.js("!!document.getElementById('createSkAccountForm')"))
        self.js("window.submitCount=0;createSkAccountForm.addEventListener('submit',e=>{e.preventDefault();submitCount++;});")

    def test_dedicated_page_sidebar_and_manual_submission(self):
        self.assertEqual(self.js("new URL(createSkAccountForm.action).pathname"), '/admin_sk_accounts.php')
        self.assertTrue(self.js("!!document.querySelector('a.tala-nav-btn.active[href=\"admin_sk_accounts.php\"]')"))
        self.assertEqual(self.js("document.querySelectorAll('dialog').length"), 0)
        self.assertEqual(self.js('skAccountPosition.value'), 'SK Chairperson')
        self.assertTrue(self.js('skAccountPosition.readOnly'))
        self.assertEqual(self.js('submitCount'), 0)
        self.js("skAccountFullName.value='Ana Reyes';skAccountBarangay.value='Annafunan';skAccountUsername.value='sk.annafunan';skAccountPassword.value='Workshop2026!';skAccountConfirmPassword.value='Workshop2026!';createSkAccountForm.requestSubmit();")
        self.assertEqual(self.js('submitCount'), 1)

    def test_confirmation_and_password_visibility(self):
        self.js("skAccountPassword.value='Workshop2026!';skAccountConfirmPassword.value='different';skAccountConfirmPassword.dispatchEvent(new Event('input'));")
        self.assertFalse(self.js('skAccountConfirmPassword.checkValidity()'))
        self.js('toggleSkAccountPasswords.click()')
        self.assertEqual(self.js('[skAccountPassword.type,skAccountConfirmPassword.type]'), ['text', 'text'])
        self.js('toggleSkAccountPasswords.click()')
        self.assertEqual(self.js('skAccountPassword.type'), 'password')
        self.assertEqual(self.js('submitCount'), 0)

    def test_responsive_light_and_dark_layout(self):
        output = Path(__file__).resolve().parents[1] / 'tmp/screenshots'
        output.mkdir(parents=True, exist_ok=True)
        for width, height, theme in [(1280, 900, 'light'), (390, 844, 'dark')]:
            self.cdp('Emulation.setDeviceMetricsOverride', {'width': width, 'height': height, 'deviceScaleFactor': 1, 'mobile': False})
            self.js(f"document.documentElement.classList.toggle('dark', {str(theme == 'dark').lower()})")
            time.sleep(0.25)
            self.assertTrue(self.js('(()=>{const r=createSkAccountForm.getBoundingClientRect();return r.left>=0 && r.right<=innerWidth+1 && document.documentElement.scrollWidth<=innerWidth+1;})()'))
            shot = self.cdp('Page.captureScreenshot', {'format': 'png'})
            (output / f'sk-accounts-page-{theme}.png').write_bytes(base64.b64decode(shot['data']))


if __name__ == '__main__':
    unittest.main(verbosity=2)
