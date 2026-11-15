/**
 * visitors.js
 *
 * Shared behaviour for every page:
 * - Light/dark theme toggle, saved in localStorage ('visitors-theme'). The
 *   saved theme is applied before first paint by an inline script in
 *   header.php, this file only handles the button.
 * - Toast messages, and a confirm pop-up for forms with data-confirm.
 * - The kiosk page: signing in, and the sign-out search.
 *
 * Everything talks to process.php with relative URLs, because Home
 * Assistant serves the add-on under a deep ingress path.
 */

(function () {
    'use strict';

    const html = document.documentElement;
    const svg = (paths) => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths + '</svg>';
    const icons = {
        sun: svg('<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>'),
        moon: svg('<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>'),
        logOut: svg('<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>'),
    };

    // --- Theme --------------------------------------------------------------

    function currentTheme() {
        return html.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function renderThemeButton(button) {
        // Show the theme you'd switch TO
        const next = currentTheme() === 'light' ? 'dark' : 'light';
        button.innerHTML = next === 'dark' ? icons.moon : icons.sun;
        button.setAttribute('aria-label', next === 'dark' ? 'Switch to the dark theme' : 'Switch to the light theme');
        button.title = button.getAttribute('aria-label');
    }

    // --- Toasts -------------------------------------------------------------

    function showToast(message, type, duration) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = 'toast toast--' + (type || 'info');
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.textContent = message;
        container.appendChild(toast);

        requestAnimationFrame(() => toast.classList.add('toast--visible'));
        setTimeout(() => {
            toast.classList.remove('toast--visible');
            setTimeout(() => toast.remove(), 400);
        }, duration || 4500);
    }

    // --- Confirm pop-up -----------------------------------------------------

    function confirmAction(title, message, confirmLabel) {
        // Older tablets (iOS 15.3 and earlier) have no <dialog>: use the
        // browser's own confirm box there
        if (typeof HTMLDialogElement !== 'function') {
            return Promise.resolve(window.confirm(title + '\n\n' + message));
        }
        return new Promise((resolve) => {
            let dialog = document.getElementById('confirmDialog');
            if (!dialog) {
                dialog = document.createElement('dialog');
                dialog.id = 'confirmDialog';
                dialog.className = 'v-modal';
                dialog.setAttribute('aria-labelledby', 'confirmTitle');
                dialog.innerHTML = '<article><h3 id="confirmTitle"></h3><p id="confirmMessage"></p>'
                    + '<footer><button type="button" class="btn-outline" data-cancel>Cancel</button>'
                    + '<button type="button" class="btn-danger" data-ok></button></footer></article>';
                document.body.appendChild(dialog);
            }
            dialog.querySelector('#confirmTitle').textContent = title;
            dialog.querySelector('#confirmMessage').textContent = message;
            dialog.querySelector('[data-ok]').textContent = confirmLabel;

            const okButton = dialog.querySelector('[data-ok]');
            const cancelButton = dialog.querySelector('[data-cancel]');
            const finish = (answer) => {
                okButton.removeEventListener('click', onOk);
                cancelButton.removeEventListener('click', onCancel);
                dialog.removeEventListener('cancel', onCancel);
                if (dialog.open) dialog.close();
                resolve(answer);
            };
            const onOk = () => finish(true);
            const onCancel = (event) => {
                if (event) event.preventDefault();
                finish(false);
            };
            okButton.addEventListener('click', onOk);
            cancelButton.addEventListener('click', onCancel);
            dialog.addEventListener('cancel', onCancel); // the Esc key
            dialog.showModal();
            cancelButton.focus();
        });
    }

    // Forms that need a yes first (data-confirm), and no double sends for the
    // rest: once a form is on its way, more taps on it do nothing.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-ajax')) return;

        if (form.dataset.confirm && !form.dataset.confirmed) {
            event.preventDefault();
            confirmAction(form.dataset.confirmTitle || 'Are you sure?', form.dataset.confirm, form.dataset.confirmButton || 'OK')
                .then((yes) => {
                    if (!yes) return;
                    form.dataset.confirmed = '1';
                    if (form.requestSubmit) form.requestSubmit(); else form.submit();
                });
            return;
        }

        if (form.dataset.submitting) {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = '1';
        // Disable the buttons only after the browser has read the form
        setTimeout(() => {
            document.querySelectorAll('button').forEach((button) => {
                if (button.form === form) {
                    button.disabled = true;
                    button.setAttribute('aria-busy', 'true');
                }
            });
        }, 0);
    });

    // Back/forward can bring a page back from the cache as it was left: with
    // the menu open or the buttons still disabled
    window.addEventListener('pageshow', () => {
        const navToggle = document.getElementById('nav-toggle');
        if (navToggle) navToggle.checked = false;
        document.querySelectorAll('form[data-submitting], form[data-confirmed]').forEach((form) => {
            delete form.dataset.submitting;
            delete form.dataset.confirmed;
        });
        document.querySelectorAll('button[aria-busy="true"]').forEach((button) => {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        });
    });

    // --- Talking to process.php --------------------------------------------

    async function post(formData) {
        const response = await fetch('process.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        let body = null;
        try {
            body = await response.json();
        } catch (e) {
            body = null;
        }
        return body || { ok: false };
    }

    const offline = "Couldn't reach the visitor log. Check the connection and try again.";

    // --- Kiosk: sign in ------------------------------------------------------

    function setupSignIn(form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (form.dataset.busy) return;
            form.dataset.busy = '1';
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');

            const data = new FormData(form);
            data.append('action', 'sign_in');
            try {
                const result = await post(data);
                if (result.ok) {
                    showToast(result.message, 'success', 6000);
                    form.reset();
                    if (document.activeElement) document.activeElement.blur();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    showToast(result.message || "That didn't save. Try again, or let a staff member know.", 'error', 6000);
                }
            } catch (e) {
                showToast(offline, 'error', 6000);
            } finally {
                delete form.dataset.busy;
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        });
    }

    // --- Kiosk: sign out -----------------------------------------------------

    function setupSignOut(input, results) {
        let timer = null;
        let latest = 0; // only the newest search gets to show its answer

        const note = (text, type) => {
            const p = document.createElement('p');
            p.className = 'sign-out-note' + (type ? ' sign-out-note--' + type : '');
            p.textContent = text;
            return p;
        };

        const clear = () => {
            latest++;
            results.replaceChildren();
        };

        function render(result, term) {
            results.replaceChildren();
            if (!result.ok) {
                results.appendChild(note(result.message || "Couldn't search just now. Try again.", 'error'));
                return;
            }
            if (!result.visitors || result.visitors.length === 0) {
                results.appendChild(note('No one signed in today matches “' + term + '”.'));
                return;
            }
            const list = document.createElement('ul');
            list.className = 'visitor-options';
            result.visitors.forEach((visitor) => {
                const item = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'visitor-option';
                button.dataset.visitorId = visitor.id;
                button.setAttribute('aria-label', 'Sign out ' + visitor.name);

                const text = document.createElement('span');
                text.className = 'visitor-option-text';
                const name = document.createElement('strong');
                name.textContent = visitor.name;
                text.appendChild(name);
                // The company, then the time they signed in, each on its own line
                [visitor.company, visitor.signedIn ? 'Signed in ' + visitor.signedIn : ''].forEach((line) => {
                    if (!line) return;
                    const meta = document.createElement('span');
                    meta.className = 'visitor-option-meta';
                    meta.textContent = line;
                    text.appendChild(meta);
                });

                const action = document.createElement('span');
                action.className = 'visitor-option-action';
                action.innerHTML = icons.logOut + '<span>Sign out</span>';

                button.append(text, action);
                item.appendChild(button);
                list.appendChild(item);
            });
            results.appendChild(list);
        }

        async function search(term) {
            const mine = ++latest;
            const data = new FormData();
            data.append('action', 'search_for_sign_out');
            data.append('searchTerm', term);
            let result;
            try {
                result = await post(data);
            } catch (e) {
                result = { ok: false, message: offline };
            }
            if (mine === latest) render(result, term);
        }

        input.addEventListener('input', () => {
            clearTimeout(timer);
            const term = input.value.trim();
            if (term.length < 3) {
                clear();
                return;
            }
            timer = setTimeout(() => search(term), 200);
        });

        results.addEventListener('click', async (event) => {
            const button = event.target.closest('.visitor-option');
            if (!button || button.disabled) return;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');

            const data = new FormData();
            data.append('action', 'sign_out');
            data.append('visitorId', button.dataset.visitorId);
            let result;
            try {
                result = await post(data);
            } catch (e) {
                result = { ok: false, message: offline };
            }

            if (result.ok) {
                showToast(result.message, 'success', 6000);
                input.value = '';
                input.blur();
                clear();
            } else {
                showToast(result.message || "That didn't save. Try again, or let a staff member know.", 'error', 6000);
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        });
    }

    // --- Start ---------------------------------------------------------------

    document.addEventListener('DOMContentLoaded', () => {
        const themeButton = document.getElementById('theme_switcher');
        if (themeButton) {
            renderThemeButton(themeButton);
            themeButton.addEventListener('click', (event) => {
                event.preventDefault();
                const next = currentTheme() === 'light' ? 'dark' : 'light';
                html.setAttribute('data-theme', next);
                try {
                    localStorage.setItem('visitors-theme', next);
                } catch (e) { /* private browsing: the switch still works for this page */ }
                renderThemeButton(themeButton);
            });
        }

        // Admin checklist: mark an item changed, so its Save button stands out
        document.querySelectorAll('.term-form textarea').forEach((box) => {
            box.addEventListener('input', () => {
                box.form.classList.toggle('is-dirty', box.value !== box.defaultValue);
            });
        });

        const signInForm = document.getElementById('signInForm');
        if (signInForm) setupSignIn(signInForm);

        const signOutInput = document.getElementById('signOutName');
        const signOutResults = document.getElementById('signOutResults');
        if (signOutInput && signOutResults) setupSignOut(signOutInput, signOutResults);
    });

    window.showToast = showToast;
})();
