{{-- Climora launcher: only the round button is part of every page. The assistant itself (components/ai-chat-widget,
     about 40 KB of markup, styles and script) is fetched from route('ai-chat.widget') on the first click, then opened.
     window.iClimateAi is available at once (open / prefill / ask / toggle) and loads the assistant when first used. --}}
<style>
    #icAiLauncher { position: fixed; right: 24px; bottom: 24px; z-index: 2050; }
    #icAiLauncher .ic-ai-toggle { width: 62px; height: 62px; border-radius: 999px; display: grid; place-items: center; background: #fff; border: 2px solid #2d6a4f; box-shadow: 0 18px 34px rgba(13, 31, 24, .28); padding: 0; overflow: hidden; transition: transform 200ms ease, box-shadow 200ms ease; }
    #icAiLauncher .ic-ai-toggle:hover { transform: translateY(-2px); box-shadow: 0 22px 38px rgba(13, 31, 24, .32); }
    #icAiLauncher .ic-ai-toggle img { width: 100%; height: 100%; object-fit: cover; display: block; border-radius: 999px; }
    #icAiLauncher.is-loading .ic-ai-toggle { opacity: .6; cursor: progress; }
    @media (max-width: 575.98px) { #icAiLauncher { right: 8px; bottom: max(8px, env(safe-area-inset-bottom)); } }
    @media (prefers-reduced-motion: reduce) { #icAiLauncher .ic-ai-toggle, #icAiLauncher .ic-ai-toggle:hover { transition: none; transform: none; } }
</style>
<div id="icAiLauncher" data-widget-url="{{ route('ai-chat.widget') }}">
    <button type="button" class="ic-ai-toggle" data-ai-launch aria-label="Open Climora AI">
        <img src="{{ asset('images/climora-ai-icon.png') }}" alt="Climora AI">
    </button>
</div>
<script>
    (() => {
        const launcher = document.getElementById('icAiLauncher');
        if (!launcher || window.iClimateAi) return;
        const button = launcher.querySelector('[data-ai-launch]');
        let loading = null;

        // Adds the fetched widget to the page in order; scripts are re-created so the browser runs them.
        const insert = (html) => {
            const box = document.createElement('div');
            box.innerHTML = html;
            [...box.childNodes].forEach((node) => {
                if (node.nodeName !== 'SCRIPT') return document.body.appendChild(node);
                const script = document.createElement('script');
                if (node.type) script.type = node.type;
                if (node.id) script.id = node.id;
                script.textContent = node.textContent;
                document.body.appendChild(script);
            });
        };

        const load = () => loading ??= fetch(launcher.dataset.widgetUrl, { headers: { Accept: 'text/html' }, credentials: 'same-origin', loader: false })
            .then((response) => {
                if (!response.ok) throw new Error('Climora ' + response.status);
                return response.text();
            })
            .then((html) => {
                insert(html);
                launcher.remove();
                return window.iClimateAi;
            })
            .catch((error) => {
                loading = null;
                launcher.classList.remove('is-loading');
                button.title = 'Climora AI is not available right now. Please try again.';
                throw error;
            });

        const use = (action) => {
            launcher.classList.add('is-loading');
            return load().then((ai) => action(ai)).catch(() => {});
        };

        window.iClimateAi = {
            open: () => use((ai) => ai.open()),
            close: () => {},
            toggle: () => use((ai) => ai.open()),
            prefill: (question) => use((ai) => ai.prefill(question)),
            ask: (question) => use((ai) => ai.ask(question)),
        };

        button.addEventListener('click', (event) => {
            event.preventDefault();
            window.iClimateAi.open();
        });

        // Other "Ask Climora" buttons on the page ([data-ai-open]) open it too until the widget, which handles them itself, is loaded.
        document.addEventListener('click', (event) => {
            if (!launcher.isConnected || !event.target.closest('[data-ai-open]')) return;
            event.preventDefault();
            window.iClimateAi.open();
        });
    })();
</script>
