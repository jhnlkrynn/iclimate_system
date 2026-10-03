/*
 * iClimate global loading overlay.
 *
 * One reusable loader for every role and page. It is loaded (non-deferred) from
 * resources/views/partials/global-loader.blade.php in each page <head>, so it is
 * ready before any page script runs.
 *
 * Automatic coverage:
 *   - same-site link navigation (sidebar, menus, cards, pagination...)
 *   - every form submission that is not cancelled by validation or page JS
 *   - programmatic form.submit() calls
 *   - fetch() and axios requests that start right after a user action
 *     (background polling/timers never show the overlay)
 *   - any other page unload (location.href = ..., reloads)
 *
 * Opt-outs / overrides:
 *   - data-no-loader on a link, form, button or container skips it
 *   - data-loader-text="Saving..." on a link, form or submit button sets the message
 *   - fetch(url, { loader: false }) never shows the overlay
 *   - fetch(url, { loader: 'Sending...' }) always shows it with that message
 *   - axios: same `loader` option in the request config
 *
 * Manual API:
 *   showGlobalLoader(message?)  -> token
 *   hideGlobalLoader(token?)    -> hides that token, or everything when omitted
 *   iClimateLoader.track(promise, message?) -> promise (loader closes when it settles)
 */
(function () {
    'use strict';

    if (window.iClimateLoader) return;

    var REQUEST_DELAY = 150;   // async requests faster than this never show the overlay
    var MIN_VISIBLE = 350;     // once shown, stay long enough not to flash
    var SAFETY_MS = 60000;     // never block the screen forever
    var DOWNLOAD_MS = 2500;    // downloads keep the page, so close on our own
    var GESTURE_WINDOW = 1200; // a request this soon after a user action counts as user-started

    var DOWNLOAD_PATTERN = /\/(export|download)s?(\/|\?|$)|\/storage\/|\.(csv|xlsx?|pdf|zip|docx?)(\?|#|$)/i;
    var ACTIONABLE = 'a[href], button, [role="button"], input[type="submit"], input[type="button"], input[type="checkbox"], input[type="radio"], select, summary';

    var RICE_ICON =
        '<svg class="icl-rice" viewBox="0 0 32 32" focusable="false">' +
            '<path class="icl-rice-leaf" fill="currentColor" d="M16 27.6c-3.7-1.8-6-5.2-6.6-10 3.3 1.5 5.6 4.5 6.8 8.2z"/>' +
            '<path class="icl-rice-leaf" fill="currentColor" opacity=".8" d="M16.3 24.8c2.1-1.7 4.4-2.4 7.1-2.3-1.6 2.1-3.9 3.2-7 3.4z"/>' +
            '<path class="icl-rice-stem" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" d="M16 29.6C16 21 16.8 13 20.4 5.2"/>' +
            '<ellipse class="icl-rice-grain" cx="21" cy="3.6" rx="1.35" ry="2.4" transform="rotate(24 21 3.6)"/>' +
            '<ellipse class="icl-rice-grain" cx="17.9" cy="7.2" rx="1.35" ry="2.4" transform="rotate(-34 17.9 7.2)"/>' +
            '<ellipse class="icl-rice-grain" cx="21.1" cy="8.6" rx="1.35" ry="2.4" transform="rotate(50 21.1 8.6)"/>' +
            '<ellipse class="icl-rice-grain" cx="16.6" cy="11.1" rx="1.35" ry="2.4" transform="rotate(-34 16.6 11.1)"/>' +
            '<ellipse class="icl-rice-grain" cx="19.8" cy="12.5" rx="1.35" ry="2.4" transform="rotate(50 19.8 12.5)"/>' +
            '<ellipse class="icl-rice-grain" cx="15.7" cy="15.2" rx="1.35" ry="2.4" transform="rotate(-34 15.7 15.2)"/>' +
            '<ellipse class="icl-rice-grain" cx="18.8" cy="16.4" rx="1.35" ry="2.4" transform="rotate(48 18.8 16.4)"/>' +
        '</svg>';

    var overlay = null;
    var textEl = null;
    var liveEl = null;
    var tokens = new Map();
    var seq = 0;
    var showTimer = null;
    var showDue = 0;
    var hideTimer = null;
    var visible = false;
    var visibleAt = 0;
    var lockedButtons = [];
    var lastGestureAt = 0;
    var ignoreUnloadUntil = 0;

    function now() {
        return Date.now();
    }

    function ensureElements() {
        if (overlay) return true;
        if (!document.body) return false;

        overlay = document.createElement('div');
        overlay.className = 'icl-overlay';
        overlay.id = 'iclimateLoader';
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML =
            '<div class="icl-box">' +
                '<div class="icl-spinner">' +
                    '<svg class="icl-ring" viewBox="0 0 50 50" focusable="false">' +
                        '<circle class="icl-ring-track" cx="25" cy="25" r="22"/>' +
                        '<circle class="icl-ring-arc" cx="25" cy="25" r="22"/>' +
                    '</svg>' +
                    RICE_ICON +
                '</div>' +
                '<p class="icl-text"></p>' +
            '</div>';
        textEl = overlay.querySelector('.icl-text');

        // Kept in the accessibility tree at all times so screen readers announce changes.
        liveEl = document.createElement('div');
        liveEl.className = 'icl-sr';
        liveEl.setAttribute('role', 'status');
        liveEl.setAttribute('aria-live', 'polite');
        liveEl.setAttribute('aria-atomic', 'true');

        document.body.appendChild(overlay);
        document.body.appendChild(liveEl);

        return true;
    }

    function currentMessage() {
        var message = 'Loading...';
        tokens.forEach(function (entry) { message = entry.message; });
        return message;
    }

    function reveal() {
        showTimer = null;
        if (!tokens.size || !ensureElements()) return;

        var message = currentMessage();
        textEl.textContent = message;
        liveEl.textContent = message;
        overlay.classList.add('is-visible');
        visible = true;
        visibleAt = now();
    }

    function activate(delay) {
        if (!ensureElements()) {
            // Called from <head> before <body> exists: try again once the DOM is ready.
            document.addEventListener('DOMContentLoaded', function () {
                if (tokens.size) activate(delay);
            }, { once: true });
            return;
        }

        if (hideTimer) {
            clearTimeout(hideTimer);
            hideTimer = null;
        }

        overlay.classList.add('is-active');
        document.body.setAttribute('aria-busy', 'true');

        if (visible) {
            var message = currentMessage();
            textEl.textContent = message;
            liveEl.textContent = message;
            return;
        }

        var due = now() + delay;
        if (showTimer && due >= showDue) return;
        if (showTimer) clearTimeout(showTimer);
        showDue = due;
        showTimer = setTimeout(reveal, Math.max(0, delay));
    }

    function deactivate() {
        hideTimer = null;
        if (showTimer) {
            clearTimeout(showTimer);
            showTimer = null;
        }
        visible = false;

        if (overlay) {
            overlay.classList.remove('is-visible', 'is-active');
            liveEl.textContent = '';
        }
        if (document.body) document.body.removeAttribute('aria-busy');

        unlockButtons();
        // If the page never unloaded (a download, a cancelled navigation), undo the
        // "page leaving" dimming app.js applies so the page is usable again.
        if (document.body) document.body.classList.remove('ic-page-leaving');
        document.querySelectorAll('.is-loading-action').forEach(function (element) {
            element.classList.remove('is-loading-action');
        });
        document.querySelectorAll('form[data-icl-submitting]').forEach(function (form) {
            delete form.dataset.iclSubmitting;
        });
    }

    function finish() {
        if (tokens.size) return;

        if (showTimer) {
            clearTimeout(showTimer);
            showTimer = null;
        }

        if (visible) {
            if (hideTimer) clearTimeout(hideTimer);
            hideTimer = setTimeout(deactivate, Math.max(0, MIN_VISIBLE - (now() - visibleAt)));
        } else {
            deactivate();
        }
    }

    function show(message, options) {
        options = options || {};
        var id = ++seq;
        var entry = { message: message || 'Loading...', timer: null };
        var timeout = options.timeout === undefined ? SAFETY_MS : options.timeout;

        if (timeout) {
            entry.timer = setTimeout(function () { hide(id); }, timeout);
        }
        tokens.set(id, entry);
        activate(options.delay || 0);

        return id;
    }

    function hide(id) {
        if (!id || !tokens.has(id)) return;

        clearTimeout(tokens.get(id).timer);
        tokens.delete(id);

        if (tokens.size) {
            if (visible) textEl.textContent = liveEl.textContent = currentMessage();
            return;
        }
        finish();
    }

    function reset() {
        tokens.forEach(function (entry) { clearTimeout(entry.timer); });
        tokens.clear();
        if (hideTimer) clearTimeout(hideTimer);
        deactivate();
    }

    function track(promiseOrFn, message) {
        var id = show(message, { delay: REQUEST_DELAY });
        var promise;

        try {
            promise = Promise.resolve(typeof promiseOrFn === 'function' ? promiseOrFn() : promiseOrFn);
        } catch (error) {
            promise = Promise.reject(error);
        }

        return promise.finally(function () { hide(id); });
    }

    /* ------------------------------------------------------------ messages */

    function pathOf(url) {
        try {
            var parsed = new URL(url, window.location.href);
            // The app lives under /iclimate_system, which must not read as "climate".
            return (parsed.pathname + parsed.search).toLowerCase().replace(/iclimate/g, '');
        } catch (error) {
            return String(url || '').toLowerCase();
        }
    }

    function messageFor(url, method, kind) {
        var path = pathOf(url);
        method = String(method || 'GET').toUpperCase();

        if (/logout/.test(path)) return 'Logging out...';
        if (/login/.test(path) && method === 'POST') return 'Signing in...';
        if (/register/.test(path) && method === 'POST') return 'Creating account...';
        if (method === 'DELETE') return 'Deleting...';
        if (method === 'PUT' || method === 'PATCH') return 'Updating...';

        if (method === 'POST') {
            if (/predict|forecast/.test(path)) return 'Generating Forecast...';
            if (/report/.test(path)) return 'Generating Report...';
            if (/message|chat|send/.test(path)) return 'Sending...';
            if (/upload|import/.test(path)) return 'Uploading...';
            if (/read/.test(path)) return 'Updating...';
            return kind === 'fetch' ? 'Processing...' : 'Saving...';
        }

        if (/export|download/.test(path)) return 'Generating Report...';
        if (/weather/.test(path)) return 'Loading Weather Data...';
        if (/climate/.test(path)) return 'Loading Climate Data...';
        if (/predict|forecast/.test(path)) return 'Loading Predictions...';
        if (/rice|yield|production|harvest/.test(path)) return 'Loading Rice Production Data...';
        if (/report/.test(path)) return 'Loading Reports...';
        if (/notification/.test(path)) return 'Loading Notifications...';

        return 'Loading...';
    }

    function isDownload(url) {
        return DOWNLOAD_PATTERN.test(pathOf(url));
    }

    /* ------------------------------------------------------------ user gestures */

    function noteGesture() {
        lastGestureAt = now();
    }

    function recentGesture() {
        return now() - lastGestureAt <= GESTURE_WINDOW;
    }

    window.addEventListener('click', function (event) {
        var target = event.target;
        if (!event.isTrusted || !target || !target.closest) return;
        if (target.closest('[data-no-loader]')) return;
        if (target.closest(ACTIONABLE)) noteGesture();
    }, true);

    window.addEventListener('change', function (event) {
        var target = event.target;
        if (!event.isTrusted || !target || !target.matches) return;
        if (target.closest('[data-no-loader]')) return;
        if (target.matches('select, input[type="checkbox"], input[type="radio"], input[type="file"], input[type="date"], input[type="month"]')) noteGesture();
    }, true);

    window.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || !event.isTrusted) return;
        var target = event.target;
        if (target && target.tagName === 'TEXTAREA') return;
        if (target && target.closest && target.closest('[data-no-loader]')) return;
        noteGesture();
    }, true);

    // While the overlay is up, keyboard activation of buttons/links is held so the
    // same action cannot be fired twice (mouse clicks are already blocked by the overlay).
    window.addEventListener('keydown', function (event) {
        if (!tokens.size || (event.key !== 'Enter' && event.key !== ' ')) return;
        var target = event.target;
        if (!target || !target.closest || target.tagName === 'TEXTAREA') return;
        if (target.closest('a[href], button, [role="button"], input[type="submit"], input[type="button"]')) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    /* ------------------------------------------------------------ async requests */

    // Returns a token when the overlay should cover this request, otherwise null.
    function beginRequest(url, method, loaderOption) {
        if (loaderOption === false) return null;
        if (loaderOption === undefined || loaderOption === null || loaderOption === true) {
            if (loaderOption !== true && !recentGesture()) return null;
            loaderOption = messageFor(url, method, 'fetch');
        }

        return show(String(loaderOption), { delay: REQUEST_DELAY });
    }

    if (typeof window.fetch === 'function') {
        var nativeFetch = window.fetch;

        window.fetch = function (input, init) {
            var loaderOption;

            if (init && Object.prototype.hasOwnProperty.call(init, 'loader')) {
                loaderOption = init.loader;
                init = Object.assign({}, init);
                delete init.loader;
            }

            var request = nativeFetch.call(this, input, init);
            var url = typeof input === 'string' ? input : (input && input.url) || String(input);
            var method = (init && init.method) || (input && input.method) || 'GET';
            var id = beginRequest(url, method, loaderOption);

            if (id) {
                request.then(function () { hide(id); }, function () { hide(id); });
            }

            return request;
        };
    }

    /* ------------------------------------------------------------ navigation */

    function startNavigation(href, text) {
        if (isDownload(href)) {
            // The browser keeps this page while the file downloads, so close on our own.
            show(text || messageFor(href, 'GET'), { timeout: DOWNLOAD_MS });
            return;
        }

        show(text || messageFor(href, 'GET'));
    }

    // Bubble phase on window runs after page handlers, so cancelled clicks
    // (confirm dialogs, JS-handled links) are skipped through defaultPrevented.
    window.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        var link = event.target && event.target.closest && event.target.closest('a[href]');
        if (!link || typeof link.href !== 'string') return;

        var raw = (link.getAttribute('href') || '').trim();
        var skip = link.closest('[data-no-loader]')
            || link.hasAttribute('download')
            || (link.target && link.target.toLowerCase() !== '_self')
            || link.hasAttribute('data-bs-toggle')
            || !raw
            || raw.charAt(0) === '#'
            || /^(javascript|mailto|tel|sms):/i.test(raw);

        if (skip) {
            ignoreUnloadUntil = now() + 1500;
            return;
        }

        var url;
        try {
            url = new URL(link.href, window.location.href);
        } catch (error) {
            return;
        }

        if (!/^https?:$/.test(url.protocol) || url.origin !== window.location.origin) return;
        if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return;

        startNavigation(url.href, link.getAttribute('data-loader-text'));
    });

    // Anything else that leaves the page (location.href = ..., reloads).
    window.addEventListener('beforeunload', function () {
        if (tokens.size || now() < ignoreUnloadUntil) return;
        show('Loading...', { timeout: 20000 });
    });

    // Coming back with the Back button restores this page from cache: start clean.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) reset();
    });

    /* ------------------------------------------------------------ forms */

    function formMethod(form, submitter) {
        var spoofed = form.querySelector('input[name="_method"]');
        if (spoofed && spoofed.value) return spoofed.value.toUpperCase();

        var method = (submitter && submitter.getAttribute('formmethod')) || form.getAttribute('method') || 'GET';
        return method.toUpperCase();
    }

    function formMessage(form, submitter, action, method) {
        var custom = (submitter && submitter.getAttribute('data-loader-text')) || form.getAttribute('data-loader-text');
        if (custom) return custom;

        if (method === 'GET') {
            var search = form.querySelector('input[type="search"], input[name="q"], input[name="search"], input[name="keyword"]');
            return search && search.value.trim() ? 'Searching...' : messageFor(action, 'GET');
        }

        return messageFor(action, method, 'form');
    }

    function shouldTrackForm(form, submitter) {
        if (form.closest('[data-no-loader]') || (submitter && submitter.closest('[data-no-loader]'))) return false;

        var target = (submitter && submitter.getAttribute('formtarget')) || form.getAttribute('target') || '';
        if (target && target.toLowerCase() !== '_self') return false;

        return (form.getAttribute('method') || '').toLowerCase() !== 'dialog';
    }

    function lockButtons(form) {
        // Runs after the submit event so the clicked button's name/value is still sent.
        setTimeout(function () {
            if (!tokens.size) return;

            Array.prototype.forEach.call(form.elements, function (element) {
                var isSubmit = (element.tagName === 'BUTTON' && (element.getAttribute('type') || 'submit').toLowerCase() === 'submit')
                    || (element.tagName === 'INPUT' && element.type === 'submit');

                if (isSubmit && !element.disabled) {
                    element.disabled = true;
                    lockedButtons.push(element);
                }
            });
        }, 0);
    }

    function unlockButtons() {
        lockedButtons.forEach(function (button) { button.disabled = false; });
        lockedButtons = [];
    }

    function beginFormSubmit(form, submitter) {
        // getAttribute: form.action returns the element when a field is named "action".
        var action = (submitter && submitter.getAttribute('formaction')) || form.getAttribute('action') || window.location.href;
        var method = formMethod(form, submitter);
        var message = formMessage(form, submitter, action, method);

        if (isDownload(action)) {
            show(message, { timeout: DOWNLOAD_MS });
            return;
        }

        form.dataset.iclSubmitting = 'true';
        show(message);
        lockButtons(form);
    }

    // Capture phase: a form that is already being sent is not sent again.
    window.addEventListener('submit', function (event) {
        var form = event.target;
        if (form && form.dataset && form.dataset.iclSubmitting === 'true') {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    // Bubble phase: only forms that passed validation and were not taken over by page JS.
    window.addEventListener('submit', function (event) {
        var form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement)) return;
        noteGesture();
        if (shouldTrackForm(form, event.submitter)) beginFormSubmit(form, event.submitter);
    });

    // form.submit() skips the submit event, so it is covered here.
    var nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        if (this.dataset.iclSubmitting !== 'true' && shouldTrackForm(this, null)) {
            beginFormSubmit(this, null);
        }

        return nativeSubmit.apply(this, arguments);
    };

    /* ------------------------------------------------------------ public API */

    window.iClimateLoader = {
        show: show,
        hide: hide,
        reset: reset,
        track: track,
        beginRequest: beginRequest,
        isActive: function () { return tokens.size > 0; },
    };

    window.showGlobalLoader = function (message, options) {
        return show(message, options);
    };

    window.hideGlobalLoader = function (token) {
        if (token === undefined) {
            reset();
        } else {
            hide(token);
        }
    };
})();
