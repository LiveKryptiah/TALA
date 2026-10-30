(() => {
    'use strict';
    const form = document.getElementById('createSkAccountForm');
    const password = document.getElementById('skAccountPassword');
    const confirmation = document.getElementById('skAccountConfirmPassword');
    const toggle = document.getElementById('toggleSkAccountPasswords');
    if (!form || !password || !confirmation || !toggle) return;
    function validateConfirmation() {
        confirmation.setCustomValidity(confirmation.value && confirmation.value !== password.value ? 'The passwords do not match.' : '');
    }
    password.addEventListener('input', validateConfirmation);
    confirmation.addEventListener('input', validateConfirmation);
    toggle.addEventListener('click', () => {
        const show = password.type === 'password';
        password.type = confirmation.type = show ? 'text' : 'password';
        toggle.textContent = show ? 'Hide passwords' : 'Show passwords';
        toggle.setAttribute('aria-pressed', String(show));
    });
})();
