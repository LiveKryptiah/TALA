"""Sample form browser regression tests. Run with Python and websocket-client; uses installed Edge."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import time
import unittest
import urllib.request
import uuid

import websocket


class FormHistoryTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.profile = Path(tempfile.mkdtemp(prefix='tala-form-test-')).resolve()
        edge = Path(os.environ.get('PROGRAMFILES(X86)', 'C:/Program Files (x86)')) / 'Microsoft/Edge/Application/msedge.exe'
        cls.process = subprocess.Popen([
            str(edge), '--headless=new', '--disable-gpu', '--no-first-run',
            '--remote-debugging-port=0', '--remote-allow-origins=http://localhost',
            f'--user-data-dir={cls.profile}', 'about:blank',
        ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=subprocess.CREATE_NO_WINDOW)
        deadline = time.time() + 20
        port_file = cls.profile / 'DevToolsActivePort'
        while time.time() < deadline:
            try:
                port = port_file.read_text().splitlines()[0]
                break
            except (OSError, IndexError):
                time.sleep(0.1)
        else:
            cls.process.terminate()
            raise RuntimeError('Edge did not expose its debugging port within 20 seconds')
        with urllib.request.urlopen(f'http://127.0.0.1:{port}/json') as response:
            target = next(item for item in json.load(response) if item['type'] == 'page')
        cls.ws = websocket.create_connection(target['webSocketDebuggerUrl'], origin='http://localhost', timeout=15)
        cls.serial = 0
        cls.source = (Path(__file__).resolve().parents[1] / 'form-intellisense.js').read_text(encoding='utf-8')
        cls.scope = 'test-' + uuid.uuid4().hex

    @classmethod
    def cdp(cls, method, params=None):
        cls.serial += 1
        ident = cls.serial
        cls.ws.send(json.dumps({'id': ident, 'method': method, 'params': params or {}}))
        while True:
            message = json.loads(cls.ws.recv())
            if message.get('id') == ident:
                if 'error' in message:
                    raise AssertionError(message['error'])
                return message.get('result', {})

    @classmethod
    def js(cls, expression):
        result = cls.cdp('Runtime.evaluate', {'expression': expression, 'returnByValue': True, 'awaitPromise': True})
        if 'exceptionDetails' in result:
            raise AssertionError(result['exceptionDetails'])
        return result['result'].get('value')

    def setUp(self):
        self.mount()

    def mount(self, hidden=False, existing=''):
        self.cdp('Emulation.setFocusEmulationEnabled', {'enabled': True})
        self.cdp('Page.navigate', {'url': 'http://localhost:8080/form-intellisense.js?run=' + uuid.uuid4().hex})
        for _ in range(100):
            if self.js("location.pathname === '/form-intellisense.js' && document.readyState === 'complete'"):
                break
            time.sleep(0.05)
        fixture = '<!doctype html><html><head></head><body><div id="modal"' + (' hidden' if hidden else '') + '>\n' + '''
            <form method="post" id="entry"><label for="title">Event Title</label><input name="title" id="title">
            <textarea name="details" id="details"></textarea>
            <select name="status" id="eventStatus"><option value="">Choose</option><option>Upcoming</option></select>
            <input name="participant_limit" type="number" min="1" max="5000" id="limit">
            <input name="event_date" type="datetime-local" id="eventDate">
            <input name="email" type="email" id="email">
            <input type="hidden" name="record_id" value="42" id="recordId">
            <input type="hidden" name="action" value="create">
            <input name="attachment" type="file" id="attachment">
            <input type="checkbox" name="consent" id="consent">
            <button id="save" type="submit">Save</button></form></div></body></html>'''
        self.js(f'document.open(); document.write({json.dumps(fixture)}); document.close();')
        self.js("history.replaceState(null, '', '/admin_calendar.php')")
        self.js(f'''window.submits=0; window.changes=0; window.inputs=0; window.testErrors=[];
            window.addEventListener('error',e=>testErrors.push(e.message));
            entry.addEventListener('submit',e=>{{e.preventDefault();submits++;}});
            entry.addEventListener('change',()=>changes++);
            entry.addEventListener('input',()=>inputs++);
            title.value={json.dumps(existing)};
            const script=document.createElement('script');script.textContent={json.dumps(self.source)};document.body.append(script);''')

    def test_visible_event_form_is_filled_without_saving(self):
        self.assertEqual(self.js('title.value'), 'Youth Leadership and Community Service Workshop')
        self.assertTrue(self.js('details.value.length > 40'))
        self.assertEqual(self.js('eventStatus.value'), 'Upcoming')
        self.assertEqual(self.js('limit.value'), '50')
        self.assertTrue(self.js('eventDate.value.length > 0 && email.validity.valid'))
        self.assertEqual(self.js('[submits,changes,inputs]'), [0,0,0])
        self.assertEqual(self.js('testErrors'), [])
        self.js('save.click()')
        self.assertEqual(self.js('submits'), 1)

    def test_modal_fills_when_opened(self):
        self.mount(hidden=True)
        self.assertEqual(self.js('title.value'), '')
        self.js('modal.hidden=false')
        for _ in range(30):
            if self.js("title.value !== ''"):
                break
            time.sleep(0.05)
        self.assertTrue(self.js('title.value.length > 0 && details.value.length > 0'))
        self.assertEqual(self.js('submits'), 0)

    def test_existing_record_fields_are_preserved(self):
        self.mount(existing='Existing real event')
        self.assertEqual(self.js('title.value'), 'Existing real event')
        self.assertEqual(self.js('recordId.value'), '42')
        self.assertFalse(self.js('consent.checked'))

    def test_no_sample_controls_are_added(self):
        self.assertEqual(self.js("document.querySelectorAll('.tala-sample-toolbar').length"), 0)
        self.assertEqual(self.js("Array.from(document.querySelectorAll('button'), b=>b.textContent)"), ['Save'])

    def test_login_and_filter_forms_are_excluded(self):
        self.js('''document.body.insertAdjacentHTML('beforeend',
            '<form action="login.php" method="post"><input name="username" id="username"><input type="password" name="password" id="password"></form>' +
            '<form method="get"><input name="search" id="search"><input name="filter" id="filter"></form>');''')
        time.sleep(0.1)
        self.assertEqual(self.js('[username.value,password.value,search.value,filter.value]'), ['', '', '', ''])
        self.assertEqual(self.js("document.querySelectorAll('.tala-sample-toolbar').length"), 0)

    def test_newly_visible_fields_fill_without_replacing_user_edits(self):
        self.js("title.value='My own title'; entry.insertAdjacentHTML('beforeend','<div id=step hidden><input name=location id=venue></div>')")
        time.sleep(0.1)
        self.assertEqual(self.js('venue.value'), '')
        self.js('step.hidden=false')
        for _ in range(30):
            if self.js("venue.value !== ''"):
                break
            time.sleep(0.05)
        self.assertEqual(self.js('venue.value'), 'Tumauini Municipal Multi-Purpose Hall')
        self.assertEqual(self.js('title.value'), 'My own title')
        self.assertEqual(self.js('submits'), 0)

    def wait_for(self, condition):
        for _ in range(100):
            if self.js(condition):
                return
            time.sleep(0.05)
        self.fail('Condition did not become true: ' + condition)

    def test_pdf_and_images_match_accepted_file_formats(self):
        self.js('''entry.insertAdjacentHTML('beforeend',
            '<input type="file" name="photo" accept="image/*" id="photo">' +
            '<input type="file" name="portrait" accept=".jpg" id="jpg">' +
            '<input type="file" name="poster" accept="image/webp" id="webp">' +
            '<input type="file" name="document" accept="application/pdf" id="pdf">' +
            '<input type="file" name="csv" accept=".csv" id="csv">');''')
        self.wait_for('photo.files.length && jpg.files.length && webp.files.length && pdf.files.length')
        self.assertEqual(self.js('[photo.files[0].type,jpg.files[0].type,webp.files[0].type,pdf.files[0].type]'),
                         ['image/png','image/jpeg','image/webp','application/pdf'])
        self.assertEqual(self.js("pdf.files[0].slice(0,4).text()"), '%PDF')
        self.assertEqual(self.js('createImageBitmap(photo.files[0]).then(image=>[image.width,image.height])'), [600,600])
        self.assertIn('ana-reyes', self.js('photo.files[0].name'))
        self.assertEqual(self.js('csv.files.length'), 0)
        self.assertEqual(self.js('[submits,changes,inputs]'), [0,0,0])
        self.assertEqual(self.js("new FormData(entry).get('document').size > 0"), True)

    def test_manual_file_selection_is_preserved(self):
        self.js("const transfer=new DataTransfer();transfer.items.add(new File(['My chosen document'],'chosen.pdf',{type:'application/pdf'}));attachment.files=transfer.files;")
        time.sleep(0.2)
        self.assertEqual(self.js('attachment.files[0].name'), 'chosen.pdf')
        self.assertEqual(self.js('attachment.files[0].text()'), 'My chosen document')

    def test_hidden_image_picker_in_file_only_form_is_filled(self):
        self.js('''document.body.insertAdjacentHTML('beforeend',
            '<form method="post"><label>Upload Picture<input type="file" name="profile_picture" accept="image/*" id="hiddenPhoto" style="display:none"></label></form>');''')
        self.wait_for('hiddenPhoto.files.length === 1')
        self.assertEqual(self.js('hiddenPhoto.files[0].type'), 'image/png')

    def test_values_match_field_labels_and_form_context(self):
        self.js('''history.replaceState(null,'','/manage_scholarships.php');
            entry.insertAdjacentHTML('beforeend',
            '<input name="title" id="scholarshipTitle">' +
            '<input name="provider" id="provider">' +
            '<textarea name="description" id="scholarshipDetails"></textarea>' +
            '<input name="full_name" id="fullName">' +
            '<input name="resolution_number" id="resolutionNumber">' +
            '<input name="org_name" id="orgName">' +
            '<input name="button_text" id="buttonText">');''')
        self.wait_for("scholarshipTitle.value !== ''")
        self.assertEqual(self.js('scholarshipTitle.value'), 'Tumauini Youth Educational Assistance Program')
        self.assertEqual(self.js('provider.value'), 'Municipal Government of Tumauini')
        self.assertIn('proof of enrollment', self.js('scholarshipDetails.value'))
        self.assertEqual(self.js('fullName.value'), 'Ana Santos Reyes')
        self.assertEqual(self.js('resolutionNumber.value'), '2026-015')
        self.assertEqual(self.js('orgName.value'), 'Tumauini Youth Volunteers Association')
        self.assertEqual(self.js('buttonText.value'), 'View Program Details')
        self.assertNotIn('sample', self.js('scholarshipDetails.value').lower())
        self.assertEqual(self.js('submits'), 0)

    @classmethod
    def tearDownClass(cls):
        try:
            cls.cdp('Browser.close')
        except Exception:
            pass
        cls.ws.close()
        try:
            cls.process.wait(timeout=15)
        except subprocess.TimeoutExpired:
            # Only stop the headless process owned by this test fixture.
            cls.process.kill()
            try:
                cls.process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                # Windows can delay signaling the exited browser process handle.
                # Keep functional assertions independent of this cleanup delay.
                import warnings
                warnings.warn('Headless Edge cleanup did not finish within 20 seconds', RuntimeWarning)
        if cls.profile.parent == Path(tempfile.gettempdir()).resolve() and cls.profile.name.startswith('tala-form-test-'):
            shutil.rmtree(cls.profile, ignore_errors=True)


if __name__ == '__main__':
    unittest.main(verbosity=2)
